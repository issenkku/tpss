import { expect, test } from '@playwright/test';
import { login } from './support/auth';

/**
 * M11 — Approval Workflow (course head + executive + notification bell)
 *
 * Smoke หน้าจอหลัก (deterministic ไม่ผูกข้อมูล seed):
 *  - login course head → เปิดหน้ารายวิชา → เห็น approval panel + ปุ่มส่งขออนุมัติ
 *  - login executive → landing = คิวอนุมัติ (ไม่ใช่ coming-soon) + การ์ดเป็นไทย
 *  - เมนู "ตีกลับ / แก้ไข" เปิดหน้าได้ และ active state ไม่ซ้อนกับคิวอนุมัติ
 *  - กระดิ่งแจ้งเตือนใน topbar เปิด dropdown ได้
 *
 * ส่วน logic submit/approve/reject + audit ครอบใน tests/Feature/M11ApprovalTest.php
 */
test.describe('M11 — Approval workflow', () => {
  test('course head sees the approval panel and submit action on a draft offering', async ({ page }) => {
    await login(page, 'head_med');

    await page.goto('/maker/course-offerings');
    await expect(page.getByTestId('offering-summary')).toBeVisible();
    await page.getByTestId('course-offering-show-link').first().click();

    await expect(page.getByTestId('offering-approval-bar')).toBeVisible();
    await expect(page.getByText('พร้อมส่งให้ผู้บริหารพิจารณา').first()).toBeVisible();
    await expect(page.getByTestId('offering-submit-button')).toBeVisible();
  });

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
    await expect(page.getByTestId('nav-approver-rejected')).toHaveClass(/(^|\s)on(\s|$)/);
    await expect(page.getByTestId('nav-approver-queue')).not.toHaveClass(/(^|\s)on(\s|$)/);
  });

  test('notification bell opens its dropdown', async ({ page }) => {
    await login(page, 'exec_01');

    const bell = page.getByTestId('notif-bell');
    await expect(bell).toBeVisible();
    await bell.click();
    await expect(page.getByTestId('notif-dropdown')).toBeVisible();
  });
});
