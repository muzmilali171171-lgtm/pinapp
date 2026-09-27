# Brand Color Palette feature — what was added/changed

An optional "Use my brand color palette" toggle was added below **Image
Style** in all three AI pin-image places. Left off (default), everything
behaves exactly as before — the AI keeps picking its own colors. Turned on,
the user picks 3 or 4 brand colors (used for the pin's headline text),
plus separate Website Text/Background colors and CTA Text/Background
colors — these override the AI's automatic colors on every generated pin.

## Where it was added
- **Bulk Pin Scheduler** (`user/bulk-schedule.php`) — under "Create Pin
  Image with AI" → Image Style. Applies per-image, sent with every
  generate/retry call to `ajax-generate-pin-image.php`.
- **Auto Website to Daily Pin** (`user/auto-website-create.php`) — under
  its Image Style select. Saved once per schedule (batch) and reused for
  every page/pin the schedule generates.
- **Auto Article** (`user/auto-article-create.php`) — under the Pin Auto
  step's Image Style select. Saved once per article batch and reused for
  every article's auto-pins.

## Edited files
- `includes/ai_functions.php` — added `pin_hex_to_rgb()` and
  `pin_normalize_color_palette()`; `compose_pin_image()` now takes an
  optional 7th `$colorPalette` argument (JSON string or array) and passes
  it into every pin-style renderer (all 13 Image Styles), which use it to
  override the headline multi-color text, the website bar, and the CTA
  badge — falling back to the existing automatic colors whenever the
  palette is missing/disabled/invalid.
- `user/ajax-generate-pin-image.php` — reads `$_POST['color_palette']`.
- `user/ajax-create-website-pin-batch.php`, `includes/website_pin_functions.php`
  — new `color_palette` column on `website_pin_batches` (JSON), read back
  at both pin-generation call sites.
- `includes/auto_article_functions.php` — reads `color_palette` out of
  the existing `pin_settings_json` column (no schema change needed there).
- `database/schema.sql`, `migrate.php` — added the `website_pin_batches.color_palette`
  column (`LONGTEXT DEFAULT NULL`), via the same safe
  `add_column_if_missing()` pattern as other columns.
- `assets/css/style.css` — small style rule so `<input type="color">`
  pickers look right inside `.form-row`.

## One-time step after upload
Visit `/migrate.php` once in the browser so the new
`website_pin_batches.color_palette` column gets added. It only ever adds
columns that don't already exist, so it's safe to run again even if you've
run it before for a previous update.

---

# Tutorials feature — what was added/changed

## New files
- `includes/tutorial_functions.php` — all DB/helper logic (YouTube ID parsing,
  thumbnail resolution, save/delete/list, view counter).
- `admin/tutorials.php` — Admin → **Tutorials**: add/edit/delete videos, set
  video link, title, video length (e.g. `8:36`), priority/order, thumbnail
  upload (optional — auto-pulled from YouTube if left blank), and a
  "Set as main / featured video" toggle (only one video can be featured —
  it becomes the big "Quick Start" card, same as the crash-course card in
  your screenshot).
- `tutorials.php` (site root) — the public **/tutorials.php** page: Quick
  Start card for the featured video, then the full list of all active
  tutorials. Clicking any thumbnail/row opens the YouTube video in an
  in-page modal (falls back to opening the link in a new tab if the URL
  isn't a recognizable YouTube link).
- `tutorials-view.php` — tiny endpoint the page pings to bump the view
  counter shown in the admin table.

## Edited files
- `database/schema.sql` — added the `tutorials` table
  (`CREATE TABLE IF NOT EXISTS`, so it's picked up automatically).
- `admin/includes/admin-header.php` — added the **Tutorials** link to the
  admin sidebar.
- `user/includes/user-header.php` — the two existing placeholder
  "Tutorial" links (topbar button + sidebar item, previously pointing at
  `coming-soon.php`) now point at `../tutorials.php` (opens in a new tab).
- `index.php`, `about.php`, `contact.php`, `pricing.php`, `terms.php` —
  added a **Tutorials** link to the public site header/nav.
- `assets/css/style.css` — added the Tutorials page styles and video modal.

## One-time step after upload
Visit `/migrate.php` once in the browser (same as any other schema update)
so the new `tutorials` table gets created. It only ever does
`CREATE TABLE IF NOT EXISTS`, so it's safe to run even if you've run it
before — it won't touch existing data.

## Notes
- Any YouTube URL format works as the video link: full `watch?v=`,
  `youtu.be/...`, `youtube.com/embed/...`, or `youtube.com/shorts/...` — the
  video ID and default thumbnail are extracted automatically.
- Duration/"video length" is just a free-text label (e.g. `8:36`) — it's for
  display only, not read from YouTube.
- I added the Tutorials link to the 5 main site pages (Home, About, Contact,
  Pricing, Terms). The 20+ nested `free-tools/*/index.php` pages share the
  same header markup but weren't touched — say the word if you'd like that
  link added there too.
