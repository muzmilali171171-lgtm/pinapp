<?php
/**
 * 100 more pin templates:
 *   - 70 single-photo templates with the text directly ON the photo (no background box):
 *       30 single-colour text (crisp outline + drop shadow so it reads on any photo),
 *       30 "thickness" text (3D extruded letters),
 *       10 multi-coloured text (each line in its own colour);
 *     text placed in 11 different spots (top, upper third, centre, lower third, bottom, the four
 *     corners, left column, right column).
 *   - 30 collage templates (5 new layouts + the 5 collage layouts from pin_templates_60.php).
 * Photos are never darkened or blurred.
 *
 * Presets: pt3_presets() — merged into pt2_all_presets(), which pin_template_registry() and
 * pt2_render() use.
 */

/* ------------------------------------------------------------------ text effects ---------------- */

/** Draws one line with an effect. $x is the left edge; returns nothing. */
function pt3_fx_line($im, string $text, string $font, int $size, int $x, int $base, array $fx, array $fill): void
{
    $type = $fx['type'];
    if ($type === 'extrude') {
        $depth = max(3, (int)round($size * ($fx['depth'] ?? 0.09)));
        [$dx, $dy] = ['dr' => [1, 1], 'd' => [0, 1], 'dl' => [-1, 1], 'r' => [1, 0]][$fx['dir'] ?? 'dr'];
        $side = px_col($im, $fx['side']);
        $stroke = isset($fx['stroke']) ? px_col($im, $fx['stroke']) : null;
        $sw = (int)round($size * ($fx['sw'] ?? 0.05));
        // outline around the whole extruded body, then the side layers, then the face
        if ($stroke && $sw > 0) {
            for ($i = $depth; $i >= 0; $i -= max(1, (int)($depth / 4))) {
                for ($k = 0; $k < 16; $k++) {
                    $a = 2 * M_PI * $k / 16;
                    imagettftext($im, $size, 0, $x + $dx * $i + (int)round(cos($a) * $sw), $base + $dy * $i + (int)round(sin($a) * $sw), $stroke, $font, $text);
                }
            }
        }
        for ($i = $depth; $i >= 1; $i--) imagettftext($im, $size, 0, $x + $dx * $i, $base + $dy * $i, $side, $font, $text);
        imagettftext($im, $size, 0, $x, $base, px_col($im, $fill), $font, $text);
        return;
    }
    // outline (+ optional soft drop shadow) — works for single-colour and multi-colour text
    $sw = (int)round($size * ($fx['sw'] ?? 0.06));
    if (!empty($fx['shadow'])) {
        $sh = imagecolorallocatealpha($im, 0, 0, 0, 80);
        $off = max(3, (int)($size * 0.06));
        for ($d = 1; $d <= $off; $d++) imagettftext($im, $size, 0, $x + $d + $sw, $base + $d + $sw, $sh, $font, $text);
    }
    if ($sw > 0 && isset($fx['stroke'])) pt_outline_left($im, $size, $font, $text, $x, $base, px_col($im, $fill), px_col($im, $fx['stroke']), $sw);
    else imagettftext($im, $size, 0, $x, $base, px_col($im, $fill), $font, $text);
}

/** Text region + alignment for each position key. Returns [x1, x2, yAnchor, anchorMode, align, maxHfrac]. */
function pt3_region(string $pos, int $W, int $H): array
{
    $m = (int)($W * 0.06);
    switch ($pos) {
        case 'top':          return [$m, $W - $m, (int)($H * 0.05), 'top', 'center', 0.42];
        case 'upper':        return [$m, $W - $m, (int)($H * 0.3), 'mid', 'center', 0.4];
        case 'center':       return [$m, $W - $m, (int)($H * 0.5), 'mid', 'center', 0.45];
        case 'lower':        return [$m, $W - $m, (int)($H * 0.7), 'mid', 'center', 0.4];
        case 'bottom':       return [$m, $W - $m, (int)($H * 0.9), 'bottom', 'center', 0.42];
        case 'top-left':     return [$m, (int)($W * 0.8), (int)($H * 0.05), 'top', 'left', 0.42];
        case 'top-right':    return [(int)($W * 0.2), $W - $m, (int)($H * 0.05), 'top', 'right', 0.42];
        case 'bottom-left':  return [$m, (int)($W * 0.8), (int)($H * 0.9), 'bottom', 'left', 0.42];
        case 'bottom-right': return [(int)($W * 0.2), $W - $m, (int)($H * 0.9), 'bottom', 'right', 0.42];
        case 'left-col':     return [$m, (int)($W * 0.56), (int)($H * 0.45), 'mid', 'left', 0.6];
        case 'right-col':    return [(int)($W * 0.44), $W - $m, (int)($H * 0.45), 'mid', 'right', 0.6];
    }
    return [$m, $W - $m, (int)($H * 0.5), 'mid', 'center', 0.45];
}

/** onphoto: kicker / big number / title / sub drawn straight on the photo with the preset's effect. */
function pt2_e_onphoto($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    [$x1, $x2, $ay, $mode, $align, $hf] = pt3_region($sp['pos'] ?? 'center', $W, $H);
    $bw = $x2 - $x1;
    $fx = $sp['fx'];
    $tf = px_font($sp['tfont']);
    $kf = px_font($sp['kfont'] ?? 'Pacifico-Regular.ttf');
    $upper = $sp['upper'] ?? true;
    $extra = $fx['type'] === 'extrude' ? 0.16 : 0.12;         // room for outline / 3D depth between lines
    $fills = $col['fills'] ?? [$col['fg']];
    $maxH = (int)($H * $hf);
    $scale = 1.0;
    for ($pass = 0; $pass < 8; $pass++) {
        $rows = [];
        if ($p['kick'] !== '') {
            [$ks, $kl] = px_fit($kf, $p['kick'], $bw, (int)($maxH * 0.2), (int)($W / 14 * $scale), 1, 1.1);
            $rows[] = [$kl[0], $kf, $ks, $col['accent'], ['type' => 'outline', 'stroke' => $fx['stroke'] ?? $fx['side'] ?? [0, 0, 0], 'sw' => 0.05, 'shadow' => true]];
        }
        if ($p['big'] !== null) {
            $bs = (int)($W / 4.2 * $scale);
            $rows[] = [$p['big'], $tf, $bs, $col['accent2'] ?? $fills[0], $fx];
        }
        $tt = $upper ? px_upper($p['title']) : $p['title'];
        [$ts, $tl] = px_fit($tf, $tt, $bw, (int)($maxH * 0.7), (int)($W / 7.2 * ($sp['tscale'] ?? 1) * $scale), $sp['tlines'] ?? 4, 1.1);
        foreach ($tl as $i => $l) $rows[] = [$l, $tf, $ts, $fills[$i % count($fills)], $fx];
        if ($p['sub'] !== '') {
            [$ss, $sl] = px_fit($tf, $upper ? px_upper($p['sub']) : $p['sub'], $bw, (int)($maxH * 0.18), (int)($ts * 0.52), 1, 1.1);
            $rows[] = [$sl[0], $tf, $ss, $col['sub'] ?? $col['accent'], $fx];
        }
        $h = 0;
        foreach ($rows as $r) $h += pin_line_height($r[1], $r[2], 1.05, [$r[0]]) + (int)($r[2] * $extra);
        if ($h <= $maxH) break;
        $scale *= 0.9;
    }
    $y = $mode === 'top' ? $ay : ($mode === 'bottom' ? $ay - $h : $ay - (int)($h / 2));
    foreach ($rows as [$text, $font, $size, $fill, $rfx]) {
        $lh = pin_line_height($font, $size, 1.05, [$text]);
        [$a, $d] = px_metrics($size, $font);
        $tw = px_text_w($size, $font, $text);
        $x = $align === 'left' ? $x1 : ($align === 'right' ? $x2 - $tw : (int)(($x1 + $x2 - $tw) / 2));
        pt3_fx_line($im, $text, $font, $size, $x, $y + (int)(($lh + $a - $d) / 2), $rfx, $fill);
        $y += $lh + (int)($size * $extra);
    }
    // website: small pill on the opposite end from the text
    if ($website !== '') {
        $bottomText = $mode === 'bottom';
        pt_pill($im, pt_site($website), (int)($W / 2), $bottomText ? (int)($H * 0.04) : $H - (int)($H * 0.035), max(12, (int)($W / 34)),
            px_font('Poppins-Bold.ttf'), $col['pill'] ?? [255, 255, 255], $col['pilltext'] ?? [20, 20, 20]);
    }
}

/* ------------------------------------------------------------------ new collage engines --------- */

/** tri_rows: three stacked full-width photos, solid text band between row 1 and row 2. */
function pt2_e_tri_rows($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $g = (int)($W * 0.008);
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.86), (int)($H * 0.28), $col);
    $bh = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 22) : 0);
    $rest = $H - $bh - 2 * $g;
    $r1 = (int)($rest * 0.36); $r23 = (int)(($rest - $r1 - $g) / 2);
    pt_photo($im, $srcs, 0, 0, 0, $W, $r1, 0.25);
    $by = $r1 + $g;
    pt_photo($im, $srcs, 1, 0, $by + $bh + $g, $W, $r23, 0.25);
    pt_photo($im, $srcs, 2, 0, $by + $bh + 2 * $g + $r23, $W, $H - ($by + $bh + 2 * $g + $r23), 0.25);
    imagefilledrectangle($im, 0, $by, $W, $by + $bh, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', 0, $by, $W, $by + $bh, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.07), (int)($W * 0.93), $by + (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** center_circle: 4 quadrant photos with a big circle badge in the middle. */
function pt2_e_center_circle($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $g = (int)($W * 0.01);
    $cw = (int)(($W - $g) / 2); $ch = (int)(($H - $g) / 2);
    for ($r = 0; $r < 2; $r++) for ($c = 0; $c < 2; $c++) pt_photo($im, $srcs, $r * 2 + $c, $c * ($cw + $g), $r * ($ch + $g), $c ? $W - $cw - $g : $cw, $r ? $H - $ch - $g : $ch, 0.2);
    $d = (int)($W * ($sp['size'] ?? 0.66));
    $cx = (int)($W / 2); $cy = (int)($H / 2);
    imagefilledellipse($im, $cx, $cy, $d + 18, $d + 18, px_col($im, $col['border'] ?? [255, 255, 255]));
    imagefilledellipse($im, $cx, $cy, $d, $d, px_col($im, $col['bg']));
    $block = pt2_layout($sp, $p, $W, (int)($d * 0.68), (int)($d * 0.62), $col);
    pt2_draw($im, $block, $cx - (int)($d * 0.36), $cx + (int)($d * 0.36), $cy - (int)($block['h'] / 2), 'center', $W);
    pt2_site($im, $website, 'pill', $W, $H, $col);
}

/** bordered_offset: coloured page, three white-bordered photos in a staggered column, title on top. */
function pt2_e_bordered_offset($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    imagefilledrectangle($im, 0, 0, $W, $H, px_col($im, $col['bg']));
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.88), (int)($H * 0.26), $col);
    $y = pt2_draw($im, $block, (int)($W * 0.06), (int)($W * 0.94), (int)($W * 0.05), 'center', $W);
    $top = $y + (int)($W * 0.03);
    $bot = $H - ($website !== '' ? (int)($H * 0.07) : (int)($W * 0.04));
    $pw = (int)($W * 0.58); $ph = (int)(($bot - $top) * 0.52);
    $spots = [[0.04, 0.0], [0.38, 0.24], [0.08, 0.48]];
    foreach ($spots as $i => [$fx, $fy]) {
        $x = (int)($W * $fx); $yy = $top + (int)(($bot - $top) * $fy);
        $h = min($ph, $bot - $yy);
        imagefilledrectangle($im, $x - 10, $yy - 10, $x + $pw + 10, $yy + $h + 10, px_col($im, [255, 255, 255]));
        pt_photo($im, $srcs, $i, $x, $yy, $pw, $h, 0.2);
    }
    pt2_site($im, $website, 'text', $W, $H, $col, $H - (int)($H * 0.025));
}

/** header_grid: solid header with the title, 2×3 photo grid below. */
function pt2_e_header_grid($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.88), (int)($H * 0.28), $col);
    $hh = $block['h'] + (int)($W * 0.1) + ($website !== '' ? (int)($W / 22) : 0);
    imagefilledrectangle($im, 0, 0, $W, $hh, px_col($im, $col['bg']));
    pt2_deco($im, $sp['deco'] ?? 'none', 0, 0, $W, $hh, $col, $W);
    $y = pt2_draw($im, $block, (int)($W * 0.06), (int)($W * 0.94), (int)($W * 0.05), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
    $g = (int)($W * 0.008);
    $cols = 2; $rows = 3;
    $cw = (int)(($W - $g) / 2); $ch = (int)(($H - $hh - 3 * $g) / 3);
    for ($r = 0; $r < $rows; $r++) for ($c = 0; $c < $cols; $c++) {
        $x = $c * ($cw + $g); $yy = $hh + $g + $r * ($ch + $g);
        pt_photo($im, $srcs, $r * 2 + $c, $x, $yy, $c ? $W - $x : $cw, $r === 2 ? $H - $yy : $ch, 0.2);
    }
}

/** seam_outline: two photos (top / bottom), big outlined or 3D title across the seam — no box. */
function pt2_e_seam_outline($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    $g = (int)($W * 0.01);
    $h1 = (int)(($H - $g) / 2);
    pt_photo($im, $srcs, 0, 0, 0, $W, $h1, 0.25);
    pt_photo($im, $srcs, 1, 0, $h1 + $g, $W, $H - $h1 - $g, 0.25);
    imagefilledrectangle($im, 0, $h1, $W, $h1 + $g, px_col($im, [255, 255, 255]));
    pt2_e_onphoto_text($im, $W, $H, $sp + ['pos' => 'center'], $p, $col, '');
    pt2_site($im, $website, 'pill', $W, $H, $col);
}

/** Text-only part of the onphoto engine (used by seam_outline over an existing collage). */
function pt2_e_onphoto_text($im, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    // draw onto a copy trick: render onphoto with a transparent "photo" by temporarily skipping pt_photo
    [$x1, $x2, $ay, $mode, $align, $hf] = pt3_region($sp['pos'] ?? 'center', $W, $H);
    $fx = $sp['fx']; $tf = px_font($sp['tfont']); $fills = $col['fills'] ?? [$col['fg']];
    $tt = ($sp['upper'] ?? true) ? px_upper(trim(($p['big'] !== null ? $p['big'] . ' ' : '') . $p['title'])) : trim(($p['big'] !== null ? $p['big'] . ' ' : '') . $p['title']);
    [$ts, $tl] = px_fit($tf, $tt, $x2 - $x1, (int)($H * 0.4), (int)($W / 7.5), 4, 1.1);
    $lhs = array_map(fn($l) => pin_line_height($tf, $ts, 1.05, [$l]) + (int)($ts * 0.16), $tl);
    $y = $ay - (int)(array_sum($lhs) / 2);
    foreach ($tl as $i => $l) {
        [$a, $d] = px_metrics($ts, $tf);
        $tw = px_text_w($ts, $tf, $l);
        pt3_fx_line($im, $l, $tf, $ts, (int)(($W - $tw) / 2), $y + (int)(($lhs[$i] + $a - $d) / 2), $fx, $fills[$i % count($fills)]);
        $y += $lhs[$i];
    }
}

/* ------------------------------------------------------------------ presets --------------------- */

function pt3_presets(): array
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $W = [255, 255, 255]; $K = [12, 12, 12];
    $positions = ['top', 'bottom', 'center', 'upper', 'lower', 'top-left', 'bottom-left', 'top-right', 'bottom-right', 'left-col', 'right-col'];
    $posName = ['top' => 'Top', 'bottom' => 'Bottom', 'center' => 'Center', 'upper' => 'Upper', 'lower' => 'Lower', 'top-left' => 'Top Left',
        'bottom-left' => 'Bottom Left', 'top-right' => 'Top Right', 'bottom-right' => 'Bottom Right', 'left-col' => 'Left Column', 'right-col' => 'Right Column'];
    $fonts = [
        ['Anton-Regular.ttf', 'Tall', true], ['BebasNeue-Regular.ttf', 'Condensed', true], ['ArchivoBlack-Regular.ttf', 'Heavy', true],
        ['TitanOne-Regular.ttf', 'Rounded', true], ['LuckiestGuy-Regular.ttf', 'Comic', true], ['AbrilFatface-Regular.ttf', 'Serif', false],
        ['Poppins-Black.ttf', 'Modern', true], ['Lobster-Regular.ttf', 'Retro', false], ['DMSerifDisplay-Italic.ttf', 'Italic', false],
        ['Poppins-ExtraBold.ttf', 'Clean', false],
    ];
    $cats = ['Fashion', 'Food & Recipes', 'Home Decor', 'Beauty & Hair', 'Travel', 'Fitness', 'Holidays', 'Lifestyle', 'DIY & Crafts',
        'Wedding', 'Garden', 'Tips & Business', 'Kids & Parenting', 'Health & Wellness', 'Pets'];

    // --- 30 single-colour text on the photo ---
    $solid = [
        ['White', $W, $K], ['Black', $K, $W], ['Yellow', [255, 221, 0], $K], ['Hot Pink', [240, 36, 110], $W], ['Red', [226, 30, 36], $W],
        ['Cream', [255, 244, 222], [90, 50, 26]], ['Navy', [24, 40, 88], $W], ['Teal', [0, 150, 150], $W], ['Orange', [255, 130, 20], $K], ['Lime', [180, 250, 60], $K],
    ];
    for ($i = 0; $i < 30; $i++) {
        [$cn, $fill, $stroke] = $solid[$i % 10];
        [$ff, $fn, $up] = $fonts[($i * 3) % 10];
        $pos = $positions[($i * 4) % 11];
        $out['tpl3_solid_' . ($i + 1)] = [
            "$cn $fn Text · {$posName[$pos]}", $cats[$i % 15], 'single', 1, 'onphoto',
            ['pos' => $pos, 'tfont' => $ff, 'upper' => $up, 'kfont' => ['Pacifico-Regular.ttf', 'Satisfy-Regular.ttf', 'GreatVibes-Regular.ttf'][$i % 3],
             'bignum' => $i % 4 === 0, 'fx' => ['type' => 'outline', 'stroke' => $stroke, 'sw' => $i % 2 ? 0.07 : 0.05, 'shadow' => $i % 3 !== 1]],
            ['fg' => $fill, 'accent' => $fill, 'sub' => $fill, 'pill' => $stroke, 'pilltext' => $fill, 'bg' => $stroke],
        ];
    }

    // --- 30 thickness (3D extruded) text ---
    $three = [
        ['White/Black 3D', $W, $K, $K], ['Yellow/Red 3D', [255, 214, 0], [200, 30, 30], $K], ['Pink/Purple 3D', [255, 110, 170], [110, 30, 120], $W],
        ['Cream/Brown 3D', [255, 240, 214], [120, 66, 30], [60, 30, 10]], ['Mint/Teal 3D', [170, 255, 220], [0, 110, 100], $K], ['Orange/Navy 3D', [255, 140, 30], [20, 36, 80], $W],
        ['Red/Black 3D', [230, 36, 40], $K, $W], ['Sky/Blue 3D', [140, 210, 255], [20, 70, 160], $W], ['Gold/Brown 3D', [245, 196, 70], [110, 60, 10], [40, 20, 0]], ['Lilac/Plum 3D', [220, 190, 255], [90, 40, 120], $W],
    ];
    $dirs = ['dr', 'd', 'dl', 'r'];
    for ($i = 0; $i < 30; $i++) {
        [$cn, $fill, $side, $stroke] = $three[$i % 10];
        [$ff, $fn, $up] = $fonts[($i * 7 + 1) % 7];
        $pos = $positions[($i * 5 + 2) % 11];
        $out['tpl3_3d_' . ($i + 1)] = [
            "$cn $fn · {$posName[$pos]}", $cats[($i + 5) % 15], 'single', 1, 'onphoto',
            ['pos' => $pos, 'tfont' => $ff, 'upper' => $up, 'kfont' => ['Pacifico-Regular.ttf', 'Satisfy-Regular.ttf'][$i % 2], 'bignum' => $i % 3 === 0,
             'fx' => ['type' => 'extrude', 'side' => $side, 'stroke' => $stroke, 'depth' => [0.08, 0.1, 0.12][$i % 3], 'dir' => $dirs[$i % 4], 'sw' => 0.04]],
            ['fg' => $fill, 'accent' => $fill, 'sub' => $fill, 'pill' => $side, 'pilltext' => $W, 'bg' => $side],
        ];
    }

    // --- 10 multi-coloured text ---
    $multi = [
        ['Candy', [[255, 80, 140], [255, 214, 0], [70, 200, 255]]], ['Sunset', [[255, 200, 60], [255, 120, 50], [230, 40, 90]]],
        ['Tropical', [[0, 210, 170], [255, 214, 0], [255, 90, 120]]], ['Autumn', [[240, 160, 40], [200, 70, 30], [255, 236, 200]]],
        ['Retro', [[255, 90, 60], [255, 200, 40], [40, 160, 150]]], ['Neon', [[200, 255, 60], [255, 60, 200], [60, 220, 255]]],
        ['Berry', [[255, 130, 170], [190, 60, 140], $W]], ['Holiday', [[230, 40, 50], $W, [40, 160, 80]]],
        ['Ocean', [[120, 220, 255], $W, [255, 210, 90]]], ['Pastel', [[255, 190, 210], [190, 230, 255], [255, 240, 170]]],
    ];
    for ($i = 0; $i < 10; $i++) {
        [$cn, $fills] = $multi[$i];
        [$ff, $fn, $up] = $fonts[[0, 3, 4, 2, 6, 1, 3, 5, 0, 4][$i]];
        $pos = $positions[($i * 3 + 1) % 11];
        $fx = $i % 2
            ? ['type' => 'extrude', 'side' => $K, 'stroke' => $K, 'depth' => 0.07, 'dir' => 'dr', 'sw' => 0.035]
            : ['type' => 'outline', 'stroke' => $K, 'sw' => 0.06, 'shadow' => true];
        $out['tpl3_multi_' . ($i + 1)] = [
            "$cn Multi-Colour · {$posName[$pos]}", $cats[($i + 2) % 15], 'single', 1, 'onphoto',
            ['pos' => $pos, 'tfont' => $ff, 'upper' => $up, 'kfont' => 'Pacifico-Regular.ttf', 'fx' => $fx],
            ['fg' => $fills[0], 'fills' => $fills, 'accent' => $fills[1], 'sub' => $fills[2], 'pill' => $K, 'pilltext' => $W, 'bg' => $K],
        ];
    }

    // --- 30 collage templates ---
    $cl = function (string $key, string $name, string $cat, int $photos, string $engine, array $sp, array $col) use (&$out) {
        $out[$key] = [$name, $cat, 'collage', $photos, $engine, $sp, $col];
    };
    $cl('tpl3_c_rows_cream', 'Triple Rows Cream', 'Fashion', 3, 'tri_rows', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'rules'], ['bg' => [248, 242, 232], 'fg' => [50, 36, 28], 'accent' => [170, 120, 80], 'sub' => [110, 90, 70]]);
    $cl('tpl3_c_rows_red', 'Triple Rows Red', 'Food & Recipes', 3, 'tri_rows', ['tfont' => 'Anton-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'bignum' => true, 'bigscale' => 0.55], ['bg' => [206, 40, 36], 'fg' => $W, 'accent' => [255, 214, 120], 'sub' => [255, 230, 210]]);
    $cl('tpl3_c_rows_sage', 'Triple Rows Sage', 'Garden', 3, 'tri_rows', ['tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf', 'deco' => 'topline'], ['bg' => [206, 220, 196], 'fg' => [40, 60, 40], 'accent' => [90, 120, 80], 'sub' => [60, 80, 56]]);
    $cl('tpl3_c_circle_blush', 'Circle Center Blush', 'Beauty & Hair', 4, 'center_circle', ['tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'bignum' => true, 'bigscale' => 0.5], ['bg' => [250, 222, 228], 'fg' => [150, 40, 80], 'accent' => [210, 80, 120], 'sub' => [120, 50, 80], 'border' => $W, 'pill' => [150, 40, 80], 'pilltext' => $W]);
    $cl('tpl3_c_circle_black', 'Circle Center Noir', 'Fashion', 4, 'center_circle', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf'], ['bg' => $K, 'fg' => $W, 'accent' => [214, 176, 110], 'sub' => [214, 176, 110], 'border' => [214, 176, 110], 'pill' => $K, 'pilltext' => $W]);
    $cl('tpl3_c_circle_sun', 'Circle Center Sunny', 'Kids & Parenting', 4, 'center_circle', ['tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'size' => 0.7], ['bg' => [255, 206, 60], 'fg' => [120, 40, 10], 'accent' => [220, 70, 40], 'sub' => [120, 40, 10], 'border' => $W, 'pill' => [220, 70, 40], 'pilltext' => $W]);
    $cl('tpl3_c_offset_navy', 'Offset Frames Navy', 'Travel', 3, 'bordered_offset', ['tfont' => 'BebasNeue-Regular.ttf', 'tscale' => 1.3, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [24, 44, 80], 'fg' => $W, 'accent' => [255, 200, 110], 'sub' => [200, 214, 235]]);
    $cl('tpl3_c_offset_peach', 'Offset Frames Peach', 'Lifestyle', 3, 'bordered_offset', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [252, 214, 190], 'fg' => [110, 46, 30], 'accent' => [200, 90, 60], 'sub' => [110, 46, 30]]);
    $cl('tpl3_c_offset_olive', 'Offset Frames Olive', 'Home Decor', 3, 'bordered_offset', ['tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'GreatVibes-Regular.ttf', 'bignum' => true, 'bigscale' => 0.5], ['bg' => [96, 104, 70], 'fg' => $W, 'accent' => [240, 226, 190], 'sub' => [240, 226, 190]]);
    $cl('tpl3_c_header_white', 'Header + Six Grid', 'Fashion', 6, 'header_grid', ['tfont' => 'Anton-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'topline'], ['bg' => $W, 'fg' => $K, 'accent' => [220, 60, 90], 'sub' => [90, 90, 90]]);
    $cl('tpl3_c_header_choc', 'Chocolate Header Grid', 'Food & Recipes', 6, 'header_grid', ['tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'bignum' => true, 'bigscale' => 0.5], ['bg' => [84, 50, 34], 'fg' => [255, 236, 210], 'accent' => [240, 180, 110], 'sub' => [240, 210, 180]]);
    $cl('tpl3_c_header_mint', 'Mint Header Grid', 'Health & Wellness', 6, 'header_grid', ['tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [200, 238, 222], 'fg' => [20, 80, 70], 'accent' => [0, 140, 110], 'sub' => [20, 80, 70]]);
    $cl('tpl3_c_seam_white', 'Seam Outline White', 'Fashion', 2, 'seam_outline', ['tfont' => 'Anton-Regular.ttf', 'fx' => ['type' => 'outline', 'stroke' => $K, 'sw' => 0.07, 'shadow' => true]], ['fg' => $W, 'accent' => $W, 'pill' => $K, 'pilltext' => $W, 'bg' => $K]);
    $cl('tpl3_c_seam_3d', 'Seam 3D Yellow', 'Food & Recipes', 2, 'seam_outline', ['tfont' => 'LuckiestGuy-Regular.ttf', 'fx' => ['type' => 'extrude', 'side' => [200, 30, 30], 'stroke' => $K, 'depth' => 0.1, 'dir' => 'dr', 'sw' => 0.04]], ['fg' => [255, 214, 0], 'accent' => [255, 214, 0], 'pill' => [200, 30, 30], 'pilltext' => $W, 'bg' => $K]);
    $cl('tpl3_c_seam_multi', 'Seam Multi-Colour', 'Holidays', 2, 'seam_outline', ['tfont' => 'TitanOne-Regular.ttf', 'fx' => ['type' => 'outline', 'stroke' => $W, 'sw' => 0.07, 'shadow' => true]], ['fg' => [230, 40, 50], 'fills' => [[230, 40, 50], [30, 130, 70], [220, 170, 40]], 'accent' => [230, 40, 50], 'pill' => [30, 130, 70], 'pilltext' => $W, 'bg' => $K]);
    $cl('tpl3_c_band_lux', 'Luxe Grid Band', 'Wedding', 4, 'grid_band', ['cols' => 2, 'tfont' => 'GreatVibes-Regular.ttf', 'upper' => false, 'tscale' => 1.3, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'deco' => 'rules'], ['bg' => [252, 248, 242], 'fg' => [90, 70, 56], 'accent' => [190, 156, 100], 'sub' => [120, 104, 90]]);
    $cl('tpl3_c_band_pop', 'Pop Grid Band', 'Kids & Parenting', 4, 'grid_band', ['cols' => 2, 'tfont' => 'LuckiestGuy-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'alt' => true, 'deco' => 'dots'], ['bg' => [66, 165, 245], 'fg' => $W, 'accent' => [255, 214, 60], 'sub' => $W]);
    $cl('tpl3_c_band_fit', 'Fitness Grid Band', 'Fitness', 6, 'grid_band', ['cols' => 3, 'tfont' => 'Anton-Regular.ttf', 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'kspaced' => true], ['bg' => [230, 40, 40], 'fg' => $W, 'accent' => $K, 'sub' => $W]);
    $cl('tpl3_c_hero_teal', 'Teal Hero Collage', 'Travel', 4, 'hero', ['heroH' => 0.6, 'tfont' => 'Poppins-ExtraBold.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'radius' => 50], ['bg' => [0, 140, 140], 'fg' => $W, 'accent' => [255, 230, 150], 'sub' => [210, 245, 240], 'border' => $W]);
    $cl('tpl3_c_hero_bottom', 'Hero Bottom Collage', 'Home Decor', 4, 'hero', ['heroH' => 0.56, 'heroPos' => 'bottom', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'radius' => 8], ['bg' => [250, 246, 238], 'fg' => [44, 40, 36], 'accent' => [168, 130, 96], 'sub' => [110, 96, 84], 'border' => [44, 40, 36]]);
    $cl('tpl3_c_hero_berry', 'Berry Hero Collage', 'Beauty & Hair', 4, 'hero', ['heroH' => 0.58, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'bignum' => true, 'bigscale' => 0.5], ['bg' => [170, 40, 100], 'fg' => $W, 'accent' => [255, 200, 220], 'sub' => [255, 220, 235], 'border' => $W]);
    $cl('tpl3_c_frames_gold', 'Gold Frame Grid', 'Holidays', 4, 'frame_grid', ['tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'deco' => 'sparkle', 'gap' => 0.03], ['bg' => [20, 60, 44], 'fg' => $W, 'accent' => [232, 190, 100], 'sub' => [220, 236, 226], 'gutter' => [232, 190, 100], 'border' => [232, 190, 100]]);
    $cl('tpl3_c_frames_craft', 'Craft Rounded Grid', 'DIY & Crafts', 6, 'frame_grid', ['round' => true, 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf'], ['bg' => [255, 244, 220], 'fg' => [60, 90, 140], 'accent' => [230, 90, 80], 'sub' => [60, 90, 140], 'gutter' => [214, 180, 132], 'border' => [60, 90, 140]]);
    $cl('tpl3_c_frames_mono', 'Mono Frame Grid', 'Lifestyle', 4, 'frame_grid', ['tfont' => 'Anton-Regular.ttf', 'kfont' => 'Poppins-Regular.ttf', 'kupper' => true, 'kspaced' => true, 'radius' => 0, 'gap' => 0.02], ['bg' => $K, 'fg' => $W, 'accent' => [200, 200, 200], 'sub' => [200, 200, 200], 'gutter' => $W]);
    $cl('tpl3_c_strips_gold', 'Gold Strips', 'Wedding', 3, 'strips', ['pos' => 'middle', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'GreatVibes-Regular.ttf', 'edge' => true], ['bg' => [252, 248, 240], 'fg' => [70, 56, 44], 'accent' => [190, 156, 100], 'sub' => [120, 104, 90]]);
    $cl('tpl3_c_strips_neon', 'Neon Strips', 'Fitness', 4, 'strips', ['pos' => 'bottom', 'tfont' => 'BebasNeue-Regular.ttf', 'tscale' => 1.35, 'kfont' => 'Poppins-Bold.ttf', 'kupper' => true, 'edge' => true], ['bg' => [16, 16, 20], 'fg' => $W, 'accent' => [200, 255, 60], 'sub' => [200, 255, 60]]);
    $cl('tpl3_c_strips_rose', 'Rose Strips', 'Beauty & Hair', 4, 'strips', ['pos' => 'middle', 'tfont' => 'Poppins-ExtraBold.ttf', 'upper' => false, 'kfont' => 'Pacifico-Regular.ttf'], ['bg' => [255, 236, 240], 'fg' => [170, 40, 90], 'accent' => [220, 90, 130], 'sub' => [140, 60, 90]]);
    $cl('tpl3_c_column_navy', 'Navy Column Collage', 'Tips & Business', 4, 'column', ['side' => 'right', 'tfont' => 'ArchivoBlack-Regular.ttf', 'kfont' => 'Satisfy-Regular.ttf', 'bignum' => true, 'bigscale' => 0.5], ['bg' => [24, 40, 80], 'fg' => $W, 'accent' => [255, 200, 110], 'sub' => [200, 214, 235]]);
    $cl('tpl3_c_column_leaf', 'Leaf Column Collage', 'Garden', 4, 'column', ['side' => 'left', 'tfont' => 'AbrilFatface-Regular.ttf', 'upper' => false, 'kfont' => 'Satisfy-Regular.ttf'], ['bg' => [52, 96, 56], 'fg' => $W, 'accent' => [210, 236, 180], 'sub' => [210, 236, 180]]);
    $cl('tpl3_c_column_pet', 'Pet Column Collage', 'Pets', 4, 'column', ['side' => 'right', 'tfont' => 'TitanOne-Regular.ttf', 'kfont' => 'Pacifico-Regular.ttf', 'deco' => 'dots'], ['bg' => [255, 176, 70], 'fg' => [70, 36, 10], 'accent' => [150, 70, 20], 'sub' => [90, 50, 20]]);
    return $out;
}

/** All preset templates (60 + 100). */
function pt2_all_presets(): array
{
    return pt2_presets() + pt3_presets() + (function_exists('pt4_presets') ? pt4_presets() : []);
}
