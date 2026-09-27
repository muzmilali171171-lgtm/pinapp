<?php
/**
 * Run this ONCE after uploading the updated files to add the new AI Article
 * Writer tables (ai_providers, article_settings, websites, articles) to a
 * site that was already installed. It only ever does CREATE TABLE IF NOT
 * EXISTS — it will never touch or delete your existing data.
 *
 * Visit https://yourdomain.com/migrate.php in your browser once, then
 * delete this file (or it will just no-op on future visits since the
 * tables will already exist).
 */

require_once __DIR__ . '/includes/db.php';

$sql = file_get_contents(__DIR__ . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)));

$created = [];
$errors = [];

foreach ($statements as $statement) {
    if ($statement === '') continue;
    try {
        $pdo->exec($statement);
        if (preg_match('/CREATE TABLE IF NOT EXISTS `?(\w+)`?/i', $statement, $m)) {
            $created[] = $m[1];
        }
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

/**
 * Add a column to a table only if it doesn't already exist. Safe to call
 * repeatedly and works on older MySQL/MariaDB that lack "ADD COLUMN IF NOT
 * EXISTS" support.
 */
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition, array &$errors): void
{
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $check->execute([$table, $column]);
    if ((int)$check->fetchColumn() > 0) return;
    try {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    } catch (PDOException $e) {
        $errors[] = "Adding column $table.$column: " . $e->getMessage();
    }
}

// Bring existing installs (created before the Bulk Pin Scheduler feature) up to date.
// Never touches or deletes existing data — only adds new, nullable columns.
add_column_if_missing($pdo, 'pinterest_boards', 'board_description', 'board_description TEXT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'pinterest_boards', 'status', "status ENUM('ready','pending_creation','create_failed') NOT NULL DEFAULT 'ready'", $errors);
add_column_if_missing($pdo, 'pinterest_boards', 'create_attempts', 'create_attempts INT NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'pinterest_boards', 'create_error', 'create_error TEXT DEFAULT NULL', $errors);
try {
    // board_id must become nullable to support boards that are drafted locally
    // and created on Pinterest later by the cron job.
    $pdo->exec("ALTER TABLE pinterest_boards MODIFY board_id VARCHAR(100) DEFAULT NULL");
} catch (PDOException $e) {
    $errors[] = 'Making pinterest_boards.board_id nullable: ' . $e->getMessage();
}

add_column_if_missing($pdo, 'scheduled_pins', 'board_row_id', 'board_row_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'alt_text', 'alt_text VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'source', "source ENUM('manual','bulk') NOT NULL DEFAULT 'manual'", $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'batch_id', 'batch_id VARCHAR(64) DEFAULT NULL', $errors);
try {
    $pdo->exec("ALTER TABLE scheduled_pins MODIFY board_id VARCHAR(100) DEFAULT NULL");
} catch (PDOException $e) {
    $errors[] = 'Making scheduled_pins.board_id nullable: ' . $e->getMessage();
}

add_column_if_missing($pdo, 'article_settings', 'pin_text_provider', 'pin_text_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_text_model', 'pin_text_model VARCHAR(255) DEFAULT NULL', $errors);

// Free Tools -> Pinterest Pin Maker (public, no-login tool).
add_column_if_missing($pdo, 'article_settings', 'freetool_text_provider', 'freetool_text_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_text_model', 'freetool_text_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_max_attempts', 'freetool_max_attempts INT DEFAULT NULL', $errors);
// Admin → Account Settings: display name and authenticator-app two-factor login.
add_column_if_missing($pdo, 'admin_users', 'name', 'name VARCHAR(150) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'admin_users', 'totp_secret', 'totp_secret VARCHAR(64) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'admin_users', 'totp_enabled', 'totp_enabled TINYINT(1) NOT NULL DEFAULT 0', $errors);
// Public Pin Maker (v2): free generations per visitor, default 3.
add_column_if_missing($pdo, 'article_settings', 'pinmaker_attempts', 'pinmaker_attempts INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_ai_design', 'freetool_ai_design TINYINT(1) NOT NULL DEFAULT 1', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_template_count', 'freetool_template_count INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_coupon_code', 'freetool_coupon_code VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_discount_percent', 'freetool_discount_percent INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_marketing_heading', 'freetool_marketing_heading VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetool_marketing_body', 'freetool_marketing_body TEXT DEFAULT NULL', $errors);

// Free Tools: AI Pinterest Pin Create + AI Image Creator settings.
add_column_if_missing($pdo, 'article_settings', 'pincreate_image_provider', 'pincreate_image_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pincreate_image_model', 'pincreate_image_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pincreate_max_pins', 'pincreate_max_pins INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pincreate_watermark_text', 'pincreate_watermark_text VARCHAR(100) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pincreate_watermark_logo_path', 'pincreate_watermark_logo_path VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'imagecreator_image_provider', 'imagecreator_image_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'imagecreator_image_model', 'imagecreator_image_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'imagecreator_daily_limit', 'imagecreator_daily_limit INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetext_provider', 'freetext_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetext_model', 'freetext_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'freetext_max_attempts', 'freetext_max_attempts INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'etsy_text_provider', 'etsy_text_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'etsy_text_model', 'etsy_text_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'etsy_max_attempts', 'etsy_max_attempts INT DEFAULT NULL', $errors);

// free_tool_usage: widen from a single lifetime counter per session to per-tool, per-day rows,
// so each free tool (and a true "daily limit") can be tracked independently.
add_column_if_missing($pdo, 'free_tool_usage', 'tool', "tool VARCHAR(50) NOT NULL DEFAULT 'pin_maker'", $errors);
add_column_if_missing($pdo, 'free_tool_usage', 'usage_date', 'usage_date DATE DEFAULT NULL', $errors);
try {
    $pdo->exec("UPDATE free_tool_usage SET usage_date = DATE(created_at) WHERE usage_date IS NULL");
    $pdo->exec("ALTER TABLE free_tool_usage MODIFY usage_date DATE NOT NULL");
} catch (PDOException $e) {
    $errors[] = 'Backfilling free_tool_usage.usage_date: ' . $e->getMessage();
}
try {
    $pdo->exec("ALTER TABLE free_tool_usage DROP INDEX uniq_session");
} catch (PDOException $e) {
    // Already dropped, or a fresh install that never had it — fine either way.
}
try {
    $pdo->exec("ALTER TABLE free_tool_usage ADD UNIQUE KEY uniq_session_tool_date (session_token, tool, usage_date)");
} catch (PDOException $e) {
    // Already present.
}

// Classic Wizard: extends website_pin_batches/pages (shared engine with Auto Website to Daily Pin)
// with real-page-image sourcing, scheduling controls, approval gate, and multi-board pools.
add_column_if_missing($pdo, 'website_pin_batches', 'wizard_source', "wizard_source ENUM('auto_website','classic_wizard') NOT NULL DEFAULT 'auto_website'", $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'source_type', "source_type ENUM('ai_image','page_scan') NOT NULL DEFAULT 'ai_image'", $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'template_styles', 'template_styles VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'warmup_enabled', 'warmup_enabled TINYINT(1) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'floating_days_enabled', 'floating_days_enabled TINYINT(1) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'floating_minutes', 'floating_minutes INT NOT NULL DEFAULT 5', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'lifetime_limit_per_url', 'lifetime_limit_per_url INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'monthly_limit_per_url', 'monthly_limit_per_url INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'no_link_pins', 'no_link_pins TINYINT(1) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'requires_approval', 'requires_approval TINYINT(1) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'floating_times_enabled', 'floating_times_enabled TINYINT(1) NOT NULL DEFAULT 0', $errors);
// Brand Color Palette (optional): user-picked colors that override the AI's automatic colors on generated pins.
add_column_if_missing($pdo, 'website_pin_batches', 'color_palette', 'color_palette LONGTEXT DEFAULT NULL', $errors);
// Pin Templates & Styles picker: image_style can now hold several templates ("auto,tpl_a,tpl_b,…").
try {
    $pdo->exec("ALTER TABLE website_pin_batches MODIFY image_style VARCHAR(2000) NOT NULL DEFAULT 'auto'");
} catch (PDOException $e) {
    $errors[] = 'Widening website_pin_batches.image_style: ' . $e->getMessage();
}
try {
    $pdo->exec("ALTER TABLE website_pin_batches MODIFY board_mode ENUM('existing','ai_separate','multi_select') NOT NULL DEFAULT 'ai_separate'");
} catch (PDOException $e) {
    $errors[] = 'Widening website_pin_batches.board_mode: ' . $e->getMessage();
}
try {
    $pdo->exec("ALTER TABLE website_pin_pages MODIFY status ENUM('queued','generating_text','generating_images','ready','pending_approval','scheduled','failed') NOT NULL DEFAULT 'queued'");
} catch (PDOException $e) {
    $errors[] = 'Widening website_pin_pages.status: ' . $e->getMessage();
}

// Bulk Pin Scheduler v2: batches (with draft autosave) + per-pin tags/keywords/product link.
add_column_if_missing($pdo, 'scheduled_pins', 'tags', 'tags VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'keywords', 'keywords VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'product_link', 'product_link VARCHAR(500) DEFAULT NULL', $errors);

// Bulk Pin Scheduler v3: AI pin-image generation + credits.
add_column_if_missing($pdo, 'users', 'credits', 'credits DECIMAL(10,2) NOT NULL DEFAULT 1000', $errors);
add_column_if_missing($pdo, 'users', 'email_verified_at', 'email_verified_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'plan_id', 'plan_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'plan_billing_cycle', "plan_billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly'", $errors);
add_column_if_missing($pdo, 'users', 'plan_started_at', 'plan_started_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'plan_end_date', 'plan_end_date DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'plan_auto_renew', 'plan_auto_renew TINYINT(1) NOT NULL DEFAULT 1', $errors);
add_column_if_missing($pdo, 'users', 'plan_reminder_sent_at', 'plan_reminder_sent_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'team_members', 'limit_share_percent', 'limit_share_percent INT NOT NULL DEFAULT 100', $errors);
add_column_if_missing($pdo, 'users', 'image_credits_balance', 'image_credits_balance DECIMAL(10,2) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'users', 'text_credits_balance', 'text_credits_balance DECIMAL(10,2) NOT NULL DEFAULT 0', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_provider', 'pin_image_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_model', 'pin_image_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_iterations', 'pin_image_iterations INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_cost_2', 'pin_image_cost_2 DECIMAL(6,2) NOT NULL DEFAULT 0.2', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_cost_3', 'pin_image_cost_3 DECIMAL(6,2) NOT NULL DEFAULT 0.7', $errors);
add_column_if_missing($pdo, 'article_settings', 'pin_image_cost_4', 'pin_image_cost_4 DECIMAL(6,2) NOT NULL DEFAULT 1.0', $errors);

// Auto Article Pin (v6): article batches, per-article scheduling/publishing fields, and
// separate feature-image model settings.
add_column_if_missing($pdo, 'article_settings', 'feature_image_provider', 'feature_image_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'feature_image_model', 'feature_image_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'batch_id', 'batch_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'meta_description', 'meta_description VARCHAR(500) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'slug', 'slug VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'scheduled_for', 'scheduled_for DATE DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'pin_status', "pin_status ENUM('none','scheduled','done') NOT NULL DEFAULT 'none'", $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'source_article_id', 'source_article_id INT DEFAULT NULL', $errors);

// Auto Article v2: a resumable, step-at-a-time pipeline (queued -> drafted -> imaging ->
// ready -> published/failed) so a single web request never has to do the whole
// outline+draft+every-image+publish chain in one shot — each step is short enough to
// stay well inside shared-hosting timeouts, and a killed request never leaves an
// article silently stuck (every step is wrapped so a crash still marks it failed).
add_column_if_missing($pdo, 'articles', 'image_slots_needed', 'image_slots_needed INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'articles', 'image_progress_json', 'image_progress_json LONGTEXT DEFAULT NULL', $errors);
try {
    $pdo->exec("ALTER TABLE articles MODIFY status ENUM('queued','drafting','drafted','imaging','ready','publishing','draft','published','failed') NOT NULL DEFAULT 'queued'");
} catch (Throwable $e) {
    $errors[] = 'articles.status widen (v2): ' . $e->getMessage();
}

// Auto Article v5: author selection + a cache column on websites for the WP author list.
add_column_if_missing($pdo, 'article_batches', 'wp_author_id', 'wp_author_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'websites', 'authors_cache', 'authors_cache TEXT DEFAULT NULL', $errors);

// Auto Website to Daily Pin (v10): model settings + linking column on scheduled_pins.
// The new tables (crawl_sites, crawl_sitemaps, crawl_pages, crawler_providers,
// website_pin_batches, website_pin_pages) are created automatically above via schema.sql.
add_column_if_missing($pdo, 'article_settings', 'wpin_text_provider', 'wpin_text_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'wpin_text_model', 'wpin_text_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'wpin_image_provider', 'wpin_image_provider VARCHAR(50) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'wpin_image_model', 'wpin_image_model VARCHAR(255) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_settings', 'wpin_image_iterations', 'wpin_image_iterations INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'source_website_page_id', 'source_website_page_id INT DEFAULT NULL', $errors);
// Publisher retry/back-off + overdue publishing, and Website → Daily Pin gap unit / start day / start time.
add_column_if_missing($pdo, 'scheduled_pins', 'next_retry_at', 'next_retry_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'scheduled_pins', 'claimed_at', 'claimed_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'page_gap_unit', 'page_gap_unit VARCHAR(10) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'page_gap_minutes', 'page_gap_minutes INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'start_date', 'start_date DATE DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'website_pin_batches', 'start_time', 'start_time VARCHAR(5) DEFAULT NULL', $errors);
// External image storage: settings, per-file location (external / hosting) and the error log.
try {
    require_once __DIR__ . '/includes/functions.php';
    @unlink(__DIR__ . '/uploads/.schema_extstorage_v1');
    ext_ensure_schema($pdo);
} catch (Throwable $e) {
    $errors[] = 'External storage tables: ' . $e->getMessage();
}
// Design editor: element type (Shapes / Graphics / Emoji / 3D / Frames) for admin-uploaded elements.
try {
    require_once __DIR__ . '/includes/design_functions.php';
    @unlink(__DIR__ . '/uploads/.schema_eltype_v1');
    design_ensure_element_type($pdo);
} catch (Throwable $e) {
    $errors[] = 'Design element type: ' . $e->getMessage();
}
// Admin → AI Article Data (reference articles + categories) and the Auto Article length setting.
try {
    require_once __DIR__ . '/includes/ai_article_data_functions.php';
    @unlink(__DIR__ . '/uploads/.schema_aad_v1');
    aad_ensure_schema($pdo);
} catch (Throwable $e) {
    $errors[] = 'AI article data tables: ' . $e->getMessage();
}

// Long articles: make sure the article text / progress columns can hold them.
try { $pdo->exec("ALTER TABLE articles MODIFY sections_json LONGTEXT NULL, MODIFY content LONGTEXT NULL, MODIFY image_progress_json LONGTEXT NULL"); }
catch (Throwable $e) { $errors[] = 'articles LONGTEXT: ' . $e->getMessage(); }

// Analytics → Template Tracking tables.
try {
    @unlink(__DIR__ . '/uploads/.schema_tt_v1');
    tt_ensure_schema($pdo);
} catch (Throwable $e) {
    $errors[] = 'Template tracking tables: ' . $e->getMessage();
}
// Image categories: the Auto Website to Daily Pin batch remembers the category picked for its images.
add_column_if_missing($pdo, 'website_pin_batches', 'image_category_id', 'image_category_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'article_batches', 'image_category_id', 'image_category_id INT DEFAULT NULL', $errors);
try {
    require_once __DIR__ . '/includes/image_category_functions.php';
    $imgCatCount = (int)$pdo->query("SELECT COUNT(*) FROM image_categories")->fetchColumn();
    if ($imgCatCount === 0) image_categories_seed_defaults($pdo); // first install only — admin edits are never overwritten
} catch (Throwable $e) {
    $errors[] = 'image categories seed: ' . $e->getMessage();
}
try {
    $pdo->exec("ALTER TABLE scheduled_pins MODIFY source ENUM('manual','bulk','auto_article','website_pin','regenerate','keyword') NOT NULL DEFAULT 'manual'");
} catch (Throwable $e) {
    $errors[] = 'scheduled_pins.source widen (v10): ' . $e->getMessage();
}

// Multi-platform websites (WordPress / Shopify / Wix / Custom webhook).
// Existing rows are all WordPress sites, so 'wordpress' is the default for them.
// Nothing is deleted or rewritten — only new nullable columns and widened types.
add_column_if_missing($pdo, 'websites', 'platform', "platform ENUM('none','wordpress','shopify','wix','custom') NOT NULL DEFAULT 'wordpress'", $errors);
add_column_if_missing($pdo, 'websites', 'external_id', 'external_id VARCHAR(191) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'websites', 'access_token', 'access_token TEXT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'websites', 'platform_meta', 'platform_meta LONGTEXT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'websites', 'webhook_url', 'webhook_url VARCHAR(700) DEFAULT NULL', $errors);
try {
    // Shopify / Wix sites have no WordPress site key.
    $pdo->exec("ALTER TABLE websites MODIFY site_key VARCHAR(255) NOT NULL DEFAULT ''");
} catch (Throwable $e) {
    $errors[] = 'websites.site_key default: ' . $e->getMessage();
}
add_column_if_missing($pdo, 'crawl_pages', 'item_type', "item_type VARCHAR(20) NOT NULL DEFAULT 'page'", $errors);
add_column_if_missing($pdo, 'crawl_pages', 'image_url', 'image_url VARCHAR(700) DEFAULT NULL', $errors);
try {
    $pdo->exec("ALTER TABLE crawl_pages MODIFY source ENUM('sitemap','crawl','manual','csv','api') NOT NULL DEFAULT 'sitemap'");
} catch (Throwable $e) {
    $errors[] = 'crawl_pages.source widen: ' . $e->getMessage();
}
try {
    // Wix post ids are GUIDs, so this can no longer be an INT.
    $pdo->exec("ALTER TABLE articles MODIFY wp_post_id VARCHAR(100) DEFAULT NULL");
} catch (Throwable $e) {
    $errors[] = 'articles.wp_post_id widen: ' . $e->getMessage();
}

// Auto Website to Daily Pin: CSV URL archives. A crawl site built from an uploaded
// CSV is marked source='csv' so it is kept separate from scanned sites.
add_column_if_missing($pdo, 'crawl_sites', 'source', "source ENUM('scan','csv') NOT NULL DEFAULT 'scan'", $errors);

// Affiliate Program (User → Affiliate, Admin → Affiliate): referral-link code per user,
// who referred them, and the affiliate_settings/clicks/referrals/commissions/payout
// tables (created above by schema.sql — this just adds the new users columns and seeds
// one default affiliate_settings row).
add_column_if_missing($pdo, 'users', 'affiliate_code', 'affiliate_code VARCHAR(30) DEFAULT NULL UNIQUE', $errors);
add_column_if_missing($pdo, 'users', 'referred_by_user_id', 'referred_by_user_id INT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'referred_by_code', 'referred_by_code VARCHAR(30) DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'users', 'timezone', 'timezone VARCHAR(64) DEFAULT NULL', $errors);
// Custom Design → Share design (public link)
add_column_if_missing($pdo, 'user_designs', 'share_token', 'share_token VARCHAR(32) DEFAULT NULL UNIQUE', $errors);
add_column_if_missing($pdo, 'user_designs', 'shared_at', 'shared_at DATETIME DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'user_designs', 'share_images', 'share_images TEXT DEFAULT NULL', $errors);
add_column_if_missing($pdo, 'user_designs', 'copied_from', 'copied_from INT DEFAULT NULL', $errors);
try {
    $affCount = $pdo->query("SELECT COUNT(*) FROM affiliate_settings")->fetchColumn();
    if ($affCount == 0) {
        $pdo->exec("INSERT INTO affiliate_settings (program_enabled, commission_percent, duration_type, min_payout_threshold, cookie_days)
            VALUES (1, 40, 'lifetime', 50, 60)");
    }
} catch (Throwable $e) {
    $errors[] = 'Seeding affiliate_settings: ' . $e->getMessage();
}

// SEO Setting (Admin → SEO Setting): site meta, favicon/logo, indexing toggle and
// schema markup. Tables are created by schema.sql above; here we just make sure a
// single settings row exists, pre-filled from the config constants. Indexing stays
// OFF until the admin turns it on.
try {
    $seoCount = $pdo->query("SELECT COUNT(*) FROM seo_settings")->fetchColumn();
    if ($seoCount == 0) {
        $pdo->prepare("INSERT INTO seo_settings
            (meta_title, app_name, app_url, publisher_name, publisher_url, robots_index)
            VALUES (?, ?, ?, ?, ?, 0)")
            ->execute([
                defined('SITE_BRAND') ? SITE_BRAND : null,
                defined('SITE_BRAND') ? SITE_BRAND : null,
                defined('APP_URL') ? rtrim(APP_URL, '/') . '/' : null,
                defined('SITE_BRAND') ? SITE_BRAND : null,
                defined('APP_URL') ? rtrim(APP_URL, '/') . '/' : null,
            ]);
    }
} catch (Throwable $e) {
    $errors[] = 'Seeding seo_settings: ' . $e->getMessage();
}

// Make sure the folder for favicon/logo uploads exists.
if (!is_dir(__DIR__ . '/uploads/seo')) {
    @mkdir(__DIR__ . '/uploads/seo', 0775, true);
}

// Seed a single empty article_settings row if none exists yet, so the admin
// settings page always has one row to update.
$count = $pdo->query("SELECT COUNT(*) FROM article_settings")->fetchColumn();
if ($count == 0) {
    $pdo->exec("INSERT INTO article_settings (text_provider, text_model, image_provider, image_model, pin_text_provider, pin_text_model) VALUES (NULL, NULL, NULL, NULL, NULL, NULL)");
}

// Plan Pricing: seed 5 default plans (Free, Starter, Pro, Growth, Agency) the FIRST time
// only — if any plan already exists (including ones the admin created/edited), this is
// skipped entirely so it never overwrites anything.
try {
    $planCount = $pdo->query("SELECT COUNT(*) FROM pricing_plans")->fetchColumn();
} catch (Throwable $e) {
    $planCount = null; // pricing_plans table doesn't exist yet somehow — schema.sql above should have created it; skip seeding safely.
}

// Pinterest accounts, websites and uploaded pins are Unlimited on every plan.
try {
    $pdo->exec("UPDATE pricing_plans SET pinterest_accounts_limit = NULL, websites_limit = NULL, upload_pins_limit = NULL");
} catch (Throwable $e) { /* table missing */ }

if ($planCount === '0' || $planCount === 0) {
    $planCols = "(name, is_free, price_monthly, discount_monthly, discount_yearly, short_description, tag, tag_color,
        pay_button_text, pay_button_bg, pay_button_text_color, pay_button_border_color, buy_button_position,
        image_ai_credits_monthly, text_ai_credits_monthly, pin_scheduling_monthly_limit, pin_scheduling_daily_limit,
        pinterest_accounts_limit, websites_limit, upload_pins_limit, bulk_scheduling_enabled, invite_team_members_limit,
        auto_website_daily_pin_enabled, auto_article_enabled, single_article_writer_enabled, cloud_storage_mb, sort_order, status)";
    $planIns = $pdo->prepare("INSERT INTO pricing_plans $planCols VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $defaultPlans = [
        // name, is_free, price_monthly, disc_monthly, disc_yearly, description, tag, tag_color, btn_text, btn_bg, btn_text_color, btn_border, btn_pos,
        // image_credits, text_credits, pin_monthly, pin_daily, pinterest_accts, websites, upload_pins, bulk, team_invites, auto_web_pin, auto_article, single_article, storage_mb, sort, status
        ['Free', 1, 0, 0, 0, 'Perfect for trying things out', null, 'red', 'Start Free', '#6b7280', '#ffffff', '#6b7280', 'bottom',
            5, 10000, 100, 15, null, null, null, 0, 2, 0, 0, 1, 100, 1, 'active'],
        ['Starter', 0, 20.00, 5, 15, 'Perfect plan for starting out', null, 'blue', 'Choose Plan', '#2563eb', '#ffffff', '#2563eb', 'bottom',
            300, 1000000, 2100, 70, null, null, null, 1, 2, 1, 1, 1, 1024, 2, 'active'],
        ['Pro', 0, 39.00, 7, 19, 'Great for one growing Pinterest account', 'popular', 'red', 'Choose Plan', '#e60023', '#ffffff', '#e60023', 'bottom',
            600, 2000000, 4200, 140, null, null, null, 1, 4, 1, 1, 1, 2048, 3, 'active'],
        ['Growth', 0, 75.00, 10, 20, 'Works well for multiple sites', 'recommended', 'blue', 'Choose Plan', '#2563eb', '#ffffff', '#2563eb', 'bottom',
            1200, 4000000, 8500, 300, null, null, null, 1, 8, 1, 1, 1, 5120, 4, 'active'],
        ['Agency', 0, 150.00, 15, 30, 'For large businesses and agencies', 'best_value', 'purple', 'Choose Plan', '#7c3aed', '#ffffff', '#7c3aed', 'bottom',
            2500, 10000000, 20000, 650, null, null, null, 1, 15, 1, 1, 1, 10240, 5, 'active'],
    ];
    $planIds = [];
    foreach ($defaultPlans as $p) {
        $planIns->execute($p);
        $planIds[$p[0]] = (int)$pdo->lastInsertId();
    }

    // A short, representative feature-bullet list per plan (admin can edit/add more from Create Plan).
    // Bulk scheduling / Auto Website-to-Daily-Pin / Auto Article / Single Article Writer are NOT
    // repeated here — pricing.php and upgrade.php already render those 4 automatically (as a ✓/✕
    // line) straight from their own DB columns above, so listing them again here would duplicate them.
    $rowIns = $pdo->prepare("INSERT INTO plan_feature_rows (plan_id, text, checkmark_type, text_size, text_color, checkmark_size, checkmark_color, sort_order) VALUES (?,?,?,?,?,?,?,?)");
    $featureRows = [
        'Free' => ['5 Image AI credits/month', '10,000 Text AI credits/month', 'Unlimited Pinterest accounts', 'Unlimited websites', 'Unlimited pin uploads', '100 pins/month (15/day)', '2 team members', 'Analytics', 'Delete 2 underperforming pins/day', '100MB cloud storage', 'Email support'],
        'Starter' => ['300 Image AI credits/month', '1,000,000 Text AI credits/month', 'Unlimited Pinterest accounts', 'Unlimited websites', 'Unlimited pin uploads', '2,100 pins/month (70/day)', '2 team members', 'Analytics', 'Delete 5 underperforming pins/day', '1GB cloud storage', 'Email support'],
        'Pro' => ['600 Image AI credits/month', '2,000,000 Text AI credits/month', '4,200 pins/month (140/day)', 'All from Starter', '4 team members', 'Analytics', 'Delete 10 underperforming pins/day', '2GB cloud storage', 'Priority support'],
        'Growth' => ['1,200 Image AI credits/month', '4,000,000 Text AI credits/month', '8,500 pins/month (300/day)', 'All from Pro', '8 team members', 'Analytics', 'Delete 15 underperforming pins/day', '5GB cloud storage', 'Priority support'],
        'Agency' => ['2,500 Image AI credits/month', '10,000,000 Text AI credits/month', '20,000 pins/month (650/day)', 'All from Growth', '15 team members', 'Analytics', 'Delete 20 underperforming pins/day', '10GB cloud storage', 'Urgent support'],
    ];
    foreach ($featureRows as $planName => $rows) {
        $order = 0;
        foreach ($rows as $text) {
            $rowIns->execute([$planIds[$planName], $text, 'check', '14px', '#1a1a1a', '16px', '#16a34a', $order++]);
        }
    }
}

// Any existing user with no plan at all (registered before Plan Pricing existed) gets put
// on the Free Plan now, so they're not suddenly locked out of every plan-gated feature —
// this only ever touches users where plan_id IS NULL, never someone already on a plan.
try {
    $freePlan = $pdo->query("SELECT * FROM pricing_plans WHERE is_free = 1 LIMIT 1")->fetch();
    if ($freePlan) {
        $pdo->prepare("UPDATE users SET plan_id = ?, plan_started_at = NOW(), image_credits_balance = ?, text_credits_balance = ? WHERE plan_id IS NULL")
            ->execute([$freePlan['id'], $freePlan['image_ai_credits_monthly'], $freePlan['text_ai_credits_monthly']]);
    }
} catch (Throwable $e) {
    $errors[] = 'Backfilling existing users onto the Free Plan: ' . $e->getMessage();
}

// Blog (Admin -> Blog Post): blog_categories / blog_posts are created by schema.sql
// above, which also seeds the 3 default categories (growth-guide, help)
// via INSERT IGNORE. Here we seed one full example article — a big, richly styled
// "How to Go Viral on Pinterest" guide at /growth-guide/pinterest-growth — the FIRST
// time only (skipped entirely if that slug already exists, so re-running this file
// never overwrites anything the admin has since edited).
require_once __DIR__ . '/includes/blog_functions.php';
try {
    $existingSlugCheck = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
    $existingSlugCheck->execute(['pinterest-growth']);
    if (!$existingSlugCheck->fetch()) {
        $growthCat = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = ?");
        $growthCat->execute(['growth-guide']);
        $growthCatId = $growthCat->fetchColumn();
        if ($growthCatId) {
            $seedContentPath = __DIR__ . '/database/seed-pinterest-growth.html';
            if (is_file($seedContentPath)) {
                $seedContent = file_get_contents($seedContentPath);
                $pdo->prepare(
                    "INSERT INTO blog_posts
                    (title, subtitle, meta_title, meta_description, slug, category_id, feature_image, content, editor_mode, status, author, published_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'html', 'published', ?, NOW())"
                )->execute([
                    'How to Go Viral on Pinterest in 2026 (The Complete Growth Playbook)',
                    'A step-by-step, data-backed guide to turning your website content into consistent Pinterest traffic — no ad spend required.',
                    'How to Go Viral on Pinterest in 2026 — Complete Growth Guide',
                    'A complete, data-backed playbook for growing Pinterest traffic in 2026: design, SEO, publishing cadence, and the exact 8-step system to break out of the sandbox and scale.',
                    'pinterest-growth',
                    (int)$growthCatId,
                    null,
                    $seedContent,
                    'AutomatedPin Team',
                ]);
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Seeding the Pinterest Growth blog post: ' . $e->getMessage();
}

// Seed the rest of the default Growth Guide series (Step -1 through the bonus
// playbooks) the same way — see database/blog-seed-manifest.php. Already-seeded
// or admin-edited posts (matched by slug) are always left untouched.
try {
    blog_seed_default_posts($pdo, 'growth-guide', 'AutomatedPin Team');
} catch (Throwable $e) {
    $errors[] = 'Seeding the Growth Guide series: ' . $e->getMessage();
}

// Seed the /compare category (Later vs Tailwind, Buffer vs Hootsuite,
// Pin Generator vs Tailwind) the same way — see database/blog-compare-manifest.php.
try {
    blog_seed_default_posts($pdo, 'compare', 'AutomatedPin Team', 'blog-compare-manifest.php', 'seed-compare');
} catch (Throwable $e) {
    $errors[] = 'Seeding the Compare series: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Update</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body class="auth-page">
<div class="auth-card" style="max-width:620px;">
    <h1>Database Update</h1>
    <?php if (empty($errors)): ?>
        <div class="alert alert-success">
            Done. All tables checked/created (admin_users, users, pinterest_*, scheduled_pins, pin_batches,
            cloudflare_accounts, cloudflare_usage, logs, ai_providers, article_settings, websites, articles), and
            the new columns (batches, tags, keywords, product link, credits, AI pin-image settings) were added
            to pinterest_boards / scheduled_pins / users / article_settings. Nothing existing was modified or deleted.<br><br>
            The new <strong>SEO Setting</strong> tables (seo_settings, seo_offers, seo_reviews) were also
            created — search engine indexing is <strong>off by default</strong>, so turn it on from
            Admin → SEO Setting → Indexing when the site is ready to be found on Google.<br><br>
            The new <strong>Settings</strong> tables (user_ai_settings, team_members, user_oauth_connections,
            user_tokens) and the <code>users.email_verified_at</code> column were also added, for the new
            User → Settings page (Account / your own OpenRouter API key / Image Generation Models / Team
            Management) and the new Admin → User Setting / Email Setting pages.<br><br>
            <strong>Plan Pricing</strong> was set up with 5 starter plans — Free, Starter, Pro, Growth, and Agency
            — each with its own AI credit, pin-scheduling, account/website, team-invite and storage limits. Edit
            or remove any of them from Admin → Plan Pricing → All Plans. AI credits are now tracked as two
            separate pools per plan (Image AI / Text AI) instead of one shared number.<br><br>
            The new <strong>Affiliate Program</strong> tables (affiliate_settings, affiliate_clicks,
            affiliate_referrals, affiliate_commissions, affiliate_payout_methods, affiliate_payouts) and the
            <code>users.affiliate_code</code> / <code>referred_by_user_id</code> columns were also added, for the
            new User → Affiliate (Dashboard / Payouts) and Admin → Affiliate (Dashboard / Settings / Payouts)
            pages. Open Admin → Affiliate → Settings to set the commission rate, commission duration, minimum
            payout threshold, and which payout methods (PayPal / Crypto / Binance Pay) are offered.<br><br>
            The new <strong>Pinterest Analytics</strong> tables (pa_account_daily, pa_pins, pa_sync_state, pa_api_cache,
            pa_regenerated_pins) were also added for User → Analytics → Pinterest Analytics (Analytics + Top Pins with
            Regenerate Similar). Pinterest only shares analytics for Business accounts.<br><br>
            Image categories (200 default categories) and per-quality image model settings were added —
            set them under Admin → Image Categories and each AI settings page.<br><br>
            Trends, Delete Underperforming Pins and Regen Draft added three more tables (pa_pin_daily,
            pa_delete_queue, pa_regen_drafts).<br><br>
            Next: log into the admin panel and open <strong>Models</strong> to add your provider keys (and any
            Cloudflare Worker accounts for free pin-image generation), then <strong>AI Setting By Features →
            Bulk Pin Scheduler</strong> to pick your text/image models.<br><br>
            <strong>Delete this file (migrate.php) now for security.</strong>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <div class="alert alert-info">Some statements failed — if the error above says a table already exists with
        a different structure, that's fine and expected for your original tables; otherwise contact support with
        the error text above.</div>
    <?php endif; ?>
    <a href="admin/login" class="btn-primary">Go to Admin Panel</a>
</div>
</body>
</html>
