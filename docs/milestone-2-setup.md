# Milestone 2: install and use the production office

Latest ZIP: https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip

This phase adds administrator security only. Recruitment, application processing, CMS publishing, newsletters and the contact inbox are still unavailable. You do not need to send any private codes or database credentials to the developer.

## InfinityFree setup from a phone

1. Download and extract the latest ZIP. Upload the contents of `SYA-main` into `htdocs`, replacing the previous application files. Keep all `.htaccess` files. **Preserve your existing `config/config.php`** if you already have one. Never upload a `.git` folder, test fixtures or database exports.
2. In the InfinityFree control panel, create a MySQL database. Record the **assigned database name, username, password and database hostname**. Do not substitute `localhost` for the assigned hostname. Open that database in phpMyAdmin.
3. In phpMyAdmin, choose **Import** and select `database/install-milestone-2.sql` from the ZIP on your phone. It creates the two migration records and six essential tables. It contains no accounts or credentials. If you already imported Milestone 1, this bundle can still be imported. It is restart-safe and does not reset a completed setup lock. Back up any existing database first. Never import a database dump from local tests.
4. Open `https://YOUR-DOMAIN/public/setup-helper.html` (use `/setup-helper.html` if only public files are your document root). Tap **Generate new setup values**. Save them privately in a password manager. The page creates a random application key, setup token, token hash and suggested administrator private code **in your browser**. It sends no generated values over the network or to browser storage. Close the tab after saving. An offline copy is `tools/setup-helper.html` in the ZIP; support for opening local HTML varies by phone/browser.
5. In the hosting File Manager, copy `config/config.example.php` to **`config/config.php`** if it does not exist. Edit the private config:
   - `environment`: `production`.
   - `base_url`: keep empty for the automatic `/public` layout.
   - `database`: assigned host, port (normally 3306 unless the provider specifies another), name, username and password.
   - `security.app_key`: paste the generated **application key** (64 hexadecimal characters).
   - `security.setup_token_hash`: paste the generated **token hash**, not the token itself.
   - Keep `security.https_required` and `session.secure` set to `true`.
   - Keep the file valid PHP. Quote values as shown in the example. If a database password contains a single quote or backslash, escape it correctly inside the PHP single-quoted string. Do not save public backup copies of this file.
6. Before entering any secret, verify your domain uses HTTPS without a certificate warning. Confirm `/config/config.example.php`, `/database/install-milestone-2.sql` and `/public/setup.php.dist` return **403 Forbidden**, without file contents. If these protections do not work, stop and remove the upload; do not activate administration.
7. In File Manager, rename **`public/setup.php.dist` to `public/setup.php`**. The `.dist` file is deliberately inactive and protected before this step.
8. Visit **`https://YOUR-DOMAIN/public/setup.php`**. Enter the generated **one-time token**, choose a username, and enter/repeat your private code. Save the username/code privately. A username uses 3–48 lowercase letters, digits, dots, underscores or hyphens, starting with a letter/digit. Codes require 24–72 printable characters, at least three character types and 12 distinct characters. The suggested code meets those rules.
9. After the success message, **delete `public/setup.php`** using File Manager. Clear `setup_token_hash` in the private configuration (set it to an empty string). Keep `app_key` unchanged. Deleting a completed setup file alone is not the lock: the database permanently records completion as well.
10. Visit **`https://YOUR-DOMAIN/public/admin.php`** and sign in with your username and private code. No email address or 2FA is required. On a public-only document root, use `/admin.php`.

If the portal reports unavailable, check PHP syntax in config, database credentials/hostname, completed SQL import, application key and native PHP session storage. Do not turn off HTTPS or remove security rules to bypass the error. InfinityFree control-panel screens, session storage and real production TLS/cookie behavior were not available for account-level testing here.

## Routine future uploads

Replace application files from the new ZIP while preserving `config/config.php`, your database and future private storage. The ZIP contains only the **inactive** `setup.php.dist`; do not rename it for routine updates. Never leave an active `setup.php` behind. If someone restores that file after setup, it returns 410, and the portal refuses login until it is removed.

Do not re-create administrator accounts or reset `system_state` when updating. New database migrations will have explicit instructions. You may omit `tests`, `tools`, `docs`, `bin`, README and AGENTS from the web upload after using the SQL bundle; their folders are protected even if included. The optional browser setup helper can be removed after use; restoring it later generates fresh browser-only random values and cannot create accounts by itself.

## Administrator tasks

- **Overview:** role-specific navigation, internal staff notice and session information.
- **Administration (Super Admin only):** create accounts, change role/active status, reset another account’s code, and import CSV. Any role/status/code update revokes that account’s existing sessions. You cannot change your own role or disable yourself. At least one active Super Admin must remain.
- **My private code:** available to all roles; requires the current code. A successful change signs the account out on every device.
- **Settings (Super Admin only):** inactivity timeout (300–3600 seconds) and a plain-text internal dashboard notice (up to 300 UTF-8 bytes). Secrets and production security remain in the protected config.
- **Audit log (Super Admin only):** paginated security and staff-management events, using UTC timestamps. No private code, setup token or CSV content is recorded.
- **Casting, Crew, Content, Media, Communications:** permission-controlled placeholders appropriate to the current role. No later-milestone workflow is operational.

The portal works without JavaScript. Browser-only key generation requires Web Crypto. Sign out when finished. The absolute session lifetime is eight hours regardless of activity.

## CSV import

Use a UTF-8 CSV with this exact header and field order:

```csv
username,private_code,role
casting01,REPLACE_WITH_A_RANDOM_PRIVATE_CODE,casting_manager
crew01,REPLACE_WITH_A_DIFFERENT_RANDOM_CODE,crew_coordinator
editor01,REPLACE_WITH_ANOTHER_RANDOM_CODE,content_editor
```

The example strings are placeholders, not acceptable credentials. Generate each real code with a password manager. Valid role names are `super_admin`, `casting_manager`, `crew_coordinator`, `content_editor`. Limits: 64 KiB and 100 data rows. No file is retained by the application; PHP’s temporary upload is removed after parsing. Header or file/row-limit errors reject the whole import before account creation. Otherwise valid rows are committed individually and rejected rows receive safe row-number results. A retry rejects already-created usernames instead of overwriting them.

Credentials are hashed before insertion. Super Admin authorization, CSRF and the importing administrator’s current private code are required. Importing additional Super Admins grants full access, so distribute codes privately and delete your own plaintext CSV copies after use. Provider-level temporary-file cleanup and phone/cloud backups are outside application control.

## Rate limits and recovery

Limits use a persistent database ledger and 15-minute windows: six login attempts per normalized username, 30 per connecting IP; six setup attempts per connecting IP; ten sensitive-action confirmations per account and 30 per IP. Successful attempts also count. New browser sessions do not bypass these limits. An attacker can temporarily exhaust an account bucket; wait for the window rather than weakening limits. No raw IP addresses are stored; keyed hashes are used. Proxy headers are not trusted for HTTPS or client-IP detection.

If a staff member loses a private code, a Super Admin can set a replacement. There is no email recovery. Keep a second trusted Super Admin or a secure recovery plan. If every Super Admin loses access, recovery requires an authorized database operator to restore access with a newly generated password hash; initial setup deliberately **does not reopen**, even if all accounts are deleted. Do not delete the setup-lock row or replace it with a fresh database to recover an established installation.

## Local development and tests

Install PHP 8.2+ with PDO MySQL and native sessions, plus MySQL 8 or MariaDB. Copy the example config, enter local database settings and a 64-hex application key. For loopback-only HTTP development, explicitly set `environment` to `local`, `security.https_required` to `false` and `session.secure` to `false`. Remote HTTP remains forbidden. Leave production settings enabled on InfinityFree.

```sh
php bin/generate-secrets.php
php bin/migrate.php
php tests/database.php
php tests/security.php
php -S 127.0.0.1:8000 -t public
```

Keep generator output private. The web setup procedure is the same locally: activate the temporary file, use the token, complete setup and delete the file.

Windows 11: use the same commands in PowerShell once PHP is installed and in PATH; `Copy-Item config/config.example.php config/config.php` creates your private configuration. PHP must have a writable native session directory; configure `session.save_path` privately if your local installation lacks one. Windows execution was not available for verification.

Browser tests require development-only Playwright, Chromium and `@axe-core/playwright`. **Never run reset fixtures on an installation containing real accounts.** They require a local database whose name ends in `_test`, and `SYA_TEST_RESET=YES`; they intentionally reset setup/account data. See `tests/prepare-admin.php`, `tests/admin.cjs`, `tests/setup-race.cjs`, `tests/helper.cjs` and the milestone report for exact execution and scope. Node is not a production dependency.
