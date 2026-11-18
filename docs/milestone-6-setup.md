# Milestone 6 — phone upgrade and operations

Milestone 6 adds optional confirmed newsletters, a managed contact inbox, privacy/retention tools and encrypted backup parts with offline recovery. Milestone 7 (final launch/integration/deployment) is not included. Existing recruitment, auditions and publishing remain in place. No live deployment or production messages were sent by the assistant.

## Upgrade on your phone

1. First make private backups of your current database/files/config/storage using phpMyAdmin Export and your existing protected file-transfer tools. Store copies off-host with suitable encryption; never leave dumps in `htdocs` or put credentials in GitHub.
2. Download [the latest GitHub ZIP](https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip), extract it, and select your **existing Milestone 5 SYA database** in phpMyAdmin.
3. Import **`database/upgrade-milestone-6.sql`**. This additive upgrade adds 14 tables and the sixth migration entry (43 total tables). It preserves accounts, setup lock, applications, auditions and CMS data. Do not drop/recreate the database or rerun initial administrator setup. Inspect import errors before retry: MySQL DDL implicitly commits.
4. Replace the supplied application files, keeping all `.htaccess` files. **Preserve `config/config.php`, your application key, database password, private attachments, CMS media and any backups.** The actual config is not in the ZIP. Do not upload `.git`, development artifacts, plaintext dumps, decrypted backups or activate `public/setup.php`.
5. Keep `base_url` as **`https://seeyoulater.freedev.app/public`**. Reuse your existing Resend key, verified sender and email settings. No new provider account/key is required. Image handling still requires the Milestone 5 GD/Fileinfo/storage checks; authenticated backup encryption requires PHP OpenSSL with AES-256-GCM.
6. Sign in → **Communications → Open assigned inbox**. Content Editors now also have Communications for assigned inquiries and newsletter drafts. Newsletter approval/sends, privacy and backups are Super Admin only. Staff see only assigned inquiries; the Super Admin sees and assigns new inquiries.
7. Recheck in a normal browser that `/database/upgrade-milestone-6.sql`, `/app/backups.php`, `/bin/restore-backup.php`, `/config/config.example.php` and `/storage/private/access-check.txt` return Forbidden without source/file contents. Anonymous private endpoint requests must not expose messages, JSON exports or encrypted parts. Keep the real private-storage protection flag accurate.

## Add configuration alongside your existing blocks

**Do not replace your whole config.** Add these as new top-level entries alongside `email`, `storage` and `recruitment`, before the closing `];`:

```php
'communications' => [
    'privacy_approved' => false,
    'notice' => '', // Paste the ACTUAL reviewed communications/privacy information.
    'notice_version' => '1',
    'privacy_contact' => '', // Actual approved public privacy email address.
    'rights_enabled' => false,
    'response_days' => 30, // Operational reminder; confirm the applicable legal deadline.
    'contact_retention_days' => 180, // After first resolution/closure; confirm your policy.
],
'backups' => [
    'retention_days' => 7, // Encrypted host copies; manual bounded deletion.
    'reminder_days' => 14,
],
'social_links' => [], // Optional approved ['label' => '...', 'url' => 'https://...'] entries.
```

Update your **existing** features block to the desired launch settings; keep these off during initial checks:

```php
'features' => [
    'recruitment' => false, // Preserve your intended existing setting after testing.
    'newsletter' => false,
    'contact' => false,
],
```

The owner reports privacy/legal review completed. Paste the actual reviewed information covering the responsible production/controller identity, purposes and appropriate lawful basis, optional marketing consent, email provider/storage, retention, rights and public privacy contact. Do not invent registration/notice numbers or use sample text as an approved policy. Acknowledgment of a contact/privacy notice is not automatically treated as marketing consent.

After actual notice/contact/version are configured, set `communications.privacy_approved` true. Enable `rights_enabled` to accept email-verified privacy requests. Enable newsletter/contact feature flags only when ready. Newsletter and rights verification also require your existing `email.delivery_verified` flag and working delivery. Keep marketing disabled until your own mailbox tests pass. The public Contact page offers the configured privacy email as a manual alternative if verification is unavailable; keep that mailbox monitored. No official address/social account was fabricated.

## Newsletter test and operation

1. With the notice and feature flag enabled, open `https://seeyoulater.freedev.app/public/connect.php?view=newsletter`. Use your own mailbox and choose categories. Check spam. Opening the emailed link alone must not activate anything; submit **Confirm subscription**.
2. Confirmed subscriptions are separate from applications. Categories: recruitment, production, cast reveals, trailers/release. The original notice snapshot, version, requested/confirmed timestamps and preference events are recorded. No tracking pixels/opens are collected.
3. Content Editor or Super Admin creates a campaign draft in Communications → Newsletter campaigns. Super Admin reviews/approves it; stale draft approval is rejected if another editor changed the content. Approved/preparing campaigns cannot be edited—create a new draft for changes.
4. Click **Prepare next 100 subscriber records** until the campaign is ready. Each action scans at most 100 active subscriptions, recording only category matches. Then click **Send delivery** per recipient. There is no background sender or automatic bulk email. Pending/failed/accepted/skipped and attempt counts are visible. Provider acceptance does not guarantee inbox delivery; inspect Resend and spam folders.
5. Subscribe a second test address, prepare a queue, unsubscribe that address, then attempt its delivery: it must be skipped. Each send rechecks consent/preferences. One email already processing may still arrive after an opt-out/cancellation; it cannot be recalled.
6. The email contains a private preferences/unsubscribe link. GET is read-only; explicit POST saves changes/unsubscribes. Confirmation links expire after 24 hours; request-preferences links after 10 minutes; campaign footer management links after 90 days. They are single-use for a change, with a maximum ten-minute open-page grant. If used/expired, request a fresh preferences email at `connect.php?view=preferences`. An inactive subscription requires a fresh opt-in to subscribe again. Unsubscribe invalidates outstanding confirmation links.
7. Test a second mailbox/provider and your phone. Do not import application emails as subscribers or create real campaigns during testing. Check your Resend account/domain limits and authentication before a broader campaign. The newsletter feature flag also stops campaign sends.

## Managed contact inbox

Public contact submissions have categories General/Press/Partnerships/Sponsorships/Production/Other, a random reference, notice acknowledgment, CSRF, a honeypot and persistent rate limits. They do not create subscriptions/applications. The inquiry goes into the private inbox; no administrator email address is needed.

Super Admin assigns staff. Others can search/read/change only assigned inquiries; assigning someone authorizes them to that inquiry regardless of their recruitment department. Staff save private notes, status and UTC follow-up dates. Due follow-ups appear in the list. New reply text is a **draft**, followed by a separate **Send reply** action. Delivery result/history is retained; failure can be retried manually. No automatic forwarding or staff-email notifications are assumed. Contacts must remain enabled and email delivery ready for reply sending in this milestone.

Statuses: New, In Progress, Awaiting Reply, Resolved, Closed. First resolution/closure sets the configured retention-review date; reopening does not silently extend it. Check assignment denial by signing in as an unrelated staff member. Test search/status filters, reply arrival, status history and phone layout.

## Privacy and retention

Read [privacy operations](privacy-operations.md) before destructive actions. Public `connect.php?view=rights` accepts access/deletion requests with an email-verification link. Verification establishes mailbox access only; staff may need proportionate further identity checks. The Super Admin records review, evidence, status, decisions and reminders. No applicant receives internal evaluations/notes, and no records are automatically released or deleted.

Staff-review access JSON includes matching submitted applications, contact messages and subscriptions; review/redact third-party information and legal exemptions, and arrange approved secure transfer of attachments separately. The downloaded file contains personal data and must not be stored publicly. Access export requires a verified access request and current Super Admin private code.

Use Initialize missing retention dates to process up to 100 non-selected applications whose role closed or deadline passed without an existing review date; it never extends existing dates or deletes anything. Retention-expired lists identify up to 20 records per category. Holds block anonymization. Typed identifiers, current code and either an expired applicable retention period or matching verified deletion request are required. Selected production records additionally require a verified request and explicit contractual-policy review. Anonymization revokes private access, scrubs answers/notes and personal evaluations, and queues attachments for physical deletion; click **Process file deletions** until pending/failed jobs are resolved. Review each matching application/contact/subscription separately. Production CMS content erasure is a separate exceptional reviewed action after archiving; orphan images cannot be removed while referenced by any current/historical revision.

Sensitive confirmations throttle after five failed private-code attempts for 15 minutes. Never send private codes or exported personal records in support screenshots.

## Encrypted backups and recovery

Follow [recovery instructions](recovery.md). Super Admin starts with a **separate 24–256 character passphrase** and current private code. Start refuses in-flight writes, active sends or pending file deletion. It freezes mutations for at most **20 minutes** while you prepare bounded encrypted parts. Browsing/login/logout remain available. Do not edit via FTP/phpMyAdmin or run another writer during a backup. A crashed write lease is not automatically ignored; the recovery control requires confirmation that the hosting request ended.

Prepare each part, download every numbered `.syab` file, and include the final completion manifest. One database step handles at most 50 rows/~3.5 MB SQL; private files use 1 MB chunks. Backup includes application database schema/data, registered private attachments, optimized media variants and private config; source code comes from the matching GitHub ZIP. Ephemeral verification/rate-limit/write-lease state and backup bookkeeping data are intentionally not restored. No plaintext server dump is created. AES-256-GCM authenticates each part; PBKDF2-SHA256 (600,000 iterations, random per-run salt) derives the key. The derived key stays only in the short-lived private staff session, not the database.

Resume a still-active run using its original passphrase if necessary. Expired/cancelled/incomplete sets cannot restore. Cancelling an old run cannot release a different run's pause. Complete/cancel resumes writes. Copy all encrypted parts off-host, keep the passphrase separately, and record actual custody/restoration results—notes do not perform a copy/test. Host-copy retention defaults to seven days; Super Admin deletes up to 20 parts per action. Delete off-host expired copies separately. Old backups may still contain erased data until their approved retention expires; reconcile erasure/opt-outs before any restore.

On a small site expect at least one part per table plus private-file chunks and the manifest. If your phone/storage/dataset cannot finish within the 20-minute window, cancel rather than trust an incomplete set. Use the documented supervised maintenance export/file-transfer/offline-encryption alternative; no security limit is silently removed. Restoration requires PHP CLI on a trusted computer/technician; no public web restore uploader was added.

## Hosted acceptance checklist

- Test newsletter confirmation, preferences, unsubscribe, category filtering, stale approval, queued-recipient opt-out and actual Resend receipt/spam placement using only your addresses.
- Test contact receipt, assignment isolation, private note, reply, search/status/follow-up and phone views.
- Test a synthetic verified access/deletion request. Confirm private-code/identifier/hold safeguards, selected-record protection, access JSON exclusion of private notes, private-link revocation and actual file cleanup.
- Run a small encrypted backup, download all parts, restore to an empty **test** database/protected storage on a trusted computer, compare files and inspect the site. Record only results actually achieved. Never test restoration over production first.
- Verify new private URLs and old login/recruitment/tracking/uploads/auditions/CMS/reveals after upgrade. Leave untested public services disabled.

Local tests passed as recorded in the report. Real InfinityFree M6 routes, OpenSSL/memory/execution limits, storage/leases, actual communications and phone backup-download behavior remain owner verification items. Milestone 7 requires explicit approval.
