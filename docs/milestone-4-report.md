# Milestone 4 — Auditions and evaluations

Date: 2026-11-17. Scope authorized by the owner after reporting that hosted Milestone 3 tests passed. Milestones 1–4 are implemented; **Milestones 5–7 have not been implemented**. No live hosting files, configuration or database were changed by this agent. Privacy/legal review is pending; keep real recruitment closed.

## A. Implementation summary

Delivered staff-managed physical/virtual acting audition sessions, Lagos/UTC scheduling, generated/manual slots, panel and applicant overlap prevention, appointments, callbacks, attendance, private notes and event history. Changes/cancellations invalidate earlier invitation grants and preserve history. Invitations and update/cancellation notices use the existing replaceable Resend adapter and delivery log, one explicit staff send per request. No cron/worker or new account is required.

Applicants preview and confirm/decline through hashed, expiring, single-response invitation links with session/CSRF protection. GET never records a response. Email-verified application tracking shows an explicitly limited current schedule. Private notes, scores, recommendations and final decisions are withheld.

Acting/crew evaluation supports editable criteria, optional weights, 0–10 scores, weighted averages, private recommendations/feedback, published immutable criteria versions, reviewer identity and revision history. Server-side department guards apply. Recommendations never decide recruitment outcomes, change public application status or publish actor reveals. Final decisions stay in the existing authorized Milestone 3 workflow.

## B. Important files

| Files | Purpose |
| --- | --- |
| `app/production.php` | Scheduling transactions, booking conflicts, callbacks, attendance, notification queue and invitation helpers |
| `app/evaluations.php` | Department criteria, immutable assessment versions and reviewer revisions |
| `app/production-controller.php`, `app/production-data.php` | Authenticated module routing, server authorization, bounded queries/pagination |
| `public/production.php` | Private production-office entry point, integrated with existing Casting/Crew navigation |
| `app/audition-public.php`, `public/audition.php` | Applicant invitation preview, secure response and receipt |
| `resources/views/production*.php`, `audition*.php` | Staff scheduling/evaluation and private applicant schedule templates |
| `app/recruitment-public.php`, recruitment/admin layouts | Existing email-verified tracking integration and authorized module links |
| `public/assets/css/admin.css` | Responsive production controls and section navigation |
| `database/migrations/004_auditions_evaluations.sql` | Additive schema migration |
| `database/upgrade-milestone-4.sql` | Phone/phpMyAdmin upgrade with matching checksum ledger |
| `tests/production*`, `tests/prepare-production.php` | Synthetic local workflow, unit and real database-contention checks |
| `tests/database.php`, `tests/hosting.py`, `tests/prepare-recruitment.php` | Updated schema expectations, private-file probes and local fixture cleanup |
| `docs/milestone-4-setup.md` | Phone upgrade, operating instructions, hosted acceptance checks and local testing |

README, hosting report and AGENTS were updated. Existing migration files were not modified. Private config, active setup file, generated fixture credentials, captured mail and screenshots are excluded from Git.

## C. Database changes

Migration 004 adds nine tables: `audition_sessions`, `audition_slots`, `audition_callbacks`, `audition_appointments`, `audition_events`, `audition_notifications`, `evaluation_rubrics`, `evaluation_rubric_versions`, `evaluations`.

Foreign keys/indexes connect existing applications/admins, slots, callbacks, notifications and immutable criteria versions. A nullable unique `active_slot_id` enforces one active reservation. Appointments preserve public snapshots and booking revisions; history retains reviewer/actor identity snapshots. Only a hash of the random invitation token is stored. The existing delivery-log purpose ENUM gains `audition`; existing values remain valid. Schema total: **23 tables, four migration records**. No CMS, subscriber or inbox tables were created.

The existing singleton `system_state` row is locked for scheduling writes, avoiding race-prone check/insert sequences. Evaluation writes lock the application row and reject stale reviewer revisions. Schema DDL implicitly commits; backup and inspect errors before retry. The additive SQL was imported twice into an independent pre-M4 fixture: 23 tables/four versions, existing setup timestamp and a legacy delivery row preserved. The PHP runner was also repeated successfully with checksum verification.

## D. Executed tests and actual results

Environment: disposable local MariaDB **11.8.6**, PHP **8.4.27**, Apache **2.4.69**, Chromium/Playwright and axe. No live applicant records, production credentials or real Resend API calls were used. Raw results are in [milestone-4-test-results.txt](milestone-4-test-results.txt).

| Test | Execution method | Actual result | Status |
| --- | --- | --- | --- |
| PHP syntax | `php -l` over all PHP files under app/public/resources/config/bin/tests | All checked files have no syntax errors | PASS |
| JavaScript syntax | `node --check tests/production.cjs` and race suite | Both scripts parse | PASS |
| Schema/connection | `php tests/database.php` | Native PDO prepares, literal UTF-8/SQL metacharacters, exactly 23 tables/four versions | PASS |
| Migration repeat/checksum | `php bin/migrate.php` twice | Four known versions skipped with matching hashes; schema version 4 | PASS |
| Existing-install upgrade | Local MariaDB import of M2+M3 then M4 SQL twice; inspect ledger/old rows | Setup timestamp unchanged, legacy `receipt/legacy_fixture` delivery preserved; 23 tables/four ledger rows | PASS |
| Date/score/URL validation | `php tests/production-unit.php` | 13 checks: WAT conversion, date/zone rejection, order/weights/exact average, invalid scores/criteria and unsafe canonical URLs | PASS |
| Scheduling/security workflow | Synthetic fixture + `AXE=1 node tests/production.cjs` | Department guards, CSRF, resource/slot/person conflicts, booking edit controls, callbacks, attendance and history verified | PASS |
| Invitation workflow | Same browser suite, local mail capture | Privacy gate, token cleanup, read-only GET/no-JS preview, hashed expiry, one-use response, replay/CSRF denial, confirmation/decline, reschedule/cancellation revocation | PASS |
| Delivery failure/retry | Same suite deliberately removes capture-directory write permission, then restores it | Failed send preserves booking, invalidates unsent token, records failure; retry succeeds | PASS |
| Private evaluations | Same suite | Acting/crew weighted scoring, exact results, reviewer identity, independent reviews/revisions, immutable criteria, stale-write rejection; no automatic final status/selection | PASS |
| Verified tracking/privacy | Same suite | Schedule appears only after email verification; no private notes/scores/callback records/decisions; applicant sessions cannot access staff modules | PASS |
| Concurrent booking/review | `node tests/production-races.cjs` across **two independent PHP processes** against one DB | One active reservation for a contested slot; same-email overlap across roles rejected; one review revision accepted and stale concurrent write rejected | PASS |
| Accessibility/responsive | M4 suite with axe at 360/768/1440 | 21 staff/valid-invitation page-viewport combinations plus confirmation receipt, no axe violations; no horizontal overflow or browser JS/console errors | PASS |
| Keyboard/headers | M4 browser suite | Enter confirms attendance, private skip link works, no-store/no-referrer headers present | PASS |
| Existing security helpers | `php tests/security.php` | Private-code rules, HTTPS/session/proxy-header enforcement | PASS |
| Existing administrator regression | `node tests/admin.cjs`, axe | Setup/lock/login/CSV/RBAC/session/CSRF/rate-limit workflows; 15 viewport/module checks | PASS |
| Existing recruitment regression | `node tests/recruitment.cjs`, axe | Roles/forms/uploads/duplicate/versions/tracking/status/retention/closure; 21 viewport checks | PASS |
| Recruitment editor/settings | `tests/recruitment-editor.cjs`, `tests/recruitment-settings.cjs` | Keyboard question addition/draft persistence/no-JS fallback; delivery settings/log/global switch and mobile axe check | PASS |
| Public website regression | `AXE=1 node tests/browser.cjs` | All eight pages at 320/360/768/1440, keyboard/menu/no-JS/security checks; no browser console errors | PASS |
| Shared-root hosting protection | `python tests/hosting.py` against local Apache serving entire repository, plus new entry-point requests | 20 private probes denied with 403, eight public pages/assets/root redirect work; production redirects to login, invitation executes PHP | PASS |
| Actual InfinityFree M4 upgrade/mail/RSVP | No hosting access or live API key supplied | Not deployed/tested by agent | UNVERIFIED |
| Windows 11/physical phone/screen reader | Instructions supplied; no such device test performed | Not executed | UNVERIFIED |

The deliberate mail-capture permission failure generated expected PHP `file_put_contents ... Permission denied` warnings in the protected local log. No unexpected PHP fatal/runtime errors were observed in the successful final workflow. Earlier development checks found a missing fixture include, a schema-list sort expectation and test timing/stale-capture problems; corrected and rerun successfully. A final keyboard check now waits for form navigation before asserting the receipt. These were test/development failures, not reported as passes before correction.

## E. Visual and responsive verification

Automated M4 checks cover sessions overview, selected session, applicant history, manage booking, role criteria, application scoring and a valid invitation at **360, 768 and 1440 px** widths. Confirmation receipt also passed axe. Public regression covers all eight public pages at **320, 360, 768 and 1440 px**. Existing administrator and recruitment suites cover their own mobile/tablet/desktop combinations. Inspected saved 360 px invitation and evaluation screenshots: text/forms/actions fit the viewport and retain the existing palette/type hierarchy. Long history/criteria pages scroll vertically; booking management is limited to one selected booking. Keyboard response and skip-link checks passed. This is not a WCAG certification or a physical-device/screen-reader review.

## F. Security observations

All staff routes/actions enforce the existing authenticated department permissions; audition scheduling is casting-only. Staff writes and applicant responses require CSRF. Tokens use 32 random bytes, HMAC hashes, expiry, booking version matching and single-response consumption. Configured HTTPS base URLs prevent host-header-derived email links. Public schedule fields use a specific allowlist. Private content is escaped; PDO uses native prepares. Unique slot reservations and database locks were tested under real contention.

Invitation links are bearer secrets: forwarding them grants access until expiry/use/replacement. The first token-bearing request may appear in hosting/proxy access logs even though the application redirects to a clean URL and uses no-referrer/no-store. Protect logs; do not send tokens in support screenshots. External meeting permissions require separate management. Production HTTPS, cookie handling and operational access must be rechecked on hosting.

No automated retention/deletion or backup system was added (Milestone 6). Evaluation and audition history remain sensitive production records. Private notes must contain only necessary information; privacy/legal review is pending. Delivery retries cannot guarantee exactly-once mail after network uncertainty; booking versions revoke obsolete links. Manual pending notices must actually be sent by staff. API acceptance is not proof of inbox delivery.

## G. InfinityFree feasibility

No new hosting dependency was introduced. Ordinary PHP, PDO/InnoDB transactions, sessions, `.htaccess` protection and the existing Resend HTTP adapter remain the architecture. No worker, cron, Redis, Node production runtime or paid infrastructure was added. Owner-reported M3 hosted delivery/tracking/private-storage checks passed; that does not verify new M4 routes, SQL privileges or emails. Local Apache protection and database behavior passed. See [hosting feasibility](hosting-feasibility.md) and the hosted checklist in [the setup guide](milestone-4-setup.md). No provider capability or price beyond available evidence is claimed.

## H. Local setup

README covers prerequisites, ignored configuration, local database creation, `php bin/migrate.php` and `php -S 127.0.0.1:8000 -t public`, including Windows 11 PowerShell steps. Open `http://127.0.0.1:8000/` on that computer; the assistant workspace's localhost is separate. For a phone accessing a hosted copy, follow the additive SQL/ZIP upload guide. PHP's built-in server does not implement `.htaccess`; never expose the repository root with it. Use disposable `*_test` databases for destructive fixtures, local mail capture only, and two independent PHP processes for race tests. Windows execution remains unverified.

## I. Outstanding work and limitations

- Hosted M4 installation, real Resend invitation/update/cancellation receipt and hosted RSVP/concurrency remain to be checked by the owner after upgrade.
- Nigerian privacy review is pending. Do not open real recruitment or assert legal approval based on test success.
- Notices require manual sends/retries. No automatic background dispatch, external meeting creation or crew interview scheduling.
- Lists/history are deliberately bounded for shared hosting; booking lists paginate 20 and only one management form opens. See setup guide for limits.
- No automated backups, restoration/deletion tooling, production CMS/media reveal system or broader communications was implemented.
- No known blocking failure remains in the executed local checks. Physical-device/Windows/screen-reader testing and live-host behavior remain unverified.

## J. Next milestone readiness

Milestone 4 is ready for the owner's hosted acceptance checks. The existing modular PHP architecture, migrations, immutable evaluation history and separate private/public states support later CMS/media/character reveals without coupling them to recruitment decisions. **Stop here. Milestone 5 requires explicit approval.**
