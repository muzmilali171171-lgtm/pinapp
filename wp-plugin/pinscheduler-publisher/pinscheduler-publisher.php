<?php
/**
 * Plugin Name: PinScheduler Publisher
 * Description: Lets your VideoConvertly Article Writer / Pinterest Scheduler app publish posts (with featured images, categories, tags, custom slugs, meta descriptions and inline content images) to this WordPress site, via a secure shared-secret REST API. No passwords are shared — only a random site key that you can regenerate any time.
 * Version: 1.2.1
 * Author: VideoConvertly
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit; // no direct access
}

define('PINSCHEDULER_OPTION_KEY', 'pinscheduler_site_key');

/* -------------------------------------------------------------------- */
/* Settings page                                                         */
/* -------------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_options_page(
        'PinScheduler Publisher',
        'PinScheduler Publisher',
        'manage_options',
        'pinscheduler-publisher',
        'pinscheduler_render_settings_page'
    );
});

function pinscheduler_get_or_create_site_key(): string
{
    $key = get_option(PINSCHEDULER_OPTION_KEY);
    if (!$key) {
        $key = wp_generate_password(40, false, false);
        update_option(PINSCHEDULER_OPTION_KEY, $key);
    }
    return $key;
}

function pinscheduler_render_settings_page(): void
{
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['pinscheduler_regenerate']) && check_admin_referer('pinscheduler_regenerate_action')) {
        $newKey = wp_generate_password(40, false, false);
        update_option(PINSCHEDULER_OPTION_KEY, $newKey);
        echo '<div class="notice notice-success"><p>Site key regenerated. Update it in your app too, or the connection will stop working.</p></div>';
    }

    $siteKey = pinscheduler_get_or_create_site_key();
    $siteUrl = get_site_url();
    ?>
    <div class="wrap">
        <h1>PinScheduler Publisher</h1>
        <p>Copy these two values into your app's <strong>Add Websites</strong> page to connect this site.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Site URL</th>
                <td><input type="text" readonly style="width:420px;" value="<?php echo esc_attr($siteUrl); ?>" onclick="this.select();"></td>
            </tr>
            <tr>
                <th scope="row">Site Key</th>
                <td>
                    <input type="text" readonly style="width:420px;" value="<?php echo esc_attr($siteKey); ?>" onclick="this.select();">
                    <p class="description">Treat this like a password. Anyone with this key can publish posts to your site through the app.</p>
                </td>
            </tr>
        </table>

        <form method="POST">
            <?php wp_nonce_field('pinscheduler_regenerate_action'); ?>
            <button type="submit" name="pinscheduler_regenerate" class="button button-secondary"
                onclick="return confirm('This will invalidate the old key. You will need to update it in your app too. Continue?');">
                Regenerate Site Key
            </button>
        </form>

        <hr>
        <h2>Connection test</h2>
        <p>Test URL (open in a new tab — without the key header it will correctly return "Forbidden", that's expected):</p>
        <code><?php echo esc_html(rtrim($siteUrl, '/') . '/wp-json/pinscheduler/v1/ping'); ?></code>
    </div>
    <?php
}

/* -------------------------------------------------------------------- */
/* REST API                                                              */
/* -------------------------------------------------------------------- */

add_action('rest_api_init', function () {
    register_rest_route('pinscheduler/v1', '/ping', [
        'methods' => 'GET',
        'callback' => 'pinscheduler_rest_ping',
        'permission_callback' => 'pinscheduler_verify_key',
    ]);

    register_rest_route('pinscheduler/v1', '/publish', [
        'methods' => 'POST',
        'callback' => 'pinscheduler_rest_publish',
        'permission_callback' => 'pinscheduler_verify_key',
    ]);
});

function pinscheduler_verify_key(WP_REST_Request $request)
{
    $provided = $request->get_header('x-pinscheduler-key');
    $expected = get_option(PINSCHEDULER_OPTION_KEY);
    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        return new WP_Error('forbidden', 'Invalid or missing site key.', ['status' => 403]);
    }
    return true;
}

function pinscheduler_rest_ping(WP_REST_Request $request)
{
    $categories = get_categories(['hide_empty' => false]);
    $tags = get_tags(['hide_empty' => false]);
    // role__in is the reliable, modern way to list users who can author posts — the older
    // who=>authors / has_published_posts combination is fragile across WP/role-plugin setups
    // and could silently return an empty list.
    $authors = get_users(['role__in' => ['administrator', 'editor', 'author', 'contributor'], 'orderby' => 'display_name']);
    if (empty($authors)) {
        // Fallback for sites with fully custom roles: anyone who can actually publish posts.
        $authors = get_users(['capability' => 'publish_posts', 'orderby' => 'display_name']);
    }

    return [
        'ok' => true,
        'site_name' => get_bloginfo('name'),
        'wp_version' => get_bloginfo('version'),
        'categories' => array_map(fn($c) => ['id' => $c->term_id, 'name' => $c->name], $categories),
        'tags' => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], $tags),
        'authors' => array_map(fn($u) => ['id' => $u->ID, 'name' => $u->display_name], $authors),
    ];
}

function pinscheduler_rest_publish(WP_REST_Request $request)
{
    $params = $request->get_json_params();

    $title = sanitize_text_field($params['title'] ?? '');
    $content = wp_kses_post($params['content'] ?? '');
    $categoryName = sanitize_text_field($params['category'] ?? '');
    $tagsCsv = sanitize_text_field($params['tags'] ?? '');
    $status = in_array($params['status'] ?? 'publish', ['publish', 'draft'], true) ? $params['status'] : 'publish';
    $featuredImageBase64 = $params['featured_image_base64'] ?? null;
    $featuredImageFilename = $params['featured_image_filename'] ?? 'featured-image.jpg';
    $slug = sanitize_title($params['slug'] ?? '');
    $metaDescription = sanitize_text_field($params['meta_description'] ?? '');
    $inlineImages = is_array($params['inline_images'] ?? null) ? $params['inline_images'] : [];
    $authorId = (int)($params['author_id'] ?? 0);

    if ($title === '') {
        return new WP_Error('missing_title', 'Title is required.', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $postData = [
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => $status,
        'post_type' => 'post',
    ];
    if ($authorId > 0 && get_userdata($authorId)) {
        $postData['post_author'] = $authorId;
    }
    if ($slug !== '') {
        $postData['post_name'] = $slug;
    }
    if ($metaDescription !== '') {
        // WP's own excerpt is the most universally-read fallback; also write it under
        // Yoast's and RankMath's own meta keys so either plugin (if installed) picks it up.
        $postData['post_excerpt'] = $metaDescription;
    }

    if ($categoryName !== '') {
        $term = term_exists($categoryName, 'category');
        if (!$term) {
            $term = wp_insert_term($categoryName, 'category');
        }
        if (!is_wp_error($term)) {
            $postData['post_category'] = [(int)$term['term_id']];
        }
    }

    $postId = wp_insert_post($postData, true);
    if (is_wp_error($postId)) {
        return new WP_Error('insert_failed', $postId->get_error_message(), ['status' => 500]);
    }

    if ($metaDescription !== '') {
        update_post_meta($postId, '_yoast_wpseo_metadesc', $metaDescription);
        update_post_meta($postId, 'rank_math_description', $metaDescription);
    }

    if ($tagsCsv !== '') {
        $tags = array_filter(array_map('trim', explode(',', $tagsCsv)));
        wp_set_post_tags($postId, $tags, false);
    }

    // Inline content images (recipe/ideas illustrations): upload each to the media
    // library, then swap its placeholder token for the real URL in the saved content.
    if (!empty($inlineImages)) {
        $updatedContent = $content;
        foreach ($inlineImages as $img) {
            $placeholder = $img['placeholder'] ?? '';
            $base64 = $img['base64'] ?? '';
            $filename = $img['filename'] ?? 'article-image.jpg';
            if ($placeholder === '' || $base64 === '') continue;

            $binary = base64_decode($base64);
            $upload = wp_upload_bits($filename, null, $binary);
            if ($upload['error']) continue;

            $filetype = wp_check_filetype($upload['file'], null);
            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit',
            ];
            $attachId = wp_insert_attachment($attachment, $upload['file'], $postId);
            if (is_wp_error($attachId)) continue;
            $attachData = wp_generate_attachment_metadata($attachId, $upload['file']);
            wp_update_attachment_metadata($attachId, $attachData);
            $imageUrl = wp_get_attachment_url($attachId);

            $updatedContent = str_replace($placeholder, esc_url($imageUrl), $updatedContent);
        }
        if ($updatedContent !== $content) {
            wp_update_post(['ID' => $postId, 'post_content' => $updatedContent]);
        }
    }

    if ($featuredImageBase64) {
        $binary = base64_decode($featuredImageBase64);
        $upload = wp_upload_bits($featuredImageFilename, null, $binary);

        if (!$upload['error']) {
            $filetype = wp_check_filetype($upload['file'], null);
            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name($featuredImageFilename),
                'post_content' => '',
                'post_status' => 'inherit',
            ];
            $attachId = wp_insert_attachment($attachment, $upload['file'], $postId);
            if (!is_wp_error($attachId)) {
                $attachData = wp_generate_attachment_metadata($attachId, $upload['file']);
                wp_update_attachment_metadata($attachId, $attachData);
                set_post_thumbnail($postId, $attachId);
            }
        }
    }

    return [
        'ok' => true,
        'id' => $postId,
        'url' => get_permalink($postId),
        'status' => get_post_status($postId),
    ];
}
