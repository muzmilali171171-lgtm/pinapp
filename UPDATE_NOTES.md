# Update — Auto Article batches run in parallel

Every Auto Article batch now runs on its own: a new batch starts writing / publishing the moment it
is created, and never waits for older batches (the same user's or other users') to finish.

1. New cron/article-worker.php: one background worker per batch (started automatically on batch
   create, by cron/article-scheduler.php, the built-in runner and page-load ticks). Up to 10 batches
   run at the same time (define ARTICLE_MAX_PARALLEL_BATCHES in config.php to change it).
2. A per-batch lock (uploads/.article_batch_ID.lock) makes sure only one process works on a batch.
3. The cron / runner fallback loop now goes round-robin across batches (one step per batch in turn)
   instead of always finishing the oldest batch first.
4. "Process Now" uses the same lock; if the batch's worker is already on it, it just says so.
Upload the changed files: includes/auto_article_functions.php, includes/pin_publisher.php,
cron/article-worker.php, user/ajax-create-article-batch.php, user/ajax-process-batch-now.php,
user/ajax-tick.php. No database changes.

# Update — boards, overdue pins, gaps, publisher

After uploading, open /migrate.php once (new columns are also added automatically on first use).

Cron (VPS, every minute is safe):
    * * * * * php /path/to/public_html/cron/scheduler.php >/dev/null 2>&1

1. Bulk Scheduler: fixed "Please name the new board" / "Please select or create a board" when pins
   have their own board (Multiple Boards with AI, "Board for this pin" select/create).
2. Overdue pins (time already passed) publish immediately on save: Single pin, Bulk pin,
   Website to Daily Pin, Auto Article pins, Classic Wizard (on approve), Pinterest Analytics regenerate.
3. Website to Daily Pin: gap in Minutes or Days, First Publish Day, First Pin of the Day At.
4. Auto Article: Cloudflare 0.2 option removed (Budget / High / Ultra only); pin gap in Minutes or
   Days; separate "Select Category" for pin images (Auto = article's category).
5. Classic Wizard: gap between pins of the same page in Minutes or Days.
6. New includes/pin_publisher.php: atomic claim (no double publish), on Pinterest 2787/2786/5xx
   retries at once by uploading the image directly, then backs off 2/5/15/30 min (max 5 attempts).
7. cron/scheduler.php: lock file (no overlapping runs), stuck "processing" pins recovered after 10 min.
8. Admin → Scheduler "Retry" now resets attempts so the pin is really retried.

## Update 2 — Pinterest login connects the account + external image storage

Open /migrate.php once after uploading. The new .htaccess rule (2b) is needed only if you turn on
"Remove the hosting copy after N days" (Apache/LiteSpeed; on Nginx add an equivalent try_files → /media.php?p=$uri).

1. Login with Pinterest now also saves that Pinterest account as fully connected (tokens, status
   'connected') — it appears under Pinterest Accounts right away. The login account is always allowed
   as the user's first account; the plan limit still applies to extra accounts.
2. Admin → Storage Settings:
   - "Store all image data on external storage" (default ON).
   - Images: Pin images / Article images / Storage uploads / Designs — each ON by default.
   - Every image goes to the first active R2 / S3 / B2 account with room, next account if one fails.
   - No account, all accounts full, or an error → stays on hosting; reasons + errors listed on the page
     (per-account "Last error", "Storage Errors" table, Status counts).
   - "Test" button per account, editable Public Base URL, "Move existing images now".
   - Optional: remove hosting copy after N days (0 = keep, default).
3. Pinterest and WordPress receive the external image URL when available.
4. cron/scheduler.php also moves new images each minute and does the hourly hosting cleanup.

## Update 3 — Design editor: pages, bulk "Use This Design", crop, import, PDF/SVG

1. Canva-style pages: each page has a bar with its number, a title, ↑ Move up, ↓ Move down,
   ⧉ Duplicate, 🗑 Delete and ＋ Add page; "+ Add page" under the last page. Click any page to edit it.
2. Use This Design → Bulk Pin Scheduler: every chosen page becomes a pin row (page title = pin title).
   With several pages you choose All / Current / Custom. Plans without Bulk Scheduler: one page still
   goes to the single-pin page.
3. Download: PNG, JPG, PNG 2×, PDF (all pages in one file), SVG (fonts linked), JSON design file;
   pages: Current (single), All, or Custom select. Several PNG/JPG/SVG pages download as one .zip.
4. Crop for photos: ✂ Crop button or double-click the photo → drag corners → Done (Enter) / Esc.
5. 📂 Import: opens a .json design (new multi-page format, old downloads and plain Fabric JSON).
   Blank design → replaced; otherwise the pages are added after the current page (resized to fit).
6. 100 more text styles (114 total) and 100 more Google Fonts (138 total, loaded only when used).
Saved designs now store all pages; old single-page designs open as before.

## Update 4 — Design editor Elements (Shapes · Graphics · Emoji · 3D · Frames) + admin element types

User side (Design editor → Elements), tabs with sub categories, search and "See all":
- Shapes (160): Lines, Basic Shapes, Polygons, Arrows, Stars, Flow Charts, Hearts, Speech Bubbles,
  Clouds, Banners, Teardrops, Cogs, Square Stars, Organic Shapes.
- Graphics (238) in 12 categories, 3D (101) in 7 categories — built in (assets/elements, Microsoft
  Fluent UI Emoji, MIT licence: assets/elements/LICENSE-fluent-emoji.txt).
- Emoji (581) in 9 categories.
- Frames (24): photo frames (square, portrait, landscape, wide, strip, rounded) and shape frames
  (circle, oval, arch, triangle, diamond, pentagon, hexagon, octagon, star, heart, blob, cloud,
  ticket, scalloped, leaf). Drag a photo onto a frame to fill it.
Admin → Canva → Add Elements: 1) select element type, 2) select sub category (or add a new one),
3) add file(s) and upload. Filter by type and sub category; change type/sub category per element.
Run /migrate.php once (adds design_elements.element_type; old uploads become "Graphics").

## Update 5 — 100 arrow graphics, colour panel, graphic colours & flip
- Graphics → Arrows: 100 new arrows (chevrons, block, outlined, dart, brush/gradient, curved, loop,
  wave, zigzag, spiral, elbow, U-turn, circle, dashed/dotted/hand-drawn…) — original SVGs in
  assets/elements/graphics/arrows. Admin → Add Elements now also lists all built-in elements.
- Colour panel: clicking any colour (text, fill, border, outline, shadow, highlight, background,
  graphic colours) opens a side panel: custom picker + hex, "Your used colours", 30 default colours
  and 20 gradients (for fills, text, background and graphics).
- Graphics (SVG — built-in or admin uploads): each colour in the graphic is shown as a swatch you can
  change; Flip H / Flip V. PNG/WEBP graphics (3D, admin uploads): 🎨 Colour tint with strength
  + "Original colours", and Flip.

## Update 6 — Canva-like selection, drag photos into frames, transparent download
- Clicking anywhere outside the page (grey area) deselects the selected element (text / image / shape).
- Drag a photo that's already on the page onto a frame → it drops into the frame (frame glows while
  over it). Dragging from Uploads / Photos onto a frame still works; dropping on a filled frame replaces its photo.
- Download → "Transparent background" (PNG, PNG 2×, SVG): no background colour, image or gradient.

## Update 7 — Analytics → Template Tracking
- New menu: Analytics → Template Tracking. Pick the Pinterest account; range Lifetime / Last 30 days /
  Last 90 days / Custom; Min. pins; filter by source. "Showing stats for N pins across N templates".
- Columns (sortable): Template name, Total Pins, Total Views, Avg. Views, Total Clicks, Avg. Clicks,
  Avg. CTR, Avg. Save %, Link (view the pins), Colour stats.
- Colour stats (per template, or for all pins): Main colour, same columns, Link.
- Tracks Image Styles & Templates (every compose_pin_image() result is fingerprinted), Classic Wizard
  templates (incl. older pins) and custom designs (Design editor → Use This Design).
- New tables pin_image_templates + pin_template_links (run /migrate.php once; also created automatically).

## Update 8 — Scheduler fixes (pins stuck in "Pending")
- Cron: CyberPanel needs the PHP program in the command, not only the file path. Use (every minute):
    /usr/local/lsws/lsphpXX/bin/php /home/SITE/public_html/cron/scheduler.php >/dev/null 2>&1
  or:  curl -s "https://SITE/cron/scheduler.php?key=KEY" >/dev/null 2>&1
  Admin → Scheduler shows the exact command, the key, a health check and "Run scheduler now".
- Fallback: if cron hasn't run for 3 minutes, due pins are also published from normal page loads.
- MySQL now uses the same time zone as PHP (config.php), so NOW() and scheduled times always match.
- Fixed: plans with "Unlimited" pins / accounts / websites / uploads were treated as a limit of 0.
- Fixed: an overdue pin whose new board wasn't created yet stayed pending — the board is now created at once.
- Fixed: boards of a disconnected account left their pins pending forever — they now fail with a clear reason.
- Scheduler log: uploads/logs/scheduler.log (shown in Admin → Scheduler).
- config/config.php is NOT in this zip — keep your own.

## Update 9 — Pins publish automatically (no manual "Run now")
- Admin → Scheduler → "⚙ Add cron job automatically": writes the correct every-minute cron line into
  this site user's crontab (replaces an old/broken line such as a bare file path). "🧪 Test cron command"
  runs it exactly like cron does and shows the output. Status shows: cron working / wrong command /
  not set up, cron service running/stopped, PHP CLI path.
- Built-in runner (cron/runner.php): while no cron job works, a background process publishes due pins
  every minute and restarts itself every ~4 minutes; started automatically from page loads and Admin.
  It stands by as soon as a real cron job runs. Can be turned off in Admin → Scheduler.

## Update 10 — Admin pins/articles overview, article cron, 24-hour start time
- User: Website → Daily Pin schedule view: new "Publish date & time" column (every pin of every page).
- User: "First Pin of the Day At" is a 24-hour dropdown (Website → Daily Pin and Classic Wizard).
- Admin dashboard: Articles scheduled / published / failed, Websites connected.
- Admin → All Pins Scheduled → Scheduler & Cron / All Pins (filters: Published, Scheduled, Failed;
  Today, Last 30 days, Lifetime, Custom; search). Cards: image, user, Pinterest account, source,
  scheduled/published date, View pin.
- Admin → Articles Schedule → Queue & Cron (health, add cron automatically, test, run now, upcoming
  queue, failed articles with full error + Retry) / All Articles (filters, stats, pin stats, View post).
- "Add cron job automatically" now installs both jobs: pins every minute, articles every 2 minutes.
- cron/article-scheduler.php: lock (no double processing with page loads), URL trigger with key, heartbeat.

## Update 11 — AI Article Data + article length + recipe nutrition
- Admin → AI Article Data → Ideas Articles (50 starter categories) / Recipes & Food Articles (30):
  select or create category + sub category, add one or many articles (title + content), "Add now";
  everything added is listed below (filter by category, search, delete). Create/delete categories.
- Before writing, Auto Article finds the 2 closest reference articles (title + category match), studies
  their structure/depth/tone (never copies text), checks the site's earlier titles to take a fresh angle,
  works out the niche + reader intent, then writes a detailed, reader-first article.
- User → Auto Article → Step 3 "Article Length": Auto / Minimum words (300–8000) / Random long-form
  (1,800–3,500). Short drafts get one expansion pass to reach the length.
- Recipes now include Why This Recipe Works, servings/prep/cook time, Storage & Make-Ahead,
  Nutrition Information (per-serving estimate) and FAQs.

## Update 12 — How-to buttons, grouped menu, long-form articles that don't fail
- User pages Bulk Scheduler, Auto Website to Daily Pin, Auto Article, Classic Wizard: "📘 How to use …"
  button at the top-right (opens /pinterest-bulk-scheduler, /ai-blog-to-pin-create-schedul,
  /ai-bulk-article-write-publish-pins-schedul, /blog-to-pin in a new tab). ✕ hides it (a small "?" stays).
  Links are in user/includes/user-header.php ($howToLinks); define HOWTO_BASE_URL in config.php to point
  them at another domain.
- User sidebar grouped: Pin Scheduling · Automation · Create · Analytics · Websites · Account · Help.
- Auto Article (fixes "Outline: Could not parse the outline response" on 18+ idea titles):
  * outline gets room for every idea; reasoning "thinking" text is removed; cut-off JSON is repaired;
    if it still can't be read, a simple numbered-list outline is requested instead.
  * long articles are written in parts (intro · ideas in groups of 6 · FAQs + wrap-up), one part per
    run, progress saved after each part — no time-outs, nothing lost if a run is killed.
  * a part that gets cut off is split in two automatically; missing ideas are detected.
  * failed steps are retried 3 times (a few minutes apart) before the article is marked failed.
  * AI requests wait up to 5 minutes for big answers (was 90 s); cut-off recipe answers are continued.

## Update 13 — Articles stuck in "Writing (in parts)" / "Imaging" fixed
- Bug: the queue query skipped every article for 3 minutes after each step (a NULL comparison), so
  a long article advanced one part every few minutes and 20+ images took over an hour. Fixed.
- Bug: page loads (ajax-tick, 90 s limit) and "Process now" (150 s) ran article steps inside web
  requests; a long part or slow image was killed half-way and the article kept restarting that step.
  Page loads now only start a background article runner (cron/runner.php?job=articles, no time limit);
  "Process now" keeps running even if the browser stops waiting.
- Built-in runners are now two jobs: pins (every minute) and articles (step after step). Each stands
  by while its cron job works. Admin → Articles Schedule shows the article runner status.
- Not enough image credits now stops the article with a clear message (it waited in "Imaging"
  forever); Retry continues from the images already made.
- Writing progress is saved safely (invalid UTF-8 can't wipe it; columns are LONGTEXT); a crashed
  part is picked up again after 6 minutes (was 10).
