# Milestone 7 — phone upload and final checks

The owner reports that the Milestone 6 ZIP, SQL upgrade and hosted tests succeeded. This release is a tested integration package, not evidence that the new code has already been deployed to the live account.

## Your existing Milestone 6 website

1. Download [the latest ZIP](https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip) and extract it. Back up the current database, application files and private storage first; keep an encrypted off-host copy and its passphrase separately.
2. Stop staff editing and public submissions during file replacement. Upload the ZIP's application contents into the existing `htdocs`, including root `index.php`, `public/`, `app/`, `resources/` and the hidden `.htaccess` files. Preserve the existing **`config/config.php`, application key, private storage and uploaded files**. The ZIP contains a safe example configuration, not your live configuration.
3. **Do not import SQL for this update.** Milestone 7 adds no tables or migration. Do not import `database/install.sql` into your working website; that bundle is only for a new empty database. The expected schema remains 43 tables and six migration records.
4. Remove any activated temporary `public/setup.php`. Preserve the database initialization lock. Keep `environment` set to `production`, `debug` false, secure session settings, and the full HTTPS base URL matching your actual installation, normally `https://seeyoulater.freedev.app/public` with the root redirect layout. Keep your existing Resend details and approved notices.
5. Sign in as Super Admin. Open **Communications → Launch checks**. It is read-only. Resolve BLOCKED items; UNVERIFIED items require the separate practical checks below. Disabled recruitment/newsletter/contact services may intentionally stay disabled. No diagnostic switch certifies legal review, storage protection or delivery.
6. Hard refresh the browser. Static asset URLs now change when their files change. Check all eight public pages and navigation on your phone; check the administrator pages you use. Keep submissions closed if a critical check fails.

Do not upload a `.git` directory, local test configuration, SQL dumps, plaintext credential CSVs or `tests/artifacts`. Documentation, tests, CLI tools and the fresh-install/upgrade bundles can be omitted from the production web upload. Retain `database/migrations/` and its directory protection: Launch checks compares those files with the applied checksum ledger. Retain the protected CLI restore tool in your separate recovery copy.

## Final hosted checks

Record date, operator and actual result for each. Previously completed checks need not be repeated without a reason, but changed host settings, code or configuration may warrant retesting.

- HTTPS loads the correct domain without warnings; administrator and applicant cookies have Secure, HttpOnly and appropriate SameSite attributes. Inspect using a computer browser's developer tools when available. No HTTP credential submissions.
- Private paths such as `/config/config.example.php`, `/app/readiness.php`, `/database/install.sql`, `/storage/private/access-check.txt`, `/bin/check-readiness.php`, `/README.md` and `/.git/config` return 403 without contents. A hosting browser challenge is not a protection test result. Never put real secrets in a public probe file.
- Staff permissions, sign-out and expiry still work; Casting/Crew staff cannot open Launch checks. Approved public content/reveals work and private draft identities, applicant documents, notes and scores stay private.
- Using only your own synthetic applicant/email, verify the application, tracking code, audition invitation/RSVP and status flow if those services will be open. Remove test records through the appropriate privacy process. Send no notices to real applicants for testing.
- Confirm one intended transactional email reaches your own mailbox; check spam and sender-domain SPF/DKIM/DMARC. Local captured-mail tests do not establish Resend deliverability. Test newsletter confirmation/unsubscribe and contact only if enabled, with your own address; never send a real campaign merely to test launch.
- Preserve the actual professionally reviewed Nigerian privacy notices, lawful-basis records and host/storage assessment. The owner previously reported legal review completed. Do not replace the approved text with examples.
- Download every encrypted backup part and manifest to a protected device/off-host location; keep the passphrase separately. Restore a copy into a separate empty environment, verify DB/file consistency and record the test. A custody note is an operator record, not proof that an external copy exists. See [recovery instructions](recovery.md).

Assistant checks could not independently inspect the hosted app: HTTPS requests encountered InfinityFree's JavaScript challenge and automated browser navigation was unavailable. No live authenticated tests, mail, uploads or production changes were performed. Owner reports of successful Milestone 6 hosting are recorded separately from agent-run local results.

## Local inspection, including Windows 11

Use PHP 8.2+ with PDO MySQL, Ctype, Fileinfo, GD, OpenSSL and native sessions, plus MySQL/MariaDB. Follow the [README](../README.md) for Windows PowerShell and database-user setup. Copy `config/config.example.php` to the ignored `config/config.php`, enter your local DB credentials, use a stable random 64-hex application key and local environment settings. Start MySQL separately, then run from the project directory:

```sh
php bin/migrate.php
php bin/migrate.php
php tests/database.php
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000/`. Never expose the repository root using PHP's built-in server; it does not honor `.htaccess`. For a completely new phone-hosted empty database without CLI access, import **`database/install.sql` once**, then follow the [one-time administrator setup guide](milestone-2-setup.md). The bundle includes all six schemas and their checksum ledger; do not substitute the old M2-only schema with current application files. Setup remains inactive in the ZIP and permanently locked after initialization.

Optional CLI diagnostics: `php bin/check-readiness.php --production`. A local HTTP/development configuration should fail production checks. Tests that reset fixtures must run only against disposable local databases ending `_test`; never run them against the live database. Browser tools are development-only, not production requirements. Actual execution commands/results are in [the report](milestone-7-report.md).
