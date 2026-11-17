# Milestone 1 report — 2026-11-17

## A. Implementation summary

Implemented only the foundation and eight public pages: Home, About the Film, Characters, Casting Calls, Join the Crew, Production Updates, Media and Contact. Shared cinematic layout uses the approved palette, local system typography, lightweight decorative CSS artwork and SVG silhouettes. Isioma and Ayo are explicitly male; no actors, credits, dates or production milestones were invented. Unavailable features are labeled; no forms collect data. Home includes every requested preview section. Website remains useful as a public home beyond recruitment.

## B. Important files

| File | Purpose |
| --- | --- |
| `public/index.php` | Public route allowlist, 404/405 responses, security headers |
| `app/bootstrap.php` | Protected central configuration loading, escaping, base URL helpers |
| `app/database.php` | Lazy PDO connection and native prepared statements |
| `config/config.example.php` | Safe settings template; actual `config.php` is ignored |
| `resources/views/layout.php` | Shared accessible navigation and footer |
| `resources/views/pages.php` | Eight public page templates and placeholder notices |
| `resources/views/characters.php` | Reusable anonymous character cards |
| `public/assets/css/site.css` | Palette, typography, layouts, focus and reduced motion |
| `public/assets/js/site.js` | Progressive mobile menu, Escape and focus handling |
| `public/assets/images/` | Resolution-independent silhouette and favicon |
| `public/.htaccess` | Directory listing and dotfile protection for Apache |
| `bin/migrate.php` | CLI migration runner, lock, checksums and ledger |
| `tests/database.php` | PDO, Unicode and schema assertions |
| `tests/browser.cjs` | Responsive, axe, keyboard, route and exposure tests |
| `tests/extra.cjs` | Additional small-screen, reduced-motion and homepage link checks |
| `README.md` | Local/Windows, database, configuration and testing instructions |
| `docs/hosting-feasibility.md` | Hosting evidence, uncertainties and future gates |
| `.gitignore` | Excludes credentials, storage, logs and test artifacts |

Git repository initialized locally. After Milestone 1, the owner authorized committing and synchronizing the project to https://github.com/blizz-daniels/SYA.git. No hosting deployment was performed. Application has no third-party dependencies. Browser testing uses optional development packages installed outside the project.

## C. Database changes

`001_foundation.sql` creates **only `schema_migrations`**: version primary key, SHA-256 checksum, applied timestamp; InnoDB and utf8mb4. No recruitment, user, CMS or communications tables exist. PDO connection is lazy: public pages do not break when the database is unavailable. Tested locally against MariaDB 11.8.6; MySQL 8 execution is unverified.

## D. Tests executed

Runtime: PHP 8.4.27 CLI, MariaDB 11.8.6, Chromium via Playwright, axe-core via `@axe-core/playwright`. Local runtime packages were extracted into `/tmp` because the environment initially had no PHP/database binaries. Initial runtime configuration errors (missing mysqlnd load ordering and database initialization) were corrected before final checks.

| Test | Execution method | Actual result | Status |
| --- | --- | --- | --- |
| PHP syntax | `php -l` on all 10 PHP files, including ignored local config | No syntax errors | PASS |
| JavaScript syntax | `node --check public/assets/js/site.js` | Exit 0 | PASS |
| Local startup | PHP CLI server, document root `public/`, port 8000 | Started successfully; HTTP responses render | PASS |
| Database connectivity and initial migration | `php bin/migrate.php` against local MariaDB | Applied 001; schema version 1 | PASS |
| Migration idempotence | Repeated migration command | Already applied; exactly one entry | PASS |
| Migration integrity | Temporarily changed SQL, invoked runner, restored original in finally block | Changed migration rejected with exit 1; original rerun exited 0 | PASS |
| PDO/UTF-8/SQL literals/schema | `php tests/database.php` | All four assertions passed; only ledger table exists | PASS |
| Public rendering and layout | `tests/browser.cjs`, all eight pages × four widths (320, 360, 768, 1440), height 900 | 32/32 render/layout checks passed | PASS |
| Accessibility automation | axe WCAG 2 A/AA, 2.1 AA and 2.2 AA rule tags across same matrix | Zero axe violations across 32 scans | PASS |
| Keyboard | Playwright Tab/Enter on skip link and mobile menu; Escape restores focus; menu link navigation | All keyboard assertions passed | PASS |
| No-JavaScript fallback | Browser context with JS disabled at 360px | Navigation remains available | PASS in final run |
| Configuration exposure and input handling | HTTP requests for config, app, migrations, Git and CLI paths; invalid and array page parameters; POST and headers | 404 private/invalid paths, 405 POST, safe headers, no echoed script input | PASS in final run |
| Reduced motion and homepage links | `tests/extra.cjs` | No animation with reduced motion; every homepage link HTTP 200; no 320px page overflow | PASS |
| Git ignore | `git check-ignore config/config.php tests/artifacts/home-360.png` | Both ignored | PASS |
| Content review | Read templates and rendered pages | No invented actor identity, release date, production credit or open recruitment claim | PASS |
| Actual InfinityFree account | No hosting access supplied | Not executed | UNVERIFIED |
| Windows 11 execution | No Windows host available | Instructions provided, not executed | UNVERIFIED |
| Screen reader / real phones / other browser engines | Not available in this review | Not executed | UNVERIFIED |

Earlier runs caught and led to corrections for development-server unknown-path fallback, favicon 404, an offscreen skip-link screenshot artifact, missing diagonal-arrow glyphs and title clipping at 320px. These are recorded rather than presenting the earlier runs as clean passes. No browser error was hidden or filtered to make tests pass.

Final browser command exited **0** with `AXE=1`. All 32 page/viewport combinations passed, including responsive image loads, one H1/main, active navigation, no forms, no horizontal overflow, and no visible PHP warnings. Reduced-motion/title-fit assertions passed. Browser JavaScript and console errors: **none**. Raw final output is retained in `docs/browser-results.txt`.

## E. Visual and responsive verification

Full-page Chromium screenshots generated for all eight pages at tested widths. Human visual inspection performed on Home at 320, 360 and 1440px, Characters at 768px and About at 1440px. Character silhouettes and question marks render; mobile cards stack, tablet/desktop cards use two columns. The 320px title clipping discovered during inspection was fixed with adaptive font sizing and covered by a title-width assertion. Automated page checks supplement this representative visual review. This is not a claim of physical-device testing or full WCAG certification.

## F. Security observations

Configuration is physically outside `public/`; only that directory must be served. HTTP exposure probes passed locally. Route selection is allowlisted and output is escaped. PDO uses utf8mb4, exception mode and native prepared statements. CSP prohibits external scripts, framing, objects and form submissions; nosniff, referrer and permissions headers are present. No sessions, cookies, personal data, third-party requests or upload handlers exist. Actual Apache `.htaccess` behavior and production TLS have not been tested. Production PHP must disable displayed errors and protect logs. Future Milestone 2 forms will require a deliberate CSP `form-action` adjustment and CSRF controls.

## G. InfinityFree feasibility

The provider homepage retrieved successfully and advertises PHP 8.4, MySQL 8.0 / MariaDB 11.4, SSL, `.htaccess`, FTP and File Manager. These are documented claims, not account tests. Detailed support retrieval returned 403 or a JavaScript shell. Private storage, connection restrictions, upload limits, email, outbound APIs, cron, execution limits and document-root layout remain unverified. No hosting workaround, external service or cost was introduced. See the separate feasibility report for required checks and alternatives. No currently implemented feature depends on the uncertain email/upload/worker capabilities.

## H. Run and inspect locally

From `see-you-around`, copy `config/config.example.php` to `config/config.php`, set database settings, create a local utf8mb4 database/user, then run:

```sh
php bin/migrate.php
php tests/database.php
php -S 127.0.0.1:8000 -t public
```

Visit http://127.0.0.1:8000/ and use the navigation to inspect all eight pages. Windows 11 PowerShell installation and commands are in README. Never serve the project root.

In this running workspace PHP is available through the temporary `/tmp/php` wrapper and the preview server is already running on port 8000. The local disposable MariaDB instance uses port 3307; its development-only credentials are in the ignored config. These `/tmp` tools and processes are ephemeral; use normally installed PHP/MySQL after moving the project.

## I. Outstanding issues and limits

- Official synopsis, confirmed format, approved biographies, credits, portraits, contact address and social links await the owner.
- Production hosting and actual account restrictions remain unverified; not deployment-ready until those checks are completed.
- No full screen-reader, Windows, physical-device or cross-browser review was performed.
- No performance throttling benchmark or load test was executed. Local CSS/JS/SVG assets total about 10 KB uncompressed; no video or external font downloads exist.
- All excluded features remain intentionally unavailable. There are no application, contact or newsletter forms that pretend to work.

## J. Milestone 2 readiness

The configuration, PDO connection, versioned migration runner, shared layout and route allowlist provide a simple foundation for administration. No speculative future tables or authentication stubs were added. Milestone 2 must add its own secure setup lock, username/private-code authentication, session enforcement, rate limits, CSRF, roles and audit schema. InfinityFree TLS, sessions, protected layout and DB privileges need account verification before administrative deployment.

**Stopped after Milestone 1. Milestones 2–7 have not been implemented and require explicit approval.**

## Shared-hosting entry point update

At the owner's request, a root `index.php` now redirects to `public/index.php` and preserves a string page selection. The public entry point detects its `/public` base path when no explicit base URL is configured. Root `.htaccess` permits only the redirect and public files; private folders also contain independent deny rules. Phone upload instructions are in README.

Verification executed on 2026-11-17: PHP syntax checks passed for both entry points; `python tests/hosting.py http://127.0.0.1:8093` passed against Apache 2.4.69 using a local PHP 8.4.27 backend. All eight pages rendered, the page-preserving 302 redirect worked, public assets loaded, and 13 private URLs returned 403. This verifies the local Apache rules, not InfinityFree account behavior. InfinityFree access tests remain required after upload. No database schema or future milestone features changed.
