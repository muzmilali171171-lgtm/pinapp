<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin-auth.php';
require_admin_login();

// User search for the "selected users" picker.
if (($_GET['ajax'] ?? '') === 'users') {
    header('Content-Type: application/json');
    $q = trim((string)($_GET['q'] ?? ''));
    $s = $pdo->prepare("SELECT id, name, email, status FROM users WHERE name LIKE ? OR email LIKE ? OR id = ? ORDER BY id DESC LIMIT 20");
    $s->execute(["%$q%", "%$q%", ctype_digit($q) ? (int)$q : 0]);
    echo json_encode($s->fetchAll());
    exit;
}

$activePage = 'notifications';
$pageTitle = 'Notifications';
$admin = current_admin($pdo);
$msg = null;
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));
    $audience = ($_POST['audience'] ?? 'all') === 'selected' ? 'selected' : 'all';
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)json_decode((string)($_POST['user_ids'] ?? '[]'), true)))));

    if ($title === '') $errs[] = 'Add a title.';
    if (mb_strlen($title) > 150) $errs[] = 'Keep the title under 150 characters.';
    if (mb_strlen($message) > 500) $errs[] = 'Keep the message under 500 characters.';
    if ($link !== '' && !preg_match('#^(https?://|/)#i', $link)) $errs[] = 'The link must start with https:// or / (e.g. /user/classic-wizard).';
    if ($audience === 'selected' && !$ids) $errs[] = 'Select at least one user, or choose “All users”.';

    if (!$errs) {
        if ($audience === 'all') {
            $recipients = $pdo->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT id FROM users WHERE id IN ($ph)");
            $s->execute($ids);
            $recipients = $s->fetchAll(PDO::FETCH_COLUMN);
        }
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($recipients, 500) as $chunk) {
                $rows = implode(',', array_fill(0, count($chunk), "(?, 'admin', ?, ?, ?)"));
                $args = [];
                foreach ($chunk as $uid) array_push($args, (int)$uid, $title, $message !== '' ? $message : null, $link !== '' ? $link : null);
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES $rows")->execute($args);
            }
            $pdo->prepare("INSERT INTO admin_notification_log (admin_id, title, message, link, audience, recipient_count) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$admin['id'] ?? null, $title, $message ?: null, $link ?: null, $audience, count($recipients)]);
            $pdo->commit();
            $msg = 'Notification sent to ' . number_format(count($recipients)) . ' user' . (count($recipients) === 1 ? '' : 's') . '. They’ll see it under the 🔔 bell in their dashboard.';
            $_POST = [];
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errs[] = 'Could not send: ' . $e->getMessage();
        }
    }
}

$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$history = [];
try { $history = $pdo->query("SELECT * FROM admin_notification_log ORDER BY id DESC LIMIT 30")->fetchAll(); } catch (Throwable $e) {}

include __DIR__ . '/includes/admin-header.php';
?>
<style>
.nt-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; align-items: start; }
.nt-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 22px; }
.nt-card h2 { font-size: 17px; margin: 0 0 14px; }
.nt-aud { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
.nt-aud label { flex: 1; min-width: 160px; border: 2px solid var(--border); border-radius: 12px; padding: 12px; cursor: pointer; }
.nt-aud label:has(input:checked) { border-color: var(--red); background: #fff5f6; }
.nt-aud input { margin-right: 6px; }
.nt-search { position: relative; }
.nt-results { position: absolute; left: 0; right: 0; top: 100%; z-index: 20; background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 12px 30px rgba(0,0,0,.12); max-height: 260px; overflow: auto; }
.nt-results button { display: block; width: 100%; text-align: left; padding: 9px 12px; border: 0; background: none; cursor: pointer; font-size: 13.5px; }
.nt-results button:hover, .nt-results button:focus { background: var(--light); }
.nt-results small { color: var(--gray); }
.nt-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.nt-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff0f1; border: 1px solid #ffd3d8; color: var(--dark); border-radius: 20px; padding: 4px 6px 4px 12px; font-size: 13px; }
.nt-chip button { border: 0; background: var(--red); color: #fff; width: 20px; height: 20px; border-radius: 50%; cursor: pointer; line-height: 1; }
.nt-preview { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.nt-preview strong { display: block; }
.nt-count { float: right; font-weight: 400; font-size: 12px; color: var(--gray); }
@media (max-width: 900px) { .nt-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header"><h1>Notifications</h1></div>
<p class="muted">Send a message to your users. It appears under the 🔔 bell in their dashboard.</p>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php foreach ($errs as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>

<div class="nt-grid">
    <form method="POST" class="nt-card" id="ntForm">
        <h2>New notification</h2>
        <div class="nt-aud" role="radiogroup" aria-label="Send to">
            <label><input type="radio" name="audience" value="all" <?= ($_POST['audience'] ?? 'all') === 'all' ? 'checked' : '' ?>><b>All users</b><br><small class="muted"><?= number_format($totalActive) ?> active users</small></label>
            <label><input type="radio" name="audience" value="selected" <?= ($_POST['audience'] ?? '') === 'selected' ? 'checked' : '' ?>><b>Selected users</b><br><small class="muted">Search and pick users</small></label>
        </div>
        <div id="ntPicker" hidden>
            <div class="form-row nt-search">
                <label for="ntSearch">Search users</label>
                <input type="search" id="ntSearch" placeholder="Name, email or user ID" autocomplete="off">
                <div class="nt-results" id="ntResults" hidden></div>
            </div>
            <div class="nt-chips" id="ntChips"></div>
            <input type="hidden" name="user_ids" id="ntIds" value="[]">
        </div>
        <div class="form-row"><label for="ntTitle">Title <span class="nt-count" id="ntTitleCount">0/150</span></label><input type="text" id="ntTitle" name="title" maxlength="150" required value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. New: 20 recipe pin templates"></div>
        <div class="form-row"><label for="ntMsg">Message <span class="nt-count" id="ntMsgCount">0/500</span></label><textarea id="ntMsg" name="message" rows="4" maxlength="500" placeholder="A short message users will read in the bell dropdown."><?= e($_POST['message'] ?? '') ?></textarea></div>
        <div class="form-row"><label for="ntLink">Link <span class="muted">(optional — where clicking goes)</span></label><input type="text" id="ntLink" name="link" value="<?= e($_POST['link'] ?? '') ?>" placeholder="/user/classic-wizard or https://…"></div>
        <button class="btn-primary" id="ntSend">Send notification</button>
    </form>

    <div>
        <div class="nt-card" style="margin-bottom:16px;">
            <h2>Preview</h2>
            <div class="nt-preview"><strong id="pvTitle">Your title</strong><div class="muted" style="font-size:12px;" id="pvMsg">Your message</div><div class="muted" style="font-size:11px;">Just now</div></div>
        </div>
        <div class="nt-card">
            <h2>Sent</h2>
            <?php if (!$history): ?><p class="muted">Nothing sent yet.</p><?php else: ?>
            <table>
                <tr><th>Title</th><th>To</th><th>Sent</th></tr>
                <?php foreach ($history as $h): ?>
                    <tr><td><strong><?= e($h['title']) ?></strong><?php if ($h['message']): ?><div class="muted" style="font-size:12px;"><?= e(mb_strimwidth($h['message'], 0, 90, '…')) ?></div><?php endif; ?></td>
                        <td><?= $h['audience'] === 'all' ? 'All users' : 'Selected' ?> (<?= number_format($h['recipient_count']) ?>)</td>
                        <td style="white-space:nowrap;"><?= e(format_datetime($h['created_at'])) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var picked = {};
    var $ = function (id) { return document.getElementById(id); };
    function esc(t) { return String(t || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function syncAud() {
        var sel = document.querySelector('input[name=audience]:checked').value === 'selected';
        $('ntPicker').hidden = !sel;
        updateBtn();
    }
    function updateBtn() {
        var sel = document.querySelector('input[name=audience]:checked').value === 'selected';
        var n = Object.keys(picked).length;
        $('ntSend').textContent = sel ? 'Send to ' + n + ' user' + (n === 1 ? '' : 's') : 'Send to all users';
    }
    function renderChips() {
        $('ntChips').innerHTML = Object.values(picked).map(function (u) {
            return '<span class="nt-chip">' + esc(u.name || u.email) + ' <button type="button" data-rm="' + u.id + '" aria-label="Remove ' + esc(u.email) + '">×</button></span>';
        }).join('');
        $('ntIds').value = JSON.stringify(Object.keys(picked).map(Number));
        updateBtn();
    }
    var t;
    $('ntSearch').addEventListener('input', function () {
        clearTimeout(t);
        var q = this.value.trim();
        if (q.length < 2) { $('ntResults').hidden = true; return; }
        t = setTimeout(function () {
            fetch('notifications?ajax=users&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
                $('ntResults').innerHTML = list.length ? list.map(function (u) {
                    return '<button type="button" data-pick=\'' + esc(JSON.stringify(u)) + '\'>' + esc(u.name) + ' <small>' + esc(u.email) + (u.status !== 'active' ? ' · ' + esc(u.status) : '') + '</small></button>';
                }).join('') : '<div style="padding:10px 12px" class="muted">No users found.</div>';
                $('ntResults').hidden = false;
            });
        }, 250);
    });
    document.addEventListener('click', function (e) {
        var p = e.target.closest('[data-pick]');
        if (p) { var u = JSON.parse(p.getAttribute('data-pick')); picked[u.id] = u; renderChips(); $('ntResults').hidden = true; $('ntSearch').value = ''; $('ntSearch').focus(); return; }
        var r = e.target.closest('[data-rm]');
        if (r) { delete picked[r.getAttribute('data-rm')]; renderChips(); return; }
        if (!e.target.closest('.nt-search')) $('ntResults').hidden = true;
    });
    function preview() {
        $('pvTitle').textContent = $('ntTitle').value || 'Your title';
        $('pvMsg').textContent = $('ntMsg').value || 'Your message';
        $('ntTitleCount').textContent = $('ntTitle').value.length + '/150';
        $('ntMsgCount').textContent = $('ntMsg').value.length + '/500';
    }
    ['ntTitle', 'ntMsg'].forEach(function (id) { $(id).addEventListener('input', preview); });
    document.querySelectorAll('input[name=audience]').forEach(function (r) { r.addEventListener('change', syncAud); });
    $('ntForm').addEventListener('submit', function (e) {
        var sel = document.querySelector('input[name=audience]:checked').value === 'selected';
        var n = sel ? Object.keys(picked).length : <?= $totalActive ?>;
        if (sel && !n) { e.preventDefault(); alert('Select at least one user.'); return; }
        if (!confirm('Send this notification to ' + n + ' user' + (n === 1 ? '' : 's') + '?')) e.preventDefault();
    });
    preview(); syncAud();
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
