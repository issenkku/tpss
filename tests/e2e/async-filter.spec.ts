import { expect, test, type Page } from '@playwright/test';
import { login } from './support/auth';

async function markStableShell(page: Page) {
  await page.evaluate(() => document.body.dataset.asyncFilterShell = 'stable');
}

async function expectStableShell(page: Page) {
  await expect(page.locator('#tpss-filter-status')).toHaveText('กรองข้อมูลเรียบร้อยแล้ว');
  expect(await page.evaluate(() => document.body.dataset.asyncFilterShell)).toBe('stable');
}

test.describe('server-backed filters', () => {
  test('course offering year filter keeps the application shell mounted', async ({ page }) => {
    await login(page, 'head_med');
    await page.goto('/maker/course-offerings');

    const filter = page.getByTestId('offering-year-filter');
    const options = filter.locator('option');

    const currentValue = await filter.inputValue();
    const nextValue = await options.evaluateAll((items, selected) =>
      items.map((item) => (item as HTMLOptionElement).value).find((value) => value !== selected), currentValue);

    await markStableShell(page);
    await filter.selectOption(nextValue ?? currentValue);
    if (!nextValue) await filter.dispatchEvent('change');
    await expectStableShell(page);
    await expect(page).toHaveURL(new RegExp(`year=${nextValue ?? currentValue}`));
  });

  test('alert year filter keeps the application shell mounted', async ({ page }) => {
    await login(page, 'head_med');
    await page.goto('/maker/alerts');

    const filter = page.locator('#alert-year-sel');
    await markStableShell(page);

    if (await filter.count()) {
      const options = filter.locator('option');
      const currentValue = await filter.inputValue();
      const nextValue = await options.evaluateAll((items, selected) =>
        items.map((item) => (item as HTMLOptionElement).value).find((value) => value !== selected), currentValue);
      await filter.selectOption(nextValue ?? currentValue);
      if (!nextValue) await filter.dispatchEvent('change');
    } else {
      await page.evaluate(() => (window as typeof window & {
        tpssAsyncFilter: { navigate: (url: string, options: { scope: string }) => Promise<void> };
      }).tpssAsyncFilter.navigate(window.location.href, { scope: 'schedule-alerts' }));
    }

    await expectStableShell(page);
    await expect(page.locator('[data-async-filter-scope="schedule-alerts"]')).toBeVisible();
  });

  test('schedule date navigation keeps the application shell mounted', async ({ page }) => {
    await login(page, 'head_med');
    await page.goto('/maker/schedules');

    const next = page.getByTestId('schedule-nav-next');
    await expect(next).toHaveCount(1);
    const previousUrl = page.url();

    await markStableShell(page);
    await next.dispatchEvent('click');
    await expectStableShell(page);
    expect(page.url()).not.toBe(previousUrl);
    await expect(page.locator('[data-async-filter-scope="teaching-schedule"]')).toBeVisible();
  });
});
