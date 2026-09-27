/* "Pin Templates & Styles" picker.
 *
 * Usage: <input type="hidden" id="piImageStyle" value="auto" data-tplpick> + this script.
 * The input becomes a button; clicking opens a popup with search, category filter, single/collage
 * filter, and a scrollable grid of every template (live preview image + name). Users can select
 * several templates and/or "AI Auto".
 *
 * The input's value (read by the page exactly as before):
 *   "auto"                 AI picks the best template for each pin
 *   "tpl_a"                one template
 *   "tpl_a,tpl_b,tpl_c"    one of these, varied per pin
 *   "auto,tpl_a,tpl_b"     AI picks the best fit among the selected templates
 * A 'change' event fires on the input whenever the selection changes.
 * Data: <app root>/pin-templates (includes/pin_template_registry.php).
 */
(function () {
    'use strict';
    var script = document.currentScript;
    var root = script ? script.src.replace(/assets\/js\/template-picker\.js.*$/, '') : '/';

    if (!document.querySelector('link[data-tplpick-css]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = root + 'assets/css/template-picker.css';
        link.setAttribute('data-tplpick-css', '1');
        document.head.appendChild(link);
    }

    var data = null, loading = null, byKey = {}, overlay = null, activeInput = null;
    var state = { auto: true, sel: [], q: '', cat: 'All', layout: 'all' };

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function load() {
        if (data) return Promise.resolve(data);
        if (loading) return loading;
        loading = fetch(root + 'pin-templates?t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                data = d && d.templates ? d : { categories: [], templates: [] };
                data.templates.forEach(function (t) { byKey[t.key] = t; });
                return data;
            })
            .catch(function () { loading = null; return { categories: [], templates: [] }; });
        return loading;
    }

    function parse(value) {
        var parts = String(value || 'auto').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var auto = parts.indexOf('auto') !== -1 || !parts.length;
        return { auto: auto, sel: parts.filter(function (p) { return p !== 'auto'; }) };
    }
    function serialize(auto, sel) {
        if (!sel.length) return 'auto';
        return (auto ? ['auto'] : []).concat(sel).join(',');
    }
    function labelFor(value) {
        var p = parse(value);
        if (!p.sel.length) return '✨ AI Auto — best template for each pin';
        var names = p.sel.map(function (k) { return byKey[k] ? byKey[k].name : k; });
        var txt = names.length === 1 ? names[0] : names.length + ' templates: ' + names.slice(0, 2).join(', ') + (names.length > 2 ? '…' : '');
        return (p.auto ? '✨ AI Auto from ' : '') + txt;
    }

    function setValue(input, value, silent) {
        input.value = value;
        var wrap = input._tplpick;
        if (wrap) {
            wrap.querySelector('.tplpick-label').textContent = labelFor(value);
            var thumbs = wrap.querySelector('.tplpick-thumbs');
            var p = parse(value);
            thumbs.innerHTML = p.sel.slice(0, 4).map(function (k) {
                return byKey[k] ? '<img src="' + esc(root + byKey[k].preview) + '" alt="">' : '';
            }).join('');
        }
        if (!silent) input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'tplpick-overlay';
        overlay.innerHTML =
            '<div class="tplpick-modal" role="dialog" aria-modal="true" aria-label="Pin Templates & Styles">' +
            '  <div class="tplpick-head"><h2>Pin Templates &amp; Styles</h2><button type="button" class="tplpick-close" aria-label="Close">✕</button></div>' +
            '  <div class="tplpick-tools">' +
            '    <input type="search" class="tplpick-search" placeholder="Search templates…">' +
            '    <div class="tplpick-layout"><button type="button" data-layout="all" class="on">All</button><button type="button" data-layout="single">Single photo</button><button type="button" data-layout="collage">Collage</button></div>' +
            '  </div>' +
            '  <div class="tplpick-cats"></div>' +
            '  <div class="tplpick-body">' +
            '    <label class="tplpick-autocard"><input type="checkbox" class="tplpick-auto"> <span><strong>✨ AI Auto</strong><br><small>With nothing selected, AI picks the best template for each pin. With templates selected, AI picks the best fit among them.</small></span></label>' +
            '    <div class="tplpick-grid"></div>' +
            '  </div>' +
            '  <div class="tplpick-foot"><span class="tplpick-count"></span><div><button type="button" class="tplpick-clear">Clear selection</button> <button type="button" class="tplpick-done">Done</button></div></div>' +
            '</div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        overlay.querySelector('.tplpick-close').addEventListener('click', close);
        overlay.querySelector('.tplpick-done').addEventListener('click', close);
        overlay.querySelector('.tplpick-clear').addEventListener('click', function () { state.sel = []; state.auto = true; renderGrid(); apply(); });
        overlay.querySelector('.tplpick-auto').addEventListener('change', function (e) { state.auto = e.target.checked; apply(); });
        overlay.querySelector('.tplpick-search').addEventListener('input', function (e) { state.q = e.target.value.trim().toLowerCase(); renderGrid(); });
        overlay.querySelectorAll('.tplpick-layout button').forEach(function (b) {
            b.addEventListener('click', function () {
                state.layout = b.getAttribute('data-layout');
                overlay.querySelectorAll('.tplpick-layout button').forEach(function (x) { x.classList.toggle('on', x === b); });
                renderGrid();
            });
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) close(); });
    }

    function renderCats() {
        // 49 categories: a dropdown (with counts) is easier than a row of chips; "Selected" is its own toggle.
        var box = overlay.querySelector('.tplpick-cats');
        var counts = {};
        data.templates.forEach(function (t) { counts[t.category] = (counts[t.category] || 0) + 1; });
        var opts = ['<option value="All"' + (state.cat === 'All' ? ' selected' : '') + '>All categories (' + data.templates.length + ')</option>']
            .concat(data.categories.map(function (c) {
                return '<option value="' + esc(c) + '"' + (state.cat === c ? ' selected' : '') + '>' + esc(c) + ' (' + (counts[c] || 0) + ')</option>';
            }));
        box.innerHTML = '<select class="tplpick-catsel">' + opts.join('') + '</select>' +
            '<button type="button" class="tplpick-selbtn' + (state.cat === 'Selected' ? ' on' : '') + '">✓ Selected <span>' + state.sel.length + '</span></button>';
        box.querySelector('.tplpick-catsel').addEventListener('change', function (e) { state.cat = e.target.value; renderCats(); renderGrid(); });
        box.querySelector('.tplpick-selbtn').addEventListener('click', function () { state.cat = state.cat === 'Selected' ? 'All' : 'Selected'; renderCats(); renderGrid(); });
    }

    function renderGrid() {
        var grid = overlay.querySelector('.tplpick-grid');
        var list = data.templates.filter(function (t) {
            if (state.cat === 'Selected' && state.sel.indexOf(t.key) === -1) return false;
            if (state.cat !== 'All' && state.cat !== 'Selected' && t.category !== state.cat) return false;
            if (state.layout !== 'all' && t.layout !== state.layout) return false;
            if (state.q && (t.name + ' ' + t.category + ' ' + t.layout).toLowerCase().indexOf(state.q) === -1) return false;
            return true;
        });
        grid.innerHTML = list.length ? list.map(function (t) {
            var i = state.sel.indexOf(t.key);
            return '<button type="button" class="tplpick-card' + (i !== -1 ? ' sel' : '') + '" data-key="' + esc(t.key) + '">' +
                '<span class="tplpick-img"><img loading="lazy" src="' + esc(root + t.preview) + '" alt="' + esc(t.name) + '"></span>' +
                (i !== -1 ? '<span class="tplpick-check">✓</span>' : '') +
                '<span class="tplpick-name">' + esc(t.name) + '</span>' +
                '<span class="tplpick-meta">' + esc(t.category) + ' · ' + (t.layout === 'collage' ? 'Collage · ' + (t.photos || 4) + ' photos' : 'Single photo') + '</span>' +
                '</button>';
        }).join('') : '<p class="tplpick-empty">No templates match.</p>';
        grid.querySelectorAll('.tplpick-card').forEach(function (c) {
            c.addEventListener('click', function () {
                var k = c.getAttribute('data-key');
                var i = state.sel.indexOf(k);
                // first pick after "AI Auto only": use exactly what the user picks (they can tick AI Auto again)
                if (i === -1 && !state.sel.length) state.auto = false;
                if (i === -1) state.sel.push(k); else state.sel.splice(i, 1);
                renderGrid(); renderCats(); apply();
            });
        });
        updateFoot();
    }

    function updateFoot() {
        var n = state.sel.length;
        overlay.querySelector('.tplpick-count').textContent = n ? n + ' selected' + (state.auto ? ' · AI picks the best fit among them' : ' · varied across your pins')
            : 'Nothing selected · AI Auto picks for every pin';
        var cb = overlay.querySelector('.tplpick-auto');
        cb.checked = state.auto || !n;
        cb.disabled = !n;
    }

    function apply() {
        if (!activeInput) return;
        setValue(activeInput, serialize(state.auto, state.sel));
        updateFoot();
    }

    function open(input) {
        activeInput = input;
        if (!overlay) buildOverlay();
        var p = parse(input.value);
        state.auto = p.auto; state.sel = p.sel.slice(); state.q = ''; state.cat = 'All'; state.layout = 'all';
        overlay.querySelector('.tplpick-search').value = '';
        overlay.querySelectorAll('.tplpick-layout button').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-layout') === 'all'); });
        overlay.classList.add('open');
        document.documentElement.classList.add('tplpick-lock');
        overlay.querySelector('.tplpick-grid').innerHTML = '<p class="tplpick-empty">Loading templates…</p>';
        load().then(function () { renderCats(); renderGrid(); overlay.querySelector('.tplpick-search').focus(); });
    }
    function close() {
        if (!overlay) return;
        overlay.classList.remove('open');
        document.documentElement.classList.remove('tplpick-lock');
        activeInput = null;
    }

    function enhance(input) {
        if (input._tplpick) return;
        var wrap = document.createElement('div');
        wrap.className = 'tplpick';
        wrap.innerHTML = '<button type="button" class="tplpick-btn"><span class="tplpick-thumbs"></span><span class="tplpick-label"></span><span class="tplpick-caret">▾</span></button>';
        input.parentNode.insertBefore(wrap, input.nextSibling);
        input._tplpick = wrap;
        wrap.querySelector('.tplpick-btn').addEventListener('click', function () { open(input); });
        if (!input.value) input.value = 'auto';
        wrap.querySelector('.tplpick-label').textContent = labelFor(input.value);
        load().then(function () { setValue(input, input.value, true); });
    }

    function init() { document.querySelectorAll('input[data-tplpick]').forEach(enhance); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.TemplatePicker = { init: init, set: function (input, v) { setValue(input, v, true); } };
})();
