<div class="pa-subtabs" role="tablist">
    <button type="button" class="pa-subtab on" data-subtab="under" role="tab">Underperforming (<span id="paUnderCount">0</span>)</button>
    <button type="button" class="pa-subtab" data-subtab="queue" role="tab">Deletion Queue (<span id="paQueueCount"><?= (int)$deleteQueueCount ?></span>)</button>
</div>

<div id="paUnderPanel">
    <section class="pa-chart-card">
        <h2 style="margin:0 0 4px;">Identify &amp; Queue Underperforming Pins</h2>
        <p class="muted" style="margin:0 0 16px;">Review older pins that Pinterest barely shows anymore, then queue them for deletion. Nothing is deleted until you confirm it in the Deletion Queue.</p>

        <div class="pa-filter-box">
            <div class="pa-filter-top">
                <div>
                    <div class="pa-filter-label">Pins Created Before</div>
                    <div class="pa-pill-row" role="group" aria-label="Pin age">
                        <button type="button" class="pa-age on" data-age="90">📅 &gt; 90 Days Ago</button>
                        <button type="button" class="pa-age" data-age="180">📅 &gt; 180 Days Ago</button>
                    </div>
                </div>
                <div class="pa-median">
                    <div class="pa-filter-label">Median Impr. (Created &gt; <span id="paMedianAge">90</span> days ago)</div>
                    <div class="pa-median-value">📊 <b id="paMedian">—</b></div>
                </div>
            </div>

            <div class="pa-filter-label" style="margin-top:14px;">Impression Threshold <span class="muted">(show pins with impressions at or below)</span></div>
            <div class="pa-pill-row" id="paThresholds" role="group" aria-label="Impression threshold"></div>

            <details class="pa-adv" open>
                <summary>Advanced Filters</summary>
                <div class="pa-filter-label">Quick Presets</div>
                <div class="pa-pill-row">
                    <button type="button" class="pa-preset" data-preset="zero">Zero engagement</button>
                    <button type="button" class="pa-preset" data-preset="poorctr">Poor CTR (&lt;1%)</button>
                    <button type="button" class="pa-preset" data-preset="nosaves">No saves + Low CTR</button>
                    <button type="button" class="pa-preset" data-preset="reset">Reset filters</button>
                </div>
                <div class="pa-filter-label">CTR Filters <span class="muted" title="CTR here = (pin clicks + outbound clicks) ÷ impressions">ⓘ</span></div>
                <div class="pa-check-row">
                    <label class="pa-check"><input type="checkbox" data-ctr="1"> CTR &lt; 1%</label>
                    <label class="pa-check"><input type="checkbox" data-ctr="2"> CTR &lt; 2%</label>
                    <label class="pa-check"><input type="checkbox" data-ctr="5"> CTR &lt; 5%</label>
                </div>
                <div class="pa-filter-label">Engagement Filters</div>
                <div class="pa-check-row">
                    <label class="pa-check"><input type="checkbox" id="paNoSaves"> No Saves</label>
                    <label class="pa-check"><input type="checkbox" id="paNoOutbound"> No Outbound Clicks</label>
                    <label class="pa-check"><input type="checkbox" id="paNoClicks"> No Pin Clicks</label>
                </div>
            </details>
        </div>

        <input type="search" id="paUrlFilter" class="pa-url-filter" placeholder="Filter by URL…" aria-label="Filter by URL">

        <div class="pa-selbar">
            <label class="pa-check"><input type="checkbox" id="paUnderSelectAll"> <span>Select All (<span id="paUnderSel">0</span> / <span id="paUnderTotal">0</span>)</span></label>
            <button type="button" class="btn-primary btn-small" id="paQueueSelected" disabled>🗑 Add to Deletion Queue (<span id="paQueueSelCount">0</span>)</button>
            <span class="muted" id="paUnderSynced" style="margin-left:auto; font-size:13px;"></span>
        </div>

        <div id="paUnderList"><div class="pa-loading"><span class="pa-spinner"></span> Loading your pins…</div></div>
    </section>
</div>

<div id="paQueuePanel" hidden>
    <section class="pa-chart-card">
        <div class="pa-top-head">
            <div>
                <h2>Deletion Queue</h2>
                <p class="muted" style="margin:2px 0 0;">Deleting removes the pin from Pinterest permanently. It can't be undone.</p>
            </div>
        </div>
        <div class="pa-selbar">
            <label class="pa-check"><input type="checkbox" id="paQSelectAll"> Select all</label>
            <button type="button" class="btn-danger btn-small" id="paDeleteNow" disabled>Delete selected from Pinterest (<span id="paQSel">0</span>)</button>
            <button type="button" class="btn-secondary btn-small" id="paUnqueue" disabled>Remove from queue</button>
            <span class="pa-inline-status" id="paDeleteStatus" style="margin:0 0 0 auto;"></span>
        </div>
        <div id="paQueueList"><div class="pa-loading"><span class="pa-spinner"></span> Loading queue…</div></div>
        <details class="pa-adv" id="paHistoryWrap" hidden>
            <summary>Recently deleted (<span id="paHistoryCount">0</span>)</summary>
            <div id="paHistoryList"></div>
            <button type="button" class="btn-secondary btn-small" id="paClearHistory" style="margin-top:10px;">Clear history</button>
        </details>
    </section>
</div>
