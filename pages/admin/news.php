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
        // station billboard ad image: validate, cover-crop to 1024x256, save
        if (!isset($_FILES['bbfile']) || $_FILES['bbfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed (file missing or too large).';
        } elseif ($_FILES['bbfile']['size'] > 8 * 1024 * 1024) {
            $error = 'Image too large (max 8 MB).';
        } else {
            $tmp = $_FILES['bbfile']['tmp_name'];
            $sz = @getimagesize($tmp);
            $im = null;
            if ($sz) {
                switch ($sz[2]) {
                    case IMAGETYPE_PNG:  $im = @imagecreatefrompng($tmp);  break;
                    case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($tmp); break;
                    case IMAGETYPE_WEBP: $im = @imagecreatefromwebp($tmp); break;
                    case IMAGETYPE_GIF:  $im = @imagecreatefromgif($tmp);  break;
                }
            }
            if (!$im) {
                $error = 'Not a valid image (png/jpg/webp/gif allowed).';
            } else {
                $W = 1024; $H = 256;
                $dst = imagecreatetruecolor($W, $H);
                $bg = imagecolorallocate($dst, 4, 6, 12);
                imagefill($dst, 0, 0, $bg);
                $sw = imagesx($im); $sh = imagesy($im);
                $scale = max($W / $sw, $H / $sh);
                $nw = (int)round($sw * $scale); $nh = (int)round($sh * $scale);
                $mid = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($mid, $im, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
                imagecopy($dst, $mid, (int)(($W - $nw) / 2), (int)(($H - $nh) / 2), 0, 0, $nw, $nh);
                imagedestroy($im); imagedestroy($mid);
                imagepng($dst, BILLBOARD_IMG_PATH, 6);
                // Also keep it in the rotation pool: each upload becomes one of
                // the ads served by /billboard.asp (rotating like the original).
                $poolDir = dirname(BILLBOARD_IMG_PATH) . '/billboard';
                if (!is_dir($poolDir)) @mkdir($poolDir, 0775, true);
                if (is_dir($poolDir)) {
                    @imagepng($dst, $poolDir . '/ad_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.png', 6);
                    $pool = glob($poolDir . '/ad_*.png') ?: [];
                    if (count($pool) > 20) {
                        sort($pool);
                        foreach (array_slice($pool, 0, count($pool) - 20) as $old) @unlink($old);
                    }
                }
                imagedestroy($dst);
                $msg = 'Billboard image updated.';
            }
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
    The image is shown on the station exterior billboard screens (auto center-crop to 1024&times;256,
    png/jpg/webp/gif up to 8&nbsp;MB). Players see the new image after their next station dock.
</p>
<div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
    <div style="border:1px solid var(--border);border-radius:6px;overflow:hidden;background:#04060c">
        <img src="/billboard.asp?&lt;refresh&gt;" style="width:512px;height:128px;display:block"
             onerror="this.onerror=null;this.src='/billboard.png'">
    </div>
    <form method="POST" enctype="multipart/form-data" style="min-width:280px">
        <input type="hidden" name="action" value="billboard">
        <input type="file" name="bbfile" accept="image/*"
               style="display:block;margin-bottom:10px;font-size:12px;color:var(--text)">
        <button class="btn" style="width:auto;padding:6px 18px">Upload billboard</button>
    </form>
</div>
