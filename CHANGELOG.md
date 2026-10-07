# Changelog

All notable changes to the MeetAgain core are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Upgrade notes

- Back up before upgrading. This release adds 94 database migrations that create, rename and drop tables - run
  `php bin/console doctrine:migrations:migrate -n` after deploying. The migration namespace changed from
  `DoctrineMigrations` to `AppMigrations`; the migrations handle the switch themselves.
- New required environment variable `APP_SECRET_BOX_KEY`, used to encrypt stored secrets such as plugin API keys. It
  must be a base64-encoded 32-byte key: `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`. The web installer
  generates one for new installs; existing installs must add it by hand. Keep it stable - changing it makes stored
  secrets unreadable.
- New optional environment variables: `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY` and `PUSH_VAPID_SUBJECT` turn
  on web push (generate a pair with `bin/console app:push:vapid-keys`, leave empty to keep push off); `METRICS_DSN`
  sends runtime metrics over UDP and is off when unset.
- New required PHP extensions: `sodium`, `intl` and `zip`. The Docker image now runs PHP 8.5; `composer.json` still
  allows PHP 8.4 or newer.
- Symfony 8.0 to 8.1 - run `composer install` after pulling.
- Only `app:cron` needs scheduling now. The separate commands for RSVP notifications, cleanup, recurring-event
  extension, email template seeding and translation import were removed and their work moved into `app:cron`. Remove
  any crontab or systemd entries that call them and keep `app:cron` running every minute.
- Interface translations moved into the code as YAML files in `translations/`. The database translation table, the
  in-app translation editor and the translation import were removed, so wording customised in the database is
  dropped by the migration. The locale code `cn` was renamed to `zh`; the migration rewrites existing data.
- Each account now stores one base role (User or Admin) instead of a free list of role strings, and a role hierarchy
  adds the Organizer and Steward levels between them. Migrations convert existing accounts - check the member list
  after upgrading.
- The SendGrid mailer bridge is no longer installed; the core ships plain SMTP and `symfony/sweego-mailer`. If your
  `MAILER_DSN` uses `sendgrid://`, switch to SMTP or `composer require` the bridge you need. Local development uses
  Mailpit instead of MailHog (`smtp://mailpit:1025`).
- The stand-alone menu editor was removed. Navbar and footer links now come from CMS pages, which carry their own
  menu placement and per-language link names - recreate custom menu entries as CMS page links.
- Plugins can declare their own Composer dependencies, merged through `wikimedia/composer-merge-plugin`. Run
  `composer install` again after enabling a plugin that ships a `composer.json`.

### Added

- New bundled plugins: Books (keyless ISBN lookup via Open Library), Films (TMDB/OMDb lookup), Board Games (member
  shelves and bring pledges), Photos (member uploads with EXIF details and per-photo comments) and Wishlist (a member
  backlog that ranks candidates for votes). Dishes, Glossary and Karaoke were rebuilt.
- A shared item system for the catalogue plugins: one list and gallery view with filters, hierarchical categories and
  tags, links between items and events, and a uniform look.
- A vocabulary trainer for the Glossary, and synced lyrics with a lyrics lookup for Karaoke.
- Circulation of physical items: members donate a copy, queue for it and hand it over, with a dashboard of who holds
  what.
- Ballots: a steward opens a vote over candidates, members approve the ones they would accept, and the winner is
  applied at the deadline. Events can settle their venue by vote.
- A trust and vouching system that derives member reputation from vouches within a context.
- Member proposals: suggest new entries or corrections to existing content, both through a review-and-apply flow,
  with a `/contribute` hub listing open work.
- Reusable comments on events, items and photos.
- Reporting of items and images by anyone, guests included, with an admin review queue.
- Town Hall: a community hub with forum topics, a gallery and statistics tiles that plugins can extend.
- Event series on a new recurrence engine with presets and custom RFC 5545 rules; organizers can realign or reschedule
  a series.
- For events: iCal download and a calendar feed, a share button, RSVPs with guests, a manual external RSVP count,
  change notifications to attendees, and reminder and weekly digest emails.
- Web push notifications, and per-user notification settings with delayed message notifications.
- Blocking other members, and editing direct messages within a time window.
- Archive export and import: `app:export`, `app:import`, `app:import:inspect` and a two-step admin import move members,
  events, venues, CMS pages, items, tags and images between installs. The format is versioned (currently 2.0) and
  older 1.x archives still import.
- A larger CMS editor: a WYSIWYG text editor, new blocks (gallery, trio cards, facts row, event teaser, text with map),
  reordering and image alignment, per-language titles, menu link names and meta descriptions, a "locked" flag and
  reserved slugs.
- Announcements that link to CMS pages and go out by email in every language.
- Email management: per-language template editing with previews in a shared layout, a send log with delivery status, an
  email blocklist, and CMS-managed footer links in emails.
- Support requests with secret-URL reply threads, answered by email or direct message.
- Theme and branding settings: site logo upload, a site-wide Open Graph image, theme colours and footer column titles.
- French and Spanish interface translations, and new content languages (Luxembourgish, Romansh, Faroese, Frisian).
  Language tiles on the front page can be edited.
- SEO tools: canonical URLs, a sitemap with hreflang alternates, IndexNow submission, a dynamic `robots.txt`,
  `/.well-known/*` and `llms.txt`, per-page meta descriptions and meta robots control.
- Image tools: alt-text editing with reminders for every active language, image attribution, filtering images by
  issue, and a lazy thumbnail pipeline that stores shared images once.
- Admin logs for cron runs, activity, 404s (with suspicious-URL flagging), system log files, and a Redis cache browser.
- An admin dashboard with statistics tiles and scope filters, member approval and optional automatic registration.
- Plugin extension points: filter and provider chains for events, members, CMS pages, images, languages, the sitemap
  and navigation; plugins can also add cron tasks, settings at global and per-scope level, footer and menu links,
  event tiles and global scripts.
- Optional runtime metrics (request timings, cron durations, gauges) for VictoriaMetrics and Grafana.

### Changed

- A redesigned admin area with consistent list, tab and filter layouts; settings are grouped under System and access
  is checked with Symfony security voters.
- Sessions are stored in Redis/Valkey, logged-in users stay signed in longer, and the cookie consent banner was
  reworked.
- Faster pages: rendered CMS pages, configuration and app state are cached with tag-based invalidation, and N+1
  queries on busy pages were removed.
- Front-end assets are built with Symfony AssetMapper and Sass, served from cache-busting URLs with minified
  JavaScript.
- Email delivery runs through a queue with status tracking, send deadlines and recipient guard rules.
- A bundled Chinese web font (LXGW WenKai) and a better mobile layout.
- The web installer generates all required secrets, checks for the `sodium` extension and offers Mailpit for local
  mail.

### Fixed

- Recurring events: the published status syncs between series members, and untitled series show their dates.
- Forms: image re-upload, gallery views, form saving and event end times in the side panel.
- Line breaks in event descriptions, the latitude/longitude order on maps, and reverse image lookup per locale.
- Past cancelled events no longer send notifications, and the notification toggle no longer fails with a 500.
- False 404 entries and duplicate content in the sitemap.
- 404s no longer flood the error log, and unreadable log files are skipped.
- The web installer no longer offers SendGrid, Mailgun and Amazon SES, whose mailer bridges are not installed and
  produced a mail setup that could not send. It offers Mailpit, SMTP, Sweego and no mail; the other services work
  through their SMTP endpoints.
- The send log no longer mistakes an SMTP username for a Sweego API key.

### Security

- Fixed XSS in activity messages, event descriptions, CMS blocks and user-written messages; user content is sanitised
  with `symfony/html-sanitizer` and a dedicated sanitiser for chat and support text.
- Fixed an open redirect in vouching, and vouching for non-members.
- Closed accounts can no longer reset their password, organizers can no longer moderate higher-ranked members, and
  images belonging to others can no longer be selected.
- CSRF protection on every state-changing action, including logout and member approval; GET routes no longer change
  data.
- Captcha and rate limits on registration, password reset and contact forms; limits count submissions, not page views.
- Toggleable bot defences (failed-login blocking, URL-probing detection, session and IP blocks) with an admin log of
  what each one did.
- Stronger defaults: expiring registration codes, strict email validation, tuned password hashing, and security
  headers in the shipped Caddy configuration.
