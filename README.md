# QuestScene

### Step. **Out.** Stand Out.

**QuestScene shows you what's happening around you — runs, treks, football, workshops, meet-ups — lets you join in one tap, and helps you start your own.**

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![No build step](https://img.shields.io/badge/build%20step-none-F97316)
![Mobile first](https://img.shields.io/badge/mobile-first-3FA34D)

> **This repository is the source code.** It is not the website.
> QuestScene is a PHP + MySQL application, so it needs a host that runs PHP
> (Hostinger, or any shared host with PHP 8 and MySQL).
> **GitHub Pages cannot run it** — Pages only serves static files, which is why
> visiting the Pages URL shows this README instead of the app.

---

It is not a dating app, not a ticketing site, and not another feed to scroll. The object is the **activity**, never the person. You open QuestScene to see what you can do, where you can go, and what is happening around you — then you go and do it.

Built as a plain **PHP 8 + MySQL** app with **no build step** — upload the folder and it runs. That is deliberate: it works on every Hostinger plan including the cheapest shared one, there is no `node_modules`, nothing to compile, and moving it between machines is a drag-and-drop.

## Features

| | |
|---|---|
| 🗺️ **Discover** | Search and filter every plan by city, category, today / weekend / free |
| ✋ **One-tap join** | Live spot counts, automatic waitlist, host notifications |
| 💬 **Plan & community chat** | Gated — you get a room by joining, never by browsing profiles |
| 👥 **Communities** | Member lists, plan calendars, announcements, organizer dashboard |
| ⚡ **Create a plan** | Under a minute, then one shareable link that works without an account |
| 🗣️ **Forum** | Activity-oriented posts that convert into plans, not endless scrolling |
| 🎮 **Play** | 2048, Sudoku, Memory Match, Reaction Test, Tic-Tac-Toe + a Daily Challenge |
| 🛡️ **Trust & safety** | Verification tiers, reporting queue, blocking, admin moderation |
| 🎛️ **Admin panel** | Publish events for any organizer, verify communities, manage users, work reports |
| 🌗 **Dark / light** | Theme toggle, remembered, applied before first paint |
| ✨ **3D & motion** | Tilting cards, a three.js hero that refuses to load on low-end devices |

## Screenshots

> Drop images into `docs/` and reference them here — for example:
> `![Home](docs/home.png)` · `![Plan page](docs/plan.png)` · `![Play](docs/play.png)`

## Tech

- **PHP 8.1+** — no framework, PDO with prepared statements throughout
- **MySQL 5.7+ / MariaDB 10.3+** — 17 tables, foreign keys, InnoDB
- **Vanilla JS** — no jQuery, no bundler; `three.js` is the only dependency and is self-hosted and lazy-loaded
- **Sessions + CSRF tokens** on every write, `password_hash()` for credentials, per-session rate limiting

## Quick start

```bash
git clone git@github.com:Sumitkumar-curio/questscene.git
cd questscene/public_html
php -S localhost:8000
```

Import `database/schema.sql`, copy your DB settings into `includes/config.php` (or a gitignored `includes/config.local.php`), then open `/install.php` and tick "seed demo content".

Full deployment instructions are below.

---

## What's in the box

```
QuestScene/
├── database/
│   └── schema.sql              ← import this first
├── public_html/                ← upload the CONTENTS of this into Hostinger's public_html
│   ├── index.php               Home
│   ├── discover.php            Search + filters
│   ├── plan.php                Plan page (the shareable one)
│   ├── create.php              Create a plan
│   ├── plan-created.php        "Share it now" screen
│   ├── plan-edit.php           Host manages their plan
│   ├── communities.php         Directory
│   ├── community.php           Community page
│   ├── community-new.php       Start a community
│   ├── forum.php / post.php    Activity-oriented forum
│   ├── chat.php                Plan + community chat
│   ├── dashboard.php           "My QuestScene"
│   ├── profile.php             Public profile
│   ├── settings.php            Account settings
│   ├── organizer.php           Community owner dashboard
│   ├── admin/                  Admin panel (overview, events, plans, communities, users, reports)
│   ├── api/                    JSON endpoints (join, save, interest, chat)
│   ├── includes/               config, db, auth, helpers, queries, components, layout
│   ├── assets/                 css / js / img
│   ├── uploads/                user images (covers, avatars)
│   └── install.php             one-time setup — DELETE AFTER RUNNING
└── README.md
```

**Requirements:** PHP 8.1+ with the `pdo_mysql`, `mbstring` and `gd` extensions, and MySQL 5.7+ / MariaDB 10.3+. Hostinger's defaults already satisfy all of this — you don't need to change the PHP configuration.

---

## Deploy to Hostinger — 6 steps

### 1. Create the database
hPanel → **Databases → MySQL Databases**. Create a database and a user, and give the user all privileges. Write down the three values it shows you — Hostinger prefixes them, so they look like `u123456789_questscene`.

### 2. Import the schema
hPanel → **phpMyAdmin** → select your database → **Import** → choose `database/schema.sql` → Go.

### 3. Edit the config
Open `public_html/includes/config.php` and set:

```php
define('DB_NAME', 'u123456789_questscene');
define('DB_USER', 'u123456789_qsuser');
define('DB_PASS', 'your-database-password');

define('SITE_URL', 'https://yourdomain.com');   // no trailing slash
define('INSTALL_KEY', 'pick-something-random');
define('DEV_MODE', true);                       // flip to false after step 6
```

### 4. Upload
hPanel → **File Manager** (or FTP). Upload everything **inside** `public_html/` into Hostinger's `public_html/`. Do not upload the `database/` folder or `README.md` — they don't belong on a web server.

Make sure `uploads/`, `uploads/covers/` and `uploads/avatars/` exist and are writable (permissions `755`).

### 5. Run the installer
Visit `https://yourdomain.com/install.php`, enter your install key and admin details, and leave **Seed demo content** ticked for the first run — it creates 6 communities, 12 plans and a few forum posts so the homepage isn't a wall of "no events found".

### 6. Lock it down
- **Delete `install.php` from the server.**
- Set `DEV_MODE` to `false` in `config.php`.
- Turn on the free SSL certificate (hPanel → SSL), then uncomment the HTTPS redirect block in `.htaccess`.

---

## Running it locally

Any PHP stack works — XAMPP, Laragon, or the built-in server:

```bash
cd public_html
php -S localhost:8000
```

Set `SITE_URL` to `http://localhost:8000` and point `DB_*` at your local MySQL.

---

## Moving hosts later

Two things and only two things:

1. **Files** — copy `public_html/` wherever it needs to go.
2. **Database** — phpMyAdmin → Export → import on the other side.

Then update `SITE_URL` and the `DB_*` values in `includes/config.php`. There is no build artifact, no `node_modules`, no environment daemon, nothing to recompile.

---

## Roles

| Role | Can do |
|---|---|
| **user** | Join plans, create plans, join communities, chat, post in the forum |
| **organizer** | Everything above, plus publish **Events** and see the organizer dashboard |
| **admin** | Everything, plus the full admin panel: post events on behalf of any organizer, hide/verify plans, verify communities, promote users, work the report queue |

Starting a community promotes you to `organizer` automatically. Admins promote people from **Admin → Users**.

---

## Branding, theme and social

**Colours** come from the logo: navy `#14243F` for surfaces, orange `#F97316` as the only accent, pine green `#3FA34D` for success states. Everything is a CSS custom property at the top of `assets/css/app.css` — change the tokens, the whole site follows.

**Dark and light mode.** Dark is the default (it suits the navy brand); the sun/moon button in the header toggles it and the choice is remembered in `localStorage`. An inline script in `includes/header.php` applies the saved theme *before first paint* — leave it where it is, or light-mode users get a dark flash on every page load.

**The logo** renders as inline SVG from `logo_mark()` / `logo_lockup()` in `includes/helpers.php`. It's inline rather than an `<img>` so the half of the mark that is navy on your white artwork inherits `currentColor` and turns light on the dark UI — an `<img>` can't do that and would disappear.

To use your original artwork instead, drop it at `public_html/assets/img/logo.png`; `logo_lockup()` picks it up automatically and uses it in place of the SVG. Keep it around 200px wide with a transparent background.

**Link previews** use `assets/img/og-default.png`. It must stay a **PNG or JPG** — WhatsApp, Telegram and Facebook do not render SVG `og:image`, and a broken preview badly hurts a share-driven product. Regenerate it with any image editor at 1200×630 if you rebrand.

**Social handles** live in `includes/config.php`:

```php
define('CONTACT_EMAIL',    'questscene@gmail.com');
define('SOCIAL_HANDLE',    '@questscene');
define('SOCIAL_INSTAGRAM', 'https://instagram.com/questscene');
define('SOCIAL_TELEGRAM',  'https://t.me/questscene');
```

They feed the footer, About, Contact and the homepage follow band. Change them in one place.

---

## Motion and 3D

**Plan cards are layered in 3D.** `.pcard` holds the perspective; `.pcard-3d` rotates inside it and the children sit at different `translateZ` depths, so the emoji floats above the cover on tilt. On a fine pointer the card tilts toward the cursor (capped at 7°, one transform write per animation frame). On touch there's a press-in scale instead — a tilt fights the scroll.

**Every category has its own cover gradient** (`--cat-grad` / `--cat-glow`, set by `.pcard[data-cat="…"]`). A wall of cards reads as variety rather than twelve grey boxes. The chrome stays orange; only the artwork area is tinted.

**Cards show who's going.** `PLAN_SELECT` pulls the first three attendees via `GROUP_CONCAT` + `SUBSTRING_INDEX`, so the face pile costs no extra query per card.

**The three.js hero is a garnish and is treated as one.** `assets/js/hero3d.js` refuses to load at all when any of these is true: `prefers-reduced-motion`, Data Saver on, 2G, `deviceMemory < 4`, or no WebGL. When it does run it waits for `requestIdleCallback`, caps `devicePixelRatio` at 1.5 on phones, drops to 14 objects, and stops rendering entirely when the hero scrolls out of view or the tab is hidden. The CSS radial-gradient hero is the baseline everyone sees — the canvas fades in over it only after a successful render.

three.js is **self-hosted** at `assets/js/vendor/three.module.min.js` (655 KB raw, ~150 KB gzipped). No CDN, so nothing external can break your site. It is loaded with a dynamic `import()`, so devices that skip it never download a byte.

If you want the 3D hero gone entirely, delete the `<div id="hero3d">` and the `hero3d.js` script tag from `index.php`. Nothing else depends on it.

**Reduced motion is honoured throughout** — tilt, reveal, count-up, confetti and the 3D scene all check it.

---

## Play

A deliberately small games section — a reason to open QuestScene on a dull Tuesday, with a route back to a real plan at the bottom of every screen. It is not meant to grow into a gaming site.

| Game | Mode |
|---|---|
| 🧩 2048 | Solo, score-based. Keyboard, WASD or swipe. |
| 🧠 Sudoku | Solo, four difficulties. Puzzles generated at runtime with a guaranteed unique solution. |
| 🃏 Memory Match | Solo, 8 pairs. Score rewards fewer moves and less time. |
| 🔢 Reaction Test | Solo, 5 rounds averaged. Lower is better. |
| ❌ Tic-Tac-Toe | vs computer (Easy / Unbeatable minimax) or 2 players on one device. |
| 🎯 Daily Challenge | One game, one seed, the same for everyone, rotating at midnight. |

**Adding a game** means two files and one array entry: add a row to `games()` in `includes/games.php`, then create `assets/js/games/<slug>.js` that calls `QSGame.mount()`. The shell, leaderboard, scoring and Daily Challenge rotation all come for free.

**The Daily Challenge needs no cron.** `daily_challenge()` derives the game and seed from `crc32` of today's date, so every visitor computes the same answer independently and it rolls over at midnight on its own.

**Scores are client-reported and therefore forgeable.** There is no way around that short of running each game on the server, which is not worth it for a side feature. `api/score.php` caps each game at a plausible ceiling, rejects implausibly fast runs and rate-limits submissions — enough to stop casual nonsense, not a determined cheat. Treat the boards as fun, not as a competitive record.

**Not built:** Chess and Ludo. Chess needs full legal move generation (castling, en passant, promotion, check and stalemate detection) plus a search engine before it is any fun — that is a project of its own, and a weak chess engine is worse than no chess. Ludo adds multiplayer game-state sync that does not fit shared hosting's lack of WebSockets.

---

## Design decisions worth knowing

**Chat is gated, not open.** You get a room because you joined a plan or a community — there are no open DMs. That one rule removes most of the spam and all of the "is this a dating app?" feeling. Direct chat should only arrive later, behind real interaction signals.

**Chat polls, it doesn't socket.** Shared hosting has no WebSocket support, so `assets/js/app.js` polls `api/chat_fetch.php` every 4 seconds and backs off to 20 seconds when the tab is hidden. That is genuinely fine at launch volumes. If you later move to a VPS, swap that one function for a WebSocket client — nothing else changes.

**The activity is the object, never the person.** No matching, no swiping, no "find your perfect match". Discover, Join, Participate, Follow, Create.

**Badges mean what they say.** `verify_badge()` in `includes/helpers.php` only returns a badge for a check an admin actually performed. Nothing self-awards.

**Counters are denormalised.** `plans.going_count` and `communities.member_count` are maintained by `refresh_plan_count()` / `refresh_community_count()` in `includes/queries.php`. If a number ever looks wrong, Admin → Communities → **Recount** fixes it.

---

## Not built yet (and why)

| Feature | Why it waits |
|---|---|
| **Map view** | Needs lat/lng on every plan plus a maps key. The `plans` table has `map_url` today; add `lat`/`lng` columns when you're ready. |
| **Phone OTP** | Needs a paid SMS gateway (MSG91 / Twilio). The `phone_verified` column and admin toggle already exist, so wiring a gateway is a small change. |
| **Voice chat** | WebRTC needs a signalling server that stays connected — that means a VPS or a service like Agora/LiveKit. Shared hosting cannot do it. Plan for it after the text loop is proven. |
| **Google / phone login** | Adds an OAuth round-trip and a dependency. Email + password gets you launched; add Google Sign-In once you have real signup volume to compare against. |
| **Payments** | `plans.price` is recorded and displayed, but money is settled between host and attendee off-platform. `safety.php` says so explicitly — anyone asking to pay *through* QuestScene is a scam. |
| **Learn / Build** | Phase III and IV. The category system already has room; don't build them before the core loop turns. |

---

## Before you launch publicly

The plan doc has this right and it's worth repeating: **do not launch with an empty city.**

1. Seed 20–30 real communities (Admin → Communities, or invite the owners to create them).
2. Publish 100+ real plans and events (Admin → Post event).
3. Bring your existing Telegram members over — they're the ones who will test whether the create→share→join loop actually turns.

A first-time visitor who sees "No plans found" does not come back.
