/* "Select category" picker for AI pin images.
 *
 * Usage: <input type="hidden" id="piImageCategory" data-catpick> + this script. The input becomes a
 * button; clicking opens a popup with a search box and every main category with its subcategories.
 * The input's value is the chosen category id ('' = Auto: the AI infers the category from the title),
 * and a 'change' event fires on the input whenever the choice changes.
 * Categories come from <app root>/image-categories.php (Admin → Image Categories).
 */
(function () {
    'use strict';
    var script = document.currentScript;
    var root = script ? script.src.replace(/assets\/js\/category-picker\.js.*$/, '') : '/';

    // Stylesheet next to this script, added once.
    if (!document.querySelector('link[data-catpick-css]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = root + 'assets/css/category-picker.css';
        link.setAttribute('data-catpick-css', '1');
        document.head.appendChild(link);
    }

    var data = null, loading = null, overlay = null, activeInput = null, byId = {};
    var AUTO_LABEL = '✨ Auto — AI picks from the title';

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function load() {
        if (data) return Promise.resolve(data);
        if (loading) return loading;
        loading = fetch(root + 'image-categories', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                data = (d && d.categories) || [];
                data.forEach(function (m) {
                    byId[m.id] = m.name;
                    m.subs.forEach(function (s) { byId[s.id] = m.name + ' › ' + s.name; });
                });
                return data;
            })
            .catch(function () { loading = null; return []; });
        return loading;
    }

    function setValue(input, id) {
        input.value = id ? String(id) : '';
        var wrap = input._catpick;
        var label = wrap.querySelector('.catpick-label');
        label.textContent = id ? (byId[id] || 'Category #' + id) : AUTO_LABEL;
        label.classList.toggle('is-auto', !id);
        wrap.querySelector('.catpick-clear').hidden = !id;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function highlight(text, q) {
        if (!q) return esc(text);
        var i = text.toLowerCase().indexOf(q);
        if (i === -1) return esc(text);
        return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
    }

    function render(q) {
        var list = overlay.querySelector('.catpick-list');
        var current = activeInput ? parseInt(activeInput.value, 10) || 0 : 0;
        q = (q || '').trim().toLowerCase();
        var shownMains = 0, shownSubs = 0;
        var html = q ? '' : '<button type="button" class="catpick-auto" data-id="">' + AUTO_LABEL + '</button>';
        data.forEach(function (m) {
            var mainHit = !q || m.name.toLowerCase().indexOf(q) !== -1;
            var subs = mainHit ? m.subs : m.subs.filter(function (s) { return s.name.toLowerCase().indexOf(q) !== -1; });
            if (!mainHit && !subs.length) return;
            shownMains++;
            shownSubs += subs.length;
            html += '<div class="catpick-group"><button type="button" class="catpick-main' + (current === m.id ? ' sel' : '') + '" data-id="' + m.id + '">' + highlight(m.name, q) + '</button>' +
                (subs.length ? '<div class="catpick-subs">' + subs.map(function (s) {
                    return '<button type="button" class="catpick-sub' + (current === s.id ? ' sel' : '') + '" data-id="' + s.id + '">' + highlight(s.name, q) + '</button>';
                }).join('') + '</div>' : '') + '</div>';
        });
        if (!shownMains) html += '<div class="catpick-empty">No category matches “' + esc(q) + '”.</div>';
        list.innerHTML = html;
        overlay.querySelector('.catpick-count').textContent = shownMains + ' categories · ' + shownSubs + ' subcategories';
    }

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'catpick-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Select category');
        overlay.innerHTML = '<div class="catpick-modal"><div class="catpick-head"><h2>Select category</h2>' +
            '<button type="button" class="catpick-close" aria-label="Close">✕</button></div>' +
            '<input type="search" class="catpick-search" placeholder="Search categories, e.g. nails, pasta, bedroom…" aria-label="Search categories">' +
            '<div class="catpick-count"></div><div class="catpick-list"></div></div>';
        document.body.appendChild(overlay);
        var search = overlay.querySelector('.catpick-search');
        var timer;
        search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { render(search.value); }, 120); });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = overlay.querySelector('.catpick-sub, .catpick-main');
                if (first) choose(first.getAttribute('data-id'));
            }
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.closest('.catpick-close')) return close();
            var b = e.target.closest('[data-id]');
            if (b) choose(b.getAttribute('data-id'));
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) close(); });
    }
    function choose(id) {
        if (activeInput) setValue(activeInput, id ? parseInt(id, 10) : 0);
        close();
    }
    function open(input) {
        activeInput = input;
        if (!overlay) build();
        overlay.classList.add('open');
        var search = overlay.querySelector('.catpick-search');
        search.value = '';
        overlay.querySelector('.catpick-list').innerHTML = '<div class="catpick-empty">Loading categories…</div>';
        load().then(function () { render(''); search.focus(); });
    }
    function close() {
        if (!overlay) return;
        overlay.classList.remove('open');
        if (activeInput && activeInput._catpick) activeInput._catpick.querySelector('.catpick-btn').focus();
    }

    function enhance(input) {
        if (input._catpick) return;
        var wrap = document.createElement('div');
        wrap.className = 'catpick';
        wrap.innerHTML = '<button type="button" class="catpick-btn" aria-haspopup="dialog"><span class="catpick-label is-auto">' + AUTO_LABEL +
            '</span><span class="catpick-caret">▾</span></button><button type="button" class="catpick-clear" aria-label="Clear category" hidden>✕</button>';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input._catpick = wrap;
        wrap.querySelector('.catpick-btn').addEventListener('click', function () { open(input); });
        wrap.querySelector('.catpick-clear').addEventListener('click', function () { setValue(input, 0); });
        if (input.value) load().then(function () { setValue(input, parseInt(input.value, 10)); });
    }

    function init() { document.querySelectorAll('input[data-catpick]').forEach(enhance); }
    window.CategoryPicker = { init: init, set: function (input, id) { load().then(function () { setValue(input, id); }); } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
