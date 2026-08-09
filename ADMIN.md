# Church Media — Admin Panel Guide

The admin panel lives at **`/admin`** on the website and is the single place the church staff manage the entire site. You must be signed in; everyone who can log in also gets a **"My Account"** page to update their own details.

## Roles

There are three roles. Every page checks its own permission, so what you see is what you're allowed to use.

| Role | Can do |
|------|--------|
| **admin** | Everything, including Users, Security, and Settings |
| **editor** | Content only: media, events, sermons, team, prayer, newsletter, forms, pages |
| **media_team** | Media & Reels only (upload and manage posts) |

| Page | admin | editor | media_team |
|------|:-----:|:------:|:----------:|
| Dashboard | ✓ | ✓ | ✓ |
| Media & Reels | ✓ | ✓ | ✓ |
| Events | ✓ | ✓ | – |
| Sermons | ✓ | ✓ | – |
| Team | ✓ | ✓ | – |
| Prayer Wall | ✓ | ✓ | – |
| Newsletter | ✓ | ✓ | – |
| Forms | ✓ | ✓ | – |
| Pages | ✓ | ✓ | – |
| Security | ✓ | – | – |
| Settings | ✓ | – | – |
| Users | ✓ | – | – |
| My Account | ✓ | ✓ | ✓ |

---

## Dashboard (`/admin`)

An overview landing page after login:

- **Stat cards** — media posts, upcoming events, sermons, new prayer requests, active newsletter subscribers, and blocked IPs.
- **Recent media posts** — the latest uploads with type, likes, views, and posting time.
- **Recent security activity** — recent login attempts (success / failed / blocked) with username and IP.

---

## Media & Reels (`/admin/media`)

Manage the social-style media feed that appears on the website and in the mobile app.

- **Create posts**:
  - Upload **multiple photos and/or videos** in one go (up to 200MB per file). Images are auto-compressed to WebP; videos are kept in their original file so they play immediately.
  - Or paste a **YouTube link** (regular videos and Shorts both work); an optional custom cover image can be set.
  - The post type is chosen automatically: single image, carousel (multiple photos), or vertical reel (contains video).
  - Add a caption, tick categories, and choose **publish immediately** or save as a draft.
  - Instant upload with a progress bar; uploads are never blocked on video conversion.
- **Video conversion** — uploaded videos are automatically cropped to the vertical 9:16 "reel" format in the background when FFmpeg is available. A **Process** button lets you retry any that are still pending, and a cron job (see Settings) is the safety net that guarantees every video is converted.
- **List** — see cover thumbnails, caption, type, conversion status (converted / converting / original), and engagement (likes, views, saves).
- **Edit** — change the caption, categories, and publish status.
- **Publish toggle** — flip any post between published and draft with one click.
- **Delete** — removes the post and its files permanently (with confirmation).
- **Categories** — create and manage categories to organize the feed.

---

## Events (`/admin/events`)

List upcoming events for the site's Events section.

- **Create / edit events**: title, description, start and optional end date-time, location, cover image, an optional **RSVP link** (enable + URL), and publish/draft status.
- **List** — start time, location, RSVP on/off, status.
- **Delete** — remove an event permanently (with confirmation).
- Slugs are auto-generated and kept unique.

---

## Sermons (`/admin/sermons`)

The sermon library — audio + optional video per message.

- **Add / edit sermons**: title, speaker, series, scripture reference, description, optional video embed URL (YouTube/Vimeo), cover image, and an **audio file** (mp3, m4a, or wav).
- Set the published date and publish/draft status.
- **List** — title, speaker, series, published date, status.
- **Delete** — remove a sermon permanently.

---

## Team (`/admin/team`)

Profiles shown on the About page.

- **Add / edit team members**: name, role/title (e.g. Lead Pastor), bio, photo, sort order (controls display order), and "Visible on About page".
- **List** — photo, name, role, visibility status.
- **Delete** — remove a member permanently.

---

## Prayer Wall (`/admin/prayer`)

Incoming prayer requests submitted from the public "Prayer" page (anonymously or with contact info).

- **Public / private toggle** — mark a request as public to feature it on the site's prayer wall.
- **Status tracking** — move requests through **new → prayed → archived** with a dropdown.
- **Delete** — remove a request.

---

## Newsletter (`/admin/newsletter`)

People who subscribed on the website.

- **List subscribers** with status (active / unsubscribed) and when they joined.
- **Export CSV** — download all active subscribers.
- **Remove** — delete an individual subscriber.

---

## Forms (`/admin/forms`)

A full drag-and-drop form builder, like Google Forms, for registrations, event sign-ups, feedback, etc.

- **Build a form**:
  - Title, optional public-link slug (auto-generated from the title), description, and submit-button label.
  - Optional **closing date/time** — after this, visitors see a "form closed" page instead.
  - **Field types**: text, textarea, email, phone, number, date, url, select, radio, checkbox, and **image upload**.
  - Per field: label, placeholder, options (one per line for select/radio/checkbox), and required.
  - **Reorder fields** by dragging them.
- **Share** — each form has its own public link (`/forms/your-slug`); an "Open form" button jumps to it.
- **Responses**:
  - View all submissions for a form, with a response count.
  - **Export CSV** — submissions exported with one column per field (image fields export the file URLs).
  - Delete individual submissions, or **clear all** at once.
- **Delete a form** — removes the form and its submissions permanently.

---

## Pages (`/admin/pages`)

A visual CMS for building standalone pages (e.g. About, Our Story) without touching code.

- **Create / edit pages**: title, slug, eyebrow, meta description, nav label, include-in-nav, publish status, and sort order.
- **Section-based content builder** with drag-and-drop blocks:
  - **Hero** — big banner block
  - **Text** — heading + body with alignment
  - **Columns** — multi-column text blocks
  - **Image** — uploaded image
  - **Quote** — quote + source
  - **CTA** — call-to-action title, subtitle, button label and link
- **Upload images** directly into page sections (up to 8MB, auto-compressed).
- **Toggle** publish and include-in-nav from the list.
- **Delete** — removes the page and cleans up any images it used.

---

## Security (`/admin/security`) — admin only

Access controls and monitoring.

- **IP access rules** — blacklist or whitelist specific IP addresses, with an optional reason. Manual blocks never expire until removed here. A 👑 marks IPs auto-whitelisted after repeated successful logins.
- **Country rules** — whitelist or blacklist entire countries by 2-letter code (enforced when the server can detect the country, e.g. via a CDN header or geoip).
- **Recent login activity** — the last 50 login events (success / failed / blocked) with the username attempted and IP.

---

## Settings (`/admin/settings`) — admin only

Global site settings, in four groups:

1. **Branding** — site / church name, tagline, logo, and favicon.
2. **Homepage Hero** — the big banner on the homepage:
   - Background image (auto-compressed to WebP, with a "remove" option to go back to the animated gradient)
   - Eyebrow text, headline, and scripture line
   - Two call-to-action buttons, each with its own label and link
3. **Contact & Service Times** —
   - Contact email, phone, and address
   - **Service Times** — up to 4 rows of label + time. These show everywhere service times are displayed (homepage, footer, contact page, live page) **and** in the mobile app. Saving here overrides the default schedule.
4. **Social & Live** —
   - Facebook, Instagram, YouTube, TikTok URLs
   - **Livestream YouTube link** + a "We are live right now" toggle (shows a LIVE badge site-wide)
   - **Giving / donation URL**
5. **Footer & SEO** — footer about text and the site meta description.

The page also includes the **Video Conversion (Cron Job)** instructions with the exact cPanel cron command to guarantee background video processing.

---

## Users (`/admin/users`) — admin only

Team accounts and their access.

- **Add team accounts** with a role: **Media Team** (upload & manage posts), **Editor** (posts, events, sermons), or **Admin** (full access).
- **List** — name, username, role, last login (time + IP), and status.
- **Suspend / activate** any user (you can't suspend your own account).
- **Delete** — remove a user (you can't delete your own account).
- Passwords are hashed (Argon2id) and must be at least 10 characters.

---

## My Account (`/admin/account`)

Every signed-in user can:

- Update their own **name** and **email**.
- **Change their password** (must verify the current password; minimum 10 characters).

---

## Login & Logout

- **`/admin/login`** — sign-in page with built-in protection: failed attempts are logged, IPs can be blocked by rule, and the Security Center tracks everything.
- **`/admin/logout`** — signs you out of the panel.

## General notes

- **CSRF protection** is applied to every form and POST action automatically.
- Uploaded images are **auto-compressed to WebP** for fast page loads; video files stay in their original format so they play immediately while the vertical crop happens in the background.
- The panel is built to be used on desktop; the public website and mobile app are what visitors see.
