<?php
/**
 * User → Analytics → Pinterest Analytics.
 * Tabs: Analytics, Top Pins (built), Trends / Breakdowns / Delete Underperforming Pins (coming next).
 * All data is loaded by assets/js/pinterest-analytics.js from the user/ajax-pa-*.php endpoints.
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
$activePage = 'pinterest-analytics';
$pageTitle = 'Pinterest Analytics';

$tabs = [
    'analytics' => ['Analytics', true],
    'top-pins' => ['Top Pins', true],
    'trends' => ['Trends', true],
    'breakdowns' => ['Breakdowns', true],
    'delete-underperforming' => ['Delete Underperforming Pins', true],
    'regen-drafts' => ['Regen Draft', true],
];
$tab = $_GET['tab'] ?? 'analytics';
if (!isset($tabs[$tab])) $tab = 'analytics';

$accounts = pa_user_accounts($pdo, (int)$user['id']);
$connected = array_values(array_filter($accounts, fn($a) => $a['status'] === 'connected'));
$accountId = (int)($_GET['account'] ?? ($_SESSION['pa_account_id'] ?? 0));
$account = null;
foreach ($connected as $a) {
    if ((int)$a['id'] === $accountId) $account = $a;
}
if (!$account && $connected) $account = $connected[0];
if ($account) $_SESSION['pa_account_id'] = (int)$account['id'];
$draftCount = $account ? pa_drafts_count($pdo, (int)$user['id'], (int)$account['id']) : 0;
$deleteQueueCount = $account ? pa_delete_queue_count($pdo, (int)$account['id']) : 0;

$pricing = credit_pricing_get($pdo);
$articleSettings = get_article_settings($pdo);
$hasPinAi = $articleSettings && (!empty($articleSettings['pin_text_provider']) || !empty($articleSettings['text_provider']));
$usesOwnTextKey = (bool)get_user_ai_credentials($pdo, (int)$user['id'], 'text');

$paConfig = [
    'accountId' => $account ? (int)$account['id'] : 0,
    'accountName' => $account ? ($account['pinterest_username'] ?: 'Account #' . $account['id']) : '',
    'tab' => $tab,
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
    'draftCount' => $draftCount,
    'deleteQueueCount' => $deleteQueueCount,
    'defaultWebsite' => '',
];

$tabUrl = function (string $t) use ($account) {
    return 'pinterest-analytics?tab=' . urlencode($t) . ($account ? '&account=' . (int)$account['id'] : '');
};

include __DIR__ . '/includes/user-header.php';
?>
<link rel="stylesheet" href="../assets/css/pinterest-analytics.css?v=<?= @filemtime(__DIR__ . '/../assets/css/pinterest-analytics.css') ?: time() ?>">

<div class="pa-head">
    <div>
        <h1>Pinterest Analytics</h1>
        <p>See how your pins perform and turn your winners into fresh pins.</p>
    </div>
    <?php if ($connected): ?>
    <form method="GET" class="pa-account-picker">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
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
            <p style="font-size:16px; margin-top:0;">Connect a Pinterest account to see its analytics.</p>
            <?php if ($accounts): ?><p class="muted">Your connected account needs to be reconnected before analytics can load.</p><?php endif; ?>
            <a href="connect-pinterest" class="btn-primary">Go to Pinterest Accounts</a>
        </div>
    </div>
<?php else: ?>

<nav class="pa-tabs" aria-label="Pinterest analytics sections">
    <?php foreach ($tabs as $key => [$label, $built]): ?>
        <a href="<?= e($tabUrl($key)) ?>" class="pa-tab <?= $tab === $key ? 'active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
            <?= e($label) ?><?php if (!$built): ?><span class="pa-soon">Soon</span><?php endif; ?><?php if ($key === 'regen-drafts'): ?><span class="pa-tab-count" id="paDraftTabCount"<?= $draftCount ? '' : ' data-zero' ?>><?= $draftCount ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'analytics'): ?>
    <div class="pa-stats" id="paStats">
        <div class="pa-stat" style="--pa-accent:#f43f5e;"><div class="pa-stat-label">Impressions</div><div class="pa-stat-value" data-stat="impressions">—</div><div class="pa-stat-change" data-change="impressions"></div></div>
        <div class="pa-stat" style="--pa-accent:#8b5cf6;"><div class="pa-stat-label">Outbound Clicks</div><div class="pa-stat-value" data-stat="outbound_clicks">—</div><div class="pa-stat-change" data-change="outbound_clicks"></div></div>
        <div class="pa-stat" style="--pa-accent:#3b82f6;"><div class="pa-stat-label">Saves</div><div class="pa-stat-value" data-stat="saves">—</div><div class="pa-stat-change" data-change="saves"></div></div>
        <div class="pa-stat" style="--pa-accent:#10b981;"><div class="pa-stat-label">Click-through Rate</div><div class="pa-stat-value" data-stat="outbound_click_rate">—</div><div class="pa-stat-change" data-change="outbound_click_rate"></div></div>
    </div>

    <section class="pa-chart-card">
        <div class="pa-chart-top">
            <div>
                <h2 id="paChartTitle">Impressions, Pin Clicks over time</h2>
                <p>View how your impressions, pin clicks have changed over time</p>
            </div>
            <label class="pa-switch">Show previous period <input type="checkbox" id="paShowPrev" checked><span class="pa-switch-track"></span></label>
        </div>

        <div class="pa-chips" id="paMetricChips" role="group" aria-label="Metrics shown on the graph"></div>

        <div class="pa-ranges" role="group" aria-label="Date range">
            <button type="button" class="pa-range" data-range="7">7 days</button>
            <button type="button" class="pa-range" data-range="14">14 days</button>
            <button type="button" class="pa-range on" data-range="30">30 days</button>
            <button type="button" class="pa-range" data-range="90">90 days</button>
            <button type="button" class="pa-range" data-range="custom">📅 Custom</button>
        </div>
        <div class="pa-custom" id="paCustom">
            <input type="date" id="paStart" max="<?= date('Y-m-d') ?>" aria-label="Start date">
            <span class="muted">to</span>
            <input type="date" id="paEnd" max="<?= date('Y-m-d') ?>" aria-label="End date">
            <button type="button" class="btn-primary btn-small" id="paCustomApply">Apply</button>
        </div>

        <div class="pa-chart" id="paMainChart"><div class="pa-loading"><span class="pa-spinner"></span> Loading your analytics…</div></div>

        <div class="pa-note">ⓘ <span>Pinterest data for the last 2-3 days might be incomplete as it's still processing. All data shown is at daily granularity.<span id="paSyncedAt"></span></span></div>
    </section>

<?php elseif ($tab === 'top-pins'): ?>
    <section class="pa-chart-card" style="padding-bottom:12px;">
        <div class="pa-top-head">
            <div>
                <h2>Top 200 performing pins (last 90 days)</h2>
                <div class="pa-sync-meta" id="paSyncMeta">&nbsp;</div>
            </div>
            <div class="pa-top-tools">
                <button type="button" class="btn-secondary btn-small" id="paResync">↻ Resync</button>
                <a href="#" class="btn-secondary btn-small" id="paExport">⬇ Export CSV</a>
                <label class="pa-switch">Show only your pins <input type="checkbox" id="paOwnOnly"><span class="pa-switch-track"></span></label>
                <select id="paSort" aria-label="Sort pins by">
                    <option value="impressions" selected>⇅ Impressions</option>
                    <option value="clicks">⇅ Pin Clicks</option>
                    <option value="outbound">⇅ Outbound Clicks</option>
                    <option value="saves">⇅ Saves</option>
                    <option value="ctr">⇅ Click-through Rate</option>
                </select>
            </div>
        </div>

        <div class="pa-selbar">
            <label class="pa-check"><input type="checkbox" id="paSelectAll"> Select pins</label>
            <select id="paSelectTop" aria-label="Select top pins">
                <option value="">Select top ▾</option>
                <option value="all">All</option>
                <option value="5">Top 5</option>
                <option value="10">Top 10</option>
                <option value="50">Top 50</option>
                <option value="100">Top 100</option>
                <option value="200">Top 200</option>
                <option value="none">Clear selection</option>
            </select>
            <button type="button" class="btn-secondary btn-small" id="paBulkRegen" disabled>Regenerate Similar Pins (<span id="paSelCount">0</span>)</button>
            <label class="pa-switch" style="margin-left:auto;"><span>Regenerated only (<span id="paRegenCount">0</span>)</span><input type="checkbox" id="paRegenOnly"><span class="pa-switch-track"></span></label>
        </div>

        <div id="paPinList"><div class="pa-loading"><span class="pa-spinner"></span> Loading your top pins…</div></div>
    </section>

<?php else: ?>
    <?php include __DIR__ . '/includes/pa-tab-' . $tab . '.php'; ?>
<?php endif; ?>


<?php if (in_array($tab, ['top-pins', 'trends', 'breakdowns', 'regen-drafts'], true)) include __DIR__ . '/includes/pa-regen-modal.php'; ?>

<script>window.PA_CONFIG = <?= json_encode($paConfig, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/pinterest-analytics.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pinterest-analytics.js') ?: time() ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
