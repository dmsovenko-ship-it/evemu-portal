<?php
/**
 * Admin -> CQ Screen: ad content for the captain's quarters holo screen.
 *
 * The client only plays Bink video: holoscreenSvc.GetCustomVideoPlaylist() scans
 * <client>/cache/CQScreenVideos/*.bik and plays each one through
 * cqscreen.templates.FullscreenVideo. Admins convert their clip themselves with
 * the RAD Video Tools (https://www.radgametools.com/bnkdown.htm, 7z password RAD)
 * and upload the .bik here - the portal only VALIDATES and stores it.
 *
 * Images: PNG/JPEG 16:9 for a future image template / launcher delivery.
 */

$cqVideoDir = __DIR__ . '/../../media/cq/video';
$cqImageDir = __DIR__ . '/../../media/cq/image';
foreach ([$cqVideoDir, $cqImageDir] as $d) {
    if (!is_dir($d)) @mkdir($d, 0775, true);
}

/** Read a Bink 1 header. Returns [w, h, frames, fps, seconds] or null. */
function cq_bink_info($path)
{
    $fp = @fopen($path, 'rb');
    if (!$fp) return null;
    $hdr = @fread($fp, 40);
    fclose($fp);
    if ($hdr === false || strlen($hdr) < 32) return null;
    if (substr($hdr, 0, 3) !== 'BIK') return null;   // not a Bink file
    $u32 = function ($off) use ($hdr) { return unpack('V', substr($hdr, $off, 4))[1]; };
    $frames = $u32(8);
    $w = $u32(20);
    $h = $u32(24);
    $fps = $u32(28);
    if ($fps <= 0 || $fps > 240) $fps = 30;
    $secs = $frames > 0 ? ($frames / $fps) : 0;
    return [$w, $h, $frames, $fps, $secs];
}

$msg = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'cqvideo') {
        if (!isset($_FILES['cqfile']) || $_FILES['cqfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed (file missing or too large).';
        } elseif ($_FILES['cqfile']['size'] > 5 * 1024 * 1024) {
            $error = 'Video too large: max 5 MB (re-encode with the RAD tools at a lower bitrate).';
        } else {
            $tmp = $_FILES['cqfile']['tmp_name'];
            $orig = strtolower($_FILES['cqfile']['name'] ?? '');
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            if ($ext !== 'bik' && $ext !== 'bk2') {
                $error = 'Only .bik / .bk2 (Bink) is accepted. Convert your clip with the RAD Video Tools first.';
            } else {
                $info = cq_bink_info($tmp);
                if ($info === null) {
                    $error = 'Not a Bink file (bad signature). A renamed mp4 will not play in the client.';
                } else {
                    list($w, $h, $frames, $fps, $secs) = $info;
                    if ($w <= 0 || $h <= 0) {
                        $error = 'Could not read the Bink resolution from the header.';
                    } elseif ($w > 1280 || $h > 720) {
                        $error = "Resolution $w" . "x$h is too big: max 1280x720 (the CQ screen is small).";
                    } elseif (abs(($w / $h) - (16 / 9)) > 0.02) {
                        $error = 'Aspect ratio must be 16:9 (got ' . round($w / $h, 2) . ':1). No cropping is done - re-export the video.';
                    } elseif ($secs > 60.0) {
                        $error = 'Video is too long: ' . round($secs, 1) . 's (max 60s).';
                    } else {
                        $slug = 'cq_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                        if (@move_uploaded_file($tmp, $cqVideoDir . '/' . $slug)) {
                            $msg = 'Video added: ' . $slug . ' (' . $w . 'x' . $h . ', ' . round($secs, 1) . 's, '
                                 . number_format($_FILES['cqfile']['size']) . ' b).';
                        } else {
                            $error = 'Could not save the file (check media/cq/video permissions).';
                        }
                    }
                }
            }
        }

    } elseif ($act === 'cqimage') {
        if (!isset($_FILES['cqimg']) || $_FILES['cqimg']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed (file missing or too large).';
        } elseif ($_FILES['cqimg']['size'] > 2 * 1024 * 1024) {
            $error = 'Image too large: max 2 MB.';
        } else {
            $tmp = $_FILES['cqimg']['tmp_name'];
            $sz = @getimagesize($tmp);
            $ext = null;
            if ($sz) {
                if ($sz[2] === IMAGETYPE_PNG) $ext = 'png';
                elseif ($sz[2] === IMAGETYPE_JPEG) $ext = 'jpg';
            }
            if (!$ext) {
                $error = 'Only PNG or JPEG images are allowed.';
            } elseif (abs(($sz[0] / $sz[1]) - (16 / 9)) > 0.02) {
                $error = 'Aspect ratio must be 16:9 (got ' . intval($sz[0]) . 'x' . intval($sz[1]) . '). No resizing is done.';
            } else {
                $slug = 'cq_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                if (@move_uploaded_file($tmp, $cqImageDir . '/' . $slug)) {
                    $msg = 'Image added: ' . $slug . ' (' . intval($sz[0]) . 'x' . intval($sz[1]) . ').';
                } else {
                    $error = 'Could not save the file (check media/cq/image permissions).';
                }
            }
        }

    } elseif ($act === 'cqdelete') {
        $f = basename((string)($_POST['cqname'] ?? ''));
        $cat = ($_POST['cqcat'] ?? '') === 'image' ? 'image' : 'video';
        $dir = $cat === 'image' ? $cqImageDir : $cqVideoDir;
        if (preg_match('/^[A-Za-z0-9_\-\.]+\.(bik|bk2|png|jpg|jpeg)$/i', $f)) {
            if (@unlink($dir . '/' . $f)) $msg = 'Removed ' . $f;
            else $error = 'Could not remove ' . $f;
        } else {
            $error = 'Bad file name.';
        }
    }
}

$cqVideos = is_dir($cqVideoDir) ? (glob($cqVideoDir . '/*.{bik,bk2}', GLOB_BRACE) ?: []) : [];
sort($cqVideos);
$cqImages = is_dir($cqImageDir) ? (glob($cqImageDir . '/*.{png,jpg,jpeg}', GLOB_BRACE) ?: []) : [];
sort($cqImages);

// Manifest for the launcher/release tooling: which .bik files must be copied into
// <client>/cache/CQScreenVideos/ (the client auto-plays everything it finds there).
$cqManifest = array(
    'generated' => date('c'),
    'videos'    => array_map('basename', $cqVideos),
    'images'    => array_map('basename', $cqImages),
);
@file_put_contents(__DIR__ . '/../../media/cq/manifest.json', json_encode($cqManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
?>
<h2 style="margin:0 0 12px">CQ Screen (captain's quarters)</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:14px">
    The client plays only <b>Bink</b> video: any <code>.bik</code> placed in
    <code>&lt;client&gt;/cache/CQScreenVideos/</code> is picked up automatically
    (holoscreenSvc.GetCustomVideoPlaylist). Convert clips yourself with
    <a href="https://www.radgametools.com/bnkdown.htm" target="_blank">RAD Video Tools</a>
    (7z password: <code>RAD</code>), then upload the <code>.bik</code> here.
    Limits: <b>max 1280x720, 16:9, 60s, 5 MB</b>. The portal validates and stores -
    it never re-encodes or resizes.
</p>

<?php if ($msg): ?><div style="padding:8px 12px;margin-bottom:12px;border:1px solid #2c6;border-radius:6px"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error): ?><div style="padding:8px 12px;margin-bottom:12px;border:1px solid #c44;border-radius:6px"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<h3 style="margin:18px 0 8px">Video (.bik)</h3>
<form method="POST" enctype="multipart/form-data" style="margin-bottom:14px">
    <input type="hidden" name="action" value="cqvideo">
    <input type="file" name="cqfile" accept=".bik,.bk2" style="display:block;margin-bottom:8px;font-size:12px;color:var(--text)">
    <button class="btn" style="width:auto;padding:6px 18px">Upload video</button>
</form>
<table style="width:100%;border-collapse:collapse;font-size:12px">
    <tr style="color:var(--text-dim);text-align:left">
        <th style="padding:6px">file</th><th style="padding:6px">resolution</th>
        <th style="padding:6px">length</th><th style="padding:6px">size</th><th></th>
    </tr>
<?php foreach ($cqVideos as $pf): $bn = basename($pf); $inf = cq_bink_info($pf); ?>
    <tr style="border-top:1px solid var(--border)">
        <td style="padding:6px"><?php echo htmlspecialchars($bn); ?></td>
        <td style="padding:6px;color:var(--text-dim)"><?php echo $inf ? intval($inf[0]) . 'x' . intval($inf[1]) : '?'; ?></td>
        <td style="padding:6px;color:var(--text-dim)"><?php echo $inf ? round($inf[4], 1) . 's' : '?'; ?></td>
        <td style="padding:6px;color:var(--text-dim)"><?php echo number_format((int)@filesize($pf)); ?> b</td>
        <td style="padding:6px">
            <form method="POST" onsubmit="return confirm('Remove <?php echo htmlspecialchars($bn); ?>?')">
                <input type="hidden" name="action" value="cqdelete">
                <input type="hidden" name="cqcat" value="video">
                <input type="hidden" name="cqname" value="<?php echo htmlspecialchars($bn); ?>">
                <button class="btn" style="width:auto;padding:4px 12px">Delete</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
</table>

<h3 style="margin:24px 0 8px">Image (16:9)</h3>
<form method="POST" enctype="multipart/form-data" style="margin-bottom:14px">
    <input type="hidden" name="action" value="cqimage">
    <input type="file" name="cqimg" accept="image/png,image/jpeg" style="display:block;margin-bottom:8px;font-size:12px;color:var(--text)">
    <button class="btn" style="width:auto;padding:6px 18px">Upload image</button>
</form>
<table style="width:100%;border-collapse:collapse;font-size:12px">
    <tr style="color:var(--text-dim);text-align:left">
        <th style="padding:6px">preview</th><th style="padding:6px">file</th><th style="padding:6px">size</th><th></th>
    </tr>
<?php foreach ($cqImages as $pf): $bn = basename($pf); ?>
    <tr style="border-top:1px solid var(--border)">
        <td style="padding:6px"><img src="/media/cq/image/<?php echo rawurlencode($bn); ?>?v=<?php echo @filemtime($pf); ?>"
            style="width:128px;height:72px;object-fit:cover;border-radius:4px;display:block"></td>
        <td style="padding:6px"><?php echo htmlspecialchars($bn); ?></td>
        <td style="padding:6px;color:var(--text-dim)"><?php echo number_format((int)@filesize($pf)); ?> b</td>
        <td style="padding:6px">
            <form method="POST" onsubmit="return confirm('Remove <?php echo htmlspecialchars($bn); ?>?')">
                <input type="hidden" name="action" value="cqdelete">
                <input type="hidden" name="cqcat" value="image">
                <input type="hidden" name="cqname" value="<?php echo htmlspecialchars($bn); ?>">
                <button class="btn" style="width:auto;padding:4px 12px">Delete</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
</table>

<p style="margin-top:18px;color:var(--text-dim);font-size:12px">
    Delivery: the launcher update ZIP copies <code>media/cq/video/*.bik</code> into
    <code>&lt;client&gt;/cache/CQScreenVideos/</code> (the client picks them up on
    the next station load). Images are kept for an image template / future delivery.
</p>
