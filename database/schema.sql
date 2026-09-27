-- Pinterest Auto Scheduler — Database Schema
-- Charset/engine chosen for broad Hostinger MySQL/MariaDB compatibility

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    credits DECIMAL(10,2) NOT NULL DEFAULT 1000,
    email_verified_at DATETIME DEFAULT NULL,
    -- Affiliate program: this user's own referral-link code (set the first time they
    -- open Affiliate → Dashboard, editable from there), and who referred THEM in.
    affiliate_code VARCHAR(30) DEFAULT NULL UNIQUE,
    referred_by_user_id INT DEFAULT NULL,
    referred_by_code VARCHAR(30) DEFAULT NULL,
    timezone VARCHAR(64) DEFAULT NULL,          -- user's own time zone (times are stored in server time)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-row table holding the admin-configured Pinterest App credentials.
-- Users never see or enter these; they only click "Connect Pinterest".
CREATE TABLE IF NOT EXISTS pinterest_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) DEFAULT NULL,
    client_secret VARCHAR(255) DEFAULT NULL,
    redirect_uri VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per Pinterest account a user has connected via OAuth.
CREATE TABLE IF NOT EXISTS pinterest_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pinterest_user_id VARCHAR(100) DEFAULT NULL,
    pinterest_username VARCHAR(150) DEFAULT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    status ENUM('connected','revoked','error') NOT NULL DEFAULT 'connected',
    connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cached board list per connected account, refreshed on demand from the Pinterest API.
-- A row can also represent a board the user drafted locally but that does not exist on
-- Pinterest yet ('pending_creation') — the cron job creates it automatically shortly
-- before the first pin scheduled to that board is due (see cron/scheduler.php).
CREATE TABLE IF NOT EXISTS pinterest_boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinterest_account_id INT NOT NULL,
    board_id VARCHAR(100) DEFAULT NULL,
    board_name VARCHAR(255) NOT NULL,
    board_description TEXT DEFAULT NULL,
    status ENUM('ready','pending_creation','create_failed') NOT NULL DEFAULT 'ready',
    create_attempts INT NOT NULL DEFAULT 0,
    create_error TEXT DEFAULT NULL,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per bulk-scheduler batch: tracks the batch's name, account/board,
-- draft-vs-scheduled state, and (while status='draft') an autosaved JSON
-- snapshot of the whole builder UI so the user can leave and resume later.
-- Once "Schedule All Pins" runs, status flips to 'active' and draft_json is
-- cleared; scheduled_pins.batch_id links back to this row's batch_id.
-- Cloudflare Worker accounts used for free AI pin-image generation. Unlimited
-- accounts can be added; generation rotates to the next account once one
-- hits its daily_limit for the day (see cloudflare_usage below).
CREATE TABLE IF NOT EXISTS cloudflare_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(255) DEFAULT NULL,
    daily_limit INT NOT NULL DEFAULT 30,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per Cloudflare account per day, counting how many images it has
-- generated that day so rotation can move on once daily_limit is hit.
CREATE TABLE IF NOT EXISTS cloudflare_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    usage_date DATE NOT NULL,
    count INT NOT NULL DEFAULT 0,
    UNIQUE KEY account_date (account_id, usage_date),
    FOREIGN KEY (account_id) REFERENCES cloudflare_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pin_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    batch_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    pinterest_account_id INT DEFAULT NULL,
    board_row_id INT DEFAULT NULL,
    board_name VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','active') NOT NULL DEFAULT 'draft',
    draft_json LONGTEXT DEFAULT NULL,
    total_pins INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scheduled_at DATETIME DEFAULT NULL,
    UNIQUE KEY batch_id_unique (batch_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheduled_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pinterest_account_id INT NOT NULL,
    board_id VARCHAR(100) DEFAULT NULL,
    board_name VARCHAR(255) DEFAULT NULL,
    board_row_id INT DEFAULT NULL,
    image_path VARCHAR(500) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    dest_link VARCHAR(500) DEFAULT NULL,
    alt_text VARCHAR(500) DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    keywords VARCHAR(500) DEFAULT NULL,
    product_link VARCHAR(500) DEFAULT NULL,
    source ENUM('manual','bulk','auto_article','website_pin','regenerate','keyword') NOT NULL DEFAULT 'manual',
    batch_id VARCHAR(64) DEFAULT NULL,
    source_article_id INT DEFAULT NULL,
    source_website_page_id INT DEFAULT NULL,
    publish_at DATETIME NOT NULL,
    status ENUM('pending','processing','published','failed') NOT NULL DEFAULT 'pending',
    pinterest_pin_id VARCHAR(100) DEFAULT NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    next_retry_at DATETIME DEFAULT NULL,
    claimed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (board_row_id) REFERENCES pinterest_boards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('oauth','api','publish','system','ai','article') NOT NULL DEFAULT 'system',
    user_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== AI Article Writer =====================

-- Admin-configured API keys, one row per provider+model_type combination.
CREATE TABLE IF NOT EXISTS ai_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('chatgpt','claude','openrouter','deepinfra','google','pexels') NOT NULL,
    model_type ENUM('text','image','video','image_search') NOT NULL,
    api_key VARCHAR(500) DEFAULT NULL,
    default_model VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY provider_type (provider, model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-row table: site-wide defaults for the Write Article feature, plus the
-- separate AI model choice used by the Bulk Pin Scheduler's "Create with AI" writer.
CREATE TABLE IF NOT EXISTS article_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text_provider VARCHAR(50) DEFAULT NULL,
    text_model VARCHAR(255) DEFAULT NULL,
    image_provider VARCHAR(50) DEFAULT NULL,
    image_model VARCHAR(255) DEFAULT NULL,
    feature_image_provider VARCHAR(50) DEFAULT NULL,
    feature_image_model VARCHAR(255) DEFAULT NULL,
    wpin_text_provider VARCHAR(50) DEFAULT NULL,
    wpin_text_model VARCHAR(255) DEFAULT NULL,
    wpin_image_provider VARCHAR(50) DEFAULT NULL,
    wpin_image_model VARCHAR(255) DEFAULT NULL,
    wpin_image_iterations INT DEFAULT NULL,
    pin_text_provider VARCHAR(50) DEFAULT NULL,
    pin_text_model VARCHAR(255) DEFAULT NULL,
    pin_image_provider VARCHAR(50) DEFAULT NULL,
    pin_image_model VARCHAR(255) DEFAULT NULL,
    pin_image_iterations INT DEFAULT NULL,
    pin_image_cost_2 DECIMAL(6,2) NOT NULL DEFAULT 0.2,
    pin_image_cost_3 DECIMAL(6,2) NOT NULL DEFAULT 0.7,
    pin_image_cost_4 DECIMAL(6,2) NOT NULL DEFAULT 1.0,
    freetool_text_provider VARCHAR(50) DEFAULT NULL,
    freetool_text_model VARCHAR(255) DEFAULT NULL,
    freetool_max_attempts INT DEFAULT NULL,
    freetool_ai_design TINYINT(1) NOT NULL DEFAULT 1,
    freetool_template_count INT DEFAULT NULL,
    freetool_coupon_code VARCHAR(50) DEFAULT NULL,
    freetool_discount_percent INT DEFAULT NULL,
    freetool_marketing_heading VARCHAR(255) DEFAULT NULL,
    freetool_marketing_body TEXT DEFAULT NULL,
    pincreate_image_provider VARCHAR(50) DEFAULT NULL,
    pincreate_image_model VARCHAR(255) DEFAULT NULL,
    pincreate_max_pins INT DEFAULT NULL,
    pincreate_watermark_text VARCHAR(100) DEFAULT NULL,
    pincreate_watermark_logo_path VARCHAR(500) DEFAULT NULL,
    imagecreator_image_provider VARCHAR(50) DEFAULT NULL,
    imagecreator_image_model VARCHAR(255) DEFAULT NULL,
    imagecreator_daily_limit INT DEFAULT NULL,
    freetext_provider VARCHAR(50) DEFAULT NULL,
    freetext_model VARCHAR(255) DEFAULT NULL,
    freetext_max_attempts INT DEFAULT NULL,
    etsy_text_provider VARCHAR(50) DEFAULT NULL,
    etsy_text_model VARCHAR(255) DEFAULT NULL,
    etsy_max_attempts INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Free Tools -> Pinterest Pin Maker: anonymous (no-login) usage tracking, keyed by
-- browser session token + IP, so the visitor's free generation attempts can be capped
-- (Admin -> AI Setting By Features -> Classic Wizard -> attempt limit, default 10).
CREATE TABLE IF NOT EXISTS free_tool_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    tool VARCHAR(50) NOT NULL DEFAULT 'pin_maker',
    usage_date DATE NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_tool_date (session_token, tool, usage_date),
    KEY idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A user's websites. platform says which platform (if any) the site is linked to:
-- wordpress (companion plugin + shared key), shopify (Admin API token), wix (API key + site id),
-- custom (signed webhook), or none (added but not connected yet). status = connected/error,
-- where error is shown to the user as Unconnected.
CREATE TABLE IF NOT EXISTS websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    platform ENUM('none','wordpress','shopify','wix','custom') NOT NULL DEFAULT 'wordpress',
    site_name VARCHAR(255) DEFAULT NULL,
    site_url VARCHAR(500) NOT NULL,
    site_key VARCHAR(255) NOT NULL DEFAULT '',
    external_id VARCHAR(191) DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    platform_meta LONGTEXT DEFAULT NULL,
    webhook_url VARCHAR(700) DEFAULT NULL,
    status ENUM('connected','error') NOT NULL DEFAULT 'error',
    categories_cache TEXT DEFAULT NULL,
    tags_cache TEXT DEFAULT NULL,
    authors_cache TEXT DEFAULT NULL,
    last_checked_at DATETIME DEFAULT NULL,
    connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin-editable key/value settings for the website platforms (Shopify app credentials,
-- Shopify and Wix setup guides shown to users).
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value LONGTEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Storage: unified media library (manual uploads, Pexels stock, AI-generated,
-- and website-scanned images), plus quota tracking (1GB/user by default).
CREATE TABLE IF NOT EXISTS storage_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source ENUM('upload','url','pexels','ai','website_scan') NOT NULL DEFAULT 'upload',
    storage_provider ENUM('local','remote') NOT NULL DEFAULT 'local',
    provider_row_id INT DEFAULT NULL,
    file_path VARCHAR(700) NOT NULL,
    public_url VARCHAR(700) DEFAULT NULL,
    filename VARCHAR(255) DEFAULT NULL,
    size_bytes INT NOT NULL DEFAULT 0,
    tags VARCHAR(500) DEFAULT NULL,
    source_page_url VARCHAR(700) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin: cloud storage provider accounts (Backblaze B2, Amazon S3, Cloudflare R2 — all
-- S3-compatible, so one client handles all three). Multiple rows per provider let capacity
-- roll over automatically (e.g. several free Cloudflare R2 accounts at ~10GB each).
CREATE TABLE IF NOT EXISTS storage_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('b2','s3','r2') NOT NULL,
    label VARCHAR(255) DEFAULT NULL,
    endpoint VARCHAR(500) NOT NULL,
    region VARCHAR(100) NOT NULL DEFAULT 'auto',
    bucket VARCHAR(255) NOT NULL,
    access_key VARCHAR(255) NOT NULL,
    secret_key VARCHAR(255) NOT NULL,
    public_base_url VARCHAR(500) DEFAULT NULL,
    capacity_gb DECIMAL(8,2) NOT NULL DEFAULT 10,
    used_bytes BIGINT NOT NULL DEFAULT 0,
    tier ENUM('free','paid') NOT NULL DEFAULT 'free',
    priority INT NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin: Pexels API key for the Stock Images search.
CREATE TABLE IF NOT EXISTS pexels_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crawl Sites: any website being scanned for pages to use as pin sources (may or may not
-- also be a plugin-connected WordPress site in `websites`) — the foundation for both
-- "Auto Website to Daily Pin" and Storage's "Website Images" scan.
CREATE TABLE IF NOT EXISTS crawl_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    website_id INT DEFAULT NULL,
    site_url VARCHAR(500) NOT NULL,
    site_name VARCHAR(255) DEFAULT NULL,
    source ENUM('scan','csv') NOT NULL DEFAULT 'scan',
    last_scanned_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per discovered sitemap XML file for a crawl site (a site can have several,
-- e.g. post-sitemap1.xml, post-sitemap2.xml, category-sitemap.xml).
CREATE TABLE IF NOT EXISTS crawl_sitemaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crawl_site_id INT NOT NULL,
    sitemap_url VARCHAR(700) NOT NULL,
    page_count INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (crawl_site_id) REFERENCES crawl_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Individual discovered/added pages — the "Pages To Use For Pins" list.
CREATE TABLE IF NOT EXISTS crawl_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crawl_site_id INT NOT NULL,
    sitemap_id INT DEFAULT NULL,
    url VARCHAR(700) NOT NULL,
    url_hash CHAR(32) NOT NULL,
    source ENUM('sitemap','crawl','manual','csv','api') NOT NULL DEFAULT 'sitemap',
    item_type VARCHAR(20) NOT NULL DEFAULT 'page',
    image_url VARCHAR(700) DEFAULT NULL,
    last_modified DATE DEFAULT NULL,
    category_tags VARCHAR(500) DEFAULT NULL,
    keywords VARCHAR(500) DEFAULT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    active TINYINT(1) NOT NULL DEFAULT 0,
    pinned_count INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY site_url_unique (crawl_site_id, url_hash),
    FOREIGN KEY (crawl_site_id) REFERENCES crawl_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin: Page Crawler provider config (e.g. Firecrawl) used when a site has no
-- discoverable sitemap — the free-tier fallback the user asked for guides on.
CREATE TABLE IF NOT EXISTS crawler_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    api_key VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY provider_unique (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auto Website to Daily Pin: a batch of selected pages, each getting a set of pins
-- (default 3) generated and scheduled a month apart, at a controlled daily pace.
CREATE TABLE IF NOT EXISTS website_pin_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    batch_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    crawl_site_id INT NOT NULL,
    pinterest_account_id INT NOT NULL,
    pins_per_page INT NOT NULL DEFAULT 3,
    board_mode ENUM('existing','ai_separate','multi_select') NOT NULL DEFAULT 'ai_separate',
    board_row_id INT DEFAULT NULL,
    page_gap_days INT NOT NULL DEFAULT 30,
    page_gap_unit VARCHAR(10) DEFAULT NULL,
    page_gap_minutes INT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    start_time VARCHAR(5) DEFAULT NULL,
    daily_pin_count INT NOT NULL DEFAULT 6,
    image_quality VARCHAR(20) NOT NULL DEFAULT 'ultra',
    pin_size VARCHAR(20) NOT NULL DEFAULT '2:3',
    image_style VARCHAR(2000) NOT NULL DEFAULT 'auto',
    color_palette LONGTEXT DEFAULT NULL,
    cta_mode VARCHAR(20) NOT NULL DEFAULT 'auto',
    cta_text VARCHAR(255) DEFAULT NULL,
    website_text VARCHAR(255) DEFAULT NULL,
    content_source ENUM('ai','csv') NOT NULL DEFAULT 'ai',
    status ENUM('active','stopped') NOT NULL DEFAULT 'active',
    wizard_source ENUM('auto_website','classic_wizard') NOT NULL DEFAULT 'auto_website',
    source_type ENUM('ai_image','page_scan') NOT NULL DEFAULT 'ai_image',
    template_styles VARCHAR(500) DEFAULT NULL,
    warmup_enabled TINYINT(1) NOT NULL DEFAULT 0,
    floating_days_enabled TINYINT(1) NOT NULL DEFAULT 0,
    floating_minutes INT NOT NULL DEFAULT 5,
    lifetime_limit_per_url INT DEFAULT NULL,
    monthly_limit_per_url INT DEFAULT NULL,
    no_link_pins TINYINT(1) NOT NULL DEFAULT 0,
    requires_approval TINYINT(1) NOT NULL DEFAULT 0,
    floating_times_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY wpb_batch_id_unique (batch_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crawl_site_id) REFERENCES crawl_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Classic Wizard "Boards To Use" multi-select pool: when website_pin_batches.board_mode =
-- 'multi_select', pins are distributed across these boards (AI picks the best board per pin
-- from this pool) instead of one fixed board or one-new-board-per-page.
CREATE TABLE IF NOT EXISTS website_pin_batch_boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    board_row_id INT NOT NULL,
    FOREIGN KEY (batch_id) REFERENCES website_pin_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (board_row_id) REFERENCES pinterest_boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per page in the batch — tracks that page's own pin-generation pipeline
-- (resumable, one step at a time, same crash-safe pattern as Auto Article).
CREATE TABLE IF NOT EXISTS website_pin_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    crawl_page_id INT DEFAULT NULL,
    page_url VARCHAR(700) NOT NULL,
    page_title VARCHAR(255) DEFAULT NULL,
    csv_title VARCHAR(255) DEFAULT NULL,
    csv_description VARCHAR(500) DEFAULT NULL,
    csv_alt VARCHAR(500) DEFAULT NULL,
    csv_keywords VARCHAR(500) DEFAULT NULL,
    pins_needed INT NOT NULL DEFAULT 3,
    pin_data_json LONGTEXT DEFAULT NULL,
    board_row_id INT DEFAULT NULL,
    board_id VARCHAR(100) DEFAULT NULL,
    board_name VARCHAR(255) DEFAULT NULL,
    status ENUM('queued','generating_text','generating_images','ready','pending_approval','scheduled','failed') NOT NULL DEFAULT 'queued',
    scheduled_for DATE DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES website_pin_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- (each section: heading, content, image_source [none/web/ai], image_url, image_credit).
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    batch_id INT DEFAULT NULL,
    source_type ENUM('keyword','competitor_url') NOT NULL DEFAULT 'keyword',
    source_value VARCHAR(500) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    content LONGTEXT DEFAULT NULL,
    sections_json LONGTEXT DEFAULT NULL,
    featured_image_path VARCHAR(500) DEFAULT NULL,
    website_id INT DEFAULT NULL,
    category VARCHAR(255) DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    scheduled_for DATE DEFAULT NULL,
    pin_status ENUM('none','scheduled','done') NOT NULL DEFAULT 'none',
    status ENUM('queued','drafting','drafted','imaging','ready','publishing','draft','published','failed') NOT NULL DEFAULT 'queued',
    image_slots_needed INT DEFAULT NULL,
    image_progress_json LONGTEXT DEFAULT NULL,
    wp_post_id VARCHAR(100) DEFAULT NULL,
    wp_post_url VARCHAR(500) DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auto Article: a saved "batch" campaign — a set of titles (ideas or recipe articles) that
-- get outlined, written, illustrated and published to WordPress at a daily pace, optionally
-- followed by automatic Pinterest pin creation for each published article.
CREATE TABLE IF NOT EXISTS article_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    batch_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    article_type ENUM('ideas','recipe') NOT NULL DEFAULT 'ideas',
    website_id INT DEFAULT NULL,
    category VARCHAR(255) DEFAULT NULL,
    wp_author_id INT DEFAULT NULL,
    tags_enabled TINYINT(1) NOT NULL DEFAULT 0,
    daily_count INT NOT NULL DEFAULT 1,
    feature_image_w INT NOT NULL DEFAULT 1200,
    feature_image_h INT NOT NULL DEFAULT 630,
    ideas_image_size VARCHAR(20) NOT NULL DEFAULT '3:4',
    recipe_image_count INT NOT NULL DEFAULT 3,
    image_quality VARCHAR(20) NOT NULL DEFAULT 'budget',
    publish_mode ENUM('now','pin_auto') NOT NULL DEFAULT 'now',
    pin_settings_json LONGTEXT DEFAULT NULL,
    status ENUM('active','stopped') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY batch_id_unique (batch_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SEO Setting (Admin → SEO Setting)
-- Site-wide meta tags, favicon/logo, indexing control and JSON-LD schema.
-- Single-row settings table + one row per pricing offer / review.
-- =====================================================================
CREATE TABLE IF NOT EXISTS seo_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Website meta
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    canonical_url VARCHAR(500) DEFAULT NULL,
    favicon_path VARCHAR(500) DEFAULT NULL,
    logo_path VARCHAR(500) DEFAULT NULL,
    og_image_path VARCHAR(500) DEFAULT NULL,
    extra_head_code TEXT DEFAULT NULL,

    -- Indexing (OFF by default: the site stays noindex until the admin allows it)
    robots_index TINYINT(1) NOT NULL DEFAULT 0,
    robots_follow TINYINT(1) NOT NULL DEFAULT 1,
    robots_txt TEXT DEFAULT NULL,

    -- Software App schema
    schema_enabled TINYINT(1) NOT NULL DEFAULT 1,
    app_type VARCHAR(50) NOT NULL DEFAULT 'WebApplication',
    app_name VARCHAR(255) DEFAULT NULL,
    app_url VARCHAR(500) DEFAULT NULL,
    app_category VARCHAR(100) DEFAULT 'BusinessApplication',
    app_operating_system VARCHAR(100) DEFAULT 'Any',
    app_browser_requirements VARCHAR(255) DEFAULT NULL,
    app_description TEXT DEFAULT NULL,
    app_screenshot VARCHAR(500) DEFAULT NULL,

    -- Aggregate rating
    rating_enabled TINYINT(1) NOT NULL DEFAULT 1,
    rating_value DECIMAL(3,2) NOT NULL DEFAULT 0,
    rating_count INT NOT NULL DEFAULT 0,
    rating_best DECIMAL(3,2) NOT NULL DEFAULT 5,
    rating_worst DECIMAL(3,2) NOT NULL DEFAULT 1,

    -- Publisher organization
    publisher_enabled TINYINT(1) NOT NULL DEFAULT 1,
    publisher_name VARCHAR(255) DEFAULT NULL,
    publisher_url VARCHAR(500) DEFAULT NULL,
    publisher_logo_url VARCHAR(500) DEFAULT NULL,
    publisher_logo_width INT NOT NULL DEFAULT 512,
    publisher_logo_height INT NOT NULL DEFAULT 512,

    -- Offers / reviews toggles
    offers_enabled TINYINT(1) NOT NULL DEFAULT 1,
    offers_currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    reviews_enabled TINYINT(1) NOT NULL DEFAULT 1,

    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per pricing plan; rolled up into the schema's AggregateOffer
-- (lowPrice / highPrice / offerCount are calculated from the active rows).
CREATE TABLE IF NOT EXISTS seo_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    url VARCHAR(500) DEFAULT NULL,
    availability VARCHAR(100) NOT NULL DEFAULT 'https://schema.org/InStock',
    price_valid_until DATE DEFAULT NULL,
    quantity_value INT NOT NULL DEFAULT 1,
    unit_code VARCHAR(10) NOT NULL DEFAULT 'MON',
    description VARCHAR(500) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per Review JSON-LD block.
CREATE TABLE IF NOT EXISTS seo_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(255) NOT NULL,
    author_type VARCHAR(30) NOT NULL DEFAULT 'Person',
    rating_value DECIMAL(3,2) NOT NULL DEFAULT 5,
    best_rating DECIMAL(3,2) NOT NULL DEFAULT 5,
    worst_rating DECIMAL(3,2) NOT NULL DEFAULT 1,
    review_body TEXT NOT NULL,
    item_name VARCHAR(255) DEFAULT NULL,
    item_url VARCHAR(500) DEFAULT NULL,
    item_operating_system VARCHAR(100) DEFAULT 'All',
    item_application_category VARCHAR(100) DEFAULT 'DesignApplication',
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== User Settings: BYOK, Teams, Social Login, Tokens =====================

-- BYOK: a user's own AI provider credentials (OpenRouter), used instead of the admin's
-- configured models when enabled. Falls back automatically to the admin's model if the
-- user's own key errors out or hits a limit (see ai_generate_text()/ai_generate_image()).
CREATE TABLE IF NOT EXISTS user_ai_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    text_enabled TINYINT(1) NOT NULL DEFAULT 0,
    text_provider VARCHAR(50) NOT NULL DEFAULT 'openrouter',
    text_api_key VARCHAR(500) DEFAULT NULL,
    text_model VARCHAR(255) DEFAULT NULL,
    image_enabled TINYINT(1) NOT NULL DEFAULT 0,
    image_provider VARCHAR(50) NOT NULL DEFAULT 'openrouter',
    image_api_key VARCHAR(500) DEFAULT NULL,
    image_model VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Team membership: a user (owner_id) invites others by email to share their account's
-- resources (credits, BYOK AI keys). A member never sees the owner's or other members'
-- data — they just get resource access (credits/AI keys) while working under their own login.
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    member_user_id INT DEFAULT NULL,
    invited_email VARCHAR(150) NOT NULL,
    status ENUM('pending','active','removed') NOT NULL DEFAULT 'pending',
    invited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    joined_at DATETIME DEFAULT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY owner_email (owner_id, invited_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per (user, provider) social account connection (login and/or "Connect Google account").
CREATE TABLE IF NOT EXISTS user_oauth_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider ENUM('google','facebook','microsoft','pinterest') NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255) DEFAULT NULL,
    connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY user_provider (user_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password-reset / email-verification / email-change tokens.
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('password_reset','email_verify','email_change') NOT NULL,
    token VARCHAR(100) NOT NULL,
    payload VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY token_idx (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Plan Pricing: plans, features, coupons, payments, contact sales =====================

-- One row per subscription plan (including the one allowed "Free Plan").
CREATE TABLE IF NOT EXISTS pricing_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_free TINYINT(1) NOT NULL DEFAULT 0,
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_monthly DECIMAL(5,2) NOT NULL DEFAULT 0,
    discount_yearly DECIMAL(5,2) NOT NULL DEFAULT 0,
    short_description VARCHAR(500) DEFAULT NULL,
    tag VARCHAR(30) DEFAULT NULL,               -- e.g. popular, recommended, budget, best_value, new
    tag_color VARCHAR(20) DEFAULT NULL,          -- one of a small preset palette, picked in Create Plan
    pay_button_text VARCHAR(60) NOT NULL DEFAULT 'Choose Plan',
    pay_button_bg VARCHAR(20) DEFAULT '#e60023',
    pay_button_text_color VARCHAR(20) DEFAULT '#ffffff',
    pay_button_border_color VARCHAR(20) DEFAULT '#e60023',
    buy_button_position ENUM('top','bottom','both') NOT NULL DEFAULT 'bottom',
    -- Feature limits (NULL = unlimited for the *_limit columns below)
    image_ai_credits_monthly INT NOT NULL DEFAULT 0,
    text_ai_credits_monthly INT NOT NULL DEFAULT 0,
    pin_scheduling_monthly_limit INT DEFAULT NULL,
    pin_scheduling_daily_limit INT DEFAULT NULL,
    pinterest_accounts_limit INT DEFAULT NULL,
    websites_limit INT DEFAULT NULL,
    upload_pins_limit INT DEFAULT NULL,
    bulk_scheduling_enabled TINYINT(1) NOT NULL DEFAULT 0,
    invite_team_members_limit INT NOT NULL DEFAULT 0,
    auto_website_daily_pin_enabled TINYINT(1) NOT NULL DEFAULT 0,
    auto_article_enabled TINYINT(1) NOT NULL DEFAULT 0,
    single_article_writer_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cloud_storage_mb INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The bullet-point feature-list rows shown on a plan's pricing card (separate from the
-- structured limits above, which drive enforcement — these rows are just display text).
CREATE TABLE IF NOT EXISTS plan_feature_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    text VARCHAR(255) NOT NULL,
    tooltip VARCHAR(255) DEFAULT NULL,
    checkmark_type VARCHAR(20) NOT NULL DEFAULT 'check',   -- check, check-circle, star, bolt, dot
    text_size VARCHAR(10) NOT NULL DEFAULT '14px',
    text_color VARCHAR(20) NOT NULL DEFAULT '#1a1a1a',
    font VARCHAR(60) DEFAULT NULL,
    checkmark_size VARCHAR(10) NOT NULL DEFAULT '16px',
    checkmark_color VARCHAR(20) NOT NULL DEFAULT '#16a34a',
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin-added custom payment methods (bank transfer, Easypaisa, JazzCash, etc.) — a user
-- pays manually and uploads a screenshot; admin approves/rejects from Plan Pricing → Users.
CREATE TABLE IF NOT EXISTS custom_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    details_html LONGTEXT DEFAULT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    end_date DATE DEFAULT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    apply_to_all_plans TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupon_plans (
    coupon_id INT NOT NULL,
    plan_id INT NOT NULL,
    PRIMARY KEY (coupon_id, plan_id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per successful redemption, for "total users used" on the Coupons list.
CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    redeemed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per checkout attempt/payment (both gateway and custom/manual methods) — this is
-- the source of truth for revenue reporting and for the Users list's payment/approval column.
CREATE TABLE IF NOT EXISTS plan_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    coupon_id INT DEFAULT NULL,
    payment_method VARCHAR(40) NOT NULL,          -- stripe, paypal, nowpayments, binance, or custom:<id>
    custom_payment_method_id INT DEFAULT NULL,
    proof_screenshot_path VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
    admin_message VARCHAR(500) DEFAULT NULL,
    gateway_reference VARCHAR(255) DEFAULT NULL,   -- e.g. Stripe session/charge id
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_sales_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(40) DEFAULT NULL,
    email VARCHAR(150) NOT NULL,
    budget VARCHAR(100) DEFAULT NULL,
    message TEXT NOT NULL,
    viewed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Header bell-icon notifications (renewal reminders, payment approvals, etc.).
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'general',
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Affiliate Program =====================
-- User side: Affiliate → Dashboard (link, clicks, referrals, sales) and Affiliate →
-- Payouts (balance, payout methods, withdrawal requests). Admin side: Affiliate →
-- Dashboard (all affiliates + per-affiliate analytics), Settings (commission rate,
-- duration, threshold, payout methods on/off), Payouts (approve/reject/mark paid).

-- Single-row: admin-configured commission rate, commission window, payout threshold,
-- and which payout methods are offered to affiliates.
CREATE TABLE IF NOT EXISTS affiliate_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_enabled TINYINT(1) NOT NULL DEFAULT 1,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 20,
    -- 'lifetime': every future payment from a referred user earns commission forever.
    -- 'custom': only payments made within duration_months of the referral's signup date
    -- earn commission (e.g. 1 for one month, 12/24/36 for a 1/2/3-year window on annual plans).
    duration_type ENUM('lifetime','custom') NOT NULL DEFAULT 'lifetime',
    duration_months INT DEFAULT NULL,
    min_payout_threshold DECIMAL(10,2) NOT NULL DEFAULT 50,
    cookie_days INT NOT NULL DEFAULT 60,
    paypal_enabled TINYINT(1) NOT NULL DEFAULT 1,
    crypto_enabled TINYINT(1) NOT NULL DEFAULT 1,
    binance_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per visit to a ?ref=CODE link — powers the affiliate's "Total Clicks" stat.
CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    landing_url VARCHAR(500) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per signed-up user who arrived via an affiliate link. status flips to
-- 'customer' the first time an approved paid-plan payment is recorded for them.
CREATE TABLE IF NOT EXISTS affiliate_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    referred_user_id INT NOT NULL UNIQUE,
    ref_code_used VARCHAR(30) DEFAULT NULL,
    status ENUM('pending','customer') NOT NULL DEFAULT 'pending',
    first_purchase_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per commissioned sale — created whenever an admin approves a referred user's
-- plan_payments row (initial purchase OR a later renewal payment), as long as that
-- payment falls inside the commission window (lifetime, or affiliate_settings'
-- duration_months counted from the referral's own signup date).
CREATE TABLE IF NOT EXISTS affiliate_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_user_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    plan_payment_id INT DEFAULT NULL,
    plan_id INT DEFAULT NULL,
    revenue_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (affiliate_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- An affiliate's saved payout destination per method (PayPal email, crypto wallet
-- address, Binance Pay ID) — shown back to them and snapshotted onto each payout request.
CREATE TABLE IF NOT EXISTS affiliate_payout_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    method ENUM('paypal','crypto','binance') NOT NULL,
    details VARCHAR(500) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_method (user_id, method),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per payout request/withdrawal an affiliate makes. Admin → Affiliate →
-- Payouts approves/rejects, then marks it paid once actually sent.
CREATE TABLE IF NOT EXISTS affiliate_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    method ENUM('paypal','crypto','binance') NOT NULL,
    payment_details VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Site Footer (branding, social, editable menu) =====================

-- Single-row table: footer logo/description/social links and the free-text
-- line shown at the very bottom of the footer (defaults to a copyright line
-- when left blank). Admin -> Footer Settings.
CREATE TABLE IF NOT EXISTS footer_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logo_path VARCHAR(500) DEFAULT NULL,
    logo_text VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    social_pinterest VARCHAR(500) DEFAULT NULL,
    social_instagram VARCHAR(500) DEFAULT NULL,
    social_facebook VARCHAR(500) DEFAULT NULL,
    social_linkedin VARCHAR(500) DEFAULT NULL,
    social_x VARCHAR(500) DEFAULT NULL,
    social_youtube VARCHAR(500) DEFAULT NULL,
    social_tiktok VARCHAR(500) DEFAULT NULL,
    footer_text TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per footer column (Product, Company, Free Tools, Comparisons,
-- Use Cases by default). Heading text and order are editable; columns with
-- no items yet still render their heading.
CREATE TABLE IF NOT EXISTS footer_menu_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    heading VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per link inside a footer column.
CREATE TABLE IF NOT EXISTS footer_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column_id INT NOT NULL,
    label VARCHAR(150) NOT NULL,
    url VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (column_id) REFERENCES footer_menu_columns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Contact Us =====================

-- Single-row table: support email/WhatsApp/address shown on the public
-- Contact page. Admin -> Contacts -> Settings.
CREATE TABLE IF NOT EXISTS contact_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_email VARCHAR(150) DEFAULT NULL,
    whatsapp_number VARCHAR(50) DEFAULT NULL,
    whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    address TEXT DEFAULT NULL,
    intro_text TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per submission of the public Contact Us form.
CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new','read') NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Tutorials =====================

-- Video tutorials shown on the public /tutorials.php page and linked from the
-- user panel (topbar "Tutorials" button + sidebar "Tutorials" link).
-- Admin -> Tutorials manages this list.
CREATE TABLE IF NOT EXISTS tutorials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    video_url VARCHAR(500) NOT NULL,
    video_id VARCHAR(50) DEFAULT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    duration VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    view_count INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Blog (Growth Guide / Help / Use Cases) =====================

-- Blog categories double as the top-level URL segment for their posts, e.g.
-- /growth-guide is the category archive and /growth-guide/some-post is a post
-- inside it. Admin -> Blog Post -> Category manages this list. Three default
-- categories are seeded below to back the header menu (Growth Guide, Use
-- Cases, Help) — see also migrate.php which seeds them for existing installs.
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_blog_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO blog_categories (name, slug) VALUES
    ('Growth Guide', 'growth-guide'),
    ('Help', 'help'),
    ('Compare', 'compare');

-- Blog posts. Public URL is /{category slug}/{post slug} (see .htaccess).
-- Admin -> Blog Post manages this list; the public archive is /blog.php
-- (also reachable at /blog and /blog/{category}) and single posts render
-- through /blog-post.php.
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    slug VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    feature_image VARCHAR(500) DEFAULT NULL,
    content LONGTEXT,
    editor_mode ENUM('ckeditor','html') NOT NULL DEFAULT 'ckeditor',
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    author VARCHAR(150) DEFAULT NULL,
    view_count INT NOT NULL DEFAULT 0,
    published_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_blog_post_slug (slug),
    KEY idx_blog_post_category (category_id),
    CONSTRAINT fk_blog_post_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Pinterest Analytics (User -> Analytics -> Pinterest Analytics) =====================
-- Daily account-level metrics. Pinterest only serves the last 90 days, so every fetch is
-- upserted here - over time this builds a longer history (used for 90-day previous-period
-- comparisons and custom ranges older than 90 days).
CREATE TABLE IF NOT EXISTS pa_account_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinterest_account_id INT NOT NULL,
    metric_date DATE NOT NULL,
    impressions BIGINT NOT NULL DEFAULT 0,
    pin_clicks BIGINT NOT NULL DEFAULT 0,
    outbound_clicks BIGINT NOT NULL DEFAULT 0,
    saves BIGINT NOT NULL DEFAULT 0,
    data_status VARCHAR(40) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pa_daily (pinterest_account_id, metric_date),
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cached pin list + last-90-day and lifetime metrics, used by the Top Pins tab.
-- is_own = 1 for pins on the account's own boards, 0 for pins only seen via
-- Pinterest's top-pins report (e.g. saves of your claimed-website content by others).
CREATE TABLE IF NOT EXISTS pa_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinterest_account_id INT NOT NULL,
    pin_id VARCHAR(100) NOT NULL,
    title VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    link VARCHAR(1000) DEFAULT NULL,
    image_url VARCHAR(1000) DEFAULT NULL,
    board_id VARCHAR(100) DEFAULT NULL,
    pin_created_at DATETIME DEFAULT NULL,
    is_own TINYINT(1) NOT NULL DEFAULT 1,
    imp_90 BIGINT NOT NULL DEFAULT 0,
    clicks_90 BIGINT NOT NULL DEFAULT 0,
    outbound_90 BIGINT NOT NULL DEFAULT 0,
    saves_90 BIGINT DEFAULT NULL,
    imp_life BIGINT DEFAULT NULL,
    clicks_life BIGINT DEFAULT NULL,
    outbound_life BIGINT DEFAULT NULL,
    saves_life BIGINT DEFAULT NULL,
    synced_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_pa_pin (pinterest_account_id, pin_id),
    KEY idx_pa_pin_imp (pinterest_account_id, imp_90),
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-account sync bookkeeping (when the daily metrics / pin list were last refreshed).
CREATE TABLE IF NOT EXISTS pa_sync_state (
    pinterest_account_id INT PRIMARY KEY,
    daily_synced_at DATETIME DEFAULT NULL,
    pins_sync_started_at DATETIME DEFAULT NULL,
    pins_synced_at DATETIME DEFAULT NULL,
    pins_count INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Short-lived cache of raw API payloads (per-pin 90-day graphs) to protect the rate limit.
CREATE TABLE IF NOT EXISTS pa_api_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinterest_account_id INT NOT NULL,
    cache_key VARCHAR(191) NOT NULL,
    payload LONGTEXT,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pa_cache (pinterest_account_id, cache_key),
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per pin created through "Regenerate Similar" - links the source Pinterest pin
-- to the new scheduled_pins row, so Top Pins can show how often a pin was regenerated.
CREATE TABLE IF NOT EXISTS pa_regenerated_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pinterest_account_id INT NOT NULL,
    source_pin_id VARCHAR(100) NOT NULL,
    scheduled_pin_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pa_regen_src (pinterest_account_id, source_pin_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pinterest Analytics -> Trends: per-pin daily metrics. Pinterest serves 90 days, and every
-- fetch is upserted here, so longer comparisons (90d vs the previous 90d) become possible
-- as history builds up.
CREATE TABLE IF NOT EXISTS pa_pin_daily (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pinterest_account_id INT NOT NULL,
    pin_id VARCHAR(100) NOT NULL,
    metric_date DATE NOT NULL,
    impressions INT NOT NULL DEFAULT 0,
    pin_clicks INT NOT NULL DEFAULT 0,
    outbound_clicks INT NOT NULL DEFAULT 0,
    saves INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_pa_pin_day (pinterest_account_id, pin_id, metric_date),
    KEY idx_pa_pin_day_date (pinterest_account_id, metric_date),
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pinterest Analytics -> Delete Underperforming Pins: pins the user queued for deletion,
-- and the history of what was actually deleted from Pinterest.
CREATE TABLE IF NOT EXISTS pa_delete_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pinterest_account_id INT NOT NULL,
    pin_id VARCHAR(100) NOT NULL,
    title VARCHAR(500) DEFAULT NULL,
    image_url VARCHAR(1000) DEFAULT NULL,
    link VARCHAR(1000) DEFAULT NULL,
    impressions BIGINT DEFAULT NULL,
    outbound_clicks BIGINT DEFAULT NULL,
    saves BIGINT DEFAULT NULL,
    status ENUM('queued','deleted','failed') NOT NULL DEFAULT 'queued',
    last_error TEXT DEFAULT NULL,
    queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_pa_delq (pinterest_account_id, pin_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pinterest Analytics -> Regen Draft: a pin the user regenerated (AI text and/or image) but
-- did not publish or schedule yet. Opening a draft reloads it into the Regenerate popup.
CREATE TABLE IF NOT EXISTS pa_regen_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pinterest_account_id INT NOT NULL,
    source_pin_id VARCHAR(100) DEFAULT NULL,
    source_json TEXT DEFAULT NULL,
    title VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    link VARCHAR(1000) DEFAULT NULL,
    alt_text VARCHAR(500) DEFAULT NULL,
    keywords VARCHAR(500) DEFAULT NULL,
    image_mode ENUM('ai','original') NOT NULL DEFAULT 'ai',
    image_path VARCHAR(500) DEFAULT NULL,
    board VARCHAR(50) DEFAULT NULL,
    new_board_name VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pa_draft_acc (pinterest_account_id, updated_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pinterest_account_id) REFERENCES pinterest_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Image categories (Admin → Image Categories): main categories (parent_id NULL) and their
-- subcategories. Users pick one in every pin-image form so the AI image matches the niche.
CREATE TABLE IF NOT EXISTS image_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_imgcat_parent (parent_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which image model each quality tier uses, per feature (Admin → AI settings pages).
-- feature: bulk_pin | website_pin | article_pin | article_feature | article_content
-- quality: low | medium | high (= the user's Budget / High / Ultra).
CREATE TABLE IF NOT EXISTS image_model_settings (
    feature VARCHAR(40) NOT NULL,
    quality VARCHAR(10) NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'deepinfra',
    model VARCHAR(160) DEFAULT NULL,
    steps INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (feature, quality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Classic Wizard v2 (design → schedule → generate & review) =====================
-- One row per wizard run. Stays 'draft' until the user clicks "Approve & schedule now";
-- a run the user leaves without approving is listed under Classic Wizard -> Drafts.
CREATE TABLE IF NOT EXISTS cw_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    site_url VARCHAR(500) DEFAULT NULL,
    crawl_site_id INT DEFAULT NULL,
    pinterest_account_id INT DEFAULT NULL,
    config_json LONGTEXT DEFAULT NULL,
    pages_json LONGTEXT DEFAULT NULL,
    status ENUM('draft','scheduled') NOT NULL DEFAULT 'draft',
    pin_batch_id VARCHAR(64) DEFAULT NULL,
    total_pins INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scheduled_at DATETIME DEFAULT NULL,
    KEY cwp_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every generated pin of a wizard run. design_json keeps everything needed to re-render
-- the pin in the browser (template, palette, fonts, size, layout, source images, overlay text).
CREATE TABLE IF NOT EXISTS cw_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    page_key VARCHAR(40) NOT NULL,
    page_url VARCHAR(700) NOT NULL,
    page_title VARCHAR(255) DEFAULT NULL,
    pin_index INT NOT NULL DEFAULT 0,
    image_path VARCHAR(500) NOT NULL,
    template_id VARCHAR(40) DEFAULT NULL,
    design_json LONGTEXT DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    alt_text VARCHAR(500) DEFAULT NULL,
    keywords VARCHAR(500) DEFAULT NULL,
    board_row_id INT DEFAULT NULL,
    board_name VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','scheduled') NOT NULL DEFAULT 'draft',
    scheduled_pin_id INT DEFAULT NULL,
    publish_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY cwpin_project (project_id, page_key),
    FOREIGN KEY (project_id) REFERENCES cw_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Designs a user imported from Canva (exported as SVG). {{title}}, {{kicker}}, {{cta}} and
-- {{website}} text in the SVG is replaced per pin; an element with id "photo" marks where the
-- page image goes (otherwise the image fills the whole pin behind the design).
CREATE TABLE IF NOT EXISTS cw_custom_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    svg_content LONGTEXT NOT NULL,
    text_position ENUM('none','top','center','bottom') NOT NULL DEFAULT 'none',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Site-wide key/value settings =====================
-- Admin login URL and similar one-off settings.
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value MEDIUMTEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin → Notifications: one row per broadcast (the per-user rows live in `notifications`).
CREATE TABLE IF NOT EXISTS admin_notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    audience ENUM('all','selected') NOT NULL DEFAULT 'all',
    recipient_count INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================== Custom Design editor (Canva-style) =====================
-- A user's saved designs (Fabric.js JSON). is_published = admin shared it as a template for everyone.
CREATE TABLE IF NOT EXISTS user_designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'Untitled design',
    width INT NOT NULL DEFAULT 1000,
    height INT NOT NULL DEFAULT 1500,
    design_json LONGTEXT NOT NULL,
    thumb_path VARCHAR(500) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME DEFAULT NULL,
    template_category VARCHAR(100) DEFAULT NULL,
    share_token VARCHAR(32) DEFAULT NULL UNIQUE,
    shared_at DATETIME DEFAULT NULL,
    share_images TEXT DEFAULT NULL,
    copied_from INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ud_user (user_id, updated_at),
    KEY idx_ud_pub (is_published, published_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Images a user uploaded (or imported from stock photos) for the editor.
CREATE TABLE IF NOT EXISTS design_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    width INT NOT NULL DEFAULT 0,
    height INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_du_user (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Graphics the admin adds for everyone (Admin → Canva → Add Elements): SVG / PNG stickers, icons, illustrations.
CREATE TABLE IF NOT EXISTS design_elements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    element_type VARCHAR(20) NOT NULL DEFAULT 'graphics',
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(10) NOT NULL DEFAULT 'png',
    status ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_de_cat (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin → Articles Schedule → Batch Limits (user_id 0 = default per user, -1 = server total)
CREATE TABLE IF NOT EXISTS article_batch_limits (
    user_id INT NOT NULL PRIMARY KEY,
    max_batches INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
