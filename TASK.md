# TASK.md

## Last Updated
<!-- Update this date at the start or end of each AI-assisted session. -->
2026-06-24

## Current Sprint
<!-- Keep this to the active sprint/module only; do not record project history here. -->
Phase 2 — `Sprint 2 — M6 Workload from Real Schedules` (dev เริ่ม ศ.26 มิ.ย. 2569 · ขณะนี้ = วางแผน scope)

## Current Focus
<!-- Name the exact feature/page/file being worked on now, not a broad module. -->
วางแผน M6 — คำนวณภาระงานอาจารย์จาก `schedules` จริง (แทน quota คงที่ใน `DashboardController::instructorWorkloadData`); ยังไม่เริ่มเขียนโค้ด รอเคาะสูตรนับชั่วโมง/ขอบเขตผ่าน grilling

## Allowed Directories
<!-- List only paths the AI may modify for the current session. Tighten this before coding. -->
- (planning only — ยังไม่เปิดสิทธิ์แก้โค้ดจน scope M6 เคาะเสร็จ)
- เอกสาร: `TASK.md`, `.claude/rules/`

## Scope Boundary
<!-- Treat these as hard constraints. Move permanent rules into MEMORY.md. -->
- ขณะนี้ทำได้เฉพาะ: อัปเดตเอกสาร (codebase + ClickUp) + บันทึกผลทดสอบ + วางแผน M6.
- ห้ามเริ่มเขียนโค้ด M6 จนกว่าจะเคาะสูตรนับชั่วโมง + ขอบเขต group-by + แหล่งข้อมูล (published vs all).
- Do not change Laravel, Blade, Alpine.js, MySQL, or RBAC architecture; no React/Vue/Inertia/SPA.
- ผู้บริหาร (`executive`) + admin = read-only สำหรับตาราง; admin อ่าน/export รายงานภาระงานได้ แต่ไม่แก้ตาราง.

## Active Tasks
<!-- Keep at most 7 one-line tasks; replace this list each session. -->
- [x] ยืนยันจาก ClickUp ว่า Sprint 1 — M11 = completed (M11-01…06 Closed).
- [ ] อัปเดตเอกสาร codebase ให้สะท้อน M11 เสร็จ + M6 เป็นงานถัดไป (sprint-status.md, TASK.md).
- [ ] อัปเดต ClickUp ให้เป็นปัจจุบัน (Sprint 2 → active) + บันทึกผลทดสอบ M11 ลง WP-07.
- [ ] เคาะสูตรแปลง slot → ชั่วโมงภาระงาน (block date-range, instructor split, วันหยุด/สอบ).
- [ ] เคาะแหล่งข้อมูล: นับเฉพาะ published/approved หรือทุกสถานะ.
- [ ] เคาะมิติ group-by: คน / วิชา / ระดับหลักสูตร / ประเภทกิจกรรม.
- [ ] เคาะ activity ที่ไม่นับ (`counts_toward_workload=false`) + `counts_service_only` curriculum.

## Definition of Done
<!-- Make completion measurable for the current session only. -->
- เอกสาร codebase + ClickUp สะท้อนสถานะปัจจุบัน (M11 done, M6 active) ครบ.
- ผลทดสอบ M11 ถูกบันทึกลงถังกลาง WP-07 (ถ้าตกลงให้ทำ).
- สูตร M6 + ขอบเขตถูกเคาะเป็นลายลักษณ์อักษรก่อนเปิดสิทธิ์เขียนโค้ด.

## Blocked / Waiting
<!-- Record unanswered questions or external blockers; clear this when resolved. -->
- รอเคาะ: สูตรนับชั่วโมง block slot, instructor split, แหล่งข้อมูล (published vs all), group-by dimensions.

## Completed (Previous Sprint — M11, closed 23 มิ.ย.)
<!-- Add completed items only; keep this short and prune when sprint changes. -->
- Sprint 1 — M11 Approval Workflow: submit/resubmit, lock pending/published, executive approve/reject + reason, audit + `course_offering_approvals` + notification.
- M11 PHPUnit (`M11ApprovalTest`) + Playwright (`m11-approval.spec.ts`) เขียว.

## Next Up
<!-- Add only 2-3 backlog items; do not write implementation plans here. -->
- `Sprint 3: Reporting module` (M9 PDF/Excel).
- `Sprint 4: Smart warnings` (M5).
