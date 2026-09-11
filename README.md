<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge&logo=php&logoColor=white"/>
    <img src="https://img.shields.io/badge/PHP-8.x-4f5b93?style=for-the-badge&logo=php&logoColor=white"/>
  </picture>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/nginx-php--fpm-269539?style=for-the-badge&logo=nginx&logoColor=white"/>
    <img src="https://img.shields.io/badge/nginx-php--fpm-009639?style=for-the-badge&logo=nginx&logoColor=white"/>
  </picture>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/EVEmu-API%20:26002-4f9eff?style=for-the-badge&logo=eveonline&logoColor=white"/>
    <img src="https://img.shields.io/badge/EVEmu-API%20:26002-1364d2?style=for-the-badge&logo=eveonline&logoColor=white"/>
  </picture>
</p>

<h1 align="center">EVEmu Portal</h1>

<p align="center">
  <b>Killboard &amp; account portal for a private EVEmu server</b> · portal ~95% complete
</p>

<br>

> **Private project** — companion web front-end for the EVEmu server.  
> *Приватный проект* — веб-фронтенд для сервера EVEmu.

---

## Architecture / Архитектура

```
Browser ──▶ nginx + php-fpm ──▶ EVEmu API server (:26002) ──▶ MariaDB
                    │
                    └──▶ image server (:26001) + images.evetech.net (via img.php cache)
```

> The portal never touches the database directly — it reads XML from the EVEmu API server only.  
> *Портал не ходит в БД напрямую — только в API-сервер EVEmu (XML).*

---

## Features / Возможности

| EN | RU |
|----|----|
| **Killboard** — recent kills, top by damage and by ISK value, kill detail with fit, related kills, corporation loss matrix | **Киллборда** — последние киллы, топ по урону и по ISK, детальный килл с фитом, related-киллы, матрица потерь корпораций |
| **Battles** — system-scoped battle pages, corporations instead of raw names, per-class K/L matrix | **Битвы** — бои по системам, корпорации вместо имён, матрица K/L по классам |
| **Characters & Corporations** — kill history, corp stats, portraits and logos | **Персонажи и корпорации** — история киллов, статистика корпы, портреты и лого |
| **Systems & Sovereignty** — per-system activity, constellation/region SVG maps, sovereignty change log | **Системы и суверенность** — активность по системам, SVG-карты констелляций/регионов, журнал смены влияния |
| **Market** — traded items, buy/sell stats, top movers | **Маркет** — торгуемые предметы, статистика покупок/продаж, топы |
| **Haul** — public courier contracts with filters and pagination | **Перевозки** — публичные курьерские контракты с фильтрами и пагинацией |
| **Stats & Activity** — current activity (chars/corps/alliances/ships/systems/regions), top lists | **Статистика и активность** — текущая активность, топ-списки |
| **Players & Online** — online counts, character list, per-account characters | **Игроки и онлайн** — счётчики онлайна, список персонажей, персонажи аккаунта |
| **EVE-Mail** — inbox/sent, compose/reply, read/unread, folder menu, notification categories | **EVE-почта** — входящие/отправленные, написать/ответить, прочтение, папки, категории уведомлений |
| **Notifications** — in-game notification tab with per-type labels and sender avatars | **Уведомления** — вкладка игровых уведомлений с подписями по типам и аватарами отправителей |
| **Live mail** — unread badge in the navbar, background polling, desktop toast, optional Web Push layer | **Живая почта** — бейдж непрочитанного в меню, фоновый поллинг, desktop-тост, опциональный Web Push |
| **Petitions** — player threads (shared with the in-game F12 window) + GM queue | **Петиции** — треды игрока (общие с игровым окном F12) + очередь GM |
| **Admin panel** — accounts, roles, timecodes, item grants, news archive, e-mail test | **Админ-панель** — аккаунты, роли, таймкоды, выдача предметов, архив новостей, тест почты |
| **Admin security** — login IP history, offline GeoLite2 geolocation, ASN/provider, shared-IP detection | **Безопасность админа** — история IP, офлайн-геолокация GeoLite2, ASN/провайдер, детект shared-IP |
| **Authentication** — CCP password hash login, character picker, 8h sessions | **Авторизация** — вход по CCP-хешу, выбор персонажа, сессии 8ч |
| **Registration** — mandatory valid e-mail + server-rules agreement | **Регистрация** — обязательный валидный e-mail + согласие с правилами |
| **2FA** — e-mail codes on admins always and on new device/IP, trusted-device cookie | **2FA** — коды на почту: админам всегда, новый device/IP, cookie доверенного устройства |
| **SMTP** — pure-PHP sender (tls/ssl/none, AUTH LOGIN/PLAIN, UTF-8, RFC2047) | **SMTP** — чистый PHP-отправитель (tls/ssl/none, AUTH, UTF-8, RFC2047) |
| **Images** — cached proxy for ship renders/icons, portraits/logos from the image server, inline SVG maps | **Изображения** — кеш-прокси рендеров/иконок кораблей, портреты/лого с image server, inline SVG-карты |
| **Messenger delivery** — published news can be pushed to a Telegram channel | **Доставка в мессенджер** — опубликованные новости можно отправить в Telegram-канал |
| **Responsive UI** — navbar dropdowns that open on tap/click and close on outside click/Esc | **Адаптивный UI** — дропдауны меню открываются тапом/кликом и закрываются по клику вне/Esc |

---

## Quick Start / Быстрый старт

1. Deploy into a web root served by nginx + php-fpm / Разместить в веб-корне (nginx + php-fpm)
2. Copy `config.sample.php` to `config.php` and set `API_BASE` / `IMAGE_SERVER` / `SITE_NAME`
3. Enable URL rewriting (nginx: `try_files $uri $uri/ /index.php?$query_string`)

```bash
cp config.sample.php config.php
# edit config.php: API_BASE, IMAGE_SERVER, SITE_NAME, MAIL_*, TFA_*, PUSH_*
chmod 777 cache
```

> `config.php` is git-ignored and holds only user settings — updates never overwrite it.  
> *`config.php` в `.gitignore` и хранит только пользовательские настройки — обновления его не затирают.*  
> Engine, defaults and helpers live in `const.php`; `config.sample.php` is the template.  
> *Движок, дефолты и функции — в `const.php`; шаблон — `config.sample.php`.*

### nginx

```nginx
server {
    listen 80;
    server_name kb.yourdomain.com;
    root /path/to/evemu-portal;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Routes / Маршруты

| Route | Page |
|-------|------|
| `/` | Home — most valuable ships/structures, sponsored, activity sidebar |
| `/kills` | Kill feed |
| `/kill/{id}` | Kill detail (fit, maps, related, killmail) |
| `/battles`, `/battle/{system}-{id}` | Battles |
| `/character/{id}`, `/corporation/{id}`, `/corporation/{id}/stats` | Character / corporation |
| `/system/{id}`, `/ship/{id}`, `/group/{id}` | System / ship / group kills |
| `/stats`, `/players`, `/systems`, `/market`, `/haul`, `/sov` | Stats, players, systems, market, couriers, sovereignty |
| `/search?q=` | Search (characters / corporations / systems) |
| `/login`, `/register`, `/logout`, `/rules` | Auth & rules |
| `/characters`, `/petitions`, `/mail`, `/mail/poll`, `/mail/push` | Account, petitions, EVE-mail |
| `/admin` | Admin panel (accounts, roles, timecodes, items, news, petitions, network, security, e-mail test) |

---

## Progress / Прогресс

**Portal overall `███████████████████░` ~95%**

| Module | % | Bar | Module | % | Bar |
|--------|---|-----|--------|---|-----|
| Authentication & Sessions | 98% | `████████████████████` | Registration & Rules | 100% | `████████████████████` |
| 2FA & E-mail Security | 95% | `███████████████████░` | Killboard | 97% | `███████████████████░` |
| Characters & Corporations | 95% | `███████████████████░` | Systems & Sovereignty | 96% | `███████████████████░` |
| Market | 90% | `██████████████████░░` | Haul (Couriers) | 95% | `███████████████████░` |
| Stats & Activity | 95% | `███████████████████░` | Players & Online | 92% | `██████████████████░░` |
| EVE-Mail | 98% | `████████████████████` | Notifications | 95% | `███████████████████░` |
| Petitions | 97% | `███████████████████░` | Admin Panel | 95% | `███████████████████░` |
| Admin Security & Monitoring | 92% | `██████████████████░░` | Search | 95% | `███████████████████░` |
| Images & Rendering | 97% | `███████████████████░` | Messenger Delivery | 90% | `██████████████████░░` |
| Config & Deployment | 98% | `████████████████████` | Mobile / Responsive UI | 95% | `███████████████████░` |
| Web Push (implemented, disabled) | 85% | `█████████████████░░░` | | | |

> Totals are the mean of the modules above.  
> See [`PROGRESS.md`](PROGRESS.md) for the full breakdown.  
> Полная раскладка — в [`PROGRESS.md`](PROGRESS.md).

---

## Requirements / Зависимости

- PHP 8.x (SimpleXML, curl, openssl) with php-fpm
- EVEmu API server (`:26002`) and image server (`:26001`)
- Optional: SMTP account (2FA / notifications), GeoLite2 `.mmdb` for geolocation, VAPID keys + cron for Web Push

---

<p align="center">
  <a href="PROGRESS.md">Progress</a> ·
  <b>Private project</b>
</p>
