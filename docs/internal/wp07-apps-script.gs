/**
 * WP-07 Test Automation — Google Apps Script Web App
 *
 * รับ payload จาก scripts/wp07-record-test-result.mjs แล้วเขียนลงถังกลาง
 * แท็บ "Automated Test Runs" แบบ atomic: 1 แถว = 1 เทส ต่อ 1 รอบ
 * (ถ้า payload ไม่มี cases → เขียน 1 แถวสรุปรอบ)
 *
 * Deploy:
 *  1) Extensions → Apps Script → วางไฟล์นี้
 *  2) Project Settings → Script Properties → เพิ่ม WP07_TOKEN = <ค่าเดียวกับ .env WP07_GOOGLE_SHEET_WEBHOOK_TOKEN>
 *  3) Deploy → New deployment → Web app → Execute as: Me · Who has access: Anyone
 *  4) เอา Web app URL ไปใส่ WP07_GOOGLE_SHEET_WEBHOOK_URL ใน .env
 */

var SHEET_NAME = 'Automated Test Runs';

var HEADER = [
  'Timestamp', 'Test Run ID', 'Branch', 'Commit', 'Environment', 'Command',
  'Module', 'Test Type', 'Framework', 'Suite/Class', 'Test Name', 'Case Status',
  'Duration (ms)', 'Error', 'Evidence', 'Notes',
];

function doPost(e) {
  try {
    var body = JSON.parse(e.postData.contents);

    var expected = PropertiesService.getScriptProperties().getProperty('WP07_TOKEN');
    if (!expected || body.token !== expected) {
      return jsonOut({ ok: false, error: 'invalid token' });
    }

    var sheet = ensureSheet();
    var now = new Date();

    // ข้อมูลระดับรอบ (ซ้ำทุกแถวของรอบนั้น เพื่อให้กรอง/group ได้)
    var base = [
      now, body.testRunId || '', body.branch || '', body.commit || '',
      body.environment || '', body.command || '', body.module || '', body.testType || '',
    ];

    var rows;
    if (body.cases && body.cases.length) {
      rows = body.cases.map(function (c) {
        return base.concat([
          c.framework || '', c.suite || '', c.name || '', c.status || '',
          c.durationMs === 0 ? 0 : (c.durationMs || ''), c.error || '',
          body.evidence || '', body.notes || '',
        ]);
      });
    } else {
      // framework ไม่รู้จัก → เขียน 1 แถวสรุปรอบ
      rows = [base.concat([
        '', '', '(run summary)', body.status || '',
        '', '', body.evidence || '', body.notes || '',
      ])];
    }

    sheet.getRange(sheet.getLastRow() + 1, 1, rows.length, HEADER.length).setValues(rows);

    return jsonOut({ ok: true, written: rows.length, testRunId: body.testRunId });
  } catch (err) {
    return jsonOut({ ok: false, error: String(err) });
  }
}

function ensureSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
  }
  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, HEADER.length).setValues([HEADER]);
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function jsonOut(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * ลบแถวทดสอบ/ตัวอย่างออก เหลือแต่ผลรันจริง
 * รันครั้งเดียวจากหน้า editor: เลือกฟังก์ชัน cleanupTestRows -> Run (ไม่ต้อง deploy)
 * ลบแถวที่ Test Run ID เป็น marker ชั่วคราว: CHECK / VERIFY# / ALLFORMS / DEMO
 */
function cleanupTestRows() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
  if (!sheet || sheet.getLastRow() < 2) return;

  var ids = sheet.getRange(2, 2, sheet.getLastRow() - 1, 1).getValues(); // คอลัมน์ B (Test Run ID) ตั้งแต่แถว 2
  var marker = /-(CHECK|VERIFY\d*|ALLFORMS|DEMO)-/;
  var deleted = 0;

  for (var r = ids.length - 1; r >= 0; r--) { // ลบจากล่างขึ้นบน กัน index เลื่อน
    if (marker.test(String(ids[r][0]))) {
      sheet.deleteRow(r + 2);
      deleted++;
    }
  }

  SpreadsheetApp.getActiveSpreadsheet().toast('ลบแถวทดสอบ ' + deleted + ' แถว');
}
