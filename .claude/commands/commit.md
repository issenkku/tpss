# Commit

สร้าง git commit ตามมาตรฐานของโปรเจกต์ TPSS — conventional commit ภาษาไทย + pre-commit checklist

> ใช้ `$ARGUMENTS` เป็นใบ้เนื้อหา message หรือ scope ได้ เช่น `/commit M11 approval flow` — ถ้าไม่ระบุให้ derive จาก diff เอง

## ขั้นตอน

### 1. สำรวจสิ่งที่จะ commit
- รัน `git status` + `git diff` (และ `git diff --staged` ถ้ามีของ stage ไว้แล้ว)
- รัน `git log --oneline -5` ดู style commit ล่าสุดให้ message ใหม่เข้าชุดกัน
- ถ้า working tree สะอาด → แจ้งว่าไม่มีอะไรให้ commit แล้วหยุด

### 2. Pre-commit guards (เตือนก่อน ไม่ commit ทันทีถ้าติด)
- **Branch guard** — รัน `git branch --show-current`. ถ้าอยู่บน `main` ให้เตือน: งานเฟส 2 ทำบน `to-serve` แล้ว merge กลับ main เป็นระยะ — เสนอแตก branch ใหม่ก่อน commit (รอผู้ใช้ยืนยันก่อนเสมอ)
- **Test reminder** — ถ้า diff แตะโค้ด/ฟีเจอร์ (`app/`, `routes/`, `resources/views/`) แต่ **ไม่** แตะ `tests/` → เตือนตามกฎเหล็ก "แก้โค้ด → อัปเดต test" ถามว่าจะ run/อัปเดต test ก่อนไหม
- **Secret scan** — ถ้าเห็น `.env`, password, token, key ใน diff → เตือนและไม่รวมเข้า commit

### 3. ร่าง commit message
- รูปแบบ: `type(scope): สรุปภาษาไทย` — type จาก conventional commit (`feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `style`)
- อ้าง Module/User Story ID ถ้าเกี่ยวข้อง (เช่น M11, M11-02)
- body (ถ้าจำเป็น) อธิบาย "ทำไม" สั้น ๆ เป็น bullet ไทย
- **ต้องลงท้ายทุก commit ด้วย:**
  ```
  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  ```

### 4. Stage + commit
- ถ้า `$ARGUMENTS` หรือผู้ใช้ระบุไฟล์เจาะจง → stage เฉพาะนั้น มิฉะนั้นเสนอ `git add -A`
- แสดง message ที่จะใช้ให้ผู้ใช้เห็นก่อน แล้ว commit ด้วย heredoc
- **ห้าม push** เว้นแต่ผู้ใช้สั่ง — commit อย่างเดียว
- **ห้าม** `--no-verify` / skip hooks — ถ้า hook fail ให้แก้ต้นเหตุ

### 5. รายงานผล
- แสดง `git log --oneline -1` ยืนยัน commit + branch ปัจจุบัน
- ถ้ามี guard ที่ข้ามไป (เช่น commit บน main ตามที่ผู้ใช้ยืนยัน) → ระบุให้ชัด
