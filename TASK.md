# TASK.md

## Last Updated
<!-- Update this date at the start or end of each AI-assisted session. -->
2026-06-23

## Current Sprint
<!-- Keep this to the active sprint/module only; do not record project history here. -->
Phase 2 — `Sprint 1 — M11 Approval Workflow` (22–25 มิ.ย. 2569)

## Current Focus
<!-- Name the exact feature/page/file being worked on now, not a broad module. -->
ClickUp `Sprint 1 — M11 Approval Workflow` — ส่งขออนุมัติ, ผู้บริหาร approve/reject, ล็อกตาราง, audit/notification

## Allowed Directories
<!-- List only paths the AI may modify for the current session. Tighten this before coding. -->
- `app/Http/Controllers/`
- `app/Models/`
- `resources/views/course_head/`
- `resources/views/executive/`
- `resources/views/components/`
- `routes/web.php`
- `tests/Feature/`
- `tests/e2e/`

## Scope Boundary
<!-- Treat these as hard constraints. Move permanent rules into MEMORY.md. -->
- Do not modify migrations or seeders unless approval workflow cannot be completed without a schema fix.
- Do not change Laravel, Blade, Alpine.js, MySQL, or RBAC architecture.
- Do not add React, Vue, Inertia, or a frontend SPA architecture.
- ผู้บริหาร (`executive`) = read-only + Approve/Reject เท่านั้น; ห้าม implement ปุ่ม edit หรือ flow แก้ตารางให้ executive.
- Maintain audit trail, `course_offering_approvals`, and notification behavior for every approval action.
- Any UI/flow change must update matching Feature/E2E tests in the same work item.

## M11 Page / Role Scope
<!-- Working scope for which pages should be opened in Approval Workflow. -->
- Course Head: open `maker.course_offerings.index`, `maker.course_offerings.show`, offering schedule pages, `maker.schedules.index`, and `maker.alerts`; this role prepares schedules, sees rejection reasons, submits/resubmits, and must see locks after submit.
- Executive: open `approver.dashboard`, `approver.offerings.show`, `approver.offerings.rejected`, and notifications; this role reviews read-only and can only approve/reject with a reason.
- Admin: open monitoring only through `admin.dashboard`, `admin.audit_logs.index`, and `admin.alerts`; admin must not submit, approve, reject, or edit submitted schedules.
- Staff: no direct approval action; may access schedule/support pages only through existing delegated scheduling permissions.
- Instructor: no direct approval action; may access lecturer pages or delegated schedule help only, never submit/approve/reject.
- Decision needed: whether to open a global Course Head approval history page (`ประวัติส่งอนุมัติ`) in M11 or keep per-offering history only for this sprint.
- Resolved UX follow-up: executive sidebar active states are split so approval queue and rejected queue do not select together.

## Active Tasks
<!-- Keep at most 7 one-line tasks; replace this list each session. -->
- [x] Verify submit flow: `draft/rejected → pending`, schedule exists, scheduling phase required, executives notified.
- [x] Verify lock behavior: pending/published offerings cannot edit course info, instructor pool, student groups, or schedules.
- [x] Verify executive review flow: pending queue, read-only detail, approve → published, reject → rejected with reason.
- [x] Verify resubmission flow: rejected offering shows reason, course head can fix and submit again.
- [x] Verify audit trail + `course_offering_approvals` + notifications for submit/approve/reject.
- [x] Run focused PHPUnit for `M11ApprovalTest` and related lock tests.
- [x] Run focused Playwright smoke for `m11-approval.spec.ts` after UI changes.

## Definition of Done
<!-- Make completion measurable for the current session only. -->
- Course head can submit an offering for approval only when valid and schedulable.
- Pending/published offerings are locked against edits from course head/instructor/staff scheduling flows.
- Executive can view pending offerings read-only and approve or reject with required reason.
- Rejected offerings show rejection reason to course head and can be resubmitted after fixes.
- Approval history, audit logs, and notifications are written for submit/approve/reject.
- Relevant PHPUnit tests pass; Playwright smoke passes when UI is touched.

## Blocked / Waiting
<!-- Record unanswered questions or external blockers; clear this when resolved. -->
- None.

## Completed This Sprint
<!-- Add completed items only; keep this short and prune when sprint changes. -->
- ClickUp MCP connected and current task confirmed: `Sprint 1 — M11 Approval Workflow`.
- `CLAUDE.md` and relevant rules reviewed for M11 scope.
- M11 approval panel copy/layout polished for rejected, pending, published, and draft states.
- M11 page/role scope documented in `TASK.md`, `.claude/rules/sprint-status.md`, and ClickUp comment.
- Executive sidebar active state fixed so approval queue and rejected queue do not select together.
- Focused M11 PHPUnit and Playwright smoke pass after UI polish.

## Next Up
<!-- Add only 2-3 backlog items; do not write implementation plans here. -->
- `Sprint 2 — M6 Workload from Real Schedules`.
- `Sprint 3: Reporting module`.
- `Sprint 4: Smart warnings`.
