/* Custom Design editor — Canva-style, built on Fabric.js 5.
 *
 * Page model: the Fabric canvas is always the design's real size (e.g. 1000×1500) shown through a zoom.
 * Frames: an empty frame is a grey placeholder Rect with isFrame=true. When a photo is dropped on it,
 * the placeholder is replaced by a fabric.Image that is cropped to the frame (cropX/cropY/width/height)
 * and clipped to the frame shape (rounded / circle / arch). The photo can be re-positioned inside its
 * frame with the "Crop in frame" sliders, and resizing a frame re-crops instead of stretching the photo.
 */
(function () {
    'use strict';
    const BOOT = window.DE_BOOT || {};
    const AJAX = window.DE_AJAX || 'ajax-design';
    const BASE = window.DE_BASE || '../';
    const $ = (id) => document.getElementById(id);
    const PROPS = ['deId', 'name', 'isFrame', 'frameShape', 'frameW', 'frameH', 'frameFit', 'frameRadius', 'deSrc', 'lockedDE', 'fxName', 'isBg', 'deFill', 'imgRadius', 'selectable', 'evented', 'hasControls', 'lockMovementX', 'lockMovementY', 'lockScalingX', 'lockScalingY', 'lockRotation'];

    const FONTS = ['Poppins', 'Montserrat', 'Open Sans', 'Roboto', 'Lato', 'Raleway', 'Nunito', 'Quicksand', 'Josefin Sans', 'Fredoka',
        'Playfair Display', 'Merriweather', 'DM Serif Display', 'Abril Fatface', 'Cinzel', 'Alfa Slab One',
        'Anton', 'Bebas Neue', 'Oswald', 'Archivo Black', 'Titan One', 'Luckiest Guy', 'Bangers', 'Righteous',
        'Lobster', 'Pacifico', 'Great Vibes', 'Dancing Script', 'Satisfy', 'Sacramento', 'Kaushan Script', 'Caveat',
        'Shadows Into Light', 'Permanent Marker', 'Amatic SC', 'Arial', 'Georgia', 'Times New Roman'];

    /* 100 extra Google Fonts — loaded on demand (only when used), so the editor stays fast. */
    const EXTRA_FONTS = ['Inter', 'Work Sans', 'Rubik', 'Karla', 'Mulish', 'Barlow', 'Barlow Condensed', 'Manrope', 'Outfit', 'Urbanist',
        'Sora', 'Lexend', 'Space Grotesk', 'DM Sans', 'Plus Jakarta Sans', 'Figtree', 'Kanit', 'Heebo', 'Cabin', 'Exo 2',
        'Titillium Web', 'Ubuntu', 'Source Sans 3', 'PT Sans', 'Noto Sans', 'Hind', 'Mukta', 'Archivo', 'Asap', 'Signika',
        'Varela Round', 'Comfortaa', 'Baloo 2', 'Chewy', 'Bungee', 'Russo One', 'Staatliches', 'Teko', 'Fjalla One', 'Passion One',
        'Black Ops One', 'Bowlby One SC', 'Ultra', 'Rammetto One', 'Paytone One', 'Carter One', 'Changa One', 'Boogaloo', 'Chango', 'Monoton',
        'Audiowide', 'Orbitron', 'Press Start 2P', 'Special Elite', 'Courier Prime', 'Space Mono', 'IBM Plex Mono', 'Roboto Slab', 'Zilla Slab', 'Arvo',
        'Bitter', 'Crete Round', 'Libre Baskerville', 'Lora', 'Cormorant Garamond', 'EB Garamond', 'Crimson Text', 'Noto Serif', 'PT Serif', 'Prata',
        'Marcellus', 'Cormorant', 'Italiana', 'Bodoni Moda', 'Yeseva One', 'Gloock', 'Fraunces', 'Young Serif', 'Rozha One', 'Limelight',
        'Poiret One', 'Josefin Slab', 'Parisienne', 'Allura', 'Alex Brush', 'Tangerine', 'Pinyon Script', 'Yellowtail', 'Cookie', 'Courgette',
        'Kalam', 'Handlee', 'Indie Flower', 'Gloria Hallelujah', 'Patrick Hand', 'Architects Daughter', 'Rock Salt', 'Homemade Apple', 'Reenie Beanie', 'Covered By Your Grace'];

    /* 100 more ready-made text styles: [text, font, colour, effect, extra] */
    const MORE_STYLES = [
        ['SUMMER SALE', 'Bungee', '#f97316', 'thick', { stroke: '#7c2d12', shadow: '#7c2d12' }],
        ['Easy Recipes', 'Pacifico', '#16a34a', 'shadow', {}],
        ['HOME DECOR', 'Staatliches', '#111827', 'none', { charSpacing: 300 }],
        ['Cozy Vibes', 'Courgette', '#92400e', 'none', {}],
        ['50% OFF', 'Russo One', '#dc2626', 'outline', { stroke: '#fde047' }],
        ['Minimal', 'Inter', '#111827', 'none', { charSpacing: 800, fontWeight: 300 }],
        ['DIY Ideas', 'Chewy', '#7c3aed', 'splice', { stroke: '#111111', shadow: '#f472b6' }],
        ['Wedding Day', 'Pinyon Script', '#9d174d', 'none', {}],
        ['TOP 10', 'Anton', '#ffffff', 'outline', { stroke: '#111111' }],
        ['Fresh & Healthy', 'Quicksand', '#15803d', 'none', { fontWeight: 700 }],
        ['GAME ON', 'Black Ops One', '#facc15', 'shadow', {}],
        ['Classic Serif', 'Libre Baskerville', '#1f2937', 'none', {}],
        ['Vintage Style', 'Special Elite', '#44403c', 'none', {}],
        ['RETRO 80s', 'Monoton', '#ec4899', 'neon', { shadow: '#ec4899' }],
        ['Handmade', 'Indie Flower', '#b45309', 'none', {}],
        ['LUXURY', 'Cinzel', '#b8860b', 'lift', { charSpacing: 400 }],
        ['Travel Guide', 'Yellowtail', '#0e7490', 'shadow', {}],
        ['BIG NEWS', 'Bowlby One SC', '#111827', 'highlight', { bg: '#a5f3fc' }],
        ['Soft Pastel', 'Comfortaa', '#db2777', 'none', { fontWeight: 700 }],
        ['NEW POST', 'Bebas Neue', '#ffffff', 'highlight', { bg: '#111827', charSpacing: 200 }],
        ['Garden Party', 'Parisienne', '#166534', 'none', {}],
        ['TECH', 'Orbitron', '#22d3ee', 'neon', { shadow: '#0891b2' }],
        ['Pixel Game', 'Press Start 2P', '#16a34a', 'thick', { stroke: '#052e16', shadow: '#052e16', fontSize: 70 }],
        ['Bold Statement', 'Archivo Black', '#111827', 'none', {}],
        ['Sweet Treats', 'Cookie', '#be185d', 'shadow', {}],
        ['WORKOUT', 'Teko', '#ef4444', 'none', { charSpacing: 150 }],
        ['Book Club', 'Lora', '#374151', 'none', { fontStyle: 'italic' }],
        ['Mom Life', 'Kalam', '#7c2d12', 'none', {}],
        ['GIVEAWAY', 'Passion One', '#fde047', 'thick', { stroke: '#7c3aed', shadow: '#4c1d95' }],
        ['Elegant Title', 'Cormorant Garamond', '#1f2937', 'none', { charSpacing: 100 }],
        ['Tiny Notes', 'Patrick Hand', '#1d4ed8', 'none', {}],
        ['FLASH DEAL', 'Fjalla One', '#ffffff', 'highlight', { bg: '#dc2626' }],
        ['Rustic Farm', 'Arvo', '#78350f', 'none', {}],
        ['Dream Big', 'Alex Brush', '#7c3aed', 'shadow', {}],
        ['STREET', 'Rock Salt', '#111827', 'none', { fontSize: 90 }],
        ['Modern Clean', 'Outfit', '#0f172a', 'none', { fontWeight: 700 }],
        ['KIDS FUN', 'Boogaloo', '#f59e0b', 'thick', { stroke: '#1e3a8a', shadow: '#1e3a8a' }],
        ['Spa Day', 'Tangerine', '#0f766e', 'none', { fontSize: 170 }],
        ['FITNESS', 'Barlow Condensed', '#111827', 'none', { charSpacing: 250 }],
        ['Christmas', 'Allura', '#b91c1c', 'shadow', {}],
        ['HALLOWEEN', 'Ultra', '#f97316', 'outline', { stroke: '#111111' }],
        ['Fall Favorites', 'Fraunces', '#9a3412', 'none', { fontStyle: 'italic' }],
        ['Spring Bloom', 'Great Vibes', '#db2777', 'none', {}],
        ['LIMITED', 'Limelight', '#111827', 'none', { charSpacing: 200 }],
        ['Journal', 'Homemade Apple', '#334155', 'none', { fontSize: 90 }],
        ['Coffee Time', 'Satisfy', '#6b3f1d', 'lift', {}],
        ['BEAUTY', 'Bodoni Moda', '#111827', 'none', { charSpacing: 500 }],
        ['Hair Goals', 'Dancing Script', '#be123c', 'none', { fontWeight: 700 }],
        ['FASHION WEEK', 'Italiana', '#111827', 'none', { charSpacing: 350 }],
        ['Budget Tips', 'Manrope', '#065f46', 'highlight', { bg: '#bbf7d0', fontWeight: 700 }],
        ['MONEY', 'Rubik', '#15803d', 'thick', { stroke: '#052e16', shadow: '#052e16', fontWeight: 700 }],
        ['Study Notes', 'Architects Daughter', '#1e40af', 'none', {}],
        ['Cute Pets', 'Baloo 2', '#ea580c', 'none', { fontWeight: 700 }],
        ['BREAKING', 'Oswald', '#ffffff', 'highlight', { bg: '#b91c1c', fontWeight: 700 }],
        ['Wanderlust', 'Sacramento', '#0369a1', 'none', { fontSize: 150 }],
        ['OPEN HOUSE', 'Josefin Sans', '#1f2937', 'none', { charSpacing: 300, fontWeight: 700 }],
        ['Meal Prep', 'Varela Round', '#16a34a', 'none', {}],
        ['POWER', 'Changa One', '#ffffff', 'thick', { stroke: '#1d4ed8', shadow: '#1e3a8a' }],
        ['Poetry', 'EB Garamond', '#3f3f46', 'none', { fontStyle: 'italic' }],
        ['Celebrate!', 'Carter One', '#e11d48', 'splice', { stroke: '#111111', shadow: '#fde047' }],
        ['NIGHT OUT', 'Audiowide', '#e879f9', 'neon', { shadow: '#a21caf' }],
        ['Recipe Card', 'Crete Round', '#9a3412', 'none', {}],
        ['LOVE', 'Rozha One', '#e11d48', 'lift', {}],
        ['Self Care', 'Poiret One', '#7c3aed', 'none', { fontSize: 120 }],
        ['HOW TO', 'Paytone One', '#0f172a', 'highlight', { bg: '#fde047' }],
        ['Printable', 'Handlee', '#0f766e', 'none', {}],
        ['PODCAST', 'Space Grotesk', '#ffffff', 'highlight', { bg: '#7c3aed', fontWeight: 700 }],
        ['Code & Coffee', 'Space Mono', '#0f172a', 'none', {}],
        ['Typewriter', 'Courier Prime', '#292524', 'none', {}],
        ['GRAND OPENING', 'Yeseva One', '#b45309', 'none', {}],
        ['Beach Day', 'Chango', '#0ea5e9', 'thick', { stroke: '#0c4a6e', shadow: '#0c4a6e', fontSize: 90 }],
        ['Autumn', 'Gloock', '#9a3412', 'none', {}],
        ['Sunday Brunch', 'Young Serif', '#be185d', 'none', {}],
        ['WEBINAR', 'Lexend', '#1d4ed8', 'none', { fontWeight: 700, charSpacing: 120 }],
        ['Free Download', 'Plus Jakarta Sans', '#ffffff', 'highlight', { bg: '#16a34a', fontWeight: 700 }],
        ['Quote of the Day', 'Prata', '#1f2937', 'none', {}],
        ['Before & After', 'Figtree', '#0f172a', 'none', { fontWeight: 700 }],
        ['NEON NIGHTS', 'Righteous', '#fef9c3', 'neon', { shadow: '#f59e0b' }],
        ['Little Things', 'Reenie Beanie', '#374151', 'none', { fontSize: 140 }],
        ['Thank You', 'Covered By Your Grace', '#9d174d', 'none', { fontSize: 130 }],
        ['PREMIUM', 'Marcellus', '#b8860b', 'none', { charSpacing: 300 }],
        ['Gallery', 'Cormorant', '#111827', 'none', { fontStyle: 'italic' }],
        ['Tutorial', 'Titillium Web', '#1e40af', 'none', { fontWeight: 700 }],
        ['CHALLENGE', 'Kanit', '#ffffff', 'outline', { stroke: '#dc2626', fontWeight: 700 }],
        ['Kitchen Hacks', 'Mukta', '#b45309', 'highlight', { bg: '#fef3c7', fontWeight: 700 }],
        ['DECOR', 'Bitter', '#1f2937', 'none', { charSpacing: 400 }],
        ['Crafts', 'Gloria Hallelujah', '#9333ea', 'none', {}],
        ['GOLDEN HOUR', 'Zilla Slab', '#d97706', 'lift', { fontWeight: 700 }],
        ['Pure & Simple', 'Karla', '#57534e', 'none', {}],
        ['Adventure', 'Exo 2', '#0f766e', 'shadow', { fontWeight: 700 }],
        ['SHOP NOW', 'Work Sans', '#ffffff', 'highlight', { bg: '#db2777', fontWeight: 700 }],
        ['Bookmark This', 'Urbanist', '#0f172a', 'none', { fontWeight: 700 }],
        ['Nature Walk', 'Cabin', '#166534', 'none', {}],
        ['WIN BIG', 'Rammetto One', '#fde047', 'thick', { stroke: '#b91c1c', shadow: '#7f1d1d' }],
        ['Hand Lettered', 'Caveat', '#be123c', 'none', { fontWeight: 700 }],
        ['Clean Layout', 'DM Sans', '#111827', 'none', { fontWeight: 700 }],
        ['The Editorial', 'Playfair Display', '#111827', 'none', { fontWeight: 900 }],
        ['Save for Later', 'Sora', '#ffffff', 'highlight', { bg: '#0f172a' }],
        ['STAY WILD', 'Permanent Marker', '#16a34a', 'shadow', {}],
        ['Hello Friend', 'Fredoka', '#f97316', 'splice', { stroke: '#111111', shadow: '#38bdf8' }],
    ];

    /* =================================================== state */
    const design = { id: BOOT.id || 0, w: BOOT.width || 1000, h: BOOT.height || 1500, title: BOOT.title || 'Untitled design' };
    let zoom = 1;
    let dirty = false;
    let loading = false;
    let undoStack = [];   // per page — swapped when you switch pages
    let redoStack = [];
    let clipboard = null;
    let guides = { v: false, h: false };
    let pages = [];      // Canva-style pages: { pid, name, json, preview, undo, redo }
    let cur = 0;         // index of the page on the live canvas
    let crop = null;     // active crop session (normal images)
    const pagesEl = $('dePages');
    const canvasBox = $('deCanvasBox');

    const canvas = new fabric.Canvas('deCanvas', {
        preserveObjectStacking: true, backgroundColor: '#ffffff', selectionColor: 'rgba(124,58,237,0.08)',
        selectionBorderColor: '#7c3aed', stopContextMenu: true, fireRightClick: false,
    });
    fabric.Object.prototype.set({
        transparentCorners: false, cornerColor: '#ffffff', cornerStrokeColor: '#7c3aed', borderColor: '#7c3aed',
        cornerSize: 12, cornerStyle: 'circle', borderScaleFactor: 1.5, padding: 2,
    });
    fabric.Object.prototype.objectCaching = true;
    window.DE_CANVAS = canvas; // handy for debugging from the browser console

    /* =================================================== helpers */
    function toast(msg, ms) {
        const t = $('deToast');
        t.textContent = msg; t.classList.add('show');
        clearTimeout(toast._t); toast._t = setTimeout(() => t.classList.remove('show'), ms || 2600);
    }
    function uid() { return 'o' + Math.random().toString(36).slice(2, 9); }
    function post(data, isForm) {
        const fd = isForm ? data : new FormData();
        if (!isForm) Object.keys(data).forEach((k) => fd.append(k, data[k]));
        return fetch(AJAX, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then((r) => r.text()).then((t) => { try { return JSON.parse(t); } catch (e) { return { ok: false, error: 'Server error.' }; } })
            .catch(() => ({ ok: false, error: 'Network error.' }));
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function url(path) { return /^(https?:|data:|\/)/.test(path) ? path : BASE + path; }
    function active() { return canvas.getActiveObject(); }
    function isText(o) { return o && (o.type === 'textbox' || o.type === 'i-text' || o.type === 'text'); }
    function isImage(o) { return o && o.type === 'image'; }
    function isEmptyFrame(o) { return o && o.isFrame && o.type !== 'image'; }
    function isLine(o) { return o && (o.type === 'line' || (o.type === 'path' && o.name && /line|arrow/i.test(o.name) && !o.fill)); }
    function markDirty() { dirty = true; $('deSaveState').textContent = 'Unsaved changes'; }
    const fontCss = {};
    /** Adds the Google Fonts stylesheet for extra fonts (one request for many families). */
    function requireFontCss(families) {
        const need = families.filter((f) => EXTRA_FONTS.includes(f) && !fontCss[f]);
        if (!need.length) return Promise.all(families.map((f) => fontCss[f]).filter(Boolean));
        const p = new Promise((resolve) => {
            const l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = 'https://fonts.googleapis.com/css2?' + need.map((f) => 'family=' + encodeURIComponent(f).replace(/%20/g, '+')).join('&') + '&display=swap';
            l.onload = () => resolve(); l.onerror = () => resolve();
            document.head.appendChild(l);
        });
        need.forEach((f) => { fontCss[f] = p; });
        return p;
    }
    function loadFont(family) {
        if (!document.fonts || !family || /^(Arial|Georgia|Times New Roman)$/.test(family)) return Promise.resolve();
        if (EXTRA_FONTS.includes(family)) {
            return requireFontCss([family]).then(() => Promise.all([
                document.fonts.load('400 40px "' + family + '"'), document.fonts.load('700 40px "' + family + '"'),
            ])).catch(() => {});
        }
        return Promise.all([
            document.fonts.load('400 40px "' + family + '"'), document.fonts.load('700 40px "' + family + '"'),
            document.fonts.load('italic 400 40px "' + family + '"'),
        ]).catch(() => {});
    }
    function refreshTextObj(o) {
        if (!isText(o)) return;
        o.initDimensions && o.initDimensions();
        o.setCoords(); o.dirty = true;
    }
    function ensureFonts(objs) {
        const fams = new Set();
        (function walk(list) { list.forEach((o) => { if (isText(o)) fams.add(o.fontFamily); if (o._objects) walk(o._objects); }); })(objs);
        return Promise.all([...fams].map(loadFont)).then(() => {
            (function walk(list) { list.forEach((o) => { if (isText(o)) refreshTextObj(o); if (o._objects) walk(o._objects); }); })(objs);
            canvas.requestRenderAll();
        });
    }

    /* =================================================== zoom & size */
    function applyZoom(z) {
        zoom = Math.max(0.05, Math.min(4, z));
        canvas.setDimensions({ width: Math.round(design.w * zoom), height: Math.round(design.h * zoom) });
        canvas.setZoom(zoom);
        $('deZoomLabel').textContent = Math.round(zoom * 100) + '%';
        layoutPages();
        canvas.calcOffset();
        canvas.requestRenderAll();
    }
    function fitZoom() {
        const stage = $('deStage');
        const z = Math.min((stage.clientWidth - 60) / design.w, (stage.clientHeight - 120) / design.h);
        applyZoom(z);
    }
    /** Scales one page's saved JSON from the old page size to a new one (same maths as before). */
    function scaleJSON(json, ow, oh, w, h) {
        const s = Math.min(w / ow, h / oh);
        const offX = (w - ow * s) / 2, offY = (h - oh * s) / 2;
        (json.objects || []).forEach((o) => {
            if (o.isBg) { o.left = 0; o.top = 0; o.width = w; o.height = h; return; }
            o.left = o.left * s + offX; o.top = o.top * s + offY;
            o.scaleX = (o.scaleX || 1) * s; o.scaleY = (o.scaleY || 1) * s;
            if (o.isFrame && o.type === 'image') { if (o.frameW) o.frameW *= s; if (o.frameH) o.frameH *= s; }
        });
        return json;
    }
    function setDesignSize(w, h, scaleContent) {
        commitActive();
        const ow = design.w, oh = design.h;
        pages.forEach((p) => {
            if (scaleContent) scaleJSON(p.json, ow, oh, w, h);
            else (p.json.objects || []).forEach((o) => { if (o.isBg) { o.width = w; o.height = h; } });
            p.undo = []; p.redo = [];   // history restarts after a resize (all pages share one size)
        });
        design.w = w; design.h = h;
        $('deSizeLabel').textContent = w + ' × ' + h;
        fitZoom();
        showPage(cur, true).then(() => { markDirty(); refreshAllPreviews(); });
    }

    /* =================================================== history */
    function snapshot() { return JSON.stringify({ w: design.w, h: design.h, c: canvas.toJSON(PROPS) }); }
    function pushHistory() {
        if (loading || crop) return;
        const s = snapshot();
        if (undoStack.length && undoStack[undoStack.length - 1] === s) return;
        undoStack.push(s);
        if (undoStack.length > 80) undoStack.shift();
        redoStack.length = 0;
        markDirty();
        renderLayers();
    }
    let histTimer = null;
    function pushHistorySoon() { clearTimeout(histTimer); histTimer = setTimeout(pushHistory, 250); }
    function restore(s) {
        const d = JSON.parse(s);
        loading = true;
        design.w = d.w; design.h = d.h;
        $('deSizeLabel').textContent = d.w + ' × ' + d.h;
        canvas.loadFromJSON(d.c, () => {
            applyZoom(zoom);
            ensureFonts(canvas.getObjects());
            loading = false;
            markDirty(); renderLayers(); updateCtx();
        });
    }
    function undo() { if (undoStack.length < 2) return; redoStack.push(undoStack.pop()); restore(undoStack[undoStack.length - 1]); }
    function redo() { if (!redoStack.length) return; const s = redoStack.pop(); undoStack.push(s); restore(s); }

    canvas.on('object:added', (e) => { if (e.target && !e.target.deId) e.target.deId = uid(); pushHistorySoon(); });
    canvas.on('object:removed', pushHistorySoon);
    canvas.on('object:modified', (e) => { bakeFrameScale(e.target); pushHistorySoon(); updateCtx(); });
    canvas.on('text:changed', pushHistorySoon);

    /* =================================================== snapping guides */
    canvas.on('object:moving', (e) => {
        const o = e.target;
        const c = o.getCenterPoint();
        const tol = 8 / zoom;
        guides = { v: false, h: false };
        if (Math.abs(c.x - design.w / 2) < tol) { o.setPositionByOrigin(new fabric.Point(design.w / 2, c.y), 'center', 'center'); guides.v = true; }
        const c2 = o.getCenterPoint();
        if (Math.abs(c2.y - design.h / 2) < tol) { o.setPositionByOrigin(new fabric.Point(c2.x, design.h / 2), 'center', 'center'); guides.h = true; }
    });
    canvas.on('mouse:up', () => { if (guides.v || guides.h) { guides = { v: false, h: false }; canvas.requestRenderAll(); } });
    canvas.on('after:render', () => {
        if (!guides.v && !guides.h) return;
        const ctx = canvas.contextContainer;
        ctx.save();
        ctx.strokeStyle = '#ec4899'; ctx.lineWidth = 1; ctx.setLineDash([5, 4]);
        if (guides.v) { ctx.beginPath(); ctx.moveTo(canvas.width / 2 + 0.5, 0); ctx.lineTo(canvas.width / 2 + 0.5, canvas.height); ctx.stroke(); }
        if (guides.h) { ctx.beginPath(); ctx.moveTo(0, canvas.height / 2 + 0.5); ctx.lineTo(canvas.width, canvas.height / 2 + 0.5); ctx.stroke(); }
        ctx.restore();
    });

    /* =================================================== frames */
    function framePlaceholder(opts) {
        const shape = opts.shape || 'rect';
        const w = opts.w, h = shape === 'circle' ? opts.w : opts.h;
        const r = shape === 'circle' ? w / 2 : (shape === 'rounded' ? (opts.radius || Math.min(w, h) * 0.08) : 0);
        const ph = new fabric.Rect({
            left: opts.left, top: opts.top, width: w, height: h, rx: r, ry: r,
            fill: '#e7e9ee', stroke: '#b3b9c4', strokeWidth: 2, strokeDashArray: [10, 8], strokeUniform: true,
            isFrame: true, frameShape: shape, frameRadius: shape === 'rounded' ? r : 0, name: 'Frame (' + shape + ')',
        });
        if (shape === 'arch') { ph.set({ rx: 0, ry: 0 }); ph.clipPath = archPath(w, h); }
        if (FRAME_PATHS[shape]) { ph.set({ rx: 0, ry: 0 }); ph.clipPath = pathClip(shape, w, h); }
        return ph;
    }
    /* Frame shapes as paths in a 0–100 box (M/L/C/Q/Z only, so they can be stretched to any frame size). */
    const FRAME_PATHS = {
        oval: 'M 100 50 C 100 77.6 77.6 100 50 100 C 22.4 100 0 77.6 0 50 C 0 22.4 22.4 0 50 0 C 77.6 0 100 22.4 100 50 Z',
        triangle: 'M 50 0 L 100 100 L 0 100 Z',
        diamond: 'M 50 0 L 100 50 L 50 100 L 0 50 Z',
        pentagon: 'M 50 0 L 100 38 L 81 100 L 19 100 L 0 38 Z',
        hexagon: 'M 25 0 L 75 0 L 100 50 L 75 100 L 25 100 L 0 50 Z',
        octagon: 'M 30 0 L 70 0 L 100 30 L 100 70 L 70 100 L 30 100 L 0 70 L 0 30 Z',
        star: 'M 50 0 L 61.8 35.5 L 100 36.3 L 69.1 58.8 L 80.9 95.1 L 50 73 L 19.1 95.1 L 30.9 58.8 L 0 36.3 L 38.2 35.5 Z',
        heart: 'M 50 100 C 20 78 0 58 0 32 C 0 13 13 0 29 0 C 39 0 46 6 50 15 C 54 6 61 0 71 0 C 87 0 100 13 100 32 C 100 58 80 78 50 100 Z',
        blob: 'M 52 1 C 75 0 97 16 99 42 C 101 66 86 95 58 99 C 31 103 4 86 1 60 C -2 34 27 2 52 1 Z',
        cloud: 'M 25 100 C 10 100 0 88 0 74 C 0 60 10 50 23 49 C 25 26 40 10 58 10 C 74 10 86 22 89 39 C 96 41 100 52 100 64 C 100 83 90 100 75 100 Z',
        ticket: 'M 0 0 L 100 0 L 100 38 C 94 38 90 43 90 50 C 90 57 94 62 100 62 L 100 100 L 0 100 L 0 62 C 6 62 10 57 10 50 C 10 43 6 38 0 38 Z',
        scallop: (() => { let d = ''; const n = 16; for (let i = 0; i <= n; i++) { const a = -Math.PI / 2 + i * 2 * Math.PI / n, a2 = a - Math.PI / n, r = 50, rc = 58; const x = 50 + r * Math.cos(a), y = 50 + r * Math.sin(a); if (i === 0) d += `M ${x.toFixed(1)} ${y.toFixed(1)} `; else d += `Q ${(50 + rc * Math.cos(a2)).toFixed(1)} ${(50 + rc * Math.sin(a2)).toFixed(1)} ${x.toFixed(1)} ${y.toFixed(1)} `; } return d + 'Z'; })(),
        leaf: 'M 0 100 C 0 40 40 0 100 0 C 100 60 60 100 0 100 Z',
    };
    /** Stretches a 0–100 unit path to w×h, centred on (cx, cy) (default: centred on 0,0 for clip paths). */
    function unitPath(d, w, h, cx, cy) {
        cx = cx || 0; cy = cy || 0;
        const toks = d.trim().split(/[\s,]+/);
        let axis = 0;
        return toks.map((t) => {
            if (/^[A-Za-z]$/.test(t)) { axis = 0; return t; }
            const v = +t;
            const out = axis === 0 ? (v / 100 * w - w / 2 + cx) : (v / 100 * h - h / 2 + cy);
            axis ^= 1;
            return out.toFixed(2);
        }).join(' ');
    }
    function pathClip(shape, w, h) {
        return new fabric.Path(unitPath(FRAME_PATHS[shape], w, h), { originX: 'center', originY: 'center', left: 0, top: 0 });
    }
    function archPath(w, h) {
        const r = Math.min(w / 2, h);
        const d = `M ${-w / 2} ${h / 2} L ${-w / 2} ${-h / 2 + r} A ${w / 2} ${r} 0 0 1 ${w / 2} ${-h / 2 + r} L ${w / 2} ${h / 2} Z`;
        return new fabric.Path(d, { originX: 'center', originY: 'center', left: 0, top: 0 });
    }
    /** Bounding box (display units) of a frame (placeholder or filled). */
    function frameBox(o) {
        const c = o.getCenterPoint();
        const w = o.type === 'image' ? o.frameW : o.getScaledWidth();
        const h = o.type === 'image' ? o.frameH : o.getScaledHeight();
        return { cx: c.x, cy: c.y, w, h, angle: o.angle || 0 };
    }
    /** Crops + scales an image element to cover a frame box, with zoom/position fit params. */
    function cropProps(el, box, fit) {
        const sw = el.naturalWidth || el.width, sh = el.naturalHeight || el.height;
        const z = Math.max(1, fit.zoom || 1);
        const s = Math.max(box.w / sw, box.h / sh) * z;
        const cw = box.w / s, ch = box.h / s;
        return { cropX: (sw - cw) * (fit.fx ?? 0.5), cropY: (sh - ch) * (fit.fy ?? 0.5), width: cw, height: ch, scaleX: s, scaleY: s };
    }
    function frameClip(shape, cw, ch, s, radius) {
        if (shape === 'circle') return new fabric.Ellipse({ rx: cw / 2, ry: ch / 2, originX: 'center', originY: 'center', left: 0, top: 0 });
        if (shape === 'rounded') { const r = (radius || Math.min(cw, ch) * s * 0.08) / s; return new fabric.Rect({ width: cw, height: ch, rx: r, ry: r, originX: 'center', originY: 'center', left: 0, top: 0 }); }
        if (shape === 'arch') return archPath(cw, ch);
        if (FRAME_PATHS[shape]) return pathClip(shape, cw, ch);
        return null;
    }
    function fillFrame(frame, src) {
        return new Promise((resolve) => {
            fabric.util.loadImage(url(src), (el) => {
                if (!el) { toast('Could not load that image.'); return resolve(null); }
                const box = frameBox(frame);
                const fit = frame.frameFit || { zoom: 1, fx: 0.5, fy: 0.5 };
                const cp = cropProps(el, box, fit);
                const img = new fabric.Image(el, Object.assign({
                    originX: 'center', originY: 'center', left: box.cx, top: box.cy, angle: box.angle,
                    isFrame: true, frameShape: frame.frameShape, frameW: box.w, frameH: box.h, frameFit: fit, frameRadius: frame.frameRadius || 0,
                    deSrc: src, name: 'Photo in frame', opacity: frame.opacity ?? 1, lockScalingFlip: true,
                }, cp));
                img.clipPath = frameClip(frame.frameShape, cp.width, cp.height, cp.scaleX, frame.frameRadius);
                const idx = canvas.getObjects().indexOf(frame);
                loading = true;
                canvas.remove(frame);
                canvas.insertAt(img, Math.max(0, idx));
                loading = false;
                canvas.setActiveObject(img);
                canvas.requestRenderAll();
                pushHistory();
                resolve(img);
            }, null, 'anonymous');
        });
    }
    /** Re-crops a filled frame to a new display size / fit without stretching the photo. */
    function refitFrame(img, w, h, fit) {
        const el = img.getElement();
        const box = { w: w, h: h };
        img.frameW = w; img.frameH = h;
        img.frameFit = fit || img.frameFit || { zoom: 1, fx: 0.5, fy: 0.5 };
        const cp = cropProps(el, box, img.frameFit);
        img.set(cp);
        img.clipPath = frameClip(img.frameShape, cp.width, cp.height, cp.scaleX, img.frameRadius);
        img.setCoords(); img.dirty = true;
        canvas.requestRenderAll();
    }
    /** After resizing, bake scale into width/height for placeholders & rounded images (keeps corner radius right). */
    function bakeFrameScale(o) {
        if (!o) return;
        // a resized photo-frame: re-crop to the new frame size instead of stretching the photo
        if (o.type === 'image' && o.isFrame) {
            const w = o.width * o.scaleX, h = o.height * o.scaleY;
            if (Math.abs(w - o.frameW) > 0.5 || Math.abs(h - o.frameH) > 0.5 || Math.abs(o.scaleX - o.scaleY) > 1e-6) refitFrame(o, w, h);
            return;
        }
        if (isEmptyFrame(o) && (o.scaleX !== 1 || o.scaleY !== 1)) {
            const w = o.width * o.scaleX, h = o.height * o.scaleY;
            o.set({ width: w, height: h, scaleX: 1, scaleY: 1 });
            if (o.frameShape === 'circle') { const d = Math.min(w, h); o.set({ width: d, height: d, rx: d / 2, ry: d / 2 }); }
            if (o.frameShape === 'arch') o.clipPath = archPath(o.width, o.height);
            if (FRAME_PATHS[o.frameShape]) o.clipPath = pathClip(o.frameShape, o.width, o.height);
            o.setCoords();
        }
    }
    function emptyFrame(img) {
        const box = frameBox(img);
        const ph = framePlaceholder({ shape: img.frameShape, w: box.w, h: box.h, left: 0, top: 0, radius: img.frameRadius });
        ph.set({ originX: 'center', originY: 'center', left: box.cx, top: box.cy, angle: box.angle });
        const idx = canvas.getObjects().indexOf(img);
        canvas.remove(img);
        canvas.insertAt(ph, idx);
        canvas.setActiveObject(ph);
        pushHistory();
    }
    function frameAt(pt) {
        const objs = canvas.getObjects();
        for (let i = objs.length - 1; i >= 0; i--) {
            const o = objs[i];
            if (o.isFrame && o.visible !== false && o.containsPoint(pt, null, true, true)) return o;   // scene coords (zoom-independent)
        }
        return null;
    }

    /* =================================================== adding things */
    function center(o) {
        o.set({ originX: 'center', originY: 'center', left: design.w / 2, top: design.h / 2 });
        canvas.add(o); canvas.setActiveObject(o); canvas.requestRenderAll();
    }
    function addImage(src, at) {
        fabric.Image.fromURL(url(src), (img) => {
            if (!img || !img.width) { toast('Could not load that image.'); return; }
            const s = Math.min(design.w * 0.6 / img.width, design.h * 0.6 / img.height, 1);
            img.set({ scaleX: s, scaleY: s, deSrc: src, name: 'Image' });
            if (at) { img.set({ originX: 'center', originY: 'center', left: at.x, top: at.y }); canvas.add(img); canvas.setActiveObject(img); }
            else center(img);
        }, { crossOrigin: 'anonymous' });
    }
    function useImage(src, at) {
        const a = active();
        if (at) { const f = frameAt(at); if (f) return fillFrame(f, src); return addImage(src, at); }
        if (a && a.isFrame) return fillFrame(a, src);
        addImage(src);
    }
    function addText(kind, preset) {
        const p = Object.assign({
            heading: { text: 'Add a heading', fontSize: Math.round(design.w * 0.09), fontFamily: 'Poppins', fontWeight: 700 },
            sub: { text: 'Add a subheading', fontSize: Math.round(design.w * 0.055), fontFamily: 'Poppins', fontWeight: 600 },
            body: { text: 'Add a little bit of body text', fontSize: Math.round(design.w * 0.034), fontFamily: 'Poppins', fontWeight: 400 },
        }[kind] || {}, preset || {});
        const t = new fabric.Textbox(p.text, Object.assign({
            width: design.w * 0.8, textAlign: 'center', fill: '#111111', lineHeight: 1.15, charSpacing: 0, name: 'Text',
            splitByGrapheme: false,
        }, p));
        loadFont(t.fontFamily).then(() => { refreshTextObj(t); canvas.requestRenderAll(); });
        center(t);
        if (preset && preset.fxName) applyEffect(preset.fxName, t, preset);
    }

    /* =================================================== shapes & lines */
    function poly(pts, fill) { return new fabric.Polygon(pts.map(([x, y]) => ({ x, y })), { fill }); }
    function regular(n, r, fill) { const pts = []; for (let i = 0; i < n; i++) { const a = -Math.PI / 2 + i * 2 * Math.PI / n; pts.push([r + r * Math.cos(a), r + r * Math.sin(a)]); } return poly(pts, fill); }
    function star(n, ro, ri, fill) { const pts = []; for (let i = 0; i < n * 2; i++) { const r = i % 2 ? ri : ro; const a = -Math.PI / 2 + i * Math.PI / n; pts.push([ro + r * Math.cos(a), ro + r * Math.sin(a)]); } return poly(pts, fill); }
    function addShape(make, name) {
        const o = make();
        o.set({ name: name, strokeUniform: true });
        if (!o.stroke) o.set({ stroke: null, strokeWidth: 0 });
        const s = Math.min(1, design.w * 0.4 / (o.width || 300));
        o.set({ scaleX: s, scaleY: s });
        center(o);
    }

    /* =================================================== text effects */
    function applyEffect(name, o, opt) {
        o = o || active();
        if (!isText(o)) return;
        opt = opt || {};
        const baseFill = (o.fxName === 'hollow' ? o.deFill : o.fill) || '#111111';
        o.set({ shadow: null, stroke: null, strokeWidth: 0, textBackgroundColor: '', paintFirst: 'fill', fill: baseFill });
        const sc = $('deShadowC').value;
        // default outline colour contrasts with the text colour (dark text → yellow, light text → near-black)
        const lum = (() => { const h = toHex(baseFill).slice(1); const n = parseInt(h, 16); return ((n >> 16 & 255) * 0.299 + (n >> 8 & 255) * 0.587 + (n & 255) * 0.114) / 255; })();
        const picked = $('deStrokeC').value;
        const stc = (picked && picked.toLowerCase() !== toHex(baseFill).toLowerCase()) ? picked : (lum < 0.5 ? '#facc15' : '#111111');
        const fs = o.fontSize;
        switch (name) {
            case 'shadow': o.set({ shadow: new fabric.Shadow({ color: hexA(sc, 0.45), blur: fs * 0.12, offsetX: fs * 0.06, offsetY: fs * 0.06 }) }); break;
            case 'lift': o.set({ shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.35)', blur: fs * 0.35, offsetX: 0, offsetY: fs * 0.08 }) }); break;
            case 'hollow': o.set({ deFill: baseFill, fill: 'rgba(0,0,0,0)', stroke: opt.stroke || baseFill, strokeWidth: Math.max(1.5, fs * 0.03) }); break;
            case 'outline': o.set({ stroke: opt.stroke || stc, strokeWidth: opt.strokeWidth || Math.max(2, fs * 0.08), paintFirst: 'stroke', strokeLineJoin: 'round' }); break;
            case 'splice': o.set({ stroke: opt.stroke || stc, strokeWidth: Math.max(1.5, fs * 0.025), shadow: new fabric.Shadow({ color: opt.shadow || '#ff4d6d', blur: 0, offsetX: fs * 0.07, offsetY: fs * 0.07 }) }); break;
            case 'neon': o.set({ shadow: new fabric.Shadow({ color: opt.shadow || sc || '#ff2bd6', blur: fs * 0.45, offsetX: 0, offsetY: 0 }) }); break;
            case 'thick': o.set({ stroke: opt.stroke || stc, strokeWidth: opt.strokeWidth || Math.max(3, fs * 0.1), paintFirst: 'stroke', strokeLineJoin: 'round', shadow: new fabric.Shadow({ color: opt.shadow || stc, blur: 0, offsetX: fs * 0.07, offsetY: fs * 0.09 }) }); break;
            case 'highlight': o.set({ textBackgroundColor: opt.bg || $('deHighlightC').value }); break;
        }
        o.fxName = name === 'none' ? '' : name;
        refreshTextObj(o);
        canvas.requestRenderAll();
        pushHistory(); updateCtx();
    }
    function hexA(hex, a) { const n = parseInt(hex.replace('#', ''), 16); return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`; }
    const TEXT_STYLES = [
        { label: 'BOLD HEADLINE', text: 'BOLD HEADLINE', fontFamily: 'Anton', fill: '#ffffff', fxName: 'outline', stroke: '#111111', fontSize: 120 },
        { label: 'Script Accent', text: 'Script Accent', fontFamily: 'Great Vibes', fill: '#b45309', fontSize: 130 },
        { label: 'NEON GLOW', text: 'NEON GLOW', fontFamily: 'Righteous', fill: '#ffe4fa', fxName: 'neon', shadow: '#ff2bd6', fontSize: 110 },
        { label: '3D POP', text: '3D POP', fontFamily: 'Luckiest Guy', fill: '#ffd400', fxName: 'thick', stroke: '#c81e1e', shadow: '#7f1212', fontSize: 140 },
        { label: 'Elegant Serif', text: 'Elegant Serif', fontFamily: 'Playfair Display', fontStyle: 'italic', fill: '#1f2937', fontSize: 100 },
        { label: 'RETRO SPLICE', text: 'RETRO SPLICE', fontFamily: 'Righteous', fill: '#fde68a', fxName: 'splice', stroke: '#111111', shadow: '#f97316', fontSize: 100 },
        { label: 'Marker Note', text: 'Marker Note', fontFamily: 'Permanent Marker', fill: '#111111', fontSize: 100 },
        { label: 'HIGHLIGHT', text: 'HIGHLIGHT', fontFamily: 'Montserrat', fontWeight: 800, fill: '#111111', fxName: 'highlight', bg: '#fde047', fontSize: 90 },
        { label: '25', text: '25', fontFamily: 'Bebas Neue', fill: '#ef4444', fontSize: 320 },
        { label: 'HOLLOW', text: 'HOLLOW', fontFamily: 'Archivo Black', fill: '#111111', fxName: 'hollow', fontSize: 120 },
        { label: 'handwritten', text: 'handwritten', fontFamily: 'Caveat', fontWeight: 700, fill: '#374151', fontSize: 110 },
        { label: 'S P A C E D', text: 'SPACED OUT', fontFamily: 'Montserrat', fill: '#111111', charSpacing: 600, fontSize: 60 },
        { label: 'Lifted', text: 'Lifted Title', fontFamily: 'Poppins', fontWeight: 900, fill: '#ffffff', fxName: 'lift', fontSize: 110 },
        { label: 'Pacifico', text: 'Sweet & Fun', fontFamily: 'Pacifico', fill: '#db2777', fontSize: 100 },
    ].concat(MORE_STYLES.map(([text, font, fill, fx, x]) => Object.assign({
        label: text, text, fontFamily: font, fill, fxName: fx === 'none' ? '' : fx,
        fontSize: x.fontSize || Math.round(Math.max(60, Math.min(150, 1100 / Math.max(6, text.length)))),
    }, x)));

    /* =================================================== context toolbar */
    function showGroup(name, on) { document.querySelector(`[data-ctx="${name}"]`).hidden = !on; }
    function updateCtx() {
        const o = active();
        if (crop) {
            ['common', 'text', 'shape', 'image', 'graphic'].forEach((g) => showGroup(g, false));
            $('deCtxEmpty').hidden = false;
            $('deCtxEmpty').textContent = 'Cropping — drag the corners, then click Done (Enter). Esc cancels.';
            return;
        }
        $('deCtxEmpty').textContent = 'Select an element to edit it · Double-click text to type · Double-click a photo to crop · Del to delete · Ctrl+D duplicate';
        $('deCtxEmpty').hidden = !!o;
        showGroup('common', !!o);
        showGroup('text', isText(o));
        const shapeLike = o && !isText(o) && !isImage(o) && o.type !== 'activeSelection' && o.type !== 'group' && !isEmptyFrame(o);
        showGroup('shape', !!(shapeLike || isEmptyFrame(o)));
        showGroup('image', isImage(o) || isEmptyFrame(o));
        showGroup('graphic', isGraphic(o));
        if (isGraphic(o)) updateGfxColors();
        if (!o) return;
        $('deGroup').hidden = o.type !== 'activeSelection';
        $('deUngroup').hidden = o.type !== 'group';
        $('deLock').textContent = o.lockedDE ? '🔓' : '🔒';
        $('deOpacity').value = o.opacity ?? 1; $('deOpacityOut').textContent = Math.round((o.opacity ?? 1) * 100) + '%';
        $('deAngle').value = Math.round(o.angle || 0);
        $('dePosX').value = Math.round(o.left); $('dePosY').value = Math.round(o.top);
        $('deSizeW').value = Math.round(o.type === 'image' && o.isFrame ? o.frameW : o.getScaledWidth());
        $('deSizeH').value = Math.round(o.type === 'image' && o.isFrame ? o.frameH : o.getScaledHeight());
        if (isText(o)) {
            $('deFont').value = o.fontFamily;
            $('deFontSize').value = Math.round(o.fontSize * (o.scaleY || 1));
            $('deTextColor').value = toHex(o.fxName === 'hollow' ? o.deFill : o.fill);
            $('deBold').classList.toggle('on', +o.fontWeight >= 600 || o.fontWeight === 'bold');
            $('deItalic').classList.toggle('on', o.fontStyle === 'italic');
            $('deUnderline').classList.toggle('on', !!o.underline);
            $('deStrike').classList.toggle('on', !!o.linethrough);
            document.querySelectorAll('[data-align]').forEach((b) => b.classList.toggle('on', b.dataset.align === o.textAlign));
            $('deCharSpacing').value = o.charSpacing || 0; $('deCharSpacingOut').textContent = o.charSpacing || 0;
            $('deLineHeight').value = o.lineHeight || 1.15; $('deLineHeightOut').textContent = (+o.lineHeight || 1.15).toFixed(2);
            $('deStrokeW').value = o.strokeWidth || 0; $('deStrokeWOut').textContent = o.strokeWidth || 0;
            if (o.stroke) $('deStrokeC').value = toHex(o.stroke);
            const sh = o.shadow;
            $('deShadowBlur').value = sh ? sh.blur : 0; $('deShadowBlurOut').textContent = sh ? Math.round(sh.blur) : 0;
            $('deShadowOff').value = sh ? Math.max(sh.offsetX, sh.offsetY) : 0; $('deShadowOffOut').textContent = sh ? Math.round(Math.max(sh.offsetX, sh.offsetY)) : 0;
            document.querySelectorAll('[data-fx]').forEach((b) => b.classList.toggle('on', (o.fxName || 'none') === b.dataset.fx));
        }
        if (shapeLike || isEmptyFrame(o)) {
            const line = isLine(o);
            $('deFill').value = toHex(line ? o.stroke : o.fill);
            $('deBorderC').value = toHex(o.stroke || '#000000');
            $('deBorderW').value = line ? o.strokeWidth : (o.strokeWidth || 0);
            $('deRadiusWrap').hidden = o.type !== 'rect';
            $('deRadius').value = Math.round(o.rx || 0);
            $('deDashed').checked = !!(o.strokeDashArray && o.strokeDashArray.length);
        }
        if (isImage(o) || isEmptyFrame(o)) {
            $('deFrameFitWrap').hidden = !(isImage(o) && o.isFrame);
            $('deImgRadiusWrap').hidden = !isImage(o) || o.isFrame;
            $('deSetBg').hidden = !isImage(o);
            $('deCropBtn').hidden = !(isImage(o) && !o.isFrame);
            $('deTintBtn').hidden = !(isImage(o) && !o.isFrame);
            $('deImgFlipH').hidden = !isImage(o);
            $('deReplaceImg').textContent = isEmptyFrame(o) ? '⬆ Add photo' : '🔁 Replace';
            if (isImage(o)) {
                const f = o.frameFit || { zoom: 1, fx: 0.5, fy: 0.5 };
                $('deFrameZoom').value = f.zoom; $('deFrameX').value = f.fx; $('deFrameY').value = f.fy;
                $('deImgRadius').value = o.imgRadius || 0;
                const fl = filtersOf(o);
                $('deBright').value = fl.b; $('deContrast').value = fl.c; $('deSaturate').value = fl.s; $('deGray').checked = fl.g; $('deSepia').checked = fl.p;
            }
        }
    }
    function toHex(c) {
        if (!c || typeof c !== 'string') return '#000000';
        if (c[0] === '#') return c.length === 4 ? '#' + c.slice(1).split('').map((x) => x + x).join('') : c.slice(0, 7);
        const m = c.match(/\d+(\.\d+)?/g);
        if (!m) return '#000000';
        return '#' + m.slice(0, 3).map((v) => (+v).toString(16).padStart(2, '0')).join('');
    }
    ['selection:created', 'selection:updated', 'selection:cleared'].forEach((ev) => canvas.on(ev, () => { updateCtx(); renderLayers(); }));
    canvas.on('object:moving', () => { const o = active(); if (o) { $('dePosX').value = Math.round(o.left); $('dePosY').value = Math.round(o.top); } });

    function setProp(fn) {
        const o = active();
        if (!o) return;
        const targets = o.type === 'activeSelection' ? o.getObjects() : [o];
        targets.forEach(fn);
        canvas.requestRenderAll();
        pushHistorySoon();
    }
    function eachText(fn) { setProp((o) => { if (isText(o)) { fn(o); refreshTextObj(o); } }); }

    // text controls
    const fontSel = $('deFont');
    fontSel.innerHTML = '<optgroup label="Popular">' + FONTS.map((f) => `<option value="${esc(f)}" style="font-family:'${esc(f)}'">${esc(f)}</option>`).join('') + '</optgroup>'
        + '<optgroup label="More fonts (100)">' + EXTRA_FONTS.slice().sort().map((f) => `<option value="${esc(f)}" style="font-family:'${esc(f)}'">${esc(f)}</option>`).join('') + '</optgroup>';
    fontSel.addEventListener('focus', () => requireFontCss(EXTRA_FONTS.slice(0, 50)).then(() => requireFontCss(EXTRA_FONTS.slice(50))), { once: true });
    fontSel.addEventListener('change', () => { const f = fontSel.value; loadFont(f).then(() => eachText((o) => o.set('fontFamily', f))); });
    $('deFontSize').addEventListener('input', (e) => eachText((o) => o.set({ fontSize: Math.max(4, +e.target.value / (o.scaleY || 1)) })));
    $('deTextColor').addEventListener('input', (e) => eachText((o) => { if (o.fxName === 'hollow') { o.deFill = e.target.value; o.set('stroke', e.target.value); } else o.set('fill', e.target.value); }));
    $('deBold').addEventListener('click', () => eachText((o) => o.set('fontWeight', (+o.fontWeight >= 600 || o.fontWeight === 'bold') ? 400 : 700)) || updateCtx());
    $('deItalic').addEventListener('click', () => eachText((o) => o.set('fontStyle', o.fontStyle === 'italic' ? 'normal' : 'italic')) || updateCtx());
    $('deUnderline').addEventListener('click', () => eachText((o) => o.set('underline', !o.underline)) || updateCtx());
    $('deStrike').addEventListener('click', () => eachText((o) => o.set('linethrough', !o.linethrough)) || updateCtx());
    document.querySelectorAll('[data-align]').forEach((b) => b.addEventListener('click', () => { eachText((o) => o.set('textAlign', b.dataset.align)); updateCtx(); }));
    document.querySelectorAll('[data-case]').forEach((b) => b.addEventListener('click', () => eachText((o) => {
        const t = o.text || '';
        const mode = b.dataset.case;
        const v = mode === 'upper' ? t.toUpperCase() : mode === 'lower' ? t.toLowerCase()
            : mode === 'title' ? t.toLowerCase().replace(/(^|\s|-)(\S)/g, (m, p, c) => p + c.toUpperCase())
            : t.toLowerCase().replace(/(^\s*\S|[.!?]\s+\S)/g, (m) => m.toUpperCase());
        o.set('text', v);
    })));
    $('deCharSpacing').addEventListener('input', (e) => { $('deCharSpacingOut').textContent = e.target.value; eachText((o) => o.set('charSpacing', +e.target.value)); });
    $('deLineHeight').addEventListener('input', (e) => { $('deLineHeightOut').textContent = (+e.target.value).toFixed(2); eachText((o) => o.set('lineHeight', +e.target.value)); });
    document.querySelectorAll('[data-fx]').forEach((b) => b.addEventListener('click', () => {
        const o = active();
        const list = o && o.type === 'activeSelection' ? o.getObjects() : [o];
        list.forEach((t) => applyEffect(b.dataset.fx, t));
    }));
    $('deStrokeW').addEventListener('input', (e) => { $('deStrokeWOut').textContent = e.target.value; eachText((o) => o.set({ strokeWidth: +e.target.value, stroke: o.stroke || $('deStrokeC').value, paintFirst: o.fxName === 'hollow' ? 'fill' : 'stroke', strokeLineJoin: 'round' })); });
    $('deStrokeC').addEventListener('input', (e) => eachText((o) => { o.set('stroke', e.target.value); if (o.fxName === 'thick' && o.shadow) o.shadow.color = e.target.value; }));
    function shadowEdit(fn) { eachText((o) => { if (!o.shadow) o.set('shadow', new fabric.Shadow({ color: hexA($('deShadowC').value, 0.5), blur: 10, offsetX: 6, offsetY: 6 })); fn(o.shadow, o); }); }
    $('deShadowC').addEventListener('input', (e) => shadowEdit((s, o) => { s.color = o.fxName === 'neon' || o.fxName === 'thick' || o.fxName === 'splice' ? e.target.value : hexA(e.target.value, 0.5); }));
    $('deShadowBlur').addEventListener('input', (e) => { $('deShadowBlurOut').textContent = e.target.value; shadowEdit((s) => { s.blur = +e.target.value; }); });
    $('deShadowOff').addEventListener('input', (e) => { $('deShadowOffOut').textContent = e.target.value; shadowEdit((s, o) => { const v = +e.target.value; if (o.fxName === 'lift') { s.offsetX = 0; s.offsetY = v; } else { s.offsetX = v * 0.8; s.offsetY = v; } }); });
    $('deHighlightC').addEventListener('input', (e) => eachText((o) => { if (o.fxName === 'highlight') o.set('textBackgroundColor', e.target.value); }));

    // shape controls
    $('deFill').addEventListener('input', (e) => setProp((o) => { if (isLine(o)) o.set('stroke', e.target.value); else o.set('fill', e.target.value); }));
    $('deBorderC').addEventListener('input', (e) => setProp((o) => { if (!isLine(o)) o.set({ stroke: e.target.value, strokeWidth: o.strokeWidth || 6, strokeUniform: true }); }));
    $('deBorderW').addEventListener('input', (e) => setProp((o) => o.set({ strokeWidth: +e.target.value, stroke: o.stroke || $('deBorderC').value, strokeUniform: true })));
    $('deRadius').addEventListener('input', (e) => setProp((o) => { if (o.type === 'rect') { const r = +e.target.value / (o.scaleX || 1); o.set({ rx: r, ry: r }); if (o.isFrame) { o.frameShape = r > 0 ? 'rounded' : 'rect'; o.frameRadius = r; } } }));
    $('deDashed').addEventListener('change', (e) => setProp((o) => o.set('strokeDashArray', e.target.checked ? [Math.max(6, (o.strokeWidth || 4) * 3), Math.max(4, (o.strokeWidth || 4) * 2)] : null)));

    // image controls
    function filtersOf(o) {
        const f = o.filters || [];
        const get = (t) => f.find((x) => x && x.type === t);
        return { b: get('Brightness') ? get('Brightness').brightness : 0, c: get('Contrast') ? get('Contrast').contrast : 0, s: get('Saturation') ? get('Saturation').saturation : 0, g: !!get('Grayscale'), p: !!get('Sepia') };
    }
    function applyFilters() {
        const o = active();
        if (!isImage(o)) return;
        const F = fabric.Image.filters;
        const list = [];
        if (+$('deBright').value) list.push(new F.Brightness({ brightness: +$('deBright').value }));
        if (+$('deContrast').value) list.push(new F.Contrast({ contrast: +$('deContrast').value }));
        if (+$('deSaturate').value) list.push(new F.Saturation({ saturation: +$('deSaturate').value }));
        if ($('deGray').checked) list.push(new F.Grayscale());
        if ($('deSepia').checked) list.push(new F.Sepia());
        const tint = (o.filters || []).find((x) => x && x.type === 'BlendColor');
        if (tint) list.push(tint);
        o.filters = list; o.applyFilters(); canvas.requestRenderAll(); pushHistorySoon();
    }
    ['deBright', 'deContrast', 'deSaturate'].forEach((id) => $(id).addEventListener('input', applyFilters));
    ['deGray', 'deSepia'].forEach((id) => $(id).addEventListener('change', applyFilters));
    $('deResetFilters').addEventListener('click', () => { ['deBright', 'deContrast', 'deSaturate'].forEach((id) => { $(id).value = 0; }); $('deGray').checked = false; $('deSepia').checked = false; applyFilters(); });
    function frameFitInput() {
        const o = active();
        if (!(isImage(o) && o.isFrame)) return;
        refitFrame(o, o.frameW, o.frameH, { zoom: +$('deFrameZoom').value, fx: +$('deFrameX').value, fy: +$('deFrameY').value });
        pushHistorySoon();
    }
    ['deFrameZoom', 'deFrameX', 'deFrameY'].forEach((id) => $(id).addEventListener('input', frameFitInput));
    $('deFrameEmpty').addEventListener('click', () => { const o = active(); if (isImage(o) && o.isFrame) emptyFrame(o); });
    $('deImgRadius').addEventListener('input', (e) => setProp((o) => {
        if (!isImage(o) || o.isFrame) return;
        const r = +e.target.value / (o.scaleX || 1);
        o.imgRadius = +e.target.value;
        o.clipPath = r > 0 ? new fabric.Rect({ width: o.width, height: o.height, rx: r, ry: r, originX: 'center', originY: 'center', left: 0, top: 0 }) : null;
        o.dirty = true;
    }));
    $('deReplaceImg').addEventListener('click', () => { replaceTarget = active(); $('deReplaceInput').click(); });
    let replaceTarget = null;
    $('deReplaceInput').addEventListener('change', (e) => {
        const files = e.target.files; if (!files.length || !replaceTarget) return;
        uploadFiles(files).then((ups) => {
            if (!ups.length) return;
            const t = replaceTarget; replaceTarget = null;
            if (t.isFrame) { canvas.setActiveObject(t); fillFrame(t, ups[0].path); }
            else if (isImage(t)) {
                const dispW = t.getScaledWidth();
                t.setSrc(url(ups[0].path), () => { t.scaleToWidth(dispW); t.deSrc = ups[0].path; canvas.requestRenderAll(); pushHistory(); }, { crossOrigin: 'anonymous' });
            }
        });
        e.target.value = '';
    });
    $('deSetBg').addEventListener('click', () => { const o = active(); if (isImage(o)) { setBgImage(o.deSrc || o.getSrc()); } });

    // common controls
    $('deOpacity').addEventListener('input', (e) => { $('deOpacityOut').textContent = Math.round(e.target.value * 100) + '%'; setProp((o) => o.set('opacity', +e.target.value)); });
    $('deAngle').addEventListener('input', (e) => { const o = active(); if (!o) return; o.rotate(+e.target.value); o.setCoords(); canvas.requestRenderAll(); pushHistorySoon(); });
    $('dePosX').addEventListener('input', (e) => { const o = active(); if (!o) return; o.set('left', +e.target.value); o.setCoords(); canvas.requestRenderAll(); pushHistorySoon(); });
    $('dePosY').addEventListener('input', (e) => { const o = active(); if (!o) return; o.set('top', +e.target.value); o.setCoords(); canvas.requestRenderAll(); pushHistorySoon(); });
    function sizeInput() {
        const o = active(); if (!o) return;
        const w = Math.max(4, +$('deSizeW').value), h = Math.max(4, +$('deSizeH').value);
        if (isImage(o) && o.isFrame) refitFrame(o, w, h);
        else if (o.type === 'textbox') { o.set({ width: w / (o.scaleX || 1) }); refreshTextObj(o); }
        else { o.set({ scaleX: w / o.width, scaleY: h / o.height }); bakeFrameScale(o); }
        o.setCoords(); canvas.requestRenderAll(); pushHistorySoon();
    }
    $('deSizeW').addEventListener('change', sizeInput); $('deSizeH').addEventListener('change', sizeInput);
    document.querySelectorAll('[data-layer]').forEach((b) => b.addEventListener('click', () => {
        const o = active(); if (!o) return;
        ({ front: () => o.bringToFront(), forward: () => o.bringForward(), backward: () => o.sendBackwards(), back: () => { o.sendToBack(); keepBgAtBack(); } })[b.dataset.layer]();
        canvas.requestRenderAll(); pushHistory();
    }));
    document.querySelectorAll('[data-palign]').forEach((b) => b.addEventListener('click', () => {
        const o = active(); if (!o) return;
        const r = o.getBoundingRect(true, true);
        const a = b.dataset.palign;
        if (a === 'left') o.left += -r.left; if (a === 'right') o.left += design.w - (r.left + r.width);
        if (a === 'hcenter') o.left += design.w / 2 - (r.left + r.width / 2);
        if (a === 'top') o.top += -r.top; if (a === 'bottom') o.top += design.h - (r.top + r.height);
        if (a === 'vcenter') o.top += design.h / 2 - (r.top + r.height / 2);
        o.setCoords(); canvas.requestRenderAll(); pushHistory(); updateCtx();
    }));
    $('deFlipH').addEventListener('click', () => setProp((o) => o.set('flipX', !o.flipX)));
    $('deFlipV').addEventListener('click', () => setProp((o) => o.set('flipY', !o.flipY)));
    $('deGroup').addEventListener('click', groupSel);
    $('deUngroup').addEventListener('click', ungroupSel);
    $('deLock').addEventListener('click', () => { setProp((o) => lockObj(o, !o.lockedDE)); updateCtx(); });
    $('deDuplicate').addEventListener('click', duplicate);
    $('deDelete').addEventListener('click', removeSel);

    function lockObj(o, lock) {
        o.lockedDE = lock;
        o.set({ lockMovementX: lock, lockMovementY: lock, lockScalingX: lock, lockScalingY: lock, lockRotation: lock, hasControls: !lock, editable: !lock });
    }
    function groupSel() { const o = active(); if (!o || o.type !== 'activeSelection') return; const g = o.toGroup(); g.name = 'Group'; canvas.requestRenderAll(); pushHistory(); updateCtx(); }
    function ungroupSel() { const o = active(); if (!o || o.type !== 'group') return; o.toActiveSelection(); canvas.requestRenderAll(); pushHistory(); updateCtx(); }
    function removeSel() {
        const o = active(); if (!o) return;
        if (o.isEditing) return;
        const list = o.type === 'activeSelection' ? o.getObjects() : [o];
        canvas.discardActiveObject();
        list.forEach((x) => canvas.remove(x));
        canvas.requestRenderAll(); pushHistory(); updateCtx();
    }
    function duplicate() {
        const o = active(); if (!o) return;
        o.clone((c) => {
            canvas.discardActiveObject();
            c.set({ left: c.left + 24, top: c.top + 24, evented: true });
            if (c.type === 'activeSelection') {
                c.canvas = canvas;
                c.forEachObject((x) => { x.deId = uid(); canvas.add(x); });
                c.setCoords();
            } else { c.deId = uid(); canvas.add(c); }
            canvas.setActiveObject(c); canvas.requestRenderAll(); pushHistory();
        }, PROPS);
    }
    function copy() { const o = active(); if (o) o.clone((c) => { clipboard = c; }, PROPS); }
    function paste() {
        if (!clipboard) return;
        clipboard.clone((c) => {
            canvas.discardActiveObject();
            c.set({ left: c.left + 24, top: c.top + 24, evented: true });
            if (c.type === 'activeSelection') { c.canvas = canvas; c.forEachObject((x) => canvas.add(x)); c.setCoords(); }
            else canvas.add(c);
            clipboard.top += 24; clipboard.left += 24;
            canvas.setActiveObject(c); canvas.requestRenderAll(); pushHistory();
        }, PROPS);
    }

    /* =================================================== background */
    const SWATCHES = ['#ffffff', '#000000', '#f5f5f4', '#fef3c7', '#fde68a', '#fecaca', '#fbcfe8', '#e9d5ff', '#bfdbfe', '#a7f3d0', '#d9f99d', '#1f2937', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#2563eb', '#78350f', '#14532d', '#1e3a8a', '#4c1d95'];
    const GRADIENTS = [['#fbc2eb', '#a6c1ee'], ['#fddb92', '#d1fdff'], ['#f6d365', '#fda085'], ['#a1c4fd', '#c2e9fb'], ['#d4fc79', '#96e6a1'], ['#ff9a9e', '#fecfef'], ['#667eea', '#764ba2'], ['#f093fb', '#f5576c'], ['#43e97b', '#38f9d7'], ['#fa709a', '#fee140'], ['#30cfd0', '#330867'], ['#0f2027', '#2c5364']];
    function bgObj() { return canvas.getObjects().find((o) => o.isBg); }
    function keepBgAtBack() { const b = bgObj(); if (b) b.sendToBack(); }
    function setBgColor(c) {
        const b = bgObj(); if (b) canvas.remove(b);
        canvas.setBackgroundColor(c, () => { canvas.requestRenderAll(); pushHistory(); });
    }
    function setBgGradient(a, b2) {
        const old = bgObj(); if (old) canvas.remove(old);
        const r = new fabric.Rect({ left: 0, top: 0, width: design.w, height: design.h, selectable: false, evented: false, isBg: true, name: 'Background gradient', hoverCursor: 'default' });
        r.set('fill', new fabric.Gradient({ type: 'linear', gradientUnits: 'percentage', coords: { x1: 0, y1: 0, x2: 1, y2: 1 }, colorStops: [{ offset: 0, color: a }, { offset: 1, color: b2 }] }));
        canvas.add(r); r.sendToBack(); canvas.requestRenderAll(); pushHistory();
    }
    function setBgImage(src) {
        fabric.Image.fromURL(url(src), (img) => {
            if (!img || !img.width) return;
            img.deSrc = src;
            canvas.setBackgroundImage(img, () => { fitBgImage(); canvas.requestRenderAll(); pushHistory(); toast('Background image set.'); });
        }, { crossOrigin: 'anonymous' });
    }
    function fitBgImage() {
        const img = canvas.backgroundImage;
        if (!img || !img.width) return;
        const s = Math.max(design.w / img.width, design.h / img.height);
        img.set({ scaleX: s, scaleY: s, originX: 'center', originY: 'center', left: design.w / 2, top: design.h / 2 });
    }
    $('deBgColor').addEventListener('input', (e) => setBgColor(e.target.value));
    $('deBgSwatches').innerHTML = SWATCHES.map((c) => `<button type="button" style="background:${c}" data-bg="${c}" title="${c}"></button>`).join('');
    $('deBgSwatches').addEventListener('click', (e) => { const c = e.target.dataset.bg; if (c) { $('deBgColor').value = c; setBgColor(c); } });
    $('deBgGradients').innerHTML = GRADIENTS.map(([a, b], i) => `<button type="button" style="background:linear-gradient(135deg,${a},${b})" data-grad="${i}"></button>`).join('');
    $('deBgGradients').addEventListener('click', (e) => { const i = e.target.dataset.grad; if (i != null) setBgGradient(...GRADIENTS[i]); });
    $('deBgClearImg').addEventListener('click', () => { canvas.setBackgroundImage(null, () => { canvas.requestRenderAll(); pushHistory(); }); });

    /* =================================================== layouts (collage) */
    const T = 1 / 3;
    const LAYOUTS = [
        { n: 'Full', c: [[0, 0, 1, 1]] },
        { n: '2 columns', c: [[0, 0, .5, 1], [.5, 0, .5, 1]] },
        { n: '2 rows', c: [[0, 0, 1, .5], [0, .5, 1, .5]] },
        { n: '3 rows', c: [[0, 0, 1, T], [0, T, 1, T], [0, 2 * T, 1, T]] },
        { n: '3 columns', c: [[0, 0, T, 1], [T, 0, T, 1], [2 * T, 0, T, 1]] },
        { n: '2 × 2', c: [[0, 0, .5, .5], [.5, 0, .5, .5], [0, .5, .5, .5], [.5, .5, .5, .5]] },
        { n: '1 + 2', c: [[0, 0, 1, .5], [0, .5, .5, .5], [.5, .5, .5, .5]] },
        { n: '2 + 1', c: [[0, 0, .5, .5], [.5, 0, .5, .5], [0, .5, 1, .5]] },
        { n: 'Big left', c: [[0, 0, .6, 1], [.6, 0, .4, .5], [.6, .5, .4, .5]] },
        { n: 'Big right', c: [[0, 0, .4, .5], [0, .5, .4, .5], [.4, 0, .6, 1]] },
        { n: 'Hero + 3', c: [[0, 0, 1, .62], [0, .62, T, .38], [T, .62, T, .38], [2 * T, .62, T, .38]] },
        { n: '2 × 3', c: [[0, 0, .5, T], [.5, 0, .5, T], [0, T, .5, T], [.5, T, .5, T], [0, 2 * T, .5, T], [.5, 2 * T, .5, T]] },
        { n: '3 × 3', c: [0, 1, 2].flatMap((r) => [0, 1, 2].map((c) => [c * T, r * T, T, T])) },
        { n: 'Mosaic', c: [[0, 0, .5, .6], [.5, 0, .5, .3], [.5, .3, .5, .3], [0, .6, T, .4], [T, .6, T, .4], [2 * T, .6, T, .4]] },
        { n: 'Pinterest 2+2', band: [.36, .64], c: [[0, 0, .5, .36], [.5, 0, .5, .36], [0, .64, .5, .36], [.5, .64, .5, .36]] },
        { n: 'Pinterest 1+1', band: [.42, .58], c: [[0, 0, 1, .42], [0, .58, 1, .42]] },
        { n: 'Pinterest 3+3', band: [.34, .66], c: [[0, 0, T, .34], [T, 0, T, .34], [2 * T, 0, T, .34], [0, .66, T, .34], [T, .66, T, .34], [2 * T, .66, T, .34]] },
        { n: 'Top band + 2×2', band: [0, .26], c: [[0, .26, .5, .37], [.5, .26, .5, .37], [0, .63, .5, .37], [.5, .63, .5, .37]] },
        { n: '4 strips', c: [[0, 0, .25, 1], [.25, 0, .25, 1], [.5, 0, .25, 1], [.75, 0, .25, 1]] },
        { n: 'Filmstrip', c: [[.08, .02, .84, .31], [.08, .345, .84, .31], [.08, .67, .84, .31]] },
    ];
    function layoutThumb(l) {
        const cells = l.c.map(([x, y, w, h]) => `<rect x="${x * 60 + 1.5}" y="${y * 90 + 1.5}" width="${w * 60 - 3}" height="${h * 90 - 3}" rx="2" fill="#c7cbd3"/>`).join('');
        const band = l.band ? `<rect x="6" y="${(l.band[0] + (l.band[1] - l.band[0]) * 0.3) * 90}" width="48" height="${(l.band[1] - l.band[0]) * 90 * 0.4}" rx="2" fill="#7c3aed"/>` : '';
        return `<svg viewBox="0 0 60 90" width="60" height="90">${cells}${band}</svg>`;
    }
    $('deLayoutGrid').innerHTML = LAYOUTS.map((l, i) => `<button type="button" data-layout="${i}" title="${esc(l.n)}">${layoutThumb(l)}<span>${esc(l.n)}</span></button>`).join('');
    $('deLayoutGap').addEventListener('input', (e) => { $('deLayoutGapOut').textContent = e.target.value; });
    $('deLayoutGrid').addEventListener('click', (e) => {
        const b = e.target.closest('[data-layout]'); if (!b) return;
        const l = LAYOUTS[+b.dataset.layout];
        const gap = +$('deLayoutGap').value;
        const round = $('deLayoutRound').checked;
        loading = true;
        if ($('deLayoutReplace').checked) canvas.getObjects().filter((o) => o.isFrame).forEach((o) => canvas.remove(o));
        if (gap > 0) canvas.setBackgroundColor($('deLayoutGapColor').value);
        const W = design.w, H = design.h;
        const frames = l.c.map(([x, y, w, h]) => {
            const x1 = x * W + (x === 0 ? gap : gap / 2), y1 = y * H + (y === 0 ? gap : gap / 2);
            const x2 = (x + w) * W - (Math.abs(x + w - 1) < 1e-6 ? gap : gap / 2), y2 = (y + h) * H - (Math.abs(y + h - 1) < 1e-6 ? gap : gap / 2);
            return framePlaceholder({ shape: round ? 'rounded' : 'rect', w: x2 - x1, h: y2 - y1, left: x1, top: y1, radius: Math.min(x2 - x1, y2 - y1) * 0.06 });
        });
        const bgIdx = bgObj() ? 1 : 0;
        frames.forEach((f, i) => canvas.insertAt(f, bgIdx + i));
        if (l.band) {
            const bh = (l.band[1] - l.band[0]) * H;
            if (bh > 0) {
                const t = new fabric.Textbox('YOUR TITLE HERE', {
                    width: W * 0.86, fontSize: Math.min(bh * 0.32, W * 0.1), fontFamily: 'Anton', textAlign: 'center', fill: '#111111', name: 'Title',
                    originX: 'center', originY: 'center', left: W / 2, top: (l.band[0] + (l.band[1] - l.band[0]) / 2) * H, lineHeight: 1.05,
                });
                loadFont('Anton').then(() => { refreshTextObj(t); canvas.requestRenderAll(); });
                canvas.add(t);
            }
        }
        loading = false;
        canvas.discardActiveObject(); canvas.requestRenderAll(); pushHistory();
        toast('Layout added — drag photos onto the frames.');
    });
    /* ---------- Frames: basic shapes (photo gets clipped to the shape) ---------- */
    const FRAMES = [
        ['Square', 'rect', 1, 1], ['Portrait 4:5', 'rect', 4, 5], ['Portrait 2:3', 'rect', 2, 3], ['Landscape 3:2', 'rect', 3, 2], ['Wide 16:9', 'rect', 16, 9], ['Strip', 'rect', 3, 1],
        ['Rounded square', 'rounded', 1, 1], ['Rounded portrait', 'rounded', 1, 1.25], ['Circle', 'circle', 1, 1], ['Oval', 'oval', 1, 1.3], ['Arch', 'arch', 1, 1.35], ['Wide arch', 'arch', 1.3, 1],
        ['Triangle', 'triangle', 1, 0.9], ['Diamond', 'diamond', 1, 1], ['Pentagon', 'pentagon', 1, 1], ['Hexagon', 'hexagon', 1, 0.9], ['Octagon', 'octagon', 1, 1], ['Star', 'star', 1, 1],
        ['Heart', 'heart', 1, 0.92], ['Blob', 'blob', 1, 1], ['Cloud', 'cloud', 1.3, 0.8], ['Ticket', 'ticket', 1.6, 1], ['Scalloped', 'scallop', 1, 1], ['Leaf', 'leaf', 1, 1],
    ];
    renderFrameButtons($('deFrameGrid'));
    $('deFrameGrid').addEventListener('click', (e) => { const b = e.target.closest('[data-frame]'); if (b) addFrame(+b.dataset.frame); });
    function frameIconSVG(s, w, h) {
        const W = 40 * w / Math.max(w, h), H = 40 * h / Math.max(w, h);
        if (s === 'circle') return `<circle cx="25" cy="25" r="18" fill="#c7cbd3"/>`;
        if (s === 'arch') { const r = Math.min(W / 2, H); return `<path d="M ${25 - W / 2} ${25 + H / 2} V ${25 - H / 2 + r} A ${W / 2} ${r} 0 0 1 ${25 + W / 2} ${25 - H / 2 + r} V ${25 + H / 2} Z" fill="#c7cbd3"/>`; }
        if (FRAME_PATHS[s]) return `<path d="${unitPath(FRAME_PATHS[s], W, H, 25, 25)}" fill="#c7cbd3"/>`;
        return `<rect x="${25 - W / 2}" y="${25 - H / 2}" width="${W}" height="${H}" rx="${s === 'rounded' ? 6 : 0}" fill="#c7cbd3"/>`;
    }
    function renderFrameButtons(box, list) {
        const idx = list || FRAMES.map((_, i) => i);
        box.innerHTML = idx.map((i) => { const [n, s, w, h] = FRAMES[i]; return `<button type="button" data-frame="${i}" title="${esc(n)} frame"><svg viewBox="0 0 50 50" width="46" height="46">${frameIconSVG(s, w, h)}</svg><span>${esc(n)}</span></button>`; }).join('');
    }
    function addFrame(i) {
        const [n, s, w, h] = FRAMES[i];
        const base = design.w * 0.55;
        const f = framePlaceholder({ shape: s, w: base * w / Math.max(w, h), h: base * h / Math.max(w, h), left: 0, top: 0 });
        f.name = n + ' frame';
        center(f);
        toast('Frame added — drag a photo onto it, or double-click it to upload one.');
    }

    /* =================================================== colour panel (Canva-style) */
    const DEFAULT_COLORS = ['#000000', '#545454', '#737373', '#a6a6a6', '#d9d9d9', '#ffffff',
        '#ff3131', '#ff5757', '#ff66c4', '#cb6ce6', '#8c52ff', '#5e17eb',
        '#0097b2', '#0cc0df', '#5ce1e6', '#38b6ff', '#5271ff', '#004aad',
        '#00bf63', '#7ed957', '#c1ff72', '#ffde59', '#ffbd59', '#ff914d',
        '#8b4513', '#c68642', '#f5deb3', '#1e3a5f', '#14532d', '#7f1d1d'];
    const GRADIENTS_20 = [['#000000', '#737373'], ['#000000', '#c89116'], ['#000000', '#3533cd'], ['#a6a6a6', '#ffffff'], ['#fff7ad', '#ffa9f9'],
        ['#cdffd8', '#94b9ff'], ['#ff3131', '#ff914d'], ['#ff5757', '#8c52ff'], ['#5170ff', '#ff66c4'], ['#004aad', '#cb6ce6'],
        ['#8c52ff', '#5ce1e6'], ['#5de0e6', '#004aad'], ['#8c52ff', '#00bf63'], ['#0097b2', '#7ed957'], ['#c9e265', '#ffde59'],
        ['#ffde59', '#ff914d'], ['#ff66c4', '#ffde59'], ['#fbc2eb', '#a6c1ee'], ['#30cfd0', '#330867'], ['#0f2027', '#2c5364']];
    let colorCtx = null;       // { title, gradient, apply(c), current, tint }
    let colorReturnPanel = 'elements';
    function gradFill(a, b) {
        return new fabric.Gradient({ type: 'linear', gradientUnits: 'percentage', coords: { x1: 0, y1: 0, x2: 1, y2: 1 }, colorStops: [{ offset: 0, color: a }, { offset: 1, color: b }] });
    }
    function normHex(c) {
        if (!c || typeof c !== 'string' || c === 'none' || c === 'transparent') return null;
        try { const col = new fabric.Color(c); if (col.getAlpha() === 0) return null; return '#' + col.toHex().toLowerCase(); } catch (e) { return null; }
    }
    function recentColors() { try { return JSON.parse(localStorage.getItem('de_recent_colors') || '[]'); } catch (e) { return []; } }
    function rememberColor(c) {
        const h = normHex(c); if (!h) return;
        try { localStorage.setItem('de_recent_colors', JSON.stringify([h].concat(recentColors().filter((x) => x !== h)).slice(0, 12))); } catch (e) { /* private mode */ }
    }
    function documentColors() {
        const set = new Set();
        const add = (c) => { const h = normHex(c); if (h) set.add(h); };
        (function walk(list) { list.forEach((o) => { if (o.excludeFromExport) return; add(o.fill); add(o.stroke); if (o._objects) walk(o._objects); }); })(canvas.getObjects());
        add(canvas.backgroundColor);
        return [...set];
    }
    function swatchBtn(c, sel) { return `<button type="button" class="de-sw${sel ? ' on' : ''}" data-col="${c}" style="background:${c}" title="${c}"></button>`; }
    function openColorPanel(opts) {
        const vis = document.querySelector('[data-panel-body]:not([hidden])');
        if (vis && vis.dataset.panelBody !== 'color') colorReturnPanel = vis.dataset.panelBody;
        colorCtx = opts;
        $('deColorTitle').textContent = opts.title || 'Colour';
        const cur = normHex(opts.current) || '#7c3aed';
        $('deColorPicker').value = cur; $('deColorHex').value = cur;
        $('deTintWrap').hidden = !opts.tint;
        $('deGradWrap').hidden = !opts.gradient;
        const used = [...new Set(recentColors().concat(documentColors()))].slice(0, 18);
        $('deUsedColors').innerHTML = used.length ? used.map((c) => swatchBtn(c, c === cur)).join('') : '<p class="de-muted" style="grid-column:1/-1">Colours you use appear here.</p>';
        $('deDefaultColors').innerHTML = DEFAULT_COLORS.map((c) => swatchBtn(c, c === cur)).join('');
        $('deGradColors').innerHTML = GRADIENTS_20.map(([a, b], i) => `<button type="button" class="de-sw" data-grad="${i}" style="background:linear-gradient(135deg,${a},${b})" title="${a} → ${b}"></button>`).join('');
        document.querySelectorAll('[data-panel-body]').forEach((s) => { s.hidden = s.dataset.panelBody !== 'color'; });
        document.body.classList.remove('de-panel-closed');
    }
    function closeColorPanel() {
        colorCtx = null;
        document.querySelectorAll('[data-panel-body]').forEach((s) => { s.hidden = s.dataset.panelBody !== colorReturnPanel; });
    }
    function pickColor(c) {
        if (!colorCtx) return;
        colorCtx.apply(c);
        if (typeof c === 'string') { rememberColor(c); $('deColorHex').value = c; }
        document.querySelectorAll('#dePanel .de-sw').forEach((b) => b.classList.toggle('on', b.dataset.col === c));
        canvas.requestRenderAll();
        pushHistorySoon();
        updateGfxColors();
    }
    $('deColorBack').addEventListener('click', closeColorPanel);
    $('deColorPicker').addEventListener('input', (e) => pickColor(e.target.value));
    $('deColorHexApply').addEventListener('click', () => { const h = normHex($('deColorHex').value.trim()); if (h) pickColor(h); else toast('Type a colour like #ff5733'); });
    $('deColorHex').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('deColorHexApply').click(); } });
    document.querySelector('[data-panel-body="color"]').addEventListener('click', (e) => {
        const s = e.target.closest('[data-col]'); if (s) return pickColor(s.dataset.col);
        const g = e.target.closest('[data-grad]'); if (g && colorCtx && colorCtx.gradient) { const [a, b] = GRADIENTS_20[+g.dataset.grad]; colorCtx.apply({ gradient: [a, b] }); canvas.requestRenderAll(); pushHistorySoon(); updateGfxColors(); }
    });
    // switching to another rail panel leaves colour mode
    document.querySelectorAll('.de-rail [data-panel]').forEach((b) => b.addEventListener('click', () => { colorCtx = null; }));

    /** Every native colour box in the editor opens the colour panel instead of the browser picker. */
    function hookColorInput(id, title, gradientApply) {
        const inp = $(id); if (!inp) return;
        inp.addEventListener('click', (e) => {
            e.preventDefault();
            openColorPanel({
                title, current: inp.value, gradient: !!gradientApply,
                apply: (c) => {
                    if (typeof c === 'string') { inp.value = c; inp.dispatchEvent(new Event('input', { bubbles: true })); }
                    else if (gradientApply) gradientApply(c.gradient);
                },
            });
        });
    }
    hookColorInput('deTextColor', 'Text colour', ([a, b]) => eachText((o) => { if (o.fxName === 'hollow') return; o.set('fill', gradFill(a, b)); }));
    hookColorInput('deFill', 'Fill colour', ([a, b]) => setProp((o) => { if (!isLine(o)) o.set('fill', gradFill(a, b)); }));
    hookColorInput('deBorderC', 'Border colour');
    hookColorInput('deStrokeC', 'Outline colour');
    hookColorInput('deShadowC', 'Shadow / glow colour');
    hookColorInput('deHighlightC', 'Highlight colour');
    hookColorInput('deBgColor', 'Background colour', ([a, b]) => setBgGradient(a, b));
    hookColorInput('deLayoutGapColor', 'Gap colour');

    /* ---------- graphics: change each colour + flip ---------- */
    /** Colours inside an SVG graphic (group): { key → [[child, 'fill'|'stroke'], …] }. */
    function gfxColorMap(o) {
        const map = new Map();
        (function walk(list) {
            list.forEach((ch) => {
                if (ch._objects) return walk(ch._objects);
                ['fill', 'stroke'].forEach((prop) => {
                    const v = ch[prop];
                    if (!v) return;
                    let key = null;
                    if (typeof v === 'object' && v.colorStops) key = 'grad:' + v.colorStops.map((s) => normHex(s.color)).join('>');
                    else { key = normHex(v); if (prop === 'stroke' && !ch.strokeWidth) key = null; }
                    if (!key) return;
                    if (!map.has(key)) map.set(key, []);
                    map.get(key).push([ch, prop]);
                });
            });
        })(o._objects || []);
        return map;
    }
    function isGraphic(o) { return o && o.type === 'group' && !o.isFrame; }
    function updateGfxColors() {
        const o = active();
        if (!isGraphic(o)) return;
        const map = gfxColorMap(o);
        const keys = [...map.keys()].slice(0, 12);
        $('deGfxColors').innerHTML = keys.map((k, i) => {
            const bg = k.startsWith('grad:') ? `linear-gradient(135deg,${k.slice(5).split('>').join(',')})` : k;
            return `<button type="button" class="de-sw de-sw-sm" data-gfx="${i}" style="background:${bg}" title="Change this colour"></button>`;
        }).join('') + (map.size > 12 ? `<span class="de-mini">+${map.size - 12}</span>` : '');
        $('deGfxColors').dataset.keys = JSON.stringify(keys);
    }
    $('deGfxColors').addEventListener('click', (e) => {
        const b = e.target.closest('[data-gfx]'); if (!b) return;
        const o = active(); if (!isGraphic(o)) return;
        const keys = JSON.parse($('deGfxColors').dataset.keys || '[]');
        let key = keys[+b.dataset.gfx];
        const cur = key.startsWith('grad:') ? key.slice(5).split('>')[0] : key;
        openColorPanel({
            title: 'Graphic colour', current: cur, gradient: true,
            apply: (c) => {
                const targets = gfxColorMap(o).get(key) || [];
                const val = typeof c === 'string' ? c : null;
                targets.forEach(([ch, prop]) => { ch.set(prop, val || gradFill(c.gradient[0], c.gradient[1])); ch.dirty = true; });
                o.dirty = true;
                key = val ? normHex(val) : 'grad:' + c.gradient.map(normHex).join('>');   // keep editing the same parts
            },
        });
    });
    $('deGfxFlipH').addEventListener('click', () => setProp((o) => o.set('flipX', !o.flipX)));
    $('deGfxFlipV').addEventListener('click', () => setProp((o) => o.set('flipY', !o.flipY)));
    $('deImgFlipH').addEventListener('click', () => setProp((o) => o.set('flipX', !o.flipX)));

    /* ---------- PNG / WEBP graphics (3D, admin uploads): colour tint ---------- */
    function tintOf(o) { return (o.filters || []).find((f) => f && f.type === 'BlendColor'); }
    function setTint(o, color, alpha) {
        const rest = (o.filters || []).filter((f) => f && f.type !== 'BlendColor');
        if (color) rest.push(new fabric.Image.filters.BlendColor({ color, mode: 'tint', alpha }));
        o.filters = rest; o.applyFilters();
    }
    $('deTintBtn').addEventListener('click', () => {
        const o = active(); if (!isImage(o)) return;
        const t = tintOf(o);
        // 3D renders keep their shading with a lighter tint; flat icons take the full colour
        $('deTintAlpha').value = t ? t.alpha : (/assets\/elements\/3d\//.test(o.deSrc || '') ? 0.55 : 1);
        openColorPanel({
            title: 'Graphic colour', current: t ? t.color : '#7c3aed', tint: true,
            apply: (c) => { if (typeof c === 'string') setTint(o, c, +$('deTintAlpha').value); },
        });
    });
    $('deTintAlpha').addEventListener('input', () => { const o = active(); const t = o && tintOf(o); if (t) { setTint(o, t.color, +$('deTintAlpha').value); canvas.requestRenderAll(); pushHistorySoon(); } });
    $('deTintClear').addEventListener('click', () => { const o = active(); if (isImage(o)) { setTint(o, null); canvas.requestRenderAll(); pushHistory(); } });

    /* =================================================== left panels */
    document.querySelectorAll('.de-rail [data-panel]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('.de-rail [data-panel]').forEach((x) => x.classList.toggle('on', x === b));
        document.querySelectorAll('[data-panel-body]').forEach((s) => { s.hidden = s.dataset.panelBody !== b.dataset.panel; });
        const p = b.dataset.panel;
        if (p === 'uploads') loadUploads();
        if (p === 'elements') loadElements();
        if (p === 'layers') renderLayers();
        if (p === 'templates') loadTemplates();
        if (p === 'text') requireFontCss([...new Set(TEXT_STYLES.map((t) => t.fontFamily))]);
        document.body.classList.remove('de-panel-closed');
    }));

    /* =================================================== Elements panel: Shapes · Graphics · Emoji · 3D · Frames */
    const LIB = window.DE_LIB || { shapes: [], emoji: [], graphics: [], '3d': [] };
    const EL_PREVIEW = 8;              // items shown per sub-category before "See all"
    let elTab = 'shapes';
    let elAdmin = null;                // admin-uploaded elements (Admin → Canva → Add Elements)
    const elOpen = new Set();          // expanded sub-categories
    let elIndex = [];                  // flat list of what's on screen, for clicks
    const segmenter = (window.Intl && Intl.Segmenter) ? new Intl.Segmenter('en', { granularity: 'grapheme' }) : null;
    function splitEmoji(s) { return segmenter ? [...segmenter.segment(s)].map((x) => x.segment) : Array.from(s); }
    const EMOJI_LIST = LIB.emoji.map(([cat, s]) => splitEmoji(s).map((e) => ({ kind: 'emoji', cat, name: e, ch: e }))).flat();
    function builtin(tab) {
        if (tab === 'shapes') return LIB.shapes.map((g) => g.items.map((it) => Object.assign({ kind: 'shape', cat: g.cat, name: it.n }, { item: it }))).flat();
        if (tab === 'graphics') return LIB.graphics.map((g) => ({ kind: 'file', cat: g.cat, name: g.name, path: g.path }));
        if (tab === '3d') return LIB['3d'].map((g) => ({ kind: 'file', cat: g.cat, name: g.name, path: g.path }));
        if (tab === 'emoji') return EMOJI_LIST;
        if (tab === 'frames') return FRAMES.map(([n, s], i) => ({ kind: 'frame', cat: /rect|rounded/.test(s) ? 'Photo frames' : 'Shape frames', name: n, fi: i }));
        return [];
    }
    function adminItems(tab) {
        return (elAdmin || []).filter((el) => (el.etype || 'graphics') === tab)
            .map((el) => ({ kind: 'file', cat: el.category || 'More', name: el.name, path: el.path, svg: el.type === 'svg' }));
    }
    function loadElements() {
        if (elAdmin) return renderElPanel();
        renderElPanel();
        post({ action: 'elements' }).then((r) => { elAdmin = r.elements || []; renderElPanel(); });
    }
    function thumbHTML(it, i) {
        if (it.kind === 'emoji') return `<button type="button" class="de-el-emoji" data-eli="${i}" title="${esc(it.name)}">${it.ch}</button>`;
        if (it.kind === 'frame') { const [n, s, w, h] = FRAMES[it.fi]; return `<button type="button" class="de-el-frame" data-eli="${i}" title="${esc(n)} frame"><svg viewBox="0 0 50 50" width="46" height="46">${frameIconSVG(s, w, h)}</svg></button>`; }
        if (it.kind === 'shape') {
            const s = it.item;
            const fill = s.f ? s.f : 'none';
            const stroke = s.s ? `stroke="${s.s}" stroke-width="${s.sw || 6}" ${s.dash ? `stroke-dasharray="${s.dash.join(' ')}"` : ''} stroke-linecap="${s.cap || 'butt'}" stroke-linejoin="round"` : '';
            return `<button type="button" class="de-el-shape" data-eli="${i}" title="${esc(it.name)}"><svg data-autobox width="46" height="46" viewBox="0 0 300 300"><path d="${esc(s.d)}" fill="${fill}" ${stroke} fill-rule="${s.rule || 'nonzero'}"/></svg></button>`;
        }
        return `<button type="button" class="de-el-file" data-eli="${i}" title="${esc(it.name)}"><img loading="lazy" src="${esc(url(it.path))}" alt=""></button>`;
    }
    function renderElPanel() {
        document.querySelectorAll('[data-eltab]').forEach((b) => b.classList.toggle('on', b.dataset.eltab === elTab));
        const q = ($('deElSearch').value || '').trim().toLowerCase();
        const all = builtin(elTab).concat(adminItems(elTab));
        const list = q ? all.filter((it) => (it.name + ' ' + it.cat).toLowerCase().includes(q)) : all;
        const cats = [];
        const byCat = {};
        list.forEach((it) => { if (!byCat[it.cat]) { byCat[it.cat] = []; cats.push(it.cat); } byCat[it.cat].push(it); });
        elIndex = [];
        const html = cats.map((c) => {
            const items = byCat[c];
            const open = q || elOpen.has(elTab + '|' + c) || items.length <= EL_PREVIEW;
            const shown = open ? items : items.slice(0, EL_PREVIEW);
            const cells = shown.map((it) => { elIndex.push(it); return thumbHTML(it, elIndex.length - 1); }).join('');
            return `<div class="de-elcat"><div class="de-elcat-head"><span>${esc(c)}</span>${items.length > EL_PREVIEW && !q ? `<button type="button" data-elcat="${esc(c)}">${open ? 'Show less' : 'See all (' + items.length + ')'}</button>` : ''}</div>
                <div class="de-elgrid-${elTab === 'emoji' ? 'emoji' : 'std'}">${cells}</div></div>`;
        }).join('');
        const body = $('deElBody');
        body.innerHTML = html || `<p class="de-muted">${q ? 'Nothing matches “' + esc(q) + '”.' : 'Nothing here yet.'}</p>`;
        if (elTab === 'frames') body.insertAdjacentHTML('afterbegin', '<p class="de-muted" style="margin-top:0">Add a frame, then drag a photo from Uploads / Photos onto it.</p>');
        // fit each shape preview to its own bounds
        body.querySelectorAll('svg[data-autobox]').forEach((svg) => {
            try {
                const bb = svg.firstElementChild.getBBox();
                const pad = 10 + (+svg.firstElementChild.getAttribute('stroke-width') || 0);
                svg.setAttribute('viewBox', `${bb.x - pad} ${bb.y - pad} ${bb.width + pad * 2} ${bb.height + pad * 2}`);
            } catch (e) { /* hidden panel */ }
        });
    }
    function addFileElement(it) {
        const p = it.path;
        if (/\.svg($|\?)/i.test(p)) {
            fabric.loadSVGFromURL(url(p), (objs, opts) => {
                if (!objs || !objs.length) { toast('Could not load that graphic.'); return; }
                const g = fabric.util.groupSVGElements(objs, opts);
                g.set({ name: it.name });
                g.scaleToWidth(design.w * 0.3);
                center(g);
            });
        } else {
            fabric.Image.fromURL(url(p), (img) => {
                if (!img || !img.width) { toast('Could not load that graphic.'); return; }
                img.set({ name: it.name, deSrc: p });
                img.scaleToWidth(Math.min(design.w * 0.3, img.width * 2.5));
                center(img);
            }, { crossOrigin: 'anonymous' });
        }
    }
    function addShapeItem(it) {
        const s = it.item;
        const style = {
            fill: s.f ? s.f : '', stroke: s.s || null, strokeWidth: s.s ? (s.sw || 6) : 0,
            strokeDashArray: s.dash || null, strokeLineCap: s.cap || 'butt', strokeLineJoin: 'round', fillRule: s.rule || 'nonzero',
        };
        const name = s.s && !s.f && !/line|arrow/i.test(it.name) ? it.name : it.name;
        addShape(() => (s.line ? new fabric.Line([0, 0, 400, 0], style) : new fabric.Path(s.d, style)), name);
    }
    $('deElTabs').addEventListener('click', (e) => {
        const b = e.target.closest('[data-eltab]'); if (!b) return;
        elTab = b.dataset.eltab;
        $('deElBody').scrollTop = 0;
        renderElPanel();
    });
    $('deElSearch').addEventListener('input', () => { clearTimeout(renderElPanel._t); renderElPanel._t = setTimeout(renderElPanel, 150); });
    $('deElBody').addEventListener('click', (e) => {
        const more = e.target.closest('[data-elcat]');
        if (more) { const k = elTab + '|' + more.dataset.elcat; elOpen.has(k) ? elOpen.delete(k) : elOpen.add(k); renderElPanel(); return; }
        const b = e.target.closest('[data-eli]'); if (!b) return;
        const it = elIndex[+b.dataset.eli]; if (!it) return;
        if (it.kind === 'emoji') center(new fabric.Text(it.ch, { fontSize: design.w * 0.18, name: 'Emoji ' + it.ch }));
        else if (it.kind === 'shape') addShapeItem(it);
        else if (it.kind === 'frame') addFrame(it.fi);
        else addFileElement(it);
    });

    // text panel
    document.querySelectorAll('[data-addtext]').forEach((b) => b.addEventListener('click', () => addText(b.dataset.addtext)));
    $('deTextStyles').innerHTML = TEXT_STYLES.map((s, i) => `<button type="button" data-tstyle="${i}" style="font-family:'${esc(s.fontFamily)}';${s.fontStyle ? 'font-style:' + s.fontStyle + ';' : ''}color:${s.fxName === 'neon' ? '#ff2bd6' : (s.fill === '#ffffff' || s.fill === '#ffe4fa' ? '#111' : s.fill)};${s.fxName === 'outline' || s.fxName === 'thick' ? '-webkit-text-stroke:1px #111;' : ''}${s.bg ? 'background:' + s.bg + ';' : ''}">${esc(s.label)}</button>`).join('');
    $('deTextStyles').addEventListener('click', (e) => {
        const b = e.target.closest('[data-tstyle]'); if (!b) return;
        const s = Object.assign({}, TEXT_STYLES[+b.dataset.tstyle]);
        const scale = design.w / 1000;
        const preset = { text: s.text, fontFamily: s.fontFamily, fill: s.fill, fontSize: Math.round(s.fontSize * scale), fontWeight: s.fontWeight || 400, fontStyle: s.fontStyle || 'normal', charSpacing: s.charSpacing || 0, fxName: s.fxName, stroke: s.stroke, shadow: s.shadow, bg: s.bg };
        loadFont(s.fontFamily).then(() => {
            const t = new fabric.Textbox(preset.text, { width: design.w * 0.8, textAlign: 'center', fill: preset.fill, fontFamily: preset.fontFamily, fontSize: preset.fontSize, fontWeight: preset.fontWeight, fontStyle: preset.fontStyle, charSpacing: preset.charSpacing, lineHeight: 1.1, name: 'Text' });
            center(t);
            if (preset.fxName) applyEffect(preset.fxName, t, { stroke: preset.stroke, shadow: preset.shadow, bg: preset.bg });
        });
    });

    // uploads
    function uploadFiles(files) {
        const fd = new FormData();
        fd.append('action', 'upload');
        [...files].forEach((f) => fd.append('files[]', f));
        toast('Uploading…', 60000);
        return post(fd, true).then((r) => {
            if (r.error) toast(r.error, 5000); else toast('Uploaded.');
            if (r.uploads && r.uploads.length) { uploadsCache = r.uploads.concat(uploadsCache || []); renderUploads(); }
            return r.uploads || [];
        });
    }
    let uploadsCache = null;
    function loadUploads() {
        if (uploadsCache) return renderUploads();
        post({ action: 'uploads' }).then((r) => { uploadsCache = r.uploads || []; renderUploads(); });
    }
    function photoTile(p, extra) {
        return `<div class="de-photo" draggable="true" data-src="${esc(p.path || p.full)}" ${extra || ''}><img loading="lazy" src="${esc(url(p.path || p.thumb))}" alt="">` +
            (p.id ? `<button type="button" class="de-photo-del" data-delup="${p.id}" title="Delete">✕</button><button type="button" class="de-photo-bg" data-bgup="${esc(p.path)}" title="Set as background">BG</button>` : '') + `</div>`;
    }
    function renderUploads() {
        $('deUploadGrid').innerHTML = uploadsCache.length ? uploadsCache.map((p) => photoTile(p)).join('') : '<p class="de-muted">No uploads yet.</p>';
    }
    $('deUploadInput').addEventListener('change', (e) => { if (e.target.files.length) uploadFiles(e.target.files); e.target.value = ''; });
    $('deUploadGrid').addEventListener('click', (e) => {
        const del = e.target.closest('[data-delup]');
        if (del) { e.stopPropagation(); if (!confirm('Delete this upload?')) return; post({ action: 'delete_upload', id: del.dataset.delup }).then(() => { uploadsCache = uploadsCache.filter((u) => String(u.id) !== del.dataset.delup); renderUploads(); }); return; }
        const bg = e.target.closest('[data-bgup]');
        if (bg) { e.stopPropagation(); setBgImage(bg.dataset.bgup); return; }
        const t = e.target.closest('.de-photo'); if (t) useImage(t.dataset.src);
    });

    // stock photos
    $('dePhotoForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const q = $('dePhotoQ').value.trim(); if (!q) return;
        $('dePhotoGrid').innerHTML = '<p class="de-muted">Searching…</p>';
        post({ action: 'stock_search', query: q }).then((r) => {
            if (!r.ok) { $('dePhotoGrid').innerHTML = `<p class="de-muted">${esc(r.error || 'Search failed.')}</p>`; return; }
            $('dePhotoGrid').innerHTML = (r.results || []).map((p) => `<div class="de-photo de-stock" draggable="true" data-stock="${esc(p.full)}"><img loading="lazy" src="${esc(p.thumb)}" alt=""></div>`).join('') || '<p class="de-muted">No photos found.</p>';
        });
    });
    function importStock(full) {
        toast('Adding photo…', 30000);
        return post({ action: 'import_url', url: full }).then((r) => {
            if (!r.ok) { toast(r.error || 'Could not add that photo.', 5000); return null; }
            toast('Added to your uploads.');
            if (uploadsCache) uploadsCache.unshift(r.upload);
            return r.upload.path;
        });
    }
    $('dePhotoGrid').addEventListener('click', (e) => {
        const t = e.target.closest('[data-stock]'); if (!t) return;
        const target = active();
        importStock(t.dataset.stock).then((p) => { if (!p) return; if (target && target.isFrame) canvas.setActiveObject(target); useImage(p); });
    });

    // drag & drop onto the page / frames
    document.addEventListener('dragstart', (e) => {
        const t = e.target.closest && e.target.closest('.de-photo');
        if (!t) return;
        e.dataTransfer.setData('text/de-src', t.dataset.src || '');
        e.dataTransfer.setData('text/de-stock', t.dataset.stock || '');
        e.dataTransfer.effectAllowed = 'copy';
    });
    const stageEl = $('deStage');
    stageEl.addEventListener('scroll', () => canvas.calcOffset());
    let hoverFrame = null;
    stageEl.addEventListener('dragover', (e) => {
        e.preventDefault();
        const pt = canvas.getPointer(e);
        const f = frameAt(new fabric.Point(pt.x, pt.y));
        if (f !== hoverFrame) {
            if (hoverFrame) hoverFrame.set({ shadow: null });
            hoverFrame = f;
            if (f) f.set({ shadow: new fabric.Shadow({ color: '#7c3aed', blur: 30 }) });
            canvas.requestRenderAll();
        }
    });
    stageEl.addEventListener('dragleave', () => { if (hoverFrame) { hoverFrame.set({ shadow: null }); hoverFrame = null; canvas.requestRenderAll(); } });
    stageEl.addEventListener('drop', (e) => {
        e.preventDefault();
        if (hoverFrame) { hoverFrame.set({ shadow: null }); hoverFrame = null; }
        const pt = canvas.getPointer(e);
        const at = new fabric.Point(pt.x, pt.y);
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
            uploadFiles(e.dataTransfer.files).then((ups) => { if (ups[0]) useImage(ups[0].path, at); });
            return;
        }
        const src = e.dataTransfer.getData('text/de-src');
        const stock = e.dataTransfer.getData('text/de-stock');
        if (src) useImage(src, at);
        else if (stock) importStock(stock).then((p) => { if (p) useImage(p, at); });
    });

    // templates
    let tplSrc = 'pub';
    const tplCache = {};
    function loadTemplates() {
        if (tplCache[tplSrc]) return renderTemplates();
        post({ action: tplSrc === 'pub' ? 'templates' : 'list' }).then((r) => { tplCache[tplSrc] = r.templates || r.designs || []; renderTemplates(); });
    }
    function renderTemplates() {
        const q = ($('deTplSearch').value || '').toLowerCase();
        const list = (tplCache[tplSrc] || []).filter((t) => !q || (t.title + ' ' + (t.template_category || '')).toLowerCase().includes(q));
        $('deTplGrid').innerHTML = list.length ? list.map((t) => `<button type="button" class="de-tpl" data-tpl="${t.id}" title="${esc(t.title)}">${t.thumb_path ? `<img loading="lazy" src="${esc(url(t.thumb_path))}" alt="">` : '<span>No preview</span>'}<em>${esc(t.title)}</em></button>`).join('')
            : `<p class="de-muted">${tplSrc === 'pub' ? 'No published templates yet.' : 'No saved designs yet.'}</p>`;
    }
    document.querySelectorAll('[data-tplsrc]').forEach((b) => b.addEventListener('click', () => {
        tplSrc = b.dataset.tplsrc;
        document.querySelectorAll('[data-tplsrc]').forEach((x) => x.classList.toggle('on', x === b));
        loadTemplates();
    }));
    $('deTplSearch').addEventListener('input', renderTemplates);
    $('deTplGrid').addEventListener('click', (e) => {
        const b = e.target.closest('[data-tpl]'); if (!b) return;
        if (canvas.getObjects().length && !confirm('Replace the current page with this template? (You can undo.)')) return;
        loadDesign(+b.dataset.tpl, tplSrc === 'mine');
    });

    /* =================================================== layers panel */
    function objLabel(o) {
        if (isText(o)) return 'T  ' + (o.text || '').replace(/\s+/g, ' ').slice(0, 28);
        if (o.isFrame) return (o.type === 'image' ? '🖼 ' : '▢ ') + (o.name || 'Frame');
        if (isImage(o)) return '🖼 ' + (o.name || 'Image');
        if (o.type === 'group') return '⛓ ' + (o.name || 'Group');
        return '◆ ' + (o.name || o.type);
    }
    function renderLayers() {
        const box = $('deLayerList');
        if (!box || box.closest('[hidden]')) return;
        const objs = canvas.getObjects().filter((o) => !o.isBg);
        const act = canvas.getActiveObjects();
        box.innerHTML = objs.slice().reverse().map((o) => {
            const i = canvas.getObjects().indexOf(o);
            return `<div class="de-layer ${act.includes(o) ? 'on' : ''}" data-li="${i}">
                <span class="de-layer-name">${esc(objLabel(o))}</span>
                <button type="button" data-lact="vis" title="Show / hide">${o.visible === false ? '🙈' : '👁'}</button>
                <button type="button" data-lact="lock" title="Lock">${o.lockedDE ? '🔒' : '🔓'}</button>
                <button type="button" data-lact="up" title="Forward">▲</button>
                <button type="button" data-lact="down" title="Backward">▼</button>
            </div>`;
        }).join('') || '<p class="de-muted">Nothing on the page yet.</p>';
    }
    $('deLayerList').addEventListener('click', (e) => {
        const row = e.target.closest('[data-li]'); if (!row) return;
        const o = canvas.getObjects()[+row.dataset.li]; if (!o) return;
        const act = e.target.dataset.lact;
        if (act === 'vis') { o.visible = o.visible === false; if (!o.visible) canvas.discardActiveObject(); }
        else if (act === 'lock') lockObj(o, !o.lockedDE);
        else if (act === 'up') o.bringForward();
        else if (act === 'down') { o.sendBackwards(); keepBgAtBack(); }
        else if (o.visible !== false) canvas.setActiveObject(o);
        canvas.requestRenderAll(); if (act) pushHistory(); renderLayers(); updateCtx();
    });

    /* =================================================== resize modal */
    const SIZE_PRESETS = [['Pinterest pin', 1000, 1500], ['Pinterest long', 1000, 2100], ['Story / Reel', 1080, 1920], ['Square post', 1080, 1080], ['Instagram portrait', 1080, 1350], ['Facebook post', 1200, 630], ['YouTube thumbnail', 1280, 720], ['Blog banner', 1200, 628], ['A4 poster', 2480, 3508], ['Etsy listing', 2000, 1500]];
    $('deSizePresets').innerHTML = SIZE_PRESETS.map(([n, w, h], i) => `<button type="button" data-sp="${i}">${esc(n)}<small>${w} × ${h}</small></button>`).join('');
    $('deSizePresets').addEventListener('click', (e) => { const b = e.target.closest('[data-sp]'); if (!b) return; const p = SIZE_PRESETS[+b.dataset.sp]; $('deNewW').value = p[1]; $('deNewH').value = p[2]; });
    $('deResizeBtn').addEventListener('click', () => { $('deNewW').value = design.w; $('deNewH').value = design.h; $('deResizeModal').hidden = false; });
    $('deResizeModal').addEventListener('click', (e) => { if (e.target === $('deResizeModal') || e.target.hasAttribute('data-close')) $('deResizeModal').hidden = true; });
    $('deResizeApply').addEventListener('click', () => {
        const w = Math.max(50, Math.min(8000, +$('deNewW').value || design.w)), h = Math.max(50, Math.min(8000, +$('deNewH').value || design.h));
        $('deResizeModal').hidden = true;
        setDesignSize(w, h, $('deScaleContent').checked);
    });

    /* =================================================== export, save, use */
    function exportData(fmt, mult) {
        canvas.discardActiveObject(); canvas.requestRenderAll();
        const hidden = canvas.getObjects().filter((o) => isEmptyFrame(o) && o.visible !== false);
        const prev = hidden.map((o) => [o, o.stroke, o.strokeDashArray]);
        hidden.forEach((o) => o.set({ stroke: null }));   // no dashed edges on empty frames in the export
        const data = canvas.toDataURL({ format: fmt === 'jpg' ? 'jpeg' : 'png', quality: 0.95, multiplier: (mult || 1) / zoom });
        prev.forEach(([o, s, d]) => o.set({ stroke: s, strokeDashArray: d }));
        canvas.requestRenderAll();
        return data;
    }
    function download(name, href) { const a = document.createElement('a'); a.href = href; a.download = name; document.body.appendChild(a); a.click(); a.remove(); }
    function downloadBlob(name, blob) { const u = URL.createObjectURL(blob); download(name, u); setTimeout(() => URL.revokeObjectURL(u), 4000); }
    function safeName() { return (($('deTitle').value || 'design').replace(/[^\w\- ]+/g, '').trim() || 'design').replace(/\s+/g, '-').toLowerCase(); }
    function dataURLtoBlob(d) {
        const [head, b64] = d.split(','); const mime = (head.match(/data:([^;]+)/) || [])[1] || 'application/octet-stream';
        const bin = atob(b64); const arr = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        return new Blob([arr], { type: mime });
    }
    const libs = {};
    function loadScript(src) {
        if (!libs[src]) libs[src] = new Promise((res, rej) => { const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = () => rej(new Error('load')); document.head.appendChild(s); });
        return libs[src];
    }
    const JSZIP_URL = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
    const JSPDF_URL = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';

    /** Renders one page's JSON off-screen (never touches the page you're editing). opts: {fmt:'png'|'jpg', mult, svg:true} */
    function renderJSON(json, opts) {
        opts = opts || {};
        return new Promise((resolve, reject) => {
            const el = document.createElement('canvas');
            const sc = new fabric.StaticCanvas(el, { width: design.w, height: design.h, enableRetinaScaling: false, renderOnAddRemove: false, backgroundColor: '#ffffff' });
            sc.loadFromJSON(JSON.parse(JSON.stringify(json)), () => {
                if (opts.transparent) {
                    // "Transparent background": drop the page colour, background image and gradient backgrounds
                    sc.backgroundColor = null;
                    sc.backgroundImage = null;
                    sc.getObjects().filter((o) => o.isBg).forEach((o) => sc.remove(o));
                }
                const objs = sc.getObjects();
                objs.forEach((o) => { if (isEmptyFrame(o)) o.set({ stroke: null }); });
                const bg = sc.backgroundImage;
                if (bg && bg.width) { const k = Math.max(design.w / bg.width, design.h / bg.height); bg.set({ scaleX: k, scaleY: k, originX: 'center', originY: 'center', left: design.w / 2, top: design.h / 2 }); }
                const fams = new Set();
                (function walk(list) { list.forEach((o) => { if (isText(o)) fams.add(o.fontFamily); if (o._objects) walk(o._objects); }); })(objs);
                Promise.all([...fams].map(loadFont)).then(() => {
                    (function walk(list) { list.forEach((o) => { if (isText(o)) { o.initDimensions && o.initDimensions(); o.dirty = true; } if (o._objects) walk(o._objects); }); })(objs);
                    try {
                        sc.renderAll();
                        let out;
                        if (opts.svg) out = svgWithFonts(sc.toSVG(), [...fams]);
                        else out = sc.toDataURL({ format: opts.fmt === 'jpg' ? 'jpeg' : 'png', quality: opts.quality || 0.92, multiplier: opts.mult || 1 });
                        sc.dispose();
                        resolve(out);
                    } catch (e) { sc.dispose(); reject(e); }
                });
            });
        });
    }
    /** SVG export: pull the Google Fonts the text uses into the file so it looks right anywhere. */
    function svgWithFonts(svg, fams) {
        const web = fams.filter((f) => f && !/^(Arial|Georgia|Times New Roman)$/.test(f));
        if (!web.length) return svg;
        const href = 'https://fonts.googleapis.com/css2?' + web.map((f) => 'family=' + encodeURIComponent(f).replace(/%20/g, '+')).join('&amp;') + '&amp;display=swap';
        const style = `<style type="text/css">@import url('${href}');</style>`;
        return svg.indexOf('<defs>') !== -1 ? svg.replace('<defs>', '<defs>' + style) : svg.replace(/(<svg[^>]*>)/, '$1<defs>' + style + '</defs>');
    }

    /* ---------- page picker modal (Download + Use this design) ---------- */
    let dlMode = 'download';
    function openPageModal(mode) {
        commitActive();
        try { canvas.discardActiveObject(); pages[cur].preview = canvas.toDataURL({ format: 'jpeg', quality: 0.8, multiplier: previewMult() / zoom }); } catch (e) { /* tainted image */ }
        dlMode = mode;
        const multi = pages.length > 1;
        $('deDlTitle').textContent = mode === 'use' ? 'Use this design — choose pages' : 'Download';
        $('deDlFmtWrap').hidden = mode === 'use';
        $('deDlPagesWrap').hidden = !multi;
        $('deDlCount').textContent = pages.length;
        document.querySelector('input[name="deDlPages"][value="all"]').checked = true;
        $('deDlGrid').hidden = true;
        $('deDlGrid').innerHTML = pages.map((p, i) => `<label class="de-dl-tile"><input type="checkbox" value="${i}" checked>
            <span class="de-dl-thumb" style="aspect-ratio:${design.w}/${design.h}">${p.preview ? `<img src="${p.preview}" alt="">` : ''}</span>
            <em>Page ${i + 1}${p.name ? ' · ' + esc(p.name) : ''}</em></label>`).join('');
        $('deDlGo').textContent = mode === 'use' ? '➜ Continue to Bulk Scheduler' : 'Download';
        updateDlNote();
        $('deDlModal').hidden = false;
    }
    function selectedPages() {
        const mode = (document.querySelector('input[name="deDlPages"]:checked') || {}).value || 'all';
        if (pages.length === 1 || mode === 'all') return pages.map((_, i) => i);
        if (mode === 'current') return [cur];
        return [...$('deDlGrid').querySelectorAll('input:checked')].map((c) => +c.value);
    }
    function updateDlNote() {
        const fmt = (document.querySelector('input[name="deDlFmt"]:checked') || {}).value;
        $('deDlTransWrap').hidden = dlMode === 'use' || !['png', 'png2', 'svg'].includes(fmt);
        const n = selectedPages().length;
        let note = '';
        if (dlMode === 'use') note = n + ' page(s) will be added to the Bulk Pin Scheduler as separate pins.';
        else if (n > 1 && ['png', 'jpg', 'png2', 'svg'].includes(fmt)) note = n + ' pages → one .zip file.';
        else if (fmt === 'pdf') note = n + ' page(s) → one PDF.';
        else if (fmt === 'json') note = 'Design file — open it again with 📂 Import.';
        $('deDlNote').textContent = note;
    }
    $('deDlModal').addEventListener('change', (e) => {
        if (e.target.name === 'deDlPages') $('deDlGrid').hidden = e.target.value !== 'custom';
        updateDlNote();
    });
    $('deDlModal').addEventListener('click', (e) => { if (e.target === $('deDlModal') || e.target.hasAttribute('data-close')) $('deDlModal').hidden = true; });
    $('deDownloadBtn').addEventListener('click', () => openPageModal('download'));
    $('deDlGo').addEventListener('click', () => {
        const idx = selectedPages();
        if (!idx.length) { toast('Select at least one page.'); return; }
        $('deDlModal').hidden = true;
        if (dlMode === 'use') return useDesign(idx);
        const fmt = (document.querySelector('input[name="deDlFmt"]:checked') || {}).value || 'png';
        runDownload(fmt, idx, $('deDlTrans').checked && ['png', 'png2', 'svg'].includes(fmt)).catch(() => toast('Download failed — one of the images blocks export. Re-upload it from your computer.', 6000));
    });

    async function runDownload(fmt, idx, transparent) {
        const base = safeName();
        const suffix = (i) => (idx.length > 1 || pages.length > 1 ? '-page-' + (i + 1) : '');
        if (fmt === 'json') {
            const data = serializeDesign(idx);
            downloadBlob(base + '.json', new Blob([JSON.stringify(data)], { type: 'application/json' }));
            return;
        }
        toast(idx.length > 1 ? 'Preparing ' + idx.length + ' pages…' : 'Preparing download…', 60000);
        if (fmt === 'pdf') {
            await loadScript(JSPDF_URL);
            const JsPDF = window.jspdf.jsPDF;
            const orient = design.w > design.h ? 'l' : 'p';
            const pdf = new JsPDF({ orientation: orient, unit: 'px', format: [design.w, design.h], hotfixes: ['px_scaling'], compress: true });
            const mult = Math.min(2, 3000 / Math.max(design.w, design.h));
            for (let n = 0; n < idx.length; n++) {
                const img = await renderJSON(pages[idx[n]].json, { fmt: 'jpg', mult: Math.max(1, mult), quality: 0.92 });
                if (n > 0) pdf.addPage([design.w, design.h], orient);
                pdf.addImage(img, 'JPEG', 0, 0, design.w, design.h);
            }
            pdf.save(base + '.pdf');
            toast('PDF downloaded.');
            return;
        }
        const ext = fmt === 'svg' ? 'svg' : (fmt === 'jpg' ? 'jpg' : 'png');
        const files = [];
        for (const i of idx) {
            if (fmt === 'svg') files.push([base + suffix(i) + '.svg', new Blob([await renderJSON(pages[i].json, { svg: true, transparent })], { type: 'image/svg+xml' })]);
            else files.push([base + suffix(i) + (fmt === 'png2' ? '@2x' : '') + (transparent ? '-transparent' : '') + '.' + ext, dataURLtoBlob(await renderJSON(pages[i].json, { fmt: fmt === 'jpg' ? 'jpg' : 'png', mult: fmt === 'png2' ? 2 : 1, quality: 0.95, transparent: transparent && fmt !== 'jpg' }))]);
        }
        if (files.length === 1) { downloadBlob(files[0][0], files[0][1]); toast('Downloaded.'); return; }
        await loadScript(JSZIP_URL);
        const zip = new window.JSZip();
        files.forEach(([n, b]) => zip.file(n, b));
        downloadBlob(base + '-' + ext + '.zip', await zip.generateAsync({ type: 'blob' }));
        toast(files.length + ' pages downloaded as a .zip.');
    }

    function save() {
        $('deSaveState').textContent = 'Saving…';
        commitActive();
        const data = serializeDesign();
        const thumbP = cur === 0
            ? Promise.resolve((() => { try { return canvas.toDataURL({ format: 'jpeg', quality: 0.8, multiplier: 400 / (design.w * zoom) }); } catch (e) { return ''; } })())
            : renderJSON(pages[0].json, { fmt: 'jpg', mult: 400 / design.w, quality: 0.8 }).catch(() => '');
        return thumbP.then((thumb) => post({ action: 'save', id: design.id, title: $('deTitle').value, width: design.w, height: design.h, json: JSON.stringify(data), thumb: thumb || '' }))
            .then((r) => {
                if (!r.ok) { $('deSaveState').textContent = 'Not saved'; toast(r.error || 'Save failed.', 5000); return false; }
                if (!design.id) { design.id = r.id; history.replaceState(null, '', 'design-editor?id=' + r.id); }
                dirty = false; $('deSaveState').textContent = 'All changes saved';
                delete tplCache.mine;
                return true;
            });
    }
    $('deSave').addEventListener('click', () => save().then((ok) => ok && toast('Design saved to “Your Designs”.')));

    /* ---------- Use this design → Bulk Pin Scheduler (every chosen page becomes a pin) ---------- */
    $('deUse').addEventListener('click', () => {
        commitActive();
        if (pages.length > 1) openPageModal('use'); else useDesign([0]);
    });
    async function useDesign(idx) {
        const chk = await post({ action: 'use_check', count: idx.length });
        if (!chk.ok) { toast(chk.error || 'Could not use this design.', 6000); return; }
        toast('Preparing ' + idx.length + ' pin image(s)…', 120000);
        await save();
        const items = [];
        for (let n = 0; n < idx.length; n++) {
            let data;
            try { data = await renderJSON(pages[idx[n]].json, { fmt: 'jpg', mult: 1, quality: 0.95 }); }
            catch (e) { toast('Export failed on page ' + (idx[n] + 1) + ' — re-upload the blocked image from your computer.', 6000); return; }
            toast('Preparing pin image ' + (n + 1) + ' of ' + idx.length + '…', 120000);
            const r = await post({ action: 'use', image: data, design_id: design.id || 0, design_title: $('deTitle').value || '' });
            if (!r.ok) { toast(r.error || 'Could not use this design.', 5000); return; }
            items.push({ file: r.file, name: pages[idx[n]].name || '' });
        }
        const fin = await post({ action: 'use_finish', items: JSON.stringify(items), title: $('deTitle').value });
        if (!fin.ok) { toast(fin.error || 'Could not continue.', 5000); return; }
        window.location.href = fin.url;
    }
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    // autosave every 60s for saved designs
    setInterval(() => { if (dirty && design.id && !crop) save(); }, 60000);

    /* =================================================== load */
    /** Accepts every format we've ever saved: multi-page {pages:[…]}, the old download {w,h,c}, or plain Fabric JSON. */
    function parseDesign(json, w, h) {
        const d = typeof json === 'string' ? JSON.parse(json) : json;
        if (d && Array.isArray(d.pages)) {
            return { w: +d.w || w, h: +d.h || h, pages: d.pages.map((p) => ({ name: p.name || '', c: p.c || blankJSON() })) };
        }
        if (d && d.c && (d.w || d.h)) return { w: +d.w || w, h: +d.h || h, pages: [{ name: '', c: d.c }] };
        if (d && Array.isArray(d.objects)) return { w: w, h: h, pages: [{ name: '', c: d }] };
        throw new Error('Not a design file');
    }
    function loadJSONInto(json, w, h) {
        const d = parseDesign(json, w, h);
        design.w = d.w; design.h = d.h;
        $('deSizeLabel').textContent = d.w + ' × ' + d.h;
        pages = d.pages.map((p) => newPage(p.c, p.name));
        cur = 0;
        fitZoom();
        return showPage(0, true).then(() => { refreshAllPreviews(); });
    }
    /** Template / "My designs" click. One-page designs keep the old behaviour; otherwise the template's
     *  page(s) replace the current page (scaled to this design's size). */
    function loadDesign(id, asOwn) {
        return post({ action: 'load', id: id }).then((r) => {
            if (!r.ok) { toast(r.error || 'Could not open that design.'); return; }
            const d = r.design;
            if (asOwn && d.mine) {
                design.id = d.id; $('deTitle').value = d.title; history.replaceState(null, '', 'design-editor?id=' + d.id);
                return loadJSONInto(d.json, d.width, d.height).then(() => { dirty = false; $('deSaveState').textContent = 'All changes saved'; toast('Design opened.'); });
            }
            if (pages.length <= 1) return loadJSONInto(d.json, d.width, d.height).then(() => { markDirty(); toast('Template loaded.'); });
            const t = parseDesign(d.json, d.width, d.height);
            commitActive();
            const incoming = t.pages.map((p) => newPage((t.w !== design.w || t.h !== design.h) ? scaleJSON(p.c, t.w, t.h, design.w, design.h) : p.c, p.name));
            pages.splice(cur, 1, ...incoming);
            showPage(cur, true).then(() => { markDirty(); refreshAllPreviews(); toast('Template loaded into page ' + (cur + 1) + '.'); });
        });
    }

    /* ---------- Import a design file (.json) ---------- */
    $('deImportBtn').addEventListener('click', () => $('deImportInput').click());
    $('deImportInput').addEventListener('change', (e) => {
        const f = e.target.files[0]; e.target.value = '';
        if (!f) return;
        const rd = new FileReader();
        rd.onload = () => {
            let t;
            try { t = parseDesign(String(rd.result), design.w, design.h); } catch (err) { toast('That file is not a design file (.json from this editor).', 5000); return; }
            commitActive();
            const blank = pages.length === 1 && !(pages[0].json.objects || []).length && !pages[0].json.backgroundImage;
            if (blank) {
                design.w = t.w; design.h = t.h; $('deSizeLabel').textContent = t.w + ' × ' + t.h;
                pages = t.pages.map((p) => newPage(p.c, p.name)); cur = 0;
                fitZoom();
                showPage(0, true).then(() => { markDirty(); refreshAllPreviews(); toast('Imported ' + t.pages.length + ' page(s).'); });
                return;
            }
            const add = t.pages.map((p) => newPage((t.w !== design.w || t.h !== design.h) ? scaleJSON(p.c, t.w, t.h, design.w, design.h) : p.c, p.name));
            pages.splice(cur + 1, 0, ...add);
            showPage(cur + 1, true).then(() => {
                markDirty(); refreshAllPreviews(); scrollToPage(cur);
                toast('Imported ' + add.length + ' page(s) after page ' + cur + (t.w !== design.w || t.h !== design.h ? ' (resized to fit)' : '') + '.', 4000);
            });
        };
        rd.readAsText(f);
    });

    /* =================================================== pages (Canva-style) */
    let pageBusy = false;
    function blankJSON() { return { version: fabric.version, objects: [], background: '#ffffff' }; }
    function newPage(json, name) { return { pid: uid(), name: name || '', json: json || blankJSON(), preview: '', undo: [], redo: [] }; }
    /** Saves what's on the live canvas back into the current page. */
    function commitActive() {
        if (crop) applyCrop();
        const a = active();
        if (a && a.isEditing) a.exitEditing();
        const p = pages[cur];
        if (!p) return;
        p.json = canvas.toJSON(PROPS);
        p.undo = undoStack; p.redo = redoStack;
    }
    function serializeDesign(idx) {
        commitActive();
        const list = idx ? idx.map((i) => pages[i]) : pages;
        return { deVersion: 2, w: design.w, h: design.h, pages: list.map((p) => ({ name: p.name, c: p.json })) };
    }
    /** Loads page i onto the live canvas. skipCommit when the current page was already committed. */
    function showPage(i, skipCommit) {
        if (!skipCommit && pages[cur] && i !== cur) { commitActive(); const prev = cur; setTimeout(() => refreshPreview(prev), 30); }
        cur = Math.max(0, Math.min(pages.length - 1, i));
        const p = pages[cur];
        loading = true;
        canvas.discardActiveObject();
        canvas.clear();
        canvas.backgroundColor = '#ffffff';
        renderPages();
        return new Promise((resolve) => {
            canvas.loadFromJSON(JSON.parse(JSON.stringify(p.json)), () => {
                fitBgImage();
                ensureFonts(canvas.getObjects()).then(() => {
                    loading = false;
                    undoStack = p.undo; redoStack = p.redo;
                    if (!undoStack.length) undoStack.push(snapshot());
                    p.undo = undoStack; p.redo = redoStack;
                    applyZoom(zoom);
                    renderLayers(); updateCtx();
                    canvas.requestRenderAll();
                    resolve();
                });
            });
        });
    }
    function previewMult() { return Math.min(1, 700 / Math.max(design.w, design.h)); }
    function refreshPreview(i) {
        const p = pages[i]; if (!p) return Promise.resolve();
        return renderJSON(p.json, { fmt: 'jpg', mult: previewMult(), quality: 0.8 }).then((d) => {
            p.preview = d;
            const img = document.querySelector(`.de-page[data-pid="${p.pid}"] .de-pageprev`);
            if (img) img.src = d;
        }).catch(() => {});
    }
    async function refreshAllPreviews() {
        for (let i = 0; i < pages.length; i++) if (i !== cur) await refreshPreview(i);
    }
    function renderPages() {
        canvasBox.remove();
        const W = Math.round(design.w * zoom), H = Math.round(design.h * zoom);
        const n = pages.length;
        pagesEl.innerHTML = pages.map((p, i) => {
            const on = i === cur;
            return `<div class="de-page ${on ? 'on' : ''}" data-pi="${i}" data-pid="${p.pid}">
                <div class="de-pagebar" style="width:${W}px">
                    <span class="de-pagenum">Page ${i + 1}</span>
                    ${on ? `<input class="de-pagename" data-pname="${i}" value="${esc(p.name)}" placeholder="Add page title" maxlength="100">` : `<span class="de-pagename-ro">${esc(p.name)}</span>`}
                    ${on ? `<span class="de-pageacts">
                        <button type="button" data-pact="up" title="Move page up" ${i === 0 ? 'disabled' : ''}>↑</button>
                        <button type="button" data-pact="down" title="Move page down" ${i === n - 1 ? 'disabled' : ''}>↓</button>
                        <button type="button" data-pact="dup" title="Duplicate page">⧉</button>
                        <button type="button" data-pact="del" title="Delete page" ${n === 1 ? 'disabled' : ''}>🗑</button>
                        <button type="button" data-pact="add" title="Add page after this one">＋</button>
                    </span>` : ''}
                </div>
                <div class="de-pageslot" style="width:${W}px;height:${H}px">${on ? '' : (p.preview ? `<img class="de-pageprev" src="${p.preview}" alt="Page ${i + 1}">` : '<img class="de-pageprev" alt="">')}</div>
            </div>`;
        }).join('') + `<button type="button" class="de-addpage" data-pact="addend" style="width:${W}px">＋ Add page</button>`;
        const slot = pagesEl.querySelector('.de-page.on .de-pageslot');
        if (slot) slot.appendChild(canvasBox);
        canvas.calcOffset();
    }
    function layoutPages() {
        if (!pagesEl || !pages.length) return;
        const W = Math.round(design.w * zoom), H = Math.round(design.h * zoom);
        pagesEl.querySelectorAll('.de-pagebar, .de-addpage').forEach((el) => { el.style.width = W + 'px'; });
        pagesEl.querySelectorAll('.de-pageslot').forEach((el) => { el.style.width = W + 'px'; el.style.height = H + 'px'; });
    }
    function scrollToPage(i) {
        const el = pagesEl.querySelector(`.de-page[data-pi="${i}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function pageOp(fn) {
        if (pageBusy) return;
        pageBusy = true;
        Promise.resolve(fn()).finally(() => { pageBusy = false; markDirty(); });
    }
    pagesEl.addEventListener('click', (e) => {
        const actBtn = e.target.closest('[data-pact]');
        if (actBtn) {
            const act = actBtn.dataset.pact;
            pageOp(() => {
                commitActive();
                const i = cur;
                if (act === 'add' || act === 'addend') {
                    const at = act === 'addend' ? pages.length : i + 1;
                    pages.splice(at, 0, newPage());
                    refreshPreview(i);
                    return showPage(at, true).then(() => scrollToPage(at));
                }
                if (act === 'dup') {
                    const c = newPage(JSON.parse(JSON.stringify(pages[i].json)), pages[i].name ? pages[i].name + ' (copy)' : '');
                    pages.splice(i + 1, 0, c);
                    refreshPreview(i);
                    return showPage(i + 1, true).then(() => { scrollToPage(i + 1); toast('Page duplicated.'); });
                }
                if (act === 'del') {
                    if (pages.length === 1) return;
                    if (!confirm('Delete page ' + (i + 1) + '?')) return;
                    pages.splice(i, 1);
                    return showPage(Math.min(i, pages.length - 1), true);
                }
                if (act === 'up' || act === 'down') {
                    const j = act === 'up' ? i - 1 : i + 1;
                    if (j < 0 || j >= pages.length) return;
                    [pages[i], pages[j]] = [pages[j], pages[i]];
                    cur = j;
                    renderPages();
                    scrollToPage(j);
                }
            });
            return;
        }
        if (e.target.closest('.de-pagename')) return;
        const pg = e.target.closest('.de-page');
        if (pg && +pg.dataset.pi !== cur) pageOp(() => showPage(+pg.dataset.pi));
    });
    pagesEl.addEventListener('input', (e) => {
        const inp = e.target.closest('[data-pname]'); if (!inp) return;
        pages[+inp.dataset.pname].name = inp.value; markDirty();
    });

    /* =================================================== Canva-like: click outside the page deselects */
    // 'click' (not mousedown) so the toolbar changing height can't swallow a click on the page buttons
    $('deStage').addEventListener('click', (e) => {
        if (e.target.closest('.canvas-container') || e.target.closest('input, textarea, select')) return;
        if (crop) { applyCrop(); return; }
        const a = active();
        if (!a) return;
        if (a.isEditing) a.exitEditing();
        canvas.discardActiveObject();
        canvas.requestRenderAll();
    });

    /* =================================================== drag a placed photo onto a frame */
    let dragFrame = null;
    function frameUnder(pt, except) {
        const objs = canvas.getObjects();
        for (let i = objs.length - 1; i >= 0; i--) {
            const o = objs[i];
            if (o !== except && o.isFrame && o.visible !== false && o.containsPoint(pt, null, true, true)) return o;
        }
        return null;
    }
    canvas.on('object:moving', (e) => {
        const o = e.target;
        if (crop || !isImage(o) || o.isFrame) return;
        const p = canvas.getPointer(e.e);
        const f = frameUnder(new fabric.Point(p.x, p.y), o);
        if (f === dragFrame) return;
        if (dragFrame) dragFrame.set({ shadow: null });
        dragFrame = f;
        if (o._deDragOp === undefined) o._deDragOp = o.opacity == null ? 1 : o.opacity;
        if (f) f.set({ shadow: new fabric.Shadow({ color: '#7c3aed', blur: 30 }) });
        o.set('opacity', f ? 0.45 : o._deDragOp);
        canvas.requestRenderAll();
    });
    canvas.on('mouse:up', (e) => {
        const o = e.target;
        if (o && o._deDragOp !== undefined) { o.set('opacity', o._deDragOp); delete o._deDragOp; }
        if (!dragFrame) return;
        const f = dragFrame;
        dragFrame = null;
        f.set({ shadow: null });
        if (!isImage(o) || o.isFrame) { canvas.requestRenderAll(); return; }
        const src = o.deSrc || o.getSrc();
        loading = true;
        canvas.remove(o);
        loading = false;
        fillFrame(f, src).then((img) => { if (img) toast('Photo placed in the frame.'); });
    });

    /* =================================================== crop (normal images) */
    function enterCrop(img) {
        if (crop || !img || img.type !== 'image' || img.isFrame || img.lockedDE) return;
        if (Math.round(img.angle || 0) % 360 !== 0) { toast('Set rotation to 0° before cropping (Position → Rotate).', 4000); return; }
        const el = img.getElement();
        const nw = el.naturalWidth || el.width, nh = el.naturalHeight || el.height;
        const sx = Math.abs(img.scaleX), sy = Math.abs(img.scaleY);
        const tl = img.getBoundingRect(true, true);
        const offX = (img.flipX ? (nw - (img.cropX || 0) - img.width) : (img.cropX || 0)) * sx;
        const offY = (img.flipY ? (nh - (img.cropY || 0) - img.height) : (img.cropY || 0)) * sy;
        const gL = tl.left - offX, gT = tl.top - offY;
        const base = { originX: 'left', originY: 'top', left: gL, top: gT, scaleX: sx, scaleY: sy, cropX: 0, cropY: 0, width: nw, height: nh,
            flipX: img.flipX, flipY: img.flipY, selectable: false, evented: false, excludeFromExport: true, objectCaching: false };
        const ghost = new fabric.Image(el, Object.assign({}, base, { opacity: 0.35 }));
        const clip = new fabric.Rect({ originX: 'left', originY: 'top', left: tl.left, top: tl.top, width: tl.width, height: tl.height, absolutePositioned: true });
        const bright = new fabric.Image(el, Object.assign({}, base, { clipPath: clip }));
        const rect = new fabric.Rect({ originX: 'left', originY: 'top', left: tl.left, top: tl.top, width: tl.width, height: tl.height,
            fill: 'rgba(0,0,0,0)', stroke: '#7c3aed', strokeWidth: 2, strokeUniform: true, strokeDashArray: [8, 6],
            excludeFromExport: true, lockRotation: true, lockScalingFlip: true, objectCaching: false, name: '__crop' });
        rect.setControlsVisibility({ mtr: false });
        const frozen = canvas.getObjects().filter((o) => o !== img).map((o) => [o, o.evented, o.selectable]);
        frozen.forEach(([o]) => { o.evented = false; o.selectable = false; });
        crop = { img, ghost, bright, clip, rect, frozen, sx, sy, nw, nh, B: { l: gL, t: gT, r: gL + nw * sx, b: gT + nh * sy } };
        img.visible = false;
        const at = canvas.getObjects().indexOf(img);
        canvas.insertAt(ghost, at + 1);
        canvas.insertAt(bright, at + 2);
        canvas.add(rect);
        canvas.setActiveObject(rect);
        rect.on('moving', cropMoving);
        rect.on('scaling', cropSync);
        rect.on('modified', cropClamp);
        $('deCropBar').hidden = false;
        canvas.requestRenderAll();
    }
    function cropBox() { const r = crop.rect; return { l: r.left, t: r.top, w: r.width * r.scaleX, h: r.height * r.scaleY }; }
    function cropSync() { const b = cropBox(); crop.clip.set({ left: b.l, top: b.t, width: b.w, height: b.h }); crop.bright.dirty = true; }
    function cropMoving() {
        const b = cropBox(), B = crop.B;
        crop.rect.set({ left: Math.max(B.l, Math.min(b.l, B.r - b.w)), top: Math.max(B.t, Math.min(b.t, B.b - b.h)) });
        cropSync();
    }
    function cropClamp() {
        const b = cropBox(), B = crop.B;
        const L = Math.max(B.l, b.l), T = Math.max(B.t, b.t);
        const R = Math.min(B.r, b.l + b.w), Bo = Math.min(B.b, b.t + b.h);
        crop.rect.set({ left: L, top: T, width: Math.max(8, R - L), height: Math.max(8, Bo - T), scaleX: 1, scaleY: 1 });
        crop.rect.setCoords();
        cropSync();
        canvas.requestRenderAll();
    }
    function exitCrop() {
        if (!crop) return;
        const c = crop;
        crop = null;
        loading = true;
        canvas.remove(c.ghost, c.bright, c.rect);
        loading = false;
        c.frozen.forEach(([o, ev, sel]) => { o.evented = ev; o.selectable = sel; });
        c.img.visible = true;
        $('deCropBar').hidden = true;
        canvas.setActiveObject(c.img);
        canvas.requestRenderAll();
        updateCtx();
    }
    function applyCrop() {
        if (!crop) return;
        cropClamp();
        const c = crop, b = cropBox();
        let cx = (b.l - c.B.l) / c.sx, cy = (b.t - c.B.t) / c.sy;
        const cw = Math.min(c.nw, b.w / c.sx), ch = Math.min(c.nh, b.h / c.sy);
        if (c.img.flipX) cx = c.nw - cx - cw;
        if (c.img.flipY) cy = c.nh - cy - ch;
        c.img.set({ cropX: Math.max(0, cx), cropY: Math.max(0, cy), width: cw, height: ch });
        if (c.img.imgRadius) {
            const r = c.img.imgRadius / (c.img.scaleX || 1);
            c.img.clipPath = new fabric.Rect({ width: cw, height: ch, rx: r, ry: r, originX: 'center', originY: 'center', left: 0, top: 0 });
        }
        c.img.setPositionByOrigin(new fabric.Point(b.l + b.w / 2, b.t + b.h / 2), 'center', 'center');
        c.img.setCoords(); c.img.dirty = true;
        exitCrop();
        pushHistory();
    }
    function resetCrop() {
        if (!crop) return;
        const B = crop.B;
        crop.rect.set({ left: B.l, top: B.t, width: B.r - B.l, height: B.b - B.t, scaleX: 1, scaleY: 1 });
        crop.rect.setCoords(); cropSync(); canvas.requestRenderAll();
    }
    $('deCropBtn').addEventListener('click', () => enterCrop(active()));
    $('deCropApply').addEventListener('click', applyCrop);
    $('deCropCancel').addEventListener('click', exitCrop);
    $('deCropReset').addEventListener('click', resetCrop);
    canvas.on('selection:cleared', () => { if (crop) applyCrop(); });
    canvas.on('mouse:dblclick', (e) => { if (e.target && isImage(e.target) && !e.target.isFrame && !crop) enterCrop(e.target); });


    /* =================================================== keyboard */
    document.addEventListener('keydown', (e) => {
        const tag = (e.target.tagName || '').toLowerCase();
        const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || (active() && active().isEditing);
        const mod = e.ctrlKey || e.metaKey;
        if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
        if (typing) return;
        if (crop) {
            if (e.key === 'Enter') { e.preventDefault(); applyCrop(); }
            else if (e.key === 'Escape') { e.preventDefault(); exitCrop(); }
            else if (e.key === 'Delete' || e.key === 'Backspace' || (mod && 'zydcvga'.includes(e.key.toLowerCase()))) e.preventDefault();
            return;
        }
        const o = active();
        if (e.key === 'Delete' || e.key === 'Backspace') { if (o) { e.preventDefault(); removeSel(); } }
        else if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
        else if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
        else if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicate(); }
        else if (mod && e.key.toLowerCase() === 'c') copy();
        else if (mod && e.key.toLowerCase() === 'v') paste();
        else if (mod && e.key.toLowerCase() === 'g') { e.preventDefault(); e.shiftKey ? ungroupSel() : groupSel(); }
        else if (mod && e.key.toLowerCase() === 'a') { e.preventDefault(); const all = canvas.getObjects().filter((x) => !x.isBg && x.selectable !== false && x.visible !== false); if (all.length) { canvas.setActiveObject(new fabric.ActiveSelection(all, { canvas })); canvas.requestRenderAll(); } }
        else if (o && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
            e.preventDefault();
            const d = e.shiftKey ? 10 : 1;
            if (e.key === 'ArrowLeft') o.left -= d; if (e.key === 'ArrowRight') o.left += d;
            if (e.key === 'ArrowUp') o.top -= d; if (e.key === 'ArrowDown') o.top += d;
            o.setCoords(); canvas.requestRenderAll(); pushHistorySoon();
        } else if (e.key === 'Escape') { canvas.discardActiveObject(); canvas.requestRenderAll(); }
    });
    $('deUndo').addEventListener('click', undo);
    $('deRedo').addEventListener('click', redo);
    $('deZoomIn').addEventListener('click', () => applyZoom(zoom * 1.15));
    $('deZoomOut').addEventListener('click', () => applyZoom(zoom / 1.15));
    $('deZoomFit').addEventListener('click', fitZoom);
    stageEl.addEventListener('wheel', (e) => { if (e.ctrlKey) { e.preventDefault(); applyZoom(zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08)); } }, { passive: false });
    window.addEventListener('resize', () => { clearTimeout(window._deR); window._deR = setTimeout(fitZoom, 150); });
    $('deTitle').addEventListener('input', markDirty);
    // double-click an empty frame → pick a photo
    canvas.on('mouse:dblclick', (e) => { if (e.target && isEmptyFrame(e.target)) { replaceTarget = e.target; $('deReplaceInput').click(); } });

    /* =================================================== boot */
    applyZoom(1);
    fitZoom();
    loadTemplates();
    function bootBlank() {
        pages = [newPage()]; cur = 0;
        return showPage(0, true).then(() => { dirty = false; $('deSaveState').textContent = ''; });
    }
    if (BOOT.id) {
        post({ action: 'load', id: BOOT.id }).then((r) => {
            if (!r.ok) return bootBlank();
            loadJSONInto(r.design.json, r.design.width, r.design.height)
                .then(() => { dirty = false; $('deSaveState').textContent = 'All changes saved'; })
                .catch(() => { toast('Could not read this design.'); bootBlank(); });
        });
    } else if (BOOT.template) {
        post({ action: 'load', id: BOOT.template }).then((r) => {
            if (!r.ok) return bootBlank();
            $('deTitle').value = r.design.title;
            loadJSONInto(r.design.json, r.design.width, r.design.height).then(markDirty).catch(bootBlank);
        });
    } else {
        bootBlank();
    }
    updateCtx();
})();
