# Sync ClickUp + Test Evidence

หลังปิดงานก้อนหนึ่ง (module/feature/แก้บั๊ก) — อัปเดต ClickUp ให้ตรงสถานะปัจจุบัน + บันทึกเทสใหม่ขึ้น Google Sheet และ ClickUp evidence task เพื่อให้ติดตามสถานะได้

## กลไก ClickUp (อ่านครั้งเดียวก่อนเริ่ม)

ClickUp MCP ถูกล็อก premium → ใช้ **REST API** เสมอ (`https://api.clickup.com/api/v2/...`)

- **Token:** ดึงจาก mcp config `~/.claude.json` → `projects['<repo path>'].mcpServers.clickup.env.CLICKUP_API_KEY` (อย่า hardcode/commit). ส่งใน header `Authorization`. **ถ้า auth fail (`OAUTH_025`/401) = token ใน config หมดอายุ → ขอ token ใหม่จากผู้ใช้ แล้วเขียนทับใน config ด้วย string-replace (กัน formatting JSON พัง)**
- **ข้อความไทย:** ส่งผ่าน **`python json.dumps`** (`PYTHONIOENCODING=utf-8`, print เป็น ASCII กัน cp1252 crash) — **ห้าม `curl -d '{...ไทย...}'`** เพราะ shell ทำไทยเพี้ยนเป็น `?????`
- **IDs:** team `90182762652` · space `901811306058` (Project_Trss) · WP-07 evidence list `901818980114` · Daily Updates task `86ey35uuj`
- **Endpoints:** comment `POST /task/{id}/comment` · แก้ comment `PUT /comment/{id}` · ลบ `DELETE /comment/{id}` · status `PUT /task/{id}` body `{"status":"..."}` · evidence task `POST /list/{listId}/task` body `{"name","markdown_content"}`
- รายละเอียดเพิ่ม: memory `clickup-rest-thai-encoding`

## ขั้นตอน

### 1. ระบุงานที่เพิ่งเสร็จ
- จาก `git log --oneline -10` + สิ่งที่ทำใน session: module (M6/M11/...), branch, commit
- หา ClickUp sprint task ที่ตรง — GET tasks ใน list `Backlog` แล้ว match ชื่อ (เช่น "Sprint 2 — M6 Workload")
- **เสร็จเมื่อ:** รู้ module + sprint task id + มี/ไม่มีเทสใหม่หรือแก้

### 2. บันทึกเทสใหม่ขึ้น Sheet (ทำเฉพาะถ้ามีเทสใหม่/แก้)
- มี script แล้ว: `npm run wp07:test:<module>:<type>` · ยังไม่มี → เพิ่มลง `package.json` ตาม pattern `wp07:test:m6:*` (filter เฉพาะ test ของ module นั้น)
- recorder รัน test จริงแล้วโพสต์รายเคสขึ้น Sheet อัตโนมัติ (phpunit/playwright)
- **เสร็จเมื่อ:** print `Recorded WP-07: TR-... (Pass) — N row(s)` ครบทุก type ที่รัน

### 3. สร้าง/อัปเดต ClickUp evidence task
- **1 task ต่อ module** ใน WP-07 list (ไม่ใช่ราย-run) — เนื้อหา: metadata (module/branch/commit/date) + run summary (Run ID, pass/total) + **link กลับ Google Sheet** + scope + defect/follow-up
- แนบ artifact ที่ Sheet เก็บไม่ได้ถ้ามี (Playwright report zip → `POST /task/{id}/attachment` multipart)
- โพสต์ผ่าน python (ไทย-safe)
- **เสร็จเมื่อ:** task สร้าง/อัปเดตสำเร็จ (ได้ id กลับมา)

### 4. อัปเดต status (subtask + sprint) + คอมเมนต์
- **ต้องอัปเดต status field จริง ไม่ใช่แค่คอมเมนต์** — GET subtasks ของ sprint task (`?include_subtasks=true`) แล้วไล่ทีละตัว:
  - user-story/dev-task ที่ **เสร็จ** → `Closed` · ที่ **กำลังทำ** → `in progress` · ยังไม่แตะ → คงเดิม
  - sprint task เอง → `complete` ก็ต่อเมื่อ merge + ปิดครบ ไม่งั้น `in progress`
  - status ที่ใช้ได้ใน Backlog list: `Open / to do / in progress / review / completed / Closed`
- คอมเมนต์สรุปงานที่เพิ่งเสร็จ (สิ่งที่ทำ + เทสที่ผ่าน + ข้อค้าง) ผ่าน python · **verify:** GET comment เช็คไทยอ่านได้ (เพี้ยน = ลบ+โพสต์ใหม่)
- **เสร็จเมื่อ:** subtask ที่เสร็จทุกตัวเป็น `Closed` (ไม่เหลือ to-do ของงานที่ทำจริงแล้ว) + sprint status ตรง + comment อ่านไทยได้

### 5. อัปเดต Daily Update (standup)
- เติม **บล็อกวันใหม่ไว้บนสุด** ของ task `📋 Daily Updates — Phase 2` (id `86ey35uuj`):
  GET description เดิม → prepend บล็อกใหม่ (คั่นด้วย `---`) → `PUT /task/{id}` body `{"markdown_content": ...}` ผ่าน python (ไทย-safe)
- บล็อกใหม่ **ต้องมี 4 หัวข้อชัดเจน** (โครง Scrum standup):
  - **Done** — เมื่อวานทำอะไรเสร็จไปแล้วบ้าง
  - **Doing** — วันนี้ตั้งใจจะทำอะไรต่อ
  - **Blocker** — มีอะไรติดขัดจนทำงานต่อไม่ได้ไหม (ไม่มี = เขียน "ไม่มี")
  - **Questions** — มีคำถามต้องถาม PO/ลูกค้า/คนอื่นไหม (ไม่มี = เขียน "ไม่มี")
- **เสร็จเมื่อ:** มีบล็อกวันใหม่ครบ 4 หัวข้อ อยู่บนสุด อ่านไทยได้
