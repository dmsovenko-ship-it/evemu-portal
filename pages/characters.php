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
<h1>Персонажи</h1>
<?php if (empty($chars)): ?>
    <p style="color:var(--text-dim)">У этого аккаунта пока нет персонажей. Создайте нового в EVE Online客户端е.</p>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:16px">
<?php foreach ($chars as $c): ?>
    <div class="form-card" style="cursor:pointer" onclick="location.href='/character/<?= $c['characterid'] ?>'">
        <div style="display:flex;gap:12px;align-items:center">
            <img src="https://images.eveonline.com/Character/<?= $c['characterid'] ?>_128.jpg" width="64" height="64" style="border-radius:4px;background:#111" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2264%22 height=%2264%22><rect fill=%22%23111%22 width=%2264%22 height=%2264%22/><text x=%2232%22 y=%2236%22 text-anchor=%22middle%22 fill=%22%23556%22 font-size=%2224%22>?</text></svg>'">
            <div>
                <div style="font-weight:600;color:var(--text-bright);font-size:15px"><?= e($c['charactername']) ?></div>
                <div style="color:var(--text-dim);font-size:12px">Skillpoints: <?= number_format((int)$c['skillpoints']) ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php
render_layout('Персонажи', 'chars', ob_get_clean());
