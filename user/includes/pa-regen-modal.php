<?php
/** Regenerate Similar popup — shared by the Top Pins, Trends and Regen Draft tabs of user/pinterest-analytics.php. Expects $pricing. */
?>
<!-- ===================== Regenerate Similar popup ===================== -->
<div class="pa-modal-overlay" id="paRegenModal" role="dialog" aria-modal="true" aria-labelledby="paRegenTitle">
    <div class="pa-modal">
        <div class="pa-modal-head">
            <h2 id="paRegenTitle">Regenerate Similar Pin</h2>
            <div class="pa-queue-nav" id="paQueueNav" hidden>
                <button type="button" class="pa-icon-btn" id="paQPrev" aria-label="Previous pin">‹</button>
                <span>Pin <b id="paQPos">1</b> of <b id="paQTotal">1</b></span>
                <button type="button" class="pa-icon-btn" id="paQNext" aria-label="Next pin">›</button>
            </div>
            <button type="button" class="modal-close" id="paRegenClose" aria-label="Close">✕</button>
        </div>

        <div class="pa-modal-body">
            <!-- Left: original vs new -->
            <div>
                <div class="pa-preview-pair">
                    <figure class="pa-preview" style="margin:0;"><img id="paOrigImg" alt="Original pin"><div class="pa-preview-empty pa-kw-card" id="paOrigKw" hidden></div><figcaption id="paOrigCap">Original</figcaption></figure>
                    <figure class="pa-preview" style="margin:0;">
                        <div id="paNewImgWrap"><div class="pa-preview-empty">New image appears here</div></div>
                        <figcaption>New pin</figcaption>
                    </figure>
                </div>
                <div class="pa-orig-meta" id="paOrigMeta"></div>
                <div class="pa-queue-list" id="paQueueList" hidden></div>
            </div>

            <!-- Right: editor -->
            <div>
                <div class="pa-section">
                    <div class="pa-section-head">
                        <h3>Title &amp; description</h3>
                        <button type="button" class="btn-primary btn-small" id="paRegenTextBtn">✍️ Regenerate Text</button>
                    </div>
                    <div class="form-row">
                        <label for="paTitle">Title <span class="pa-counter" id="paTitleCount">0/100</span></label>
                        <input type="text" id="paTitle" maxlength="200">
                    </div>
                    <div class="form-row">
                        <label for="paDesc">Description <span class="pa-counter" id="paDescCount">0/500</span></label>
                        <textarea id="paDesc" rows="4"></textarea>
                    </div>
                    <div class="form-row">
                        <label for="paLink"><span id="paLinkLabel">Destination link</span></label>
                        <input type="url" id="paLink" placeholder="https://">
                    </div>
                    <details>
                        <summary class="muted" style="cursor:pointer;">More options (custom AI instructions, hashtags, alt text)</summary>
                        <div class="form-row" style="margin-top:10px;">
                            <label for="paTextPrompt">Custom instructions for the AI <span class="muted">(optional)</span></label>
                            <input type="text" id="paTextPrompt" placeholder="e.g. Target beginners, mention budget-friendly">
                        </div>
                        <label class="checkbox-row"><input type="checkbox" id="paWithTags"> Add 3-5 hashtags to the description</label>
                        <div class="form-row" style="margin-top:10px;">
                            <label for="paAlt">Alt text</label>
                            <input type="text" id="paAlt" maxlength="500">
                        </div>
                    </details>
                    <div class="pa-inline-status" id="paTextStatus"></div>
                </div>

                <div class="pa-section">
                    <div class="pa-section-head">
                        <h3>Pin image</h3>
                        <div class="pa-seg" role="group" aria-label="Image source" id="paImgSeg">
                            <button type="button" class="on" data-imgmode="ai">New AI image</button>
                            <button type="button" data-imgmode="original">Keep original image</button>
                        </div>
                    </div>
                    <div class="pa-img-settings" id="paImgSettings">
                        <div class="two-col">
                            <div class="form-row">
                                <label for="paSize">Size</label>
                                <select id="paSize">
                                    <option value="2:3">1000 × 1500 px (2:3)</option>
                                    <option value="9:16">1080 × 1920 px (9:16)</option>
                                    <option value="1:2.1">1000 × 2100 px (1:2.1)</option>
                                    <option value="1:1">1000 × 1000 px (1:1)</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="paImageStyle">Pin Templates &amp; Styles</label>
                                <input type="hidden" id="paImageStyle" value="auto" data-tplpick>
                            </div>
                        </div>

                        <div class="form-row">
                            <label>Select Category <span class="muted" style="font-weight:400;">(the AI image matches this niche)</span></label>
                            <input type="hidden" id="paImageCategory" data-catpick>
                        </div>

                        <div class="form-row">
                            <label class="checkbox-row"><input type="checkbox" id="paPaletteEnabled"> Use my brand color palette <span class="muted">(optional)</span></label>
                        </div>
                        <div id="paPaletteWrap" style="display:none;">
                            <div class="two-col">
                                <div class="form-row">
                                    <label for="paPaletteCount">Number of Colors</label>
                                    <select id="paPaletteCount"><option value="3" selected>3 Colors</option><option value="4">4 Colors</option></select>
                                </div>
                                <div class="form-row" style="display:flex; gap:8px; align-items:flex-end;">
                                    <input type="color" id="paColor1" value="#E91E63" aria-label="Color 1">
                                    <input type="color" id="paColor2" value="#FFEB3B" aria-label="Color 2">
                                    <input type="color" id="paColor3" value="#212121" aria-label="Color 3">
                                    <input type="color" id="paColor4" value="#FFFFFF" aria-label="Color 4" style="display:none;">
                                </div>
                            </div>
                            <div class="two-col">
                                <div class="form-row"><label>Website text / background</label>
                                    <input type="color" id="paWebsiteTextColor" value="#FFFFFF" aria-label="Website text color">
                                    <input type="color" id="paWebsiteBgColor" value="#E91E63" aria-label="Website background color"></div>
                                <div class="form-row"><label>CTA text / background</label>
                                    <input type="color" id="paCtaTextColor" value="#FFFFFF" aria-label="CTA text color">
                                    <input type="color" id="paCtaBgColor" value="#E91E63" aria-label="CTA background color"></div>
                            </div>
                        </div>

                        <input type="hidden" id="paImageType" value="auto"><input type="hidden" id="paCollageCount" value="4">
<p class="muted" style="margin:-4px 0 12px; font-size:12.5px;">Single photo or collage is chosen automatically from the template: single-photo templates get one image, collage templates get several different images (credits are per photo — the photo count is shown on each template).</p>
                        <div class="two-col">
                            <div class="form-row">
                                <label for="paWebsite">Website <span class="muted">(shown on the pin)</span></label>
                                <input type="text" id="paWebsite" placeholder="example.com">
                            </div>
                            <div class="form-row">
                                <label for="paCtaMode">CTA</label>
                                <select id="paCtaMode">
                                    <option value="auto">Auto (based on the title)</option>
                                    <option value="custom">Custom text</option>
                                    <option value="none">None</option>
                                </select>
                                <input type="text" id="paCtaText" placeholder="e.g. Explore All Ideas" style="display:none; margin-top:8px;">
                            </div>
                        </div>
                        <div class="two-col">
                            <div class="form-row">
                                <label for="paQuality">Quality</label>
                                <select id="paQuality">
                                    <option value="budget">Budget — <?= number_format($pricing['image_quality_low'], 2) ?> credits/image</option>
                                    <option value="high">High Quality — <?= number_format($pricing['image_quality_medium'], 2) ?> credits/image</option>
                                    <option value="ultra">Ultra Quality — <?= number_format($pricing['image_quality_high'], 2) ?> credits/image</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="paImgPrompt">Custom image prompt <span class="muted">(optional)</span></label>
                                <input type="text" id="paImgPrompt" placeholder="Leave blank for an automatic design">
                            </div>
                        </div>
                        <button type="button" class="btn-primary btn-small" id="paRegenImgBtn">🎨 Regenerate Pin Image</button>
                        <span class="pa-credit-line" id="paImgCost"></span>
                    </div>
                    <p class="muted" id="paKeepNote" style="display:none; margin:0;">The original pin image will be downloaded and re-published with your new title and description.</p>
                    <div class="pa-inline-status" id="paImgStatus"></div>
                </div>

                <div class="pa-section">
                    <div class="pa-section-head"><h3>Board &amp; publishing</h3></div>
                    <div class="two-col">
                        <div class="form-row">
                            <label for="paBoard">Board</label>
                            <select id="paBoard"><option value="">Loading boards…</option></select>
                            <input type="text" id="paNewBoard" placeholder="New board name" style="display:none; margin-top:8px;">
                        </div>
                        <div class="form-row">
                            <label>When</label>
                            <div class="pa-when">
                                <label><input type="radio" name="paWhen" value="publish"> Publish now</label>
                                <label><input type="radio" name="paWhen" value="schedule" checked> Schedule</label>
                            </div>
                        </div>
                    </div>
                    <div class="pa-when" id="paScheduleWrap">
                        <input type="datetime-local" id="paPublishAt" aria-label="Publish date and time">
                        <span id="paIntervalWrap" hidden><label>then every <input type="number" id="paInterval" min="0" value="60" aria-label="Minutes between pins"> min</label></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pa-modal-foot">
            <div class="pa-credit-line">Image AI credits: <b id="paImgCredits"></b> · Text AI credits: <b id="paTxtCredits"></b></div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn-secondary" id="paAutoAll" hidden>⚡ Generate text + image for all</button>
                <button type="button" class="btn-primary" id="paSubmit">Schedule Pin</button>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/category-picker.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/category-picker.js') ?: time() ?>"></script>
<script src="../assets/js/template-picker.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/template-picker.js') ?: time() ?>"></script>
