# See You Around — Milestones 1–5

Lightweight PHP public website. Milestones 1–5 are implemented: the public website, production-office security, casting/crew recruitment, auditions/evaluations, and the content/media/reveal studio. Recruitment defaults closed until the deployment’s privacy, private-storage and Resend delivery checks are satisfied. Newsletters, the contact inbox, retention/deletion and automated backup tools remain later milestones. The illustrative hero is concept artwork, not a production still; promotional copy is provisional. Official synopsis, format, credits, actor identities and production dates await approval.

## Requirements

PHP 8.2+ (tested: 8.4.27), Ctype, PDO and pdo_mysql, native sessions, Fileinfo for attachments, GD for private image optimization, and OpenSSL/HTTPS transport (cURL recommended); MySQL 8+ or MariaDB 10.6+ (tested: MariaDB 11.8.6), modern browser. No Composer, Node or external runtime dependencies for the application. PHP built-in server is for local use only.

## Content, media and reveals upgrade (Milestone 5)

Follow [the Milestone 5 phone guide](docs/milestone-5-setup.md): back up, import `database/upgrade-milestone-5.sql` into the existing Milestone 4 database and replace supplied files while preserving private configuration/key/storage. No new Resend key is required. Text uses immutable revisions and Super Admin publication approval; images stay private until approved publication, and actor reveals require a separate explicit action and published announcement. Scheduled visibility is evaluated on requests without cron. [Actual results and limitations](docs/milestone-5-report.md).

## Auditions and evaluations upgrade (Milestone 4)

Use [the Milestone 4 phone upgrade guide](docs/milestone-4-setup.md). Back up, import `database/upgrade-milestone-4.sql` into the existing Milestone 3 database, and preserve your live private configuration/application key/storage while replacing application files. Reuse the existing Resend settings and configure the complete HTTPS base URL. Invitations and update/cancellation notices require individual staff sends; there is no background worker. The owner reports privacy review completed; preserve the actual approved notice and live launch settings. See [actual Milestone 4 results](docs/milestone-4-report.md).

## Recruitment upgrade (Milestone 3)

Use [the phone upgrade guide](docs/milestone-3-setup.md). Import `database/upgrade-milestone-3.sql` into the existing Milestone 2 database, preserve `config/config.php` and its application key, add the Resend/privacy/storage settings, and verify the launch gates before opening roles. The correct domain is **seeyoulater.freedev.app**.

## Production office setup

**Using a phone? Follow [Milestone 2 setup instructions](docs/milestone-2-setup.md)** for the SQL import, private configuration, one-time setup and login. Uploading files alone does not create an administrator or configure your database. The setup file ships inactive.

## Get the files

Install Git and PHP 8.2+ on your own computer. Clone the project and enter its directory:

```sh
git clone https://github.com/blizz-daniels/SYA.git
cd SYA
```

The commands below run on your own computer. A server started in the assistant workspace is separate from your computer: its localhost address is not automatically available on your machine. For later updates, run `git pull --ff-only` from this folder before starting the server. Keep local credentials in the ignored configuration file.

## Local setup (Linux/macOS)

From this directory:

```sh
cp config/config.example.php config/config.php
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000/ . The public film pages work without database credentials when recruitment is disabled. Recruitment and administration require database configuration. Never serve the project root: only `public/` may be the document root.

## Windows 11

Install a supported PHP build and MySQL/MariaDB, or use a local PHP stack such as XAMPP with an appropriate supported PHP version. Add PHP to PATH. In PowerShell, enter this project directory:

```powershell
Copy-Item config/config.example.php config/config.php
php -v
php -m
php -S 127.0.0.1:8000 -t public
```

Check `PDO` and `pdo_mysql` appear in `php -m`; enable `extension=pdo_mysql` in the loaded php.ini if needed. Start your MySQL/MariaDB service separately. The PHP CLI server avoids changing Apache configuration. These Windows instructions have not been executed on Windows in this environment.

## Database setup

Create a local empty database and a dedicated user with a strong local password (replace the example below):

```sql
CREATE DATABASE see_you_around CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sya'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_LOCAL_SECRET';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
ON see_you_around.* TO 'sya'@'127.0.0.1';
```

Set host, port, name, username and password in `config/config.php`. For local stacks that use `localhost`, ensure the database user host matches. Then:

```sh
php bin/migrate.php
php bin/migrate.php
php tests/database.php
```

The runner applies all migration files in order; subsequent executions skip applied files after checking their checksums. Milestone 1 creates `schema_migrations`; Milestone 2 adds administrators, the permanent setup lock, login limits, audit events and private settings. The runner obtains a MySQL advisory lock, uses prepared ledger queries and rejects modified applied migrations. MySQL DDL implicitly commits; migrations must be restart-safe, and failures require inspection before retry. There is no automatic rollback. Back up before future schema changes. `tests/database.php` expects the 29-table Milestone 5 schema and five migration records.

## Configuration

`config/config.php` is the central configuration, outside the web root and ignored by Git. The safe example provides defaults for public rendering only. `base_url` is empty for a root installation; for a subdirectory use e.g. `/film` (without a trailing slash). No host header is used to build links. Recruitment/email/storage settings are documented in the milestone setup guides. Audition email requires a complete HTTPS base URL, e.g. `https://seeyoulater.freedev.app/public`, rather than an empty or relative value. Session settings are enforced by the administrator portal. Keep debug off in production; configure PHP `display_errors=Off` and protected error logging at the server level. No production credentials are provided. Keep the application key stable: recruitment email hashes and verification-code hashes depend on it. Do not change feature flags to imply unimplemented functionality exists.

## Structure

- `public/index.php`: route allowlist, security headers, method handling.
- `public/assets/`: local CSS, vanilla JS and vector placeholders.
- `app/bootstrap.php`: configuration, escaping and URL helpers.
- `app/database.php`: lazy PDO connection with native prepared statements.
- `resources/views/`: shared layout, eight public pages and character component.
- `database/migrations/`: version ledger and additive milestone schemas.
- `app/content*.php`, `public/content.php`: lightweight content workflow and private studio.
- `public/media.php`: authorization-checked optimized image serving; protected storage holds image variants.
- `bin/migrate.php`: CLI-only migrations.
- `tests/`: database and optional browser checks.
- `docs/`: hosting feasibility and actual milestone report.

## Verification

Lint all PHP files with `php -l` (PowerShell: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`). Run `node --check public/assets/js/site.js` if Node is available. Node is only an optional testing tool.

For browser verification, install development-only packages in a separate temporary directory: `npm install --no-save --prefix /tmp/sya-test playwright @axe-core/playwright`. Set `NODE_PATH` to that directory's `node_modules`, `CHROMIUM_PATH` to your Chrome/Chromium executable, `AXE=1`, and run `node tests/browser.cjs` while PHP serves port 8000. On Windows use a writable temporary prefix and set environment variables with `$env:NAME='value'`. Alternatively let Playwright install Chromium and adapt the executable path in the test. `TEST_URL` overrides the target URL. Tests write ignored screenshots to `tests/artifacts/`.

Additional checks can be run with `node tests/extra.cjs` using the same development environment.

See [Milestone 5 results](docs/milestone-5-report.md), [Milestone 4 results](docs/milestone-4-report.md), [Milestone 3 results](docs/milestone-3-report.md), [Milestone 2 results](docs/milestone-2-report.md), [Milestone 1 results](docs/milestone-1-report.md) and [hosting feasibility](docs/hosting-feasibility.md). Automated checks supplement manual keyboard, screen-reader, device and content review; they do not certify WCAG conformance.

## Phone upload to InfinityFree

Download the latest ZIP: https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip

1. Extract `SYA-main.zip` on your phone.
2. Open `SYA-main`. Upload its **contents** into your domain's `htdocs` directory. The new root `index.php` must be directly inside `htdocs`, with `public`, `app`, `config`, and `resources` alongside it.
3. Include the root `.htaccess`, `public/.htaccess`, and the `.htaccess` files inside private folders. Enable hidden-file display in your phone's file manager if needed. Do not delete a provider-required file outside this project.
4. Remove or rename the provider's default `index.html` if it takes precedence over `index.php`.
5. Open your domain. The root index redirects to `public/index.php`; the application detects that base path automatically when `base_url` is empty. No database configuration is needed to preview these public pages.
6. Confirm `/config/config.example.php`, `/database/migrations/001_foundation.sql`, `/README.md`, and `/.git/config` return **403 Forbidden**, with no file contents. If they do not, remove the upload and stop; the hosting server is not applying the required protection rules.

This whole-project layout was tested with local Apache 2.4, not an InfinityFree account. It depends on working `.htaccess` rules. A configurable document root pointing directly at `public/` remains preferable when available. A PHP built-in server does **not** apply `.htaccess`: continue using `-t public` for local development, and never expose the repository root with that server.

On future updates, replace the supplied application files and keep all `.htaccess` files. Preserve your private `config/config.php` and any future private storage; neither is part of the ZIP. You may omit `docs`, `tests`, `bin`, `AGENTS.md`, README and `.gitignore` from a production upload; never upload a `.git` directory, database dumps or development artifacts.

No deployment has been performed by the assistant. Public film pages collect no submitted personal data. The private portal processes staff usernames, password hashes, audit events and native session cookies. When deliberately enabled, recruitment collects the stated applicant data, uses private attachments and transactional email, and issues a separate applicant-session cookie. No external scripts, videos, tracking services or social widgets are used. Full privacy and recruitment safeguards remain future work. No official film assets or contact details were supplied. Milestones 6–7 require separate explicit authorization.
