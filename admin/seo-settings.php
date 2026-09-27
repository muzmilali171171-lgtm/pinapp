<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seo_functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'seo-settings';
$pageTitle = 'SEO Setting';
$errors = [];
$tab = $_GET['tab'] ?? 'general';

/** Make sure a settings row exists so every UPDATE below has a target. */
function seo_ensure_row(PDO $pdo): int
{
    $id = $pdo->query("SELECT id FROM seo_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($id) return (int)$id;
    $pdo->exec("INSERT INTO seo_settings (meta_title) VALUES (NULL)");
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rowId = seo_ensure_row($pdo);

    // ---------------------------------------------------------------- general
    if ($action === 'save_general') {
        $favicon = seo_handle_upload('favicon_file', $errors);
        $logo    = seo_handle_upload('logo_file', $errors);
        $ogImage = seo_handle_upload('og_image_file', $errors);

        // Normalise a comma-separated keyword list (trim, drop blanks, de-dupe).
        $keywords = array_filter(array_map('trim', explode(',', $_POST['meta_keywords'] ?? '')));
        $keywords = implode(', ', array_unique($keywords));

        $fields = [
            'meta_title' => mb_substr(trim($_POST['meta_title'] ?? ''), 0, 255),
            'meta_description' => mb_substr(trim($_POST['meta_description'] ?? ''), 0, 500),
            'meta_keywords' => mb_substr($keywords, 0, 500),
            'canonical_url' => trim($_POST['canonical_url'] ?? ''),
            'extra_head_code' => trim($_POST['extra_head_code'] ?? ''),
        ];
        if ($favicon) $fields['favicon_path'] = $favicon;
        if ($logo)    $fields['logo_path'] = $logo;
        if ($ogImage) $fields['og_image_path'] = $ogImage;

        // Allow clearing an image without uploading a replacement.
        foreach (['favicon' => 'favicon_path', 'logo' => 'logo_path', 'og_image' => 'og_image_path'] as $k => $col) {
            if (!empty($_POST['remove_' . $k])) $fields[$col] = '';
        }

        if (!$errors) {
            $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
            $pdo->prepare("UPDATE seo_settings SET $set WHERE id = ?")
                ->execute([...array_values($fields), $rowId]);
            log_event($pdo, 'system', 'Admin updated SEO meta settings');
            redirect('seo-settings?tab=general&saved=1');
        }
        $tab = 'general';

    // --------------------------------------------------------------- indexing
    } elseif ($action === 'save_indexing') {
        $pdo->prepare("UPDATE seo_settings SET robots_index = ?, robots_follow = ?, robots_txt = ? WHERE id = ?")
            ->execute([
                isset($_POST['robots_index']) ? 1 : 0,
                isset($_POST['robots_follow']) ? 1 : 0,
                trim($_POST['robots_txt'] ?? ''),
                $rowId,
            ]);
        log_event($pdo, 'system', 'Admin updated site indexing settings (index=' . (isset($_POST['robots_index']) ? 'on' : 'off') . ')');
        seo_write_robots_file($pdo);
        redirect('seo-settings?tab=indexing&saved=1');

    // ----------------------------------------------------------- app schema
    } elseif ($action === 'save_schema') {
        $screenshot = seo_handle_upload('screenshot_file', $errors);
        $pubLogo = seo_handle_upload('publisher_logo_file', $errors);

        $fields = [
            'schema_enabled' => isset($_POST['schema_enabled']) ? 1 : 0,
            'app_type' => in_array($_POST['app_type'] ?? '', ['WebApplication', 'SoftwareApplication', 'MobileApplication'], true) ? $_POST['app_type'] : 'WebApplication',
            'app_name' => trim($_POST['app_name'] ?? ''),
            'app_url' => trim($_POST['app_url'] ?? ''),
            'app_category' => trim($_POST['app_category'] ?? ''),
            'app_operating_system' => trim($_POST['app_operating_system'] ?? ''),
            'app_browser_requirements' => trim($_POST['app_browser_requirements'] ?? ''),
            'app_description' => trim($_POST['app_description'] ?? ''),
            'rating_enabled' => isset($_POST['rating_enabled']) ? 1 : 0,
            'rating_value' => (float)($_POST['rating_value'] ?? 0),
            'rating_count' => (int)($_POST['rating_count'] ?? 0),
            'rating_best' => (float)($_POST['rating_best'] ?? 5),
            'rating_worst' => (float)($_POST['rating_worst'] ?? 1),
            'publisher_enabled' => isset($_POST['publisher_enabled']) ? 1 : 0,
            'publisher_name' => trim($_POST['publisher_name'] ?? ''),
            'publisher_url' => trim($_POST['publisher_url'] ?? ''),
            'publisher_logo_width' => (int)($_POST['publisher_logo_width'] ?? 512),
            'publisher_logo_height' => (int)($_POST['publisher_logo_height'] ?? 512),
            'offers_enabled' => isset($_POST['offers_enabled']) ? 1 : 0,
            'offers_currency' => strtoupper(trim($_POST['offers_currency'] ?? 'USD')),
            'reviews_enabled' => isset($_POST['reviews_enabled']) ? 1 : 0,
        ];
        $fields['app_screenshot'] = $screenshot ?: trim($_POST['app_screenshot_url'] ?? '');
        $fields['publisher_logo_url'] = $pubLogo ?: trim($_POST['publisher_logo_url_text'] ?? '');

        if (!$errors) {
            $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($fields)));
            $pdo->prepare("UPDATE seo_settings SET $set WHERE id = ?")
                ->execute([...array_values($fields), $rowId]);
            log_event($pdo, 'system', 'Admin updated Software App schema markup');
            redirect('seo-settings?tab=schema&saved=1');
        }
        $tab = 'schema';

    // --------------------------------------------------------------- offers
    } elseif ($action === 'save_offer') {
        $id = (int)($_POST['offer_id'] ?? 0);
        $data = [
            trim($_POST['name'] ?? ''),
            (float)($_POST['price'] ?? 0),
            strtoupper(trim($_POST['price_currency'] ?? 'USD')),
            trim($_POST['url'] ?? ''),
            trim($_POST['availability'] ?? 'https://schema.org/InStock'),
            trim($_POST['price_valid_until'] ?? '') ?: null,
            (int)($_POST['quantity_value'] ?? 1),
            trim($_POST['unit_code'] ?? 'MON'),
            trim($_POST['description'] ?? ''),
            (int)($_POST['sort_order'] ?? 0),
            ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled',
        ];
        if ($data[0] === '') {
            $errors[] = 'Offer name is required.';
        } elseif ($id > 0) {
            $pdo->prepare("UPDATE seo_offers SET name=?, price=?, price_currency=?, url=?, availability=?,
                price_valid_until=?, quantity_value=?, unit_code=?, description=?, sort_order=?, status=? WHERE id=?")
                ->execute([...$data, $id]);
            redirect('seo-settings?tab=offers&saved=1');
        } else {
            $pdo->prepare("INSERT INTO seo_offers (name, price, price_currency, url, availability,
                price_valid_until, quantity_value, unit_code, description, sort_order, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($data);
            redirect('seo-settings?tab=offers&saved=1');
        }
        $tab = 'offers';

    } elseif ($action === 'delete_offer') {
        $pdo->prepare("DELETE FROM seo_offers WHERE id = ?")->execute([(int)($_POST['offer_id'] ?? 0)]);
        redirect('seo-settings?tab=offers');

    } elseif ($action === 'toggle_offer') {
        $id = (int)($_POST['offer_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT status FROM seo_offers WHERE id = ?");
        $stmt->execute([$id]);
        $cur = $stmt->fetchColumn();
        $pdo->prepare("UPDATE seo_offers SET status = ? WHERE id = ?")
            ->execute([$cur === 'active' ? 'disabled' : 'active', $id]);
        redirect('seo-settings?tab=offers');

    // -------------------------------------------------------------- reviews
    } elseif ($action === 'save_review') {
        $id = (int)($_POST['review_id'] ?? 0);
        $data = [
            trim($_POST['author_name'] ?? ''),
            ($_POST['author_type'] ?? 'Person') === 'Organization' ? 'Organization' : 'Person',
            (float)($_POST['rating_value'] ?? 5),
            (float)($_POST['best_rating'] ?? 5),
            (float)($_POST['worst_rating'] ?? 1),
            trim($_POST['review_body'] ?? ''),
            trim($_POST['item_name'] ?? ''),
            trim($_POST['item_url'] ?? ''),
            trim($_POST['item_operating_system'] ?? 'All'),
            trim($_POST['item_application_category'] ?? 'DesignApplication'),
            (int)($_POST['sort_order'] ?? 0),
            ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled',
        ];
        if ($data[0] === '' || $data[5] === '') {
            $errors[] = 'Reviewer name and review text are both required.';
        } elseif ($id > 0) {
            $pdo->prepare("UPDATE seo_reviews SET author_name=?, author_type=?, rating_value=?, best_rating=?,
                worst_rating=?, review_body=?, item_name=?, item_url=?, item_operating_system=?,
                item_application_category=?, sort_order=?, status=? WHERE id=?")->execute([...$data, $id]);
            redirect('seo-settings?tab=reviews&saved=1');
        } else {
            $pdo->prepare("INSERT INTO seo_reviews (author_name, author_type, rating_value, best_rating,
                worst_rating, review_body, item_name, item_url, item_operating_system,
                item_application_category, sort_order, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($data);
            redirect('seo-settings?tab=reviews&saved=1');
        }
        $tab = 'reviews';

    } elseif ($action === 'delete_review') {
        $pdo->prepare("DELETE FROM seo_reviews WHERE id = ?")->execute([(int)($_POST['review_id'] ?? 0)]);
        redirect('seo-settings?tab=reviews');

    } elseif ($action === 'toggle_review') {
        $id = (int)($_POST['review_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT status FROM seo_reviews WHERE id = ?");
        $stmt->execute([$id]);
        $cur = $stmt->fetchColumn();
        $pdo->prepare("UPDATE seo_reviews SET status = ? WHERE id = ?")
            ->execute([$cur === 'active' ? 'disabled' : 'active', $id]);
        redirect('seo-settings?tab=reviews');
    }
}

seo_ensure_row($pdo);
$s = $pdo->query("SELECT * FROM seo_settings ORDER BY id ASC LIMIT 1")->fetch() ?: [];
$offers = seo_offers($pdo, false);
$reviews = seo_reviews($pdo, false);
$editOffer = null;
$editReview = null;
if (!empty($_GET['edit_offer'])) {
    $stmt = $pdo->prepare("SELECT * FROM seo_offers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_offer']]);
    $editOffer = $stmt->fetch() ?: null;
}
if (!empty($_GET['edit_review'])) {
    $stmt = $pdo->prepare("SELECT * FROM seo_reviews WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_review']]);
    $editReview = $stmt->fetch() ?: null;
}

$v = static fn(string $k, $default = '') => e((string)($s[$k] ?? $default));
$on = static fn(string $k, bool $default = false) => (isset($s[$k]) ? (int)$s[$k] : (int)$default) ? 'checked' : '';

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>SEO Setting</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="tabs">
    <a class="tab <?= $tab === 'general' ? 'active' : '' ?>" href="?tab=general">Meta &amp; Branding</a>
    <a class="tab <?= $tab === 'indexing' ? 'active' : '' ?>" href="?tab=indexing">Indexing</a>
    <a class="tab <?= $tab === 'schema' ? 'active' : '' ?>" href="?tab=schema">Software App Schema</a>
    <a class="tab <?= $tab === 'offers' ? 'active' : '' ?>" href="?tab=offers">Pricing Offers <span class="tab-count"><?= count($offers) ?></span></a>
    <a class="tab <?= $tab === 'reviews' ? 'active' : '' ?>" href="?tab=reviews">Review Schema <span class="tab-count"><?= count($reviews) ?></span></a>
    <a class="tab <?= $tab === 'preview' ? 'active' : '' ?>" href="?tab=preview">Preview</a>
</div>

<?php if ($tab === 'general'): ?>
<div class="card">
    <h2>Website Meta</h2>
    <p class="muted">These are used on the public pages (homepage, privacy policy and the login/signup pages).</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_general">
        <div class="form-row">
            <label>Meta Title <span class="muted">(50–60 characters works best)</span></label>
            <input type="text" name="meta_title" maxlength="255" value="<?= $v('meta_title') ?>" placeholder="Pin Generator — Create Pinterest Pins in Seconds">
        </div>
        <div class="form-row">
            <label>Meta Description <span class="muted">(aim for 150–160 characters)</span></label>
            <textarea name="meta_description" rows="3" maxlength="500" placeholder="Speed up your Pinterest marketing..."><?= $v('meta_description') ?></textarea>
        </div>
        <div class="form-row">
            <label>Meta Keywords <span class="muted">(separate with commas)</span></label>
            <input type="text" name="meta_keywords" maxlength="500" value="<?= $v('meta_keywords') ?>" placeholder="pinterest pin maker, pin scheduler, pinterest seo">
        </div>
        <div class="form-row">
            <label>Canonical URL <span class="muted">(optional — the preferred address of your homepage)</span></label>
            <input type="text" name="canonical_url" value="<?= $v('canonical_url') ?>" placeholder="https://yourdomain.com/">
        </div>

        <h3 style="margin-top:26px;">Branding</h3>
        <div class="two-col">
            <div class="form-row">
                <label>Favicon <span class="muted">(ICO, PNG or SVG — 32×32 or 512×512)</span></label>
                <?php if (!empty($s['favicon_path'])): ?>
                    <p><img src="<?= e(seo_asset_url($s['favicon_path'])) ?>" alt="" style="height:36px;border:1px solid var(--border);border-radius:6px;padding:4px;background:#fff;"></p>
                    <label class="muted" style="font-weight:400;"><input type="checkbox" name="remove_favicon" value="1"> Remove current favicon</label>
                <?php endif; ?>
                <input type="file" name="favicon_file" accept=".ico,.png,.svg,.webp,.gif">
            </div>
            <div class="form-row">
                <label>Logo</label>
                <?php if (!empty($s['logo_path'])): ?>
                    <p><img src="<?= e(seo_asset_url($s['logo_path'])) ?>" alt="" style="height:44px;border:1px solid var(--border);border-radius:6px;padding:4px;background:#fff;"></p>
                    <label class="muted" style="font-weight:400;"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                <?php endif; ?>
                <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            </div>
        </div>
        <div class="form-row">
            <label>Social Share Image <span class="muted">(Open Graph — 1200×630 recommended; falls back to your logo)</span></label>
            <?php if (!empty($s['og_image_path'])): ?>
                <p><img src="<?= e(seo_asset_url($s['og_image_path'])) ?>" alt="" style="max-height:110px;border:1px solid var(--border);border-radius:8px;"></p>
                <label class="muted" style="font-weight:400;"><input type="checkbox" name="remove_og_image" value="1"> Remove current image</label>
            <?php endif; ?>
            <input type="file" name="og_image_file" accept=".png,.jpg,.jpeg,.webp">
        </div>
        <div class="form-row">
            <label>Extra &lt;head&gt; Code <span class="muted">(optional — analytics, verification tags, etc.)</span></label>
            <textarea name="extra_head_code" rows="4" placeholder="&lt;meta name=&quot;google-site-verification&quot; content=&quot;...&quot;&gt;"><?= $v('extra_head_code') ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Save Meta Settings</button>
    </form>
</div>

<?php elseif ($tab === 'indexing'): ?>
<div class="card">
    <h2>Search Engine Indexing</h2>
    <p class="muted">This is <strong>off by default</strong>. While it's off, every public page sends
    <code>noindex, nofollow</code>, robots.txt blocks all crawlers, and no schema markup is output — useful while
    you're still building. Turn it on once the site is ready to be found on Google.</p>
    <form method="POST">
        <input type="hidden" name="action" value="save_indexing">
        <div class="form-row">
            <label style="font-weight:600;">
                <input type="checkbox" name="robots_index" value="1" <?= $on('robots_index', false) ?>>
                Allow search engines to index this website
            </label>
            <p class="muted" style="margin:6px 0 0;">Currently:
                <span class="badge badge-<?= !empty($s['robots_index']) ? 'connected' : 'error' ?>">
                    <?= !empty($s['robots_index']) ? 'Indexing ON' : 'Indexing OFF' ?>
                </span>
            </p>
        </div>
        <div class="form-row">
            <label style="font-weight:600;">
                <input type="checkbox" name="robots_follow" value="1" <?= $on('robots_follow', true) ?>>
                Allow search engines to follow links on this website
            </label>
        </div>
        <div class="form-row">
            <label>Custom robots.txt <span class="muted">(leave blank to use the generated one below)</span></label>
            <textarea name="robots_txt" rows="8" style="font-family:monospace;"><?= $v('robots_txt') ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Save Indexing Settings</button>
    </form>
</div>
<div class="card">
    <h3>Current robots.txt output</h3>
    <p class="muted">Served at <code><?= e(rtrim(defined('APP_URL') ? APP_URL : '', '/')) ?>/robots.txt</code></p>
    <pre style="background:var(--light);padding:14px;border-radius:8px;overflow:auto;"><?= e(seo_robots_txt($pdo)) ?></pre>
</div>

<?php elseif ($tab === 'schema'): ?>
<div class="card">
    <h2>Software App Schema Markup</h2>
    <p class="muted">Output as JSON-LD on your homepage so Google can show your app name, rating, publisher and
    pricing in search results. Pricing rows live on the <a href="?tab=offers">Pricing Offers</a> tab — the
    AggregateOffer's lowest price, highest price and offer count are calculated from them automatically.</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_schema">
        <div class="form-row">
            <label style="font-weight:600;"><input type="checkbox" name="schema_enabled" value="1" <?= $on('schema_enabled', true) ?>> Enable schema markup output</label>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Type</label>
                <select name="app_type">
                    <?php foreach (['WebApplication', 'SoftwareApplication', 'MobileApplication'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($s['app_type'] ?? 'WebApplication') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row"><label>Name</label><input type="text" name="app_name" value="<?= $v('app_name') ?>" placeholder="Pin Generator"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>URL</label><input type="text" name="app_url" value="<?= $v('app_url') ?>" placeholder="https://pingenerator.com/"></div>
            <div class="form-row"><label>Application Category</label><input type="text" name="app_category" value="<?= $v('app_category', 'BusinessApplication') ?>" placeholder="BusinessApplication"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Operating System</label><input type="text" name="app_operating_system" value="<?= $v('app_operating_system', 'Any') ?>" placeholder="Any"></div>
            <div class="form-row"><label>Browser Requirements</label><input type="text" name="app_browser_requirements" value="<?= $v('app_browser_requirements') ?>" placeholder="Requires HTML5 support and JavaScript"></div>
        </div>
        <div class="form-row">
            <label>Description</label>
            <textarea name="app_description" rows="3" placeholder="Speed up your Pinterest marketing..."><?= $v('app_description') ?></textarea>
        </div>
        <div class="form-row">
            <label>Screenshot URL <span class="muted">(or upload below)</span></label>
            <input type="text" name="app_screenshot_url" value="<?= $v('app_screenshot') ?>" placeholder="https://yourdomain.com/screenshot.webp">
            <input type="file" name="screenshot_file" accept=".png,.jpg,.jpeg,.webp" style="margin-top:8px;">
        </div>

        <h3 style="margin-top:26px;">Aggregate Rating</h3>
        <div class="form-row">
            <label style="font-weight:600;"><input type="checkbox" name="rating_enabled" value="1" <?= $on('rating_enabled', true) ?>> Include aggregateRating</label>
            <p class="muted" style="margin:6px 0 0;">Only publish ratings you can actually back up with real reviews — Google can issue a manual action for invented ones.</p>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Rating Value</label><input type="number" step="0.1" name="rating_value" value="<?= $v('rating_value', '4.6') ?>"></div>
            <div class="form-row"><label>Rating Count</label><input type="number" name="rating_count" value="<?= $v('rating_count', '0') ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Best Rating</label><input type="number" step="0.1" name="rating_best" value="<?= $v('rating_best', '5') ?>"></div>
            <div class="form-row"><label>Worst Rating</label><input type="number" step="0.1" name="rating_worst" value="<?= $v('rating_worst', '1') ?>"></div>
        </div>

        <h3 style="margin-top:26px;">Publisher</h3>
        <div class="form-row">
            <label style="font-weight:600;"><input type="checkbox" name="publisher_enabled" value="1" <?= $on('publisher_enabled', true) ?>> Include publisher Organization</label>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Publisher Name</label><input type="text" name="publisher_name" value="<?= $v('publisher_name') ?>"></div>
            <div class="form-row"><label>Publisher URL</label><input type="text" name="publisher_url" value="<?= $v('publisher_url') ?>"></div>
        </div>
        <div class="form-row">
            <label>Publisher Logo URL <span class="muted">(or upload below)</span></label>
            <input type="text" name="publisher_logo_url_text" value="<?= $v('publisher_logo_url') ?>" placeholder="https://yourdomain.com/logos/logo512.webp">
            <input type="file" name="publisher_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" style="margin-top:8px;">
        </div>
        <div class="two-col">
            <div class="form-row"><label>Logo Width</label><input type="number" name="publisher_logo_width" value="<?= $v('publisher_logo_width', '512') ?>"></div>
            <div class="form-row"><label>Logo Height</label><input type="number" name="publisher_logo_height" value="<?= $v('publisher_logo_height', '512') ?>"></div>
        </div>

        <h3 style="margin-top:26px;">Offers &amp; Reviews</h3>
        <div class="form-row">
            <label style="font-weight:600;"><input type="checkbox" name="offers_enabled" value="1" <?= $on('offers_enabled', true) ?>> Include AggregateOffer (pricing)</label>
        </div>
        <div class="form-row" style="max-width:220px;">
            <label>Offers Currency</label><input type="text" name="offers_currency" maxlength="5" value="<?= $v('offers_currency', 'USD') ?>">
        </div>
        <div class="form-row">
            <label style="font-weight:600;"><input type="checkbox" name="reviews_enabled" value="1" <?= $on('reviews_enabled', true) ?>> Include Review schema blocks</label>
        </div>
        <button type="submit" class="btn-primary">Save Schema Settings</button>
    </form>
</div>

<?php elseif ($tab === 'offers'): ?>
<div class="card">
    <h2><?= $editOffer ? 'Edit Offer' : 'Add Pricing Offer' ?></h2>
    <p class="muted">Each row becomes one <code>Offer</code> inside the AggregateOffer. Low/high price and offer
    count are worked out from the active rows for you.</p>
    <form method="POST">
        <input type="hidden" name="action" value="save_offer">
        <input type="hidden" name="offer_id" value="<?= (int)($editOffer['id'] ?? 0) ?>">
        <div class="two-col">
            <div class="form-row"><label>Plan Name</label><input type="text" name="name" required value="<?= e($editOffer['name'] ?? '') ?>" placeholder="Pro monthly"></div>
            <div class="form-row"><label>Price</label><input type="number" step="0.01" name="price" required value="<?= e((string)($editOffer['price'] ?? '')) ?>" placeholder="29.99"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Currency</label><input type="text" name="price_currency" maxlength="5" value="<?= e($editOffer['price_currency'] ?? ($s['offers_currency'] ?? 'USD')) ?>"></div>
            <div class="form-row"><label>Pricing Page URL</label><input type="text" name="url" value="<?= e($editOffer['url'] ?? '') ?>" placeholder="https://yourdomain.com/pricing"></div>
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Billing Period Length</label>
                <input type="number" name="quantity_value" value="<?= e((string)($editOffer['quantity_value'] ?? 1)) ?>">
            </div>
            <div class="form-row">
                <label>Period Unit</label>
                <select name="unit_code">
                    <?php foreach (['MON' => 'Month(s)', 'ANN' => 'Year(s)', 'DAY' => 'Day(s)', 'WEE' => 'Week(s)'] as $code => $label): ?>
                        <option value="<?= $code ?>" <?= ($editOffer['unit_code'] ?? 'MON') === $code ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label>Description</label>
            <input type="text" name="description" maxlength="500" value="<?= e($editOffer['description'] ?? '') ?>" placeholder="1,000 Pinterest pin credits per month.">
        </div>
        <div class="two-col">
            <div class="form-row">
                <label>Availability</label>
                <select name="availability">
                    <?php foreach (['https://schema.org/InStock' => 'In Stock', 'https://schema.org/OutOfStock' => 'Out of Stock', 'https://schema.org/PreOrder' => 'Pre-order'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($editOffer['availability'] ?? 'https://schema.org/InStock') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row"><label>Price Valid Until</label><input type="date" name="price_valid_until" value="<?= e($editOffer['price_valid_until'] ?? '') ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Sort Order</label><input type="number" name="sort_order" value="<?= e((string)($editOffer['sort_order'] ?? 0)) ?>"></div>
            <div class="form-row">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($editOffer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= ($editOffer['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn-primary"><?= $editOffer ? 'Update Offer' : 'Add Offer' ?></button>
        <?php if ($editOffer): ?><a href="?tab=offers" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
</div>
<div class="card">
    <h2>Offers</h2>
    <?php if (!$offers): ?>
        <p class="muted">No offers yet — add your pricing plans above.</p>
    <?php else: ?>
    <table>
        <tr><th>Name</th><th>Price</th><th>Period</th><th>Description</th><th>Status</th><th></th></tr>
        <?php foreach ($offers as $o): ?>
        <tr>
            <td><?= e($o['name']) ?></td>
            <td><?= e($o['price_currency']) ?> <?= number_format((float)$o['price'], 2) ?></td>
            <td><?= (int)$o['quantity_value'] ?> <?= e($o['unit_code']) ?></td>
            <td class="muted"><?= e($o['description'] ?: '—') ?></td>
            <td><span class="badge badge-<?= $o['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="?tab=offers&edit_offer=<?= (int)$o['id'] ?>" class="btn-secondary btn-small">Edit</a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small"><?= $o['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this offer?');">
                    <input type="hidden" name="action" value="delete_offer">
                    <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'reviews'): ?>
<div class="card">
    <h2><?= $editReview ? 'Edit Review' : 'Add Review' ?></h2>
    <p class="muted">Each row becomes its own <code>Review</code> JSON-LD block. Use real reviews only.</p>
    <form method="POST">
        <input type="hidden" name="action" value="save_review">
        <input type="hidden" name="review_id" value="<?= (int)($editReview['id'] ?? 0) ?>">
        <div class="two-col">
            <div class="form-row"><label>Reviewer Name</label><input type="text" name="author_name" required value="<?= e($editReview['author_name'] ?? '') ?>" placeholder="Kara Buntin (YouTube)"></div>
            <div class="form-row">
                <label>Author Type</label>
                <select name="author_type">
                    <option value="Person" <?= ($editReview['author_type'] ?? 'Person') === 'Person' ? 'selected' : '' ?>>Person</option>
                    <option value="Organization" <?= ($editReview['author_type'] ?? '') === 'Organization' ? 'selected' : '' ?>>Organization</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label>Review Text</label>
            <textarea name="review_body" rows="3" required placeholder="It creates titles and descriptions for you... it's such a time-saver."><?= e($editReview['review_body'] ?? '') ?></textarea>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Rating Value</label><input type="number" step="0.1" name="rating_value" value="<?= e((string)($editReview['rating_value'] ?? 5)) ?>"></div>
            <div class="form-row"><label>Sort Order</label><input type="number" name="sort_order" value="<?= e((string)($editReview['sort_order'] ?? 0)) ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Best Rating</label><input type="number" step="0.1" name="best_rating" value="<?= e((string)($editReview['best_rating'] ?? 5)) ?>"></div>
            <div class="form-row"><label>Worst Rating</label><input type="number" step="0.1" name="worst_rating" value="<?= e((string)($editReview['worst_rating'] ?? 1)) ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Reviewed Item Name <span class="muted">(blank = app name)</span></label><input type="text" name="item_name" value="<?= e($editReview['item_name'] ?? '') ?>"></div>
            <div class="form-row"><label>Reviewed Item URL <span class="muted">(blank = app URL)</span></label><input type="text" name="item_url" value="<?= e($editReview['item_url'] ?? '') ?>"></div>
        </div>
        <div class="two-col">
            <div class="form-row"><label>Operating System</label><input type="text" name="item_operating_system" value="<?= e($editReview['item_operating_system'] ?? 'All') ?>"></div>
            <div class="form-row"><label>Application Category</label><input type="text" name="item_application_category" value="<?= e($editReview['item_application_category'] ?? 'DesignApplication') ?>"></div>
        </div>
        <div class="form-row" style="max-width:220px;">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= ($editReview['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="disabled" <?= ($editReview['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
            </select>
        </div>
        <button type="submit" class="btn-primary"><?= $editReview ? 'Update Review' : 'Add Review' ?></button>
        <?php if ($editReview): ?><a href="?tab=reviews" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
</div>
<div class="card">
    <h2>Reviews</h2>
    <?php if (!$reviews): ?>
        <p class="muted">No reviews yet.</p>
    <?php else: ?>
    <table>
        <tr><th>Author</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr>
        <?php foreach ($reviews as $r): ?>
        <tr>
            <td><?= e($r['author_name']) ?></td>
            <td><?= e((string)(float)$r['rating_value']) ?> / <?= e((string)(float)$r['best_rating']) ?></td>
            <td class="muted"><?= e(mb_strimwidth($r['review_body'], 0, 70, '…')) ?></td>
            <td><span class="badge badge-<?= $r['status'] === 'active' ? 'connected' : 'error' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="?tab=reviews&edit_review=<?= (int)$r['id'] ?>" class="btn-secondary btn-small">Edit</a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_review">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-secondary btn-small"><?= $r['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review?');">
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <h2>Live Output Preview</h2>
    <?php if (empty($s['robots_index'])): ?>
        <div class="alert alert-info">Indexing is currently <strong>off</strong>, so the live pages output
        <code>noindex</code> and no schema markup. The preview below shows what will be published once you turn
        indexing on.</div>
    <?php endif; ?>
    <h3>Schema markup</h3>
    <?php
    $nodes = [];
    $app = seo_software_schema($pdo);
    if ($app) $nodes[] = $app;
    foreach (seo_review_schemas($pdo) as $rev) $nodes[] = $rev;
    ?>
    <?php if (!$nodes): ?>
        <p class="muted">Nothing to output yet — enable schema markup and fill in the app name on the Software App Schema tab.</p>
    <?php else: ?>
        <?php foreach ($nodes as $node): ?>
<pre style="background:var(--light);padding:14px;border-radius:8px;overflow:auto;max-height:420px;"><?= e(json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
        <?php endforeach; ?>
        <p class="muted">Paste any block into <a href="https://validator.schema.org/" target="_blank" rel="noopener">validator.schema.org</a>
        or Google's Rich Results Test to check it before going live.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
