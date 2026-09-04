# EVEmu Portal — Session Context

PHP-портал-киллборда для приватного EVEmu. Репозиторий PRIVATE. Развёрнут на отдельном хосте `video.iks-online.net:26006` (nginx+php-fpm), конфиг `config.php` там свой (API_BASE наружу к игровому серверу). Сервер EVEmu: `172.20.1.47`, API `:26002`, image server `:26001`.

**Правило**: портал НЕ ходит в БД напрямую — только в API-сервер EVEmu (`API_BASE`, default `http://127.0.0.1:26002`). Данные получает XML; SimpleXML в PHP 8 регистрозависим → ВСЕ элементы/атрибуты API lowercase.

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
- `/auth/Login.xml.aspx` (POST form name/password; CCP PasswordHash SHA1), `/auth/Register.xml.aspx`.
- `/admin/AccountList|BanAccount|UnbanAccount|PetitionList|PetitionClose|PetitionReply|TimecodeList|GrantTimecode|GiveItem|SetRole.xml.aspx`.

## Рабочие заметки / правила API
- `sDatabase.RunQuery(res, ...)` принимает const char* — SQL-конкатенацию строить в std::string + `.c_str()`.
- Всякий текстовый/блоб-атрибут в XML экранировать `xmlEscape()` (внутри API сервера) — имена могут содержать `'` (Sansha's...), killBlob содержит `<items>`.
- Атрибуты/элементы API только lowercase.
- CCP PasswordHash (логин): `hash = SHA1(password_utf16be? + salt)` по `PasswordModule::GeneratePassHash`; `hash` колонка содержит raw 20 байт; Login API сравнивает HEX(hash) из БД с hex вычисленного. Клиент-аккаунты имеют пустую `password`.
- Карты: `render_minimap($nodes,$links,$focus)` в kill.php строит inline SVG (x→px, z→py), точки по sec-цвету.
- Portal version footer: `PORTAL_VERSION`.

## TODO / на проверку
- После пересборки сервера `d0c2e655` + portal `e2ec7c0`: главная (карточки Ships/Structures/Sponsored со значением, сайдбар Current Activity/Top), детальный килл (корпы/альянсы/карты справа, related), онлайн с челоботами, логин (CCP hash).
- Оценка ISK зависит от mktOrders: если цены нереалистичны/пусты — подкрутить (возможно SEED/import цен, fallback на basePrice invTypes).
- Админка: выдача таймкодов/предметов требует проверки на живой сессии; роли субадминов настраиваются через SetRole.
- Telegram-интеграция (уведомления/чат) — не начата.
