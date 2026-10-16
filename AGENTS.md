# EVEmu Portal — Session Context

## 16 окт.: лаунчер-API, новости на сайт, правила (Markdown), RDF-дата, единый источник новостей
- **`launcher_api.php`** (новый, JSON для лаунчера): `login`(ok|code|error)/`code`/`resend`/`whoami`/`logout`/`clientinfo`. **ОБЯЗАТЕЛЬНО `require config.php` ДО `const.php`** — иначе `API_BASE` = дефолтный `127.0.0.1` → «Server unreachable» (то же чинили в `feed_rdfnews.php`/`news_headlines.php`, которые грузили только `const.php`). nginx `location = /launcher/api { rewrite ^ /launcher_api.php last; }` (точный матч — иначе перехватит `location /launcher/`). Сессия/2FA — общие с веб-входом (`tfa_*` из const.php).
- **Новости — единый источник `serverNews`, рендер по каналу**: TG (богато) / сайт (`/news`) / лаунчер (title+body) / **клиент** (plain). Хелпер `news_client_text()` в `const.php` — вырезает эмодзи/не-BMP (оставляет ASCII+кириллицу+тире/кавычки), схлопывает переводы, клампит длину; `news_body_html()` для сайта.
- **Страница `/news`** (`pages/news.php`) + роут `case 'news'` + пункт в World-меню (`layout.php`, active `news`). Читает публичный `NewsFeed.xml.aspx` (сервер теперь отдаёт `body`).
- **Правила**: текст в `portal_rules.php` — Markdown, а рендерился сырым (в `e()` + `pre-wrap`). Добавлен **`rules_html()`** (h2/h3, `**bold**`, `| таблицы |`, `- списки`, `---`) + CSS; применён в `/rules` (`pages/rules.php`) и в боксе на `/register`.
- **Админка `/admin/news`**: кнопка **«Изменить»** (префилл формы по `?edit=ID`) + действие `edit` → `NewsEdit.xml.aspx`.
- **🔴 RDF `dc:date` (`feed_rdfnews.php`)**: клиентский парсер holoscreen принимает ТОЛЬКО `YYYY-MM-DDTHH:MM+ZZZZ:SS` (таймзона МЕЖДУ минутами и секундами — иначе `split(':')`/`split('+')` дают `ValueError`). Старый `gmdate('Y-m-d\TH:i:s').'+0000'` (таймзона в конце) рушил `GetNewsTickerData` → `OnMainScreenDesktopCreated`. Исправлено.
- **Биллборд**: `/billboard.asp`→`/media/billboard.png` (загрузка в `/admin/news`); заголовки `/mb/news-headlines.asp`.
- ⚠️ **Opcache** на .49 может отдать старый PHP 1-2с после `git pull` (проверять curl-ом ВТОРОЙ раз).


## 1 окт.: портал открывался очень долго → кеш API на стороне портала + прогрев (`4979694`)
- **Симптом**: главная открывалась **~17.7с** (холодный кеш игрового API). Причина: `home.php` делает 4 **последовательных** `api_get` (ServerStatus+TopValuables+Activity+AllKills, до 5с каждый), а тяжёлые агрегаты по `mktOrders`/`chrKillTable` на сервере выполнялись заново.
- **Фикс** (`const.php`, `api_get`): **файловый кеш** сырого XML в `cache/api/<sha1(path)>.xml` с TTL по эндпоинту (тяжёлые чтения 15–300с; `/char/KillMail`/`Resolve`/`CharacterInfo`/`MapData`/`CorpSheet` = 300с; live-эндпоинты auth/mail/petition **не кешируются** — префикс не в списке → TTL 0). При ошибке API — отдаёт stale (до `API_CACHE_STALE`=24ч). Новые константы `CACHE_DIR`/`API_CACHE`/`API_CACHE_STALE` (переопределяются в `config.php`).
- **Результат**: тёплый запрос **0.015с** (было 17.7с).
- **Прогрев**: на прод-хосте добавлен cron **root**: `* * * * * curl -s -o /dev/null http://127.0.0.1/` — держит кеш тёплым (пользователь не ловит холодные 17с).
- **Осталось (если надо)**: холодный cold-start всё ещё ~17с (тяжёлые SQL на сервере — `TopValuables`/`AllKills`); можно оптимизировать сами запросы/индексы. Плюс возможно распараллелить 4 вызова (curl_multi) вместо последовательных.

## 20 сент.: `/mail` — вкладки списков рассылки были пустыми (`5359439`)
- **Симптом (из старого TODO «оформить /mail» — оформление и пагинация уже были сделаны ранее, `d9af208`/`4185d98`)**: реальный баг — **вкладки «Списки рассылки» всегда пустые**.
- **Корень**: серверный `MailList.xml.aspx` отдаёт в каждой строке `tolistid` и `tocorpallianceid` (см. `APICharacterManager.cpp:822/903`), а `pages/mail.php` эти атрибуты **не парсил** → фильтр `($r['tolistid'] ?? 0) === $listFilter` всегда сравнивал 0 → пусто.
- **Фикс**: парсинг `tolistid`/`tocorpallianceid` в `$rows` и `$viewRow`; фильтр списков заработал; `$mailPageUrl` сохраняет `&list=` при пагинации; подписи «кому» — имя списка / «Корпорация» / «Альянс» (в списке и в открытом письме); **пагинация уведомлений** (30/стр, `$notifShown`, свой пейджер) — раньше был жёсткий `limit=100`.
- **Деплой**: `git pull --ff-only` на `/var/www/html` (боевой), `php -l` — OK, `GET /login` 200, `GET /mail` 302 (не авторизован), `config.php` (API_BASE) не затронут. Паттерн деплоя: base64-скрипт через plink→`sshpass ssh dmitry@172.20.1.49 'bash -s' < file` (⚠️ передавать скрипт по stdin, а не `bash /tmp/...` — /tmp на другом хосте).

## ✅ Статус 11 сент. (подтверждено юзером)
- **Портал актуальный** (`bd24463`, README + PROGRESS обновлены), проверен — всё работает.
- **Web Push пока НЕ включён**: нет TLS-сертификата (нужен HTTPS для service worker / VAPID). Включать позже: `php tools/gen_vapid.php` → `VAPID_*` + `PUSH_ENABLED=true` + cron `tools/push_worker.php`.
- Роадмап (ISK-fallback для карточек маркета, проверка админ-выдачи таймкодов/предметов, субадмин-роли) — позже.

PHP-портал-киллборда для приватного EVEmu. Репозиторий PRIVATE. Развёрнут на отдельном хосте `video.iks-online.net:26006` (nginx+php-fpm) и `http://router.iks-online.net:26006` (тот же деплой), конфиг `config.php` там свой (API_BASE наружу к игровому серверу `172.20.1.47:26002`, image server `:26001`). Сервер EVEmu: `172.20.1.47`, API `:26002`, image server `:26001`. SSH-мост деплоя: `plink dmitry@172.20.1.47` → `sshpass ssh dmitry@172.20.1.49`, repo в `/var/www/html`, sudo через `echo gbnjy78 | sudo -S -p ""` (base64-скрипт паттерн). Прод-репо: локальные правки battle*.php застейджированы (устарели — origin уже содержит финальные версии).

**Правило**: портал НЕ ходит в БД напрямую — только в API-сервер EVEmu (`API_BASE`, default `http://127.0.0.1:26002`). Данные получает XML; SimpleXML в PHP 8 регистрозависим → ВСЕ элементы/атрибуты API lowercase.

**Конфиг (10 сент.)**: `config.php` — ТОЛЬКО пользовательские настройки (API_BASE/IMAGE_SERVER/SITE_NAME/MAIL_*/TFA_*/PUSH_*); файл в `.gitignore` (git-ignored) — обновления его не трогают. Движок, дефолты (guard `if(!defined)`) и все функции — в `const.php` (перезаписывается при обновлении). Шаблон — `config.sample.php`. Миграция на сервере: `cp config.php /tmp/config.php.bak; git checkout -- config.php; git pull; cp config.sample.php config.php` + перенести MAIL_*/значения из бэкапа.

## 10 сентября (портал): конфиг-сплит, /mail, уведомления, SMTP/2FA/правила
- **Конфиг разнесён**: `config.php` — только пользовательские настройки (git-ignored), движок/дефолты и все функции — в `const.php` (guard `if(!defined)`), шаблон `config.sample.php`. Обновления не затирают user-конфиг.
- **/mail**: однострочные строки (без переносов, обрезка `..`), аватары уведомлений (агент/CEO/фракция, генерация портрета на image server), полный маппинг Notify::Types (RU), категории, пагинация/оформление.
- **Безопасность**: SMTP (`mailer.php`, `/admin/emailtest`), обязательный email при регистрации, 2FA (код на почту: админы всегда, новый device/IP), правила при регистрации (`portal_rules.php`), сессия 8ч.
- Петиции/новости — единая с игрой модель (см. ниже).
- **Навбар-дропдауны** (`502fb3d`): открываются тап/клик через JS-класс `.open` (надёжно на мобильных), hover только для `(hover:hover) and (pointer:fine)`, закрытие по клику вне и Esc — больше не «залипают».

## 9 сентября (вечер): eve-mail `/mail` + безопасность (SMTP→email при регистрации→2FA→правила)
Портал-серия (`bc8ef23`..`6f8ff16`), серверная подложка — в evemu AGENTS.
- **Eve-mail `/mail`** (игрок, только свой аккаунт): табы Входящие/Отправленные/Уведомления. API: `char/MailList`(inbox|sent, aggregate по чарам accountID, unread), `MailGet`(body распаковывается по `0x78`, авто-read), `MailSend`(от чара аккаунта, схема MailDB::SendMail), `MailRead/MailUnread`, `Notifications`(игровые, processed=0 по умолчанию), `NotifRead/NotifReadAll`, `MailStatus`(unread/notifications/lastmessageid/lastnotificationid для поллинга). Стили страницы — костыльные inline в `pages/mail.php` (CSS `.mail-*` инлайном), НЕ вынесены в style.css. События: `/mail/poll` (JSON) + бейдж непрочитанного в меню Mail (все страницы, поллинг 20с; на `/mail` свои 10с), desktop `Notification` при открытой вкладке. Web Push СДЕЛАН, ВЫКЛЮЧЕН (`PUSH_ENABLED=false`): `sw.js`, `/mail/push`, `tools/push_worker.php` (cron), `tools/gen_vapid.php`; нужен HTTPS + VAPID + cron на хосте.
- **SMTP** (`mailer.php`, порт. тест `/admin/emailtest`): чистый PHP SMTP (tls/ssl/none, AUTH LOGIN/PLAIN, UTF-8/base64, RFC2047). Конфиг `MAIL_*`; прод тестирован (SSL). ⚠️ Прод-`config.php` хранит локальные `MAIL_*` — при деплое не затирать.
- **Email обязателен при регистрации** (сервер): `auth/Register` валидирует/хранит уникальный email (колонка `account.email` уже была), `auth/Login` возвращает `<email>`, новый `auth/SetEmail` для аккаунтов без почты. Экранирование имён (SQL-inj закрыта).
- **2FA** (портал `604ac28`): код на email. Админы — всегда; остальные — новый device/IP (`cache/tfa_devices.json`, cookie `evemu_2fa_dev` 180д). Код 6 цифр, TTL 10мин, 5 попыток, в сессии только hash. Аккаунт без email → шаг привязки (SetEmail). Сессия создаётся после кода; `SESSION_LIFETIME=8ч` (cookie + guard `login_time`). `TFA_ENABLED/TFA_REQUIRE_NEW_DEVICE/TFA_ADMIN_ALWAYS` в config.
- **Правила при регистрации** (`6f8ff16`): `portal_rules.php` (`server_rules_text()`), страница `/rules`, в форме обязательный чекбокс (серверная проверка) + раскрывающийся блок текста; ссылка Rules в футере.
- Деплой: требует пересборку сервера (mail API/live-push/auth в evemu origin), затем git pull портала.

## TODO / next session (портал)
- 🔴 **`/mail` — оформить + пагинация** (юзер: «в еве почте нет оформления и пагинации»). Перенести инлайн-CSS `.mail-*`/тост/бейдж в style.css; списки писем/уведомлений пагинировать (сервер: `limit` есть, offset/page НЕТ — добавить page/offset в `MailList/Notifications` или нарезать в PHP как haul.php). Возможно: вынести шапку тредов, кнопки компоуз/ответ красивее.

## Петиции и новости — единая с игрой модель (8 сент., портал `955be91` за петициями, `32acbe5`+)
- **Петиции** (портал ↔ игровой F12 через общие таблицы сервера): `/petitions` (игрок): форма «группа→категория» (PetitionCategories, язык whitelist) + subject/body → `PetitionCreate` (author=первый чар аккаунта через CharacterList; senderid передаётся, petition.characterID=0 → видна всем чарам аккаунта в игре); список своих `PetitionMine?accountid` (вкл. игровые строки); тред `PetitionMessages?petitionid&accountid`; ответ `PetitionAddMessage` (ownership+open) / отмена `PetitionCancel`. `/admin/petitions` (GM): `PetitionList` (все, источник игра/портал по characterid, статус), тред, ответ `PetitionReply` (isGM=1 в тред, adminname/senderid = первый чар админа), закрыть `PetitionClose`. CSS тредов `.pet-thread/.pet-msg/.pet-msg-gm` в style.css.
- **Новости** `/admin/news`: публикация `PostNews` + **архив** из `NewsList` (id/date/author/title/body) с кнопками «В ТГ» (`NewsResend`) и «Удалить» (`NewsDelete`, confirm). Превью без mbstring (`news_preview` UTF-8-safe).
- Поля API: `petitionid/accountid/characterid/authorname/categoryid/categoryname/subject/status/claimedby/updated/deleted/createdate/touchdate`; сообщения: `messageid/senderid/sendername/isgm/comment/text/sentdate`; новости: `newsid/title/body/authorname/createdat`.

## Страница /sov («смена влияния», 6 сент.)
Читает `/server/SovChanges.xml.aspx?limit=N&systemid=`. Атрибуты row: `changeid/systemid/ownertype('faction'|'alliance')/oldownerid/oldownername/newownerid/newownername/systemname/regionid/regionname/time` (time = filetime → `filetime_to_unix`). Показывает When/System/Region/Previous→New owner/Type; owner 0 → «—»; цвет фракций оранжевый, альянсов синий (CSS в sov.php). Роут `case 'sov'` в index.php; пункт **Sovereignty** в World-меню (layout.php, active='sov').

## Battles (корпорации вместо имён) + Haul (пагинация), 6 сент. (юзер: «вместо корпораций имена игроков вылезают за край; haul — бесконечный список»)
- **battles.php**: колонка **Corporations** вместо Victims: `corp_list($battle, attr, corpNames)` — уникальные corpID с обеих сторон (`victimcorporationid`/`finalcorporationid` из AllKills), имена резолвятся ОДНИМ вызовом `/char/Resolve.xml.aspx?ids=...` (type=corporation), кламп 3 + «+N more», CSS `.battle-parties` (max-width 420px, nowrap, ellipsis). Каждый corp — ссылка `/corporation/{id}`. battle_summary удалён (поля берутся из $b[0]).
- **haul.php**: один запрос `CourierContracts?limit=500` (у API нет offset; сервер кеширует 20с по query string без page), PHP-пагинация по 20 (`array_slice`), пагер `.pagination` (Prev/Page X of Y/Next) с сохранением фильтров from/to (`haul_page_url`).

## Структура
- `config.php` — API_BASE, helpers: `api_get/api_post`, `current_user/is_logged_in/has_role`, CCP role-биты (ROLE_ADMIN=72057594037927936 и т.д. из Acct::Role), `ship_icon/ship_type_icon` (evetech через `/img.php`), `char_portrait/corp_logo` (image server `:26001`), `filetime_to_unix`, `security_color`, `isk_compact` ("57.86b"), `get_slot_name` (6-13 High, 19-26 Mid, 27-34 Low, 92-94 Rig...), `slot_sort_order`.
- `index.php` — роутер: `/`, `/kills`, `/kill/{id}`, `/search?q=`, `/character/{id}`, `/corporation/{id}`, `/system/{id}`, `/stats`, `/players`, `/systems`, `/market`, `/login`, `/register`, `/logout`, `/characters`, `/admin`.
- `layout.php` — навбар (Killboard/Players/Systems + Админка для ADMIN/GM), футер с `PORTAL_VERSION`.
- `img.php` — кеш-прокси внешних картинок (только images.evetech.net / eveonline.com / zkillboard.com). Кеш в `cache/` (chmod 777). curl → file_get_contents fallback. zkillboard CDN часто 403 с сервера — иконки кораблей бери с **evetech**.
- `pages/*` — kill.php (детальный килл + SVG-карты), home.php (3 блока × 6 карточек + сайдбар Activity/Top), kills/search/char_kills/corp_kills/system_kills/stats/players/systems/market, admin/*.

## API-эндпоинты (сервер evemu, service `:26002`)
- `/server/ServerStatus.xml.aspx` — serveronline/serverversion/apiversion/onlineplayers(=клиенты+челоботы)/onlineplayersreal/accountcount/charactercount/botcount.
- `/server/TopKills.xml.aspx?period=24h|7d|30d|all&page=` — топ по damage; row содержит solarsystemname.
- `/server/TopValuables.xml.aspx?period=&limit=` — топ по оценке ISK (корпус+фит по AVG(price) mktOrders); атрибуты categoryid (6=ship), victimshiptypeid, value, victimname...
- `/server/Activity.xml.aspx?period=` — `<summary total characters corporations alliances ships systems regions/>` + `<characters/corporations/alliances/ships/systems>` топы (row id/name/count).
- `/server/MapData.xml.aspx?systemid=` — `<system>` + `<constellations>`(региона) + `<systems>`(констелляции) + `<jumps>`(внутри констелляции), координаты x/z.
- `/server/Search.xml.aspx?q=` — characters/corporations/systems.
- `/server/ActiveSystems.xml.aspx`, `/server/MarketStats.xml.aspx`, `/server/KillStats.xml.aspx`.
- `/char/KillMails.xml.aspx?characterID=&beforekillid=`, `/char/AllKills.xml.aspx`, `/char/CharacterList.xml.aspx?accountid=|page=`, `/char/CharacterInfo.xml.aspx?characterID=`, `/char/KillDetail.xml.aspx?killid=` (полный: corp/alliance/region жертвы+убийцы, ticker), `/char/KillMail.xml.aspx?killid=` (текст killmail), `/char/RelatedKills.xml.aspx?killid=` (та же система ±24ч), `/char/Resolve.xml.aspx?ids=`.
- `/corp/KillMails.xml.aspx?corporationID=`, `/corp/MemberTracking.xml.aspx`.
- `/auth/Login.xml.aspx` (POST form name/password; CCP PasswordHash SHA1; возвращает `<email>`), `/auth/Register.xml.aspx` (email обязателен+уникален), `/auth/SetEmail.xml.aspx` (для аккаунтов без почты).
- `/admin/AccountList|BanAccount|UnbanAccount|PetitionList|PetitionClose|PetitionReply|TimecodeList|GrantTimecode|GiveItem|SetRole.xml.aspx`.

## Рабочие заметки / правила API
- `sDatabase.RunQuery(res, ...)` принимает const char* — SQL-конкатенацию строить в std::string + `.c_str()`.
- Всякий текстовый/блоб-атрибут в XML экранировать `xmlEscape()` (внутри API сервера) — имена могут содержать `'` (Sansha's...), killBlob содержит `<items>`.
- Атрибуты/элементы API только lowercase.
- CCP PasswordHash (логин): `hash = SHA1(password_utf16be? + salt)` по `PasswordModule::GeneratePassHash`; `hash` колонка содержит raw 20 байт; Login API сравнивает HEX(hash) из БД с hex вычисленного. Клиент-аккаунты имеют пустую `password`.
- Карты: `render_minimap($nodes,$links,$focus)` в kill.php строит inline SVG (x→px, z→py), точки по sec-цвету.
- Portal version footer: `PORTAL_VERSION`.

## TODO / на проверку
- ✅ **eve-mail `/mail` оформление + пагинация** — ЗАКРЫТО (`d9af208`, 9 сент.): общий стиль портала, портреты отправителей, подсветка непрочитанных, пагинация 20/страницу_one-fetch limit=500 (сервер offset не имеет; паттерн haul.php), читалка с портретом. Деплой на прод `d9af208`.
- Проверить после деплоя сервера (auth/mail в evemu origin): регистрация с email, 2FA-код (админ всегда / новый IP), привязка email для старых аккаунтов.
- **Web Push** — включить позже на HTTPS: `php tools/gen_vapid.php` → VAPID_* в config, `PUSH_ENABLED=true`, cron `tools/push_worker.php` каждую минуту.
- После пересборки сервера `d0c2e655` + portal `e2ec7c0`: главная (карточки Ships/Structures/Sponsored со значением, сайдбар Current Activity/Top), детальный килл (корпы/альянсы/карты справа, related), онлайн с челоботами, логин (CCP hash).
- Оценка ISK зависит от mktOrders: если цены нереалистичны/пусты — подкрутить (возможно SEED/import цен, fallback на basePrice invTypes).
- Админка: выдача таймкодов/предметов требует проверки на живой сессии; роли субадминов настраиваются через SetRole.
- Telegram-команды реализованы на СЕРВЕРЕ (`TelegramCmd`, см. evemu AGENTS): /online /topkills /market /who /last (игроки) и /status /flags /petitions /accounts /bans (админы) — портальные страницы для этого не нужны.

## 15 окт.: грид-фиксы на сервере + портал: наложение таблицы, тормоз первого клика по килу
- **Главная: длинные имена (Tash-Murkon Prime и т.п.) наезжали таблицей киллов на правый рельс топов**. Корень: `max-width` на `td` игнорируется при дефолтном `table-layout:auto`. Фикс (`bb04433`, страницы/home.php, скоуп `.zk-main`): `table-layout:fixed` + nowrap/ellipsis + ширины фикс-колонок (иконки 44px, Damage 9%, When 64px).
- **Килл-страница: первый клик медленный и фит «Unknown #N», обновление — мгновенно и всё ок**. Корень СЕРВЕРНЫЙ: `/char/Resolve` считал цену типа коррелированным `PERCENTILE_CONT` по ВСЕМ его строкам `mktOrders` (38.6M) → секунды на фит → таймаут портала 5с → XML не пришёл → «Unknown #N»; сервер дописывал и клал в свой in-memory кеш 300с → рефреш мгновенный. Фикс на сервере (evemu `APICharacterManager.cpp`): тот же сэмпл, что TopValuables (`ORDER BY orderID DESC LIMIT 500000` + `AVG(price) GROUP BY typeID` + fallback `invTypes.basePrice`); бонус — `xmlEscape` на именах (было сырьём). ⚠️ Коммит ПОСЛЕ старта сборки юзера 15 окт. — требует ещё одного pull+build.
- Страховка портала: таймаут `Resolve` в kill.php 5→15с (bb04433).
- **⚠️ Портал теперь слушает :80** (nginx на 26006 больше не висит — проверено `ss -tlnp`); проверки локально делать на `http://127.0.0.1/`. Как снаружи проброшен 26006 — вопрос роутинга/NAT юзера.
- Деплой 15 окт.: prod-репо был на 4979694 с ручным img.php (origin 0177d83 тот же контент) → `git checkout -- img.php && git pull --ff-only` (bb04433), php -l OK, home 0.06s, kill/29 0.53s (тёплый кеш).

## 📌 ДОПОЛНЕНИЕ (15 окт., вечер): биллборды + админка картинок + раздача клиента
- **Станционные биллборды**: клиент (`parklife/billboardMgr.py`) тянет 2 URL с CCP — патчем клиента переписаны на `router.iks-online.net:26006`. Портал: `news_headlines.php` (`/mb/news-headlines.asp` → текст `dd.mm.yyyy;Title` CRLF — точный формат клиентского парсера) + `/billboard.asp` → статичная `/media/billboard.png` (1024×256; сейчас — Rattlesnake render с images.evetech.net + подпись). nginx rewrites обязательны (.asp php-fpm не ест).
- **Админка** (`/admin/news`): + секция **Station Billboard** — превью + upload (png/jpg/webp/gif ≤8МБ) → GD cover-crop resize в 1024×256 → `/var/www/html/media/billboard.png` (dir chown www-data — иначе rename поверх root-файла падает). Игроки видят после следующего дока.
- **news_headlines.php** читает ТОЛЬКО API (`NewsFeed.xml.aspx`, serverNews) — правило портал≠БД соблюдено.
- **Раздача клиента**: `/launcher/update.json` + zip (`script/compiled.code` + `bin/blue.dll` + `lib/carbonlib.ccp`). **version в json ОБЯЗАН быть ≥ билда клиента** (dotted compare; 7.31.360230 > 7.31.360229). nginx `location /launcher/` no-cache (open_file_cache off).
- **Опс**: .47=tvstreamer (игра), .49=pss (портал). Вложенные ssh-кавычки молча ломаются → файловый релей (cat→.47:/tmp, scp→.49:/tmp, sudo bash). CRLF стрипать. Про .21 (video.iks-online.net DNS=172.20.1.21) — юзер пояснил: внутренние ходят через :80 на .49, снаружи только 26006→.49; «сталеп»-загадки были битым mv на .49 (файлы застревали в /tmp), НЕ другим хостом и НЕ кешем.
- Тест юзера: новости на патченном клиенте работают ✅; журнал инкурсий и магазин — после подъёма сервера с фиксами.
