<?php
$msg = '';
$error = '';
$author = current_user()['accountName'] ?? 'GM';
$editId = intval($_GET['edit'] ?? 0);

if (!defined('BILLBOARD_IMG_PATH')) define('BILLBOARD_IMG_PATH', __DIR__ . '/../../media/billboard.png');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            $error = 'Заполните заголовок и текст.';
        } else {
            $xml = api_post('/admin/PostNews.xml.aspx',
                'title=' . urlencode($title)
                . '&body=' . urlencode($body)
                . '&author=' . urlencode($author));
            if ($xml && $xml->result) {
                $msg = 'Новость опубликована и отправлена в игровой Telegram.';
            } else {
                $error = 'Не удалось опубликовать новость.';
            }
        }
    } elseif ($act === 'edit') {
        $nid = intval($_POST['newsid'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        if (!$nid) {
            $error = 'Не указана новость.';
        } elseif ($title === '' || $body === '') {
            $error = 'Заполните заголовок и текст.';
        } else {
            $xml = api_post('/admin/NewsEdit.xml.aspx',
                'newsid=' . $nid
                . '&title=' . urlencode($title)
                . '&body=' . urlencode($body));
            if ($xml && $xml->result) {
                $msg = "Новость #$nid обновлена (клиент/лаунчер/сайт увидят изменение).";
                $editId = 0;
            } else {
                $error = 'Не удалось сохранить (сервер мог не пересобраться).';
            }
        }
    } elseif ($act === 'resend') {
        $nid = intval($_POST['newsid'] ?? 0);
        if ($nid) {
            $xml = api_post('/admin/NewsResend.xml.aspx', 'newsid=' . $nid);
            $msg = $xml && $xml->result ? "Новость #$nid повторно отправлена в Telegram." : 'Не удалось отправить.';
        }
    } elseif ($act === 'billboard') {
        // Station billboard ad: STRICT validation, NO resizing. The client's ad
        // texture is square 1024x1024 (like the baked quafe.dds); admins upload
        // the exact asset and anything else is rejected.
        if (!isset($_FILES['bbfile']) || $_FILES['bbfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed (file missing or too large).';
        } elseif ($_FILES['bbfile']['size'] > 8 * 1024 * 1024) {
            $error = 'Image too large (max 8 MB).';
        } else {
            $tmp = $_FILES['bbfile']['tmp_name'];
            $sz = @getimagesize($tmp);
            $ext = null;
            if ($sz) {
                if ($sz[2] === IMAGETYPE_PNG) $ext = 'png';
                elseif ($sz[2] === IMAGETYPE_JPEG) $ext = 'jpg';
            }
            if (!$ext) {
                $error = 'Only PNG or JPEG images are allowed.';
            } elseif ($sz[0] !== 1024 || $sz[1] !== 1024) {
                $error = 'Image must be exactly 1024x1024 (got ' . intval($sz[0]) . 'x' . intval($sz[1]) . '). No resizing is done.';
            } else {
                $poolDir = dirname(BILLBOARD_IMG_PATH) . '/billboard';
                if (!is_dir($poolDir)) @mkdir($poolDir, 0775, true);
                $fname = 'ad_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                if (@move_uploaded_file($tmp, $poolDir . '/' . $fname)) {
                    @copy($poolDir . '/' . $fname, BILLBOARD_IMG_PATH);
                    $pool = glob($poolDir . '/ad_*.{png,jpg,jpeg}', GLOB_BRACE) ?: [];
                    if (count($pool) > 20) {
                        sort($pool);
                        foreach (array_slice($pool, 0, count($pool) - 20) as $old) @unlink($old);
                    }
                    $msg = 'Billboard image added to the pool (' . $fname . ').';
                } else {
                    $error = 'Could not save the uploaded file (check media/billboard permissions).';
                }
            }
        }

    } elseif ($act === 'bbdelete') {
        $f = basename((string)($_POST['bbfile_name'] ?? ''));
        if (preg_match('/^[A-Za-z0-9_\-\.]+\.(png|jpg|jpeg)$/i', $f)) {
            $poolDir = dirname(BILLBOARD_IMG_PATH) . '/billboard';
            if (@unlink($poolDir . '/' . $f)) $msg = 'Removed ' . $f;
            else $error = 'Could not remove ' . $f;
        } else {
            $error = 'Bad file name.';
        }

    } elseif ($act === 'delete') {
        $nid = intval($_POST['newsid'] ?? 0);
        if ($nid) {
            $xml = api_post('/admin/NewsDelete.xml.aspx', 'newsid=' . $nid);
            $msg = $xml && $xml->result ? "Новость #$nid удалена из архива." : 'Не удалось удалить.';
        }
    }
}

// Archive list (all published news).
$news = [];
$xml = api_get('/admin/NewsList.xml.aspx');
if ($xml && $xml->result && $xml->result->news)
    foreach ($xml->result->news->row as $r) $news[] = $r;

// prefill the form when editing an existing item
$editRow = null;
if ($editId) foreach ($news as $n) if ((int)$n['newsid'] === $editId) { $editRow = $n; break; }

function news_preview($s, $max = 140) {
    $s = (string)$s;
    if (strlen($s) <= $max) return $s;
    $s = substr($s, 0, $max);
    // don't split a UTF-8 char
    while (strlen($s) > 0 && (ord($s[strlen($s)-1]) & 0xC0) === 0x80) $s = substr($s, 0, -1);
    return $s . '…';
}
?>

<h2 style="margin-bottom:16px">Новости (публичное событие)</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
    Публикуется в игровой Telegram-группе и сохраняется в архиве. Из архива можно
    отправить заново или удалить навсегда.
</p>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:720px;margin-bottom:20px">
    <?php if ($editRow): ?>
    <h3 style="margin:0 0 12px;font-size:14px">Редактировать новость #<?= (int)$editRow['newsid'] ?>
        <a href="/admin/news" style="font-size:12px;font-weight:400;margin-left:8px">&times; отмена</a></h3>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'create' ?>">
        <?php if ($editRow): ?><input type="hidden" name="newsid" value="<?= (int)$editRow['newsid'] ?>"><?php endif; ?>
        <div class="form-group">
            <label>Заголовок</label>
            <input name="title" maxlength="200" required placeholder="Например: Событие выходных / Обновление"
                   value="<?= $editRow ? e((string)$editRow['title']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Текст</label>
            <textarea name="body" rows="8" required placeholder="Текст новости..."><?= $editRow ? e((string)$editRow['body']) : '' ?></textarea>
        </div>
        <p style="color:var(--text-dim);font-size:11px;margin-bottom:10px">Автор: <?= e($author) ?></p>
        <button class="btn btn-primary" style="width:auto"><?= $editRow ? 'Сохранить' : 'Опубликовать' ?></button>
    </form>
</div>

<h3 style="margin-bottom:10px;font-size:14px">Архив новостей</h3>
<?php if (empty($news)): ?>
    <p style="color:var(--text-dim);padding:20px 0;text-align:center">Пока нет опубликованных новостей.</p>
<?php else: ?>
<table class="data-table">
    <thead><tr><th>#</th><th>Дата</th><th>Автор</th><th>Заголовок</th><th>Текст</th><th style="width:180px"></th></tr></thead>
    <tbody>
    <?php foreach ($news as $n): ?>
    <tr>
        <td><?= (int)$n['newsid'] ?></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($n['createdat']) ?></td>
        <td><?= e($n['authorname']) ?></td>
        <td><b><?= e($n['title']) ?></b></td>
        <td style="color:var(--text-dim);max-width:420px;white-space:normal"
            title="<?= e($n['body']) ?>"><?= e(news_preview($n['body'])) ?></td>
        <td style="white-space:nowrap">
            <a class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px"
               href="/admin/news?edit=<?= (int)$n['newsid'] ?>">Изменить</a>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="resend">
                <input type="hidden" name="newsid" value="<?= (int)$n['newsid'] ?>">
                <button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">В ТГ</button>
            </form>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="newsid" value="<?= (int)$n['newsid'] ?>">
                <button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px;color:#cc4444;border-color:#5a2a2a"
                    onclick="return confirm('Удалить новость #<?= (int)$n['newsid'] ?> навсегда?')">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 style="margin:28px 0 12px">Station Billboard (news headlines screen ad)</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:10px">
    The billboard ad texture is square <b>1024&times;1024</b>. Uploads are validated
    and stored <b>as-is</b> (no resizing): PNG or JPEG only, exactly 1024&times;1024,
    up to 8&nbsp;MB. Every upload joins the rotation pool below.
</p>
<div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
    <div style="border:1px solid var(--border);border-radius:6px;overflow:hidden;background:#04060c">
        <img src="/billboard.asp?&lt;refresh&gt;" style="width:256px;height:256px;display:block"
             onerror="this.onerror=null;this.src='/billboard.png'">
    </div>
    <form method="POST" enctype="multipart/form-data" style="min-width:280px">
        <input type="hidden" name="action" value="billboard">
        <input type="file" name="bbfile" accept="image/png,image/jpeg"
               style="display:block;margin-bottom:10px;font-size:12px;color:var(--text)">
        <button class="btn" style="width:auto;padding:6px 18px">Upload billboard</button>
    </form>
</div>

<h3 style="margin:24px 0 8px">Ad pool (rotation)</h3>
<table style="width:100%;border-collapse:collapse;font-size:12px">
    <tr style="color:var(--text-dim);text-align:left">
        <th style="padding:6px">preview</th>
        <th style="padding:6px">file</th>
        <th style="padding:6px">size</th>
        <th style="padding:6px"></th>
    </tr>
<?php
$poolDir = dirname(BILLBOARD_IMG_PATH) . '/billboard';
$pool = is_dir($poolDir) ? (glob($poolDir . '/*.{png,jpg,jpeg}', GLOB_BRACE) ?: []) : array();
sort($pool);
foreach ($pool as $pf):
    $bn = basename($pf);
?>
    <tr style="border-top:1px solid var(--border)">
        <td style="padding:6px"><img src="/media/billboard/<?php echo rawurlencode($bn); ?>?v=<?php echo @filemtime($pf); ?>"
            style="width:72px;height:72px;object-fit:cover;border-radius:4px;display:block"></td>
        <td style="padding:6px"><?php echo htmlspecialchars($bn); ?></td>
        <td style="padding:6px;color:var(--text-dim)"><?php echo number_format((int)@filesize($pf)); ?> b</td>
        <td style="padding:6px">
            <form method="POST" onsubmit="return confirm('Remove <?php echo htmlspecialchars($bn); ?>?')">
                <input type="hidden" name="action" value="bbdelete">
                <input type="hidden" name="bbfile_name" value="<?php echo htmlspecialchars($bn); ?>">
                <button class="btn" style="width:auto;padding:4px 12px">Delete</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
</table>

