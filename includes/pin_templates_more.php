<?php
/**
 * 28 more pin templates. Rules for all of them:
 *   - photos are never darkened, tinted or blurred (no scrim/overlay on the image) — text sits on
 *     solid panels, labels and bands, or uses a crisp outline when it has to sit on the photo;
 *   - photos are cover-cropped with the crop biased upward (keeps faces/heads in frame);
 *   - every template works with 1 image or many (collage cells re-crop/mirror repeats);
 *   - CTA text feeds the subtitle/pill where a design has one; website feeds the brand tag.
 *
 * Keys, names and categories are listed in pin_template_registry() (pin_template_registry.php).
 * Helpers px_* come from pin_styles_extra.php.
 */

/* ============================== shared helpers ============================== */

/** Picks source #$i; repeats of the same photo get a different crop / mirror so cells don't look duplicated. */
function pt_cell($srcs, int $i, int $w, int $h, float $fy = 0.32)
{
    $n = count($srcs);
    $variant = intdiv($i, max(1, $n));
    $focus = [[0.5, $fy], [0.2, 0.25], [0.8, 0.45], [0.35, 0.6], [0.65, 0.2], [0.5, 0.7], [0.15, 0.5], [0.85, 0.3]];
    [$fx, $fyv] = $variant === 0 ? [0.5, $fy] : $focus[($i + $variant) % count($focus)];
    $zoom = $variant === 0 ? 1.0 : min(1.8, 1.2 + 0.18 * $variant);
    return px_cover_cell($srcs[$i % $n], $w, $h, $zoom, $fx, $fyv, $variant % 2 === 1);
}

/** Places photo #$i into a rectangle. */
function pt_photo($canvas, $srcs, int $i, int $x, int $y, int $w, int $h, float $fy = 0.32): void
{
    if ($w < 2 || $h < 2) return;
    $cell = pt_cell($srcs, $i, $w, $h, $fy);
    imagecopy($canvas, $cell, $x, $y, 0, 0, $w, $h);
    imagedestroy($cell);
}

/**
 * Places photo #$i with rounded corners (or a circle when $r >= w/2) onto a solid background of
 * $bgRgb. Drawn at 2× and scaled down so the curved edges are smooth, not jagged.
 */
function pt_photo_round($canvas, $srcs, int $i, int $x, int $y, int $w, int $h, int $r, array $bgRgb, float $fy = 0.32): void
{
    if ($w < 2 || $h < 2) return;
    $W2 = $w * 2; $H2 = $h * 2;
    $cell = pt_cell($srcs, $i, $W2, $H2, $fy);
    $mask = imagecreatetruecolor($W2, $H2);
    $bg = imagecolorallocate($mask, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
    $key = imagecolorallocate($mask, 255, 0, 254);
    imagefilledrectangle($mask, 0, 0, $W2, $H2, $bg);
    if ($r * 2 >= min($w, $h)) imagefilledellipse($mask, $w, $h, $W2, $H2, $key);
    else px_rrect($mask, 0, 0, $W2 - 1, $H2 - 1, $r * 2, $key);
    imagecolortransparent($mask, $key);
    imagecopymerge($cell, $mask, 0, 0, 0, 0, $W2, $H2, 100);
    imagecopyresampled($canvas, $cell, $x, $y, 0, 0, $w, $h, $W2, $H2);
    imagedestroy($cell); imagedestroy($mask);
}

/** Draws something on a transparent layer via $draw($layer, $lw, $lh), rotates it by $deg and pastes it centred at ($cx,$cy). */
function pt_rotated($canvas, int $lw, int $lh, float $deg, int $cx, int $cy, callable $draw): void
{
    $layer = imagecreatetruecolor($lw, $lh);
    imagesavealpha($layer, true);
    imagealphablending($layer, false);
    imagefilledrectangle($layer, 0, 0, $lw, $lh, imagecolorallocatealpha($layer, 0, 0, 0, 127));
    imagealphablending($layer, true);
    $draw($layer, $lw, $lh);
    if (abs($deg) > 0.01) {
        $rot = imagerotate($layer, $deg, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagedestroy($layer);
        $layer = $rot;
        imagesavealpha($layer, true);
    }
    $w = imagesx($layer); $h = imagesy($layer);
    imagealphablending($canvas, true);
    imagecopy($canvas, $layer, (int)($cx - $w / 2), (int)($cy - $h / 2), 0, 0, $w, $h);
    imagedestroy($layer);
}

/** Soft drop shadow + text (readable on photos without darkening the photo). */
function pt_shadow_text($im, int $size, string $font, string $text, int $x, int $y, int $color, int $off = 3): void
{
    $sh = imagecolorallocatealpha($im, 0, 0, 0, 70);
    for ($d = 1; $d <= $off; $d++) imagettftext($im, $size, 0, $x + $d, $y + $d, $sh, $font, $text);
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/** Text with an outline, left-aligned at $x. */
function pt_outline_left($im, int $size, string $font, string $text, int $x, int $y, int $color, int $outline, int $ow): void
{
    for ($r = $ow; $r >= 1; $r = (int)floor($r * 0.6)) {
        $steps = max(16, $r * 3);
        for ($i = 0; $i < $steps; $i++) {
            $a = 2 * M_PI * $i / $steps;
            imagettftext($im, $size, 0, $x + (int)round(cos($a) * $r), $y + (int)round(sin($a) * $r), $outline, $font, $text);
        }
        if ($r === 1) break;
    }
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/** Text with a hard offset shadow in a second colour (the "3D" sticker look in the reference pins). */
function pt_offset_text($im, int $size, string $font, string $text, int $cx, int $y, int $color, int $shadow, int $off): void
{
    $x = (int)($cx - px_text_w($size, $font, $text) / 2);
    for ($d = 1; $d <= $off; $d++) imagettftext($im, $size, 0, $x + $d, $y + $d, $shadow, $font, $text);
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/**
 * Splits a title into [number, lead, main, tail]:
 *   "How to Wear Wide Leg Pants Over 50" -> [null, "How to Wear", "Wide Leg Pants", "Over 50"]
 *   "17 Wool Outfits for Women Over 50 That Are Warm" -> ["17", "", "Wool Outfits", "for Women Over 50 That Are Warm"]
 */
function pt_parts(string $title): array
{
    [$num, $rest] = px_split_number($title);
    $lead = '';
    if (preg_match('/^((?:how|ways|what|where|when|why)\s+to\s+\S+|(?:the\s+)?(?:best|easy|simple|cozy|quick)|must[- ]have)\s+(.+)$/iu', $rest, $m)) {
        $lead = trim($m[1]); $rest = trim($m[2]);
    }
    $tail = '';
    if (preg_match('/^(.+?)\s+((?:that|which|to|you|for|over|after|in\s+your|with|on\s+a|under)\b.*)$/iu', $rest, $m) && str_word_count($m[1]) >= 1) {
        $main = trim($m[1]); $tail = trim($m[2]);
    } else {
        $main = $rest;
        $w = preg_split('/\s+/', $rest);
        if (count($w) > 5) { $main = implode(' ', array_slice($w, 0, -2)); $tail = implode(' ', array_slice($w, -2)); }
    }
    // drop the hook clause ("That Are Warm Without Being Heavy") — it doesn't fit on a pin label
    if (preg_match('/^(that|which|who)\b/i', $tail)) $tail = '';
    $tail = trim(preg_replace('/\s+(that|which|who)\b.*$/iu', '', $tail));
    return [$num, $lead, $main, $tail];
}

/** Fits $text into a box and draws centred lines. Returns the Y after the last line. */
function pt_lines_center($im, string $font, string $text, int $cx, int $top, int $maxW, int $maxH, int $start, int $maxLines, int $color, float $mul = 1.1, ?int $outline = null, int $ow = 0, int $min = 16): int
{
    [$size, $lines, $lh] = px_fit($font, $text, $maxW, $maxH, $start, $maxLines, $mul, $min);
    [$a] = px_metrics($size, $font);
    $y = $top;
    foreach ($lines as $l) {
        px_center_text($im, $size, $font, $l, $cx, $y + $a, $color, $outline, $ow);
        $y += $lh;
    }
    return $y;
}

/** Height a pt_lines_center() call would use. */
function pt_lines_height(string $font, string $text, int $maxW, int $maxH, int $start, int $maxLines, float $mul = 1.1, int $min = 16): int
{
    [, $lines, $lh] = px_fit($font, $text, $maxW, $maxH, $start, $maxLines, $mul, $min);
    return count($lines) * $lh;
}

/** Brand wordmark like "SAGELY CHIC": first word bold, second word boxed. */
function pt_brand_mark($im, string $website, int $cx, int $baseY, int $size, array $rgb1, array $boxRgb, array $boxTextRgb): void
{
    $name = preg_replace('~^(https?://)?(www\.)?~i', '', $website);
    $name = preg_replace('~\.(com|net|org|co|io|us|blog|info|site)(/.*)?$~i', '', $name);
    $name = px_upper(trim($name));
    if ($name === '') return;
    $f = px_font('Poppins-ExtraBold.ttf');
    $name = str_replace(['-', '_'], ' ', $name);
    if (strpos($name, ' ') === false) {
        px_center_text($im, $size, $f, $name, $cx, $baseY, px_col($im, $rgb1));
        return;
    }
    $a = substr($name, 0, strrpos($name, ' ')); $b = substr($name, strrpos($name, ' ') + 1);
    $wa = px_text_w($size, $f, $a); $wb = px_text_w($size, px_font('Poppins-Regular.ttf'), $b);
    $pad = (int)($size * 0.25);
    $total = $wa + $pad + $wb + $pad * 2;
    $x = (int)($cx - $total / 2);
    imagettftext($im, $size, 0, $x, $baseY, px_col($im, $rgb1), $f, $a);
    $bx = $x + $wa + $pad;
    imagefilledrectangle($im, $bx, $baseY - $size - $pad, $bx + $wb + $pad * 2, $baseY + (int)($pad * 0.9), px_col($im, $boxRgb));
    imagettftext($im, $size, 0, $bx + $pad, $baseY, px_col($im, $boxTextRgb), px_font('Poppins-Regular.ttf'), $b);
}

/** Rounded pill with centred text (e.g. "sagelychic.com" footer). */
function pt_pill($im, string $text, int $cx, int $cy, int $size, string $font, array $bgRgb, array $fgRgb, float $padX = 0.9, float $padY = 0.55): array
{
    $tw = px_text_w($size, $font, $text);
    [$a, $d] = px_metrics($size, $font);
    $w = $tw + (int)($size * $padX * 2); $h = $a + $d + (int)($size * $padY * 2);
    $x1 = (int)($cx - $w / 2); $y1 = (int)($cy - $h / 2);
    px_rrect($im, $x1, $y1, $x1 + $w, $y1 + $h, (int)($h / 2), px_col($im, $bgRgb));
    imagettftext($im, $size, 0, (int)($cx - $tw / 2), $y1 + (int)($size * $padY) + $a, px_col($im, $fgRgb), $font, $text);
    return [$x1, $y1, $x1 + $w, $y1 + $h];
}

/** Dashed curved arrow (the hand-drawn pointer in the reference pins). */
function pt_dashed_arrow($im, array $pts, int $color, int $thick = 4, bool $dashed = true): void
{
    imagesetthickness($im, $thick);
    $n = count($pts);
    $prev = null; $seg = 0;
    // Catmull-Rom style smoothing through the points.
    for ($i = 0; $i < $n - 1; $i++) {
        [$x0, $y0] = $pts[max(0, $i - 1)]; [$x1, $y1] = $pts[$i]; [$x2, $y2] = $pts[$i + 1]; [$x3, $y3] = $pts[min($n - 1, $i + 2)];
        for ($t = 0; $t <= 1.0001; $t += 0.05) {
            $t2 = $t * $t; $t3 = $t2 * $t;
            $x = 0.5 * ((2 * $x1) + (-$x0 + $x2) * $t + (2 * $x0 - 5 * $x1 + 4 * $x2 - $x3) * $t2 + (-$x0 + 3 * $x1 - 3 * $x2 + $x3) * $t3);
            $y = 0.5 * ((2 * $y1) + (-$y0 + $y2) * $t + (2 * $y0 - 5 * $y1 + 4 * $y2 - $y3) * $t2 + (-$y0 + 3 * $y1 - 3 * $y2 + $y3) * $t3);
            if ($prev && (!$dashed || ($seg++ % 2 === 0))) imageline($im, (int)$prev[0], (int)$prev[1], (int)$x, (int)$y, $color);
            $prev = [$x, $y];
        }
    }
    // arrow head
    [$ax, $ay] = $pts[$n - 1]; [$bx, $by] = $pts[$n - 2];
    $ang = atan2($ay - $by, $ax - $bx);
    $len = $thick * 5;
    imagefilledpolygon($im, [
        (int)$ax, (int)$ay,
        (int)($ax - $len * cos($ang - 0.45)), (int)($ay - $len * sin($ang - 0.45)),
        (int)($ax - $len * cos($ang + 0.45)), (int)($ay - $len * sin($ang + 0.45)),
    ], $color);
    imagesetthickness($im, 1);
}

function pt_sparkle($im, int $cx, int $cy, int $r, int $color): void
{
    $k = max(2, (int)($r * 0.18));
    imagefilledpolygon($im, [$cx, $cy - $r, $cx + $k, $cy - $k, $cx + $r, $cy, $cx + $k, $cy + $k, $cx, $cy + $r, $cx - $k, $cy + $k, $cx - $r, $cy, $cx - $k, $cy - $k], $color);
}

function pt_begin(array $imageBytesList, string $sizeKey, array $bg = [255, 255, 255]): ?array
{
    [$W, $H] = pin_image_size_dims($sizeKey);
    $srcs = px_sources($imageBytesList);
    if (!$srcs) return null;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $W, $H, px_col($im, $bg));
    return [$im, $srcs, $W, $H];
}

function pt_end($im, array $srcs): ?string
{
    foreach ($srcs as $s) imagedestroy($s);
    ob_start();
    imagejpeg($im, null, 94);
    $out = ob_get_clean();
    imagedestroy($im);
    return $out ?: null;
}

function pt_site(string $website): string
{
    return preg_replace('~^(https?://)?(www\.)?~i', '', trim($website));
}

/* ======================= designs from the reference pins ======================= */

/** tpl_label_stack — full photo, dashed arrow, three tilted labels ("How to Wear" / MAIN / "OVER 50"), brand wordmark. */
function pt_label_stack(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.2);
    [$num, $lead, $main, $tail] = pt_parts($title);
    if ($num !== null) $lead = trim($num . ' ' . $lead);
    $cream = px_pal($pal, 1, [236, 228, 216]); $olive = px_pal($pal, 0, [110, 114, 86]); $ink = [34, 24, 20];
    $fR = px_font('TitanOne-Regular.ttf'); $fB = px_font('Anton-Regular.ttf');
    $cx = (int)($W / 2);
    $mainTop = (int)($H * 0.64);
    [$ms, $ml, $mlh] = px_fit($fR, px_upper($main), (int)($W * 0.74), (int)($H * 0.17), (int)($W / 8.5), 3, 1.08);
    $mh = count($ml) * $mlh + (int)($W * 0.06);
    $boxW = (int)($W * 0.82);
    imagefilledrectangle($im, $cx - (int)($boxW / 2), $mainTop, $cx + (int)($boxW / 2), $mainTop + $mh, px_col($im, $olive));
    [$a] = px_metrics($ms, $fR);
    $y = $mainTop + (int)($W * 0.03);
    foreach ($ml as $l) { px_center_text($im, $ms, $fR, $l, $cx, $y + $a, px_col($im, [255, 255, 255]), px_col($im, $ink), max(3, (int)($ms * 0.07))); $y += $mlh; }

    if ($lead !== '') {
        [$ls, $ll] = px_fit($fR, $lead, (int)($W * 0.62), (int)($W * 0.12), (int)($W / 11), 1, 1.0);
        $lw = px_text_w($ls, $fR, $ll[0]) + (int)($W * 0.12); $lh = (int)($ls * 1.9);
        pt_rotated($im, $lw + 10, $lh + 10, 3, (int)($W * 0.53), $mainTop - (int)($lh * 0.42), function ($L, $w, $h) use ($ls, $fR, $ll, $cream, $ink) {
            imagefilledrectangle($L, 5, 5, $w - 5, $h - 5, px_col($L, $cream));
            [$a] = px_metrics($ls, $fR);
            px_center_text($L, $ls, $fR, $ll[0], (int)($w / 2), (int)(($h + $a * 0.8) / 2), px_col($L, [255, 255, 255]), px_col($L, $ink), max(3, (int)($ls * 0.09)));
        });
    }
    $tailText = $tail !== '' ? $tail : ($cta !== '' ? $cta : '');
    if ($tailText !== '') {
        [$ts, $tl] = px_fit($fB, px_upper($tailText), (int)($W * 0.5), (int)($W * 0.1), (int)($W / 12), 1, 1.0);
        $tw = px_text_w($ts, $fB, $tl[0]) + (int)($W * 0.1); $th = (int)($ts * 1.7);
        pt_rotated($im, $tw + 10, $th + 10, -3, $cx, $mainTop + $mh + (int)($th * 0.62), function ($L, $w, $h) use ($ts, $fB, $tl, $cream, $ink) {
            imagefilledrectangle($L, 5, 5, $w - 5, $h - 5, px_col($L, $cream));
            [$a] = px_metrics($ts, $fB);
            px_center_text($L, $ts, $fB, $tl[0], (int)($w / 2), (int)(($h + $a * 0.8) / 2), px_col($L, $ink));
        });
    }
    // dashed curly arrow toward the label stack
    pt_dashed_arrow($im, [[(int)($W * 0.02), (int)($H * 0.40)], [(int)($W * 0.09), (int)($H * 0.39)], [(int)($W * 0.08), (int)($H * 0.45)], [(int)($W * 0.04), (int)($H * 0.43)], [(int)($W * 0.15), (int)($H * 0.42)], [(int)($W * 0.27), (int)($H * 0.5)]], px_col($im, [255, 255, 255]), 4);
    if ($website !== '') pt_brand_mark($im, $website, $cx, $H - (int)($H * 0.022), (int)($W / 26), [255, 255, 255], [255, 255, 255], $ink);
    return pt_end($im, $srcs);
}

/** tpl_highlight_lines — full photo, big white left-aligned lines, key words on coloured highlight bars, curved arrow. */
function pt_highlight_lines(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.2);
    $f = px_font('Poppins-Bold.ttf');
    $hl = px_pal($pal, 0, [150, 104, 70]);
    $white = px_col($im, [255, 255, 255]);
    $words = preg_split('/\s+/', trim($title));
    // lines of 1-2 words (the reference stacks short lines); highlight the longest words
    $size = (int)($W / 7.2);
    $maxW = (int)($W * 0.8);
    $lines = [];
    foreach ($words as $w) {
        $last = end($lines);
        if ($last !== false && px_text_w($size, $f, $last . ' ' . $w) <= $maxW && str_word_count($last) < 2) { $lines[key($lines)] = $last . ' ' . $w; }
        else $lines[] = $w;
    }
    while (count($lines) * $size * 1.25 > $H * 0.7 && $size > 30) {
        $size = (int)($size * 0.9);
        $lines = pin_image_wrap_text($f, $size, $title, $maxW, 12);
    }
    $scores = array_map(fn($l) => preg_match('/^(how|to|the|a|and|for|of|in|over|\d+)$/i', $l) ? 0 : mb_strlen($l), $lines);
    arsort($scores);
    $hi = array_slice(array_keys($scores), 0, min(2, max(1, (int)floor(count($lines) / 3))));
    $lh = pin_line_height($f, $size, 1.28, $lines);
    [$a, $d] = px_metrics($size, $f);
    $x = (int)($W * 0.085);
    $y = (int)(($H - count($lines) * $lh) / 2) - (int)($H * 0.02);
    foreach ($lines as $i => $l) {
        if (in_array($i, $hi, true)) {
            $tw = px_text_w($size, $f, $l);
            imagefilledrectangle($im, $x - (int)($size * 0.18), $y + (int)($lh * 0.04), $x + $tw + (int)($size * 0.22), $y + $lh - (int)($lh * 0.02), px_col($im, $hl));
            imagettftext($im, $size, 0, $x, $y + (int)(($lh + $a - $d) / 2), $white, $f, $l);
        } else {
            pt_shadow_text($im, $size, $f, $l, $x, $y + (int)(($lh + $a - $d) / 2), $white, 4);
        }
        $y += $lh;
    }
    $top = (int)(($H - count($lines) * $lh) / 2);
    pt_dashed_arrow($im, [[(int)($W * 0.98), (int)($top - $H * 0.02)], [(int)($W * 0.86), (int)($top + $H * 0.03)], [(int)($W * 0.79), (int)($top + $H * 0.11)]], $white, 5, false);
    if ($website !== '') pt_brand_mark($im, $website, (int)($W / 2), $H - (int)($H * 0.03), (int)($W / 28), [214, 186, 150], [255, 255, 255], [30, 30, 30]);
    return pt_end($im, $srcs);
}

/** tpl_cream_band — 2 photos / cream band (outlined kicker, dark bar, big outlined lines) / 2 photos, vertical brand. */
function pt_cream_band(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey, [238, 234, 228]); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $cream = [238, 234, 228]; $brown = px_pal($pal, 0, [178, 108, 70]); $dark = px_pal($pal, 1, [98, 70, 50]); $ink = [40, 30, 24];
    $gap = (int)($W * 0.008);
    $bandY = (int)($H * 0.325); $bandH = (int)($H * 0.33);
    $cw = (int)(($W - $gap) / 2);
    pt_photo($im, $srcs, 0, 0, 0, $cw, $bandY, 0.15);
    pt_photo($im, $srcs, 1, $cw + $gap, 0, $W - $cw - $gap, $bandY, 0.15);
    pt_photo($im, $srcs, 2, 0, $bandY + $bandH, $cw, $H - $bandY - $bandH, 0.2);
    pt_photo($im, $srcs, 3, $cw + $gap, $bandY + $bandH, $W - $cw - $gap, $H - $bandY - $bandH, 0.2);
    [$num, $lead, $main, $tail] = pt_parts($title);
    $kicker = $cta !== '' ? $cta : ($num !== null ? $num . ' ' . $lead : $lead);
    $fK = px_font('TitanOne-Regular.ttf'); $fBar = px_font('Poppins-Bold.ttf'); $fBig = px_font('LuckiestGuy-Regular.ttf');
    $y = $bandY + (int)($bandH * 0.08);
    if (trim($kicker) !== '') {
        [$ks, $kl] = px_fit($fK, px_upper($kicker), (int)($W * 0.86), (int)($bandH * 0.2), (int)($W / 11), 1, 1.0);
        [$ka] = px_metrics($ks, $fK);
        pt_offset_text($im, $ks, $fK, $kl[0], (int)($W / 2), $y + $ka, px_col($im, $brown), px_col($im, $ink), 4);
        $y += (int)($ks * 1.35);
    }
    [$bs, $bl] = px_fit($fBar, $main, (int)($W * 0.86), (int)($bandH * 0.22), (int)($W / 11.5), 1, 1.0);
    $bh = (int)($bs * 1.6);
    imagefilledrectangle($im, (int)($W * 0.03), $y, $W - (int)($W * 0.03), $y + $bh, px_col($im, $dark));
    [$ba, $bd] = px_metrics($bs, $fBar);
    px_center_text($im, $bs, $fBar, $bl[0], (int)($W / 2), $y + (int)(($bh + $ba - $bd) / 2), px_col($im, [255, 255, 255]));
    $y += $bh + (int)($bandH * 0.05);
    if ($tail !== '') {
        [$ts, $tl, $tlh] = px_fit($fBig, px_upper($tail), (int)($W * 0.9), $bandY + $bandH - $y - (int)($bandH * 0.05), (int)($W / 10), 2, 1.02);
        [$ta] = px_metrics($ts, $fBig);
        foreach ($tl as $l) { pt_offset_text($im, $ts, $fBig, $l, (int)($W / 2), $y + $ta, px_col($im, $brown), px_col($im, $ink), 4); $y += $tlh; }
    }
    if ($website !== '') {
        $name = px_upper(pt_site($website));
        $fs = max(12, (int)($W / 34));
        $tw = px_text_w($fs, $fBar, $name);
        pt_rotated($im, $tw + 24, (int)($fs * 2), 90, $W - (int)($fs * 1.1), $H - (int)($tw / 2) - 20, function ($L, $w, $h) use ($fs, $fBar, $name, $ink) {
            px_rrect($L, 0, 0, $w - 1, $h - 1, 6, px_col($L, [255, 255, 255]));
            px_center_text($L, $fs, $fBar, $name, (int)($w / 2), (int)($h * 0.72), px_col($L, $ink));
        });
    }
    return pt_end($im, $srcs);
}

/** tpl_side_stack — photo, right-side stacked condensed words (black/pink with white outline), pink website bar. */
function pt_side_stack(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $barH = $website !== '' ? (int)($H * 0.035) : 0;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H - $barH, 0.2);
    $pink = px_pal($pal, 0, [240, 36, 92]);
    $f = px_font('Anton-Regular.ttf'); $fS = px_font('Pacifico-Regular.ttf');
    $white = px_col($im, [255, 255, 255]); $black = px_col($im, [10, 10, 10]); $pinkC = px_col($im, $pink);
    $words = preg_split('/\s+/', px_upper(trim($title)));
    $last = array_pop($words);
    // keep "Over 50" / "After 40" together on the script line
    if (preg_match('/^\d+[\'’]?S?$/u', $last) && $words) $last = array_pop($words) . ' ' . $last;
    $lines = [];
    foreach ($words as $w) {
        $cur = end($lines);
        if ($cur !== false && mb_strlen($cur . ' ' . $w) <= 9) $lines[key($lines)] = $cur . ' ' . $w; else $lines[] = $w;
    }
    $colW = (int)($W * 0.48); $x2 = $W - (int)($W * 0.05);
    $y = (int)($H * 0.1);
    $avail = (int)($H * 0.72);
    $n = count($lines) + 1;
    $size = (int)min($W / 7, $avail / max(1, $n) / 1.05);
    foreach ($lines as $i => $l) {
        $s = $size;
        while (px_text_w($s, $f, $l) > $colW && $s > 20) $s--;
        [$a] = px_metrics($s, $f);
        $isPink = $i >= (int)floor(count($lines) * 0.4) && $i < count($lines) - 0 && $i % 3 !== 0;
        $tw = px_text_w($s, $f, $l);
        pt_outline_left($im, $s, $f, $l, $x2 - $tw, $y + $a, $isPink ? $pinkC : $black, $white, max(4, (int)($s * 0.08)));
        $y += pin_line_height($f, $s, 1.08, [$l]) + (int)($s * 0.08);
    }
    $ls = (int)($size * 1.25);
    $lastT = mb_convert_case(mb_strtolower($last), MB_CASE_TITLE);
    while (px_text_w($ls, $fS, $lastT) > $colW && $ls > 20) $ls--;
    [$la] = px_metrics($ls, $fS);
    pt_outline_left($im, $ls, $fS, $lastT, $x2 - px_text_w($ls, $fS, $lastT), $y + $la, $pinkC, $white, max(4, (int)($ls * 0.06)));
    if ($website !== '') {
        imagefilledrectangle($im, 0, $H - $barH, $W, $H, $pinkC);
        px_spaced_text($im, max(12, (int)($barH * 0.5)), px_font('Poppins-Regular.ttf'), px_upper(pt_site($website)), (int)($W / 2), $H - (int)($barH * 0.28), $white, (int)($barH * 0.12));
    }
    return pt_end($im, $srcs);
}

/** tpl_top_panel — solid top panel (brand, "HOW TO" with rules, big outlined title, subtitle), photo below, dashed arrow. */
function pt_top_panel(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bg = px_pal($pal, 0, [88, 124, 160]);
    $b = pt_begin($imgs, $sizeKey, $bg); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $panelH = (int)($H * 0.375);
    pt_photo($im, $srcs, 0, 0, $panelH, $W, $H - $panelH, 0.15);
    $white = px_col($im, [255, 255, 255]); $black = px_col($im, [12, 12, 12]);
    $f = px_font('LuckiestGuy-Regular.ttf');
    [$num, $lead, $main, $tail] = pt_parts($title);
    $y = (int)($H * 0.02);
    if ($website !== '') { pt_brand_mark($im, $website, (int)($W / 2), $y + (int)($W / 22), (int)($W / 26), [236, 230, 214], [255, 255, 255], [60, 70, 80]); $y += (int)($W / 13); }
    $kick = $lead !== '' ? $lead : ($num !== null ? $num : '');
    if ($kick !== '') {
        $ks = (int)($W / 17);
        [$ka] = px_metrics($ks, $f);
        $kw = px_text_w($ks, $f, px_upper($kick));
        px_center_text($im, $ks, $f, px_upper($kick), (int)($W / 2), $y + $ka, $white, $black, 3);
        imagesetthickness($im, 2);
        imageline($im, (int)($W * 0.04), $y + (int)($ka * 0.55), (int)($W / 2 - $kw / 2 - 24), $y + (int)($ka * 0.55), $black);
        imageline($im, (int)($W / 2 + $kw / 2 + 24), $y + (int)($ka * 0.55), (int)($W * 0.96), $y + (int)($ka * 0.55), $black);
        imagesetthickness($im, 1);
        $y += (int)($ks * 1.5);
    }
    $sub = $tail !== '' ? $tail : $cta;
    $subH = $sub !== '' ? (int)($W / 9) : 0;
    $y = pt_lines_center($im, $f, px_upper($main), (int)($W / 2), $y, (int)($W * 0.9), $panelH - $y - $subH - (int)($H * 0.02), (int)($W / 8.5), 2, $white, 1.05, $black, max(5, (int)($W / 120)));
    if ($sub !== '') {
        [$ss, $sl] = px_fit($f, px_upper($sub), (int)($W * 0.8), $subH, (int)($W / 11), 1, 1.0);
        [$sa] = px_metrics($ss, $f);
        px_center_text($im, $ss, $f, $sl[0], (int)($W / 2), $y + (int)($H * 0.012) + $sa, $white, $black, 4);
    }
    pt_dashed_arrow($im, [[$W - 4, (int)($panelH + $H * 0.11)], [(int)($W * 0.92), (int)($panelH + $H * 0.09)], [(int)($W * 0.94), (int)($panelH + $H * 0.05)], [(int)($W * 0.89), (int)($panelH + $H * 0.07)], [(int)($W * 0.74), (int)($panelH + $H * 0.07)], [(int)($W * 0.69), (int)($panelH + $H * 0.005)]], $white, 4);
    return pt_end($im, $srcs);
}

/** tpl_half_circle — 2 photos / half-circle number badge / white band with outlined title / 2 photos / black website pill. */
function pt_half_circle(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $maroon = px_pal($pal, 0, [120, 40, 20]); $brown = px_pal($pal, 1, [110, 60, 30]); $ink = [40, 24, 16];
    $gap = (int)($W * 0.008); $cw = (int)(($W - $gap) / 2);
    $topH = (int)($H * 0.36); $band = (int)($H * 0.25);
    pt_photo($im, $srcs, 0, 0, 0, $cw, $topH, 0.15);
    pt_photo($im, $srcs, 1, $cw + $gap, 0, $W - $cw - $gap, $topH, 0.15);
    pt_photo($im, $srcs, 2, 0, $topH + $band, $cw, $H - $topH - $band, 0.18);
    pt_photo($im, $srcs, 3, $cw + $gap, $topH + $band, $W - $cw - $gap, $H - $topH - $band, 0.18);
    [$num, $rest] = px_split_number($title);
    if ($num !== null) {
        $r = (int)($W * 0.22);
        imagefilledarc($im, (int)($W / 2), $topH, $r * 2, $r * 2, 180, 360, px_col($im, $maroon), IMG_ARC_PIE);
        $ns = (int)($r * 0.62);
        $fN = px_font('LuckiestGuy-Regular.ttf');
        [$na] = px_metrics($ns, $fN);
        px_center_text($im, $ns, $fN, $num, (int)($W / 2), $topH - (int)($r * 0.18), px_col($im, [255, 255, 255]), px_col($im, $ink), 4);
    }
    [, , $main, $tail] = pt_parts($rest);
    $fT = px_font('TitanOne-Regular.ttf');
    $y = $topH + (int)($band * 0.1);
    $subH = $tail !== '' ? (int)($band * 0.24) : 0;
    $y = pt_lines_center($im, $fT, px_upper($main), (int)($W / 2), $y, (int)($W * 0.9), $band - $subH - (int)($band * 0.18), (int)($W / 9), 2, px_col($im, $brown), 1.05, px_col($im, $ink), max(3, (int)($W / 250)));
    if ($tail !== '') {
        [$ts, $tl] = px_fit($fT, px_upper($tail), (int)($W * 0.9), $subH, (int)($W / 15), 1, 1.0);
        [$ta] = px_metrics($ts, $fT);
        px_center_text($im, $ts, $fT, $tl[0], (int)($W / 2), $y + $ta, px_col($im, [255, 255, 255]), px_col($im, $maroon), max(4, (int)($ts / 14)));
    }
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.035), (int)($W / 26), px_font('Poppins-Regular.ttf'), [14, 14, 14], [255, 255, 255], 0.7, 0.45);
    return pt_end($im, $srcs);
}

/** tpl_rounded_tiles — white page, rounded photo tiles around, big number, pastel pill title, brown subtitle, sparkles, arrow. */
function pt_rounded_tiles(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bgRgb = [250, 249, 250];
    $b = pt_begin($imgs, $sizeKey, $bgRgb); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $purple = px_pal($pal, 0, [150, 40, 150]); $pill = px_pal($pal, 1, [244, 222, 244]); $brown = px_pal($pal, 2, [120, 72, 56]);
    $m = (int)($W * 0.018); $r = (int)($W * 0.04);
    $u = (int)($H / 100);
    // top: tall left, wide middle, two small right
    pt_photo_round($im, $srcs, 0, $m, $m, (int)($W * 0.26), 32 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 1, (int)($W * 0.3), $m, (int)($W * 0.4), 24 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 2, (int)($W * 0.73), $m, (int)($W * 0.25), 14 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 3, (int)($W * 0.73), 17 * $u, (int)($W * 0.25), 13 * $u, $r, $bgRgb, 0.15);
    // bottom
    pt_photo_round($im, $srcs, 4, $m, 71 * $u, (int)($W * 0.24), 15 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 5, $m, 87 * $u, (int)($W * 0.24), 12 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 6, (int)($W * 0.28), 69 * $u, (int)($W * 0.45), 30 * $u, $r, $bgRgb, 0.12);
    pt_photo_round($im, $srcs, 7, (int)($W * 0.75), 72 * $u, (int)($W * 0.23), 14 * $u, $r, $bgRgb, 0.15);
    pt_photo_round($im, $srcs, 8, (int)($W * 0.75), 88 * $u, (int)($W * 0.23), 11 * $u, $r, $bgRgb, 0.15);
    [$num, $rest] = px_split_number($title);
    [, $lead, $main, $tail] = pt_parts($rest);
    $main = trim($lead . ' ' . $main);
    $cx = (int)($W / 2);
    $y = 32 * $u;
    if ($num !== null) {
        $fN = px_font('LuckiestGuy-Regular.ttf');
        $ns = (int)($W / 7.5);
        [$na] = px_metrics($ns, $fN);
        pt_offset_text($im, $ns, $fN, $num, $cx, $y + $na, px_col($im, [15, 15, 15]), px_col($im, [214, 150, 140]), 4);
        $y += (int)($ns * 1.08);
        imagesetthickness($im, 2);
        imageline($im, (int)($W * 0.32), $y, (int)($W * 0.68), $y, px_col($im, [20, 20, 20]));
        imagesetthickness($im, 1);
        $y += (int)(2 * $u);
    }
    $fT = px_font('ArchivoBlack-Regular.ttf');
    [$ts, $tl, $tlh] = px_fit($fT, px_upper($main), (int)($W * 0.72), 14 * $u, (int)($W / 13), 2, 1.12);
    $pillH = count($tl) * $tlh + (int)($ts * 0.6);
    $widest = max(array_map(fn($l) => px_text_w($ts, $fT, $l), $tl));
    px_rrect($im, (int)($cx - $widest / 2 - $W * 0.04), $y, (int)($cx + $widest / 2 + $W * 0.04), $y + $pillH, (int)($W * 0.04), px_col($im, $pill));
    [$ta] = px_metrics($ts, $fT);
    $yy = $y + (int)($ts * 0.3);
    foreach ($tl as $l) { px_center_text($im, $ts, $fT, $l, $cx, $yy + $ta, px_col($im, $purple)); $yy += $tlh; }
    $y += $pillH + (int)(2 * $u);
    $sub = $tail !== '' ? $tail : $cta;
    if ($sub !== '') {
        $fB = px_font('Anton-Regular.ttf');
        pt_lines_center($im, $fB, px_upper($sub), $cx, $y, (int)($W * 0.94), 68 * $u - $y, (int)($W / 12), 2, px_col($im, $brown), 1.1);
    }
    pt_sparkle($im, (int)($W * 0.87), 38 * $u, (int)($W * 0.07), px_col($im, [15, 15, 15]));
    pt_sparkle($im, (int)($W * 0.95), 35 * $u, (int)($W * 0.025), px_col($im, [15, 15, 15]));
    pt_sparkle($im, (int)($W * 0.1), 40 * $u, (int)($W * 0.035), px_col($im, [170, 120, 115]));
    pt_dashed_arrow($im, [[0, 62 * $u], [(int)($W * 0.08), 57 * $u], [(int)($W * 0.07), 62 * $u], [(int)($W * 0.14), 55 * $u], [(int)($W * 0.24), 50 * $u]], px_col($im, [20, 20, 20]), 3, false);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W * 0.5), 96 * $u, (int)($W / 28), px_font('Poppins-Regular.ttf'), [14, 14, 14], [255, 255, 255], 0.7, 0.4);
    return pt_end($im, $srcs);
}

/** tpl_magazine_band — 3 photos / white band (big number, script word, brown title, navy subtitle) / 6 photos / website pill. */
function pt_magazine_band(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bgRgb = [246, 246, 246];
    $b = pt_begin($imgs, $sizeKey, $bgRgb); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $navy = px_pal($pal, 0, [50, 72, 100]); $brown = px_pal($pal, 1, [110, 56, 20]); $tan = px_pal($pal, 2, [214, 170, 120]);
    $g = (int)($W * 0.006); $cw = (int)(($W - 2 * $g) / 3);
    $topH = (int)($H * 0.255); $bandY = $topH; $bandH = (int)($H * 0.345);
    for ($i = 0; $i < 3; $i++) pt_photo($im, $srcs, $i, $i * ($cw + $g), 0, $i === 2 ? $W - 2 * ($cw + $g) : $cw, $topH, 0.12);
    $rowH = (int)(($H - $bandY - $bandH - $g) / 2);
    for ($r = 0; $r < 2; $r++) for ($c = 0; $c < 3; $c++) {
        pt_photo($im, $srcs, 3 + $r * 3 + $c, $c * ($cw + $g), $bandY + $bandH + $r * ($rowH + $g), $c === 2 ? $W - 2 * ($cw + $g) : $cw, $r === 1 ? $H - ($bandY + $bandH + $rowH + $g) : $rowH, 0.15);
    }
    [$num, $lead, $main0, $tail] = pt_parts($title);
    if ($lead !== '') { $script = mb_convert_case(mb_strtolower($lead), MB_CASE_TITLE); $main = $main0; }
    else { $w = preg_split('/\s+/', $main0); $script = array_shift($w); $main = implode(' ', $w); }
    if ($main === '') { $main = $tail; $tail = ''; }
    $fN = px_font('ArchivoBlack-Regular.ttf'); $fS = px_font('Pacifico-Regular.ttf'); $fT = px_font('Poppins-ExtraBold.ttf');
    $y = $bandY + (int)($bandH * 0.05);
    $rowTop = $y;
    $numS = (int)($bandH * 0.3);
    $scS = (int)($bandH * 0.24);
    $numW = $num !== null ? px_text_w($numS, $fN, $num) : 0;
    $scW = px_text_w($scS, $fS, $script);
    $total = $numW + ($numW ? (int)($W * 0.06) : 0) + $scW;
    if ($total > $W * 0.92) { $k = $W * 0.92 / $total; $numS = (int)($numS * $k); $scS = (int)($scS * $k); $numW = $num !== null ? px_text_w($numS, $fN, $num) : 0; $scW = px_text_w($scS, $fS, $script); $total = $numW + ($numW ? (int)($W * 0.06) : 0) + $scW; }
    $x = (int)(($W - $total) / 2);
    [$na] = px_metrics($numS, $fN);
    if ($num !== null) { pt_outline_left($im, $numS, $fN, $num, $x, $y + $na, px_col($im, $navy), px_col($im, [255, 255, 255]), 5); $x += $numW + (int)($W * 0.06); }
    for ($d = 1; $d <= 5; $d++) imagettftext($im, $scS, 0, $x + $d, $y + $na + $d, px_col($im, $tan), $fS, $script);
    imagettftext($im, $scS, 0, $x, $y + $na, px_col($im, [10, 10, 10]), $fS, $script);
    $y += (int)($numS * 1.15);
    $subH = $tail !== '' || $cta !== '' ? (int)($bandH * 0.12) : 0;
    $y = pt_lines_center($im, px_font('Poppins-Bold.ttf'), $main, (int)($W / 2), $y, (int)($W * 0.92), $bandY + $bandH - $y - $subH - (int)($bandH * 0.04), (int)($W / 10), 2, px_col($im, $brown), 1.1);
    $sub = $tail !== '' ? $tail : $cta;
    if ($sub !== '') pt_lines_center($im, $fT, px_upper($sub), (int)($W / 2), $y + (int)($bandH * 0.02), (int)($W * 0.94), $subH, (int)($W / 22), 1, px_col($im, $navy), 1.0);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.032), (int)($W / 26), px_font('Poppins-Regular.ttf'), [14, 14, 14], [255, 255, 255], 0.7, 0.45);
    return pt_end($im, $srcs);
}

/* ============================== 10 single-photo templates ============================== */

/** tpl_postcard (Travel) — full photo, white postcard card at the bottom with serif title + stamp. */
function pt_postcard(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.35);
    $accent = px_pal($pal, 0, [214, 90, 60]); $ink = [28, 36, 44];
    $fT = px_font('AbrilFatface-Regular.ttf'); $fK = px_font('Satisfy-Regular.ttf');
    $cx1 = (int)($W * 0.06); $cx2 = $W - $cx1;
    $kick = $cta !== '' ? $cta : 'Travel Guide';
    $th = pt_lines_height($fT, $title, (int)($W * 0.74), (int)($H * 0.2), (int)($W / 11), 3, 1.1);
    $cardH = $th + (int)($W * 0.2);
    $cy2 = $H - (int)($H * 0.04); $cy1 = $cy2 - $cardH;
    imagefilledrectangle($im, $cx1 + 8, $cy1 + 8, $cx2 + 8, $cy2 + 8, px_col($im, [0, 0, 0], 90));
    imagefilledrectangle($im, $cx1, $cy1, $cx2, $cy2, px_col($im, [253, 251, 246]));
    // airmail edge
    for ($x = $cx1; $x < $cx2; $x += 36) {
        imagefilledpolygon($im, [$x, $cy1, $x + 18, $cy1, $x + 10, $cy1 + 10, $x - 8, $cy1 + 10], px_col($im, $accent));
        imagefilledpolygon($im, [$x + 18, $cy1, $x + 36, $cy1, $x + 28, $cy1 + 10, $x + 10, $cy1 + 10], px_col($im, [40, 80, 150]));
    }
    // stamp
    $sw = (int)($W * 0.13); $sx = $cx2 - $sw - (int)($W * 0.03); $sy = $cy1 - (int)($sw * 0.55);
    pt_rotated($im, $sw + 20, (int)($sw * 1.2) + 20, -6, $sx + (int)($sw / 2), $sy + (int)($sw * 0.6), function ($L, $w, $h) use ($accent) {
        imagefilledrectangle($L, 10, 10, $w - 10, $h - 10, px_col($L, [255, 255, 255]));
        imagefilledrectangle($L, 18, 18, $w - 18, $h - 18, px_col($L, $accent));
        for ($x = 10; $x < $w - 10; $x += 10) { imagefilledellipse($L, $x, 10, 7, 7, px_col($L, [0, 0, 0], 127)); }
        pt_sparkle($L, (int)($w / 2), (int)($h / 2), (int)($w * 0.25), px_col($L, [255, 255, 255]));
    });
    $ks = (int)($W / 17);
    [$ka] = px_metrics($ks, $fK);
    px_center_text($im, $ks, $fK, $kick, (int)($W / 2), $cy1 + (int)($W * 0.03) + $ka, px_col($im, $accent));
    pt_lines_center($im, $fT, $title, (int)($W / 2), $cy1 + (int)($W * 0.05) + (int)($ks * 1.2), (int)($W * 0.74), (int)($H * 0.2), (int)($W / 11), 3, px_col($im, $ink), 1.1);
    if ($website !== '') px_center_text($im, max(12, (int)($W / 40)), px_font('Poppins-Bold.ttf'), pt_site($website), (int)($W / 2), $cy2 - (int)($W * 0.025), px_col($im, [120, 120, 120]));
    return pt_end($im, $srcs);
}

/** tpl_soft_pills (Beauty & Hair) — photo, soft pink stacked pills with the title, small white website pill. */
function pt_soft_pills(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.25);
    $pill = px_pal($pal, 0, [248, 214, 222]); $ink = px_pal($pal, 1, [120, 30, 60]);
    $f = px_font('Poppins-ExtraBold.ttf');
    [$s, $lines] = px_fit($f, $title, (int)($W * 0.8), (int)($H * 0.3), (int)($W / 11), 4, 1.3);
    $items = array_map(fn($l) => [$l, $f, $s, px_col($im, $ink)], $lines);
    $y = (int)($H * 0.58);
    $y = px_block_stack($im, $items, (int)($W / 2), $y, px_col($im, $pill), (int)($W * 0.045), (int)($s * 0.3), (int)($s * 0.9), 0);
    if ($cta !== '') pt_pill($im, $cta, (int)($W / 2), $y + (int)($s * 1.1), (int)($s * 0.5), px_font('Poppins-Bold.ttf'), $ink, [255, 255, 255]);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 34), px_font('Poppins-Bold.ttf'), [255, 255, 255], $ink);
    return pt_end($im, $srcs);
}

/** tpl_diagonal_band (Fitness) — photo, bold tilted colour band across with condensed uppercase title. */
function pt_diagonal_band(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $band = px_pal($pal, 0, [255, 214, 0]); $ink = [14, 14, 14];
    $f = px_font('Anton-Regular.ttf');
    [$s, $lines, $lh] = px_fit($f, px_upper($title), (int)($W * 0.76), (int)($H * 0.28), (int)($W / 8), 3, 1.18);
    $bh = count($lines) * $lh + (int)($W * 0.1);
    $bw = (int)($W * 1.3);
    pt_rotated($im, $bw, $bh, 5, (int)($W / 2), (int)($H * 0.62), function ($L, $w, $h) use ($s, $lines, $lh, $f, $band, $ink) {
        imagefilledrectangle($L, 0, 0, $w, $h, px_col($L, $band));
        imagefilledrectangle($L, 0, 0, $w, 8, px_col($L, $ink));
        imagefilledrectangle($L, 0, $h - 8, $w, $h, px_col($L, $ink));
        [$a] = px_metrics($s, $f);
        $y = (int)(($h - (count($lines) - 1) * $lh - $a) / 2);
        foreach ($lines as $l) { px_center_text($L, $s, $f, $l, (int)($w / 2), $y + $a, px_col($L, $ink)); $y += $lh; }
    });
    if ($cta !== '') pt_rotated($im, (int)($W * 0.6), (int)($W * 0.12), 5, (int)($W * 0.62), (int)($H * 0.62 + $bh / 2 + $W * 0.07), function ($L, $w, $h) use ($cta, $ink) {
        $fs = (int)($h * 0.42);
        pt_pill($L, px_upper($cta), (int)($w / 2), (int)($h / 2), $fs, px_font('Poppins-ExtraBold.ttf'), $ink, [255, 255, 255], 0.8, 0.35);
    });
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $ink, [255, 255, 255]);
    return pt_end($im, $srcs);
}

/** tpl_kraft_tag (DIY & Crafts) — photo, kraft-paper gift tag with string, handwritten + bold title. */
function pt_kraft_tag(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.4);
    $kraft = px_pal($pal, 0, [205, 168, 120]); $ink = [60, 38, 20];
    $fT = px_font('Poppins-ExtraBold.ttf'); $fH = px_font('Pacifico-Regular.ttf');
    $tw = (int)($W * 0.78);
    [$s, $lines, $lh] = px_fit($fT, px_upper($title), (int)($tw * 0.78), (int)($H * 0.24), (int)($W / 11), 4, 1.12);
    $th = count($lines) * $lh + (int)($W * 0.22);
    pt_rotated($im, $tw + 40, $th + 40, -4, (int)($W / 2), (int)($H * 0.3), function ($L, $w, $h) use ($s, $lines, $lh, $fT, $fH, $kraft, $ink, $cta) {
        $x1 = 20; $y1 = 20; $x2 = $w - 20; $y2 = $h - 20; $notch = (int)(($y2 - $y1) * 0.25);
        imagefilledpolygon($L, [$x1 + $notch, $y1, $x2, $y1, $x2, $y2, $x1 + $notch, $y2, $x1, $y2 - $notch, $x1, $y1 + $notch], px_col($L, $kraft));
        imagefilledellipse($L, $x1 + (int)($notch * 0.8), (int)(($y1 + $y2) / 2), 26, 26, px_col($L, [250, 246, 238]));
        imagesetthickness($L, 3);
        imageline($L, 0, (int)(($y1 + $y2) / 2) - 40, $x1 + (int)($notch * 0.8), (int)(($y1 + $y2) / 2), px_col($L, [245, 240, 230]));
        imagesetthickness($L, 1);
        $cx = (int)(($x1 + $notch + $x2) / 2);
        $k = $cta !== '' ? $cta : 'DIY idea';
        $ks = (int)($s * 0.7);
        [$ka] = px_metrics($ks, $fH);
        px_center_text($L, $ks, $fH, $k, $cx, $y1 + 18 + $ka, px_col($L, [255, 255, 255]));
        [$a] = px_metrics($s, $fT);
        $y = $y1 + 30 + (int)($ks * 1.3);
        foreach ($lines as $l) { px_center_text($L, $s, $fT, $l, $cx, $y + $a, px_col($L, $ink)); $y += $lh; }
    });
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $kraft, $ink);
    return pt_end($im, $srcs);
}

/** tpl_elegant_arch (Wedding) — photo, ivory arch window with script kicker, serif title, thin rule. */
function pt_elegant_arch(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.3);
    $ivory = [252, 248, 242]; $gold = px_pal($pal, 0, [180, 146, 90]); $ink = px_pal($pal, 1, [50, 44, 40]);
    $aw = (int)($W * 0.66); $ah = (int)($H * 0.42);
    $ax1 = (int)(($W - $aw) / 2); $ay2 = (int)($H * 0.93); $ay1 = $ay2 - $ah;
    $col = px_col($im, $ivory);
    imagefilledrectangle($im, $ax1, $ay1 + (int)($aw / 2), $ax1 + $aw, $ay2, $col);
    imagefilledellipse($im, (int)($W / 2), $ay1 + (int)($aw / 2), $aw, $aw, $col);
    imagesetthickness($im, 2);
    $in = (int)($W * 0.02);
    imagearc($im, (int)($W / 2), $ay1 + (int)($aw / 2), $aw - $in * 2, $aw - $in * 2, 180, 360, px_col($im, $gold));
    imageline($im, $ax1 + $in, $ay1 + (int)($aw / 2), $ax1 + $in, $ay2 - $in, px_col($im, $gold));
    imageline($im, $ax1 + $aw - $in, $ay1 + (int)($aw / 2), $ax1 + $aw - $in, $ay2 - $in, px_col($im, $gold));
    imageline($im, $ax1 + $in, $ay2 - $in, $ax1 + $aw - $in, $ay2 - $in, px_col($im, $gold));
    imagesetthickness($im, 1);
    $fS = px_font('GreatVibes-Regular.ttf'); $fT = px_font('AbrilFatface-Regular.ttf');
    $y = $ay1 + (int)($aw * 0.2);
    $k = $cta !== '' ? $cta : 'inspiration';
    $ks = (int)($W / 12);
    [$ka] = px_metrics($ks, $fS);
    px_center_text($im, $ks, $fS, $k, (int)($W / 2), $y + $ka, px_col($im, $gold));
    $y += (int)($ks * 1.3);
    $y = pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($aw * 0.78), $ay2 - $y - (int)($H * 0.08), (int)($W / 13), 4, px_col($im, $ink), 1.15);
    imageline($im, (int)($W * 0.42), $y + 14, (int)($W * 0.58), $y + 14, px_col($im, $gold));
    if ($website !== '') px_spaced_text($im, max(11, (int)($W / 48)), px_font('Poppins-Regular.ttf'), px_upper(pt_site($website)), (int)($W / 2), $ay2 - $in - (int)($W * 0.03), px_col($im, $ink), 3);
    return pt_end($im, $srcs);
}

/** tpl_ribbon_banner (Holidays) — photo, red ribbon banner with folded tails, title on a white card above. */
function pt_ribbon_banner(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.4);
    $red = px_pal($pal, 0, [190, 30, 45]); $dark = px_pal($pal, 1, [120, 16, 28]); $green = px_pal($pal, 2, [24, 90, 60]);
    $fR = px_font('TitanOne-Regular.ttf'); $fT = px_font('AbrilFatface-Regular.ttf');
    [$num, $lead, $main, $tail] = pt_parts($title);
    $ribbonText = $cta !== '' ? $cta : ($num !== null ? $num . ' Ideas' : 'Holiday Ideas');
    $cardText = trim(($lead !== '' ? $lead . ' ' : '') . $main . ($tail !== '' ? ' ' . $tail : ''));
    $cw = (int)($W * 0.84); $cx1 = (int)(($W - $cw) / 2);
    $th = pt_lines_height($fT, $cardText, (int)($cw * 0.86), (int)($H * 0.22), (int)($W / 11), 4, 1.12);
    $cy1 = (int)($H * 0.08); $ch = $th + (int)($W * 0.16);
    imagefilledrectangle($im, $cx1, $cy1, $cx1 + $cw, $cy1 + $ch, px_col($im, [255, 253, 248]));
    imagesetthickness($im, 3);
    imagerectangle($im, $cx1 + 10, $cy1 + 10, $cx1 + $cw - 10, $cy1 + $ch - 10, px_col($im, $green));
    imagesetthickness($im, 1);
    pt_lines_center($im, $fT, $cardText, (int)($W / 2), $cy1 + (int)($W * 0.05), (int)($cw * 0.86), (int)($H * 0.22), (int)($W / 11), 4, px_col($im, $green), 1.12);
    // ribbon across the card's bottom edge
    $ry = $cy1 + $ch - (int)($W * 0.02); $rh = (int)($W * 0.12); $rw = (int)($W * 0.74); $rx1 = (int)(($W - $rw) / 2);
    $t = (int)($rh * 0.7);
    imagefilledpolygon($im, [$rx1 - $t, $ry + (int)($rh * 0.25), $rx1 + 10, $ry + (int)($rh * 0.25), $rx1 + 10, $ry + $rh + (int)($rh * 0.25), $rx1 - $t, $ry + $rh + (int)($rh * 0.25), $rx1 - (int)($t * 0.55), $ry + (int)($rh * 0.75)], px_col($im, $dark));
    imagefilledpolygon($im, [$rx1 + $rw + $t, $ry + (int)($rh * 0.25), $rx1 + $rw - 10, $ry + (int)($rh * 0.25), $rx1 + $rw - 10, $ry + $rh + (int)($rh * 0.25), $rx1 + $rw + $t, $ry + $rh + (int)($rh * 0.25), $rx1 + $rw + (int)($t * 0.55), $ry + (int)($rh * 0.75)], px_col($im, $dark));
    imagefilledrectangle($im, $rx1, $ry, $rx1 + $rw, $ry + $rh, px_col($im, $red));
    [$rs, $rl] = px_fit($fR, px_upper($ribbonText), (int)($rw * 0.86), (int)($rh * 0.7), (int)($rh * 0.55), 1, 1.0);
    [$ra, $rd] = px_metrics($rs, $fR);
    px_center_text($im, $rs, $fR, $rl[0], (int)($W / 2), $ry + (int)(($rh + $ra - $rd) / 2), px_col($im, [255, 255, 255]));
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $red, [255, 255, 255]);
    return pt_end($im, $srcs);
}

/** tpl_top_title_bar (Lifestyle) — solid colour title block on top, photo below, small script kicker, website under the title. */
function pt_top_title_bar(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bg = px_pal($pal, 0, [34, 52, 48]);
    $b = pt_begin($imgs, $sizeKey, $bg); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $fT = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('Satisfy-Regular.ttf');
    $accent = px_pal($pal, 1, [236, 196, 140]);
    $kick = $cta !== '' ? $cta : '';
    $th = pt_lines_height($fT, $title, (int)($W * 0.86), (int)($H * 0.25), (int)($W / 10), 4, 1.1);
    $barH = $th + (int)($W * 0.12) + ($kick !== '' ? (int)($W / 11) : 0) + ($website !== '' ? (int)($W / 22) : 0);
    pt_photo($im, $srcs, 0, 0, $barH, $W, $H - $barH, 0.25);
    $y = (int)($W * 0.05);
    if ($kick !== '') { $ks = (int)($W / 14); [$ka] = px_metrics($ks, $fS); px_center_text($im, $ks, $fS, $kick, (int)($W / 2), $y + $ka, px_col($im, $accent)); $y += (int)($W / 11); }
    $y = pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($W * 0.86), (int)($H * 0.25), (int)($W / 10), 4, px_col($im, [255, 255, 255]), 1.1);
    if ($website !== '') px_spaced_text($im, max(11, (int)($W / 45)), px_font('Poppins-Regular.ttf'), px_upper(pt_site($website)), (int)($W / 2), $y + (int)($W / 40), px_col($im, $accent), 3);
    return pt_end($im, $srcs);
}

/** tpl_sticky_note (Tips & Business) — photo, tilted yellow sticky note with tape and handwritten-bold title. */
function pt_sticky_note(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.35);
    $note = px_pal($pal, 0, [255, 232, 120]); $ink = [30, 30, 30];
    $f = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('Pacifico-Regular.ttf');
    $nw = (int)($W * 0.72);
    [$s, $lines, $lh] = px_fit($f, $title, (int)($nw * 0.84), (int)($nw * 0.62), (int)($W / 11), 5, 1.12);
    $nh = max((int)($nw * 0.9), count($lines) * $lh + (int)($nw * 0.35));
    pt_rotated($im, $nw + 40, $nh + 60, 5, (int)($W / 2), (int)($H * 0.42), function ($L, $w, $h) use ($s, $lines, $lh, $f, $fS, $note, $ink, $cta) {
        imagefilledrectangle($L, 26, 36, $w - 14, $h - 14, px_col($L, [0, 0, 0], 95));
        imagefilledrectangle($L, 20, 30, $w - 20, $h - 20, px_col($L, $note));
        imagefilledrectangle($L, (int)($w / 2 - 70), 12, (int)($w / 2 + 70), 52, px_col($L, [240, 240, 240], 30));
        $y = 30 + (int)(($h - 50 - count($lines) * $lh - ($cta !== '' ? $s * 1.4 : 0)) / 2);
        [$a] = px_metrics($s, $f);
        foreach ($lines as $l) { px_center_text($L, $s, $f, $l, (int)($w / 2), $y + $a, px_col($L, $ink)); $y += $lh; }
        if ($cta !== '') { $cs = (int)($s * 0.75); [$ca] = px_metrics($cs, $fS); px_center_text($L, $cs, $fS, $cta, (int)($w / 2), $y + (int)($s * 0.3) + $ca, px_col($L, [200, 60, 40])); }
    });
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $ink, $note);
    return pt_end($im, $srcs);
}

/** tpl_corner_card (Garden) — photo, green card from the bottom-left corner with leaf accents and bold title. */
function pt_corner_card(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.4);
    $green = px_pal($pal, 0, [46, 84, 52]); $cream = px_pal($pal, 1, [240, 232, 206]);
    $f = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('Satisfy-Regular.ttf');
    $cw = (int)($W * 0.84);
    $th = pt_lines_height($f, $title, (int)($cw * 0.84), (int)($H * 0.26), (int)($W / 10.5), 4, 1.1);
    $ch = $th + (int)($W * 0.14) + ($cta !== '' ? (int)($W / 11) : 0) + ($website !== '' ? (int)($W / 18) : 0);
    $y1 = $H - $ch - (int)($H * 0.03);
    px_rrect($im, -40, $y1, $cw, $H - (int)($H * 0.03), (int)($W * 0.07), px_col($im, $green));
    // leaves
    foreach ([[0.78, -0.02, 0.12], [0.86, 0.03, 0.08]] as [$lx, $ly, $lr]) {
        pt_rotated($im, (int)($W * $lr * 2), (int)($W * $lr), -30, (int)($W * $lx), $y1 + (int)($W * $ly), function ($L, $w, $h) use ($cream) {
            imagefilledellipse($L, (int)($w / 2), (int)($h / 2), $w - 4, $h - 4, px_col($L, $cream));
            imageline($L, 6, (int)($h / 2), $w - 6, (int)($h / 2), px_col($L, [46, 84, 52]));
        });
    }
    $y = $y1 + (int)($W * 0.06);
    if ($cta !== '') { $ks = (int)($W / 15); [$ka] = px_metrics($ks, $fS); imagettftext($im, $ks, 0, (int)($W * 0.07), $y + $ka, px_col($im, $cream), $fS, $cta); $y += (int)($W / 11); }
    [$s, $lines, $lh] = px_fit($f, $title, (int)($cw * 0.84), (int)($H * 0.26), (int)($W / 10.5), 4, 1.1);
    [$a] = px_metrics($s, $f);
    foreach ($lines as $l) { imagettftext($im, $s, 0, (int)($W * 0.07), $y + $a, px_col($im, [255, 255, 255]), $f, $l); $y += $lh; }
    if ($website !== '') imagettftext($im, max(11, (int)($W / 40)), 0, (int)($W * 0.07), $y + (int)($W / 30), px_col($im, $cream), px_font('Poppins-Bold.ttf'), pt_site($website));
    return pt_end($im, $srcs);
}

/** tpl_bubble_badge (Kids & Parenting) — photo, playful colour bubble with chunky title and confetti dots. */
function pt_bubble_badge(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    pt_photo($im, $srcs, 0, 0, 0, $W, $H, 0.35);
    $c1 = px_pal($pal, 0, [255, 112, 67]); $c2 = px_pal($pal, 1, [66, 165, 245]); $c3 = px_pal($pal, 2, [255, 202, 40]);
    $f = px_font('LuckiestGuy-Regular.ttf');
    $d = (int)($W * 0.8);
    $cx = (int)($W / 2); $cy = (int)($H * 0.34);
    imagefilledellipse($im, $cx + 12, $cy + 14, $d, (int)($d * 0.82), px_col($im, [0, 0, 0], 95));
    imagefilledellipse($im, $cx, $cy, $d, (int)($d * 0.82), px_col($im, [255, 255, 255]));
    imagefilledellipse($im, $cx, $cy, $d - 24, (int)($d * 0.82) - 24, px_col($im, $c1));
    foreach ([[0.2, -0.3, $c2], [0.85, -0.2, $c3], [0.12, 0.35, $c3], [0.9, 0.38, $c2], [0.5, -0.46, $c3]] as [$fx, $fy, $c]) {
        imagefilledellipse($im, (int)($W * $fx), $cy + (int)($d * $fy), (int)($W * 0.05), (int)($W * 0.05), px_col($im, $c));
    }
    [$s, $lines, $lh] = px_fit($f, px_upper($title), (int)($d * 0.72), (int)($d * 0.52), (int)($W / 10), 4, 1.05);
    [$a] = px_metrics($s, $f);
    $y = $cy - (int)(count($lines) * $lh / 2) - (int)($s * 0.08);
    foreach ($lines as $l) { px_center_text($im, $s, $f, $l, $cx, $y + $a, px_col($im, [255, 255, 255]), px_col($im, [60, 30, 20]), 4); $y += $lh; }
    if ($cta !== '') pt_pill($im, px_upper($cta), $cx, $cy + (int)($d * 0.41), (int)($W / 26), $f, $c2, [255, 255, 255]);
    if ($website !== '') pt_pill($im, pt_site($website), $cx, $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), [255, 255, 255], $c1);
    return pt_end($im, $srcs);
}

/* ============================== 10 collage templates ============================== */

/** tpl_polaroid_scatter (Travel) — coloured background, 3 tilted polaroids, title card. */
function pt_polaroid_scatter(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bg = px_pal($pal, 0, [233, 223, 206]);
    $b = pt_begin($imgs, $sizeKey, $bg); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $ink = px_pal($pal, 1, [40, 56, 70]);
    $pw = (int)($W * 0.52); $ph = (int)($pw * 1.12);
    $spots = [[0.3, 0.2, -7], [0.72, 0.28, 6], [0.4, 0.8, 4], [0.76, 0.84, -5]];
    foreach ($spots as $i => [$fx, $fy, $deg]) {
        if ($i === 3 && $H / $W < 1.4) continue;
        $cell = pt_cell($srcs, $i, $pw - 30, $ph - 30 - (int)($pw * 0.14), 0.3);
        pt_rotated($im, $pw + 10, $ph + 10, $deg, (int)($W * $fx), (int)($H * $fy), function ($L, $w, $h) use ($cell, $pw) {
            imagefilledrectangle($L, 9, 11, $w - 1, $h - 1, px_col($L, [0, 0, 0], 100));
            imagefilledrectangle($L, 5, 5, $w - 6, $h - 6, px_col($L, [255, 255, 255]));
            imagecopy($L, $cell, 20, 20, 0, 0, imagesx($cell), imagesy($cell));
        });
        imagedestroy($cell);
    }
    $fT = px_font('AbrilFatface-Regular.ttf'); $fS = px_font('Satisfy-Regular.ttf');
    $cw = (int)($W * 0.86);
    $th = pt_lines_height($fT, $title, (int)($cw * 0.86), (int)($H * 0.2), (int)($W / 11), 3, 1.1);
    $ch = $th + (int)($W * 0.12) + ($cta !== '' ? (int)($W / 12) : 0);
    $cy1 = (int)($H * 0.5 - $ch / 2);
    imagefilledrectangle($im, (int)(($W - $cw) / 2), $cy1, (int)(($W + $cw) / 2), $cy1 + $ch, px_col($im, $ink));
    $y = $cy1 + (int)($W * 0.05);
    if ($cta !== '') { $ks = (int)($W / 15); [$ka] = px_metrics($ks, $fS); px_center_text($im, $ks, $fS, $cta, (int)($W / 2), $y + $ka, px_col($im, $bg)); $y += (int)($W / 12); }
    pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($cw * 0.86), (int)($H * 0.2), (int)($W / 11), 3, px_col($im, [255, 255, 255]), 1.1);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.035), (int)($W / 32), px_font('Poppins-Bold.ttf'), $ink, [255, 255, 255]);
    return pt_end($im, $srcs);
}

/** tpl_three_strips (Beauty & Hair) — three vertical photo strips, centred white pill title with pink outline. */
function pt_three_strips(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $pink = px_pal($pal, 0, [214, 70, 120]); $ink = px_pal($pal, 1, [40, 20, 30]);
    $g = (int)($W * 0.01); $sw = (int)(($W - 2 * $g) / 3);
    for ($i = 0; $i < 3; $i++) pt_photo($im, $srcs, $i, $i * ($sw + $g), 0, $i === 2 ? $W - 2 * ($sw + $g) : $sw, $H, 0.25);
    $f = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('GreatVibes-Regular.ttf');
    $cw = (int)($W * 0.84);
    $th = pt_lines_height($f, px_upper($title), (int)($cw * 0.84), (int)($H * 0.24), (int)($W / 11), 4, 1.1);
    $ch = $th + (int)($W * 0.12) + ($cta !== '' ? (int)($W / 10) : 0);
    $y1 = (int)($H * 0.5 - $ch / 2);
    px_rrect($im, (int)(($W - $cw) / 2) - 6, $y1 - 6, (int)(($W + $cw) / 2) + 6, $y1 + $ch + 6, (int)($W * 0.07), px_col($im, $pink));
    px_rrect($im, (int)(($W - $cw) / 2), $y1, (int)(($W + $cw) / 2), $y1 + $ch, (int)($W * 0.065), px_col($im, [255, 255, 255]));
    $y = $y1 + (int)($W * 0.05);
    if ($cta !== '') { $ks = (int)($W / 11); [$ka] = px_metrics($ks, $fS); px_center_text($im, $ks, $fS, $cta, (int)($W / 2), $y + $ka, px_col($im, $pink)); $y += (int)($W / 10); }
    pt_lines_center($im, $f, px_upper($title), (int)($W / 2), $y, (int)($cw * 0.84), (int)($H * 0.24), (int)($W / 11), 4, px_col($im, $ink), 1.1);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $pink, [255, 255, 255]);
    return pt_end($im, $srcs);
}

/** tpl_slant_split (Fitness) — two photos split by a slanted dark band with neon title. */
function pt_slant_split(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $dark = px_pal($pal, 1, [18, 18, 22]); $neon = px_pal($pal, 0, [190, 255, 60]);
    pt_photo($im, $srcs, 0, 0, 0, $W, (int)($H * 0.55), 0.3);
    pt_photo($im, $srcs, 1, 0, (int)($H * 0.45), $W, $H - (int)($H * 0.45), 0.3);
    $f = px_font('Anton-Regular.ttf');
    [$s, $lines, $lh] = px_fit($f, px_upper($title), (int)($W * 0.8), (int)($H * 0.24), (int)($W / 9), 3, 1.2);
    $bh = count($lines) * $lh + (int)($W * 0.2) + ($cta !== '' ? (int)($W / 13) : 0);
    $mid = (int)($H * 0.5); $sk = (int)($W * 0.09);
    imagefilledpolygon($im, [0, $mid - (int)($bh / 2) + $sk, $W, $mid - (int)($bh / 2) - $sk, $W, $mid + (int)($bh / 2) - $sk, 0, $mid + (int)($bh / 2) + $sk], px_col($im, $dark));
    imagesetthickness($im, 6);
    imageline($im, 0, $mid - (int)($bh / 2) + $sk, $W, $mid - (int)($bh / 2) - $sk, px_col($im, $neon));
    imageline($im, 0, $mid + (int)($bh / 2) + $sk, $W, $mid + (int)($bh / 2) - $sk, px_col($im, $neon));
    imagesetthickness($im, 1);
    [$a] = px_metrics($s, $f);
    $y = $mid - (int)(($bh - (int)($W * 0.2)) / 2) + (int)(($lh - $a) / 2);
    foreach ($lines as $i => $l) { px_center_text($im, $s, $f, $l, (int)($W / 2), $y + $a, px_col($im, $i === 0 ? $neon : [255, 255, 255])); $y += $lh; }
    if ($cta !== '') { $cs = (int)($W / 26); [$ca] = px_metrics($cs, px_font('Poppins-Bold.ttf')); px_spaced_text($im, $cs, px_font('Poppins-Bold.ttf'), px_upper($cta), (int)($W / 2), $y + $ca, px_col($im, $neon), 4); }
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.04), (int)($W / 32), px_font('Poppins-Bold.ttf'), $dark, $neon);
    return pt_end($im, $srcs);
}

/** tpl_framed_trio (Wedding) — ivory page, one tall + two stacked framed photos, serif title and script kicker. */
function pt_framed_trio(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bgRgb = [250, 246, 240];
    $b = pt_begin($imgs, $sizeKey, $bgRgb); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $gold = px_pal($pal, 0, [184, 150, 96]); $ink = px_pal($pal, 1, [46, 40, 36]);
    $fT = px_font('AbrilFatface-Regular.ttf'); $fS = px_font('GreatVibes-Regular.ttf');
    $m = (int)($W * 0.06);
    $textH = (int)($H * 0.3);
    $y = (int)($W * 0.05);
    $k = $cta !== '' ? $cta : 'beautiful ideas';
    $ks = (int)($W / 11); [$ka] = px_metrics($ks, $fS);
    px_center_text($im, $ks, $fS, $k, (int)($W / 2), $y + $ka, px_col($im, $gold));
    $y += (int)($ks * 1.2);
    $y = pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($W * 0.86), $textH - $y, (int)($W / 11), 3, px_col($im, $ink), 1.12);
    $top = max($y + (int)($W * 0.04), $textH);
    $bot = $H - ($website !== '' ? (int)($H * 0.07) : $m);
    $g = (int)($W * 0.03);
    $lw = (int)(($W - 2 * $m - $g) * 0.56);
    $frames = [[$m, $top, $lw, $bot - $top], [$m + $lw + $g, $top, $W - 2 * $m - $lw - $g, (int)(($bot - $top - $g) / 2)], [$m + $lw + $g, $top + (int)(($bot - $top - $g) / 2) + $g, $W - 2 * $m - $lw - $g, $bot - $top - (int)(($bot - $top - $g) / 2) - $g]];
    foreach ($frames as $i => [$x, $yy, $w, $h]) {
        imagefilledrectangle($im, $x - 8, $yy - 8, $x + $w + 8, $yy + $h + 8, px_col($im, [255, 255, 255]));
        imagerectangle($im, $x - 8, $yy - 8, $x + $w + 8, $yy + $h + 8, px_col($im, $gold));
        pt_photo($im, $srcs, $i, $x, $yy, $w, $h, 0.25);
    }
    if ($website !== '') px_spaced_text($im, max(11, (int)($W / 44)), px_font('Poppins-Regular.ttf'), px_upper(pt_site($website)), (int)($W / 2), $H - (int)($H * 0.025), px_col($im, $ink), 4);
    return pt_end($im, $srcs);
}

/** tpl_hero_row (Food & Recipes) — big hero photo, bold title band, row of three photos below. */
function pt_hero_row(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $band = px_pal($pal, 0, [200, 60, 30]); $cream = px_pal($pal, 1, [255, 244, 225]);
    $f = px_font('ArchivoBlack-Regular.ttf'); $fS = px_font('Pacifico-Regular.ttf');
    $heroH = (int)($H * 0.5);
    pt_photo($im, $srcs, 0, 0, 0, $W, $heroH, 0.4);
    [$s, $lines, $lh] = px_fit($f, px_upper($title), (int)($W * 0.9), (int)($H * 0.2), (int)($W / 10), 3, 1.08);
    $bh = count($lines) * $lh + (int)($W * 0.08) + ($cta !== '' ? (int)($W / 12) : 0);
    imagefilledrectangle($im, 0, $heroH, $W, $heroH + $bh, px_col($im, $band));
    $y = $heroH + (int)($W * 0.04);
    if ($cta !== '') { $cs = (int)($W / 16); [$ca] = px_metrics($cs, $fS); px_center_text($im, $cs, $fS, $cta, (int)($W / 2), $y + $ca, px_col($im, $cream)); $y += (int)($W / 12); }
    [$a] = px_metrics($s, $f);
    foreach ($lines as $l) { px_center_text($im, $s, $f, $l, (int)($W / 2), $y + $a, px_col($im, [255, 255, 255])); $y += $lh; }
    $ry = $heroH + $bh; $g = (int)($W * 0.008); $cw = (int)(($W - 2 * $g) / 3);
    for ($i = 0; $i < 3; $i++) pt_photo($im, $srcs, 1 + $i, $i * ($cw + $g), $ry + $g, $i === 2 ? $W - 2 * ($cw + $g) : $cw, $H - $ry - $g, 0.45);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.035), (int)($W / 32), px_font('Poppins-Bold.ttf'), $cream, $band);
    return pt_end($im, $srcs);
}

/** tpl_mosaic (Home Decor) — one big photo left + two stacked right, clean white title bar at the bottom. */
function pt_mosaic(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $ink = px_pal($pal, 0, [44, 44, 40]); $accent = px_pal($pal, 1, [176, 140, 100]);
    $fT = px_font('AbrilFatface-Regular.ttf');
    $th = pt_lines_height($fT, $title, (int)($W * 0.86), (int)($H * 0.2), (int)($W / 11), 3, 1.1);
    $barH = $th + (int)($W * 0.1) + ($cta !== '' ? (int)($W / 16) : 0) + ($website !== '' ? (int)($W / 22) : 0);
    $g = (int)($W * 0.01);
    $ph = $H - $barH;
    $lw = (int)($W * 0.58);
    pt_photo($im, $srcs, 0, 0, 0, $lw, $ph, 0.35);
    pt_photo($im, $srcs, 1, $lw + $g, 0, $W - $lw - $g, (int)(($ph - $g) / 2), 0.35);
    pt_photo($im, $srcs, 2, $lw + $g, (int)(($ph - $g) / 2) + $g, $W - $lw - $g, $ph - (int)(($ph - $g) / 2) - $g, 0.35);
    $y = $ph + (int)($W * 0.04);
    if ($cta !== '') { $cs = (int)($W / 30); [$ca] = px_metrics($cs, px_font('Poppins-Bold.ttf')); px_spaced_text($im, $cs, px_font('Poppins-Bold.ttf'), px_upper($cta), (int)($W / 2), $y + $ca, px_col($im, $accent), 4); $y += (int)($W / 16); }
    $y = pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($W * 0.86), (int)($H * 0.2), (int)($W / 11), 3, px_col($im, $ink), 1.1);
    if ($website !== '') px_center_text($im, max(11, (int)($W / 42)), px_font('Poppins-Regular.ttf'), pt_site($website), (int)($W / 2), $y + (int)($W / 40), px_col($im, $accent));
    return pt_end($im, $srcs);
}

/** tpl_kraft_board (DIY & Crafts) — kraft background, 2×2 taped photos, title on a white torn-paper strip. */
function pt_kraft_board(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bg = px_pal($pal, 0, [196, 160, 116]);
    $b = pt_begin($imgs, $sizeKey, $bg); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $ink = px_pal($pal, 1, [52, 36, 24]);
    // subtle paper grain (does not touch photos — drawn before them)
    mt_srand(11);
    for ($i = 0; $i < 2500; $i++) imagesetpixel($im, mt_rand(0, $W - 1), mt_rand(0, $H - 1), px_col($im, [170 + mt_rand(0, 30), 136 + mt_rand(0, 25), 96 + mt_rand(0, 20)]));
    mt_srand();
    $cw = (int)($W * 0.42); $chh = (int)($H * 0.3);
    $pos = [[0.27, 0.19, -3], [0.73, 0.2, 4], [0.27, 0.8, 3], [0.73, 0.81, -4]];
    foreach ($pos as $i => [$fx, $fy, $deg]) {
        $cell = pt_cell($srcs, $i, $cw, $chh, 0.35);
        pt_rotated($im, $cw + 30, $chh + 30, $deg, (int)($W * $fx), (int)($H * $fy), function ($L, $w, $h) use ($cell, $cw, $chh) {
            imagefilledrectangle($L, 5, 5, $w - 5, $h - 5, px_col($L, [255, 255, 255]));
            imagecopy($L, $cell, 15, 15, 0, 0, $cw, $chh);
            imagefilledrectangle($L, (int)($w / 2 - 40), 0, (int)($w / 2 + 40), 26, px_col($L, [240, 228, 190], 25));
        });
        imagedestroy($cell);
    }
    $f = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('Pacifico-Regular.ttf');
    $th = pt_lines_height($f, px_upper($title), (int)($W * 0.84), (int)($H * 0.2), (int)($W / 12), 3, 1.1);
    $sh = $th + (int)($W * 0.1) + ($cta !== '' ? (int)($W / 12) : 0);
    $y1 = (int)($H * 0.5 - $sh / 2);
    px_brush_box($im, (int)($W * 0.04), $y1, (int)($W * 0.96), $y1 + $sh, px_col($im, [255, 253, 248]), 5);
    $y = $y1 + (int)($W * 0.045);
    if ($cta !== '') { $cs = (int)($W / 16); [$ca] = px_metrics($cs, $fS); px_center_text($im, $cs, $fS, $cta, (int)($W / 2), $y + $ca, px_col($im, [200, 90, 50])); $y += (int)($W / 12); }
    pt_lines_center($im, $f, px_upper($title), (int)($W / 2), $y, (int)($W * 0.84), (int)($H * 0.2), (int)($W / 12), 3, px_col($im, $ink), 1.1);
    if ($website !== '') pt_pill($im, pt_site($website), (int)($W / 2), $H - (int)($H * 0.03), (int)($W / 34), px_font('Poppins-Bold.ttf'), $ink, [255, 255, 255]);
    return pt_end($im, $srcs);
}

/** tpl_circle_trio (Holidays) — coloured page, three circle photos, festive title with sparkles. */
function pt_circle_trio(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bgRgb = px_pal($pal, 0, [22, 70, 52]);
    $b = pt_begin($imgs, $sizeKey, $bgRgb); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $gold = px_pal($pal, 1, [232, 190, 100]); $white = [255, 255, 255];
    $d1 = (int)($W * 0.62); $d2 = (int)($W * 0.42);
    $ring = function ($cx, $cy, $d) use ($im, $gold) { for ($t = 0; $t < 9; $t++) imageellipse($im, $cx, $cy, $d + $t * 2, $d + $t * 2, px_col($im, $gold)); };
    pt_photo_round($im, $srcs, 0, (int)($W / 2 - $d1 / 2), (int)($H * 0.26 - $d1 / 2), $d1, $d1, $d1, $bgRgb, 0.35);
    $ring((int)($W / 2), (int)($H * 0.26), $d1);
    foreach ([[0.26, 0.8, 1], [0.74, 0.8, 2]] as [$fx, $fy, $i]) {
        pt_photo_round($im, $srcs, $i, (int)($W * $fx - $d2 / 2), (int)($H * $fy - $d2 / 2), $d2, $d2, $d2, $bgRgb, 0.35);
        $ring((int)($W * $fx), (int)($H * $fy), $d2);
    }
    $fT = px_font('AbrilFatface-Regular.ttf'); $fS = px_font('GreatVibes-Regular.ttf');
    $top = (int)($H * 0.26 + $d1 / 2 + $H * 0.025);
    $bottom = (int)($H * 0.8 - $d2 / 2 - $H * 0.02);
    $y = $top;
    if ($cta !== '') { $cs = (int)($W / 12); [$ca] = px_metrics($cs, $fS); px_center_text($im, $cs, $fS, $cta, (int)($W / 2), $y + $ca, px_col($im, $gold)); $y += (int)($cs * 1.1); }
    pt_lines_center($im, $fT, $title, (int)($W / 2), $y, (int)($W * 0.88), $bottom - $y, (int)($W / 11), 3, px_col($im, $white), 1.08);
    foreach ([[0.08, 0.1, 0.03], [0.92, 0.14, 0.025], [0.1, 0.56, 0.02], [0.9, 0.5, 0.03]] as [$fx, $fy, $fr]) pt_sparkle($im, (int)($W * $fx), (int)($H * $fy), (int)($W * $fr), px_col($im, $gold));
    if ($website !== '') px_center_text($im, max(11, (int)($W / 40)), px_font('Poppins-Bold.ttf'), pt_site($website), (int)($W / 2), $H - (int)($H * 0.02), px_col($im, $gold));
    return pt_end($im, $srcs);
}

/** tpl_film_strip (Fashion) — black film strip with sprocket holes down the left, stacked frames, title panel right. */
function pt_film_strip(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $bgRgb = px_pal($pal, 0, [240, 232, 222]);
    $b = pt_begin($imgs, $sizeKey, $bgRgb); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $ink = px_pal($pal, 1, [20, 20, 20]); $accent = px_pal($pal, 2, [186, 80, 60]);
    $sw = (int)($W * 0.56); $sx = (int)($W * 0.05);
    imagefilledrectangle($im, $sx, 0, $sx + $sw, $H, px_col($im, [16, 16, 16]));
    $hole = (int)($sw * 0.05);
    for ($y = 12; $y < $H; $y += (int)($hole * 2.2)) {
        px_rrect($im, $sx + (int)($hole * 0.5), $y, $sx + (int)($hole * 1.5), $y + $hole, 3, px_col($im, $bgRgb));
        px_rrect($im, $sx + $sw - (int)($hole * 1.5), $y, $sx + $sw - (int)($hole * 0.5), $y + $hole, 3, px_col($im, $bgRgb));
    }
    $fx = $sx + (int)($hole * 2.2); $fw = $sw - (int)($hole * 4.4);
    $n = 3; $g = (int)($H * 0.02); $fh = (int)(($H - ($n + 1) * $g) / $n);
    for ($i = 0; $i < $n; $i++) pt_photo($im, $srcs, $i, $fx, $g + $i * ($fh + $g), $fw, $fh, 0.2);
    $px = $sx + $sw + (int)($W * 0.04); $pw = $W - $px - (int)($W * 0.04);
    $cx = $px + (int)($pw / 2);
    $f = px_font('Anton-Regular.ttf'); $fS = px_font('Satisfy-Regular.ttf');
    [$s, $lines, $lh] = px_fit($f, px_upper($title), $pw, (int)($H * 0.6), (int)($W / 9), 9, 1.05);
    $block = count($lines) * $lh + ($cta !== '' ? (int)($W / 10) : 0);
    $y = (int)(($H - $block) / 2);
    if ($cta !== '') {
        [$cs, $cl] = px_fit($fS, $cta, $pw, (int)($W / 10), (int)($W / 17), 2, 1.1);
        [$ca] = px_metrics($cs, $fS);
        foreach ($cl as $c) { px_center_text($im, $cs, $fS, $c, $cx, $y + $ca, px_col($im, $accent)); $y += (int)($cs * 1.25); }
        $y += (int)($W * 0.02);
    }
    [$a] = px_metrics($s, $f);
    foreach ($lines as $l) { px_center_text($im, $s, $f, $l, $cx, $y + $a, px_col($im, $ink)); $y += $lh; }
    if ($website !== '') {
        $fs = max(11, (int)($W / 46));
        $name = pt_site($website);
        while (px_text_w($fs, px_font('Poppins-Bold.ttf'), $name) > $pw && $fs > 9) $fs--;
        px_center_text($im, $fs, px_font('Poppins-Bold.ttf'), $name, $cx, $H - (int)($H * 0.03), px_col($im, $accent));
    }
    return pt_end($im, $srcs);
}

/** tpl_nine_grid (Lifestyle) — 3×3 photo grid with the centre cell replaced by a solid title tile. */
function pt_nine_grid(array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $b = pt_begin($imgs, $sizeKey); if (!$b) return null; [$im, $srcs, $W, $H] = $b;
    $tile = px_pal($pal, 0, [230, 90, 70]); $ink = [255, 255, 255];
    $g = (int)($W * 0.008);
    $cw = (int)(($W - 2 * $g) / 3); $ch = (int)(($H - 2 * $g) / 3);
    $k = 0;
    for ($r = 0; $r < 3; $r++) for ($c = 0; $c < 3; $c++) {
        $x = $c * ($cw + $g); $y = $r * ($ch + $g);
        $w = $c === 2 ? $W - $x : $cw; $h = $r === 2 ? $H - $y : $ch;
        if ($r === 1 && $c === 1) continue;
        pt_photo($im, $srcs, $k++, $x, $y, $w, $h, 0.3);
    }
    // centre title tile spans a little wider than the middle cell for readable text
    $tx1 = (int)($cw * 0.55); $tx2 = $W - (int)($cw * 0.55);
    $ty1 = $ch + $g - (int)($ch * 0.15); $ty2 = 2 * $ch + $g + (int)($ch * 0.15);
    imagefilledrectangle($im, $tx1 - 8, $ty1 - 8, $tx2 + 8, $ty2 + 8, px_col($im, [255, 255, 255]));
    imagefilledrectangle($im, $tx1, $ty1, $tx2, $ty2, px_col($im, $tile));
    $f = px_font('Poppins-ExtraBold.ttf'); $fS = px_font('Satisfy-Regular.ttf');
    $boxW = $tx2 - $tx1 - (int)($W * 0.06);
    $top = $ty1 + (int)($W * 0.035);
    if ($cta !== '') { $cs = (int)($W / 18); [$ca] = px_metrics($cs, $fS); px_center_text($im, $cs, $fS, $cta, (int)($W / 2), $top + $ca, px_col($im, $ink)); $top += (int)($W / 13); }
    $bottom = $ty2 - (int)($W * 0.03) - ($website !== '' ? (int)($W / 22) : 0);
    $hh = pt_lines_height($f, px_upper($title), $boxW, $bottom - $top, (int)($W / 12), 5, 1.08);
    pt_lines_center($im, $f, px_upper($title), (int)($W / 2), $top + (int)(($bottom - $top - $hh) / 2), $boxW, $bottom - $top, (int)($W / 12), 5, px_col($im, $ink), 1.08);
    if ($website !== '') px_center_text($im, max(11, (int)($W / 44)), px_font('Poppins-Bold.ttf'), pt_site($website), (int)($W / 2), $ty2 - (int)($W * 0.03), px_col($im, $ink));
    return pt_end($im, $srcs);
}

/** Dispatcher used by compose_pin_image(): returns null when $style isn't one of these templates. */
function pt_render(string $style, array $imgs, string $title, string $website, string $cta, string $sizeKey, ?array $pal): ?string
{
    $map = [
        'tpl_label_stack' => 'pt_label_stack', 'tpl_highlight_lines' => 'pt_highlight_lines', 'tpl_cream_band' => 'pt_cream_band',
        'tpl_side_stack' => 'pt_side_stack', 'tpl_top_panel' => 'pt_top_panel', 'tpl_half_circle' => 'pt_half_circle',
        'tpl_rounded_tiles' => 'pt_rounded_tiles', 'tpl_magazine_band' => 'pt_magazine_band',
        'tpl_postcard' => 'pt_postcard', 'tpl_soft_pills' => 'pt_soft_pills', 'tpl_diagonal_band' => 'pt_diagonal_band',
        'tpl_kraft_tag' => 'pt_kraft_tag', 'tpl_elegant_arch' => 'pt_elegant_arch', 'tpl_ribbon_banner' => 'pt_ribbon_banner',
        'tpl_top_title_bar' => 'pt_top_title_bar', 'tpl_sticky_note' => 'pt_sticky_note', 'tpl_corner_card' => 'pt_corner_card',
        'tpl_bubble_badge' => 'pt_bubble_badge',
        'tpl_polaroid_scatter' => 'pt_polaroid_scatter', 'tpl_three_strips' => 'pt_three_strips', 'tpl_slant_split' => 'pt_slant_split',
        'tpl_framed_trio' => 'pt_framed_trio', 'tpl_hero_row' => 'pt_hero_row', 'tpl_mosaic' => 'pt_mosaic',
        'tpl_kraft_board' => 'pt_kraft_board', 'tpl_circle_trio' => 'pt_circle_trio', 'tpl_film_strip' => 'pt_film_strip',
        'tpl_nine_grid' => 'pt_nine_grid',
    ];
    if (!isset($map[$style])) return null;
    return ($map[$style])($imgs, $title, $website, trim($cta), $sizeKey, $pal);
}
