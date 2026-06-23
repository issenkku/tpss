import { expect, test } from '@playwright/test';
import { login, switchRole } from './support/auth';

/**
 * M10 — Login, RBAC & Role Switcher
 *
 * ครอบคลุม:
 *  - แต่ละ role login แล้ว landing ถูกหน้า (admin/staff/course_head/executive/instructor)
 *  - Access control: role ที่ไม่มีสิทธิ์เข้าหน้าของ role อื่นไม่ได้ (403/redirect)
 *  - Role switcher: ผู้ใช้หลาย role สลับได้ และ active_role เปลี่ยนตาม
 *
 * บัญชี seed (password = "password"):
 *   admin_01 (admin) · staff_01 (staff) · head_med/head_psy (course_head + instructor)
 *   exec_01 (executive) · instructor_01 (instructor)
 */

test.describe('M10 — RBAC landing per role', () => {
  test('admin lands on the admin dashboard', async ({ page }) => {
    await login(page, 'admin_01');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
  });

  test('course head lands on a maker page', async ({ page }) => {
    await login(page, 'head_med');
    await expect(page).toHaveURL(/\/maker\//);
  });

  test('executive lands on the read-only landing page', async ({ page }) => {
    // M11: executive ถูก map ไป approver.dashboard (คิวอนุมัติ) ใน DashboardController::index()
    await login(page, 'exec_01');
    await expect(page).toHaveURL(/\/dashboard\/coming-soon|\/approver\/dashboard/);
  });

  test('instructor (no schedulable offering) lands on the lecturer dashboard', async ({ page }) => {
    await login(page, 'instructor_01');
    await expect(page).toHaveURL(/\/lecturer\/dashboard/);
  });

});

test.describe('M10 — RBAC access control', () => {
  test('instructor cannot reach the admin user-management page', async ({ page }) => {
    await login(page, 'instructor_01');
    const response = await page.goto('/admin/users');
    expect(response?.status()).not.toBe(200);
  });

  test('instructor cannot reach the admin settings page', async ({ page }) => {
    await login(page, 'instructor_01');
    const response = await page.goto('/admin/settings');
    expect(response?.status()).not.toBe(200);
  });

  test('executive (read-only) cannot reach the admin master-data page', async ({ page }) => {
    await login(page, 'exec_01');
    const response = await page.goto('/admin/master-data');
    expect(response?.status()).not.toBe(200);
  });

  test('staff cannot reach the admin user-management page', async ({ page }) => {
    await login(page, 'staff_01');
    const response = await page.goto('/admin/users');
    expect(response?.status()).not.toBe(200);
  });
});

test.describe('M10 — Role switcher (multi-role user)', () => {
  test('head_med can switch active role and the instructor-only page follows the switch', async ({ page }) => {
    await login(page, 'head_med');
    await expect(page).toHaveURL(/\/maker\//);

    // ขณะ active=course_head: หน้า lecturer (instructor-only) ต้องถูก gate ออก
    await page.goto('/lecturer/dashboard');
    await expect(page).not.toHaveURL(/\/lecturer\/dashboard/);

    // สลับเป็น instructor (head_med ช่วยจัดตารางได้ → landing เป็น maker ก็ยอมรับ)
    await switchRole(page, 'instructor');

    // ขณะ active=instructor: หน้า lecturer เข้าถึงได้แล้ว
    await page.goto('/lecturer/dashboard');
    await expect(page).toHaveURL(/\/lecturer\/dashboard/);

    // สลับกลับเป็น course_head แล้วหน้า lecturer ถูก gate อีกครั้ง
    await switchRole(page, 'course_head');
    await page.goto('/lecturer/dashboard');
    await expect(page).not.toHaveURL(/\/lecturer\/dashboard/);
  });
});
