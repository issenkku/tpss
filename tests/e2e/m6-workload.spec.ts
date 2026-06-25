import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M6 — Workload report (admin)
 *
 * Smoke หน้าจอ (deterministic ไม่ผูกข้อมูล seed):
 *  - เมนู "รายงานภาระงาน" เปิดใช้งานแล้ว (เลิก กำลังพัฒนา) → คลิกแล้วเข้าหน้ารายงาน
 *  - หน้ารายงานมีการ์ดสรุป + breakdown ระดับหลักสูตร + ตารางรายอาจารย์ + ปุ่ม export
 *  - ปุ่ม export ดาวน์โหลดไฟล์ CSV ได้
 *
 * ส่วน logic คำนวณชั่วโมง/accrual/by-category/by-level + CSV+BOM ครอบใน
 * tests/Unit/WorkloadCalculatorTest.php + tests/Feature/Schedule/M6WorkloadDashboardTest.php
 */
test.describe('M6 — Workload report', () => {
  test('admin opens the workload report from the sidebar', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'sidebar ซ่อนหลัง hamburger บน mobile');
    await login(page); // admin_01

    const nav = page.getByTestId('sidebar-workload-report');
    await expect(nav).toBeVisible();
    await Promise.all([
      page.waitForURL(/\/admin\/reports\/workload/),
      nav.click(),
    ]);

    await expect(page.getByText('รายงานภาระงานสอน').first()).toBeVisible();
    await expect(page.getByTestId('workload-summary')).toBeVisible();
    await expect(page.getByTestId('workload-by-level')).toBeVisible();
    await expect(page.getByTestId('workload-export-csv')).toBeVisible();
    await expect(nav).toHaveClass(/(^|\s)on(\s|$)/);
  });

  test('export button downloads a CSV file', async ({ page }) => {
    await login(page);
    await page.goto('/admin/reports/workload');

    const downloadPromise = page.waitForEvent('download');
    await page.getByTestId('workload-export-csv').click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toContain('workload-report');
    expect(download.suggestedFilename()).toMatch(/\.csv$/);
  });
});
