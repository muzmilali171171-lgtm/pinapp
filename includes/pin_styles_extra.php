<?php
/**
 * Extra pin-image styles (added on top of the originals in ai_functions.php).
 *
 *   home_decor3   Hand-Lettered Grid   — 8-photo grid, huge black lowercase title with a cream outline, spaced website strip
 *   home_decor4   Dark Badge Grid      — 4-photo grid, dark rounded "blob" badge in the middle with peach text
 *   home_decor5   Script Overlay       — one full photo, white title lines on translucent boxes + a script line
 *   home_decor6   Badge + Button Grid  — 4-photo grid, white scalloped badge, subtitle, pink button, pink website bar
 *   recipe_food3  Outlined Number Band — photo / white band (outlined number + stacked words + big lowercase line) / photo
 *   recipe_food4  Gold Number Split    — two photos with a dark gradient middle: gold number, white title, gold divider line, italic line
 *   recipe_food5  Green Block Title    — full photo with dark-green rounded text blocks + subtitle pill
 *   recipe_food6  Boxed Title Split    — photo / bordered white title box with a tan website tag / photo
 *   recipe_food7  Brush Label          — full photo, white brush-edged title label on top, brush website tag at the bottom
 *   recipe_food8  Bold Outline Grid    — 8-photo grid, huge white uppercase title with black outline, orange subtitle pill
 *   recipe_food9  Black Band Label     — photo / black band with a small white label + white title / photo
 *   recipe_food10 Framed Card          — photo / white card with a tan inner frame, kicker, serif title, brand tag / photo
 *
 * Every style works with 1 image or many: grid styles re-crop and mirror photos to fill
 * extra cells when fewer images were generated than the grid has cells.
 * Subtitles/buttons use the CTA text; footers use the website. Both are skipped when empty.
 * A brand color palette (if the user enabled one) replaces each style's accent colors.
 */

/* ============================== helpers ============================== */

function px_font(string $name): string
{
    $path = __DIR__ . '/../assets/fonts/' . $name;
    // A missing or broken (e.g. a saved "404" page) font file would blank out the text — fall back to Poppins.
    if (!is_file($path) || filesize($path) < 1024) {
        $fallback = stripos($name, 'Regular') !== false ? 'Poppins-Regular.ttf' : 'Poppins-Bold.ttf';
        return __DIR__ . '/../assets/fonts/' . $fallback;
    }
    return $path;
}

function px_col($im, array $rgb, int $alpha = 0): int
{
    return imagecolorallocatealpha($im, (int)$rgb[0], (int)$rgb[1], (int)$rgb[2], max(0, min(127, $alpha)));
}

function px_text_w(int $size, string $font, string $text): int
{
    $b = imagettfbbox($size, 0, $font, $text);
    return (int)($b[2] - $b[0]);
}

/** [ascent, descent] of a font at a size (baseline-relative, both positive). */
function px_metrics(int $size, string $font): array
{
    $b = imagettfbbox($size, 0, $font, 'Hdgjy');
    return [(int)(-$b[7]), (int)max(0, $b[1])];
}

function px_lower(string $t): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
}

function px_upper(string $t): string
{
    return pin_image_upper($t);
}

/** Splits a leading listicle number ("25", "35+") off the title: [number|null, rest]. */
function px_split_number(string $title): array
{
    $title = trim($title);
    if (preg_match('/^(\d{1,4}\+?)\s+(.+)$/u', $title, $m)) {
        return [$m[1], trim($m[2])];
    }
    return [null, $title];
}

/** Splits words into [first part, last $n words] (used for script / italic tail lines). */
function px_split_tail(string $text, int $n): array
{
    $w = preg_split('/\s+/', trim($text));
    if (count($w) <= $n) return [trim($text), ''];
    return [implode(' ', array_slice($w, 0, count($w) - $n)), implode(' ', array_slice($w, -$n))];
}

/**
 * Finds the largest font size (≤ $start) at which $text wraps into ≤ $maxLines lines that all fit
 * $maxW and whose block height fits $maxH. Returns [size, lines, lineHeight].
 */
function px_fit(string $font, string $text, int $maxW, int $maxH, int $start, int $maxLines, float $lineMul = 1.15, int $min = 16): array
{
    $size = max($min, $start);
    while (true) {
        $lines = pin_image_wrap_text($font, $size, $text, $maxW, 50);
        $lh = pin_line_height($font, $size, $lineMul, $lines);
        $widest = 0;
        foreach ($lines as $l) $widest = max($widest, px_text_w($size, $font, $l));
        if ((count($lines) <= $maxLines && count($lines) * $lh <= $maxH && $widest <= $maxW) || $size <= $min) break;
        $size = (int)floor($size * 0.93);
    }
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]) . '…';
    }
    return [$size, $lines, pin_line_height($font, $size, $lineMul, $lines)];
}

/** Draws text centered on $cx with its baseline at $y, optionally with a solid outline. */
function px_center_text($im, int $size, string $font, string $text, int $cx, int $y, int $color, ?int $outline = null, int $outlineW = 0): void
{
    $x = (int)($cx - px_text_w($size, $font, $text) / 2);
    if ($outline !== null && $outlineW > 0) {
        $rings = [$outlineW, (int)ceil($outlineW * 0.66), (int)ceil($outlineW * 0.33)];
        foreach (array_unique($rings) as $r) {
            $steps = max(16, $r * 3);
            for ($i = 0; $i < $steps; $i++) {
                $a = 2 * M_PI * $i / $steps;
                imagettftext($im, $size, 0, $x + (int)round(cos($a) * $r), $y + (int)round(sin($a) * $r), $outline, $font, $text);
            }
        }
    }
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/** Letter-spaced single line centered on $cx. */
function px_spaced_text($im, int $size, string $font, string $text, int $cx, int $y, int $color, int $spacing): void
{
    $chars = function_exists('mb_str_split') ? mb_str_split($text, 1, 'UTF-8') : str_split($text);
    $total = 0;
    foreach ($chars as $c) $total += ($c === ' ' ? (int)($size * 0.4) : px_text_w($size, $font, $c)) + $spacing;
    $total -= $spacing;
    $x = (int)($cx - $total / 2);
    foreach ($chars as $c) {
        if ($c !== ' ') imagettftext($im, $size, 0, $x, $y, $color, $font, $c);
        $x += ($c === ' ' ? (int)($size * 0.4) : px_text_w($size, $font, $c)) + $spacing;
    }
}

/** Width of a letter-spaced line (matches px_spaced_text). */
function px_spaced_w(int $size, string $font, string $text, int $spacing): int
{
    $chars = function_exists('mb_str_split') ? mb_str_split($text, 1, 'UTF-8') : str_split($text);
    $total = 0;
    foreach ($chars as $c) $total += ($c === ' ' ? (int)($size * 0.4) : px_text_w($size, $font, $c)) + $spacing;
    return max(0, $total - $spacing);
}

/** Filled rounded rectangle. */
function px_rrect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    $r = max(0, min($r, (int)(($x2 - $x1) / 2), (int)(($y2 - $y1) / 2)));
    if ($r === 0) { imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color); return; }
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
    imagefilledrectangle($im, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
    $d = $r * 2;
    imagefilledarc($im, $x1 + $r, $y1 + $r, $d, $d, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $r, $y1 + $r, $d, $d, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x1 + $r, $y2 - $r, $d, $d, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $r, $y2 - $r, $d, $d, 0, 90, $color, IMG_ARC_PIE);
}

/** Rounded-rectangle outline of thickness $t. */
function px_rrect_outline($im, int $x1, int $y1, int $x2, int $y2, int $r, int $t, int $color, int $inside): void
{
    px_rrect($im, $x1, $y1, $x2, $y2, $r, $color);
    px_rrect($im, $x1 + $t, $y1 + $t, $x2 - $t, $y2 - $t, max(0, $r - $t), $inside);
}

/** Small 4-point sparkle/diamond. */
function px_diamond($im, int $cx, int $cy, int $r, int $color): void
{
    $k = max(2, (int)($r * 0.28));
    imagefilledpolygon($im, [$cx, $cy - $r, $cx + $k, $cy - $k, $cx + $r, $cy, $cx + $k, $cy + $k, $cx, $cy + $r, $cx - $k, $cy + $k, $cx - $r, $cy, $cx - $k, $cy - $k], $color);
}

/** A rectangle with rough "brush stroke" edges. */
function px_brush_box($im, int $x1, int $y1, int $x2, int $y2, int $color, int $seed = 7): void
{
    mt_srand($seed);
    $j = max(4, (int)(($y2 - $y1) * 0.035));
    $step = 18;
    $pts = [];
    for ($x = $x1; $x <= $x2; $x += $step) { $pts[] = $x; $pts[] = $y1 + mt_rand(-$j, $j); }
    for ($y = $y1; $y <= $y2; $y += $step) { $pts[] = $x2 + mt_rand(-$j, $j) * 2; $pts[] = $y; }
    for ($x = $x2; $x >= $x1; $x -= $step) { $pts[] = $x; $pts[] = $y2 + mt_rand(-$j, $j); }
    for ($y = $y2; $y >= $y1; $y -= $step) { $pts[] = $x1 + mt_rand(-$j, $j) * 2; $pts[] = $y; }
    imagefilledrectangle($im, $x1 + $j * 2, $y1 + $j, $x2 - $j * 2, $y2 - $j, $color);
    imagefilledpolygon($im, $pts, $color);
    mt_srand();
}

/** Vertical dark gradient band (alpha from $edgeAlpha at the edges to $midAlpha in the middle). */
function px_gradient_band($im, int $y1, int $y2, int $W, int $midAlpha = 20, int $edgeAlpha = 127): void
{
    imagealphablending($im, true);
    $h = max(1, $y2 - $y1);
    for ($y = $y1; $y < $y2; $y++) {
        $t = abs(($y - $y1) / $h - 0.5) * 2;           // 0 in the middle, 1 at the edges
        $a = (int)round($midAlpha + ($edgeAlpha - $midAlpha) * pow($t, 1.6));
        imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, 0, 0, 0, $a));
    }
}

/** Decodes the raw image list into GD images. */
function px_sources(array $imageBytesList): array
{
    $out = [];
    foreach ($imageBytesList as $bytes) {
        $img = @imagecreatefromstring($bytes);
        if ($img) $out[] = $img;
    }
    return $out;
}

/** Cover-crops $src into a new $w×$h image; $zoom > 1 crops tighter, focus ($fx,$fy) in 0..1 picks the region. */
function px_cover_cell($src, int $w, int $h, float $zoom = 1.0, float $fx = 0.5, float $fy = 0.5, bool $flip = false)
{
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh) * max(1.0, $zoom);
    $cw = (int)round($w / $scale); $ch = (int)round($h / $scale);
    $cx = (int)round(($sw - $cw) * $fx); $cy = (int)round(($sh - $ch) * $fy);
    $cell = imagecreatetruecolor($w, $h);
    imagecopyresampled($cell, $src, 0, 0, max(0, $cx), max(0, $cy), $w, $h, $cw, $ch);
    if ($flip && function_exists('imageflip')) imageflip($cell, IMG_FLIP_HORIZONTAL);
    return $cell;
}

/**
 * A $cols × $rows photo grid with $gap-px gutters. When there are fewer photos than cells,
 * repeats are re-cropped (tighter zoom, different focus, mirrored) so the grid still looks varied.
 */
function px_grid(array $imageBytesList, int $W, int $H, int $cols, int $rows, int $gap, array $gapRgb)
{
    $sources = px_sources($imageBytesList);
    if (empty($sources)) return null;
    $canvas = imagecreatetruecolor($W, $H);
    imagefilledrectangle($canvas, 0, 0, $W, $H, px_col($canvas, $gapRgb));
    $n = count($sources);
    $cellW = (int)floor(($W - $gap * ($cols - 1)) / $cols);
    $cellH = (int)floor(($H - $gap * ($rows - 1)) / $rows);
    $focus = [[0.5, 0.5], [0.15, 0.3], [0.85, 0.7], [0.3, 0.85], [0.7, 0.15], [0.5, 0.2], [0.2, 0.7], [0.8, 0.4]];
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $i = $r * $cols + $c;
            $variant = intdiv($i, $n);
            [$fx, $fy] = $focus[($i + $variant) % count($focus)];
            $zoom = $variant === 0 ? 1.0 : min(1.9, 1.25 + 0.2 * $variant);
            $cell = px_cover_cell($sources[$i % $n], $cellW, $cellH, $zoom, $variant === 0 ? 0.5 : $fx, $variant === 0 ? 0.5 : $fy, $variant % 2 === 1);
            $x = $c * ($cellW + $gap);
            $y = $r * ($cellH + $gap);
            // last column/row absorbs rounding so the grid always fills the canvas edge-to-edge
            $dw = $c === $cols - 1 ? $W - $x : $cellW;
            $dh = $r === $rows - 1 ? $H - $y : $cellH;
            imagecopyresampled($canvas, $cell, $x, $y, 0, 0, $dw, $dh, $cellW, $cellH);
            imagedestroy($cell);
        }
    }
    foreach ($sources as $s) imagedestroy($s);
    return $canvas;
}

/** Top/bottom photo split: first half of the images on top, the rest (or all) on the bottom. */
function px_split_photos($canvas, array $imageBytesList, int $W, int $topH, int $bottomY, int $H): bool
{
    $half = max(1, intdiv(count($imageBytesList) + 1, 2));
    $topImgs = array_slice($imageBytesList, 0, $half);
    $botImgs = array_slice($imageBytesList, $half);
    $top = pin_build_photo_canvas($topImgs, $W, max(1, $topH));
    if (!$top) return false;
    imagecopy($canvas, $top, 0, 0, 0, 0, $W, $topH);
    imagedestroy($top);
    $botH = $H - $bottomY;
    if ($botH > 0) {
        if (empty($botImgs)) {
            // Single image: show a different (lower, mirrored) crop of it on the bottom.
            $src = @imagecreatefromstring($imageBytesList[0]);
            if ($src) {
                $cell = px_cover_cell($src, $W, $botH, 1.3, 0.5, 0.85, true);
                imagecopy($canvas, $cell, 0, $bottomY, 0, 0, $W, $botH);
                imagedestroy($cell); imagedestroy($src);
            }
        } else {
            $bot = pin_build_photo_canvas($botImgs, $W, $botH);
            if ($bot) { imagecopy($canvas, $bot, 0, $bottomY, 0, 0, $W, $botH); imagedestroy($bot); }
        }
    }
    return true;
}

/** Stacked rounded blocks behind centered lines. $items = [[text, font, size, colorInt], ...]. Returns bottom Y. */
function px_block_stack($im, array $items, int $cx, int $top, int $blockColor, int $padX, int $padY, int $radius, int $overlap = 0): int
{
    $y = $top;
    foreach ($items as $it) {
        [$text, $font, $size, $color] = $it;
        [$asc, $desc] = px_metrics($size, $font);
        $tw = px_text_w($size, $font, $text);
        $bh = $asc + $desc + $padY * 2;
        px_rrect($im, (int)($cx - $tw / 2 - $padX), $y, (int)($cx + $tw / 2 + $padX), $y + $bh, $radius, $blockColor);
        imagettftext($im, $size, 0, (int)($cx - $tw / 2), $y + $padY + $asc, $color, $font, $text);
        $y += $bh - $overlap;
    }
    return $y + $overlap;
}

function px_out($canvas): ?string
{
    ob_start();
    imagejpeg($canvas, null, 94);
    $out = ob_get_clean();
    imagedestroy($canvas);
    return $out ?: null;
}

function px_pal(?array $palette, int $i, array $fallback): array
{
    return ($palette && !empty($palette['colors'][$i])) ? $palette['colors'][$i] : $fallback;
}

/* ============================== HOME DECOR ============================== */

/** home_decor3 — Hand-Lettered Grid (8-photo grid, huge black lowercase title, cream outline, spaced website strip). */
function pin_render_home_decor3(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    // one tall cell per photo: 4 photos -> 2×2 (people stay whole), 6+ photos on tall pins -> 2×4
    $rows = (count($imageBytesList) >= 6 && $H / $W >= 1.4) ? 4 : 2;
    $canvas = px_grid($imageBytesList, $W, $H, 2, $rows, (int)($W * 0.012), [250, 250, 248]);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $font = px_font('Poppins-Bold.ttf');
    $ink = px_col($canvas, px_pal($palette, 0, [12, 12, 12]));
    $cream = px_col($canvas, px_pal($palette, 1, [252, 250, 242]));
    $footH = $website !== '' ? (int)($H * 0.045) : 0;
    $areaTop = (int)($H * 0.05);
    $areaH = $H - $footH - (int)($H * 0.1);
    [$size, $lines, $lh] = px_fit($font, px_lower($title), $W - (int)($W * 0.12), $areaH, (int)($W / 5.6), 7, 1.12);
    [$asc] = px_metrics($size, $font);
    $y = $areaTop + (int)(($areaH - count($lines) * $lh) / 2) + $asc;
    $ow = max(6, (int)($size * 0.16));
    foreach ($lines as $line) {
        px_center_text($canvas, $size, $font, $line, (int)($W / 2), $y, $ink, $cream, $ow);
        $y += $lh;
    }

    if ($website !== '') {
        imagefilledrectangle($canvas, 0, $H - $footH, $W, $H, px_col($canvas, [20, 20, 20], 50));
        $fs = max(12, (int)($footH * 0.42));
        $fb = px_font('Poppins-Bold.ttf');
        $sp = (int)($fs * 0.55);
        while (px_spaced_w($fs, $fb, px_upper($website), $sp) > $W - 40 && $fs > 10) { $fs--; $sp = (int)($fs * 0.55); }
        px_spaced_text($canvas, $fs, $fb, px_upper($website), (int)($W / 2), $H - (int)(($footH - $fs) / 2) - 2, px_col($canvas, [255, 255, 255]), $sp);
    }
    return px_out($canvas);
}

/** home_decor4 — Dark Badge Grid (4-photo grid, dark rounded blob badge, peach text). */
function pin_render_home_decor4(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = px_grid($imageBytesList, $W, $H, 2, 2, (int)($W * 0.014), [255, 255, 255]);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $badge = px_col($canvas, px_pal($palette, 0, [40, 36, 34]));
    $peach = px_col($canvas, px_pal($palette, 1, [245, 214, 196]));
    $fBlack = px_font('Poppins-Black.ttf');
    $fBold = px_font('Poppins-ExtraBold.ttf');
    [$num, $rest] = px_split_number($title);
    [$main, $tail] = px_split_tail(px_upper($rest), 2);
    if ($main === '') { $main = $tail; $tail = ''; }

    $maxW = (int)($W * 0.6);
    $items = [];
    if ($num !== null) $items[] = [$num, $fBlack, (int)($W / 7.5), $peach];
    [$ms, $mLines] = px_fit($fBold, $main, $maxW, (int)($H * 0.4), (int)($W / 9), 4, 1.1);
    foreach ($mLines as $l) $items[] = [$l, $fBold, $ms, $peach];
    if ($tail !== '') {
        [$ts, $tLines] = px_fit($fBold, $tail, $maxW, (int)($H * 0.15), (int)($ms * 0.62), 2, 1.1);
        foreach ($tLines as $l) $items[] = [$l, $fBold, $ts, $peach];
    }

    // Measure the stack height first, then centre it.
    $pad = (int)($W * 0.028);
    $total = 0;
    foreach ($items as $it) { [$a, $d] = px_metrics($it[2], $it[1]); $total += $a + $d + $pad * 2 - (int)($pad * 0.9); }
    $top = (int)(($H - $total) / 2);
    px_block_stack($canvas, $items, (int)($W / 2), $top, $badge, (int)($W * 0.045), $pad, (int)($W * 0.04), (int)($pad * 0.9));

    if ($website !== '') {
        $fs = max(12, (int)($W / 48));
        $f = px_font('Poppins-Bold.ttf');
        $tw = px_text_w($fs, $f, $website);
        $y2 = $H - (int)($H * 0.025);
        px_rrect($canvas, (int)($W / 2 - $tw / 2 - 18), $y2 - $fs - 18, (int)($W / 2 + $tw / 2 + 18), $y2, 20, $badge);
        px_center_text($canvas, $fs, $f, $website, (int)($W / 2), $y2 - 10, $peach);
    }
    return px_out($canvas);
}

/** home_decor5 — Script Overlay (full photo, white uppercase lines on translucent boxes + script tail line). */
function pin_render_home_decor5(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas(array_slice($imageBytesList, 0, 1), $W, $H);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $white = px_col($canvas, [255, 255, 255]);
    $box = px_col($canvas, px_pal($palette, 0, [30, 38, 46]), 55);
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fScript = px_font('Lobster-Regular.ttf');
    [$num, $rest] = px_split_number($title);
    [$main, $script] = px_split_tail($rest, 2);
    if ($main === '') { $main = $script; $script = ''; }

    $items = [];
    if ($num !== null) $items[] = [$num, (int)($W / 7)];
    [$ms, $mLines] = px_fit($fBold, px_upper($main), (int)($W * 0.8), (int)($H * 0.35), (int)($W / 7), 3, 1.1);
    foreach ($mLines as $l) $items[] = [$l, $ms];

    $padX = (int)($W * 0.025); $padY = (int)($W * 0.012);
    $heights = 0;
    foreach ($items as [$t, $s]) { [$a, $d] = px_metrics($s, $fBold); $heights += $a + $d + $padY * 2 + 6; }
    $scriptSize = (int)($W / 8.5);
    if ($script !== '') {
        [$scriptSize, $sLines] = px_fit($fScript, $script, (int)($W * 0.9), (int)($H * 0.15), $scriptSize, 1, 1.1);
        $script = $sLines[0];
        $heights += (int)($scriptSize * 1.2);
    }
    $y = (int)(($H - $heights) / 2);
    foreach ($items as [$t, $s]) {
        [$a, $d] = px_metrics($s, $fBold);
        $tw = px_text_w($s, $fBold, $t);
        imagefilledrectangle($canvas, (int)($W / 2 - $tw / 2 - $padX), $y, (int)($W / 2 + $tw / 2 + $padX), $y + $a + $d + $padY * 2, $box);
        px_center_text($canvas, $s, $fBold, $t, (int)($W / 2), $y + $padY + $a, $white);
        $y += $a + $d + $padY * 2 + 6;
    }
    if ($script !== '') {
        [$a] = px_metrics($scriptSize, $fScript);
        px_center_text($canvas, $scriptSize, $fScript, $script, (int)($W / 2), $y + $a - (int)($scriptSize * 0.15), $white, px_col($canvas, [20, 28, 36]), max(3, (int)($scriptSize * 0.06)));
    }
    if ($website !== '') {
        px_center_text($canvas, max(12, (int)($W / 45)), px_font('Poppins-Bold.ttf'), $website, (int)($W / 2), $H - (int)($H * 0.03), $white, px_col($canvas, [0, 0, 0], 60), 2);
    }
    return px_out($canvas);
}

/** home_decor6 — Badge + Button Grid (4-photo grid, white scalloped badge, subtitle, pink button, pink website bar). */
function pin_render_home_decor6(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $barH = $website !== '' ? (int)($H * 0.045) : 0;
    $grid = px_grid($imageBytesList, $W, $H - $barH, 2, 2, (int)($W * 0.008), [255, 255, 255]);
    if (!$grid) return null;
    $canvas = imagecreatetruecolor($W, $H);
    imagecopy($canvas, $grid, 0, 0, 0, 0, $W, $H - $barH);
    imagedestroy($grid);
    imagealphablending($canvas, true);

    $pinkRgb = ($palette && !empty($palette['cta_bg'])) ? $palette['cta_bg'] : px_pal($palette, 0, [236, 64, 90]);
    $pink = px_col($canvas, $pinkRgb);
    $white = px_col($canvas, [255, 255, 255]);
    $ink = px_col($canvas, [18, 18, 18]);
    $fBlack = px_font('Poppins-Black.ttf');
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fItal = px_font('DMSerifDisplay-Italic.ttf');
    [$num, $rest] = px_split_number($title);
    $cta = trim($ctaText) !== '' ? $ctaText : 'Click for more';

    $bw = (int)($W * 0.8);
    $innerW = $bw - (int)($W * 0.12);
    $numSize = (int)($W / 7);
    [$ts, $tLines, $tlh] = px_fit($fBold, px_upper($rest), $innerW, (int)($H * 0.3), (int)($W / 10), 4, 1.12);
    $ctaSize = max(14, (int)($W / 30));
    $bhBtn = (int)($ctaSize * 2.3);
    $padV = (int)($H * 0.035);
    $bh = $padV + ($num !== null ? (int)($numSize * 1.25) : 0) + count($tLines) * $tlh + (int)($H * 0.035) + $bhBtn + $padV;
    $bx1 = (int)(($W - $bw) / 2); $bx2 = $bx1 + $bw;
    $by1 = (int)(($H - $barH - $bh) / 2); $by2 = $by1 + $bh;

    // Scalloped white badge: rounded body + top/bottom bumps, thin pink inner line.
    $bump = (int)($bw * 0.34);
    imagefilledellipse($canvas, (int)($W / 2), $by1 + 6, $bump, (int)($bump * 0.45), $white);
    imagefilledellipse($canvas, (int)($W / 2), $by2 - 6, $bump, (int)($bump * 0.45), $white);
    px_rrect($canvas, $bx1, $by1, $bx2, $by2, (int)($W * 0.09), $white);
    px_rrect_outline($canvas, $bx1 + 10, $by1 + 10, $bx2 - 10, $by2 - 10, (int)($W * 0.08), 2, $pink, $white);
    px_diamond($canvas, $bx1 + 10, (int)(($by1 + $by2) / 2), (int)($W * 0.022), $pink);
    px_diamond($canvas, $bx2 - 10, (int)(($by1 + $by2) / 2), (int)($W * 0.022), $pink);

    $y = $by1 + $padV;
    if ($num !== null) {
        [$a] = px_metrics($numSize, $fBlack);
        px_center_text($canvas, $numSize, $fBlack, $num, (int)($W / 2), $y + $a, $ink);
        // little burst strokes either side of the number
        $nw = px_text_w($numSize, $fBlack, $num);
        foreach ([-1, 1] as $side) {
            $sx = (int)($W / 2 + $side * ($nw / 2 + $W * 0.03));
            foreach ([-0.35, 0, 0.35] as $k) {
                imagesetthickness($canvas, 4);
                imageline($canvas, $sx, (int)($y + $a * (0.5 + $k)), (int)($sx + $side * $W * 0.035), (int)($y + $a * (0.5 + $k * 1.6)), $ink);
            }
        }
        imagesetthickness($canvas, 1);
        $y += (int)($numSize * 1.25);
    }
    [$a] = px_metrics($ts, $fBold);
    foreach ($tLines as $l) { px_center_text($canvas, $ts, $fBold, $l, (int)($W / 2), $y + $a, $ink); $y += $tlh; }

    // pink swoosh underline, just below the last title line
    $uy = $y + (int)($H * 0.012);
    imagesetthickness($canvas, 4);
    imageline($canvas, (int)($W * 0.33), $uy + 3, (int)($W * 0.67), $uy - 3, $pink);
    imagesetthickness($canvas, 1);
    $y += (int)($H * 0.035);

    // CTA button
    $bwBtn = px_text_w($ctaSize, $fBold, $cta) + (int)($W * 0.1);
    px_rrect($canvas, (int)($W / 2 - $bwBtn / 2) - 3, $y - 3, (int)($W / 2 + $bwBtn / 2) + 3, $y + $bhBtn + 3, (int)($bhBtn / 2) + 3, $ink);
    px_rrect($canvas, (int)($W / 2 - $bwBtn / 2), $y, (int)($W / 2 + $bwBtn / 2), $y + $bhBtn, (int)($bhBtn / 2), $pink);
    [$ca, $cd] = px_metrics($ctaSize, $fBold);
    px_center_text($canvas, $ctaSize, $fBold, $cta, (int)($W / 2), $y + (int)(($bhBtn + $ca - $cd) / 2), $white);

    if ($website !== '') {
        $barRgb = ($palette && !empty($palette['website_bg'])) ? $palette['website_bg'] : $pinkRgb;
        $barTxt = ($palette && !empty($palette['website_text'])) ? px_col($canvas, $palette['website_text']) : $white;
        imagefilledrectangle($canvas, 0, $H - $barH, $W, $H, px_col($canvas, $barRgb));
        $fs = max(12, (int)($barH * 0.4));
        $sp = max(1, (int)($fs * 0.2));
        $tw = px_spaced_w($fs, $fBold, $website, $sp);
        px_spaced_text($canvas, $fs, $fBold, $website, (int)($W / 2), $H - (int)(($barH - $fs) / 2) - 2, $barTxt, $sp);
        px_diamond($canvas, (int)($W / 2 - $tw / 2 - $fs * 1.5), $H - (int)($barH / 2), (int)($fs * 0.55), $barTxt);
        px_diamond($canvas, (int)($W / 2 + $tw / 2 + $fs * 1.5), $H - (int)($barH / 2), (int)($fs * 0.55), $barTxt);
    }
    return px_out($canvas);
}

/* ============================== RECIPE FOOD ============================== */

/** recipe_food3 — Outlined Number Band (photo / white band: outlined number + stacked words + big lowercase line / photo). */
function pin_render_recipe_food3(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $rust = px_col($canvas, px_pal($palette, 0, [176, 72, 30]));
    $slate = px_col($canvas, px_pal($palette, 1, [44, 58, 70]));
    $slateLight = px_col($canvas, px_pal($palette, 2, [96, 112, 126]));
    $white = px_col($canvas, [255, 255, 255]);
    $fBlack = px_font('Poppins-Black.ttf');
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fSemi = px_font('Poppins-Bold.ttf');

    [$num, $rest] = px_split_number($title);
    $words = preg_split('/\s+/', $rest);
    $k = count($words) >= 4 ? (int)ceil(count($words) / 2) : max(1, count($words) - 1);
    $stack = implode(' ', array_slice($words, 0, $k));
    $bottom = implode(' ', array_slice($words, $k));
    if ($num === null && $bottom === '') { $bottom = $stack; $stack = ''; }

    $bandH = (int)($H * 0.2);
    $bandY = (int)($H * 0.36);
    if (!px_split_photos($canvas, $imageBytesList, $W, $bandY, $bandY + $bandH, $H)) { imagedestroy($canvas); return null; }
    imagealphablending($canvas, true);
    imagefilledrectangle($canvas, 0, $bandY, $W, $bandY + $bandH, $white);

    $rowTop = $bandY + (int)($bandH * 0.08);
    $row1H = $bottom !== '' ? (int)($bandH * 0.5) : (int)($bandH * 0.84);
    $x = (int)($W * 0.06);
    if ($num !== null || $stack !== '') {
        $numW = 0;
        $numSize = (int)($row1H * 0.78);
        if ($num !== null) {
            $numW = px_text_w($numSize, $fBlack, $num);
        }
        $stackW = $W - (int)($W * 0.12) - ($numW ? $numW + (int)($W * 0.04) : 0);
        // 2 stacked lines to the right of the number
        $sw = preg_split('/\s+/', px_upper($stack));
        $sl = count($sw) > 1 ? [implode(' ', array_slice($sw, 0, (int)ceil(count($sw) / 2))), implode(' ', array_slice($sw, (int)ceil(count($sw) / 2)))] : [px_upper($stack)];
        $ss = (int)($row1H * 0.4);
        while ($ss > 12 && max(array_map(fn($l) => px_text_w($ss, $fBold, $l), $sl)) > $stackW) $ss--;
        $blockW = ($numW ? $numW + (int)($W * 0.04) : 0) + max(array_map(fn($l) => px_text_w($ss, $fBold, $l), $sl));
        $x = (int)(($W - $blockW) / 2);
        if ($num !== null) {
            [$na] = px_metrics($numSize, $fBlack);
            $by = $rowTop + (int)(($row1H + $na * 0.72) / 2);
            $tx = $x;
            foreach ([6, 4, 2] as $r) for ($i = 0; $i < 24; $i++) { $a = 2 * M_PI * $i / 24; imagettftext($canvas, $numSize, 0, $tx + (int)round(cos($a) * $r), $by + (int)round(sin($a) * $r), $r === 6 ? $slate : $white, $fBlack, $num); }
            imagettftext($canvas, $numSize, 0, $tx, $by, $slateLight, $fBlack, $num);
            $x += $numW + (int)($W * 0.04);
        }
        [$sa] = px_metrics($ss, $fBold);
        $lh = (int)($ss * 1.12);
        $sy = $rowTop + (int)(($row1H - count($sl) * $lh) / 2) + $sa;
        foreach ($sl as $i => $l) {
            if ($num === null) { px_center_text($canvas, $ss, $fBold, $l, (int)($W / 2), $sy, $i % 2 ? $slate : $rust); }
            else imagettftext($canvas, $ss, 0, $x, $sy, $i % 2 ? $slate : $rust, $fBold, $l);
            $sy += $lh;
        }
    }
    if ($bottom !== '') {
        $avail = $bandY + $bandH - ($rowTop + $row1H) - (int)($bandH * 0.06);
        [$bs, $bl] = px_fit($fSemi, px_lower($bottom), $W - (int)($W * 0.1), $avail, (int)($avail * 0.95), 1, 1.0);
        [$ba] = px_metrics($bs, $fSemi);
        px_center_text($canvas, $bs, $fSemi, $bl[0], (int)($W / 2), $rowTop + $row1H + (int)(($avail + $ba * 0.75) / 2), $rust);
    }
    if ($website !== '') {
        px_center_text($canvas, max(12, (int)($W / 48)), $fSemi, $website, (int)($W / 2), $H - (int)($H * 0.02), $white, px_col($canvas, [0, 0, 0], 50), 2);
    }
    return px_out($canvas);
}

/** recipe_food4 — Gold Number Split (two photos, dark gradient middle: gold number, white title, gold divider line, white italic line). */
function pin_render_recipe_food4(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $mid = (int)($H * 0.52);
    if (!px_split_photos($canvas, $imageBytesList, $W, $mid, $mid, $H)) { imagedestroy($canvas); return null; }
    imagealphablending($canvas, true);

    $gold = px_col($canvas, px_pal($palette, 0, [248, 196, 58]));
    $white = px_col($canvas, [255, 255, 255]);
    $shadow = px_col($canvas, [0, 0, 0], 0);
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fSemi = px_font('Poppins-Bold.ttf');
    $fItal = px_font('DMSerifDisplay-Italic.ttf');

    [$num, $rest] = px_split_number($title);
    $w = preg_split('/\s+/', $rest);
    $mainN = count($w) <= 3 ? count($w) : (count($w) === 5 ? 3 : 2);
    $main = implode(' ', array_slice($w, 0, $mainN));
    $tailW = array_slice($w, $mainN);
    $gline = ''; $iline = '';
    if ($tailW) {
        $cut = (int)floor(count($tailW) / 2);
        $gline = implode(' ', array_slice($tailW, 0, $cut));
        $iline = implode(' ', array_slice($tailW, $cut));
    }
    if ($iline === '' && trim($ctaText) !== '') $iline = $ctaText;

    $y = (int)($H * 0.36);
    if ($num !== null) {
        $ns = (int)($W / 7);
        [$a] = px_metrics($ns, $fBold);
        px_center_text($canvas, $ns, $fBold, $num, (int)($W / 2), $y + $a, $gold, $shadow, 3);
        $y += (int)($ns * 1.12);
    }
    [$ms, $mLines, $mlh] = px_fit($fSemi, $main, (int)($W * 0.92), (int)($H * 0.1), (int)($W / 9.5), 1, 1.05, (int)($W / 14));
    if (count($mLines) === 1 && substr($mLines[0], -3) === '…') {
        [$ms, $mLines, $mlh] = px_fit($fSemi, $main, (int)($W * 0.92), (int)($H * 0.2), (int)($W / 9.5), 2, 1.05);
    }
    [$a] = px_metrics($ms, $fSemi);
    foreach ($mLines as $l) { px_center_text($canvas, $ms, $fSemi, $l, (int)($W / 2), $y + $a, $white, $shadow, 3); $y += $mlh; }
    $y += (int)($H * 0.012);
    if ($gline !== '') {
        [$gs, $gl] = px_fit($fBold, px_upper($gline), (int)($W * 0.7), (int)($H * 0.05), (int)($W / 28), 1, 1.0);
        [$ga] = px_metrics($gs, $fBold);
        $gw = px_text_w($gs, $fBold, $gl[0]);
        px_center_text($canvas, $gs, $fBold, $gl[0], (int)($W / 2), $y + $ga, $gold);
        imagesetthickness($canvas, 2);
        $ly = $y + (int)($ga * 0.55);
        imageline($canvas, (int)($W * 0.05), $ly, (int)($W / 2 - $gw / 2 - 18), $ly, $gold);
        imageline($canvas, (int)($W / 2 + $gw / 2 + 18), $ly, (int)($W * 0.95), $ly, $gold);
        imagesetthickness($canvas, 1);
        $y += (int)($gs * 1.6);
    }
    if ($iline !== '') {
        [$is, $il] = px_fit($fItal, $iline, (int)($W * 0.92), (int)($H * 0.08), (int)($W / 14), 1, 1.0);
        [$ia] = px_metrics($is, $fItal);
        px_center_text($canvas, $is, $fItal, $il[0], (int)($W / 2), $y + $ia, $white, $shadow, 3);
    }
    if ($website !== '') {
        px_center_text($canvas, max(12, (int)($W / 45)), $fSemi, $website, (int)($W / 2), $H - (int)($H * 0.02), $white, $shadow, 2);
    }
    return px_out($canvas);
}

/** recipe_food5 — Green Block Title (full photo, dark-green rounded text blocks + subtitle pill). */
function pin_render_recipe_food5(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas($imageBytesList, $W, $H);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $green = px_col($canvas, px_pal($palette, 0, [36, 72, 50]));
    $border = px_col($canvas, [255, 255, 255], 70);
    $white = px_col($canvas, [255, 255, 255]);
    $fBlack = px_font('Poppins-Black.ttf');
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fSemi = px_font('Poppins-Bold.ttf');
    [$num, $rest] = px_split_number($title);

    $items = [];
    if ($num !== null) $items[] = [$num, $fBlack, (int)($W / 5.2), $white];
    [$ts, $tLines] = px_fit($fBold, px_upper($rest), (int)($W * 0.82), (int)($H * 0.3), (int)($W / 11), 3, 1.1);
    foreach ($tLines as $l) $items[] = [$l, $fBold, $ts, $white];

    $pad = (int)($W * 0.02);
    // white hairline border: draw the same stack slightly larger in translucent white first
    $top = (int)($H * 0.05);
    px_block_stack($canvas, array_map(fn($it) => [$it[0], $it[1], $it[2], $border], $items), (int)($W / 2), $top - 4, $border, (int)($W * 0.04) + 4, $pad + 4, (int)($W * 0.035), (int)($pad * 1.2) + 8);
    $y = px_block_stack($canvas, $items, (int)($W / 2), $top, $green, (int)($W * 0.04), $pad, (int)($W * 0.03), (int)($pad * 1.2));

    if (trim($ctaText) !== '') {
        $cs = max(14, (int)($W / 27));
        $y += (int)($pad * 0.6);
        px_block_stack($canvas, [[$ctaText, $fSemi, $cs, $white]], (int)($W / 2), $y, $green, (int)($W * 0.03), (int)($pad * 0.7), (int)($W * 0.02));
    }
    if ($website !== '') {
        $fs = max(12, (int)($W / 45));
        $tw = px_text_w($fs, $fSemi, $website);
        px_rrect($canvas, (int)($W / 2 - $tw / 2 - 20), $H - (int)($H * 0.05) - $fs, (int)($W / 2 + $tw / 2 + 20), $H - (int)($H * 0.02), 18, $green);
        px_center_text($canvas, $fs, $fSemi, $website, (int)($W / 2), $H - (int)($H * 0.02) - (int)($fs * 0.55), $white);
    }
    return px_out($canvas);
}

/** recipe_food6 — Boxed Title Split (photo / white bordered title box + tan website tag / photo). */
function pin_render_recipe_food6(array $imageBytesList, string $title, string $website, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $mid = (int)($H * 0.5);
    if (!px_split_photos($canvas, $imageBytesList, $W, $mid, $mid, $H)) { imagedestroy($canvas); return null; }
    imagealphablending($canvas, true);

    $black = px_col($canvas, [10, 10, 10]);
    $white = px_col($canvas, [255, 255, 255]);
    $tanRgb = ($palette && !empty($palette['website_bg'])) ? $palette['website_bg'] : px_pal($palette, 0, [198, 168, 118]);
    $tan = px_col($canvas, $tanRgb);
    $tagText = ($palette && !empty($palette['website_text'])) ? px_col($canvas, $palette['website_text']) : $white;
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fItal = px_font('DMSerifDisplay-Italic.ttf');

    $bx1 = (int)($W * 0.045); $bx2 = $W - $bx1;
    [$ts, $tLines, $tlh] = px_fit($fBold, px_upper($title), $bx2 - $bx1 - (int)($W * 0.08), (int)($H * 0.24), (int)($W / 13), 4, 1.28);
    $boxH = count($tLines) * $tlh + (int)($W * 0.1);
    $by1 = (int)($H * 0.52 - $boxH / 2); $by2 = $by1 + $boxH;
    imagefilledrectangle($canvas, $bx1, $by1, $bx2, $by2, $black);
    imagefilledrectangle($canvas, $bx1 + 5, $by1 + 5, $bx2 - 5, $by2 - 5, $white);
    [$a] = px_metrics($ts, $fBold);
    $y = $by1 + (int)(($boxH - count($tLines) * $tlh) / 2) + $a + (int)(($tlh - $ts) / 3);
    foreach ($tLines as $l) { px_center_text($canvas, $ts, $fBold, $l, (int)($W / 2), $y, $black); $y += $tlh; }

    if ($website !== '') {
        $label = px_upper(preg_replace('~^www\.~i', '', $website));
        $fs = max(14, (int)($W / 34));
        $sp = (int)($fs * 0.18);
        $tw = px_spaced_w($fs, $fItal, $label, $sp);
        $tagW = min((int)($W * 0.88), $tw + (int)($W * 0.12));
        $tagH = (int)($fs * 2.2);
        $ty1 = $by2 - (int)($tagH * 0.35);
        imagefilledrectangle($canvas, (int)($W / 2 - $tagW / 2), $ty1, (int)($W / 2 + $tagW / 2), $ty1 + $tagH, $tan);
        px_spaced_text($canvas, $fs, $fItal, $label, (int)($W / 2), $ty1 + (int)(($tagH + $fs * 0.8) / 2), $tagText, $sp);
    }
    return px_out($canvas);
}

/** recipe_food7 — Brush Label (full photo, white brush-edged title label at the top, brush website tag at the bottom). */
function pin_render_recipe_food7(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = pin_build_photo_canvas($imageBytesList, $W, $H);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $paper = px_col($canvas, px_pal($palette, 1, [255, 255, 255]));
    $ink = px_col($canvas, px_pal($palette, 0, [14, 14, 14]));
    $kick = px_col($canvas, px_pal($palette, 2, [70, 52, 40]));
    $fSerif = px_font('AbrilFatface-Regular.ttf');
    $fItal = px_font('DMSerifDisplay-Italic.ttf');

    $kicker = trim($ctaText);
    [$ts, $tLines, $tlh] = px_fit($fSerif, px_upper($title), (int)($W * 0.72), (int)($H * 0.2), (int)($W / 11), 4, 1.08);
    $ks = (int)($ts * 0.62);
    $boxH = count($tLines) * $tlh + ($kicker !== '' ? (int)($ks * 1.35) : 0) + (int)($W * 0.07);
    $bx1 = (int)($W * 0.12); $bx2 = $W - $bx1;
    $by1 = (int)($H * 0.035); $by2 = $by1 + $boxH;
    px_brush_box($canvas, $bx1, $by1, $bx2, $by2, $paper, crc32($title) % 997);
    $y = $by1 + (int)($W * 0.035);
    if ($kicker !== '') {
        [$ks, $kl] = px_fit($fItal, $kicker, (int)($W * 0.7), (int)($ks * 1.3), $ks, 1, 1.0);
        [$ka] = px_metrics($ks, $fItal);
        px_center_text($canvas, $ks, $fItal, $kl[0], (int)($W / 2), $y + $ka, $kick);
        $y += (int)($ks * 1.35);
    }
    [$a] = px_metrics($ts, $fSerif);
    foreach ($tLines as $l) { px_center_text($canvas, $ts, $fSerif, $l, (int)($W / 2), $y + (int)($a * 0.92), $ink); $y += $tlh; }

    if ($website !== '') {
        $fs = max(14, (int)($W / 30));
        $tw = px_text_w($fs, $fItal, $website);
        $tagH = (int)($fs * 2.1);
        $ty2 = $H - (int)($H * 0.03);
        px_brush_box($canvas, (int)($W / 2 - $tw / 2 - $W * 0.04), $ty2 - $tagH, (int)($W / 2 + $tw / 2 + $W * 0.04), $ty2, $paper, 31);
        px_center_text($canvas, $fs, $fItal, $website, (int)($W / 2), $ty2 - (int)(($tagH - $fs * 0.9) / 2), $ink);
    }
    return px_out($canvas);
}

/** recipe_food8 — Bold Outline Grid (8-photo grid, huge white uppercase title with black outline, orange subtitle pill). */
function pin_render_recipe_food8(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    // one tall cell per photo: 4 photos -> 2×2 (people stay whole), 6+ photos on tall pins -> 2×4
    $rows = (count($imageBytesList) >= 6 && $H / $W >= 1.4) ? 4 : 2;
    $canvas = px_grid($imageBytesList, $W, $H, 2, $rows, (int)($W * 0.006), [245, 245, 245]);
    if (!$canvas) return null;
    imagealphablending($canvas, true);

    $white = px_col($canvas, [255, 255, 255]);
    $black = px_col($canvas, [8, 8, 8]);
    $orange = px_col($canvas, px_pal($palette, 0, [206, 92, 52]));
    $fBlack = px_font('Poppins-Black.ttf');
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $sub = trim($ctaText);

    [$ts, $tLines, $tlh] = px_fit($fBlack, px_upper($title), (int)($W * 0.92), (int)($H * 0.6), (int)($W / 6), 6, 1.05);
    $ss = max(14, (int)($W / 26));
    $pillH = $sub !== '' ? (int)($ss * 2.1) : 0;
    $blockH = count($tLines) * $tlh + ($pillH ? $pillH + (int)($H * 0.02) : 0);
    [$a] = px_metrics($ts, $fBlack);
    $y = (int)(($H - $blockH) / 2) + $a;
    $ow = max(6, (int)($ts * 0.1));
    foreach ($tLines as $l) { px_center_text($canvas, $ts, $fBlack, $l, (int)($W / 2), $y, $white, $black, $ow); $y += $tlh; }

    if ($sub !== '') {
        [$ss, $sl] = px_fit($fBold, px_upper($sub), (int)($W * 0.82), $pillH, $ss, 1, 1.0);
        $tw = px_text_w($ss, $fBold, $sl[0]);
        $py = $y - $a + (int)($H * 0.02);
        px_rrect($canvas, (int)($W / 2 - $tw / 2 - $W * 0.035), $py, (int)($W / 2 + $tw / 2 + $W * 0.035), $py + $pillH, (int)($W * 0.02), $orange);
        [$sa, $sd] = px_metrics($ss, $fBold);
        px_center_text($canvas, $ss, $fBold, $sl[0], (int)($W / 2), $py + (int)(($pillH + $sa - $sd) / 2), $white);
    }
    if ($website !== '') {
        px_center_text($canvas, max(12, (int)($W / 45)), $fBold, $website, (int)($W / 2), $H - (int)($H * 0.02), $white, $black, 3);
    }
    return px_out($canvas);
}

/** recipe_food9 — Black Band Label (photo / black band: small white label + white title / photo). */
function pin_render_recipe_food9(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $fBold = px_font('Poppins-ExtraBold.ttf');
    $fSemi = px_font('Poppins-Bold.ttf');
    $label = trim($ctaText);

    [$ts, $tLines, $tlh] = px_fit($fBold, px_upper($title), (int)($W * 0.88), (int)($H * 0.2), (int)($W / 10), 3, 1.1);
    $ls = max(14, (int)($W / 26));
    $labelH = $label !== '' ? (int)($ls * 1.9) : 0;
    $bandH = count($tLines) * $tlh + (int)($H * 0.05) + ($labelH ? (int)($labelH * 0.5) : 0) + ($website !== '' ? (int)($W / 18) : 0);
    $bandY = (int)($H * 0.41);
    $bandTop = $bandY + (int)($labelH * 0.5);
    if (!px_split_photos($canvas, $imageBytesList, $W, $bandTop, $bandTop + $bandH, $H)) { imagedestroy($canvas); return null; }
    imagealphablending($canvas, true);

    $bandRgb = px_pal($palette, 0, [0, 0, 0]);
    $band = px_col($canvas, $bandRgb);
    $white = px_col($canvas, [255, 255, 255]);
    imagefilledrectangle($canvas, 0, $bandTop, $W, $bandTop + $bandH, $band);
    // thin white rules framing the band
    imagefilledrectangle($canvas, 0, $bandTop + 6, $W, $bandTop + 8, $white);
    imagefilledrectangle($canvas, 0, $bandTop + $bandH - 8, $W, $bandTop + $bandH - 6, $white);

    $y = $bandTop + (int)($H * 0.02);
    if ($label !== '') {
        [$ls, $ll] = px_fit($fSemi, px_lower($label), (int)($W * 0.7), $labelH, $ls, 1, 1.0);
        $tw = px_text_w($ls, $fSemi, $ll[0]);
        $lx1 = (int)($W / 2 - $tw / 2 - $W * 0.05);
        imagefilledrectangle($canvas, $lx1 - 4, $bandY - 4, $W - $lx1 + 4, $bandY + $labelH + 4, $band);
        imagefilledrectangle($canvas, $lx1, $bandY, $W - $lx1, $bandY + $labelH, $white);
        [$la, $ld] = px_metrics($ls, $fSemi);
        px_center_text($canvas, $ls, $fSemi, $ll[0], (int)($W / 2), $bandY + (int)(($labelH + $la - $ld) / 2), $band);
        $y = $bandY + $labelH + (int)($H * 0.012);
    }
    [$a] = px_metrics($ts, $fBold);
    $lastBase = $y;
    foreach ($tLines as $l) { $lastBase = $y + $a; px_center_text($canvas, $ts, $fBold, $l, (int)($W / 2), $lastBase, $white); $y += $tlh; }
    if ($website !== '') {
        $wfs = max(12, (int)($W / 45));
        px_center_text($canvas, $wfs, $fSemi, $website, (int)($W / 2), $lastBase + (int)($wfs * 2), px_col($canvas, [200, 200, 200]));
    }
    return px_out($canvas);
}

/** recipe_food10 — Framed Card (photo / white card with tan inner frame: kicker, serif title, brand tag / photo). */
function pin_render_recipe_food10(array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey, ?array $palette = null): ?string
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $canvas = imagecreatetruecolor($W, $H);
    $mid = (int)($H * 0.5);
    if (!px_split_photos($canvas, $imageBytesList, $W, $mid, $mid, $H)) { imagedestroy($canvas); return null; }
    imagealphablending($canvas, true);

    $cardRgb = [255, 254, 251];
    $card = px_col($canvas, $cardRgb);
    $tan = px_col($canvas, px_pal($palette, 0, [196, 140, 88]));
    $ink = px_col($canvas, px_pal($palette, 1, [22, 22, 22]));
    $fSerif = px_font('AbrilFatface-Regular.ttf');
    $fReg = px_font('Poppins-Regular.ttf');
    $fSemi = px_font('Poppins-Bold.ttf');

    $kicker = px_upper(trim($ctaText));
    $cx1 = (int)($W * 0.045); $cx2 = $W - $cx1;
    [$ts, $tLines, $tlh] = px_fit($fSerif, $title, $cx2 - $cx1 - (int)($W * 0.14), (int)($H * 0.22), (int)($W / 10), 3, 1.12);
    $ks = max(12, (int)($W / 40));
    $cardH = count($tLines) * $tlh + (int)($W * 0.14) + ($kicker !== '' ? (int)($ks * 2.4) : 0);
    $cy1 = (int)($H * 0.49 - $cardH / 2); $cy2 = $cy1 + $cardH;
    imagefilledrectangle($canvas, $cx1, $cy1, $cx2, $cy2, $card);
    $in = (int)($W * 0.018);
    imagesetthickness($canvas, 3);
    imagerectangle($canvas, $cx1 + $in, $cy1 + $in, $cx2 - $in, $cy2 - $in, $tan);
    imagesetthickness($canvas, 1);

    $y = $cy1 + (int)($W * 0.06);
    if ($kicker !== '') {
        [$ka] = px_metrics($ks, $fReg);
        px_spaced_text($canvas, $ks, $fReg, $kicker, (int)($W / 2), $y + $ka, $ink, (int)($ks * 0.2));
        $y += (int)($ks * 2.4);
    }
    [$a] = px_metrics($ts, $fSerif);
    foreach ($tLines as $l) { px_center_text($canvas, $ts, $fSerif, $l, (int)($W / 2), $y + (int)($a * 0.9), $ink); $y += $tlh; }

    if ($website !== '') {
        $tag = px_upper(preg_replace('~^www\.~i', '', $website));
        $fs = max(11, (int)($W / 48));
        $sp = (int)($fs * 0.25);
        $tw = px_spaced_w($fs, $fSemi, $tag, $sp);
        $ly = $cy2 - $in;
        imagefilledrectangle($canvas, (int)($W / 2 - $tw / 2 - 16), $ly - (int)($fs * 0.9), (int)($W / 2 + $tw / 2 + 16), $ly + (int)($fs * 0.9), $card);
        px_spaced_text($canvas, $fs, $fSemi, $tag, (int)($W / 2), $ly + (int)($fs * 0.45), $ink, $sp);
    }
    return px_out($canvas);
}

/** Style keys → labels for the new styles (used by the free-tool template list). */
function pin_extra_style_labels(): array
{
    return [
        'home_decor3' => 'Hand-Lettered Grid',
        'home_decor4' => 'Dark Badge Grid',
        'home_decor5' => 'Script Overlay',
        'home_decor6' => 'Badge + Button Grid',
        'recipe_food3' => 'Outlined Number Band',
        'recipe_food4' => 'Gold Number Split',
        'recipe_food5' => 'Green Block Title',
        'recipe_food6' => 'Boxed Title Split',
        'recipe_food7' => 'Brush Label',
        'recipe_food8' => 'Bold Outline Grid',
        'recipe_food9' => 'Black Band Label',
        'recipe_food10' => 'Framed Card',
    ];
}
