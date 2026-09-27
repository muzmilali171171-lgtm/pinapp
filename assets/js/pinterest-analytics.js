/* Pinterest Analytics — user/pinterest-analytics.php
 * Analytics tab (stat cards + multi-metric graph with previous period), Top Pins tab
 * (top 200 list, per-pin 90-day graph, CSV export, selection) and the Regenerate Similar popup.
 */
(function () {
    'use strict';
    var CFG = window.PA_CONFIG || {};
    if (!CFG.accountId) return;

    /* ------------------------------------------------------------------ helpers */
    function $(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fmtNum(n) {
        if (n == null || isNaN(n)) return '—';
        n = Number(n);
        var a = Math.abs(n);
        if (a >= 1e6) return (n / 1e6).toFixed(a >= 1e7 ? 0 : 1).replace(/\.0$/, '') + 'M';
        if (a >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
        return String(Math.round(n));
    }
    function fmtPct(n, digits) {
        if (n == null || isNaN(n)) return '—';
        var d = digits != null ? digits : (Math.abs(n) < 0.1 && n !== 0 ? 2 : (Math.abs(n) < 10 ? 2 : 1));
        return Number(n).toFixed(d) + '%';
    }
    function shortDate(iso) {
        var d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString(undefined, { month: 'short', day: '2-digit' });
    }
    function longDate(iso) {
        var d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }
    function qs(obj) {
        return Object.keys(obj).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
    }
    function getJSON(url, params) {
        params = Object.assign({ account_id: CFG.accountId }, params || {});
        return fetch(url + '?' + qs(params), { credentials: 'same-origin' }).then(parseJSON);
    }
    function postJSON(url, params) {
        var fd = new FormData();
        params = Object.assign({ account_id: CFG.accountId, csrf_token: CFG.csrf }, params || {});
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(parseJSON);
    }
    function parseJSON(r) {
        return r.text().then(function (t) {
            try { return JSON.parse(t); } catch (e) { return { ok: false, error: 'Unexpected server response (HTTP ' + r.status + ').' }; }
        });
    }
    function setDraftCount(n) {
        CFG.draftCount = Math.max(0, n);
        var el = $('paDraftTabCount');
        if (el) { el.textContent = CFG.draftCount; if (CFG.draftCount) el.removeAttribute('data-zero'); else el.setAttribute('data-zero', ''); }
    }
    function upgradeCheck(msg) { if (window.maybeShowUpgradePopup) window.maybeShowUpgradePopup(msg, { cooldownKey: 'pa' }); }
    function loading(text) { return '<div class="pa-loading"><span class="pa-spinner"></span> ' + esc(text) + '</div>'; }

    /* ------------------------------------------------------------------ metrics */
    var METRICS = {
        impressions:         { label: 'Impressions',         color: '#f43f5e' },
        pin_clicks:          { label: 'Pin Clicks',          color: '#22c55e' },
        outbound_clicks:     { label: 'Outbound Clicks',     color: '#8b5cf6' },
        saves:               { label: 'Saves',               color: '#3b82f6' },
        outbound_click_rate: { label: 'Outbound Click Rate', color: '#a855f7', rate: 'outbound_clicks' },
        save_rate:           { label: 'Save Rate',           color: '#06b6d4', rate: 'saves' }
    };
    /** Value of a metric on one day (rates derived from counts; null = no data that day). */
    function dayValue(day, key) {
        if (!day || day.has_data === false) return null;
        var m = METRICS[key];
        if (m.rate) {
            if (day.impressions == null || day[m.rate] == null) return null;
            return day.impressions > 0 ? day[m.rate] / day.impressions * 100 : 0;
        }
        return day[key] == null ? null : Number(day[key]);
    }
    function fmtMetric(key, v) { return METRICS[key].rate ? fmtPct(v, 2) : fmtNum(v); }

    /* ------------------------------------------------------------------ chart
     * Each metric is scaled to its own peak (0–100%), so metrics with very different sizes
     * (impressions vs outbound clicks) can share one graph — the tooltip shows real values.
     * The previous period is drawn dashed in the same colour, aligned day-by-day.
     */
    var SVGNS = 'http://www.w3.org/2000/svg';
    function svgEl(tag, attrs) {
        var el = document.createElementNS(SVGNS, tag);
        for (var k in attrs) el.setAttribute(k, attrs[k]);
        return el;
    }
    function smoothPath(pts) {
        // pts: [[x,y],...] contiguous. Catmull-Rom → cubic Bézier with clamped control points.
        if (pts.length === 1) return 'M' + pts[0][0] + ',' + pts[0][1] + 'h0.01';
        var d = 'M' + pts[0][0] + ',' + pts[0][1];
        for (var i = 0; i < pts.length - 1; i++) {
            var p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
            var t = 0.18;
            var c1x = p1[0] + (p2[0] - p0[0]) * t, c1y = p1[1] + (p2[1] - p0[1]) * t;
            var c2x = p2[0] - (p3[0] - p1[0]) * t, c2y = p2[1] - (p3[1] - p1[1]) * t;
            var lo = Math.min(p1[1], p2[1]), hi = Math.max(p1[1], p2[1]);
            c1y = Math.max(lo, Math.min(hi, c1y)); c2y = Math.max(lo, Math.min(hi, c2y));
            d += 'C' + c1x.toFixed(1) + ',' + c1y.toFixed(1) + ' ' + c2x.toFixed(1) + ',' + c2y.toFixed(1) + ' ' + p2[0].toFixed(1) + ',' + p2[1].toFixed(1);
        }
        return d;
    }
    function segments(values, xAt, yAt) {
        var segs = [], cur = [];
        values.forEach(function (v, i) {
            if (v == null) { if (cur.length) segs.push(cur); cur = []; return; }
            cur.push([xAt(i), yAt(v)]);
        });
        if (cur.length) segs.push(cur);
        return segs;
    }

    /**
     * opts: { dates:[iso], prevDates:[iso]|null, series:[{key, values:[], prev:[]|null}], height }
     */
    function renderChart(host, opts) {
        host.innerHTML = '';
        var width = Math.max(320, host.clientWidth || 800);
        var height = opts.height || 360;
        var pad = { l: 52, r: 18, t: 14, b: 34 };
        var iw = width - pad.l - pad.r, ih = height - pad.t - pad.b;
        var n = opts.dates.length;
        if (!n || !opts.series.length) {
            host.innerHTML = '<div class="pa-empty">' + (opts.series.length ? 'No data for this period yet.' : 'Pick at least one metric above.') + '</div>';
            return;
        }
        var xAt = function (i) { return pad.l + (n === 1 ? iw / 2 : iw * i / (n - 1)); };

        var svg = svgEl('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': 'Metrics over time' });
        var grid = svgEl('g', { 'class': 'pa-grid' }), axis = svgEl('g', { 'class': 'pa-axis' });
        [0, 25, 50, 75, 100].forEach(function (p) {
            var y = pad.t + ih - ih * p / 100;
            grid.appendChild(svgEl('line', { x1: pad.l, x2: width - pad.r, y1: y, y2: y }));
            var tx = svgEl('text', { x: pad.l - 10, y: y + 4, 'text-anchor': 'end' });
            tx.textContent = p + '%';
            axis.appendChild(tx);
        });
        var step = Math.max(1, Math.ceil(n / Math.max(4, Math.floor(iw / 78))));
        for (var i = 0; i < n; i += step) {
            var t = svgEl('text', { x: xAt(i), y: height - 10, 'text-anchor': 'middle' });
            t.textContent = shortDate(opts.dates[i]);
            axis.appendChild(t);
        }
        svg.appendChild(grid); svg.appendChild(axis);

        var scaled = opts.series.map(function (s) {
            var all = s.values.concat(s.prev || []).filter(function (v) { return v != null; });
            var max = all.length ? Math.max.apply(null, all) : 0;
            var yAt = function (v) { return pad.t + ih - (max > 0 ? v / max : 0) * ih; };
            return { s: s, max: max, yAt: yAt };
        });

        // Previous period first (behind), then current with a faint area fill.
        scaled.forEach(function (sc) {
            if (!sc.s.prev) return;
            segments(sc.s.prev, xAt, sc.yAt).forEach(function (seg) {
                svg.appendChild(svgEl('path', { d: smoothPath(seg), fill: 'none', stroke: METRICS[sc.s.key].color, 'stroke-width': 2, 'stroke-dasharray': '4 4', opacity: 0.45 }));
            });
        });
        scaled.forEach(function (sc, idx) {
            var color = METRICS[sc.s.key].color;
            segments(sc.s.values, xAt, sc.yAt).forEach(function (seg) {
                var line = smoothPath(seg);
                if (idx === 0 && seg.length > 1) {
                    var area = line + 'L' + seg[seg.length - 1][0] + ',' + (pad.t + ih) + 'L' + seg[0][0] + ',' + (pad.t + ih) + 'Z';
                    svg.appendChild(svgEl('path', { d: area, fill: color, opacity: 0.07 }));
                }
                svg.appendChild(svgEl('path', { d: line, fill: 'none', stroke: color, 'stroke-width': 2.4, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
            });
        });

        // Hover: vertical guide + dots + tooltip with real values.
        var guide = svgEl('line', { 'class': 'pa-hover-line', y1: pad.t, y2: pad.t + ih, x1: 0, x2: 0, visibility: 'hidden' });
        svg.appendChild(guide);
        var dots = scaled.map(function (sc) {
            var c = svgEl('circle', { r: 4, fill: '#fff', stroke: METRICS[sc.s.key].color, 'stroke-width': 2, cx: 0, cy: 0, visibility: 'hidden' });
            svg.appendChild(c); return c;
        });
        var overlay = svgEl('rect', { x: pad.l, y: pad.t, width: iw, height: ih, fill: 'transparent' });
        svg.appendChild(overlay);
        host.appendChild(svg);

        var tip = document.createElement('div');
        tip.className = 'pa-tooltip';
        host.appendChild(tip);

        function show(evt) {
            var rect = svg.getBoundingClientRect();
            var x = (evt.clientX - rect.left) * (width / rect.width);
            var i = n === 1 ? 0 : Math.round((x - pad.l) / iw * (n - 1));
            i = Math.max(0, Math.min(n - 1, i));
            var gx = xAt(i);
            guide.setAttribute('x1', gx); guide.setAttribute('x2', gx); guide.setAttribute('visibility', 'visible');
            var html = '<strong>' + esc(longDate(opts.dates[i])) + '</strong>';
            var prevHtml = '';
            scaled.forEach(function (sc, k) {
                var v = sc.s.values[i];
                dots[k].setAttribute('visibility', v == null ? 'hidden' : 'visible');
                if (v != null) { dots[k].setAttribute('cx', gx); dots[k].setAttribute('cy', sc.yAt(v)); }
                html += '<div class="pa-tt-row"><span><i class="pa-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + METRICS[sc.s.key].color + '"></i>' +
                    esc(METRICS[sc.s.key].label) + '</span><b>' + (v == null ? '—' : fmtMetric(sc.s.key, v)) + '</b></div>';
                if (sc.s.prev && opts.prevDates) {
                    var pv = sc.s.prev[i];
                    prevHtml += '<div class="pa-tt-row"><span>' + esc(METRICS[sc.s.key].label) + '</span><b>' + (pv == null ? '—' : fmtMetric(sc.s.key, pv)) + '</b></div>';
                }
            });
            if (prevHtml) html += '<div class="pa-tt-prev">Previous: ' + esc(opts.prevDates[i] ? longDate(opts.prevDates[i]) : '') + prevHtml + '</div>';
            tip.innerHTML = html;
            tip.style.display = 'block';
            var hostRect = host.getBoundingClientRect();
            var left = evt.clientX - hostRect.left + 14;
            if (left + tip.offsetWidth > hostRect.width) left = evt.clientX - hostRect.left - tip.offsetWidth - 14;
            tip.style.left = Math.max(0, left) + 'px';
            tip.style.top = Math.max(0, evt.clientY - hostRect.top - 20) + 'px';
        }
        function hide() {
            tip.style.display = 'none';
            guide.setAttribute('visibility', 'hidden');
            dots.forEach(function (d) { d.setAttribute('visibility', 'hidden'); });
        }
        overlay.addEventListener('mousemove', show);
        overlay.addEventListener('mouseleave', hide);
        overlay.addEventListener('touchstart', function (e) { if (e.touches[0]) show(e.touches[0]); }, { passive: true });
    }

    function buildChips(host, keys, selected, onChange) {
        host.innerHTML = '';
        keys.forEach(function (key) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pa-chip' + (selected.indexOf(key) !== -1 ? ' on' : '');
            b.style.setProperty('--pa-c', METRICS[key].color);
            b.setAttribute('aria-pressed', selected.indexOf(key) !== -1 ? 'true' : 'false');
            b.innerHTML = '<span class="pa-dot"></span>' + esc(METRICS[key].label);
            b.addEventListener('click', function () {
                var idx = selected.indexOf(key);
                if (idx === -1) selected.push(key); else selected.splice(idx, 1);
                b.classList.toggle('on', idx === -1);
                b.setAttribute('aria-pressed', idx === -1 ? 'true' : 'false');
                onChange();
            });
            host.appendChild(b);
        });
    }

    var resizeHandlers = [];
    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { resizeHandlers.forEach(function (fn) { fn(); }); }, 150);
    });

    /* ================================================================== ANALYTICS TAB */
    function initAnalytics() {
        var chartHost = $('paMainChart');
        var selected = ['impressions', 'pin_clicks'];
        var state = { range: '30', start: '', end: '', data: null };

        function setTitle() {
            var names = selected.map(function (k) { return METRICS[k].label; });
            $('paChartTitle').textContent = (names.length ? names.join(', ') : 'Metrics') + ' over time';
            var sub = $('paChartTitle').nextElementSibling;
            if (sub) sub.textContent = 'View how your ' + (names.length ? names.join(', ').toLowerCase() : 'metrics') + ' have changed over time';
        }
        buildChips($('paMetricChips'), Object.keys(METRICS), selected, function () { setTitle(); draw(); });
        setTitle();

        function draw() {
            var d = state.data;
            if (!d) return;
            var showPrev = $('paShowPrev').checked && d.has_previous;
            renderChart(chartHost, {
                dates: d.current.map(function (x) { return x.date; }),
                prevDates: showPrev ? d.previous.map(function (x) { return x.date; }) : null,
                series: selected.map(function (key) {
                    return {
                        key: key,
                        values: d.current.map(function (day) { return dayValue(day, key); }),
                        prev: showPrev ? d.previous.map(function (day) { return dayValue(day, key); }) : null
                    };
                })
            });
        }
        resizeHandlers.push(draw);

        function setStats(d) {
            var label = 'vs. previous ' + d.range.days + 'd';
            ['impressions', 'outbound_clicks', 'saves', 'outbound_click_rate'].forEach(function (key) {
                var v = d.totals[key];
                document.querySelector('[data-stat="' + key + '"]').textContent = key === 'outbound_click_rate' ? fmtPct(v, 2) : fmtNum(v);
                var ch = d.change[key];
                var el = document.querySelector('[data-change="' + key + '"]');
                if (ch == null) {
                    el.innerHTML = d.has_previous ? '<span class="pa-flat">New</span> ' + esc(label) : '<span class="muted">No earlier data to compare yet</span>';
                    return;
                }
                var cls = ch > 0 ? 'pa-up' : (ch < 0 ? 'pa-down' : 'pa-flat');
                var arrow = ch > 0 ? '↑' : (ch < 0 ? '↓' : '');
                var txt = key === 'outbound_click_rate' ? Math.abs(ch).toFixed(2) + '%' : Math.abs(ch).toFixed(1) + '%';
                el.innerHTML = '<span class="' + cls + '">' + arrow + ' ' + txt + '</span> ' + esc(label);
            });
        }

        function load(refresh) {
            chartHost.innerHTML = loading('Loading your analytics…');
            var params = { range: state.range };
            if (state.range === 'custom') { params.start = state.start; params.end = state.end; }
            if (refresh) params.refresh = 1;
            getJSON('ajax-pa-analytics', params).then(function (d) {
                if (!d.ok) {
                    chartHost.innerHTML = '<div class="pa-empty">' + esc(d.error || 'Could not load analytics.') + '</div>';
                    return;
                }
                state.data = d;
                setStats(d);
                var prevToggle = $('paShowPrev');
                prevToggle.disabled = !d.has_previous;
                prevToggle.parentElement.title = d.has_previous ? '' : 'Pinterest only keeps 90 days of data — the previous period fills in as your history builds up here.';
                $('paSyncedAt').textContent = (d.synced_at ? ' Last updated ' + d.synced_at + '.' : '') + (d.warning ? ' ' + d.warning : '');
                draw();
            }).catch(function () {
                chartHost.innerHTML = '<div class="pa-empty">Network error — please try again.</div>';
            });
        }

        document.querySelectorAll('.pa-range').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pa-range').forEach(function (b) { b.classList.toggle('on', b === btn); });
                var r = btn.getAttribute('data-range');
                $('paCustom').classList.toggle('open', r === 'custom');
                if (r === 'custom') {
                    if (!$('paEnd').value) $('paEnd').value = new Date().toISOString().slice(0, 10);
                    if (!$('paStart').value) $('paStart').value = new Date(Date.now() - 29 * 864e5).toISOString().slice(0, 10);
                    return;
                }
                state.range = r;
                load(false);
            });
        });
        $('paCustomApply').addEventListener('click', function () {
            if (!$('paStart').value || !$('paEnd').value) return;
            state.range = 'custom'; state.start = $('paStart').value; state.end = $('paEnd').value;
            load(false);
        });
        $('paShowPrev').addEventListener('change', draw);
        load(false);
    }

    /* ================================================================== PIN SYNC (shared)
     * Reads the account's pins page by page into the local cache, then merges Pinterest's
     * top-pins report. Used by Top Pins, Trends and Delete Underperforming Pins.
     */
    function syncPins(progress) {
        return new Promise(function (resolve) {
            var total = 0;
            var netErr = function () { resolve({ ok: false, error: 'Network error while syncing — please try again.' }); };
            function step(page, bookmark) {
                postJSON('ajax-pa-sync', { step: 'pins', page: page, bookmark: bookmark || '' }).then(function (d) {
                    if (!d.ok) return resolve({ ok: false, error: d.error });
                    total += d.count || 0;
                    progress('Syncing pins from Pinterest… ' + total + ' pins read');
                    if (d.bookmark) return step(page + 1, d.bookmark);
                    progress('Merging Pinterest\'s top-pin report…');
                    postJSON('ajax-pa-sync', { step: 'finish' }).then(function (f) {
                        resolve(f.ok ? { ok: true } : { ok: false, error: f.error });
                    }).catch(netErr);
                }).catch(netErr);
            }
            progress('Syncing pins from Pinterest…');
            step(1, '');
        });
    }

    /* ================================================================== TOP PINS TAB */
    var TP = { pins: [], selected: {}, sort: 'impressions', ownOnly: false, regenOnly: false, syncing: false };

    function tpVisible() {
        return TP.pins.filter(function (p) { return !TP.regenOnly || p.regen_count > 0; });
    }
    function pinById(id) {
        for (var i = 0; i < TP.pins.length; i++) if (TP.pins[i].pin_id === id) return TP.pins[i];
        return null;
    }

    function initTopPins() {
        $('paSort').addEventListener('change', function () { TP.sort = this.value; loadTopPins(); });
        $('paOwnOnly').addEventListener('change', function () { TP.ownOnly = this.checked; loadTopPins(); });
        $('paRegenOnly').addEventListener('change', function () { TP.regenOnly = this.checked; renderPins(); });
        $('paResync').addEventListener('click', function () { runSync(true); });
        $('paExport').addEventListener('click', function (e) {
            e.preventDefault();
            window.location = 'ajax-pa-export?' + qs({ account_id: CFG.accountId, sort: TP.sort, own_only: TP.ownOnly ? 1 : 0 });
        });
        $('paSelectAll').addEventListener('change', function () {
            var on = this.checked;
            TP.selected = {};
            if (on) tpVisible().forEach(function (p) { TP.selected[p.pin_id] = true; });
            syncChecks();
        });
        $('paSelectTop').addEventListener('change', function () {
            var v = this.value;
            this.value = '';
            if (!v) return;
            TP.selected = {};
            var list = tpVisible();
            var n = v === 'all' ? list.length : (v === 'none' ? 0 : parseInt(v, 10));
            list.slice(0, n).forEach(function (p) { TP.selected[p.pin_id] = true; });
            syncChecks();
        });
        $('paBulkRegen').addEventListener('click', function () {
            var ids = tpVisible().filter(function (p) { return TP.selected[p.pin_id]; });
            if (ids.length) Regen.open(ids);
        });
        loadTopPins();
    }

    function loadTopPins(afterSync) {
        var list = $('paPinList');
        if (!TP.pins.length) list.innerHTML = loading('Loading your top pins…');
        getJSON('ajax-pa-top-pins', { sort: TP.sort, own_only: TP.ownOnly ? 1 : 0 }).then(function (d) {
            if (!d.ok) { list.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
            TP.pins = d.pins;
            var keep = {};
            TP.pins.forEach(function (p) { if (TP.selected[p.pin_id]) keep[p.pin_id] = true; });
            TP.selected = keep;
            $('paSyncMeta').textContent = d.synced_at
                ? 'Pins last synced ' + d.synced_at + ' · ' + TP.pins.length + ' shown'
                : 'Not synced yet';
            if (d.never_synced && !afterSync) { runSync(false); return; }
            renderPins();
            if (d.needs_sync && !afterSync && !TP.syncing) runSync(false, true);
        }).catch(function () { list.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
    }

    /** Sync the pin cache page by page. background=true keeps the current list on screen. */
    function runSync(force, background) {
        if (TP.syncing) return;
        TP.syncing = true;
        var list = $('paPinList');
        var btn = $('paResync');
        btn.disabled = true;
        syncPins(function (msg) {
            if (background) $('paSyncMeta').textContent = msg;
            else list.innerHTML = loading(msg);
        }).then(function (r) {
            TP.syncing = false; btn.disabled = false;
            if (r.ok) return loadTopPins(true);
            if (background && TP.pins.length) { $('paSyncMeta').textContent = 'Sync failed: ' + r.error; renderPins(); }
            else list.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>';
        });
    }

    function renderPins() {
        var list = $('paPinList');
        var pins = tpVisible();
        var regenTotal = TP.pins.filter(function (p) { return p.regen_count > 0; }).length;
        $('paRegenCount').textContent = regenTotal;
        if (!pins.length) {
            list.innerHTML = '<div class="pa-empty">' + (TP.regenOnly ? 'None of these pins has been regenerated yet.' : 'No pins with analytics yet. New pins usually show data after 2-3 days.') + '</div>';
            syncChecks();
            return;
        }
        var sortKey = { impressions: 'impressions', clicks: 'clicks', outbound: 'outbound', saves: 'saves', ctr: 'outbound' }[TP.sort];
        list.innerHTML = pins.map(function (p, i) {
            var thumb = p.image_url ? '<img class="pa-pin-thumb" src="' + esc(p.image_url) + '" alt="" loading="lazy" onerror="this.removeAttribute(\'src\');this.style.visibility=\'hidden\'">' : '<div class="pa-pin-thumb"></div>';
            var saveRate = p.save_rate == null ? '' : '<small>(' + fmtPct(p.save_rate) + ')</small>';
            var tags = '';
            if (p.regen_count > 0) tags += '<span class="pa-tag pa-tag-regen">Regenerated ×' + p.regen_count + '</span>';
            if (!p.is_own) tags += '<span class="pa-tag pa-tag-ext" title="Seen in Pinterest\'s top-pins report, not on your own boards">Not your board</span>';
            function metric(key, label, val, extra) {
                return '<div class="pa-metric' + (sortKey === key ? ' sorted' : '') + '"><span class="pa-metric-label">' + label + '</span><b>' + val + '</b>' + (extra || '') + '</div>';
            }
            return '<div class="pa-pin" data-pin="' + esc(p.pin_id) + '">' +
                '<div class="pa-pin-row">' +
                    '<input type="checkbox" class="pa-row-check" aria-label="Select pin"' + (TP.selected[p.pin_id] ? ' checked' : '') + '>' +
                    thumb +
                    '<div style="min-width:0;">' +
                        '<div class="pa-pin-title" title="' + esc(p.title) + '">' + esc(p.title || 'Untitled pin') + '</div>' +
                        '<div class="pa-pin-sub"><span class="pa-rank">#' + (i + 1) + '</span>' + (p.created_at ? 'Created at ' + esc(p.created_at) : '') + tags + '</div>' +
                        '<div class="pa-metrics-compact">👁 ' + fmtNum(p.impressions) + ' · 👆 ' + fmtNum(p.clicks) + ' · ↗ ' + fmtNum(p.outbound) + ' · 🔖 ' + fmtNum(p.saves) + '</div>' +
                    '</div>' +
                    metric('impressions', '👁 Impressions', fmtNum(p.impressions)) +
                    metric('clicks', '👆 Pin clicks', fmtNum(p.clicks)) +
                    metric('outbound', '↗ Outbound', fmtNum(p.outbound), '<small>(' + fmtPct(p.ctr) + ')</small>') +
                    metric('saves', '🔖 Saves', fmtNum(p.saves), saveRate) +
                    '<div class="pa-pin-actions">' +
                        '<button type="button" class="btn-secondary btn-small" data-act="regen">Regenerate</button>' +
                        '<a class="pa-icon-btn" href="' + esc(p.url) + '" target="_blank" rel="noopener" title="View on Pinterest" aria-label="View on Pinterest">↗</a>' +
                        '<button type="button" class="pa-icon-btn" data-act="toggle" aria-expanded="false" aria-label="Show pin details"><span class="pa-caret">▾</span></button>' +
                    '</div>' +
                '</div>' +
                '<div class="pa-pin-detail"></div>' +
            '</div>';
        }).join('');

        list.querySelectorAll('.pa-pin').forEach(function (row) {
            var id = row.getAttribute('data-pin');
            row.querySelector('.pa-row-check').addEventListener('change', function () {
                if (this.checked) TP.selected[id] = true; else delete TP.selected[id];
                syncChecks();
            });
            row.querySelector('[data-act="regen"]').addEventListener('click', function () { Regen.open([pinById(id)]); });
            row.querySelector('[data-act="toggle"]').addEventListener('click', function () {
                var open = !row.classList.contains('open');
                row.classList.toggle('open', open);
                this.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) renderPinDetail(row, pinById(id));
            });
        });
        syncChecks();
    }

    function syncChecks() {
        var count = Object.keys(TP.selected).length;
        $('paSelCount').textContent = count;
        $('paBulkRegen').disabled = count === 0;
        var visible = tpVisible();
        $('paSelectAll').checked = visible.length > 0 && visible.every(function (p) { return TP.selected[p.pin_id]; });
        document.querySelectorAll('#paPinList .pa-pin').forEach(function (row) {
            row.querySelector('.pa-row-check').checked = !!TP.selected[row.getAttribute('data-pin')];
        });
    }

    function renderPinDetail(row, p) {
        var box = row.querySelector('.pa-pin-detail');
        if (box.getAttribute('data-loaded')) return;
        box.setAttribute('data-loaded', '1');
        function v(n) { return n == null ? '—' : fmtNum(n); }
        box.innerHTML =
            '<div class="pa-life-grid">' +
                '<div><h4>Lifetime Stats</h4><dl>' +
                    '<div><dt>Total Impressions:</dt><dd>' + v(p.life.impressions) + '</dd></div>' +
                    '<div><dt>Total Clicks:</dt><dd>' + v(p.life.clicks) + '</dd></div>' +
                    '<div><dt>Total Outbound Clicks:</dt><dd>' + v(p.life.outbound) + '</dd></div>' +
                    '<div><dt>Total Saves:</dt><dd>' + v(p.life.saves) + '</dd></div>' +
                '</dl></div>' +
                '<div><h4>Last 90 Days</h4><dl>' +
                    '<div><dt>Impressions:</dt><dd>' + v(p.impressions) + '</dd></div>' +
                    '<div><dt>Clicks:</dt><dd>' + v(p.clicks) + '</dd></div>' +
                    '<div><dt>Outbound Clicks:</dt><dd>' + v(p.outbound) + '</dd></div>' +
                    '<div><dt>Saves:</dt><dd>' + v(p.saves) + '</dd></div>' +
                '</dl></div>' +
            '</div>' +
            '<h3>Performance over last 90 days</h3><div class="muted" style="font-size:13px;">Last few days are still processing</div>' +
            '<div class="pa-chips"></div><div class="pa-chart">' + loading('Loading pin performance…') + '</div>';

        var chartHost = box.querySelector('.pa-chart');
        var selected = ['impressions', 'outbound_clicks'];
        var days = null;
        function draw() {
            if (!days || !row.classList.contains('open')) return;
            renderChart(chartHost, {
                height: 260,
                dates: days.map(function (d) { return d.date; }),
                prevDates: null,
                series: selected.map(function (key) {
                    return { key: key, values: days.map(function (d) { return dayValue(d, key); }), prev: null };
                })
            });
        }
        buildChips(box.querySelector('.pa-chips'), ['impressions', 'pin_clicks', 'saves', 'outbound_clicks', 'outbound_click_rate'], selected, draw);
        resizeHandlers.push(draw);

        if (!p.is_own) {
            chartHost.innerHTML = '<div class="pa-empty">Pinterest only shares daily graphs for pins on your own boards.</div>';
            return;
        }
        getJSON('ajax-pa-pin-analytics', { pin_id: p.pin_id }).then(function (d) {
            if (!d.ok) { chartHost.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; box.removeAttribute('data-loaded'); return; }
            days = d.days;
            if (!days.length) { chartHost.innerHTML = '<div class="pa-empty">No daily data for this pin in the last 90 days.</div>'; return; }
            draw();
        }).catch(function () { chartHost.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; box.removeAttribute('data-loaded'); });
    }

    /* ================================================================== REGENERATE POPUP */
    var Regen = (function () {
        var Q = [], qi = 0, boards = null, busy = false, onCloseCb = null;
        var modal = $('paRegenModal');
        if (!modal) return { open: function () {} };

        function hostOf(url) {
            try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return ''; }
        }
        function localInputValue(date) {
            var d = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
            return d.toISOString().slice(0, 16);
        }
        function creditsLine() {
            $('paImgCredits').textContent = Number(CFG.imageCredits).toFixed(1);
            $('paTxtCredits').textContent = Number(CFG.textCredits).toFixed(1);
        }
        function imageCost() {
            var per = CFG.qualityCosts[$('paQuality').value] || 0;
            var n = $('paImageType').value === 'collage' ? parseInt($('paCollageCount').value, 10) : 1;
            return per * n;
        }
        function isKw(it) { return !!it && it.pin && it.pin.origin === 'keyword'; }
        function textBtnLabel() {
            var kw = isKw(Q[qi]);
            return (kw ? '✍️ Generate Title, Description, Alt & Keywords' : '✍️ Regenerate Text') + (CFG.textCost > 0 ? ' (' + CFG.textCost + ' credit)' : '');
        }
        function updateImageCost() {
            $('paImgCost').textContent = 'Costs ' + imageCost().toFixed(2) + ' image credits per pin';
            if (Q.length) $('paRegenTextBtn').textContent = textBtnLabel();
        }
        function isReady(item) {
            if (isKw(item) && !item.link.trim()) return false; // keyword pins need the page they promote
            return item.title.trim() !== '' && (item.imageMode === 'original' ? !!item.pin.image_url : !!item.imagePath);
        }
        function stateLabel(item) {
            if (item.status === 'done') return ['done', item.doneLabel || 'Scheduled'];
            if (item.status === 'busy') return ['busy', 'Working…'];
            if (item.status === 'error') return ['error', 'Error'];
            if (isKw(item) && !item.link.trim()) return ['', 'Needs page link'];
            return isReady(item) ? ['ready', 'Ready'] : ['', item.textDone ? 'Needs image' : 'Not started'];
        }

        function paletteJSON() {
            if (!$('paPaletteEnabled').checked) return '{}';
            var colors = [$('paColor1').value, $('paColor2').value, $('paColor3').value];
            if ($('paPaletteCount').value === '4') colors.push($('paColor4').value);
            return JSON.stringify({
                enabled: true, colors: colors,
                website_text_color: $('paWebsiteTextColor').value, website_bg_color: $('paWebsiteBgColor').value,
                cta_text_color: $('paCtaTextColor').value, cta_bg_color: $('paCtaBgColor').value
            });
        }

        /* ---------- render current item into the form ---------- */
        function saveFields() {
            var it = Q[qi];
            if (!it) return;
            it.title = $('paTitle').value;
            it.desc = $('paDesc').value;
            it.link = $('paLink').value;
            it.alt = $('paAlt').value;
            it.board = $('paBoard').value;
            it.newBoard = $('paNewBoard').value;
        }
        function counters() {
            var t = $('paTitle').value.length, d = $('paDesc').value.length;
            $('paTitleCount').textContent = t + '/100'; $('paTitleCount').classList.toggle('over', t > 100);
            $('paDescCount').textContent = d + '/500'; $('paDescCount').classList.toggle('over', d > 500);
        }
        function renderNewImage(it) {
            var wrap = $('paNewImgWrap');
            if (it.imageMode === 'original') {
                wrap.innerHTML = it.pin.image_url ? '<img src="' + esc(it.pin.image_url) + '" alt="New pin (original image)">' : '<div class="pa-preview-empty">No original image</div>';
            } else if (it.imagePath) {
                wrap.innerHTML = '<img src="../' + esc(it.imagePath) + '" alt="New pin image">';
            } else {
                wrap.innerHTML = '<div class="pa-preview-empty">New image appears here</div>';
            }
        }
        function renderQueue() {
            var multi = Q.length > 1;
            $('paQueueNav').hidden = !multi;
            $('paQueueList').hidden = !multi;
            $('paAutoAll').hidden = !multi;
            $('paIntervalWrap').hidden = !multi;
            $('paRegenTitle').textContent = isKw(Q[0]) ? (multi ? 'Create Pins from Keywords' : 'Create Pin from Keyword') : (multi ? 'Regenerate Similar Pins' : 'Regenerate Similar Pin');
            $('paQPos').textContent = qi + 1;
            $('paQTotal').textContent = Q.length;
            if (multi) {
                $('paQueueList').innerHTML = Q.map(function (it, i) {
                    var st = stateLabel(it);
                    return '<div class="pa-queue-item' + (i === qi ? ' current' : '') + '" data-i="' + i + '">' +
                        (it.pin.image_url ? '<img src="' + esc(it.pin.image_url) + '" alt="" onerror="this.style.visibility=\'hidden\'">' : '') +
                        '<span class="pa-q-title">' + esc(it.title || it.pin.title || 'Untitled') + '</span>' +
                        '<span class="pa-q-state ' + st[0] + '">' + esc(st[1]) + '</span></div>';
                }).join('');
                $('paQueueList').querySelectorAll('.pa-queue-item').forEach(function (el) {
                    el.addEventListener('click', function () { go(parseInt(el.getAttribute('data-i'), 10)); });
                });
            }
            updateSubmitLabel();
        }
        function renderItem() {
            var it = Q[qi];
            var kw = isKw(it);
            $('paOrigImg').hidden = kw;
            $('paOrigKw').hidden = !kw;
            $('paOrigCap').textContent = kw ? 'Keyword' : 'Original';
            $('paImgSeg').hidden = kw;
            if (kw && it.imageMode !== 'ai') it.imageMode = 'ai';
            $('paLinkLabel').textContent = kw ? 'Page link (required)' : 'Destination link';
            $('paTitle').placeholder = kw ? 'Click "Generate Title, Description…" or type a title' : '';
            $('paRegenTextBtn').textContent = textBtnLabel();
            if (kw) {
                $('paOrigKw').textContent = it.pin.keyword;
                var g = function (label, v) { return v == null ? '' : label + ' ' + (v > 0 ? '+' : '') + v + '%'; };
                $('paOrigMeta').innerHTML = '<b>Keyword: ' + esc(it.pin.keyword) + '</b>' +
                    ([g('Month', it.pin.mom), g('Year', it.pin.yoy)].filter(Boolean).length ? '<br>Growth: ' + esc([g('Month', it.pin.mom), g('Year', it.pin.yoy)].filter(Boolean).join(' · ')) : '');
            } else {
                $('paOrigImg').style.visibility = 'visible';
                $('paOrigImg').onerror = function () { this.style.visibility = 'hidden'; };
                $('paOrigImg').src = it.pin.image_url || '';
                $('paOrigMeta').innerHTML =
                    '<b>' + esc(it.pin.title || 'Untitled pin') + '</b><br>' +
                    '90 days: ' + fmtNum(it.pin.impressions) + ' impressions · ' + fmtNum(it.pin.outbound) + ' outbound · ' + fmtNum(it.pin.saves) + ' saves' +
                    (it.pin.link ? '<br>Link: <a href="' + esc(it.pin.link) + '" target="_blank" rel="noopener">' + esc(it.pin.link) + '</a>' : '');
            }
            $('paTitle').value = it.title;
            $('paDesc').value = it.desc;
            $('paLink').value = it.link;
            $('paAlt').value = it.alt;
            if (boards) {
                $('paBoard').value = it.board;
                $('paNewBoard').value = it.newBoard || '';
                $('paNewBoard').style.display = it.board === '__new__' ? 'block' : 'none';
            }
            $('paTextStatus').textContent = it.textMsg || ''; $('paTextStatus').className = 'pa-inline-status' + (it.textMsgCls ? ' ' + it.textMsgCls : '');
            $('paImgStatus').textContent = it.imgMsg || ''; $('paImgStatus').className = 'pa-inline-status' + (it.imgMsgCls ? ' ' + it.imgMsgCls : '');
            if (!$('paWebsite').dataset.touched) $('paWebsite').value = hostOf(it.link || it.pin.link);
            counters();
            document.querySelectorAll('[data-imgmode]').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-imgmode') === it.imageMode); });
            $('paImgSettings').classList.toggle('hidden', it.imageMode === 'original');
            $('paKeepNote').style.display = it.imageMode === 'original' ? 'block' : 'none';
            renderNewImage(it);
            renderQueue();
        }
        function go(i) {
            if (i < 0 || i >= Q.length) return;
            saveFields();
            qi = i;
            renderItem();
        }

        /* ---------- boards ---------- */
        function loadBoards() {
            var sel = $('paBoard');
            if (boards) { fillBoards(); return; }
            sel.innerHTML = '<option value="">Loading boards…</option>';
            getJSON('ajax-pa-boards').then(function (d) {
                if (!d.ok) { sel.innerHTML = '<option value="">' + esc(d.error) + '</option>'; return; }
                boards = d.boards;
                fillBoards();
            });
        }
        function fillBoards() {
            var sel = $('paBoard');
            sel.innerHTML = '<option value="">— Choose a board —</option>' + boards.map(function (b) {
                return '<option value="' + esc(b.value) + '">' + esc(b.name) + (b.status === 'pending_creation' ? ' (will be created)' : '') + '</option>';
            }).join('') + '<option value="__new__">+ Create a new board…</option>';
            Q.forEach(function (it) {
                if (it.board) return;
                var match = boards.filter(function (b) { return b.board_id && b.board_id === it.pin.board_id; })[0];
                it.board = match ? match.value : '';
            });
            sel.value = Q[qi].board;
            $('paNewBoard').style.display = sel.value === '__new__' ? 'block' : 'none';
        }

        /* ---------- AI text / image ---------- */
        function regenText(i) {
            var it = Q[i];
            if (!CFG.hasPinAi) return Promise.resolve({ ok: false, error: 'AI writing isn\'t set up yet — ask the site admin to choose a model for the Bulk Pin Scheduler.' });
            it.status = 'busy'; renderQueue();
            return postJSON('ajax-pa-regenerate-text', {
                title: it.title || it.pin.title || '', description: it.desc || it.pin.description || '', link: it.link,
                mode: isKw(it) ? 'keyword' : '', keyword: it.pin.keyword || '',
                custom_prompt: $('paTextPrompt').value, with_tags: $('paWithTags').checked ? 1 : 0
            }).then(function (d) {
                it.status = 'idle';
                if (!d.ok) { it.textMsg = d.error; it.textMsgCls = 'err'; upgradeCheck(d.error); renderQueue(); return d; }
                it.title = d.title; it.desc = d.description; it.alt = d.alt_text || it.alt; it.keywords = d.keywords || '';
                it.textDone = true; it.textMsg = 'New title and description written — saved to Regen Draft until you publish or schedule it.'; it.textMsgCls = 'ok';
                saveDraft(it);
                if (d.remaining_text_credits != null) CFG.textCredits = d.remaining_text_credits;
                creditsLine(); renderQueue();
                return d;
            }).catch(function () { it.status = 'idle'; it.textMsg = 'Network error — please try again.'; it.textMsgCls = 'err'; renderQueue(); return { ok: false }; });
        }
        function regenImage(i) {
            var it = Q[i];
            var input = (it.title || it.pin.title || it.pin.keyword || '').trim();
            if (!input) return Promise.resolve({ ok: false, error: 'Add a title first — the image is designed around it.' });
            it.status = 'busy'; it.imgMsg = 'Designing a new pin image… this can take up to a minute.'; it.imgMsgCls = ''; renderQueue();
            if (i === qi) { $('paImgStatus').textContent = it.imgMsg; $('paImgStatus').className = 'pa-inline-status'; }
            var fd = new FormData();
            fd.append('input', input);
            fd.append('size', $('paSize').value);
            fd.append('website', $('paWebsite').value.trim());
            fd.append('cta_mode', $('paCtaMode').value);
            fd.append('cta_text', $('paCtaText').value.trim());
            fd.append('custom_prompt', $('paImgPrompt').value.trim());
            fd.append('image_type', $('paImageType').value);
            fd.append('collage_count', $('paCollageCount').value);
            fd.append('quality', $('paQuality').value);
            fd.append('image_style', $('paImageStyle').value);
            fd.append('color_palette', paletteJSON());
            fd.append('image_category_id', $('paImageCategory') ? $('paImageCategory').value : '');
            return fetch('ajax-generate-pin-image', { method: 'POST', body: fd, credentials: 'same-origin' }).then(parseJSON).then(function (d) {
                it.status = 'idle';
                if (!d.ok) { it.imgMsg = d.error || 'Image generation failed.'; it.imgMsgCls = 'err'; upgradeCheck(it.imgMsg); }
                else {
                    it.imagePath = d.path; it.imgMsg = 'New pin image ready — saved to Regen Draft until you publish or schedule it.'; it.imgMsgCls = 'ok';
                    saveDraft(it);
                    if (d.remaining_credits != null) CFG.imageCredits = d.remaining_credits;
                    creditsLine();
                }
                if (i === qi) renderItem(); else renderQueue();
                return d;
            }).catch(function () { it.status = 'idle'; it.imgMsg = 'Network error — please try again.'; it.imgMsgCls = 'err'; if (i === qi) renderItem(); return { ok: false }; });
        }
        function withBusy(btn, fn) {
            if (busy) return;
            busy = true; btn.disabled = true;
            saveFields();
            fn().then(function () { busy = false; btn.disabled = false; if (Q.length) renderItem(); });
        }

        /* ---------- publish / schedule ---------- */
        function whenMode() { return document.querySelector('input[name="paWhen"]:checked').value; }
        function updateSubmitLabel() {
            var n = Q.filter(function (it) { return it.status !== 'done'; }).length;
            var verb = whenMode() === 'publish' ? 'Publish' : 'Schedule';
            $('paSubmit').textContent = Q.length > 1 ? verb + ' ' + n + ' Pin' + (n === 1 ? '' : 's') : verb + ' Pin';
            $('paSubmit').disabled = n === 0;
            $('paScheduleWrap').style.display = whenMode() === 'schedule' ? 'flex' : 'none';
        }
        function submitAll() {
            if (busy) return;
            saveFields();
            var mode = whenMode();
            var base = mode === 'schedule' ? new Date($('paPublishAt').value) : null;
            if (mode === 'schedule' && (!$('paPublishAt').value || isNaN(base.getTime()))) { alert('Please pick a date and time.'); return; }
            var interval = Math.max(0, parseInt($('paInterval').value, 10) || 0);
            var todo = [];
            Q.forEach(function (it, i) { if (it.status !== 'done') todo.push(i); });
            var notReady = todo.filter(function (i) { return !isReady(Q[i]) || !Q[i].board; });
            if (notReady.length === todo.length) {
                var first = Q[notReady[0]];
                alert(isKw(first) && !first.link.trim() ? 'Please add the page link for this pin.' : !first.board ? 'Please choose a board.' : (first.imageMode === 'ai' && !first.imagePath ? 'Generate the new pin image first (Regenerate Pin Image), or choose "Keep original image".' : 'Please add a title.'));
                go(notReady[0]);
                return;
            }
            if (notReady.length && !confirm(notReady.length + ' pin(s) aren\'t ready yet (missing title, image or board) and will be skipped. Continue?')) return;

            busy = true; $('paSubmit').disabled = true;
            var slot = 0;
            var ready = todo.filter(function (i) { return notReady.indexOf(i) === -1; });
            (function next(k) {
                if (k >= ready.length) {
                    busy = false;
                    renderItem();
                    var done = Q.filter(function (it) { return it.status === 'done'; }).length;
                    var failedItems = Q.filter(function (it) { return it.status === 'error'; });
                    if (Q.length === 1 && failedItems.length) return; // the pin's own error is already on screen
                    $('paImgStatus').innerHTML = done + ' pin(s) sent to <a href="schedule-list">Scheduled Pins</a>' +
                        (failedItems.length ? '. ' + failedItems.length + ' failed: ' + esc(failedItems[0].imgMsg || '') : '.');
                    $('paImgStatus').className = 'pa-inline-status ' + (failedItems.length ? 'err' : 'ok');
                    if ($('paPinList')) renderPins();
                    return;
                }
                var i = ready[k], it = Q[i];
                var whenDate = base ? new Date(base.getTime() + slot * interval * 60000) : null;
                var when = whenDate ? localInputValue(whenDate) : '';
                // Absolute time (seconds since epoch) so the server schedules the moment the user
                // picked in THEIR timezone, whatever timezone the server runs in.
                var whenTs = whenDate ? Math.round(whenDate.getTime() / 1000) : '';
                it.status = 'busy'; renderQueue();
                postJSON('ajax-pa-schedule', {
                    source_pin_id: it.pin.pin_id || '', origin: isKw(it) ? 'keyword' : '', board: it.board, new_board_name: it.newBoard || '',
                    title: it.title, description: it.desc, link: it.link, alt_text: it.alt, keywords: it.keywords || '',
                    image_mode: it.imageMode, image_path: it.imagePath || '', original_image_url: it.pin.image_url || '',
                    mode: mode, publish_at: when, publish_ts: whenTs, draft_id: it.draftId || ''
                }).then(function (d) {
                    if (!d.ok) {
                        it.status = 'error'; it.imgMsg = d.error; it.imgMsgCls = 'err'; upgradeCheck(d.error);
                        if (/per day|per month|scheduling limit/i.test(d.error || '')) { busy = false; go(i); updateSubmitLabel(); return; }
                    } else {
                        slot++;
                        it.status = 'done';
                        it.doneLabel = d.mode === 'publish' ? (d.published ? 'Published' : 'Queued') : 'Scheduled';
                        it.imgMsg = d.mode === 'publish'
                            ? (d.published ? 'Published to Pinterest.' : 'Queued — ' + (d.publish_error || 'it will publish shortly.'))
                            : 'Scheduled for ' + d.publish_at + ' on ' + (d.board_name || 'your board') + '.';
                        it.imgMsgCls = 'ok';
                        var src = pinById(it.pin.pin_id);
                        if (src) src.regen_count = (src.regen_count || 0) + 1;
                        if (it.draftId) { it.draftId = null; setDraftCount(CFG.draftCount - 1); }
                        if (it.board === '__new__' && boards) boards = null; // board now exists locally — reload next time
                    }
                    if (i === qi) renderItem(); else renderQueue();
                    next(k + 1);
                }).catch(function () { it.status = 'error'; it.imgMsg = 'Network error.'; it.imgMsgCls = 'err'; next(k + 1); });
            })(0);
        }

        function autoAll() {
            if (busy) return;
            saveFields();
            busy = true; $('paAutoAll').disabled = true;
            var idx = 0;
            (function next() {
                while (idx < Q.length && Q[idx].status === 'done') idx++;
                if (idx >= Q.length) { busy = false; $('paAutoAll').disabled = false; renderItem(); return; }
                var i = idx++;
                var it = Q[i];
                var p = it.textDone ? Promise.resolve({ ok: true }) : regenText(i);
                p.then(function (t) {
                    if (t && t.ok === false && /credit/i.test(t.error || '')) return t;
                    if (it.imageMode === 'ai' && !it.imagePath) return regenImage(i);
                    return { ok: true };
                }).then(function (r) {
                    if (r && r.ok === false && /credit/i.test(r.error || '')) { busy = false; $('paAutoAll').disabled = false; go(i); return; }
                    if (i === qi) renderItem(); else renderQueue();
                    next();
                });
            })();
        }

        /* ---------- wiring ---------- */
        $('paRegenClose').addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
        $('paQPrev').addEventListener('click', function () { go(qi - 1); });
        $('paQNext').addEventListener('click', function () { go(qi + 1); });
        ['paTitle', 'paDesc', 'paLink', 'paAlt', 'paNewBoard'].forEach(function (id) {
            $(id).addEventListener('input', function () { saveFields(); counters(); if (id === 'paTitle' || id === 'paLink') renderQueue(); });
        });
        $('paWebsite').addEventListener('input', function () { this.dataset.touched = '1'; });
        $('paBoard').addEventListener('change', function () {
            $('paNewBoard').style.display = this.value === '__new__' ? 'block' : 'none';
            saveFields();
            if (Q.length > 1 && this.value) { // apply the chosen board to every pin not yet sent
                var v = this.value;
                Q.forEach(function (it) { if (!it.board || it.status !== 'done') { it.board = v; it.newBoard = $('paNewBoard').value; } });
            }
            renderQueue();
        });
        $('paRegenTextBtn').addEventListener('click', function () { var b = this; withBusy(b, function () { return regenText(qi); }); });
        $('paRegenImgBtn').addEventListener('click', function () { var b = this; withBusy(b, function () { return regenImage(qi); }); });
        $('paAutoAll').addEventListener('click', autoAll);
        $('paSubmit').addEventListener('click', submitAll);
        document.querySelectorAll('input[name="paWhen"]').forEach(function (r) { r.addEventListener('change', updateSubmitLabel); });
        document.querySelectorAll('[data-imgmode]').forEach(function (b) {
            b.addEventListener('click', function () {
                var mode = b.getAttribute('data-imgmode');
                // Applies to every pin in the popup that hasn't been sent yet.
                Q.forEach(function (it) { if (it.status !== 'done') it.imageMode = mode; });
                renderItem();
            });
        });
        $('paPaletteEnabled').addEventListener('change', function () { $('paPaletteWrap').style.display = this.checked ? 'block' : 'none'; });
        $('paPaletteCount').addEventListener('change', function () { $('paColor4').style.display = this.value === '4' ? 'inline-block' : 'none'; });
        $('paImageType').addEventListener('change', function () { $('paCollageWrap').style.display = this.value === 'collage' ? 'block' : 'none'; updateImageCost(); });
        $('paCollageCount').addEventListener('change', updateImageCost);
        $('paQuality').addEventListener('change', updateImageCost);
        $('paCtaMode').addEventListener('change', function () { $('paCtaText').style.display = this.value === 'custom' ? 'block' : 'none'; });

        /* ---------- Regen Drafts: anything regenerated but not sent is kept ---------- */
        function draftPayload(it) {
            return {
                action: 'save', draft_id: it.draftId || '', source_pin_id: it.pin.pin_id || '',
                source_json: JSON.stringify(it.pin), title: it.title, description: it.desc, link: it.link,
                alt_text: it.alt || '', keywords: it.keywords || '', image_mode: it.imageMode,
                image_path: it.imagePath || '', board: it.board || '', new_board_name: it.newBoard || ''
            };
        }
        function saveDraft(it) {
            if (it.status === 'done') return Promise.resolve();
            var isNew = !it.draftId;
            // Chain saves per item so a slow first save can't create a duplicate draft.
            it.savePromise = (it.savePromise || Promise.resolve()).then(function () {
                if (it.status === 'done') return;
                return postJSON('ajax-pa-drafts', draftPayload(it)).then(function (d) {
                    if (d.ok && d.draft_id) {
                        if (!it.draftId && isNew) setDraftCount(CFG.draftCount + 1);
                        it.draftId = d.draft_id;
                    }
                });
            }).catch(function () {});
            return it.savePromise;
        }
        function hasWork(it) { return it.status !== 'done' && (it.textDone || it.imagePath || it.draftId); }

        function makeItem(p) {
            var kwPin = p.origin === 'keyword';
            return { pin: p, title: kwPin ? '' : (p.title || ''), desc: kwPin ? '' : (p.description || ''), link: p.link || '', alt: '', keywords: '',
                imagePath: '', imageMode: 'ai', board: '', newBoard: '', status: 'idle', textDone: false, draftId: null };
        }
        function itemFromDraft(d) {
            var src = d.source || { pin_id: d.source_pin_id || '', title: d.title, description: d.description, link: d.link, image_url: '' };
            return { pin: src, title: d.title || '', desc: d.description || '', link: d.link || '', alt: d.alt_text || '',
                keywords: d.keywords || '', imagePath: d.image_path || '', imageMode: d.image_mode || 'ai',
                board: d.board || '', newBoard: d.new_board_name || '', status: 'idle', textDone: true, draftId: d.draft_id };
        }
        function openItems(items, onClose) {
            Q = items;
            if (!Q.length) return;
            onCloseCb = onClose || null;
            qi = 0;
            $('paWebsite').dataset.touched = '';
            var start = new Date(Date.now() + 60 * 60000); start.setMinutes(0, 0, 0);
            $('paPublishAt').value = localInputValue(start);
            $('paPublishAt').min = localInputValue(new Date());
            creditsLine();
            updateImageCost();
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            renderItem();
            loadBoards();
            $('paTitle').focus();
        }
        function open(pins, onClose) { openItems(pins.filter(Boolean).map(makeItem), onClose); }
        function openDrafts(drafts, onClose) { openItems(drafts.map(itemFromDraft), onClose); }
        function close() {
            if (busy && !confirm('Pins are still being processed. Close anyway?')) return;
            saveFields();
            modal.classList.remove('open');
            document.body.style.overflow = '';
            // Keep every regenerated-but-unsent pin (with the user's latest edits) as a Regen Draft.
            Promise.all(Q.filter(hasWork).map(saveDraft)).then(function () {
                if (onCloseCb) onCloseCb();
            });
        }
        return { open: open, openDrafts: openDrafts };
    })();

    /* ================================================================== TRENDS TAB */
    function initTrends() {
        var T = { period: 7, metric: 'all', rising: true, data: null };
        var grid = $('paTrendGrid');

        function sum(a) { var t = 0; for (var i = 0; i < a.length; i++) t += a[i]; return t; }
        function pctOf(cur, prev) { return prev > 0 ? (cur - prev) / prev * 100 : (cur > 0 ? Infinity : null); }
        function fmtChange(pct, diff) {
            if (pct == null) return '<b class="pa-flat">—</b>';
            var cls = pct > 0 ? 'pa-up' : (pct < 0 ? 'pa-down' : 'pa-flat');
            var p = pct === Infinity ? 'New' : (pct > 0 ? '+' : '') + pct.toFixed(1) + '%';
            return '<b class="' + cls + '">' + p + '</b> <span class="muted">(' + (diff >= 0 ? '+' : '') + fmtNum(diff) + ')</span>';
        }
        function enrich(p) {
            var ci = sum(p.cur_imp), pi = sum(p.prev_imp), cc = sum(p.cur_clk), pc = sum(p.prev_clk);
            var cur, prev, min;
            if (T.metric === 'clicks') { cur = cc; prev = pc; min = 3; }
            else if (T.metric === 'impressions') { cur = ci; prev = pi; min = 20; }
            else { cur = ci + cc; prev = pi + pc; min = 20; }
            return { p: p, ci: ci, pi: pi, cc: cc, pc: pc, pct: pctOf(cur, prev), enough: cur + prev >= min };
        }
        function visible() {
            if (!T.data) return [];
            var rows = T.data.pins.map(enrich).filter(function (r) {
                if (!r.enough || r.pct == null) return false;
                return T.rising ? r.pct > 0 : r.pct < 0;
            });
            rows.sort(function (a, b) { return T.rising ? b.pct - a.pct : a.pct - b.pct; });
            return rows;
        }
        function spark(cur, prev, rising) {
            var w = 300, h = 56, n = cur.length;
            var max = Math.max.apply(null, cur.concat(prev).concat([1]));
            var x = function (i) { return n === 1 ? w / 2 : i * w / (n - 1); };
            var y = function (v) { return h - 4 - (v / max) * (h - 10); };
            var path = function (a) { return smoothPath(a.map(function (v, i) { return [x(i), y(v)]; })); };
            var color = rising ? '#16a34a' : '#dc2626';
            var line = path(cur);
            return '<svg class="pa-spark" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">' +
                '<path d="' + line + 'L' + w + ',' + h + 'L0,' + h + 'Z" fill="' + color + '" opacity="0.12"/>' +
                '<path d="' + path(prev) + '" fill="none" stroke="#9ca3af" stroke-width="1.6" stroke-dasharray="4 3" vector-effect="non-scaling-stroke"/>' +
                '<path d="' + line + '" fill="none" stroke="' + color + '" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>';
        }
        function render() {
            $('paTrendNote').innerHTML = '';
            document.querySelectorAll('[data-period-label]').forEach(function (el) { el.textContent = T.period; });
            if (!T.data) return;
            if (!T.data.pins.length) {
                grid.innerHTML = '<div class="pa-empty">No pins with analytics yet. New pins usually show data after 2-3 days.</div>';
                return;
            }
            if (!T.data.prev_complete) {
                grid.innerHTML = '<div class="pa-empty">A ' + T.period + '-day comparison needs ' + (T.period * 2 + 2) + ' days of pin history, and ' +
                    T.data.history_days + ' days are stored so far. Pinterest only shares the last 90 days, so the ' + T.period +
                    '-day view fills in automatically as this page keeps collecting data. The 3d, 7d and 30d views work right away.</div>';
                $('paTrendSummary').innerHTML = '&nbsp;';
                return;
            }
            var rows = visible();
            T.rows = rows;
            var metricLabel = { all: 'views + clicks', clicks: 'clicks', impressions: 'impressions' }[T.metric];
            $('paTrendSummary').innerHTML = 'Showing <b>' + (T.rising ? 'Rising' : 'Declining') + '</b> pins (' + rows.length + ') · last ' + T.period +
                ' days avg vs previous avg · ' + esc(metricLabel) + ' · top ' + T.data.pins.length + ' pins';
            if (!rows.length) {
                grid.innerHTML = '<div class="pa-empty">No ' + (T.rising ? 'rising' : 'declining') + ' pins for this period and metric.</div>';
                return;
            }
            grid.innerHTML = '<div class="pa-trend-grid">' + rows.map(function (r, idx) {
                var p = r.p;
                var badge = r.pct === Infinity ? '<span class="pa-tcard-badge new">New</span>'
                    : '<span class="pa-tcard-badge' + (r.pct < 0 ? ' down' : '') + '">' + (r.pct > 0 ? '↗ +' : '↘ ') + r.pct.toFixed(1) + '%</span>';
                var useClicks = T.metric === 'clicks';
                return '<article class="pa-tcard">' +
                    '<div class="pa-tcard-media">' +
                        (p.image_url ? '<img src="' + esc(p.image_url) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '<div style="aspect-ratio:2/3"></div>') +
                        badge +
                        '<div class="pa-tcard-hover"><button type="button" class="btn-primary btn-small" data-regen="' + idx + '">✨ Regenerate</button>' +
                        '<a class="pa-icon-btn" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="View on Pinterest">↗</a></div>' +
                    '</div>' +
                    '<div class="pa-tcard-body">' +
                        '<div class="pa-tcard-title" title="' + esc(p.title) + '">' + esc(p.title || 'Untitled pin') + '</div>' +
                        spark(useClicks ? p.cur_clk : p.cur_imp, useClicks ? p.prev_clk : p.prev_imp, r.pct > 0) +
                        '<div class="pa-spark-legend"><span><i style="border-color:' + (r.pct > 0 ? '#16a34a' : '#dc2626') + '"></i>Current</span><span><i class="prev"></i>Previous</span></div>' +
                        '<div class="pa-tstats">' +
                            '<div><span>Last ' + T.period + ' Days:</span><b>' + fmtNum(r.ci) + ' views · ' + fmtNum(r.cc) + ' clicks</b></div>' +
                            '<div><span>Views trend:</span><span>' + fmtChange(pctOf(r.ci, r.pi), r.ci - r.pi) + '</span></div>' +
                            '<div><span>Clicks trend:</span><span>' + fmtChange(pctOf(r.cc, r.pc), r.cc - r.pc) + '</span></div>' +
                        '</div>' +
                    '</div></article>';
            }).join('') + '</div>';
            grid.querySelectorAll('[data-regen]').forEach(function (b) {
                b.addEventListener('click', function () { Regen.open([rows[parseInt(b.getAttribute('data-regen'), 10)].p]); });
            });
        }
        function syncTrends(offset) {
            return postJSON('ajax-pa-trends', { action: 'sync', offset: offset }).then(function (d) {
                if (!d.ok) return d;
                if (d.next == null) return { ok: true };
                grid.innerHTML = loading('Reading daily history for your top pins… ' + Math.min(d.next, d.total) + ' / ' + d.total);
                return syncTrends(d.next);
            });
        }
        function load(noSync) {
            grid.innerHTML = loading('Loading trends…');
            getJSON('ajax-pa-trends', { period: T.period, no_sync: noSync ? 1 : 0 }).then(function (d) {
                if (!d.ok) { grid.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
                if (d.needs_pins_sync) {
                    return syncPins(function (m) { grid.innerHTML = loading(m); }).then(function (r) {
                        if (!r.ok) { grid.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>'; return; }
                        load(false);
                    });
                }
                if (d.needs_trend_sync) {
                    grid.innerHTML = loading('Reading daily history for your top pins…');
                    return syncTrends(0).then(function (r) {
                        if (!r.ok) { grid.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>'; return; }
                        load(true);
                    }).catch(function () { grid.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
                }
                T.data = d;
                render();
            }).catch(function () { grid.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
        }
        function pills(attr, key, reload) {
            document.querySelectorAll('[data-' + attr + ']').forEach(function (b) {
                b.addEventListener('click', function () {
                    document.querySelectorAll('[data-' + attr + ']').forEach(function (x) { x.classList.toggle('on', x === b); });
                    var v = b.getAttribute('data-' + attr);
                    T[key] = key === 'period' ? parseInt(v, 10) : v;
                    if (reload) load(true); else render();
                });
            });
        }
        pills('period', 'period', true);
        pills('metric', 'metric', false);
        $('paTrendDir').addEventListener('change', function () { T.rising = this.checked; render(); });
        $('paTrendsExport').addEventListener('click', function () {
            var rows = T.rows || [];
            if (!rows.length) return;
            var lines = [['Pin ID', 'Title', 'Direction', 'Period (days)', 'Views (current)', 'Views (previous)', 'Views change %', 'Clicks (current)', 'Clicks (previous)', 'Clicks change %', 'Pin URL']];
            rows.forEach(function (r) {
                var vp = pctOf(r.ci, r.pi), cp = pctOf(r.cc, r.pc);
                var f = function (x) { return x == null ? '' : (x === Infinity ? 'new' : x.toFixed(1)); };
                lines.push([r.p.pin_id, r.p.title, T.rising ? 'rising' : 'declining', T.period, r.ci, r.pi, f(vp), r.cc, r.pc, f(cp), r.p.url]);
            });
            var csv = '\ufeff' + lines.map(function (l) {
                return l.map(function (v) { v = String(v == null ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(',');
            }).join('\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'pin-trends-' + (T.rising ? 'rising' : 'declining') + '-' + T.period + 'd.csv';
            document.body.appendChild(a); a.click(); a.remove();
        });
        load(false);
    }

    /* ================================================================== DELETE UNDERPERFORMING TAB */
    function initDelete() {
        var U = { age: 90, threshold: 10, ctr: null, pins: [], selected: {}, limit: 300, data: null };
        var list = $('paUnderList');

        function ctrOf(p) { return p.impressions > 0 ? (p.clicks + p.outbound) / p.impressions * 100 : 0; }
        function filtered() {
            var url = $('paUrlFilter').value.trim().toLowerCase();
            var noSaves = $('paNoSaves').checked, noOut = $('paNoOutbound').checked, noClicks = $('paNoClicks').checked;
            var ctrMax = null;
            document.querySelectorAll('[data-ctr]').forEach(function (c) { if (c.checked) { var v = parseFloat(c.getAttribute('data-ctr')); ctrMax = ctrMax == null ? v : Math.min(ctrMax, v); } });
            return U.pins.filter(function (p) {
                if (p.impressions > U.threshold) return false;
                if (url && (p.link || '').toLowerCase().indexOf(url) === -1 && (p.title || '').toLowerCase().indexOf(url) === -1) return false;
                if (noSaves && (p.saves || 0) > 0) return false;
                if (noOut && p.outbound > 0) return false;
                if (noClicks && p.clicks > 0) return false;
                if (ctrMax != null && ctrOf(p) >= ctrMax) return false;
                return true;
            });
        }
        function groupKey(link) {
            if (!link) return '';
            return link.replace(/[?#].*$/, '').replace(/\/+$/, '/').toLowerCase();
        }
        function renderThresholds() {
            var d = U.data;
            var vals = [0, 10, 100, 200, 300];
            var extra = {};
            if (d) {
                if (d.p25 > 0) { vals.push(d.p25); extra[d.p25] = '25% of these pins are at or below this'; }
                if (d.p40 > 0) { vals.push(d.p40); extra[d.p40] = '40% of these pins are at or below this'; }
            }
            vals = vals.filter(function (v, i) { return vals.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
            var custom = vals.indexOf(U.threshold) === -1;
            $('paThresholds').innerHTML = vals.map(function (v) {
                return '<button type="button" data-th="' + v + '"' + (v === U.threshold ? ' class="on"' : '') + (extra[v] ? ' title="' + esc(extra[v]) + '"' : '') + '>' + fmtNum(v) + '</button>';
            }).join('') + '<input type="number" min="0" class="pa-threshold-custom" id="paThCustom" placeholder="Custom" aria-label="Custom threshold"' + (custom ? ' value="' + U.threshold + '"' : '') + '>';
            $('paThresholds').querySelectorAll('[data-th]').forEach(function (b) {
                b.addEventListener('click', function () { U.threshold = parseInt(b.getAttribute('data-th'), 10); renderThresholds(); render(); });
            });
            $('paThCustom').addEventListener('change', function () {
                var v = parseInt(this.value, 10);
                if (!isNaN(v) && v >= 0) { U.threshold = v; renderThresholds(); render(); }
            });
        }
        function render() {
            var rows = filtered();
            U.visible = rows;
            // Drop selections that are no longer visible so the count always matches what you see.
            var keep = {};
            rows.forEach(function (p) { if (U.selected[p.pin_id]) keep[p.pin_id] = true; });
            U.selected = keep;
            $('paUnderCount').textContent = rows.length;
            $('paUnderTotal').textContent = rows.length;
            if (!rows.length) {
                list.innerHTML = '<div class="pa-empty">No pins match these filters. Try a higher impression threshold or the other age option.</div>';
                updateSel();
                return;
            }
            var groups = [], byKey = {};
            rows.forEach(function (p) {
                var k = groupKey(p.link);
                if (!byKey[k]) { byKey[k] = { key: k, link: p.link, pins: [] }; groups.push(byKey[k]); }
                byKey[k].pins.push(p);
            });
            groups.sort(function (a, b) { return b.pins.length - a.pins.length; });
            var shown = 0, html = '';
            for (var g = 0; g < groups.length && shown < U.limit; g++) {
                var grp = groups[g];
                var pins = grp.pins.slice(0, U.limit - shown);
                shown += pins.length;
                html += '<div class="pa-group" data-group="' + g + '"><div class="pa-group-head"><span class="pa-group-url">' +
                    (grp.link ? esc(grp.link) : '<i>No destination link</i>') + '<small>(' + grp.pins.length + ' pin' + (grp.pins.length === 1 ? '' : 's') + ')</small></span>' +
                    '<button type="button" data-group-sel="' + g + '">Select All</button></div><div class="pa-ucards">' +
                    pins.map(function (p) {
                        return '<div class="pa-ucard' + (U.selected[p.pin_id] ? ' sel' : '') + '" data-pin="' + esc(p.pin_id) + '">' +
                            '<div class="pa-ucard-img">' + (p.image_url ? '<img src="' + esc(p.image_url) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '') +
                            '<input type="checkbox" aria-label="Select pin"' + (U.selected[p.pin_id] ? ' checked' : '') + '>' +
                            '<a class="pa-ucard-open" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="View on Pinterest">↗</a>' +
                            '<span class="pa-ucard-date">' + esc(p.created) + '</span></div>' +
                            '<div class="pa-ucard-body"><div class="pa-ucard-title" title="' + esc(p.title) + '">' + esc(p.title || 'Untitled pin') + '</div>' +
                            '<div class="pa-ucard-meta">' + (p.lifetime ? 'Lifetime' : 'Last 90 days') + '</div>' +
                            '<div class="pa-ucard-stats"><span title="Impressions">👁 ' + fmtNum(p.impressions) + '</span><span title="Outbound clicks">↗ ' + fmtNum(p.outbound) +
                            '</span><span title="Saves">🔖 ' + fmtNum(p.saves) + '</span></div></div></div>';
                    }).join('') + '</div></div>';
            }
            if (shown < rows.length) html += '<button type="button" class="btn-secondary pa-more" id="paUnderMore">Show more (' + (rows.length - shown) + ' more)</button>';
            list.innerHTML = html;
            list.querySelectorAll('.pa-ucard').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (e.target.closest('a')) return;
                    var id = card.getAttribute('data-pin');
                    if (U.selected[id]) delete U.selected[id]; else U.selected[id] = true;
                    card.classList.toggle('sel', !!U.selected[id]);
                    card.querySelector('input').checked = !!U.selected[id];
                    updateSel();
                });
            });
            list.querySelectorAll('[data-group-sel]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var grp = groups[parseInt(b.getAttribute('data-group-sel'), 10)];
                    var all = grp.pins.every(function (p) { return U.selected[p.pin_id]; });
                    grp.pins.forEach(function (p) { if (all) delete U.selected[p.pin_id]; else U.selected[p.pin_id] = true; });
                    render();
                });
            });
            if ($('paUnderMore')) $('paUnderMore').addEventListener('click', function () { U.limit += 300; render(); });
            updateSel();
        }
        function updateSel() {
            var n = Object.keys(U.selected).length;
            $('paUnderSel').textContent = n;
            $('paQueueSelCount').textContent = n;
            $('paQueueSelected').disabled = n === 0;
            $('paUnderSelectAll').checked = U.visible && U.visible.length > 0 && n === U.visible.length;
        }
        function load() {
            list.innerHTML = loading('Loading your pins…');
            getJSON('ajax-pa-underperforming', { age: U.age }).then(function (d) {
                if (!d.ok) { list.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
                if (d.needs_pins_sync) {
                    return syncPins(function (m) { list.innerHTML = loading(m); }).then(function (r) {
                        if (!r.ok) { list.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>'; return; }
                        load();
                    });
                }
                U.data = d;
                U.pins = d.pins;
                U.limit = 300;
                $('paMedian').textContent = fmtNum(d.median);
                $('paMedianAge').textContent = d.age;
                $('paQueueCount').textContent = d.queue_count;
                $('paUnderSynced').textContent = d.synced_at ? 'Pin data from ' + d.synced_at : '';
                renderThresholds();
                render();
            }).catch(function () { list.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
        }

        document.querySelectorAll('.pa-age').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('.pa-age').forEach(function (x) { x.classList.toggle('on', x === b); });
                U.age = parseInt(b.getAttribute('data-age'), 10);
                U.selected = {};
                load();
            });
        });
        document.querySelectorAll('.pa-preset').forEach(function (b) {
            b.addEventListener('click', function () {
                var pr = b.getAttribute('data-preset');
                var set = function (ids, ctr) {
                    $('paNoSaves').checked = ids.indexOf('s') !== -1;
                    $('paNoOutbound').checked = ids.indexOf('o') !== -1;
                    $('paNoClicks').checked = ids.indexOf('c') !== -1;
                    document.querySelectorAll('[data-ctr]').forEach(function (c) { c.checked = c.getAttribute('data-ctr') === String(ctr); });
                };
                if (pr === 'zero') set(['s', 'o', 'c'], null);
                else if (pr === 'poorctr') set([], 1);
                else if (pr === 'nosaves') set(['s'], 2);
                else set([], null);
                document.querySelectorAll('.pa-preset').forEach(function (x) { x.classList.toggle('on', x === b && pr !== 'reset'); });
                render();
            });
        });
        document.querySelectorAll('[data-ctr], #paNoSaves, #paNoOutbound, #paNoClicks').forEach(function (c) {
            c.addEventListener('change', function () { document.querySelectorAll('.pa-preset').forEach(function (x) { x.classList.remove('on'); }); render(); });
        });
        var urlTimer;
        $('paUrlFilter').addEventListener('input', function () { clearTimeout(urlTimer); urlTimer = setTimeout(render, 200); });
        $('paUnderSelectAll').addEventListener('change', function () {
            U.selected = {};
            if (this.checked) (U.visible || []).forEach(function (p) { U.selected[p.pin_id] = true; });
            render();
        });
        $('paQueueSelected').addEventListener('click', function () {
            var ids = Object.keys(U.selected);
            if (!ids.length) return;
            var btn = this;
            btn.disabled = true;
            postJSON('ajax-pa-delete-queue', { action: 'queue', pin_ids: JSON.stringify(ids) }).then(function (d) {
                if (!d.ok) { alert(d.error); btn.disabled = false; return; }
                U.pins = U.pins.filter(function (p) { return !U.selected[p.pin_id]; });
                U.selected = {};
                $('paQueueCount').textContent = d.queue_count;
                render();
                $('paUnderSynced').textContent = d.added + ' pin(s) added to the Deletion Queue.';
            });
        });

        /* ----- sub-tabs + deletion queue ----- */
        var Q = { rows: [], selected: {} };
        document.querySelectorAll('.pa-subtab').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.getAttribute('data-subtab');
                document.querySelectorAll('.pa-subtab').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
                $('paUnderPanel').hidden = t !== 'under';
                $('paQueuePanel').hidden = t !== 'queue';
                if (t === 'queue') loadQueue();
            });
        });
        function qRow(r, history) {
            return '<div class="pa-qrow" data-id="' + r.id + '">' +
                (history ? '<span></span>' : '<input type="checkbox" class="pa-row-check" aria-label="Select"' + (Q.selected[r.id] ? ' checked' : '') + '>') +
                (r.image_url ? '<img src="' + esc(r.image_url) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '<span></span>') +
                '<div style="min-width:0;"><div class="pa-pin-title" style="font-size:15px;">' + esc(r.title || 'Untitled pin') + '</div>' +
                '<div class="pa-q-meta">👁 ' + fmtNum(r.impressions) + ' · ↗ ' + fmtNum(r.outbound) + ' · 🔖 ' + fmtNum(r.saves) + ' · ' +
                (history ? 'Deleted ' + esc(r.deleted_at || '') : 'Queued ' + esc(r.queued_at)) + '</div>' +
                (r.status === 'failed' && r.error ? '<div class="pa-q-err">Last attempt failed: ' + esc(r.error) + '</div>' : '') + '</div>' +
                '<span class="pa-q-state ' + (r.status === 'failed' ? 'error' : (history ? 'done' : '')) + '">' + (history ? 'Deleted' : (r.status === 'failed' ? 'Failed' : 'Queued')) + '</span>' +
                '<a class="pa-icon-btn" href="' + esc(r.url) + '" target="_blank" rel="noopener" aria-label="View on Pinterest">↗</a></div>';
        }
        function renderQueue() {
            $('paQueueCount').textContent = Q.rows.length;
            if (!Q.rows.length) $('paQueueList').innerHTML = '<div class="pa-empty">The deletion queue is empty. Select pins on the Underperforming tab to add them.</div>';
            else $('paQueueList').innerHTML = Q.rows.map(function (r) { return qRow(r, false); }).join('');
            $('paQueueList').querySelectorAll('.pa-qrow').forEach(function (row) {
                var cb = row.querySelector('input');
                if (!cb) return;
                cb.addEventListener('change', function () {
                    var id = row.getAttribute('data-id');
                    if (cb.checked) Q.selected[id] = true; else delete Q.selected[id];
                    updateQSel();
                });
            });
            $('paHistoryWrap').hidden = !Q.history.length;
            $('paHistoryCount').textContent = Q.history.length;
            $('paHistoryList').innerHTML = Q.history.map(function (r) { return qRow(r, true); }).join('');
            updateQSel();
        }
        function updateQSel() {
            var n = Object.keys(Q.selected).length;
            $('paQSel').textContent = n;
            $('paDeleteNow').disabled = n === 0;
            $('paUnqueue').disabled = n === 0;
            $('paQSelectAll').checked = Q.rows.length > 0 && n === Q.rows.length;
        }
        function loadQueue() {
            $('paQueueList').innerHTML = loading('Loading queue…');
            getJSON('ajax-pa-delete-queue').then(function (d) {
                if (!d.ok) { $('paQueueList').innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
                Q.rows = d.queue; Q.history = d.history; Q.selected = {};
                renderQueue();
            });
        }
        $('paQSelectAll').addEventListener('change', function () {
            Q.selected = {};
            if (this.checked) Q.rows.forEach(function (r) { Q.selected[r.id] = true; });
            renderQueue();
        });
        $('paUnqueue').addEventListener('click', function () {
            postJSON('ajax-pa-delete-queue', { action: 'remove', ids: JSON.stringify(Object.keys(Q.selected)) }).then(function () {
                loadQueue();
                load(); // removed pins go back to the Underperforming list
            });
        });
        $('paClearHistory').addEventListener('click', function () {
            postJSON('ajax-pa-delete-queue', { action: 'clear_history' }).then(loadQueue);
        });
        $('paDeleteNow').addEventListener('click', function () {
            var ids = Object.keys(Q.selected);
            if (!ids.length) return;
            if (!confirm('Permanently delete ' + ids.length + ' pin(s) from Pinterest?\n\nThis cannot be undone.')) return;
            var status = $('paDeleteStatus');
            var done = 0, failed = 0;
            $('paDeleteNow').disabled = true; $('paUnqueue').disabled = true;
            (function next(k) {
                if (k >= ids.length) {
                    status.className = 'pa-inline-status ' + (failed ? 'err' : 'ok');
                    status.textContent = done + ' deleted' + (failed ? ', ' + failed + ' failed (kept in the queue)' : '') + '.';
                    loadQueue();
                    return;
                }
                var row = $('paQueueList').querySelector('[data-id="' + ids[k] + '"]');
                if (row) row.classList.add('deleting');
                status.className = 'pa-inline-status';
                status.textContent = 'Deleting ' + (k + 1) + ' of ' + ids.length + '…';
                postJSON('ajax-pa-delete-queue', { action: 'delete', id: ids[k] }).then(function (d) {
                    if (d.ok) done++; else failed++;
                    // A short pause keeps bulk deletes gentle on Pinterest's rate limit.
                    setTimeout(function () { next(k + 1); }, 350);
                }).catch(function () { failed++; next(k + 1); });
            })(0);
        });

        load();
    }

    /* ================================================================== BREAKDOWNS TAB
     * Groups the account's own pins by Board / URL / Keyword / Title length / Description
     * length / Time. Clicks here = outbound clicks, CTR = outbound clicks ÷ views, Save % =
     * saves ÷ views — the same definitions as the Analytics tab.
     */
    var BD_STOP = ('a an and are as at be but by for from has have how i if in into is it its of on or our so that the their them these this those to up was we what when where which who why will with you your my me more most best top new ideas idea diy easy simple ways way tips guide get make made can do not all any about over under than then out just like').split(' ');
    var BD_GROUPS = {
        boards: { name: 'Board Name', col: 'Board Name', expand: true },
        urls: { name: 'Outbound URL', col: 'Outbound URL', expand: true, select: true },
        keywords: { name: 'Keyword', col: 'Keyword', expand: true },
        titles: { name: 'Title Length', col: 'Title Length', buckets: true },
        descriptions: { name: 'Description Length', col: 'Description Length', buckets: true },
        time: { name: 'Time Pattern', col: 'Time Pattern (UTC)', buckets: true }
    };
    function initBreakdowns() {
        var B = { group: 'boards', period: 'life', data: null, sort: null, dir: -1, open: {}, selected: {}, rows: [] };
        var host = $('paBdTable');

        function metrics(p) {
            var m = B.period === 'life' ? p.mlife : p.m90;
            if (!m) return null; // no lifetime stats (pins created before 2023-03-20)
            return { views: m[0] || 0, clicks: m[2] || 0, saves: m[3] || 0 };
        }
        function slug(name) { return String(name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
        function normUrl(u) { return u ? u.replace(/[?#].*$/, '').replace(/\/+$/, '/') : ''; }
        function urlLabel(u) {
            if (!u) return '(no destination link)';
            try { var x = new URL(u); return (x.pathname + x.search) || '/'; } catch (e) { return u; }
        }
        function goodWord(w) { return !!w && w.length >= 3 && !/^\d+$/.test(w) && BD_STOP.indexOf(w) === -1; }
        /** All words of a title, in order (stop words kept as null so phrases never jump over them). */
        function words(title) {
            return (title || '').toLowerCase().replace(/[^\p{L}\p{N}\s'-]/gu, ' ').split(/\s+/)
                .map(function (w) { w = w.replace(/^['-]+|['-]+$/g, ''); return goodWord(w) ? w : null; });
        }
        function bucketOf(len, edges, labels) {
            for (var i = 0; i < edges.length; i++) if (len < edges[i]) return labels[i];
            return labels[labels.length - 1];
        }
        var TITLE_B = { edges: [10, 30, 50, 70], labels: ['< 10 chars', '10-30 chars', '30-50 chars', '50-70 chars', '70-100 chars'] };
        var DESC_B = { edges: [100, 200, 300, 400, 500], labels: ['< 100 chars', '100-200 chars', '200-300 chars', '300-400 chars', '400-500 chars', '500+ chars'] };
        var TIME_ORDER = ['Night (0-6 UTC)', 'Morning (6-12 UTC)', 'Afternoon (12-18 UTC)', 'Evening (18-24 UTC)', 'Weekday', 'Weekend'];

        /** [{key, label, link, order}] a pin belongs to, for the current grouping. */
        function keysFor(p) {
            var g = B.group;
            if (g === 'boards') {
                var name = B.data.boards[p.board_id] || 'Unknown board';
                var link = B.data.username && B.data.boards[p.board_id] ? 'https://www.pinterest.com/' + encodeURIComponent(B.data.username) + '/' + slug(name) + '/' : '';
                return [{ key: p.board_id || '_', label: name, link: link }];
            }
            if (g === 'urls') { var u = normUrl(p.link); return [{ key: u || '_', label: urlLabel(u), full: u, link: u }]; }
            if (g === 'keywords') {
                var t = words(p.title), seen = {}, out = [];
                t.forEach(function (w, i) {
                    if (!w) return;
                    var next = t[i + 1];
                    [w, next && next !== w ? w + ' ' + next : null].forEach(function (k) {
                        if (k && !seen[k]) { seen[k] = 1; out.push({ key: k, label: k, link: 'https://www.pinterest.com/search/pins/?q=' + encodeURIComponent(k) }); }
                    });
                });
                return out;
            }
            if (g === 'titles') { var tl = bucketOf((p.title || '').length, TITLE_B.edges, TITLE_B.labels); return [{ key: tl, label: tl, order: TITLE_B.labels.indexOf(tl) }]; }
            if (g === 'descriptions') { var dl = bucketOf((p.description || '').length, DESC_B.edges, DESC_B.labels); return [{ key: dl, label: dl, order: DESC_B.labels.indexOf(dl) }]; }
            if (g === 'time') {
                if (!p.created_ts) return [];
                var d = new Date(p.created_ts * 1000), h = d.getUTCHours(), day = d.getUTCDay();
                var slot = TIME_ORDER[Math.floor(h / 6)];
                var wk = (day === 0 || day === 6) ? 'Weekend' : 'Weekday';
                return [{ key: slot, label: slot, order: TIME_ORDER.indexOf(slot) }, { key: wk, label: wk, order: TIME_ORDER.indexOf(wk) }];
            }
            return [];
        }
        function build() {
            var groups = {}, list = [];
            B.data.pins.forEach(function (p) {
                var m = metrics(p);
                if (!m) return;
                keysFor(p).forEach(function (k) {
                    var g = groups[k.key];
                    if (!g) { g = groups[k.key] = { key: k.key, label: k.label, full: k.full, link: k.link, order: k.order, pins: [], views: 0, clicks: 0, saves: 0 }; list.push(g); }
                    g.pins.push(p); g.views += m.views; g.clicks += m.clicks; g.saves += m.saves;
                });
            });
            // One-off keywords are noise: keep keywords shared by 2+ pins, at most the 300 most used.
            if (B.group === 'keywords') {
                list = list.filter(function (g) { return g.pins.length >= 2; })
                    .sort(function (a, b) { return b.pins.length - a.pins.length; }).slice(0, 300);
            }
            list.forEach(function (g) {
                var n = g.pins.length;
                g.total = n;
                g.avgViews = n ? g.views / n : 0;
                g.avgClicks = n ? g.clicks / n : 0;
                g.ctr = g.views ? g.clicks / g.views * 100 : 0;
                g.saveRate = g.views ? g.saves / g.views * 100 : 0;
            });
            return list;
        }
        var COLS = [
            ['label', null], ['total', 'Total Pins'], ['views', 'Total Views'], ['avgViews', 'Avg. Views'],
            ['clicks', 'Total Clicks'], ['avgClicks', 'Avg. Clicks'], ['ctr', 'Avg. CTR'], ['saveRate', 'Avg. Save %']
        ];
        function fmtAvg(v) { return v >= 1000 ? fmtNum(v) : Number(v).toFixed(2); }
        function sorted(list) {
            var key = B.sort || (BD_GROUPS[B.group].buckets ? 'order' : 'avgViews');
            var dir = B.sort ? B.dir : (key === 'order' ? 1 : -1);
            return list.slice().sort(function (a, b) {
                var x = key === 'label' ? String(a.label).toLowerCase() : a[key], y = key === 'label' ? String(b.label).toLowerCase() : b[key];
                if (x < y) return -dir; if (x > y) return dir; return b.views - a.views;
            });
        }
        function render() {
            var cfg = BD_GROUPS[B.group];
            var min = Math.max(0, parseInt($('paBdMin').value, 10) || 0);
            var rows = sorted(build().filter(function (g) { return g.total >= min; }));
            B.rows = rows;
            var unique = {};
            rows.forEach(function (g) { g.pins.forEach(function (p) { unique[p.pin_id] = 1; }); });
            $('paBdIntro').textContent = 'Analytics summary grouped by ' + cfg.name + ' for the selected period. Click headers to sort. ' +
                (B.period === 'life' ? '(Lifetime stats exclude pins created before 2023-03-20 — Pinterest has no lifetime data for them.)' : '(Last 90 days.)') +
                (B.group === 'time' ? ' Each pin counts once in a time-of-day row and once in Weekday/Weekend.' : '');
            $('paBdSummary').innerHTML = 'Showing stats for <b>' + Object.keys(unique).length + '</b> pins across <b>' + rows.length + '</b> ' + esc(cfg.name);
            $('paBdSelect').hidden = !cfg.select;
            if (!rows.length) {
                host.innerHTML = '<div class="pa-empty">No groups to show' + (min ? ' with at least ' + min + ' pins' : '') + '.</div>';
                updateSel();
                return;
            }
            var activeKey = B.sort || (cfg.buckets ? 'label' : 'avgViews');
            var head = '<tr>' + (cfg.select ? '<th class="pa-bd-chk"><input type="checkbox" id="paBdAll" aria-label="Select all"></th>' : '') +
                COLS.map(function (c) {
                    var label = c[1] || cfg.col;
                    var on = activeKey === c[0];
                    var arrow = on ? ((B.sort ? B.dir : (cfg.buckets ? 1 : -1)) > 0 ? '↑' : '↓') : '⇅';
                    return '<th class="' + (c[0] === 'label' ? 'pa-bd-label' : 'pa-num') + (on ? ' on' : '') + '"><button type="button" data-sort="' + c[0] + '">' + esc(label) + ' <span>' + arrow + '</span></button></th>';
                }).join('') + '<th class="pa-num">Link</th></tr>';
            var body = rows.map(function (g, i) {
                var open = !!B.open[g.key];
                var labelCell = (cfg.expand ? '<button type="button" class="pa-bd-exp" data-exp="' + i + '" aria-expanded="' + open + '" title="Show pins">' +
                    '<span class="pa-caret">' + (open ? '▾' : '▸') + '</span></button>' : '') +
                    '<span class="pa-bd-name" title="' + esc(g.full || g.label) + '">' + esc(g.label) + '</span>' +
                    (cfg.expand ? ' <small class="muted">(' + g.total + ')</small>' : '');
                var link = g.link ? '<a class="pa-bd-link" href="' + esc(g.link) + '" target="_blank" rel="noopener" aria-label="Open link">🔗</a>' +
                    (B.group === 'urls' ? '<button type="button" class="pa-bd-link" data-copy="' + i + '" aria-label="Copy URL" title="Copy URL">⧉</button>' : '') : '<span class="muted">-</span>';
                var tr = '<tr class="' + (B.selected[g.key] ? 'sel' : '') + '">' + (cfg.select ? '<td class="pa-bd-chk"><input type="checkbox" data-sel="' + i + '"' + (B.selected[g.key] ? ' checked' : '') + ' aria-label="Select"></td>' : '') +
                    '<td class="pa-bd-label"><div class="pa-bd-cell">' + labelCell + '</div></td><td class="pa-num">' + g.total + '</td><td class="pa-num">' + fmtNum(g.views) + '</td>' +
                    '<td class="pa-num">' + fmtAvg(g.avgViews) + '</td><td class="pa-num">' + fmtNum(g.clicks) + '</td><td class="pa-num">' + g.avgClicks.toFixed(2) + '</td>' +
                    '<td class="pa-num">' + g.ctr.toFixed(2) + '%</td><td class="pa-num">' + g.saveRate.toFixed(2) + '%</td><td class="pa-num">' + link + '</td></tr>';
                if (open) {
                    var pins = g.pins.slice().sort(function (a, b) { return (metrics(b) || {}).views - (metrics(a) || {}).views; }).slice(0, 20);
                    tr += '<tr class="pa-bd-sub"><td colspan="' + (COLS.length + 1 + (cfg.select ? 1 : 0)) + '"><div class="pa-bd-pins">' + pins.map(function (p) {
                        var m = metrics(p);
                        return '<div class="pa-bd-pin">' + (p.image_url ? '<img src="' + esc(p.image_url) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '<span class="pa-bd-noimg"></span>') +
                            '<div class="pa-bd-pin-main"><div class="pa-pin-title" style="font-size:14px;" title="' + esc(p.title) + '">' + esc(p.title || 'Untitled pin') + '</div>' +
                            '<div class="pa-q-meta">👁 ' + fmtNum(m.views) + ' · ↗ ' + fmtNum(m.clicks) + ' · 🔖 ' + fmtNum(m.saves) + '</div></div>' +
                            '<button type="button" class="btn-secondary btn-small" data-regen-pin="' + esc(p.pin_id) + '">Regenerate</button>' +
                            '<a class="pa-icon-btn" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="View on Pinterest">↗</a></div>';
                    }).join('') + (g.total > 20 ? '<div class="muted" style="font-size:13px;padding:4px 2px;">Showing the 20 most viewed of ' + g.total + ' pins.</div>' : '') + '</div></td></tr>';
                }
                return tr;
            }).join('');
            host.innerHTML = '<div class="pa-bd-wrap"><table class="pa-bd-table"><thead>' + head + '</thead><tbody>' + body + '</tbody></table></div>';

            host.querySelectorAll('[data-sort]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var k = b.getAttribute('data-sort');
                    if (B.sort === k) B.dir = -B.dir; else { B.sort = k; B.dir = k === 'label' ? 1 : -1; }
                    render();
                });
            });
            host.querySelectorAll('[data-exp]').forEach(function (b) {
                b.addEventListener('click', function () { var g = rows[+b.getAttribute('data-exp')]; B.open[g.key] = !B.open[g.key]; render(); });
            });
            host.querySelectorAll('[data-copy]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var g = rows[+b.getAttribute('data-copy')];
                    if (navigator.clipboard) navigator.clipboard.writeText(g.full || '').then(function () { b.textContent = '✓'; setTimeout(function () { b.textContent = '⧉'; }, 1200); });
                });
            });
            host.querySelectorAll('[data-regen-pin]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var id = b.getAttribute('data-regen-pin');
                    var p = B.data.pins.filter(function (x) { return x.pin_id === id; })[0];
                    if (p) Regen.open([toRegenPin(p)]);
                });
            });
            host.querySelectorAll('[data-sel]').forEach(function (c) {
                c.addEventListener('change', function () {
                    var g = rows[+c.getAttribute('data-sel')];
                    if (c.checked) B.selected[g.key] = true; else delete B.selected[g.key];
                    c.closest('tr').classList.toggle('sel', c.checked);
                    updateSel();
                });
            });
            if ($('paBdAll')) $('paBdAll').addEventListener('change', function () {
                B.selected = {};
                if (this.checked) rows.forEach(function (g) { B.selected[g.key] = true; });
                render();
            });
            updateSel();
        }
        function toRegenPin(p) {
            return { pin_id: p.pin_id, title: p.title, description: p.description, link: p.link, image_url: p.image_url, board_id: p.board_id,
                impressions: p.m90[0], clicks: p.m90[1], outbound: p.m90[2], saves: p.m90[3], url: p.url };
        }
        function selectedRows() { return B.rows.filter(function (g) { return B.selected[g.key]; }); }
        function updateSel() {
            var n = selectedRows().length;
            $('paBdSelN').textContent = n;
            $('paBdRegen').disabled = n === 0;
            $('paBdExportSel').disabled = n === 0;
            $('paBdSelLabel').textContent = n ? n + ' selected' : 'None selected';
            if ($('paBdAll')) $('paBdAll').checked = n > 0 && n === B.rows.length;
        }
        function exportRows(rows, suffix) {
            var cfg = BD_GROUPS[B.group];
            var lines = [[cfg.col, 'Total Pins', 'Total Views', 'Avg. Views', 'Total Clicks', 'Avg. Clicks', 'Avg. CTR %', 'Avg. Save %', 'Link']];
            rows.forEach(function (g) {
                lines.push([g.full || g.label, g.total, g.views, g.avgViews.toFixed(2), g.clicks, g.avgClicks.toFixed(2), g.ctr.toFixed(3), g.saveRate.toFixed(3), g.link || '']);
            });
            var csv = '\ufeff' + lines.map(function (l) {
                return l.map(function (v) { v = String(v == null ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(',');
            }).join('\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'pin-breakdown-' + B.group + '-' + (B.period === 'life' ? 'lifetime' : '90d') + (suffix || '') + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
        }
        function load(refreshBoards) {
            host.innerHTML = loading('Loading your pins…');
            getJSON('ajax-pa-breakdowns', refreshBoards ? { refresh_boards: 1 } : {}).then(function (d) {
                if (!d.ok) { host.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
                if (d.needs_pins_sync) {
                    return syncPins(function (m) { host.innerHTML = loading(m); }).then(function (r) {
                        if (!r.ok) { host.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>'; return; }
                        load(true);
                    });
                }
                B.data = d;
                render();
            }).catch(function () { host.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
        }

        document.querySelectorAll('[data-bd]').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('[data-bd]').forEach(function (x) { x.classList.toggle('on', x === b); });
                B.group = b.getAttribute('data-bd'); B.sort = null; B.open = {}; B.selected = {};
                if (B.data) render();
            });
        });
        document.querySelectorAll('[data-bdperiod]').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('[data-bdperiod]').forEach(function (x) { x.classList.toggle('on', x === b); });
                B.period = b.getAttribute('data-bdperiod');
                if (B.data) render();
            });
        });
        $('paBdMin').addEventListener('input', function () { if (B.data) render(); });
        $('paBdQuick').addEventListener('change', function () {
            var v = this.value; this.value = '';
            if (!v) return;
            B.selected = {};
            var n = v === 'all' ? B.rows.length : (v === 'none' ? 0 : parseInt(v, 10));
            B.rows.slice(0, n).forEach(function (g) { B.selected[g.key] = true; });
            render();
        });
        $('paBdRegen').addEventListener('click', function () {
            var pins = selectedRows().map(function (g) {
                return g.pins.slice().sort(function (a, b) { return (metrics(b) || {}).views - (metrics(a) || {}).views; })[0];
            }).filter(Boolean).map(toRegenPin);
            if (pins.length) Regen.open(pins);
        });
        $('paBdExport').addEventListener('click', function () { if (B.rows.length) exportRows(B.rows); });
        $('paBdExportSel').addEventListener('click', function () { exportRows(selectedRows(), '-selected'); });
        $('paBdRefresh').addEventListener('click', function () {
            var btn = this; btn.disabled = true;
            syncPins(function (m) { host.innerHTML = loading(m); }).then(function (r) {
                btn.disabled = false;
                if (!r.ok) { host.innerHTML = '<div class="pa-empty">' + esc(r.error) + '</div>'; return; }
                load(true);
            });
        });
        load(false);
    }

    /* ================================================================== REGEN DRAFTS TAB */
    function initDrafts() {
        var D = { rows: [], selected: {} };
        var grid = $('paDraftGrid');
        function byId(id) { for (var i = 0; i < D.rows.length; i++) if (String(D.rows[i].draft_id) === String(id)) return D.rows[i]; return null; }
        function updateSel() {
            var n = Object.keys(D.selected).length;
            $('paDraftSel').textContent = n;
            $('paDraftOpen').disabled = n === 0;
            $('paDraftDelete').disabled = n === 0;
            $('paDraftSelectAll').checked = D.rows.length > 0 && n === D.rows.length;
        }
        function render() {
            setDraftCount(D.rows.length);
            if (!D.rows.length) {
                grid.innerHTML = '<div class="pa-empty">No drafts. When you regenerate a pin (text or image) and close the popup without publishing or scheduling it, it is kept here.</div>';
                updateSel();
                return;
            }
            grid.innerHTML = '<div class="pa-dgrid">' + D.rows.map(function (d) {
                var src = d.source || {};
                var img = d.image_mode === 'ai' ? (d.image_path ? '../' + d.image_path : '') : (src.image_url || '');
                var tag = d.image_mode === 'ai' ? (d.image_path ? 'New AI image' : 'Needs image') : 'Original image';
                return '<div class="pa-dcard' + (D.selected[d.draft_id] ? ' sel' : '') + '" data-id="' + d.draft_id + '">' +
                    '<div class="pa-dcard-img">' + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '') +
                    '<input type="checkbox" aria-label="Select draft"' + (D.selected[d.draft_id] ? ' checked' : '') + '>' +
                    '<span class="pa-dcard-tag">' + esc(tag) + '</span></div>' +
                    '<div class="pa-dcard-body"><div class="pa-dcard-title">' + esc(d.title || 'Untitled draft') + '</div>' +
                    (d.description ? '<div class="pa-dcard-desc">' + esc(d.description) + '</div>' : '') +
                    '<div class="pa-dcard-meta">Saved ' + esc(d.updated_at) + (src.origin === 'keyword' && src.keyword ? ' · keyword “' + esc(src.keyword) + '”' : (src.title ? ' · from “' + esc(src.title.length > 40 ? src.title.slice(0, 40) + '…' : src.title) + '”' : '')) + '</div></div>' +
                    '<div class="pa-dcard-actions"><button type="button" class="btn-primary btn-small" data-open>Open</button>' +
                    '<button type="button" class="btn-secondary btn-small" data-del aria-label="Delete draft">Delete</button></div></div>';
            }).join('') + '</div>';
            grid.querySelectorAll('.pa-dcard').forEach(function (card) {
                var id = card.getAttribute('data-id');
                card.querySelector('input').addEventListener('change', function () {
                    if (this.checked) D.selected[id] = true; else delete D.selected[id];
                    card.classList.toggle('sel', this.checked);
                    updateSel();
                });
                card.querySelector('[data-open]').addEventListener('click', function () { Regen.openDrafts([byId(id)], load); });
                card.querySelector('[data-del]').addEventListener('click', function () { del([id]); });
            });
            updateSel();
        }
        function del(ids) {
            if (!confirm('Delete ' + ids.length + ' draft(s)? The regenerated text and image will be discarded.')) return;
            postJSON('ajax-pa-drafts', { action: 'delete', ids: JSON.stringify(ids) }).then(load);
        }
        function load() {
            getJSON('ajax-pa-drafts').then(function (d) {
                if (!d.ok) { grid.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>'; return; }
                D.rows = d.drafts;
                var keep = {};
                D.rows.forEach(function (r) { if (D.selected[r.draft_id]) keep[r.draft_id] = true; });
                D.selected = keep;
                render();
            }).catch(function () { grid.innerHTML = '<div class="pa-empty">Network error — please try again.</div>'; });
        }
        $('paDraftSelectAll').addEventListener('change', function () {
            D.selected = {};
            if (this.checked) D.rows.forEach(function (r) { D.selected[r.draft_id] = true; });
            render();
        });
        $('paDraftOpen').addEventListener('click', function () {
            var rows = Object.keys(D.selected).map(byId).filter(Boolean);
            if (rows.length) Regen.openDrafts(rows, load);
        });
        $('paDraftDelete').addEventListener('click', function () { del(Object.keys(D.selected)); });
        load();
    }

    /* ================================================================== KEYWORD RESEARCH PAGE */
    function initKeywords() {
        var K = { rows: [], offset: 0, hasMore: false, selected: {}, loading: false, params: null };
        var host = $('kwTable');

        function params() {
            return {
                q: $('kwQuery').value.trim(), region: $('kwRegion').value, type: $('kwType').value, interest: $('kwInterest').value,
                gmetric: $('kwGMetric').value, gmin: $('kwGMin').value.trim(), gmax: $('kwGMax').value.trim()
            };
        }
        function growth(v) {
            if (v == null) return '<span class="muted">—</span>';
            var cls = v > 0 ? 'pa-up' : (v < 0 ? 'pa-down' : 'pa-flat');
            var txt = v >= 10001 ? '> +10,000%' : (v > 0 ? '+' : '') + v.toLocaleString() + '%';
            return '<span class="' + cls + '">' + txt + '</span>';
        }
        function spark(vals) {
            if (!vals || vals.length < 2) return '';
            var w = 90, h = 26, max = Math.max.apply(null, vals.concat([1]));
            var pts = vals.map(function (v, i) { return [i * w / (vals.length - 1), h - 2 - v / max * (h - 4)]; });
            return '<svg class="kw-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><path d="' + smoothPath(pts) + '" fill="none" stroke="#e60023" stroke-width="1.6"/></svg>';
        }
        function render() {
            if (!K.rows.length) {
                host.innerHTML = '<div class="pa-empty">No keywords found' + (K.params && (K.params.gmin || K.params.gmax) ? ' for this growth range' : '') + '. Try a broader word, another region or trend type.</div>';
                $('kwMore').hidden = true;
                updateSel();
                return;
            }
            var typeLabel = { monthly: 'monthly', yearly: 'yearly', growing: 'growing', seasonal: 'seasonal' };
            host.innerHTML = '<div class="pa-bd-wrap"><table class="pa-bd-table kw-table"><thead><tr>' +
                '<th class="pa-bd-chk"><input type="checkbox" id="kwAll" aria-label="Select all"></th><th>Keyword</th><th>Pinterest popularity</th>' +
                '<th class="pa-num">Week</th><th class="pa-num">Month</th><th class="pa-num">Year</th><th>Source</th><th class="pa-num"><span class="sr-only">Actions</span></th></tr></thead><tbody>' +
                K.rows.map(function (k, i) {
                    var pop = k.source === 'trends'
                        ? '<div class="kw-pop"><div><b>#' + k.rank + '</b> ' + esc(typeLabel[k.trend_type] || '') + ' trend' + (k.interest_label ? ' · ' + esc(k.interest_label) : '') +
                          '<div class="muted" style="font-size:12px;">Interest now ' + (k.now == null ? '—' : k.now + '/100') + ' of its yearly peak</div></div>' + spark(k.series) + '</div>'
                        : '<span class="muted">Suggested by Pinterest search (no trend data)</span>';
                    return '<tr class="' + (K.selected[k.keyword] ? 'sel' : '') + '"><td class="pa-bd-chk"><input type="checkbox" data-kw="' + i + '"' + (K.selected[k.keyword] ? ' checked' : '') + ' aria-label="Select keyword"></td>' +
                        '<td><span class="kw-name">' + esc(k.keyword) + '</span></td><td>' + pop + '</td>' +
                        '<td class="pa-num">' + growth(k.wow) + '</td><td class="pa-num">' + growth(k.mom) + '</td><td class="pa-num">' + growth(k.yoy) + '</td>' +
                        '<td><a href="' + esc(k.trends_url) + '" target="_blank" rel="noopener" class="kw-src">Pinterest Trends ↗</a><br><a href="' + esc(k.search_url) + '" target="_blank" rel="noopener" class="kw-src muted">Search pins ↗</a></td>' +
                        '<td class="pa-num"><div class="kw-menu"><button type="button" class="pa-icon-btn" data-menu="' + i + '" aria-haspopup="true" aria-label="More actions">⋯</button>' +
                        '<div class="kw-menu-list" hidden><button type="button" data-create="' + i + '">✨ Create pin</button><button type="button" data-copy="' + i + '">⧉ Copy keyword</button>' +
                        '<a href="' + esc(k.trends_url) + '" target="_blank" rel="noopener">📈 Open in Pinterest Trends</a></div></div></td></tr>';
                }).join('') + '</tbody></table></div>';
            host.querySelectorAll('[data-kw]').forEach(function (c) {
                c.addEventListener('change', function () {
                    var k = K.rows[+c.getAttribute('data-kw')];
                    if (c.checked) K.selected[k.keyword] = k; else delete K.selected[k.keyword];
                    c.closest('tr').classList.toggle('sel', c.checked);
                    updateSel();
                });
            });
            $('kwAll').addEventListener('change', function () {
                K.selected = {};
                if (this.checked) K.rows.forEach(function (k) { K.selected[k.keyword] = k; });
                render();
            });
            host.querySelectorAll('[data-menu]').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var list = b.nextElementSibling, wasOpen = !list.hidden;
                    closeMenus();
                    list.hidden = wasOpen;
                });
            });
            host.querySelectorAll('[data-create]').forEach(function (b) {
                b.addEventListener('click', function () { closeMenus(); createPins([K.rows[+b.getAttribute('data-create')]]); });
            });
            host.querySelectorAll('[data-copy]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var k = K.rows[+b.getAttribute('data-copy')];
                    if (navigator.clipboard) navigator.clipboard.writeText(k.keyword);
                    closeMenus();
                });
            });
            $('kwMore').hidden = !K.hasMore;
            updateSel();
        }
        function closeMenus() { host.querySelectorAll('.kw-menu-list').forEach(function (l) { l.hidden = true; }); }
        document.addEventListener('click', closeMenus);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });

        function selectedList() { return Object.keys(K.selected).map(function (k) { return K.selected[k]; }); }
        function updateSel() {
            var n = Object.keys(K.selected).length;
            $('kwSelN').textContent = n;
            $('kwCreate').disabled = n === 0;
            $('kwSelLabel').textContent = n ? n + ' selected' : 'None selected';
            if ($('kwAll')) $('kwAll').checked = n > 0 && K.rows.every(function (k) { return K.selected[k.keyword]; });
        }
        function createPins(list) {
            if (!list.length) return;
            Regen.open(list.map(function (k) {
                return { origin: 'keyword', keyword: k.keyword, pin_id: '', title: '', description: '', link: $('kwLink').value.trim(), image_url: '', mom: k.mom, yoy: k.yoy };
            }));
        }
        function fetchPage(reset, refresh) {
            if (K.loading) return;
            K.loading = true;
            if (reset) { K.params = params(); K.offset = 0; K.rows = []; K.selected = {}; host.innerHTML = loading('Finding keywords on Pinterest…'); }
            $('kwMore').disabled = true;
            $('kwMore').textContent = 'Loading…';
            var q = Object.assign({ offset: K.offset }, K.params);
            if (refresh) q.refresh = 1;
            getJSON('ajax-kw-research', q).then(function (d) {
                K.loading = false;
                $('kwMore').disabled = false;
                $('kwMore').textContent = 'Load more';
                if (!d.ok) {
                    if (reset) host.innerHTML = '<div class="pa-empty">' + esc(d.error) + '</div>';
                    else alert(d.error);
                    return;
                }
                K.rows = K.rows.concat(d.keywords);
                K.offset += d.keywords.length;
                K.hasMore = d.has_more && d.keywords.length > 0;
                // More sources exist but none matched this round — let the user keep digging.
                if (d.has_more && !d.keywords.length && K.rows.length) K.hasMore = true;
                $('kwCount').textContent = K.rows.length ? K.rows.length + ' keywords' : '';
                render();
            }).catch(function () {
                K.loading = false; $('kwMore').disabled = false; $('kwMore').textContent = 'Load more';
                if (reset) host.innerHTML = '<div class="pa-empty">Network error — please try again.</div>';
            });
        }

        $('kwSearch').addEventListener('click', function () { fetchPage(true, false); });
        $('kwQuery').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fetchPage(true, false); } });
        ['kwRegion', 'kwType', 'kwInterest', 'kwGMetric'].forEach(function (id) { $(id).addEventListener('change', function () { fetchPage(true, false); }); });
        ['kwGMin', 'kwGMax'].forEach(function (id) { $(id).addEventListener('keydown', function (e) { if (e.key === 'Enter') fetchPage(true, false); }); });
        $('kwApply').addEventListener('click', function () { fetchPage(true, false); });
        $('kwRefresh').addEventListener('click', function () { fetchPage(true, true); });
        $('kwMore').addEventListener('click', function () { fetchPage(false, false); });
        $('kwSelectTop').addEventListener('change', function () {
            var v = this.value; this.value = '';
            if (!v) return;
            K.selected = {};
            var n = v === 'all' ? K.rows.length : (v === 'none' ? 0 : parseInt(v, 10));
            K.rows.slice(0, n).forEach(function (k) { K.selected[k.keyword] = k; });
            render();
        });
        $('kwCreate').addEventListener('click', function () { createPins(selectedList()); });
        $('kwExport').addEventListener('click', function () {
            var list = selectedList().length ? selectedList() : K.rows;
            if (!list.length) return;
            var lines = [['Keyword', 'Trend type', 'Rank', 'Interest', 'Interest now (0-100)', 'Growth week %', 'Growth month %', 'Growth year %', 'Source', 'Pinterest Trends link']];
            list.forEach(function (k) {
                lines.push([k.keyword, k.trend_type || '', k.rank || '', k.interest_label || '', k.now == null ? '' : k.now, k.wow == null ? '' : k.wow,
                    k.mom == null ? '' : k.mom, k.yoy == null ? '' : k.yoy, k.source === 'trends' ? 'Pinterest Trends' : 'Pinterest suggested', k.trends_url]);
            });
            var csv = '\ufeff' + lines.map(function (l) {
                return l.map(function (v) { v = String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(',');
            }).join('\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'pinterest-keywords' + (K.params && K.params.q ? '-' + K.params.q.replace(/[^a-z0-9]+/gi, '-') : '') + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
        });
        fetchPage(true, false);
    }

    /* ------------------------------------------------------------------ boot */
    if (CFG.tab === 'analytics') initAnalytics();
    if (CFG.tab === 'top-pins') initTopPins();
    if (CFG.tab === 'trends') initTrends();
    if (CFG.tab === 'breakdowns') initBreakdowns();
    if (CFG.tab === 'delete-underperforming') initDelete();
    if (CFG.tab === 'regen-drafts') initDrafts();
    if (CFG.tab === 'keyword-research') initKeywords();
})();
