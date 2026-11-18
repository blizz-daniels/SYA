# Milestone 7 — final integration report

18 November 2026. Status: **local integration/release package verified; final M7 hosted upload and launch evidence pending owner operation**. The owner reports the M6 upload, SQL import and hosted tests passed, and previously reported professional privacy review completed. Those reports are separate from agent-run checks. No hosting-control-panel changes, real applicant notices, campaigns or live database writes were performed.

## A. Implementation summary

Completed a cross-module public application → verified tracking → audition/RSVP → weighted evaluation → separate selection → approved public reveal journey, with private scores and notes withheld. Re-ran earlier milestone workflows and concurrent-write tests. Added a phone-accessible, read-only Super Admin Launch checks page and equivalent redacted CLI diagnostics. It reports BLOCKED and UNVERIFIED evidence rather than claiming configuration flags certify launch.

Fixed unsafe write-preflight failure handling: if a backup write lease cannot be registered, POST mutations now return 503 without saving. Backup/privacy storage and existing authorized attachment downloads work when new recruitment uploads are disabled; that switch still blocks new uploads. Reject control characters in backup passphrases to match offline line-based recovery input. Suppress displayed PHP errors early on web requests. Version static URLs and optionally cache static assets to avoid stale phone-upload styling. Allow configured passwordless local PDO content reads without treating that as acceptable production credentials.

## B. Important files

- `app/readiness.php`, `bin/check-readiness.php`: redacted runtime/schema/configuration diagnostics, explicit evidence gaps.
- `public/operations.php`, `resources/views/operations.php`: authenticated Super Admin launch page; other roles denied, POST rejected.
- `app/maintenance.php`: fail-closed mutation preflight.
- `app/recruitment.php`, `app/backups.php`, `app/privacy.php`, `public/attachment.php`: independent verified private-storage access, preserving upload/auth guards.
- `app/backup-crypto.php`: recoverable printable passphrase validation; cipher format unchanged.
- `app/bootstrap.php`, `app/content-public.php`, `public/.htaccess`: displayed-error suppression, versioned static URLs, local PDO configuration and optional static caching.
- `database/install.sql`: fresh-only six-migration installer with exact checksum ledger, no accounts or credentials.
- `tests/final-security.php`, `final-db.php`, `final-journey.cjs`, `final-browser.cjs`, `hosting.py`: targeted regression, failure injection and launch-page checks.
- `docs/milestone-7-setup.md`, this report, `milestone-7-test-results.txt`, updated README/hosting/recovery notes: phone upgrade, local setup, actual evidence and remaining gates.

## C. Database changes

**No M7 migration or new tables.** Existing M6 installations retain 43 tables and six migration records; import no SQL for this update. Fresh-only `database/install.sql` combines unchanged migrations 001–006 with their exact SHA-256 ledger entries. Imported twice on an empty disposable database: 43 tables, six records, zero administrators, first setup still unlocked. All six ledger hashes were compared with original files. It is not an upgrade or recovery script.

## D. Tests executed

Actual output: [test results](milestone-7-test-results.txt). All local tests below passed after corrections during development; earlier failed development attempts are not reported as successful runs. Runtime: PHP 8.4.27, MariaDB 11.8.6, Apache 2.4.69, Chromium/Playwright and axe. Test data and credentials remained in ignored local artifacts.

| Test | Execution method | Actual result / status |
|---|---|---|
| Syntax and whitespace | `php -l` for 83 non-artifact PHP files; `node --check` public JS; `git diff --check` | PASS |
| PDO/schema/migration replay | `php tests/database.php`; `php bin/migrate.php` against local DB | PASS native prepares, literal UTF-8/metacharacters, 43 tables, six unchanged migrations skipped |
| Fresh database installer | MariaDB import of `database/install.sql` twice; table/admin/setup counts and SHA-256 comparison | PASS, counts described above |
| Broken configuration exposure | `PHP_BINARY=/tmp/php python3 tests/config-exposure.py` | PASS malformed/echoing/invalid configs fail without secret output; missing config uses safe example |
| New hardening checks | `php tests/final-security.php` | PASS backup storage independent of upload switch, new uploads blocked, nonprintable passphrase rejected, asset version present, diagnostic secrets absent, local config blocked by production checker |
| End-to-end integration | `tests/prepare-operations.php`, captured test-mail adapter, `node tests/final-journey.cjs` | PASS single real HTTP application through verified tracking/audition/RSVP/evaluation/selection/reveal; no marketing auto-enrollment or private leakage; existing authenticated attachment still served with uploads disabled |
| Failure injection | Same final journey, temporarily rename local write-lease table, submit mutation, restore table in finally | PASS 503 and no saved item; expected protected PDOException log |
| New launch UI | `node tests/final-browser.cjs` | PASS Super Admin page, 360/768/1440 axe/keyboard/no overflow, POST 405; other-role 403 in journey |
| Administration | Local reset fixture; `AXE=1 node tests/admin.cjs` | PASS setup/replay lock, CSRF, private-code hashing, CSV validation, account revocation, role matrix, session rotation/expiry, throttle and 15 viewport checks |
| Recruitment | Local recruitment fixture; `AXE=1 node tests/recruitment.cjs`; editor/settings browser scripts | PASS eligibility/form snapshots, upload MIME/size, private downloads, duplicate flags, email verification/expiry/rate limits, privacy gates, independent final decisions and 21 viewport checks |
| Auditions/evaluations | Local production fixture; `AXE=1 node tests/production.cjs` | PASS slot/panel/person conflicts, WAT/UTC, private links, RSVP expiry/replay/rescheduling/cancellation, callbacks, weighted immutable evaluations and 21 viewport checks |
| Content/media/reveals | Local content fixture; `AXE=1 node tests/content.cjs` | PASS immutable approval/publishing, scheduling, pagination, image validation/re-encoding/private previews, separate reveal/announcement gates, draft omission and 39 viewport checks |
| Communications/privacy | Local operations fixture; `AXE=1 node tests/operations.cjs`; `privacy-production.cjs` | PASS double opt-in/preferences/unsubscribe, explicit bounded campaign sends, assignment inbox/replies, legal holds/access export/anonymization, production-content erasure dependencies and 24 viewport checks |
| Independent-process races | Two local PHP processes; `production-races.cjs`, `content-races.cjs`, `operations-races.cjs` | PASS one slot/person reservation, stale evaluation/CMS write rejection, one campaign delivery/message |
| Encrypted backup | `node tests/backups.cjs`; actual offline restore tool | PASS bounded encrypted parts, write pause, SQL/config/private multi-chunk file and image recovery, wrong key/tamper/missing-part rejection, expiry/cancel/custody deletion and sensitive-action throttling |
| Restored DB/app | Import recovered SQL into separate DB; copy current source into separate app; `php tests/restore-check.php`; `node tests/restore-smoke.cjs` on port 8102 | PASS 57 FK relationships without orphans, six ledger entries, exact registered attachment bytes; eight pages, fresh login, authorized attachment and private image preview |
| Public website | `AXE=1 node tests/browser.cjs` | PASS all eight pages at 320/360/768/1440, keyboard menu/skip/Escape, no-JS navigation, protected paths, route/method/escaping/header checks; no browser JS/console errors |
| Apache whole-root layout | `python3 tests/hosting.py http://127.0.0.1:8101`, configured local base URL restored afterward | PASS 35 private-path denials, root redirect, eight pages/assets; optional mod_headers static cache checked |
| Mobile budget | `node tests/final-browser.cjs`, Chromium CDP 360px, 150ms/1.5Mbps/4x CPU simulation | PASS home transferred 26,654 bytes (<250,000 budget), local resources only, no autoplay video; laboratory result, not field Core Web Vitals |
| Hosted M6 | Owner report | OWNER-REPORTED PASS upload/import/testing; individual checks not independently observed |
| Hosted M7/TLS/delivery/private paths/cookies | Read-only HTTPS probes and headless browser attempts | UNVERIFIED app behavior: HTTPS probes received hosting JS challenge, browser navigation unavailable; no authenticated live tests |

Fixture reset scripts require local environment and a disposable database ending `_test`. Never run destructive fixtures on production. Browser dependencies are development-only. Existing fixture-specific setup and executable paths are in the test scripts and earlier milestone reports. CLI production-readiness checking intentionally returns a failure for local HTTP/development settings; that verifies its blocker, not a production readiness pass.

## E. Visual and responsive verification

Eight public templates checked at 320, 360, 768 and 1440px. Populated recruitment, production, content, operations and admin workflows checked at 360/768/1440. New launch page checked at the same three sizes. Automated WCAG 2/2.1/2.2 A/AA tags and keyboard interactions passed; no horizontal overflow or unexpected page errors in tested flows. Character reveal tests independently preserve Ayo's silhouette while Isioma is revealed with synthetic, approved fixture content. No test identities are shipped as official film information.

This is not WCAG certification. Physical phone/browser, assistive-technology and final approved-content review remain operator checks. Static home performance is a controlled local budget, not a mobile-network or InfinityFree benchmark.

## F. Security observations

The preflight fail-open condition and upload-switch coupling were corrected and regression-tested. Server-side role/CSRF/attachment controls remain intact; no new public diagnostics or authentication alternatives. The launch page contains no DB password, app key, applicant data or private path. Configuration remains ignored/protected; no live secrets added. Existing normal backup formats remain compatible.

Protected error-log review found deliberate mail-capture permission-denied warnings from delivery-failure tests and the deliberate preflight PDOException. No unexpected fatal error was observed in the completed suites; logs are not claimed warning-free. Production PHP display/log settings still need host enforcement. Stored data, actual host directory denies, delivered cookies and off-host custody depend on the operator's real deployment. Source review and regression tests are not an independent penetration test.

## G. InfinityFree feasibility

See [hosting report](hosting-feasibility.md). No additional runtime, cron, worker, cloud infrastructure or cost introduced. Local Apache proves this layout works with the tested rules; it cannot prove provider configuration. TLS-verified public requests reached InfinityFree's challenge, which prevents treating HTTP 200 as application success or private-file denial. Current account PHP/extensions/limits, outbound mail, browser TLS/cookies and storage protection require actual hosting evidence. Existing M6 successful hosted tests are owner-reported.

## H. Local setup

See [README](../README.md) for Linux/macOS and Windows 11 PowerShell, local MySQL setup, safe configuration and PHP server commands; [M7 phone/local guide](milestone-7-setup.md) explains existing versus fresh installations. Local inspection uses `php -S 127.0.0.1:8000 -t public`. Windows instructions are documented but were not executed on a Windows machine. The assistant's localhost is separate from the owner's phone/computer.

## I. Outstanding issues and limits

No known failing local acceptance regression remains. Final M7 upload, hosted cookie/protection/delivery checks, production off-host restore evidence and owner launch signoff remain outstanding. The owner performs uploads; the assistant has no hosting credentials and did not deploy. Readiness flags and custody notes are not external evidence. No real recruitment/campaign was automatically opened or sent. Final film copy, credits and media require approved production information. Database migrations are not transactionally reversible; preserve backups. Partial uploads can break service, so replace files during a maintenance interval and retain a rollback copy.

## J. Readiness and stopping point

The seven-milestone implementation is packaged for final owner deployment/testing. M7 code and local integration verification are complete; final live launch must remain pending until the owner uploads and records the hosted checks. No eighth milestone, automatic live deployment, further features or launch action is started. Existing M6 installations need no SQL, no new Resend key, and no replacement of the live application key.
