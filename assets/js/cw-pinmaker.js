/*
 * Public Pinterest Pin Maker — same design tools as the Classic Wizard (pages, size, layout,
 * 70 templates + AI pick, Canva SVG, palettes, fonts, live preview), rendered in the browser.
 * One click on "Generate pins" = one free generation (limit set by the admin, default 3).
 */
(function () {
    'use strict';
    const E = window.CWEngine, B = window.PM_BOOT || {}, BASE = '../../';
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const hostOf = (u) => { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return ''; } };
    const pathOf = (u) => { try { const x = new URL(u); return x.pathname + x.search; } catch (e) { return u; } };
    const slug = (t) => String(t || 'pin').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'pin';
    const SAMPLE = { headline: '15 Easy Dinner Ideas for Busy Weeknights', kicker: 'Save for later', cta: 'Read more' };

    const S = {
        remaining: B.remaining, site: { url: '', host: '' },
        pages: [], selected: [], search: '',
        size: '2:3', layout: 'single', singlePct: 70,
        aiTemplates: true, selectedTpls: [], tplTab: 'all', tplFilter: 'all', customTpls: [],
        paletteId: 'p01', customPalette: { bg: '#ffffff', primary: '#e60023', secondary: '#fde8ec', accent: '#ffb000', dark: '#111111' },
        comboIdx: 0, customFonts: false, fonts: Object.assign({}, E.FONT_COMBOS[0]),
        sample: null, preview: { url: null, title: '', images: [] },
        gen: { pages: [], running: false, counter: 0, token: null },
    };
    let pinSeq = 1;

    /* ---------- helpers ---------- */
    async function api(action, data) {
        const fd = new FormData();
        fd.append('action', action);
        Object.entries(data || {}).forEach(([k, v]) => { if (v != null) fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v); });
        try {
            const res = await fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' });
            const txt = await res.text();
            try { return JSON.parse(txt); } catch (e) { return { ok: false, error: 'The server sent an unexpected reply (HTTP ' + res.status + ').' }; }
        } catch (e) { return { ok: false, error: 'Network error — check your connection and try again.' }; }
    }
    function notify(msg, type) {
        const box = $('#cwAlert');
        if (!msg) { box.innerHTML = ''; return; }
        box.innerHTML = `<div class="alert alert-${type || 'info'}">${msg}<button type="button" class="cw-x" aria-label="Dismiss">&times;</button></div>`;
        $('.cw-x', box).onclick = () => { box.innerHTML = ''; };
        if (type === 'error') box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function showAttempts() {
        const el = $('#pmAttempts');
        if (S.remaining > 0) {
            el.className = 'pm-attempts';
            el.innerHTML = `<b>${S.remaining}</b> of ${B.max} free generation${B.max === 1 ? '' : 's'} left · each makes up to ${B.maxPins} pins for up to ${B.maxPages} pages`;
        } else {
            el.className = 'pm-attempts is-out';
            el.innerHTML = B.loggedIn
                ? 'You’ve used your free generations here. <a href="../../user/classic-wizard">Open the Classic Wizard</a> to keep creating and scheduling pins.'
                : 'You’ve used your free generations. <a href="../../auth/register?from=freetool">Create a free account</a> to keep making pins and schedule them automatically.';
        }
        $('#pmGenerate').disabled = S.remaining <= 0;
    }
    const palette = () => S.paletteId === 'custom' ? Object.assign({ id: 'custom' }, S.customPalette) : (E.PALETTES.find((p) => p.id === S.paletteId) || E.PALETTES[0]);
    const fonts = () => S.customFonts ? Object.assign({}, S.fonts) : Object.assign({}, E.FONT_COMBOS[S.comboIdx]);
    const tplPool = () => S.selectedTpls.length ? S.selectedTpls.slice() : E.TEMPLATES.map((t) => t.id);
    const abs = (u) => /^(https?:|blob:|data:|\.\.\/)/.test(u) ? u : BASE + u;
    const specFrom = (d, scale) => Object.assign({}, d, { images: (d.images || []).map(abs), scale });
    let chain = Promise.resolve();
    function queueRender(canvas, spec) { const j = chain.then(() => E.render(canvas, spec)).catch(() => {}); chain = j; return j; }

    function goto(step) {
        if (step === 2 && !S.gen.pages.length) { generate(); return; }
        $$('.cw-step-panel').forEach((p) => { p.hidden = +p.dataset.panel !== step; });
        $$('.pm-stepper li').forEach((li) => { li.classList.toggle('is-active', +li.dataset.step === step); li.classList.toggle('is-done', +li.dataset.step < step); });
        if (step === 1) refreshThumbs();
        window.scrollTo({ top: $('.pm-stepper').offsetTop - 80, behavior: 'smooth' });
    }

    /* ---------- pages ---------- */
    async function scan() {
        let url = $('#cwSiteUrl').value.trim();
        if (url && !/^https?:\/\//i.test(url)) { url = 'https://' + url; $('#cwSiteUrl').value = url; }
        let u;
        try { u = new URL(url); } catch (e) { notify('Paste a website or page link first.', 'error'); return; }
        notify('');
        S.site = { url, host: hostOf(url) };
        const isPage = u.pathname.replace(/\/+$/, '') !== '';
        const btn = $('#cwScanBtn');
        btn.disabled = true; btn.textContent = 'Scanning…';
        $('#cwScanStatus').textContent = 'Reading the sitemap…';
        const r = await api('scan', { url });
        btn.disabled = false; btn.textContent = 'Scan';
        S.pages = (r.ok ? r.pages : []);
        if (isPage && !S.pages.some((p) => p.url.replace(/\/+$/, '') === url.replace(/\/+$/, ''))) S.pages.unshift({ id: null, url, title: '' });
        S.selected = isPage ? [S.pages.find((p) => p.url.replace(/\/+$/, '') === url.replace(/\/+$/, '')).url] : [];
        $('#cwScanStatus').textContent = !S.pages.length ? 'No pages found. Paste a link to a single post instead.'
            : isPage ? `Your page is selected. ${S.pages.length > 1 ? `Add up to ${B.maxPages - 1} more from ${S.pages.length - 1} other page(s) on this site.` : ''}`
            : `${S.pages.length} page(s) found — select up to ${B.maxPages}.`;
        $('#cwPagesBox').hidden = !S.pages.length;
        $('#cwShuffle').disabled = !S.pages.length;
        renderPages();
        loadPreviewPage();
        refreshThumbs();
    }
    function filtered() { const q = S.search.toLowerCase(); return q ? S.pages.filter((p) => p.url.toLowerCase().includes(q)) : S.pages; }
    function renderPages() {
        const list = filtered(), shown = list.slice(0, 400), full = S.selected.length >= B.maxPages;
        $('#cwPagesList').innerHTML = shown.map((p) => {
            const on = S.selected.includes(p.url);
            return `<label class="cw-page ${!on && full ? 'is-disabled' : ''}" role="listitem"><input type="checkbox" value="${esc(p.url)}" ${on ? 'checked' : ''} ${!on && full ? 'disabled' : ''}>
                <span><b>${esc(p.title || pathOf(p.url))}</b><small>${esc(p.url)}</small></span></label>`;
        }).join('') + (list.length > shown.length ? `<p class="cw-help cw-more">Showing 400 of ${list.length} — search to narrow down.</p>` : '') + (!list.length ? '<p class="cw-help cw-more">No pages match that search.</p>' : '');
        $('#cwSelCount').textContent = S.selected.length;
        $('#cwPageTotal').textContent = S.pages.length;
        $('#cwStep1Summary').textContent = S.selected.length ? `${S.selected.length} page(s) × ${$('#pmPinsPerPage').value} pins` : '';
    }

    /* ---------- sizes / templates / palettes / fonts (same as the wizard) ---------- */
    function renderSizes() {
        $('#cwSizes').innerHTML = Object.entries(E.SIZES).map(([k, v]) => `
            <label class="cw-size ${k === S.size ? 'is-on' : ''}"><input type="radio" name="cwSize" value="${k}" ${k === S.size ? 'checked' : ''}>
            <span class="cw-size-box"><i style="aspect-ratio:${v.w}/${v.h}"></i></span><b>${esc(v.label)}</b><small>${esc(v.sub)}</small></label>`).join('');
    }
    const thumbIO = new IntersectionObserver((ents) => ents.forEach((en) => { if (en.isIntersecting) drawThumb(en.target); }), { rootMargin: '200px' });
    let thumbVer = 0;
    function drawThumb(tile) {
        if (+tile.dataset.ver === thumbVer) return;
        tile.dataset.ver = thumbVer;
        const collage = S.layout === 'collage', img = S.sample ? [S.sample] : [];
        queueRender($('canvas', tile), specFrom({
            template: tile.dataset.id, size: S.size, mode: collage ? 'collage' : 'single', images: collage && img.length ? [img[0], img[0], img[0], img[0]] : img,
            headline: SAMPLE.headline, kicker: SAMPLE.kicker, cta: SAMPLE.cta, website: S.site.host || 'yourwebsite.com', palette: palette(), fonts: fonts(),
        }, 0.26));
    }
    function tileHtml(t, removable) {
        const on = S.selectedTpls.includes(t.id);
        return `<div class="cw-tpl ${on ? 'is-on' : ''}" data-id="${esc(t.id)}"><button type="button" class="cw-tpl-pick" aria-pressed="${on}"><canvas></canvas><span class="cw-tick" aria-hidden="true">✓</span></button>
            <span class="cw-tpl-name">${esc(t.name)}</span>${removable ? `<button type="button" class="cw-tpl-del" data-del="${esc(t.id)}">Remove</button>` : ''}</div>`;
    }
    function renderTemplates() {
        const canva = S.tplTab === 'canva';
        $('#cwCanvaBox').hidden = !canva; $('#cwTplGrid').hidden = canva; $('#cwTplFilters').hidden = S.tplTab !== 'all';
        $('#cwTplSelCount').textContent = S.selectedTpls.length;
        $('#cwTplAllCount').textContent = E.TEMPLATES.length + S.customTpls.length;
        const list = S.tplTab === 'selected' ? S.selectedTpls.map((id) => E.getTemplate(id)).filter(Boolean)
            : E.TEMPLATES.filter((t) => S.tplFilter === 'all' || t.tags.includes(S.tplFilter)).concat(S.tplFilter === 'all' ? S.customTpls : []);
        $('#cwTplGrid').innerHTML = list.length ? list.map((t) => tileHtml(t)).join('') : `<p class="cw-help cw-empty">${S.tplTab === 'selected' ? 'No templates selected yet. Open “All templates” and click the designs you like.' : 'No templates in this category.'}</p>`;
        $('#cwCanvaGrid').innerHTML = S.customTpls.length ? S.customTpls.map((t) => tileHtml(t, true)).join('') : '<p class="cw-help cw-empty">No designs added yet.</p>';
        $$('.cw-tpl').forEach((t) => { t.dataset.ver = -1; thumbIO.observe(t); });
    }
    function renderFilters() {
        const cats = ['all'].concat(E.CATEGORIES.filter((c) => c !== 'general'));
        $('#cwTplFilters').innerHTML = cats.map((c) => `<button type="button" class="${c === S.tplFilter ? 'is-on' : ''}" data-cat="${c}">${c === 'all' ? 'All' : c === 'diy' ? 'DIY' : c[0].toUpperCase() + c.slice(1)}</button>`).join('');
    }
    function toggleTemplate(id) {
        const i = S.selectedTpls.indexOf(id);
        if (i >= 0) S.selectedTpls.splice(i, 1); else S.selectedTpls.push(id);
        $$(`.cw-tpl[data-id="${CSS.escape(id)}"]`).forEach((t) => { const on = S.selectedTpls.includes(id); t.classList.toggle('is-on', on); $('.cw-tpl-pick', t).setAttribute('aria-pressed', on); });
        $('#cwTplSelCount').textContent = S.selectedTpls.length;
        if (S.tplTab === 'selected') renderTemplates();
        refreshPreview();
    }
    const refreshThumbs = debounce(() => {
        thumbVer++;
        $$('.cw-tpl').forEach((t) => { const r = t.getBoundingClientRect(); if (r.bottom > -200 && r.top < innerHeight + 200 && t.offsetParent) drawThumb(t); });
        refreshPreview();
    }, 250);
    function renderPalettes() {
        const sw = (p) => ['bg', 'primary', 'secondary', 'accent', 'dark'].map((k) => `<i style="background:${p[k]}"></i>`).join('');
        $('#cwPalettes').innerHTML = E.PALETTES.map((p) => `<button type="button" class="cw-pal ${p.id === S.paletteId ? 'is-on' : ''}" data-pal="${p.id}" title="${esc(p.name)}" aria-pressed="${p.id === S.paletteId}"><span class="cw-pal-sw">${sw(p)}</span><small>${esc(p.name)}</small></button>`).join('')
            + `<button type="button" class="cw-pal cw-pal-custom ${S.paletteId === 'custom' ? 'is-on' : ''}" data-pal="custom"><span class="cw-pal-sw">${sw(S.customPalette)}</span><small>Custom colours</small></button>`;
        $('#cwCustomPalette').hidden = S.paletteId !== 'custom';
    }
    /** Font-combo cards are plain HTML: add their stylesheets once, when the card nears the screen. */
    let comboSheetsDone = false;
    function loadComboSheets() {
        if (comboSheetsDone) return;
        const box = document.getElementById('cwCombos');
        const go = () => { if (comboSheetsDone) return; comboSheetsDone = true; E.FONT_COMBOS.forEach((c) => [c.main, c.secondary, c.accent].forEach(E.ensureFontSheet)); };
        if (!box || !('IntersectionObserver' in window)) { go(); return; }
        new IntersectionObserver((es, obs) => { if (es.some((e) => e.isIntersecting)) { obs.disconnect(); go(); } }, { rootMargin: '300px' }).observe(box);
    }
    function renderCombos() {
        $('#cwCombos').innerHTML = E.FONT_COMBOS.map((c, i) => `<button type="button" class="cw-combo ${!S.customFonts && i === S.comboIdx ? 'is-on' : ''}" data-combo="${i}">
            <span class="cw-combo-accent" style="font-family:'${c.accent}',cursive">${esc(c.name)}</span>
            <span class="cw-combo-main" style="font-family:'${c.main}',serif">Easy Dinner Ideas</span>
            <span class="cw-combo-sec" style="font-family:'${c.secondary}',sans-serif">${esc(c.main)} · ${esc(c.secondary)} · ${esc(c.accent)}</span></button>`).join('');
        loadComboSheets();
    }
    const pickers = [];
    function fontPicker(host, role) {
        const wrap = document.createElement('div');
        wrap.className = 'cw-fp';
        wrap.innerHTML = `<button type="button" class="cw-fp-btn" aria-haspopup="listbox"></button><div class="cw-fp-pop" hidden><input type="search" placeholder="Search ${E.FONTS.length} fonts" aria-label="Search fonts"><div class="cw-fp-list" role="listbox"></div></div>`;
        host.appendChild(wrap);
        const btn = $('.cw-fp-btn', wrap), pop = $('.cw-fp-pop', wrap), input = $('input', pop), list = $('.cw-fp-list', pop);
        const paint = () => { const v = S.fonts[role]; btn.textContent = v; btn.style.fontFamily = `'${v}', sans-serif`; E.loadFont(v); };
        const fill = () => {
            const q = input.value.trim().toLowerCase();
            list.innerHTML = E.FONTS.filter((f) => f.toLowerCase().includes(q)).map((f) => `<button type="button" role="option" data-f="${esc(f)}" aria-selected="${f === S.fonts[role]}">${esc(f)}${E.isScript(f) ? ' <small>script</small>' : ''}</button>`).join('') || '<p class="cw-help">No font matches.</p>';
        };
        btn.onclick = () => { pop.hidden = !pop.hidden; if (!pop.hidden) { input.value = ''; fill(); input.focus(); } };
        input.oninput = fill;
        list.onclick = (e) => { const b = e.target.closest('[data-f]'); if (!b) return; S.fonts[role] = b.dataset.f; pop.hidden = true; paint(); E.loadFont(b.dataset.f).then(refreshThumbs); };
        list.onmouseover = (e) => { const b = e.target.closest('[data-f]'); if (b && !b.dataset.l) { b.dataset.l = 1; E.loadFont(b.dataset.f).then(() => { b.style.fontFamily = `'${b.dataset.f}', sans-serif`; }); } };
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) pop.hidden = true; });
        wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape') { pop.hidden = true; btn.focus(); } });
        paint();
        pickers.push({ paint });
    }

    /** Canva SVG kept in this browser only: strip scripts/handlers, then add as a template. */
    function addSvg(file) {
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { notify('That SVG is larger than 5 MB. Export it again with fewer embedded images.', 'error'); return; }
        const reader = new FileReader();
        reader.onload = () => {
            let svg = String(reader.result || '');
            if (!/<svg[\s>]/i.test(svg)) { notify('That file is not an SVG. In Canva choose Share → Download → SVG.', 'error'); return; }
            svg = svg.replace(/<script[\s\S]*?<\/script\s*>/gi, '').replace(/<foreignObject[\s\S]*?<\/foreignObject\s*>/gi, '').replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*')/gi, '');
            const t = { id: 'u' + Date.now(), name: $('#cwCanvaName').value.trim() || file.name.replace(/\.svg$/i, '') || 'My design', svg, text_position: $('#cwCanvaTextPos').value };
            S.customTpls.unshift(t); E.setCustomTemplates(S.customTpls); S.selectedTpls.push(t.id);
            $('#cwCanvaName').value = ''; $('#cwCanvaFile').value = '';
            notify(`“${esc(t.name)}” was added to your selected templates.`, 'success');
            renderTemplates(); refreshPreview();
        };
        reader.readAsText(file);
    }

    /* ---------- live preview ---------- */
    async function loadPreviewPage(tries) {
        tries = tries || 0;
        const src = S.selected.length ? S.selected : S.pages.map((p) => p.url);
        if (!src.length || tries > 2) return;
        const url = src[Math.floor(Math.random() * src.length)];
        $('#cwPreviewPage').textContent = 'Loading ' + pathOf(url) + '…';
        const r = await api('page_images', { url });
        if (!r.ok || !r.images.length) { if (src.length > 1) return loadPreviewPage(tries + 1); $('#cwPreviewPage').textContent = r.error || 'No usable images on that page.'; return; }
        S.preview = { url, title: r.title || SAMPLE.headline, images: r.images.map((i) => i.url) };
        const pg = S.pages.find((p) => p.url === url); if (pg && !pg.title) { pg.title = r.title; renderPages(); }
        $('#cwPreviewPage').textContent = S.preview.title;
        refreshPreview();
    }
    const refreshPreview = debounce(() => {
        let ids = S.selectedTpls.slice(0, 6), note = '';
        if (!ids.length) {
            ids = S.aiTemplates ? ['t51', 't12', 't56', 't16', 't47', 't60'] : [];
            note = S.aiTemplates ? `No templates selected — AI will choose from all ${E.TEMPLATES.length}. A few examples are shown.` : 'Select templates to preview them here.';
        } else if (S.selectedTpls.length > 6) note = `Showing 6 of ${S.selectedTpls.length} selected templates.`;
        $('#cwPreviewNote').textContent = note;
        const imgs = S.preview.images.length ? S.preview.images : (S.sample ? [S.sample] : []);
        const h = S.preview.title || SAMPLE.headline, head = h.length > 60 ? h.slice(0, 57).replace(/\s+\S*$/, '') + '…' : h;
        const grid = $('#cwPreviewGrid');
        grid.innerHTML = ids.map((id) => `<figure><canvas></canvas><figcaption>${esc(E.templateName(id))}</figcaption></figure>`).join('');
        $$('canvas', grid).forEach((c, i) => {
            const mode = E.pickMode(S.layout, S.singlePct, i);
            queueRender(c, specFrom({ template: ids[i], size: S.size, mode, images: E.pickImages(imgs.length > 1 || mode !== 'collage' ? imgs : [imgs[0], imgs[0], imgs[0]], mode, i),
                headline: head, kicker: SAMPLE.kicker, cta: SAMPLE.cta, website: S.site.host || 'yourwebsite.com', palette: palette(), fonts: fonts() }, 0.34));
        });
    }, 300);

    /* ---------- generate ---------- */
    async function generate() {
        if (S.gen.running) return;
        if (!S.selected.length) { notify('Paste a link and click Scan, then select at least one page.', 'error'); return; }
        if (!S.aiTemplates && !S.selectedTpls.length) { notify('Select at least one template, or turn on “AI picks the best template”.', 'error'); return; }
        if (S.remaining <= 0) { showAttempts(); notify($('#pmAttempts').innerHTML, 'info'); return; }
        const btn = $('#pmGenerate');
        btn.disabled = true; btn.textContent = 'Starting…';
        const r = await api('start', { pages: S.selected });
        btn.textContent = 'Generate pins';
        if (!r.ok) { if (r.limit_reached) { S.remaining = 0; showAttempts(); } btn.disabled = S.remaining <= 0; notify(esc(r.error), 'error'); return; }
        S.remaining = r.remaining; showAttempts();
        notify('');
        S.gen = { pages: r.pages.map((url, i) => ({ key: 'p' + i, url, title: '', status: 'pending', pins: [], images: [] })), running: true, counter: 0, token: r.token };
        goto(2);
        renderReview();
        const cfg = { layout: S.layout, pct: S.singlePct, ai: S.aiTemplates, pool: tplPool(), size: S.size, palette: palette(), fonts: fonts(), count: Math.max(1, Math.min(B.maxPins, +$('#pmPinsPerPage').value || B.maxPins)) };
        const next = () => S.gen.pages.find((p) => p.status === 'pending');
        const worker = async () => { let p; while ((p = next())) { p.status = 'working'; await processPage(p, cfg); updateProgress(); } };
        await Promise.all([worker(), worker()]);
        S.gen.running = false;
        updateProgress();
    }
    async function processPage(p, cfg) {
        renderGroup(p);
        const r = await api('prepare', { token: S.gen.token, url: p.url, count: cfg.count });
        if (!r.ok) { p.status = r.skip ? 'skipped' : 'failed'; p.error = r.error; renderGroup(p); return; }
        Object.assign(p, { title: r.title, images: r.images.map((i) => i.url), category: r.category });
        const idx = S.gen.pages.indexOf(p);
        for (let i = 0; i < r.items.length; i++) {
            const it = r.items[i];
            let mode = E.pickMode(cfg.layout, cfg.pct, S.gen.counter++);
            const images = E.pickImages(p.images, mode, i);
            if (images.length < 2) mode = 'single';
            const design = { template: E.pickTemplate(cfg.pool, r.category, cfg.ai, idx, i, r.items.length), size: cfg.size, mode, images, page_images: p.images.slice(),
                headline: it.headline, kicker: it.kicker, cta: it.cta, website: S.site.host || hostOf(p.url), palette: cfg.palette, fonts: cfg.fonts };
            const pin = { id: pinSeq++, pin_index: i, design, title: it.title, description: it.description, alt_text: it.alt_text, keywords: it.keywords, page_url: p.url };
            await renderPin(pin);
            p.pins.push(pin);
            renderGroup(p);
        }
        p.status = p.pins.length ? 'done' : 'failed';
        renderGroup(p);
    }
    async function renderPin(pin) {
        const c = document.createElement('canvas');
        await E.render(c, specFrom(pin.design, 1));
        const blob = await E.toBlob(c, 0.92);
        if (pin.url) URL.revokeObjectURL(pin.url);
        pin.blob = blob; pin.url = URL.createObjectURL(blob);
    }

    /* ---------- results ---------- */
    const STATUS = { pending: 'Waiting', working: 'Generating…', done: 'Ready', skipped: 'Skipped', failed: 'Failed' };
    function renderReview() { $('#cwReviewPages').innerHTML = S.gen.pages.map((p) => `<div class="cw-group" id="g-${p.key}"></div>`).join(''); S.gen.pages.forEach(renderGroup); updateProgress(); }
    function renderGroup(p) {
        const el = $('#g-' + p.key); if (!el) return;
        el.innerHTML = `<div class="cw-group-head pm-group-head"><span class="cw-group-title"><b>${esc(p.title || pathOf(p.url))}</b><small>${esc(p.url)}</small></span>
            <span class="cw-status cw-status-${p.status}">${STATUS[p.status]}${p.status === 'done' ? ' · ' + p.pins.length + ' pins' : ''}</span></div>
            ${p.error ? `<p class="cw-group-msg">${esc(p.error)}</p>` : ''}
            <div class="cw-pins">${p.pins.map((pin) => `
                <div class="cw-pin" data-pin="${pin.id}">
                    <button type="button" class="cw-pin-open" data-edit="${pin.id}" title="Edit pin"><img src="${pin.url}" alt="${esc(pin.alt_text || pin.title)}"></button>
                    <button type="button" class="cw-pin-del" data-del="${pin.id}" aria-label="Remove pin">&times;</button>
                    <span class="cw-pin-title">${esc(pin.title)}</span>
                    <div class="pm-pin-actions">
                        <a class="btn-secondary btn-small" href="${pin.url}" download="${slug(pin.title)}.jpg">Download</a>
                        <button type="button" class="btn-secondary btn-small" data-copy="${pin.id}">Copy text</button>
                    </div>
                </div>`).join('')}${p.status === 'working' ? '<div class="cw-pin cw-pin-ghost" aria-hidden="true"><span></span></div>' : ''}</div>`;
    }
    function updateProgress() {
        const pages = S.gen.pages, fin = pages.filter((p) => ['done', 'skipped', 'failed'].includes(p.status)).length;
        const pins = pages.reduce((n, p) => n + p.pins.length, 0);
        $('#cwProgBar').style.width = pages.length ? (fin / pages.length * 100) + '%' : '0';
        $('#cwProgText').textContent = S.gen.running ? `Generating… ${fin} of ${pages.length} pages` : pins ? `${pins} pins ready` : 'No pins could be made';
        const skipped = pages.filter((p) => p.status === 'skipped').length;
        $('#cwProgSub').textContent = skipped ? `${skipped} page(s) skipped` : '';
        $('#pmDownloadAll').disabled = S.gen.running || !pins;
    }
    function findPin(id) { for (const p of S.gen.pages) { const pin = p.pins.find((x) => x.id === id); if (pin) return { page: p, pin }; } return null; }
    function removePin(id) {
        const f = findPin(id); if (!f) return false;
        f.page.pins = f.page.pins.filter((x) => x.id !== id); URL.revokeObjectURL(f.pin.url);
        renderGroup(f.page); updateProgress(); return true;
    }
    async function copyText(id) {
        const f = findPin(id); if (!f) return;
        const text = `${f.pin.title}\n\n${f.pin.description}\n\nAlt text: ${f.pin.alt_text}\nKeywords: ${f.pin.keywords}\nLink: ${f.pin.page_url}`;
        try { await navigator.clipboard.writeText(text); notify('Title, description, alt text and keywords copied.', 'success'); }
        catch (e) { notify('Could not copy automatically — open the pin to select the text.', 'error'); }
    }
    async function downloadAll() {
        const pins = S.gen.pages.flatMap((p) => p.pins);
        if (!pins.length) return;
        if (!window.JSZip) { pins.forEach((pin, i) => setTimeout(() => { const a = document.createElement('a'); a.href = pin.url; a.download = `${i + 1}-${slug(pin.title)}.jpg`; a.click(); }, i * 400)); return; }
        const btn = $('#pmDownloadAll'); btn.disabled = true; btn.textContent = 'Preparing ZIP…';
        const zip = new JSZip();
        const q = (v) => '"' + String(v || '').replace(/"/g, '""') + '"';
        const rows = ['file,title,description,alt_text,keywords,link'];
        pins.forEach((pin, i) => { const name = `${String(i + 1).padStart(2, '0')}-${slug(pin.title)}.jpg`; zip.file(name, pin.blob); rows.push([name, pin.title, pin.description, pin.alt_text, pin.keywords, pin.page_url].map(q).join(',')); });
        zip.file('pins.csv', rows.join('\n'));
        const blob = await zip.generateAsync({ type: 'blob' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `pins-${slug(S.site.host)}.zip`; a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 5000);
        btn.disabled = false; btn.textContent = 'Download all';
    }
    async function keep() {
        const r = await api('keep', { config: {
            site: S.site, pages: S.selected,
            design: { size: S.size, layout: S.layout, singlePct: S.singlePct, aiTemplates: S.aiTemplates, selectedTpls: S.selectedTpls.filter((id) => /^t\d+$/.test(id)),
                paletteId: S.paletteId, customPalette: S.customPalette, comboIdx: S.comboIdx, customFonts: S.customFonts, fonts: S.fonts },
            pins_per_page: +$('#pmPinsPerPage').value,
        } });
        if (!r.ok) { notify(esc(r.error), 'error'); return; }
        location.href = r.next;
    }

    /* ---------- pin editor ---------- */
    const M = { pin: null, page: null, design: null };
    const modalDesign = () => Object.assign({}, M.design, { headline: $('#cwMHeadline').value, kicker: $('#cwMKicker').value, cta: $('#cwMCta').value });
    const modalRender = debounce(() => { if (M.pin) queueRender($('#cwModalCanvas'), specFrom(modalDesign(), 0.5)); }, 200);
    function openModal(id) {
        const f = findPin(id); if (!f) return;
        M.pin = f.pin; M.page = f.page; M.design = JSON.parse(JSON.stringify(f.pin.design));
        $('#cwMHeadline').value = M.design.headline || ''; $('#cwMKicker').value = M.design.kicker || ''; $('#cwMCta').value = M.design.cta || '';
        $('#cwMTitle').value = f.pin.title || ''; $('#cwMDesc').value = f.pin.description || ''; $('#cwMAlt').value = f.pin.alt_text || ''; $('#cwMKeywords').value = f.pin.keywords || '';
        renderModalImages(); renderModalTemplates(); counters();
        $('#cwModal').hidden = false; document.body.classList.add('cw-noscroll');
        modalRender(); $('#cwMHeadline').focus();
    }
    function closeModal() { $('#cwModal').hidden = true; document.body.classList.remove('cw-noscroll'); M.pin = null; }
    function renderModalImages() {
        const sel = M.design.images || [];
        $('#cwModalImgs').innerHTML = M.design.page_images.map((u) => { const i = sel.indexOf(u); return `<button type="button" class="${i >= 0 ? 'is-on' : ''}" data-img="${esc(u)}" aria-pressed="${i >= 0}"><img src="${esc(abs(u))}" alt="">${i >= 0 ? `<span>${i + 1}</span>` : ''}</button>`; }).join('');
    }
    function renderModalTemplates() {
        const all = E.TEMPLATES.concat(S.customTpls);
        const ordered = S.selectedTpls.map((id) => E.getTemplate(id)).filter(Boolean).concat(all.filter((t) => !S.selectedTpls.includes(t.id)));
        const grid = $('#cwModalTpls');
        grid.innerHTML = ordered.map((t) => `<button type="button" class="cw-mtpl ${t.id === M.design.template ? 'is-on' : ''}" data-tpl="${esc(t.id)}" title="${esc(t.name)}"><canvas></canvas><span>${esc(t.name)}</span></button>`).join('');
        const io = new IntersectionObserver((ents) => ents.forEach((en) => {
            if (!en.isIntersecting || en.target.dataset.done) return;
            en.target.dataset.done = 1;
            queueRender($('canvas', en.target), specFrom(Object.assign(modalDesign(), { template: en.target.dataset.tpl }), 0.16));
        }), { root: grid.closest('.cw-modal-form'), rootMargin: '100px' });
        $$('.cw-mtpl', grid).forEach((b) => io.observe(b));
    }
    function counters() { $$('.cw-counter').forEach((c) => { c.textContent = `${$('#' + c.dataset.for).value.length}/${c.dataset.max}`; }); }
    async function saveModal() {
        if (!M.pin) return;
        const btn = $('#cwModalSave'); btn.disabled = true; btn.textContent = 'Saving…';
        Object.assign(M.pin, { design: modalDesign(), title: $('#cwMTitle').value.trim(), description: $('#cwMDesc').value.trim(), alt_text: $('#cwMAlt').value.trim(), keywords: $('#cwMKeywords').value.trim() });
        await renderPin(M.pin);
        btn.disabled = false; btn.textContent = 'Save pin';
        renderGroup(M.page); closeModal();
    }

    /* ---------- wiring ---------- */
    function bind() {
        $$('[data-goto]').forEach((b) => b.addEventListener('click', () => goto(+b.dataset.goto)));
        $('#cwScanBtn').onclick = scan;
        $('#cwSiteUrl').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); scan(); } });
        $('#cwPageSearch').addEventListener('input', debounce((e) => { S.search = e.target.value.trim(); renderPages(); }, 150));
        $('#cwPagesList').addEventListener('change', (e) => {
            if (!e.target.matches('input[type=checkbox]')) return;
            const u = e.target.value;
            if (e.target.checked) { if (S.selected.length < B.maxPages) S.selected.push(u); } else S.selected = S.selected.filter((x) => x !== u);
            renderPages();
            if (S.selected.length === 1 && e.target.checked) loadPreviewPage();
        });
        $('#cwSelectNone').onclick = () => { S.selected = []; renderPages(); };
        $('#pmPinsPerPage').onchange = renderPages;
        $('#cwShuffle').onclick = () => loadPreviewPage();
        $('#cwSizes').addEventListener('change', (e) => { S.size = e.target.value; renderSizes(); refreshThumbs(); });
        $('#cwLayout').addEventListener('change', (e) => { S.layout = e.target.value; $('#cwCustomMix').hidden = S.layout !== 'custom'; refreshThumbs(); });
        $('#cwMixRange').addEventListener('input', (e) => { S.singlePct = +e.target.value; $('#cwMixSingle').textContent = S.singlePct + '%'; $('#cwMixCollage').textContent = (100 - S.singlePct) + '%'; refreshPreview(); });
        $('#cwAiTemplates').onchange = (e) => { S.aiTemplates = e.target.checked; refreshPreview(); };
        $$('.cw-tabs [data-tab]').forEach((b) => b.addEventListener('click', () => { S.tplTab = b.dataset.tab; $$('.cw-tabs [data-tab]').forEach((x) => x.classList.toggle('is-active', x === b)); renderTemplates(); }));
        $('#cwTplFilters').addEventListener('click', (e) => { const b = e.target.closest('[data-cat]'); if (!b) return; S.tplFilter = b.dataset.cat; renderFilters(); renderTemplates(); });
        document.addEventListener('click', (e) => {
            const pick = e.target.closest('.cw-tpl-pick'); if (pick) { toggleTemplate(pick.closest('.cw-tpl').dataset.id); return; }
            const del = e.target.closest('.cw-tpl-del');
            if (del) { S.customTpls = S.customTpls.filter((t) => t.id !== del.dataset.del); S.selectedTpls = S.selectedTpls.filter((x) => x !== del.dataset.del); E.setCustomTemplates(S.customTpls); renderTemplates(); refreshPreview(); }
        });
        $('#cwCanvaFile').onchange = (e) => addSvg(e.target.files[0]);
        $('#cwPalettes').addEventListener('click', (e) => { const b = e.target.closest('[data-pal]'); if (!b) return; S.paletteId = b.dataset.pal; renderPalettes(); refreshThumbs(); });
        $('#cwCustomPalette').addEventListener('input', debounce((e) => { if (!e.target.dataset.role) return; S.customPalette[e.target.dataset.role] = e.target.value; renderPalettes(); refreshThumbs(); }, 120));
        $('#cwCombos').addEventListener('click', (e) => {
            const b = e.target.closest('[data-combo]'); if (!b) return;
            S.comboIdx = +b.dataset.combo; S.customFonts = false; $('#cwCustomFonts').checked = false; $('#cwFontPickers').hidden = true;
            renderCombos(); E.loadFonts(fonts()).then(refreshThumbs);
        });
        $('#cwCustomFonts').onchange = (e) => {
            S.customFonts = e.target.checked;
            if (S.customFonts) { S.fonts = Object.assign({}, E.FONT_COMBOS[S.comboIdx]); pickers.forEach((p) => p.paint()); }
            $('#cwFontPickers').hidden = !S.customFonts; renderCombos(); refreshThumbs();
        };
        $('#pmGenerate').onclick = generate;
        $('#pmDownloadAll').onclick = downloadAll;
        $('#pmKeep').onclick = keep;
        $('#cwReviewPages').addEventListener('click', (e) => {
            const del = e.target.closest('[data-del]'); if (del) { removePin(+del.dataset.del); return; }
            const ed = e.target.closest('[data-edit]'); if (ed) { openModal(+ed.dataset.edit); return; }
            const cp = e.target.closest('[data-copy]'); if (cp) copyText(+cp.dataset.copy);
        });
        $('#cwModalClose').onclick = closeModal; $('#cwModalCancel').onclick = closeModal;
        $('#cwModal').addEventListener('click', (e) => { if (e.target.id === 'cwModal') closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('#cwModal').hidden) closeModal(); });
        ['cwMHeadline', 'cwMKicker', 'cwMCta'].forEach((id) => $('#' + id).addEventListener('input', modalRender));
        $('#cwModal').addEventListener('input', counters);
        $('#cwModalImgs').addEventListener('click', (e) => {
            const b = e.target.closest('[data-img]'); if (!b) return;
            let imgs = (M.design.images || []).slice(); const u = b.dataset.img, i = imgs.indexOf(u);
            if (i >= 0) { if (imgs.length > 1) imgs.splice(i, 1); } else imgs.push(u);
            M.design.images = imgs.slice(0, 4); M.design.mode = M.design.images.length > 1 ? 'collage' : 'single';
            renderModalImages(); modalRender();
        });
        $('#cwModalUpload').onchange = (e) => {
            const file = e.target.files[0]; if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { notify('Upload a JPG, PNG or WebP image.', 'error'); return; }
            const url = URL.createObjectURL(file); e.target.value = '';
            M.design.page_images.unshift(url); M.design.images = [url]; M.design.mode = 'single';
            renderModalImages(); modalRender();
        };
        $('#cwModalTpls').addEventListener('click', (e) => { const b = e.target.closest('[data-tpl]'); if (!b) return; M.design.template = b.dataset.tpl; $$('.cw-mtpl').forEach((x) => x.classList.toggle('is-on', x === b)); modalRender(); });
        $('#cwModalSave').onclick = saveModal;
        $('#cwModalRemove').onclick = () => { if (M.pin && removePin(M.pin.id)) closeModal(); };
        window.addEventListener('beforeunload', (e) => { if (S.gen.pages.some((p) => p.pins.length)) { e.preventDefault(); e.returnValue = ''; } });
    }

    async function init() {
        renderSizes(); renderFilters(); renderPalettes(); renderCombos();
        $$('#cwFontPickers .cw-fontpick').forEach((h) => fontPicker(h, h.dataset.role));
        bind(); renderTemplates(); showAttempts(); refreshPreview();
        const s = await api('sample');
        if (s.ok) { S.sample = BASE + s.url; refreshThumbs(); }
        if (B.prefillUrl) { await scan(); if (B.auto && S.selected.length) generate(); }
    }
    init();
})();
