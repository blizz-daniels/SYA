# Milestone 6 — Communications, privacy and backups

Date: 2026-11-18. The owner authorized Milestone 6 after Milestone 5 delivery and previously reported Nigerian privacy review completed. **Milestones 1–6 are implemented; Milestone 7 is not started.** No live InfinityFree deployment, production configuration change, real campaign or third-party transfer was performed by the assistant. Local messages used synthetic addresses and a private capture transport.

## A. Implementation summary

- Optional newsletters with four categories, separate double opt-in, notice/consent snapshots, preferences, single-use expiring links, unsubscribe, subscriber statistics, draft campaign creation, Super Admin review/approval, stale-draft protection, bounded queue preparation and explicit single-recipient sends. Applications never create subscriptions. Recipient identities/sending controls are Super Admin only; editors can author drafts. Opt-outs/preferences are rechecked before dispatch, and already-processing mail cannot be recalled.
- Managed contact inbox with random references, inquiry categories, notice acknowledgment, CSRF/honeypot/persistent rate limits, Super Admin assignment, assigned-only staff access, private notes, reply drafts/separate sends, history, search/status filters and UTC follow-ups. No administrator email login/account field is introduced. Portal reminders replace assumptions about background notifications.
- Email-verified privacy requests and review history, configurable response-date reminders, staff-review access JSON, legal holds, selected-record safeguards, retention candidates, bounded missing-date initialization, deliberate anonymization and physical attachment-deletion jobs. Selected production records need a matching verified request and additional contractual-policy review. Archived CMS content can be exceptionally scrubbed after identity/legal review; unreferenced optimized media can be removed separately. Private evaluations/notes are not released to applicants.
- Encrypted database/config/private-file backup parts, short write leases and an explicit bounded maintenance pause, per-run custody/restore records, reminders, expiry/deletion controls and a CLI-only authenticated restore tool. No plaintext dump is stored server-side. No cron, worker, Redis, ZIP extension or cloud account is required. Off-host copying is an explicit operator action.
- Optional approved social links, native-share/clipboard/permalink fallback for approved public posts, configuration-output shielding, and readable phone/desktop operations pages. Existing configuration/key/storage are preserved; public communications default disabled until actual reviewed settings and host checks are supplied.

## B. Important files

| Files | Purpose |
| --- | --- |
| `app/communications.php` | Opt-in/tokens, preferences, inquiries, campaign review/queue/send and private reply handling |
| `app/privacy.php` | Verified requests, reviewed exports, retention/hold/anonymization, CMS erasure and cleanup |
| `app/backups.php`, `app/backup-crypto.php` | Bounded snapshot parts, ownership/pause controls, AES-256-GCM and passphrase derivation |
| `app/maintenance.php`, `app/bootstrap.php` | Write leases, mutation pause and safe handling of malformed/echoing config |
| `public/connect.php`, `resources/views/connect.php` | Public contact/newsletter/privacy forms and clean-link confirmation pages |
| `public/operations.php`, `resources/views/operations.php` | Authenticated role-scoped operations portal and private downloads |
| `bin/restore-backup.php` | CLI-only authenticated offline extraction, complete-manifest/file validation and session-version rotation |
| `database/migrations/006_communications_privacy_backups.sql` | Fourteen additive tables and delivery-purpose extension |
| `database/upgrade-milestone-6.sql` | Phone/phpMyAdmin upgrade with exact checksum ledger |
| `config/config.example.php` | Safe disabled communications/backups/social settings without secrets |
| `public/assets/js/share.js`, updated views/styles | Approved-post sharing, public links and accessible operations UI |
| Updated recruitment/production/content helpers | Refuse processing anonymized applications; protect referenced assets during privacy cleanup |
| `tests/operations*`, `backups.cjs`, `privacy-production.cjs` | Synthetic workflow, security, concurrency and actual encrypted-file verification |
| `tests/restore-check.php`, `restore-smoke.cjs`, `config-exposure.py`, `share.cjs` | SQL/file/app recovery, config non-exposure and isolated share UI verification |
| Setup/privacy/recovery guides | Phone configuration/upgrade, operational procedures, off-host custody and recovery |

README, AGENTS, hosting report, admin navigation/permissions, schema/browser/hosting tests and safe fixture cleanup were updated. Real config, active setup, private storage, generated credentials, encrypted/plaintext test backups and screenshots are excluded from Git.

## C. Database changes

Migration 006 adds:

1. `newsletter_subscribers` — independent email/state/preferences and notice/confirmation evidence.
2. `newsletter_events` — confirmation/preferences/unsubscribe history.
3. `newsletter_campaigns` — draft/frozen approved campaign and recipient-preparation cursor.
4. `newsletter_deliveries` — unique campaign/subscriber delivery, state/lease/attempts/log link.
5. `contact_inquiries` — references, submitted content, assignment/status/follow-up and retention dates.
6. `contact_events` — private notes, assignment/status history and explicit reply delivery.
7. `privacy_requests` — reference, email verification, request/review state and reminder date.
8. `privacy_events` — notice acknowledgment, identity/decision/fulfillment evidence.
9. `communication_tokens` — HMAC-hashed expiring single-use confirmation/manage/rights tokens.
10. `privacy_controls` — scoped legal holds and completed anonymization records.
11. `privacy_file_deletions` — bounded private attachment cleanup/retry tracking.
12. `backup_runs` — encrypted-set metadata, expiry, ownership/custody/test notes, salted key check.
13. `backup_parts` — run/part uniqueness, random encrypted object keys, hashes and sizes.
14. `maintenance_writes` — short write leases; stale leases cannot silently bypass backup consistency.

The existing redacted delivery log gains newsletter/confirmation/contact/rights purposes without changing prior values. Total: **43 tables, six migration records**. Existing migrations, recruitment records, CMS revisions, accounts and setup lock are not overwritten by upgrade. Relationships/indexes prevent duplicate recipients/parts and maintain department/inquiry identity boundaries. Private codes remain bcrypt hashes; no admin email or 2FA was added.

The phone upgrade imported twice into an independent existing Milestone 5 fixture: 43 tables/six versions, original setup timestamp, legacy delivery row and two character records preserved. PHP migration/checksum repeats also pass. DDL implicitly commits; backup before import and inspect errors.

## D. Executed tests and actual results

Local environment: PHP **8.4.27**, MariaDB **11.8.6**, Apache **2.4.69**, GD/OpenSSL, Chromium/Playwright and axe. These tests use disposable `*_test` databases and synthetic data. No live Resend messages or hosting credentials were used. Raw results: [milestone-6-test-results.txt](milestone-6-test-results.txt).

| Test | Execution method | Actual result | Status |
| --- | --- | --- | --- |
| PHP syntax | `php -l` over app/public/resources/config/bin/tests | All checked files parse | PASS |
| JavaScript syntax | `node --check` for share script and new workflow/restore/UI suites | Checked scripts parse | PASS |
| Native PDO/schema | `php tests/database.php` | Prepared statements/literal Unicode/metacharacters; exactly 43 tables/six versions | PASS |
| Migration/checksum/repeat | PHP runner twice; phone SQL twice in independent old fixture | Existing data/lock preserved; matching sixth migration | PASS |
| M6 unit guards/crypto | `php tests/operations-unit.php` | 11 checks: invalid topics/email, failed launch gate, unsafe HTTP link, fresh nonce, authenticated round trip, tamper/weak-passphrase rejection | PASS |
| Malformed configuration | `PHP_BINARY=... python tests/config-exposure.py` in isolated copies | Missing PHP tag, unexpected output, parse error and non-array return fail without printing test secret; missing config uses safe example | PASS |
| Newsletter workflow | `AXE=1 node tests/operations.cjs` | Separate opt-in, read-only GET, clean URLs, CSRF, replay/expiry rejection, notice history, preferences/unsubscribe and honeypot | PASS |
| Campaign workflow/authorization | Same suite | Editor drafts, Super Admin approval, stale digest rejection, immutable approved campaign, matching recipients, post-queue opt-out skip, explicit capture delivery; editor cannot view recipient identities | PASS |
| Inbox workflow | Same suite | Inquiry receipt, denied unassigned/cross-staff reads/writes, assignment, private note/reply/history, status/search and UTC follow-up | PASS |
| Rights/privacy | Same suite | Email verification, staff-only reviewed export excluding internal notes, current-code/typed identifier/hold checks, selected-record protection, missing closure/deadline date initialization, linked anonymization and physical attachment cleanup | PASS |
| Anonymized workflow revocation | Same suite | Application identities/answers/reference scrubbed; ordinary evaluation route returns 410; subscription/contact content removed | PASS |
| Production content/media privacy | `node tests/privacy-production.cjs` | Requires verified request/contract review/prior archive; live/historical references prevent image removal; approved erasure scrubs snapshots and deletes orphan variants | PASS |
| Concurrent campaign delivery | `node tests/operations-races.cjs`, two independent PHP processes | One delivery accepted and one captured message under simultaneous send attempts | PASS |
| Encrypted export/pause | `node tests/backups.cjs` | Current-code/key guards, in-flight writer refusal, mutating routes paused, bounded encrypted parts, authorized downloads and completion release | PASS |
| Restore failure boundaries | Same suite plus CLI extractor | Wrong passphrase, modified ciphertext and missing part rejected; incomplete/expired set cannot continue; old cancellation cannot release replacement pause | PASS |
| Backup custody/cleanup/throttle | Same suite | Custody/test records, bounded host-part deletion, cancellation and persistent five-failed-code throttling | PASS |
| Actual file restoration | Same suite, `bin/restore-backup.php` | SQL/private config, multi-chunk attachment and all three JPEG variants reassembled with matching hashes | PASS |
| Actual SQL restoration | Import decrypted SQL into **empty** separate test database, `php tests/restore-check.php` | 43 tables/six versions, no restored leases, session versions rotated; all **57 foreign-key relationships** have no orphan rows; registered attachment bytes agree | PASS |
| Restored application | `node tests/restore-smoke.cjs` against separate source/config/DB/private storage | Eight public pages, fresh admin login, exact-byte authenticated attachment download and private optimized image preview | PASS |
| Responsive/accessibility | Operations suite with axe at 360/768/1440 | 24 public/staff view/viewport checks, zero configured axe violations, no horizontal overflow/page errors, keyboard skip link | PASS |
| Populated confirmation/backup UI | Supplemental headless Playwright/axe at 360/768/1440 | Six valid-token/active-backup checks including download controls, zero axe violations/overflow | PASS |
| M6 browser console | Supplemental Playwright listeners across five public/four staff views | No page/JavaScript/console errors | PASS |
| Approved sharing | `node tests/share.cjs`, isolated native/clipboard stubs | Correct title/permalink, clipboard fallback and cancel feedback; no external share performed | PASS |
| Existing CMS/media/reveals | Content browser/race suites | All review/version/reveal/media/pagination/keyboard/no-JS flows, 39 populated viewport checks and concurrent draft protection | PASS |
| Existing auditions | Production browser/race suites | RSVP/callback/attendance/scoring/privacy flows, 21 viewport checks plus receipt and actual booking/review contention | PASS |
| Existing recruitment | Recruitment browser/editor/settings suites | Dynamic forms/uploads/tracking/retention/closure, 21 viewport checks and private settings/editor fallback | PASS |
| Existing public pages | `AXE=1 node tests/browser.cjs` | Eight pages × 320/360/768/1440; navigation/keyboard/no-JS/reduced-motion/security checks, no console errors | PASS |
| Existing administration | `AXE=1 node tests/admin.cjs` with updated authorized editor Communications module | Setup lock/login/CSV/RBAC/sessions/rate/CSRF/private-code workflows, 15 viewport/module checks | PASS |
| Existing unit/security | Security/recruitment/production/content unit scripts | Prior HTTPS/session/validation/storage/scoring guards remain passing | PASS |
| Shared-root denial | `python tests/hosting.py` and new endpoint probes against local Apache | **32** private paths denied with 403; root redirect/eight pages/assets work; operations requires login, public token endpoint executes PHP without source exposure | PASS |
| Manual privacy channel | Isolated rendered Contact view | Approved existing privacy email is retained as fallback if new contact config is blank | PASS |
| New live-host operations | No actual InfinityFree M6 deployment/access | New mail/leases/OpenSSL/memory/storage/phone downloads/permissions remain account-level checks | UNVERIFIED |
| Real off-host custody/production recovery | Only local encrypted copies/restoration performed | No cloud/off-site transfer or production restoration by agent | UNVERIFIED |
| Windows/physical phone/screen reader | Instructions supplied; no such device session | Not executed | UNVERIFIED |

Development failures found and corrected included request-array serialization/replay test setup, configuration-file output exposure in the malformed-file case, and the missing retention-date edge case for naturally expired roles. Relevant suites were rerun after fixes. The existing audition regression deliberately makes local mail capture unwritable; its protected-log permission warning is expected and its retry passed. No unexpected PHP runtime failure remains in successful final M6 workflows. No failure was counted as passed before correction.

## E. Visual and responsive verification

M6 checks cover newsletter request, preference request, contact request, privacy request and the four staff operation views at **360, 768 and 1440 px** (24 combinations), plus valid confirmation and active-backup detail at the same sizes (six additional combinations; **30 M6 checks total**). Inspected saved phone backup/operations screenshot: labels/actions fit, long instructions wrap, visible focus, consistent film palette and no horizontal overflow. Existing CMS/media adds 39 populated checks; recruitment/auditions each add 21; public regression covers all eight pages at **320, 360, 768 and 1440 px** and administration 15 combinations.

Native forms work without a frontend framework. Public token GET is read-only; POST requires CSRF. Shared post links remain a no-JS fallback; share UI testing stubs native APIs and does not send externally. Automated axe tags target WCAG A/AA/2.1/2.2 checks, not certification. Physical-phone, full screen-reader and Windows behavior remain unverified.

## F. Security observations

Permissions/assignment checks occur in PHP before data display and writes. Subscriber identities and backup/privacy downloads require Super Admin. Prepared SQL, scalar/topic validation, escaped plaintext, CSRF, persistent limits, generic public email responses, hashed tokens, clean redirects, HTTPS sessions and no-store/no-referrer boundaries protect new flows. Internal notes/evaluations are excluded from public status and access review exports. Current private code plus typed identifiers protects irreversible actions; legal holds and selected-production review prevent casual deletion.

Incremental backups use a mutation pause, short per-request leases and run ownership checks. Stale/crashed leases require an operator-confirmed recovery step; expired snapshots cannot resume as valid backups after writes restart. Database/auth metadata explicitly excluded from restore is documented. No external FTP/phpMyAdmin writer is controlled by this application: the operator must pause them. Encryption authenticates part metadata/data and the completion set; private keys/passphrases are not stored in MySQL. Restored auth versions are rotated, but account/consent decisions after the snapshot still require reconciliation.

Initial token URLs can enter hosting/proxy logs despite clean redirects; protect those logs. Email already processing may arrive after opt-out/cancellation, and network uncertainty can require provider-log review before retry. Delivered copies cannot be recalled. App erasure does not delete external provider, contractual, local-export or old backup copies automatically. Free-text rationale can reintroduce unnecessary personal data; staff must keep it minimal. The application is not a legal-compliance certification.

## G. InfinityFree feasibility

No new cloud/runtime/cron requirement is introduced. Resend reuses existing config. New prerequisites are OpenSSL AES-256-GCM, protected writable disk space, ordinary MySQL transactional tables and a short POST preflight connection. Each export step is bounded, but the whole run must finish in 20 minutes with at most 5,000 registered files; oversized rows or unavailable protection/encryption stop the action. See [hosting feasibility](hosting-feasibility.md) and [recovery](recovery.md) for the supervised maintenance export/transfer/offline-encryption alternative. No provider capability, cost, automatic backup or actual off-host copy is invented.

The owner must test actual InfinityFree SQL privileges, resource/session/HTTPS behavior, new communications, phone downloads and restoration procedure. Existing owner-reported M3 delivery/M4 spam-folder receipt does not verify these new workflows. The assistant did not deploy or change production flags.

## H. Local setup

README covers PHP 8.2+/Ctype/PDO MySQL/Fileinfo/GD/OpenSSL, private configuration, database creation/migrations and `php -S 127.0.0.1:8000 -t public`, including Windows 11 PowerShell steps. Only `public/` is a safe PHP built-in document root; `.htaccess` is not applied by that server. Windows execution is unverified. Localhost belongs to the computer running PHP, not the user's phone automatically.

Tests use disposable local `*_test` databases and explicit `SYA_TEST_RESET=YES`. Prepare `tests/prepare-operations.php`, clear the private local mail capture, run operations/production-privacy/backups/campaign-race suites with the documented development-only Playwright/axe environment, then import decrypted SQL into a fresh separate test database and run restore-check/smoke. Do not target real data or real delivery. All generated artifacts/credentials/backup output are ignored/protected. [Phone setup](milestone-6-setup.md), [privacy procedures](privacy-operations.md) and [recovery](recovery.md) explain operation and actual limits.

## I. Outstanding issues and limits

- New live InfinityFree behavior, actual mail placement, physical-phone/Windows/screen-reader execution and real off-host custody/production recovery remain unverified.
- Public communications stay disabled until actual approved notice/contact/version and readiness settings are installed. Existing Resend credentials can be reused, but delivery must be rechecked with the owner's addresses.
- Sends/queue preparation, follow-ups, privacy reviews, cleanup and backup custody are explicit actions; no background worker or automatic cloud transfer is supplied. Replies currently require the contact feature and email readiness to remain enabled.
- Backup parts require all downloads and completion within 20 minutes; minimum one database part per table plus file chunks/manifest can mean many phone downloads. Larger/unsupported hosting cases require the documented supervised alternative, not removal of controls. Offline restoration needs a trusted PHP CLI computer/technician.
- Lists/exports are bounded as documented. Normal closed/deadline retention refresh processes 100 missing dates per action; due lists show 20 per category. Physical cleanup handles 20 files/parts per action. Active/selected/held/production records require their separate reviewed policy.
- Off-host/processor copies, post-snapshot opt-out/erasure/account reconciliation and unregistered external files remain operational responsibilities. No automated legal determination or universal internet erasure is claimed.
- No known blocking failure remains in executed local checks. Milestone 7 final launch/security/integration assessment is still separate.

## J. Next milestone readiness

Milestone 6 is implemented and ready for owner-hosted acceptance with the new services initially gated off. The project now has bounded communication/privacy/backup tools and an actual locally tested recovery path. **Stop here. Milestone 7 requires explicit approval.**
