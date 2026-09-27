<?php
/**
 * Custom Design editor — Canva-style drag & drop editor (Fabric.js).
 *   design-editor              → new blank design (1000×1500)
 *   design-editor?id=12        → edit your design #12
 *   design-editor?template=34  → start a new design from published template #34
 *   design-editor?w=1080&h=1920 → new blank design at that size
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/design_functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Shared design link (design-share?t=…): logged-in visitors open it straight in the editor
// (their own copy, or the original for its owner). Not logged in yet → back to the preview page.
if (!empty($_GET['shared'])) {
    $shared = design_by_share_token($pdo, (string)$_GET['shared']);
    if (!$shared) redirect('designs');
    if (empty($_SESSION['user_id'])) redirect(design_share_url($shared['share_token']));
}
require_login();
$user = current_user($pdo);
if (!empty($shared)) redirect('design-editor?id=' . design_open_shared($pdo, $shared, (int)$user['id']));

$boot = ['id' => 0, 'template' => 0, 'width' => 1000, 'height' => 1500, 'title' => 'Untitled design'];
if (!empty($_GET['id'])) {
    $row = design_row_for_user($pdo, (int)$_GET['id'], (int)$user['id']);
    if ($row) $boot = ['id' => (int)$row['id'], 'template' => 0, 'width' => (int)$row['width'], 'height' => (int)$row['height'], 'title' => $row['title']];
} elseif (!empty($_GET['template'])) {
    $boot['template'] = (int)$_GET['template'];
} else {
    if (!empty($_GET['w'])) $boot['width'] = max(50, min(8000, (int)$_GET['w']));
    if (!empty($_GET['h'])) $boot['height'] = max(50, min(8000, (int)$_GET['h']));
}
$v = function (string $rel) { return @filemtime(__DIR__ . '/../' . $rel) ?: time(); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Design Editor — <?= e(SITE_BRAND) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Alfa+Slab+One&family=Amatic+SC:wght@700&family=Anton&family=Archivo+Black&family=Bangers&family=Bebas+Neue&family=Caveat:wght@400;700&family=Cinzel:wght@400;700&family=DM+Serif+Display:ital@0;1&family=Dancing+Script:wght@400;700&family=Fredoka:wght@400;700&family=Great+Vibes&family=Josefin+Sans:ital,wght@0,400;0,700;1,400&family=Kaushan+Script&family=Lato:ital,wght@0,400;0,900;1,400&family=Lobster&family=Luckiest+Guy&family=Merriweather:ital,wght@0,400;0,900;1,400&family=Montserrat:ital,wght@0,400;0,800;1,400;1,800&family=Nunito:ital,wght@0,400;0,900;1,400&family=Open+Sans:ital,wght@0,400;0,800;1,400&family=Oswald:wght@400;700&family=Pacifico&family=Permanent+Marker&family=Playfair+Display:ital,wght@0,400;0,900;1,400;1,900&family=Poppins:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Quicksand:wght@400;700&family=Raleway:ital,wght@0,400;0,800;1,400&family=Righteous&family=Roboto:ital,wght@0,400;0,900;1,400&family=Sacramento&family=Satisfy&family=Shadows+Into+Light&family=Titan+One&display=swap">
<link rel="stylesheet" href="../assets/css/design-editor.css?v=<?= $v('assets/css/design-editor.css') ?>">
</head>
<body class="de-body">

<header class="de-top">
    <a href="designs" class="de-back" title="Back to your designs">←</a>
    <input type="text" id="deTitle" class="de-title" value="<?= e($boot['title']) ?>" maxlength="255" aria-label="Design title">
    <button type="button" class="de-tbtn de-desk" id="deResizeBtn" title="Resize">📐 <span id="deSizeLabel"><?= $boot['width'] ?> × <?= $boot['height'] ?></span></button>
    <div class="de-sep de-desk"></div>
    <button type="button" class="de-tbtn" id="deUndo" title="Undo (Ctrl+Z)">↶</button>
    <button type="button" class="de-tbtn" id="deRedo" title="Redo (Ctrl+Y)">↷</button>
    <div class="de-sep de-desk"></div>
    <button type="button" class="de-tbtn de-desk" id="deZoomOut" title="Zoom out">−</button>
    <span id="deZoomLabel" class="de-zoom de-desk">100%</span>
    <button type="button" class="de-tbtn de-desk" id="deZoomIn" title="Zoom in">+</button>
    <button type="button" class="de-tbtn de-desk" id="deZoomFit" title="Fit to screen">⤢</button>
    <div class="de-spacer"></div>
    <span id="deSaveState" class="de-savestate"></span>
    <button type="button" class="de-btn de-desk" id="deSave">💾 Save</button>
    <button type="button" class="de-btn de-desk" id="deImportBtn" title="Import a design file (.json)">📂 Import</button>
    <button type="button" class="de-btn" id="deDownloadBtn" title="Download or share">⬇<span class="de-lbl"> Download</span></button>
    <button type="button" class="de-btn de-primary" id="deUse">✔<span class="de-lbl"> Use This Design</span><span class="de-lbl-short"> Use</span></button>
    <div class="de-dd de-mob" id="deMoreDd">
        <button type="button" class="de-tbtn de-more" id="deMoreBtn" title="More" aria-label="More options">⋯</button>
        <div class="de-ddmenu de-moremenu">
            <button type="button" data-proxy="deSave">💾 Save</button>
            <button type="button" data-proxy="deShareOpenBtn">🔗 Share design</button>
            <button type="button" data-proxy="deResizeBtn">📐 Resize</button>
            <button type="button" data-proxy="deZoomIn">➕ Zoom in</button>
            <button type="button" data-proxy="deZoomOut">➖ Zoom out</button>
            <button type="button" data-proxy="deZoomFit">⤢ Fit to screen</button>
            <button type="button" data-proxy="deImportBtn">📂 Import design file</button>
        </div>
    </div>
    <button type="button" id="deShareOpenBtn" hidden></button>
</header>

<div class="de-main">
    <nav class="de-rail">
        <button type="button" data-panel="templates" class="on"><span>🗂</span>Templates</button>
        <button type="button" data-panel="layouts"><span>▦</span>Layouts</button>
        <button type="button" data-panel="elements"><span>✦</span>Elements</button>
        <button type="button" data-panel="text"><span>T</span>Text</button>
        <button type="button" data-panel="uploads"><span>⬆</span>Uploads</button>
        <button type="button" data-panel="photos"><span>🖼</span>Photos</button>
        <button type="button" data-panel="background"><span>🎨</span>Background</button>
        <button type="button" data-panel="layers"><span>☰</span>Layers</button>
    </nav>

    <aside class="de-panel" id="dePanel">
        <div class="de-sheetbar"><span class="de-grip" aria-hidden="true"></span><button type="button" class="de-panel-close" id="dePanelClose" aria-label="Close panel">✕</button></div>
        <!-- Templates -->
        <section data-panel-body="templates">
            <h3>Templates</h3>
            <input type="search" class="de-input" id="deTplSearch" placeholder="Search templates…">
            <div class="de-subtabs"><button type="button" data-tplsrc="pub" class="on">Published</button><button type="button" data-tplsrc="mine">My designs</button></div>
            <div class="de-thumbgrid" id="deTplGrid"><p class="de-muted">Loading…</p></div>
            <div class="de-more-sentinel" id="deTplMore" aria-hidden="true"></div>
        </section>

        <!-- Layouts (collage frames) -->
        <section data-panel-body="layouts" hidden>
            <h3>Collage layouts</h3>
            <p class="de-muted">Adds photo frames that fill the page. Drag photos from Uploads / Photos onto a frame, or select a frame and click a photo.</p>
            <label class="de-row"><span>Gap</span><input type="range" id="deLayoutGap" min="0" max="60" value="12"><output id="deLayoutGapOut">12</output></label>
            <label class="de-row"><span>Gap colour</span><input type="color" id="deLayoutGapColor" value="#ffffff"></label>
            <label class="de-check"><input type="checkbox" id="deLayoutRound"> Rounded frames</label>
            <label class="de-check"><input type="checkbox" id="deLayoutReplace" checked> Replace existing frames</label>
            <div class="de-layoutgrid" id="deLayoutGrid"></div>
            <h3>Single frames</h3>
            <div class="de-shapegrid" id="deFrameGrid"></div>
        </section>

        <!-- Elements: Shapes · Graphics · Emoji · 3D · Frames -->
        <section data-panel-body="elements" hidden>
            <h3>Elements</h3>
            <input type="search" class="de-input" id="deElSearch" placeholder="Search shapes, graphics, emoji…">
            <div class="de-eltabs" id="deElTabs">
                <button type="button" data-eltab="shapes" class="on">Shapes</button>
                <button type="button" data-eltab="graphics">Graphics</button>
                <button type="button" data-eltab="emoji">Emoji</button>
                <button type="button" data-eltab="3d">3D</button>
                <button type="button" data-eltab="frames">Frames</button>
            </div>
            <div id="deElBody" class="de-elbody"><p class="de-muted">Loading…</p></div>
        </section>

        <!-- Text -->
        <section data-panel-body="text" hidden>
            <h3>Text</h3>
            <button type="button" class="de-addtext de-h1" data-addtext="heading">Add a heading</button>
            <button type="button" class="de-addtext de-h2" data-addtext="sub">Add a subheading</button>
            <button type="button" class="de-addtext de-h3" data-addtext="body">Add a little bit of body text</button>
            <h3>Text styles</h3>
            <div class="de-textstyles" id="deTextStyles"></div>
        </section>

        <!-- Uploads -->
        <section data-panel-body="uploads" hidden>
            <h3>Uploads</h3>
            <label class="de-upload">⬆ Upload images<input type="file" id="deUploadInput" accept="image/*" multiple hidden></label>
            <p class="de-muted">JPG, PNG, WEBP or GIF up to 15 MB. Drag onto the page or onto a frame.</p>
            <div class="de-thumbgrid de-photogrid" id="deUploadGrid"></div>
        </section>

        <!-- Stock photos -->
        <section data-panel-body="photos" hidden>
            <h3>Free photos</h3>
            <form id="dePhotoForm" class="de-inline"><input type="search" class="de-input" id="dePhotoQ" placeholder="Search photos… e.g. cozy living room"><button type="submit" class="de-btn">Go</button></form>
            <div class="de-thumbgrid de-photogrid" id="dePhotoGrid"><p class="de-muted">Search millions of free photos (Pexels).</p></div>
            <div class="de-more-sentinel" id="dePhotoMore" aria-hidden="true"></div>
            <p class="de-muted de-loadmore-note" id="dePhotoNote" hidden></p>
        </section>

        <!-- Colour panel (opens beside the canvas when any colour is clicked) -->
        <section data-panel-body="color" hidden>
            <div class="de-colorhead"><button type="button" class="de-ib" id="deColorBack" title="Back">←</button><h3 id="deColorTitle">Colour</h3></div>
            <div class="de-colorcustom">
                <label class="de-colorpick" title="Pick any colour"><input type="color" id="deColorPicker" value="#7c3aed"><span>＋</span></label>
                <input type="text" id="deColorHex" class="de-input" maxlength="7" placeholder="#RRGGBB" spellcheck="false">
                <button type="button" class="de-btn" id="deColorHexApply">Apply</button>
            </div>
            <div id="deTintWrap" hidden>
                <label class="de-row"><span>Strength</span><input type="range" id="deTintAlpha" min="0.1" max="1" step="0.05" value="1"></label>
                <button type="button" class="de-btn" id="deTintClear">Original colours</button>
            </div>
            <h4 class="de-colorsub">Your used colours</h4>
            <div class="de-swatchgrid" id="deUsedColors"></div>
            <h4 class="de-colorsub">Default colours</h4>
            <div class="de-swatchgrid" id="deDefaultColors"></div>
            <div id="deGradWrap">
                <h4 class="de-colorsub">Gradients</h4>
                <div class="de-swatchgrid" id="deGradColors"></div>
            </div>
        </section>

        <!-- Background -->
        <section data-panel-body="background" hidden>
            <h3>Background colour</h3>
            <input type="color" id="deBgColor" value="#ffffff" class="de-bigcolor">
            <div class="de-swatches" id="deBgSwatches"></div>
            <h3>Gradients</h3>
            <div class="de-swatches" id="deBgGradients"></div>
            <h3>Background image</h3>
            <p class="de-muted">Select a photo in Uploads with “Set as background”, or:</p>
            <button type="button" class="de-btn" id="deBgClearImg">Remove background image</button>
        </section>

        <!-- Layers -->
        <section data-panel-body="layers" hidden>
            <h3>Layers</h3>
            <p class="de-muted">Top of the list = front. Click to select.</p>
            <div id="deLayerList" class="de-layers"></div>
        </section>
    </aside>

    <div class="de-stagewrap">
        <!-- context toolbar -->
        <div class="de-ctx" id="deCtx">
            <div class="de-ctx-empty" id="deCtxEmpty"><span class="de-desk-t">Select an element to edit it · Double-click text to type · Del to delete · Ctrl+D duplicate</span><span class="de-mob-t">Tap an element to edit it · Tap selected text again to type · Pinch to zoom</span></div>

            <div class="de-ctx-group" data-ctx="text" hidden>
                <select id="deFont" class="de-fontsel" title="Font"></select>
                <div class="de-dd de-fontdd">
                    <button type="button" class="de-ib" id="deFontSearchBtn" title="Search fonts" aria-label="Search fonts">🔎</button>
                    <div class="de-ddmenu de-ddpad de-fontmenu">
                        <input type="search" id="deFontQ" class="de-input" placeholder="Search fonts…" autocomplete="off" spellcheck="false">
                        <div id="deFontList" class="de-fontlist" role="listbox" aria-label="Fonts"></div>
                    </div>
                </div>
                <input type="number" id="deFontSize" class="de-num" min="6" max="600" title="Font size">
                <input type="color" id="deTextColor" title="Text colour">
                <button type="button" class="de-ib" id="deBold" title="Bold"><b>B</b></button>
                <button type="button" class="de-ib" id="deItalic" title="Italic"><i>I</i></button>
                <button type="button" class="de-ib" id="deUnderline" title="Underline"><u>U</u></button>
                <button type="button" class="de-ib" id="deStrike" title="Strikethrough"><s>S</s></button>
                <div class="de-dd">
                    <button type="button" class="de-ib" id="deCaseBtn" title="Letter case">Aa ▾</button>
                    <div class="de-ddmenu">
                        <button type="button" data-case="upper">UPPERCASE</button>
                        <button type="button" data-case="lower">lowercase</button>
                        <button type="button" data-case="title">Title Case</button>
                        <button type="button" data-case="sentence">Sentence case</button>
                    </div>
                </div>
                <button type="button" class="de-ib" data-align="left" title="Align left">⟸</button>
                <button type="button" class="de-ib" data-align="center" title="Align centre">≡</button>
                <button type="button" class="de-ib" data-align="right" title="Align right">⟹</button>
                <button type="button" class="de-ib" data-align="justify" title="Justify">☰</button>
                <div class="de-dd">
                    <button type="button" class="de-ib" title="Spacing">↕ Spacing ▾</button>
                    <div class="de-ddmenu de-ddpad">
                        <label class="de-row"><span>Letter spacing</span><input type="range" id="deCharSpacing" min="-200" max="1500" step="10"><output id="deCharSpacingOut"></output></label>
                        <label class="de-row"><span>Line spacing</span><input type="range" id="deLineHeight" min="0.5" max="3" step="0.05"><output id="deLineHeightOut"></output></label>
                    </div>
                </div>
                <div class="de-dd">
                    <button type="button" class="de-ib" title="Text effects">✨ Effects ▾</button>
                    <div class="de-ddmenu de-ddpad de-effects">
                        <div class="de-effectgrid">
                            <button type="button" data-fx="none">None</button>
                            <button type="button" data-fx="shadow">Shadow</button>
                            <button type="button" data-fx="lift">Lift</button>
                            <button type="button" data-fx="hollow">Hollow</button>
                            <button type="button" data-fx="outline">Outline</button>
                            <button type="button" data-fx="splice">Splice</button>
                            <button type="button" data-fx="neon">Neon</button>
                            <button type="button" data-fx="thick">3D Thick</button>
                            <button type="button" data-fx="highlight">Highlight</button>
                        </div>
                        <label class="de-row"><span>Outline / thickness</span><input type="range" id="deStrokeW" min="0" max="40" step="0.5"><output id="deStrokeWOut"></output></label>
                        <label class="de-row"><span>Outline colour</span><input type="color" id="deStrokeC"></label>
                        <label class="de-row"><span>Shadow / glow colour</span><input type="color" id="deShadowC" value="#000000"></label>
                        <label class="de-row"><span>Shadow blur</span><input type="range" id="deShadowBlur" min="0" max="80"><output id="deShadowBlurOut"></output></label>
                        <label class="de-row"><span>Shadow offset</span><input type="range" id="deShadowOff" min="0" max="60"><output id="deShadowOffOut"></output></label>
                        <label class="de-row"><span>Highlight colour</span><input type="color" id="deHighlightC" value="#ffe066"></label>
                    </div>
                </div>
            </div>

            <div class="de-ctx-group" data-ctx="shape" hidden>
                <label class="de-mini">Fill <input type="color" id="deFill"></label>
                <label class="de-mini">Border <input type="color" id="deBorderC"></label>
                <label class="de-mini">Width <input type="number" id="deBorderW" class="de-num" min="0" max="200"></label>
                <label class="de-mini" id="deRadiusWrap">Corners <input type="number" id="deRadius" class="de-num" min="0" max="1000"></label>
                <label class="de-mini"><input type="checkbox" id="deDashed"> Dashed</label>
            </div>

            <div class="de-ctx-group" data-ctx="image" hidden>
                <button type="button" class="de-ib" id="deReplaceImg" title="Replace image">🔁 Replace</button>
                <button type="button" class="de-ib" id="deCropBtn" title="Crop image (or double-click it)">✂ Crop</button>
                <div class="de-dd">
                    <button type="button" class="de-ib" title="Adjust">🎚 Adjust ▾</button>
                    <div class="de-ddmenu de-ddpad">
                        <label class="de-row"><span>Brightness</span><input type="range" id="deBright" min="-0.6" max="0.6" step="0.02"></label>
                        <label class="de-row"><span>Contrast</span><input type="range" id="deContrast" min="-0.6" max="0.6" step="0.02"></label>
                        <label class="de-row"><span>Saturation</span><input type="range" id="deSaturate" min="-1" max="1" step="0.05"></label>
                        <label class="de-check"><input type="checkbox" id="deGray"> Black &amp; white</label>
                        <label class="de-check"><input type="checkbox" id="deSepia"> Sepia</label>
                        <button type="button" class="de-btn" id="deResetFilters">Reset</button>
                    </div>
                </div>
                <div class="de-dd" id="deFrameFitWrap">
                    <button type="button" class="de-ib" title="Position photo inside the frame">✥ Crop in frame ▾</button>
                    <div class="de-ddmenu de-ddpad">
                        <label class="de-row"><span>Zoom</span><input type="range" id="deFrameZoom" min="1" max="4" step="0.02"></label>
                        <label class="de-row"><span>Left ↔ Right</span><input type="range" id="deFrameX" min="0" max="1" step="0.01"></label>
                        <label class="de-row"><span>Up ↕ Down</span><input type="range" id="deFrameY" min="0" max="1" step="0.01"></label>
                        <button type="button" class="de-btn" id="deFrameEmpty">Remove photo (keep frame)</button>
                    </div>
                </div>
                <label class="de-mini" id="deImgRadiusWrap">Corners <input type="number" id="deImgRadius" class="de-num" min="0" max="2000"></label>
                <button type="button" class="de-ib" id="deSetBg" title="Use as page background">🖼 Set as background</button>
                <button type="button" class="de-ib" id="deTintBtn" title="Change the colour of this graphic">🎨 Colour</button>
                <button type="button" class="de-ib" id="deImgFlipH" title="Flip horizontal">⇋ Flip</button>
            </div>

            <div class="de-ctx-group" data-ctx="graphic" hidden>
                <span class="de-mini">Colours</span>
                <div class="de-gfxcolors" id="deGfxColors"></div>
                <button type="button" class="de-ib" id="deGfxFlipH" title="Flip horizontal">⇋ Flip H</button>
                <button type="button" class="de-ib" id="deGfxFlipV" title="Flip vertical">⇵ Flip V</button>
            </div>

            <div class="de-ctx-group de-ctx-common" data-ctx="common" hidden>
                <div class="de-dd">
                    <button type="button" class="de-ib" title="Transparency">◐ ▾</button>
                    <div class="de-ddmenu de-ddpad"><label class="de-row"><span>Transparency</span><input type="range" id="deOpacity" min="0" max="1" step="0.01"><output id="deOpacityOut"></output></label></div>
                </div>
                <div class="de-dd">
                    <button type="button" class="de-ib" title="Position">⇅ Position ▾</button>
                    <div class="de-ddmenu de-ddpad">
                        <div class="de-effectgrid">
                            <button type="button" data-layer="front">To front</button><button type="button" data-layer="forward">Forward</button>
                            <button type="button" data-layer="backward">Backward</button><button type="button" data-layer="back">To back</button>
                        </div>
                        <div class="de-effectgrid">
                            <button type="button" data-palign="left">Left</button><button type="button" data-palign="hcenter">Centre</button><button type="button" data-palign="right">Right</button>
                            <button type="button" data-palign="top">Top</button><button type="button" data-palign="vcenter">Middle</button><button type="button" data-palign="bottom">Bottom</button>
                        </div>
                        <label class="de-row"><span>Rotate °</span><input type="number" id="deAngle" class="de-num" min="-360" max="360"></label>
                        <label class="de-row"><span>X</span><input type="number" id="dePosX" class="de-num"><span>Y</span><input type="number" id="dePosY" class="de-num"></label>
                        <label class="de-row"><span>W</span><input type="number" id="deSizeW" class="de-num"><span>H</span><input type="number" id="deSizeH" class="de-num"></label>
                    </div>
                </div>
                <button type="button" class="de-ib" id="deFlipH" title="Flip horizontal">⇋</button>
                <button type="button" class="de-ib" id="deFlipV" title="Flip vertical">⇵</button>
                <button type="button" class="de-ib" id="deGroup" title="Group (Ctrl+G)">⛓ Group</button>
                <button type="button" class="de-ib" id="deUngroup" title="Ungroup">✂ Ungroup</button>
                <button type="button" class="de-ib" id="deLock" title="Lock / unlock">🔒</button>
                <button type="button" class="de-ib" id="deDuplicate" title="Duplicate (Ctrl+D)">⧉</button>
                <button type="button" class="de-ib de-danger" id="deDelete" title="Delete (Del)">🗑</button>
            </div>
        </div>

        <div class="de-stage" id="deStage">
            <div class="de-pages" id="dePages">
                <div class="de-canvas-shadow" id="deCanvasBox"><canvas id="deCanvas"></canvas></div>
            </div>
        </div>
        <div class="de-cropbar" id="deCropBar" hidden>
            <span>✂ Drag the corners or edges to crop</span>
            <button type="button" class="de-btn" id="deCropReset">Reset</button>
            <button type="button" class="de-btn" id="deCropCancel">Cancel</button>
            <button type="button" class="de-btn de-primary" id="deCropApply">Done</button>
        </div>
    </div>
</div>

<!-- Resize modal -->
<div class="de-modal" id="deResizeModal" hidden>
    <div class="de-modal-box">
        <h3>Resize design</h3>
        <div class="de-presets" id="deSizePresets"></div>
        <div class="de-row"><span>Width</span><input type="number" id="deNewW" class="de-num" min="50" max="8000"><span>Height</span><input type="number" id="deNewH" class="de-num" min="50" max="8000"><span>px</span></div>
        <label class="de-check"><input type="checkbox" id="deScaleContent" checked> Scale the content to the new size</label>
        <div class="de-modal-actions"><button type="button" class="de-btn" data-close>Cancel</button><button type="button" class="de-btn de-primary" id="deResizeApply">Resize</button></div>
    </div>
</div>

<!-- Download / Use-this-design page picker -->
<div class="de-modal" id="deDlModal" hidden>
    <div class="de-modal-box">
        <h3 id="deDlTitle">Download</h3>
        <div id="deDlFmtWrap">
            <div class="de-dl-label">File type</div>
            <div class="de-fmtgrid" id="deDlFmt">
                <label><input type="radio" name="deDlFmt" value="png" checked><span>PNG<small>Best quality</small></span></label>
                <label><input type="radio" name="deDlFmt" value="jpg"><span>JPG<small>Smaller file</small></span></label>
                <label><input type="radio" name="deDlFmt" value="png2"><span>PNG 2×<small>Double size</small></span></label>
                <label><input type="radio" name="deDlFmt" value="pdf"><span>PDF<small>All pages in one file</small></span></label>
                <label><input type="radio" name="deDlFmt" value="svg"><span>SVG<small>Vector</small></span></label>
                <label><input type="radio" name="deDlFmt" value="json"><span>JSON<small>Design file (re-import)</small></span></label>
            </div>
        </div>
        <label class="de-check" id="deDlTransWrap" style="margin:-4px 0 12px;"><input type="checkbox" id="deDlTrans"> Transparent background <span class="de-muted">(no background colour / image — PNG &amp; SVG)</span></label>
        <div id="deDlPagesWrap">
            <div class="de-dl-label">Pages</div>
            <div class="de-dl-modes" id="deDlModes">
                <label><input type="radio" name="deDlPages" value="current"> Current page (single)</label>
                <label><input type="radio" name="deDlPages" value="all" checked> All pages (<span id="deDlCount">1</span>)</label>
                <label><input type="radio" name="deDlPages" value="custom"> Custom select</label>
            </div>
            <div class="de-dl-grid" id="deDlGrid" hidden></div>
        </div>
        <p class="de-muted" id="deDlNote" style="margin:10px 0 0;"></p>
        <div class="de-modal-actions"><button type="button" class="de-btn de-share-btn" id="deDlShare">🔗 Share design</button><span class="de-spacer"></span><button type="button" class="de-btn" data-close>Cancel</button><button type="button" class="de-btn de-primary" id="deDlGo">Download</button></div>
    </div>
</div>

<!-- Share design: public link anyone can open (no approval needed) -->
<div class="de-modal" id="deShareModal" hidden>
    <div class="de-modal-box de-share-box">
        <h3>🔗 Share design</h3>
        <p class="de-muted">Anyone with the link can see this design and open their own copy of it in the editor.</p>
        <div id="deShareBusy" class="de-share-busy"><span class="de-spin"></span> Publishing your design…</div>
        <div id="deShareReady" hidden>
            <img id="deShareImg" class="de-share-img" alt="">
            <div class="de-sharelink">
                <input type="text" id="deShareUrl" class="de-input" readonly aria-label="Design link">
                <button type="button" class="de-btn de-primary" id="deShareCopy">Copy link</button>
            </div>
            <div class="de-socials" id="deShareSocials"></div>
            <div class="de-modal-actions">
                <button type="button" class="de-btn" id="deUnshare">Stop sharing</button>
                <span class="de-spacer"></span>
                <a class="de-btn" id="deShareView" href="#" target="_blank" rel="noopener">Open page</a>
                <button type="button" class="de-btn de-primary" data-close>Done</button>
            </div>
        </div>
    </div>
</div>

<input type="file" id="deImportInput" accept=".json,application/json" hidden>
<input type="file" id="deReplaceInput" accept="image/*" hidden>
<div class="de-toast" id="deToast"></div>

<script>
window.DE_BOOT = <?= json_encode($boot) ?>;
window.DE_AJAX = 'ajax-design';
window.DE_BASE = '../';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script charset="utf-8" src="../assets/js/design-elements-data.js?v=<?= $v('assets/js/design-elements-data.js') ?>"></script>
<script src="../assets/js/design-editor.js?v=<?= $v('assets/js/design-editor.js') ?>"></script>
</body>
</html>
