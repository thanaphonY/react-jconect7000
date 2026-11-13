# Wave 6 PO

PHP: http://127.0.0.1/reports and http://127.0.0.1/reports/files
React: http://127.0.0.1:5173/reports and http://127.0.0.1:5173/reports/files

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Open report templates | Templates lists Temperature Report, updated 03/10/2026 16:24 | The same template and the same updated time | same |
| Open previously exported files | 27 files. First page starts with test_20261003_161119.pdf, Temperature Report, PDF, exported 03/10/2026 16:11, then 160850, 160503, 160204, 160026 | The same file names, template, and exported times in the same order. Download of file 964 returns the PDF (95,605 bytes, header %PDF) | same |
| Stack rules | PHP on port 80 still opens Reports | Routes are under `/api/v1`. Files are read from the existing export folder. No new columns. No Prisma. `dataedge` and `alarm-engine` were not edited | same |

The report builder, schedules, and new exports stay on PHP. Opening the saved templates and the files already exported works.

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
