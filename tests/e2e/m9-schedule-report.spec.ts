import { expect, test } from '@playwright/test';
import { login } from './support/auth';

test.describe('M9 — Schedule reporting', () => {
  test.describe.configure({ retries: 2 });

  test('published schedule report renders from the enabled sidebar menu', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'sidebar ซ่อนหลัง hamburger บน mobile');
    await login(page);
    await page.goto('/admin/reports/schedules');

    await expect(page.getByRole('heading', { name: 'รายงานตารางสอน', exact: true }).first()).toBeVisible();
    await expect(page.locator('[data-async-filter-scope="schedule-report"]')).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'ปีการศึกษา', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'ภาคเรียน', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'หลักสูตร', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'รายวิชา', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'กลุ่มนักศึกษา', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'อาจารย์ผู้สอน', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'ห้อง / สถานที่', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'สถิติการใช้ห้องและภาควิชา', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'การใช้ห้อง', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'สรุปตามภาควิชา', exact: true })).toBeVisible();
    await expect(page.getByTestId('schedule-report-export-pdf')).toBeVisible();
    await expect(page.getByTestId('schedule-report-export-excel')).toBeVisible();

    const nav = page.getByTestId('sidebar-admin-schedule-report');
    await expect(nav).toHaveAttribute('href', /\/admin\/reports\/schedules/);
    await expect(nav).toHaveClass(/(^|\s)on(\s|$)/);
  });

  test('report filters update without reloading the application shell', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/schedules');

    await expect(page.getByRole('button', { name: 'แสดงผล' })).toHaveCount(0);
    const term = page.getByRole('combobox', { name: 'ภาคเรียน', exact: true });
    test.skip(await term.locator('option').count() < 2, 'No selectable academic term is available.');

    await page.evaluate(() => document.body.dataset.asyncFilterShell = 'stable');
    await term.selectOption({ index: 1 });

    await expect(page.locator('#tpss-filter-status')).toHaveText('กรองข้อมูลเรียบร้อยแล้ว');
    await expect(page).toHaveURL(/term_sequence=\d+/);
    await expect(page.getByTestId('schedule-report-export-pdf')).toHaveAttribute('href', /term_sequence=\d+/);
    await expect(page.getByTestId('schedule-report-export-excel')).toHaveAttribute('href', /term_sequence=\d+/);
    expect(await page.evaluate(() => document.body.dataset.asyncFilterShell)).toBe('stable');
  });

  test('PDF and Excel actions download real report files', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/schedules');

    const excelDownload = page.waitForEvent('download');
    await page.getByTestId('schedule-report-export-excel').click();
    const excel = await excelDownload;
    expect(excel.suggestedFilename()).toMatch(/schedule-report-.*\.xlsx$/);
    await excel.path();

    const pdfDownload = page.waitForEvent('download');
    await page.getByTestId('schedule-report-export-pdf').click();
    const pdf = await pdfDownload;
    expect(pdf.suggestedFilename()).toMatch(/schedule-report-.*\.pdf$/);
    await pdf.path();
  });
});
