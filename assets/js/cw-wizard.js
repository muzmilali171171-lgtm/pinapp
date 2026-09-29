/*
 * Classic Wizard — UI controller.
 * Step 1 Design → Step 2 Schedule → Step 3 Generate & review (draft) → Approve & schedule.
 */
(function () {
    'use strict';
    const E = window.CWEngine, B = window.CW_BOOT || {}, BASE = '../';
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const hostOf = (u) => { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return ''; } };
    const pathOf = (u) => { try { const x = new URL(u); return x.pathname + x.search; } catch (e) { return u; } };
    const fmtDate = (s) => { if (!s) return ''; const d = new Date(s.replace(' ', 'T')); return d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }); };

    const SAMPLE_TEXT = { headline: '15 Easy Dinner Ideas for Busy Weeknights', kicker: 'Save for later', cta: 'Read more' };

    const S = {
        step: 1, projectId: B.draftId || null,
        site: { url: '', crawl_site_id: null, host: '' },
        pages: [], selected: new Set(), search: '',
        size: '2:3', layout: 'single', singlePct: 70,
        aiTemplates: true, selectedTpls: [], tplTab: 'all', tplFilter: 'all', customTpls: [],
        paletteId: 'p01', customPalette: { bg: '#ffffff', primary: '#e60023', secondary: '#fde8ec', accent: '#ffb000', dark: '#111111' },
        comboIdx: 0, customFonts: false, fonts: Object.assign({}, E.FONT_COMBOS[0]),
        sample: null, preview: { url: null, title: '', images: [] },
        boards: [], boardIds: new Set(),
        gen: { pages: [], running: false, paused: false, activeKey: null, counter: 0 },
    };

    /* ===================== Small helpers ===================== */
    async function post(url, data, files) {
        const fd = new FormData();
        Object.entries(data || {}).forEach(([k, v]) => {
            if (v === undefined || v === null) return;
            fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
        });
        Object.entries(files || {}).forEach(([k, f]) => fd.append(k, f, f.name || 'pin.jpg'));
        try {
            const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
            const text = await res.text();
            try { return JSON.parse(text); } catch (e) { return { ok: false, error: 'The server sent an unexpected reply (HTTP ' + res.status + '). Try again.' }; }
        } catch (e) {
            return { ok: false, error: 'Network error — check your connection and try again.' };
        }
    }
    const api = (action, data, files) => post('ajax-cw', Object.assign({ action }, data || {}), files);

    function notify(msg, type) {
        const box = $('#cwAlert');
        if (!msg) { box.innerHTML = ''; return; }
        box.innerHTML = `<div class="alert alert-${type || 'info'}">${msg}<button type="button" class="cw-x" aria-label="Dismiss">&times;</button></div>`;
        $('.cw-x', box).onclick = () => { box.innerHTML = ''; };
        if (type === 'error') box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function palette() {
        if (S.paletteId === 'custom') return Object.assign({ id: 'custom', name: 'Custom' }, S.customPalette);
        return E.PALETTES.find((p) => p.id === S.paletteId) || E.PALETTES[0];
    }
    function fonts() { return S.customFonts ? Object.assign({}, S.fonts) : Object.assign({}, E.FONT_COMBOS[S.comboIdx]); }
    function tplPool() { return S.selectedTpls.length ? S.selectedTpls.slice() : E.TEMPLATES.map((t) => t.id); }
    function specFrom(design, scale) {
        return Object.assign({}, design, { images: (design.images || []).map((u) => (/^(https?:|blob:|\.\.\/)/.test(u) ? u : BASE + u)), scale });
    }

    // One render at a time keeps the page responsive while dozens of thumbnails redraw.
    let renderChain = Promise.resolve();
    function queueRender(canvas, spec) {
        const job = renderChain.then(() => E.render(canvas, spec)).catch(() => {});
        renderChain = job;
        return job;
    }
    async function renderBlob(design) {
        const c = document.createElement('canvas');
        await E.render(c, specFrom(design, 1));
        return E.toBlob(c, 0.9);
    }

    /* ===================== Bulk Pin "Pin Templates & Styles" ===================== */
    async function loadProTemplates() {
        try {
            const res = await fetch(BASE + 'pin-templates?t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' });
            const j = await res.json();
            return j && j.ok && Array.isArray(j.templates) ? j.templates : [];
        } catch (e) { return []; }
    }
    // Same designs as Bulk Pin, drawn on the server on this pin's images. Same inputs → same cached file.
    const proCache = new Map();
    function renderProOnServer(o) {
        const k = JSON.stringify(o);
        if (!proCache.has(k)) {
            proCache.set(k, api('pt_render', { key: o.key, size: o.size, headline: o.headline, website: o.website, cta: o.cta, images: o.images, final: o.final ? 1 : '' })
                .then((r) => (r.ok ? BASE + r.url : null)));
        }
        return proCache.get(k);
    }

    /* ===================== Stepper ===================== */
    function goto(step) {
        if (step === 2 && !validateDesign()) return;
        if (step === 3 && !S.projectId) { startGeneration(); return; }
        S.step = step;
        $$('.cw-step-panel').forEach((p) => { p.hidden = +p.dataset.panel !== step; });
        $$('.cw-stepper li').forEach((li) => {
            const n = +li.dataset.step;
            li.classList.toggle('is-active', n === step);
            li.classList.toggle('is-done', n < step);
        });
        if (step === 1) refreshThumbs();
        if (step === 2) updateSummary();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function validateDesign() {
        if (!S.selected.size) { notify('Scan your website and select at least one page.', 'error'); return false; }
        if (!S.aiTemplates && !S.selectedTpls.length) { notify('Select at least one template, or turn on “AI picks the best template”.', 'error'); return false; }
        notify('');
        return true;
    }

    /* ===================== Step 1: website & pages ===================== */
    async function scan() {
        let url = $('#cwSiteUrl').value.trim();
        if (url && !/^https?:\/\//i.test(url)) { url = 'https://' + url; $('#cwSiteUrl').value = url; }
        if (!url) { notify('Paste your website or page link first.', 'error'); return; }
        const btn = $('#cwScanBtn');
        btn.disabled = true; btn.textContent = 'Scanning…';
        $('#cwScanStatus').textContent = 'Reading the sitemap — large sites can take a minute.';
        const r = await post('ajax-scan-website', { url });
        btn.disabled = false; btn.textContent = 'Scan';
        if (!r.crawl_site_id) { $('#cwScanStatus').textContent = ''; notify(esc(r.error || 'Could not scan that website.'), 'error'); return; }
        S.site = { url, crawl_site_id: r.crawl_site_id, host: hostOf(url) };
        await loadPages(url);
        if (!S.pages.length) {
            $('#cwScanStatus').textContent = '';
            notify(esc(r.error || 'No pages were found on that website.'), 'error');
        }
    }

    async function loadPages(pastedUrl, keepSelection) {
        const r = await post('ajax-classic-wizard-pages', { crawl_site_id: S.site.crawl_site_id });
        if (!r.ok) { notify(esc(r.error || 'Could not load pages.'), 'error'); return; }
        S.pages = r.pages.map((p) => ({ id: p.id, url: p.url, title: p.title || '' }));
        if (pastedUrl) {
            const u = new URL(pastedUrl);
            const isPage = u.pathname.replace(/\/+$/, '') !== '';
            const norm = (x) => x.replace(/\/+$/, '').replace(/^https?:\/\/(www\.)?/, '');
            let hit = S.pages.find((p) => norm(p.url) === norm(pastedUrl));
            if (isPage && !hit) { hit = { id: null, url: pastedUrl, title: '' }; S.pages.unshift(hit); }
            if (!keepSelection) S.selected = new Set(isPage ? [hit.url] : S.pages.map((p) => p.url));
            $('#cwScanStatus').textContent = `${S.pages.length} page(s) found.` + (isPage ? ' Your pasted page is selected — select more below, or all.' : ' All pages are selected — untick the ones you don’t want.');
        }
        $('#cwPagesBox').hidden = !S.pages.length;
        renderPages();
        $('#cwShuffle').disabled = false;
        loadPreviewPage();
        refreshThumbs();
    }

    function filteredPages() {
        const q = S.search.toLowerCase();
        return q ? S.pages.filter((p) => p.url.toLowerCase().includes(q) || (p.title || '').toLowerCase().includes(q)) : S.pages;
    }
    function renderPages() {
        const list = filteredPages();
        const shown = list.slice(0, 600);
        $('#cwPagesList').innerHTML = shown.map((p) => `
            <label class="cw-page" role="listitem">
                <input type="checkbox" value="${esc(p.url)}" ${S.selected.has(p.url) ? 'checked' : ''}>
                <span><b>${esc(p.title || pathOf(p.url))}</b><small>${esc(p.url)}</small></span>
            </label>`).join('') + (list.length > shown.length ? `<p class="cw-help cw-more">Showing the first 600 of ${list.length} matches — search to narrow down.</p>` : '')
            + (!list.length ? '<p class="cw-help cw-more">No pages match that search.</p>' : '');
        updatePageCount();
    }
    function updatePageCount() {
        $('#cwSelCount').textContent = S.selected.size;
        $('#cwPageTotal').textContent = S.pages.length;
        $('#cwStep1Summary').textContent = S.selected.size ? `${S.selected.size} page(s) selected` : '';
    }

    /* ===================== Step 1: size, layout ===================== */
    function renderSizes() {
        $('#cwSizes').innerHTML = Object.entries(E.SIZES).map(([k, v]) => `
            <label class="cw-size ${k === S.size ? 'is-on' : ''}">
                <input type="radio" name="cwSize" value="${k}" ${k === S.size ? 'checked' : ''}>
                <span class="cw-size-box"><i style="aspect-ratio:${v.w}/${v.h}"></i></span>
                <b>${esc(v.label)}</b><small>${esc(v.sub)}</small>
            </label>`).join('');
    }

    /* ===================== Step 1: templates ===================== */
    const thumbObserver = new IntersectionObserver((entries) => {
        entries.forEach((en) => { if (en.isIntersecting) drawThumb(en.target); });
    }, { rootMargin: '200px' });
    let thumbVer = 0;

    function thumbSpec(id) {
        const img = S.sample ? [S.sample] : [];
        const collage = S.layout === 'collage';
        return {
            template: id, size: S.size, mode: collage ? 'collage' : 'single', images: collage ? [img[0], img[0], img[0], img[0]].filter(Boolean) : img,
            headline: SAMPLE_TEXT.headline, kicker: SAMPLE_TEXT.kicker, cta: SAMPLE_TEXT.cta, website: S.site.host || 'yourwebsite.com',
            palette: palette(), fonts: fonts(), scale: 0.26,
        };
    }
    function drawThumb(tile) {
        if (+tile.dataset.ver === thumbVer) return;
        tile.dataset.ver = thumbVer;
        queueRender($('canvas', tile), specFrom(thumbSpec(tile.dataset.id), 0.26));
    }
    function tileHtml(t, removable) {
        const on = S.selectedTpls.includes(t.id);
        return `<div class="cw-tpl ${on ? 'is-on' : ''}" data-id="${esc(t.id)}">
            <button type="button" class="cw-tpl-pick" aria-pressed="${on}" title="${on ? 'Remove from' : 'Add to'} your selection">
                <canvas></canvas><span class="cw-tick" aria-hidden="true">✓</span>
            </button>
            <span class="cw-tpl-name">${esc(t.name)}</span>
            ${removable ? `<button type="button" class="cw-tpl-del" data-del="${t.db_id}" title="Delete this design">Delete</button>` : ''}
        </div>`;
    }
    function renderTemplates() {
        const grid = $('#cwTplGrid');
        const canva = S.tplTab === 'canva';
        $('#cwCanvaBox').hidden = !canva;
        grid.hidden = canva;
        $('#cwTplFilters').hidden = S.tplTab !== 'all';
        $('#cwTplSelCount').textContent = S.selectedTpls.length;
        $('#cwTplAllCount').textContent = E.TEMPLATES.length + S.customTpls.length;
        let list;
        if (S.tplTab === 'selected') list = S.selectedTpls.map((id) => E.getTemplate(id)).filter(Boolean);
        else list = E.TEMPLATES.filter((t) => S.tplFilter === 'all' || t.tags.includes(S.tplFilter)).concat(S.tplFilter === 'all' ? S.customTpls : []);
        grid.innerHTML = list.length ? list.map((t) => tileHtml(t, false)).join('')
            : `<p class="cw-help cw-empty">${S.tplTab === 'selected' ? 'No templates selected yet. Open “All templates” and click the designs you like.' : 'No templates in this category.'}</p>`;
        $('#cwCanvaGrid').innerHTML = S.customTpls.length ? S.customTpls.map((t) => tileHtml(t, true)).join('')
            : '<p class="cw-help cw-empty">No imported designs yet.</p>';
        $$('.cw-tpl').forEach((tile) => { tile.dataset.ver = -1; thumbObserver.observe(tile); });
    }
    function renderFilters() {
        const hasPro = E.TEMPLATES.some((t) => t.pt);
        const cats = ['all'].concat(hasPro ? [E.PRO_TAG] : [], E.CATEGORIES.filter((c) => c !== 'general'));
        const label = (c) => (c === 'all' ? 'All' : c === E.PRO_TAG ? 'Pin Templates &amp; Styles' : c === 'diy' ? 'DIY' : c[0].toUpperCase() + c.slice(1));
        $('#cwTplFilters').innerHTML = cats.map((c) => `<button type="button" class="${c === S.tplFilter ? 'is-on' : ''}" data-cat="${c}">${label(c)}</button>`).join('');
    }
    function toggleTemplate(id) {
        const i = S.selectedTpls.indexOf(id);
        if (i >= 0) S.selectedTpls.splice(i, 1); else S.selectedTpls.push(id);
        $$(`.cw-tpl[data-id="${CSS.escape(id)}"]`).forEach((t) => {
            const on = S.selectedTpls.includes(id);
            t.classList.toggle('is-on', on);
            $('.cw-tpl-pick', t).setAttribute('aria-pressed', on);
        });
        $('#cwTplSelCount').textContent = S.selectedTpls.length;
        if (S.tplTab === 'selected') renderTemplates();
        refreshPreview();
    }
    const refreshThumbs = debounce(() => {
        thumbVer++;
        $$('.cw-tpl').forEach((tile) => {
            const r = tile.getBoundingClientRect();
            if (r.bottom > -200 && r.top < innerHeight + 200 && tile.offsetParent) drawThumb(tile);
        });
        refreshPreview();
    }, 250);

    async function uploadCanva(file) {
        if (!file) return;
        const r = await api('custom_template_upload', { name: $('#cwCanvaName').value.trim(), text_position: $('#cwCanvaTextPos').value }, { svg: file });
        $('#cwCanvaFile').value = '';
        if (!r.ok) { notify(esc(r.error), 'error'); return; }
        S.customTpls.unshift(r.template);
        E.setCustomTemplates(S.customTpls);
        S.selectedTpls.push(r.template.id);
        $('#cwCanvaName').value = '';
        notify(`“${esc(r.template.name)}” was imported and added to your selected templates.`, 'success');
        renderTemplates();
        refreshPreview();
    }

    /* ===================== Step 1: palettes & fonts ===================== */
    function renderPalettes() {
        $('#cwPalettes').innerHTML = E.PALETTES.map((p) => `
            <button type="button" class="cw-pal ${p.id === S.paletteId ? 'is-on' : ''}" data-pal="${p.id}" title="${esc(p.name)}" aria-pressed="${p.id === S.paletteId}">
                <span class="cw-pal-sw">${['bg', 'primary', 'secondary', 'accent', 'dark'].map((k) => `<i style="background:${p[k]}"></i>`).join('')}</span>
                <small>${esc(p.name)}</small>
            </button>`).join('') + `
            <button type="button" class="cw-pal cw-pal-custom ${S.paletteId === 'custom' ? 'is-on' : ''}" data-pal="custom" aria-pressed="${S.paletteId === 'custom'}">
                <span class="cw-pal-sw">${['bg', 'primary', 'secondary', 'accent', 'dark'].map((k) => `<i style="background:${S.customPalette[k]}"></i>`).join('')}</span>
                <small>Custom colours</small>
            </button>`;
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
        $('#cwCombos').innerHTML = E.FONT_COMBOS.map((c, i) => `
            <button type="button" class="cw-combo ${!S.customFonts && i === S.comboIdx ? 'is-on' : ''}" data-combo="${i}" aria-pressed="${!S.customFonts && i === S.comboIdx}">
                <span class="cw-combo-accent" style="font-family:'${c.accent}',cursive">${esc(c.name)}</span>
                <span class="cw-combo-main" style="font-family:'${c.main}',serif">Easy Dinner Ideas</span>
                <span class="cw-combo-sec" style="font-family:'${c.secondary}',sans-serif">${esc(c.main)} · ${esc(c.secondary)} · ${esc(c.accent)}</span>
            </button>`).join('');
        loadComboSheets();
    }

    /** Searchable font dropdown. */
    function fontPicker(host, role, getVal, setVal) {
        const wrap = document.createElement('div');
        wrap.className = 'cw-fp';
        wrap.innerHTML = `<button type="button" class="cw-fp-btn" aria-haspopup="listbox"></button>
            <div class="cw-fp-pop" hidden><input type="search" placeholder="Search ${E.FONTS.length} fonts" aria-label="Search fonts"><div class="cw-fp-list" role="listbox"></div></div>`;
        host.appendChild(wrap);
        const btn = $('.cw-fp-btn', wrap), pop = $('.cw-fp-pop', wrap), input = $('input', pop), list = $('.cw-fp-list', pop);
        const paint = () => { const v = getVal(); btn.textContent = v; btn.style.fontFamily = `'${v}', sans-serif`; E.loadFont(v); };
        const fill = () => {
            const q = input.value.trim().toLowerCase();
            const items = E.FONTS.filter((f) => f.toLowerCase().includes(q));
            list.innerHTML = items.map((f) => `<button type="button" role="option" data-f="${esc(f)}" aria-selected="${f === getVal()}">${esc(f)}${E.isScript(f) ? ' <small>script</small>' : ''}</button>`).join('') || '<p class="cw-help">No font matches.</p>';
        };
        btn.onclick = () => { pop.hidden = !pop.hidden; if (!pop.hidden) { input.value = ''; fill(); input.focus(); } };
        input.oninput = fill;
        list.onclick = (e) => { const b = e.target.closest('[data-f]'); if (!b) return; setVal(b.dataset.f); pop.hidden = true; paint(); };
        list.onmouseover = (e) => { const b = e.target.closest('[data-f]'); if (b && !b.dataset.l) { b.dataset.l = 1; E.loadFont(b.dataset.f).then(() => { b.style.fontFamily = `'${b.dataset.f}', sans-serif`; }); } };
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) pop.hidden = true; });
        wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape') { pop.hidden = true; btn.focus(); } });
        paint();
        return { paint };
    }
    const pickers = [];
    function initFontPickers() {
        $$('#cwFontPickers .cw-fontpick').forEach((host) => {
            const role = host.dataset.role;
            pickers.push(fontPicker(host, role, () => S.fonts[role], (v) => { S.fonts[role] = v; E.loadFont(v).then(refreshThumbs); }));
        });
    }

    /* ===================== Step 1: live preview ===================== */
    async function loadPreviewPage(tries) {
        tries = tries || 0;
        const pool = S.pages.filter((p) => S.selected.has(p.url));
        const src = pool.length ? pool : S.pages;
        if (!src.length || tries > 3) return;
        const page = src[Math.floor(Math.random() * src.length)];
        $('#cwPreviewPage').textContent = 'Loading ' + (page.title || pathOf(page.url)) + '…';
        const r = await api('page_images', { url: page.url });
        if (!r.ok || !r.images.length) { if (src.length > 1) return loadPreviewPage(tries + 1); $('#cwPreviewPage').textContent = 'No usable images found on the previewed page.'; return; }
        S.preview = { url: page.url, title: r.title || page.title || SAMPLE_TEXT.headline, images: r.images.map((i) => i.url) };
        $('#cwPreviewPage').textContent = S.preview.title;
        $('#cwPreviewPage').title = page.url;
        refreshPreview();
    }
    const refreshPreview = debounce(() => {
        const grid = $('#cwPreviewGrid');
        let ids = S.selectedTpls.slice(0, 6);
        let note = '';
        if (!ids.length) {
            ids = S.aiTemplates ? ['t01', 't12', 't16', 't26', 't47', 't21'] : [];
            note = S.aiTemplates ? 'No templates selected — AI will choose from all ' + E.TEMPLATES.length + ' templates. A few examples are shown.' : 'Select templates to preview them here.';
        } else if (S.selectedTpls.length > 6) note = `Showing 6 of ${S.selectedTpls.length} selected templates.`;
        $('#cwPreviewNote').textContent = note;
        const imgs = S.preview.images.length ? S.preview.images : (S.sample ? [S.sample] : []);
        const headline = S.preview.title || SAMPLE_TEXT.headline;
        const shortHead = headline.length > 60 ? headline.slice(0, 57).replace(/\s+\S*$/, '') + '…' : headline;
        grid.innerHTML = ids.map((id) => `<figure><canvas></canvas><figcaption>${esc(E.templateName(id))}</figcaption></figure>`).join('');
        $$('canvas', grid).forEach((c, i) => {
            const mode = E.pickMode(S.layout, S.singlePct, i);
            queueRender(c, specFrom({
                template: ids[i], size: S.size, mode, images: E.pickImages(imgs.length > 1 || mode !== 'collage' ? imgs : [imgs[0], imgs[0], imgs[0]], mode, i),
                headline: shortHead, kicker: SAMPLE_TEXT.kicker, cta: SAMPLE_TEXT.cta, website: S.site.host || 'yourwebsite.com',
                palette: palette(), fonts: fonts(),
            }, 0.34));
        });
    }, 300);

    /* ===================== Step 2: schedule ===================== */
    function renderAccounts() {
        $('#cwAccount').innerHTML = B.accounts.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
    }
    async function loadBoards() {
        const accountId = $('#cwAccount').value;
        $('#cwBoards').innerHTML = '<p class="cw-help">Loading boards…</p>';
        const r = await post('ajax-classic-wizard-boards', { action: 'list', account_id: accountId });
        if (!r.ok) { $('#cwBoards').innerHTML = `<p class="cw-help">${esc(r.error || 'Could not load boards.')}</p>`; return; }
        S.boards = r.boards;
        const valid = new Set(S.boards.map((b) => b.id));
        S.boardIds = new Set([...S.boardIds].filter((id) => valid.has(id)));
        renderBoards();
    }
    function renderBoards() {
        $('#cwBoards').innerHTML = S.boards.length ? S.boards.map((b) => `
            <label class="cw-board ${S.boardIds.has(b.id) ? 'is-on' : ''}"><input type="checkbox" value="${b.id}" ${S.boardIds.has(b.id) ? 'checked' : ''}>
            ${esc(b.name)}${b.status === 'pending_creation' ? ' <small>(created with first pin)</small>' : ''}</label>`).join('')
            : '<p class="cw-help">This account has no boards yet — create one below, or let AI choose and create boards.</p>';
    }
    async function createBoard() {
        const name = $('#cwNewBoard').value.trim();
        if (!name) { notify('Type a board name first.', 'error'); return; }
        const r = await post('ajax-classic-wizard-boards', { action: 'create', account_id: $('#cwAccount').value, name, use_ai: 1 });
        if (!r.ok) { notify(esc(r.error), 'error'); return; }
        S.boards.unshift(r.board);
        S.boardIds.add(r.board.id);
        $('#cwNewBoard').value = '';
        renderBoards();
    }
    function schedValues() {
        const pace = $('input[name="cwPace"]:checked').value;
        return {
            account_id: +$('#cwAccount').value,
            name: $('#cwName').value.trim(),
            mode: pace,
            per_day: Math.max(1, Math.min(100, +$('#cwPerDay').value || 6)),
            ramp: $$('.cw-ramp-in').map((i) => Math.max(1, Math.min(100, +i.value || 1))),
            start_date: $('#cwStartDate').value,
            start_time: $('#cwStartTime').value || '08:00',
            jitter: $('#cwJitter').checked,
            pins_per_page: Math.max(1, Math.min(10, +$('#cwPinsPerPage').value || 3)),
            page_gap_unit: $('#cwPageGapUnit').value === 'minutes' ? 'minutes' : 'days',
            page_gap_minutes: $('#cwPageGapUnit').value === 'minutes' ? Math.max(1, Math.min(525600, +$('#cwPageGap').value || 60)) : null,
            page_gap_days: $('#cwPageGapUnit').value === 'minutes' ? 1 : Math.max(1, Math.min(365, +$('#cwPageGap').value || 30)),
            no_link: $('#cwNoLink').checked,
            board_mode: $('input[name="cwBoardMode"]:checked').value,
            board_ids: [...S.boardIds],
            ai_create: $('#cwAiCreate').checked,
        };
    }
    function syncGapUnit() {
        const mins = $('#cwPageGapUnit').value === 'minutes';
        const inp = $('#cwPageGap');
        inp.max = mins ? 525600 : 365;
        if (!mins && +inp.value > 365) inp.value = 30;
        $('#cwPageGapHelp').textContent = mins
            ? 'Minutes between pins of the same page (60 = 1 hour, 1440 = 1 day).'
            : 'Recommended: 30 days — after a page\'s first pin publishes, its next pin waits a month.';
    }
    function gapText(n) {
        const mins = Math.round(1440 / n);
        if (mins % 60 === 0) return `${mins / 60} hour${mins === 60 ? '' : 's'}`;
        return mins >= 60 ? `${Math.floor(mins / 60)} h ${mins % 60} min` : `${mins} minutes`;
    }
    /** Mirrors the server's scheduler (without other runs' pins) to estimate the finish date. */
    function estimateDays(sc, pages) {
        const quota = (day) => sc.mode === 'ramp' ? sc.ramp[Math.min(4, Math.floor(day / 30))] : sc.per_day;
        const used = {}, last = {};
        let maxDay = 0;
        for (let r = 0; r < sc.pins_per_page; r++) {
            for (let p = 0; p < pages; p++) {
                const gapD = sc.page_gap_unit === 'minutes' ? Math.floor((sc.page_gap_minutes || 60) / 1440) : sc.page_gap_days;
                let d = r === 0 ? 0 : last[p] + gapD;
                while ((used[d] || 0) >= quota(d)) d++;
                used[d] = (used[d] || 0) + 1; last[p] = d; if (d > maxDay) maxDay = d;
                if (maxDay > 20000) return maxDay;
            }
        }
        return maxDay + 1;
    }
    function updateSummary() {
        const sc = schedValues();
        $('#cwGapNote').innerHTML = `A pin every <b>${gapText(sc.per_day)}</b>, starting ${esc(sc.start_time)}.`;
        const pages = S.selected.size, total = pages * sc.pins_per_page;
        if (!pages) { $('#cwSummary').innerHTML = '<p class="cw-help">Select pages in the Design step to see the plan.</p>'; return; }
        const days = estimateDays(sc, pages);
        const end = new Date((sc.start_date || B.tomorrow) + 'T00:00:00'); end.setDate(end.getDate() + days - 1);
        $('#cwSummary').innerHTML = `
            <div class="cw-sum"><b>${pages}</b><span>pages</span></div>
            <div class="cw-sum"><b>${sc.pins_per_page}</b><span>pins per page</span></div>
            <div class="cw-sum"><b>${total}</b><span>pins in total</span></div>
            <div class="cw-sum"><b>${days}</b><span>days, last pin around ${end.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}</span></div>`;
    }
    function validateSchedule(sc) {
        if (!sc.account_id) return 'Choose a Pinterest account.';
        if (sc.board_mode === 'selected' && !sc.board_ids.length) return 'Select at least one board, or choose “Let AI choose”.';
        if (sc.board_mode === 'ai' && !sc.ai_create && !S.boards.length) return 'This account has no boards. Turn on “Create a new board automatically” or create a board.';
        return null;
    }

    /* ===================== Step 3: generation ===================== */
    function buildConfig() {
        return {
            site: S.site,
            design: {
                size: S.size, layout: S.layout, singlePct: S.singlePct, aiTemplates: S.aiTemplates, selectedTpls: S.selectedTpls,
                paletteId: S.paletteId, customPalette: S.customPalette, comboIdx: S.comboIdx, customFonts: S.customFonts, fonts: S.fonts,
            },
            schedule: schedValues(),
        };
    }
    async function startGeneration() {
        if (!validateDesign()) { goto(1); return; }
        const sc = schedValues();
        const err = validateSchedule(sc);
        if (err) { notify(err, 'error'); if (S.step !== 2) goto(2); return; }
        const btn = $('#cwStartGen');
        btn.disabled = true; btn.textContent = 'Saving…';
        const pages = S.pages.filter((p) => S.selected.has(p.url)).map((p) => ({ url: p.url, title: p.title, id: p.id }));
        const r = await api('project_save', { project_id: S.projectId || '', config: buildConfig(), pages });
        btn.disabled = false; btn.textContent = 'Generate pins';
        if (!r.ok) { notify(esc(r.error), 'error'); return; }
        S.projectId = r.project_id;
        history.replaceState(null, '', 'classic-wizard?draft=' + r.project_id);
        const old = new Map(S.gen.pages.map((p) => [p.key, p]));
        S.gen.pages = r.pages.map((p) => Object.assign({ pins: [], images: [], category: 'general' }, old.get(p.key) || {}, p));
        S.gen.counter = S.gen.pages.reduce((n, p) => n + p.pins.length, 0);
        S.step = 3;
        $$('.cw-step-panel').forEach((p) => { p.hidden = +p.dataset.panel !== 3; });
        $$('.cw-stepper li').forEach((li) => { li.classList.toggle('is-active', +li.dataset.step === 3); li.classList.toggle('is-done', +li.dataset.step < 3); });
        notify('');
        renderReview();
        runQueue();
    }

    async function runQueue() {
        if (S.gen.running) return;
        S.gen.running = true; S.gen.paused = false;
        $('#cwPauseBtn').textContent = 'Pause';
        updateProgress();
        const next = () => S.gen.pages.find((p) => p.status === 'pending' && !p._busy);
        const worker = async () => {
            let p;
            while (!S.gen.paused && (p = next())) {
                p._busy = true;
                try { await processPage(p); } catch (e) { p.status = 'failed'; p.error = 'Something went wrong on this page.'; renderGroup(p); }
                p._busy = false;
                updateProgress();
            }
        };
        await Promise.all([worker(), worker()]);
        S.gen.running = false;
        updateProgress();
        if (!S.gen.pages.some((p) => p.status === 'pending')) schedulePreview();
    }

    async function processPage(p) {
        p.status = 'working'; p.error = null;
        renderGroup(p);
        const r = await api('prepare_page', { project_id: S.projectId, key: p.key });
        if (!r.ok) {
            p.status = r.skip ? 'skipped' : 'failed';
            p.error = r.error;
            renderGroup(p);
            api('page_status', { project_id: S.projectId, key: p.key, status: p.status, error: r.error });
            return;
        }
        if (r.text_credits != null) $('#cwCredits').textContent = r.text_credits;
        Object.assign(p, { title: r.title, images: r.images.map((i) => i.url), category: r.category, board: r.board, notice: r.notice });
        if (r.board && r.board.created && !S.boards.some((b) => b.id === r.board.row_id)) S.boards.push({ id: r.board.row_id, name: r.board.name, status: 'pending_creation' });
        const cfg = buildDesignCfg();
        const pageIndex = S.gen.pages.indexOf(p);
        for (let i = 0; i < r.items.length; i++) {
            if (p.pins.some((x) => x.pin_index === i)) continue; // already made before a pause/reload
            const it = r.items[i];
            const n = S.gen.counter++;
            let mode = E.pickMode(cfg.layout, cfg.singlePct, n);
            const images = E.pickImages(p.images, mode, i);
            if (images.length < 2) mode = 'single';
            const design = {
                template: E.pickTemplate(cfg.pool, r.category, cfg.ai, pageIndex, i, r.items.length, it.headline || it.title || ''),
                size: cfg.size, mode, images, page_images: p.images,
                headline: it.headline, kicker: it.kicker, cta: it.cta, website: S.site.host || hostOf(p.url),
                palette: cfg.palette, fonts: cfg.fonts, category: r.category,
            };
            const blob = await renderBlob(design);
            const saved = await api('save_pin', {
                project_id: S.projectId, page_url: p.url, page_title: p.title, pin_index: i, template_id: design.template, design,
                title: it.title, description: it.description, alt_text: it.alt_text, keywords: it.keywords, board_row_id: r.board ? r.board.row_id : '',
            }, { image: new File([blob], 'pin.jpg', { type: 'image/jpeg' }) });
            if (!saved.ok) { p.error = saved.error; continue; }
            saved.pin.localUrl = URL.createObjectURL(blob);
            p.pins.push(saved.pin);
            renderGroup(p);
        }
        p.status = p.pins.length ? 'done' : 'failed';
        if (!p.pins.length && !p.error) p.error = 'No pins could be created for this page.';
        renderGroup(p);
        api('page_status', { project_id: S.projectId, key: p.key, status: p.status, error: p.error || '' });
    }
    function buildDesignCfg() {
        return { layout: S.layout, singlePct: S.singlePct, ai: S.aiTemplates, pool: tplPool(), size: S.size, palette: palette(), fonts: fonts() };
    }

    function updateProgress() {
        const pages = S.gen.pages;
        const finished = pages.filter((p) => ['done', 'skipped', 'failed'].includes(p.status)).length;
        const pins = pages.reduce((n, p) => n + p.pins.length, 0);
        const pending = pages.filter((p) => p.status === 'pending' || p.status === 'working').length;
        $('#cwProgBar').style.width = pages.length ? (finished / pages.length * 100) + '%' : '0';
        const skipped = pages.filter((p) => p.status === 'skipped').length, failed = pages.filter((p) => p.status === 'failed').length;
        $('#cwProgText').textContent = pending
            ? (S.gen.running ? `Generating… ${finished} of ${pages.length} pages` : `Paused — ${pending} page(s) left`)
            : `All pages processed — ${pins} pins ready for review`;
        $('#cwProgSub').textContent = [`${pins} pins`, skipped ? `${skipped} skipped` : '', failed ? `${failed} failed` : ''].filter(Boolean).join(' · ');
        $('#cwApproveBtn').disabled = !!pending || !pins;
        $('#cwApproveBtn').textContent = pins ? `Approve & schedule ${pins} pins` : 'Approve & schedule now';
        $('#cwPauseBtn').hidden = !pending;
        $('#cwPauseBtn').textContent = S.gen.running ? (S.gen.paused ? 'Pausing…' : 'Pause') : 'Continue generating';
    }
    async function schedulePreview() {
        const r = await api('schedule_preview', { project_id: S.projectId });
        $('#cwSchedPreview').innerHTML = r.ok && r.count ? `When approved, ${r.count} pins publish from <b>${esc(fmtDate(r.first))}</b> to <b>${esc(fmtDate(r.last))}</b>.` : '';
    }

    /* ===================== Step 3: review list ===================== */
    const STATUS = { pending: 'Waiting', working: 'Generating…', done: 'Ready', skipped: 'Skipped', failed: 'Failed' };
    function renderReview() {
        $('#cwReviewPages').innerHTML = S.gen.pages.map((p) => `<div class="cw-group" id="g-${p.key}"></div>`).join('');
        S.gen.pages.forEach(renderGroup);
        updateProgress();
        renderPageEditor();
    }
    function pinSrc(pin) { return pin.localUrl || (BASE + pin.image_path + '?v=' + (pin.updated || '')); }
    function renderGroup(p) {
        const el = $('#g-' + p.key);
        if (!el) return;
        el.classList.toggle('is-active', S.gen.activeKey === p.key);
        const boardName = p.pins[0] && p.pins[0].board_name;
        el.innerHTML = `
            <button type="button" class="cw-group-head" data-page="${p.key}">
                <span class="cw-group-title"><b>${esc(p.title || pathOf(p.url))}</b><small>${esc(p.url)}</small></span>
                <span class="cw-status cw-status-${p.status}">${STATUS[p.status] || ''}${p.status === 'done' ? ' · ' + p.pins.length + ' pins' : ''}</span>
            </button>
            ${p.error && p.status !== 'done' ? `<p class="cw-group-msg">${esc(p.error)} ${p.status === 'failed' ? `<button type="button" class="btn-secondary btn-small" data-retry="${p.key}">Try again</button>` : ''}</p>` : ''}
            ${p.notice ? `<p class="cw-group-msg cw-notice">${esc(p.notice)}</p>` : ''}
            ${boardName ? `<p class="cw-help cw-group-board">Board: ${esc(boardName)}</p>` : ''}
            <div class="cw-pins">
                ${p.pins.map((pin) => `
                    <div class="cw-pin" data-pin="${pin.id}">
                        <button type="button" class="cw-pin-open" data-edit="${pin.id}" title="Edit pin"><img src="${esc(pinSrc(pin))}" alt="${esc(pin.alt_text || pin.title)}" loading="lazy"></button>
                        <button type="button" class="cw-pin-del" data-del="${pin.id}" aria-label="Remove pin">&times;</button>
                        <span class="cw-pin-title">${esc(pin.title)}</span>
                        <small>${esc(E.templateName(pin.template_id))}</small>
                    </div>`).join('')}
                ${p.status === 'working' ? '<div class="cw-pin cw-pin-ghost" aria-hidden="true"><span></span></div>' : ''}
            </div>`;
    }
    function findPin(id) {
        for (const p of S.gen.pages) { const pin = p.pins.find((x) => x.id === id); if (pin) return { page: p, pin }; }
        return null;
    }
    async function removePin(id) {
        const f = findPin(id);
        if (!f || !confirm('Remove this pin? It will not be scheduled.')) return false;
        const r = await api('remove_pin', { project_id: S.projectId, pin_id: id });
        if (!r.ok) { notify(esc(r.error), 'error'); return false; }
        f.page.pins = f.page.pins.filter((x) => x.id !== id);
        renderGroup(f.page); updateProgress(); schedulePreviewSoon();
        return true;
    }
    const schedulePreviewSoon = debounce(() => { if (!S.gen.running) schedulePreview(); }, 800);

    /** Re-renders a pin with a new design and saves it (text fields unchanged unless given). */
    async function saveDesign(pin, design, fields) {
        const blob = await renderBlob(design);
        const f = Object.assign({ title: pin.title, description: pin.description, alt_text: pin.alt_text, keywords: pin.keywords, board_row_id: pin.board_row_id || '' }, fields || {});
        const r = await api('save_pin', Object.assign({ project_id: S.projectId, pin_id: pin.id, template_id: design.template, design }, f), { image: new File([blob], 'pin.jpg', { type: 'image/jpeg' }) });
        if (!r.ok) { notify(esc(r.error), 'error'); return null; }
        if (pin.localUrl) URL.revokeObjectURL(pin.localUrl);
        r.pin.localUrl = URL.createObjectURL(blob);
        return r.pin;
    }

    /* ===================== Step 3: page editor (right panel) ===================== */
    function renderPageEditor() {
        const box = $('#cwPageEditor');
        const p = S.gen.pages.find((x) => x.key === S.gen.activeKey);
        if (!p) { box.innerHTML = '<p class="cw-help">Select a page on the left to change the template, colours, fonts or size of all its pins at once.</p>'; return; }
        const keep = '<option value="">Keep as is</option>';
        box.innerHTML = `
            <h3 class="cw-side-title">${esc(p.title || pathOf(p.url))}</h3>
            <p class="cw-help cw-ellipsis">${esc(p.url)}</p>
            ${!p.pins.length ? '<p class="cw-help">This page has no pins yet.</p>' : `
            <div class="form-row"><label for="peTpl">Template</label><select id="peTpl">${keep}
                <optgroup label="Your selected">${S.selectedTpls.map((id) => `<option value="${esc(id)}">${esc(E.templateName(id))}</option>`).join('')}</optgroup>
                <optgroup label="All templates">${E.TEMPLATES.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join('')}${S.customTpls.map((t) => `<option value="${esc(t.id)}">${esc(t.name)} (Canva)</option>`).join('')}</optgroup>
            </select></div>
            <div class="form-row"><label>Colours</label><div class="cw-pal-mini" id="pePal">
                <button type="button" class="is-on" data-pal="" title="Keep as is">Keep</button>
                ${E.PALETTES.map((pl) => `<button type="button" data-pal="${pl.id}" title="${esc(pl.name)}">${['bg', 'primary', 'accent'].map((k) => `<i style="background:${pl[k]}"></i>`).join('')}</button>`).join('')}
            </div></div>
            <div class="form-row"><label for="peFont">Fonts</label><select id="peFont">${keep}${E.FONT_COMBOS.map((c, i) => `<option value="${i}">${esc(c.name)} — ${esc(c.main)} / ${esc(c.secondary)}</option>`).join('')}<option value="custom">Custom (from Design step)</option></select></div>
            <div class="cw-two">
                <div class="form-row"><label for="peSize">Size</label><select id="peSize">${keep}${Object.entries(E.SIZES).map(([k, v]) => `<option value="${k}">${esc(v.label)}</option>`).join('')}</select></div>
                <div class="form-row"><label for="peMode">Photos</label><select id="peMode">${keep}<option value="single">Single image</option><option value="collage">Collage</option></select></div>
            </div>
            <button type="button" class="btn-primary cw-full" id="peApply">Apply to ${p.pins.length} pin${p.pins.length === 1 ? '' : 's'}</button>
            <p class="cw-help" id="peStatus"></p>`}`;
        let pal = '';
        const palBox = $('#pePal');
        if (palBox) palBox.onclick = (e) => {
            const b = e.target.closest('[data-pal]'); if (!b) return;
            pal = b.dataset.pal; $$('button', palBox).forEach((x) => x.classList.toggle('is-on', x === b));
        };
        const apply = $('#peApply');
        if (apply) apply.onclick = async () => {
            const tpl = $('#peTpl').value, font = $('#peFont').value, size = $('#peSize').value, mode = $('#peMode').value;
            if (!tpl && !pal && font === '' && !size && !mode) { $('#peStatus').textContent = 'Change at least one setting first.'; return; }
            apply.disabled = true;
            for (let i = 0; i < p.pins.length; i++) {
                $('#peStatus').textContent = `Updating pin ${i + 1} of ${p.pins.length}…`;
                const pin = p.pins[i];
                const d = Object.assign({}, pin.design || {});
                if (tpl) d.template = tpl;
                if (pal) d.palette = E.PALETTES.find((x) => x.id === pal);
                if (font === 'custom') d.fonts = Object.assign({}, S.fonts); else if (font !== '') d.fonts = Object.assign({}, E.FONT_COMBOS[+font]);
                if (size) d.size = size;
                const imgs = d.page_images || d.images || [];
                if (mode === 'single') { d.mode = 'single'; d.images = [d.images && d.images[0] || imgs[0]].filter(Boolean); }
                if (mode === 'collage') {
                    const list = E.pickImages(imgs, 'collage', pin.pin_index);
                    if (list.length > 1) { d.mode = 'collage'; d.images = list; }
                }
                const saved = await saveDesign(pin, d);
                if (saved) p.pins[i] = saved;
                renderGroup(p);
            }
            apply.disabled = false;
            $('#peStatus').textContent = 'Done — all pins on this page were updated.';
        };
    }

    /* ===================== Pin editor modal ===================== */
    const M = { pin: null, page: null, design: null };
    const modalRender = debounce(() => { if (M.pin) queueRender($('#cwModalCanvas'), specFrom(modalDesign(), 0.5)); }, 200);
    function modalDesign() {
        return Object.assign({}, M.design, {
            headline: $('#cwMHeadline').value, kicker: $('#cwMKicker').value, cta: $('#cwMCta').value,
        });
    }
    function openModal(id) {
        const f = findPin(id);
        if (!f) return;
        M.pin = f.pin; M.page = f.page;
        M.design = JSON.parse(JSON.stringify(f.pin.design || {}));
        if (!M.design.page_images) M.design.page_images = (f.page.images && f.page.images.length ? f.page.images : M.design.images || []).slice();
        $('#cwMHeadline').value = M.design.headline || '';
        $('#cwMKicker').value = M.design.kicker || '';
        $('#cwMCta').value = M.design.cta || '';
        $('#cwMTitle').value = f.pin.title || '';
        $('#cwMDesc').value = f.pin.description || '';
        $('#cwMAlt').value = f.pin.alt_text || '';
        $('#cwMKeywords').value = f.pin.keywords || '';
        $('#cwMBoard').innerHTML = '<option value="">Choose a board</option>' + S.boards.map((b) => `<option value="${b.id}" ${b.id === f.pin.board_row_id ? 'selected' : ''}>${esc(b.name)}</option>`).join('');
        renderModalImages();
        renderModalTemplates();
        counters();
        $('#cwModal').hidden = false;
        document.body.classList.add('cw-noscroll');
        modalRender();
        $('#cwMHeadline').focus();
    }
    function closeModal() {
        $('#cwModal').hidden = true;
        document.body.classList.remove('cw-noscroll');
        M.pin = null;
    }
    function renderModalImages() {
        const sel = M.design.images || [];
        $('#cwModalImgs').innerHTML = M.design.page_images.map((u) => {
            const i = sel.indexOf(u);
            return `<button type="button" class="${i >= 0 ? 'is-on' : ''}" data-img="${esc(u)}" aria-pressed="${i >= 0}"><img src="${esc(BASE + u)}" alt="">${i >= 0 ? `<span>${i + 1}</span>` : ''}</button>`;
        }).join('');
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
    function counters() {
        $$('.cw-counter').forEach((c) => { const el = $('#' + c.dataset.for); c.textContent = `${el.value.length}/${c.dataset.max}`; });
    }
    async function saveModal() {
        if (!M.pin) return;
        const btn = $('#cwModalSave');
        btn.disabled = true; btn.textContent = 'Saving…';
        const saved = await saveDesign(M.pin, modalDesign(), {
            title: $('#cwMTitle').value.trim(), description: $('#cwMDesc').value.trim(), alt_text: $('#cwMAlt').value.trim(),
            keywords: $('#cwMKeywords').value.trim(), board_row_id: $('#cwMBoard').value,
        });
        btn.disabled = false; btn.textContent = 'Save pin';
        if (!saved) return;
        const idx = M.page.pins.findIndex((x) => x.id === M.pin.id);
        M.page.pins[idx] = saved;
        renderGroup(M.page);
        closeModal();
    }

    /* ===================== Approve ===================== */
    async function approve() {
        const pins = S.gen.pages.reduce((n, p) => n + p.pins.length, 0);
        if (!pins || S.gen.running) return;
        if (!confirm(`Schedule ${pins} pins now? They will publish automatically at the planned times.`)) return;
        const btn = $('#cwApproveBtn');
        btn.disabled = true; btn.textContent = 'Scheduling…';
        const r = await api('approve', { project_id: S.projectId });
        if (!r.ok) {
            updateProgress();
            notify(esc(r.error), 'error');
            if (r.upgrade && window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(r.error);
            return;
        }
        window.onbeforeunload = null;
        $$('.cw-step-panel').forEach((p) => { p.hidden = true; });
        $('.cw-stepper').hidden = true;
        notify('');
        const done = $('#cwDone');
        done.hidden = false;
        done.innerHTML = `<h2>${r.count} pins scheduled</h2>
            <p>The first pin publishes <b>${esc(fmtDate(r.first))}</b> and the last one <b>${esc(fmtDate(r.last))}</b>.</p>
            ${r.published_now ? `<p><b>${r.published_now}</b> pin(s) whose time had already passed were published right away.</p>` : ''}
            ${r.failed_now ? `<p>${r.failed_now} overdue pin(s) could not be published — check the batch.</p>` : ''}
            ${r.overdue_queued ? `<p>${r.overdue_queued} more overdue pin(s) will publish within a minute.</p>` : ''}
            <div class="cw-done-actions"><a class="btn-primary" href="batch-view?batch_id=${encodeURIComponent(r.batch_id)}">View scheduled pins</a>
            <a class="btn-secondary" href="classic-wizard">Start a new schedule</a></div>`;
        history.replaceState(null, '', 'classic-wizard');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ===================== Drafts: resume ===================== */
    function applyConfigToUi(cfg) {
        const d = cfg.design || {}, sc = cfg.schedule || {};
        S.site = Object.assign(S.site, cfg.site || {});
        $('#cwSiteUrl').value = S.site.url || '';
        Object.assign(S, {
            size: d.size || S.size, layout: d.layout || S.layout, singlePct: d.singlePct != null ? d.singlePct : S.singlePct,
            aiTemplates: d.aiTemplates !== false, selectedTpls: (d.selectedTpls || []).filter((id) => E.getTemplate(id)),
            paletteId: d.paletteId || S.paletteId, customPalette: d.customPalette || S.customPalette,
            comboIdx: d.comboIdx || 0, customFonts: !!d.customFonts, fonts: d.fonts || S.fonts,
        });
        $$('input[name="cwLayout"]').forEach((i) => { i.checked = i.value === S.layout; });
        $('#cwMixRange').value = S.singlePct;
        $('#cwAiTemplates').checked = S.aiTemplates;
        $('#cwCustomFonts').checked = S.customFonts;
        $$('#cwCustomPalette input').forEach((i) => { i.value = S.customPalette[i.dataset.role]; });
        if (sc.account_id) $('#cwAccount').value = sc.account_id;
        $('#cwName').value = sc.name || '';
        $$('input[name="cwPace"]').forEach((i) => { i.checked = i.value === (sc.mode || 'fixed'); });
        if (sc.per_day) $('#cwPerDay').value = sc.per_day;
        (sc.ramp || []).forEach((v, i) => { const inp = $$('.cw-ramp-in')[i]; if (inp) inp.value = v; });
        if (sc.start_date) $('#cwStartDate').value = sc.start_date;
        if (sc.start_time) {
            const sel = $('#cwStartTime');
            if (![...sel.options].some((o) => o.value === sc.start_time)) sel.add(new Option(sc.start_time, sc.start_time));
            sel.value = sc.start_time;
        }
        $('#cwJitter').checked = sc.jitter !== false;
        if (sc.pins_per_page) $('#cwPinsPerPage').value = sc.pins_per_page;
        $('#cwPageGapUnit').value = sc.page_gap_unit === 'minutes' ? 'minutes' : 'days';
        if (sc.page_gap_unit === 'minutes' && sc.page_gap_minutes) $('#cwPageGap').value = sc.page_gap_minutes;
        else if (sc.page_gap_days) $('#cwPageGap').value = sc.page_gap_days;
        syncGapUnit();
        $('#cwNoLink').checked = !!sc.no_link;
        $$('input[name="cwBoardMode"]').forEach((i) => { i.checked = i.value === (sc.board_mode || 'selected'); });
        $('#cwAiCreate').checked = sc.ai_create !== false;
        S.boardIds = new Set((sc.board_ids || []).map(Number));
        syncUi();
    }
    async function loadDraft() {
        const r = await api('project_load', { project_id: S.projectId });
        if (!r.ok) { notify(esc(r.error), 'error'); S.projectId = null; return; }
        if (r.project.status === 'scheduled') { location.href = 'batch-view?batch_id=' + encodeURIComponent(r.project.pin_batch_id); return; }
        applyConfigToUi(r.config);
        await loadBoards();
        const byKey = {};
        r.pins.forEach((pin) => { (byKey[pin.key] = byKey[pin.key] || []).push(pin); });
        S.pages = r.pages.map((p) => ({ id: p.crawl_page_id, url: p.url, title: p.title }));
        S.selected = new Set(r.pages.map((p) => p.url));
        if (S.site.crawl_site_id) {
            const selected = new Set(S.selected), draftPages = S.pages.slice();
            await loadPages(null, true);
            draftPages.forEach((dp) => { if (!S.pages.some((p) => p.url === dp.url)) S.pages.unshift(dp); });
            S.selected = selected;
            renderPages();
        } else { $('#cwPagesBox').hidden = false; renderPages(); }
        S.gen.pages = r.pages.map((p) => {
            const pins = byKey[p.key] || [];
            const imgs = pins[0] && pins[0].design && pins[0].design.page_images;
            let status = p.status || 'pending';
            if (status === 'pending' && pins.length >= (r.config.schedule || {}).pins_per_page) status = 'done';
            return Object.assign({}, p, { status, pins, images: imgs || [] });
        });
        S.gen.counter = r.pins.length;
        S.step = 3;
        $$('.cw-step-panel').forEach((p) => { p.hidden = +p.dataset.panel !== 3; });
        $$('.cw-stepper li').forEach((li) => { li.classList.toggle('is-active', +li.dataset.step === 3); li.classList.toggle('is-done', +li.dataset.step < 3); });
        S.gen.paused = true;
        renderReview();
        const left = S.gen.pages.filter((p) => p.status === 'pending').length;
        notify(left ? `Draft opened. ${left} page(s) still need pins — click “Continue generating”.` : 'Draft opened. Review your pins, then approve to schedule them.', 'info');
        if (!left) schedulePreview();
    }

    /** Design chosen in the public Pin Maker: apply it, scan the same site and select the same pages. */
    async function applyPrefill(pf) {
        const d = pf.design || {};
        applyConfigToUi({ site: { url: (pf.site || {}).url || '' }, design: d, schedule: { pins_per_page: pf.pins_per_page || 3 } });
        notify('Your Pin Maker design is loaded — templates, colours and fonts are set. Choose your pages, then set the schedule.', 'success');
        if (S.site.url) {
            await scan();
            const want = new Set(pf.pages || []);
            if (want.size && S.pages.some((p) => want.has(p.url))) { S.selected = new Set(S.pages.filter((p) => want.has(p.url)).map((p) => p.url)); renderPages(); }
        }
    }

    /* ===================== Wiring ===================== */
    function syncUi() {
        renderSizes();
        $('#cwCustomMix').hidden = S.layout !== 'custom';
        $('#cwMixSingle').textContent = S.singlePct + '%';
        $('#cwMixCollage').textContent = (100 - S.singlePct) + '%';
        renderPalettes();
        renderCombos();
        $('#cwFontPickers').hidden = !S.customFonts;
        pickers.forEach((p) => p.paint());
        renderTemplates();
        const pace = $('input[name="cwPace"]:checked').value;
        $('#cwPaceFixed').hidden = pace !== 'fixed';
        $('#cwPaceRamp').hidden = pace !== 'ramp';
        const bm = $('input[name="cwBoardMode"]:checked').value;
        $('#cwAiCreateRow').hidden = bm !== 'ai';
        $('#cwBoardsWrap').hidden = bm === 'ai';
        updateSummary();
        refreshThumbs();
    }

    function bind() {
        $$('[data-goto]').forEach((b) => b.addEventListener('click', () => goto(+b.dataset.goto)));
        $$('[data-next]').forEach((b) => b.addEventListener('click', () => goto(+b.dataset.next)));
        $('#cwScanBtn').onclick = scan;
        $('#cwSiteUrl').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); scan(); } });
        $('#cwPageSearch').addEventListener('input', debounce((e) => { S.search = e.target.value.trim(); renderPages(); }, 150));
        $('#cwPagesList').addEventListener('change', (e) => {
            if (!e.target.matches('input[type=checkbox]')) return;
            if (e.target.checked) S.selected.add(e.target.value); else S.selected.delete(e.target.value);
            updatePageCount();
        });
        $('#cwSelectAll').onclick = () => { filteredPages().forEach((p) => S.selected.add(p.url)); renderPages(); };
        $('#cwSelectNone').onclick = () => { filteredPages().forEach((p) => S.selected.delete(p.url)); renderPages(); };
        $('#cwShuffle').onclick = () => loadPreviewPage();

        $('#cwSizes').addEventListener('change', (e) => { S.size = e.target.value; renderSizes(); refreshThumbs(); });
        $('#cwLayout').addEventListener('change', (e) => { S.layout = e.target.value; $('#cwCustomMix').hidden = S.layout !== 'custom'; refreshThumbs(); });
        $('#cwMixRange').addEventListener('input', (e) => { S.singlePct = +e.target.value; $('#cwMixSingle').textContent = S.singlePct + '%'; $('#cwMixCollage').textContent = (100 - S.singlePct) + '%'; refreshPreview(); });

        $('#cwAiTemplates').onchange = (e) => { S.aiTemplates = e.target.checked; refreshPreview(); };
        $$('.cw-tabs [data-tab]').forEach((b) => b.addEventListener('click', () => {
            S.tplTab = b.dataset.tab;
            $$('.cw-tabs [data-tab]').forEach((x) => x.classList.toggle('is-active', x === b));
            renderTemplates();
        }));
        $('#cwTplFilters').addEventListener('click', (e) => { const b = e.target.closest('[data-cat]'); if (!b) return; S.tplFilter = b.dataset.cat; renderFilters(); renderTemplates(); });
        document.addEventListener('click', async (e) => {
            const pickBtn = e.target.closest('.cw-tpl-pick');
            if (pickBtn) { toggleTemplate(pickBtn.closest('.cw-tpl').dataset.id); return; }
            const del = e.target.closest('.cw-tpl-del');
            if (del && confirm('Delete this imported design?')) {
                await api('custom_template_delete', { id: del.dataset.del });
                const id = 'c' + del.dataset.del;
                S.customTpls = S.customTpls.filter((t) => t.id !== id);
                S.selectedTpls = S.selectedTpls.filter((x) => x !== id);
                E.setCustomTemplates(S.customTpls);
                renderTemplates(); refreshPreview();
            }
        });
        $('#cwCanvaFile').onchange = (e) => uploadCanva(e.target.files[0]);

        $('#cwPalettes').addEventListener('click', (e) => {
            const b = e.target.closest('[data-pal]'); if (!b) return;
            S.paletteId = b.dataset.pal; renderPalettes(); refreshThumbs();
        });
        $('#cwCustomPalette').addEventListener('input', debounce((e) => {
            if (!e.target.dataset.role) return;
            S.customPalette[e.target.dataset.role] = e.target.value; renderPalettes(); refreshThumbs();
        }, 120));
        $('#cwCombos').addEventListener('click', (e) => {
            const b = e.target.closest('[data-combo]'); if (!b) return;
            S.comboIdx = +b.dataset.combo; S.customFonts = false; $('#cwCustomFonts').checked = false; $('#cwFontPickers').hidden = true;
            renderCombos(); E.loadFonts(fonts()).then(refreshThumbs);
        });
        $('#cwCustomFonts').onchange = (e) => {
            S.customFonts = e.target.checked;
            if (S.customFonts) { S.fonts = Object.assign({}, E.FONT_COMBOS[S.comboIdx]); pickers.forEach((p) => p.paint()); }
            $('#cwFontPickers').hidden = !S.customFonts;
            renderCombos(); refreshThumbs();
        };

        $('#cwAccount').onchange = () => { S.boardIds = new Set(); loadBoards(); };
        $$('input[name="cwPace"], input[name="cwBoardMode"]').forEach((i) => i.addEventListener('change', syncUi));
        $('.cw-schedule').addEventListener('input', debounce(updateSummary, 150));
        $('#cwPageGapUnit').addEventListener('change', () => { syncGapUnit(); updateSummary(); });
        $('#cwBoards').addEventListener('change', (e) => {
            const id = +e.target.value;
            if (e.target.checked) S.boardIds.add(id); else S.boardIds.delete(id);
            e.target.closest('.cw-board').classList.toggle('is-on', e.target.checked);
        });
        $('#cwNewBoardBtn').onclick = createBoard;
        $('#cwStartGen').onclick = startGeneration;

        $('#cwPauseBtn').onclick = () => {
            if (S.gen.running) { S.gen.paused = true; updateProgress(); }
            else runQueue();
        };
        $('#cwApproveBtn').onclick = approve;
        $('#cwReviewPages').addEventListener('click', async (e) => {
            const head = e.target.closest('[data-page]');
            if (head) { S.gen.activeKey = head.dataset.page; S.gen.pages.forEach((p) => { const g = $('#g-' + p.key); if (g) g.classList.toggle('is-active', p.key === S.gen.activeKey); }); renderPageEditor(); return; }
            const del = e.target.closest('[data-del]');
            if (del) { removePin(+del.dataset.del); return; }
            const ed = e.target.closest('[data-edit]');
            if (ed) { openModal(+ed.dataset.edit); return; }
            const retry = e.target.closest('[data-retry]');
            if (retry) { const p = S.gen.pages.find((x) => x.key === retry.dataset.retry); p.status = 'pending'; p.error = null; renderGroup(p); runQueue(); }
        });

        // Modal
        $('#cwModalClose').onclick = closeModal;
        $('#cwModalCancel').onclick = closeModal;
        $('#cwModal').addEventListener('click', (e) => { if (e.target.id === 'cwModal') closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('#cwModal').hidden) closeModal(); });
        ['cwMHeadline', 'cwMKicker', 'cwMCta'].forEach((id) => $('#' + id).addEventListener('input', modalRender));
        $('#cwModal').addEventListener('input', counters);
        $('#cwModalImgs').addEventListener('click', (e) => {
            const b = e.target.closest('[data-img]'); if (!b) return;
            let imgs = (M.design.images || []).slice();
            const u = b.dataset.img, i = imgs.indexOf(u);
            if (i >= 0) { if (imgs.length > 1) imgs.splice(i, 1); } else imgs.push(u);
            imgs = imgs.slice(0, 4);
            M.design.images = imgs;
            M.design.mode = imgs.length > 1 ? 'collage' : 'single';
            renderModalImages(); modalRender();
        });
        $('#cwModalUpload').onchange = async (e) => {
            const file = e.target.files[0]; if (!file) return;
            const r = await api('upload_image', {}, { file });
            e.target.value = '';
            if (!r.ok) { notify(esc(r.error), 'error'); return; }
            M.design.page_images.unshift(r.image.url);
            M.design.images = [r.image.url]; M.design.mode = 'single';
            renderModalImages(); modalRender();
        };
        $('#cwModalTpls').addEventListener('click', (e) => {
            const b = e.target.closest('[data-tpl]'); if (!b) return;
            M.design.template = b.dataset.tpl;
            $$('.cw-mtpl').forEach((x) => x.classList.toggle('is-on', x === b));
            modalRender();
        });
        $('#cwModalSave').onclick = saveModal;
        $('#cwModalRemove').onclick = async () => { if (M.pin && await removePin(M.pin.id)) closeModal(); };

        window.onbeforeunload = (e) => { if (S.gen.running) { e.preventDefault(); e.returnValue = ''; return ''; } };
    }

    async function init() {
        $('#cwStartDate').value = B.tomorrow;
        $('#cwStartDate').min = B.today;
        $('#cwCredits').textContent = B.textCredits;
        renderAccounts();
        renderFilters();
        initFontPickers();
        bind();
        syncUi();
        const [sample, customs, pros] = await Promise.all([api('sample'), api('custom_templates'), loadProTemplates()]);
        if (pros.length) {
            E.setProTemplates(pros.map((t) => Object.assign({}, t, { preview: BASE + t.preview })));
            E.setProRenderer(renderProOnServer);
            renderFilters();
        }
        if (sample.ok) S.sample = BASE + sample.url;
        if (customs.ok) { S.customTpls = customs.templates; E.setCustomTemplates(S.customTpls); }
        if (S.projectId) await loadDraft();
        else {
            loadBoards();
            if (B.prefill) await applyPrefill(B.prefill);
        }
        renderTemplates();
        refreshThumbs();
    }

    if ($('#cwStepper') || $('.cw-stepper')) init();
})();
