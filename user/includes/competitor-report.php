<?php
/** Renders a competitor / keyword report (from cp_report()) — shared by Competitor Analysis and Competitor Research. */

function cp_css(): void
{ ?>
<style>
.cp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.cp-stat { background: var(--light, #f9fafb); border-radius: 12px; padding: 12px 14px; }
.cp-stat b { display: block; font-size: 22px; line-height: 1.2; }
.cp-stat span { font-size: 12px; color: var(--gray, #6b7280); }
.cp-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 900px) { .cp-two { grid-template-columns: 1fr; } }
.cp-card h3 { margin: 0 0 10px; font-size: 16px; }
.cp-bars { display: flex; flex-direction: column; gap: 6px; }
.cp-bar { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; gap: 8px; align-items: center; font-size: 13px; }
.cp-bar .l { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp-bar .t { background: var(--light, #f3f4f6); border-radius: 6px; height: 12px; overflow: hidden; }
.cp-bar .f { background: #e60023; height: 100%; border-radius: 6px; }
.cp-bar .n { font-variant-numeric: tabular-nums; color: var(--gray, #6b7280); min-width: 34px; text-align: right; }
.cp-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.cp-chips span { background: var(--light, #f3f4f6); border-radius: 999px; padding: 4px 10px; font-size: 12.5px; }
.cp-chips span small { color: var(--gray, #6b7280); margin-left: 4px; }
.cp-pins { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.cp-pin { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
.cp-pin:hover { text-decoration: none; box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.cp-pin img { width: 100%; aspect-ratio: 2 / 3; object-fit: cover; background: #f3f4f6; }
.cp-pin div { padding: 7px 9px; font-size: 12px; }
.cp-pin .tt { font-weight: 600; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cp-pin .mm { color: var(--gray, #6b7280); margin-top: 3px; }
.cp-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; align-items: end; height: 110px; }
.cp-days div { display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 11px; color: var(--gray, #6b7280); height: 100%; justify-content: flex-end; }
.cp-days i { display: block; width: 100%; max-width: 34px; background: #e60023; border-radius: 6px 6px 0 0; min-height: 2px; }
.cp-hours { display: grid; grid-template-columns: repeat(24, 1fr); gap: 2px; align-items: end; height: 60px; margin-top: 6px; }
.cp-hours i { display: block; background: #fda4af; border-radius: 3px 3px 0 0; min-height: 2px; }
.cp-note { font-size: 12px; color: var(--gray, #6b7280); }
[data-theme="dark"] .cp-stat, [data-theme="dark"] .cp-chips span { background: #2a2c33; }
[data-theme="dark"] .cp-pin { border-color: #33353c; }
</style>
<?php }

function cp_bars(array $assoc, int $limit = 12, string $suffix = ''): string
{
    if (!$assoc) return '<p class="cp-note">Not enough data yet.</p>';
    $assoc = array_slice($assoc, 0, $limit, true);
    $max = max(1, max($assoc));
    $h = '<div class="cp-bars">';
    foreach ($assoc as $label => $n) {
        $h .= '<div class="cp-bar"><span class="l" title="' . e((string)$label) . '">' . e((string)$label) . '</span><span class="t"><span class="f" style="width:'
            . round($n / $max * 100, 1) . '%"></span></span><span class="n">' . e(number_format((float)$n) . $suffix) . '</span></div>';
    }
    return $h . '</div>';
}

function cp_render_report(array $rep, array $opt = []): void
{
    $post = $rep['posting'];
    $video = $rep['content']['video'];
    $image = $rep['content']['image'];
    $total = max(1, $rep['total']);
    ?>
    <div class="card">
        <div class="cp-grid">
            <div class="cp-stat"><b><?= number_format($rep['total']) ?></b><span>Total Pins collected</span></div>
            <div class="cp-stat"><b><?= $rep['avg_saves'] !== null ? number_format($rep['avg_saves'], 1) : '—' ?></b><span>Average saves per Pin</span></div>
            <div class="cp-stat"><b><?= number_format($rep['total_saves']) ?></b><span>Total saves (public)</span></div>
            <div class="cp-stat"><b><?= $image >= $video ? 'Image' : 'Video' ?></b><span>Most-used content type · <?= round($image / $total * 100) ?>% image · <?= round($video / $total * 100) ?>% video</span></div>
            <div class="cp-stat"><b><?= $post['per_week'] !== null ? number_format($post['per_week'], 1) : '—' ?></b><span>Pins / week<?= isset($post['per_week_recent']) ? ' · ' . number_format($post['per_week_recent'], 1) . ' in the last 30 days' : '' ?></span></div>
            <div class="cp-stat"><b><?= count($rep['clusters']) ?></b><span>Topic clusters</span></div>
            <div class="cp-stat"><b><?= count($rep['domains']) ?></b><span>Destination domains</span></div>
        </div>
        <p class="cp-note" style="margin:10px 0 0;">Public data only: Pinterest shows save counts for public Pins; impressions and clicks exist only for your own Pins.
            <?php if ($post['dated'] < $rep['total']): ?>Posting pattern uses the <?= (int)$post['dated'] ?> Pins with a publish date (recent Pins in Pinterest's public feeds).<?php endif; ?></p>
    </div>

    <div class="cp-two">
        <div class="card cp-card">
            <h3>🧩 Topic clusters</h3>
            <?php if (!$rep['clusters']): ?><p class="cp-note">Not enough data yet.</p><?php else: ?>
            <table>
                <tr><th>Topic</th><th>Pins</th><th>Avg saves</th></tr>
                <?php foreach ($rep['clusters'] as $c): ?>
                <tr><td><strong><?= e($c['topic']) ?></strong><?php if ($c['examples']): ?><div class="cp-note"><?= e(implode(' · ', array_map(fn($t) => mb_strimwidth($t, 0, 60, '…'), $c['examples']))) ?></div><?php endif; ?></td>
                    <td><?= (int)$c['pins'] ?></td><td><?= $c['avg_saves'] !== null ? number_format($c['avg_saves'], 1) : '—' ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <div class="card cp-card">
            <h3>📈 Content patterns</h3>
            <?php if (!$rep['patterns']): ?><p class="cp-note">No clear patterns yet.</p><?php else: ?>
            <table>
                <tr><th>Pattern</th><th>Pins</th><th>Share</th><th>Avg saves</th></tr>
                <?php foreach ($rep['patterns'] as $p): ?>
                <tr><td><?= e($p['pattern']) ?></td><td><?= (int)$p['pins'] ?></td><td><?= e((string)$p['share']) ?>%</td><td><?= $p['avg_saves'] !== null ? number_format($p['avg_saves'], 1) : '—' ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="cp-two">
        <div class="card cp-card">
            <h3>🏷️ Common keywords</h3>
            <?= cp_bars($rep['keywords']['words'], 15) ?>
        </div>
        <div class="card cp-card">
            <h3>🔁 Repeated phrases</h3>
            <?php $ph = $rep['keywords']['phrases'] + $rep['keywords']['long_phrases']; arsort($ph); ?>
            <?= cp_bars($ph, 15) ?>
        </div>
    </div>

    <div class="cp-two">
        <div class="card cp-card">
            <h3>🔗 Destination domains</h3>
            <?= cp_bars($rep['domains'], 12) ?>
        </div>
        <div class="card cp-card">
            <h3>🗓 Posting pattern <span class="cp-note">(UTC)</span></h3>
            <?php if (empty($post['days'])): ?><p class="cp-note">Not enough dated Pins yet — publish dates come from Pinterest's public feeds.</p><?php else:
                $mx = max(1, max($post['days'])); $mh = max(1, max($post['hours'])); ?>
            <div class="cp-days">
                <?php foreach ($post['days'] as $d => $n): ?><div><i style="height:<?= round($n / $mx * 85) ?>%"></i><?= e($d) ?> · <?= (int)$n ?></div><?php endforeach; ?>
            </div>
            <div class="cp-hours" title="Pins by hour of day (0–23 UTC)">
                <?php foreach ($post['hours'] as $h => $n): ?><i style="height:<?= round($n / $mh * 100) ?>%" title="<?= $h ?>:00 — <?= (int)$n ?> Pins"></i><?php endforeach; ?>
            </div>
            <p class="cp-note">Dated Pins: <?= (int)$post['dated'] ?> · <?= e((string)$post['first']) ?> → <?= e((string)$post['last']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($rep['boards']) && empty($opt['hide_boards'])): ?>
    <div class="card cp-card">
        <h3>📌 Boards</h3>
        <table>
            <tr><th>Board</th><th>Pins collected</th><th>Avg saves</th></tr>
            <?php foreach (array_slice($rep['boards'], 0, 20) as $b): ?>
            <tr><td><?= e($b['board']) ?></td><td><?= (int)$b['pins'] ?></td><td><?= $b['avg_saves'] !== null ? number_format($b['avg_saves'], 1) : '—' ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="card cp-card">
        <h3>🏆 Top-performing Pins <span class="cp-note">(by public saves)</span></h3>
        <?php if (!$rep['top']): ?><p class="cp-note">No Pins yet.</p><?php else: ?>
        <div class="cp-pins">
            <?php foreach ($rep['top'] as $p): ?>
            <a class="cp-pin" href="https://www.pinterest.com/pin/<?= e($p['pin_id']) ?>/" target="_blank" rel="noopener noreferrer">
                <img loading="lazy" src="<?= e((string)$p['image_url']) ?>" alt="" referrerpolicy="no-referrer">
                <div>
                    <div class="tt"><?= e((string)($p['title'] ?: '(no title)')) ?></div>
                    <div class="mm"><?= $p['saves'] !== null ? '💾 ' . number_format((int)$p['saves']) . ' saves' : 'saves n/a' ?><?= $p['is_video'] ? ' · 🎬' : '' ?><?= !empty($p['username']) ? ' · @' . e($p['username']) : '' ?></div>
                    <?php if ($p['domain']): ?><div class="mm">🔗 <?= e($p['domain']) ?></div><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php }
