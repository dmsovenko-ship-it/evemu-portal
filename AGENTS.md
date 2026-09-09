# EVEmu Portal — Session Context

PHP-портал-киллборда для приватного EVEmu. Репозиторий PRIVATE. Развёрнут на отдельном хосте `video.iks-online.net:26006` (nginx+php-fpm) и `http://router.iks-online.net:26006` (тот же деплой), конфиг `config.php` там свой (API_BASE наружу к игровому серверу `172.20.1.47:26002`, image server `:26001`). Сервер EVEmu: `172.20.1.47`, API `:26002`, image server `:26001`. SSH-мост деплоя: `plink dmitry@172.20.1.47` → `sshpass ssh dmitry@172.20.1.49`, repo в `/var/www/html`, sudo через `echo gbnjy78 | sudo -S -p ""` (base64-скрипт паттерн). Прод-репо: локальные правки battle*.php застейджированы (устарели — origin уже содержит финальные версии).

**Правило**: портал НЕ ходит в БД напрямую — только в API-сервер EVEmu (`API_BASE`, default `http://127.0.0.1:26002`). Данные получает XML; SimpleXML в PHP 8 регистрозависим → ВСЕ элементы/атрибуты API lowercase.

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
