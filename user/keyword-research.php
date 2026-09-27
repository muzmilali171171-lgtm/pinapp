<?php
/**
 * User → Analytics → Keyword Research.
 * Trending Pinterest keywords from the official Pinterest Trends API (user/ajax-kw-research.php),
 * with a "Create pin" popup (the shared Regenerate popup in keyword mode) that publishes or
 * schedules into Scheduled Pins with the same AI settings and credits as the Bulk Pin Scheduler.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_functions.php';
require_once __DIR__ . '/../includes/platform_functions.php';
require_once __DIR__ . '/../includes/pricing_functions.php';
require_once __DIR__ . '/../includes/team_functions.php';
require_once __DIR__ . '/../includes/pinterest_analytics_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user($pdo);
$activePage = 'keyword-research';
$pageTitle = 'Pinterest Keyword Research';

$accounts = pa_user_accounts($pdo, (int)$user['id']);
$connected = array_values(array_filter($accounts, fn($a) => $a['status'] === 'connected'));
$accountId = (int)($_GET['account'] ?? ($_SESSION['pa_account_id'] ?? 0));
$account = null;
foreach ($connected as $a) {
    if ((int)$a['id'] === $accountId) $account = $a;
}
if (!$account && $connected) $account = $connected[0];
if ($account) $_SESSION['pa_account_id'] = (int)$account['id'];

$pricing = credit_pricing_get($pdo);
$articleSettings = get_article_settings($pdo);
$hasPinAi = $articleSettings && (!empty($articleSettings['pin_text_provider']) || !empty($articleSettings['text_provider']));
$usesOwnTextKey = (bool)get_user_ai_credentials($pdo, (int)$user['id'], 'text');

$paConfig = [
    'accountId' => $account ? (int)$account['id'] : 0,
    'accountName' => $account ? ($account['pinterest_username'] ?: 'Account #' . $account['id']) : '',
    'tab' => 'keyword-research',
    'csrf' => csrf_token(),
    'textCost' => $usesOwnTextKey ? 0 : (float)$pricing['text_credit_per_call'],
    'qualityCosts' => [
        'budget' => (float)$pricing['image_quality_low'],
        'high' => (float)$pricing['image_quality_medium'],
        'ultra' => (float)$pricing['image_quality_high'],
    ],
    'imageCredits' => get_user_image_credits($pdo, (int)$user['id']),
    'textCredits' => get_user_text_credits($pdo, (int)$user['id']),
    'hasPinAi' => (bool)$hasPinAi,
    'draftCount' => $account ? pa_drafts_count($pdo, (int)$user['id'], (int)$account['id']) : 0,
];

include __DIR__ . '/includes/user-header.php';
?>
<link rel="stylesheet" href="../assets/css/pinterest-analytics.css?v=<?= @filemtime(__DIR__ . '/../assets/css/pinterest-analytics.css') ?: time() ?>">

<div class="pa-head">
    <div>
        <h1>Pinterest Keyword Research</h1>
        <p>Discover trending Pinterest keywords, then turn them into AI pins for your pages.</p>
    </div>
    <?php if ($connected): ?>
    <form method="GET" class="pa-account-picker">
        <label for="paAccount">Pinterest account</label>
        <select name="account" id="paAccount" onchange="this.form.submit()">
            <?php foreach ($connected as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $account && (int)$a['id'] === (int)$account['id'] ? 'selected' : '' ?>>
                    <?= e($a['pinterest_username'] ? '@' . $a['pinterest_username'] : 'Account #' . $a['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if (!$connected): ?>
    <div class="card">
        <div class="empty-state">
            <p style="font-size:16px; margin-top:0;">Connect a Pinterest account to research keywords — Pinterest's Trends API is used through your connected account.</p>
            <a href="connect-pinterest" class="btn-primary">Go to Pinterest Accounts</a>
        </div>
    </div>
<?php else: ?>

<section class="pa-chart-card">
    <div class="pa-filter-label" style="margin-top:0;">Explore Pinterest keywords</div>
    <div class="kw-searchrow">
        <input type="search" id="kwQuery" placeholder="🔍 Search Pinterest trends, e.g. fall nails — leave empty for the top trends" aria-label="Keyword">
        <button type="button" class="btn-primary" id="kwSearch">Search keywords</button>
    </div>
    <div class="kw-filters">
        <div class="form-row">
            <label for="kwRegion">Region</label>
            <select id="kwRegion"><?php foreach (PA_KW_REGIONS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-row">
            <label for="kwType">Trend type</label>
            <select id="kwType"><?php foreach (PA_KW_TYPES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-row">
            <label for="kwInterest">Category</label>
            <select id="kwInterest"><option value="">All categories</option><?php foreach (PA_KW_INTERESTS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="kw-filters kw-growth">
        <div class="form-row">
            <label for="kwGMetric">Growth period</label>
            <select id="kwGMetric"><option value="mom" selected>Month over month</option><option value="wow">Week over week</option><option value="yoy">Year over year</option></select>
        </div>
        <div class="form-row">
            <label for="kwGMin">Growth % · min</label>
            <input type="number" id="kwGMin" placeholder="No minimum">
        </div>
        <div class="form-row">
            <label for="kwGMax">Growth % · max</label>
            <input type="number" id="kwGMax" placeholder="No maximum">
        </div>
        <div class="form-row" style="align-self:flex-end;"><button type="button" class="btn-secondary" id="kwApply">Apply</button></div>
    </div>
    <p class="muted" style="font-size:13px; margin:10px 0 0;">Pinterest's API doesn't share exact search counts ("58m people searched"). Popularity here is Pinterest's own trend ranking, growth in searches, and each keyword's interest over the past year.</p>
</section>

<section class="pa-chart-card">
    <div class="pa-selbar">
        <select id="kwSelectTop" aria-label="Select keywords">
            <option value="">Keyword Select ▾</option>
            <option value="10">Top 10</option>
            <option value="50">Top 50</option>
            <option value="100">Top 100</option>
            <option value="all">All loaded</option>
            <option value="none">Clear selection</option>
        </select>
        <button type="button" class="btn-primary btn-small" id="kwCreate" disabled>✨ Create pins (<span id="kwSelN">0</span>)</button>
        <button type="button" class="btn-secondary btn-small" id="kwExport">⬇ Export CSV</button>
        <button type="button" class="btn-secondary btn-small" id="kwRefresh" title="Fetch fresh data from Pinterest">↻ Refresh</button>
        <input type="url" id="kwLink" class="kw-link" placeholder="Default page link for new pins (optional)" aria-label="Default page link for new pins">
        <span class="muted" id="kwSelLabel" style="margin-left:auto;">None selected</span>
    </div>
    <div class="muted" id="kwCount" style="font-size:13px; margin-bottom:8px;"></div>
    <div id="kwTable"><div class="pa-loading"><span class="pa-spinner"></span> Loading…</div></div>
    <button type="button" class="btn-secondary pa-more" id="kwMore" hidden>Load more</button>
</section>

<?php include __DIR__ . '/includes/pa-regen-modal.php'; ?>

<script>window.PA_CONFIG = <?= json_encode($paConfig, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/pinterest-analytics.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pinterest-analytics.js') ?: time() ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
