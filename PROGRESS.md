# EVEmu Portal — Progress / Прогресс

> **Portal overall: `███████████████████░` ~95%**
> Killboard & account front-end for a private EVEmu server.

> Totals are the mean of the modules listed below.
> Итог — среднее по перечисленным модулям.

---

## Overview / Обзор

| Module | % | Bar | Status | Module | % | Bar | Status |
|--------|---|-----|--------|--------|---|-----|--------|
| Authentication & Sessions | 98% | `████████████████████` | ✅ | Registration & Rules | 100% | `████████████████████` | ✅ |
| 2FA & E-mail Security | 95% | `███████████████████░` | ✅ | Killboard | 97% | `███████████████████░` | ✅ |
| Characters & Corporations | 95% | `███████████████████░` | ✅ | Systems & Sovereignty | 96% | `███████████████████░` | ✅ |
| Market | 90% | `██████████████████░░` | 🟡 | Haul (Couriers) | 95% | `███████████████████░` | ✅ |
| Stats & Activity | 95% | `███████████████████░` | ✅ | Players & Online | 92% | `██████████████████░░` | 🟡 |
| EVE-Mail | 98% | `████████████████████` | ✅ | Notifications | 95% | `███████████████████░` | ✅ |
| Petitions | 97% | `███████████████████░` | ✅ | Admin Panel | 95% | `███████████████████░` | ✅ |
| Admin Security & Monitoring | 92% | `██████████████████░░` | 🟡 | Search | 95% | `███████████████████░` | ✅ |
| Images & Rendering | 97% | `███████████████████░` | ✅ | Messenger Delivery | 90% | `██████████████████░░` | 🟡 |
| Config & Deployment | 98% | `████████████████████` | ✅ | Mobile / Responsive UI | 95% | `███████████████████░` | ✅ |
| Web Push | 85% | `█████████████████░░░` | ⏸ | | | | |

> ✅ done · 🟡 partial · ⏸ implemented but disabled · ❌ not implemented

---

## Details / Детали

### 1. Authentication & Sessions `████████████████████` 98%

| Feature | Status |
|---------|:------:|
| Login with CCP password hash (SHA1, hex compare) | ✅ |
| Character picker after login (account characters) | ✅ |
| Session lifetime cap (8h, cookie + `login_time` guard) | ✅ |
| Logout / session destroy | ✅ |
| Legacy accounts without e-mail get a bind step (`SetEmail`) | ✅ |

### 2. Registration & Rules `████████████████████` 100%

| Feature | Status |
|---------|:------:|
| Mandatory valid + unique e-mail (server-side) | ✅ |
| Mandatory server-rules agreement (checkbox, server-side enforced) | ✅ |
| `/rules` page + expandable rules box in the form + footer link | ✅ |
| Reserved/offensive names refused | ✅ |

### 3. 2FA & E-mail Security `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| E-mail codes — admins always, other users on new device/IP | ✅ |
| 6-digit code, 10 min TTL, 5 attempts, hash stored in session only | ✅ |
| Trusted-device cookie (180 days, token + IP store) | ✅ |
| Pure-PHP SMTP sender (`mailer.php`) — tls/ssl/none, AUTH LOGIN/PLAIN, UTF-8, RFC2047 | ✅ |
| Admin e-mail test page (`/admin/emailtest`) | ✅ |

### 4. Killboard `███████████████████░` 97%

| Feature | Status |
|---------|:------:|
| Home — most valuable ships/structures/sponsored cards with ISK value | ✅ |
| Kill feed (`/kills`) | ✅ |
| Kill detail — fit by slot, attacker/victim corps/alliances, related kills | ✅ |
| Killmail text + original blob view | ✅ |
| Top kills by damage (`/server/TopKills`) | ✅ |
| Top valuables by ISK (hull + fit valuation) | ✅ |
| Battles — system-scoped, corporation columns, per-class K/L matrix | ✅ |
| Inline SVG maps (system / constellation / region) | ✅ |

### 5. Characters & Corporations `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Character kill history | ✅ |
| Corporation kills + corporation stats | ✅ |
| Portraits and corporation logos (image server) | ✅ |
| Ship / group kill pages | ✅ |

### 6. Systems & Sovereignty `███████████████████░` 96%

| Feature | Status |
|---------|:------:|
| Per-system kill pages | ✅ |
| Active systems list | ✅ |
| Sovereignty change log (`/sov`) — faction/alliance owner flips | ✅ |
| System / constellation / region coordinate maps | ✅ |

### 7. Market `██████████████████░░` 90%

| Feature | Status |
|---------|:------:|
| Market stats and top traded items | ✅ |
| Buy/sell aggregates from the API | ✅ |
| ISK valuations depend on market data (fallback to base price) | 🟡 |

### 8. Haul (Couriers) `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Public courier contract list | ✅ |
| Filters by origin/destination system | ✅ |
| Server-side pagination (PHP slice, 20/page) | ✅ |

### 9. Stats & Activity `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Current activity summary (chars/corps/alliances/ships/systems/regions) | ✅ |
| Top lists per period | ✅ |
| Kill / market / active-system stat pages | ✅ |

### 10. Players & Online `██████████████████░░` 92%

| Feature | Status |
|---------|:------:|
| Online player counts (server status) | ✅ |
| Players list | ✅ |
| Per-account character list | ✅ |
| Per-character info page | ✅ |

### 11. EVE-Mail `████████████████████` 98%

| Feature | Status |
|---------|:------:|
| Inbox / Sent / Notifications tabs | ✅ |
| Compose and reply | ✅ |
| Read / unread toggle, unread highlighting | ✅ |
| Left folder menu (corp/alliance/mailing lists) | ✅ |
| Sender portraits, single-line rows with `..` truncation | ✅ |
| Pagination (one fetch, 20/page) | ✅ |
| Reader view with portrait | ✅ |
| Live unread badge + background polling + desktop toast | ✅ |

### 12. Notifications `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| In-game notification tab | ✅ |
| Full per-type label map (Russian) | ✅ |
| Notification categories with in-place expansion | ✅ |
| Sender avatars (agent / CEO / faction) via image server | ✅ |
| Mark read / read all | ✅ |

### 13. Petitions `███████████████████░` 97%

| Feature | Status |
|---------|:------:|
| Player: create (group → category), list, thread, reply, cancel | ✅ |
| Shared thread model with the in-game petition window | ✅ |
| GM queue: all petitions, source (game/portal), status | ✅ |
| GM reply (marked as GM) and close | ✅ |
| Category tree per language (whitelist) | ✅ |

### 14. Admin Panel `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Dashboard | ✅ |
| Accounts — list, card, ban/unban, admin comment, ban reason, last login IP | ✅ |
| Roles — set role bits per account | ✅ |
| Timecodes — list, grant | ✅ |
| Items — grant to character | ✅ |
| News — publish + archive (resend to messenger, delete) | ✅ |
| Petitions admin queue | ✅ |
| E-mail test | ✅ |

### 15. Admin Security & Monitoring `██████████████████░░` 92%

| Feature | Status |
|---------|:------:|
| Login IP history per account | ✅ |
| Offline GeoLite2 geolocation (country/subdivision/city) | ✅ |
| ASN / provider lookup (GeoLite2-ASN) | ✅ |
| Shared-IP grouping / multibox detection | ✅ |
| Capital ships of human accounts on the security page | ✅ |

### 16. Search `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Search characters / corporations / systems | ✅ |

### 17. Images & Rendering `███████████████████░` 97%

| Feature | Status |
|---------|:------:|
| `img.php` cache proxy (whitelisted hosts, curl → fallback) | ✅ |
| Ship renders / icons with size snapping (32/64/128/256/512) | ✅ |
| Portraits / logos from the image server | ✅ |
| Inline SVG minimap renderer | ✅ |

### 18. Messenger Delivery `██████████████████░░` 90%

| Feature | Status |
|---------|:------:|
| Published news can be delivered to a Telegram channel | ✅ |
| Resend from the news archive | ✅ |
| Admin alerts (ban/registration events) | ✅ |

### 19. Config & Deployment `████████████████████` 98%

| Feature | Status |
|---------|:------:|
| Config split — `config.php` (user, git-ignored) / `const.php` (engine+defaults) / `config.sample.php` | ✅ |
| `PORTAL_VERSION` in footer | ✅ |
| nginx rewrite documented (`try_files`) | ✅ |
| Cache directory for images / push / 2FA devices | ✅ |

### 20. Mobile / Responsive UI `███████████████████░` 95%

| Feature | Status |
|---------|:------:|
| Navbar dropdowns open on tap/click (`.open` class) | ✅ |
| Hover-open only for fine pointers (`hover:hover and pointer:fine`) | ✅ |
| Close on outside click / Escape | ✅ |
| Responsive tables and cards | ✅ |

### 21. Web Push `█████████████████░░░` 85% ⏸

| Feature | Status |
|---------|:------:|
| Service worker (`sw.js`) + subscription store (`/mail/push`) | ✅ |
| Push worker (`tools/push_worker.php`) + VAPID generator (`tools/gen_vapid.php`) | ✅ |
| Gated by `PUSH_ENABLED` (needs HTTPS + VAPID + cron) | ⏸ |

---

## Key Notes / Ключевые заметки

- **API-only** — the portal reads XML from the EVEmu API server, never the database; all XML element/attribute names are lowercase.
- **Security** — mandatory e-mail + rules, 2FA on new devices, SMTP sender, session cap, SQL-escaped names, reserved-name refusal.
- **Shared models** — petitions and news use the same tables as the in-game views, so both sides see one conversation.
- **Images** — ship renders/icons come from `images.evetech.net` through the local cache proxy; portraits/logos come from the image server.
- **Config safety** — updates overwrite `const.php` but never `config.php`.

---

## Roadmap / Дорожная карта

- ⏸ **Enable Web Push** — run `php tools/gen_vapid.php`, set `VAPID_*` + `PUSH_ENABLED=true`, add a per-minute cron for `tools/push_worker.php` on HTTPS.
- 🟡 **Market valuation** — if market prices are thin, add a base-price fallback so ISK cards stay sensible.
- 🟡 **Admin tooling** — verify timecode / item grants against a live session; tune sub-admin roles.
- 🟡 **Messenger delivery** — cover more event types beyond news.
