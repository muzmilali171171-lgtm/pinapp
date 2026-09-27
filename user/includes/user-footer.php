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
</body>
</html>
