## Objective
- Ship and harden the Church CMS Reels feature set: YouTube-link posts shown as vertical reels, auto-captured/overridable video covers, instant XHR uploads, web feed redesign with engagement (like/comment/save, For You/Saved), and the matching Flutter app.
- Make the feed auto-play the newest post on access, play the exact post clicked from the home page, show date/time + feed info, and — new — make uploaded videos play IMMEDIATELY (no "converting when visitors access"), keep uploads fast, and hide the feed scrollbar.

## Important Details
- Production/mobile API base URL: `https://rccglp63yaya.org.ng` (no trailing slash; `mobile/lib/services/api_client.dart`)
- **Production serves STALE assets** — confirmed: `assets/js/feed.js` is 7.3KB OLD (queries `.feed-slide`/`.feed-media` that no longer exist in the new `views/feed.php`; crashes → blank feed) vs 17.7KB local; `admin-media.js` still **404**. User must deploy new assets.
- No FFmpeg anywhere (config `ffmpeg_path` null, no `bin/ffmpeg/ffmpeg.exe`) — uploaded videos previously stored as `pending` originals that NEVER converted → feed showed "Converting…" forever. This round makes them `ready` + playable immediately.
- Flutter SDK 3.44.8 at `C:\Users\User\flutter\flutter`; AGP 9.0.1/Gradle 9.1.0/Kotlin 2.3.20; app on built-in Kotlin — removing share_plus fixed the KGP warning (`a43bdb4`)
- PHP 8.2 lint at `C:\xampp\php\php.exe`; MariaDB 10.4.32 at `C:\xampp\mysql\bin\mysql.exe` (root, no password); Node at `C:\Program Files\nodejs\node.exe`; PowerShell 5.1 only (no `-Form` → `curl.exe -F` + cookie jars)
- Local dev DB admin password reset to `admin123` for testing — original unknown, should be restored
- Git `main`: `dc55960` → `7256cc8` → `7d43b75` → `4d015a7` → `a43bdb4` → `1b0a7b6` → `522f05f` → `d722da1` → `5e77b36`; working tree clean except untracked `summary.md`; `config/database.php` committed with local dev creds
- Headless Edge `--dump-dom` is unreliable (stale/truncated output; kill lingering msedge + fresh `--user-data-dir` per run); one valid deep-link verification obtained earlier
- Two transient `EPERM: operation not permitted, uv_spawn ...powershell.EXE` errors with long multi-step commands — break into short single-purpose commands with `Start-Sleep` between

## Work State
### Completed
- **Instant video playback round (committed `5e77b36`)**: Root problem — uploaded videos were stored `pending` + only converted via background beacon, and with no FFmpeg they stayed pending forever, so every visitor saw the "Converting…" spinner on the feed. Now:
  - `storeMediaItems()` (admin/media.php) always stores uploaded videos as `originals/<file>.<ext>` with `processing_status='ready'` (no `$instant` param, no synchronous FFmpeg) — uploads are fast and posts play instantly. Returns convertible ids so the composer beacon still kicks off `action=process` right after upload.
  - `MediaProcessor::processVideoToReel()` no-FFmpeg and FFmpeg-failure paths now return `status='ready'` (a playable copy) instead of `pending` — the feed never blocks a reel on conversion.
  - `convertPendingItem()` renamed `convertOriginalVideo()` and queries `source='upload' AND file_path LIKE 'originals/%'` (catches ready + legacy pending originals); if the crop isn't ready it keeps the playable original rather than downgrading to pending; on success swaps to `reels/` + unlinks the original.
  - `handleCreatePost()` dropped the `$instant` arg; `process`/`reprocess` handlers updated.
  - **Bug fixed**: stored original previously got PHP's temp extension (`.tmp`) — browsers may refuse it. `collectMediaFiles()` now maps finfo MIME → real extension (mp4/mov/avi/mkv/webm) and it's used in the stored filename.
  - `feed.js`: uploaded-video `<video>` now `preload='auto'` (starts instantly instead of metadata-only). Autoplay machinery (observer + `activateMedia` + top-slide guard) unchanged from `522f05f`.
  - `feed.css`: `.reels-scroller` scrollbar hidden (`scrollbar-width:none`, `-ms-overflow-style:none`, `::-webkit-scrollbar{display:none}`) — scroll still works, bar is invisible.
  - `admin-media.js` success label now "Published! Optimizing video crops in the background…".
  - README updated (instant-ready originals, ffmpeg optional, no stuck pending).
  - **Verified**: XHR video upload → post stored `ready` with `originals/*.mp4` (correct ext), feed API returns `processing_status:"ready"` (no spinner), `action=process` returns `{"status":"success","processing_status":"ready"}` (no-op keeps original playable), served Content-Type `video/mp4` 200, php/node lint clean, test posts/files cleaned, no stale `$instant`/`convertPendingItem`/`pending` refs.
- **Installer fix (committed `d722da1`)**: fresh-install `SQLSTATE 2014` — schema.sql guard blocks ran `EXECUTE ... SELECT 1` (columns already existed) leaving an unconsumed result set. Fixed: no-op branch → `'DO 0'`, `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true` on main + `testConnection`, `importSqlFile()` uses `query()+fetchAll()`. Verified fresh import + idempotent re-run + legacy-upgrade ALTER path.
- **Feed round (committed `522f05f`)**: `feed.js` `?post=ID` deep-link (target post first, dedupe via `seenPosts`, scroll+force-play), top-slide autoplay guard, `activateMedia` refactor, `formatPostedAt()` → "Aug 5, 2026 · 1:30 PM", `.reel-date` + `.reel-cats`, `seenPosts` reset on tab/category switch, share URL `/feed?post=ID`; `views/home.php` cards → `/feed?post=<id>`; verified in headless Edge (`slides=2 | firstId=11 | date=... | cats=Worship | ytAutoplay=true | ytMuted=true`)
- **Admin media (committed `4d015a7`, `1b0a7b6`)**: `MEDIA_*` constants (media.php require'd in method scope — fixed "max 0MB"), `ytId()` accepts `watch?v=`, no-tabs composer with YouTube field visible by default + native-submit fallback; all 4 posting paths verified via curl
- **Mobile share (committed `a43bdb4`)**: `share_service.dart` (MethodChannel `church_media/share`), `MainActivity.kt` ACTION_SEND, `AppDelegate.swift` UIActivityViewController (untested, no macOS); share_plus removed; `flutter analyze` 0 errors, `flutter test` passes, release APK 51.8MB, **no KGP warning**
- README.md at repo root; `.gitignore` excludes `storage/migrations.json`

### Active
- None — committed and pushed; working tree clean

### Blocked
- iOS build/test on Windows (needs macOS/Xcode); AppDelegate.swift untested
- No Play Store keystore (debug-signed APK only)
- **Production still runs stale assets** — deploy new `public/assets/js/feed.js`, `public/assets/css/feed.css`, `public/assets/js/admin-media.js`, `public/assets/css/admin-media.css` (feed.js 200-stale, admin-media.js 404)

## Next Move
1. User must upload the new asset files to production (feed.js, feed.css, admin-media.js, admin-media.css). Until then: feed page renders blank (old feed.js crashes against new HTML), admin composer works only without JS
2. Restore the dev DB admin password if the original is known
3. If desired, deploy the app to Play Store (needs a keystore)

## Relevant Files
- `admin/media.php`: `storeMediaItems()` ready-originals, `collectMediaFiles()` MIME→ext map, `convertOriginalVideo()`, `process`/`reprocess` handlers, no-`$instant`
- `core/MediaProcessor.php`: no-FFmpeg/failure paths return `ready`; `processVideoToReel()` (9:16 crop when ffmpeg present)
- `public/assets/js/feed.js`: `preload='auto'`, deep-link, autoplay guard, `formatPostedAt`, category chips, `activateMedia`
- `public/assets/css/feed.css`: hidden scrollbar, `.reel-date`, `.reel-cats`, `.reel-cat`
- `public/assets/js/admin-media.js`: success label, no-tabs native-submit composer logic
- `views/feed.php`, `views/home.php` (media cards → `/feed?post=ID`)
- `installer/schema.sql` (`'DO 0'` no-op), `core/Database.php` (buffered queries, `importSqlFile` query+fetchAll)
- `core/helpers.php`: `youtubeVideoId()`, `youtubeThumbnailUrl()`, `asset()`, `uploadUrl()` (passes absolute URLs through)
- `api/feed.php`, `api/post.php` (single-post same shape), `api/save.php`, `api/comments.php`, `api/like.php`
- `mobile/lib/services/share_service.dart`, `MainActivity.kt`, `AppDelegate.swift`, `mobile/lib/screens/feed_screen.dart`, `mobile/pubspec.yaml` (share_plus removed)
