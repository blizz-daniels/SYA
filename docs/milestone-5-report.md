# Milestone 5 — CMS, media and character reveals

Date: 2026-11-18. Authorized by the owner after Milestone 4 implementation and hosted invitation testing. The owner reports Nigerian privacy review completed. Only Milestones 1–5 are implemented; **Milestones 6–7 have not been started**. No production files, database, credentials or launch settings were changed by the assistant.

## A. Implementation summary

Implemented a lightweight PHP content studio within the existing production office. Super Admins/Content Editors create Articles, Production diaries, Announcements, Media posts, Character biographies and Film information, with categories/collections, cover images, SEO title/description, private preview, revision history, archiving and approval records. Body content is safe plain text with paragraphs; no third-party editor/framework is required.

Every save creates an immutable content revision. Editors submit drafts and Super Admins request changes or approve them. Only Super Admins publish/archive, including their own approved drafts. A published revision remains visible while edits are reviewed. An optional approved scheduled replacement becomes effective on the first page request at/after its UTC time, without cron/workers. Scheduling/approval actions are recorded. Direct unauthorized POSTs are rejected server-side.

Public Home/About/Characters/Updates/Media now consume approved content with honest empty states. Updates/media filter by public categories/collections and paginate 20 entries; detail links preserve access to older public material. Super Admins can enable/disable/reorder homepage sections. Public copy stays provisional until approved material is supplied; no actor identities/credits/releases or completed milestones are invented or seeded.

Image uploads validate MIME/dimensions/size/memory, decode with GD, discard the original file/name/metadata and write JPEG variants up to 480/960/1600 px in private storage. Media metadata is part of the reviewed revision. Anonymous image delivery requires an effective public reference; staff previews require appropriate authentication/permissions. Unrevealed portraits are blocked even through another cover association. The gallery has responsive thumbnails, captions/credits/alt text, collection filters, a full-screen native dialog, keyboard/focus return, horizontal swipe and a no-JavaScript image-link fallback. Videos use approved external HTTPS links; no large uploads or automatic embeds.

Isioma and Ayo remain independently unrevealed male characters until a Super Admin explicitly publishes an approved reveal with a confirmation checkbox and an already-public announcement. Private actor fields do not enter public markup/metadata or public image access before reveal. Archiving the announcement hides the linked reveal. Recruitment selection, auditions and evaluation scores never publish actors automatically.

## B. Important files

| Files | Purpose |
| --- | --- |
| `app/content.php` | Validation, permissions, immutable revisions, workflow, public revision queries, schedules and homepage controls |
| `app/content-media.php` | GD optimization, storage safety and public/private image authorization |
| `app/content-public.php` | Page-specific approved content loading, public filters/pagination and character sanitization |
| `public/content.php` | Authenticated content studio controller with setup/session/CSRF/role guards |
| `public/media.php` | Controlled JPEG delivery, staff previews and generic private-resource denial |
| `resources/views/content-*.php` | Studio, preview, approved stories, update lists and gallery templates |
| `resources/views/home.php`, `characters.php`, `pages.php`, `layout.php` | Dynamic homepage sections, independent reveal cards, public-page/SEO integration |
| `public/assets/js/gallery.js` | Native dialog, keyboard navigation, swipe and focus restoration |
| `public/assets/css/site.css`, `admin.css` | Responsive CMS/gallery styling, visible focus and entrance-animation contrast fix |
| `database/migrations/005_content_media.sql` | Six-table additive schema and honest default labels/empty character records |
| `database/upgrade-milestone-5.sql` | Existing-install phone/phpMyAdmin upgrade and matching checksum ledger |
| `tests/content*.php`, `tests/content*.cjs`, `prepare-content.php` | Local-only synthetic unit/workflow/concurrency/viewport verification |
| Updated database/browser/hosting/fixture tests | Fifth-version schema, permitted public GET filters, new private-path denial and fixture cleanup |
| `docs/milestone-5-setup.md` | Phone upload, studio operation, explicit reveals and hosted test checklist |

README, AGENTS and hosting feasibility were updated. Private config/storage, real credentials, active setup, generated fixture codes, screenshots and test images are excluded from Git. Existing migrations were not modified.

## C. Database changes

Migration 005 adds **`cms_categories`, `media_collections`, `media_assets`, `cms_items`, `cms_revisions`, `cms_events`**. Total schema: **29 tables, five migration records**.

Items hold permanent type/slug plus current-draft, published and scheduled revision pointers. Every public query joins both revision ID and item ID and requires approved state; no draft fallback is public. Revision content/metadata are immutable; only workflow state changes. Category/collection labels do not have an unreviewed rename route. Foreign keys connect revisions to items/assets/categories/collections and optional administrators; author names are preserved if accounts are removed. Pointer ownership is enforced transactionally by the application and by paired joins (pointers are not circular foreign keys). A locked item row plus an expected revision prevents lost updates. File bytes are immutable per upload; new uploads receive new random storage keys.

The SQL seeds category/collection labels and empty Isioma/Ayo item records, not published content. A repeated additive import into an independent Milestone 4 fixture produced 29 tables/five ledger entries, retained the original setup timestamp and legacy delivery row, and did not duplicate character items. DDL implicitly commits; backup/inspect on failures. The PHP migration runner also passed repeat/checksum checks.

## D. Tests executed and actual results

Environment: disposable local PHP **8.4.27**, MariaDB **11.8.6**, Apache **2.4.69**, GD, Chromium/Playwright and axe. Real production data/API keys were not used. Mail regression used the existing explicitly local capture transport. Raw results: [milestone-5-test-results.txt](milestone-5-test-results.txt).

| Test | Execution method | Actual result | Status |
| --- | --- | --- | --- |
| PHP syntax | `php -l` across app/public/resources/config/bin/tests | All checked PHP files parse | PASS |
| JS syntax | `node --check` for gallery and both new browser suites | All three parse | PASS |
| PDO/schema | `php tests/database.php` | Native prepares, literal UTF-8/SQL metacharacters, exactly 29 tables/five versions | PASS |
| PHP migrations | `php bin/migrate.php` twice | Applied hashes match; all five versions recognized | PASS |
| Phone SQL upgrade | Import M5 SQL twice into independent existing M4 fixture, inspect old rows/ledger | 29 tables/five records, original setup timestamp and receipt delivery retained; one Isioma/one Ayo | PASS |
| Validation/storage guards | `php tests/content-unit.php` | Ten checks covering safe URLs/UTF-8/control/byte limits, unauthorized roles and unsafe/unverified storage | PASS |
| Missing GD | Same unit script in separate PHP process without GD | Upload fails closed before accepting bytes; 11 checks pass in this configuration | PASS |
| Workflow/permissions | `AXE=1 node tests/content.cjs` with synthetic fixture | Guest/cross-role/CSRF denial; categories/collections; submit → changes → revised submission → approval → publication; editor publish denial; stale writes/history | PASS |
| Revision/public isolation | Same suite | Draft/SEO/private notes withheld; safe escaped text; historical preview unchanged; old published snapshot preserved during edits and before scheduled replacement | PASS |
| Schedule/archive | Same suite, test-only clock fixture advances scheduled timestamp | Due approved replacement visible on request without cron; archive produces 404; approved republication restores it | PASS |
| Upload/media | Same suite, real HTTP multipart PNG uploads | Spoofed and oversized uploads rejected; private previews authorized; derivatives are JPEG and strip a trailing test payload; required alt text and HTTPS external video validation | PASS |
| Reveal confidentiality | Same suite | Unrevealed actor name/bio/portrait absent from public pages/source; direct private image/detail routes denied; explicit Super Admin confirmation and published announcement required; Ayo stays independent | PASS |
| Image/reveal revocation | Same suite | Archiving announcement hides reveal/portrait; archiving last published media references revokes anonymous image access | PASS |
| Homepage/film/filter/pagination | Same suite, synthetic multi-page fixture | Super Admin section order/visibility, published film info, category/collection filters and older update/media/staff records reachable | PASS |
| Populated responsive/accessibility | Same suite with axe, 360/768/1440 px | 39 page/dialog/staff combinations; zero configured WCAG-tag violations, no horizontal overflow, no browser JS/console errors | PASS |
| Gallery keyboard/swipe/fallback | Same suite | Open, ArrowRight, synthetic horizontal touch swipe, Escape and focus return; staff skip link and no-JS full-image link | PASS |
| Concurrent CMS edits | `node tests/content-races.cjs`, independent PHP processes on two ports | Exactly one new revision accepted; stale competitor rejected with 409; publication remains separate | PASS |
| Existing administrator security | `AXE=1 node tests/admin.cjs` | Setup/lock/login/import/role/session/rate-limit/CSRF workflows and 15 viewport/module checks | PASS |
| Existing recruitment | Recruitment, editor/settings browser suites | Forms/publication/files/tracking/retention/closure; 21 viewport checks; editor/no-JS fallback, delivery settings/log/switch | PASS |
| Existing auditions/evaluations | Production browser/race suites | RSVP/reschedule/cancel/callback/attendance/weighted history, 21 viewport checks plus receipt, private tracking and two-process booking/review races | PASS |
| Existing unit/security | `security.php`, `recruitment-unit.php`, `production-unit.php` | Security/session/HTTPS, dynamic form and scoring/date/URL checks pass | PASS |
| Public regression | `AXE=1 node tests/browser.cjs` against empty CMS state | Eight pages × 320/360/768/1440, navigation/keyboard/reduced-motion/no-JS/security checks; no console errors | PASS |
| Shared-root protection | `python tests/hosting.py` plus new endpoint probes against local Apache | 24 private paths denied with 403; eight public pages/assets/root redirect work; content requires login, private media returns 404 | PASS |
| InfinityFree M5 upload/GD/publishing | No account deployment/access performed | Host-specific privileges, memory, image uploads/serving and publishing remain to be verified | UNVERIFIED |
| Windows/physical phone/screen reader | Setup supplied; no such device session | Not executed | UNVERIFIED |

Development verification exposed a transient homepage fade contrast issue, an unauthorized-media expected-status mismatch in the test, a synthetic touch-event fixture missing its identifier, a test login using a noncanonical port, and trailing-null validation after trimming. These were corrected and relevant suites rerun successfully. The entrance now uses motion without fading text contrast. Unit output buffering avoids CLI-only header warnings. The existing audition delivery-failure regression deliberately makes local capture storage unwritable; its expected protected-log warning is not a production failure. No unexpected PHP runtime error remains in successful final CMS runs.

## E. Visual and responsive verification

Populated CMS checks cover Home, About, Characters, Updates, Media and an article detail; an open gallery dialog; staff list/new draft, article edit, character edit, private preview, media tools and Super Admin reveal controls at **360, 768 and 1440 px**. These total 39 page/viewport combinations. Public empty-state regression additionally covers all eight public pages at **320, 360, 768 and 1440 px**. Inspected saved 360/1440 gallery screenshots and staff view: readable cinematic palette, cards/forms fit, images retain aspect ratio, visible keyboard focus and a single-column phone layout. Focused gallery links retain a gap before their captions to avoid overlap.

Keyboard gallery/skip-link checks passed; synthetic touch dispatch verifies the swipe handler, not physical-device behavior. The no-JavaScript image link works. Axe scans use the existing WCAG A/AA/2.1/2.2 tag set; this is not a complete WCAG certification, screen-reader or real-device assessment.

## F. Security observations

Content/media permissions are enforced by PHP, not menu visibility. Staff operations require sessions/CSRF, setup completion and temporary-setup removal. PDO statements bind user values; IDs/slugs/URLs/text are validated and output escaped. No raw HTML or third-party embed script is rendered. Draft previews have no-store/noindex and authenticated access. Public images/markup use only effective approved associations; private actor fields are separated from public character fields and not exposed by a JSON API. Unreleased images have no static public path, and predictable image IDs do not bypass authorization. Image decode/re-encoding removes original metadata and appended payloads. Storage must be outside public, verified and not a symlink for its CMS child directory.

Staff can still deliberately type confidential information into public body/SEO fields: those fields are clearly marked, and approval must cover them. Dedicated actor fields are withheld automatically until explicit reveal. Treat the linked announcement as an independently approved publication. Public downloads cannot be recalled after recipients save/copy them. Publishing/archiving never changes recruitment decisions or sends email.

GD/memory/permissions must be verified on the actual host. No unsafe fallback was introduced. Unused assets/history are retained privately; automated deletion/retention/backup tools remain Milestone 6. Private media storage must be backed up along with the database. No production credentials or live configuration are in Git.

## G. InfinityFree feasibility

Architecture stays PHP/MySQL/vanilla JS with native sessions and ordinary InnoDB transactions/indexes. Scheduled publication uses read-time approved revision selection, not a worker/cron. New media processing requires GD/Fileinfo, protected writable storage and sufficient memory; optional EXIF orientation is not implemented, so rotate phone images before upload. Text and external video links remain usable if image processing is unavailable, with uploads failing safely. Local Apache/DB/GD behavior was tested; actual InfinityFree capabilities remain account-level verification. See [hosting feasibility](hosting-feasibility.md). No new paid service/provider integration was introduced and no production deployment is claimed.

## H. Local setup

README and [Milestone 5 setup](milestone-5-setup.md) cover PHP 8.2+/Ctype/PDO MySQL/Fileinfo/GD, local database/config creation, `php bin/migrate.php` and `php -S 127.0.0.1:8000 -t public`. Open localhost on the computer actually running PHP; assistant localhost is separate. Windows 11 PowerShell instructions are supplied but unverified. Use a protected ignored config and a disposable `*_test` database for fixtures. The PHP built-in server ignores `.htaccess`; never serve the repository root with it. Node/Playwright/axe are development tools only.

## I. Outstanding issues and limitations

- Owner must verify the new SQL import, host GD/Fileinfo/memory/permissions, actual phone uploads/publishing and role/reveal protections after upload. No live InfinityFree M5 test was performed.
- Plain text editing is intentional; no rich HTML editor, inline script/embeds or large video upload. Images flatten transparency to ivory and are capped at 1600 px; original uploads are discarded and automatic EXIF orientation is not supplied.
- Staff records/images paginate 30; public updates/media paginate 20. Categories/collections/announcement choices are bounded at 200, character directory at 100, published About information at 20, and displayed revision/events history at 100 per record. Earlier history remains stored. Save before changing image pages; existing selected older assets remain available in the editor.
- Schedule evaluation needs a request and a correct server UTC clock; no timed email/background dispatch. Cancelling an already-due scheduled replacement restores the previous published pointer.
- Unused assets, history cleanup, rights/retention tooling, newsletters/contact and automatic backup/restoration remain later scope. Maintain manual protected backups.
- No known blocking failure remains in executed local checks. Device/Windows/screen-reader and production-host behavior remain unverified.

## J. Next milestone readiness

Milestone 5 is implemented, documented and ready for owner-hosted acceptance. Immutable content/publication states, protected assets and separate private actor metadata prepare for broader communications/privacy/backup work. **Stop here. Milestone 6 requires explicit approval.**
