<?php
require_once __DIR__ . '/../layout.php';

$query = trim($_GET['q'] ?? '');
$results = null;

if ($query !== '') {
    $results = api_get('/server/Search.xml.aspx?q=' . urlencode($query));
}

$characters = [];
$corporations = [];
$systems = [];

if ($results && $results->result) {
    if (!empty($results->result->characters))
        foreach ($results->result->characters->row as $r) $characters[] = $r;
    if (!empty($results->result->corporations))
        foreach ($results->result->corporations->row as $r) $corporations[] = $r;
    if (!empty($results->result->systems))
        foreach ($results->result->systems->row as $r) $systems[] = $r;
}

$totalResults = count($characters) + count($corporations) + count($systems);

ob_start();
?>

<div class="search-page">
    <div class="search-hero">
        <h1>Search</h1>
        <form action="/search" method="get" class="search-form-large">
            <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search characters, corporations, systems..." autofocus class="search-input-large">
            <button type="submit" class="search-btn-large">Search</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
    <div class="search-results-summary">
        <?= $totalResults ?> result<?= $totalResults !== 1 ? 's' : '' ?> for "<strong><?= e($query) ?></strong>"
    </div>

    <?php if (!empty($characters)): ?>
    <div class="search-section">
        <h2>Characters</h2>
        <div class="search-grid">
            <?php foreach ($characters as $c): ?>
            <a href="/character/<?= $c['characterid'] ?>" class="search-card">
                <img src="<?= char_portrait($c['characterid'], 64) ?>" width="64" height="64" class="search-card-img" onerror="this.style.display='none'">
                <div class="search-card-info">
                    <div class="search-card-name"><?= e($c['charactername']) ?></div>
                    <div class="search-card-meta"><?= e($c['corporationname'] ?? '') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($corporations)): ?>
    <div class="search-section">
        <h2>Corporations</h2>
        <div class="search-grid">
            <?php foreach ($corporations as $c): ?>
            <a href="/corporation/<?= $c['corporationid'] ?>" class="search-card">
                <img src="<?= corp_logo($c['corporationid'], 64) ?>" width="64" height="64" class="search-card-img" onerror="this.style.display='none'">
                <div class="search-card-info">
                    <div class="search-card-name"><?= e($c['corporationname']) ?></div>
                    <div class="search-card-meta"><?= e($c['ticker'] ?? '') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($systems)): ?>
    <div class="search-section">
        <h2>Systems</h2>
        <div class="search-grid">
            <?php foreach ($systems as $s): ?>
            <a href="/system/<?= $s['solarsystemid'] ?>" class="search-card search-card-system">
                <div class="search-card-info">
                    <div class="search-card-name">
                        <?php $sec = (float)($s['security'] ?? 0); ?>
                        <span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span>
                        <?= e($s['solarsystemname']) ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($totalResults === 0): ?>
    <div class="search-empty">
        No results found for "<strong><?= e($query) ?></strong>"
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
render_layout('Search', 'search', $content);
