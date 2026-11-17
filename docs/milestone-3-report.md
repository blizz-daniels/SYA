# Milestone 3 — Casting and crew recruitment

Date: 2026-11-17. The owner authorized the next milestone after reporting that administration works. Only Milestones 1–3 are implemented. Milestone 4 has not been started. Production domain recorded correctly as **seeyoulater.freedev.app**; the owner's live configuration must be preserved.

## A. Implementation

- Department-scoped acting and crew role management: approved description, location, availability, explicit mandatory requirements/preferences, adult age limits, UTC deadlines, draft/publication/closure controls.
- Server-enforced permissions on every read/write/download: Casting Managers manage acting recruitment; Crew Coordinators manage crew recruitment; Content Editors have no applicant access; Super Admins manage both and control final selection decisions.
- Dynamic forms: up to 20 ordered short/long text, selection, checkbox, external-link and private-file questions; required/optional flags, editable selection options, saved-draft preview and reusable department templates. Fixed identity/contact/adult-eligibility/notice fields remain mandatory. Three blank rows support editing without JavaScript; the optional vanilla-JS control adds rows.
- Immutable published form versions and role snapshots. Draft saving pauses the role; republication creates a new version. Submitted applications keep their original version, answers and privacy notice. Stale forms are rejected before accepting files or data.
- Applicant submissions without accounts, cryptographically random 128-bit public references, duplicate flags without automatic rejection, server validation, prepared statements, transactions, CSRF, honeypot and persistent throttling. Successful applications are not rolled back solely because confirmation delivery fails.
- JPG/PNG/WebP/PDF content/size/structure checks, at most three small files, random private `.bin` objects, no original filename retention and authenticated department-authorized downloads with attachment/nosniff headers. No public upload URLs. No audition video hosting or public media publishing.
- Application lists/status filter/pagination; private review notes/history with reviewer/time; public status separate from private final selection. Selection cannot publish or reveal an actor. Retention-review dates are capped at 12 months (or a shorter configured period), not extended by later closure, and separated from selected production records.
- Reference-plus-email tracking with six-digit email verification, hashed codes, ten-minute maximum code validity, five guesses, single use, replacement of older challenges and ten-minute application-scoped access. No applicant accounts. Tracking exposes only original role title, reference, received time and public status; private answers, notes, outcomes and files are never queried for presentation.
- Resend transactional confirmation/tracking/test integration, redacted delivery logs and manual staff-contact fallback. API keys remain in private configuration; browser code contains none. Broader communications/newsletters/inbox remain later milestones.
- Explicit launch gates for approved privacy information, real delivery verification and file-storage protection. SQL upgrade and phone instructions preserve existing accounts/config/application key. Recruitment ships disabled and with no seeded roles.

## B. Main files

| Files | Purpose |
| --- | --- |
| `app/recruitment.php` | Shared role/form validation, launch gates, Resend adapter, private uploads and transactional submissions |
| `app/recruitment-admin.php` | Department guards, role publication/templates, private application reviews and global/test controls |
| `app/recruitment-public.php` | Application, receipt, verification and restricted tracking request handling |
| `public/recruitment.php`, `public/attachment.php` | Public recruitment endpoint and authenticated private downloads |
| `resources/views/recruitment-*.php`, `opportunities.php` | Accessible public forms/tracking, role editor/preview, staff review and launch controls |
| `public/assets/js/recruitment-admin.js` | Optional question-row additions without a frontend framework |
| `storage/.htaccess`, `storage/private/.htaccess`, `access-check.txt` | Protected storage layout and non-sensitive deployment probe |
| `database/migrations/003_recruitment.sql` | Eight normalized recruitment tables |
| `database/upgrade-milestone-3.sql` | Additive phpMyAdmin upgrade with migration ledger/checksum |
| `config/config.example.php` | Safe, disabled Resend/privacy/storage configuration additions |
| `tests/recruitment*.php`, `tests/recruitment*.cjs`, test fixture | Disposable local-only automated verification |
| `docs/milestone-3-setup.md` | Exact phone upload, Resend account reuse, configuration and launch procedure |

Existing administrator/public controllers and views, CSS, database tests, Git ignore rules, README and AGENTS instructions were updated. Private config, active setup, real keys, uploads and generated test artifacts remain excluded from Git.

## C. Database

Migration `003_recruitment.sql` adds:

1. `recruitment_roles` — department, role metadata, draft form, publication version and deadline/closure.
2. `recruitment_form_versions` — composite role/version primary key, immutable schema/role snapshot and publishing administrator.
3. `recruitment_form_templates` — reusable department schemas.
4. `recruitment_applications` — random unique reference, role/version foreign key, necessary applicant data, answers, duplicate flag, separate public/private state, immutable notice acknowledgment and retention due date.
5. `recruitment_attachments` — application foreign key, question ID, random storage key, validated MIME and byte size.
6. `recruitment_events` — private reviewer/status/outcome/note history.
7. `recruitment_tracking_challenges` — application, hashed code, expiry, attempts and use state.
8. `recruitment_delivery_log` — redacted purpose/recipient hash/result/provider acceptance, with no message body/code/key.

Total: **14 tables, three migration records**. InnoDB, utf8mb4, appropriate indexes and foreign keys; native PDO prepares and UTC timestamps. No audition/evaluation/CMS/newsletter/inbox tables were created. The additive SQL bundle does not reset administrator accounts, setup state or existing recruitment data. MySQL DDL requires restart-safe migrations rather than an automatic transactional rollback.

## D. Tests executed and actual results

Local environment: PHP 8.4.27, MariaDB 11.8.6, Apache 2.4.69, Chromium/Playwright and axe-core. All applicant records were clearly synthetic; all destructive fixtures used an explicitly local database ending `_test`. No real emails were sent. No Resend API key/sender was supplied.

| Test | Method | Actual result | Status |
| --- | --- | --- | --- |
| Migration/connectivity | `php bin/migrate.php`, repeated | 003 applied; repeat verified/skipped all three versions | PASS |
| Upgrade import | Imported M2 then M3 SQL into independent DB, repeated M3 | Fourteen tables, three versions; completed setup timestamp unchanged | PASS |
| PHP/JS syntax | `php -l` all PHP; `node --check` public/test JS | No syntax errors | PASS |
| PDO/schema | `php tests/database.php` | Native prepares, Unicode/SQL-literal round trip, correct versions/table set | PASS |
| Form validation | `php tests/recruitment-unit.php` | Ten checks: option counts, unique IDs, allowed types, order, 3-file/20-question caps, unsafe/credential-bearing URL rejection, valid HTTPS links, UTF-8 rejection | PASS |
| Recruitment authorization | `tests/recruitment.cjs` | Department-scoped creation/publication; denied editor/cross-department GET/POST/review/download | PASS |
| Applications | Same suite, real HTTP form/multipart requests | Valid submission/receipt, unpredictable reference, notice/version retention, duplicate flagging without rejection | PASS |
| Upload validation | Same suite | Forged JPG/PHP bytes rejected; >2 MiB rejected; valid PNG accepted despite deceptive filename/browser MIME, stored as random `.bin` | PASS |
| Attachment access | Same suite | Anonymous and wrong department denied; authorized download bytes match, attachment/nosniff headers | PASS |
| Versions/templates | Same suite | Department template saved/copied; other department denied; v1 retained after v2; stale submission 409 | PASS |
| Tracking | Same suite with private local capture transport | Generic request messages, matching email receives code; invalid, expired, replayed and over-attempt codes rejected | PASS |
| Malformed tracking input | Python HTTP POST with invalid UTF-8 reference | Generic response, no SQL/service error | PASS |
| Scope/expiry/privacy | Same suite | Verified access bound to original application despite URL ID; ten-minute grant expiry enforced; no applicant email/answers/private notes/files/outcomes in status response | PASS |
| Tracking rate limits | Same suite | Persistent five-attempt challenge lock and request 429 threshold | PASS |
| Launch gates | Same suite | Global off, privacy off, storage off and email off each prevent application form (410) | PASS |
| Review/final decisions | Same suite | Scoped notes/history; managers cannot set/overwrite selection; Super Admin can; selected retention separate; no public actor reveal | PASS |
| Deadlines/closure | Same suite | Closed and expired roles reject new applications; unselected/pending records retain due dates | PASS |
| New responsive/axe | Seven recruitment views × 360/768/1440px | 21 combinations; no overflow; zero axe violations | PASS |
| Question editor fallback | `tests/recruitment-editor.cjs` | Added/focused row, unique IDs, real draft save; three usable blank rows with JavaScript disabled | PASS |
| Recruitment settings | `tests/recruitment-settings.cjs` | Current-code-protected test/toggle, redacted test-log view, mobile settings axe scan | PASS |
| Administrator regression | `tests/admin.cjs`, AXE=1 | M2 setup/login/permissions/CSV/session/security checks and 15 module/viewport axe combinations passed | PASS |
| Public regression | `tests/browser.cjs`, AXE=1 | Eight pages × four widths (320/360/768/1440), keyboard/reduced-motion/navigation/exposure checks; zero axe violations/console errors | PASS |
| Shared-root protection | `tests/hosting.py` with Apache | Seventeen private/template/storage/migration/tool URLs 403; root/public pages/assets work | PASS |
| Shared-root endpoints | Direct Apache HTTP requests | Recruitment tracking HTML renders; unauthenticated attachment request denied | PASS |
| Existing HTTPS/session policy | `php tests/security.php` | Strict cookies, code policy, transport enforcement and forged proxy rejection still pass | PASS |
| Resend documentation | Retrieved official send-email reference | HTTP 200; endpoint/auth/payload/response contract reviewed | PASS (documentation only) |
| Real Resend/InfinityFree mail delivery | No sender/key or hosting access | Not executed | UNVERIFIED |
| Production private storage/multipart/tracking | Host automated response was a browser-check shell | Must verify in normal browser after upload | UNVERIFIED |
| Physical phone, Windows execution, screen reader, other browsers | Not available | Not executed | UNVERIFIED |

Early runs caught a test helper attempting to fetch CSRF from a deliberately forbidden module, and a missing Fileinfo extension in the local PHP runtime; corrected the helper and loaded Fileinfo locally. The application correctly refused file-role publication while Fileinfo was missing. The existing admin XSS assertion was updated to reject inline script injection while allowing the legitimate new external question-editor script. Departmental GET checks were moved ahead of rendering so authorization failures send the correct HTTP status before any page output. These earlier failures are not described as successful runs.

## E. Visual/accessibility review

Full browser checks covered public opportunity/application/tracking views and staff list/editor/preview/review at 360, 768 and 1440px. Representative screenshots of mobile private review and desktop tracking were visually inspected. Forms use explicit labels, native controls, fieldsets, readable instructions and role-based errors; no client framework or large media. Existing 320px public-site behavior remains tested. Automated scans do not certify complete WCAG conformance; real assistive-technology and device review remain unverified.

## F. Security and limits

HTTPS, CSRF, session separation, native prepares, unpredictable references, keyed code hashes, limits, private-object paths and per-request authorization protect recruitment data. Reference/email/code values use POST; no verification secrets appear in URLs, audit logs or delivery records. Browser status grants rotate their session ID and expire; they cannot be repointed to arbitrary IDs. Public pages expose only published roles and no private actor outcome. Authentication revocation from Milestone 2 also governs file downloads.

Resend calls use a fixed HTTPS destination, TLS verification and no redirects, with bounded transport timeouts. Provider acceptance is distinguished from inbox delivery. Responses are generic but synchronous delivery can have variable timing; limits and unpredictable references reduce enumeration risk, without claiming timing indistinguishability. File checks are not malware scanning, and database/file operations cannot be globally atomic; early rollback attempts remove moved objects. Treat downloaded files as untrusted, preserve protected manual backups and test restoration in the later backup milestone.

Application ages/eligibility are self-declared. The code refuses declared minors but is not identity verification. Notice acknowledgment is not automatically a lawful-basis consent claim. Approved privacy information and operator verification flags are required; flags are not independent proof that legal review, hosting protection or delivery occurred.

No automated erasure/anonymization, backup manager, audition scheduler, evaluation scores, approval CMS, character reveal or newsletter/inbox tools were added. Due dates identify future retention work but do not delete data. Selected records need the separate production/contractual policy. Keep the application key stable; rotating it without a data migration invalidates stored email/code hashes and tracking lookups.

## G. InfinityFree and Resend

The owner reports that existing administration works at the corrected domain. New private storage, host upload limits, Fileinfo, outbound Resend calls, sender verification and full multipart/tracking behavior remain to be confirmed on that account. An automated host request returned a JavaScript/AES challenge, not direct evidence of protection or exposure. Use the normal-browser marker check, owner-controlled synthetic application and email test described in the setup guide. Do not weaken gates if a critical check fails.

Using the existing Resend account for both projects is supported architecturally: separate sending key and verified sender, with account-level sending allowances shared. No external service was provisioned, no paid plan selected and no pricing assumption made. No PHP mail, worker, cron, Redis, Node production runtime or mandatory cloud storage was introduced.

## H. Local and phone inspection

Follow `docs/milestone-3-setup.md` for the exact phone/phpMyAdmin/config procedure. Preserve the already corrected private URL and existing key; import only the new upgrade into the existing Milestone 2 database. Do not recreate the first Super Admin. Keep recruitment disabled until mail/privacy/storage checks succeed, then publish individually approved roles.

Locally, run PHP with the existing `public/` document root, configure a local database, run migrations and supply test/private email settings. The public film pages still render with recruitment disabled. Browser tests require optional development-only Playwright/axe packages. The executed suite used `/tmp/php`, Fileinfo, a private session directory, server port 8097 with `SYA_TEST_MAIL_DIR=/tmp/sya-m3-mail`, and a disposable `_test` database. `SYA_TEST_RESET=YES php tests/prepare-recruitment.php` is destructive to that fixture database and must never run against real data. Windows instructions remain in README; Windows was not available for execution.

## I. Outstanding deployment work

- Owner must import the upgrade, replace files while preserving private configuration/storage, add the separate Resend key and real verified sender, confirm inbox delivery and set verification flags deliberately.
- Final approved Nigerian recruitment privacy notice/contact and a successful normal-browser private-storage check are still needed before accepting real applicants.
- Actual hosted upload limits, transport and end-to-end tracking remain unverified. Recruitment stays closed by default.
- No real provider integration was claimed tested; local capture proves application logic, not Resend account configuration/deliverability.
- Role/template lists currently show the most recent 100 records; application lists paginate, while private history/version views show recent records with older history retained in the database.
- There are no known failing final local tests. Later-milestone systems remain intentionally absent.

## J. Next milestone

The foundation now supplies scoped applicants, immutable submissions, protected attachments, references, tracking and review history for future audition/evaluation work. Milestone 4 can build on those relationships only after explicit approval. No audition sessions, slots, invitations, callbacks or evaluation scoring have been implemented.

**Stopped after Milestone 3.**
