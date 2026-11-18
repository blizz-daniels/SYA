# Backup custody and disaster recovery

Milestone 6 uses small authenticated encrypted parts, not a public ZIP/dump or a background worker. A backup is usable only with **every numbered part, its completion manifest and the separate passphrase**. Application source comes from the matching project ZIP; save that ZIP/commit with the backup records. Never assume InfinityFree or GitHub backs up your database/private files.

## Create and keep a backup

1. Use Super Admin → Communications → Backups. Finish sends and pending physical-deletion jobs first. Choose a separate, unpredictable 24–256 character passphrase; it cannot equal the current administrator code. Save it in a trusted password manager/off-host location, separately from the encrypted files. Lost passphrases cannot be reset to decrypt old parts.
2. Start requires the current private code, working OpenSSL AES-256-GCM and protected writable storage. It refuses any other in-flight write lease. A hard-killed request can leave a stale lease; do not clear it just because it is old. Confirm the hosting request ended, then use the reviewed recovery control. If that cannot be established, arrange an external maintenance backup.
3. Start pauses application mutations for at most 20 minutes. Do not change files/config via FTP, edit phpMyAdmin, run migrations or use another writer during this interval. Ordinary reads/login/logout remain available. Scheduled publication is a read-time decision and its stored approved pointers remain part of the backup.
4. Prepare successive parts. Each database part handles at most 50 rows and about 3.5 MB SQL; registered private files are split into 1 MB pieces. Each file piece includes size/hash; the final manifest authenticates part numbers/hashes and file inventory. The server writes **only encrypted `.syab` files** beneath protected private storage. Keys are derived with PBKDF2-SHA256/600,000 iterations and random per-run salt; AES-256-GCM uses fresh nonces/tags. Key material is kept only in the short-lived staff session while active, not in MySQL. Resume requires the original passphrase/private code.
5. Download **all** numbered parts through the authenticated portal, including the last completion part. A session loss permits redownloads while files remain; an incomplete/expired/cancelled run must be discarded and replaced. Cancel releases only that run's own pause. Finishing also releases the pause.
6. Copy the encrypted set off-host to a trusted, access-controlled location/device/cloud account. The application does not silently upload it to any third party. Record the date, custodian, location, expected number of parts and matching code ZIP/commit. The record is operator-supplied evidence, not a verified cloud transfer.
7. Default host retention is seven days (configurable 1–30); use the bounded delete action until all host parts are removed. This also allows deliberate early deletion after an explicit confirmation. Delete expired off-host sets separately under the approved policy and record it. Keep only the necessary rotating copies. Do not retain erased applicant data indefinitely in backups.
8. Default portal reminders ask for a backup after 14 days; restore-test notes remain visible. Establish a practical recurring restoration test (for example quarterly and after major schema/storage changes); record actual dates/results and the next review. No cron/email reminder is assumed.

Inventory includes application database tables, registered private attachment bytes, all optimized variants registered in `media_assets`, and `config/config.php`. It does not collect unrelated/orphan FTP files, PHP native session files, server settings or external video/provider data. Source code must be saved separately. Backup tables, rate-limit rows, write leases and one-time verification/token data have schema but intentionally empty restored data. Newsletter footer links may therefore need fresh preferences verification after recovery. Protect any supplemental server/config export separately.

## Test restoration on a trusted computer

**Never restore over production first.** Use PHP 8.2+ with OpenSSL on a trusted computer/technician, a fresh output directory outside any web root/cloud-synced public folder and a new empty MySQL/MariaDB test database. With only a phone, keep encrypted copies/passphrase safely and arrange this trusted-computer step; no public web restore uploader was added.

Put one backup run's `.syab` files in a single directory. Mixed runs, duplicate/missing parts, incorrect passphrases or changed ciphertext are rejected. Do not mix the matching source ZIP into that parts directory.

Linux/macOS example:

```sh
php bin/restore-backup.php /private/backups/sya-parts /private/restored-sya-new
```

Windows 11 PowerShell example:

```powershell
php bin/restore-backup.php 'C:\SYA-Backups\parts' 'C:\SYA-Restore-New'
```

The tool reads the passphrase from stdin, not a command argument. Terminal entry may be visible: use a private terminal, not screen sharing. Output permissions are 0700 directories/0600 files on Linux; on Windows explicitly restrict the output folder's NTFS permissions to the recovery operator. Windows execution is not verified in the assistant environment.

The tool first authenticates every part/completion manifest, then writes `database.sql`, `config/config.php` and `private/...` files into the NEW output directory. Reassembled files must have matching size/hash. Any failed/partial output is untrusted and must not be deployed. The output adds an Apache deny file as defense in depth; that does not make it safe to expose with a server that ignores it. The output contains plaintext secrets/personal information; protect and securely remove it when no longer needed. It is not a web application upload bundle.

Import `database.sql` into an **empty test database** through a trusted CLI or phpMyAdmin over HTTPS. For example, in a suitable shell:

```sh
mysql -u LOCAL_RESTORE_USER -p EMPTY_TEST_DATABASE < /private/restored-sya-new/database.sql
```

Use an interactive password prompt. PowerShell does not support that input-redirection syntax directly; use phpMyAdmin Import or an appropriate `cmd.exe`/MySQL client workflow. Never put database passwords on a command line or upload plaintext dumps into `htdocs`.

Deploy matching application source to a separate local/test location with `public/` as the document root. Configure the restored private config for the **test** database, base URL, TLS and restored private directory, preserving the application key. Explicitly set `features.recruitment/newsletter/contact` false, `communications.rights_enabled` false, `email.delivery_verified` false and `storage.access_verified` false in the recovered configuration until the new deployment checks pass; preserve the application key. Do not trust old readiness flags for a new location. Keep test email delivery local/disabled; do not send to restored real applicants as a test. Use a separate session name/storage when testing on the same machine. Never run the one-time initial setup again: its permanent database lock is restored. The SQL recovery output assigns new random administrator auth versions to invalidate stale sessions; staff must log in freshly. Review account status/codes against changes since the snapshot before considering launch.

Verify all expected tables/migration checksums, foreign keys, record counts, private-file hashes and storage denies. Inspect eight public pages, authenticated login, selected private downloads/media, role permissions, recruitment/tracking, auditions and content/reveals. Record failures and restoration time. Keep the approved sender/feature gates off until all live checks are complete. The assistant performed an actual local encrypted extraction, empty-database import, foreign-key check and restored-app smoke test; this does not verify InfinityFree recovery.

## Production recovery and privacy reconciliation

Recover in a maintenance window. Before launch, reconcile **unsubscribes, erasures, legal holds, status decisions and administrator changes made after the snapshot** from current records/audit/custody evidence. A backup cannot reconstruct events it never contained. If latest opt-out/erasure evidence cannot be recovered, do not resume campaigns or automatically treat restored marketing consent as current. Old links/sessions and provider delivery uncertainty need review; do not resend accepted/unknown campaigns blindly.

Preserve the application key; changing it casually invalidates HMAC-based email matching/tracking. Keep restored native sessions invalidated; review active administrators and rotate compromised credentials through a controlled authorized process. Never reopen initial setup merely to regain access. Restore external meeting-provider permissions and other off-system production records separately as needed.

Only after validation should you point the production config to the recovered database/private storage and deliberately re-enable approved features. Test HTTPS/cookies, protected directory denial and actual transactional email at the real host. Record the incident, snapshot, reconciliation, custodian and completed tests. Delete temporary plaintext recovery copies and expired backup sets according to the approved policy, including off-host locations and processor copies where applicable.

## When a shared-host backup is too large or unsupported

Stop rather than remove encryption/access controls or trust a partial set. A site exceeding 5,000 registered files, a database row exceeding the part limit, insufficient storage/OpenSSL/memory, inability to establish a write pause, or inability to finish within 20 minutes needs supervised maintenance export. Put the site into an actual hosting-level maintenance/read-only window, export MySQL securely with phpMyAdmin or a suitable client, transfer protected files/config using available secure provider tooling, encrypt the copies offline with an established authenticated-encryption tool, keep them off-host and test restoration. Do not assume shell, SFTP, cron, large downloads or long-running PHP exist on InfinityFree; verify the available transfer options. This alternative uses existing hosting/local tooling; no service purchase or capability is claimed here.

### Milestone 7 compatibility

Backups and privacy file cleanup use verified private storage even when new recruitment uploads are disabled. Authorized existing attachments remain downloadable under their original permissions. A failed write-lease preflight now rejects mutations with 503 rather than allowing them through during an uncertain backup state. Use printable backup passphrases of 24–256 bytes; control characters are rejected because the offline passphrase input is line-based. Existing valid encrypted backups retain the same format. The Milestone 7 local recovery test imported the decrypted database into a separate database, checked all 57 foreign-key relationships and exact attachment bytes, then exercised fresh login and private image serving from a separate restored application. This does not establish custody of the real production backups.
