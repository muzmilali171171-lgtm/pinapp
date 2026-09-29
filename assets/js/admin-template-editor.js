/* Admin pin template editor — runs on top of the Canva editor (design-editor.js, window.DE_API).
 *
 *  - Text type panel: every text is Main title / Only number / CTA / Website / Static (stored as deRole).
 *  - Info bar: what kind of template this is (background photo · 1 frame · collage of N) and its typed texts.
 *  - Publish: exports what the server needs to draw the template with GD for every pin:
 *      below  (PNG)  everything under the first photo frame, incl. the page background
 *      above  (PNG)  everything on top of the frames (transparent elsewhere)
 *      masks  (PNG)  the shape of each non-rectangular frame
 *      spec   (JSON) frame boxes + each typed text's box, font, size, colour, alignment and effects
 */
(function () {
    'use strict';
    const $ = (id) => document.getElementById(id);
    const ROLES = { main: 'Main title', number: 'Only number', cta: 'CTA', website: 'Website', static: 'Static text' };

    function start() {
        const API = window.DE_API;
        if (!API) return;
        const canvas = API.canvas;

        /* ---------- header: Save draft · Publish · back to the template list ---------- */
        const back = document.querySelector('.de-back');
        if (back) { back.href = 'pin-templates'; back.title = 'Back to all templates'; }
        const saveBtn = $('deSave');
        if (saveBtn) saveBtn.innerHTML = '💾 Save draft';
        const use = $('deUse');
        if (use) use.hidden = true;
        const pub = document.createElement('button');
        pub.type = 'button'; pub.className = 'de-btn de-primary'; pub.id = 'atPublishBtn';
        pub.innerHTML = '🚀<span class="de-lbl"> Publish</span>';
        use ? use.parentNode.insertBefore(pub, use) : document.querySelector('.de-top').appendChild(pub);
        document.querySelectorAll('[data-proxy="deShareOpenBtn"], #deDlShare').forEach((b) => { b.hidden = true; });

        /* ---------- text type panel ---------- */
        const isText = (o) => API.isText(o);
        const roleOf = (o) => (o && o.deRole) || 'static';
        const texts = () => canvas.getObjects().filter(isText);
        function selectedText() {
            const o = canvas.getActiveObject();
            return o && isText(o) ? o : null;
        }
        function syncRolePanel() {
            const o = selectedText();
            $('atRole').hidden = !o;
            if (!o) return;
            document.querySelectorAll('input[name="atRole"]').forEach((r) => { r.checked = r.value === roleOf(o); });
        }
        document.querySelectorAll('input[name="atRole"]').forEach((r) => r.addEventListener('change', () => {
            const o = selectedText();
            if (!o) return;
            // "Only number", "CTA" and "Website" exist once per template: move the role off any other text.
            if (r.value !== 'static' && r.value !== 'main') texts().forEach((t) => { if (t !== o && t.deRole === r.value) t.deRole = 'static'; });
            o.deRole = r.value;
            API.markDirty();
            info();
        }));
        // A brand-new text: the first one becomes the Main title, others ask for a type.
        canvas.on('object:added', (e) => {
            const o = e.target;
            if (!isText(o) || o.deRole) { info(); return; }
            setTimeout(() => {
                if (!o.deRole && !texts().some((t) => t !== o && t.deRole === 'main')) o.deRole = 'main';
                info(); syncRolePanel();
            }, 0);
        });
        ['selection:created', 'selection:updated', 'selection:cleared'].forEach((ev) => canvas.on(ev, syncRolePanel));
        ['object:removed', 'object:modified'].forEach((ev) => canvas.on(ev, info));

        /* ---------- info bar ---------- */
        function frames() { return canvas.getObjects().filter((o) => o.isFrame); }
        function summary() {
            const f = frames().length;
            const roles = {};
            texts().forEach((t) => { const r = roleOf(t); roles[r] = (roles[r] || 0) + 1; });
            const kind = f === 0 ? '🖼 Background photo (no frame: the pin photo fills the background)'
                : f === 1 ? '🖼 Single photo in 1 frame' : '🧩 Collage — ' + f + ' photos (one per frame)';
            return { f, roles, kind, numbered: !!roles.number };
        }
        function info() {
            const s = summary();
            const chips = Object.keys(ROLES).filter((k) => s.roles[k]).map((k) => '<span class="at-chip at-' + k + '">' + ROLES[k] + (s.roles[k] > 1 ? ' ×' + s.roles[k] : '') + '</span>').join('');
            $('atInfo').innerHTML = '<strong>' + s.kind + '</strong> · ' + (s.numbered ? '🔢 Numbered template (only titles with a number)' : 'Unnumbered template (any title)')
                + '<div class="at-chips">' + (chips || '<span class="at-chip">No text yet</span>') + (s.roles.main ? '' : '<span class="at-chip at-warn">⚠ Mark one text as Main title</span>') + '</div>';
        }
        info();
        setInterval(info, 3000);   // page switches / undo don't always fire events

        /* ---------- publish ---------- */
        const modal = $('atPublishModal');
        modal.addEventListener('click', (e) => { if (e.target === modal || e.target.hasAttribute('data-close')) modal.hidden = true; });
        pub.addEventListener('click', () => {
            const s = summary();
            $('atPubError').innerHTML = '';
            $('atPubDone').hidden = true;
            $('atPublishGo').disabled = false;
            if (!$('atName').value.trim() || $('atName').value === 'New pin template') $('atName').value = $('deTitle').value.trim();
            $('atSummary').innerHTML = s.kind + '<br>' + (s.numbered ? '🔢 Numbered — used only for titles that contain a number.' : 'Unnumbered — used for titles with or without a number.');
            modal.hidden = false;
        });

        function ser(o) { return o.toObject(API.props); }
        function fillColor(f) {
            if (typeof f === 'string') return f;
            if (f && f.colorStops && f.colorStops.length) return f.colorStops[0].color;
            return '#111111';
        }
        function weightOf(w) {
            if (w === 'bold') return 700;
            if (w === 'normal' || !w) return 400;
            return parseInt(w, 10) || 400;
        }
        async function exportTemplate() {
            API.commit();
            canvas.discardActiveObject();
            const all = canvas.getObjects().filter((o) => !o.excludeFromExport);
            const pageJSON = API.pageJSON();
            const W = API.design.w, H = API.design.h;
            const isRoleText = (o) => isText(o) && o.deRole && o.deRole !== 'static';
            const firstFrame = all.findIndex((o) => o.isFrame);
            const hasFrames = firstFrame !== -1;

            let below = '';
            if (hasFrames) {
                const objs = all.slice(0, firstFrame).filter((o) => !isRoleText(o)).map(ser);
                below = await API.renderJSON({ version: pageJSON.version, background: pageJSON.background, backgroundImage: pageJSON.backgroundImage, objects: objs }, { fmt: 'png' });
            }
            const aboveObjs = (hasFrames ? all.slice(firstFrame + 1) : all).filter((o) => !o.isFrame && !isRoleText(o) && !(!hasFrames && o.isBg)).map(ser);
            const above = aboveObjs.length ? await API.renderJSON({ version: pageJSON.version, objects: aboveObjs }, { fmt: 'png', transparent: true }) : '';

            const frames = [];
            const masks = [];
            for (const o of all.filter((x) => x.isFrame)) {
                const b = o.getBoundingRect(true, true);
                const radius = (o.rx || 0) + (o.frameRadius || 0);
                const shape = o.frameShape || (o.type === 'rect' ? 'rect' : 'shape');
                const rect = (shape === 'rect' || (o.type === 'rect' && !shape)) && !radius && !o.angle && !o.clipPath;
                frames.push({ x: b.left, y: b.top, w: b.width, h: b.height, rect });
                if (rect) { masks.push(''); continue; }
                const m = ser(o);
                if (o.type !== 'image') Object.assign(m, { fill: '#000000', stroke: null, strokeWidth: 0, opacity: 1, shadow: null });
                else Object.assign(m, { opacity: 1, shadow: null, filters: [] });
                masks.push(await API.renderJSON({ version: pageJSON.version, objects: [m] }, { fmt: 'png', transparent: true }));
            }

            const textsOut = all.filter(isRoleText).map((o) => {
                const b = o.getBoundingRect(true, true);
                const sc = o.scaleY || 1;
                const raw = String(o.text || '');
                return {
                    role: o.deRole, x: b.left, y: b.top, w: b.width, h: b.height,
                    font: o.fontFamily || 'Poppins', weight: weightOf(o.fontWeight), italic: o.fontStyle === 'italic',
                    size: (o.fontSize || 40) * sc, fill: fillColor(o.fill),
                    align: o.textAlign === 'justify' ? 'left' : (o.textAlign || 'center'),
                    line_height: o.lineHeight || 1.16,
                    upper: raw === raw.toUpperCase() && /[A-Z]/.test(raw),
                    stroke: o.stroke && o.strokeWidth ? fillColor(o.stroke) : '', stroke_width: (o.strokeWidth || 0) * sc,
                    shadow: o.shadow ? { color: o.shadow.color || 'rgba(0,0,0,0.5)', x: (o.shadow.offsetX || 0) * sc, y: (o.shadow.offsetY || 0) * sc } : null,
                    highlight: o.textBackgroundColor || o.backgroundColor || '',
                };
            });
            const thumb = await API.renderJSON(pageJSON, { fmt: 'jpg', mult: 400 / W, quality: 0.8 }).catch(() => '');
            return { W, H, below, above, masks, spec: { frames, texts: textsOut }, thumb };
        }

        $('atPublishGo').addEventListener('click', async () => {
            const btn = $('atPublishGo');
            const err = (m) => { $('atPubError').innerHTML = '<div class="at-err">' + String(m).replace(/</g, '&lt;') + '</div>'; };
            $('atPubError').innerHTML = '';
            const name = $('atName').value.trim();
            if (!name) return err('Please enter a template name.');
            if (!summary().roles.main) return err('Select a text on the page and set its type to “Main title” — each pin\'s title goes there.');
            btn.disabled = true; btn.textContent = 'Publishing…';
            try {
                const ex = await exportTemplate();
                const tags = [...document.querySelectorAll('#atTags input:checked')].map((c) => c.value)
                    .concat($('atTagCustom').value.split(',').map((s) => s.trim()).filter(Boolean));
                const fd = new FormData();
                const fields = {
                    action: 'publish', id: Math.abs(API.design.id || 0), name, priority: $('atPriority').value, category: $('atCategory').value,
                    tags: tags.join(','), status: $('atActive').checked ? 'active' : 'inactive', width: ex.W, height: ex.H,
                    json: JSON.stringify(API.serialize()), spec: JSON.stringify(ex.spec), below: ex.below, above: ex.above,
                    masks: JSON.stringify(ex.masks), thumb: ex.thumb || '',
                };
                Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
                const res = await fetch('ajax-pin-templates', { method: 'POST', body: fd, credentials: 'same-origin' });
                const r = await res.json();
                if (!r.ok) throw new Error(r.error || 'Publish failed.');
                API.design.id = -r.id;
                history.replaceState(null, '', 'pin-template-editor?id=-' + r.id);
                $('deTitle').value = name;
                $('deSaveState').textContent = 'Published';
                $('atPubPreview').src = r.preview;
                $('atPubDone').hidden = false;
                btn.textContent = '✓ Published';
            } catch (e) {
                err(e && e.message ? e.message : 'Publish failed. If you used a photo from another website, upload it instead (the page can\'t be exported otherwise).');
                btn.disabled = false; btn.textContent = '🚀 Publish now';
            }
        });
    }
    if (window.DE_API) start(); else document.addEventListener('de:ready', start, { once: true });
})();
