<?php
// /mail — player eve-mail portal page (inbox/sent/compose) + in-game notifications.
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../layout.php';

if (!is_logged_in()) { redirect('/login'); }

$user   = current_user();
$aid    = (int)$user['accountID'];
$tab    = $_GET['tab'] ?? 'inbox';
if (!in_array($tab, ['inbox', 'sent', 'notif', 'corp', 'alliance', 'lists'], true)) $tab = 'inbox';
$listFilter = !empty($_GET['list']) ? (int)$_GET['list'] : 0;   // single mailing list view

// ---- identity: first character + list of account characters (sender chooser)
$chars   = [];
$firstID = 0; $firstName = '';
$cx = api_get('/char/CharacterList.xml.aspx?accountid=' . $aid);
if ($cx !== null && isset($cx->result->characters)) {
    foreach ($cx->result->characters->row as $r) {
        $id = (int)($r['characterid'] ?? 0);
        $nm = (string)($r['charactername'] ?? '');
        if (!$id) continue;
        $chars[] = ['id' => $id, 'name' => $nm];
        if (!$firstID) { $firstID = $id; $firstName = $nm; }
    }
}
if (!$chars) { $error = 'У аккаунта нет персонажей.'; }

$msg = ''; $error = '';

// ---- POST actions (compose / reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $to      = trim((string)($_POST['to'] ?? ''));
    $sender  = (int)($_POST['senderid'] ?? 0);
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body    = (string)($_POST['body'] ?? '');
    if ($to === '' || $subject === '' || $body === '') {
        $error = 'Заполните получателя, тему и текст.';
    } elseif (!$sender) {
        $error = 'Выберите персонажа-отправителя.';
    } else {
        $r = api_post('/char/MailSend.xml.aspx',
            http_build_query(['accountid' => $aid, 'senderid' => $sender,
                              'recipient' => $to, 'title' => $subject, 'body' => $body]));
        if ($r === null) {
            $error = 'Сервер недоступен.';
        } elseif (isset($r->error)) {
            $error = (string)$r->error;
        } elseif (isset($r->result->messageid)) {
            $msg = 'Письмо отправлено (#' . (int)$r->result->messageid . ').';
            header('Location: /mail?tab=sent'); exit;
        } else {
            $error = 'Не удалось отправить письмо.';
        }
    }
}

// ---- GET actions: mark read/unread
if (!empty($_GET['mark']) && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $end = ($_GET['mark'] === 'read') ? 'MailRead.xml.aspx' : 'MailUnread.xml.aspx';
    api_post('/char/' . $end, 'accountid=' . $aid . '&messageid=' . $id);
    header('Location: /mail?tab=' . urlencode($tab)); exit;
}

// ---- GET actions: notifications processed
if ($tab === 'notif') {
    if (!empty($_GET['clearall'])) {
        api_post('/char/NotifReadAll.xml.aspx', 'accountid=' . $aid);
        header('Location: /mail?tab=notif'); exit;
    }
    if (!empty($_GET['marknotif'])) {
        api_post('/char/NotifRead.xml.aspx', 'accountid=' . $aid . '&notificationid=' . (int)$_GET['marknotif']);
        header('Location: /mail?tab=notif'); exit;
    }
}

// ---- data fetch per tab
$rows    = [];
$view    = !empty($_GET['view']) ? (int)$_GET['view'] : 0;
$viewRow = null;   // opened message
$notifs  = [];

if (!isset($error) || !$error) {
    if ($tab === 'notif') {
        $nx = api_get('/char/Notifications.xml.aspx?accountid=' . $aid . '&processed=0&limit=100');
        if ($nx !== null && isset($nx->result->notifications)) {
            foreach ($nx->result->notifications->row as $r)
                $notifs[] = [
                    'id' => (int)($r['notificationid'] ?? 0),
                    'typeid' => (int)($r['typeid'] ?? 0),
                    'senderid' => (int)($r['senderid'] ?? 0),
                    'sendername' => (string)($r['sendername'] ?? ''),
                    'receiverid' => (int)($r['receiverid'] ?? 0),
                    'receivername' => (string)($r['receivername'] ?? ''),
                    'created' => (int)($r['created'] ?? 0),
                    'avatarid' => (int)($r['senderavatarid'] ?? 0),
                    'avatartype' => (string)($r['senderavatartype'] ?? 'character'),
                ];
        }
    } else {
        $lx = api_get('/char/MailList.xml.aspx?accountid=' . $aid . '&folder=' . $tab . '&limit=500');
        if ($lx !== null && isset($lx->result->mail)) {
            foreach ($lx->result->mail->row as $r) {
                $rows[] = [
                    'messageid' => (int)($r['messageid'] ?? 0),
                    'senderid'  => (int)($r['senderid'] ?? 0),
                    'sendername'=> (string)($r['sendername'] ?? ''),
                    'toids'     => (string)($r['tocharacterids'] ?? ''),
                    'title'     => (string)($r['title'] ?? ''),
                    'sentdate'  => (int)($r['sentdate'] ?? 0),
                    'unread'    => (int)($r['unread'] ?? 0),
                ];
            }
        }

        if ($view) {
            $gx = api_get('/char/MailGet.xml.aspx?accountid=' . $aid . '&messageid=' . $view);
            if ($gx !== null && isset($gx->result->row)) {
                $v = $gx->result->row;
                $viewRow = [
                    'messageid' => (int)($v['messageid'] ?? $view),
                    'senderid'  => (int)($v['senderid'] ?? 0),
                    'sendername'=> (string)($v['sendername'] ?? ''),
                    'toids'     => (string)($v['tocharacterids'] ?? ''),
                    'title'     => (string)($v['title'] ?? ''),
                    'sentdate'  => (int)($v['sentdate'] ?? 0),
                    'body'      => (string)($v->body ?? ''),
                ];
            } else {
                $error = 'Не удалось открыть письмо.';
            }
        }

        // resolve recipient names for the sent folder / open message (one batch call)
        $nameIds = [];
        if ($tab === 'sent') {
            foreach ($rows as $r) foreach (explode(',', $r['toids']) as $t) if ((int)$t) $nameIds[(int)$t] = 1;
        }
        if ($viewRow && $tab === 'inbox') { /* sender shown, nothing to resolve */ }

        // single-list view under "Списки рассылки" filters server rows client-side
        if ($tab === 'lists' && $listFilter) {
            $rows = array_values(array_filter($rows, function($r) use ($listFilter) {
                return (int)($r['tolistid'] ?? 0) === $listFilter;
            }));
        }

        $nameMap = [];
        if ($nameIds) {
            $ids = implode(',', array_keys($nameIds));
            if (strlen($ids) > 200) $ids = implode(',', array_slice(array_keys($nameIds), 0, 40));
            $rx = api_get('/char/Resolve.xml.aspx?ids=' . $ids);
            if ($rx !== null && isset($rx->result->names)) {
                foreach ($rx->result->names->row as $r)
                    $nameMap[(int)($r['id'] ?? 0)] = (string)($r['name'] ?? '');
            }
        }
    }
}

// mailing lists the account chars belong to (shown in the left menu always)
$mailLists = [];
$lx2 = api_get('/char/MailingLists.xml.aspx?accountid=' . $aid);
if ($lx2 !== null && isset($lx2->result->lists)) {
    foreach ($lx2->result->lists->row as $r) {
        $mailLists[] = [
            'id' => (int)($r['listid'] ?? 0),
            'name' => (string)($r['displayname'] ?? ''),
        ];
    }
}

// ---- notification type labels (full Notify::Types from EVE_Mail.h)
function notif_label(int $t): string {
    static $m = [
        1 => 'Старые сообщения', 2 => 'Персонаж удалён', 3 => 'Медаль',
        4 => 'Счёт за обслуживание (альянс)', 5 => 'Война объявлена (альянс)',
        6 => 'Капитуляция (альянс)', 7 => 'Война отозвана (альянс)', 8 => 'Война аннулирована (альянс)',
        9 => 'Счёт (персонаж)', 10 => 'Счёт (корпорация/альянс)', 11 => 'Недостаточно средств для счёта',
        12 => 'Счёт оплачен (персонаж)', 13 => 'Счёт оплачен (корпорация/альянс)',
        14 => 'Выплата за награду', 15 => 'Активация клона',
        16 => 'Новая заявка в корпорацию', 17 => 'Заявка отклонена', 18 => 'Заявка принята',
        19 => 'Изменение налога корпорации', 20 => 'Новости корпорации', 21 => 'Персонаж покинул корпорацию',
        22 => 'Новый CEO корпорации', 23 => 'Дивиденды корпорации', 25 => 'Голосование корпорации',
        26 => 'Голосование за снятие CEO',
        27 => 'Война объявлена (корпорация)', 28 => 'Законный бой', 29 => 'Капитуляция (корпорация)',
        30 => 'Война отозвана (корпорация)', 31 => 'Война аннулирована (корпорация)',
        32 => 'Пароль контейнера', 33 => 'Таможня', 34 => 'Страховка первого корабля',
        35 => 'Страховая выплата', 36 => 'Страховка аннулирована',
        37 => 'Претензия на суверенитет не удалась (альянс)', 38 => 'Претензия на суверенитет не удалась (корпорация)',
        39 => 'Просрочен счёт за суверенитет (альянс)', 40 => 'Просрочен счёт за суверенитет (корпорация)',
        41 => 'Суверенитет потерян (альянс)', 42 => 'Суверенитет потерян (корпорация)',
        43 => 'Суверенитет получен (альянс)', 44 => 'Суверенитет получен (корпорация)',
        45 => 'Установка структуры (альянс)', 46 => 'Структура уязвима (альянс)', 47 => 'Структура неуязвима (альянс)',
        48 => 'Дизраптор суверенитета', 49 => 'Структура корпорации потеряна', 50 => 'Истекает аренда офиса',
        51 => 'Клон отозван', 52 => 'Клон перемещён', 53 => 'Клон отозван',
        54 => 'Страховка истекает', 55 => 'Страховка оформлена', 56 => 'Джамп-клон удалён', 57 => 'Джамп-клон удалён',
        58 => 'Вступление в FW (корпорация)', 59 => 'Выход из FW (корпорация)', 60 => 'Исключение из FW (корпорация)',
        61 => 'Исключение из FW (персонаж)', 62 => 'Предупреждение FW (корпорация)', 63 => 'Предупреждение FW (персонаж)',
        64 => 'Понижение в звании FW', 65 => 'Повышение в звании FW', 66 => 'Перемещение агента',
        67 => 'Отмена транзакции', 68 => 'Возмещение', 69 => 'Местоположение персонажа',
        70 => 'Доступна исследовательская миссия', 71 => 'Истекает предложение миссии', 72 => 'Миссия провалена (таймаут)',
        73 => 'Доступна сюжетная миссия', 74 => 'Обучение', 75 => 'Тревога башни', 76 => 'Ресурсы башни на исходе',
        77 => 'Агрессия на станции', 78 => 'Смена состояния станции', 79 => 'Станция захвачена', 80 => 'Агрессия на станции',
        81 => 'Запрос на вступление в FW', 82 => 'Запрос на выход из FW',
        83 => 'Отозван запрос на вступление в FW', 84 => 'Отозван запрос на выход из FW',
        85 => 'Ликвидация корпорации', 86 => 'Повреждён TCU', 87 => 'Повреждён SBU', 88 => 'Повреждён IHub',
        89 => 'Добавлен контакт', 90 => 'Изменение контакта (стендинг)', 91 => 'Вторжение завершено',
        92 => 'Исключён из корпорации', 93 => 'Орбитальная структура атакована', 94 => 'Орбитальная структура укреплена',
        95 => 'Передача владения', 96 => 'Предупреждение FW (альянс)', 97 => 'Исключение из FW (альянс)',
        125 => 'Обновление рыночного ордера', 126 => 'Изменение кошелька',
        127 => 'Обновление предметов (станция)', 128 => 'Обновление предметов (система)', 129 => 'Производственное задание',
    ];
    return $m[$t] ?? ('Уведомление #' . $t);
}

// notification sender avatar: character/agent portrait, corp logo (CEO id
// resolved server-side) or faction logo.
function notif_avatar_url(string $type, int $id): string {
    if (!$id) return '';
    if ($type === 'corporation') return corp_logo($id, 32);
    if ($type === 'faction')     return '';
    return char_portrait($id, 32);
}

// notification grouping (mirrors Notify::NotifyTypeToGroup on the server)
function notif_group(int $t): string {
    if ($t === 66 || ($t >= 70 && $t <= 74)) return 'Агенты';
    if (($t >= 4 && $t <= 13) || $t === 103) return 'Счета';
    if (($t >= 16 && $t <= 31) || $t === 85 || $t === 92) return 'Корпорация';
    if ($t === 2 || $t === 3 || $t === 14 || $t === 15 ||
        ($t >= 32 && $t <= 36) || ($t >= 51 && $t <= 56) ||
        ($t >= 67 && $t <= 69) || $t === 91) return 'Прочее';
    if ($t === 1) return 'Старые';
    if (($t >= 37 && $t <= 44) || ($t >= 77 && $t <= 80) || ($t >= 86 && $t <= 88)) return 'Суверенитет';
    if (($t >= 45 && $t <= 50) || ($t >= 75 && $t <= 76) || ($t >= 93 && $t <= 94)) return 'Структуры';
    if ($t === 5 || ($t >= 57 && $t <= 65) || ($t >= 81 && $t <= 84) || ($t >= 96 && $t <= 97)) return 'Война';
    if ($t === 89 || $t === 90) return 'Контакты';
    if ($t >= 125 && $t <= 129) return 'Корпорация';
    return 'Прочее';
}

// ---- pagination over the fetched list (server has no offset; same pattern as haul)
$mailPageSize = 20;
$mailPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$mailTotal = count($rows);
$mailPages = max(1, (int)ceil($mailTotal / $mailPageSize));
if ($mailPage > $mailPages) $mailPage = $mailPages;
$mailShown = array_slice($rows, ($mailPage - 1) * $mailPageSize, $mailPageSize);

$mailPageUrl = function(int $p) use ($tab, $view): string {
    $q = '/mail?tab=' . urlencode($tab) . '&page=' . $p;
    if ($view) $q .= '&view=' . $view;
    return $q;
};

ob_start();
?>
<style>
.mail-head{display:flex;align-items:baseline;gap:12px;margin-bottom:6px}
.mail-head h2{font-size:18px}
.mail-head .mail-count{color:var(--text-dim);font-size:12px}
.mail-tabs{display:flex;gap:8px;margin:12px 0 14px;flex-wrap:wrap;align-items:center}
.mail-tabs a{padding:7px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;background:var(--bg-card)}
.mail-tabs a.active{background:var(--accent2);color:#06121f;border-color:transparent;font-weight:700}
.mail-tabs .push-btn{padding:7px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);cursor:pointer;font-size:12px}
.mail-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);overflow:hidden}
.mail-layout{display:grid;grid-template-columns:210px 1fr;gap:16px;align-items:start}
.mail-menu{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);overflow:hidden}
.mail-menu a{display:block;padding:8px 14px;color:var(--text);border-bottom:1px solid var(--border);text-decoration:none;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mail-menu a:last-of-type{border-bottom:none}
.mail-menu a:hover{background:var(--bg-hover);color:var(--accent2)}
.mail-menu a.active{background:rgba(74,158,255,.12);color:var(--accent2);font-weight:700}
.mail-menu .m-group{padding:8px 12px 5px;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);border-bottom:1px solid var(--border);margin-top:4px}
.notif-row summary{display:flex;gap:10px;align-items:center;padding:9px 12px;border-bottom:1px solid var(--border);cursor:pointer;list-style:none;font-size:13px}
.notif-row summary::-webkit-details-marker{display:none}
.notif-row summary::before{content:'▸';color:var(--text-dim);flex:0 0 auto;transition:transform .12s}
.notif-row[open] summary::before{content:'▾'}
.notif-row summary:hover{background:var(--bg-hover)}
.notif-row summary b{flex:0 0 auto;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.notif-row summary .badge{flex:0 0 auto}
.notif-row summary span.date{margin-left:auto}
.mail-compose{margin:14px 0;padding:16px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card)}
.mail-compose>b{display:block;margin-bottom:10px;color:var(--text-bright, var(--text))}
.mail-compose label{display:block;font-size:12px;color:var(--text-dim);margin:10px 0 4px}
.mail-compose input[type=text],.mail-compose select,.mail-compose textarea{width:100%;box-sizing:border-box;padding:7px 9px;border-radius:7px;border:1px solid var(--border);background:#0d1117;color:var(--text);font-size:13px}
.mail-compose textarea{min-height:130px;font-family:inherit;resize:vertical}
.mail-row{display:flex;gap:10px;align-items:center;padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;text-decoration:none;color:var(--text)}
.mail-row:last-child{border-bottom:none}
.mail-row:hover{background:var(--bg-hover);text-decoration:none}
.mail-row.unread{background:rgba(74,158,255,.07)}
.mail-row.unread:hover{background:rgba(74,158,255,.12)}
.mail-row .m-ava{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);flex:0 0 auto;background:#0d1117;object-fit:cover}
.mail-row .dot{width:8px;height:8px;border-radius:50%;background:var(--accent2);flex:0 0 auto}
.mail-row .m-who{min-width:170px;max-width:220px;color:var(--text-dim);font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mail-row.unread .m-who{color:var(--text-bright,var(--text))}
.mail-row.unread .m-subj{font-weight:700}
.mail-row .m-subj{flex:0 1 50%;max-width:50%;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px}
.mail-row .m-date{color:var(--text-dim);font-size:12px;white-space:nowrap;margin-left:auto}
.mail-row .m-act a{color:var(--text-dim);font-size:12px;text-decoration:none;white-space:nowrap}
.mail-row .m-act a:hover{color:var(--accent2)}
.mail-pager{display:flex;gap:6px;align-items:center;justify-content:center;padding:12px}
.mail-pager a,.mail-pager span{padding:5px 11px;border-radius:6px;border:1px solid var(--border);font-size:12px;color:var(--text-dim);text-decoration:none}
.mail-pager a:hover{color:var(--accent2);border-color:var(--accent2)}
.mail-pager span.cur{background:var(--accent2);color:#06121f;border-color:transparent;font-weight:700}
.mail-view{margin:14px 0;padding:16px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card)}
.mail-view h2{margin:0 0 4px;font-size:17px;color:var(--text-bright,var(--text))}
.mail-view .meta{color:var(--text-dim);font-size:12px;margin-bottom:12px}
.mail-view .m-top{display:flex;gap:10px;align-items:center;margin-bottom:12px}
.mail-view .m-top img{width:44px;height:44px;border-radius:50%;border:1px solid var(--border)}
.mail-view .body{white-space:pre-wrap;word-break:break-word;line-height:1.55;font-size:13px}
.notif-row{padding:0;font-size:13px}
.notif-row:last-child{border-bottom:none}
.notif-row .badge{background:rgba(255,200,60,.14);color:#ffc83c;border-radius:6px;padding:2px 7px;font-size:12px;white-space:nowrap}
.empty{color:var(--text-dim);text-align:center;padding:26px 0}
.toast{position:fixed;right:16px;bottom:70px;z-index:600;background:var(--bg-card);border:1px solid var(--accent2);border-radius:10px;padding:12px 16px;max-width:320px;box-shadow:0 6px 20px rgba(0,0,0,.5);display:none}
#mailToastBtn{position:fixed;right:14px;bottom:64px;z-index:500;width:40px;height:40px;border-radius:50%;border:1px solid var(--accent2);background:var(--bg-card);color:var(--text);cursor:pointer;font-size:17px;display:none}
@media(max-width:768px){ .mail-row .m-who{min-width:110px;max-width:130px} .mail-row .m-date{display:none} }
</style>

<script>window.EVEMU_MAIL_OWN_POLL = false;</script>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="mail-head">
    <h2>Eve Mail</h2>
    <?php if ($tab !== 'notif'): ?><span class="mail-count"><?= $mailTotal ?> писем · страница <?= $mailPage ?>/<?= $mailPages ?></span><?php endif; ?>
</div>

<div class="mail-layout">
    <aside class="mail-menu">
        <div class="m-group">Почта</div>
        <a href="/mail" class="<?= $tab==='inbox'?'active':'' ?>">Личные</a>
        <a href="/mail?tab=corp" class="<?= $tab==='corp'?'active':'' ?>">Корпорация</a>
        <a href="/mail?tab=alliance" class="<?= $tab==='alliance'?'active':'' ?>">Альянс</a>
        <div class="m-group">Списки рассылки</div>
        <?php foreach ($mailLists as $l): ?>
            <a href="/mail?tab=lists&list=<?= (int)$l['id'] ?>" class="<?= ($tab==='lists' && $listFilter===(int)$l['id']) ? 'active' : '' ?>"><?= e($l['name']) ?></a>
        <?php endforeach; ?>
        <a href="/mail?tab=lists" class="<?= ($tab==='lists' && !$listFilter) ? 'active' : '' ?>">Все сообщения списков</a>
        <div class="m-group">Прочее</div>
        <a href="/mail?tab=sent" class="<?= $tab==='sent'?'active':'' ?>">Отправленные</a>
        <a href="/mail?tab=notif" class="<?= $tab==='notif'?'active':'' ?>">Уведомления</a>
        <?php if (PUSH_ENABLED): ?>
            <div style="padding:10px 12px"><button id="pushToggle" class="push-btn" style="width:100%">Уведомления: …</button></div>
        <?php endif; ?>
    </aside>
    <div class="mail-main">

<?php if ($tab !== 'notif'): ?>
<div class="mail-compose">
    <b>Новое письмо</b>
    <?php
    $preTo = (string)($_GET['to'] ?? '');
    $preSubj = (string)($_GET['subj'] ?? '');
    ?>
    <form method="post" action="/mail?tab=<?= e($tab) ?>">
        <input type="hidden" name="action" value="send">
        <label>От (персонаж)</label>
        <select name="senderid">
            <?php foreach ($chars as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (isset($_GET['sender']) && (int)$_GET['sender'] === (int)$c['id']) || (!isset($_GET['sender']) && (int)$c['id'] === $firstID) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Кому (имя персонажа или ID)</label>
        <input type="text" name="to" value="<?= e($preTo) ?>" required>
        <label>Тема</label>
        <input type="text" name="subject" value="<?= e($preSubj) ?>" required>
        <label>Текст</label>
        <textarea name="body" required></textarea>
        <div style="margin-top:10px"><button type="submit" class="btn btn-primary">Отправить</button></div>
    </form>
</div>
<?php endif; ?>

<?php if ($tab === 'notif'): ?>
    <?php if (!$notifs): ?><div class="empty">Новых уведомлений нет.</div><?php endif; ?>
    <div style="margin:0 0 12px">
        <?php if ($notifs): ?><a href="/mail?tab=notif&clearall=1" class="btn btn-outline" style="font-size:12px">Отметить все прочитанными</a><?php endif; ?>
    </div>
    <?php
    // notifications grouped by category (War / Corporation / Sovereignty / System),
    // each row is expandable (<details>) so the details open in place.
    $grpOrder = ['Война', 'Корпорация', 'Контакты', 'Суверенитет', 'Структуры', 'Агенты', 'Счета', 'Прочее', 'Старые'];
    $notifGroups = [];
    foreach ($notifs as $n)
        $notifGroups[notif_group((int)$n['typeid'])][] = $n;
    ?>
    <?php foreach ($grpOrder as $gTitle):
        if (empty($notifGroups[$gTitle])) continue;
        $gRows = $notifGroups[$gTitle]; ?>
        <div class="m-group" style="margin:14px 0 6px;letter-spacing:1px"><?= e($gTitle) ?> · <?= count($gRows) ?></div>
        <div class="mail-card">
        <?php foreach ($gRows as $n): ?>
            <details class="notif-row">
                <summary>
                    <?php $av = notif_avatar_url((string)$n['avatartype'], (int)$n['avatarid']); if ($av): ?>
                        <img class="m-ava" src="<?= e($av) ?>" alt="" onerror="this.style.visibility='hidden'">
                    <?php endif; ?>
                    <span class="badge" title="typeID <?= (int)$n['typeid'] ?>"><?= e(notif_label((int)$n['typeid'])) ?></span>
                    <?php if ((int)$n['senderid']): ?>
                        <b><?= e($n['sendername'] ?: ('#' . $n['senderid'])) ?></b>
                    <?php else: ?>
                        <b>Система</b>
                    <?php endif; ?>
                    <span class="date" style="color:var(--text-dim);font-size:12px"><?= e(date('d.m.Y H:i', filetime_to_unix($n['created']))) ?></span>
                </summary>
                <div style="padding:8px 4px 2px;font-size:12px;color:var(--text-dim)">
                    <?php if ((int)$n['senderid']): ?>от <b><?= e($n['sendername'] ?: ('#' . $n['senderid'])) ?></b><?php else: ?><b>Система</b><?php endif; ?>
                    &nbsp;для <?= e($n['receivername'] ?: ('#' . $n['receiverid'])) ?>
                    &nbsp;·&nbsp; <?= e(notif_label((int)$n['typeid'])) ?> <span style="opacity:.6">(typeID <?= (int)$n['typeid'] ?>)</span>
                    <div style="margin-top:6px">
                        <a href="/mail?tab=notif&marknotif=<?= (int)$n['id'] ?>" style="color:var(--accent2);text-decoration:none;font-size:12px">отметить прочитанным</a>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <?php if ($viewRow): ?>
        <?php if ($tab === 'inbox'): ?>
            <div style="margin:10px 0"><a href="/mail" class="btn btn-outline" style="font-size:12px">&larr; назад</a></div>
        <?php endif; ?>
        <div class="mail-view">
            <h2><?= e($viewRow['title']) ?></h2>
            <div class="m-top">
                <img src="<?= e(char_portrait((int)$viewRow['senderid'], 64)) ?>" alt="" onerror="this.style.visibility='hidden'">
                <div>
                    <div class="meta" style="margin-bottom:0">
                        От: <b><?= e($viewRow['sendername'] ?: ('#' . $viewRow['senderid'])) ?></b>
                        &nbsp;·&nbsp; <?= e(date('d.m.Y H:i', filetime_to_unix($viewRow['sentdate']))) ?>
                    </div>
                    <?php if ($viewRow['toids']): ?>
                        <div class="meta" style="margin-bottom:0">
                            Кому: <?= e(implode(', ', array_map(function($t){ return (string)$t; }, array_filter(array_map('trim', explode(',', $viewRow['toids'])))))) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="body"><?= e($viewRow['body']) ?></div>
            <?php if ($tab === 'inbox'): ?>
                <div style="margin-top:14px">
                    <a class="btn btn-primary" style="font-size:13px"
                       href="/mail?tab=inbox&view=<?= (int)$viewRow['messageid'] ?>&reply=1">Ответить</a>
                    <a class="btn btn-outline" style="font-size:13px"
                       href="/mail?tab=inbox&mark=unread&id=<?= (int)$viewRow['messageid'] ?>">Отметить непрочитанным</a>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($_GET['reply']) && $tab === 'inbox'): ?>
            <div class="mail-compose">
                <b>Ответ: <?= e($viewRow['title']) ?></b>
                <form method="post" action="/mail">
                    <input type="hidden" name="action" value="send">
                    <label>От</label>
                    <select name="senderid">
                        <?php foreach ($chars as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $firstID ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Кому</label>
                    <input type="text" name="to" value="<?= e($viewRow['sendername'] ?: $viewRow['senderid']) ?>">
                    <label>Тема</label>
                    <input type="text" name="subject" value="<?= e(preg_match('/^Re:/i', $viewRow['title']) ? $viewRow['title'] : ('Re: ' . $viewRow['title'])) ?>">
                    <label>Текст</label>
                    <textarea name="body" required></textarea>
                    <div style="margin-top:10px"><button type="submit" class="btn btn-primary">Отправить ответ</button></div>
                </form>
            </div>
        <?php endif; ?>
    <?php elseif (!$mailShown && !$viewRow): ?>
        <div class="empty">
            <?php
                echo e($tab === 'sent' ? 'Отправленных писем нет.'
                    : ($tab === 'corp' ? 'Корпоративных писем нет.'
                    : ($tab === 'alliance' ? 'Писем от альянса нет.'
                    : ($tab === 'lists' ? 'Сообщений от рассылок нет.'
                    : 'Входящих писем нет.'))));
            ?>
        </div>
    <?php endif; ?>

    <?php if (!$viewRow && $mailShown): ?>
    <div class="mail-card">
    <?php foreach ($mailShown as $r): ?>
        <a class="mail-row <?= $r['unread'] ? 'unread' : '' ?>" href="/mail?tab=<?= e($tab) ?>&view=<?= (int)$r['messageid'] ?>">
            <?php if ($tab === 'inbox'): ?>
                <img class="m-ava" src="<?= e(char_portrait((int)$r['senderid'], 32)) ?>" alt="" onerror="this.style.visibility='hidden'">
            <?php else: ?>
                <span class="m-ava" style="display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:15px">&#10148;</span>
            <?php endif; ?>
            <?php if ($r['unread']): ?><span class="dot" title="Непрочитано"></span><?php endif; ?>
            <span class="m-who">
                <?php if ($tab === 'inbox'): ?>
                    <?= e($r['sendername'] ?: ('#' . $r['senderid'])) ?>
                <?php else: ?>
                    <?php
                        $names = [];
                        foreach (array_filter(array_map('trim', explode(',', $r['toids']))) as $t) $names[] = $nameMap[(int)$t] ?? ('#' . $t);
                        echo e(implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '…' : ''));
                    ?>
                <?php endif; ?>
            </span>
            <span class="m-subj"><?= e($r['title']) ?></span>
            <span class="m-date"><?= e(date('d.m.Y H:i', filetime_to_unix($r['sentdate']))) ?></span>
            <?php if ($tab === 'inbox'): ?>
                <span class="m-act">
                    <a href="/mail?tab=inbox&mark=<?= $r['unread'] ? 'read' : 'unread' ?>&id=<?= (int)$r['messageid'] ?>"
                       onclick="event.preventDefault(); fetch('/mail?tab=inbox&mark=<?= $r['unread'] ? 'read' : 'unread' ?>&id=<?= (int)$r['messageid'] ?>').then(()=>location.reload());">
                        <?= $r['unread'] ? 'прочит.' : 'непрочит.' ?>
                    </a>
                </span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    </div>
    <?php if ($mailPages > 1): ?>
    <div class="mail-pager">
        <?php if ($mailPage > 1): ?><a href="<?= e($mailPageUrl($mailPage - 1)) ?>">&larr; Prev</a><?php endif; ?>
        <span class="cur">Page <?= $mailPage ?> of <?= $mailPages ?></span>
        <?php if ($mailPage < $mailPages): ?><a href="<?= e($mailPageUrl($mailPage + 1)) ?>">Next &rarr;</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
    </div><!-- /.mail-main -->
</div><!-- /.mail-layout -->

<button id="mailToastBtn" title="Новое событие">🔔</button>
<div class="toast" id="mailToast"></div>

<script>
(function(){
  var SITE = <?= json_encode(SITE_NAME) ?>;
  var aid  = <?= (int)$aid ?>;
  var lastMail = -1, lastNotif = -1, first = true;

  function toast(text){
    var el = document.getElementById('mailToast');
    el.textContent = text;
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function(){ el.style.display = 'none'; }, 8000);
  }
  function updateBadge(d){
    var dot = document.getElementById('mailBadge');
    if (!dot) return;
    var n = d.unread + d.notifications;
    dot.style.display = n > 0 ? '' : 'none';
    dot.textContent = n;
  }
  function poll(){
    fetch('/mail/poll?full=1', {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) return;
        updateBadge(d);
        if (first){ lastMail = d.unread; lastNotif = d.notifications; first = false; return; }
        if (d.unread > lastMail){
          var t = 'Новое письмо';
          if (d.top && d.top.sendername) t += ': ' + d.top.sendername + (d.top.title ? ' — ' + d.top.title : '');
          if (typeof Notification !== 'undefined' && Notification.permission === 'granted'){
            try{ new Notification(SITE, { body: t }); }catch(e){ toast(t); }
          } else toast(t);
          lastMail = d.unread;
        }
        if (d.notifications > lastNotif){
          var t2 = 'Новые уведомления: ' + d.notifications;
          if (typeof Notification !== 'undefined' && Notification.permission === 'granted'){
            try{ new Notification(SITE, { body: t2 }); }catch(e){ toast(t2); }
          } else toast(t2);
          lastNotif = d.notifications;
        }
      }).catch(function(){});
  }
  poll();
  setInterval(poll, 10000);

  <?php if (PUSH_ENABLED): ?>
  // ---- web push subscription management ----
  (function(){
    var btn = document.getElementById('pushToggle');
    var PUSH_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
    function setState(t){ if (btn) btn.textContent = 'Уведомления: ' + t; }
    function post(data){
      var f = new FormData(); for (var k in data) f.append(k, data[k]);
      return fetch('/mail/push', {method:'POST', credentials:'same-origin', body:f}).then(function(r){ return r.json(); });
    }
    function reg(){
      if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext){ setState('нет (нужен HTTPS)'); return; }
      navigator.serviceWorker.register('/sw.js').then(function(sw){
        sw.pushManager.getSubscription().then(function(sub){
          if (sub){ setState('вкл'); return; }
          var opts = { userVisibleOnly: true };
          if (PUSH_KEY) opts.applicationServerKey = PUSH_KEY;
          sw.pushManager.subscribe(opts).then(function(sub){
            post({ action:'subscribe', endpoint: sub.endpoint,
                   p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('p256dh')))),
                   auth:   btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('auth')))) }).then(function(d){
              setState(d.subscribed ? 'вкл' : 'ошибка');
            });
          }).catch(function(e){ setState('отказ'); });
        });
      }).catch(function(){ setState('недоступно'); });
    }
    function base64ToUrlB64(s){ return s.replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); }
    if (btn){
      btn.onclick = function(){
        if (typeof Notification === 'undefined'){ setState('нет'); return; }
        if (Notification.permission === 'granted'){ reg(); return; }
        if (Notification.permission === 'denied'){ setState('запрещено'); return; }
        Notification.requestPermission().then(function(p){
          if (p === 'granted') reg(); else setState('запрещено');
        });
      };
      fetch('/mail/push', {credentials:'same-origin'}).then(function(r){ return r.json(); }).then(function(d){
        if (d.ok && d.pushenabled) setState(d.subscribed ? 'вкл' : 'выкл');
      }).catch(function(){});
    }
  })();
  <?php endif; ?>
})();
</script>
<?php
render_layout('Mail', 'mail', ob_get_clean());
