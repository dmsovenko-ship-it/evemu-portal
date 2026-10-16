<?php
// /rules — server rules.
require_once __DIR__ . '/../portal_rules.php';
require_once __DIR__ . '/../layout.php';

ob_start();
?>
<div style="max-width:920px;margin:0 auto">
    <h1>Правила сервера</h1>
    <p style="color:var(--text-dim);font-size:13px;margin-bottom:16px">
        Регистрируясь или играя на сервере, вы соглашаетесь с этими правилами.
    </p>
    <div class="rules-panel"><?= rules_html() ?></div>
</div>
<style>
    .rules-panel{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;
                 padding:22px 26px;line-height:1.6;font-size:13.5px}
    .rules-panel h2{font-size:18px;margin:0 0 6px}
    .rules-panel h3{font-size:15px;margin:20px 0 8px;color:var(--accent2)}
    .rules-panel h4{font-size:14px;margin:14px 0 6px}
    .rules-panel p{margin:8px 0}
    .rules-panel ul{margin:6px 0 10px 0;padding-left:22px}
    .rules-panel li{margin:5px 0}
    .rules-panel hr{border:0;border-top:1px solid var(--border);margin:18px 0}
    .rules-panel strong{color:#fff}
    .rules-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:12.5px}
    .rules-table th,.rules-table td{border:1px solid var(--border);padding:8px 10px;text-align:left;vertical-align:top}
    .rules-table th{background:rgba(255,255,255,.04);color:var(--accent2)}
</style>
<?php
render_layout('Rules', '', ob_get_clean());
