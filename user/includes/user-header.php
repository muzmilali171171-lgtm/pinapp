<?php
// Expects $user (from current_user()) and $activePage (string) to be set by the including page.
require_once __DIR__ . '/../../includes/platform_functions.php';
require_once __DIR__ . '/../../includes/pricing_functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e(SITE_BRAND) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
<script>
// Applied before paint so there's no flash of the wrong theme.
(function() {
    try {
        var t = localStorage.getItem('vcTheme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) {}
})();
</script>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <button type="button" class="hamburger-btn" id="mobileMenuBtn" aria-label="Open menu" aria-controls="mainSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
        <a href="dashboard" class="topbar-logo" title="Dashboard">📍</a>
        <a href="dashboard" class="topbar-brand" title="Dashboard">Automated<span>Pin</span></a>
        <button type="button" class="topbar-icon-btn" id="sidebarToggleBtn" title="Toggle sidebar">⇤</button>
    </div>
    <div class="topbar-right">
        <a href="upgrade" class="btn-upgrade">✨ Upgrade Now</a>
        <button type="button" class="topbar-icon-btn" id="notifBellBtn" title="Notifications">🔔<?php if (isset($user['id']) && count_unread_notifications($pdo, (int)$user['id']) > 0): ?><span class="notif-badge"><?= count_unread_notifications($pdo, (int)$user['id']) ?></span><?php endif; ?></button>
        <a href="../tutorials" target="_blank" rel="noopener" class="btn-secondary btn-small">Tutorials</a>
        <button type="button" class="topbar-icon-btn" id="themeToggleBtn" title="Toggle dark mode">🌙</button>
        <a href="settings" class="topbar-avatar" title="Account">
            <?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?>
        </a>
    </div>
</div>
<div id="notifDropdown" class="notif-dropdown" style="display:none;">
    <?php $notifs = isset($user['id']) ? get_user_notifications($pdo, (int)$user['id'], 15) : []; ?>
    <?php if (empty($notifs)): ?>
        No new notifications yet.
    <?php else: ?>
        <?php foreach ($notifs as $n): ?>
            <a href="<?= e($n['link'] ? clean_php_url($n['link']) : '#') ?>" class="notif-item <?= $n['is_read'] ? '' : 'notif-unread' ?>">
                <strong><?= e($n['title']) ?></strong>
                <?php if ($n['message']): ?><div class="muted" style="font-size:12px;"><?= e($n['message']) ?></div><?php endif; ?>
                <div class="muted" style="font-size:11px;"><?= e(format_datetime($n['created_at'])) ?></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="sidebar user-sidebar" id="mainSidebar">
        <div class="brand">Automated<span>Pin</span><button type="button" class="sidebar-close" id="sidebarCloseBtn" aria-label="Close menu">&times;</button></div>
        <?php
        // Sidebar, grouped by what the user is doing. Each group: [id, label, active pages, items[href, page, label]]
        $cwDraftCount = 0;
        try { $cwDc = $pdo->prepare("SELECT COUNT(*) FROM cw_projects WHERE user_id = ? AND status = 'draft'"); $cwDc->execute([(int)($user['id'] ?? 0)]); $cwDraftCount = (int)$cwDc->fetchColumn(); } catch (Throwable $e) {}
        $navSections = [
            ['label' => null, 'entries' => [
                ['link', 'dashboard', 'dashboard', '🏠 Dashboard'],
            ]],
            ['label' => 'Pin Scheduling', 'entries' => [
                ['link', 'schedule-create', 'create', 'Schedule Single Pin'],
                ['group', 'bulkSchedGroup', 'Bulk Scheduler', [['bulk-schedule', 'bulk-schedule', 'Bulk Scheduler'], ['batches', 'batches', 'Scheduled Pin Batches']]],
                ['link', 'schedule-list', 'list', 'Scheduled Pins'],
                ['link', 'connect-pinterest', 'connect', 'Pinterest Accounts'],
            ]],
            ['label' => 'Automation', 'entries' => [
                ['group', 'autoWebsiteSubmenu', 'Auto Website to Daily Pin', [['auto-website-batches', 'auto-website-batches', 'Your Scheduled Websites'], ['website-pages', 'website-pages', 'Create New Schedule']]],
                ['group', 'classicWizardSubmenu', 'Classic Wizard', [['classic-wizard-batches', 'classic-wizard-batches', 'Your Scheduled Pins'], ['classic-wizard', 'classic-wizard', 'Create New Schedule'], ['classic-wizard-drafts', 'classic-wizard-drafts', 'Drafts' . ($cwDraftCount ? " ($cwDraftCount)" : '')]]],
                ['group', 'autoArticleSubmenu', 'Auto Article', [['auto-article-batches', 'auto-article-batches', 'Your Articles Batches'], ['auto-article-create', 'auto-article-create', 'Create New Articles Batch']]],
            ]],
            ['label' => 'Create', 'entries' => [
                ['group', 'customDesignSubmenu', '🎨 Custom Design', [['design-editor', 'design-editor', 'Create New Design'], ['designs', 'designs', 'Your Designs'], ['designs?tab=templates', '__none', 'Design Templates']]],
                ['group', 'writeArticleSubmenu', 'Write Single Article', [['write-article', 'write-article', 'Write Single Article'], ['articles', 'articles', 'My Created Articles']]],
                ['link', 'storage', 'storage', 'Storage'],
            ]],
            ['label' => 'Analytics', 'entries' => [
                ['group', 'analyticsSubmenu', 'Analytics', [['pinterest-analytics', 'pinterest-analytics', 'Pinterest Analytics'], ['template-tracking', 'template-tracking', 'Template Tracking'], ['keyword-research', 'keyword-research', 'Keyword Research']]],
            ]],
            ['label' => 'Websites', 'entries' => [
                ['group', 'addWebsitesSubmenu', 'Add Websites', [['websites', 'websites', 'All Websites'], ['website-wordpress', 'website-wordpress', 'WordPress'], ['shopify-stores', 'shopify-stores', 'Shopify'], ['wix-sites', 'wix-sites', 'Wix'], ['custom-websites', 'custom-websites', 'Custom Websites']]],
            ]],
            ['label' => 'Account', 'entries' => [
                ['link', 'settings', 'settings', 'Settings'],
                ['link', 'upgrade', 'upgrade', 'Upgrade Plan'],
                ['group', 'affiliateSubmenu', 'Affiliate', [['affiliate-dashboard', 'affiliate-dashboard', 'Dashboard'], ['affiliate-payouts', 'affiliate-payouts', 'Payouts']]],
            ]],
            ['label' => 'Help', 'entries' => [
                ['ext', '../tutorials', 'tutorials', 'Tutorials'],
                ['link', '../contact', 'support', 'Support'],
            ]],
        ];
        $pageAlias = ['shopify-items' => 'shopify-stores'];
        $curPage = $pageAlias[$activePage] ?? $activePage;
        foreach ($navSections as $sec):
            if ($sec['label']): ?><div class="sidebar-section-label"><?= e($sec['label']) ?></div><?php endif;
            foreach ($sec['entries'] as $en):
                if ($en[0] === 'link' || $en[0] === 'ext'): ?>
        <a href="<?= e($en[1]) ?>" class="<?= $curPage === $en[2] ? 'active' : '' ?>"<?= $en[0] === 'ext' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($en[3]) ?></a>
                <?php else:
                    $isOpen = in_array($curPage, array_column($en[3], 1), true); ?>
        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= $isOpen ? 'open' : '' ?>" data-nav-toggle="<?= e($en[1]) ?>">
                <?= e($en[2]) ?> <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= $isOpen ? 'open' : '' ?>" id="<?= e($en[1]) ?>">
                <?php foreach ($en[3] as [$href, $page, $label]): ?>
                    <a href="<?= e($href) ?>" class="<?= $curPage === $page ? 'active' : '' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
                <?php endif;
            endforeach;
        endforeach; ?>

        <a href="../auth/logout">Log out</a>
    </aside>
    <div class="main-content">
<?php
// "How to use" guide for the automation tools — top-right of the page, can be hidden (✕) per page.
$howToLinks = [
    'bulk-schedule'       => ['/pinterest-bulk-scheduler', 'Bulk Scheduler'],
    'website-pages'       => ['/ai-blog-to-pin-create-schedul', 'Auto Website to Daily Pin'],
    'auto-website-batches'=> ['/ai-blog-to-pin-create-schedul', 'Auto Website to Daily Pin'],
    'auto-article-create' => ['/ai-bulk-article-write-publish-pins-schedul', 'Auto Article'],
    'auto-article-batches'=> ['/ai-bulk-article-write-publish-pins-schedul', 'Auto Article'],
    'classic-wizard'      => ['/blog-to-pin', 'Classic Wizard'],
    'classic-wizard-batches' => ['/blog-to-pin', 'Classic Wizard'],
];
if (isset($howToLinks[$activePage])):
    [$howPath, $howName] = $howToLinks[$activePage];
    $howUrl = (defined('HOWTO_BASE_URL') ? rtrim(HOWTO_BASE_URL, '/') : rtrim(APP_URL, '/')) . $howPath;
    $howKey = 'howto_hidden_' . preg_replace('/[^a-z0-9]+/', '_', $howPath);
?>
<div class="howto-wrap" id="howtoWrap" data-key="<?= e($howKey) ?>">
    <a class="howto-chip" href="<?= e($howUrl) ?>" target="_blank" rel="noopener" title="How to use <?= e($howName) ?> — step-by-step guide (opens in a new tab)">📘 How to use <?= e($howName) ?></a><button type="button" class="howto-x" title="Hide this guide button" aria-label="Hide">✕</button>
    <a class="howto-mini" href="<?= e($howUrl) ?>" target="_blank" rel="noopener" title="How to use <?= e($howName) ?>" aria-label="How to use">?</a>
</div>
<style>
.howto-wrap { display:flex; justify-content:flex-end; align-items:center; gap:0; margin:-6px 0 10px; }
.howto-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid #ddd6fe; border-right:none; border-radius:999px 0 0 999px; background:#f5f3ff; color:#5b21b6; font-size:12.5px; font-weight:600; text-decoration:none; }
.howto-chip:hover { background:#ede9fe; }
.howto-x { border:1px solid #ddd6fe; background:#f5f3ff; color:#7c3aed; border-radius:0 999px 999px 0; padding:6px 10px 6px 8px; font-size:12px; cursor:pointer; line-height:1.2; }
.howto-x:hover { background:#ede9fe; }
.howto-mini { display:none; width:24px; height:24px; border-radius:50%; border:1px solid #ddd6fe; background:#fff; color:#7c3aed; font-weight:700; font-size:13px; text-decoration:none; align-items:center; justify-content:center; opacity:.7; }
.howto-mini:hover { opacity:1; }
.howto-wrap.is-hidden .howto-chip, .howto-wrap.is-hidden .howto-x { display:none; }
.howto-wrap.is-hidden .howto-mini { display:inline-flex; }
</style>
<script>
(function () {
    var w = document.getElementById('howtoWrap'); if (!w) return;
    var k = w.dataset.key;
    try { if (localStorage.getItem(k) === '1') w.classList.add('is-hidden'); } catch (e) {}
    w.querySelector('.howto-x').addEventListener('click', function () {
        w.classList.add('is-hidden');
        try { localStorage.setItem(k, '1'); } catch (e) {}
    });
})();
</script>
<?php endif; ?>
<script>
// Sidebar nav group collapse/expand — one shared handler for every group above.
document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var panel = document.getElementById(btn.dataset.navToggle);
        if (!panel) return;
        var open = !panel.classList.contains('open');
        panel.classList.toggle('open', open);
        btn.classList.toggle('open', open);
    });
});

// Sidebar collapse toggle.
(function() {
    var btn = document.getElementById('sidebarToggleBtn');
    var sidebar = document.getElementById('mainSidebar');
    if (!btn || !sidebar) return;
    try { if (localStorage.getItem('vcSidebarCollapsed') === '1') sidebar.classList.add('collapsed'); } catch (e) {}
    btn.addEventListener('click', function () {
        var collapsed = sidebar.classList.toggle('collapsed');
        try { localStorage.setItem('vcSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
    });
})();

// Mobile / tablet off-canvas sidebar (hamburger).
(function() {
    var sidebar = document.getElementById('mainSidebar');
    var openBtn = document.getElementById('mobileMenuBtn');
    var closeBtn = document.getElementById('sidebarCloseBtn');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !openBtn) return;
    function setOpen(open) {
        sidebar.classList.toggle('mobile-open', open);
        if (backdrop) backdrop.classList.toggle('show', open);
        document.body.classList.toggle('nav-lock', open);
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    openBtn.addEventListener('click', function () { setOpen(!sidebar.classList.contains('mobile-open')); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1024) setOpen(false); });
})();

// Dark/light theme toggle.
(function() {
    var btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    function setIcon() {
        btn.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
    }
    setIcon();
    btn.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        try { localStorage.setItem('vcTheme', isDark ? 'light' : 'dark'); } catch (e) {}
        setIcon();
    });
})();

// Notification bell — real notifications from the `notifications` table (renewal
// reminders, payment approvals, plan changes). Opening the dropdown marks them read.
(function() {
    var btn = document.getElementById('notifBellBtn');
    var dropdown = document.getElementById('notifDropdown');
    if (!btn || !dropdown) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var opening = dropdown.style.display === 'none';
        dropdown.style.display = opening ? 'block' : 'none';
        if (opening) {
            fetch('ajax-mark-notifications-read', {method: 'POST', keepalive: true}).catch(function () {});
            var badge = btn.querySelector('.notif-badge');
            if (badge) badge.remove();
        }
    });
    document.addEventListener('click', function () { dropdown.style.display = 'none'; });
})();

// Auto Article "poor man's cron": nudge the background article queue forward on every
// page load, throttled client-side too so it's not spammed. keepalive:true means this
// request keeps going to the server even if this tab closes or navigates away right
// after — combined with the server detaching itself, the queue advances without
// needing this tab (or any tab) to stay open.
(function() {
    try {
        var last = parseInt(localStorage.getItem('articleTickLast') || '0', 10);
        if (Date.now() - last > 120000) {
            localStorage.setItem('articleTickLast', String(Date.now()));
            fetch('ajax-tick', { method: 'POST', keepalive: true }).catch(function () {});
        }
    } catch (e) { /* localStorage unavailable — skip silently, not critical */ }
})();
</script>
