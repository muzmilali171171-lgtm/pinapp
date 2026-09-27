<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

$activePage = 'contacts';
$pageTitle = 'Contacts';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare("DELETE FROM contact_submissions WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    redirect('contacts');
}

$view = null;
if (!empty($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $view = $stmt->fetch() ?: null;
    if ($view && $view['status'] === 'new') {
        $pdo->prepare("UPDATE contact_submissions SET status = 'read' WHERE id = ?")->execute([$view['id']]);
        $view['status'] = 'read';
    }
}

$submissions = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 300")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-header"><h1>Contacts</h1></div>

<?php if ($view): ?>
<div class="card">
    <h2>Submission #<?= (int)$view['id'] ?></h2>
    <table>
        <tr><th style="width:140px;">Name</th><td><?= e($view['name']) ?></td></tr>
        <tr><th>Email</th><td><a href="mailto:<?= e($view['email']) ?>"><?= e($view['email']) ?></a></td></tr>
        <?php if (!empty($view['subject'])): ?><tr><th>Subject</th><td><?= e($view['subject']) ?></td></tr><?php endif; ?>
        <tr><th>Message</th><td style="white-space:pre-wrap;"><?= e($view['message']) ?></td></tr>
        <tr><th>Submitted</th><td><?= e(format_datetime($view['created_at'])) ?></td></tr>
        <?php if (!empty($view['ip_address'])): ?><tr><th>IP Address</th><td class="muted"><?= e($view['ip_address']) ?></td></tr><?php endif; ?>
    </table>
    <div style="margin-top:14px;">
        <a href="mailto:<?= e($view['email']) ?>" class="btn-primary btn-small">Reply by Email</a>
        <a href="contacts" class="btn-secondary btn-small">Back to list</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this submission?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
            <button type="submit" class="btn-danger btn-small">Delete</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>All Submissions</h2>
    <?php if (!$submissions): ?>
        <p class="empty-state">No contact form submissions yet.</p>
    <?php else: ?>
    <table>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Submitted</th><th>Status</th><th></th></tr>
        <?php foreach ($submissions as $s): ?>
        <tr>
            <td>#<?= (int)$s['id'] ?></td>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td class="muted"><?= e(format_datetime($s['created_at'])) ?></td>
            <td><span class="badge badge-<?= $s['status'] === 'new' ? 'connected' : 'queued' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
            <td style="white-space:nowrap;">
                <a href="?view=<?= (int)$s['id'] ?>" class="btn-secondary btn-small">View Contact</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this submission?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
