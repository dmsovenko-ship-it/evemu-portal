<?php
// /rules — server rules.
require_once __DIR__ . '/../portal_rules.php';
require_once __DIR__ . '/../layout.php';

ob_start();
?>
<div style="max-width:900px;margin:0 auto">
    <h1>Правила сервера</h1>
    <p style="color:var(--text-dim);font-size:13px">
        Регистрируясь или играя на сервере, вы соглашаетесь с этими правилами.
    </p>
    <div class="rules-panel"><?= e(server_rules_text()) ?></div>
</div>
<style>
    .rules-panel{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;
                 padding:18px 22px;white-space:pre-wrap;line-height:1.55;font-size:13.5px}
</style>
<?php
render_layout('Rules', '', ob_get_clean());
