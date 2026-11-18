# Milestone 5 — phone upgrade, publishing and reveals

Milestone 5 adds the content studio, approval workflow, public posts/media galleries and independent character reveals. It does not add newsletters, the contact inbox, retention/deletion tools or automated backups (Milestone 6). Your existing recruitment/auditions and Resend configuration remain in place. The owner reports the Nigerian privacy review completed; keep the actual approved notice and operational settings accurate.

## Upgrade your existing Milestone 4 installation

1. Make private backups of the current database, files, **`config/config.php`** and private storage before changing anything. Do not place backups in `htdocs` or GitHub. Pause staff editing during the upgrade.
2. [Download the latest GitHub ZIP](https://github.com/blizz-daniels/SYA/archive/refs/heads/main.zip) and extract it on your phone.
3. Open InfinityFree phpMyAdmin, select the **existing SYA database**, and import **`database/upgrade-milestone-5.sql`**. It adds six tables, the fifth migration record, category/collection labels and two empty character records. It does not overwrite accounts, applications, auditions, private configuration or the setup lock. Do not drop/recreate the database, rerun initial setup, or import an older milestone over it. Schema changes implicitly commit: stop and inspect an import error rather than assuming automatic rollback.
4. Replace the supplied application files in `htdocs`, keeping every `.htaccess`. **Preserve your live `config/config.php`, application key, database password and private storage.** Never activate `public/setup.php` again. The root `index.php` remains the redirect into `public/`. Do not upload `.git`, artifacts, backups or captured test mail. You may omit development-only `tests`, `docs`, `bin` and AGENTS from hosting.
5. No new Resend credentials or configuration block is required. Keep `base_url` as **`https://seeyoulater.freedev.app/public`** and all existing production HTTPS/security settings. Image uploads reuse `storage.private_path` and require the existing **`storage.access_verified => true`** after real protection checks. Do not change private storage location casually: existing attachments/images depend on it.
6. Sign in and open **Content → Open content studio** or **Media → Open content studio**. Super Admin and Content Editor have access; Casting Managers and Crew Coordinators do not.
7. Recheck that `/database/upgrade-milestone-5.sql`, `/app/content.php`, `/config/config.example.php` and `/storage/private/access-check.txt` return Forbidden in a normal browser without source/file contents. Anonymous access to an unpublished `public/media.php?id=...` must return 404. Never expose private storage to make an image preview work.

Image processing requires **PHP GD, Fileinfo and writable protected storage**. The upload action reports a clear error if GD/Fileinfo/protection is unavailable; it never stores original images publicly as a fallback. External HTTPS video links and text publishing can still work. Actual GD, memory, permissions and upload behavior on InfinityFree remain owner verification items. If the host cannot provide secure image handling, keep uploads unavailable and report the error before selecting an alternative; no new paid infrastructure has been introduced.

## Publish your first update

1. Open Content studio and create an Article, Production diary, Announcement, Media post, Character biography or Film information record. Use a unique lowercase URL slug such as `production-announcement-01`. Slugs/types become permanent after creation. The initial Isioma/Ayo records already exist and are male characters; edit those records instead of creating duplicates.
2. Fill title, introduction and body with **approved facts only**. The body supports plain text and paragraphs, not HTML or executable embeds. Optional SEO title/description appear only when that revision is public. Choose a category; add categories under Categories and collections when needed.
3. Click **Save draft revision**, then open the record from the list. Each save preserves an immutable content snapshot. The studio provides private preview and revision/history links. Earlier snapshots can be copied into a new draft; they are not overwritten. If another editor saved first, reload after the stale-edit message.
4. As a Content Editor, **Submit for review**. A Super Admin can **Request changes** with a private note or **Approve revision**. After requested changes, save a new draft and resubmit it.
5. A Super Admin approves and then publishes the approved revision. Super Admins can approve their own draft without another reviewer. **Approval alone does not make content public.** An ordinary Publish action cannot publish a character version containing an actor reveal.
6. Leave the schedule blank to publish now, or enter a **future UTC time**. Nigerian WAT/Lagos time is UTC +1: 6 PM in Lagos is 5 PM UTC. Scheduled content appears on the first request at or after that time, with no cron or worker. There is no guaranteed wall-clock background action or scheduled email. The last published version remains visible until a scheduled replacement becomes due.
7. Saving later edits leaves the approved public snapshot unchanged. Review/approve/publish the new revision separately. Cancelling a schedule leaves the earlier published version; if the schedule was already due, cancelling it restores that earlier version. Archive removes the record from public pages and cancels its schedule; republishing an approved revision restores it.

Published Articles, Diaries, Announcements and Media posts appear under Production Updates and in the homepage preview. A Film information record appears on About the Film and replaces the provisional information section. The site starts with placeholders, not invented official information. Public posts have permanent detail links; category-filtered updates and collections/media paginate 20 per page so older published records remain accessible.

## Upload and publish media

1. Open **Upload and collections**. Upload a JPG, PNG or WebP within the existing `uploads.max_bytes` limit (default 2 MB, capped at 5 MB for media). Maximum 12 megapixels / 12,000 pixels on either side; the host memory guard may require a smaller image. Rotate/orient phone images correctly before upload; automatic EXIF orientation is not provided.
2. The server validates and decodes it, strips original metadata and filename, and generates JPEG variants up to **480, 960 and 1600 px wide** without upscaling. Transparent areas flatten onto ivory. The original upload is not retained. The private image library paginates 30 uploads per page; save your draft before navigating to another image page. Its image selector includes the current page plus any image already attached to the record, so editing never silently drops an older selection. Uploaded media has an internal image number and remains private; it is not an announcement by itself.
3. Create/edit a Media post. Choose the image number as cover/gallery image and enter **meaningful alt text**, caption, approved credit and collection. For a video, provide an external HTTPS URL instead of uploading the video. No external provider scripts, automatic embeds or autoplay are used. Both image and video link may be attached to a post.
4. Submit, approve and publish through the same workflow. The public Media page provides collection filtering, responsive thumbnails and a full-screen viewer. Keyboard: Enter to open, Left/Right to browse, Escape/Close to exit and return focus. Horizontal swipe is supported. Without JavaScript the image links still open the full-size optimized image.
5. Image URLs are application-controlled: only assets referenced by effective approved public content are served anonymously. Staff-only previews require a valid authorized session. Archiving the only public reference revokes future image access. If an asset is reused in other published posts, archive those references too. A portrait attached to an unrevealed character draft is blocked from anonymous image access even if selected by another post. Use separate assets for unrelated public artwork.

Revocation cannot erase images someone already downloaded or copied elsewhere. Public content/media responses use no-store to reduce stale unpublished/revoked information; static CSS/JS assets remain ordinary local files. Unused image files remain private; automated cleanup/deletion management is not part of this milestone. Retain and back up the protected `cms` subdirectory with the existing private storage.

## Reveal Isioma or Ayo independently

1. Open the seeded character record. The public introduction/body/SEO fields must contain **spoiler-free character information only**. Keep unannounced actor names, biography and portrait in the separate **Private actor reveal draft** fields; do not type them into public copy or publish the portrait as unrelated media.
2. While unrevealed, save/approve/publish the spoiler-free character information with **Prepare this version for an actor reveal unchecked**. The public card remains an anonymous question-mark silhouette; private actor fields are omitted from public markup and image access is denied.
3. When authorized to announce the actor, prepare an approved Announcement record and publish it. Only put information in that announcement that is authorized for public release.
4. On the character draft, check Prepare this version for an actor reveal, fill the actor name, approved biography, portrait, portrait alt text and linked announcement. Save, submit and approve the revision.
5. The Super Admin must use **Publish approved character reveal** and tick the explicit approval checkbox. Ordinary Publish rejects a reveal. The announcement must already be public. You may schedule the explicit reveal using UTC; if the linked announcement is later archived, the reveal/portrait are hidden until that dependency becomes public again.
6. Inspect Characters and Home in a signed-out/private browser. Only that character should reveal; the other remains independent. Casting selection, recommendations and scores never trigger a public reveal.

## Homepage controls

Super Admins can enable/disable and order Hero, Story, Characters, Join production, Updates and Stay connected under **Homepage sections**. Smaller positions appear first; ties keep the standard section order. A hidden hero receives a screen-reader page title, and a disabled Story section does not leave the hero's Explore link pointing at a missing section. These controls do not enable future newsletters/contact features or open recruitment.

## Hosted acceptance checks

Use clearly labelled temporary test material; archive/remove it from public display after checking, and never publish real private actor details as a test.

- Editor creates a draft: signed-out detail URL must return 404; staff preview works. Submit → request changes → new revision → approve → publish. Verify private review notes never appear publicly.
- Edit a published post: the old version must remain public until the new version is approved/published. Test a schedule a few minutes ahead in UTC, cancel a schedule and archive/restore a post.
- Upload one phone image. Confirm GD/protection work, anonymous access before approval fails, published responsive images load, captions/credits/alt text are correct, and the gallery/filter/full-size viewer works on your phone.
- Test a temporary fictional actor name/portrait in an unrevealed character draft. Signed-out Characters/Home and page source must omit the name/biography/portrait URL. Do not reveal real actor data without approval. Test explicit Super Admin reveal and the announcement dependency, then archive the test content.
- Content Editor must be denied direct approve/publish/archive/reveal and homepage writes. Casting/Crew users must be denied Content studio and private media previews. Super Admin controls must work.
- Toggle/reorder homepage sections, then restore the preferred arrangement. Check all eight public pages plus existing login, applications/tracking, protected attachments, audition invitations and evaluations after upgrade.

No live InfinityFree Milestone 5 deployment, GD upload or production publishing was performed by the assistant. Record the exact action/error if a hosted check fails; never send private codes, database passwords, API keys, actor drafts or invitation tokens.

## Local and Windows setup

Follow README for PHP 8.2+, MySQL/MariaDB, ignored `config/config.php`, migrations and `php -S 127.0.0.1:8000 -t public`. Enable GD/Fileinfo/PDO MySQL in your PHP configuration and restart PHP after changing extensions. On Windows 11 use PowerShell's `Copy-Item config/config.example.php config/config.php`, check `php -m`, start your local database service, then run `php bin/migrate.php`. Windows and physical-device execution remain unverified here.

Tests require disposable `environment=local`, a `*_test` database, development-only Playwright/axe packages and explicit `SYA_TEST_RESET=YES`. Run `tests/prepare-content.php` then `tests/content.cjs` with `TEST_URL`, `PHP_BINARY`, `NODE_PATH`, `AXE=1`. It destroys synthetic fixture data; never point it at real/hosted data. Run `tests/content-races.cjs` afterwards against two independent PHP servers using `SECOND_TEST_URL`. Public-only regression can run after resetting to empty content. Keep Node/testing packages out of production.
