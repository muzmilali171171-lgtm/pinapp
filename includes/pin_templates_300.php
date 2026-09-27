<?php
/**
 * 300 more pin templates across 48 categories: 200 single-photo + 100 collage.
 * Each category has its own theme (colours + font pairing); each template in a category uses a
 * different layout engine and colour variant, so no two templates look alike.
 *
 * New single-photo engines here: duo (tag + band), window (arched photo window on a coloured page),
 * quote (quote-mark card). The other engines live in pin_templates_60.php / pin_templates_100.php.
 * Photos are never darkened or blurred.
 */

/* ------------------------------------------------------------------ new engines ------------------ */

/** duo: small kicker tag in a top corner + solid title band at the bottom. */
function pt2_e_duo($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $kick = $p['kick'] !== '' ? $p['kick'] : ($p['big'] !== null ? $p['big'] . ' Ideas' : '');
    if ($kick !== '') {
        $kf = px_font('Poppins-ExtraBold.ttf');
        [$ks, $kl] = px_fit($kf, px_upper($kick), (int)($W * 0.6), (int)($W * 0.1), (int)($W / 26), 1, 1.1);
        [$a, $d] = px_metrics($ks, $kf);
        $tw = px_text_w($ks, $kf, $kl[0]);
        $x = ($sp['tag'] ?? 'left') === 'left' ? (int)($W * 0.05) : $W - (int)($W * 0.05) - $tw - (int)($ks * 1.4);
        $y = (int)($H * 0.04);
        px_rrect($im, $x, $y, $x + $tw + (int)($ks * 1.4), $y + $a + $d + (int)($ks * 0.9), (int)($ks * 0.4), px_col($im, $col['accent']));
        imagettftext($im, $ks, 0, $x + (int)($ks * 0.7), $y + (int)($ks * 0.45) + $a, px_col($im, $col['kfg'] ?? [255, 255, 255]), $kf, $kl[0]);
    }
    $p2 = $p; $p2['kick'] = '';
    $block = pt2_layout($sp, $p2, $W, (int)($W * 0.86), (int)($H * 0.34), $col);
    $pad = (int)($W * 0.055);
    $bh = $block['h'] + $pad * 2 + ($website !== '' ? (int)($W / 22) : 0);
    imagefilledrectangle($im, 0, $H - $bh, $W, $H, px_col($im, $col['bg']));
    imagefilledrectangle($im, 0, $H - $bh, $W, $H - $bh + 6, px_col($im, $col['accent']));
    $y = pt2_draw($im, $block, (int)($W * 0.07), (int)($W * 0.93), $H - $bh + $pad, $sp['align'] ?? 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** window: coloured page, photo in an arched (or rounded) window, text block above or below. */
function pt2_e_window($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    imagefilledrectangle($im, 0, 0, $W, $H, px_col($im, $col['bg']));
    $block = pt2_layout($sp, $p, $W, (int)($W * 0.86), (int)($H * 0.3), $col);
    $th = $block['h'] + (int)($W * 0.08) + ($website !== '' ? (int)($W / 22) : 0);
    $textTop = ($sp['text'] ?? 'bottom') === 'top';
    $m = (int)($W * 0.07);
    $wy1 = $textTop ? $th + (int)($W * 0.02) : $m;
    $wy2 = $textTop ? $H - $m : $H - $th - (int)($W * 0.02);
    $ww = $W - 2 * $m; $wh = $wy2 - $wy1;
    // photo, then mask the corners (arch = half-circle top) in the page colour
    pt_photo($im, $srcs, 0, $m, $wy1, $ww, $wh, 0.3);
    $bg = px_col($im, $col['bg']);
    if (($sp['shape'] ?? 'arch') === 'arch') {
        $r = (int)($ww / 2);
        $mask = imagecreatetruecolor($ww * 2, $r * 2);
        $key = imagecolorallocate($mask, 255, 0, 254);
        imagefilledrectangle($mask, 0, 0, $ww * 2, $r * 2, imagecolorallocate($mask, $col['bg'][0], $col['bg'][1], $col['bg'][2]));
        imagefilledellipse($mask, $ww, $r * 2, $ww * 2, $r * 4, $key);
        imagecolortransparent($mask, $key);
        $tmp = imagecreatetruecolor($ww * 2, $r * 2);
        imagecopyresampled($tmp, $im, 0, 0, $m, $wy1, $ww * 2, $r * 2, $ww, $r);
        imagecopymerge($tmp, $mask, 0, 0, 0, 0, $ww * 2, $r * 2, 100);
        imagecopyresampled($im, $tmp, $m, $wy1, 0, 0, $ww, $r, $ww * 2, $r * 2);
        imagedestroy($tmp); imagedestroy($mask);
        if (!empty($col['border'])) for ($t = 0; $t < 4; $t++) imagearc($im, $m + $r, $wy1 + $r, $ww + 12 + $t, $ww + 12 + $t, 180, 360, px_col($im, $col['border']));
    } else {
        pt_photo_round($im, $srcs, 0, $m, $wy1, $ww, $wh, (int)($W * 0.06), $col['bg'], 0.3);
    }
    $y = pt2_draw($im, $block, $m, $W - $m, $textTop ? (int)($W * 0.04) : $H - $th + (int)($W * 0.01), 'center', $W);
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 40));
}

/** quote: card with big quote marks, the title as the quote, kicker as the "author" line. */
function pt2_e_quote($im, $srcs, int $W, int $H, array $sp, array $p, array $col, string $website): void
{
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $cw = (int)($W * 0.84);
    $p2 = $p; $author = $p['kick']; $p2['kick'] = '';
    $block = pt2_layout($sp, $p2, $W, (int)($cw * 0.82), (int)($H * 0.4), $col);
    $qf = px_font('AbrilFatface-Regular.ttf');
    $qs = (int)($W / 5);
    $ch = $block['h'] + (int)($W * 0.26) + ($author !== '' ? (int)($W / 13) : 0) + ($website !== '' ? (int)($W / 22) : 0);
    $x1 = (int)(($W - $cw) / 2);
    $y1 = ($sp['pos'] ?? 'center') === 'bottom' ? $H - $ch - (int)($H * 0.06) : (int)(($H - $ch) / 2);
    px_rrect($im, $x1, $y1, $x1 + $cw, $y1 + $ch, (int)($W * 0.04), px_col($im, $col['bg']));
    [$qa] = px_metrics($qs, $qf);
    px_center_text($im, $qs, $qf, '“', (int)($W / 2), $y1 + (int)($qa * 0.95), px_col($im, $col['accent']));
    $y = pt2_draw($im, $block, $x1 + (int)($cw * 0.09), $x1 + $cw - (int)($cw * 0.09), $y1 + (int)($W * 0.17), 'center', $W);
    if ($author !== '') {
        $af = px_font('Satisfy-Regular.ttf'); $as = (int)($W / 22);
        [$aa] = px_metrics($as, $af);
        px_center_text($im, $as, $af, '— ' . $author, (int)($W / 2), $y + (int)($W * 0.03) + $aa, px_col($im, $col['accent']));
        $y += (int)($W / 13);
    }
    pt2_site($im, $website, 'text', $W, $H, $col, $y + (int)($W / 36));
}

/* ------------------------------------------------------------------ category themes -------------- */

/** 48 categories: [name, primary bg, text on primary, accent, light tint, title font, kicker font, uppercase?] */
function pt4_themes(): array
{
    $W = [255, 255, 255];
    return [
        ['Home Decor', [206, 190, 170], [52, 42, 34], [140, 100, 70], [246, 240, 232], 'AbrilFatface-Regular.ttf', 'Satisfy-Regular.ttf', false],
        ['Interior Design', [44, 48, 52], $W, [196, 164, 120], [240, 238, 234], 'Poppins-ExtraBold.ttf', 'Poppins-Regular.ttf', true],
        ['DIY & Crafts', [214, 180, 132], [70, 44, 20], [220, 80, 70], [255, 244, 222], 'TitanOne-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Fashion', [20, 20, 20], $W, [214, 176, 110], [248, 244, 238], 'Anton-Regular.ttf', 'GreatVibes-Regular.ttf', true],
        ['Beauty', [248, 214, 222], [120, 30, 60], [214, 80, 120], [255, 242, 245], 'Poppins-ExtraBold.ttf', 'Pacifico-Regular.ttf', false],
        ['Hairstyles', [120, 70, 50], $W, [240, 196, 150], [250, 238, 228], 'AbrilFatface-Regular.ttf', 'Satisfy-Regular.ttf', false],
        ['Makeup', [170, 30, 70], $W, [255, 190, 200], [255, 236, 240], 'TitanOne-Regular.ttf', 'GreatVibes-Regular.ttf', true],
        ['Skincare', [222, 236, 228], [40, 80, 70], [110, 160, 140], [246, 250, 247], 'Poppins-ExtraBold.ttf', 'Satisfy-Regular.ttf', false],
        ['Fitness', [18, 18, 22], $W, [200, 255, 60], [240, 250, 230], 'Anton-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Health & Wellness', [200, 230, 222], [20, 80, 70], [0, 140, 120], [240, 250, 247], 'Poppins-ExtraBold.ttf', 'Satisfy-Regular.ttf', false],
        ['Food & Recipes', [206, 52, 36], $W, [255, 206, 90], [255, 246, 232], 'ArchivoBlack-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Baking', [240, 214, 180], [110, 60, 30], [210, 110, 80], [255, 248, 236], 'TitanOne-Regular.ttf', 'Pacifico-Regular.ttf', false],
        ['Desserts', [255, 190, 210], [120, 40, 80], [150, 90, 200], [255, 240, 246], 'LuckiestGuy-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Travel', [0, 96, 140], $W, [255, 206, 110], [230, 244, 250], 'BebasNeue-Regular.ttf', 'Satisfy-Regular.ttf', true],
        ['Photography', [30, 30, 30], $W, [240, 180, 60], [245, 245, 245], 'Poppins-ExtraBold.ttf', 'Poppins-Regular.ttf', true],
        ['Wedding', [252, 248, 242], [80, 64, 56], [190, 156, 100], [255, 252, 248], 'AbrilFatface-Regular.ttf', 'GreatVibes-Regular.ttf', false],
        ['Bridal Ideas', [246, 228, 226], [110, 70, 76], [196, 150, 130], [255, 246, 244], 'DMSerifDisplay-Italic.ttf', 'GreatVibes-Regular.ttf', false],
        ['Party Ideas', [120, 40, 200], $W, [255, 214, 0], [245, 236, 255], 'LuckiestGuy-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Parenting', [255, 200, 150], [100, 50, 30], [80, 150, 200], [255, 246, 238], 'TitanOne-Regular.ttf', 'Satisfy-Regular.ttf', false],
        ['Kids Activities', [66, 165, 245], $W, [255, 214, 60], [236, 246, 255], 'LuckiestGuy-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Education', [30, 60, 110], $W, [255, 196, 80], [236, 242, 250], 'ArchivoBlack-Regular.ttf', 'Satisfy-Regular.ttf', true],
        ['Quotes & Motivation', [250, 246, 238], [30, 30, 30], [220, 120, 90], [255, 252, 246], 'DMSerifDisplay-Italic.ttf', 'Satisfy-Regular.ttf', false],
        ['Business', [22, 40, 70], $W, [90, 170, 255], [236, 242, 250], 'Poppins-ExtraBold.ttf', 'Poppins-Bold.ttf', true],
        ['Entrepreneurship', [16, 16, 16], $W, [255, 120, 40], [245, 245, 245], 'Anton-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Marketing', [255, 80, 70], $W, [30, 30, 60], [255, 238, 236], 'ArchivoBlack-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Blogging', [250, 222, 200], [70, 40, 30], [220, 90, 70], [255, 246, 240], 'Poppins-ExtraBold.ttf', 'Pacifico-Regular.ttf', false],
        ['Money & Finance', [16, 80, 60], $W, [180, 240, 150], [236, 248, 240], 'ArchivoBlack-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Technology', [14, 20, 40], $W, [0, 220, 255], [230, 240, 250], 'Poppins-Black.ttf', 'Poppins-Regular.ttf', true],
        ['Gadgets', [40, 40, 48], $W, [255, 90, 60], [240, 240, 244], 'BebasNeue-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Art', [236, 90, 60], $W, [40, 60, 140], [255, 240, 232], 'AbrilFatface-Regular.ttf', 'Pacifico-Regular.ttf', false],
        ['Drawing & Sketching', [245, 240, 228], [40, 40, 40], [120, 120, 120], [255, 253, 248], 'Poppins-ExtraBold.ttf', 'Satisfy-Regular.ttf', false],
        ['Digital Art', [90, 30, 160], $W, [0, 230, 200], [240, 232, 255], 'Poppins-Black.ttf', 'Pacifico-Regular.ttf', true],
        ['Graphic Design', [255, 214, 0], [16, 16, 16], [240, 40, 90], [255, 250, 220], 'ArchivoBlack-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Tattoo Ideas', [16, 16, 16], [240, 230, 214], [200, 40, 40], [240, 236, 228], 'Lobster-Regular.ttf', 'GreatVibes-Regular.ttf', false],
        ['Gardening', [52, 96, 56], $W, [210, 236, 180], [240, 248, 236], 'Poppins-ExtraBold.ttf', 'Satisfy-Regular.ttf', false],
        ['Plants', [196, 220, 176], [36, 70, 40], [110, 150, 80], [246, 250, 240], 'AbrilFatface-Regular.ttf', 'Satisfy-Regular.ttf', false],
        ['Pets & Animals', [255, 176, 70], [70, 36, 10], [150, 70, 20], [255, 244, 228], 'TitanOne-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Cars & Automobiles', [180, 20, 24], $W, [20, 20, 20], [245, 240, 240], 'Anton-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Architecture', [210, 206, 198], [30, 30, 30], [120, 110, 100], [245, 244, 240], 'Poppins-ExtraBold.ttf', 'Poppins-Regular.ttf', true],
        ['Lifestyle', [230, 90, 70], $W, [255, 230, 210], [255, 244, 238], 'Poppins-ExtraBold.ttf', 'Satisfy-Regular.ttf', false],
        ['Books & Reading', [110, 60, 40], [255, 244, 224], [230, 180, 110], [250, 242, 230], 'AbrilFatface-Regular.ttf', 'Satisfy-Regular.ttf', false],
        ['Movies & TV', [20, 20, 20], [255, 214, 60], [220, 30, 40], [240, 240, 240], 'BebasNeue-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Music', [30, 10, 60], $W, [255, 70, 180], [240, 232, 250], 'Anton-Regular.ttf', 'Pacifico-Regular.ttf', true],
        ['Gaming', [10, 10, 30], [0, 255, 170], [255, 40, 180], [232, 240, 250], 'LuckiestGuy-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Fitness & Workout', [230, 40, 40], $W, [16, 16, 16], [255, 236, 236], 'Anton-Regular.ttf', 'Poppins-Bold.ttf', true],
        ['Recipes & Meal Planning', [100, 150, 60], $W, [255, 214, 90], [244, 250, 236], 'Poppins-ExtraBold.ttf', 'Pacifico-Regular.ttf', true],
        ['Organization & Productivity', [236, 232, 222], [30, 50, 70], [60, 140, 180], [250, 248, 244], 'Poppins-ExtraBold.ttf', 'Poppins-Regular.ttf', true],
        ['Seasonal & Holiday Ideas', [160, 20, 36], $W, [230, 190, 100], [250, 240, 232], 'AbrilFatface-Regular.ttf', 'GreatVibes-Regular.ttf', false],
        ['Gift Ideas', [220, 50, 90], $W, [255, 214, 120], [255, 240, 244], 'TitanOne-Regular.ttf', 'Pacifico-Regular.ttf', true],
    ];
}

/* ------------------------------------------------------------------ presets ---------------------- */

function pt4_presets(): array
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $themes = pt4_themes();
    $W = [255, 255, 255]; $K = [14, 14, 14];

    // Colour variants of a theme: panel colour / text / accent.
    $variant = function (array $t, int $v) use ($W, $K): array {
        [, $bg, $fg, $acc, $tint] = $t;
        switch ($v % 4) {
            case 0: return ['bg' => $bg, 'fg' => $fg, 'accent' => $acc, 'sub' => $fg, 'kfg' => $fg, 'pill' => $bg, 'pilltext' => $fg, 'border' => $W, 'vname' => 'Classic'];
            case 1: return ['bg' => $tint, 'fg' => $bg === $tint ? $K : pt4_dark($bg, $fg), 'accent' => $acc, 'sub' => pt4_dark($bg, $fg), 'kfg' => $W, 'pill' => pt4_dark($bg, $fg), 'pilltext' => $W, 'border' => $acc, 'vname' => 'Light'];
            case 2: return ['bg' => $acc, 'fg' => pt4_readable($acc), 'accent' => pt4_readable($acc) === $W ? $tint : $bg, 'sub' => pt4_readable($acc), 'kfg' => pt4_readable($acc), 'pill' => $acc, 'pilltext' => pt4_readable($acc), 'border' => $W, 'vname' => 'Bright'];
            default: return ['bg' => $W, 'fg' => pt4_dark($bg, $fg), 'accent' => $acc, 'sub' => [100, 100, 100], 'kfg' => $W, 'pill' => pt4_dark($bg, $fg), 'pilltext' => $W, 'border' => pt4_dark($bg, $fg), 'vname' => 'Clean'];
        }
    };

    $single = [
        ['band', 'Color Band', ['pos' => 'bottom', 'deco' => 'rules']], ['card', 'Floating Card', ['pos' => 'bottom', 'deco' => 'corners']],
        ['side', 'Side Panel', ['side' => 'left', 'edge' => true]], ['block', 'Color Block', ['pos' => 'bottom', 'deco' => 'topline']],
        ['labels', 'Label Stack', ['pos' => 'bottom', 'align' => 'left', 'alt' => true]], ['badge', 'Circle Badge', ['pos' => 'top', 'size' => 0.7, 'deco' => 'ring']],
        ['frame', 'Photo Frame', ['pos' => 'bottom']], ['onphoto', 'Outline Text', ['pos' => 'top']],
        ['tab', 'Hanging Tab', []], ['duo', 'Tag + Band', ['tag' => 'left']],
        ['window', 'Arch Window', ['shape' => 'arch', 'text' => 'bottom']], ['quote', 'Quote Card', ['pos' => 'center']],
        ['band', 'Top Band', ['pos' => 'top', 'edge' => true, 'site' => 'pill']], ['card', 'Center Card', ['pos' => 'center', 'deco' => 'sparkle']],
        ['onphoto', '3D Text', ['pos' => 'bottom', '3d' => true]], ['block', 'Wave Block', ['pos' => 'bottom', 'wave' => true]],
        ['side', 'Right Panel', ['side' => 'right']], ['window', 'Rounded Window', ['shape' => 'round', 'text' => 'top']],
        ['labels', 'Center Labels', ['pos' => 'center']], ['duo', 'Corner Tag Band', ['tag' => 'right']],
        ['badge', 'Oval Badge', ['pos' => 'center', 'size' => 0.76, 'oval' => 0.8]], ['frame', 'Top Frame', ['pos' => 'top']],
        ['onphoto', 'Corner Text', ['pos' => 'top-left']], ['tab', 'Wide Tab', ['cw' => 0.82]],
        ['band', 'Middle Band', ['pos' => 'middle', 'site' => 'pill', 'edge' => true]], ['onphoto', 'Side Text', ['pos' => 'right-col', '3d' => true]],
    ];
    $collage = [
        ['grid_band', 'Grid Band', 4, ['cols' => 2, 'deco' => 'rules']], ['hero', 'Hero + Row', 4, ['heroH' => 0.58, 'radius' => 20]],
        ['frame_grid', 'Frame Grid', 4, ['round' => true]], ['strips', 'Photo Strips', 3, ['pos' => 'middle', 'edge' => true]],
        ['column', 'Photo Column', 4, ['side' => 'left', 'deco' => 'topline']], ['tri_rows', 'Triple Rows', 3, ['deco' => 'rules']],
        ['center_circle', 'Circle Center', 4, ['size' => 0.64]], ['bordered_offset', 'Offset Frames', 3, []],
        ['header_grid', 'Header Grid', 6, ['deco' => 'topline']], ['seam_outline', 'Split Title', 2, []],
        ['grid_band', 'Three-Up Band', 6, ['cols' => 3]], ['hero', 'Hero Bottom', 4, ['heroPos' => 'bottom', 'heroH' => 0.55, 'radius' => 8]],
        ['frame_grid', 'Square Grid', 6, ['gap' => 0.02, 'radius' => 12]], ['strips', 'Four Strips', 4, ['pos' => 'bottom']],
        ['column', 'Right Column', 4, ['side' => 'right']],
    ];

    $nThemes = count($themes);
    // 200 single: every category gets 4, then 8 extra (first 8 categories get a 5th)
    for ($i = 0; $i < 200; $i++) {
        $ti = $i < $nThemes * 4 ? intdiv($i, 4) : $i - $nThemes * 4;
        $slot = $i < $nThemes * 4 ? $i % 4 : 4;
        $t = $themes[$ti];
        [$engine, $label, $spx] = $single[($ti * 5 + $slot * 7) % count($single)];
        $col = $variant($t, $ti + $slot);
        $sp = $spx + ['tfont' => $t[5], 'kfont' => $t[6], 'upper' => $t[7], 'bignum' => ($i % 3 === 0), 'bigscale' => 0.6, 'kicker' => 'cta'];
        if ($engine === 'onphoto') {
            $col['fg'] = pt4_readable_on_photo($t);
            $col['accent'] = $col['fg'];
            $col['sub'] = $col['fg'];
            $sp['fx'] = !empty($spx['3d'])
                ? ['type' => 'extrude', 'side' => $t[3] === $col['fg'] ? $K : $t[3], 'stroke' => $K, 'depth' => 0.09, 'dir' => 'dr', 'sw' => 0.04]
                : ['type' => 'outline', 'stroke' => $col['fg'] === $W ? $K : $W, 'sw' => 0.06, 'shadow' => true];
            $col['pill'] = $K; $col['pilltext'] = $W; $col['bg'] = $K;
        }
        $key = 'tpl4_' . pt4_slug($t[0]) . '_s' . ($slot + 1);
        $out[$key] = [$t[0] . ' · ' . $label . ' (' . $col['vname'] . ')', $t[0], 'single', 1, $engine, $sp, $col];
    }
    // 100 collage: every category gets 2, then 4 extra
    for ($i = 0; $i < 100; $i++) {
        $ti = $i < $nThemes * 2 ? intdiv($i, 2) : $i - $nThemes * 2;
        $slot = $i < $nThemes * 2 ? $i % 2 : 2;
        $t = $themes[$ti];
        [$engine, $label, $photos, $spx] = $collage[($ti * 3 + $slot * 8) % count($collage)];
        $col = $variant($t, $ti + $slot + 1);
        $col['gutter'] = $slot % 2 ? $t[4] : [255, 255, 255];
        $sp = $spx + ['tfont' => $t[5], 'kfont' => $t[6], 'upper' => $t[7], 'bignum' => ($i % 4 === 1), 'bigscale' => 0.5, 'kicker' => 'cta'];
        if ($engine === 'seam_outline') {
            $col['fg'] = pt4_readable_on_photo($t);
            $sp['fx'] = ['type' => 'extrude', 'side' => $K, 'stroke' => $K, 'depth' => 0.07, 'dir' => 'dr', 'sw' => 0.04];
            $col['pill'] = $K; $col['pilltext'] = $W;
        }
        $key = 'tpl4_' . pt4_slug($t[0]) . '_c' . ($slot + 1);
        $out[$key] = [$t[0] . ' · ' . $label . ' (' . $col['vname'] . ')', $t[0], 'collage', $photos, $engine, $sp, $col];
    }
    return $out;
}

function pt4_slug(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($s)), '_');
}

/** Relative luminance 0..1 */
function pt4_lum(array $c): float
{
    return (0.299 * $c[0] + 0.587 * $c[1] + 0.114 * $c[2]) / 255;
}

/** Black or white — whichever reads better on $bg. */
function pt4_readable(array $bg): array
{
    return pt4_lum($bg) > 0.6 ? [18, 18, 18] : [255, 255, 255];
}

/** The darker of a theme's two main colours (used for text on light panels). */
function pt4_dark(array $a, array $b): array
{
    $d = pt4_lum($a) < pt4_lum($b) ? $a : $b;
    return pt4_lum($d) > 0.55 ? [30, 30, 30] : $d;
}

/** Bright, saturated colour for text sitting directly on a photo (with its outline/3D). */
function pt4_readable_on_photo(array $t): array
{
    foreach ([$t[3], $t[1], $t[2]] as $c) {
        $l = pt4_lum($c);
        if ($l > 0.55 && max($c) - min($c) > 60) return $c;
    }
    return [255, 255, 255];
}
