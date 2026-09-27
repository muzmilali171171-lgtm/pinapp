<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e(APP_NAME) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
</head>
<body>
<div class="admin-mobilebar">
    <button type="button" class="hamburger-btn" id="mobileMenuBtn" aria-label="Open menu" aria-controls="mainSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
    <a href="dashboard" class="topbar-brand">Admin <span>Panel</span></a>
</div>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="sidebar" id="mainSidebar">
        <div class="brand">Admin <span>Panel</span><button type="button" class="sidebar-close" id="sidebarCloseBtn" aria-label="Close menu">&times;</button></div>
        <a href="dashboard" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="pinterest-settings" class="<?= $activePage === 'settings' ? 'active' : '' ?>">Pinterest Settings</a>
        <a href="ai-api" class="<?= $activePage === 'ai-api' ? 'active' : '' ?>">Models</a>
        <a href="image-categories" class="<?= $activePage === 'image-categories' ? 'active' : '' ?>">Image Categories</a>
        <a href="page-crawler" class="<?= $activePage === 'page-crawler' ? 'active' : '' ?>">Page Crawler</a>
        <a href="storage-settings" class="<?= $activePage === 'storage-settings' ? 'active' : '' ?>">Storage Settings</a>
        <a href="article-settings" class="<?= $activePage === 'article-settings' ? 'active' : '' ?>">Article Write</a>
        <a href="seo-settings" class="<?= $activePage === 'seo-settings' ? 'active' : '' ?>">SEO Setting</a>
        <a href="footer-settings" class="<?= $activePage === 'footer-settings' ? 'active' : '' ?>">Footer Settings</a>
        <a href="tutorials" class="<?= $activePage === 'tutorials' ? 'active' : '' ?>">Tutorials</a>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['blog-post-create', 'blog-posts', 'blog-categories'], true) ? 'open' : '' ?>" id="blogToggle">
                Blog Post <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['blog-post-create', 'blog-posts', 'blog-categories'], true) ? 'open' : '' ?>" id="blogSubmenu">
                <a href="blog-post-create" class="<?= $activePage === 'blog-post-create' ? 'active' : '' ?>">Create New</a>
                <a href="blog-posts" class="<?= $activePage === 'blog-posts' ? 'active' : '' ?>">All Posts</a>
                <a href="blog-categories" class="<?= $activePage === 'blog-categories' ? 'active' : '' ?>">Category</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['contacts', 'contact-settings'], true) ? 'open' : '' ?>" id="contactsToggle">
                Contacts <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['contacts', 'contact-settings'], true) ? 'open' : '' ?>" id="contactsSubmenu">
                <a href="contacts" class="<?= $activePage === 'contacts' ? 'active' : '' ?>">All Submissions</a>
                <a href="contact-settings" class="<?= $activePage === 'contact-settings' ? 'active' : '' ?>">Settings</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['bulk-scheduler', 'auto-article-pin', 'auto-website-pin', 'classic-wizard-settings'], true) ? 'open' : '' ?>" id="aiFeaturesToggle">
                AI Setting By Features <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['bulk-scheduler', 'auto-article-pin', 'auto-website-pin', 'classic-wizard-settings'], true) ? 'open' : '' ?>" id="aiFeaturesSubmenu">
                <a href="bulk-scheduler" class="<?= $activePage === 'bulk-scheduler' ? 'active' : '' ?>">Bulk Pin Scheduler</a>
                <a href="auto-article-pin" class="<?= $activePage === 'auto-article-pin' ? 'active' : '' ?>">Auto Article Pin</a>
                <a href="auto-website-pin" class="<?= $activePage === 'auto-website-pin' ? 'active' : '' ?>">Auto Website to Daily Pin</a>
                <a href="classic-wizard-settings" class="<?= $activePage === 'classic-wizard-settings' ? 'active' : '' ?>">Classic Wizard</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['websites', 'website-settings'], true) ? 'open' : '' ?>" id="websitesToggle">
                All Websites <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['websites', 'website-settings'], true) ? 'open' : '' ?>" id="websitesSubmenu">
                <a href="websites" class="<?= $activePage === 'websites' ? 'active' : '' ?>">All Websites</a>
                <a href="website-settings" class="<?= $activePage === 'website-settings' ? 'active' : '' ?>">Settings</a>
            </div>
        </div>

        <a href="users" class="<?= $activePage === 'users' ? 'active' : '' ?>">Users</a>
        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['scheduler', 'all-pins'], true) ? 'open' : '' ?>" id="pinsSchedToggle">
                All Pins Scheduled <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['scheduler', 'all-pins'], true) ? 'open' : '' ?>" id="pinsSchedSubmenu">
                <a href="scheduler" class="<?= $activePage === 'scheduler' ? 'active' : '' ?>">Scheduler &amp; Cron</a>
                <a href="pins" class="<?= $activePage === 'all-pins' ? 'active' : '' ?>">All Pins</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['aad-ideas', 'aad-recipe'], true) ? 'open' : '' ?>" id="aadToggle">
                AI Article Data <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['aad-ideas', 'aad-recipe'], true) ? 'open' : '' ?>" id="aadSubmenu">
                <a href="ai-article-data?type=ideas" class="<?= $activePage === 'aad-ideas' ? 'active' : '' ?>">Ideas Articles</a>
                <a href="ai-article-data?type=recipe" class="<?= $activePage === 'aad-recipe' ? 'active' : '' ?>">Recipes &amp; Food Articles</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['articles-schedule', 'all-articles', 'article-batch-limits'], true) ? 'open' : '' ?>" id="artSchedToggle">
                Articles Schedule <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['articles-schedule', 'all-articles', 'article-batch-limits'], true) ? 'open' : '' ?>" id="artSchedSubmenu">
                <a href="articles-schedule" class="<?= $activePage === 'articles-schedule' ? 'active' : '' ?>">Queue &amp; Cron</a>
                <a href="articles" class="<?= $activePage === 'all-articles' ? 'active' : '' ?>">All Articles</a>
                <a href="article-batch-limits" class="<?= $activePage === 'article-batch-limits' ? 'active' : '' ?>">Batch Limits</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['free-tool-pin-create', 'free-tool-image-creator', 'free-tool-text-settings', 'free-tool-etsy-settings'], true) ? 'open' : '' ?>" id="freeToolsToggle">
                Free Tools <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['free-tool-pin-create', 'free-tool-image-creator', 'free-tool-text-settings', 'free-tool-etsy-settings'], true) ? 'open' : '' ?>" id="freeToolsSubmenu">
                <a href="classic-wizard-settings" class="<?= $activePage === 'classic-wizard-settings' ? 'active' : '' ?>">Pinterest Pin Maker</a>
                <a href="free-tool-pin-create-settings" class="<?= $activePage === 'free-tool-pin-create' ? 'active' : '' ?>">AI Pinterest Pin Create</a>
                <a href="free-tool-image-creator-settings" class="<?= $activePage === 'free-tool-image-creator' ? 'active' : '' ?>">AI Image Creator</a>
                <a href="free-tool-text-settings" class="<?= $activePage === 'free-tool-text-settings' ? 'active' : '' ?>">Pinterest Text Generators</a>
                <a href="free-tool-etsy-settings" class="<?= $activePage === 'free-tool-etsy-settings' ? 'active' : '' ?>">Etsy Tools</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= in_array($activePage, ['design-elements', 'user-designs'], true) ? 'open' : '' ?>" id="canvaToggle">
                🎨 Canva <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= in_array($activePage, ['design-elements', 'user-designs'], true) ? 'open' : '' ?>" id="canvaSubmenu">
                <a href="design-elements" class="<?= $activePage === 'design-elements' ? 'active' : '' ?>">Add Elements</a>
                <a href="user-designs" class="<?= $activePage === 'user-designs' ? 'active' : '' ?>">User Designs</a>
                <a href="user-designs?view=published">Published Templates</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= $activePage === 'plan-pricing' ? 'open' : '' ?>" id="planPricingToggle">
                Plan Pricing <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= $activePage === 'plan-pricing' ? 'open' : '' ?>" id="planPricingSubmenu">
                <a href="plan-create" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'plan-create' ? 'active' : '' ?>">Create Plan</a>
                <a href="plans" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'plans' ? 'active' : '' ?>">All Plans</a>
                <a href="payment-gateways" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'payment-gateways' ? 'active' : '' ?>">Payment Gateway Integration</a>
                <a href="coupons" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'coupons' ? 'active' : '' ?>">Coupons</a>
                <a href="contact-sales" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'contact-sales' ? 'active' : '' ?>">Contact Sales</a>
                <a href="plan-users" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'plan-users' ? 'active' : '' ?>">Users</a>
                <a href="plan-settings" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'plan-settings' ? 'active' : '' ?>">Setting</a>
            </div>
        </div>

        <div class="admin-nav-group">
            <button type="button" class="admin-nav-group-toggle <?= $activePage === 'affiliate' ? 'open' : '' ?>" id="affiliateToggle">
                Affiliate <span class="admin-nav-caret">▾</span>
            </button>
            <div class="admin-nav-submenu <?= $activePage === 'affiliate' ? 'open' : '' ?>" id="affiliateSubmenu">
                <a href="affiliate-dashboard" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'affiliate-dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="affiliate-settings" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'affiliate-settings' ? 'active' : '' ?>">Settings</a>
                <a href="affiliate-payouts" class="<?= basename($_SERVER['PHP_SELF'] ?? '') === 'affiliate-payouts' ? 'active' : '' ?>">Payouts</a>
            </div>
        </div>

        <a href="notifications" class="<?= $activePage === 'notifications' ? 'active' : '' ?>">Notifications</a>
        <a href="user-settings" class="<?= $activePage === 'user-settings' ? 'active' : '' ?>">User Setting</a>
        <a href="email-settings" class="<?= $activePage === 'email-settings' ? 'active' : '' ?>">Email Setting</a>
        <a href="logs" class="<?= $activePage === 'logs' ? 'active' : '' ?>">Logs</a>
        <a href="account-settings" class="<?= $activePage === 'account-settings' ? 'active' : '' ?>">Account Settings</a>
        <a href="logout">Log out</a>
    </aside>
    <div class="main-content">
<script>
(function() {
    [['pinsSchedToggle', 'pinsSchedSubmenu'], ['artSchedToggle', 'artSchedSubmenu'], ['aadToggle', 'aadSubmenu']].forEach(function (pair) {
        var tg = document.getElementById(pair[0]), sm = document.getElementById(pair[1]);
        if (tg && sm) tg.addEventListener('click', function () { tg.classList.toggle('open'); sm.classList.toggle('open'); });
    });
    var t = document.getElementById('aiFeaturesToggle');
    var s = document.getElementById('aiFeaturesSubmenu');
    if (t && s) {
        t.addEventListener('click', function() {
            var open = !s.classList.contains('open');
            s.classList.toggle('open', open);
            t.classList.toggle('open', open);
        });
    }
    var ct = document.getElementById('contactsToggle');
    var cs = document.getElementById('contactsSubmenu');
    if (ct && cs) {
        ct.addEventListener('click', function() {
            var open = !cs.classList.contains('open');
            cs.classList.toggle('open', open);
            ct.classList.toggle('open', open);
        });
    }
    var wt = document.getElementById('websitesToggle');
    var ws = document.getElementById('websitesSubmenu');
    if (wt && ws) {
        wt.addEventListener('click', function() {
            var open = !ws.classList.contains('open');
            ws.classList.toggle('open', open);
            wt.classList.toggle('open', open);
        });
    }
    var pt = document.getElementById('planPricingToggle');
    var ps = document.getElementById('planPricingSubmenu');
    if (pt && ps) {
        pt.addEventListener('click', function() {
            var open = !ps.classList.contains('open');
            ps.classList.toggle('open', open);
            pt.classList.toggle('open', open);
        });
    }
    var cvt = document.getElementById('canvaToggle');
    var cvs = document.getElementById('canvaSubmenu');
    if (cvt && cvs) {
        cvt.addEventListener('click', function() {
            var open = !cvs.classList.contains('open');
            cvs.classList.toggle('open', open);
            cvt.classList.toggle('open', open);
        });
    }
    var at = document.getElementById('affiliateToggle');
    var as = document.getElementById('affiliateSubmenu');
    if (at && as) {
        at.addEventListener('click', function() {
            var open = !as.classList.contains('open');
            as.classList.toggle('open', open);
            at.classList.toggle('open', open);
        });
    }
    var bt = document.getElementById('blogToggle');
    var bs = document.getElementById('blogSubmenu');
    if (bt && bs) {
        bt.addEventListener('click', function() {
            var open = !bs.classList.contains('open');
            bs.classList.toggle('open', open);
            bt.classList.toggle('open', open);
        });
    }
    var ft = document.getElementById('freeToolsToggle');
    var fs = document.getElementById('freeToolsSubmenu');
    if (ft && fs) {
        ft.addEventListener('click', function() {
            var open = !fs.classList.contains('open');
            fs.classList.toggle('open', open);
            ft.classList.toggle('open', open);
        });
    }
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
</script>
