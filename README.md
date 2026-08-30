# Church Media Management System

A self-hosted church media CMS with an Instagram Reels–style media feed, an admin
composer for images/videos/YouTube posts, a clean JSON API, and a matching Flutter
mobile app.

- **Web** — PHP 8.2 flat-file app. Public site serves the feed, events, sermons,
  giving, and livestream pages; `admin/` and `api/` live outside the web root and
  are reached through the front router (`core/Router.php`) for clean URLs.
- **Mobile** — Flutter app in `mobile/` that mirrors the web feed.
- **Database** — MariaDB/MySQL (`installer/schema.sql`), migrated automatically on
  boot via `Database::migrate()`.

## Features

- **Reels-style media feed** (`/feed`): vertical 9:16 reels with a center action
  rail — like (double-tap also works), comment, share, save, and a “For You /
  Saved” tab switcher plus category chips.
- **Media sources** — every post item is either an `upload` (image or video) or a
  `youtube` embed. Uploaded landscape videos are auto-cropped to 9:16 reels when
  FFmpeg is available; otherwise the original plays as-is (browsers handle it) so
  a reel is never stuck “converting”.
- **Instant uploads** — admin uploads are fast and the post is ready to play
  immediately: videos are stored as `ready` originals and, when FFmpeg is
  configured, cropped to a 9:16 reel in the background right after upload via
  `admin/media?action=process` (kicked off with `navigator.sendBeacon`).
- **Engagement** — anonymous per-visitor likes, saves, and comments keyed by a
  fingerprint cookie; all three are rate-limited.
- **Admin composer** (`/admin/media`) — Upload / YouTube tabs, live preview grid,
  per-item auto-captured cover with tap-to-replace, publish toggle, edit/reprocess/
  delete.
- **Mobile app** — For You/Saved tabs, category chips, YouTube playback via
  WebView, double-tap like, comment sheet, share.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd` (webp support) enabled
- MariaDB 10.4+ / MySQL 5.7+
- Apache (or any server honoring `.htaccess`) or the PHP built-in server
- Optional: `ffmpeg` binary for true 9:16 reel cropping — set `ffmpeg_path` in
  `config/site.php` or via `/admin/settings`. Without it, uploaded videos still
  play immediately as their original file.
- Flutter SDK 3.44+ to build `mobile/`

## Quick start (XAMPP)

1. Copy the project into `C:\xampp\htdocs\church-media`.
2. Create a database (e.g. `church_media`) and set credentials in
   `config/database.php` (dev default: `root` / empty password / `127.0.0.1`).
3. Open `http://localhost/church-media/` — the installer walks through
   requirements → DB import → admin account → finish, writing
   `storage/installed.lock`.
4. Log in at `/admin` and create media posts under **Media**.

> Already-installed databases are upgraded automatically: on every boot the app
> runs `Database::migrate()` (`core/Database.php`), which applies additive
> migrations (e.g. the `media_post_items.source` column, `saves_count`, and the
> `post_saves` / `post_comments` tables) idempotently.

## Updating an existing installation (no data loss)

Updates never touch your data **as long as you don't overwrite the
machine-specific files** with the fresh copy:

- **Keep** `config/database.php` (your live DB credentials)
- **Keep** `storage/` — it holds `installed.lock` and `migrations.json`; if a
  botched upload wipes the lock, the app detects the existing database schema
  and restores the site automatically (no reinstall prompt)
- **Keep** `public/uploads/` (your media files)

Recommended flow (cPanel / FTP):

1. Take a backup first (database + `public/uploads`).
2. Upload only the files you changed (e.g. `core/`, `admin/`, `views/`, `api/`,
   `public/assets/`, `installer/schema.sql`), **or** extract the full zip but
   skip `config/database.php`, `storage/`, and `public/uploads/`.
3. Open the site — migrations run automatically and you stay logged in.

If you *did* overwrite the wrong files and the installer shows up:

1. Open `/install` and on step 2 enter your **existing** database details.
2. The installer detects the existing database, **skips the schema import** (no
   data is touched), reconnects, and takes you straight to the finish screen.
3. Done — you're back online with all your data, no reinstall needed.

### PHP built-in server (dev)

```
php -S 127.0.0.1:8099 -t public
```

## API

All endpoints are anonymous, JSON, and rate-limited per visitor fingerprint.

| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/feed` | GET | Paginated reels feed. Params: `page`, `per_page` (≤30), `category` (slug), `saved=1`. Returns `has_more`, per-post `media_items[].source`, `saves_count`, `comments_count`, `saved_by_viewer`, `author_username`. |
| `/api/post` | GET | Single post by `id` or `slug`; records a deduped anonymous view. |
| `/api/save` | POST | Toggle save for `post_id`. Returns `saved` + `saves_count`. |
| `/api/comments` | GET / POST | List comments for `post_id` (published only); POST adds one with `name` (≤100) and `message` (≤1000). |
| `/admin/media` | POST | Admin actions: `upload` (instant, XHR), `process` (background conversion), `reprocess`, `edit`, `toggle`, `delete`. |

YouTube URLs accepted in watch / shorts / embed / live / youtu.be / mobile form
(see `youtubeVideoId()` in `core/helpers.php`); thumbnails fall back to
`https://i.ytimg.com/vi/<id>/hqdefault.jpg`.

## Directory layout

```
admin/        admin-only scripts (media composer, dashboard, settings)
api/          public JSON endpoints
config/       paths.php, database.php, site.php, routes
core/         Router, Database, Auth, MediaProcessor, RateLimiter, helpers…
installer/    guided installer + full schema (schema.sql + migration block)
public/       web root (index.php, assets/css|js, uploads/)
views/        public page templates (feed, home, events, sermons…)
mobile/       Flutter app (lib/, android/, ios/…)
storage/      installed.lock, migrations.json, logs
```

## Mobile app

```powershell
cd mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --release   # → build/app/outputs/flutter-apk/app-release.apk
```

The app targets the production API configured in
`mobile/lib/services/api_client.dart` (`https://rccglp63yaya.org.ng`).

## Notes

- The web feed page (`views/feed.php`) hides the site header and renders a
  phone-frame column (max-width 470px) centered on desktop.
- The current release APK is debug-signed; a keystore is needed for Play Store.
