# Milestone 2 report — Administration and security

Date: 2026-11-17. Authorization: owner explicitly approved moving to the next phase after Milestone 1. Milestones 3–7 remain unimplemented.

## A. What was built

- One private PHP production-office portal with username/private-code authentication. No administrator email or 2FA.
- Four server-enforced roles: Super Admin, Casting Manager, Crew Coordinator and Content Editor. Personalized module navigation, overview and internal notice.
- Strong codes hashed with PHP `password_hash`, bcrypt cost 12, verified with `password_verify`. Length 24–72 printable characters, three character types and at least 12 distinct characters. Fixed work factor across the supported PHP versions; hashes are rehashed when policy changes.
- Native strict-mode, cookie-only sessions; ID rotation on authentication, HttpOnly/Secure cookies, SameSite, inactivity expiry (default 30 minutes) and eight-hour absolute lifetime. Code/role/status updates invalidate existing sessions using an account version checked on every request.
- CSRF protection on every administrator/setup POST, persistent username/IP login limits, setup/confirmation limits, HTTPS enforcement, no-store responses and safe service errors. Untrusted proxy headers do not bypass transport checks.
- Inactive temporary setup template, token-hash verification, transaction-locked singleton setup state and permanent closure. Login is blocked until the activated setup file is removed. Restoring it or deleting Super Admin accounts cannot reopen setup.
- Super Admin account creation, role/status/code management, last-Super-Admin protection and CSV imports. No destructive account-delete UI. Own code changes are available to all roles; self-demotion/self-disable is prohibited.
- CSV structure/size/row/role/strength/duplicate validation, per-row results, password hashing, no retained plaintext upload or secret-bearing results.
- UTC audit events and a paginated Super Admin audit viewer. Private office settings: inactivity timeout and internal plain-text dashboard notice.
- Browser-only setup helper and offline/CLI alternatives; a credential-free SQL import bundle suitable for phpMyAdmin; phone-oriented setup/update instructions.

Casting, crew, content, media and communications portal modules are permission-controlled notices only. No recruitment applications, auditions, evaluations, CMS editing, newsletters, inbox or automated backup tooling was added.

## B. Files and structure

| Files | Purpose |
| --- | --- |
| `public/admin.php` | Private portal entry point, base-path handling and safe errors |
| `app/admin-security.php` | Roles, hashing, transport/session policy, CSRF, limits, auditing and helpers |
| `app/admin-controller.php` | Login/logout, permissions, actions, flash results and page routing |
| `app/admin-actions.php` | Administrator operations, CSV processing and settings |
| `app/admin-setup.php` | Token-protected, one-time transactional initialization |
| `resources/views/admin-*.php` | Shared private layout, forms, dashboard and setup views |
| `public/assets/css/admin.css` | Responsive private-office styles and focus states |
| `public/setup.php.dist` | Inactive temporary entry point; rename only for first setup and delete after success |
| `public/setup-helper.html`, helper JS/CSS | Browser-local random secrets/hash generation; no secret transmission |
| `tools/setup-helper.html`, `bin/generate-secrets.php` | Offline/browser-file and CLI generation alternatives |
| `database/migrations/002_administration.sql` | Administration schema |
| `database/install-milestone-2.sql` | Reviewed, restart-safe SQL bundle with migration checksums; no accounts |
| `config/config.example.php` | Production-safe defaults and empty app-key/token-hash settings |
| `public/.htaccess`, `tools/.htaccess`, `.gitignore` | Protect template/backups/tools and exclude active setup/fixtures/config |
| `tests/admin.cjs`, `tests/setup-race.cjs`, helper/security fixtures | Executed local verification |
| `docs/milestone-2-setup.md` | Phone deployment, configuration, CSV, recovery and local instructions |

`app/database.php` now sets the PDO connection time zone to UTC. The public site and root redirect remain operational. `AGENTS.md` records the current milestone boundary and standing GitHub/ZIP-link preference.

## C. Database changes

Migration `002_administration.sql` adds five InnoDB/utf8mb4 tables:

1. `administrators`: unique normalized ASCII username, password hash, role, active flag, auth version and timestamps; role/active index.
2. `system_state`: singleton permanent setup-completion marker, locked during initialization/account mutation.
3. `auth_rate_limits`: keyed-hash buckets, attempts, expiry index; bounded request-time cleanup without cron.
4. `audit_log`: actor foreign key (set null on removal), event, target, safe details, keyed IP hash and indexed UTC timestamps.
5. `admin_settings`: named private settings, updater foreign key and timestamp.

Together with `schema_migrations`, there are exactly six tables and two migration records. No recruitment schema exists. Migration locking/checksum checks remain in place. DDL is restart-safe rather than transaction-atomic because MySQL implicitly commits DDL.

## D. Tests actually executed

Environment: PHP 8.4.27, MariaDB 11.8.6, Apache 2.4.69, Chromium/Playwright and axe-core. Development runtimes/packages are outside the project. All account/session mutation tests used a local disposable database ending in `_test`; no hosting account data was accessed.

| Test | Execution | Actual result | Status |
| --- | --- | --- | --- |
| PHP syntax | `php -l` on all PHP sources, including local config | No syntax errors | PASS |
| JavaScript syntax | `node --check` on public JS and browser tests | Exit 0 | PASS |
| CLI migrations | `php bin/migrate.php`, repeated | 002 applied; repeat skipped both with schema version 2 | PASS |
| Database assertions | `php tests/database.php` | Native PDO, Unicode/SQL literals, two versions, six tables | PASS |
| SQL import bundle | MariaDB client import into independent empty DB, repeated | Six tables, two ledger records, zero bundled administrator accounts | PASS |
| SQL import lock preservation | Set completed test state, repeat import | Existing completion timestamp unchanged | PASS |
| Setup lifecycle | `tests/admin.cjs` | Bad token, weak code and invalid CSRF rejected; first account created; replay 410; login blocked until setup removed | PASS |
| Concurrent setup | `tests/setup-race.cjs` via two independently running PHP processes | One success, one 410; only one initial account created | PASS |
| Permanent closure | Restore temporary file before/after deleting all throwaway Super Admin rows | Setup still 410; singleton lock retained | PASS |
| Login/session lifecycle | `tests/admin.cjs` | Generic invalid credentials, SQL-like username rejection, login CSRF, session rotation, HttpOnly/SameSite, idle and absolute expiry, logout | PASS |
| Persistent throttling | Repeated failed logins in two new browser contexts | 429 persists across sessions | PASS |
| Permissions | Every non-super role × all ten modules; direct POST create/update/import/settings | Authorized modules 200, other modules/actions 403, no unauthorized accounts | PASS |
| Account revocation | Disable signed-in editor, then request private page and attempt login | Existing session revoked; disabled credentials rejected | PASS |
| Code change and data inspection | Change own code, sign in with new one; inspect test DB | New code works; no fixture plaintext codes/token in data or audits; hashes stored | PASS |
| CSV accepted/rejected rows | Valid row, duplicate, invalid role, weak code, malformed row | Valid row committed; safe row errors, no secrets echoed | PASS |
| CSV bounds | Wrong/empty header, >64 KiB, >100 rows | All rejected without creating accounts | PASS |
| Settings/escaping | Save plain-text notice containing script syntax; invalid CSRF | Notice shown as text; no script element, rejected write unchanged | PASS |
| Administrator responsive/axe | Overview, Administration, Settings, Audit, Profile × 360/768/1440px | 15 combinations, no horizontal overflow, one H1, zero axe violations | PASS |
| Login responsive/axe | Login at 360px | Mobile rendering, no overflow and zero axe violations | PASS |
| Keyboard | Administrator skip link; public skip/menu/Escape tests | Expected focus/navigation | PASS |
| Public regression | `tests/browser.cjs` with AXE=1, eight pages × 320/360/768/1440px | 32 combinations passed; zero axe violations, no JS/console errors | PASS |
| Setup helper | `tests/helper.cjs` | CSPRNG output, correct SHA-256, fresh regeneration, no generation-time requests or browser-storage writes, mobile fit, zero axe violations | PASS |
| Security policy | `php tests/security.php` | Weak/null/overlength codes rejected; bcrypt cost 12; deny-by-default roles; production HTTP and forged forwarded HTTPS rejected; Secure/HttpOnly/SameSite/strict cookie-only settings under server-side HTTPS variables, correct cookie path for an absolute base URL | PASS |
| File exposure | `tests/hosting.py` against Apache | 15 private/template/tool paths 403; root redirect, all public pages and assets work | PASS |
| Credential exclusions | `git check-ignore` and staged-file review | Private config, active setup and generated artifacts excluded | PASS |
| Real InfinityFree administration | No account credentials/access supplied | Not executed | UNVERIFIED |
| Windows, physical phones, screen readers, other engines | Not available | Instructions provided; not executed | UNVERIFIED |
| Real HTTPS browser cookie flow on production | Policy tested locally with server-variable configuration, not live InfinityFree TLS | Not executed on hosting account | UNVERIFIED |

Initial runs found an unwritable **local test** default PHP session directory, PHP’s session-cache header override, and a keyboard-focus issue in the scrollable CSV example. These were corrected (local session-path configuration, explicit cache limiter policy, focusable example). A public image assertion ran before a lazy image decoded under concurrent test load; the test now awaits actual decoding rather than accepting an incomplete image. A helper-test browser-context setup error was also corrected. Final test runs passed; earlier failing runs are not presented as passes.

## E. Visual/accessibility verification

Full-page screenshots were generated for the five implemented portal modules at 360, 768 and 1440px. Representative manual visual inspection covered the mobile overview and desktop administrator management page; a mobile login screenshot was also captured. Forms have explicit labels, visible focus, browser autocomplete hints, readable errors and keyboard-scrollable data/example regions. HTML forms and navigation do not require JavaScript. Automated axe checks supplement this review; they do not certify full WCAG 2.2 AA or screen-reader usability.

## F. Security observations and limits

No administrator credentials or app keys ship in the ZIP. The setup token is represented in config only by SHA-256 of a cryptographically random token. It is entered only in a POST over enforced HTTPS and is never put in a URL, audit record or response. Private codes use bcrypt cost 12; the 72-byte boundary is enforced to prevent truncation. No email/2FA integration was introduced.

Production HTTPS is mandatory even if someone toggles local-only exemptions; exemptions require actual loopback remote address, explicit local environment and both local security flags disabled. Proxy headers are not trusted. Native session persistence and protection must be confirmed on the actual host. TLS cookie configuration was tested, but actual InfinityFree browser cookie delivery is unverified.

Fixed-window account limits can be temporarily exhausted by an attacker; windows expire after 15 minutes. This is an explicit lightweight tradeoff for the small private staff portal. Rate keys and audit IPs are hashed with the protected app key; no raw IPs are retained. Audit logs are application-managed records, not tamper-proof against a database owner. Audit archival/retention and broader privacy/backup operations remain Milestone 6. No bulk deletion/recovery endpoint can reopen setup.

CSV plaintext exists only in PHP’s upload/request memory while being processed, then is discarded; the application keeps no file copies. Provider-level temporary directories, logs and backups need account-level review. Staff must delete their own plaintext CSV copies. Valid rows are committed individually; an infrastructure failure mid-import can leave earlier rows committed, and rerunning safely rejects those usernames.

## G. InfinityFree feasibility

The owner reports that the existing public upload works. Published PHP/database/SSL claims and the shared-host layout remain documented in `hosting-feasibility.md`. This phase uses native PHP, PDO, small SQL tables and normal request-time operations; no cron, shell, email, cloud service or production Node runtime is required. The SQL bundle supports manual phpMyAdmin import.

Actual assigned DB access/privileges, session storage, connecting-IP semantics, TLS server variables, secure-cookie behavior and temporary-upload cleanup remain unverified without account access. If HTTPS/session enforcement fails, do not weaken it; investigate the host or use a conventional PHP host. No paid service was provisioned and no new cost was assumed.

## H. Local and phone inspection

See `milestone-2-setup.md` for exact steps, including Windows 11 and phone File Manager/phpMyAdmin instructions. Public pages still start with `php -S 127.0.0.1:8000 -t public`. Administration additionally needs a configured database, app key and one-time initialization.

Executed test commands used `/tmp/php`, a writable private `/tmp/sya-sessions` directory, and local test servers on ports 8094 (public-root layout) and 8095 (whole-project layout). Optional Node tests used `NODE_PATH` pointing to temporary Playwright/axe packages. The security suite intentionally resets a local `_test` database with `SYA_TEST_RESET=YES`; it must never run against production. Its final replay test deletes all disposable test accounts and retains the lock. No reusable sample admin account exists in the package.

## I. Outstanding items

- Owner must upload files, import SQL, configure private values, activate setup, create the first Super Admin and remove setup before signing in.
- No actual InfinityFree admin login or production TLS/cookie test was possible; this is the main deployment verification outstanding.
- Mobile layout was browser-emulated; physical-phone and assistive-technology review remain unverified.
- Staff-code recovery after all Super Admins lose access requires an authorized database operator; there is intentionally no public recovery bypass.
- There are no known unresolved failures in the executed final local tests. Later-milestone functionality remains intentionally absent.

## J. Next milestone readiness

Milestone 2 provides authentication, permissions, session revocation, account management, audited mutations, migrations and a common portal for Milestone 3. Private recruitment storage, eligibility/forms, applicant tracking and email delivery still require their own design/verification and explicit authorization.

**Stopped after Milestone 2. Milestone 3 has not been started.**
