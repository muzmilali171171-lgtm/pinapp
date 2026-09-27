<?php
/**
 * Shared website list table (used by All Websites and each platform page).
 * Expects: $siteRows (websites rows), $returnTo (this page's file name).
 * Optional: $emptyText.
 */
$connectPages = [
    'wordpress' => ['website-wordpress', 'WordPress'],
    'shopify' => ['shopify-stores', 'Shopify'],
    'wix' => ['wix-sites', 'Wix'],
    'custom' => ['custom-websites', 'Custom (webhook)'],
];

$postButton = function (string $action, int $id, string $label, string $class = 'btn-secondary btn-small', string $confirm = '') use ($returnTo) {
    return '<form method="POST" action="website-action"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\');"' : '') . '>'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="return" value="' . e($returnTo) . '">'
        . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
};
?>
<?php if (empty($siteRows)): ?>
    <div class="empty-state"><?= e($emptyText ?? 'No websites yet.') ?></div>
<?php else: ?>
<table>
    <tr><th>ID</th><th>Website URL</th><th>Platform</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($siteRows as $s):
        $sid = (int)$s['id'];
        $platform = $s['platform'] ?? 'wordpress';
        $state = website_connection_state($s);
        $meta = website_meta($s);
    ?>
    <tr>
        <td class="muted">#<?= $sid ?></td>
        <td>
            <a href="<?= e($s['site_url']) ?>" target="_blank" rel="noopener"><?= e($s['site_url']) ?></a>
            <?php if (!empty($s['site_name']) && $s['site_name'] !== $s['site_url']): ?><div class="muted" style="font-size:12px;"><?= e($s['site_name']) ?></div><?php endif; ?>
            <?php if ($platform === 'shopify' && !empty($s['external_id']) && $s['external_id'] !== (parse_url($s['site_url'], PHP_URL_HOST) ?: '')): ?>
                <div class="muted" style="font-size:12px;"><?= e($s['external_id']) ?></div>
            <?php endif; ?>
        </td>
        <td><span class="badge badge-platform-<?= e($platform) ?>"><?= e($platform === 'none' ? 'Not linked' : platform_label($platform)) ?></span></td>
        <td>
            <span class="badge badge-<?= $state === 'connected' ? 'connected' : 'error' ?>"><?= $state === 'connected' ? 'Connected' : 'Unconnected' ?></span>
            <?php if (!empty($meta['warning']) && $state === 'connected'): ?><div class="site-note"><?= e($meta['warning']) ?></div><?php endif; ?>
            <?php if ($state === 'unconnected' && $platform !== 'none'): ?><div class="site-note">Last check failed — click Re-check, or Reconnect with fresh credentials.</div><?php endif; ?>
        </td>
        <td>
            <div class="site-actions">
                <a href="website-automate?id=<?= $sid ?>" class="btn-primary btn-small">Automate Pin</a>

                <?php if ($platform === 'shopify' && $state === 'connected'): ?>
                    <a href="shopify-items?website_id=<?= $sid ?>&type=products" class="btn-secondary btn-small">View Products</a>
                    <a href="shopify-items?website_id=<?= $sid ?>&type=blogs" class="btn-secondary btn-small">View Blogs</a>
                <?php endif; ?>

                <?php if ($platform === 'none'): ?>
                    <details class="connect-menu">
                        <summary class="btn-secondary btn-small">Connect ▾</summary>
                        <div class="connect-menu-list">
                            <?php foreach ($connectPages as [$page, $label]): ?>
                                <a href="<?= e($page) ?>?connect_id=<?= $sid ?>"><?= e($label) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <?= $postButton('recheck', $sid, 'Re-check') ?>
                    <?php if ($state === 'unconnected'): ?>
                        <a href="<?= e($connectPages[$platform][0] ?? 'websites') ?>?connect_id=<?= $sid ?>" class="btn-secondary btn-small">Reconnect</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?= $postButton('delete', $sid, 'Remove', 'btn-secondary btn-small', 'Remove this website from your account?') ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
