<section class="pa-chart-card">
    <div class="pa-top-head">
        <div>
            <h2>Regen Drafts</h2>
            <p class="muted" style="margin:2px 0 0;">Pins you regenerated but haven't published or scheduled yet. Open one to finish it, or pick several and send them together.</p>
        </div>
    </div>
    <div class="pa-selbar">
        <label class="pa-check"><input type="checkbox" id="paDraftSelectAll"> Select all</label>
        <button type="button" class="btn-primary btn-small" id="paDraftOpen" disabled>Open selected (<span id="paDraftSel">0</span>)</button>
        <button type="button" class="btn-secondary btn-small" id="paDraftDelete" disabled>Delete selected</button>
    </div>
    <div id="paDraftGrid"><div class="pa-loading"><span class="pa-spinner"></span> Loading drafts…</div></div>
</section>
