<?php
declare(strict_types=1);

Auth::requireLogin();

$pageTitle = 'Admin Guide';
$activeNav = 'guide';
require __DIR__ . '/partials/layout-open.php';
?>
<style>
  .guide-toc{display:flex; flex-wrap:wrap; gap:8px; margin:14px 0 4px;}
  .guide-toc a{font-size:12.5px; color:var(--ink-dim); border:1px solid var(--border); border-radius:999px; padding:6px 12px; text-decoration:none; transition:.2s;}
  .guide-toc a:hover{color:var(--gold-soft); border-color:var(--gold);}
  .guide h2{margin:0 0 10px;}
  .guide h3{margin:22px 0 6px; font-size:16px;}
  .guide p, .guide li{color:var(--ink-dim); font-size:14px; line-height:1.8;}
  .guide code{background:var(--panel-2); border:1px solid var(--border); border-radius:6px; padding:1px 6px; font-size:12.5px; color:var(--gold-soft);}
  .guide .pill{display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:20px; vertical-align:middle; margin-left:6px;}
  .pill.super{background:var(--gold-soft); color:#3a2a08;}
  .pill.role{background:#6fb3ff22; color:var(--info);}
  .guide table{width:100%; border-collapse:collapse; margin:10px 0;}
  .guide table th,.guide table td{text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); font-size:13px;}
</style>

<div class="card" style="margin-bottom:18px;">
  <h2 style="margin:0 0 6px;">📖 Admin Guide</h2>
  <p class="sub">Everything you can do in the admin panel — how to manage your church's content, your account, and the site. Use the links below to jump to a section.</p>
  <div class="guide-toc">
    <a href="#roles">Roles &amp; Permissions</a>
    <a href="#dashboard">Dashboard</a>
    <a href="#media">Media &amp; Reels</a>
    <a href="#comments">Comments</a>
    <a href="#events">Events</a>
    <a href="#sermons">Sermons</a>
    <a href="#team">Team</a>
    <a href="#prayer">Prayer Wall</a>
    <a href="#newsletter">Newsletter</a>
    <a href="#forms">Forms</a>
    <a href="#notifications">Notifications</a>
    <a href="#pages">Pages</a>
    <a href="#units">Units</a>
    <a href="#security">Security</a>
    <a href="#settings">Settings</a>
    <a href="#users">Users</a>
    <a href="#firebase">Push (Firebase)</a>
    <a href="#account">My Account</a>
  </div>
</div>

<div class="guide">
  <div class="card" style="margin-bottom:18px;">
    <h2 id="roles">Roles &amp; Permissions</h2>
    <p>Every account has a role that controls what they can see and do. You'll usually only see <strong>your own church's</strong> content — each parish is fully isolated from every other parish.</p>
    <table>
      <tr><th>Role</th><th>What they can do</th></tr>
      <tr><td><strong>Admin</strong></td><td>Full content management for their church (media, events, sermons, team, prayer, newsletter, forms), manage their church's users, and use Notifications.</td></tr>
      <tr><td><strong>Editor</strong></td><td>Create and edit content (media, events, sermons, team, forms, prayer) but cannot manage users or site settings.</td></tr>
      <tr><td><strong>Media Team</strong></td><td>Post and manage media &amp; reels only.</td></tr>
      <tr><td><strong>Super Admin</strong></td><td>Everything above across the whole organisation — plus Units, Site Settings, Pages, Users, and Firebase push. Marked with a <span class="pill super">SUPER</span> badge in this guide.</td></tr>
    </table>
    <p><strong>Isolation:</strong> a parish admin only sees their own parish's posts, events, sermons, team, forms, prayer requests, and newsletter subscribers — even if they log in at the organisation level. Unassigned records (e.g. visitor prayer requests) are only visible to the super admin, who can assign them to the right church.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="dashboard">Dashboard (<code>/admin</code>)</h2>
    <p>Your landing page. It shows your church's key numbers (media posts, upcoming events, sermons, new prayer requests, subscribers) and your latest posts. It also shows <strong>📍 My Unit</strong> so you always know which church you're managing, plus any <strong>🔔 Notifications</strong> sent to your church.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="media">Media &amp; Reels (<code>/admin/media</code>)</h2>
    <p>The heart of the app — full-screen vertical reels. You can:</p>
    <ul>
      <li><strong>Create a post</strong> from uploaded photos/videos or a YouTube link (Shorts supported). Videos play instantly and are automatically cropped to 9:16 in the background.</li>
      <li><strong>Add categories</strong> (Worship, Sermon Clip, etc.) so visitors can filter the feed.</li>
      <li><strong>Edit any item on a post</strong>: replace an image or video, set a new video cover, reorder items (↑/↓), delete a single item, or add new media to an existing post. Changes appear in the feed immediately.</li>
      <li><strong>Reprocess videos</strong> if a conversion is ever stuck (the cron note at the bottom of Settings covers the safety net).</li>
    </ul>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="comments">Comments (Instagram-style)</h2>
    <p>Reels support the full comment experience on both the website and the app:</p>
    <ul>
      <li><strong>Threaded replies</strong> — tap Reply on any comment to respond.</li>
      <li><strong>Emoji picker</strong> in the composer.</li>
      <li><strong>Image attachments</strong> that are automatically compressed to the smallest webp on upload.</li>
      <li><strong>Comment likes</strong> (♥) with per-visitor deduplication.</li>
      <li><strong>Live updates</strong> — comments refresh in real time while the sheet is open.</li>
    </ul>
    <p>Comments are anonymous (optional name), so no moderation is required from your side — but spam protection is built in via rate limiting.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="events">Events (<code>/admin/events</code>)</h2>
    <p>Add upcoming events with date/time, location, description, cover image, and optional RSVP link. Publish to show them on the website and app. Only your church's events appear here.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="sermons">Sermons (<code>/admin/sermons</code>)</h2>
    <p>Upload sermon audio, attach a YouTube video, add speaker/series/scripture reference, and publish. Sermons appear on the site and app's Sermons tab.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="team">Team (<code>/admin/team</code>)</h2>
    <p>Manage your church's leadership and ministry team — name, role, photo, and bio. Published members show on the site's About/Team section.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="prayer">Prayer Wall (<code>/admin/prayer</code>)</h2>
    <p>View prayer requests submitted from the public Prayer page. Mark them <em>new / prayed / archived</em>, toggle public visibility to feature them on the site, and delete spam. Requests with no church are marked <strong>Unassigned</strong> — the super admin can assign them to the right parish.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="newsletter">Newsletter (<code>/admin/newsletter</code>)</h2>
    <p>View email subscribers (from the site footer signup), export them as CSV, and remove subscribers. Subscribers are scoped to your church; unassigned ones can be assigned by the super admin.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="forms">Forms (<code>/admin/forms</code>)</h2>
    <p>Build custom public forms with a drag-and-drop field builder (text, email, phone, number, date, URL, select, radio, checkbox, image upload). Share the generated link, view responses in the panel, export to CSV, and close/delete forms. Each form belongs to your church.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="notifications">Notifications (<code>/admin/notifications</code>)</h2>
    <p>Provinces can broadcast announcements to <strong>all churches, one church, or selected churches</strong>. Recipients see them on their admin dashboard and receive an email (via the SMTP configured in Settings). <span class="pill super">SUPER</span> admins can reach the whole organisation; other admins can only notify within their own unit.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="pages">Pages (<code>/admin/pages</code>) <span class="pill super">SUPER</span></h2>
    <p>Manage the site's content pages (About, Privacy Policy, and any new pages) with a visual section builder (hero, text, columns, image, quote, CTA). This is organisation-wide, so only the super admin can edit pages.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="units">Units (<code>/admin/units</code>) <span class="pill super">SUPER</span></h2>
    <p>Manage the <strong>Province → Zone → Area → Parish</strong> hierarchy. Create parishes/zones/areas/provinces, and each one gets its own public directory page and media roll-up. Super-admin only.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="security">Security (<code>/admin/security</code>) <span class="pill role">ADMIN</span></h2>
    <p>Review login attempts and security events, and block suspicious IPs. Helps you keep your church's admin area safe.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="settings">Settings (<code>/admin/settings</code>) <span class="pill super">SUPER</span></h2>
    <p>Site-wide configuration — name, tagline, hero content, contact details, social links, live stream link, giving URL, footer &amp; SEO, Bible source, and <strong>Email (SMTP)</strong> settings used for all outgoing mail (newsletters, notifications, security alerts). Super-admin only.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="users">Users (<code>/admin/users</code>) <span class="pill role">ADMIN</span></h2>
    <p>Create and manage accounts for your church's team. Set name, username, email, role (admin/editor/media team), password, and <strong>Home Unit</strong> (the church they manage). The very first super-admin account can't be deleted or edited by others.</p>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h2 id="firebase">Push Notifications (<code>/admin/firebase</code>) <span class="pill super">SUPER</span></h2>
    <p>Turn on real push notifications for the mobile app using Firebase. The Firebase page has a step-by-step wizard with a live status check — upload your service-account key, save your Project ID, and send a test push. Super-admin only.</p>
  </div>

  <div class="card">
    <h2 id="account">My Account (<code>/admin/account</code>)</h2>
    <p>Update your own name, email, and password, and see which church/unit you manage. If you ever need access to another church, ask the super admin to assign it in Users.</p>
    <p><strong>Tip:</strong> after any important change, use the <strong>↗ View Website</strong> link in the sidebar to preview your church's public site and app.</p>
  </div>
</div>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
