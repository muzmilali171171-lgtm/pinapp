<?php
/**
 * Site-wide footer: branding/social settings + editable menu columns, all
 * managed from Admin -> Footer Settings and rendered on every public page
 * via render_site_footer($pdo) instead of a hardcoded <footer> block.
 */

/** Absolute URL for an internal path, same convention used across the app. */
function footer_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return rtrim(APP_URL, '/') . '/';
    if (preg_match('#^https?://#i', $path)) return $path;
    // Clean URLs: links saved in the DB before the switch still end in .php —
    // "free-tools/x/index.php" -> "free-tools/x/", "pricing.php?a=1" -> "pricing?a=1".
    $path = preg_replace('#(^|/)index\.php(?=$|[?\#])#i', '$1', $path);
    $path = preg_replace('#\.php(?=$|[?\#])#i', '', $path);
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Make sure the single footer_settings row exists. Returns its id. */
function footer_ensure_settings_row(PDO $pdo): int
{
    $id = $pdo->query("SELECT id FROM footer_settings ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($id) return (int)$id;
    $pdo->exec("INSERT INTO footer_settings (description, footer_text) VALUES (
        'Automate your Pinterest marketing on autopilot. Generate and publish pins for your website, every day.',
        NULL
    )");
    return (int)$pdo->lastInsertId();
}

function get_footer_settings(PDO $pdo): array
{
    footer_ensure_settings_row($pdo);
    $row = $pdo->query("SELECT * FROM footer_settings ORDER BY id ASC LIMIT 1")->fetch();
    return $row ?: [];
}

/**
 * Seed the five default footer columns (Product, Company, Free Tools,
 * Comparisons, Use Cases) the first time this runs. Comparisons and Use
 * Cases are seeded as headings only, with no items — the admin adds items
 * to them later from Footer Settings. Safe to call on every request; it
 * only inserts once (checks for an existing column first).
 */
function footer_ensure_defaults(PDO $pdo): void
{
    $count = (int)$pdo->query("SELECT COUNT(*) FROM footer_menu_columns")->fetchColumn();
    if ($count > 0) return;

    $columns = [
        ['heading' => 'Product', 'slug' => 'product', 'sort_order' => 1, 'items' => [
            ['Pricing', 'pricing'],
            ['Free Tools', 'free-tools/'],
        ]],
        ['heading' => 'Company', 'slug' => 'company', 'sort_order' => 2, 'items' => [
            ['About', 'about'],
            ['Contact', 'contact'],
            ['Terms and Conditions', 'terms'],
            ['Privacy Policy', 'privacy-policy'],
        ]],
        ['heading' => 'Free Tools', 'slug' => 'free-tools', 'sort_order' => 3, 'items' => [
            ['All Free Tools', 'free-tools/'],
            ['Pinterest Pin Maker', 'free-tools/pinterest-pin-maker/'],
            ['AI Pinterest Pin Create', 'free-tools/ai-pinterest-pin-create/'],
            ['AI Image Creator', 'free-tools/ai-image-creater/'],
            ['Pinterest Hashtag Generator', 'free-tools/pinterest-hashtag-generator/'],
            ['Pinterest Title & Description Generator', 'free-tools/pinterest-title-description-generator/'],
            ['Pinterest Bio Generator', 'free-tools/pinterest-bio-generator/'],
            ['Pinterest Board Name Generator', 'free-tools/pinterest-board-name-generator/'],
            ['Pinterest Username Generator', 'free-tools/pinterest-username-generator/'],
            ['Pinterest Alt Text Generator', 'free-tools/pinterest-alt-text-generator/'],
            ['Pinterest Font Generator', 'free-tools/pinterest-font-generator/'],
            ['Pinterest Keyword Research Tool', 'free-tools/pinterest-keyword-research-tool/'],
            ['Pinterest Color Palette Generator', 'free-tools/pinterest-color-palette-generator/'],
            ['Pinterest Image Resizer', 'free-tools/pinterest-image-resizer/'],
            ['Pinterest Pin Preview', 'free-tools/pinterest-pin-preview/'],
            ['Pinterest Character Counter', 'free-tools/pinterest-character-counter/'],
            ['Etsy Keyword Tool', 'free-tools/etsy-keyword-tool/'],
            ['Etsy Fee Calculator', 'free-tools/etsy-fee-calculator/'],
            ['Etsy Shop Bio Generator', 'free-tools/etsy-bio-generator/'],
            ['Etsy Shop Announcement Generator', 'free-tools/etsy-shop-announcement-generator/'],
            ['Etsy Shop Name Generator', 'free-tools/etsy-shop-name-generator/'],
            ['Etsy Tags Generator', 'free-tools/etsy-tags-generator/'],
            ['Etsy Title & Description Generator', 'free-tools/etsy-title-description-generator/'],
            ['Etsy QR Code Generator', 'free-tools/etsy-qr-code-generator/'],
        ]],
        ['heading' => 'Comparisons', 'slug' => 'comparisons', 'sort_order' => 4, 'items' => []],
        ['heading' => 'Use Cases', 'slug' => 'use-cases', 'sort_order' => 5, 'items' => []],
    ];

    foreach ($columns as $col) {
        $pdo->prepare("INSERT INTO footer_menu_columns (heading, slug, sort_order) VALUES (?, ?, ?)")
            ->execute([$col['heading'], $col['slug'], $col['sort_order']]);
        $columnId = (int)$pdo->lastInsertId();
        $order = 1;
        foreach ($col['items'] as [$label, $url]) {
            $pdo->prepare("INSERT INTO footer_menu_items (column_id, label, url, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$columnId, $label, $url, $order++]);
        }
    }
}

/** All footer columns, in order, each carrying its own ordered items. */
function get_footer_columns(PDO $pdo): array
{
    footer_ensure_defaults($pdo);
    $columns = $pdo->query("SELECT * FROM footer_menu_columns WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
    $items = $pdo->query("SELECT * FROM footer_menu_items ORDER BY sort_order ASC, id ASC")->fetchAll();
    foreach ($columns as &$col) {
        $col['items'] = array_values(array_filter($items, static fn($i) => (int)$i['column_id'] === (int)$col['id']));
    }
    return $columns;
}

/**
 * Handle a footer logo upload from the admin form. Mirrors seo_handle_upload
 * but keeps footer assets in their own folder.
 */
function footer_handle_upload(string $field, array &$error = []): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error[] = "Upload failed for $field.";
        return null;
    }
    $allowed = ['png' => 1, 'jpg' => 1, 'jpeg' => 1, 'webp' => 1, 'gif' => 1, 'svg' => 1];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        $error[] = "Unsupported file type for $field (use PNG, JPG, WEBP, GIF or SVG).";
        return null;
    }
    if ($_FILES[$field]['size'] > 3 * 1024 * 1024) {
        $error[] = "File for $field is too large (max 3MB).";
        return null;
    }
    $dir = __DIR__ . '/../uploads/footer';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'logo-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        $error[] = "Could not save the uploaded file for $field.";
        return null;
    }
    return 'uploads/footer/' . $name;
}

/** The social platforms the admin can add links for, in display order. */
function footer_social_platforms(): array
{
    return [
        'social_pinterest' => 'Pinterest',
        'social_instagram' => 'Instagram',
        'social_facebook' => 'Facebook',
        'social_linkedin' => 'LinkedIn',
        'social_x' => 'X',
        'social_youtube' => 'YouTube',
        'social_tiktok' => 'TikTok',
    ];
}

/** Tiny inline glyphs so the footer needs no icon font or external request. */
function footer_social_icon(string $key): string
{
    $glyphs = [
        'social_pinterest' => '📌',
        'social_instagram' => '📷',
        'social_facebook' => 'f',
        'social_linkedin' => 'in',
        'social_x' => '𝕏',
        'social_youtube' => '▶',
        'social_tiktok' => '♪',
    ];
    return $glyphs[$key] ?? '•';
}

/** Renders the full site-wide footer. Call once, right before </body>. */
function render_site_footer(PDO $pdo): void
{
    $settings = get_footer_settings($pdo);
    $columns = get_footer_columns($pdo);
    $logo = trim((string)($settings['logo_path'] ?? ''));
    $logoText = trim((string)($settings['logo_text'] ?? '')) ?: APP_NAME;
    ?>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <?php if ($logo): ?>
                    <img src="<?= e(footer_url($logo)) ?>" alt="<?= e($logoText) ?>" class="footer-logo-img">
                <?php else: ?>
                    <div class="footer-logo-text"><?= e($logoText) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['description'])): ?>
                    <p class="footer-desc"><?= nl2br(e($settings['description'])) ?></p>
                <?php endif; ?>
                <div class="footer-social">
                    <?php foreach (footer_social_platforms() as $key => $label):
                        $link = trim((string)($settings[$key] ?? ''));
                        if ($link === '') continue;
                    ?>
                        <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= footer_social_icon($key) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($columns as $col): ?>
                <div class="footer-col">
                    <h4><?= e($col['heading']) ?></h4>
                    <?php foreach ($col['items'] as $item): ?>
                        <a href="<?= e(footer_url($item['url'])) ?>"><?= e($item['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="footer-bottom">
            <?php if (!empty($settings['footer_text'])): ?>
                <?= nl2br(e($settings['footer_text'])) ?>
            <?php else: ?>
                &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
            <?php endif; ?>
        </div>
    </footer>
    <?php
}
