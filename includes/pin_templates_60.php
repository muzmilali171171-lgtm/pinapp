<?php
/**
 * 60 more pin templates (40 single-photo, 20 collage), built from 13 layout engines + a preset per
 * template (engine, placement, fonts, colours, decorations). Same rules as pin_templates_more.php:
 * photos are never darkened or blurred — text sits on solid bands/cards/labels or has a crisp outline.
 *
 * Presets: pt2_presets(). Registry rows are added by pin_template_registry(). Dispatcher: pt2_render().
 */

/* ------------------------------------------------------------------ text block ------------------ */

/** Splits the title into the parts a block uses: kicker, big number, title, sub-line. */
function pt2_parts(string $title, string $cta, array $sp): array
{
    [$num, $lead, $main, $tail] = pt_parts($title);
    $kick = '';
    $big = null;
    if (!empty($sp['bignum']) && $num !== null) { $big = $num; $num = null; }
    $kmode = $sp['kicker'] ?? 'cta';
    if ($kmode === 'lead' && $lead !== '') { $kick = $lead; $lead = ''; }
    elseif ($kmode === 'cta' && $cta !== '') $kick = $cta;
    $titleText = trim(($num !== null ? $num . ' ' : '') . ($lead !== '' ? $lead . ' ' : '') . $main);
    if ($titleText === '') { $titleText = $tail; $tail = ''; }
    return ['kick' => $kick, 'big' => $big, 'title' => $titleText, 'sub' => $tail];
}

/**
 * Lays out kicker / big number / title / sub for a box of width $w and max height $maxH.
 * Returns ['h' => total height, 'items' => [[text, font, size, colorRgb, lineH, outlineRgb|null, ow], …]].
 */
function pt2_layout(array $sp, array $p, int $W, int $w, int $maxH, array $col): array
{
    $tf = px_font($sp['tfont'] ?? 'Poppins-ExtraBold.ttf');
    $kf = px_font($sp['kfont'] ?? 'Satisfy-Regular.ttf');
    $sf = px_font($sp['sfont'] ?? 'Poppins-Bold.ttf');
    $upper = $sp['upper'] ?? true;
    $scale = 1.0;
    for ($pass = 0; $pass < 8; $pass++) {
        $items = [];
        if ($p['kick'] !== '') {
            [$ks, $kl, $klh] = px_fit($kf, !empty($sp['kupper']) ? px_upper($p['kick']) : $p['kick'], $w, (int)($maxH * 0.2), (int)($W / 15 * $scale), 1, 1.15);
            $items[] = [$kl[0], $kf, $ks, $col['accent'], $klh, null, 0, !empty($sp['kspaced'])];
        }
        if ($p['big'] !== null) {
            $bs = (int)($W / 5 * ($sp['bigscale'] ?? 1.0) * $scale);
            $items[] = [$p['big'], $tf, $bs, $col['accent2'] ?? $col['accent'], pin_line_height($tf, $bs, 1.0, [$p['big']]), $col['outline'] ?? null, $col['ow'] ?? 0, false];
        }
        $tt = $upper ? px_upper($p['title']) : $p['title'];
        [$ts, $tl, $tlh] = px_fit($tf, $tt, $w, (int)($maxH * 0.62), (int)($W / 9.5 * ($sp['tscale'] ?? 1.0) * $scale), $sp['tlines'] ?? 4, $sp['tmul'] ?? 1.1);
        foreach ($tl as $i => $l) {
            $c = (!empty($sp['alt']) && $i % 2 === 1) ? $col['accent'] : $col['fg'];
            $items[] = [$l, $tf, $ts, $c, $tlh, $col['outline'] ?? null, $col['ow'] ?? 0, false];
        }
        if ($p['sub'] !== '') {
            $st = !empty($sp['supper']) ? px_upper($p['sub']) : $p['sub'];
            [$ss, $sl, $slh] = px_fit($sf, $st, $w, (int)($maxH * 0.2), (int)($ts * 0.5), 2, 1.15);
            foreach ($sl as $l) $items[] = [$l, $sf, $ss, $col['sub'] ?? $col['fg'], $slh, null, 0, !empty($sp['sspaced'])];
        }
        $h = 0;
        foreach ($items as $it) $h += $it[4];
        $h += (count($items) - 1) * (int)($W * 0.006);
        if ($h <= $maxH) break;
        $scale *= 0.9;
    }
    return ['h' => $h, 'items' => $items];
}

/** Draws a laid-out block with its top at $top, aligned in [$x1,$x2]. Returns the Y below it. */
function pt2_draw($im, array $block, int $x1, int $x2, int $top, string $align = 'center', int $W = 1000): int
{
    $y = $top;
    foreach ($block['items'] as [$text, $font, $size, $rgb, $lh, $outline, $ow, $spaced]) {
        [$a, $d] = px_metrics($size, $font);
        $base = $y + (int)(($lh + $a - $d) / 2);
        $c = px_col($im, $rgb);
        if ($spaced) {
            $sp = max(2, (int)($size * 0.18));
            $cx = $align === 'left' ? $x1 + (int)(px_spaced_w($size, $font, $text, $sp) / 2) : (int)(($x1 + $x2) / 2);
            px_spaced_text($im, $size, $font, $text, $cx, $base, $c, $sp);
        } elseif ($align === 'left') {
            if ($outline) pt_outline_left($im, $size, $font, $text, $x1, $base, $c, px_col($im, $outline), $ow);
            else imagettftext($im, $size, 0, $x1, $base, $c, $font, $text);
        } else {
            px_center_text($im, $size, $font, $text, (int)(($x1 + $x2) / 2), $base, $c, $outline ? px_col($im, $outline) : null, $ow);
        }
        $y += $lh + (int)($W * 0.006);
    }
    return $y;
}

/** Website: 'pill' | 'bar' | 'text' (spaced, inside the panel) | 'none'. $at = centre Y for pill/text. */
function pt2_site($im, string $website, string $mode, int $W, int $H, array $col, ?int $at = null): void
{
    if ($website === '' || $mode === 'none') return;
    $site = pt_site($website);
    $f = px_font('Poppins-Bold.ttf');
    if ($mode === 'bar') {
        $bh = (int)($H * 0.04);
        imagefilledrectangle($im, 0, $H - $bh, $W, $H, px_col($im, $col['bar'] ?? $col['bg']));
        $fs = max(12, (int)($bh * 0.42));
        px_spaced_text($im, $fs, $f, px_upper($site), (int)($W / 2), $H - (int)(($bh - $fs * 1.3) / 2), px_col($im, $col['bartext'] ?? $col['fg']), (int)($fs * 0.2));
    } elseif ($mode === 'text') {
        $fs = max(11, (int)($W / 44));
        px_spaced_text($im, $fs, px_font('Poppins-Regular.ttf'), px_upper($site), (int)($W / 2), $at ?? $H - 20, px_col($im, $col['sub'] ?? $col['fg']), (int)($fs * 0.25));
    } else {
        pt_pill($im, $site, (int)($W / 2), $at ?? $H - (int)($H * 0.035), max(12, (int)($W / 32)), $f, $col['pill'] ?? $col['bg'], $col['pilltext'] ?? $col['fg']);
    }
}

function pt2_deco($im, string $deco, int $x1, int $y1, int $x2, int $y2, array $col, int $W): void
{
    $c = px_col($im, $col['accent']);
    if ($deco === 'rules') {
        imagesetthickness($im, 2);
        $in = (int)($W * 0.02);
        imagerectangle($im, $x1 + $in, $y1 + $in, $x2 - $in, $y2 - $in, $c);
        imagesetthickness($im, 1);
    } elseif ($deco === 'dots') {
        for ($x = $x1 + 20; $x < $x2 - 10; $x += 22) { imagefilledellipse($im, $x, $y1 + 12, 6, 6, $c); imagefilledellipse($im, $x, $y2 - 12, 6, 6, $c); }
    } elseif ($deco === 'sparkle') {
        pt_sparkle($im, $x2 - (int)($W * 0.06), $y1 + (int)($W * 0.05), (int)($W * 0.03), $c);
        pt_sparkle($im, $x1 + (int)($W * 0.06), $y2 - (int)($W * 0.05), (int)($W * 0.02), $c);
    } elseif ($deco === 'corners') {
        imagesetthickness($im, 4);
        $L = (int)($W * 0.07); $in = (int)($W * 0.025);
        foreach ([[$x1 + $in, $y1 + $in, 1, 1], [$x2 - $in, $y1 + $in, -1, 1], [$x1 + $in, $y2 - $in, 1, -1], [$x2 - $in, $y2 - $in, -1, -1]] as [$x, $y, $dx, $dy]) {
            imageline($im, $x, $y, $x + $dx * $L, $y, $c); imageline($im, $x, $y, $x, $y + $dy * $L, $c);
        }
        imagesetthickness($im, 1);
    } elseif ($deco === 'topline') {
        imagefilledrectangle($im, (int)(($x1 + $x2) / 2 - $W * 0.08), $y1 + (int)($W * 0.025), (int)(($x1 + $x2) / 2 + $W * 0.08), $y1 + (int)($W * 0.025) + 5, $c);
    }
}

/* ------------------------------------------------------------------ single-photo engines -------- */

/** band: full-width solid band (top | middle | bottom) across the photo. */
function pt2_e_band($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.86), (int)($H * 0.36), $col);
    $pad = (int)($W * 0.06);
    $siteIn = ($sp['site'] ?? 'text') === 'text' && $website !== '';
    $bh = $block['h'] + $pad * 2 + ($siteIn ? (int)($W / 22) : 0);
    $pos = $sp['pos'] ?? 'bottom';
    $y1 = $pos === 'top' ? 0 : ($pos === 'middle' ? (int)(($H - $bh) / 2) : $H - $bh - (($sp['site'] ?? '') === 'bar' ? (int)($H * 0.04) : 0));
    imagefilledrectangle($im, 0, $y1, $W, $y1 + $bh, px_col($im, $col['bg']));
    if (!empty($sp['edge'])) { imagefilledrectangle($im, 0, $y1, $W, $y1 + 6, px_col($im, $col['accent'])); imagefilledrectangle($im, 0, $y1 + $bh - 6, $W, $y1 + $bh, px_col($im, $col['accent'])); }
    pt2_deco($im, $sp['deco'] ?? 'none', 0, $y1, $W, $y1 + $bh, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.07), (int)($W * 0.93), $y1 + $pad, $sp['align'] ?? 'center', $W);
    if ($siteIn) pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
    elseif (($sp['site'] ?? '') !== 'text') pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col, $pos === 'bottom' ? (int)($y1 - $H * 0.04) : null);
}

/** card: floating card (rounded or square) at top | center | bottom, optional border/deco. */
function pt2_e_card($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $cw = (int)($W * ($sp['cw'] ?? 0.82));
    $block = pt2_layout($sp, $p, $W, (int)($cw * 0.84), (int)($H * 0.4), $col);
    $pad = (int)($W * 0.07);
    $siteIn = ($sp['site'] ?? 'text') === 'text' && $website !== '';
    $ch = $block['h'] + $pad * 2 + ($siteIn ? (int)($W / 20) : 0);
    $x1 = ($sp['hpos'] ?? 'center') === 'left' ? (int)($W * 0.06) : (int)(($W - $cw) / 2);
    $pos = $sp['pos'] ?? 'bottom';
    $y1 = $pos === 'top' ? (int)($H * 0.06) : ($pos === 'center' ? (int)(($H - $ch) / 2) : $H - $ch - (int)($H * 0.08));
    $r = $sp['radius'] ?? (int)($W * 0.05);
    if (!empty($col['border'])) px_rrect($im, $x1 - 7, $y1 - 7, $x1 + $cw + 7, $y1 + $ch + 7, $r + 7, px_col($im, $col['border']));
    px_rrect($im, $x1, $y1, $x1 + $cw, $y1 + $ch, $r, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', $x1, $y1, $x1 + $cw, $y1 + $ch, $col, $W);
    $y = pt2_draw($im, $block, $x1 + (int)($cw * 0.08), $x1 + $cw - (int)($cw * 0.08), $y1 + $pad, $sp['align'] ?? 'center', $W);
    if ($siteIn) pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
    else pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col);
}

/** side: solid vertical panel (left | right) with the text, photo on the other side. */
function pt2_e_side($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $pw = (int)($W * ($sp['pw'] ?? 0.44));
    $left = ($sp['side'] ?? 'left') === 'left';
    pt_photo($im, $srcs, 0, $left ? $pw : 0, 0, $W - $pw, $H, 0.25);
    $px = $left ? 0 : $W - $pw;
    imagefilledrectangle($im, $px, 0, $px + $pw, $H, px_col($im, $col['bg']));
    if (!empty($sp['edge'])) imagefilledrectangle($im, $left ? $pw - 6 : $px, 0, $left ? $pw : $px + 6, $H, px_col($im, $col['accent']));
    $sp2 = $sp + ['tscale' => 0.7];
    $block = pt2_layout($sp2, $p, $W, (int)($pw * 0.82), (int)($H * 0.72), $col);
    $y = pt2_draw($im, $block, $px + (int)($pw * 0.09), $px + $pw - (int)($pw * 0.09), (int)(($H - $block['h']) / 2), $sp['align'] ?? 'center', $W);
    if ($website !== '') {
        $fs = max(10, (int)($W / 50));
        $f = px_font('Poppins-Bold.ttf');
        $site = pt_site($website);
        while (px_text_w($fs, $f, $site) > $pw * 0.86 && $fs > 9) $fs--;
        px_center_text($im, $fs, $f, $site, $px + (int)($pw / 2), $H - (int)($H * 0.03), px_col($im, $col['sub'] ?? $col['fg']));
    }
}

/** block: photo on top, solid colour block with text at the bottom (or the reverse). */
function pt2_e_block($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.84), (int)($H * 0.4), $col);
    $pad = (int)($W * 0.07);
    $bh = $block['h'] + $pad * 2 + ($website !== '' ? (int)($W / 18) : 0);
    $top = ($sp['pos'] ?? 'bottom') === 'top';
    $by = $top ? 0 : $H - $bh;
    pt_photo($im, $srcs, 0, 0, $top ? $bh : 0, $W, $H - $bh, 0.3);
    imagefilledrectangle($im, 0, $by, $W, $by + $bh, px_col($im, $col['bg']));
    if (!empty($sp['wave'])) {
        $c = px_col($im, $col['bg']);
        $edgeY = $top ? $by + $bh : $by;
        for ($x = 0; $x <= $W; $x += (int)($W / 8)) imagefilledellipse($im, $x + (int)($W / 16), $edgeY, (int)($W / 8), (int)($W / 14), $c);
    }
    pt2_deco($im, $sp['deco'] ?? 'none', 0, $by, $W, $by + $bh, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.08), (int)($W * 0.92), $by + $pad, $sp['align'] ?? 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 36));
}

/** labels: every title line on its own solid label (left or centre aligned), on the photo. */
function pt2_e_labels($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $tf = px_font($sp['tfont'] ?? 'Poppins-ExtraBold.ttf');
    $text = trim(($p['big'] !== null ? $p['big'] . ' ' : '') . $p['title']);
    $text = ($sp['upper'] ?? true) ? px_upper($text) : $text;
    [$s, $lines] = px_fit($tf, $text, (int)($W * 0.78), (int)($H * 0.36), (int)($W / 10 * ($sp['tscale'] ?? 1)), 5, 1.2);
    [$a, $d] = px_metrics($s, $tf);
    $padX = (int)($s * 0.35); $padY = (int)($s * 0.22);
    $lh = $a + $d + $padY * 2;
    $n = count($lines) + ($p['kick'] !== '' ? 1 : 0) + ($p['sub'] !== '' ? 1 : 0);
    $total = $n * ($lh + 6);
    $pos = $sp['pos'] ?? 'bottom';
    $y = $pos === 'top' ? (int)($H * 0.07) : ($pos === 'center' ? (int)(($H - $total) / 2) : $H - $total - (int)($H * 0.1));
    $left = ($sp['align'] ?? 'center') === 'left';
    $x0 = (int)($W * 0.07);
    $drawLabel = function (string $t, string $font, int $size, array $bg, array $fg) use ($im, &$y, $left, $x0, $W, $padX, $padY) {
        [$a, $d] = px_metrics($size, $font);
        $tw = px_text_w($size, $font, $t);
        $x1 = $left ? $x0 : (int)(($W - $tw) / 2) - $padX;
        imagefilledrectangle($im, $x1, $y, $x1 + $tw + $padX * 2, $y + $a + $d + $padY * 2, px_col($im, $bg));
        if (function_exists('pt4_lum') && abs(pt4_lum($fg) - pt4_lum($bg)) < 0.35) $fg = pt4_readable($bg);
        imagettftext($im, $size, 0, $x1 + $padX, $y + $padY + $a, px_col($im, $fg), $font, $t);
        $y += $a + $d + $padY * 2 + 6;
    };
    if ($p['kick'] !== '') $drawLabel($p['kick'], px_font($sp['kfont'] ?? 'Poppins-Bold.ttf'), (int)($s * 0.55), $col['accent'], $col['kfg'] ?? $col['fg']);
    foreach ($lines as $i => $l) $drawLabel($l, $tf, $s, (!empty($sp['alt']) && $i % 2) ? $col['accent'] : $col['bg'], (!empty($sp['alt']) && $i % 2) ? ($col['kfg'] ?? $col['fg']) : $col['fg']);
    if ($p['sub'] !== '') $drawLabel(($sp['supper'] ?? false) ? px_upper($p['sub']) : $p['sub'], px_font('Poppins-Bold.ttf'), (int)($s * 0.5), $col['accent'], $col['kfg'] ?? $col['fg']);
    pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col, $pos === 'bottom' ? $H - (int)($H * 0.045) : null);
}

/** badge: circle / oval badge with the title (centre or corner). */
function pt2_e_badge($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.35);
    $d = (int)($W * ($sp['size'] ?? 0.7));
    $pos = $sp['pos'] ?? 'center';
    $cx = $pos === 'corner' ? $W - (int)($d * 0.5) - (int)($W * 0.04) : (int)($W / 2);
    $cy = $pos === 'corner' ? (int)($d * 0.5) + (int)($H * 0.04) : ($pos === 'top' ? (int)($H * 0.26) : (int)($H * 0.62));
    $ov = $sp['oval'] ?? 1.0;
    if (!empty($col['border'])) imagefilledellipse($im, $cx, $cy, $d + 16, (int)($d * $ov) + 16, px_col($im, $col['border']));
    imagefilledellipse($im, $cx, $cy, $d, (int)($d * $ov), px_col($im, $col['bg']));
    if (($sp['deco'] ?? '') === 'ring') for ($t = 0; $t < 3; $t++) imageellipse($im, $cx, $cy, $d - 26 - $t, (int)($d * $ov) - 26 - $t, px_col($im, $col['accent']));
    $block = pt2_layout($sp, $p, $W, (int)($d * 0.66), (int)($d * $ov * 0.66), $col);
    pt2_draw($im, $block, $cx - (int)($d * 0.36), $cx + (int)($d * 0.36), $cy - (int)($block['h'] / 2), 'center', $W);
    pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col);
}

/** frame: thick coloured frame around the photo, text in the deep bottom (or top) of the frame. */
function pt2_e_frame($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    imagefilledrectangle($im, 0, 0, $W, $H, px_col($im, $col['bg']));
    $m = (int)($W * ($sp['m'] ?? 0.05));
    $block = pt2_layout($sp, $p, $W, $W - $m * 4, (int)($H * 0.34), $col);
    $th = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 20) : 0);
    $top = ($sp['pos'] ?? 'bottom') === 'top';
    $py = $top ? $th : $m;
    pt_photo($im, $srcs, 0, $m, $py, $W - 2 * $m, $H - $th - $m, 0.3);
    if (!empty($col['border'])) { imagesetthickness($im, 3); imagerectangle($im, $m - 8, $py - 8, $W - $m + 8, $py + $H - $th - $m + 8, px_col($im, $col['border'])); imagesetthickness($im, 1); }
    $ty = $top ? (int)($W * 0.05) : $H - $th + (int)($W * 0.04);
    $y = pt2_draw($im, $block, $m * 2, $W - $m * 2, $ty, $sp['align'] ?? 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** outline: big outlined words directly on the photo (top or bottom), no box. */
function pt2_e_outline($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.35);
    $sp2 = $sp;
    $block = pt2_layout($sp2, $p, $W, (int)($W * 0.9), (int)($H * 0.42), $col);
    $pos = $sp['pos'] ?? 'top';
    $y = $pos === 'top' ? (int)($H * 0.05) : $H - $block['h'] - (int)($H * 0.1);
    pt2_draw($im, $block, (int)($W * 0.05), (int)($W * 0.95), $y, $sp['align'] ?? 'center', $W);
    pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col);
}

/** tab: a hanging tag/tab from the top edge with the title, website pill below. */
function pt2_e_tab($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.45);
    $tw = (int)($W * ($sp['cw'] ?? 0.7));
    $block = pt2_layout($sp, $p, $W, (int)($tw * 0.84), (int)($H * 0.42), $col);
    $th = $block['h'] + (int)($W * 0.16);
    $x1 = (int)(($W - $tw) / 2);
    $pts = [$x1, 0, $x1 + $tw, 0, $x1 + $tw, $th, (int)($W / 2), $th + (int)($W * 0.08), $x1, $th];
    if (!empty($col['border'])) imagefilledpolygon($im, [$x1 - 8, 0, $x1 + $tw + 8, 0, $x1 + $tw + 8, $th + 6, (int)($W / 2), $th + (int)($W * 0.08) + 10, $x1 - 8, $th + 6], px_col($im, $col['border']));
    imagefilledpolygon($im, $pts, px_col($im, $col['bg']));
    pt2_draw($im, $block, $x1 + (int)($tw * 0.08), $x1 + $tw - (int)($tw * 0.08), (int)($W * 0.06), 'center', $W);
    pt2_site($im, $website, $sp['site'] ?? 'pill', $W, $H, $col);
}

/* ------------------------------------------------------------------ collage engines ------------- */

/** grid_band: photo grid above and below a full-width text band. */
function pt2_e_grid_band($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $cols = $sp['cols'] ?? 2;
    $n = max(2, count($srcs));
    $perSide = max($cols, (int)ceil($n / 2));
    $g = (int)($W * 0.008);
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.88), (int)($H * 0.3), $col);
    $bh = $block['h'] + (int)($W * 0.1) + ($website !== '' && ($sp['site'] ?? 'text') === 'text' ? (int)($W / 22) : 0);
    $by = (int)($H * ($sp['bandAt'] ?? 0.5) - $bh / 2);
    $rowsTop = (int)ceil($perSide / $cols);
    $k = 0;
    foreach ([[0, $by - $g], [$by + $bh + $g, $H]] as $half => [$ya, $yb]) {
        $cells = $half === 0 ? min($perSide, $n) : max(1, $n - $perSide);
        $c = min($cols, $cells);
        $cw = (int)(($W - ($c - 1) * $g) / $c);
        for ($i = 0; $i < $c; $i++) pt_photo($im, $srcs, $k++, $i * ($cw + $g), $ya, $i === $c - 1 ? $W - $i * ($cw + $g) : $cw, $yb - $ya, 0.2);
    }
    imagefilledrectangle($im, 0, $by, $W, $by + $bh, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', 0, $by, $W, $by + $bh, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.06), (int)($W * 0.94), $by + (int)($W * 0.05), 'center', $W);
    if (($sp['site'] ?? 'text') === 'text') pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
    else pt2_site($im, $website, $sp['site'], $W, $H, $col);
}

/** hero: big hero photo + a row of smaller photos; text card overlapping the seam. */
function pt2_e_hero($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $n = max(2, count($srcs));
    $small = $n - 1;
    $g = (int)($W * 0.008);
    $heroH = (int)($H * ($sp['heroH'] ?? 0.58));
    $top = ($sp['heroPos'] ?? 'top') === 'top';
    pt_photo($im, $srcs, 0, 0, $top ? 0 : $H - $heroH, $W, $heroH, 0.3);
    $ry = $top ? $heroH + $g : 0; $rh = $H - $heroH - $g;
    $cw = (int)(($W - ($small - 1) * $g) / $small);
    for ($i = 0; $i < $small; $i++) pt_photo($im, $srcs, 1 + $i, $i * ($cw + $g), $ry, $i === $small - 1 ? $W - $i * ($cw + $g) : $cw, $rh, 0.2);
    $cw2 = (int)($W * 0.84);
    $block = pt2_layout($sp, $p, $W, (int)($cw2 * 0.86), (int)($H * 0.3), $col);
    $ch = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 22) : 0);
    $seam = $top ? $heroH : $H - $heroH;
    $y1 = $seam - (int)($ch / 2);
    $x1 = (int)(($W - $cw2) / 2);
    if (!empty($col['border'])) px_rrect($im, $x1 - 6, $y1 - 6, $x1 + $cw2 + 6, $y1 + $ch + 6, ($sp['radius'] ?? 20) + 6, px_col($im, $col['border']));
    px_rrect($im, $x1, $y1, $x1 + $cw2, $y1 + $ch, $sp['radius'] ?? 20, px_col($im, $col['bg']));
    $y = pt2_draw($im, $block, $x1 + (int)($cw2 * 0.07), $x1 + $cw2 - (int)($cw2 * 0.07), $y1 + (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** frame_grid: 2×2 (or 2×3) photos with coloured gutters, rounded title card in the centre. */
function pt2_e_frame_grid($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    imagefilledrectangle($im, 0, 0, $W, $H, px_col($im, $col['gutter'] ?? $col['bg']));
    $n = max(4, count($srcs));
    $rows = $n >= 6 ? 3 : 2;
    $g = (int)($W * ($sp['gap'] ?? 0.02));
    $cw = (int)(($W - 3 * $g) / 2); $ch = (int)(($H - ($rows + 1) * $g) / $rows);
    $k = 0;
    for ($r = 0; $r < $rows; $r++) for ($c = 0; $c < 2; $c++) {
        $x = $g + $c * ($cw + $g); $y = $g + $r * ($ch + $g);
        if (!empty($sp['round'])) pt_photo_round($im, $srcs, $k++, $x, $y, $cw, $ch, (int)($W * 0.04), $col['gutter'] ?? $col['bg'], 0.2);
        else pt_photo($im, $srcs, $k++, $x, $y, $cw, $ch, 0.2);
    }
    $cw2 = (int)($W * 0.78);
    $block = pt2_layout($sp, $p, $W, (int)($cw2 * 0.84), (int)($H * 0.3), $col);
    $chh = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 22) : 0);
    $x1 = (int)(($W - $cw2) / 2); $y1 = (int)(($H - $chh) / 2);
    if (!empty($col['border'])) px_rrect($im, $x1 - 7, $y1 - 7, $x1 + $cw2 + 7, $y1 + $chh + 7, ($sp['radius'] ?? 30) + 7, px_col($im, $col['border']));
    px_rrect($im, $x1, $y1, $x1 + $cw2, $y1 + $chh, $sp['radius'] ?? 30, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', $x1, $y1, $x1 + $cw2, $y1 + $chh, $col, $W);
    $y = pt2_draw($im, $block, $x1 + (int)($cw2 * 0.08), $x1 + $cw2 - (int)($cw2 * 0.08), $y1 + (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** strips: vertical photo strips with a text band across (middle | bottom). */
function pt2_e_strips($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $n = max(2, count($srcs));
    $g = (int)($W * 0.008);
    $sw = (int)(($W - ($n - 1) * $g) / $n);
    for ($i = 0; $i < $n; $i++) pt_photo($im, $srcs, $i, $i * ($sw + $g), 0, $i === $n - 1 ? $W - $i * ($sw + $g) : $sw, $H, 0.22);
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.86), (int)($H * 0.32), $col);
    $bh = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 22) : 0);
    $by = ($sp['pos'] ?? 'middle') === 'bottom' ? $H - $bh - (int)($H * 0.06) : (int)(($H - $bh) / 2);
    imagefilledrectangle($im, 0, $by, $W, $by + $bh, px_col($im, $col['bg']));
    if (!empty($sp['edge'])) { imagefilledrectangle($im, 0, $by, $W, $by + 6, px_col($im, $col['accent'])); imagefilledrectangle($im, 0, $by + $bh - 6, $W, $by + $bh, px_col($im, $col['accent'])); }
    $y = pt2_draw($im, $block, (int)($W * 0.07), (int)($W * 0.93), $by + (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** column: one big photo + a column of stacked smaller photos, text block at the bottom. */
function pt2_e_column($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $n = max(2, count($srcs));
    $g = (int)($W * 0.008);
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.86), (int)($H * 0.3), $col);
    $bh = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 20) : 0);
    $ph = $H - $bh;
    $bigW = (int)($W * 0.6);
    $right = ($sp['side'] ?? 'left') === 'right';
    pt_photo($im, $srcs, 0, $right ? $W - $bigW : 0, 0, $bigW, $ph, 0.25);
    $k = $n - 1; $sh = (int)(($ph - ($k - 1) * $g) / $k); $sx = $right ? 0 : $bigW + $g;
    for ($i = 0; $i < $k; $i++) pt_photo($im, $srcs, 1 + $i, $sx, $i * ($sh + $g), $W - $bigW - $g, $i === $k - 1 ? $ph - $i * ($sh + $g) : $sh, 0.2);
    imagefilledrectangle($im, 0, $ph, $W, $H, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', 0, $ph, $W, $H, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.07), (int)($W * 0.93), $ph + (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/* ------------------------------------------------------------------ presets --------------------- */

/**
 * key => [name, category, layout, photos, engine, spec, colours]
 * colours: bg (panel), fg (title), accent (kicker/decor), sub, outline/ow, border, pill/pilltext, bar/bartext, gutter.
 */
function pt2_presets(): array
{
    $W = [255, 255, 255]; $K = [16, 16, 16];
    return [
        // ===== 40 single-photo =====
        'tpl2_ivory_editorial' => ['Ivory Editorial Band', 'Fashion', 'single', 1, 'band', ['pos' => 'bottom', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'rules'], ['bg' => [250, 246, 238], 'fg' => [30, 30, 30], 'accent' => [150, 110, 70], 'sub' => [110, 90, 70]]],
        'tpl2_blush_card' => ['Blush Round Card', 'Fashion', 'single', 1, 'card', ['pos' => 'bottom', 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'bignum' => true, 'bigscale' => 0.7], ['bg' => [248, 224, 226], 'fg' => [110, 30, 50], 'accent' => [200, 60, 90], 'sub' => [110, 30, 50], 'pill' => [110, 30, 50], 'pilltext' => $W]],
        'tpl2_black_side' => ['Black Side Panel', 'Fashion', 'single', 1, 'side', ['side' => 'left', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'bignum' => true, 'edge' => true, 'tmul' => 1.05], ['bg' => [14, 14, 14], 'fg' => $W, 'accent' => [214, 176, 110], 'sub' => [214, 176, 110]]],
        'tpl2_camel_labels' => ['Camel Label Stack', 'Fashion', 'single', 1, 'labels', ['pos' => 'bottom', 'align' => 'left', 'tfont' => 'Poppins-ExtraBold.ttf', 'alt' => true], ['bg' => [196, 150, 100], 'fg' => $W, 'accent' => [250, 244, 234], 'kfg' => [120, 80, 40], 'pill' => [120, 80, 40], 'pilltext' => $W]],
        'tpl2_mono_outline' => ['Mono Outline Title', 'Fashion', 'single', 1, 'outline', ['pos' => 'top', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'tmul' => 1.05], ['bg' => $K, 'fg' => $W, 'accent' => $W, 'outline' => $K, 'ow' => 6, 'sub' => $W, 'pill' => $K, 'pilltext' => $W]],
        'tpl2_tomato_band' => ['Tomato Recipe Band', 'Food & Recipes', 'single', 1, 'band', ['pos' => 'bottom', 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'edge' => true, 'site' => 'text'], ['bg' => [206, 52, 36], 'fg' => $W, 'accent' => [255, 214, 120], 'sub' => [255, 236, 200]]],
        'tpl2_recipe_card' => ['Recipe Index Card', 'Food & Recipes', 'single', 1, 'card', ['pos' => 'top', 'radius' => 6, 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf', 'deco' => 'dots'], ['bg' => [255, 251, 240], 'fg' => [60, 40, 30], 'accent' => [200, 70, 50], 'sub' => [120, 90, 70], 'border' => [200, 70, 50], 'pill' => [200, 70, 50], 'pilltext' => $W]],
        'tpl2_mustard_block' => ['Mustard Bottom Block', 'Food & Recipes', 'single', 1, 'block', ['pos' => 'bottom', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'wave' => true], ['bg' => [246, 186, 50], 'fg' => [40, 24, 10], 'accent' => [150, 50, 20], 'sub' => [80, 40, 10]]],
        'tpl2_chalk_frame' => ['Chalkboard Frame', 'Food & Recipes', 'single', 1, 'frame', ['pos' => 'bottom', 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [38, 44, 40], 'fg' => $W, 'accent' => [240, 200, 110], 'sub' => [220, 220, 210], 'border' => [240, 200, 110]]],
        'tpl2_basil_badge' => ['Basil Circle Badge', 'Food & Recipes', 'single', 1, 'badge', ['pos' => 'top', 'size' => 0.66, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'ring', 'tscale' => 0.9], ['bg' => [64, 120, 60], 'fg' => $W, 'accent' => [230, 245, 200], 'border' => $W, 'pill' => [64, 120, 60], 'pilltext' => $W]],
        'tpl2_sage_card' => ['Sage Center Card', 'Home Decor', 'single', 1, 'card', ['pos' => 'center', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'corners'], ['bg' => [214, 222, 204], 'fg' => [44, 56, 40], 'accent' => [96, 116, 88], 'sub' => [70, 84, 64], 'pill' => [44, 56, 40], 'pilltext' => $W]],
        'tpl2_linen_top' => ['Linen Top Block', 'Home Decor', 'single', 1, 'block', ['pos' => 'top', 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'deco' => 'topline'], ['bg' => [240, 234, 224], 'fg' => [52, 44, 38], 'accent' => [168, 130, 96], 'sub' => [120, 100, 84]]],
        'tpl2_navy_side' => ['Navy Side Panel', 'Home Decor', 'single', 1, 'side', ['side' => 'right', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [30, 44, 70], 'fg' => $W, 'accent' => [230, 196, 140], 'sub' => [200, 210, 225]]],
        'tpl2_terracotta_tab' => ['Terracotta Hanging Tab', 'Home Decor', 'single', 1, 'tab', ['tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [196, 104, 72], 'fg' => $W, 'accent' => [255, 230, 210], 'sub' => [255, 230, 210], 'border' => $W, 'pill' => [196, 104, 72], 'pilltext' => $W]],
        'tpl2_rose_labels' => ['Rose Pill Labels', 'Beauty & Hair', 'single', 1, 'labels', ['pos' => 'center', 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false], ['bg' => $W, 'fg' => [180, 50, 90], 'accent' => [230, 90, 130], 'kfg' => $W, 'pill' => [180, 50, 90], 'pilltext' => $W]],
        'tpl2_glam_frame' => ['Glam Gold Frame', 'Beauty & Hair', 'single', 1, 'frame', ['pos' => 'bottom', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf'], ['bg' => [22, 18, 18], 'fg' => $W, 'accent' => [214, 176, 110], 'sub' => [214, 176, 110], 'border' => [214, 176, 110]]],
        'tpl2_lilac_badge' => ['Lilac Oval Badge', 'Beauty & Hair', 'single', 1, 'badge', ['pos' => 'center', 'size' => 0.74, 'oval' => 0.8, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'tscale' => 0.85], ['bg' => [206, 186, 236], 'fg' => [70, 40, 110], 'accent' => [140, 80, 180], 'sub' => [70, 40, 110], 'border' => $W, 'pill' => [70, 40, 110], 'pilltext' => $W]],
        'tpl2_peach_band' => ['Peach Top Band', 'Beauty & Hair', 'single', 1, 'band', ['pos' => 'top', 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'site' => 'pill'], ['bg' => [252, 214, 190], 'fg' => [120, 50, 30], 'accent' => [220, 100, 70], 'sub' => [120, 50, 30], 'pill' => [120, 50, 30], 'pilltext' => $W]],
        'tpl2_ocean_band' => ['Ocean Middle Band', 'Travel', 'single', 1, 'band', ['pos' => 'middle', 'tfont' => 'BebasNeue-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'tscale' => 1.3, 'site' => 'pill', 'edge' => true], ['bg' => [0, 96, 140], 'fg' => $W, 'accent' => [255, 214, 120], 'sub' => [210, 236, 250], 'pill' => [0, 96, 140], 'pilltext' => $W]],
        'tpl2_passport_card' => ['Passport Card', 'Travel', 'single', 1, 'card', ['pos' => 'bottom', 'radius' => 8, 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'rules'], ['bg' => [26, 52, 92], 'fg' => $W, 'accent' => [230, 190, 110], 'sub' => [200, 214, 235], 'pill' => [230, 190, 110], 'pilltext' => [26, 52, 92]]],
        'tpl2_sunset_outline' => ['Sunset Outline Title', 'Travel', 'single', 1, 'outline', ['pos' => 'bottom', 'tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf'], ['bg' => [240, 100, 50], 'fg' => [255, 200, 70], 'accent' => $W, 'outline' => [120, 30, 20], 'ow' => 6, 'sub' => $W, 'pill' => [240, 100, 50], 'pilltext' => $W]],
        'tpl2_volt_side' => ['Volt Side Panel', 'Fitness', 'single', 1, 'side', ['side' => 'left', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'bignum' => true, 'tmul' => 1.05], ['bg' => [18, 18, 22], 'fg' => $W, 'accent' => [200, 255, 60], 'sub' => [200, 255, 60]]],
        'tpl2_red_labels' => ['Power Red Labels', 'Fitness', 'single', 1, 'labels', ['pos' => 'bottom', 'align' => 'left', 'tfont' => 'Anton-Regular.ttf', 'tscale' => 1.15, 'alt' => true], ['bg' => [220, 30, 40], 'fg' => $W, 'accent' => $K, 'kfg' => $W, 'pill' => $K, 'pilltext' => $W]],
        'tpl2_mint_block' => ['Mint Bottom Block', 'Fitness', 'single', 1, 'block', ['pos' => 'bottom', 'tfont' => 'BebasNeue-Regular.ttf', 'tscale' => 1.35, 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'kspaced' => true], ['bg' => [170, 236, 214], 'fg' => [10, 60, 50], 'accent' => [0, 130, 100], 'sub' => [10, 60, 50]]],
        'tpl2_craft_tab' => ['Craft Paper Tab', 'DIY & Crafts', 'single', 1, 'tab', ['tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'cw' => 0.76], ['bg' => [214, 180, 132], 'fg' => [70, 44, 20], 'accent' => [190, 70, 50], 'sub' => [90, 60, 30], 'border' => $W, 'pill' => [70, 44, 20], 'pilltext' => $W]],
        'tpl2_scrapbook_card' => ['Scrapbook Card', 'DIY & Crafts', 'single', 1, 'card', ['pos' => 'center', 'radius' => 4, 'tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'dots', 'cw' => 0.78], ['bg' => [255, 240, 210], 'fg' => [40, 90, 140], 'accent' => [230, 90, 80], 'sub' => [40, 90, 140], 'border' => [40, 90, 140], 'pill' => [230, 90, 80], 'pilltext' => $W]],
        'tpl2_ivory_frame' => ['Ivory Wedding Frame', 'Wedding', 'single', 1, 'frame', ['pos' => 'bottom', 'm' => 0.06, 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf'], ['bg' => [252, 249, 244], 'fg' => [60, 50, 44], 'accent' => [190, 156, 100], 'sub' => [120, 104, 90], 'border' => [190, 156, 100]]],
        'tpl2_blush_script' => ['Blush Script Card', 'Wedding', 'single', 1, 'card', ['pos' => 'top', 'tfont' => 'GreatVibes-Regular.ttf', 'upper' => false, 'tscale' => 1.2, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'sparkle', 'tmul' => 1.0], ['bg' => [250, 236, 234], 'fg' => [120, 70, 80], 'accent' => [190, 140, 120], 'sub' => [120, 90, 90], 'pill' => [120, 70, 80], 'pilltext' => $W]],
        'tpl2_xmas_band' => ['Festive Red Band', 'Holidays', 'single', 1, 'band', ['pos' => 'bottom', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'edge' => true], ['bg' => [160, 20, 36], 'fg' => $W, 'accent' => [230, 190, 100], 'sub' => [250, 230, 200]]],
        'tpl2_pumpkin_badge' => ['Pumpkin Badge', 'Holidays', 'single', 1, 'badge', ['pos' => 'corner', 'size' => 0.62, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'tscale' => 1.0, 'site' => 'pill'], ['bg' => [230, 110, 30], 'fg' => $W, 'accent' => [255, 236, 200], 'border' => $W, 'pill' => [120, 50, 10], 'pilltext' => $W]],
        'tpl2_evergreen_side' => ['Evergreen Side Panel', 'Holidays', 'single', 1, 'side', ['side' => 'right', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'edge' => true], ['bg' => [24, 72, 52], 'fg' => $W, 'accent' => [232, 190, 100], 'sub' => [220, 236, 226]]],
        'tpl2_minimal_block' => ['Minimal White Block', 'Lifestyle', 'single', 1, 'block', ['pos' => 'bottom', 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'topline'], ['bg' => $W, 'fg' => [24, 24, 24], 'accent' => [200, 120, 90], 'sub' => [110, 110, 110]]],
        'tpl2_cocoa_labels' => ['Cocoa Label Stack', 'Lifestyle', 'single', 1, 'labels', ['pos' => 'top', 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false], ['bg' => [94, 60, 44], 'fg' => $W, 'accent' => [240, 220, 200], 'kfg' => [94, 60, 44], 'pill' => [94, 60, 44], 'pilltext' => $W]],
        'tpl2_leaf_card' => ['Leaf Green Card', 'Garden', 'single', 1, 'card', ['pos' => 'bottom', 'hpos' => 'left', 'cw' => 0.86, 'align' => 'left', 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [52, 96, 56], 'fg' => $W, 'accent' => [210, 236, 180], 'sub' => [210, 236, 180], 'pill' => [52, 96, 56], 'pilltext' => $W]],
        'tpl2_bloom_tab' => ['Bloom Pink Tab', 'Garden', 'single', 1, 'tab', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [236, 120, 150], 'fg' => $W, 'accent' => [255, 236, 240], 'sub' => $W, 'border' => [70, 110, 60], 'pill' => [70, 110, 60], 'pilltext' => $W]],
        'tpl2_crayon_badge' => ['Crayon Fun Badge', 'Kids & Parenting', 'single', 1, 'badge', ['pos' => 'top', 'size' => 0.78, 'oval' => 0.78, 'tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'alt' => true], ['bg' => [66, 165, 245], 'fg' => $W, 'accent' => [255, 214, 60], 'border' => [255, 214, 60], 'outline' => [20, 60, 120], 'ow' => 3, 'pill' => [255, 112, 67], 'pilltext' => $W]],
        'tpl2_money_side' => ['Money Green Side', 'Tips & Business', 'single', 1, 'side', ['side' => 'left', 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'bignum' => true], ['bg' => [16, 80, 60], 'fg' => $W, 'accent' => [180, 240, 150], 'sub' => [200, 230, 210]]],
        'tpl2_notebook_card' => ['Notebook Card', 'Tips & Business', 'single', 1, 'card', ['pos' => 'center', 'radius' => 6, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'rules'], ['bg' => [255, 252, 232], 'fg' => [30, 40, 80], 'accent' => [220, 60, 60], 'sub' => [60, 70, 110], 'border' => [30, 40, 80], 'pill' => [30, 40, 80], 'pilltext' => $W]],
        'tpl2_paw_band' => ['Paw Friendly Band', 'Pets', 'single', 1, 'band', ['pos' => 'bottom', 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'dots'], ['bg' => [255, 176, 70], 'fg' => [70, 36, 10], 'accent' => [150, 70, 20], 'sub' => [90, 50, 20]]],
        'tpl2_calm_frame' => ['Calm Wellness Frame', 'Health & Wellness', 'single', 1, 'frame', ['pos' => 'top', 'm' => 0.05, 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [222, 236, 232], 'fg' => [30, 70, 70], 'accent' => [60, 140, 130], 'sub' => [60, 100, 100]]],
        // ===== 20 collage =====
        'tpl2_c_style_band' => ['Style Grid Band', 'Fashion', 'collage', 4, 'grid_band', ['cols' => 2, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'bignum' => true, 'bigscale' => 0.6, 'deco' => 'rules'], ['bg' => [246, 240, 232], 'fg' => [90, 50, 30], 'accent' => [180, 110, 70], 'sub' => [90, 50, 30]]],
        'tpl2_c_look_hero' => ['Lookbook Hero', 'Fashion', 'collage', 4, 'hero', ['heroH' => 0.56, 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true], ['bg' => $W, 'fg' => [24, 24, 24], 'accent' => [180, 120, 90], 'sub' => [100, 100, 100], 'border' => [24, 24, 24]]],
        'tpl2_c_runway_strips' => ['Runway Strips', 'Fashion', 'collage', 3, 'strips', ['pos' => 'bottom', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'edge' => true], ['bg' => $K, 'fg' => $W, 'accent' => [214, 176, 110], 'sub' => [214, 176, 110]]],
        'tpl2_c_menu_grid' => ['Menu Grid Band', 'Food & Recipes', 'collage', 4, 'grid_band', ['cols' => 2, 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'site' => 'text'], ['bg' => [200, 40, 30], 'fg' => $W, 'accent' => [255, 214, 120], 'sub' => [255, 236, 210]]],
        'tpl2_c_feast_hero' => ['Feast Hero + Row', 'Food & Recipes', 'collage', 4, 'hero', ['heroH' => 0.6, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'radius' => 40], ['bg' => [255, 206, 70], 'fg' => [70, 30, 10], 'accent' => [190, 60, 30], 'sub' => [70, 30, 10], 'border' => $W]],
        'tpl2_c_kitchen_frames' => ['Kitchen Frame Grid', 'Food & Recipes', 'collage', 4, 'frame_grid', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf', 'gap' => 0.025], ['bg' => [255, 250, 240], 'fg' => [60, 36, 24], 'accent' => [196, 90, 50], 'sub' => [110, 80, 60], 'gutter' => [196, 90, 50]]],
        'tpl2_c_room_column' => ['Room Column Collage', 'Home Decor', 'collage', 4, 'column', ['side' => 'left', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'topline'], ['bg' => [240, 234, 224], 'fg' => [52, 44, 38], 'accent' => [168, 130, 96], 'sub' => [110, 96, 84]]],
        'tpl2_c_cozy_frames' => ['Cozy Rounded Grid', 'Home Decor', 'collage', 4, 'frame_grid', ['round' => true, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'deco' => 'corners'], ['bg' => [255, 255, 255], 'fg' => [70, 56, 44], 'accent' => [180, 140, 100], 'sub' => [110, 96, 84], 'gutter' => [236, 226, 212], 'border' => [180, 140, 100]]],
        'tpl2_c_decor_band' => ['Decor Three-Up Band', 'Home Decor', 'collage', 6, 'grid_band', ['cols' => 3, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'bandAt' => 0.5], ['bg' => [44, 60, 56], 'fg' => $W, 'accent' => [220, 196, 150], 'sub' => [200, 214, 206]]],
        'tpl2_c_glow_strips' => ['Glow Beauty Strips', 'Beauty & Hair', 'collage', 3, 'strips', ['pos' => 'middle', 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'edge' => true], ['bg' => [252, 226, 232], 'fg' => [150, 40, 80], 'accent' => [210, 90, 130], 'sub' => [120, 50, 80]]],
        'tpl2_c_hair_grid' => ['Hair Ideas Grid', 'Beauty & Hair', 'collage', 6, 'frame_grid', ['tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'round' => true, 'bignum' => true, 'bigscale' => 0.55], ['bg' => $W, 'fg' => [110, 40, 90], 'accent' => [200, 90, 150], 'sub' => [110, 40, 90], 'gutter' => [246, 214, 230], 'border' => [200, 90, 150]]],
        'tpl2_c_trip_hero' => ['Trip Hero Collage', 'Travel', 'collage', 4, 'hero', ['heroH' => 0.55, 'heroPos' => 'top', 'tfont' => 'BebasNeue-Regular.ttf', 'tscale' => 1.3, 'kfont' => 'Satisfy-Regular.ttf', 'radius' => 12], ['bg' => [0, 90, 130], 'fg' => $W, 'accent' => [255, 214, 120], 'sub' => [210, 236, 250], 'border' => $W]],
        'tpl2_c_route_column' => ['Route Column Collage', 'Travel', 'collage', 4, 'column', ['side' => 'right', 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [250, 214, 150], 'fg' => [30, 50, 80], 'accent' => [200, 80, 50], 'sub' => [60, 70, 90]]],
        'tpl2_c_vow_frames' => ['Vow Frame Grid', 'Wedding', 'collage', 4, 'frame_grid', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'gap' => 0.03, 'deco' => 'sparkle'], ['bg' => [252, 248, 242], 'fg' => [70, 56, 50], 'accent' => [190, 156, 100], 'sub' => [120, 104, 90], 'gutter' => [252, 248, 242], 'border' => [190, 156, 100]]],
        'tpl2_c_festive_band' => ['Festive Grid Band', 'Holidays', 'collage', 4, 'grid_band', ['cols' => 2, 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'deco' => 'sparkle'], ['bg' => [22, 70, 50], 'fg' => $W, 'accent' => [232, 190, 100], 'sub' => [220, 236, 226]]],
        'tpl2_c_harvest_hero' => ['Harvest Hero Collage', 'Holidays', 'collage', 4, 'hero', ['heroH' => 0.58, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [200, 100, 40], 'fg' => $W, 'accent' => [255, 230, 190], 'sub' => [255, 236, 210], 'border' => [255, 236, 210]]],
        'tpl2_c_maker_grid' => ['Maker Grid Band', 'DIY & Crafts', 'collage', 4, 'grid_band', ['cols' => 2, 'tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'dots'], ['bg' => [255, 236, 200], 'fg' => [40, 90, 140], 'accent' => [230, 90, 80], 'sub' => [40, 90, 140]]],
        'tpl2_c_train_strips' => ['Training Strips', 'Fitness', 'collage', 3, 'strips', ['pos' => 'bottom', 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'edge' => true], ['bg' => [18, 18, 22], 'fg' => $W, 'accent' => [200, 255, 60], 'sub' => [200, 255, 60]]],
        'tpl2_c_daily_column' => ['Daily Life Column', 'Lifestyle', 'collage', 4, 'column', ['side' => 'left', 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [230, 90, 70], 'fg' => $W, 'accent' => [255, 230, 210], 'sub' => [255, 230, 210]]],
        'tpl2_c_garden_frames' => ['Garden Frame Grid', 'Garden', 'collage', 4, 'frame_grid', ['round' => true, 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [250, 252, 244], 'fg' => [44, 84, 48], 'accent' => [110, 150, 80], 'sub' => [70, 100, 60], 'gutter' => [196, 220, 176], 'border' => [110, 150, 80]]],
    ];
}

/** Dispatcher: returns null if $style isn't one of these presets. */
function pt2_render(string $style, array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $presets = function_exists('pt2_all_presets') ? pt2_all_presets() : pt2_presets();
    if (!isset($presets[$style])) return null;
    [, , , , $engine, $sp, $col] = $presets[$style];
    // Brand palette (if enabled) replaces the panel / title / accent colours.
    if ($pal && !empty($pal['colors'])) {
        $col['bg'] = $pal['colors'][0] ?? $col['bg'];
        $col['accent'] = $pal['colors'][1] ?? $col['accent'];
        if (!empty($pal['colors'][2])) $col['fg'] = $pal['colors'][2];
        $col['pill'] = $col['bg']; $col['pilltext'] = $col['fg'];
    }
    $col += ['sub' => $col['fg']];
    // Contrast safety: text colours that are too close to their panel colour get swapped for black/white.
    if ($engine !== 'onphoto' && $engine !== 'seam_outline' && function_exists('pt4_lum')) {
        $bgL = pt4_lum($col['bg']);
        foreach (['fg', 'sub', 'accent'] as $k) {
            if (isset($col[$k]) && abs(pt4_lum($col[$k]) - $bgL) < ($k === 'accent' ? 0.22 : 0.35)) $col[$k] = $k === 'accent' ? $col['fg'] : pt4_readable($col['bg']);
        }
        if (isset($col['kfg'])) {
            if (abs(pt4_lum($col['kfg']) - pt4_lum($col['accent'])) < 0.35) $col['kfg'] = pt4_readable($col['accent']);
        }
        if (isset($col['pill'], $col['pilltext']) && abs(pt4_lum($col['pill']) - pt4_lum($col['pilltext'])) < 0.35) $col['pilltext'] = pt4_readable($col['pill']);
    }
    $b = pt_begin($imgs, $sizeKey, $col['bg']);
    if (!$b) return null;
    [$im, $srcs, $W, $H] = $b;
    $p = pt2_parts($title, trim($cta), $sp);
    $fn = 'pt2_e_' . $engine;
    $fn($im, $srcs, $W, $H, $sp, $p, $col, trim($website));
    return pt_end($im, $srcs);
}
