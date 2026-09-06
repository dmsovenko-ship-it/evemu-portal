<?php
require_once __DIR__ . '/../layout.php';
if (!is_logged_in()) { redirect('/login'); return; }

$user = current_user();
$accountID = $user['accountID'];

// The author label shown on a petition: prefer the account's first character
// (a real pilot name), fall back to the account name.
$author = $user['accountName'];
$xmlChars = api_get('/char/CharacterList.xml.aspx?accountid=' . $accountID);
if ($xmlChars && $xmlChars->result && $xmlChars->result->characters && isset($xmlChars->result->characters->row[0]))
    $author = (string)$xmlChars->result->characters->row[0]['charactername'];

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    if ($subject === '' || $body === '') {
        $error = 'Заполните тему и текст петиции.';
    } else {
        $xml = api_post('/admin/PetitionCreate.xml.aspx',
            'accountid=' . $accountID
            . '&author=' . urlencode($author)
            . '&subject=' . urlencode($subject)
            . '&body=' . urlencode($body));
        if ($xml && $xml->result) {
            $msg = 'Петиция отправлена. Администратор ответит здесь.';
        } else {
            $error = 'Не удалось отправить петицию. Попробуйте ещё раз.';
        }
    }
}

$mine = [];
$xml = api_get('/admin/PetitionMine.xml.aspx?accountid=' . $accountID);
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $mine[] = $r;

ob_start();
?>
<div class="section-header" style="margin-bottom:14px">
    <h2 style="font-size:18px">Petitions</h2>
</div>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:16px;align-items:start">

    <div class="form-card">
        <h3 style="margin-bottom:12px;font-size:14px">Submit a petition</h3>
        <form method="POST">
            <div class="form-group">
                <label>Subject</label>
                <input name="subject" maxlength="200" required placeholder="Short summary">
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="body" rows="8" required placeholder="Describe your issue / request..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Send petition</button>
        </form>
        <p style="color:var(--text-dim);font-size:11px;margin-top:10px">Submitted as <b style="color:var(--text)"><?= e($author) ?></b></p>
    </div>

    <div>
        <h3 style="margin-bottom:10px;font-size:14px">My petitions</h3>
        <?php if (empty($mine)): ?>
            <p style="color:var(--text-dim);padding:24px 0;text-align:center">No petitions yet.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Date</th><th>Subject</th><th>Status</th><th>Conversation</th></tr></thead>
            <tbody>
            <?php foreach ($mine as $p): ?>
            <tr>
                <td><?= $p['petitionid'] ?></td>
                <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['createdate']) ?></td>
                <td><?= e($p['subject']) ?></td>
                <td><?= $p['status']==1 ? '<span class="badge badge-open">Open</span>' : '<span class="badge badge-closed">Closed</span>' ?></td>
                <td style="max-width:480px">
                    <?php if ($p['body'] !== ''): ?>
                        <div style="white-space:pre-wrap;font-size:12px;line-height:1.45;color:var(--text)"><?= e($p['body']) ?></div>
                    <?php else: ?>
                        <span style="color:var(--text-dim)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
render_layout('Petitions', 'petitions', ob_get_clean());
