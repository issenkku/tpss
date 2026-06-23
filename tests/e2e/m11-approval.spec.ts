import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M11 — Approval Workflow (executive side + notification bell)
 *
 * Smoke ฝั่งผู้บริหาร (deterministic ไม่ผูกข้อมูล seed):
 *  - login executive → landing = คิวอนุมัติ (ไม่ใช่ coming-soon) + การ์ดเป็นไทย
 *  - เมนู "ตีกลับ / แก้ไข" เปิดหน้าได้
 *  - กระดิ่งแจ้งเตือนใน topbar เปิด dropdown ได้
 *
 * ส่วน logic submit/approve/reject + audit ครอบใน tests/Feature/M11ApprovalTest.php
 */
test.describe('M11 — Approval (executive)', () => {
  test('executive lands on the approval queue with Thai summary cards', async ({ page }) => {
    await login(page, 'exec_01');

    await expect(page).toHaveURL(/\/approver\/dashboard/);
    await expect(page.getByText('คิวอนุมัติ').first()).toBeVisible();
    // การ์ดสรุปการชน = ภาษาไทย (ไม่ใช่ "Schedule conflict summary")
    await expect(page.getByTestId('dashboard-conflict-summary')).toContainText('สรุปการชนของตารางสอน');
    // การ์ดสถานะรายวิชา (pipeline) แสดงให้ผู้บริหารเห็น
    await expect(page.getByTestId('offering-pipeline')).toBeVisible();
  });

  test('executive can open the rejected list from the sidebar', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-chrome', 'sidebar ซ่อนหลัง hamburger บน mobile');
    await login(page, 'exec_01');

    await page.getByTestId('nav-approver-rejected').click();
    await expect(page).toHaveURL(/\/approver\/offerings\/rejected/);
    await expect(page.getByText('ตีกลับ / แก้ไข').first()).toBeVisible();
  });

  test('notification bell opens its dropdown', async ({ page }) => {
    await login(page, 'exec_01');

    const bell = page.getByTestId('notif-bell');
    await expect(bell).toBeVisible();
    await bell.click();
    await expect(page.getByTestId('notif-dropdown')).toBeVisible();
  });
});
