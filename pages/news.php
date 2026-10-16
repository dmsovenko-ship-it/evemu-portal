<?php
// /news - public server news (the single source is serverNews; authored in /admin/news).
require_once __DIR__ . '/../layout.php';

$news = [];
$xml = api_get('/server/NewsFeed.xml.aspx?limit=50', 8);
if ($xml && isset($xml->result) && isset($xml->result->rows)) {
    foreach ($xml->result->rows->row as $r) {
        $news[] = array(
            'id'     => (int)$r['id'],
            'title'  => (string)$r['title'],
            'body'   => (string)$r['body'],
            'author' => (string)$r['author'],
            'date'   => (string)$r['date'],
        );
    }
}

ob_start();
?>
<div style="max-width:860px;margin:0 auto">
    <h1>Server news</h1>
    <p style="color:var(--text-dim);font-size:13px;margin-bottom:18px">
        Posted by the server team. The same items appear on the station news ticker,
        the station billboards, the launcher and Telegram.
    </p>

    <?php if (empty($news)): ?>
        <p style="color:var(--text-dim);padding:30px 0;text-align:center">No news yet.</p>
    <?php else: ?>
        <?php foreach ($news as $n): ?>
        <article class="news-item">
            <h2><?= e($n['title']) ?></h2>
            <div class="news-meta">
                <?= e(date('d.m.Y H:i', (strtotime($n['date']) ?: time()))) ?>
                <?php if ($n['author'] !== ''): ?>&middot; <?= e($n['author']) ?><?php endif; ?>
            </div>
            <div class="news-body"><?= news_body_html($n['body']) ?></div>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<style>
    .news-item{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;
               padding:16px 20px;margin-bottom:14px}
    .news-item h2{font-size:16px;margin:0 0 4px}
    .news-meta{color:var(--text-dim);font-size:12px;margin-bottom:10px}
    .news-body{font-size:13.5px;line-height:1.55;white-space:normal}
</style>
<?php
render_layout('News', 'news', ob_get_clean());
