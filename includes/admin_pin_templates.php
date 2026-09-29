<?php
/**
 * Admin-made pin templates (Admin → Canva → Create New Template) and the on/off switch for the
 * built-in "Pin Templates & Styles" (Admin → Canva → All Image Style Templates).
 *
 * An admin template is designed in the same Canva-style editor users have. On Publish the browser
 * exports what the server needs to draw it with GD (no browser needed afterwards):
 *   below.png  — everything under the photo frames (page background + shapes below the first frame)
 *   above.png  — everything on top of the frames (decorations), transparent elsewhere
 *   mask_N.png — the shape of photo frame N (alpha = where the photo shows)
 *   spec       — frame boxes, and each typed text box (main title / only number / CTA / website)
 *                with its font, size, colour, alignment and effects
 * Photos: no frame → one photo fills the background; 1 frame → the photo fills that frame;
 * 2+ frames → a collage with one photo per frame. The typed texts are filled with each pin's own
 * title, number, CTA and website. A template with an "only number" text is a numbered template:
 * titles without a number never use it.
 *
 * Keys: 'at_<id>'. They join pin_template_registry(), so they show up in the Pin Templates & Styles
 * picker, the Classic Wizard and AI Auto like every other template.
 */

const AT_TAG_PRESETS = ['Trending', 'Popular', 'Viral', 'New', 'High CTR'];
const AT_ROLES = ['main' => 'Main title', 'number' => 'Only number', 'cta' => 'CTA', 'website' => 'Website', 'static' => 'Static text (keep as written)'];

function at_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = __DIR__ . '/../uploads/.schema_admin_pin_templates_v1';
    if (is_file($flag)) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_pin_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT '',
            priority TINYINT NOT NULL DEFAULT 5,
            tags VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
            width INT NOT NULL DEFAULT 1000,
            height INT NOT NULL DEFAULT 1500,
            frames INT NOT NULL DEFAULT 0,
            numbered TINYINT(1) NOT NULL DEFAULT 0,
            design_json LONGTEXT,
            spec_json LONGTEXT,
            thumb_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_at_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pin_template_status (
            tkey VARCHAR(64) NOT NULL PRIMARY KEY,
            active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        @file_put_contents($flag, date('c'));
    } catch (Throwable $e) { /* retried on the next request */ }
}

function at_pdo(): ?PDO
{
    global $pdo;
    return (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
}

/** Keys of built-in templates the admin switched off. */
function at_disabled_default_keys(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $pdo = at_pdo();
    if (!$pdo) return $cache;
    try {
        at_ensure_schema($pdo);
        foreach ($pdo->query("SELECT tkey FROM pin_template_status WHERE active = 0")->fetchAll(PDO::FETCH_COLUMN) as $k) $cache[$k] = true;
    } catch (Throwable $e) { /* table missing → all on */ }
    return $cache;
}

/** Published (active + inactive) admin templates, keyed by 'at_<id>'. Drafts are never listed. */
function at_published_rows(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $pdo = at_pdo();
    if (!$pdo) return $cache;
    try {
        at_ensure_schema($pdo);
        $rows = $pdo->query("SELECT id, name, category, priority, tags, status, width, height, frames, numbered, spec_json, updated_at
            FROM admin_pin_templates WHERE status IN ('active', 'inactive') ORDER BY priority DESC, id DESC")->fetchAll();
        foreach ($rows as $r) $cache['at_' . $r['id']] = $r;
    } catch (Throwable $e) { /* table missing */ }
    return $cache;
}

/** Registry entries for admin templates (merged into pin_template_registry()). */
function at_registry_entries(): array
{
    $out = [];
    foreach (at_published_rows() as $key => $r) {
        $frames = (int)$r['frames'];
        $out[$key] = [
            'key' => $key, 'name' => $r['name'], 'category' => $r['category'] !== '' ? $r['category'] : 'Lifestyle',
            'any_category' => $r['category'] === '',
            'layout' => $frames >= 2 ? 'collage' : 'single', 'photos' => max(1, $frames),
            'admin' => true, 'numbered' => (bool)$r['numbered'], 'priority' => (int)$r['priority'],
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string)$r['tags'])))),
            'active' => $r['status'] === 'active',
            'ver' => substr(md5((string)$r['updated_at'] . $r['id']), 0, 8),
        ];
    }
    return $out;
}

/** Title has a number in it (for the numbered-template rule). */
function at_title_has_number(string $title): bool
{
    return (bool)preg_match('/\d/', $title);
}

/** Splits "17 White Jeans Outfits" into ['17', 'White Jeans Outfits'] (number = first number in the title). */
function at_split_number(string $title): array
{
    if (!preg_match('/\d+(?:[.,]\d+)?\+?/', $title, $m, PREG_OFFSET_CAPTURE)) return ['', $title];
    $num = $m[0][0];
    $rest = substr($title, 0, $m[0][1]) . substr($title, $m[0][1] + strlen($num));
    $rest = trim(preg_replace('/\s{2,}/', ' ', $rest), " \t-–—:|.,");
    return [$num, $rest !== '' ? $rest : $title];
}

/* ===================== Fonts ===================== */

function at_font_dir(): string
{
    $d = __DIR__ . '/../assets/fonts/gf/';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** Bundled fonts by family name (lower-case) → file. */
function at_bundled_fonts(): array
{
    $b = __DIR__ . '/../assets/fonts/';
    return [
        'poppins' => $b . 'Poppins-Bold.ttf', 'anton' => $b . 'Anton-Regular.ttf', 'archivo black' => $b . 'ArchivoBlack-Regular.ttf',
        'bebas neue' => $b . 'BebasNeue-Regular.ttf', 'great vibes' => $b . 'GreatVibes-Regular.ttf', 'pacifico' => $b . 'Pacifico-Regular.ttf',
        'abril fatface' => $b . 'AbrilFatface-Regular.ttf', 'titan one' => $b . 'TitanOne-Regular.ttf', 'luckiest guy' => $b . 'LuckiestGuy-Regular.ttf',
        'lobster' => $b . 'Lobster-Regular.ttf', 'satisfy' => $b . 'Satisfy-Regular.ttf', 'dm serif display' => $b . 'DMSerifDisplay-Italic.ttf',
        'playfair display' => $b . 'PlayfairDisplay-BoldItalic.ttf',
    ];
}

/**
 * A TTF file for a font family + weight: the downloaded Google Font (fetched once, then cached in
 * assets/fonts/gf/), a bundled one, or Poppins as the last resort. Returns an absolute path.
 */
function at_font_file(string $family, int $weight = 400, bool $italic = false, bool $download = false): string
{
    $family = trim($family) ?: 'Poppins';
    $weight = max(100, min(900, (int)round($weight / 100) * 100));
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($family)) . '-' . $weight . ($italic ? 'i' : '');
    $cached = at_font_dir() . $slug . '.ttf';
    if (is_file($cached) && filesize($cached) > 1000) return $cached;
    if (strtolower($family) === 'poppins') {
        $b = __DIR__ . '/../assets/fonts/';
        return $weight >= 900 ? $b . 'Poppins-Black.ttf' : ($weight >= 800 ? $b . 'Poppins-ExtraBold.ttf' : ($weight >= 600 ? $b . 'Poppins-Bold.ttf' : $b . 'Poppins-Regular.ttf'));
    }
    if ($download && function_exists('curl_init') && !in_array(strtolower($family), ['arial', 'georgia', 'times new roman'], true)) {
        // A plain download-tool user-agent makes Google Fonts answer with TTF files (GD can't read woff/woff2).
        foreach ([[$weight, $italic], [$weight, false], [400, false], [700, false]] as [$w, $it]) {
            $css = at_http_get('https://fonts.googleapis.com/css?family=' . str_replace('%20', '+', rawurlencode($family)) . ':' . $w . ($it ? 'i' : ''), 'Wget/1.12');
            if ($css && preg_match('#url\((https://fonts\.gstatic\.com/[^)]+\.ttf)\)#', $css, $m)) {
                $ttf = at_http_get($m[1]);
                if ($ttf && strlen($ttf) > 1000) {
                    @file_put_contents($cached, $ttf);
                    return $cached;
                }
            }
        }
    }
    $bundled = at_bundled_fonts()[strtolower($family)] ?? null;
    if ($bundled && is_file($bundled)) return $bundled;
    $b = __DIR__ . '/../assets/fonts/';
    return $weight >= 800 ? $b . 'Poppins-ExtraBold.ttf' : ($weight >= 600 ? $b . 'Poppins-Bold.ttf' : $b . 'Poppins-Regular.ttf');
}

function at_http_get(string $url, string $ua = 'AutomatedPin'): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => $ua]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code < 300) ? (string)$body : null;
}

/* ===================== Files ===================== */

function at_dir(int $id): string
{
    return __DIR__ . '/../uploads/pin-templates/at_' . $id . '/';
}

/** Saves a data: URL (PNG/JPEG) to $path. Returns true on success. */
function at_save_data_url(string $dataUrl, string $path): bool
{
    if (!preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl, $m)) return false;
    $bytes = base64_decode(substr($dataUrl, strlen($m[0])), true);
    if ($bytes === false || strlen($bytes) > 25 * 1048576 || !@imagecreatefromstring($bytes)) return false;
    return @file_put_contents($path, $bytes) !== false;
}

/* ===================== Rendering ===================== */

function at_color(GdImage $im, $css, int $alphaOverride = -1)
{
    [$r, $g, $b, $a] = at_parse_color($css);
    if ($alphaOverride >= 0) $a = $alphaOverride;
    return imagecolorallocatealpha($im, $r, $g, $b, $a);
}

/** CSS colour → [r, g, b, gdAlpha(0 opaque … 127 clear)]; null/transparent → fully clear. */
function at_parse_color($css): array
{
    $c = is_string($css) ? strtolower(trim($css)) : '';
    if ($c === '' || $c === 'transparent' || $c === 'none') return [0, 0, 0, 127];
    if (preg_match('/^#([0-9a-f]{3})$/', $c, $m)) {
        return [hexdec($m[1][0] . $m[1][0]), hexdec($m[1][1] . $m[1][1]), hexdec($m[1][2] . $m[1][2]), 0];
    }
    if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $c, $m)) {
        $a = isset($m[2]) ? (int)round((1 - hexdec($m[2]) / 255) * 127) : 0;
        return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2)), $a];
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $c, $m)) {
        $a = isset($m[4]) && $m[4] !== '' ? (int)round((1 - (float)$m[4]) * 127) : 0;
        return [(int)$m[1], (int)$m[2], (int)$m[3], max(0, min(127, $a))];
    }
    $named = ['white' => [255, 255, 255], 'black' => [0, 0, 0], 'red' => [255, 0, 0], 'yellow' => [255, 255, 0]];
    if (isset($named[$c])) return array_merge($named[$c], [0]);
    return [17, 17, 17, 0];
}

/** Copies $src over $dst resized to $w×$h at ($x,$y), keeping transparency. */
function at_copy_scaled(GdImage $dst, GdImage $src, int $x, int $y, int $w, int $h): void
{
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
}

/** The photo, cover-cropped to $w×$h. */
function at_cover(GdImage $photo, int $w, int $h): GdImage
{
    $out = imagecreatetruecolor(max(1, $w), max(1, $h));
    $pw = imagesx($photo); $ph = imagesy($photo);
    $k = max($w / $pw, $h / $ph);
    $sw = (int)round($w / $k); $sh = (int)round($h / $k);
    imagecopyresampled($out, $photo, 0, 0, (int)(($pw - $sw) / 2), (int)(($ph - $sh) / 2), $w, $h, $sw, $sh);
    return $out;
}

/**
 * Draws one pin with admin template $key. Returns JPEG bytes or null (unknown / broken template).
 * $title is the pin's headline; the number and the rest are split for numbered templates.
 */
function at_render(string $key, array $imageBytesList, string $title, string $website, string $ctaText, string $sizeKey): ?string
{
    $pdo = at_pdo();
    if (!$pdo || !preg_match('/^at_(\d+)$/', $key, $m)) return null;
    $id = (int)$m[1];
    $st = $pdo->prepare("SELECT * FROM admin_pin_templates WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;
    $spec = json_decode((string)$row['spec_json'], true);
    if (!is_array($spec)) return null;
    $dir = at_dir($id);

    [$W, $H] = function_exists('pin_image_size_dims') ? pin_image_size_dims($sizeKey) : [1000, 1500];
    $dw = max(1, (int)$row['width']); $dh = max(1, (int)$row['height']);
    $sx = $W / $dw; $sy = $H / $dh; $sf = min($sx, $sy);

    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));

    $photos = [];
    foreach ($imageBytesList as $b) { $g = is_string($b) && $b !== '' ? @imagecreatefromstring($b) : false; if ($g) $photos[] = $g; }

    $frames = is_array($spec['frames'] ?? null) ? $spec['frames'] : [];
    if (!$frames && $photos) {
        // No frame: the photo is the background.
        $c = at_cover($photos[0], $W, $H);
        imagecopy($im, $c, 0, 0, 0, 0, $W, $H);
        imagedestroy($c);
    }
    if (is_file($dir . 'below.png') && ($below = @imagecreatefrompng($dir . 'below.png'))) {
        at_copy_scaled($im, $below, 0, 0, $W, $H);
        imagedestroy($below);
    }
    foreach ($frames as $i => $f) {
        if (!$photos) break;
        $x = (int)round($f['x'] * $sx); $y = (int)round($f['y'] * $sy);
        $w = max(1, (int)round($f['w'] * $sx)); $h = max(1, (int)round($f['h'] * $sy));
        $tile = at_cover($photos[$i % count($photos)], $w, $h);
        $maskFile = $dir . 'mask_' . $i . '.png';
        if (empty($f['rect']) && is_file($maskFile) && ($mask = @imagecreatefrompng($maskFile))) {
            // Cut the frame's shape out of the full-size mask, scaled to this tile, and apply its alpha.
            $mk = imagecreatetruecolor($w, $h);
            imagealphablending($mk, false); imagesavealpha($mk, true);
            imagefill($mk, 0, 0, imagecolorallocatealpha($mk, 0, 0, 0, 127));
            imagecopyresampled($mk, $mask, 0, 0, (int)round($f['x']), (int)round($f['y']), $w, $h, (int)round($f['w']), (int)round($f['h']));
            imagedestroy($mask);
            $shaped = imagecreatetruecolor($w, $h);
            imagealphablending($shaped, false); imagesavealpha($shaped, true);
            for ($yy = 0; $yy < $h; $yy++) {
                for ($xx = 0; $xx < $w; $xx++) {
                    $a = (imagecolorat($mk, $xx, $yy) >> 24) & 0x7F;
                    if ($a >= 127) { imagesetpixel($shaped, $xx, $yy, 0x7F000000); continue; }
                    imagesetpixel($shaped, $xx, $yy, (imagecolorat($tile, $xx, $yy) & 0xFFFFFF) | ($a << 24));
                }
            }
            imagedestroy($mk);
            imagealphablending($im, true);
            imagecopy($im, $shaped, $x, $y, 0, 0, $w, $h);
            imagedestroy($shaped);
        } else {
            imagecopy($im, $tile, $x, $y, 0, 0, $w, $h);
        }
        imagedestroy($tile);
    }
    if (is_file($dir . 'above.png') && ($above = @imagecreatefrompng($dir . 'above.png'))) {
        at_copy_scaled($im, $above, 0, 0, $W, $H);
        imagedestroy($above);
    }
    foreach ($photos as $g) imagedestroy($g);

    // Typed texts.
    $numbered = !empty($row['numbered']);
    [$num, $rest] = at_split_number($title);
    $site = preg_replace('#^https?://(www\.)?#i', '', trim($website));
    $site = rtrim(preg_replace('#/.*$#', '', $site), '/');
    foreach ((array)($spec['texts'] ?? []) as $t) {
        $role = $t['role'] ?? 'main';
        if ($role === 'main') $txt = ($numbered && $num !== '') ? $rest : $title;
        elseif ($role === 'number') $txt = $num;
        elseif ($role === 'cta') $txt = trim($ctaText);
        elseif ($role === 'website') $txt = $site;
        else continue;
        if ($txt === '') continue;
        at_draw_text($im, $txt, $t, $sx, $sy, $sf);
    }

    ob_start();
    imagejpeg($im, null, 90);
    $out = ob_get_clean();
    imagedestroy($im);
    return $out ?: null;
}

/** Word-wraps $text to $maxW at $size. */
function at_wrap(string $text, string $font, float $size, float $maxW): array
{
    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $line = '';
    foreach ($words as $w) {
        $try = $line === '' ? $w : $line . ' ' . $w;
        $bb = imagettfbbox($size, 0, $font, $try);
        if ($line !== '' && ($bb[2] - $bb[0]) > $maxW) { $lines[] = $line; $line = $w; }
        else $line = $try;
    }
    if ($line !== '') $lines[] = $line;
    return $lines ?: [''];
}

/**
 * Draws a typed text into its box: wrapped, shrunk until it fits (never larger than designed),
 * centred vertically, aligned like the design, with outline / shadow / highlight when set.
 */
function at_draw_text(GdImage $im, string $text, array $t, float $sx, float $sy, float $sf): void
{
    $font = !empty($t['font_file']) && is_file(__DIR__ . '/../' . $t['font_file'])
        ? __DIR__ . '/../' . $t['font_file']
        : at_font_file((string)($t['font'] ?? 'Poppins'), (int)($t['weight'] ?? 700), !empty($t['italic']));
    if (!empty($t['upper'])) $text = mb_strtoupper($text, 'UTF-8');
    $bx = $t['x'] * $sx; $by = $t['y'] * $sy; $bw = max(10, $t['w'] * $sx); $bh = max(10, $t['h'] * $sy);
    $single = in_array($t['role'] ?? '', ['number', 'cta', 'website'], true);
    $lh = max(0.8, (float)($t['line_height'] ?? 1.16));
    $size = max(8, (float)($t['size'] ?? 60) * $sf * 0.75);   // CSS px → GD points
    $min = 8;
    // Allow the box to grow a little for long titles before shrinking the text.
    $maxH = $bh * ($single ? 1.05 : 1.25);
    while (true) {
        $lines = $single ? [$text] : at_wrap($text, $font, $size, $bw);
        $widest = 0;
        foreach ($lines as $ln) { $bb = imagettfbbox($size, 0, $font, $ln); $widest = max($widest, $bb[2] - $bb[0]); }
        $lineH = $size / 0.75 * $lh;
        if (($widest <= $bw * 1.02 && count($lines) * $lineH <= $maxH) || $size <= $min) break;
        $size = max($min, $size * 0.93);
    }
    $lineH = $size / 0.75 * $lh;
    $totalH = count($lines) * $lineH;
    $y0 = $by + ($bh - $totalH) / 2;
    $align = $t['align'] ?? 'center';
    $fill = at_color($im, $t['fill'] ?? '#111111');
    $stroke = !empty($t['stroke']) && ($t['stroke_width'] ?? 0) > 0 ? at_color($im, $t['stroke']) : null;
    $sw = (int)max(1, round(($t['stroke_width'] ?? 0) * $sf));
    $shadow = !empty($t['shadow']) ? at_color($im, $t['shadow']['color'] ?? 'rgba(0,0,0,0.5)') : null;
    $sox = (int)round(($t['shadow']['x'] ?? 3) * $sf); $soy = (int)round(($t['shadow']['y'] ?? 3) * $sf);
    $hl = !empty($t['highlight']) ? at_color($im, $t['highlight']) : null;
    imagealphablending($im, true);
    foreach ($lines as $i => $ln) {
        $bb = imagettfbbox($size, 0, $font, $ln);
        $lw = $bb[2] - $bb[0];
        $x = $align === 'left' ? $bx : ($align === 'right' ? $bx + $bw - $lw : $bx + ($bw - $lw) / 2);
        $x -= $bb[0];
        $baseline = $y0 + $i * $lineH + ($lineH + ($size / 0.75) * 0.7) / 2;
        if ($hl) {
            $pad = $size * 0.25;
            imagefilledrectangle($im, (int)($x + $bb[0] - $pad), (int)($baseline - $size / 0.75 * 0.85), (int)($x + $bb[2] + $pad), (int)($baseline + $size / 0.75 * 0.22), $hl);
        }
        if ($shadow) imagettftext($im, $size, 0, (int)round($x + $sox), (int)round($baseline + $soy), $shadow, $font, $ln);
        if ($stroke) {
            for ($dx = -$sw; $dx <= $sw; $dx++) {
                for ($dy = -$sw; $dy <= $sw; $dy++) {
                    if ($dx * $dx + $dy * $dy > $sw * $sw + 1) continue;
                    imagettftext($im, $size, 0, (int)round($x + $dx), (int)round($baseline + $dy), $stroke, $font, $ln);
                }
            }
        }
        imagettftext($im, $size, 0, (int)round($x), (int)round($baseline), $fill, $font, $ln);
    }
}
