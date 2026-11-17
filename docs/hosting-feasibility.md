# InfinityFree feasibility — 2026-11-17

## Evidence and confidence

Retrieved https://www.infinityfree.com successfully (HTTP 200) on 2026-11-17 using Python urllib. The live provider homepage advertises **PHP 8.4**, **MySQL 8.0 / MariaDB 11.4**, 5 GB disk space, free SSL certificates, full `.htaccess` support, and uploads through FTP or online File Manager. These are documented provider claims, **not tests on an InfinityFree hosting account**. The homepage initially returned 403 without the successful request variant. https://forum.infinityfree.com/docs returned HTTP 200 but only a JavaScript shell. Attempts to retrieve documentation JSON and support topics returned 403. Detailed restrictions could not be reliably verified. No account, credentials, domain or hosting access was provided.

Local PHP 8.4.27 and MariaDB 11.8.6 passed application tests. This is compatibility evidence for the chosen technologies, not proof of InfinityFree behavior.

| Area | Current evidence | Required account-level verification |
| --- | --- | --- |
| PHP | Provider advertises 8.4 | Actual version, PDO/mysqlnd, session settings, disabled functions |
| Database | Advertises MySQL 8.0 / MariaDB 11.4 | Assigned hostname, ports, local/remote access restrictions, InnoDB, utf8mb4, advisory locks and DDL permissions |
| Uploads | No verified limits | `upload_max_filesize`, `post_max_size`, file count, allowed file types, per-file hosting limits, real MIME checks |
| Storage | Advertises 5 GB | Writable directories, storage policy, outside-htdocs persistence, direct-access denial; PHP execution denial for uploads |
| Outbound requests | Unverified | HTTPS API requests, DNS, cURL/streams, port and destination restrictions |
| Email | Unverified | Native mail reliability and external transactional HTTPS API feasibility; never assume PHP mail is usable |
| Scheduling | Unverified | CLI, cron and execution restrictions; no worker or cron dependency in Milestone 1 |
| HTTPS | Free certificates advertised | Issuance, renewal, redirect behavior and secure cookies before administration |
| Directory protection | Full .htaccess advertised | Actual deny rules, directory listing, override scope and document root constraints |
| Execution limits | Unverified | Time, CPU, memory, inode and request limits |
| Transfer | FTP and online File Manager advertised | Whether encrypted transfer is available, file permissions and upload integrity |
| Backups | No verified promise | Off-host export, restore access and secure storage; do not assume automatic backups |

## Deployment layout to investigate (not deployed)

Preferred: upload only `public/` contents into the assigned `htdocs` and place `app`, `config`, and `resources` in a protected sibling directory with the same relative layout. Confirm the provider permits and preserves this layout. `index.php` expects its parent directory to contain these folders. Never place secrets, database dumps or future attachments in the public directory. `.htaccess` disables listing and dotfile access, but is not a substitute for a correct document root.

If the host cannot preserve the layout safely, **stop before deployment**. A private directory within htdocs would require verified server deny rules and direct HTTP access tests before approval; it is not an implemented or proven solution. A conventional PHP host with a configurable document root is the simpler alternative. Pricing must be obtained from the chosen provider; no price has been verified.

## Migrations without hosted CLI

Do not assume SSH or PHP CLI. If absent, an authorized operator can use the provider's database UI to import `database/migrations/001_foundation.sql`, then register its version with a prepared local connection if permitted, or manually execute the following exact reviewed ledger statement in that UI:

```sql
INSERT INTO schema_migrations (version, checksum) VALUES
('001_foundation.sql', '71ac5473fe013d7db1f94a6c3d245fa8391f270ed077ac63f00a9eb5ead725b0');
```

This checksum belongs to the current unchanged migration. Verify it with `php -r "echo hash_file('sha256', 'database/migrations/001_foundation.sql');"` before import. Confirm exactly one ledger entry and table. This manual route has not been tested on InfinityFree. Keep operational SQL and exports off public storage. Never upload `bin/` or configuration into htdocs.

## Future feature gates

Milestone 1 has no dependency on email, private uploads or background jobs, so no unsupported critical feature is being forced into this implementation. Before recruitment, private attachment isolation and transactional email delivery are hard requirements. If either fails practical tests, stop recruitment work and report the evidence, costs and alternatives. Options for later consideration include a conventional PHP host with private disk access and a replaceable transactional email API, or private object storage with authenticated downloads. Those introduce provider costs and have not been provisioned.

Long-running workers are unnecessary. Future scheduled publication can use request-time due-date checks; retention and backups need documented administrator-triggered operations if scheduling is unavailable. These are future design options, not implemented features. Confirm hosting terms permit the intended usage and data handling before accepting private applicant records.
