<?php
require_once __DIR__ . '/../layout.php';
if (!is_logged_in()) { redirect('/login'); return; }

$user = current_user();

$xml = api_get('/char/CharacterList.xml.aspx?accountid=' . $user['accountID']);
$chars = [];
if ($xml && $xml->result && $xml->result->characters)
    foreach ($xml->result->characters->row as $r) $chars[] = $r;

ob_start();
?>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">My Characters</h2>
</div>

<?php if (empty($chars)): ?>
    <p style="color:var(--text-dim);padding:32px 0;text-align:center">No characters on this account yet. Create one in the EVE Online client.</p>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
<?php foreach ($chars as $c): ?>
    <div class="form-card" style="cursor:pointer" onclick="location.href='/character/<?= $c['characterid'] ?>'">
        <div style="display:flex;gap:12px;align-items:center">
            <img src="<?= char_portrait($c['characterid'], 64) ?>" width="56" height="56" style="border-radius:4px;background:#111820" onerror="this.style.display='none'">
            <div>
                <div style="font-weight:600;color:var(--text-bright);font-size:14px"><?= e($c['charactername']) ?></div>
                <div style="color:var(--text-dim);font-size:11px;margin-top:2px"><?= number_format((int)$c['skillpoints']) ?> SP</div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
render_layout('Characters', 'chars', ob_get_clean());
