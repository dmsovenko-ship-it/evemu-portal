<?php
require_once __DIR__ . '/../layout.php';
if (!is_logged_in()) { redirect('/login'); return; }

$user = current_user();
$accountID = $user['accountID'];

// Author label: prefer the account's first character.
$author = $user['accountName'];
$xmlChars = api_get('/char/CharacterList.xml.aspx?accountid=' . $accountID);
$senderID = 0;
if ($xmlChars && $xmlChars->result && $xmlChars->result->characters && isset($xmlChars->result->characters->row[0])) {
    $author = (string)$xmlChars->result->characters->row[0]['charactername'];
    $senderID = (int)$xmlChars->result->characters->row[0]['characterid'];
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? 'create';
    if ($act === 'create') {
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        $cat     = trim($_POST['categoryid'] ?? '');
        if ($subject === '' || $body === '') {
            $error = 'Заполните тему и текст петиции.';
        } else {
            $xml = api_post('/admin/PetitionCreate.xml.aspx',
                'accountid=' . $accountID
                . '&author=' . urlencode($author)
                . '&senderid=' . $senderID
                . '&categoryid=' . urlencode($cat)
                . '&subject=' . urlencode($subject)
                . '&body=' . urlencode($body));
            if ($xml && $xml->result && isset($xml->result->petitionid)) {
                $msg = 'Петиция #' . (int)$xml->result->petitionid . ' отправлена. Администратор ответит здесь.';
            } else {
                $error = 'Не удалось отправить петицию. Попробуйте ещё раз.';
            }
        }
    } elseif ($act === 'reply') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($pid && $reply !== '') {
            $xml = api_post('/admin/PetitionAddMessage.xml.aspx',
                'petitionid=' . $pid
                . '&accountid=' . $accountID
                . '&senderid=' . $senderID
                . '&sendername=' . urlencode($author)
                . '&message=' . urlencode($reply));
            $msg = ($xml && $xml->result) ? 'Сообщение добавлено.' : 'Не удалось отправить сообщение.';
        }
    } elseif ($act === 'cancel') {
        $pid = intval($_POST['petitionid'] ?? 0);
        if ($pid) {
            $xml = api_post('/admin/PetitionCancel.xml.aspx', "petitionid=$pid&accountid=$accountID");
            $msg = ($xml && $xml->result) ? 'Петиция отменена.' : 'Не удалось отменить петицию.';
        }
    }
}

// My petitions (list).  Open detail is fetched separately so we always get fresh thread.
$mine = [];
$xml = api_get('/admin/PetitionMine.xml.aspx?accountid=' . $accountID);
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $mine[] = $r;

$view = isset($_GET['view']) ? intval($_GET['view']) : 0;
$thread = [];
$viewPet = null;
if ($view) {
    // Only allow viewing own petition.
    foreach ($mine as $p) if ((int)$p['petitionid'] === $view) { $viewPet = $p; break; }
    if ($viewPet) {
        $xml = api_get('/admin/PetitionMessages.xml.aspx?petitionid=' . $view . '&accountid=' . $accountID);
        if ($xml && $xml->result && $xml->result->messages)
            foreach ($xml->result->messages->row as $m) $thread[] = $m;
    } else {
        $view = 0;
    }
}

// Categories (for the submit form).
$groups = [];   // id => name
$cats   = [];   // id => [name, groupName]
$xml = api_get('/admin/PetitionCategories.xml.aspx?language=ru');
if ($xml && $xml->result && $xml->result->categories)
    foreach ($xml->result->categories->row as $c) {
        if ((int)$c['parentcategoryid'] === 0)
            $groups[(int)$c['categoryid']] = (string)$c['categoryname'];
        else
            $cats[] = ['id' => (int)$c['categoryid'], 'name' => (string)$c['categoryname'], 'group' => (int)$c['parentcategoryid']];
    }

ob_start();
?>
<div class="section-header" style="margin-bottom:14px">
    <h2 style="font-size:18px">Petitions</h2>
</div>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:16px;align-items:start">

    <div class="form-card">
        <h3 style="margin-bottom:12px;font-size:14px">New petition</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Group</label>
                <select name="group" id="pet-group" onchange="petFilterCats()">
                    <option value="">— select group —</option>
                    <?php foreach ($groups as $gid => $gname): ?>
                        <option value="<?= $gid ?>"><?= e($gname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="categoryid" id="pet-cat" required>
                    <option value="">— select category —</option>
                </select>
            </div>
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
            <thead><tr><th>#</th><th>Date</th><th>Category</th><th>Subject</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($mine as $p): ?>
            <tr style="cursor:pointer" onclick="location.href='/petitions?view=<?= (int)$p['petitionid'] ?>'">
                <td><?= (int)$p['petitionid'] ?></td>
                <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['createdate']) ?></td>
                <td style="color:var(--text-dim)"><?= e($p['categoryname'] ?: '—') ?></td>
                <td><?= e($p['subject']) ?></td>
                <td><?= (int)$p['status']===1 ? '<span class="badge badge-open">Open</span>' : '<span class="badge badge-closed">Closed</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($viewPet): ?>
        <div class="form-card" style="margin-top:16px">
            <h3 style="margin-bottom:4px;font-size:14px">#<?= (int)$viewPet['petitionid'] ?> — <?= e($viewPet['subject']) ?></h3>
            <div style="color:var(--text-dim);font-size:12px;margin-bottom:10px">
                <?= e($viewPet['categoryname'] ?: '—') ?> &middot; <?= e($viewPet['createdate']) ?> &middot;
                <?= (int)$viewPet['status']===1 ? 'открыта' : 'закрыта' ?>
            </div>
            <div class="pet-thread">
                <?php foreach ($thread as $m): ?>
                <div class="pet-msg <?= (int)$m['isgm']===1 ? 'pet-msg-gm' : '' ?>">
                    <div class="pet-msg-head">
                        <?php if ((int)$m['isgm']===1): ?><span class="badge badge-admin">GM</span><?php endif; ?>
                        <b><?= e($m['sendername'] ?: ($m['isgm']==1 ? 'GM' : 'You')) ?></b>
                        <span style="color:var(--text-dim);font-size:11px"><?= e($m['sentdate']) ?></span>
                    </div>
                    <div class="pet-msg-body"><?= nl2br(e($m['text'])) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (!$thread): ?><p style="color:var(--text-dim)">—</p><?php endif; ?>
            </div>
            <?php if ((int)$viewPet['status']===1): ?>
            <form method="POST" style="margin-top:12px">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="petitionid" value="<?= (int)$viewPet['petitionid'] ?>">
                <div class="form-group">
                    <textarea name="reply" rows="3" required placeholder="Your message..."></textarea>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-primary" style="width:auto">Add message</button>
                    <button class="btn btn-outline" style="width:auto" formnovalidate name="action" value="cancel"
                        onclick="return confirm('Cancel this petition?')">Cancel petition</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const petGroups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?>;
const petCats = <?= json_encode($cats, JSON_UNESCAPED_UNICODE) ?>;
function petFilterCats() {
    const g = document.getElementById('pet-group').value;
    const sel = document.getElementById('pet-cat');
    sel.innerHTML = '<option value="">— select category —</option>';
    petCats.filter(c => !g || c.group === parseInt(g,10)).forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.name;
        sel.appendChild(o);
    });
}
</script>

<?php
render_layout('Petitions', 'petitions', ob_get_clean());
