    </div>
</div>

<!-- ===================== Upgrade limit popup (shared across all user pages) ===================== -->
<div class="upg-overlay" id="upgPopupOverlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="upgPopupTitle">
    <div class="upg-modal">
        <button type="button" class="upg-close" id="upgPopupClose" aria-label="Close">&times;</button>
        <div class="upg-badge">🔥 Limited Time — <strong>20% OFF</strong> any upgrade</div>
        <div class="upg-icon" id="upgPopupIcon">⚠️</div>
        <h3 class="upg-title" id="upgPopupTitle">Upgrade Required</h3>
        <p class="upg-message" id="upgPopupMessage"></p>
        <div class="upg-actions">
            <a href="upgrade" class="upg-btn-upgrade" id="upgPopupCta">✨ Upgrade Now</a>
            <button type="button" class="upg-btn-dismiss" id="upgPopupDismiss">Maybe later</button>
        </div>
    </div>
</div>
<script>
(function () {
    var TYPES = {
        image_credit: {
            icon: '🖼️',
            title: 'Image AI Credits Are Low',
            message: 'Your image credit balance is low — not able to create this image. Upgrade now to keep generating pin images at your selected quality.'
        },
        text_credit: {
            icon: '✍️',
            title: 'Text AI Credits Are Low',
            message: 'Your text credit is very low. Please upgrade to keep writing articles without interruption.'
        },
        pin_limit: {
            icon: '📅',
            title: 'Pin Scheduling Limit Reached',
            message: "Your pin scheduling limit is full. Please upgrade and grow your traffic with a higher daily/monthly limit."
        }
    };

    var overlay = document.getElementById('upgPopupOverlay');
    if (!overlay) return;
    var iconEl = document.getElementById('upgPopupIcon');
    var titleEl = document.getElementById('upgPopupTitle');
    var msgEl = document.getElementById('upgPopupMessage');
    var closeBtn = document.getElementById('upgPopupClose');
    var dismissBtn = document.getElementById('upgPopupDismiss');

    function open(type, customMessage) {
        var cfg = TYPES[type] || TYPES.pin_limit;
        iconEl.textContent = cfg.icon;
        titleEl.textContent = cfg.title;
        msgEl.textContent = customMessage || cfg.message;
        overlay.style.display = 'flex';
    }
    function close() { overlay.style.display = 'none'; }

    closeBtn.addEventListener('click', close);
    dismissBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Exposed globally so any page's JS can trigger a specific popup directly.
    window.showUpgradePopup = open;

    /**
     * Pattern-matches a server-returned error string against the three known
     * low-credit / limit-reached messages and opens the matching popup.
     * Returns true if it matched (and therefore opened, subject to cooldown).
     *
     * opts.cooldownKey: when set, suppresses re-opening for the same key within
     * cooldownMs (default 60s) — use this in loops/polling so a persisting
     * error doesn't reopen the modal on every step.
     */
    window.maybeShowUpgradePopup = function (message, opts) {
        if (!message || typeof message !== 'string') return false;
        opts = opts || {};
        var lower = message.toLowerCase();
        var type = null;
        if (lower.indexOf('image ai credit') !== -1 || lower.indexOf('image credit') !== -1) {
            type = 'image_credit';
        } else if (lower.indexOf('text ai credit') !== -1 || lower.indexOf('text credit') !== -1) {
            type = 'text_credit';
        } else if (lower.indexOf('per day') !== -1 || lower.indexOf('per month') !== -1 || lower.indexOf('scheduling limit') !== -1) {
            type = 'pin_limit';
        }
        if (!type) return false;

        if (opts.cooldownKey) {
            var storeKey = 'upgPopupShown_' + opts.cooldownKey;
            var last = 0;
            try { last = parseInt(sessionStorage.getItem(storeKey) || '0', 10); } catch (e) {}
            var cooldown = opts.cooldownMs || 60000;
            if (Date.now() - last < cooldown) return true; // matched, but suppressed this time
            try { sessionStorage.setItem(storeKey, String(Date.now())); } catch (e) {}
        }
        open(type, message);
        return true;
    };
})();
</script>
<script>
/* Time zone: header clock + picker (desktop), auto-detect on first visit, Account Settings picker. */
(function () {
    var cfg = window.USER_TZ || {};
    if (!cfg.tz) return;
    function post(data) {
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('csrf_token', cfg.csrf || '');
        return fetch('ajax-timezone', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    var browserTz = '';
    try { browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

    // First visit: save the zone of the user's location (IP), then show times in it.
    if (!cfg.set) {
        post({ auto: 1, tz: browserTz }).then(function (j) {
            if (j && j.ok && j.changed) location.reload();
        }).catch(function () {});
    }

    // Live clock in the header.
    var clock = document.getElementById('tzClock');
    function tick() {
        if (!clock) return;
        try { clock.textContent = new Date().toLocaleTimeString('en-US', { timeZone: cfg.tz, hour: '2-digit', minute: '2-digit' }); } catch (e) {}
    }
    tick(); setInterval(tick, 15000);

    // Picker (header dropdown on desktop; the same controls are used on Account Settings).
    function bindPicker(root) {
        var sel = root.querySelector('[data-tz-select]'), search = root.querySelector('[data-tz-search]'),
            save = root.querySelector('[data-tz-save]'), detect = root.querySelector('[data-tz-detect]'),
            msg = root.querySelector('[data-tz-msg]');
        if (!sel) return;
        var all = Array.prototype.slice.call(sel.querySelectorAll('option'));
        if (search) search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase().replace(/\s+/g, ' ');
            all.forEach(function (o) { o.hidden = q !== '' && o.textContent.toLowerCase().indexOf(q) === -1 && o.value.toLowerCase().indexOf(q.replace(/ /g, '_')) === -1; });
            sel.querySelectorAll('optgroup').forEach(function (g) {
                g.hidden = !Array.prototype.some.call(g.children, function (o) { return !o.hidden; });
            });
            var first = all.filter(function (o) { return !o.hidden; })[0];
            if (first && sel.selectedOptions[0] && sel.selectedOptions[0].hidden) first.selected = true;
        });
        if (detect) detect.addEventListener('click', function () {
            if (!browserTz) { if (msg) msg.textContent = 'Could not detect your time zone — please pick it from the list.'; return; }
            var o = all.filter(function (x) { return x.value === browserTz; })[0];
            if (o) { if (search) { search.value = ''; search.dispatchEvent(new Event('input')); } o.selected = true; o.scrollIntoView({ block: 'nearest' }); }
            if (msg) msg.textContent = 'Detected: ' + browserTz.replace(/_/g, ' ') + ' — click Save.';
        });
        if (save) save.addEventListener('click', function () {
            save.disabled = true;
            if (msg) msg.textContent = 'Saving…';
            post({ tz: sel.value }).then(function (j) {
                save.disabled = false;
                if (!j || !j.ok) { if (msg) msg.textContent = (j && j.error) || 'Could not save.'; return; }
                if (msg) msg.textContent = 'Saved ✓';
                setTimeout(function () { location.reload(); }, 400);
            }).catch(function () { save.disabled = false; if (msg) msg.textContent = 'Network error — please try again.'; });
        });
    }
    document.querySelectorAll('[data-tz-picker]').forEach(bindPicker);

    var btn = document.getElementById('tzChipBtn'), pop = document.getElementById('tzPop');
    if (btn && pop) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            pop.hidden = !pop.hidden;
            btn.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
            if (!pop.hidden) {
                var s = pop.querySelector('[data-tz-select]'), o = s && s.selectedOptions[0];
                if (o) o.scrollIntoView({ block: 'center' });
                var q = pop.querySelector('[data-tz-search]'); if (q) q.focus();
            }
        });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { if (!pop.hidden) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
    }
})();
</script>
</body>
</html>
