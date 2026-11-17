# Milestone 3 — phone upload and recruitment setup

Production website: **https://seeyoulater.freedev.app**. The owner has already corrected the live configuration; preserve that file.

Download: https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip

## Update the working Milestone 2 installation

1. Download/extract the new ZIP. Make a protected backup of your existing database and files. Keep your current `config/config.php`, application key, administrator accounts and any private storage. Do not upload local test fixtures or `.git`.
2. Open **phpMyAdmin**, select your existing Milestone 2 database, then import **`database/upgrade-milestone-3.sql`** from this ZIP. This adds eight tables and the third migration record. It does not change your administrators, setup lock or application key. It can be imported again without resetting data. Do not use a test database dump.
3. Upload the new project files into `htdocs`, replacing application files and retaining all `.htaccess` files. Include `storage/.htaccess`, `storage/private/.htaccess` and the non-sensitive `storage/private/access-check.txt`. Preserve existing private uploads during future updates.
4. **Do not rerun initial administrator setup.** The ZIP still contains only an inactive `setup.php.dist`; leave it inactive. Sign in normally at https://seeyoulater.freedev.app/public/admin.php.
5. Merge the configuration additions below into your existing private `config/config.php`. Do not replace its database password or regenerate its application key. Leave recruitment disabled until the checks are complete.

## Use your existing Resend account

Yes, the same Resend account can serve this film and Da4lions Coded. Create a **separate sending API key** for this project, restricted to your verified sending domain where the account supports that restriction. Keep it out of GitHub and browser JavaScript. Sending allowances and limits are shared at the account/plan level; check your Resend dashboard for current limits. No new service or paid plan was provisioned.

Use an email address on a domain you already verified with Resend. It can be the existing verified domain you use for your other project, with `See You Around` as the sender display name. Do not assume you can verify the hosting provider’s `freedev.app` domain or send as an arbitrary address. A Resend testing sender is not a general production sender for public applicants.

Replace the existing **`email`** configuration entry with:

```php
'email' => [
    'provider' => 'resend',
    'api_key' => 'PASTE_YOUR_SEPARATE_RESEND_SENDING_KEY',
    'from_email' => 'YOUR_ADDRESS_ON_YOUR_VERIFIED_DOMAIN',
    'from_name' => 'See You Around',
    'delivery_verified' => false,
],
```

The address and key above are placeholders. Do not add duplicate `email` entries: replace the old one. Avoid displaying your complete config or API key in screenshots.

Add this new top-level entry:

```php
'recruitment' => [
    'privacy_approved' => false,
    'uploads_enabled' => false,
    'privacy_notice' => '',
    'privacy_contact' => '',
    'notice_version' => '1',
],
```

Update the existing `storage` entry:

```php
'storage' => [
    'private_path' => dirname(__DIR__) . '/storage/private',
    'access_verified' => false,
],
```

Keep the existing top-level feature flags initially:

```php
'features' => [
    'recruitment' => false,
    'newsletter' => false,
    'contact' => false,
],
```

Keep your existing `base_url` as **`https://seeyoulater.freedev.app/public`**, or empty for automatic base-path detection. Keep production HTTPS/secure-session settings enabled.

## Verify delivery before accepting applications

In the administrator portal, open **Settings → Recruitment launch checks**. Enter your own test recipient email and current private code, then send the email test. The portal logs provider acceptance/failure without storing its message body or secret values.

Confirm that the test actually arrives in the recipient inbox or spam folder and review its sender. An API acceptance message is not proof of delivery. Only after receipt, set `email.delivery_verified` to the boolean `true` in private config. Do not quote boolean values.

The integration calls **https://api.resend.com/emails** through PHP cURL, or HTTPS streams with TLS verification. It does not use PHP mail, SMTP, a scheduled worker or a public API proxy. It has bounded connection/read timeouts and does not follow redirects. If InfinityFree blocks outbound HTTPS, the test fails; leave recruitment closed. Report the failed test/result code before selecting an alternative. Native PHP mail is not a fallback. A conventional PHP host allowing outbound HTTPS is an alternative; costs have not been verified.

## Privacy and private-upload checks

Populate `privacy_notice` with your approved recruitment notice, `privacy_contact` with a real monitored data-rights contact, and `notice_version` with your notice’s revision identifier. The notice should explain controller/contact identity, what is collected, purpose/lawful basis, retention, rights/request process and the use of the transactional email provider. Have the final notice reviewed against applicable Nigerian data-protection requirements before setting `privacy_approved` to boolean `true`.

The acknowledgment checkbox records that the applicant read the notice; it is not an assertion that consent is always the legal basis. Applications never subscribe anyone to marketing. Minors remain disabled: all roles and submitted ages must be 18 or older. Age/eligibility is self-declared, not independent identity verification; establish safeguards before considering minors in a future change.

For file questions, use a normal browser on your phone to open:

**https://seeyoulater.freedev.app/storage/private/access-check.txt**

The result must be **403 Forbidden**, with none of the marker’s contents. InfinityFree may first perform its browser check. Also verify `/database/upgrade-milestone-3.sql` and `/config/config.example.php` remain denied. If the marker is readable or protection fails, stop: do not set `access_verified`, do not publish file-upload roles, and investigate the hosting layout. Do not remove deny rules to make uploads work.

After the non-sensitive marker is denied, set `storage.access_verified` and `recruitment.uploads_enabled` to boolean `true` to permit an owner-controlled synthetic upload/download test. Do not advertise real recruitment until that test and PHP permissions checks succeed. PHP Fileinfo is required for file questions. The storage directory must be writable, outside `public/` and protected by deny rules when inside the actual document root. The application checks these conditions before opening a file-upload role. A physical location outside the whole web document root is preferable if the host permits it.

Uploaded JPG/PNG/WebP/PDF files are limited to the smaller of the configured limit and 2 MiB each, at most three per application. Hosting `upload_max_filesize`, `post_max_size` and upload-count limits may be stricter; verify them on the account. Large videos/portfolios use external HTTP/HTTPS links. Files are content-checked, stored as random `.bin` objects and served only through authenticated, department-authorized downloads with attachment/nosniff headers. Original filenames are not kept. File checks are not malware scanning; treat downloaded applicant files as untrusted.

For an initial end-to-end hosting test, use synthetic adult-only records and a test mailbox. You may temporarily use a draft test opportunity with no personal requests beyond the fixed fields. Keep ordinary recruitment closed while testing. Run private file tests only after confirming the non-sensitive marker is denied. Once readiness checks pass, an explicitly identified test role can be opened briefly for an owner-controlled test and then closed. Do not leave test roles advertised as real opportunities.

## Open recruitment deliberately

When email and privacy checks pass, change only `features.recruitment` to `true`. In **Settings**, enable the global recruitment switch, confirming your current private code. Both configuration and this Super Admin switch are required.

Create roles under **Casting** or **Crew**. Supply approved descriptions, separate mandatory requirements/preferences, location, availability, adult age limits and a future **UTC** deadline. No roles or casting facts are seeded by the SQL upgrade.

The fixed form collects name, email, age, city/region, availability and eligibility/privacy acknowledgments. Add up to 20 custom questions: short/long text, selections, checkboxes, external links and private files. Set required status, order, and selection choices. Three blank rows work without JavaScript; the Add button adds more rows when JavaScript is available. Save the draft, preview it and publish a new version to open the role. Saving any draft pauses that role until republished. Reusable templates copy within the same department. Previous submissions keep their exact published form, role snapshot and notice text.

Casting Managers can manage acting roles/applications; Crew Coordinators manage crew roles/applications. Content Editors cannot access recruitment records. Super Admins control both departments and final selection decisions. Private selection never publishes a character or actor reveal. Closing a role or reaching its deadline stops new submissions, while authorized review/tracking remains available.

## Applicant tracking and staff review

Applicants receive a random `SYA-` reference and a confirmation-email attempt. They do not create accounts. Duplicate same-email/same-role submissions are flagged on both records, without automatic rejection.

Tracking: **https://seeyoulater.freedev.app/public/recruitment.php?action=track**. The applicant enters reference/email, receives a six-digit code, and enters it in the same browser. Requests return the same generic message for mismatched details. Codes expire within ten minutes, are single-use and permit at most five guesses. Request/verification limits persist in the database across browser sessions. New requests replace previous codes for that application. Verified status access expires after ten minutes and cannot be switched to another application by changing a URL ID.

Tracking shows only original published role title, reference, received time and public status. No answers, private notes, selection decisions or attachments are exposed. Public statuses are Received, Under Review, Shortlisted, Audition Requested and Closed. Audition scheduling and invitation systems remain Milestone 4; setting a status does not create an audition or send an invitation.

If receipt delivery fails after an application was committed, the application remains received. Authorized staff can inspect redacted delivery results and contact the applicant manually through the recorded email address. Never share a tracking code/status with an unverified requester or use a public manual verification bypass. Broader campaigns/inbox/status-notification tools remain later milestones.

All private review changes retain reviewer/time/status/note history. Unselected/pending records receive a retention-review due date capped at 12 months after the relevant deadline/earlier closure (or a shorter configured period). Closure cannot extend an already earlier due date. Selected records use a separate production/contractual policy. Actual deletion/anonymization and automated backup management remain Milestone 6; due dates do not automatically delete records. Maintain protected manual backups and handle data-rights requests through the privacy contact until those tools are implemented.

## Deployment verification still required

The owner confirmed that Milestone 2 administration works on this domain. New Resend delivery, private storage, multipart application submission and the full tracking flow still require account-level verification after upload. Local tests used a private capture transport and synthetic records; no real Resend API key or sender address was supplied, and no real applicant email was sent. Automated requests to the host met its JavaScript/AES browser check, so a 200 response from those requests did not prove file protection. Use a normal browser for the hosting checks.

If a critical check fails, leave recruitment closed and report the exact behavior. Do not weaken HTTPS, private storage, email verification or authorization to force compatibility. No hosting migration, paid integration, external private storage service or extra infrastructure has been provisioned.
