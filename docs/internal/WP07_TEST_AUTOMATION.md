# WP-07 Test Automation

Use this helper to run TPSS tests from VS Code/terminal and append a test-run row to the Google Sheet tab `Automated Test Runs`.

## Google Apps Script

Current Web App URL:

```text
https://script.google.com/macros/s/<YOUR_DEPLOYMENT_ID>/exec
```

The Apps Script token must match `WP07_GOOGLE_SHEET_WEBHOOK_TOKEN` in local `.env`.

## Local `.env`

Add these values to `.env` only. Do not commit real tokens.

```env
WP07_GOOGLE_SHEET_WEBHOOK_URL=https://script.google.com/macros/s/<YOUR_DEPLOYMENT_ID>/exec
WP07_GOOGLE_SHEET_WEBHOOK_TOKEN=put-the-same-token-from-apps-script-here
```

## Commands

Run M11 Feature test and record to WP-07:

```bash
npm run wp07:test:m11:feature
```

Run M11 Playwright smoke and record to WP-07:

```bash
npm run wp07:test:m11:e2e
```

Run any command:

```bash
npm run wp07:test -- --module=M6 --type=Feature --command="php artisan test --filter=Workload"
```

Dry run without posting to Google Sheet:

```bash
npm run wp07:test -- --module=M11 --type=Feature --dry-run --command="php artisan test --filter=M11ApprovalTest"
```

## Central sink: `Automated Test Runs` (atomic, 1 row = 1 test per run)

The helper now captures **per-test** results so every downstream sheet can be derived by filtering. It injects a per-test reporter automatically:

- PHPUnit / `php artisan test` -> `--log-junit <tmp.xml>` (parses each `<testcase>`)
- Playwright -> `--reporter=json` via `PLAYWRIGHT_JSON_OUTPUT_NAME` (parses each spec)
- Unknown command -> still writes one run-level summary row

16 columns written to `Automated Test Runs`:

| # | Column | Source |
|---|--------|--------|
| 1 | Timestamp | Apps Script (`new Date()`) |
| 2 | Test Run ID | helper (groups a run) |
| 3 | Branch | git |
| 4 | Commit | git |
| 5 | Environment | `--env`, default `local` |
| 6 | Command | `--command` |
| 7 | Module | `--module` |
| 8 | Test Type | `--type` |
| 9 | Framework | phpunit / playwright |
| 10 | Suite/Class | per test |
| 11 | Test Name (Case ID) | `Class::method` / `file › title` |
| 12 | Case Status | passed / failed / skipped |
| 13 | Duration (ms) | per test |
| 14 | Error | failure message (truncated 300) |
| 15 | Evidence | `--evidence` |
| 16 | Notes | `exitCode` + parser |

## Apps Script

Deploy `docs/internal/wp07-apps-script.gs` as the Web App (see header of that file). Token lives in Script Properties `WP07_TOKEN` (must equal `.env` `WP07_GOOGLE_SHEET_WEBHOOK_TOKEN`).

## Downstream sheets — derive by formula (do not write by script)

Columns map: A=Timestamp B=RunID C=Branch D=Commit E=Env F=Command G=Module H=TestType I=Framework J=Suite K=TestName L=CaseStatus M=Duration N=Error O=Evidence P=Notes.

- **Test_Execution_Log** (run-level pass/fail per run):
  ```
  =QUERY('Automated Test Runs'!A2:P, "select B, max(A), max(C), max(D), max(G), max(H), count(K) group by B pivot L", 0)
  ```
- **Fail_Case_Record** (raw failures only — analysis columns stay manual):
  ```
  =FILTER('Automated Test Runs'!A2:P, 'Automated Test Runs'!L2:L="failed")
  ```
- **Unit_Test_Cases** (catalog of every test seen — add a manual `Automated Test Name` column to map to your `UT-*` IDs):
  ```
  =UNIQUE('Automated Test Runs'!K2:K)
  ```
  Last status per test:
  ```
  =LOOKUP(2,1/('Automated Test Runs'!K:K=<TestName cell>),'Automated Test Runs'!L:L)
  ```
- **Dashboard_Summary**: `=COUNTIF('Automated Test Runs'!L:L,"failed")`, etc.
- **TestCase_Master** and **Defect_Register**: manual (human-authored design / triage). Link back with Test Run ID + Test Name.

## Automated_Catalog — auto-built list of every automated test (from the sink)

This is the "what tests exist" view, derived from execution rows (no script). It lists each unique automated test once with its latest status. Prerequisite: run the full suite once so every test name lands in `Automated Test Runs` (`php artisan test` for all PHPUnit; one full Playwright run for E2E).

New tab `Automated_Catalog`, headers in row 1:
`Test Name | Framework | Suite/Class | Test Type | Runs | Fails | Last Run | Last Status`

Formulas (A2..H2 — they spill down). Use `XLOOKUP(..., 0, -1)` (exact match, search last-to-first = latest run); ranges start at row 2 to skip the header:
```
A2 =SORT(UNIQUE(FILTER('Automated Test Runs'!K2:K,'Automated Test Runs'!K2:K<>"")))
B2 =MAP(A2:A,LAMBDA(n,IF(n="","",IFERROR(XLOOKUP(n,'Automated Test Runs'!K2:K,'Automated Test Runs'!I2:I,"",0,-1),""))))
C2 =MAP(A2:A,LAMBDA(n,IF(n="","",IFERROR(XLOOKUP(n,'Automated Test Runs'!K2:K,'Automated Test Runs'!J2:J,"",0,-1),""))))
D2 =MAP(A2:A,LAMBDA(n,IF(n="","",IFERROR(XLOOKUP(n,'Automated Test Runs'!K2:K,'Automated Test Runs'!H2:H,"",0,-1),""))))
E2 =MAP(A2:A,LAMBDA(n,IF(n="","",COUNTIF('Automated Test Runs'!K2:K,n))))
F2 =MAP(A2:A,LAMBDA(n,IF(n="","",COUNTIFS('Automated Test Runs'!K2:K,n,'Automated Test Runs'!L2:L,"failed"))))
G2 =MAP(A2:A,LAMBDA(n,IF(n="","",IFERROR(LET(d,XLOOKUP(n,'Automated Test Runs'!K2:K,'Automated Test Runs'!A2:A,"",0,-1),TEXT(d,"dd/mm/")&(YEAR(d)+543)&TEXT(d," hh:mm")),""))))
H2 =MAP(A2:A,LAMBDA(n,IF(n="","",IFERROR(XLOOKUP(n,'Automated Test Runs'!K2:K,'Automated Test Runs'!L2:L,"",0,-1),""))))
```
- `G2` (Last Run) shows Buddhist year (Gregorian stored in the sink, +543 for display — matches the project's "store ค.ศ. / show พ.ศ." rule). For Thai month names instead of numeric, swap `TEXT(d,"dd/mm/")` for a `CHOOSE(MONTH(d), ...)` lookup.
- Do not use the `LOOKUP(2,1/(K:K=n),...)` idiom with whole-column refs — it can match the header row.
- To map this to the manual `Unit_Test_Cases` (UT-* IDs), add an `Automated Test Name` column there and `XLOOKUP` against the sink.

## Notes

- The command exit code is preserved. If the test fails, this helper exits with a failed status after recording rows.
- `--dry-run` prints the payload (with per-case rows) without posting.
- Run-level totals are computed from the per-case rows when available; otherwise the console summary is parsed as a fallback.
- Attach heavy evidence (screenshots, traces, `playwright-report`) to the matching ClickUp evidence task when needed.
