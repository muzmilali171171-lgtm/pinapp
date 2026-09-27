<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo_functions.php';
require_once __DIR__ . '/includes/footer_functions.php';
require_once __DIR__ . '/includes/tutorial_functions.php';

$user = current_user($pdo);

$tutorials = get_all_tutorials($pdo, true);
$featured = null;
$rest = [];
foreach ($tutorials as $t) {
    $t['thumb_url'] = tutorials_thumbnail_url($t);
    if ($t['is_featured'] && !$featured) {
        $featured = $t;
    } else {
        $rest[] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php seo_render_head($pdo, [
    'title' => 'Tutorials — ' . APP_NAME,
    'description' => 'Short video tutorials to get you up to speed with ' . APP_NAME . '.',
]); ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<section class="container" style="max-width:800px;">
    <div class="tutorials-hero">
        <h1>Tutorials</h1>
        <p>Short videos to get you up to speed.</p>
    </div>

    <?php if ($featured): ?>
    <div class="tutorial-quickstart">
        <span class="tutorial-quickstart-label">Quick Start</span>
        <button type="button" class="tutorial-quickstart-row"
            data-video-id="<?= e($featured['video_id'] ?? '') ?>"
            data-video-url="<?= e($featured['video_url']) ?>"
            data-title="<?= e($featured['title']) ?>"
            data-tutorial-id="<?= (int)$featured['id'] ?>">
            <span class="tutorial-quickstart-thumb">
                <?php if ($featured['thumb_url']): ?><img src="<?= e($featured['thumb_url']) ?>" alt=""><?php endif; ?>
                <span class="tutorial-play-badge"><span>▶</span></span>
            </span>
            <span class="tutorial-quickstart-title"><?= e($featured['title']) ?></span>
            <?php if ($featured['duration']): ?><span class="tutorial-quickstart-duration"><?= e($featured['duration']) ?></span><?php endif; ?>
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($tutorials)): ?>
        <p class="muted">Tutorials are on the way — check back soon.</p>
    <?php else: ?>
    <div class="tutorial-list">
        <?php foreach ($rest as $t): ?>
            <button type="button" class="tutorial-row"
                data-video-id="<?= e($t['video_id'] ?? '') ?>"
                data-video-url="<?= e($t['video_url']) ?>"
                data-title="<?= e($t['title']) ?>"
                data-tutorial-id="<?= (int)$t['id'] ?>">
                <span class="tutorial-row-thumb">
                    <?php if ($t['thumb_url']): ?><img src="<?= e($t['thumb_url']) ?>" alt=""><?php endif; ?>
                    <span class="tutorial-play-badge"><span>▶</span></span>
                </span>
                <span class="tutorial-row-title"><?= e($t['title']) ?></span>
                <?php if ($t['duration']): ?><span class="tutorial-row-duration"><?= e($t['duration']) ?></span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<div class="tutorial-video-modal-overlay" id="tutorialVideoModal">
    <div class="tutorial-video-modal-box">
        <div class="tutorial-video-modal-head">
            <button type="button" class="tutorial-video-modal-close" id="tutorialVideoModalClose">✕</button>
        </div>
        <div class="tutorial-video-frame-wrap" id="tutorialVideoFrameWrap"></div>
    </div>
</div>

<?php render_site_footer($pdo); ?>

<script>
(function() {
    var overlay = document.getElementById('tutorialVideoModal');
    var frameWrap = document.getElementById('tutorialVideoFrameWrap');
    var closeBtn = document.getElementById('tutorialVideoModalClose');

    function openModal(videoId, videoUrl, tutorialId) {
        // No recognizable YouTube ID — just open the raw link in a new tab.
        if (!videoId) {
            window.open(videoUrl, '_blank', 'noopener');
            return;
        }
        frameWrap.innerHTML = '<iframe src="https://www.youtube.com/embed/' + encodeURIComponent(videoId) +
            '?autoplay=1&rel=0" title="Tutorial video" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (tutorialId) {
            fetch('tutorials-view', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(tutorialId),
                keepalive: true
            }).catch(function () {});
        }
    }

    function closeModal() {
        overlay.classList.remove('open');
        frameWrap.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-video-url]').forEach(function (el) {
        el.addEventListener('click', function () {
            openModal(el.dataset.videoId, el.dataset.videoUrl, el.dataset.tutorialId);
        });
    });

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>

</body>
</html>
