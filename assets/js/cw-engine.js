/*
 * Classic Wizard — pin rendering engine (browser canvas).
 *
 * CWEngine.render(canvas, spec) draws one pin. spec = {
 *   template: 't01'..'t50' | 'c<id>' (Canva SVG), size: '2:3' | '1:2.1' | '9:16' | '4:5' | '1:1',
 *   mode: 'single' | 'collage', images: [url...], headline, kicker, cta, website,
 *   palette: {bg, primary, secondary, accent, dark}, fonts: {main, secondary, accent},
 *   scale: 1 (0.3 for thumbnails)
 * }
 * All layout is expressed relative to the pin width (d.s = W / 1000), so the same template
 * renders identically as a 300px thumbnail and as a 1000px pin.
 */
(function (global) {
    'use strict';

    /* ===================== Sizes ===================== */
    const SIZES = {
        '2:3': { w: 1000, h: 1500, label: 'Standard 2:3', sub: '1000 × 1500' },
        '1:2.1': { w: 1000, h: 2100, label: 'Long 1:2.1', sub: '1000 × 2100' },
        '9:16': { w: 1080, h: 1920, label: 'Story 9:16', sub: '1080 × 1920' },
        '4:5': { w: 1000, h: 1250, label: 'Portrait 4:5', sub: '1000 × 1250' },
        '1:1': { w: 1000, h: 1000, label: 'Square 1:1', sub: '1000 × 1000' },
    };

    /* ===================== Palettes: [background, primary, secondary, accent, text] ===================== */
    const P = (id, name, c) => ({ id, name, bg: c[0], primary: c[1], secondary: c[2], accent: c[3], dark: c[4] });
    const PALETTES = [
        P('p01', 'Pinterest Red', ['#ffffff', '#e60023', '#fde8ec', '#ffb000', '#111111']),
        P('p02', 'Blush & Berry', ['#fff4f2', '#c2185b', '#f8bbd0', '#ff8a65', '#2b1320']),
        P('p03', 'Sage Kitchen', ['#f4f1e8', '#5b7b5a', '#c8d5b9', '#e2a857', '#1f2a1e']),
        P('p04', 'Ocean Breeze', ['#f0f7fa', '#0f6e8c', '#9ed2e0', '#f6a04d', '#0b2530']),
        P('p05', 'Terracotta Sun', ['#fbf3ea', '#c65d3b', '#f0c9a8', '#2f6f6a', '#2a1a12']),
        P('p06', 'Midnight Gold', ['#141a2e', '#e3b04b', '#2c3553', '#f5e6c4', '#0b0f1c']),
        P('p07', 'Lemon Fresh', ['#fffdf0', '#f2c500', '#fff2a8', '#2e86de', '#1d1d1d']),
        P('p08', 'Mint Candy', ['#f1fbf7', '#18a57b', '#bff0dd', '#ff6f91', '#10281f']),
        P('p09', 'Lavender Dream', ['#f7f3fd', '#7e57c2', '#d9c9f5', '#ffb74d', '#221a33']),
        P('p10', 'Coral Reef', ['#fff6f3', '#ff6b57', '#ffd3c9', '#1a9e9e', '#2a1512']),
        P('p11', 'Forest Cabin', ['#f3f0e6', '#2d5a3d', '#a9c4a0', '#b5651d', '#16241a']),
        P('p12', 'Cherry Cola', ['#fff5f5', '#9b1b30', '#f3c1c8', '#f5b700', '#230a10']),
        P('p13', 'Nordic Grey', ['#f5f6f7', '#37474f', '#cfd8dc', '#ff7043', '#111618']),
        P('p14', 'Peach Fuzz', ['#fff7f0', '#f28c63', '#ffd8bf', '#6d4c9b', '#2b1a10']),
        P('p15', 'Royal Blue', ['#f3f6ff', '#1f4fd8', '#c7d4ff', '#ffcc29', '#0e1633']),
        P('p16', 'Olive Market', ['#f7f5ec', '#6b7a2c', '#dfe3b8', '#d9534f', '#1f220f']),
        P('p17', 'Bubblegum', ['#fff3fa', '#ff4fa3', '#ffc9e6', '#35c2e0', '#2a0f1f']),
        P('p18', 'Espresso', ['#f6efe8', '#5d3a24', '#d9c1ab', '#d4a24c', '#1f130b']),
        P('p19', 'Teal Pop', ['#effbfa', '#00897b', '#b2ebe4', '#ff5252', '#062522']),
        P('p20', 'Sunset Orange', ['#fff8f0', '#f4511e', '#ffd0b8', '#ffc107', '#26130a']),
        P('p21', 'Dusty Rose', ['#faf2f1', '#b5727a', '#ecd0d1', '#6b8f8a', '#2c1b1d']),
        P('p22', 'Black & White', ['#ffffff', '#111111', '#e7e7e7', '#e60023', '#111111']),
        P('p23', 'Emerald Luxe', ['#f1f7f3', '#0b6e4f', '#c4e3d4', '#c9a227', '#07261b']),
        P('p24', 'Plum Wine', ['#f9f2f7', '#6a1b4d', '#e3c1d6', '#f2a541', '#220917']),
        P('p25', 'Sky & Sand', ['#f7f4ee', '#4a90c2', '#cfe3f2', '#d8a25e', '#18283a']),
        P('p26', 'Cocoa Cream', ['#fbf6f0', '#8d5b4c', '#ecd9cb', '#e8b04a', '#2b1a14']),
        P('p27', 'Neon Night', ['#10131a', '#00e5ff', '#1f2533', '#ff2e97', '#05070b']),
        P('p28', 'Pastel Rainbow', ['#fffdf8', '#f78fb3', '#a0e7e5', '#fbc531', '#2d2d3a']),
        P('p29', 'Rustic Barn', ['#f5eee6', '#8b2e2e', '#e1c9b0', '#3b5249', '#231313']),
        P('p30', 'Arctic Mint', ['#f2fbfc', '#26a69a', '#c9f0ec', '#5c6bc0', '#0f2a2a']),
        P('p31', 'Honey Mustard', ['#fffaf0', '#d4a017', '#f6e2a8', '#6b3e26', '#261c05']),
        P('p32', 'Indigo Ink', ['#f4f4fb', '#303f9f', '#c5cae9', '#ff8f00', '#0d1133']),
        P('p33', 'Watermelon', ['#fff6f6', '#ef476f', '#ffd6de', '#06d6a0', '#2a0f16']),
        P('p34', 'Desert Clay', ['#f8f1ea', '#a0522d', '#e8cdb5', '#556b2f', '#2a170b']),
        P('p35', 'Baby Blue', ['#f5faff', '#5aa9e6', '#d6ecfb', '#ff9f80', '#16283a']),
        P('p36', 'Champagne', ['#fbf8f2', '#b89b72', '#eee3d0', '#3d3d3d', '#221c13']),
        P('p37', 'Tomato Basil', ['#fffaf5', '#d62828', '#fcd5b5', '#2a9d8f', '#231010']),
        P('p38', 'Moody Teal', ['#1d2d33', '#4fb3bf', '#2e4750', '#f4d35e', '#0c1418']),
        P('p39', 'Lilac Peach', ['#fdf6fb', '#b388eb', '#ffd6c2', '#f76f8e', '#2a1d33']),
        P('p40', 'Bold Yellow', ['#1a1a1a', '#ffd400', '#333333', '#ffffff', '#0d0d0d']),
        P('p41', 'Garden Party', ['#f7fbf2', '#7cb342', '#dcedc8', '#ec407a', '#1b2a10']),
        P('p42', 'Copper Glow', ['#fbf5f0', '#b87333', '#f0d6c0', '#264653', '#24150a']),
        P('p43', 'Holiday Red', ['#fbf7f2', '#b3001b', '#e9d5c5', '#1f7a3a', '#200308']),
        P('p44', 'Frosty Blue', ['#f3f8fb', '#2c6e9b', '#d0e6f3', '#b0c4de', '#0e2233']),
        P('p45', 'Pumpkin Spice', ['#fdf5ec', '#d35400', '#f5cba7', '#6e2c00', '#2a1203']),
        P('p46', 'Spring Tulip', ['#fff8fb', '#e84a8a', '#fdd1e3', '#7fb069', '#2b0f1c']),
        P('p47', 'Graphite Lime', ['#f4f5f1', '#2f3542', '#dfe4d0', '#9acd32', '#12151a']),
        P('p48', 'Caramel Latte', ['#faf4ec', '#a47148', '#ead7c3', '#3e7cb1', '#281a0f']),
        P('p49', 'Electric Violet', ['#f7f2ff', '#6c2bd9', '#dccbff', '#00c2a8', '#1a0b3b']),
        P('p50', 'Seafoam Coral', ['#f2fbf8', '#2ec4b6', '#cbf3f0', '#ff9f1c', '#0b2723']),
        P('p51', 'Classic Navy', ['#f5f7fa', '#1b2a4a', '#d6dde9', '#e63946', '#0b1222']),
        P('p52', 'Rosewood', ['#fbf3f3', '#7d2e3b', '#ecd2d5', '#d9a441', '#240c11']),
        P('p53', 'Matcha Latte', ['#f5f8ee', '#8aa35b', '#dfe8c8', '#6d4c41', '#1e2511']),
        P('p54', 'Tropical', ['#fffbf2', '#00a86b', '#ffe29a', '#ff6b35', '#0d261b']),
        P('p55', 'Soft Charcoal', ['#f6f6f6', '#3a3a3a', '#dcdcdc', '#d4af37', '#121212']),
        P('p56', 'Candy Apple', ['#fff5f4', '#ff0800', '#ffd1cf', '#1e88e5', '#240302']),
    ];

    /* ===================== Fonts ===================== */
    const FONT_COMBOS = [
        { name: 'Elegant', main: 'Playfair Display', secondary: 'Montserrat', accent: 'Great Vibes' },
        { name: 'Bold impact', main: 'Anton', secondary: 'Roboto', accent: 'Pacifico' },
        { name: 'Modern clean', main: 'Bebas Neue', secondary: 'Open Sans', accent: 'Dancing Script' },
        { name: 'Friendly', main: 'Poppins', secondary: 'Lato', accent: 'Satisfy' },
        { name: 'Editorial', main: 'Abril Fatface', secondary: 'Raleway', accent: 'Allura' },
        { name: 'Handmade', main: 'Oswald', secondary: 'Nunito', accent: 'Caveat' },
    ];
    const FONTS = [
        'Abril Fatface', 'Alegreya', 'Alex Brush', 'Alfa Slab One', 'Allura', 'Amatic SC', 'Anton', 'Architects Daughter', 'Archivo', 'Archivo Black',
        'Arvo', 'Baloo 2', 'Bangers', 'Barlow', 'Barlow Condensed', 'Bebas Neue', 'Bitter', 'Bodoni Moda', 'Bungee', 'Cabin', 'Caveat', 'Changa One',
        'Chewy', 'Cinzel', 'Comfortaa', 'Cookie', 'Cormorant Garamond', 'Courgette', 'Covered By Your Grace', 'Crimson Text', 'Dancing Script',
        'DM Sans', 'DM Serif Display', 'DM Serif Text', 'EB Garamond', 'Exo 2', 'Figtree', 'Fjalla One', 'Fraunces', 'Fredoka', 'Gloock', 'Gochi Hand',
        'Great Vibes', 'Gruppo', 'Heebo', 'Hind', 'Homemade Apple', 'Indie Flower', 'Inter', 'Italiana', 'Josefin Sans', 'Josefin Slab', 'Just Another Hand',
        'Kalam', 'Kanit', 'Karla', 'Kaushan Script', 'Lato', 'League Spartan', 'Lexend', 'Libre Baskerville', 'Libre Caslon Text', 'Lobster', 'Lora',
        'Luckiest Guy', 'Manrope', 'Marcellus', 'Merriweather', 'Monoton', 'Montserrat', 'Mr Dafoe', 'Mulish', 'Noto Sans', 'Noto Serif', 'Nothing You Could Do',
        'Nunito', 'Old Standard TT', 'Open Sans', 'Oswald', 'Outfit', 'Pacifico', 'Parisienne', 'Passion One', 'Patrick Hand', 'Paytone One',
        'Permanent Marker', 'Pinyon Script', 'Playfair Display', 'Playfair Display SC', 'Plus Jakarta Sans', 'Poiret One', 'Poppins', 'Prata', 'PT Sans',
        'PT Serif', 'Quicksand', 'Raleway', 'Red Hat Display', 'Reenie Beanie', 'Righteous', 'Roboto', 'Roboto Slab', 'Rock Salt', 'Rozha One', 'Rubik',
        'Russo One', 'Sacramento', 'Satisfy', 'Shadows Into Light', 'Six Caps', 'Sora', 'Source Sans 3', 'Space Grotesk', 'Special Elite', 'Spectral',
        'Staatliches', 'Syncopate', 'Tangerine', 'Teko', 'Titan One', 'Titillium Web', 'Ubuntu', 'Ultra', 'Urbanist', 'Varela Round', 'Work Sans',
        'Yellowtail', 'Yeseva One', 'Zilla Slab',
    ];
    const SCRIPTS = new Set(['Alex Brush', 'Allura', 'Great Vibes', 'Pacifico', 'Dancing Script', 'Satisfy', 'Sacramento', 'Parisienne', 'Kaushan Script',
        'Lobster', 'Caveat', 'Cookie', 'Mr Dafoe', 'Pinyon Script', 'Tangerine', 'Yellowtail', 'Homemade Apple', 'Courgette', 'Kalam', 'Indie Flower',
        'Shadows Into Light', 'Gochi Hand', 'Reenie Beanie', 'Covered By Your Grace', 'Just Another Hand', 'Nothing You Could Do', 'Rock Salt', 'Patrick Hand', 'Architects Daughter']);

    /*
     * Font loading, batched: all font families requested within a few milliseconds go into ONE
     * stylesheet request (the Google Fonts v1 API accepts weights a family doesn't have, so
     * "Name:400,700,900" is always valid). Font files are then fetched only for the weights a
     * pin actually draws — not every weight of every font.
     */
    function addSheet(href) {
        return new Promise((resolve) => {
            const l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = href;
            l.onload = () => resolve(true);
            l.onerror = () => resolve(false);
            document.head.appendChild(l);
            setTimeout(() => resolve(false), 6000);
        });
    }
    function withTimeout(p, ms) { return Promise.race([p, new Promise((r) => setTimeout(r, ms))]); }

    const sheetFor = new Map();   // family -> Promise (its stylesheet is in the page)
    let sheetQueue = [];
    let sheetTimer = null;
    function flushSheets() {
        const queue = sheetQueue;
        sheetQueue = [];
        sheetTimer = null;
        for (let i = 0; i < queue.length; i += 15) {
            const chunk = queue.slice(i, i + 15);
            const href = 'https://fonts.googleapis.com/css?family=' + chunk.map((q) => encodeURIComponent(q.name).replace(/%20/g, '+') + ':400,700,900').join('|') + '&display=swap';
            addSheet(href).then(() => chunk.forEach((q) => q.resolve()));
        }
    }
    /** Makes sure the @font-face rules for a family are in the page (no font files downloaded yet). */
    function ensureFontSheet(name) {
        if (!name) return Promise.resolve();
        if (!sheetFor.has(name)) {
            sheetFor.set(name, new Promise((resolve) => {
                sheetQueue.push({ name, resolve });
                if (!sheetTimer) sheetTimer = setTimeout(flushSheets, 15);
            }));
        }
        return sheetFor.get(name);
    }

    const faceReady = new Map();  // "weight|family" -> Promise
    /** Loads one family at the given weights (default regular) so the canvas can draw with it. */
    function loadFont(name, weights) {
        if (!name) return Promise.resolve();
        weights = weights || [400];
        return Promise.all(weights.map((w) => {
            const key = w + '|' + name;
            if (!faceReady.has(key)) {
                faceReady.set(key, ensureFontSheet(name)
                    .then(() => withTimeout(document.fonts.load(`${w} 40px "${name}"`), 5000))
                    .catch(() => {}));
            }
            return faceReady.get(key);
        }));
    }
    /** Only the weights pins use: bold headline, regular + bold secondary, regular accent. */
    function loadFonts(fonts) {
        return Promise.all([loadFont(fonts.main, [700, 900]), loadFont(fonts.secondary, [400, 700]), loadFont(fonts.accent, [400])]);
    }

    /* ===================== Images ===================== */
    const imgCache = new Map();
    function loadImage(url) {
        if (!url) return Promise.resolve(null);
        if (imgCache.has(url)) return imgCache.get(url);
        const p = new Promise((resolve) => {
            const img = new Image();
            img.decoding = 'async';
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = url;
        });
        imgCache.set(url, p);
        return p;
    }

    /* ===================== Colour helpers ===================== */
    function rgb(h) {
        h = String(h || '#000').replace('#', '');
        if (h.length === 3) h = h.split('').map((c) => c + c).join('');
        const n = parseInt(h, 16) || 0;
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }
    function lum(h) {
        const [r, g, b] = rgb(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }
    function contrast(a, b) { const la = lum(a), lb = lum(b); return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05); }
    /** Readable text colour on a fill: the palette's text colour or white, whichever contrasts more. */
    function on(fill, dark) { dark = dark || '#111111'; return contrast(fill, dark) >= contrast(fill, '#ffffff') ? dark : '#ffffff'; }
    /** First candidate that reads well on the fill, else the plain readable colour. */
    function pick(fill, cands, dark, min) {
        for (const c of cands) if (c && contrast(fill, c) >= (min || 2.6)) return c;
        return on(fill, dark);
    }
    function rgba(h, a) { const [r, g, b] = rgb(h); return `rgba(${r},${g},${b},${a})`; }
    function mix(a, b, t) {
        const x = rgb(a), y = rgb(b);
        return '#' + x.map((v, i) => Math.round(v + (y[i] - v) * t).toString(16).padStart(2, '0')).join('');
    }

    /* ===================== Drawing helpers ===================== */
    const F = (w, px, fam, fb) => `${w} ${Math.max(1, Math.round(px))}px "${fam}", ${fb || 'Arial, sans-serif'}`;
    function ls(ctx, px) { if ('letterSpacing' in ctx) ctx.letterSpacing = (px ? px.toFixed(1) : 0) + 'px'; }

    function rr(ctx, x, y, w, h, r) {
        r = Math.max(0, Math.min(r, w / 2, h / 2));
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }
    function archPath(ctx, x, y, w, h) {
        const r = w / 2;
        ctx.beginPath();
        ctx.moveTo(x, y + h);
        ctx.lineTo(x, y + r);
        ctx.arc(x + r, y + r, r, Math.PI, 0);
        ctx.lineTo(x + w, y + h);
        ctx.closePath();
    }
    function shadowOn(ctx, s, alpha) { ctx.shadowColor = `rgba(0,0,0,${alpha || 0.28})`; ctx.shadowBlur = 40 * s; ctx.shadowOffsetY = 12 * s; }
    function shadowOff(ctx) { ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0; ctx.shadowOffsetX = 0; }

    function wrapLines(ctx, text, maxW) {
        const words = String(text).split(/\s+/).filter(Boolean);
        const lines = [];
        let cur = '';
        for (const w of words) {
            const t = cur ? cur + ' ' + w : w;
            if (ctx.measureText(t).width > maxW && cur) { lines.push(cur); cur = w; } else cur = t;
        }
        if (cur) lines.push(cur);
        return lines.length ? lines : [''];
    }
    /** Largest font size (max → min) at which text wraps inside maxW × maxH in at most maxLines lines. */
    function fitText(ctx, text, o) {
        const t = o.upper ? String(text).toUpperCase() : String(text);
        const lh = o.lh || 1.08, maxLines = o.maxLines || 5;
        let size = o.max, lines = [], widest = 0;
        for (let i = 0; i < 45; i++) {
            ctx.font = F(o.weight || 800, size, o.fam, o.fb);
            ls(ctx, (o.ls || 0) * size);
            lines = wrapLines(ctx, t, o.maxW);
            widest = Math.max(...lines.map((l) => ctx.measureText(l).width));
            if (lines.length <= maxLines && lines.length * size * lh <= o.maxH && widest <= o.maxW) break;
            if (size <= o.min) break;
            size = Math.max(o.min, size * 0.93);
        }
        if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1].replace(/[\s,.;:!-]*$/, '') + '…'; }
        ls(ctx, 0);
        return { lines, size, lh, height: lines.length * size * lh, width: widest, fam: o.fam, weight: o.weight || 800, ls: o.ls || 0, fb: o.fb };
    }
    function drawFit(ctx, f, x, top, o) {
        o = o || {};
        ctx.font = F(f.weight, f.size, f.fam, f.fb);
        ls(ctx, f.ls * f.size);
        ctx.textAlign = o.align || 'center';
        ctx.textBaseline = 'middle';
        f.lines.forEach((ln, i) => {
            const y = top + (i + 0.5) * f.size * f.lh;
            // per-line style hook: {color, stroke, box} for this line
            const st = o.lineStyle ? (o.lineStyle(i, f.lines.length) || {}) : {};
            const col = st.color || (o.colors ? o.colors[i % o.colors.length] : o.color);
            const boxC = st.box !== undefined ? st.box : (o.boxes ? o.boxes[i % o.boxes.length] : null);
            if (boxC) {
                // highlight box behind each line
                const pad = f.size * (o.boxPad || 0.25);
                const w = ctx.measureText(ln).width + pad * 2;
                const bx = o.align === 'left' ? x - pad : o.align === 'right' ? x - w + pad : x - w / 2;
                ctx.fillStyle = boxC;
                ctx.fillRect(bx, y - f.size * f.lh * 0.5 + f.size * 0.04, w, f.size * f.lh * 0.92);
            }
            if (o.shadow) { ctx.shadowColor = o.shadow; ctx.shadowBlur = f.size * 0.3; ctx.shadowOffsetY = f.size * 0.04; }
            const strokeC = st.stroke !== undefined ? st.stroke : o.stroke;
            if (strokeC) {
                ctx.lineJoin = 'round';
                ctx.miterLimit = 2;
                ctx.lineWidth = o.strokeW || f.size * 0.14;
                ctx.strokeStyle = strokeC;
                ctx.strokeText(ln, x, y);
            }
            if (!o.strokeOnly || !o.strokeOnly(i)) { ctx.fillStyle = col; ctx.fillText(ln, x, y); }
            shadowOff(ctx);
        });
        ls(ctx, 0);
        return top + f.height;
    }
    /** Single line of text shrunk to fit maxW. */
    function line(ctx, text, x, y, o) {
        let size = o.size;
        ctx.font = F(o.weight || 400, size, o.fam, o.fb);
        ls(ctx, (o.ls || 0) * size);
        while (ctx.measureText(text).width > o.maxW && size > 8) { size *= 0.93; ctx.font = F(o.weight || 400, size, o.fam, o.fb); ls(ctx, (o.ls || 0) * size); }
        ctx.textAlign = o.align || 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = o.color;
        ctx.fillText(text, x, y);
        ls(ctx, 0);
        return size;
    }
    function pill(ctx, d, x, cy, o) {
        const s = d.s, h = (o.h || 66) * s;
        const text = String(d.cta).toUpperCase();
        ctx.font = F(700, 26 * s, d.fonts.secondary);
        ls(ctx, 2 * s);
        const w = Math.min(d.W * 0.8, ctx.measureText(text).width + 70 * s);
        ls(ctx, 0);
        const bx = o.align === 'left' ? x : o.align === 'right' ? x - w : x - w / 2;
        rr(ctx, bx, cy - h / 2, w, h, h / 2);
        ctx.fillStyle = o.bg;
        ctx.fill();
        line(ctx, text, bx + w / 2, cy + 1 * s, { size: 26 * s, weight: 700, fam: d.fonts.secondary, color: o.fg, maxW: w - 40 * s, ls: 0.08 });
    }
    function website(ctx, d, x, y, color, o) {
        if (!d.website) return;
        o = o || {};
        line(ctx, d.website.toUpperCase(), x, y, { size: (o.size || 24) * d.s, weight: 700, fam: d.fonts.secondary, color, maxW: o.maxW || d.W * 0.8, ls: 0.12, align: o.align || 'center' });
    }

    /**
     * Kicker (accent font) + headline (main font) + optional CTA and website, fitted into box b.
     * o: {color, kickerColor, align, upper, weight, max, min, lh, ls, maxLines, cta:{bg,fg}, web:colour, valign, pad, colors, stroke, strokeW, shadow, boxes}
     */
    function textBlock(ctx, d, b, o) {
        const s = d.s, align = o.align || 'center', pad = (o.pad == null ? 30 : o.pad) * s;
        const innerW = b.w - pad * 2;
        const kick = o.kicker === false ? '' : (d.kicker || '');
        const kSize = (o.kSize || 58) * s * (SCRIPTS.has(d.fonts.accent) ? 1 : 0.62);
        const kH = kick ? kSize * 1.2 : 0, kGap = kick ? 10 * s : 0;
        const hasCta = !!(o.cta && d.cta), ctaH = hasCta ? 66 * s : 0, ctaGap = hasCta ? 26 * s : 0;
        const hasWeb = !!(o.web && d.website), webH = hasWeb ? 32 * s : 0, webGap = hasWeb ? 16 * s : 0;
        const maxHead = Math.max(40 * s, b.h - pad * 2 - kH - kGap - ctaH - ctaGap - webH - webGap);
        const f = fitText(ctx, d.headline, {
            fam: o.fam || d.fonts.main, weight: o.weight || 800, maxW: innerW, maxH: maxHead,
            max: (o.max || 120) * s, min: (o.min || 26) * s, lh: o.lh || 1.06, upper: !!o.upper, ls: o.ls || 0, maxLines: o.maxLines || 5,
        });
        const total = kH + kGap + f.height + ctaGap + ctaH + webGap + webH;
        let y = b.y + (o.valign === 'top' ? pad : o.valign === 'bottom' ? b.h - pad - total : (b.h - total) / 2);
        const x = align === 'center' ? b.x + b.w / 2 : align === 'left' ? b.x + pad : b.x + b.w - pad;
        const top = y;
        if (kick) {
            line(ctx, SCRIPTS.has(d.fonts.accent) ? kick : kick.toUpperCase(), x, y + kH / 2, {
                size: kSize, weight: SCRIPTS.has(d.fonts.accent) ? 400 : 700, fam: d.fonts.accent, fb: 'cursive', color: o.kickerColor || o.color,
                maxW: innerW, align, ls: SCRIPTS.has(d.fonts.accent) ? 0 : 0.15,
            });
            y += kH + kGap;
        }
        y = drawFit(ctx, f, x, y, { align, color: o.color, colors: o.colors, stroke: o.stroke, strokeW: o.strokeW, shadow: o.shadow, boxes: o.boxes, strokeOnly: o.strokeOnly, lineStyle: o.lineStyle, boxPad: o.boxPad });
        if (hasCta) { y += ctaGap; pill(ctx, d, x, y + ctaH / 2, { bg: o.cta.bg, fg: o.cta.fg, align }); y += ctaH; }
        if (hasWeb) { y += webGap; website(ctx, d, x, y + webH / 2, o.web, { align, maxW: innerW }); y += webH; }
        return { top, bottom: y, size: f.size };
    }

    /* ===================== Photos ===================== */
    function cover(ctx, img, x, y, w, h, fy) {
        if (!img || !img.naturalWidth) {
            const g = ctx.createLinearGradient(x, y, x + w, y + h);
            g.addColorStop(0, '#d8d2cb'); g.addColorStop(1, '#a9a198');
            ctx.fillStyle = g; ctx.fillRect(x, y, w, h);
            return;
        }
        const iw = img.naturalWidth, ih = img.naturalHeight;
        const r = Math.max(w / iw, h / ih), sw = w / r, sh = h / r;
        ctx.drawImage(img, (iw - sw) / 2, (ih - sh) * (fy == null ? 0.4 : fy), sw, sh, x, y, w, h);
    }
    /** Fills a rect with the pin's photo — or, in collage mode, a 2/3/4-photo grid. */
    function photos(ctx, d, x, y, w, h, o) {
        o = o || {};
        const imgs = d.imgs.length ? d.imgs : [null];
        ctx.save();
        ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
        if (d.mode !== 'collage' || imgs.length < 2 || o.single) {
            cover(ctx, imgs[(o.index || 0) % imgs.length], x, y, w, h);
            ctx.restore();
            return;
        }
        const g = o.gap == null ? Math.round(8 * d.s) : o.gap;
        ctx.fillStyle = o.gapColor || '#ffffff';
        ctx.fillRect(x, y, w, h);
        const n = Math.min(imgs.length, o.max || 4);
        const tall = h >= w * 0.9;
        let cells;
        if (n === 2) cells = tall ? [[0, 0, 1, 0.5], [0, 0.5, 1, 0.5]] : [[0, 0, 0.5, 1], [0.5, 0, 0.5, 1]];
        else if (n === 3) cells = tall ? [[0, 0, 1, 0.55], [0, 0.55, 0.5, 0.45], [0.5, 0.55, 0.5, 0.45]] : [[0, 0, 0.6, 1], [0.6, 0, 0.4, 0.5], [0.6, 0.5, 0.4, 0.5]];
        else cells = [[0, 0, 0.5, 0.5], [0.5, 0, 0.5, 0.5], [0, 0.5, 0.5, 0.5], [0.5, 0.5, 0.5, 0.5]];
        cells.forEach((c, i) => {
            const l = c[0] > 0 ? g / 2 : 0, r = c[0] + c[2] < 0.999 ? g / 2 : 0;
            const t = c[1] > 0 ? g / 2 : 0, b = c[1] + c[3] < 0.999 ? g / 2 : 0;
            cover(ctx, imgs[i % imgs.length], x + c[0] * w + l, y + c[1] * h + t, c[2] * w - l - r, c[3] * h - t - b);
        });
        ctx.restore();
    }
    function clipPhotos(ctx, d, pathFn, x, y, w, h, o) {
        ctx.save();
        pathFn();
        ctx.clip();
        photos(ctx, d, x, y, w, h, o);
        ctx.restore();
    }
    function pattern(ctx, d, kind, color, x, y, w, h) {
        if (!kind || kind === 'none') return;
        const s = d.s;
        ctx.save();
        ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
        ctx.fillStyle = color; ctx.strokeStyle = color;
        if (kind === 'dots') {
            const st = 34 * s;
            for (let yy = y; yy < y + h; yy += st) for (let xx = x + ((yy / st) % 2 ? st / 2 : 0); xx < x + w; xx += st) { ctx.beginPath(); ctx.arc(xx, yy, 3.2 * s, 0, 7); ctx.fill(); }
        } else if (kind === 'stripes') {
            ctx.lineWidth = 10 * s;
            for (let k = -h; k < w + h; k += 44 * s) { ctx.beginPath(); ctx.moveTo(x + k, y); ctx.lineTo(x + k + h, y + h); ctx.stroke(); }
        } else if (kind === 'grid') {
            ctx.lineWidth = 1.5 * s;
            for (let xx = x; xx < x + w; xx += 50 * s) { ctx.beginPath(); ctx.moveTo(xx, y); ctx.lineTo(xx, y + h); ctx.stroke(); }
            for (let yy = y; yy < y + h; yy += 50 * s) { ctx.beginPath(); ctx.moveTo(x, yy); ctx.lineTo(x + w, yy); ctx.stroke(); }
        }
        ctx.restore();
    }

    /* ===================== Template families ===================== */
    const FAM = {};

    /** Photo top and bottom (two photos in collage mode), a colour band across the middle. */
    FAM.bandCenter = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        if (d.mode === 'collage' && d.imgs.length >= 2) {
            photos(ctx, d, 0, 0, W, H / 2, { single: true, index: 0 });
            photos(ctx, d, 0, H / 2, W, H / 2, { single: true, index: 1 });
        } else photos(ctx, d, 0, 0, W, H);
        const bh = Math.min(H * 0.36, W * 0.56), by = (H - bh) / 2;
        const fill = p[o.band || 'primary'];
        const txt = on(fill, p.dark);
        const kc = pick(fill, [p.accent, p.secondary, p.primary], p.dark);
        ctx.save();
        if (o.shape === 'tilt') { ctx.translate(W / 2, H / 2); ctx.rotate(-0.06); ctx.translate(-W / 2, -H / 2); }
        let box = { x: 0, y: by, w: W, h: bh };
        if (o.shape === 'inset') {
            shadowOn(ctx, s, 0.25); rr(ctx, W * 0.07, by, W * 0.86, bh, 26 * s); ctx.fillStyle = fill; ctx.fill(); shadowOff(ctx);
            ctx.lineWidth = 3 * s; ctx.strokeStyle = rgba(txt, 0.35); rr(ctx, W * 0.09, by + W * 0.02, W * 0.82, bh - W * 0.04, 18 * s); ctx.stroke();
            box = { x: W * 0.09, y: by, w: W * 0.82, h: bh };
        } else if (o.shape === 'torn') {
            ctx.beginPath();
            const teeth = 26, tw = W / teeth, th = 16 * s;
            ctx.moveTo(0, by);
            for (let i = 0; i <= teeth; i++) ctx.lineTo(i * tw, by + (i % 2 ? -th : th * 0.3));
            ctx.lineTo(W, by + bh);
            for (let i = teeth; i >= 0; i--) ctx.lineTo(i * tw, by + bh + (i % 2 ? th : -th * 0.3));
            ctx.closePath();
            shadowOn(ctx, s, 0.2); ctx.fillStyle = fill; ctx.fill(); shadowOff(ctx);
            box = { x: W * 0.06, y: by, w: W * 0.88, h: bh };
        } else if (o.shape === 'tilt') {
            ctx.fillStyle = fill; ctx.fillRect(-W * 0.1, by, W * 1.2, bh);
            box = { x: W * 0.06, y: by, w: W * 0.88, h: bh };
        } else {
            ctx.fillStyle = fill; ctx.fillRect(0, by, W, bh);
            if (o.shape === 'lines') {
                ctx.fillStyle = kc;
                ctx.fillRect(W * 0.1, by + 22 * s, W * 0.8, 4 * s);
                ctx.fillRect(W * 0.1, by + bh - 26 * s, W * 0.8, 4 * s);
            }
            box = { x: W * 0.06, y: by + (o.shape === 'lines' ? 20 * s : 0), w: W * 0.88, h: bh - (o.shape === 'lines' ? 40 * s : 0) };
        }
        textBlock(ctx, d, box, { color: txt, kickerColor: kc, upper: o.upper, pad: 34, max: 118, web: txt });
        ctx.restore();
    };

    /** A shaped colour panel at the top or bottom, photo fills the rest. */
    FAM.edgeBand = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const frac = o.frac || 0.36, bh = H * frac, top = o.pos === 'top';
        const fill = p[o.band || 'primary'];
        const txt = on(fill, p.dark);
        const kc = pick(fill, [p.accent, p.secondary, p.primary], p.dark);
        photos(ctx, d, 0, top ? bh * 0.8 : 0, W, H - bh * 0.8);
        const edge = 70 * s;
        ctx.beginPath();
        if (top) {
            ctx.moveTo(0, 0); ctx.lineTo(W, 0); ctx.lineTo(W, bh);
            if (o.edge === 'wave') { for (let i = 4; i >= 0; i--) { const x0 = (i / 4) * W; ctx.quadraticCurveTo(x0 + W / 8, bh + (i % 2 ? edge : -edge) * 0.6, x0, bh); } }
            else if (o.edge === 'arc') ctx.quadraticCurveTo(W / 2, bh + edge * 1.6, 0, bh);
            else if (o.edge === 'slant') ctx.lineTo(0, bh + edge * 1.2);
            else if (o.edge === 'notch') { ctx.lineTo(W / 2 + edge, bh); ctx.lineTo(W / 2, bh + edge); ctx.lineTo(W / 2 - edge, bh); ctx.lineTo(0, bh); }
            else ctx.lineTo(0, bh);
        } else {
            const y0 = H - bh;
            ctx.moveTo(0, H); ctx.lineTo(W, H); ctx.lineTo(W, y0);
            if (o.edge === 'wave') { for (let i = 4; i >= 0; i--) { const x0 = (i / 4) * W; ctx.quadraticCurveTo(x0 + W / 8, y0 + (i % 2 ? -edge : edge) * 0.6, x0, y0); } }
            else if (o.edge === 'arc') ctx.quadraticCurveTo(W / 2, y0 - edge * 1.6, 0, y0);
            else if (o.edge === 'slant') ctx.lineTo(0, y0 - edge * 1.2);
            else if (o.edge === 'notch') { ctx.lineTo(W / 2 + edge, y0); ctx.lineTo(W / 2, y0 - edge); ctx.lineTo(W / 2 - edge, y0); ctx.lineTo(0, y0); }
            else ctx.lineTo(0, y0);
        }
        ctx.closePath();
        shadowOn(ctx, s, 0.18); ctx.fillStyle = fill; ctx.fill(); shadowOff(ctx);
        const box = top ? { x: W * 0.05, y: 10 * s, w: W * 0.9, h: bh - 10 * s } : { x: W * 0.05, y: H - bh + (o.edge && o.edge !== 'straight' ? 30 * s : 0), w: W * 0.9, h: bh - (o.edge && o.edge !== 'straight' ? 30 * s : 0) };
        textBlock(ctx, d, box, { color: txt, kickerColor: kc, upper: o.upper, max: 116, web: rgba(txt, 0.85) });
        if (top && d.website) {
            const wy = H - 58 * s;
            ctx.font = F(700, 22 * s, d.fonts.secondary); ls(ctx, 2.6 * s);
            const tw = ctx.measureText(d.website.toUpperCase()).width + 50 * s; ls(ctx, 0);
            rr(ctx, W / 2 - tw / 2, wy - 22 * s, tw, 44 * s, 22 * s); ctx.fillStyle = rgba('#ffffff', 0.92); ctx.fill();
            website(ctx, d, W / 2, wy + 1 * s, '#111111', { size: 22 });
        }
    };

    /** Full-bleed photo with a scrim; text straight on the photo. */
    FAM.overlay = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        photos(ctx, d, 0, 0, W, H);
        let g;
        if (o.scrim === 'full') { ctx.fillStyle = 'rgba(0,0,0,0.42)'; ctx.fillRect(0, 0, W, H); }
        else if (o.scrim === 'tint') { ctx.fillStyle = rgba(p.primary, 0.55); ctx.fillRect(0, 0, W, H); ctx.fillStyle = 'rgba(0,0,0,0.18)'; ctx.fillRect(0, 0, W, H); }
        else if (o.scrim === 'top') { g = ctx.createLinearGradient(0, 0, 0, H * 0.62); g.addColorStop(0, 'rgba(0,0,0,0.82)'); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); }
        else { g = ctx.createLinearGradient(0, H * 0.3, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(0.55, 'rgba(0,0,0,0.6)'); g.addColorStop(1, 'rgba(0,0,0,0.88)'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); }
        const light = ['#ffffff', pick('#111111', [p.accent, p.secondary, p.primary], p.dark, 4), pick('#111111', [p.secondary, p.accent, '#ffe066'], p.dark, 4)];
        const box = o.pos === 'top' ? { x: W * 0.05, y: H * 0.04, w: W * 0.9, h: H * 0.5 } : o.pos === 'center' ? { x: W * 0.05, y: H * 0.2, w: W * 0.9, h: H * 0.6 } : { x: W * 0.05, y: H * 0.45, w: W * 0.9, h: H * 0.52 };
        textBlock(ctx, d, box, {
            color: '#ffffff', colors: o.multi ? light : null, kickerColor: light[1], upper: o.upper, max: o.stroke ? 130 : 118,
            stroke: o.stroke ? p.dark : null, strokeW: o.stroke ? 11 * s : null, shadow: o.stroke ? null : 'rgba(0,0,0,0.45)',
            valign: o.pos === 'top' ? 'top' : o.pos === 'center' ? null : 'bottom', web: 'rgba(255,255,255,0.9)',
            cta: o.cta ? { bg: p.primary, fg: on(p.primary, p.dark) } : null,
        });
    };

    /** Full-bleed photo with a floating card (rounded, circle, arch, ticket or square). */
    FAM.card = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        photos(ctx, d, 0, 0, W, H);
        const fill = p[o.role || 'bg'];
        const txt = on(fill, p.dark);
        const kc = pick(fill, [p.primary, p.accent, p.secondary], p.dark);
        const pos = o.pos || 'center';
        if (o.shape === 'circle') {
            const r = W * 0.37, cy = pos === 'bottom' ? H - r - H * 0.07 : H / 2;
            shadowOn(ctx, s, 0.3); ctx.beginPath(); ctx.arc(W / 2, cy, r, 0, 7); ctx.fillStyle = fill; ctx.fill(); shadowOff(ctx);
            ctx.lineWidth = 4 * s; ctx.strokeStyle = rgba(txt, 0.3); ctx.beginPath(); ctx.arc(W / 2, cy, r - 18 * s, 0, 7); ctx.stroke();
            textBlock(ctx, d, { x: W / 2 - r * 0.72, y: cy - r * 0.7, w: r * 1.44, h: r * 1.4 }, { color: txt, kickerColor: kc, upper: o.upper, pad: 6, max: 90, maxLines: 4 });
            website(ctx, d, W / 2, H - 40 * s, '#ffffff', { size: 22 });
            return;
        }
        const cw = W * 0.82, ch = Math.min(H * 0.38, W * 0.62);
        const cx = (W - cw) / 2;
        const cy = pos === 'bottom' ? H - ch - H * 0.05 : pos === 'top' ? H * 0.05 : (H - ch) / 2;
        shadowOn(ctx, s, 0.3);
        ctx.fillStyle = fill;
        if (o.shape === 'arch') { archPath(ctx, cx, cy - cw * 0.2, cw, ch + cw * 0.2); ctx.fill(); }
        else if (o.shape === 'ticket') {
            rr(ctx, cx, cy, cw, ch, 14 * s); ctx.fill(); shadowOff(ctx);
            // ticket notches: re-draw the photo inside two half-circles on the card edges
            [[cx, cy + ch / 2], [cx + cw, cy + ch / 2]].forEach(([nx, ny]) => clipPhotos(ctx, d, () => { ctx.beginPath(); ctx.arc(nx, ny, 26 * s, 0, 7); }, 0, 0, W, H));
            ctx.setLineDash([10 * s, 10 * s]); ctx.lineWidth = 3 * s; ctx.strokeStyle = rgba(txt, 0.35);
            rr(ctx, cx + 38 * s, cy + 22 * s, cw - 76 * s, ch - 44 * s, 8 * s); ctx.stroke(); ctx.setLineDash([]);
        } else { rr(ctx, cx, cy, cw, ch, o.shape === 'square' ? 0 : 30 * s); ctx.fill(); }
        shadowOff(ctx);
        if (o.border) { ctx.lineWidth = 5 * s; ctx.strokeStyle = p.primary; ctx.strokeRect(cx + 16 * s, cy + 16 * s, cw - 32 * s, ch - 32 * s); }
        const boxY = o.shape === 'arch' ? cy - cw * 0.08 : cy;
        const boxH = o.shape === 'arch' ? ch + cw * 0.08 : ch;
        textBlock(ctx, d, { x: cx + 20 * s, y: boxY, w: cw - 40 * s, h: boxH }, {
            color: txt, kickerColor: kc, upper: o.upper, max: 100, web: rgba(txt, 0.7), cta: o.cta ? { bg: p.primary, fg: on(p.primary, p.dark) } : null,
        });
    };

    /** Solid (optionally patterned) background, photo in a shaped frame, text above or below. */
    FAM.frame = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const bg = p[o.bg || 'bg'];
        const txt = on(bg, p.dark);
        const kc = pick(bg, [p.primary, p.accent, p.secondary], p.dark);
        ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
        pattern(ctx, d, o.pattern, rgba(txt, 0.07), 0, 0, W, H);
        const tH = H * (o.textFrac || 0.34);
        const top = o.textPos === 'top';
        const m = W * 0.07;
        const area = { x: m, y: top ? tH : m, w: W - m * 2, h: H - tH - m };
        if (o.photo === 'circle') {
            const r = Math.min(area.w, area.h) / 2, cx = W / 2, cy = area.y + area.h / 2;
            ctx.beginPath(); ctx.arc(cx, cy, r + 12 * s, 0, 7); ctx.fillStyle = p.primary; ctx.fill();
            clipPhotos(ctx, d, () => { ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); }, cx - r, cy - r, r * 2, r * 2);
        } else if (o.photo === 'arch') {
            shadowOn(ctx, s, 0.18); archPath(ctx, area.x, area.y, area.w, area.h); ctx.fillStyle = '#fff'; ctx.fill(); shadowOff(ctx);
            clipPhotos(ctx, d, () => archPath(ctx, area.x, area.y, area.w, area.h), area.x, area.y, area.w, area.h);
        } else if (o.photo === 'polaroid') {
            ctx.save();
            ctx.translate(W / 2, area.y + area.h / 2); ctx.rotate(-0.045); ctx.translate(-W / 2, -(area.y + area.h / 2));
            const pw = area.w * 0.92, ph = area.h * 0.96, px = (W - pw) / 2, py = area.y + (area.h - ph) / 2;
            shadowOn(ctx, s, 0.3); ctx.fillStyle = '#ffffff'; ctx.fillRect(px, py, pw, ph); shadowOff(ctx);
            photos(ctx, d, px + 22 * s, py + 22 * s, pw - 44 * s, ph - 110 * s);
            if (d.website) website(ctx, d, W / 2, py + ph - 46 * s, '#333333', { size: 24, maxW: pw * 0.8 });
            ctx.restore();
            // tape
            ctx.save(); ctx.translate(W / 2, area.y + 6 * s); ctx.rotate(0.05);
            ctx.fillStyle = rgba(p.secondary, 0.85); ctx.fillRect(-70 * s, -20 * s, 140 * s, 40 * s); ctx.restore();
        } else {
            const rad = o.photo === 'rounded' ? 36 * s : 0;
            shadowOn(ctx, s, 0.2); rr(ctx, area.x, area.y, area.w, area.h, rad); ctx.fillStyle = '#fff'; ctx.fill(); shadowOff(ctx);
            clipPhotos(ctx, d, () => rr(ctx, area.x, area.y, area.w, area.h, rad), area.x, area.y, area.w, area.h);
            if (!rad) { ctx.lineWidth = 3 * s; ctx.strokeStyle = rgba(txt, 0.5); ctx.strokeRect(area.x - 14 * s, area.y - 14 * s, area.w + 28 * s, area.h + 28 * s); }
        }
        const tb = top ? { x: W * 0.05, y: 0, w: W * 0.9, h: tH } : { x: W * 0.05, y: H - tH, w: W * 0.9, h: tH };
        textBlock(ctx, d, tb, { color: txt, kickerColor: kc, upper: o.upper, max: 108, web: o.photo === 'polaroid' ? null : rgba(txt, 0.7) });
    };

    /** Magazine-style layouts: masthead, big list number, header story, side strip. */
    FAM.editorial = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const bg = p[o.bg || 'bg'];
        const txt = on(bg, p.dark);
        const kc = pick(bg, [p.primary, p.accent, p.secondary], p.dark);
        ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
        const numMatch = String(d.headline).match(/^\s*(\d{1,3})\s+(.+)$/);

        if (o.variant === 'number' || o.variant === 'numberCircle') {
            const ph = H * 0.56;
            photos(ctx, d, 0, 0, W, ph);
            const num = numMatch ? numMatch[1] : '';
            const rest = numMatch ? numMatch[2] : d.headline;
            if (o.variant === 'numberCircle' && num) {
                const r = 110 * s;
                ctx.beginPath(); ctx.arc(W / 2, ph, r, 0, 7); ctx.fillStyle = p.primary; ctx.fill();
                ctx.lineWidth = 8 * s; ctx.strokeStyle = bg; ctx.stroke();
                line(ctx, num, W / 2, ph + 6 * s, { size: 120 * s, weight: 900, fam: d.fonts.main, color: on(p.primary, p.dark), maxW: r * 1.6 });
                textBlock(ctx, { ...d, headline: rest }, { x: W * 0.05, y: ph + r, w: W * 0.9, h: H - ph - r - 20 * s }, { color: txt, kickerColor: kc, upper: true, max: 96, web: rgba(txt, 0.7) });
            } else if (num) {
                const nW = W * 0.34;
                line(ctx, num, W * 0.05 + nW / 2, ph + (H - ph) / 2, { size: 300 * s, weight: 900, fam: d.fonts.main, color: p.primary, maxW: nW });
                textBlock(ctx, { ...d, headline: rest }, { x: W * 0.05 + nW, y: ph, w: W * 0.9 - nW, h: H - ph }, { color: txt, kickerColor: kc, upper: true, align: 'left', pad: 20, max: 90, web: rgba(txt, 0.7) });
            } else {
                ctx.fillStyle = p.primary; ctx.fillRect(0, ph, W, 14 * s);
                textBlock(ctx, d, { x: W * 0.05, y: ph + 14 * s, w: W * 0.9, h: H - ph - 14 * s }, { color: txt, kickerColor: kc, upper: true, max: 100, web: rgba(txt, 0.7) });
            }
            return;
        }
        if (o.variant === 'header') {
            const tH = H * 0.38;
            ctx.fillStyle = p.primary; ctx.fillRect(W * 0.08, tH - 8 * s, W * 0.84, 5 * s);
            textBlock(ctx, d, { x: W * 0.04, y: 0, w: W * 0.92, h: tH - 14 * s }, { color: txt, kickerColor: kc, max: 104, upper: o.upper });
            const m = W * 0.06;
            clipPhotos(ctx, d, () => rr(ctx, m, tH + 20 * s, W - m * 2, H - tH - 20 * s - m - 40 * s, 30 * s), m, tH + 20 * s, W - m * 2, H - tH - 20 * s - m - 40 * s);
            website(ctx, d, W / 2, H - m / 2 - 22 * s, rgba(txt, 0.7), { size: 22 });
            return;
        }
        if (o.variant === 'sidebar') {
            const sw = W * 0.13;
            ctx.fillStyle = p.primary; ctx.fillRect(0, 0, sw, H);
            if (d.website) {
                ctx.save(); ctx.translate(sw / 2, H / 2); ctx.rotate(-Math.PI / 2);
                line(ctx, d.website.toUpperCase(), 0, 0, { size: 34 * s, weight: 700, fam: d.fonts.secondary, color: on(p.primary, p.dark), maxW: H * 0.8, ls: 0.3 });
                ctx.restore();
            }
            const ph = H * 0.58;
            photos(ctx, d, sw, 0, W - sw, ph);
            textBlock(ctx, d, { x: sw, y: ph, w: W - sw, h: H - ph }, { color: txt, kickerColor: kc, align: 'left', pad: 44, max: 104, upper: o.upper });
            return;
        }
        // magazine
        const mh = 110 * s;
        ctx.fillStyle = txt; ctx.fillRect(W * 0.06, mh - 10 * s, W * 0.88, 3 * s);
        line(ctx, (d.website || 'Editor’s pick').toUpperCase(), W / 2, mh / 2, { size: 30 * s, weight: 700, fam: d.fonts.secondary, color: txt, maxW: W * 0.8, ls: 0.35 });
        const ph = H * 0.5;
        photos(ctx, d, W * 0.06, mh + 14 * s, W * 0.88, ph);
        textBlock(ctx, d, { x: W * 0.04, y: mh + ph + 20 * s, w: W * 0.92, h: H - mh - ph - 40 * s }, { color: txt, kickerColor: kc, max: 100, weight: 700, lh: 1.1 });
    };

    /** Collage-first grid with a centre element. */
    FAM.grid = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const gapC = p[o.gap || 'bg'];
        if (d.mode === 'collage' && d.imgs.length >= 2) {
            if (o.center === 'split') {
                photos(ctx, d, 0, 0, W, H * 0.62, { gapColor: gapC, gap: 12 * s });
            } else photos(ctx, d, 0, 0, W, H, { gapColor: gapC, gap: 12 * s });
        } else photos(ctx, d, 0, 0, W, o.center === 'split' ? H * 0.62 : H);
        const fill = p[o.fill || 'bg'];
        const txt = on(fill, p.dark);
        const kc = pick(fill, [p.primary, p.accent, p.secondary], p.dark);
        if (o.center === 'circle') {
            const r = W * 0.3;
            ctx.beginPath(); ctx.arc(W / 2, H / 2, r + 14 * s, 0, 7); ctx.fillStyle = gapC; ctx.fill();
            ctx.beginPath(); ctx.arc(W / 2, H / 2, r, 0, 7); ctx.fillStyle = fill; ctx.fill();
            textBlock(ctx, d, { x: W / 2 - r * 0.74, y: H / 2 - r * 0.72, w: r * 1.48, h: r * 1.44 }, { color: txt, kickerColor: kc, pad: 4, max: 80, upper: true, maxLines: 4 });
        } else if (o.center === 'diamond') {
            const r = W * 0.36;
            ctx.save(); ctx.translate(W / 2, H / 2); ctx.rotate(Math.PI / 4);
            ctx.fillStyle = gapC; ctx.fillRect(-r / 1.41 - 12 * s, -r / 1.41 - 12 * s, r * 1.41 + 24 * s, r * 1.41 + 24 * s);
            ctx.fillStyle = fill; ctx.fillRect(-r / 1.41, -r / 1.41, r * 1.41, r * 1.41);
            ctx.restore();
            textBlock(ctx, d, { x: W / 2 - r * 0.6, y: H / 2 - r * 0.55, w: r * 1.2, h: r * 1.1 }, { color: txt, kickerColor: kc, pad: 2, max: 70, upper: true, maxLines: 4 });
        } else if (o.center === 'band') {
            const bh = H * 0.24;
            ctx.fillStyle = fill; ctx.fillRect(0, (H - bh) / 2, W, bh);
            ctx.fillStyle = kc; ctx.fillRect(0, (H - bh) / 2, W, 8 * s); ctx.fillRect(0, (H + bh) / 2 - 8 * s, W, 8 * s);
            textBlock(ctx, d, { x: W * 0.05, y: (H - bh) / 2, w: W * 0.9, h: bh }, { color: txt, kicker: false, max: 100, upper: true, maxLines: 3 });
        } else if (o.center === 'split') {
            ctx.fillStyle = fill; ctx.fillRect(0, H * 0.62, W, H * 0.38);
            textBlock(ctx, d, { x: W * 0.05, y: H * 0.62, w: W * 0.9, h: H * 0.38 }, { color: txt, kickerColor: kc, max: 104, upper: o.upper, web: rgba(txt, 0.7) });
        } else {
            const bw = W * 0.72, bh = H * 0.3;
            ctx.fillStyle = gapC; ctx.fillRect((W - bw) / 2 - 12 * s, (H - bh) / 2 - 12 * s, bw + 24 * s, bh + 24 * s);
            ctx.fillStyle = fill; ctx.fillRect((W - bw) / 2, (H - bh) / 2, bw, bh);
            textBlock(ctx, d, { x: (W - bw) / 2, y: (H - bh) / 2, w: bw, h: bh }, { color: txt, kickerColor: kc, max: 90, upper: true, web: rgba(txt, 0.7) });
        }
    };

    /** Lots of whitespace, quiet typography. */
    FAM.minimal = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const bg = p[o.bg || 'bg'];
        const txt = on(bg, p.dark);
        const kc = pick(bg, [p.primary, p.accent, p.secondary], p.dark);
        ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
        const ph = H * 0.6;
        const align = o.align || 'center';
        if (o.photoPos === 'bottom') {
            photos(ctx, d, 0, H - ph, W, ph);
            textBlock(ctx, d, { x: W * 0.05, y: 0, w: W * 0.9, h: H - ph }, { color: txt, kickerColor: kc, align, weight: 700, max: 100, web: rgba(txt, 0.6) });
        } else if (o.photoPos === 'middle') {
            const m = W * 0.1, tH = H * 0.2;
            textBlock(ctx, d, { x: W * 0.05, y: 0, w: W * 0.9, h: tH }, { color: txt, kickerColor: kc, kicker: true, weight: 700, max: 70, maxLines: 3 });
            photos(ctx, d, m, tH, W - m * 2, H - tH * 2);
            if (o.lines) { ctx.lineWidth = 2 * s; ctx.strokeStyle = rgba(txt, 0.4); ctx.strokeRect(m - 20 * s, tH - 20 * s, W - m * 2 + 40 * s, H - tH * 2 + 40 * s); }
            website(ctx, d, W / 2, H - tH / 2, rgba(txt, 0.7), { size: 24 });
            if (d.cta) line(ctx, d.cta, W / 2, H - tH / 2 - 44 * s, { size: 40 * s, fam: d.fonts.accent, fb: 'cursive', color: kc, maxW: W * 0.8 });
        } else if (o.photoPos === 'inset') {
            const m = W * 0.09;
            clipPhotos(ctx, d, () => rr(ctx, m, m, W - m * 2, ph - m, 18 * s), m, m, W - m * 2, ph - m);
            textBlock(ctx, d, { x: m - 20 * s, y: ph, w: W - (m - 20 * s) * 2, h: H - ph }, { color: txt, kickerColor: kc, align, weight: 700, max: 96, web: rgba(txt, 0.6) });
        } else {
            photos(ctx, d, 0, 0, W, ph);
            ctx.fillStyle = kc; ctx.fillRect(align === 'left' ? W * 0.08 : W / 2 - 40 * s, ph + 44 * s, 80 * s, 6 * s);
            textBlock(ctx, d, { x: W * 0.03, y: ph + 50 * s, w: W * 0.94, h: H - ph - 50 * s }, { color: txt, kickerColor: kc, align, weight: 700, max: 100, pad: 50, web: rgba(txt, 0.6) });
        }
    };

    /** Photo with sticker, ribbon, tag, stamp or label, headline on a bottom plate. */
    FAM.sticker = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const plateH = H * 0.3;
        const plate = p[o.plate || 'bg'];
        const txt = on(plate, p.dark);
        const topPlate = o.style === 'bannerTop';
        photos(ctx, d, 0, topPlate ? plateH : 0, W, H - plateH);
        ctx.fillStyle = plate; ctx.fillRect(0, topPlate ? 0 : H - plateH, W, plateH);
        const kick = d.kicker || d.cta || '';
        const badgeRoom = topPlate && kick ? 96 * s : 0;
        textBlock(ctx, d, { x: W * 0.04, y: topPlate ? 0 : H - plateH, w: W * 0.92, h: plateH - badgeRoom }, { color: txt, kicker: false, upper: o.upper, max: 100, web: badgeRoom ? null : rgba(txt, 0.65) });
        if (!kick) return;
        const sc = p[o.stickerRole || 'primary'];
        const st = on(sc, p.dark);
        const photoTop = topPlate ? plateH : 0;
        ctx.save();
        if (o.style === 'ribbon') {
            ctx.translate(W - 150 * s, photoTop + 150 * s); ctx.rotate(Math.PI / 4);
            ctx.fillStyle = sc; ctx.fillRect(-300 * s, -44 * s, 600 * s, 88 * s);
            line(ctx, kick.toUpperCase(), 0, 2 * s, { size: 32 * s, weight: 800, fam: d.fonts.secondary, color: st, maxW: 300 * s, ls: 0.08 });
        } else if (o.style === 'tag') {
            ctx.translate(W * 0.08, photoTop + H * 0.06); ctx.rotate(-0.08);
            ctx.font = F(800, 38 * s, d.fonts.secondary);
            const tw = Math.min(W * 0.6, ctx.measureText(kick.toUpperCase()).width + 110 * s), th = 90 * s;
            ctx.beginPath(); ctx.moveTo(0, th / 2); ctx.lineTo(th / 2, 0); ctx.lineTo(tw, 0); ctx.lineTo(tw, th); ctx.lineTo(th / 2, th); ctx.closePath();
            shadowOn(ctx, s, 0.3); ctx.fillStyle = sc; ctx.fill(); shadowOff(ctx);
            ctx.beginPath(); ctx.arc(th / 2 + 4 * s, th / 2, 9 * s, 0, 7); ctx.fillStyle = plate; ctx.fill();
            line(ctx, kick.toUpperCase(), th / 2 + (tw - th / 2) / 2 + 10 * s, th / 2 + 2 * s, { size: 36 * s, weight: 800, fam: d.fonts.secondary, color: st, maxW: tw - th, ls: 0.06 });
        } else if (o.style === 'stamp') {
            const r = 120 * s;
            ctx.translate(W - r - 50 * s, (topPlate ? plateH : H - plateH) - (topPlate ? -r - 40 * s : r * 0.6)); ctx.rotate(0.18);
            shadowOn(ctx, s, 0.3); ctx.beginPath();
            for (let i = 0; i < 48; i++) { const a = (i / 48) * Math.PI * 2, rr2 = i % 2 ? r : r * 0.9; ctx.lineTo(Math.cos(a) * rr2, Math.sin(a) * rr2); }
            ctx.closePath(); ctx.fillStyle = sc; ctx.fill(); shadowOff(ctx);
            const words = kick.split(/\s+/);
            const lines = words.length > 1 ? [words.slice(0, Math.ceil(words.length / 2)).join(' '), words.slice(Math.ceil(words.length / 2)).join(' ')] : [kick];
            lines.forEach((ln, i) => line(ctx, SCRIPTS.has(d.fonts.accent) ? ln : ln.toUpperCase(), 0, (i - (lines.length - 1) / 2) * 50 * s, { size: 48 * s, fam: d.fonts.accent, fb: 'cursive', weight: SCRIPTS.has(d.fonts.accent) ? 400 : 700, color: st, maxW: r * 1.5 }));
        } else if (o.style === 'label') {
            const lw = W * 0.7, lh = 80 * s;
            ctx.translate(W / 2, (topPlate ? plateH : H - plateH));
            ctx.fillStyle = sc; ctx.fillRect(-lw / 2, -lh / 2, lw, lh);
            ctx.beginPath(); ctx.moveTo(-lw / 2, -lh / 2); ctx.lineTo(-lw / 2 - 30 * s, 0); ctx.lineTo(-lw / 2, lh / 2); ctx.fill();
            ctx.beginPath(); ctx.moveTo(lw / 2, -lh / 2); ctx.lineTo(lw / 2 + 30 * s, 0); ctx.lineTo(lw / 2, lh / 2); ctx.fill();
            line(ctx, kick.toUpperCase(), 0, 2 * s, { size: 36 * s, weight: 800, fam: d.fonts.secondary, color: st, maxW: lw - 40 * s, ls: 0.12 });
        } else {
            // bannerTop: round badge overlapping the plate edge
            const r = 90 * s;
            ctx.translate(W / 2, plateH);
            ctx.beginPath(); ctx.arc(0, 0, r, 0, 7); ctx.fillStyle = sc; ctx.fill();
            ctx.lineWidth = 6 * s; ctx.strokeStyle = plate; ctx.stroke();
            const words = kick.split(/\s+/).slice(0, 3);
            words.forEach((w, i) => line(ctx, w.toUpperCase(), 0, (i - (words.length - 1) / 2) * 34 * s, { size: 30 * s, weight: 800, fam: d.fonts.secondary, color: st, maxW: r * 1.6 }));
        }
        ctx.restore();
    };

    /** Loud typography on a darkened photo. */
    FAM.bold = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        photos(ctx, d, 0, 0, W, H);
        const dim = o.style === 'stacked' ? 0.15 : 0.45;
        ctx.fillStyle = `rgba(0,0,0,${dim})`; ctx.fillRect(0, 0, W, H);
        const acc = pick('#111111', [p.accent, p.primary, p.secondary], p.dark, 3.5);
        const acc2 = pick('#111111', [p.secondary, p.accent, '#ffe066'], p.dark, 3.5);
        if (o.style === 'outline') {
            textBlock(ctx, d, { x: W * 0.04, y: H * 0.15, w: W * 0.92, h: H * 0.7 }, {
                color: '#ffffff', colors: ['#ffffff', acc], kickerColor: acc2, upper: true, max: 150, lh: 1.02,
                stroke: acc, strokeW: 4 * s, strokeOnly: (i) => i % 2 === 1, web: 'rgba(255,255,255,0.85)',
            });
        } else if (o.style === 'stacked') {
            const boxes = [p.primary, p.dark, p.accent].map((c) => c);
            const cols = boxes.map((b) => on(b, p.dark));
            textBlock(ctx, d, { x: W * 0.05, y: H * 0.35, w: W * 0.9, h: H * 0.6 }, {
                color: cols[0], colors: cols, boxes, kickerColor: '#ffffff', upper: true, max: 110, lh: 1.22, valign: 'bottom', web: '#ffffff', shadow: null,
            });
        } else if (o.style === 'huge') {
            textBlock(ctx, d, { x: 0, y: H * 0.3, w: W, h: H * 0.66 }, {
                color: '#ffffff', kickerColor: acc, upper: true, align: 'left', pad: 56, max: 190, lh: 0.98, valign: 'bottom', weight: 900, web: acc,
            });
            ctx.fillStyle = acc; ctx.fillRect(56 * s, H * 0.08, 110 * s, 12 * s);
        } else if (o.style === 'splitColor') {
            const words = String(d.headline).split(/\s+/);
            const half = Math.ceil(words.length / 2);
            textBlock(ctx, { ...d, headline: words.slice(0, half).join(' '), kicker: d.kicker }, { x: W * 0.05, y: H * 0.14, w: W * 0.9, h: H * 0.36 }, { color: '#ffffff', kickerColor: acc2, upper: true, max: 140, valign: 'bottom', shadow: 'rgba(0,0,0,0.5)' });
            textBlock(ctx, { ...d, headline: words.slice(half).join(' ') || ' ', kicker: '' }, { x: W * 0.05, y: H * 0.5, w: W * 0.9, h: H * 0.34 }, { color: acc, upper: true, max: 140, valign: 'top', shadow: 'rgba(0,0,0,0.5)' });
            website(ctx, d, W / 2, H - 60 * s, '#ffffff', { size: 26 });
        } else {
            // boxLines: headline inside a thick outlined box
            const bx = W * 0.1, by = H * 0.25, bw = W * 0.8, bh = H * 0.5;
            ctx.lineWidth = 12 * s; ctx.strokeStyle = acc; ctx.strokeRect(bx, by, bw, bh);
            textBlock(ctx, d, { x: bx + 10 * s, y: by, w: bw - 20 * s, h: bh }, { color: '#ffffff', kickerColor: acc2, upper: true, max: 120, shadow: 'rgba(0,0,0,0.4)' });
            website(ctx, d, W / 2, by + bh + 60 * s, '#ffffff', { size: 26 });
            if (d.cta) pill(ctx, d, W / 2, by - 60 * s, { bg: acc, fg: on(acc, p.dark) });
        }
    };

    /* ===================== Reference pin styles (t51–t70) ===================== */
    /** "15 Bob Hairstyles…" → {num: '15', rest: 'Bob Hairstyles…'}; num is '' when there is no leading number. */
    function splitNum(t) {
        const m = String(t).match(/^\s*(\d{1,3}\+?)\s+(.+)$/);
        return m ? { num: m[1], rest: m[2] } : { num: '', rest: String(t) };
    }
    /** Solid strip at the bottom with the website — the most common Pinterest footer. Returns its height. */
    function webBar(ctx, d, color) {
        if (!d.website) return 0;
        const s = d.s, h = 58 * s;
        ctx.fillStyle = color;
        ctx.fillRect(0, d.H - h, d.W, h);
        website(ctx, d, d.W / 2, d.H - h / 2 + 1 * s, on(color, d.pal.dark), { size: 26 });
        return h;
    }
    function bigNum(ctx, d, num, x, y, o) {
        let size = o.size;
        ctx.font = F(900, size, o.fam || d.fonts.main);
        while (ctx.measureText(num).width > o.maxW && size > 20) { size *= 0.92; ctx.font = F(900, size, o.fam || d.fonts.main); }
        ctx.textAlign = o.align || 'center'; ctx.textBaseline = 'middle';
        if (o.stroke) { ctx.lineJoin = 'round'; ctx.lineWidth = o.strokeW || size * 0.1; ctx.strokeStyle = o.stroke; ctx.strokeText(num, x, y); }
        ctx.fillStyle = o.fill; ctx.fillText(num, x, y);
        return size;
    }
    function sparkle(ctx, x, y, r, color) {
        ctx.beginPath();
        for (let i = 0; i < 8; i++) { const a = i * Math.PI / 4, rr2 = i % 2 ? r * 0.28 : r; ctx.lineTo(x + Math.cos(a) * rr2, y + Math.sin(a) * rr2); }
        ctx.closePath(); ctx.fillStyle = color; ctx.fill();
    }
    /** Two photos (collage) or one photo split across the top and bottom of the pin. */
    function topBottomPhotos(ctx, d, splitY) {
        if (d.mode === 'collage' && d.imgs.length >= 2) {
            photos(ctx, d, 0, 0, d.W, splitY, { single: true, index: 0 });
            photos(ctx, d, 0, splitY, d.W, d.H - splitY, { single: true, index: 1 });
        } else photos(ctx, d, 0, 0, d.W, d.H);
    }

    FAM.ref = function (ctx, d, o) {
        const { W, H, s } = d, p = d.pal;
        const pri = p.primary, priOn = on(pri, p.dark);
        const tint = mix(pri, '#ffffff', 0.72);           // light pink-style text
        const ink = contrast(p.dark, '#ffffff') > 4 ? p.dark : '#111111';
        const serif = CW_SERIF(d);
        const { num, rest } = splitNum(d.headline);

        switch (o.v) {
        case 'pinkOutline': { // big light headline, black outline, number circle, vertical site name
            photos(ctx, d, 0, 0, W, H);
            if (num) {
                const r = 92 * s, cx = 40 * s + r, cy = H * 0.19;
                ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); ctx.fillStyle = '#ffffff'; ctx.fill();
                ctx.beginPath(); ctx.arc(cx, cy, r * 0.8, 0, 7); ctx.fillStyle = tint; ctx.fill();
                bigNum(ctx, d, num, cx, cy + 4 * s, { size: 96 * s, fill: '#ffffff', maxW: r * 1.4 });
            }
            if (d.website) {
                ctx.save(); ctx.translate(W - 34 * s, H * 0.14); ctx.rotate(Math.PI / 2);
                line(ctx, d.website, 0, 0, { size: 30 * s, weight: 700, fam: d.fonts.secondary, color: '#ffffff', maxW: H * 0.4, ls: 0.1, align: 'left' });
                ctx.restore();
            }
            textBlock(ctx, { ...d, headline: rest, kicker: '' }, { x: 0, y: H * 0.28, w: W, h: H * 0.7 }, {
                color: tint, stroke: '#111111', strokeW: 12 * s, upper: true, max: 150, lh: 1.02, pad: 24,
            });
            break;
        }
        case 'serifBoxes':
        case 'softSerifBoxes': { // serif lines on white (or alternating white/blush) highlight boxes
            photos(ctx, d, 0, 0, W, H);
            const bar = webBar(ctx, d, pri);
            const soft = o.v === 'softSerifBoxes';
            const blush = mix(pri, '#ffffff', 0.9);
            textBlock(ctx, { ...d, kicker: '' }, { x: W * 0.08, y: soft ? H * 0.34 : H * 0.24, w: W * 0.84, h: (soft ? H * 0.62 : H * 0.6) - bar }, {
                fam: serif, weight: 700, upper: true, color: '#111111', max: 120, lh: 1.26, boxPad: 0.18,
                lineStyle: (i) => ({ box: soft && i % 2 ? blush : '#ffffff' }),
            });
            break;
        }
        case 'mixedStack': { // left-aligned: coloured outlined lines, dark outlined lines, then boxed lines
            photos(ctx, d, 0, 0, W, H);
            const bar = webBar(ctx, d, pri);
            textBlock(ctx, { ...d, kicker: '' }, { x: W * 0.02, y: H * 0.02, w: W * 0.8, h: H * 0.62 - bar }, {
                align: 'left', upper: true, max: 120, lh: 1.14, valign: 'top', strokeW: 10 * s,
                lineStyle: (i, n) => {
                    const g = n < 3 ? i : Math.floor(i / Math.ceil(n / 3));
                    if (g === 0) return { color: pri, stroke: '#ffffff' };
                    if (g === 1) return { color: '#111111', stroke: '#ffffff' };
                    return { color: priOn, stroke: null, box: pri };
                },
            });
            break;
        }
        case 'headerBands': { // coloured band / white band / coloured strip, photo below
            const words = String(d.headline).split(/\s+/);
            const a = words.slice(0, Math.min(2, words.length)).join(' ');
            const restW = words.slice(a.split(' ').length);
            const half = Math.ceil(restW.length / 2);
            const b = restW.length > 2 ? restW.slice(0, half).join(' ') : restW.join(' ');
            const c = restW.length > 2 ? restW.slice(half).join(' ') : (d.kicker || '');
            const h1 = H * 0.16, h2 = b ? H * 0.1 : 0, h3 = c ? H * 0.055 : 0;
            photos(ctx, d, 0, h1 + h2 + h3, W, H - h1 - h2 - h3);
            webBar(ctx, d, pri);
            ctx.fillStyle = pri; ctx.fillRect(0, 0, W, h1);
            textBlock(ctx, { ...d, headline: a, kicker: '' }, { x: 0, y: 0, w: W, h: h1 }, { color: '#ffffff', stroke: '#111111', strokeW: 8 * s, upper: true, max: 200, maxLines: 1, pad: 18 });
            if (b) {
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0, h1, W, h2);
                textBlock(ctx, { ...d, headline: b, kicker: '' }, { x: 0, y: h1, w: W, h: h2 }, { fam: serif, weight: 400, color: '#111111', upper: true, max: 110, maxLines: 1, pad: 18 });
            }
            if (c) {
                ctx.fillStyle = pri; ctx.fillRect(0, h1 + h2, W, h3);
                textBlock(ctx, { ...d, headline: c, kicker: '' }, { x: 0, y: h1 + h2, w: W, h: h3 }, { fam: d.fonts.secondary, weight: 400, color: priOn, upper: true, max: 60, maxLines: 1, pad: 14 });
            }
            break;
        }
        case 'collageSerif': { // photo grid, serif lines on white boxes, last lines in brand colour
            photos(ctx, d, 0, 0, W, H, { gapColor: '#ffffff', gap: 10 * s });
            webBar(ctx, d, pri);
            textBlock(ctx, { ...d, kicker: '' }, { x: W * 0.08, y: H * 0.01, w: W * 0.84, h: H * 0.54 }, {
                fam: serif, weight: 800, upper: true, max: 130, lh: 1.24, boxPad: 0.2, valign: 'top',
                lineStyle: (i, n) => ({ box: '#ffffff', color: n > 2 && i >= n - Math.floor(n / 2) ? pri : '#111111' }),
            });
            break;
        }
        case 'dashedCard': { // white card with dashed border, two-colour headline, italic sub line
            photos(ctx, d, 0, 0, W, H);
            const bar = webBar(ctx, d, pri);
            const cw = W * 0.8, chh = Math.min(H * 0.4, W * 0.6), cx = (W - cw) / 2, cy = (H - bar - chh) / 2 + H * 0.02;
            ctx.fillStyle = '#ffffff'; ctx.fillRect(cx, cy, cw, chh);
            ctx.setLineDash([12 * s, 8 * s]); ctx.lineWidth = 3 * s; ctx.strokeStyle = '#111111';
            ctx.strokeRect(cx + 4 * s, cy + 4 * s, cw - 8 * s, chh - 8 * s); ctx.setLineDash([]);
            const sub = d.kicker ? 70 * s : 0;
            textBlock(ctx, { ...d, kicker: '' }, { x: cx, y: cy + 10 * s, w: cw, h: chh - sub - 10 * s }, {
                upper: true, max: 120, lh: 1.04, weight: 900,
                lineStyle: (i, n) => ({ color: n > 1 && i >= Math.ceil(n / 2) ? pri : '#111111' }),
            });
            if (d.kicker) {
                ctx.save(); ctx.font = `italic 400 ${Math.round(40 * s)}px "${d.fonts.secondary}", Arial, sans-serif`;
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#111111';
                ctx.fillText(d.kicker, W / 2, cy + chh - sub / 2 - 10 * s, cw - 60 * s); ctx.restore();
            }
            break;
        }
        case 'rightStack': { // right column: dark then brand lines with white outline, script accent at the end
            photos(ctx, d, 0, 0, W, H);
            const bar = webBar(ctx, d, pri);
            const kh = d.kicker ? 120 * s : 0;
            const r = textBlock(ctx, { ...d, kicker: '' }, { x: W * 0.44, y: H * 0.24, w: W * 0.54, h: H * 0.62 - bar - kh }, {
                align: 'right', upper: true, max: 120, lh: 1.06, strokeW: 10 * s, pad: 10,
                lineStyle: (i, n) => ({ color: i < Math.max(1, Math.round(n / 3)) ? '#111111' : pri, stroke: '#ffffff' }),
            });
            if (d.kicker) {
                ctx.save(); ctx.lineJoin = 'round'; ctx.lineWidth = 10 * s; ctx.strokeStyle = '#ffffff';
                let ks = 100 * s; ctx.font = F(400, ks, d.fonts.accent, 'cursive');
                while (ctx.measureText(d.kicker).width > W * 0.5 && ks > 20) { ks *= 0.92; ctx.font = F(400, ks, d.fonts.accent, 'cursive'); }
                ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
                ctx.strokeText(d.kicker, W * 0.96, r.bottom + kh / 2); ctx.fillStyle = pri; ctx.fillText(d.kicker, W * 0.96, r.bottom + kh / 2);
                ctx.restore();
            }
            break;
        }
        case 'twoToneBox': { // white box, first & last line brand colour, site text on the photo
            photos(ctx, d, 0, 0, W, H);
            const bw = W * 0.74, bh = Math.min(H * 0.34, W * 0.55), bx = (W - bw) / 2, by = H * 0.4;
            ctx.fillStyle = '#ffffff'; ctx.fillRect(bx, by, bw, bh);
            textBlock(ctx, { ...d, kicker: '' }, { x: bx, y: by, w: bw, h: bh }, {
                max: 120, lh: 1.02, weight: 900, pad: 22,
                lineStyle: (i, n) => ({ color: i === 0 || (n > 2 && i === n - 1) ? pri : '#111111' }),
            });
            if (d.website) {
                ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 10 * s;
                line(ctx, d.website, W / 2, H - 70 * s, { size: 44 * s, weight: 700, fam: d.fonts.secondary, color: '#ffffff', maxW: W * 0.85 });
                ctx.restore();
            }
            break;
        }
        case 'sparklePanel': { // photo top, bordered white panel, big number with sparkles
            const py = H * 0.6;
            photos(ctx, d, 0, 0, W, py + 10 * s);
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, py, W, H - py);
            ctx.lineWidth = 10 * s; ctx.strokeStyle = pri; ctx.strokeRect(5 * s, py, W - 10 * s, H - py - 5 * s);
            let top = py + 30 * s;
            if (num) {
                bigNum(ctx, d, num, W / 2, py, { size: 190 * s, fill: pri, stroke: '#ffffff', strokeW: 22 * s, maxW: W * 0.6 });
                [[W * 0.25, py - 60 * s, 26], [W * 0.73, py - 40 * s, 30], [W * 0.21, py + 60 * s, 18], [W * 0.78, py + 60 * s, 16]].forEach(([x, y, r]) => sparkle(ctx, x, y, r * s, pri));
                top = py + 100 * s;
            }
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: W * 0.04, y: top, w: W * 0.92, h: H - top - 60 * s }, {
                upper: true, max: 110, lh: 1.04, weight: 900, pad: 10,
                lineStyle: (i, n) => ({ color: i % 2 ? pri : '#111111' }),
            });
            website(ctx, d, W / 2, H - 38 * s, '#111111', { size: 28 });
            break;
        }
        case 'collageBand': { // photo grid, white band with serif title, round number badge
            photos(ctx, d, 0, 0, W, H, { gapColor: '#ffffff', gap: 10 * s });
            const bh = H * 0.19, by = H * 0.35;
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, by, W, bh);
            const badge = num || d.kicker;
            const off = badge ? 46 * s : 0;
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: 0, y: by + off, w: W, h: bh - off }, { fam: serif, weight: 800, color: '#111111', upper: true, max: 90, lh: 1.08, pad: 20 });
            if (badge) {
                const r = 80 * s;
                ctx.beginPath(); ctx.arc(W / 2, by, r, 0, 7); ctx.fillStyle = pri; ctx.fill();
                bigNum(ctx, d, badge, W / 2, by + 3 * s, { size: 64 * s, fill: priOn, maxW: r * 1.6, fam: d.fonts.secondary });
            }
            break;
        }
        case 'darkTab': { // photo top, near-black panel with a number tab, light brand-coloured title
            const py = H * 0.58, dark = '#0e0e0e';
            photos(ctx, d, 0, 0, W, py);
            ctx.fillStyle = dark; ctx.fillRect(0, py, W, H - py);
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, py - 6 * s, W, 6 * s);
            let top = py + 30 * s;
            if (num) {
                const tw = 250 * s, th = 120 * s;
                rr(ctx, W / 2 - tw / 2, py - th * 0.72, tw, th, 26 * s); ctx.fillStyle = dark; ctx.fill();
                bigNum(ctx, d, num, W / 2, py - th * 0.72 + th / 2 + 4 * s, { size: 110 * s, fill: tint, maxW: tw - 30 * s });
                top = py + 60 * s;
            }
            const kh = d.kicker ? H * 0.12 : 0;
            const r = textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: W * 0.04, y: top, w: W * 0.92, h: H - top - kh - 30 * s }, { color: tint, upper: true, max: 100, lh: 1.05, pad: 10 });
            if (d.kicker) textBlock(ctx, { ...d, headline: d.kicker, kicker: '' }, { x: W * 0.04, y: r.bottom, w: W * 0.92, h: kh }, { color: '#ffffff', upper: true, max: 64, pad: 6 });
            break;
        }
        case 'softOutline': { // number left, title-case white lines with thin grey outline
            photos(ctx, d, 0, 0, W, H);
            let top = H * 0.52;
            if (num) { bigNum(ctx, d, num, W * 0.04, top, { size: 170 * s, fill: '#ffffff', stroke: '#6b6b6b', strokeW: 10 * s, maxW: W * 0.5, align: 'left' }); top += 90 * s; }
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: W * 0.03, y: top, w: W * 0.94, h: H * 0.93 - top }, { color: '#ffffff', stroke: '#6b6b6b', strokeW: 8 * s, max: 100, lh: 1.12, pad: 16, valign: 'top' });
            website(ctx, d, W / 2, H - 42 * s, '#ffffff', { size: 26 });
            break;
        }
        case 'heavyOutline': { // bottom-heavy huge white text, thick black outline
            photos(ctx, d, 0, 0, W, H);
            const g = ctx.createLinearGradient(0, H * 0.45, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,0.35)');
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
            let top = H * 0.5;
            if (num) { bigNum(ctx, d, num, W / 2, top + 70 * s, { size: 170 * s, fill: '#ffffff', stroke: '#000000', strokeW: 16 * s, maxW: W * 0.5 }); top += 150 * s; }
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: 0, y: top, w: W, h: H - top - 20 * s }, { color: '#ffffff', stroke: '#000000', strokeW: 13 * s, upper: true, max: 150, lh: 1.02, weight: 900, pad: 22 });
            break;
        }
        case 'keywordLines': { // centred: number, then white / brand-colour lines, black outline
            photos(ctx, d, 0, 0, W, H);
            let top = H * 0.05;
            if (num) { bigNum(ctx, d, num, W / 2, top + 100 * s, { size: 180 * s, fill: '#ffffff', stroke: '#111111', strokeW: 14 * s, maxW: W * 0.6 }); top += 200 * s; }
            const pinkish = contrast(tint, '#111111') > 3 ? mix(pri, '#ffffff', 0.35) : tint;
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: W * 0.04, y: top, w: W * 0.92, h: H * 0.72 - top }, {
                color: '#ffffff', stroke: '#111111', strokeW: 11 * s, upper: true, max: 120, lh: 1.08, valign: 'top',
                lineStyle: (i) => ({ color: i % 2 === 0 ? pinkish : '#ffffff' }),
            });
            website(ctx, d, W / 2, H - 44 * s, '#ffffff', { size: 26 });
            break;
        }
        case 'outlineNumber': { // number + title in white with brand-colour outline, bottom bar
            photos(ctx, d, 0, 0, W, H);
            const bar = webBar(ctx, d, pri);
            let top = H * 0.53;
            if (num) { bigNum(ctx, d, num, W / 2, top + 60 * s, { size: 190 * s, fill: '#ffffff', stroke: pri, strokeW: 14 * s, maxW: W * 0.5 }); top += 140 * s; }
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: W * 0.04, y: top, w: W * 0.92, h: H - bar - top - 10 * s }, { color: '#ffffff', stroke: pri, strokeW: 9 * s, max: 100, lh: 1.08, pad: 10 });
            break;
        }
        case 'brandTag': { // photo(s), bordered white title box, tan tag with brand name under it
            topBottomPhotos(ctx, d, H * 0.5);
            const bw = W * 0.9, bh = H * 0.2, bx = (W - bw) / 2, by = H * 0.39;
            ctx.fillStyle = '#ffffff'; ctx.fillRect(bx, by, bw, bh);
            ctx.lineWidth = 4 * s; ctx.strokeStyle = '#111111'; ctx.strokeRect(bx, by, bw, bh);
            textBlock(ctx, { ...d, kicker: '' }, { x: bx, y: by, w: bw, h: bh }, { fam: d.fonts.secondary, weight: 800, color: '#111111', upper: true, max: 90, lh: 1.12, pad: 26, ls: 0.03 });
            const tag = d.website || d.kicker;
            if (tag) {
                const tw = bw * 0.84, th = 64 * s, tx = (W - tw) / 2, ty = by + bh - 4 * s;
                const tan = p.secondary && contrast(p.secondary, '#ffffff') > 1.6 ? p.secondary : '#c9a46b';
                ctx.fillStyle = tan; ctx.fillRect(tx, ty, tw, th);
                ctx.save(); ctx.font = `italic 700 ${Math.round(28 * s)}px "${d.fonts.secondary}", Arial, sans-serif`;
                if ('letterSpacing' in ctx) ctx.letterSpacing = (4 * s) + 'px';
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = on(tan, '#111111');
                ctx.fillText(tag.toUpperCase(), W / 2, ty + th / 2 + 2 * s, tw - 30 * s); ctx.restore();
            }
            break;
        }
        case 'markerBand':
        case 'markerBandSoft': { // photos above & below, colour band with inner white frame, white title
            topBottomPhotos(ctx, d, H * 0.5);
            const bh = H * 0.24, by = (H - bh) / 2;
            const band = o.v === 'markerBand' ? pri : mix(pri, p.dark, 0.35);
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, by - 14 * s, W, bh + 28 * s);
            ctx.fillStyle = band; ctx.fillRect(14 * s, by, W - 28 * s, bh);
            if (o.v === 'markerBand') { ctx.lineWidth = 4 * s; ctx.strokeStyle = 'rgba(255,255,255,.85)'; ctx.strokeRect(34 * s, by + 20 * s, W - 68 * s, bh - 40 * s); }
            textBlock(ctx, { ...d, kicker: '' }, { x: W * 0.06, y: by, w: W * 0.88, h: bh }, { fam: d.fonts.secondary, weight: 800, color: on(band, p.dark), upper: true, max: 92, lh: 1.12, pad: 26, ls: 0.02 });
            break;
        }
        case 'numberBlob': { // photo grid, rounded blob behind big number and title, pill sub line
            photos(ctx, d, 0, 0, W, H, { gapColor: '#ffffff', gap: 10 * s });
            const blob = mix(pri, '#0b2a1a', 0.55);
            const bx = W * 0.06, by = H * 0.03, bw = W * 0.88, bh = H * 0.36;
            rr(ctx, bx, by, bw, bh, 50 * s); ctx.fillStyle = blob; ctx.fill();
            let top = by + 20 * s;
            if (num) { bigNum(ctx, d, num, W / 2, top + 90 * s, { size: 180 * s, fill: '#ffffff', maxW: W * 0.5 }); top += 180 * s; }
            textBlock(ctx, { ...d, headline: num ? rest : d.headline, kicker: '' }, { x: bx, y: top, w: bw, h: by + bh - top - 10 * s }, { color: '#ffffff', upper: true, max: 110, lh: 1.02, pad: 26 });
            if (d.kicker) {
                ctx.font = F(700, 34 * s, d.fonts.secondary);
                const pw = Math.min(W * 0.7, ctx.measureText(d.kicker).width + 60 * s), ph = 64 * s;
                rr(ctx, W / 2 - pw / 2, by + bh - 20 * s, pw, ph, 16 * s); ctx.fillStyle = blob; ctx.fill();
                line(ctx, d.kicker, W / 2, by + bh - 20 * s + ph / 2 + 2 * s, { size: 34 * s, weight: 700, fam: d.fonts.secondary, color: '#ffffff', maxW: pw - 30 * s });
            }
            break;
        }
        default:
            photos(ctx, d, 0, 0, W, H);
        }
    };
    /** A serif for the "magazine" styles: the main font if it is a serif, otherwise Playfair Display. */
    function CW_SERIF(d) {
        const serifs = /playfair|abril|bodoni|cinzel|prata|dm serif|garamond|lora|merriweather|baskerville|caslon|crimson|yeseva|rozha|gloock|fraunces|spectral|marcellus|italiana|serif|old standard/i;
        return serifs.test(d.fonts.main) ? d.fonts.main : 'Playfair Display';
    }

    /* ===================== The templates ===================== */
    const T = (id, name, tags, fam, opts) => ({ id, name, tags, fam, opts });
    const TEMPLATES = [
        T('t01', 'Center band', ['general', 'food', 'diy', 'home'], 'bandCenter', { band: 'primary', upper: true }),
        T('t02', 'Soft center card', ['home', 'wedding', 'beauty', 'general'], 'bandCenter', { band: 'bg', shape: 'inset' }),
        T('t03', 'Torn paper', ['food', 'diy', 'parenting'], 'bandCenter', { band: 'bg', shape: 'torn', upper: true }),
        T('t04', 'Double rule band', ['fashion', 'travel', 'finance'], 'bandCenter', { band: 'dark', shape: 'lines', upper: true }),
        T('t05', 'Tilted band', ['holiday', 'pets', 'parenting', 'food'], 'bandCenter', { band: 'accent', shape: 'tilt', upper: true }),
        T('t06', 'Top headline', ['general', 'finance', 'tech'], 'edgeBand', { pos: 'top', band: 'primary', edge: 'straight', upper: true }),
        T('t07', 'Wave footer', ['travel', 'health', 'home'], 'edgeBand', { pos: 'bottom', band: 'bg', edge: 'wave' }),
        T('t08', 'Arc header', ['beauty', 'wedding', 'fashion'], 'edgeBand', { pos: 'top', band: 'secondary', edge: 'arc' }),
        T('t09', 'Slant footer', ['fashion', 'tech', 'general'], 'edgeBand', { pos: 'bottom', band: 'dark', edge: 'slant', upper: true }),
        T('t10', 'Notch header', ['food', 'diy', 'pets'], 'edgeBand', { pos: 'top', band: 'accent', edge: 'notch', upper: true }),
        T('t11', 'Night fade', ['travel', 'fashion', 'general'], 'overlay', { scrim: 'bottom', pos: 'bottom' }),
        T('t12', 'Outlined colour mix', ['general', 'diy', 'food'], 'overlay', { scrim: 'full', pos: 'center', stroke: true, multi: true, upper: true }),
        T('t13', 'Colour wash', ['beauty', 'health', 'wedding'], 'overlay', { scrim: 'tint', pos: 'center', upper: true }),
        T('t14', 'Top fade', ['travel', 'home'], 'overlay', { scrim: 'top', pos: 'top' }),
        T('t15', 'Button overlay', ['finance', 'tech', 'health'], 'overlay', { scrim: 'bottom', pos: 'bottom', cta: true, upper: true }),
        T('t16', 'Floating card', ['home', 'general', 'wedding'], 'card', { shape: 'rounded', pos: 'center' }),
        T('t17', 'Circle badge', ['food', 'pets', 'holiday'], 'card', { shape: 'circle', role: 'primary', upper: true }),
        T('t18', 'Arch window', ['beauty', 'wedding', 'home'], 'card', { shape: 'arch', pos: 'bottom' }),
        T('t19', 'Ticket', ['travel', 'holiday', 'diy'], 'card', { shape: 'ticket', pos: 'bottom', role: 'secondary', upper: true }),
        T('t20', 'Bordered box', ['fashion', 'finance', 'general'], 'card', { shape: 'square', pos: 'top', border: true, upper: true, cta: true }),
        T('t21', 'Framed photo', ['general', 'home', 'diy'], 'frame', { photo: 'rect', bg: 'bg', textPos: 'bottom' }),
        T('t22', 'Arch frame', ['beauty', 'home', 'wedding'], 'frame', { photo: 'arch', bg: 'secondary', textPos: 'bottom' }),
        T('t23', 'Circle frame', ['pets', 'parenting', 'food'], 'frame', { photo: 'circle', bg: 'bg', textPos: 'top', pattern: 'dots', upper: true }),
        T('t24', 'Polaroid', ['travel', 'diy', 'parenting'], 'frame', { photo: 'polaroid', bg: 'accent', textPos: 'bottom', pattern: 'stripes', upper: true }),
        T('t25', 'Rounded photo', ['tech', 'finance', 'fashion'], 'frame', { photo: 'rounded', bg: 'dark', textPos: 'top', pattern: 'grid', upper: true }),
        T('t26', 'Magazine', ['fashion', 'beauty', 'travel'], 'editorial', { variant: 'magazine', bg: 'bg' }),
        T('t27', 'Big number', ['general', 'finance', 'health'], 'editorial', { variant: 'number', bg: 'bg' }),
        T('t28', 'Header story', ['food', 'home', 'general'], 'editorial', { variant: 'header', bg: 'bg' }),
        T('t29', 'Side strip', ['fashion', 'tech'], 'editorial', { variant: 'sidebar', bg: 'bg', upper: true }),
        T('t30', 'Number circle', ['diy', 'food', 'parenting'], 'editorial', { variant: 'numberCircle', bg: 'secondary' }),
        T('t31', 'Grid circle', ['food', 'fashion', 'home'], 'grid', { center: 'circle', fill: 'bg', gap: 'bg' }),
        T('t32', 'Grid box', ['diy', 'home', 'general'], 'grid', { center: 'box', fill: 'primary', gap: 'bg' }),
        T('t33', 'Grid band', ['fashion', 'travel'], 'grid', { center: 'band', fill: 'dark', gap: 'dark' }),
        T('t34', 'Grid diamond', ['holiday', 'wedding'], 'grid', { center: 'diamond', fill: 'accent', gap: 'bg' }),
        T('t35', 'Grid with footer', ['food', 'general'], 'grid', { center: 'split', fill: 'primary', gap: 'bg', upper: true }),
        T('t36', 'Clean top photo', ['general', 'finance', 'tech'], 'minimal', { photoPos: 'top', align: 'left' }),
        T('t37', 'Clean bottom photo', ['home', 'health'], 'minimal', { photoPos: 'bottom' }),
        T('t38', 'Gallery white', ['wedding', 'beauty'], 'minimal', { photoPos: 'middle', lines: true }),
        T('t39', 'Soft inset', ['beauty', 'fashion'], 'minimal', { photoPos: 'inset', bg: 'secondary' }),
        T('t40', 'Note card', ['tech', 'finance'], 'minimal', { photoPos: 'top', bg: 'dark' }),
        T('t41', 'Corner ribbon', ['food', 'holiday', 'diy'], 'sticker', { style: 'ribbon', upper: true }),
        T('t42', 'Price tag', ['fashion', 'finance', 'holiday'], 'sticker', { style: 'tag', stickerRole: 'accent' }),
        T('t43', 'Round stamp', ['food', 'pets', 'parenting'], 'sticker', { style: 'stamp', plate: 'secondary', upper: true }),
        T('t44', 'Label strip', ['diy', 'home', 'general'], 'sticker', { style: 'label', plate: 'bg', upper: true }),
        T('t45', 'Badge header', ['travel', 'general'], 'sticker', { style: 'bannerTop', plate: 'primary', stickerRole: 'accent', upper: true }),
        T('t46', 'Outline stack', ['general', 'fashion', 'tech'], 'bold', { style: 'outline' }),
        T('t47', 'Highlight lines', ['general', 'food', 'diy', 'finance'], 'bold', { style: 'stacked' }),
        T('t48', 'Huge left', ['fashion', 'travel', 'tech'], 'bold', { style: 'huge' }),
        T('t49', 'Two-tone words', ['holiday', 'pets', 'parenting'], 'bold', { style: 'splitColor' }),
        T('t50', 'Boxed title', ['finance', 'health', 'general'], 'bold', { style: 'boxLines' }),
        // Modelled on popular Pinterest pins (fashion, hair and recipe niches)
        T('t51', 'Outlined headline + number', ['fashion', 'beauty', 'general'], 'ref', { v: 'pinkOutline' }),
        T('t52', 'Serif highlight boxes', ['fashion', 'beauty', 'wedding'], 'ref', { v: 'serifBoxes' }),
        T('t53', 'Mixed highlight stack', ['fashion', 'travel', 'general'], 'ref', { v: 'mixedStack' }),
        T('t54', 'Stacked header bands', ['fashion', 'beauty', 'general'], 'ref', { v: 'headerBands' }),
        T('t55', 'Collage serif boxes', ['fashion', 'travel', 'home'], 'ref', { v: 'collageSerif' }),
        T('t56', 'Dashed card', ['fashion', 'health', 'general'], 'ref', { v: 'dashedCard' }),
        T('t57', 'Right-side stack', ['fashion', 'beauty'], 'ref', { v: 'rightStack' }),
        T('t58', 'Two-tone box', ['fashion', 'finance', 'general'], 'ref', { v: 'twoToneBox' }),
        T('t59', 'Soft serif boxes', ['fashion', 'beauty', 'wedding'], 'ref', { v: 'softSerifBoxes' }),
        T('t60', 'Sparkle number panel', ['beauty', 'fashion', 'general'], 'ref', { v: 'sparklePanel' }),
        T('t61', 'Collage band + badge', ['beauty', 'home', 'general'], 'ref', { v: 'collageBand' }),
        T('t62', 'Dark panel number tab', ['beauty', 'fashion', 'tech'], 'ref', { v: 'darkTab' }),
        T('t63', 'Soft outline number', ['beauty', 'health', 'general'], 'ref', { v: 'softOutline' }),
        T('t64', 'Heavy outline bottom', ['beauty', 'general', 'diy'], 'ref', { v: 'heavyOutline' }),
        T('t65', 'Keyword colour lines', ['beauty', 'fashion', 'general'], 'ref', { v: 'keywordLines' }),
        T('t66', 'Outlined number', ['beauty', 'fashion'], 'ref', { v: 'outlineNumber' }),
        T('t67', 'Title box + brand tag', ['food', 'home', 'general'], 'ref', { v: 'brandTag' }),
        T('t68', 'Framed recipe band', ['food', 'diy', 'general'], 'ref', { v: 'markerBand' }),
        T('t69', 'Deep recipe band', ['food', 'holiday'], 'ref', { v: 'markerBandSoft' }),
        T('t70', 'Number blob', ['food', 'home', 'general'], 'ref', { v: 'numberBlob' }),
    ];
    const TEMPLATE_MAP = Object.fromEntries(TEMPLATES.map((t) => [t.id, t]));
    const CATEGORIES = ['food', 'fashion', 'beauty', 'home', 'travel', 'diy', 'health', 'finance', 'parenting', 'pets', 'wedding', 'holiday', 'tech', 'general'];

    /* ===================== Canva / SVG templates ===================== */
    const esc = (t) => String(t || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    async function renderSvgTemplate(ctx, d, tpl) {
        const { W, H } = d;
        const doc = new DOMParser().parseFromString(tpl.svg, 'image/svg+xml');
        const svg = doc.documentElement;
        if (!svg || svg.nodeName.toLowerCase() !== 'svg') { photos(ctx, d, 0, 0, W, H); return; }
        let vb = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
        if (vb.length !== 4 || vb.some((n) => isNaN(n)) || !vb[2] || !vb[3]) {
            vb = [0, 0, parseFloat(svg.getAttribute('width')) || W, parseFloat(svg.getAttribute('height')) || H];
            svg.setAttribute('viewBox', vb.join(' '));
        }
        const sx = W / vb[2], sy = H / vb[3];
        const ph = svg.querySelector('[id="photo" i], [id^="photo" i], [data-name="photo" i]');
        let drawn = false;
        if (ph) {
            const n = (a) => parseFloat(ph.getAttribute(a));
            const x = n('x') || 0, y = n('y') || 0, w = n('width'), h = n('height');
            if (w && h) {
                photos(ctx, d, (x - vb[0]) * sx, (y - vb[1]) * sy, w * sx, h * sy);
                drawn = true;
            }
            ph.remove();
        }
        if (!drawn) photos(ctx, d, 0, 0, W, H);
        svg.setAttribute('width', W);
        svg.setAttribute('height', H);
        svg.setAttribute('preserveAspectRatio', 'none');
        let str = new XMLSerializer().serializeToString(svg);
        const hadTitle = /\{\{\s*(title|headline)\s*\}\}/i.test(str);
        str = str.replace(/\{\{\s*(title|headline)\s*\}\}/gi, esc(d.headline))
            .replace(/\{\{\s*kicker\s*\}\}/gi, esc(d.kicker))
            .replace(/\{\{\s*cta\s*\}\}/gi, esc(d.cta))
            .replace(/\{\{\s*(website|url)\s*\}\}/gi, esc(d.website));
        const url = URL.createObjectURL(new Blob([str], { type: 'image/svg+xml' }));
        const img = await new Promise((res) => { const i = new Image(); i.onload = () => res(i); i.onerror = () => res(null); i.src = url; });
        if (img) ctx.drawImage(img, 0, 0, W, H);
        URL.revokeObjectURL(url);
        if (!hadTitle && tpl.text_position && tpl.text_position !== 'none') {
            const p = d.pal, bh = H * 0.28;
            const by = tpl.text_position === 'top' ? H * 0.05 : tpl.text_position === 'bottom' ? H - bh - H * 0.05 : (H - bh) / 2;
            ctx.fillStyle = rgba(p.bg, 0.93); rr(ctx, W * 0.06, by, W * 0.88, bh, 24 * d.s); ctx.fill();
            textBlock(ctx, d, { x: W * 0.06, y: by, w: W * 0.88, h: bh }, { color: on(p.bg, p.dark), kickerColor: pick(p.bg, [p.primary, p.accent], p.dark), max: 100 });
        }
    }

    /* ===================== Bulk Pin "Pin Templates & Styles" ===================== */
    // The same designs as the Bulk Pin generator. Thumbnails use their cached preview image; real pins are
    // drawn on the server (proRenderer, set by the wizard) with the page's own images and text.
    const PRO_TAG = 'styles';
    const PRO_CAT = [
        [/food|recipe|baking|dessert|meal|drink/i, 'food'], [/fashion|tattoo|outfit/i, 'fashion'],
        [/beauty|hair|makeup|skincare|nail/i, 'beauty'], [/wedding|bridal/i, 'wedding'],
        [/holiday|seasonal|party|gift|christmas/i, 'holiday'], [/pet|animal/i, 'pets'],
        [/parent|kid|education|baby/i, 'parenting'], [/travel/i, 'travel'],
        [/health|fitness|workout|wellness/i, 'health'], [/finance|money|business|entrepreneur|marketing|blog/i, 'finance'],
        [/tech|gadget|gaming|car|photo|movie|music|book/i, 'tech'], [/diy|craft|art|drawing|design/i, 'diy'],
        [/home|interior|decor|organi|architecture|plant|garden/i, 'home'],
    ];
    let proRenderer = null;
    function setProRenderer(fn) { proRenderer = typeof fn === 'function' ? fn : null; }
    function setProTemplates(list) {
        (Array.isArray(list) ? list : []).forEach((t) => {
            const id = 'pt:' + t.key;
            if (TEMPLATE_MAP[id]) return;
            const hit = PRO_CAT.find(([re]) => re.test(t.category || ''));
            const tpl = { id, name: t.name, tags: [hit ? hit[1] : 'general', PRO_TAG], pt: t.key, photos: Math.max(1, +t.photos || 1), layout: t.layout, preview: t.preview, category: t.category, numbered: !!t.numbered };
            TEMPLATES.push(tpl);
            TEMPLATE_MAP[id] = tpl;
        });
    }
    function drawCover(ctx, img, W, H) {
        const r = Math.max(W / img.width, H / img.height), w = img.width * r, h = img.height * r;
        ctx.drawImage(img, (W - w) / 2, (H - h) / 2, w, h);
    }
    async function renderProTemplate(ctx, d, tpl, spec) {
        const { W, H } = d;
        let img = null;
        if ((spec.scale || 1) < 0.3 || !proRenderer) {
            img = tpl.preview ? await loadImage(tpl.preview) : null;   // small thumbnails: the ready-made preview
        } else {
            let urls = (spec.images || []).slice();
            const pool = (spec.page_images || []).map((i) => (typeof i === 'string' ? i : i.url));
            if (tpl.photos > urls.length && pool.length > urls.length) {
                const rest = pool.filter((u) => !urls.some((x) => x.endsWith(u)));
                urls = urls.concat(rest).slice(0, tpl.photos);
            }
            const url = await proRenderer({ key: tpl.pt, size: spec.size, headline: d.headline, website: d.website, cta: d.cta || 'Read More', images: urls, final: (spec.scale || 1) >= 1 });
            img = url ? await loadImage(url) : null;
        }
        if (img) drawCover(ctx, img, W, H);
        else FAM.bandCenter(ctx, d, { band: 'primary', upper: true });
    }

    /* ===================== Public API ===================== */
    let customTemplates = [];
    function setCustomTemplates(list) { customTemplates = Array.isArray(list) ? list : []; }
    function getTemplate(id) {
        if (TEMPLATE_MAP[id]) return TEMPLATE_MAP[id];
        return customTemplates.find((t) => t.id === id) || null;
    }
    function templateName(id) { const t = getTemplate(id); return t ? t.name : 'Template'; }

    async function render(canvas, spec) {
        const size = SIZES[spec.size] || SIZES['2:3'];
        const scale = spec.scale || 1;
        const W = Math.round(size.w * scale), H = Math.round(size.h * scale);
        canvas.width = W; canvas.height = H;
        const ctx = canvas.getContext('2d');
        const fonts = spec.fonts || FONT_COMBOS[0];
        const [imgs] = await Promise.all([
            Promise.all((spec.images || []).slice(0, 4).map(loadImage)),
            loadFonts(fonts),
            loadFont('Playfair Display', [700]), // serif fallback used by the magazine-style templates
        ]);
        const d = {
            W, H, s: W / 1000,
            imgs: imgs.filter(Boolean),
            mode: spec.mode === 'collage' ? 'collage' : 'single',
            headline: (spec.headline || '').trim() || 'Your pin title',
            kicker: (spec.kicker || '').trim(),
            cta: (spec.cta || '').trim(),
            website: (spec.website || '').replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, ''),
            pal: spec.palette || PALETTES[0],
            fonts,
        };
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = d.pal.bg || '#ffffff';
        ctx.fillRect(0, 0, W, H);
        const tpl = getTemplate(spec.template) || TEMPLATES[0];
        try {
            if (tpl.pt) await renderProTemplate(ctx, d, tpl, spec);
            else if (tpl.svg) await renderSvgTemplate(ctx, d, tpl);
            else FAM[tpl.fam](ctx, d, tpl.opts || {});
        } catch (e) {
            console.error('Pin render failed', tpl.id, e);
            photos(ctx, d, 0, 0, W, H);
        }
        return canvas;
    }

    function toBlob(canvas, quality) {
        return new Promise((res) => canvas.toBlob((b) => res(b), 'image/jpeg', quality || 0.9));
    }

    /**
     * Template for one pin. aiOn: prefer templates tagged with the page's category (from AI).
     * Different pins of the same page always get different templates when the pool allows.
     */
    function pickTemplate(pool, category, aiOn, pageIndex, pinIndex, pinsPerPage, headline) {
        let all = pool && pool.length ? pool : TEMPLATES.map((t) => t.id);
        // Numbered templates print the title's number — never use them for a title without one.
        if (headline !== undefined && !/\d/.test(String(headline))) {
            const ok = all.filter((id) => { const t = TEMPLATE_MAP[id]; return !(t && t.numbered); });
            if (ok.length) all = ok;
        }
        let list = all;
        if (aiOn) {
            const tagged = all.filter((id) => { const t = TEMPLATE_MAP[id]; return t && t.tags.includes(category); });
            const customs = all.filter((id) => !TEMPLATE_MAP[id]);
            list = tagged.length ? tagged.concat(customs) : all;
        }
        const i = aiOn ? (pageIndex * 7 + pinIndex) : (pageIndex * Math.max(1, pinsPerPage) + pinIndex);
        return list[i % list.length];
    }

    /** single / collage for the n-th pin of the run, given the chosen image layout. */
    function pickMode(layout, singlePct, n) {
        if (layout === 'single') return 'single';
        if (layout === 'collage') return 'collage';
        const pct = layout === 'mix' ? 50 : Math.max(0, Math.min(100, singlePct));
        return Math.floor((n + 1) * pct / 100) > Math.floor(n * pct / 100) ? 'single' : 'collage';
    }

    /** Which page images a pin uses (rotates so pins of one page don't repeat the same photo first). */
    function pickImages(images, mode, pinIndex) {
        const urls = images.map((i) => (typeof i === 'string' ? i : i.url));
        if (!urls.length) return [];
        const start = pinIndex % urls.length;
        const rotated = urls.slice(start).concat(urls.slice(0, start));
        return mode === 'collage' ? rotated.slice(0, Math.min(4, rotated.length)) : [rotated[0]];
    }

    global.CWEngine = {
        SIZES, PALETTES, FONT_COMBOS, FONTS, TEMPLATES, CATEGORIES,
        loadFont, loadFonts, ensureFontSheet, loadImage, render, toBlob,
        setCustomTemplates, getTemplate, templateName, pickTemplate, pickMode, pickImages,
        setProTemplates, setProRenderer, PRO_TAG,
        isScript: (f) => SCRIPTS.has(f),
    };
})(window);
