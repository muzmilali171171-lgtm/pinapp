<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_functions.php';
require_once __DIR__ . '/page_crawler_functions.php';
require_once __DIR__ . '/pricing_functions.php';

/**
 * Auto Website to Daily Pin — scans a website's pages (via the Page Crawler),
 * then generates and schedules a set of pins (default 3, gapped a month apart)
 * for each selected page, at a controlled daily publish pace. Reuses the same
 * resumable, crash-safe, one-step-per-request pattern as Auto Article, and the
 * same pin image/board/scheduling primitives as Bulk Pin Scheduler.
 */

/* ===================== Batches ===================== */

function get_user_website_pin_batches(PDO $pdo, int $userId, ?string $wizardSource = null): array
{
    $sql = "SELECT b.*, cs.site_name, cs.site_url,
        (SELECT COUNT(*) FROM website_pin_pages p WHERE p.batch_id = b.id) AS total_pages,
        (SELECT COUNT(*) FROM website_pin_pages p WHERE p.batch_id = b.id AND p.status = 'scheduled') AS completed_pages,
        (SELECT COUNT(*) FROM website_pin_pages p WHERE p.batch_id = b.id AND p.status = 'failed') AS failed_pages,
        (SELECT COUNT(*) FROM website_pin_pages p WHERE p.batch_id = b.id AND p.status = 'pending_approval') AS pending_approval_pages,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS total_pins,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id AND sp.status = 'published') AS published_pins,
        (SELECT COUNT(*) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id AND sp.status = 'pending') AS pending_pins,
        (SELECT MIN(sp.publish_at) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS first_publish_at,
        (SELECT MAX(sp.publish_at) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS last_publish_at,
        (SELECT COUNT(DISTINCT board_name) FROM scheduled_pins sp WHERE sp.batch_id = b.batch_id) AS total_boards
        FROM website_pin_batches b
        LEFT JOIN crawl_sites cs ON cs.id = b.crawl_site_id
        WHERE b.user_id = ?" . ($wizardSource ? " AND b.wizard_source = ?" : "") . " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($wizardSource ? [$userId, $wizardSource] : [$userId]);
    return $stmt->fetchAll();
}

function get_website_pin_batch(PDO $pdo, string $batchId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT b.*, cs.site_name, cs.site_url FROM website_pin_batches b
        LEFT JOIN crawl_sites cs ON cs.id = b.crawl_site_id
        WHERE b.batch_id = ? AND b.user_id = ?");
    $stmt->execute([$batchId, $userId]);
    return $stmt->fetch() ?: null;
}

function get_website_pin_batch_pages(PDO $pdo, int $batchDbId): array
{
    $stmt = $pdo->prepare("SELECT * FROM website_pin_pages WHERE batch_id = ? ORDER BY id ASC");
    $stmt->execute([$batchDbId]);
    return $stmt->fetchAll();
}

/**
 * Creates a new batch and one website_pin_pages row per selected page. Each
 * $pages entry is either a crawl_pages row (['id','url']) or a CSV row
 * (['url','title','description','alt','keywords']).
 */
function create_website_pin_batch(PDO $pdo, int $userId, array $cfg, array $pages): array
{
    $batchToken = new_batch_id();
    $stmt = $pdo->prepare("INSERT INTO website_pin_batches
        (user_id, batch_id, name, crawl_site_id, pinterest_account_id, pins_per_page, board_mode, board_row_id,
         page_gap_days, daily_pin_count, image_quality, pin_size, image_style, color_palette, cta_mode, cta_text, website_text,
         content_source, status, wizard_source, source_type, template_styles, warmup_enabled, floating_days_enabled,
         floating_times_enabled, floating_minutes, lifetime_limit_per_url, monthly_limit_per_url, no_link_pins, requires_approval)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId, $batchToken, $cfg['name'] ?: null, $cfg['crawl_site_id'], $cfg['pinterest_account_id'],
        max(1, (int)$cfg['pins_per_page']), $cfg['board_mode'], $cfg['board_row_id'] ?: null,
        max(1, (int)$cfg['page_gap_days']), max(1, (int)$cfg['daily_pin_count']), $cfg['image_quality'],
        $cfg['pin_size'], $cfg['image_style'], $cfg['color_palette'] ?? null, $cfg['cta_mode'], $cfg['cta_text'] ?: null, $cfg['website_text'] ?: null,
        $cfg['content_source'],
        $cfg['wizard_source'] ?? 'auto_website',
        $cfg['source_type'] ?? 'ai_image',
        $cfg['template_styles'] ?? null,
        !empty($cfg['warmup_enabled']) ? 1 : 0,
        !empty($cfg['floating_days_enabled']) ? 1 : 0,
        !empty($cfg['floating_times_enabled']) ? 1 : 0,
        max(0, (int)($cfg['floating_minutes'] ?? 5)),
        !empty($cfg['lifetime_limit_per_url']) ? (int)$cfg['lifetime_limit_per_url'] : null,
        !empty($cfg['monthly_limit_per_url']) ? (int)$cfg['monthly_limit_per_url'] : null,
        !empty($cfg['no_link_pins']) ? 1 : 0,
        !empty($cfg['requires_approval']) ? 1 : 0,
    ]);
    $batchDbId = (int)$pdo->lastInsertId();
    // Gap unit (minutes/days), first publish day and first-pin-of-the-day time.
    webtopin_ensure_schema_pinfix($pdo);
    try {
        $pdo->prepare("UPDATE website_pin_batches SET page_gap_unit = ?, page_gap_minutes = ?, start_date = ?, start_time = ? WHERE id = ?")
            ->execute([
                ($cfg['page_gap_unit'] ?? 'days') === 'minutes' ? 'minutes' : 'days',
                !empty($cfg['page_gap_minutes']) ? (int)$cfg['page_gap_minutes'] : null,
                $cfg['start_date'] ?? null,
                $cfg['start_time'] ?? null,
                $batchDbId,
            ]);
    } catch (Throwable $e) { /* columns missing — falls back to the old day-based schedule */ }
    if (!empty($cfg['image_category_id'])) {
        $pdo->prepare("UPDATE website_pin_batches SET image_category_id = ? WHERE id = ?")->execute([(int)$cfg['image_category_id'], $batchDbId]);
    }

    if (($cfg['board_mode'] ?? '') === 'multi_select' && !empty($cfg['board_row_ids'])) {
        $insertBoard = $pdo->prepare("INSERT INTO website_pin_batch_boards (batch_id, board_row_id) VALUES (?, ?)");
        foreach ($cfg['board_row_ids'] as $boardRowId) {
            $insertBoard->execute([$batchDbId, (int)$boardRowId]);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO website_pin_pages
        (batch_id, crawl_page_id, page_url, csv_title, csv_description, csv_alt, csv_keywords, pins_needed, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'queued')");
    foreach ($pages as $p) {
        $stmt->execute([
            $batchDbId, $p['crawl_page_id'] ?? null, $p['url'],
            $p['title'] ?? null, $p['description'] ?? null, $p['alt'] ?? null, $p['keywords'] ?? null,
            max(1, (int)$cfg['pins_per_page']),
        ]);
    }

    // Shopify products / blog posts come with their real title (crawl_pages.meta_title) — use it instead
    // of guessing a title from the URL slug.
    $pdo->prepare("UPDATE website_pin_pages wp JOIN crawl_pages cp ON cp.id = wp.crawl_page_id
        SET wp.page_title = cp.meta_title
        WHERE wp.batch_id = ? AND cp.meta_title IS NOT NULL AND cp.meta_title <> ''")->execute([$batchDbId]);

    return ['ok' => true, 'batch_id' => $batchToken, 'batch_db_id' => $batchDbId, 'count' => count($pages)];
}

/* ===================== Image/model settings (admin-configured, separate from Bulk Pin Scheduler) ===================== */

function website_pin_image_settings(PDO $pdo, array $articleSettings, string $quality): array
{
    // Per-quality model choice (Admin → Auto Website to Daily Pin → Image settings); falls back to the
    // legacy single-model columns until the admin saves that page.
    return image_model_for($pdo, 'website_pin', $quality);
}

/* ===================== AI: N pin variations for ONE page (same intent, different wording) ===================== */

/**
 * Generates $count pin title/description/alt/keyword sets for the SAME page —
 * all sharing that page's topic/intent, main keyword and number, but each with a
 * new title, description and related keywords. All $count pins share one board.
 */
function ai_generate_page_pin_variations(PDO $pdo, string $pageTitle, int $count, string $destLink = ''): array
{
    $articleSettings = get_article_settings($pdo);
    $provider = $articleSettings['wpin_text_provider'] ?? ($articleSettings['pin_text_provider'] ?? ($articleSettings['text_provider'] ?? null));
    $model = $articleSettings['wpin_text_model'] ?? ($articleSettings['pin_text_model'] ?? ($articleSettings['text_model'] ?? null));
    if (!$provider) {
        return ['ok' => false, 'items' => [], 'error' => 'No AI text model is configured for Auto Website to Daily Pin yet.'];
    }

    // Same intent + main keyword (+ the title's number) on every pin, each with its own new
    // title, description and related keywords — see ai_generate_pin_variations().
    return ai_generate_pin_variations($pdo, $pageTitle, $count, $destLink, $provider, $model);
}

/* ===================== Board resolution ===================== */

function resolve_website_page_board(PDO $pdo, int $accountId, array $batch, string $pageTitle): array
{
    if ($batch['board_mode'] === 'existing' && $batch['board_row_id']) {
        return resolve_board_selection($pdo, $accountId, 'row:' . $batch['board_row_id'], '', '');
    }
    if ($batch['board_mode'] === 'multi_select') {
        return resolve_multi_select_board($pdo, (int)$batch['id'], $accountId, $pageTitle);
    }
    $suggestion = ai_generate_board_suggestion($pdo, $pageTitle);
    $name = $suggestion['ok'] ? $suggestion['name'] : board_name_from_title($pageTitle);
    $desc = $suggestion['ok'] ? $suggestion['description'] : '';
    return resolve_board_selection($pdo, $accountId, '__new__', $name, $desc);
}

/**
 * Classic Wizard "Boards To Use" multi-select: picks the best-fitting board from the
 * batch's chosen board pool (website_pin_batch_boards) for this page's title, via a
 * quick AI classification; falls back to a round-robin pick if AI is unavailable.
 */
function resolve_multi_select_board(PDO $pdo, int $batchDbId, int $accountId, string $pageTitle): array
{
    $stmt = $pdo->prepare("SELECT pb.id AS row_id, pb.board_name FROM website_pin_batch_boards wb
        JOIN pinterest_boards pb ON pb.id = wb.board_row_id WHERE wb.batch_id = ? ORDER BY pb.id ASC");
    $stmt->execute([$batchDbId]);
    $pool = $stmt->fetchAll();
    if (empty($pool)) {
        return ['ok' => false, 'board_row_id' => null, 'board_id' => null, 'board_name' => null, 'error' => 'No boards were selected for this schedule.'];
    }
    if (count($pool) === 1) {
        return resolve_board_selection($pdo, $accountId, 'row:' . $pool[0]['row_id'], '', '');
    }

    $articleSettings = get_article_settings($pdo) ?: [];
    $provider = $articleSettings['freetool_text_provider'] ?? ($articleSettings['wpin_text_provider'] ?? null);
    $model = $articleSettings['freetool_text_model'] ?? ($articleSettings['wpin_text_model'] ?? '');
    if ($provider) {
        $names = array_map(fn($b) => $b['board_name'], $pool);
        $systemPrompt = 'You pick the single best-fitting Pinterest board for a pin. Respond with ONLY the exact board name from the list, nothing else.';
        $userPrompt = "Pin title: $pageTitle\nBoards:\n- " . implode("\n- ", $names);
        $result = ai_generate_text($pdo, $provider, $model, $systemPrompt, $userPrompt, 60);
        if ($result['ok']) {
            $picked = trim($result['text']);
            foreach ($pool as $b) {
                if (mb_strtolower($b['board_name']) === mb_strtolower($picked)) {
                    return resolve_board_selection($pdo, $accountId, 'row:' . $b['row_id'], '', '');
                }
            }
        }
    }
    // Fallback: round-robin by a stable hash of the title, so re-runs are deterministic-ish.
    $index = crc32($pageTitle) % count($pool);
    return resolve_board_selection($pdo, $accountId, 'row:' . $pool[$index]['row_id'], '', '');
}

/* ===================== Staged, resumable per-page pipeline ===================== */

function process_website_pin_page_step(PDO $pdo, array $page, array $batch): array
{
    try {
        switch ($page['status']) {
            case 'queued':
            case 'generating_text':
                return step_page_text($pdo, $page, $batch);
            case 'generating_images':
                return step_page_images($pdo, $page, $batch);
            case 'ready':
                return step_page_schedule($pdo, $page, $batch);
            default:
                return ['ok' => true, 'error' => null, 'more' => false];
        }
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")
            ->execute(['Unexpected error: ' . $e->getMessage(), $page['id']]);
        return ['ok' => false, 'error' => $e->getMessage(), 'more' => false];
    }
}

/** Step 1: resolve the page's title (if not already known) + generate N pin text variations. */
function step_page_text(PDO $pdo, array $page, array $batch): array
{
    $claim = $pdo->prepare("UPDATE website_pin_pages SET status = 'generating_text' WHERE id = ?
        AND (status IN ('queued') OR (status = 'generating_text' AND updated_at < NOW() - INTERVAL 10 MINUTE))");
    $claim->execute([$page['id']]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Already being processed.', 'more' => true];
    }

    if ($batch['content_source'] === 'csv' && $page['csv_title']) {
        // User-supplied content — AI only makes the image(s); text is reused as-is for every pin.
        $items = [];
        for ($i = 0; $i < $page['pins_needed']; $i++) {
            $items[] = ['title' => $page['csv_title'], 'description' => $page['csv_description'] ?? '', 'alt_text' => $page['csv_alt'] ?? '', 'keywords' => $page['csv_keywords'] ?? ''];
        }
        $pageTitle = $page['csv_title'];
    } else {
        $pageTitle = $page['page_title'] ?: (extract_title_from_url($page['page_url']) ?: $page['page_url']);

        // Classic Wizard: text generation spends the plan's text-credit pool (same system Auto
        // Article uses), separately from the image-credit pool Auto Website to Daily Pin spends.
        if (($batch['wizard_source'] ?? 'auto_website') === 'classic_wizard') {
            if (get_user_text_credits($pdo, (int)$batch['user_id']) < 1) {
                $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = 'Text AI credits are low — upgrade your plan to get more.' WHERE id = ?")->execute([$page['id']]);
                return ['ok' => false, 'error' => 'Text AI credits are low — upgrade your plan to get more.', 'more' => false];
            }
        }

        $genResult = ai_generate_page_pin_variations($pdo, $pageTitle, (int)$page['pins_needed'], $page['page_url']);
        if (!$genResult['ok']) {
            $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")->execute(['Text: ' . $genResult['error'], $page['id']]);
            return ['ok' => false, 'error' => $genResult['error'], 'more' => false];
        }
        $items = $genResult['items'];
        if (($batch['wizard_source'] ?? 'auto_website') === 'classic_wizard') {
            deduct_text_credits($pdo, (int)$batch['user_id'], 1);
        }
    }

    // Resolve the board once for the whole page — all its pins share one board.
    $accountId = (int)$batch['pinterest_account_id'];
    $boardResolved = resolve_website_page_board($pdo, $accountId, $batch, $pageTitle);
    if (!$boardResolved['ok']) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")->execute(['Board: ' . $boardResolved['error'], $page['id']]);
        return ['ok' => false, 'error' => $boardResolved['error'], 'more' => false];
    }

    $progress = ['items' => $items, 'image_next' => 0, 'images' => []];
    $pdo->prepare("UPDATE website_pin_pages SET page_title = ?, pin_data_json = ?, board_row_id = ?, board_id = ?, board_name = ?, status = 'generating_images' WHERE id = ?")
        ->execute([$pageTitle, json_encode($progress), $boardResolved['board_row_id'], $boardResolved['board_id'], $boardResolved['board_name'], $page['id']]);

    return ['ok' => true, 'error' => null, 'more' => true];
}

/** Step 2 (one call per image): generates the next pin's image -> stays 'generating_images' or advances to 'ready'. */
function step_page_images(PDO $pdo, array $page, array $batch): array
{
    $stmt = $pdo->prepare("SELECT * FROM website_pin_pages WHERE id = ?");
    $stmt->execute([$page['id']]);
    $page = $stmt->fetch();
    if (!$page || $page['status'] !== 'generating_images') {
        return ['ok' => true, 'error' => null, 'more' => true];
    }

    $progress = json_decode($page['pin_data_json'] ?? '', true) ?: ['items' => [], 'image_next' => 0, 'images' => []];
    $n = $progress['image_next'];
    $items = $progress['items'];
    if ($n >= count($items)) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'ready' WHERE id = ?")->execute([$page['id']]);
        return ['ok' => true, 'error' => null, 'more' => true];
    }

    $articleSettings = get_article_settings($pdo) ?: [];
    $imgSettings = website_pin_image_settings($pdo, $articleSettings, $batch['image_quality']);

    $ownerStmt = $pdo->prepare("SELECT user_id FROM website_pin_batches WHERE id = ?");
    $ownerStmt->execute([$page['batch_id']]);
    $userId = (int)$ownerStmt->fetchColumn();

    $style = pin_resolve_style((string)$batch['image_style'], $items[$n]['title']);
    $photoCount = ($batch['source_type'] ?? 'ai_image') === 'page_scan' ? 0 : pin_template_image_count($style);
    if (get_user_image_credits($pdo, $userId) < $imgSettings['cost'] * max(1, $photoCount)) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = 'Image AI credits are low — upgrade your plan to get more.' WHERE id = ?")->execute([$page['id']]);
        return ['ok' => false, 'error' => 'Image AI credits are low — upgrade your plan to get more.', 'more' => false];
    }

    $ctaText = $batch['cta_mode'] === 'none' ? '' : ($batch['cta_mode'] === 'custom' && $batch['cta_text'] ? $batch['cta_text'] : auto_pick_cta($items[$n]['title']));
    $website = $batch['website_text'] ?: '';

    // Short, unique overlay headline + category-aware photo scene (one text-AI step). Page-scan pins only
    // need the headline — their photos are the site's own images.
    $isPageScan = ($batch['source_type'] ?? 'ai_image') === 'page_scan';
    $brief = pin_prepare_image_brief($pdo, $items[$n]['title'], image_category_path($pdo, (int)($batch['image_category_id'] ?? 0)), '', true, !$isPageScan);
    $overlayTitle = $brief['headline'];

    // ===== Classic Wizard: real page images (scanned + composited), no AI image generation/credits =====
    if (($batch['source_type'] ?? 'ai_image') === 'page_scan') {
        if (empty($progress['scanned_images'])) {
            $scan = scan_page_for_images($page['page_url']);
            if (!$scan['ok'] || empty($scan['images'])) {
                $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")
                    ->execute([$scan['error'] ?? 'No images found on that page.', $page['id']]);
                return ['ok' => false, 'error' => $scan['error'] ?? 'No images found on that page.', 'more' => false];
            }
            $downloaded = [];
            foreach (array_slice($scan['images'], 0, 6) as $imgUrl) {
                $ch = curl_init($imgUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                ]);
                $data = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                if ($data !== false && $httpCode < 300 && $contentType && stripos($contentType, 'image/') === 0) {
                    $downloaded[] = base64_encode($data);
                }
            }
            if (empty($downloaded)) {
                $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = 'Could not download any images from that page.' WHERE id = ?")
                    ->execute([$page['id']]);
                return ['ok' => false, 'error' => 'Could not download any images from that page.', 'more' => false];
            }
            $progress['scanned_images'] = $downloaded;
            $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ? WHERE id = ?")->execute([json_encode($progress), $page['id']]);
        }

        $imageBytesList = array_map('base64_decode', $progress['scanned_images']);
        $styles = !empty($batch['template_styles']) ? array_values(array_filter(explode(',', $batch['template_styles']))) : ['high_attractive_multi'];
        $styleKey = $styles[$n % count($styles)];

        $composited = compose_pin_image($imageBytesList, $overlayTitle, $website, $ctaText, $batch['pin_size'], $styleKey, $batch['color_palette'] ?? null);
        if (!$composited) {
            $progress['image_next'] = $n + 1;
            $more = $progress['image_next'] < count($items);
            $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ?, status = ? WHERE id = ?")
                ->execute([json_encode($progress), $more ? 'generating_images' : 'ready', $page['id']]);
            return ['ok' => false, 'error' => 'Image ' . ($n + 1) . ' composition failed.', 'more' => true];
        }

        $destDir = __DIR__ . '/../uploads/pins/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'wpin_' . bin2hex(random_bytes(8)) . '.jpg';
        file_put_contents($destDir . $filename, $composited);
        // No deduct_image_credits() call — page-scan pins reuse the site's own real images,
        // so only the AI text (already spent in step_page_text) costs credits.

        $progress['images'][$n] = 'uploads/pins/' . $filename;
        $progress['image_next'] = $n + 1;
        $more = $progress['image_next'] < count($items);
        $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ?, status = ? WHERE id = ?")
            ->execute([json_encode($progress), $more ? 'generating_images' : 'ready', $page['id']]);

        return ['ok' => true, 'error' => null, 'more' => true];
    }

    // One photo for single templates, several different photos for collage templates.
    $gen = pin_generate_template_images($pdo, $style, $overlayTitle, '', $brief, $batch['pin_size'], (string)$imgSettings['provider'], (string)$imgSettings['model'], (int)$imgSettings['iterations']);
    if (!$gen['ok']) {
        // Don't fail the whole page over one image — record and move on; the page just ends up with fewer pins.
        $progress['image_next'] = $n + 1;
        $more = $progress['image_next'] < count($items);
        $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ?, status = ? WHERE id = ?")
            ->execute([json_encode($progress), $more ? 'generating_images' : 'ready', $page['id']]);
        return ['ok' => false, 'error' => 'Image ' . ($n + 1) . ': ' . $gen['error'], 'more' => true];
    }

    $composited = compose_pin_image($gen['images'], $overlayTitle, $website, $ctaText, $batch['pin_size'], $style, $batch['color_palette'] ?? null);
    if (!$composited) {
        $progress['image_next'] = $n + 1;
        $more = $progress['image_next'] < count($items);
        $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ?, status = ? WHERE id = ?")
            ->execute([json_encode($progress), $more ? 'generating_images' : 'ready', $page['id']]);
        return ['ok' => false, 'error' => 'Image ' . ($n + 1) . ' composition failed.', 'more' => true];
    }

    $destDir = __DIR__ . '/../uploads/pins/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = 'wpin_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($destDir . $filename, $composited);
    deduct_image_credits($pdo, $userId, $imgSettings['cost'] * max(1, $photoCount));

    $progress['images'][$n] = 'uploads/pins/' . $filename;
    $progress['image_next'] = $n + 1;
    $more = $progress['image_next'] < count($items);
    $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ?, status = ? WHERE id = ?")
        ->execute([json_encode($progress), $more ? 'generating_images' : 'ready', $page['id']]);

    return ['ok' => true, 'error' => null, 'more' => true];
}

/**
 * Step 3: schedule this page's pins — one pin_batches row per website_pin_batches
 * (created on first use), pins gapped $page_gap_days apart from each other, each
 * slotted into the next day with room under the batch's daily_pin_count quota.
 */
/** Warmup + floating-days quota for one publish date (Classic Wizard only; plain daily_pin_count otherwise). */
function classic_wizard_daily_quota(array $batch, DateTime $date): int
{
    $base = max(1, (int)$batch['daily_pin_count']);
    if (!empty($batch['warmup_enabled']) && !empty($batch['created_at'])) {
        $start = new DateTime(substr($batch['created_at'], 0, 10));
        $weeksIn = (int)floor(($date->getTimestamp() - $start->getTimestamp()) / (7 * 86400));
        $ramp = [0.25, 0.5, 0.75, 1.0][min(3, max(0, $weeksIn))];
        $base = max(1, (int)ceil($base * $ramp));
    }
    if (!empty($batch['floating_days_enabled'])) {
        // Deterministic ±2 jitter per calendar date, so repeated calls for the same date agree.
        $seed = crc32($date->format('Y-m-d') . '|' . $batch['id']);
        $offset = ($seed % 5) - 2; // -2..+2
        $base = max(1, $base + $offset);
    }
    return $base;
}

function step_page_schedule(PDO $pdo, array $page, array $batch): array
{
    $requiresApproval = !empty($batch['requires_approval']);
    $nextStatus = $requiresApproval ? 'pending_approval' : 'scheduled';
    $claim = $pdo->prepare("UPDATE website_pin_pages SET status = ? WHERE id = ? AND status = 'ready'");
    $claim->execute([$nextStatus, $page['id']]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Already being processed.', 'more' => true];
    }

    $progress = json_decode($page['pin_data_json'] ?? '', true) ?: ['items' => [], 'images' => []];
    $items = $progress['items'];
    $images = $progress['images'];

    // Per-URL lifetime/monthly caps (Classic Wizard "Lifetime pin limit per URL" / "Monthly pin limit per URL").
    $userId = (int)$batch['user_id'];
    if (!empty($batch['lifetime_limit_per_url']) || !empty($batch['monthly_limit_per_url'])) {
        if (!empty($batch['lifetime_limit_per_url'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND dest_link = ?");
            $stmt->execute([$userId, $page['page_url']]);
            $already = (int)$stmt->fetchColumn();
            $room = max(0, (int)$batch['lifetime_limit_per_url'] - $already);
            $items = array_slice($items, 0, $room);
            $images = array_slice($images, 0, $room, true);
        }
        if (!empty($batch['monthly_limit_per_url']) && !empty($items)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE user_id = ? AND dest_link = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$userId, $page['page_url']]);
            $already = (int)$stmt->fetchColumn();
            $room = max(0, (int)$batch['monthly_limit_per_url'] - $already);
            $items = array_slice($items, 0, $room);
            $images = array_slice($images, 0, $room, true);
        }
        if (empty($items)) {
            $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = 'This URL has reached its pin limit.' WHERE id = ?")->execute([$page['id']]);
            return ['ok' => false, 'error' => 'This URL has reached its pin limit.', 'more' => false];
        }
    }

    $pinBatchToken = ensure_website_pin_batches_row($pdo, $batch);

    $dailyQuota = max(1, (int)$batch['daily_pin_count']);
    $gapDays = max(1, (int)$batch['page_gap_days']);
    $floatMinutes = !empty($batch['floating_times_enabled']) ? max(0, (int)$batch['floating_minutes']) : 0;
    $destLink = !empty($batch['no_link_pins']) ? null : $page['page_url'];
    $scheduled = 0;

    $limitCheck = check_pin_scheduling_limit($pdo, $userId, count($items));
    if (!$limitCheck['allowed']) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = ? WHERE id = ?")->execute([$limitCheck['message'], $page['id']]);
        return ['ok' => false, 'error' => $limitCheck['message'], 'more' => false];
    }

    $pending = []; // used only when $requiresApproval — held for the user to review, not yet inserted
    $stmt = $pdo->prepare("INSERT INTO scheduled_pins
        (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, source, batch_id, source_website_page_id, publish_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'website_pin', ?, ?, ?)");

    // Start day/time + gap unit (minutes or days) — new settings; old batches keep today @ 09:00 and day gaps.
    $gapMinutesMode = ($batch['page_gap_unit'] ?? 'days') === 'minutes' && !empty($batch['page_gap_minutes']);
    $gapMinutes = $gapMinutesMode ? max(1, (int)$batch['page_gap_minutes']) : 0;
    [$startH, $startM] = array_map('intval', explode(':', preg_match('/^\d{1,2}:\d{2}$/', (string)($batch['start_time'] ?? '')) ? $batch['start_time'] : '09:00'));
    $baseDate = new DateTime('today');
    if (!empty($batch['start_date'])) {
        $sd = DateTime::createFromFormat('Y-m-d', substr((string)$batch['start_date'], 0, 10));
        if ($sd) { $sd->setTime(0, 0); if ($sd > $baseDate) $baseDate = $sd; }
    }
    $prevAt = null; // previous pin of THIS page (minutes mode)
    $newPinIds = [];
    $dayCount = function (DateTime $d) use ($pdo, $pinBatchToken, &$pending) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM scheduled_pins WHERE batch_id = ? AND DATE(publish_at) = ?");
        $chk->execute([$pinBatchToken, $d->format('Y-m-d')]);
        $n = (int)$chk->fetchColumn();
        foreach ($pending as $pp) if (substr($pp['publish_at'], 0, 10) === $d->format('Y-m-d')) $n++;
        return $n;
    };

    foreach ($items as $i => $item) {
        if (empty($images[$i])) continue; // this one's image failed — skip it, don't schedule a pin with no image

        if ($gapMinutesMode && $prevAt !== null) {
            // Next pin of the same page: exactly $gapMinutes after the previous one (if that day still has room).
            $publishAt = (clone $prevAt)->modify("+$gapMinutes minutes");
            $targetDate = (clone $publishAt)->setTime(0, 0);
            for ($guard = 0; $guard < 60; $guard++) {
                if ($dayCount($targetDate) < classic_wizard_daily_quota($batch, $targetDate)) break;
                $targetDate->modify('+1 day');
                $publishAt = (clone $targetDate)->setTime($startH, $startM);
            }
        } else {
            $targetDate = clone $baseDate;
            if (!$gapMinutesMode) $targetDate->modify('+' . ($i * $gapDays) . ' days');
            $slotIndex = 0;
            $dayQuota = $dailyQuota;
            for ($guard = 0; $guard < 60; $guard++) {
                $dayQuota = classic_wizard_daily_quota($batch, $targetDate);
                $used = $dayCount($targetDate);
                if ($used < $dayQuota) { $slotIndex = $used; break; }
                $targetDate->modify('+1 day');
            }
            $gapHours = 24 / max(1, $dailyQuota);
            $publishAt = (clone $targetDate)->setTime($startH, $startM)->modify('+' . (int)round($slotIndex * $gapHours * 60) . ' minutes');
        }
        if ($floatMinutes > 0) {
            $publishAt->modify((random_int(-$floatMinutes, $floatMinutes)) . ' minutes');
        }
        $prevAt = clone $publishAt;

        if ($requiresApproval) {
            $pending[] = [
                'board_id' => $page['board_id'], 'board_name' => $page['board_name'], 'board_row_id' => $page['board_row_id'],
                'image_path' => $images[$i], 'title' => $item['title'], 'description' => $item['description'],
                'dest_link' => $destLink, 'alt_text' => $item['alt_text'], 'keywords' => $item['keywords'],
                'publish_at' => $publishAt->format('Y-m-d H:i:s'),
            ];
        } else {
            $stmt->execute([
                $userId, $batch['pinterest_account_id'],
                $page['board_id'], $page['board_name'], $page['board_row_id'],
                $images[$i], $item['title'], $item['description'], $destLink, $item['alt_text'],
                $item['keywords'], $pinBatchToken, $page['id'],
                $publishAt->format('Y-m-d H:i:s'),
            ]);
            $newPinIds[] = (int)$pdo->lastInsertId();
        }
        $scheduled++;
    }

    if ($scheduled === 0) {
        $pdo->prepare("UPDATE website_pin_pages SET status = 'failed', last_error = 'All image generations failed for this page.' WHERE id = ?")->execute([$page['id']]);
        return ['ok' => false, 'error' => 'All images failed for this page.', 'more' => false];
    }

    if ($requiresApproval) {
        // Held for review — nothing goes live until approve_website_pin_page() runs.
        $pdo->prepare("UPDATE website_pin_pages SET pin_data_json = ? WHERE id = ?")
            ->execute([json_encode(['items' => $items, 'images' => $images, 'pending_pins' => $pending, 'pin_batch_token' => $pinBatchToken]), $page['id']]);
        log_event($pdo, 'ai', "Classic Wizard: $scheduled pin(s) ready for review — {$page['page_url']}", $userId);
        return ['ok' => true, 'error' => null, 'more' => false];
    }

    $pdo->prepare("UPDATE pin_batches SET total_pins = total_pins + ? WHERE batch_id = ?")->execute([$scheduled, $pinBatchToken]);
    log_event($pdo, 'ai', "Auto Website to Daily Pin: scheduled $scheduled pin(s) for {$page['page_url']}", $userId);
    // Any pin whose time has already passed goes out now instead of waiting for the next cron run.
    if ($newPinIds) publish_overdue_pins_now($pdo, ['ids' => $newPinIds, 'user_id' => $userId], 10);
    return ['ok' => true, 'error' => null, 'more' => false];
}

/**
 * Classic Wizard approval: the user reviewed a 'pending_approval' page and either approves
 * (its held pins are inserted into scheduled_pins for real) or rejects it (discarded, no pins
 * created). Safe to call once per page — a second call on an already-resolved page no-ops.
 */
function approve_website_pin_page(PDO $pdo, int $pageId, int $userId, bool $approve): array
{
    $stmt = $pdo->prepare("SELECT p.*, b.pinterest_account_id, b.user_id FROM website_pin_pages p
        JOIN website_pin_batches b ON b.id = p.batch_id WHERE p.id = ? AND b.user_id = ?");
    $stmt->execute([$pageId, $userId]);
    $page = $stmt->fetch();
    if (!$page || $page['status'] !== 'pending_approval') {
        return ['ok' => false, 'error' => 'Nothing to approve.'];
    }

    $claim = $pdo->prepare("UPDATE website_pin_pages SET status = ? WHERE id = ? AND status = 'pending_approval'");
    $claim->execute([$approve ? 'scheduled' : 'failed', $pageId]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Already resolved.'];
    }

    if (!$approve) {
        $pdo->prepare("UPDATE website_pin_pages SET last_error = 'Rejected by user.' WHERE id = ?")->execute([$pageId]);
        return ['ok' => true, 'error' => null, 'scheduled' => 0];
    }

    $progress = json_decode($page['pin_data_json'] ?? '', true) ?: [];
    $pending = $progress['pending_pins'] ?? [];
    $pinBatchToken = $progress['pin_batch_token'] ?? null;
    if (empty($pending) || !$pinBatchToken) {
        return ['ok' => false, 'error' => 'No held pins found for this page.'];
    }

    $stmt = $pdo->prepare("INSERT INTO scheduled_pins
        (user_id, pinterest_account_id, board_id, board_name, board_row_id, image_path, title, description, dest_link, alt_text, keywords, source, batch_id, source_website_page_id, publish_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'website_pin', ?, ?, ?)");
    $approvedIds = [];
    foreach ($pending as $p) {
        $stmt->execute([
            $userId, $page['pinterest_account_id'],
            $p['board_id'], $p['board_name'], $p['board_row_id'],
            $p['image_path'], $p['title'], $p['description'], $p['dest_link'], $p['alt_text'],
            $p['keywords'], $pinBatchToken, $pageId, $p['publish_at'],
        ]);
        $approvedIds[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("UPDATE pin_batches SET total_pins = total_pins + ? WHERE batch_id = ?")->execute([count($pending), $pinBatchToken]);
    log_event($pdo, 'ai', 'Classic Wizard: ' . count($pending) . " pin(s) approved for {$page['page_url']}", $userId);
    if ($approvedIds) publish_overdue_pins_now($pdo, ['ids' => $approvedIds, 'user_id' => $userId], 10);
    return ['ok' => true, 'error' => null, 'scheduled' => count($pending)];
}

/** Creates (once) or reuses the pin_batches row this whole website-pin batch's pins are grouped under. */
function ensure_website_pin_batches_row(PDO $pdo, array $batch): string
{
    $stmt = $pdo->prepare("SELECT batch_id FROM pin_batches WHERE batch_id = ?");
    $stmt->execute([$batch['batch_id']]);
    if ($stmt->fetchColumn()) return $batch['batch_id'];

    $pdo->prepare("INSERT INTO pin_batches (user_id, batch_id, name, pinterest_account_id, status, scheduled_at)
        VALUES (?, ?, ?, ?, 'active', NOW())")
        ->execute([$batch['user_id'], $batch['batch_id'], 'Auto Website to Daily Pin: ' . ($batch['name'] ?: $batch['batch_id']), $batch['pinterest_account_id']]);
    return $batch['batch_id'];
}

/* ===================== Batches side by side (cron + tick + workers) ===================== */

/**
 * Every Auto Website to Daily Pin / Classic Wizard batch runs on its own — a new batch never waits
 * for another batch (the same user's or another user's) to finish:
 *   - each batch with due pages gets its own background worker (cron/website-pin-worker.php);
 *   - a per-batch lock file makes sure only one process works on a batch at a time;
 *   - the cron / runner fallback loop goes round-robin over the batches (one step per batch in turn).
 * Limits (Admin → All Pins Scheduled → Batch Limits): each user runs at most their "batches at once"
 * (default 10); their other batches wait and start as soon as one finishes. The whole server runs at
 * most the "total at once" (default 10,000).
 */
if (!defined('WEBSITE_PIN_MAX_PARALLEL_BATCHES')) define('WEBSITE_PIN_MAX_PARALLEL_BATCHES', 10000);
if (!defined('WEBSITE_PIN_DEFAULT_BATCHES_PER_USER')) define('WEBSITE_PIN_DEFAULT_BATCHES_PER_USER', 10);
if (!defined('BATCH_LIMIT_MAX')) define('BATCH_LIMIT_MAX', 100000);
if (!defined('BATCH_WORKERS_PER_KICK')) define('BATCH_WORKERS_PER_KICK', 500);

/** Table website_pin_batch_limits: user_id 0 = default per user, -1 = server total, > 0 = that user's own limit. */
function website_pin_batch_limits_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS website_pin_batch_limits (
            user_id INT NOT NULL PRIMARY KEY,
            max_batches INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* created by migrate.php */ }
}

/** All saved limits: [user_id => max_batches] (includes the 0 / -1 rows). */
function website_pin_batch_limits_all(PDO $pdo): array
{
    website_pin_batch_limits_ensure_schema($pdo);
    try {
        return array_map('intval', $pdo->query("SELECT user_id, max_batches FROM website_pin_batch_limits")->fetchAll(PDO::FETCH_KEY_PAIR));
    } catch (Throwable $e) {
        return [];
    }
}

function website_pin_batch_limit_default(array $limits): int
{
    return max(1, (int)($limits[0] ?? WEBSITE_PIN_DEFAULT_BATCHES_PER_USER));
}

function website_pin_batch_limit_total(array $limits): int
{
    return max(1, (int)($limits[-1] ?? WEBSITE_PIN_MAX_PARALLEL_BATCHES));
}

function website_pin_batch_limit_for_user(array $limits, int $userId): int
{
    return isset($limits[$userId]) && $limits[$userId] > 0 ? (int)$limits[$userId] : website_pin_batch_limit_default($limits);
}

/** Saves one limit row; $max = null removes a user's own limit (back to the default). */
function website_pin_batch_limit_save(PDO $pdo, int $userId, ?int $max): void
{
    website_pin_batch_limits_ensure_schema($pdo);
    if ($max === null || $max <= 0) {
        if ($userId > 0) $pdo->prepare("DELETE FROM website_pin_batch_limits WHERE user_id = ?")->execute([$userId]);
        return;
    }
    $pdo->prepare("INSERT INTO website_pin_batch_limits (user_id, max_batches) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE max_batches = VALUES(max_batches)")->execute([$userId, min(BATCH_LIMIT_MAX, $max)]);
}

/** SQL condition for "this page has work to do right now" (table alias p). A page another process claimed for text less than 10 minutes ago is skipped. */
function website_pin_page_due_sql(): string
{
    return "(p.status IN ('queued', 'generating_images', 'ready')
        OR (p.status = 'generating_text' AND p.updated_at < NOW() - INTERVAL 10 MINUTE))";
}

/** Active batches that have a page due right now, oldest first: [batch_id => user_id]. */
function due_website_pin_batches(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT p.batch_id, b.user_id, MIN(p.id) AS first_id FROM website_pin_pages p
        JOIN website_pin_batches b ON b.id = p.batch_id
        WHERE b.status = 'active' AND " . website_pin_page_due_sql() . "
        GROUP BY p.batch_id, b.user_id
        ORDER BY first_id ASC");
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[(int)$r['batch_id']] = (int)$r['user_id'];
    return $out;
}

/** Due batches that may run right now: each user's oldest N due batches (N = that user's "batches at once"). */
function allowed_website_pin_batch_ids(PDO $pdo, ?array $due = null): array
{
    $limits = website_pin_batch_limits_all($pdo);
    $perUser = [];
    $out = [];
    foreach ($due ?? due_website_pin_batches($pdo) as $batchId => $userId) {
        $perUser[$userId] = ($perUser[$userId] ?? 0) + 1;
        if ($perUser[$userId] <= website_pin_batch_limit_for_user($limits, $userId)) $out[] = $batchId;
    }
    return $out;
}

/** May this batch run right now (inside its user's limit)? Batches with nothing due count as allowed. */
function website_pin_batch_allowed(PDO $pdo, int $batchDbId): bool
{
    $due = due_website_pin_batches($pdo);
    if (!isset($due[$batchDbId])) return true;
    return in_array($batchDbId, allowed_website_pin_batch_ids($pdo, $due), true);
}

/** The next page to step in one batch (pages already mid-pipeline first, then the oldest queued one). */
function next_due_page_for_batch(PDO $pdo, int $batchDbId, array $skipIds = []): ?array
{
    $skip = $skipIds ? ' AND p.id NOT IN (' . implode(',', array_map('intval', $skipIds)) . ')' : '';
    $stmt = $pdo->prepare("SELECT p.* FROM website_pin_pages p
        WHERE p.batch_id = ? AND " . website_pin_page_due_sql() . "$skip
        ORDER BY (p.status = 'queued') ASC, p.id ASC LIMIT 1");
    $stmt->execute([$batchDbId]);
    return $stmt->fetch() ?: null;
}

function website_pin_batch_lock_path(int $batchDbId): string
{
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/.website_pin_batch_' . $batchDbId . '.lock';
}

/** Takes this batch's lock without waiting. Returns the handle, or null if another process is working on the batch. */
function website_pin_batch_lock_try(int $batchDbId)
{
    $h = @fopen(website_pin_batch_lock_path($batchDbId), 'c');
    if (!$h) return null;
    if (!flock($h, LOCK_EX | LOCK_NB)) { fclose($h); return null; }
    return $h;
}

function website_pin_batch_lock_release($h): void
{
    if (!$h) return;
    flock($h, LOCK_UN);
    fclose($h);
}

function website_pin_batch_busy(int $batchDbId): bool
{
    $h = website_pin_batch_lock_try($batchDbId);
    if (!$h) return true;
    website_pin_batch_lock_release($h);
    return false;
}

/** Runs one step for one page and returns its log entry (null if the batch is no longer active). */
function website_pin_run_one_step(PDO $pdo, array $page): ?array
{
    $batchStmt = $pdo->prepare("SELECT * FROM website_pin_batches WHERE id = ?");
    $batchStmt->execute([$page['batch_id']]);
    $batch = $batchStmt->fetch();
    if (!$batch || $batch['status'] !== 'active') return null;
    $result = process_website_pin_page_step($pdo, $page, $batch);
    return ['page_id' => $page['id'], 'url' => $page['page_url'], 'ok' => $result['ok'], 'more' => $result['more'], 'error' => $result['error'] ?? null];
}

/**
 * Runs up to $maxSteps steps across ALL users' due pages, round-robin over the batches (one step for
 * batch A, one for batch B, …), so no batch waits for another. Batches a worker is running are skipped.
 */
function run_due_website_pin_steps(PDO $pdo, int $maxSteps = 6): array
{
    $processed = 0;
    $log = [];
    $seenFail = [];
    $doneBatches = [];
    while ($processed < $maxSteps) {
        ensure_db_connection($pdo);
        $batchIds = array_values(array_diff(allowed_website_pin_batch_ids($pdo), $doneBatches));
        if (!$batchIds) break;
        foreach ($batchIds as $batchDbId) {
            if ($processed >= $maxSteps) break;
            $lock = website_pin_batch_lock_try($batchDbId);
            if (!$lock) { $doneBatches[] = $batchDbId; continue; }   // its own worker is on it
            try {
                $page = next_due_page_for_batch($pdo, $batchDbId, $seenFail);
                if (!$page) { $doneBatches[] = $batchDbId; continue; }
                $entry = website_pin_run_one_step($pdo, $page);
                if (!$entry) { $doneBatches[] = $batchDbId; continue; }
                $processed++;
                if (!$entry['ok'] && $entry['error'] === 'Already being processed.') $seenFail[] = (int)$page['id'];   // no progress — don't spin on it
                $log[] = $entry;
            } finally {
                website_pin_batch_lock_release($lock);
            }
        }
    }
    return ['steps_run' => $processed, 'log' => $log];
}

/**
 * Works through ONE batch until nothing is due or $seconds run out. Caller holds the batch's lock.
 * Returns ['steps_run', 'log', 'more' => bool (time ran out with work still waiting)].
 */
function run_website_pin_batch_steps(PDO $pdo, int $batchDbId, int $seconds = 540): array
{
    $end = time() + $seconds;
    $processed = 0;
    $log = [];
    $seenFail = [];
    while (true) {
        ensure_db_connection($pdo);
        $page = next_due_page_for_batch($pdo, $batchDbId, $seenFail);
        if (!$page) return ['steps_run' => $processed, 'log' => $log, 'more' => false];
        // Over the user's limit (e.g. the admin lowered it): wait for a free slot — only between pages,
        // so a page that was started is always finished first.
        if ($page['status'] === 'queued' && !website_pin_batch_allowed($pdo, $batchDbId)) return ['steps_run' => $processed, 'log' => $log, 'more' => false];
        if (time() >= $end) return ['steps_run' => $processed, 'log' => $log, 'more' => true];
        $entry = website_pin_run_one_step($pdo, $page);
        if (!$entry) return ['steps_run' => $processed, 'log' => $log, 'more' => false];   // batch paused / stopped
        $processed++;
        if (!$entry['ok'] && $entry['error'] === 'Already being processed.') $seenFail[] = (int)$page['id'];   // no progress — don't spin on it
        $log[] = $entry;
    }
}

function website_pin_batch_worker_url(int $batchDbId): string
{
    return rtrim(APP_URL, '/') . '/cron/website-pin-worker.php?key=' . scheduler_web_key() . '&t=' . time() . '&batch=' . $batchDbId;
}

/**
 * Makes sure every due batch has its own worker (inside the per-user and server limits), started
 * together in parallel. $onlyBatch: start just this batch (e.g. right after it was created).
 * Returns how many workers were started.
 */
function website_pin_batch_workers_kick(PDO $pdo, ?int $onlyBatch = null): int
{
    if (function_exists('scheduler_runner_enabled') && !scheduler_runner_enabled()) return 0;
    if (!function_exists('curl_init') || !function_exists('scheduler_web_key') || !function_exists('background_workers_start')) return 0;
    $due = due_website_pin_batches($pdo);
    $allowed = array_flip(allowed_website_pin_batch_ids($pdo, $due));
    $total = website_pin_batch_limit_total(website_pin_batch_limits_all($pdo));
    $running = 0;
    $idle = [];
    foreach (array_keys($due) as $id) {
        if (website_pin_batch_busy($id)) $running++;
        elseif (isset($allowed[$id]) && ($onlyBatch === null || $id === $onlyBatch)) $idle[] = $id;
    }
    $idle = array_slice($idle, 0, max(0, min(BATCH_WORKERS_PER_KICK, $total - $running)));
    return background_workers_start(array_map('website_pin_batch_worker_url', $idle), 'AutomatedPin-WebsitePinWorker');
}

/**
 * Right after a batch is created (response already sent): start its own worker; if workers can't
 * be started (no curl), work on this batch here for a while instead.
 */
function website_pin_batch_start_now(PDO $pdo, int $batchDbId): void
{
    if (website_pin_batch_workers_kick($pdo, $batchDbId)) return;
    $lock = website_pin_batch_lock_try($batchDbId);
    if (!$lock) return;
    try { run_website_pin_batch_steps($pdo, $batchDbId, 240); } catch (Throwable $e) { /* cron / runner continues */ }
    website_pin_batch_lock_release($lock);
}
