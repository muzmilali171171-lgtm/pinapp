<section class="pa-chart-card">
    <div class="pa-top-head">
        <div>
            <h2>Analytics Overview</h2>
            <p class="muted" style="margin:2px 0 0;" id="paBdIntro">Analytics summary grouped by Board Name for the selected period. Click headers to sort.</p>
        </div>
        <div class="pa-top-tools">
            <button type="button" class="btn-secondary btn-small" id="paBdExport">⬇ Export CSV</button>
            <button type="button" class="btn-secondary btn-small" id="paBdRefresh">↻ Refresh Data</button>
        </div>
    </div>

    <div class="pa-bd-groups" role="tablist" aria-label="Group pins by">
        <div class="pa-pill-group">
            <button type="button" data-bd="boards" class="on">Boards</button>
            <button type="button" data-bd="urls">URLs</button>
            <button type="button" data-bd="keywords">Keywords</button>
        </div>
        <div class="pa-pill-group">
            <button type="button" data-bd="titles">Titles</button>
            <button type="button" data-bd="descriptions">Descriptions</button>
            <button type="button" data-bd="time">Time</button>
        </div>
    </div>

    <div class="pa-bd-controls">
        <label for="paBdMin">Min Total Pins:</label>
        <input type="number" id="paBdMin" min="0" value="0">
        <div class="pa-pill-group" role="group" aria-label="Period">
            <button type="button" data-bdperiod="life" class="on">Lifetime</button>
            <button type="button" data-bdperiod="90">Last 90 Days</button>
        </div>
    </div>

    <div class="pa-selbar pa-bd-select" id="paBdSelect" hidden>
        <label for="paBdQuick" style="font-weight:600;">Quick Select:</label>
        <select id="paBdQuick" aria-label="Quick select URLs">
            <option value="">Select URLs ▾</option>
            <option value="10">Top 10</option>
            <option value="20">Top 20</option>
            <option value="50">Top 50</option>
            <option value="100">Top 100</option>
            <option value="all">All</option>
            <option value="none">Clear selection</option>
        </select>
        <button type="button" class="btn-primary btn-small" id="paBdRegen" disabled>✨ Regenerate best pin of each (<span id="paBdSelN">0</span>)</button>
        <button type="button" class="btn-secondary btn-small" id="paBdExportSel" disabled>Export selected</button>
        <span class="muted" id="paBdSelLabel" style="margin-left:auto;">None selected</span>
    </div>

    <p class="pa-trend-summary" id="paBdSummary">&nbsp;</p>
    <div id="paBdTable"><div class="pa-loading"><span class="pa-spinner"></span> Loading your pins…</div></div>
</section>
