# See You Around — Milestone 1

Lightweight PHP public website. Only Milestone 1 is implemented. No administrator, recruitment, CMS, newsletter or contact submission backend exists. The illustrative hero is concept artwork, not a production still; promotional copy is provisional. Official synopsis, format, credits, actor identities and production dates await approval.

## Requirements

PHP 8.2+ (tested: 8.4.27), PDO and pdo_mysql; MySQL 8+ or MariaDB 10.6+ (tested: MariaDB 11.8.6), modern browser. No Composer, Node or external runtime dependencies for the application. PHP built-in server is for local use only.

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

Open http://127.0.0.1:8000/ . The public pages work without database credentials. Never serve the project root: only `public/` may be the document root.

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

First execution applies `001_foundation.sql`; subsequent executions skip it after checking its checksum. The only table is `schema_migrations`. The runner obtains a MySQL advisory lock, uses prepared ledger queries and rejects modified applied migrations. MySQL DDL implicitly commits; migrations must be restart-safe, and failures require inspection before retry. There is no automatic rollback. Back up before future schema changes. `tests/database.php` expects a disposable Milestone 1 database with exactly one table.

## Configuration

`config/config.php` is the central configuration, outside the web root and ignored by Git. The safe example provides defaults for public rendering only. `base_url` is empty for a root installation; for a subdirectory use e.g. `/film` (without a trailing slash). No host header is used to build links. Future feature flags and session/email/storage settings are reserved configuration, not operational services. Keep debug off in production; configure PHP `display_errors=Off` and protected error logging at the server level. No production credentials are provided. Do not change feature flags to imply unimplemented functionality exists.

## Structure

- `public/index.php`: route allowlist, security headers, method handling.
- `public/assets/`: local CSS, vanilla JS and vector placeholders.
- `app/bootstrap.php`: configuration, escaping and URL helpers.
- `app/database.php`: lazy PDO connection with native prepared statements.
- `resources/views/`: shared layout, eight public pages and character component.
- `database/migrations/001_foundation.sql`: schema version ledger only.
- `bin/migrate.php`: CLI-only migrations.
- `tests/`: database and optional browser checks.
- `docs/`: hosting feasibility and actual milestone report.

## Verification

Lint all PHP files with `php -l` (PowerShell: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`). Run `node --check public/assets/js/site.js` if Node is available. Node is only an optional testing tool.

For browser verification, install development-only packages in a separate temporary directory: `npm install --no-save --prefix /tmp/sya-test playwright @axe-core/playwright`. Set `NODE_PATH` to that directory's `node_modules`, `CHROMIUM_PATH` to your Chrome/Chromium executable, `AXE=1`, and run `node tests/browser.cjs` while PHP serves port 8000. On Windows use a writable temporary prefix and set environment variables with `$env:NAME='value'`. Alternatively let Playwright install Chromium and adapt the executable path in the test. `TEST_URL` overrides the target URL. Tests write ignored screenshots to `tests/artifacts/`.

Additional checks can be run with `node tests/extra.cjs` using the same development environment.

See [actual results](docs/milestone-1-report.md) and [hosting feasibility](docs/hosting-feasibility.md). Automated checks supplement manual keyboard, screen-reader, device and content review; they do not certify WCAG conformance.

## Hosting and limitations

No deployment has been performed. A conventional host should point its document root to `public/`, leaving all other files outside it. InfinityFree document-root layout, protected storage and CLI availability require account-level confirmation. Do not upload the entire repository into `htdocs`. Do not expose a browser migration runner to compensate for missing CLI; manual SQL import with checksum registration is described in the hosting report.

No personal data is collected. No external requests, cookies, sessions, videos, tracking scripts or social widgets are initiated by this application. Full privacy and recruitment safeguards remain future work. No official film assets or contact details were supplied. Milestones 2–7 require separate explicit authorization.
