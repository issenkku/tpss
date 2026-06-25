import { spawn } from 'node:child_process';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const DEFAULT_ENVIRONMENT = 'local';

const args = parseArgs(process.argv.slice(2));

if (args.help || !args.command) {
  printHelp();
  process.exit(args.help ? 0 : 1);
}

loadDotEnv();

const webhookUrl = process.env.WP07_GOOGLE_SHEET_WEBHOOK_URL;
const token = process.env.WP07_GOOGLE_SHEET_WEBHOOK_TOKEN;

if (!args.dryRun && (!webhookUrl || !token)) {
  console.error('Missing WP07_GOOGLE_SHEET_WEBHOOK_URL or WP07_GOOGLE_SHEET_WEBHOOK_TOKEN in .env');
  process.exit(1);
}

const startedAt = new Date();
const branch = readGitValue(['branch', '--show-current']);
const commit = readGitValue(['rev-parse', '--short', 'HEAD']);

// ตรวจ framework + แทรก reporter ที่ออกผล "รายเทส" ลงไฟล์ชั่วคราว แล้วค่อยรัน
const framework = detectFramework(args.command);
const caseFile = framework === 'unknown' ? null : join(tmpdir(), `wp07-${framework}-${Date.now()}.${framework === 'phpunit' ? 'xml' : 'json'}`);
const { command: runCommandText, env: runEnv } = augmentRun(args.command, framework, caseFile);

const result = await runCommand(runCommandText, runEnv);

// อ่านผลรายเทสจากไฟล์ reporter (ถ้ามี) → ถังกลางได้ข้อมูลครบคลุม
let cases = [];
if (caseFile && existsSync(caseFile)) {
  try {
    const raw = readFileSync(caseFile, 'utf8');
    cases = framework === 'phpunit' ? parseJUnit(raw) : parsePlaywrightJson(raw);
  } catch (error) {
    console.error(`WP-07: ไม่สามารถอ่านผลรายเทส (${framework}): ${error.message}`);
  } finally {
    rmSync(caseFile, { force: true });
  }
}

// run-level summary: ถ้ามีรายเทสให้สรุปจากรายเทส, ไม่งั้น fallback parse console เดิม
const summary = cases.length ? summarizeCases(cases) : parseTestOutput(result.output, result.exitCode);
const moduleName = args.module || 'UNSPECIFIED';
const testType = args.type || args.testType || 'Automated';
const testRunId = args.id || buildTestRunId(startedAt, moduleName, testType);
const status = result.exitCode === 0 ? 'Pass' : 'Fail';

const payload = {
  token,
  testRunId,
  // เวลาจริงที่รัน (ส่งให้ Apps Script ใช้กับคอลัมน์ Last Run แทนการ parse จาก testRunId)
  recordedAt: startedAt.toISOString(),               // UTC ISO — ให้ Apps Script format เอง (Asia/Bangkok)
  recordedAtLocal: formatBangkok(startedAt),         // พร้อมใช้: "DD/MM/YYYY HH:mm:ss" เวลาไทย
  module: moduleName,
  testType,
  command: args.command,
  branch,
  commit,
  environment: args.env || args.environment || DEFAULT_ENVIRONMENT,
  total: summary.total,
  passed: summary.passed,
  failed: summary.failed,
  skipped: summary.skipped,
  status,
  evidence: args.evidence || '',
  notes: buildNotes(args.notes, result.exitCode, summary),
  // ถังกลาง atomic — 1 แถว/เทส/รอบ (ว่าง = framework ไม่รู้จัก → Apps Script เขียน 1 แถวสรุป)
  cases: cases.map((testCase) => ({
    framework: testCase.framework,
    suite: testCase.suite,
    name: testCase.name,
    status: testCase.status,
    durationMs: testCase.durationMs,
    error: testCase.error,
  })),
};

if (args.dryRun) {
  console.log(JSON.stringify(payloadWithoutToken(payload), null, 2));
  console.log(`\nWP-07 dry-run: ${payload.cases.length} case row(s) parsed from ${framework}`);
} else {
  await postToWebhook(webhookUrl, payload);
  console.log(`Recorded WP-07: ${testRunId} (${status}) — ${payload.cases.length || 1} row(s)`);
}

process.exit(result.exitCode);

function detectFramework(command) {
  if (/playwright\s+test|npx\s+playwright/i.test(command)) {
    return 'playwright';
  }
  if (/artisan\s+test|phpunit/i.test(command)) {
    return 'phpunit';
  }
  return 'unknown';
}

// แทรก reporter ที่ออกผลรายเทส โดยไม่ทำลายคำสั่งเดิม
function augmentRun(command, framework, caseFile) {
  if (framework === 'phpunit') {
    return { command: `${command} --log-junit "${caseFile}"`, env: {} };
  }
  if (framework === 'playwright') {
    // json reporter เขียนลงไฟล์เมื่อ set PLAYWRIGHT_JSON_OUTPUT_NAME (ไม่ปน stdout)
    return { command: `${command} --reporter=json`, env: { PLAYWRIGHT_JSON_OUTPUT_NAME: caseFile } };
  }
  return { command, env: {} };
}

function parseJUnit(xml) {
  const cases = [];
  const caseRe = /<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g;
  let match;

  while ((match = caseRe.exec(xml)) !== null) {
    const attrs = match[1];
    const inner = match[3] || '';
    const name = attr(attrs, 'name');
    const className = attr(attrs, 'classname') || attr(attrs, 'class');
    const time = attr(attrs, 'time');

    let status = 'passed';
    let error = '';
    if (/<failure\b/.test(inner)) {
      status = 'failed';
      error = extractIssue(inner, 'failure');
    } else if (/<error\b/.test(inner)) {
      status = 'failed';
      error = extractIssue(inner, 'error');
    } else if (/<skipped\b/.test(inner)) {
      status = 'skipped';
    }

    cases.push({
      framework: 'phpunit',
      suite: className,
      name: className ? `${className}::${name}` : name,
      status,
      durationMs: time ? Math.round(parseFloat(time) * 1000) : '',
      error,
    });
  }

  return cases;
}

function parsePlaywrightJson(text) {
  const data = JSON.parse(text);
  const cases = [];

  const walk = (suite) => {
    const file = suite.file || '';
    for (const spec of suite.specs || []) {
      let status = spec.ok ? 'passed' : 'failed';
      let durationMs = '';
      let error = '';

      for (const test of spec.tests || []) {
        for (const run of test.results || []) {
          if (typeof run.duration === 'number') {
            durationMs = run.duration;
          }
          if (run.status === 'skipped' && status !== 'failed') {
            status = 'skipped';
          }
          if (run.status === 'failed' || run.status === 'timedOut' || run.status === 'interrupted') {
            status = 'failed';
            error = error || cleanMessage(run.error?.message || '');
          }
        }
      }

      cases.push({
        framework: 'playwright',
        suite: file,
        name: [file, spec.title].filter(Boolean).join(' › '),
        status,
        durationMs,
        error,
      });
    }

    for (const child of suite.suites || []) {
      walk(child);
    }
  };

  for (const suite of data.suites || []) {
    walk(suite);
  }

  return cases;
}

function summarizeCases(cases) {
  let passed = 0;
  let failed = 0;
  let skipped = 0;
  for (const testCase of cases) {
    if (testCase.status === 'failed') failed += 1;
    else if (testCase.status === 'skipped') skipped += 1;
    else passed += 1;
  }
  return {
    total: String(cases.length),
    passed: String(passed),
    failed: String(failed),
    skipped: String(skipped),
    source: 'per-case',
  };
}

function attr(attrs, key) {
  const found = attrs.match(new RegExp(`${key}\\s*=\\s*"([^"]*)"`, 'i'));
  return found ? decodeXml(found[1]) : '';
}

function extractIssue(inner, tag) {
  const withMessage = inner.match(new RegExp(`<${tag}\\b[^>]*\\bmessage\\s*=\\s*"([^"]*)"`, 'i'));
  if (withMessage) {
    return cleanMessage(decodeXml(withMessage[1]));
  }
  const body = inner.match(new RegExp(`<${tag}\\b[^>]*>([\\s\\S]*?)<\\/${tag}>`, 'i'));
  return body ? cleanMessage(decodeXml(body[1])) : '';
}

function cleanMessage(value) {
  return value.replace(/\s+/g, ' ').trim().slice(0, 300);
}

function decodeXml(value) {
  return value
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&#x([0-9a-fA-F]+);/g, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
    .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(parseInt(dec, 10)))
    .replace(/&amp;/g, '&');
}

function parseArgs(argv) {
  const parsed = {};

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];

    if (!arg.startsWith('--')) {
      continue;
    }

    const equalsIndex = arg.indexOf('=');
    const hasInlineValue = equalsIndex !== -1;
    const rawKey = hasInlineValue ? arg.slice(2, equalsIndex) : arg.slice(2);
    const inlineValue = hasInlineValue ? arg.slice(equalsIndex + 1) : undefined;
    const key = rawKey.replace(/-([a-z])/g, (_, char) => char.toUpperCase());

    if (inlineValue !== undefined) {
      parsed[key] = inlineValue;
      continue;
    }

    const nextValue = argv[index + 1];
    if (nextValue && !nextValue.startsWith('--')) {
      parsed[key] = nextValue;
      index += 1;
    } else {
      parsed[key] = true;
    }
  }

  return parsed;
}

function loadDotEnv() {
  const envPath = '.env';

  if (!existsSync(envPath)) {
    return;
  }

  const lines = readFileSync(envPath, 'utf8').split(/\r?\n/);

  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) {
      continue;
    }

    const separatorIndex = trimmed.indexOf('=');
    const key = trimmed.slice(0, separatorIndex).trim();
    let value = trimmed.slice(separatorIndex + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    if (key.startsWith('WP07_')) {
      process.env[key] ??= value;
    }
  }
}

function runCommand(command, extraEnv = {}) {
  return new Promise((resolve) => {
    const child = spawn(command, {
      shell: true,
      stdio: ['ignore', 'pipe', 'pipe'],
      env: { ...process.env, ...extraEnv },
    });

    let output = '';

    child.stdout.on('data', (chunk) => {
      const text = chunk.toString();
      output += text;
      process.stdout.write(text);
    });

    child.stderr.on('data', (chunk) => {
      const text = chunk.toString();
      output += text;
      process.stderr.write(text);
    });

    child.on('close', (exitCode) => {
      resolve({ exitCode: exitCode ?? 1, output });
    });
  });
}

function parseTestOutput(output, exitCode) {
  const normalized = output.replace(/\[[0-9;]*m/g, '');
  const jsonSummary = parseJsonSummary(normalized);
  if (jsonSummary) {
    return jsonSummary;
  }

  const result = {
    total: '',
    passed: '',
    failed: exitCode === 0 ? '0' : '',
    skipped: '',
    source: 'generic',
  };

  const phpunitTests = normalized.match(/Tests:\s+([^\n]+)/i);
  if (phpunitTests) {
    const line = phpunitTests[1];
    const total = line.match(/(\d+)\s+tests?/i);
    const passed = line.match(/(\d+)\s+passed/i);
    const failed = line.match(/(\d+)\s+failed/i);
    const skipped = line.match(/(\d+)\s+skipped/i);

    result.total = total?.[1] || '';
    result.passed = passed?.[1] || (exitCode === 0 ? result.total : '');
    result.failed = failed?.[1] || (exitCode === 0 ? '0' : result.failed);
    result.skipped = skipped?.[1] || '0';
    result.source = 'phpunit';
    return result;
  }

  const playwrightSummary = normalized.match(/(\d+)\s+passed(?:\s+\(([^)]+)\))?/i);
  if (playwrightSummary) {
    const passed = playwrightSummary[1];
    const failed = normalized.match(/(\d+)\s+failed/i);
    const skipped = normalized.match(/(\d+)\s+skipped/i);

    result.passed = passed;
    result.failed = failed?.[1] || '0';
    result.skipped = skipped?.[1] || '0';
    result.total = String(Number(result.passed) + Number(result.failed) + Number(result.skipped));
    result.source = 'playwright';
    return result;
  }

  return result;
}

function parseJsonSummary(output) {
  const lines = output
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  for (const line of lines) {
    if (!line.startsWith('{') || !line.endsWith('}')) {
      continue;
    }

    try {
      const data = JSON.parse(line);

      if (data.tool !== 'phpunit' && data.tool !== 'playwright') {
        continue;
      }

      const total = Number(data.tests ?? data.total ?? 0);
      const failed = Number(data.failed ?? 0);
      const skipped = Number(data.skipped ?? 0);
      const passed = Number(data.passed ?? Math.max(total - failed - skipped, 0));

      return {
        total: total ? String(total) : '',
        passed: String(passed),
        failed: String(failed),
        skipped: String(skipped),
        source: `${data.tool}-json`,
      };
    } catch {
      // Continue looking for a parseable summary line.
    }
  }

  return null;
}

function readGitValue(args) {
  try {
    return execFileSync('git', args, { encoding: 'utf8' }).trim();
  } catch {
    return '';
  }
}

function buildTestRunId(date, moduleName, testType) {
  const stamp = date.toISOString().slice(0, 19).replace(/[-:T]/g, '').slice(0, 12);
  const safeModule = moduleName.replace(/[^A-Za-z0-9]+/g, '').toUpperCase() || 'TPSS';
  const safeType = testType.replace(/[^A-Za-z0-9]+/g, '').toUpperCase() || 'AUTO';

  return `TR-${stamp}-${safeModule}-${safeType}`;
}

function formatBangkok(date) {
  // "DD/MM/YYYY HH:mm:ss" เวลาไทย (Asia/Bangkok) — ปี ค.ศ. (Apps Script +543 ได้ถ้าต้องการ พ.ศ.)
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Bangkok',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
  }).formatToParts(date).reduce((acc, part) => {
    acc[part.type] = part.value;
    return acc;
  }, {});

  return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}:${parts.second}`;
}

function buildNotes(notes, exitCode, parsed) {
  const parts = [];

  if (notes) {
    parts.push(notes);
  }

  parts.push(`exitCode=${exitCode}`);

  if (parsed.source) {
    parts.push(`parser=${parsed.source}`);
  }

  return parts.join(' | ');
}

async function postToWebhook(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  const text = await response.text();

  if (!response.ok) {
    throw new Error(`Google Apps Script returned HTTP ${response.status}: ${text}`);
  }

  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`Google Apps Script returned non-JSON response: ${text}`);
  }

  if (!data.ok) {
    throw new Error(`Google Apps Script rejected payload: ${JSON.stringify(data)}`);
  }
}

function payloadWithoutToken(payload) {
  const { token: _token, ...safePayload } = payload;

  return safePayload;
}

function printHelp() {
  console.log(`
Usage:
  node scripts/wp07-record-test-result.mjs --module=M11 --type=Feature --command="php artisan test --filter=M11ApprovalTest"

Required .env:
  WP07_GOOGLE_SHEET_WEBHOOK_URL=https://script.google.com/macros/s/.../exec
  WP07_GOOGLE_SHEET_WEBHOOK_TOKEN=the-same-token-from-apps-script

Options:
  --module       Module or requirement ID, e.g. M11
  --type         Test type, e.g. Feature, Unit, E2E, Regression
  --command      Test command to run
  --env          Test environment label, default: local
  --evidence     Evidence path/link, e.g. playwright-report
  --notes        Extra notes sent to Google Sheet
  --id           Override generated Test Run ID
  --dry-run      Run command and print payload (incl. per-case rows) without posting

Per-case capture:
  phpunit    -> injects --log-junit <tmp> and parses each <testcase>
  playwright -> injects --reporter=json (PLAYWRIGHT_JSON_OUTPUT_NAME) and parses each spec
  unknown    -> still records one run-level summary row
`);
}
