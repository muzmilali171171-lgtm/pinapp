<section class="pa-chart-card">
    <div class="pa-top-head">
        <div>
            <h2>📈 Pin Performance Trends</h2>
            <p class="muted" style="margin:2px 0 0;">Discover which pins are gaining or losing momentum. Comparing average daily performance (last <span data-period-label>7</span> days vs the <span data-period-label>7</span> days before) for your top 50 pins.</p>
        </div>
        <button type="button" class="btn-secondary btn-small" id="paTrendsExport">⬇ Export CSV</button>
    </div>

    <div class="pa-trend-bar">
        <label class="pa-switch pa-dir-switch">
            <span class="pa-dir-label pa-dir-down">↘ Declining</span>
            <input type="checkbox" id="paTrendDir" checked aria-label="Show rising pins (off shows declining pins)">
            <span class="pa-switch-track"></span>
            <span class="pa-dir-label pa-dir-up">↗ Rising</span>
        </label>
        <div class="pa-trend-groups">
            <div class="pa-pill-group" role="group" aria-label="Comparison period">
                <button type="button" data-period="3">3d</button>
                <button type="button" data-period="7" class="on">7d</button>
                <button type="button" data-period="30">30d</button>
                <button type="button" data-period="90">90d</button>
            </div>
            <div class="pa-pill-group" role="group" aria-label="Metric">
                <button type="button" data-metric="all" class="on">All</button>
                <button type="button" data-metric="clicks">Clicks</button>
                <button type="button" data-metric="impressions">Impressions</button>
            </div>
        </div>
    </div>
    <p class="pa-trend-summary" id="paTrendSummary">&nbsp;</p>
    <div id="paTrendNote"></div>
    <div id="paTrendGrid"><div class="pa-loading"><span class="pa-spinner"></span> Loading trends…</div></div>
</section>
