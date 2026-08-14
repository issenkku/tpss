# TASK.md

## Last Updated
2026-08-14

## Current Sprint
Phase 2 — `Sprint 3 — M9 Reporting`

## Current Focus
ปิด Sprint 3 M9 ในฝั่งโค้ดและหลักฐานทดสอบ; รอ sync สถานะ ClickUp เมื่อ API พ้น rate limit

## Allowed Directories
- `app/Http/Controllers/Admin/`
- `app/Services/`
- `resources/views/admin/reports/`
- `resources/views/components/`
- `routes/`
- `tests/Feature/Schedule/`
- `tests/e2e/`
- `.claude/rules/`
- `composer.json`, `composer.lock`, `TASK.md`, `.gitignore`

## Scope Boundary
- ใช้เฉพาะตาราง `approved` ของรายวิชาที่ `published` เป็นข้อมูลรายงาน
- admin, staff และ executive ดูและส่งออกรายงานได้แบบ read-only; instructor ไม่มีสิทธิ์เข้าหน้านี้
- ใช้ Laravel, Blade และ async filter component เดิม; ไม่เพิ่ม React/Vue/Inertia/SPA
- PDF ต้องรองรับภาษาไทย และ Excel ต้องเป็นไฟล์ `.xlsx` จริง
- ไม่แก้ข้อมูลตารางสอนจากหน้ารายงาน

## Active Tasks
- [x] สร้างหน้ารายงานตารางสอนที่เผยแพร่แล้วสำหรับ admin/staff/executive
- [x] เพิ่มตัวกรองปี ภาคเรียน หลักสูตร รายวิชา กลุ่ม อาจารย์ และห้องแบบไม่รีเฟรชทั้งหน้า
- [x] ส่งออก PDF ภาษาไทยและ Excel ตามตัวกรอง
- [x] เพิ่ม PHPUnit และ Playwright ครอบคลุมหน้า ตัวกรอง สิทธิ์ และไฟล์ส่งออก
- [x] Task 26 (`M9-03, M9-04, M9-05`) — Room Utilization, Department Summary และชุด export สรุป
- [x] รันทดสอบ M9 รวม (Feature 24/24 + JS 6/6 + E2E 3/3 + build ผ่าน)
- [x] บันทึก WP-07 Feature `TR-202608140444-M9-FEATURE` (4/4)
- [x] บันทึก WP-07 E2E `TR-202608140445-M9-E2E` (3/3)
- [ ] อัปเดตสถานะ Sprint 3 ใน ClickUp เมื่อ API พ้น rate limit

## Definition of Done
- ตัวกรองรายอาจารย์และรายห้องแสดงเฉพาะตารางที่ตรงเงื่อนไขและคง query ในลิงก์ส่งออก
- PDF/Excel เปิดได้จริงและใช้ข้อมูลเดียวกับหน้ารายงาน
- Feature, E2E, regression และ build ผ่าน
- commit แต่ละชุดใช้ข้อความภาษาไทยและไม่รวมไฟล์ cache/generated

## Blocked / Waiting
- ClickUp API ติด rate limit ชั่วคราว; รอครบเวลาที่ระบบแจ้งก่อนปิดสถานะ Sprint 3 ใน ClickUp

## Completed
- Sprint 2 — M6 Workload from Real Schedules ถูก merge เข้า `dev` แล้วที่ `6b660d1`

## Next Up
- Task 26 — Room Utilization และ Department Summary
- Sprint 4 — M5 Smart warnings
