import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M6 — Workload report (admin)
 *
 * Smoke หน้าจอ (deterministic ไม่ผูกข้อมูล seed):
 *  - เมนู "รายงานภาระงาน" เปิดใช้งานแล้ว (เลิก กำลังพัฒนา) → คลิกแล้วเข้าหน้ารายงาน
 *  - หน้ารายงานมีการ์ดสรุป + breakdown ระดับหลักสูตร + ตารางรายอาจารย์ + ปุ่ม export
 *  - ปุ่ม export ดาวน์โหลดไฟล์ XLSX ได้
 *
 * ส่วน logic คำนวณชั่วโมง/accrual/by-category/by-level + XLSX ครอบใน
 * tests/Unit/WorkloadCalculatorTest.php + tests/Feature/Schedule/M6WorkloadDashboardTest.php
 */
test.describe('M6 — Workload report', () => {
  // กัน cold-start: test แรกอาจยิงก่อน php artisan serve พร้อม (รัน spec เดี่ยวหลัง migrate:fresh)
  test.describe.configure({ retries: 2 });

  test('workload report page renders and its menu is enabled + active', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'sidebar ซ่อนหลัง hamburger บน mobile');
    await login(page); // admin_01
    await page.goto('/admin/reports/workload');

    // หน้ารายงาน render ครบ
    await expect(page.getByText('รายงานภาระงานสอน').first()).toBeVisible();
    const summary = page.getByTestId('workload-summary');
    const emptyState = page.getByTestId('workload-empty-state');
    await expect(summary.or(emptyState)).toBeVisible();
    if (await summary.isVisible()) {
      await expect(page.getByTestId('workload-by-level')).toBeVisible();
    }
    await expect(page.getByTestId('workload-export-xlsx')).toBeVisible();

    // เมนู sidebar เปิดใช้งานแล้ว (เป็นลิงก์จริง ไม่ใช่ "กำลังพัฒนา") + active state
    const nav = page.getByTestId('sidebar-workload-report');
    await expect(nav).toHaveCount(1);
    await expect(nav).toHaveAttribute('href', /\/admin\/reports\/workload/);
    await expect(nav).toHaveClass(/(^|\s)on(\s|$)/);
  });

  test('export button downloads an XLSX file', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/workload');

    const downloadPromise = page.waitForEvent('download');
    await page.getByTestId('workload-export-xlsx').click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toContain('workload-report');
    expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
  });

  test('filter select arrow does not repeat across the control on hover', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/workload');

    const academicYear = page.locator('#workload-academic-year');
    await academicYear.hover();

    const backgroundRepeat = await academicYear.evaluate(
      (select) => getComputedStyle(select).backgroundRepeat,
    );

    expect(backgroundRepeat.split(',').map((value) => value.trim())).toEqual([
      'no-repeat',
      'no-repeat',
    ]);
  });

  test('year and term filters update without a full page reload', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/workload');

    await expect(page.getByRole('button', { name: 'แสดงผล' })).toHaveCount(0);

    const term = page.getByLabel('ภาคเรียน');
    const termOptions = term.locator('option');
    test.skip(await termOptions.count() < 2, 'No selectable academic term is available.');

    await page.evaluate(() => document.body.dataset.asyncFilterShell = 'stable');
    await term.selectOption({ index: 1 });

    await expect(page.locator('#tpss-filter-status')).toHaveText('กรองข้อมูลเรียบร้อยแล้ว');
    await expect(page).toHaveURL(/term_sequence=\d+/);
    await expect(page.getByTestId('workload-export-xlsx')).toHaveAttribute('href', /term_sequence=\d+/);
    expect(await page.evaluate(() => document.body.dataset.asyncFilterShell)).toBe('stable');
    await expect(page.getByRole('button', { name: 'แสดงผล' })).toHaveCount(0);
  });

  test('an instructor row expands to show workload by course', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/workload');

    const toggle = page.getByTestId('workload-course-details-toggle').first();
    test.skip(await toggle.count() === 0, 'No approved workload course is available.');

    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();

    const details = page.getByTestId('workload-course-details').first();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(details).toBeVisible();
    await expect(details.getByText('รายละเอียดภาระงานแยกรายวิชา')).toBeVisible();
    await expect(details.getByTestId('workload-course-role-summary')).toBeVisible();
    await expect(details.getByRole('columnheader', { name: 'รายวิชา' })).toBeVisible();
    await expect(details.getByRole('columnheader', { name: 'บทบาทรายวิชา' })).toBeVisible();
    await expect(details.getByRole('columnheader', { name: 'หน้าที่ในคาบ' })).toBeVisible();
    await expect(details.getByRole('columnheader', { name: 'รวม' })).toBeVisible();
    await expect(details.getByRole('columnheader', { name: 'เฉลี่ย/สัปดาห์' })).toBeVisible();

    const roleFilter = details.getByTestId('workload-course-role-filter').first();
    const selectedRole = (await roleFilter.locator('.workload-role-summary-name').textContent())?.trim() ?? '';
    await roleFilter.click();
    await expect(roleFilter).toHaveAttribute('aria-pressed', 'true');
    const visibleRoles = await details.locator('.workload-course-role').allTextContents();
    expect(visibleRoles).toEqual(expect.arrayContaining([selectedRole]));
    expect(visibleRoles.every((role) => role.trim() === selectedRole)).toBe(true);

    await roleFilter.click();
    await expect(roleFilter).toHaveAttribute('aria-pressed', 'false');

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(details).toBeHidden();
  });
});
