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

A root redirect upload option has now been added at the owner’s request: upload the project contents with all `.htaccess` files. The root rules allow only `index.php` and `public/`, and private folders also deny requests independently. Empty base URL configuration automatically supports `/public`. Local Apache tests verify these rules; InfinityFree account behavior is still unverified. Before using that layout on InfinityFree, directly request private file URLs and confirm 403 with no file contents. If the provider does not enforce those rules, remove the upload and **stop before deployment**. A conventional PHP host with a configurable document root is the simpler alternative. Pricing must be obtained from the chosen provider; no price has been verified.

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

## Milestone 2 update

Administration now requires a configured PDO database, writable native PHP sessions and real HTTPS. Native cookie flags, session rotation, persistent rate limits and setup-lock behavior are implemented. `database/install-milestone-2.sql` supports a manual phpMyAdmin import when hosted CLI is unavailable; no public migration runner was introduced. Setup ships as an inactive protected `.dist` file. The browser helper generates random values locally and never sends them to the server.

Local Apache, PHP and MariaDB tests are documented in the Milestone 2 report. The owner reports that the public upload works, but no account access was supplied to verify administration on InfinityFree. Actual hosted PDO privileges, session persistence, cookie flags, HTTPS server variables, connecting-IP behavior and temporary-upload handling remain account-level verification items. Do not trust arbitrary proxy headers to bypass HTTPS enforcement. The initial login should be tested before importing staff.

## Milestone 3 update (2026-11-17)

Correct domain: **seeyoulater.freedev.app**. The owner reports that the Milestone 2 administrator portal works after upload. Actual new recruitment functionality has not been deployed/tested by this agent on the hosting account.

Resend's published send-email API documentation was retrieved successfully at https://resend.com/docs/api-reference/emails/send-email (HTTP 200). The integration uses its pinned `https://api.resend.com/emails` endpoint, bearer API key, from/to/subject/text JSON and response ID, without trusting arbitrary destinations or following redirects. Local workflows used an explicitly local-only private capture transport; no live API key/sender was provided. Outbound Resend delivery remains unverified until the owner runs the administrator email test and confirms inbox delivery.

An automated request to `https://seeyoulater.freedev.app/config/config.example.php` returned an HTTP 200 **JavaScript/AES browser-check page**, not the requested config and not proof that it is exposed or protected. No production secrets were requested. Therefore neither a successful response code nor that challenge page is sufficient evidence for the new storage model. The owner must check the non-sensitive storage marker with a normal browser before setting the verification flag. Local Apache tests deny the marker, migration SQL and private paths with 403. Uploaded data is stored outside `public/`, with independent deny files required if inside the actual document root; server-side downloads require authentication and department authorization.

New launch gates default closed: application feature flag, Super Admin global switch, approved privacy notice/contact, verified Resend delivery, and, for file questions, explicit private-storage verification plus Fileinfo. No PHP mail fallback, cron, worker, object-storage account or paid infrastructure was introduced. Hosting file limits, native upload temporary storage, new multipart flows, Resend connectivity and production tracking remain account-level verification items. If a critical gate cannot be met, keep recruitment closed and report the evidence before selecting a different host/storage/email arrangement. No provider pricing or additional cost has been verified.


## Milestone 4 update (2026-11-17)

The owner now reports that Milestone 3 hosted email delivery, tracking verification, application/storage protection and closure tests passed. These are owner-reported results; the assistant has not accessed the production database or configuration. Privacy/legal review is pending.

Milestone 4 uses the existing PHP/PDO/InnoDB/session/Resend arrangement, with nine additive tables, ordinary transactions/foreign keys/unique indexes, hashed expiring RSVP tokens and manual single-notice sends. No cron, workers, new runtime, large uploads, meeting API or infrastructure was added. A database mutex plus unique active-slot reservation were exercised under two independent local PHP processes. Local Apache whole-root protections and the additive/repeated SQL upgrade are tested separately in the milestone report.

New account-level checks remain unverified: importing migration 004, real audition invitation/update/cancellation delivery, RSVP cookies/redirects, parallel booking conflicts under hosting, and correct HTTPS base URL generation. Existing Resend delivery success does not by itself verify those new flows. The site must not launch real recruitment until privacy review completes. Audition/evaluation history adds sensitive staff information; retention/deletion administration remains Milestone 6. Off-host backups remain the owner's manual responsibility. Keep meeting-provider access controls separate and do not place backups in htdocs.


## Milestone 5 update (2026-11-18)

The owner reports privacy review completed and a hosted Milestone 4 invitation arrived in spam. This confirms receipt for that test, not general inbox placement. No production account/configuration was accessed by the assistant, and Milestone 5 has not been deployed to InfinityFree by the assistant.

Milestone 5 retains ordinary PHP/PDO/native sessions and introduces six InnoDB tables with standard foreign keys/indexes, plus MySQL/MariaDB JSON extraction for portrait-reference checks. No cron, worker or Node runtime is needed: scheduled visibility is computed from approved revision pointers and UTC timestamps on each request. Previously published revisions remain visible until their approved replacements become due. Public content reads are restricted to the relevant page types; lists use bounded queries/pagination. No external scripts, embeds or large video hosting were added.

Image uploads newly require PHP GD in addition to Fileinfo, protected writable storage and adequate memory. MIME/dimension validation, a memory estimate, a 12-megapixel cap and bounded upload size precede decoding. The server re-encodes JPEG derivatives in private storage, strips original metadata/name and serves them only after checking effective public approval or a permitted staff session. It rejects missing GD, unverified storage and a path inside public; no insecure public-file fallback is used. Local tests exercise valid uploads, spoofed/oversized images, re-encoding with a trailing test payload, anonymous/private preview denial, reveal guards, image revocation and a missing-GD runtime. Local Apache denies the new SQL/app/private-storage paths.

Actual InfinityFree GD support, available memory and file permissions, upload/response execution limits, new migration privileges and published-image/viewer behavior remain unverified account-level checks. Existing Resend credentials are unchanged; Milestone 5 adds no email sending. If the image-processing requirement fails on hosting, keep uploads unavailable, retain working text/external-link publishing and report the exact limitation before adopting another storage/hosting arrangement. No new infrastructure cost has been introduced or provider pricing verified.

The private media `cms` directory must be included in the owner's protected/off-host backups. Automatic cleanup/retention/deletion and backup management remain Milestone 6. Publicly downloaded images cannot be recalled; no-store limits stale responses but does not erase third-party copies. Staff must keep private actor details in their dedicated fields and publish only approved material. Production CMS, permission and reveal tests should be completed by the owner after the additive upgrade.
