import { expect, type Page } from '@playwright/test';

export async function login(page: Page, username = 'admin_01', password = 'password') {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.getByTestId('login-username').fill(username);
  await page.getByTestId('login-password').fill(password);
  await page.getByTestId('login-submit').click();
  // รับ landing ของทุก role (staff ลงที่ /staff/settings · ไม่มี dashboard)
  await expect(page).toHaveURL(/\/dashboard|\/admin\/dashboard|\/staff\/dashboard|\/staff\/settings|\/maker\/dashboard|\/maker\/schedules|\/maker\/course-offerings\/\d+\/schedules|\/lecturer\/dashboard|\/approver\/dashboard|\/dashboard\/coming-soon/);
}

export async function switchRole(page: Page, role: 'admin' | 'staff' | 'course_head' | 'instructor' | 'executive') {
  await page
    .locator(`form:has(input[name="role"][value="${role}"])`)
    .evaluate((form) => (form as HTMLFormElement).submit());
  await expect(page).toHaveURL(/\/dashboard|\/admin\/dashboard|\/staff\/settings|\/maker\/schedules|\/maker\/course-offerings\/\d+\/schedules|\/lecturer\/dashboard|\/approver\/dashboard|\/dashboard\/coming-soon/);
}
