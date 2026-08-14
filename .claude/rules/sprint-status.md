# Sprint Status — ณ 22 มิ.ย. 2569

> ⚠️ **เปลี่ยนโหมดทีม → ผู้พัฒนาคนเดียว:** จากนี้พัฒนา TPSS ต่อ **คนเดียว** — เลิก branch ขนาน/merge order แบบทีม ทำ sequential ทีละโมดูล (โค้ด+test+docs จบในงานเดียว) · roadmap ดูหัวข้อ "Solo Roadmap (TPSS เท่านั้น)" ด้านล่าง

## 🔧 Phase UX Refactor + Bug Fix + Important Features — ✅ เสร็จครบ · merged เข้า `to-serve` ทั้ง 3 branch

> เอกสารต้นทาง: `อัพเดต_แก้บัค (2).pdf` (20 ข้อ · ข้ามข้อ 4) · แผนแบ่งงาน: `Doc/requirement/phase_ux_work_split.html`
> แตก 3 branch จาก `to-serve` — แบ่งตาม **โซนไฟล์** กัน conflict (10/20 ข้อแตะ `shared/schedules/index.blade.php` + `ScheduleController`)

| Branch | คน | ขอบเขต | สถานะ |
|--------|----|--------|-------|
| `feat/phase-ux-a-schedule-modal` | A | modal กรอก/แก้ slot + series/copy + ชุดผู้สอน (ข้อ 2,10,11,12,13,19,20,6a) | ✅ **merged เข้า `to-serve`** |
| `feat/phase-ux-b-schedule-calendar` | B | ปฏิทิน/การ์ด/ตัวกรอง/ตัวนับ (ข้อ 14,15,16,17,18,21) | ✅ **merged เข้า `to-serve`** (`aaf9a13`) |
| `feat/phase-ux-c-platform` | C | security/audit + settings + master data + test gate | ✅ **merged เข้า `to-serve`** (`304429e`) |

**Merge order (ทำเสร็จแล้ว):** C → B → A — verify ด้วย `git merge-base --is-ancestor origin/feat/phase-ux-a-schedule-modal to-serve` (ทั้ง 3 เป็น ancestor ของ `to-serve` แล้ว)

## 🎨 UI Redesign Pass — 🟡 กำลังทำ (3 branch แตกจาก `to-serve` · ยังไม่ merge)

> งาน redesign หน้าจอให้เข้า Impeccable Design รอบใหม่ — ทำต่อบน `to-serve`

| Branch | ขอบเขต | สถานะ |
|--------|--------|-------|
| `feat/redesign-dashboard` | dashboard เป็นโซน (hero/การ์ดต้องทำ/ภาพรวม+กราฟ/ติดตามงาน) | 🟡 admin เสร็จ 5 commits · **role อื่นยังไม่ทำ** · ยังไม่ merge |
| `feat/redesign-master-data` | redesign หน้า Master Data | 🟡 ยังไม่ merge เข้า `to-serve` |
| `feat/redesign-settings` | redesign หน้า Settings | 🟡 ยังไม่ merge เข้า `to-serve` |

## 🧭 Solo Roadmap (TPSS เท่านั้น · ไม่รวม PA/QA)

> ทำคนเดียว sequential · base จาก `to-serve` · ไม่ตัดงาน — ลิสต์ครบทุกส่วนพร้อมวัน
> ประมาณการเป็น **วันทำงาน** สำหรับ dev ที่รู้ codebase แล้ว (รวม test + docs ในงาน)
> ✅ **Stabilize + merge เสร็จแล้ว** (~22 มิ.ย. — `main` มีงานครบ) · **redesign พักไว้ทำทีหลัง** → เริ่มเฟส 2 ได้เลย

### Phase 1 — งานปิด (เหลือเฉพาะ optional/พักไว้)
| งาน | วัน | สถานะ |
|-----|----|-------|
| ~~Stabilize + merge `to-serve`→`main`~~ | ~~2~~ | ✅ เสร็จแล้ว |
| จบ redesign 3 หน้า (dashboard role อื่น + master-data + settings) | 5–7 | ⏸️ พักไว้ ทำหลังเฟส 2 |
| `rooms.campus` (optional) | 1 | ⏸️ ไม่อยู่ V3 |

### Phase 2 — โมดูลใหม่ (เริ่มได้เลย · base `to-serve`)
| ลำดับ | Module | งาน | วัน | ขึ้นกับ |
|------|--------|-----|----|--------|
| 4 | **M11** | Approval — ส่ง/อนุมัติ/reject+comment/lock/revised + log | **3** | table พร้อม · flow มีบางส่วน |
| 5 | ⭐ **M6** | Workload จาก schedule จริง — แยกคน/วิชา/ระดับ/ประเภท + กราฟ + Excel | **5** | ฐานของ PA · ตัดไม่ได้ |
| 6 | **M9** | Report PDF/Excel — ตารางวิชา/กลุ่ม/อาจารย์/ห้อง + filter | **5** | schedule + M6 · priority สูงสุดลูกค้า |
| 7 | **M5** | Smart Warning — ภาระงานเกิน/ต่ำเกณฑ์ + วิชายังไม่จัดตาราง | **2** | M6 |
| 8 | **M8+** | Publish Views ตาม role (อาจารย์/นักศึกษา/เจ้าหน้าที่ เห็นเฉพาะ published) | **3** | M11 |
| 9 | **Rotation** | `rotation_rounds`/`assignments` ปี 3-4 (4 รอบ/ปี + สลับข้ามเทอม) + workload per-round | **8–10** | M6 · ก้อนใหญ่สุด |
| | | **รวม Phase 2** | **~26–28** | |

### Test + UAT + buffer: **~3 วัน**

### สรุปเวลา TPSS ที่เหลือ (คนเดียว · Stabilize เสร็จแล้ว)
- Phase 2 ~26–28 + UAT/buffer ~3 = **~29–31 วันทำงาน** (+ redesign 5–7 ถ้ากลับมาทำ)
- **1 เดือนแรก (~20 วัน):** M11 → **M6 → M9** → M5 (= 15 วัน + buffer) — แกนหลักที่ต้องได้
- **เดือนถัดไป:** Publish Views + Rotation (+ redesign)

> ลำดับเหตุผล: **M6 มาก่อน M9** เพราะเป็นฐานของทั้ง report และ PA actual (โมดูล PA/QA อนาคต)
> ✅ **Sprint 1 — M11 Approval = completed + verified (23 มิ.ย. 2569)** — M11-01…06 Closed ครบ, `M11ApprovalTest` + e2e `m11-approval.spec.ts` เขียว
> 🚩 **เริ่มงานถัดไป = M6 Workload from Real Schedules** (Sprint 2 · dev เริ่ม ศ.26 มิ.ย. · กำลังวางแผน scope)

### M11 Approval Workflow — Page / Role Scope (อัปเดต 23 มิ.ย. 2569)

**Core workflow:** หัวหน้าวิชาเตรียมตารางและส่งขออนุมัติ → ผู้บริหารตรวจแบบ read-only แล้วอนุมัติหรือตีกลับพร้อมเหตุผล → ระบบล็อก offering ที่ `pending`/`published` และบันทึก approval history, audit log, notification ทุก action

| Role | หน้าที่ควรเปิดใน M11 | เหตุผล | ขอบเขตที่ไม่ควรเปิด |
|------|----------------------|--------|----------------------|
| **Course Head** | `maker.course_offerings.index`, `maker.course_offerings.show`, offering schedule pages, `maker.schedules.index`, `maker.alerts`, notifications | เป็นเจ้าของ workflow: เตรียมตาราง ตรวจ warning/conflict ส่งขออนุมัติ เห็นเหตุผลตีกลับ และ resubmit | ห้าม approve/reject เอง และห้ามแก้ offering เมื่อสถานะ `pending`/`published` |
| **Executive** | `approver.dashboard`, `approver.offerings.show`, `approver.offerings.rejected`, notifications | เป็นผู้พิจารณาอนุมัติ ต้องเห็นคิว pending รายละเอียด read-only และรายการที่ตีกลับแล้ว | ห้ามแก้ข้อมูลรายวิชา/ตาราง ห้ามใช้ schedule edit flow |
| **Admin** | `admin.dashboard`, `admin.audit_logs.index`, `admin.alerts` แบบ monitoring | ดูภาพรวม pipeline ตรวจ audit และช่วย support ระบบ | ห้าม submit/approve/reject แทน role จริง |
| **Staff** | schedule/support pages เฉพาะเมื่อมี delegated scheduling permission | ช่วยเตรียมข้อมูลหรือตารางได้ แต่ไม่ใช่เจ้าของการส่งอนุมัติ | ห้ามเข้า approval submit/action และห้ามเข้า `/approver/*` |
| **Instructor** | lecturer pages หรือ delegated schedule help เฉพาะกรณีได้รับสิทธิ์ | เกี่ยวข้องกับตาราง/ภาระงาน แต่ไม่ใช่ actor ใน approval chain | ห้าม submit/approve/reject และห้ามเข้า course offering management ของหัวหน้าวิชา |

**Decision / UX follow-up:**
- เมนู `ประวัติส่งอนุมัติ` ฝั่ง Course Head ยัง disabled: เปิดใน M11 เฉพาะถ้าต้องการ approval history แบบรวมทุกวิชา; ถ้า per-offering history พอ ให้เลื่อนไปเฟสถัดไป
- Sidebar ฝั่ง Executive แก้ active state แล้ว: คิวอนุมัติและเมนูตีกลับไม่ active ซ้อนกัน
- `ตารางทั้งหมด` และ `รายงานภาพรวม` ฝั่ง Executive ยังไม่ใช่ M11 core; เปิดภายหลังเมื่อเข้าสู่ publish/report/workload scope

### 📅 ตารางเฟส 2 แกนหลัก (รวมทดสอบ+แก้บั๊ก) — เริ่ม จ. 22 มิ.ย. 2569 (วันทำงาน จ.–ศ.)
> ⚠️ สลับลำดับจาก PDF: **M6 ก่อน M5** เพราะ M5 (warning ภาระงานเกิน/ต่ำเกณฑ์) ต้องใช้ตัวเลข workload จาก M6
> ทดสอบ 2 ชั้น: ท้ายแต่ละโมดูล (manual test + แก้ข้อบกพร่อง 1 วัน · คนละส่วนกับ unit test ที่รวมในวัน dev) + ทดสอบบูรณาการรวมตอนจบ
> ClickUp parent: `Build advanced features and reporting (Phase 2)` · Sprint Folder: `Phase 2 Sprints`

| ClickUp sprint task | งานหลัก | งานย่อย / task ที่อยู่ใน sprint | ช่วงวันที่ | สถานะ |
|-----|-----|-----|-----|-----|
| `Sprint 1 — M11 Approval Workflow` | **M11 Approval** | Task17 (M11-01,06) + Task18 (M11-02,03,04,05) + Module Testing & Bug Fix | จ.22–พฤ.25 มิ.ย. | completed |
| `Sprint 2 — M6 Workload from Real Schedules` | **M6 Workload** | Task23 (M6-01,02) + Task24 (M6-03,04,05) + Module Testing & Bug Fix | ศ.26 มิ.ย. · จ.29 มิ.ย. · อ.30 มิ.ย. · พ.1 ก.ค. · พฤ.2 ก.ค. · ศ.3 ก.ค. | ✅ completed (`6b660d1`) |
| `Sprint 3: Reporting module` | **M9 Report PDF/Excel** | Task25 (M9-01,02) + Task26 (M9-03,04,05) + Module Testing & Bug Fix | จ.6–จ.13 ก.ค. | 🟡 implemented + tests pass; รอ sync WP-07/ClickUp |
| `Sprint 4: Smart warnings` | **M5 Smart Warning** | Task21 (M5-01,02) + Task22 (M5-03..06) + Module Testing & Bug Fix | อ.14–พฤ.16 ก.ค. | Open |
| `Run internal system and integration tests` | **Integration Testing & Bug Fix** | ทดสอบบูรณาการ M11 + M6 + M9 + M5 และแก้ข้อบกพร่องก่อนปิด Phase 2 core | ศ.17 · จ.20 ก.ค. | Open |

**รวม 21 วันทำงาน → เสร็จแกนหลัก จ. 20 ก.ค. 2569** (Publish Views + Rotation = เดือนถัดไป)

### ✅ Branch C — เสร็จครบทุกข้อ + PHPUnit 564/564 เขียว (`feat/phase-ux-c-platform`)
- **8.1** force logout บัญชีถูกปิด (CheckActiveUser + 401 JSON สำหรับ AJAX) · **8.2** กัน role แปลกปลอม (`UserRole::VALID_ROLES` กรองที่ `normalizeRoles` คุม store/update/CSV) · **8.3** audit สลับบทบาท (`switchRole`) · **8.4** audit holiday CRUD/sync + ปิด alert · **8.5** mask รหัสผ่าน/token ลึกทุกชั้น (`AuditLogger::maskRecursive`)
- **ข้อ 3** locale=th (`.env`/`.env.example`/`phpunit.xml`) + ขยาย `lang/th/validation.php` attributes
- **ข้อ 5** ปุ่มนำเข้า CSV (`<template x-if>` หลุดนอก x-data scope จาก `</div>` เกิน → modal ไม่ render)
- **ข้อ 1** ปฏิทินการศึกษา **2 card**: "ปฏิทินกลางของคณะ" (fallbackCalendar ของปีปัจจุบัน) + "ปฏิทินแยกตามหลักสูตร/ชั้นปี" (accordion collapse · inheritance 3 ชั้น: ชั้นปี→ทั้งหลักสูตร→กลาง · หัวกลุ่ม=ทั้งหลักสูตรแก้ได้) · **วันหยุดแยกเป็น tab** · ปฏิทินอิงปีปัจจุบัน (ไม่มี dropdown เลือกปี) · ลบปุ่ม 📅 ที่แถวปี · ⚠️ resolve ปฏิทินรายหลักสูตรเข้า schedule observer = เฟสถัดไป (M6)
- **ข้อ 7** (เพื่อน) หน้าภาระงานอาจารย์ + PA self-service + donut · **ข้อ 9** modal เงื่อนไขรายวิชา = chip picker + ค้นหา (เลิก ctrl+click) · **ข้อ 4** (เพื่อน) confirm dialog เปิด/ปิดตาราง + dropdown แท็บปี
- **6b** PHPUnit 564/564 ผ่าน (ยังไม่รัน Playwright e2e เต็มรอบ — `m2-course-management` bulk groups เป็นของ 6b)
- tests ใหม่: `UserRoleSecurityTest`(5) `AuditLogSecurityTest`(5) `LocalizationTest`(3) + e2e `settings-calendar.spec.ts`, `user-management` import

## 🔔 Active Milestone — Client Demo + Staging Deploy (2 มิ.ย. 2569)

- **เป้าหมาย**: deploy Docker ขึ้น server บริษัทก่อน demo → demo บน URL จริง + ลูกค้าเข้าทดสอบต่อได้เอง
- **Docker setup**: ✅ สร้างแล้ว (`Dockerfile` multi-stage, `docker-compose.yml` app+mysql, `docker/entrypoint.sh`, `.env.docker.example`) — ⚠️ **ยังไม่ build/test** (เครื่อง dev ไม่มี Docker → จะ build บน server ตอน SSH access มา)
- **กฎเหล็กก่อน demo**: ห้ามแตะ migration `academic_years`/`course_offerings` บน `sprint` — demo จาก `sprint` (term-based เดิม) ไม่ใช่ V2 refactor
- **ใช้ demo เป็นเวทีเคาะ open questions** ของ V2 → ดู `architecture.md` "Requirement V2 Direction"
- **V2 direction documented**: `architecture.md` + `database.md`

## 🧹 Master Data Cleanup Phase (V2) — ✅ เสร็จครบ scope V3 (31 พ.ค.) · branch `feat/v2-requirement`

> เคลียร์ Master Data ให้นิ่งก่อนทำ schedule/rotation — ดูรายละเอียด `architecture.md` "Master Data Cleanup Phase (V2)"
> ทำบน `feat/v2-requirement` · `sprint` ยังเป็น demo fallback · ทุกรายการ REQUIRED ใน V3 master/setup scope ปิดครบ (เหลือแค่ `rooms.campus` ที่ optional + ไม่อยู่ใน V3)

**✅ DONE (verified migrate:fresh + test + php -l views):**
1. `academic_years` = "ปี" (ตัด `semester`, unique(name)) + ตาราง `terms` (เทอม 1/2/ฤดูร้อน + วันสอบกลาง/ปลายภาค) · วันปี derive จากเทอม · validation วันเทอม/สอบครบ
2. `courses` ตัด `default_semester` (วิชาเปิดทั้งปี) + เอา field ภาคออกจาก UI/CSV/audit
3. `course_offerings` ราย-ปี + auto-open = ทุกวิชาใน active curriculum + offering route key = course-year
4. `student_cohorts` กลุ่มชั้นปี (ปี1-2 กลุ่มใหญ่ / ปี3-4 = A1,B1,A2,B2) · location_types.`is_shared`
5. `holidays` (date, name, remark, source) — ตารางวันหยุดราชการ: auto-fetch จาก Google Thai ICS ตอนสร้าง/แก้ปี (ตามช่วงปีปฏิทินที่คร่อม) · ปุ่ม "ดึงวันหยุดซ้ำ" ต้องมีปี active ก่อน · refresh ลบเฉพาะ source=google คงของ manual + ปีอื่น · จัดการในหน้าตั้งค่า→ปีการศึกษา (เพิ่ม/แก้/ลบ + highlight แถว manual) · fail-safe (ดึงไม่ได้ไม่พัง flow)
6. `activity_types.counts_toward_workload` (bool) — Admin ตั้งนับ/ไม่นับภาระงาน · default ตามหมวด (other=ไม่นับ, อื่นๆ=นับ) ปรับเองได้ · ตาราง master data แยกคอลัมน์ "หมวดหมู่" + "ภาระงาน" เป็น pill
- **เคาะ 31 พ.ค.**: อนุมัติทั้งปี (per-year) · สลับกลุ่ม A/B ใน offering ปีเดียว (slot ติดป้ายเทอม)

**🔲 เหลือ optional (ไม่บล็อก — ไม่อยู่ใน V3):**
- `rooms.campus` ศาลายา/บางกอกน้อย — มาจาก V1/V2 เท่านั้น · display ก่อน ยังไม่ผูก conflict

**🟢 Schedule phase (term dimension) — เสร็จแล้ว (1 มิ.ย.):** `schedules.term_id` (observer derive จาก start_date) + `AcademicCalendar` จำแนกวัน + filter เทอม + ลงสีปฏิทิน (วันหยุด/สอบ/ปิดเทอม/เทอมอื่น) + บล็อกจัดกิจกรรมช่วงสอบ/ปิดเทอม + ตัด group selector ออกจาก modal · validation สอบปลายภาคหลังกลางภาค · seeder สอบปลายภาคจบตรงวันจบเทอม

**🟢 Delegation (อาจารย์ + เจ้าหน้าที่ช่วยจัดตาราง) — เสร็จแล้ว (1 มิ.ย.):** `CourseOffering::canBeScheduledBy`/`scopeSchedulableBy` = coordinator หรือ instructor `schedule_permission='schedule'` หรือ staff ใน `course_staff` + toggle รายอาจารย์ในหน้า offering (`instructors.permission`) + staff มอบหมายผ่าน modal รายวิชา + route จัดตาราง = `course_head,instructor,staff` (จัดการ offering + conflict-badge คง course_head) + sidebar อาจารย์/เจ้าหน้าที่เมนู "ช่วยจัดตาราง" (gate `canHelpSchedule`) · 12 tests (`ScheduleDelegationTest`) · ดู memory [[project-delegation-deferred]]

**🔲 Phase ถัดไป (rotation — ไม่ใช่ Master Data):** rotation_rounds/assignments (หมุนกลุ่มปี 3-4 หลังสอบ), visual dashboard (V3 ข้อ 8.1) · ~~cross-course group conflict~~ ✅ DONE ใน V4 แล้ว

## 🆕 Requirement V4 (ประชุมลูกค้า) — ✅ IMPLEMENTED + merged เข้า `to-serve`

> เอกสาร: `Doc/requirement/requirement_v4.md` (ข้อ 13) · แผนแบ่งงาน: `Doc/requirement/v4_work_split.md` · design ดู `architecture.md` "Requirement V4 Update"
> ทำเสร็จ 3 branch แล้ว merge เข้า **`to-serve`** (integration branch ใหม่ ต่อจาก `sprint`): A=`feat/v4-schedule-groups` · B=`feat/v4-master-data` · C=`feat/v4-rbac-pa-ops`

✅ (1) หัวข้อกิจกรรม dropdown ต่อวิชา (`activity_topics`) · ✅ (2) **หัวหน้าวิชาจัดกลุ่ม นศ. เอง** — group selector กลับเข้า modal + routes `student_groups.*` + `student_groups.cohort_group_id` + **cross-course GROUP conflict** (`group_overlap`) + ปี 3-4 = 4 กลุ่มใหญ่ · ✅ (3) executive=หัวหน้าภาค gate · ✅ (4) `curriculums.counts_service_only` · ✅ (5) อาจารย์กรอก PA เอง (`Instructor/PaController` + `pa_rounds`/`instructor_pa_allocations`) · ✅ (6) สลับเวรผ่านหัวหน้าวิชา + last-updated timestamp · ✅ (7) ลากช่วงวันที่ (`storeSeries` date-range) · ✅ **(8 🆕 นอกลิสต์เดิม) ปฏิทินแยกตามหลักสูตร/ชั้นปี** (`academic_calendars`) · ดู memory [[project-requirement-v4]]
🔲 เหลือ: **rotation_rounds/assignments** (หมุนกลุ่มปี 3-4 หลังสอบ) = phase ถัดไป · `rooms.campus` (optional)

## Phase Overview

| Phase | ชื่อ | สถานะ |
|-------|------|-------|
| Phase 1–3 | Initiation → Design | ✅ เสร็จ |
| Phase 4–5 | Development | 🟢 Sprint 1+2+3+M7 merge แล้ว, Schedule Suite (M3+M4+M8) Phase A/B/C ส่งบน branch `4-m3-schedule-suite-testPat` |
| Phase 5 | Testing | 🟡 Internal Testing กำลังดำเนินการ |
| Phase 6–7 | Deployment → Closure | 🟢 Staging deploy (Docker) เริ่ม 30 พ.ค. สำหรับ client demo 2 มิ.ย. — production closure **ขยายถึง 18 มิ.ย. 2569** (เดิม 4–7 มิ.ย.) |

## Sprint Plan — Phase 1 (193 SP)

| Sprint | วันที่ | Module | สถานะ |
|--------|--------|--------|-------|
| Sprint 1 | 11–12 พ.ค. | M10 Login/RBAC | ✅ 100% |
| Sprint 2 | 12–15 พ.ค. | M1 Master Data | ✅ 100% |
| Sprint 3 | 18–19 พ.ค. | M2 Course Management | ✅ merge เข้า sprint แล้ว (18 พ.ค.) |
| **Sprint 4–6** | **20–28 พ.ค.** | **M3 + M4 + M8 → Schedule Suite** | ✅ merge เข้า sprint แล้ว — รวม Cross-course conflict (branch `4-m4-cross-course-conflict` merge `42e4810`) |
| Sprint 7 | 20–27 พ.ค. | M7 Search & Filter | ✅ merge เข้า sprint แล้ว (16 พ.ค.) |

## Sprint 2 (M1) — สิ่งที่เสร็จแล้ว

- Shared Views: `views/shared/master_data/`, `views/shared/settings/`
- Staff\MasterDataController extends Admin\MasterDataController
- Staff\SettingController — จัดการ academic_year ได้ทั้งหมด, ไม่เห็น tab PA
- Lock icon บน tab ที่ Staff ดูอย่างเดียว
- Accordion drill-down: dept→อาจารย์, curriculum→วิชา, location_type→ห้อง
- Student Groups ย้ายออกจาก M1 → ไปสร้างใน M2 ตอน confirm offering
- `requires_capacity` boolean บน `location_types` — ห้องในประเภทที่ไม่ต้องการความจุ (เช่น ชุมชน) ไม่โดนแจ้งเตือน
- Admin Dashboard + role-based dashboards (executive, course_head, instructor, staff)
- Alerts system: `AlertController` + `/admin/alerts` page + dashboard widget
- PA criteria schema เปลี่ยนจาก string → `{min: int, max: int}` ต่อแต่ละด้าน

### M1 Requirement Re-check — 30 พ.ค. 2569

- ตรวจ requirement V2 เฉพาะส่วน Master Data เทียบกับระบบที่ implement แล้ว
- ยืนยันว่าไม่ต้องเพิ่ม field หลักสูตรต่อปี/track นานาชาติใน demo ปัจจุบัน เพราะหลักสูตรมี `effective_year`, `education_level`, `duration_years`, `uses_year_level`, `total_credits_required`, `status` และ `courses.curriculum_id` รองรับแล้ว
- ยืนยันว่าไม่ต้องเพิ่ม field campus/location kind ในตอนนี้ เพราะ `location_types` ใช้แยก logic conflict/capacity และรายละเอียดอยู่ใน `rooms` แล้ว
- ยืนยันว่า activity types ให้ผู้ใช้สร้างเองได้ โดยเลือกหมวด `lecture`, `practicum`, `thesis`, `other`; workload/hour calculation เป็น Phase 2
- ทำแล้ว: เพิ่ม helper text อธิบายหมวดกิจกรรมใน Master Data UI เพื่อให้ผู้ใช้เลือกหมวดถูก
- ทำแล้ว: อัปเดต `CourseRoleSeeder` ให้ seed บทบาทครบ ได้แก่ หัวหน้าวิชา, เลขานุการวิชา, ผู้ช่วยเลขานุการวิชา, อาจารย์ผู้สอน, อาจารย์ประจำกลุ่ม, อาจารย์พี่เลี้ยง, Preceptor/ผู้ควบคุมแหล่งฝึก

## Sprint 3 (M2) — สิ่งที่เสร็จแล้ว

### Two-Layer Status System
- `academic_years.phase` (preparation → scheduling → published) — จัดการในแท็บ "ปีการศึกษา" (column "ช่วงจัดตาราง")
- `course_offerings.approval_status` (draft → pending → published/rejected)
- ScheduleController + CourseOfferingController guard `phase != 'scheduling'`

### Course Pool (NEW — admin + staff)
- `course_roles` master table: หัวหน้าวิชา / เลขานุการวิชา / อาจารย์ผู้สอน / อาจารย์ประจำกลุ่ม / อาจารย์พี่เลี้ยง
- `course_instructors` pivot: template ระดับวิชา (course-level)
- `CoursePoolController` (admin + staff inherit): CRUD ชุดผู้สอน + เจ้าหน้าที่ + หัวหน้าวิชา
- Lock semantics: template ล็อกเมื่อมี offering เข้า scheduling/published phase แล้ว
- Sidebar เพิ่มเมนู "Course Pool"

### Course Offering — Hardening
- `openSchedulingWindow` — sync planning fields + instructor pool จาก template, ตัด filter `default_semester`
- Critical-gate ใหม่: `no_active_course`, `active_courses_missing_head` block การเปิด scheduling
- UI: 3-second countdown confirm + critical pill cards ก่อนกด "เปิดช่วงจัดตาราง"
- `bulkStoreStudentGroups` — สร้างหลายกลุ่มทีเดียวพร้อม auto-distribution + auto-color
- `course_offering_instructors.course_role_id` FK + `role_in_course` → varchar(100)
- Course-head show page: AJAX combobox + role chip dropdown (no reload)
- ~~Practicum-note override flow~~ ⚠️ ลบแล้ว (teardown 8 มิ.ย. — rotation/practicum_note ถอดทั้งหมด)
- Executive ถูกกรองออกจาก available instructor pool
- `course_type` ทำเป็น nullable + ลบจาก UI (UI infer จาก lecture/lab — rotation ถูก drop 8 มิ.ย.)

### Master Data — ย้าย Prerequisite
- Prerequisite ย้ายจาก per-offering (M2) → per-course (M1 Master Data)
- `MasterDataController::storeCourse/updateCourse` รับ `prerequisite_ids[]`
- `Rule::notIn([$course->id])` ป้องกัน self-prereq

### Removed (M3 ยังไม่เริ่ม)
- `resources/views/course_head/schedules/{index,create}.blade.php` ลบ
- `routes/web.php` ลบ schedule routes (controller method ยังอยู่ — orphan)

### Sprint 3 Hardening — 19 พ.ค.

- **academic_level refactor** (commit `5f436b5`): ย้าย `courses.academic_level` → `curriculums.education_level` (ระดับการศึกษาเป็น property ของหลักสูตร)
- **รองรับ ป.โท/ป.เอก**: เพิ่ม `curriculums.{education_level, duration_years, uses_year_level, total_credits_required}` — ป.โท/ป.เอกใช้ระบบ prerequisite + หน่วยกิตสะสมแทนระบบชั้นปี
- **`courses.is_required`** boolean แทน academic_level (วิชาบังคับ/เลือก)
- **`courses.default_year_level`** เป็น nullable, capped ตาม `curriculum.duration_years`
- **updateCurriculum cascade**: toggle `uses_year_level: true → false` → `default_year_level=null` ทุกวิชาในหลักสูตร
- **Settings UI merge** (commit `b05381b`): รวม tab "ช่วงจัดตาราง" เข้า tab "ปีการศึกษา" — ตารางเดียวรวม phase column + ปุ่ม toggle (admin-only) staff เห็น read-only — ลด cognitive load
- **CSV importer**: รับ `is_required` column แบบ optional (default true) + preload curriculums (เลิก N+1)
- **เพิ่ม 4 tests**: master curriculum without year_level, course year_level capped by duration, credit-based requires total_credits, cascade clear on toggle

## Schedule Suite (M3 + M4 + M8) — ✅ merge เข้า sprint แล้ว

> งาน Friend 2 (pronpimon013) — commits 22–25 พ.ค. + Cross-course conflict (merge `42e4810`)

### M3 — Schedule CRUD
- `CourseHead\ScheduleController` — index/create/store/update/destroy + workspace mode + per-offering view
- View ใหม่: `course_head/schedules/index.blade.php` (list + calendar), `_form.blade.php` (modal-based)
- Realtime conflict checks ผ่าน debounced AJAX (commit `741e51d`)
- List warning badges (quota / missing info / capacity) (commit `80fd486`)
- Dashboard widget "ตารางสอนที่กำลังจะถึง" (commit `3026295`)
- Optional `ScheduleFlowSeeder` (`php artisan db:seed --class=ScheduleFlowSeeder`) — พาระบบจาก fresh seed → state จัดตารางพร้อมข้อมูลตัวอย่าง

### M4 — Conflict & Validation Hardening (commits `c4eebcd`, `d41055c`, `42e4810`)
- **Instructor / room / group overlap** ภายใน offering เดียวกัน บล็อกบันทึก (severity `conflict`)
- **Cross-course conflict** ✅ — instructor/room overlap ข้ามวิชาผ่าน `ScheduleConflictChecker::bulkConflictMap()` + `ScheduleConflictReadRepository` (read model สำหรับหน้า conflicts) — `buildOwnedConflictMap()` ใน `CourseHead\ScheduleController` ดึง schedules ทั้งระบบที่ overlap date window แล้ว pairwise compare
- **Department gate** — อาจารย์ที่เลือกใน slot และที่ add เข้า instructor pool ต้องมี `instructor_profiles.department_id == courses.department_id` (`ScheduleController::assertInstructorsBelongToCourseDepartment`, `CourseHead\CourseOfferingController::storeInstructor`)
- **Capacity gate** — sum(`student_groups.student_count`) ของ groups ที่เลือก ≤ `capacity_required` (`ScheduleController::assertSelectedGroupsFitCapacity`)
- Conflict error key `'schedule'` ส่งเป็น **array of messages** (ไม่ใช่ string implode) → UI render เป็น bullet list
- **Conflicts page** — `/course_head/schedule/conflicts` dedicated page (4-card dashboard summary + per-schedule detail + styled popover tooltip, portal pattern)

### M8 — Calendar Views (commit `96f3fa7`, `7633390`, `e8ea5e1`)
- Period filters: **day / week / month** — toggle ผ่าน query param `?period=day|week|month&date=YYYY-MM-DD`
- Date picker year range คำนวณจาก `AcademicYear.start_date/end_date` (`ScheduleController::scheduleDatePickerYearRange`)
- Label `นอกช่วงปีการศึกษา` เมื่อวันที่ตกนอก start/end ของปีการศึกษาที่ active
- Month grid testid: `schedule-month-calendar-co`
- Grid events align ตาม start_time/end_time (commit `e8ea5e1`)

### Settings — Academic Year Activation Lock (commit `4df394c`)
- `AdminSettingController::storeYear/updateYear` block ตั้งปีอื่น `is_active=true` ถ้ามี `AcademicYear` ใดเหลือ `phase='scheduling'`
- Error message: "ไม่สามารถตั้งปีการศึกษา ... เป็นปีปัจจุบันได้ เนื่องจากยังมีช่วงจัดตารางที่เปิดใช้งานอยู่"

### Tests เพิ่ม
> ⚠️ **11 มิ.ย.: `ScheduleManagementTest` (99 tests) ถูกซอยเป็น `tests/Feature/Schedule/`** — `ScheduleTestCase` (base: setUp + helpers) + `ScheduleViewsTest` (index/views/workspace/routes) + `ScheduleStoreTest` (store/validation/gate/check_conflicts) + `ScheduleConflictTest` (copy-week/update/delete/overlap/audit) · ชื่อ test เดิมคงไว้ทุกตัว
- `ScheduleViewsTest::test_schedule_index_supports_day_week_and_month_periods` — period labels (`03/08/2569`, `สัปดาห์ที่ 1`, `สิงหาคม 2569`, `นอกช่วงปีการศึกษา`)
- `ScheduleStoreTest` capacity + department + conflict-array tests (commit `d41055c`)
- `SchedulingPhaseTest` — active-year switch lock ระหว่าง scheduling phase

## Post-Merge Branch Split (ตกลง 25 พ.ค.)

หลัง merge `4-m3-schedule-suite-testPat` เข้า sprint แล้ว แตก 3 branch ขนาน:

| คน | งาน | ขอบเขต |
|----|-----|--------|
| **Rachan (Lead)** | Course Offering UI fixes | หน้าจัดการ course offering — polish layout/UX ต่อ |
| **pronpimon** | M4 Cross-Course Conflict Check | ✅ merge แล้ว (`42e4810`) — ตรวจ conflict ห้อง/ผู้สอนข้ามรายวิชาภายในคณะ |
| **phuwadon** | User Management UX/UI | หน้าจัดการผู้ใช้ — polish + accessibility |

### Test Coverage (134 tests / 133 passing — pre-Schedule Suite)
- `CoursePoolManagementTest` (18) — CRUD + lock + RBAC
- `CourseOfferingHardeningTest` (11) — template sync + bulk groups + critical gate
- `CourseOfferingShowPageTest` — instructor AJAX flow + `instructor_pool_note` (note เมื่อ pool ต่างจาก template · 8 มิ.ย.) — ⚠️ practicum_note override tests ลบแล้ว
- `SchedulingPhaseTest` (13) — เพิ่ม critical-gate test, ลบ prereq/schedule guards
- `CourseOfferingManagementTest` updated — ลบ prereq tests, fix view assertions
- `AlertSystemTest` updated — `seedMinimalCriticals` รวม active course + head
- ลบ `ScheduleManagementTest` (routes deleted)

## Design Decisions (ตกลงแล้ว)

### Two-Layer Status System
- **ชั้น 1 — ระดับระบบ**: `academic_years.phase` (Admin ควบคุม)
- **ชั้น 2 — ระดับรายวิชา**: `course_offerings.approval_status` (Course Head + Executive)

### Scheduling Window
- Admin เปิด/ปิดผ่าน Settings tab "ปีการศึกษา" (column "ช่วงจัดตาราง", admin-only — staff เห็นเป็น pill อย่างเดียว)
- เปิด **ทั้งภาคเรียน** พร้อมกัน — fairness
- Critical-gate ต้องเคลียร์ก่อน
- Email notification = Phase 2 (future work)

### Course Role Management
- `course_roles` master + `course_instructors` template + `course_role_id` FK ใน offering pivot
- "หัวหน้าวิชา" auto-assign จาก `courses.head_instructor_id` (ไม่ใช่ role ใน dropdown)
- Default role เมื่อเพิ่มอาจารย์ = "อาจารย์ผู้สอน"
- Phase 2: ใช้สำหรับ M6 Workload report แยกประเภทบทบาท

### Prerequisite Location
- อยู่ที่ M1 Master Data (per-course) — ไม่ใช่ M2 (per-offering)
- Reason: prerequisite เป็น property ของวิชา ไม่เปลี่ยนตามรอบเปิดสอน

## คำถามที่รอคำตอบจากลูกค้า (pending)

- (ไม่มีค้าง — เคาะ V4 ครบ 4 มิ.ย.)

## คำถามที่ได้คำตอบแล้ว

| คำถาม | คำตอบ |
|-------|-------|
| ผู้ประสานรายวิชา = course_head role เดียวกับหัวหน้าวิชาไหม? | ใช่ — role เดียวกัน |
| ใครกด "ยืนยันเปิด" ให้จัดตาราง? | **Admin** ผ่าน Settings tab "ปีการศึกษา" (ปุ่ม toggle ใน column "ช่วงจัดตาราง") |
| Course Head รู้ว่าวิชาถูกเปิดให้จัดตารางยังไง? | Phase 2 — email notification (Gmail) |
| Prerequisite ระดับวิชาหรือระดับรอบเปิดสอน? | ระดับวิชา (M1 Master Data) |
| ป.โท/ป.เอก ใช้ระบบชั้นปีไหม? | ไม่ — ใช้ prerequisite + หน่วยกิตสะสม (`curriculums.uses_year_level=false`) |
| **(V4 4 มิ.ย.)** ใครจัดกลุ่มนักศึกษา? | **หัวหน้าวิชาจัดเองตั้งแต่กรอกตาราง** (กลับทิศจาก 31 พ.ค.) · ฐาน = จำนวน นศ. ชั้นปี ไม่ใช่ capacity |
| **(V4 4 มิ.ย.)** ปี 3-4 กี่กลุ่มใหญ่? | **4 กลุ่มใหญ่** (ไม่ใช่ 2 A/B) |
| **(V4 4 มิ.ย.)** รอบ rotation ใช้อะไรเป็นเส้นแบ่ง? | **ทั้งสองแบบ** — ปีละ 4 รอบ (มิดเทอมเป็นเส้นแบ่งในแต่ละเทอม) + สลับกลุ่มข้ามเทอม |
| **(V4 4 มิ.ย.)** เลขานุการวิชาอยู่ที่ไหน? | อยู่ใน **course_instructors template** (course-level) แต่ **หัวหน้าวิชาแก้ไขได้** ที่ระดับ offering |

## ข้อค้นพบสำคัญสำหรับ M3 (Schedule Management)

> อ้างอิง: `Doc/ตัวอย่างตารางสอน/` (ปี 1-4, เทอม 1-2)

1. **Block-based** — ตารางไม่ซ้ำรายสัปดาห์ → `schedules` ต้องเก็บ `start_date`/`end_date` ไม่ใช่ `day_of_week`
2. **วันที่เฉพาะเจาะจง** — บางกิจกรรมระบุวันที่ตรงๆ เช่น "15-19 ก.ค. 2568"
3. **Parallel Groups** — วันเดียวกัน กลุ่ม A ward, กลุ่ม B ห้องเรียน → ทุก slot ต้อง link `student_group_id`
4. **Nested Groups** — ปี 3-4 แบ่ง A→A1/A2, B→B1/B2
5. **หลายอาจารย์ต่อกิจกรรม** — `schedule_instructors` pivot รองรับได้ ✅
6. **M2 เสร็จแล้ว** — instructor pool พร้อมใช้ผ่าน `course_offering.instructorPool`
7. **Guard**: ห้ามสร้าง schedule ถ้า `academic_year.phase != 'scheduling'`

## Definition of Done

- Code สมบูรณ์และผ่าน unit test
- ผ่าน code review
- ทดสอบ UI บน Chrome / Edge
- ไม่มี conflict ที่ยังไม่ได้แก้ไข
- บันทึกผลใน System Test Checklist
- เอกสาร (SRS / User Manual) อัปเดตแล้ว (ถ้าเกี่ยวข้อง)

## Requirement Update — 29 พ.ค. 2569 จากเอกสาร `รายละเอียดระบบ` 🟡 BACKLOG

> อ้างอิงภาพเรียงหน้า: `storage/tpss_detail_pages/page-00.jpg` ถึง `page-25.jpg`
> สิ่งสำคัญคือ comment สีน้ำเงินจากอาจารย์ ซึ่งชี้ว่า TPSS ควรเป็นเครื่องมือกรอกตาราง ตรวจสอบ เผยแพร่ และสรุปภาระงาน มากกว่า auto scheduler เต็มรูปแบบ

### Phase 1 Triage (ทบทวน 29 พ.ค. — เทียบกับ state จริงในโค้ด)

comment อาจารย์ดึงงานที่เดิม label Phase 2 (M6/M9/M11) ขึ้นมาเป็น priority — ลำดับแนะนำสำหรับ Phase 1:

| Priority | งาน | Module | สถานะจริง | ลำดับ Phase 1 |
|----------|-----|--------|-----------|---------------|
| 3 | Activity Type Workload flag | M1 | ❌ `activity_types` ยังไม่มี `counts_toward_workload` | **1 (S, ปลดล็อกของอื่น)** |
| 1 | PDF/Excel Report | M9 | ❌ ไม่มี ReportController | **2 (สูงสุดจากอาจารย์)** |
| Bug #2 | Filter ตารางตามอาจารย์ | M3 | ❌ ScheduleController ยังไม่อ่าน `?instructor_id` | **2 (quick win ขนานได้)** |
| 4 | Publish Views ตาม role | M8 | ❌ instructor/executive มีแค่ dashboard | **3 (M)** |
| 5 | Approval/Revision Flow | M11 | ❌ ไม่มี ApprovalController (table `course_offering_approvals` เตรียมแล้ว) | **3 (M)** |
| 2 | Workload Dashboard จาก schedule จริง | M6 | ⚠️ widget ใช้ quota (`teachingWeeks × hoursPerWeek`) ไม่ใช่ schedule | **4 (L, ขึ้นกับ #3 — defer ได้ถ้าเวลาไม่พอ)** |
| 6 | Duplicate/Series | M3 | ✅ `schedule_templates` + `storeSeries()` merge แล้ว | — done |
| 7 | Curriculum version/intl metadata | M1 | ⚠️ `education_level` มี — version/นานาชาติ ยังไม่ครบ | Phase 2 |

### Priority 1 — PDF Report สำหรับแจกตาราง

- เพิ่ม report/export PDF ที่ใช้งานจริงได้สำหรับตารางรายวิชาและรายกลุ่มนักศึกษา
- เป้าหมาย: เมื่อเปิดรายวิชาแล้วสามารถ export PDF แจกนักศึกษาได้ ไม่ต้องพิมพ์ใหม่
- Scope เริ่มต้นควรมี filter ปี/ภาค/หลักสูตร/รายวิชา/กลุ่ม และแสดงวันที่ เวลา กิจกรรม สถานที่ ผู้สอน หมายเหตุ
- Excel ยังต้องรองรับตาม requirement เดิม แต่ PDF มีน้ำหนักสูงกว่าใน comment ล่าสุด

### Priority 2 — Workload Dashboard จาก schedule จริง

- ผู้บริหารต้องเห็นว่าอาจารย์แต่ละคนสอนระดับใดบ้าง จำนวนเท่าไร
- สรุป workload ต้องคำนวณจาก schedules จริง ไม่ใช่ quota/profile อย่างเดียว
- ต้องแยกตามรายบุคคล รายวิชา ระดับหลักสูตร และประเภทกิจกรรม
- ต้องรองรับบริบท ป.ตรี block ซับซ้อน และ ป.โท/ป.เอก นักศึกษา 10-50 คนต่อหลักสูตร

### Priority 3 — Activity Type Workload Semantics

- เพิ่ม metadata/flag ให้ประเภทกิจกรรมว่า "นับชั่วโมง workload หรือไม่"
- ปฐมนิเทศและ SDL มักไม่นับชั่วโมงให้อาจารย์
- เพิ่มประเภทกิจกรรม "วิทยานิพนธ์" และ "ดุษฎีนิพนธ์"
- รายงาน workload ต้องใช้ flag นี้

### Priority 4 — Publish Views ตามผู้ใช้

- Publish ต้องให้เห็นทั้งอาจารย์ นักศึกษา และงานบริการการศึกษา/เจ้าหน้าที่
- อาจารย์เห็นตารางของตนเอง
- นักศึกษาเห็นตารางเรียนของกลุ่ม
- เจ้าหน้าที่เห็นตารางทั้งหมด
- แสดงเฉพาะตารางที่ Approved/Published ตาม workflow

### Priority 5 — Approval/Revision Flow ให้ครบ

- หัวหน้าวิชา/ผู้มีอำนาจ approve: Draft → Approved
- ระบบ lock ข้อมูลบางส่วนหลัง approve
- บันทึก approval log/audit
- Operation phase: เมื่อแก้ระหว่างภาคให้ตรวจ conflict ใหม่, เปลี่ยนสถานะเป็น `revised`, เก็บประวัติแก้ไข
- การแจ้งผู้เกี่ยวข้องเป็น optional ใน v1

### Priority 6 — Duplicate/Series สำหรับ pattern ป.ตรีปี 3-4 ✅ DONE

- ป.ตรีปี 3-4 สอนซ้ำเดิม 2 ครั้งใน 1 ภาค แต่เปลี่ยนกลุ่ม
- ควรรองรับการคัดลอกโครงตาราง/series แล้วเปลี่ยนกลุ่มต่อรอบ
- ✅ implement แล้ว: table `schedule_templates` (`2026_05_28_000003`) + `schedules` template fields (`..000004`) + routes `schedules.series.store` / `templates.update` / `templates.destroy` + `ScheduleController::storeSeries()` — ตรงกับ Bug #3 ด้านล่าง

### Priority 7 — Curriculum/Program Metadata

- หลักสูตรเปลี่ยนทุก 5 ปี → report/filter ต้องเลือกหลักสูตรและ version ได้ชัด
- เพิ่ม/รองรับ metadata หลักสูตรนานาชาติหรือสองภาษา
- ระวังการผูกชื่อวิชากับหลักสูตรที่เปลี่ยน version ไม่ให้ report ปนกัน

## Bug Report — 28 พ.ค. 2569 รอบ 2 ✅ ปิดหมดแล้ว

หลัง close 12 bugs รอบแรก ลูกค้า/Rachan แจ้งเพิ่ม 3 รายการ (priority: #0+#1 → #2 → #3)
สถานะ ณ 29 พ.ค.: **#0+#1 ✅ done · #3 ✅ done · #2 ✅ done (wire backend filter เสร็จ)**

### #0+#1 — Location Type "ใช้ร่วมกันได้" (Bug + UX) — S/M effort ✅ DONE

> ✅ implement แล้ว: migration `2026_05_28_065050_add_is_shared_to_location_types` + `2026_05_28_083032_drop_requires_capacity_from_location_types` — `requires_capacity` ถูกแทนด้วย `is_shared` (พลิก semantic) เรียบร้อย

Field `location_types.requires_capacity` มีอยู่แล้วแต่ครอบคลุมแค่ capacity alert — ต้องขยาย:

| ติ๊ก (`is_shared=true`) | ไม่ติ๊ก (default) |
|---|---|
| ❌ ไม่เตือน capacity | ✅ เตือน capacity |
| ❌ ไม่เช็ค room overlap ข้ามวิชา | ✅ เช็ค cross-course room conflict |
| ❌ ไม่เช็ค in-course room overlap | ✅ เช็ค (มีอยู่แล้ว) |

**UX:**
- เปลี่ยน label: `"ต้องระบุความจุ (จำนวนที่นั่ง)"` → `"สถานที่ใช้ร่วมกันได้ (ขนาดใหญ่)"`
- พลิก semantic — ต้อง migration พลิกค่า OR rename column → `is_shared`
- เพิ่ม description: `"เช่น โรงพยาบาล, ชุมชน, สนามฝึก — กิจกรรมหลายวิชาใช้สถานที่เดียวกันพร้อมกันได้"`

**ไฟล์เกี่ยวข้อง:** `database/migrations/...rename_or_add_is_shared.php`, `shared/master_data/index.blade.php`, `ScheduleConflictChecker.php` หรือ `ScheduleConflictIndex.php` (skip room overlap ถ้า `room.locationType.is_shared`), `Admin/AlertController.php` (skip capacity alert)

### #2 — Filter ตารางตามอาจารย์ (Enhancement) — S effort ✅ DONE

> ✅ wire backend เสร็จ 29 พ.ค.:
> - `schedulePageData()` อ่าน `$request->integer('instructor_id')` → closure `$instructorFilter` ใช้ `->tap()` บนทั้ง query `$schedules` (period) และ `$allSchedules` (list) → `whereHas('instructors', fn => where('users.id', $id))`
> - ส่ง `selectedInstructorId` กลับ view → dropdown คง selection หลัง redirect
> - `schedulePeriodUrl()` รับ `$instructorId` → ปุ่มเปลี่ยน period / prev-next / weekend-toggle พา `instructor_id` ไปด้วย (filter ไม่หลุดตอนนำทาง)
> - JS `applyInstructorFilter()` + dropdown UI มีอยู่แล้ว (`index.blade.php:4513, 4642`)
> - Test: `ScheduleStoreTest::test_schedule_index_filters_by_instructor` (unfiltered เห็นทั้งสอง / filtered เห็นเฉพาะของ instructor นั้น) — เดิมอยู่ใน ScheduleManagementTest ก่อนซอย

หน้าตารางสอนหัวหน้าวิชา เพิ่ม dropdown filter "เลือกอาจารย์" ใน toolbar → ดูเฉพาะ slot ที่อาจารย์คนนั้นสอน รองรับทุก view (list/week/day/month)

**ไฟล์เกี่ยวข้อง:** `CourseHead/ScheduleController.php` (รับ `?instructor_id=` ใน `schedulePageData()`), `course_head/schedules/index.blade.php` (toolbar + filter UI + join `schedule_instructors`)

### #3 — Auto-duplicate กิจกรรมรายสัปดาห์ (Feature ใหญ่) — L effort ✅ DONE

> ✅ implement แล้ว: table `schedule_templates` (`2026_05_28_000003`) + `schedules` template fields (`..000004`) + `ScheduleController::storeSeries()` / `updateSeriesTemplate()` / `destroySeriesTemplate()` — ตรงกับ Priority 6 ด้านบน

หัวหน้าวิชาควรสร้าง slot ต้นแบบ (วันในสัปดาห์ + เวลา + ช่วงสัปดาห์) → ระบบ auto-generate slot ลูกทุกสัปดาห์ → แก้แค่ห้อง+กลุ่ม นศ. ต่อสัปดาห์ (workflow ของ practicum rotation จริง)

**Schema:** ใช้ table `schedule_templates` แยก template/instance (ไม่ได้ใช้ `schedules.practicum_series_id` ตามที่เคยคาด)

**คำถามที่ต้องถามลูกค้าก่อน design:**
- แก้ template ภายหลัง → slot ลูกที่แก้ไปแล้ว ควรถูก overwrite ไหม?
- จำนวนสัปดาห์ default = `course.teaching_weeks` หรือไม่?
- วันหยุด → skip auto หรือ user ลบเอง?

**Effort:** ~2-3 วัน, ขนาดเท่า Schedule Suite phase ย่อย — ควรเป็น sprint ของตัวเอง

---

## Bug Report — 28 พ.ค. 2569 (อัพเดต_แก้บัค.pdf) ✅ ปิดหมดแล้ว

12 รายการจาก test รอบ Schedule Suite — แบ่ง 3 branch ทำขนาน, merge เข้า sprint ครบทั้งหมด:

### Branch A — `fix/conflicts-page-detail` (Rachan, Lead) ✅ merge `ad76341`
หน้าแจ้งเตือนการชน + sidebar + tooltip:
1. ลบ dropdown ปีการศึกษา + เพิ่ม detail (อาจารย์/ห้อง/กลุ่ม) ในกล่องแจ้งเตือน + สรุปภาพรวม 4 cards
9. Empty state แยก `preparation` / `no_offerings` / `no_conflicts` — สร้าง `App\Support\CoordinatorEmptyState` ใช้ร่วมทุกหน้าฝั่ง course_head
10. Sidebar icon (CRITICAL=circle, WARNING=triangle) sync กับหน้า admin.alerts
11. Tooltip การชนเป็น styled popover ใน `_conflict_pill.blade.php` ใช้ **portal pattern** (escape transform/overflow ของ card cell ที่ block `position: fixed`)
12. Count "ดูทั้งหมด N" ใช้ `COUNT(DISTINCT conflicting_schedule_id)` ตรงกับ cards ที่ render หลัง `groupBy(schedule_id)` + preview fetch ครบทุก record ของ top N distinct schedules → preview = expanded view

### Branch B — `fix/schedule-calendar-ui` (pronpimon) ✅ merge `219becf`
Calendar render + mobile responsive (10 commits ใน `schedules/index.blade.php` + `app.css`):
4. ตารางสอนมุมสัปดาห์ scroll แนวนอนในจอ mobile (iPhone)
5. การ์ดมุมมองเดือน align กับ week/day grid styling
7. ป้าย "นอกช่วงปีการศึกษา" wrap ขึ้นบรรทัดบนเมื่อจอแคบ
8. Ghost card contain ใน column ของวันตัวเอง

### Branch C — `fix/schedule-logic-form` (phuwadon) ✅ merge `9b0b43f`
Controller logic + modal form (commit msg เดิม "แก้บัคยับๆครับเฮีย" — amend แล้ว):
2. Default รายวิชารหัสน้อยสุด (course_code ASC)
3. Modal เพิ่มกิจกรรม: clear default time + required validation
6. Filter วิชา `status='active'` ใน coordinator queries

### Lessons learned (ใช้กับงานขนานครั้งต่อไป)

- **แบ่งไฟล์ก่อน แล้วค่อยลงมือ** — phuwadon/pronpimon/Rachan แตะ `schedules/index.blade.php` ทั้ง 3 คน แต่ละคนละจุด → rebase ไม่ conflict สักครั้ง (เพราะวางแผนล่วงหน้า)
- **Merge order** (phuwadon → pronpimon → Rachan) ตามแผนเดิม รันได้ตามจริง
- **`--force-with-lease`** เป็น default บน branch ตัวเอง — safe ปฏิเสธถ้ามีคนทับ
- **Reword commit message ของเพื่อน** — ถ้าจำเป็น: reset sprint → cherry-pick + amend (preserve author) → force push sprint — ทำได้ในช่วง window สั้น ๆ ก่อนคนอื่น pull

---

## Sprint 3 Bug Report — 18 พ.ค. 2569 (bug_sprint2_18_05_69.pdf) ✅ ปิดหมดแล้ว

15 bugs จากการ test M1/M2/M10 — ปิดแล้วใน 2 branch:
- **Branch A** (`fix/m1-master-data-bugs`) — merged `d614817` + review fixes `6770a65, fe0ebee`
- **Branch B** (`fix/m2-ux-bugs`) — merged `23b4e0f` + review fixes `5ef4386`
- พร้อมเริ่ม Schedule Suite (M3+M4+M8) ได้แล้ว

### Branch A — `fix/m1-master-data-bugs` (Friend 1)
1. รหัสวิชาซ้ำในหลักสูตรเดียวกันได้ — ต้อง unique constraint `(curriculum_id, course_code)`
2. CSV export ภาษาไทยเพี้ยนใน Excel — ต้อง prepend UTF-8 BOM
3. CSV template ของ courses/rooms ไม่รองรับภาษาไทย + field ใหม่
4. Empty state "ไม่พบข้อมูล" ไม่ขึ้นทุก tab M1 (ยกเว้น instructor)
5. เลือก head=secretary คนเดียวกัน → confirm แล้วบันทึกไม่สำเร็จ
15. หน้าจัดการผู้ใช้: ไม่ขึ้น % PA criteria + ข้อมูลหายเมื่อ validation error (ต้อง `withInput()`)

### Branch B — `fix/m2-ux-bugs` (Friend 2 หรือ defer ไป Phase 2)
6. URL `/admin/course-pool/1` ใช้ id — ควรใช้รหัสวิชา (`getRouteKeyName`)
7. รายวิชาในหน้าจัดการของ course head **แสดงซ้ำ** — query JOIN ทำให้แถวซ้ำ
8. Admin dashboard ไม่เห็นสถานะระบบ + ไม่มี shortcut (UX)
9. ไม่มี filter "วิชาเปิดสอน vs ปิดสอน" + badge "ยังไม่มีหัวหน้าวิชา" (UX)
10. Sidebar เด้งขึ้นบนสุดเองหลังกด "ตั้งค่าระบบ" — scroll position หาย
11. URL `/maker/course-offerings/1` ใช้ id — เหมือนกับ #6 (รวม PR เดียวได้)
12. เพิ่ม badge "กลุ่มเต็ม" / "เหลือ N คน" ในหน้า course offering list
13. Layout course offering list table แน่นไป
14. หน้า course offering detail: layout ทับ + คลิก input ไม่ได้ (z-index/pointer-events)

**กฎก่อนแก้:** Bug #6, #10, #14 ต้อง **merge เข้า sprint ก่อน Sprint 4 เริ่ม** เพราะกระทบ M3 (route binding, sidebar conflict, show page layout)

## Post-Bugfix Parallel Plan (อัปเดต 18 พ.ค.)

หลัง bug fix เสร็จ ทำ 3 งานขนานกัน — แยก page ปลอดภัยถ้าทำตาม [widget contract](.claude/rules/ui.md#dashboard-widget-contract-สำคัญเมื่อทำงานขนาน):

| คน | งาน | ไฟล์หลัก |
|----|-----|----------|
| Lead (Rachan) | Dashboard polish ของ admin/staff/course_head + เขียน E2E/Feature test ตามหลัง | `views/{admin,staff,course_head}/dashboard.blade.php` + `views/shared/dashboard/*` |
| Friend 1 | Audit Log สำหรับ M10/M1/M2 | NEW: `audit-logs/*`, migration, trait/observer |
| Friend 2 | **Schedule Suite (M3+M4+M8 รวมกัน)** ดูหัวข้อด้านล่าง | `CourseHead/ScheduleController.php` (มี foundation), NEW views `schedules/*` |

**Sidebar slots พร้อมแล้ว** ใน `components/sidebar.blade.php`: "ตารางสอน" (admin/staff/course_head) และ "Audit Logs" (admin) เป็น `href="#"` รอเสียบ route → ไม่ต้องเพิ่ม structure ใหม่

## Schedule Suite — รวม Sprint 4+5+6 (ตกลง 18 พ.ค.)

**เหตุผลที่รวม:** M3 (สร้าง slot) → M4 (conflict check ตอน save) → M8 (calendar view) เป็น feature เดียวจากมุมผู้ใช้ — แยกสปรินต์แล้วทดสอบเร่อ ๆ ใช้งานไม่ได้จริง

| Phase | Scope | ระยะเวลา | คนทำ |
|-------|-------|---------|------|
| **A — MVP** | M3 CRUD slots (list view) + M4 conflict check ตอน save + phase guard | 5-7 วัน | Friend 2 |
| **B — UX สมจริง** | M4 real-time check (debounced AJAX) + warning badges (quota, missing info) | 3-4 วัน | Friend 2 |
| **C — Calendar** | M8 calendar view (เดือน/สัปดาห์) + click-empty-cell-to-add + filter | 3-4 วัน | **Lead (Rachan)** ช่วยหลัง dashboard เสร็จ |

**กฎสำคัญสำหรับ Lead เข้าไปช่วย:**
- ต้อง merge dashboard เข้า sprint ให้เสร็จ **100% ก่อน** ค่อยข้ามไปช่วย calendar
- Phase C ใช้ **Test After Merge** flow (ดู [.claude/rules/testing.md](.claude/rules/testing.md#test-after-merge-flow))
- Phase C **ไม่ขนานกับ Phase A/B** — Friend 2 หยุดทำงานบน feature branch ก่อน Lead เริ่ม

**ข้อระวัง:** ถ้าลูกค้าอยากเห็น calendar ตั้งแต่ test รอบแรก ต้องสลับ Phase C ขึ้นก่อน Phase B

## Known Bugs / Hotfixes

- `AdminUserController:32` — `reset(reset($x))` ส่ง value แทน reference → แก้แล้ว (afa38ae)
- `AdminUserManagementTest::test_admin_can_create_user` — แก้แล้วใน `test/user-management-coverage` (8ecdfb1) เพิ่ม `employee_id` field + ลบ `withSession` chain

## Git Branching

```
main ← production-ready  ✅ มี V4/V2/PhaseUX ครบแล้ว (merge to-serve กลับ main เรียบร้อย ~22 มิ.ย.)
  │                        ตามหลัง to-serve แค่ 1 commit (8d5d37b Thai date) — sync กันแล้ว
  └── sprint ← integration เดิม (ทิ้งไว้ · ตามหลัง to-serve 276 commits · ไม่ใช้ต่อ)
  └── to-serve ← ⭐ working base ปัจจุบัน — งานเฟส 2 ทำต่อบนนี้ แล้ว merge กลับ main เป็นระยะ
        ├── V4 (master-data / rbac-pa-ops / schedule-groups)  ✅ in main
        ├── feat/phase-ux-a/b/c   ✅ Phase UX Refactor + Bug Fix (20 ข้อ)  ✅ in main
        └── (redesign branches แยกไว้ พักก่อน — ดู 🎨 UI Redesign Pass)
```
> ✅ **หนี้ integration เคลียร์แล้ว** — `main` มีงานครบ (debt 616 commits หายแล้ว) · `sprint` ถูกทิ้ง
> ⭐ **กฎต่อจากนี้:** ทำเฟส 2 บน `to-serve` → test เขียว → merge กลับ `main` เป็นระยะ (ไม่ปล่อยห่างอีก)
> 🎨 **redesign 3 branch พักไว้** (`redesign-dashboard/master-data/settings`) — ทำเฟส 2 ก่อน ค่อยกลับมาทำ redesign ทีหลัง
