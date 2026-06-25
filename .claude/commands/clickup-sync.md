# Sync ClickUp + Test Evidence

หลังปิดงานก้อนหนึ่ง (module/feature/แก้บั๊ก) — อัปเดต ClickUp ให้ตรงสถานะปัจจุบัน + บันทึกเทสใหม่ขึ้น Google Sheet และ ClickUp evidence task เพื่อให้ติดตามสถานะได้

## กลไก ClickUp (อ่านครั้งเดียวก่อนเริ่ม)

ClickUp MCP ถูกล็อก premium → ใช้ **REST API** เสมอ (`https://api.clickup.com/api/v2/...`)

- **Token:** ดึงจาก mcp config `~/.claude.json` → `mcpServers.clickup.env.CLICKUP_API_KEY` (อย่า hardcode/commit). ส่งใน header `Authorization`
- **ข้อความไทย:** ส่งผ่าน **`python json.dumps`** (`PYTHONIOENCODING=utf-8`, print เป็น ASCII กัน cp1252 crash) — **ห้าม `curl -d '{...ไทย...}'`** เพราะ shell ทำไทยเพี้ยนเป็น `?????`
- **IDs:** team `90182762652` · space `901811306058` (Project_Trss) · WP-07 evidence list `901818980114`
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

### 4. อัปเดตสถานะ + คอมเมนต์ sprint task
- status → `in progress` / `complete` ตามจริง
- คอมเมนต์สรุปงานที่เพิ่งเสร็จ (สิ่งที่ทำ + เทสที่ผ่าน + ข้อค้างถ้ามี) ผ่าน python
- **verify:** GET comment กลับมาเช็คว่าไทยอ่านได้ ไม่เพี้ยน (ถ้าเพี้ยน = ลบ+โพสต์ใหม่ผ่าน python)
- **เสร็จเมื่อ:** comment ปรากฏอ่านไทยได้ + status ตรงกับงานจริง
