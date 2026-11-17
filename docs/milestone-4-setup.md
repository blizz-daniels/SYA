# Milestone 4 — phone upgrade and audition testing

The owner has approved Milestone 4. Milestone 5 is not included. The correct site is **https://seeyoulater.freedev.app**. Keep real recruitment closed until the pending Nigerian privacy review is complete. Passing technical tests does not approve the privacy notice or lawful basis.

## Upgrade your existing Milestone 3 installation

1. Back up the existing database and uploaded project files privately. Keep a separate safe copy of `config/config.php` and private attachments. Never publish these backups or put them in GitHub.
2. Pause use of the administration portal during the upgrade. Download and extract [the latest GitHub ZIP](https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip).
3. In InfinityFree's phpMyAdmin, select your **existing** SYA database. Import **`database/upgrade-milestone-4.sql`** from the ZIP. This is an additive upgrade from Milestone 3; do not drop tables or import a fresh database over your existing records. It adds nine tables and the fourth migration ledger entry. Existing accounts, applications and permanent setup lock are preserved. Back up first: MySQL schema changes do not automatically roll back.
4. Upload/replace the supplied project files in `htdocs`, including all `.htaccess` files. **Preserve your existing `config/config.php`, application key, database password and private storage.** Do not activate `setup.php` or run initial administrator setup again. Do not upload `.git`, test artifacts or backups. Development-only `tests`, `docs` and `bin` may be omitted from hosting.
5. Your existing Resend provider, API key and verified sender are reused. No new email account or API key is needed. In your private configuration, set the existing top-level entry to:

   ```php
   'base_url' => 'https://seeyoulater.freedev.app/public',
   ```

   A complete configured HTTPS URL is required to create invitation links. Do not copy the assistant's local test database settings. Keep production HTTPS/security settings enabled. Existing configurations work without a new auditions block; invitations default to 48 hours and always expire by the slot's start time. If desired, add this optional top-level entry alongside `email`, not inside it:

   ```php
   'auditions' => ['invitation_hours' => 48], // allowed 1–168; also capped at slot start
   ```

6. Sign in to your existing production office. Open **Casting → Auditions and callbacks** or **Casting → Evaluations**. Crew Coordinators use **Crew → Evaluations**; they cannot access acting auditions. Content Editors have neither module. If a database error appears, check that the SQL import succeeded before changing configuration.
7. Verify `/database/upgrade-milestone-4.sql`, `/app/production.php` and `/config/config.example.php` return Forbidden in a normal browser, without source content. Keep all private paths protected.

## Privacy review before real use

Ask your reviewer to cover private audition scheduling, physical/virtual meeting details, attendance, callbacks, structured scores, private reviewer notes, transactional email providers, applicant rights and retention. Explain the actual production arrangements and lawful basis; do not use invented registration/notice numbers. Keep the recruitment launch switch off while review is pending. The existing `recruitment.privacy_approved` setting also gates audition email sending: set it true only after the real review is complete. Until then, inspect sessions/evaluation controls without sending invitations or collecting real applicant information. The automated tests used disposable synthetic data and a local mail capture, not a live recruitment launch.

## Auditions and callbacks

1. As a Casting Manager or Super Admin, create a physical or virtual session. Use a clear title, **Africa/Lagos** for Nigerian local time (WAT), location or HTTPS meeting link, and approved applicant-facing instructions. UTC is also available. Dates are stored as UTC. Private notes are never applicant instructions.
2. Give each room/panel a consistent **resource key**, e.g. `lagos-panel-1`. Overlapping sessions with that key are rejected. Different keys allow parallel panels. This cannot detect that two differently named keys refer to the same actual room: staff must keep naming consistent.
3. Add slots manually or generate them. Sessions are limited to 24 hours and 100 slots; generation allows 5–120 minute slots with 0–60 minute breaks. Slots cannot overlap or extend outside their session.
4. Assign an existing acting application to an available slot. One slot has one active booking. The same applicant email cannot have overlapping active auditions across different roles/panels. These checks include concurrent requests. No applicant account is required.
5. Assignment **queues** an invitation. Click **Send notice** for each pending notice. Messages are sent one per request, without cron or background workers. A successful provider response means accepted for delivery: confirm actual arrival separately. Failed notices can be retried. A retry can replace the previous link; ask the applicant to use the latest email. A sending lease lasts 60 seconds after an interrupted request. Messages already superseded by schedule changes should not be sent.
6. The applicant opens the private email link and confirms or declines. Opening the link is read-only and removes the token from the visible URL. Confirmation/decline requires an explicit protected POST; each link can record one response. Declining releases the slot. The link expires at the earlier of its configured lifetime or slot start. Do not forward it. After responding, the applicant can view current details through existing **email-verified application tracking**.
7. Use **Manage booking** to reschedule, change the round or cancel. Rescheduling invalidates earlier invitation links and temporary receipts, resets the response and queues an update. A session with active bookings cannot be edited: create a corrected session and reschedule those bookings. Cancelling a session releases its bookings and queues individual cancellation notices; send each notice manually. Changing a record does not send email automatically.
8. Record attendance after the slot starts. Create a private callback request, then explicitly book a callback round with its matching open request. A recommendation to request a callback never creates an appointment automatically.

External meeting links are provided by staff; this application does not create rooms or change meeting-provider permissions. If access must be revoked, change the meeting link/settings at that provider too. Staff notes and evaluation history remain private. Appointment details shown to an applicant are explicitly approved fields captured at booking, not private session notes.

The overview lists up to 100 sessions, 200 recent application choices, 300 free slots and 100 pending notices/callbacks. Booking history is paginated in groups of 20; manage one booking at a time. Open older applications from recruitment review when needed. Displayed evaluation/event history is bounded to 100 records and criteria version listings to 50; older database records are retained. This is intended for a small production, not an unlimited scheduling service.

## Acting and crew evaluation

1. Open Evaluations in the correct department and select a role. Edit its assessment criteria (1–20). Default acting and crew criteria are provided for editing. Leave weight at 1 for equal weighting; positive custom weights are supported. Save the draft, then **publish a new assessment version**.
2. Select an application. Score every published criterion from 0 to 10 (up to two decimal places), choose a private recommendation, and optionally write private feedback. The weighted score is `sum(score × weight) / sum(weight)`.
3. Saving a changed review creates a new revision under the signed-in reviewer's identity. Previous reviews and the criteria version used remain in history. A simultaneous/stale save is rejected: reload before retrying.
4. These scores/recommendations do **not** select/reject an applicant, change their public tracking status or reveal an actor. Final recruitment decisions remain the separate authorized Milestone 3 action. Crew evaluation does not add a crew interview scheduler.

## Hosted acceptance checklist

Use only your own synthetic test applications/mailboxes after the applicable review gates permit testing. Do not expose real applicant details in screenshots.

- Create physical and virtual sessions; verify Lagos time, overlap rejection and slot generation.
- Book an acting applicant; attempt a duplicate slot and an overlapping booking for the same email across roles. Both should be rejected.
- Send one invitation; verify real inbox arrival and the correct HTTPS domain. Preview alone must leave the response invited. Confirm, check email-verified tracking, and ensure replay cannot submit another response.
- Reschedule: old links must stop working, new notice must arrive, and tracking must show the new schedule. Test decline, cancellation, callback and attendance.
- Publish acting/crew criteria; save and revise two staff reviews; verify old versions/reviewer history and department permissions. Applicant tracking must show no private notes/scores. Verify no final decision changes automatically.
- Test on your phone, with keyboard where available, and verify private-file denial. Recheck existing login, application submission, Resend tracking and downloads after upgrade.
- Close synthetic roles and keep real recruitment closed until privacy approval. Record hosted failures before enabling real recruitment.

No live InfinityFree Milestone 4 deployment has been verified by the assistant. Send the exact error text and which action produced it if a hosted check fails; do not send passwords, API keys or invitation tokens.

## Local verification

Follow README's PHP/MySQL and Windows 11 setup, preserve a disposable local config, then run `php bin/migrate.php`, repeat it, and run `php tests/database.php`, `php tests/security.php`, `php tests/production-unit.php`. Migrations require PDO MySQL. These Windows instructions have not been executed on Windows.

The destructive fixtures require `environment=local`, an explicitly named `*_test` database, and `SYA_TEST_RESET=YES`; never target hosted/real data. Start the existing local-only mail-capture transport with `SYA_TEST_MAIL_DIR=/tmp/sya-m3-mail`, clear that disposable directory between fixture runs, prepare `tests/prepare-production.php`, then run `tests/production.cjs` with `TEST_URL`, `PHP_BINARY`, `NODE_PATH` and optional `AXE=1`. See the test scripts for the default localhost ports. Run `tests/production-races.cjs` afterwards with a second independent PHP process at `SECOND_TEST_URL` to verify database contention rather than PHP's single-server serialization. Browser/axe packages and Node are development tools only. Never enable the test capture transport in production.
