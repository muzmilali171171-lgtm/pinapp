# Pinterest Auto Scheduler and bulk Blog writer

A self-hosted PHP + MySQL app: users connect their own Pinterest account via
OAuth (no API keys typed by them), schedule pins with an image/title/
description/link/board/date-time, and a cron job publishes them automatically.

## 1. Upload to Hostinger

Upload every file/folder in this zip to your domain's `public_html`
(or a subfolder, e.g. `public_html/pin-scheduler`) via Hostinger's File
Manager or FTP.

## 2. Create a database

In hPanel → **Databases → MySQL Databases**, create a database and a
database user, and note the database name, username, password and host
(usually `localhost` on Hostinger).

## 3. Run the installer

Visit `https://yourdomain.com/install.php` in your browser. Fill in the
database details and your site URL, and submit. This will:

- Create the database tables automatically (no manual SQL needed)
- Write `config/config.php` for you
- Create the default admin login: **username `admin`, password `1234`**,
  email `admin@mail.com`

**Delete `install.php` after this step** — it's a security risk to leave it
on a live server.

## 4. Create a Pinterest Developer App

1. Go to https://developers.pinterest.com/apps/ and create an app.
2. Under your app's settings, add this exact **Redirect URI**:
   `https://yourdomain.com/oauth/pinterest-callback.php`
3. Copy the app's **Client ID** and **Client Secret**.

## 5. Enter Pinterest credentials in the admin panel

Log into `https://yourdomain.com/admin/login.php` (admin / 1234), open
**Pinterest Settings**, and paste in the Client ID, Client Secret, and the
Redirect URL from step 4.

## 6. Set up the cron job

In hPanel → **Advanced → Cron Jobs**, add a job that runs every 5 minutes:

```
*/5 * * * * php /home/USERNAME/domains/yourdomain.com/public_html/cron/scheduler.php
```

(Hostinger shows you the exact server path to use — copy it from there.)
This is what actually publishes pins on schedule; without it, pins will
stay "pending" forever.

## 7. You're live

- Public site: `https://yourdomain.com/` — homepage with signup/login
- User signup/login: `/auth/register.php`, `/auth/login.php`
- User dashboard: `/user/dashboard.php`
- Admin panel: `/admin/login.php`
- Privacy Policy: `/privacy-policy.php`

## How it works

- **Admin Panel** — you configure the Pinterest App (Client ID/Secret/
  Redirect URL) once. You also see all users, the full pin queue, failed
  jobs (with retry), and logs (OAuth events, API responses, publish history).
- **User Panel** — a user signs up, clicks "Connect Pinterest," authorizes
  on Pinterest's own site, and is redirected back with their account
  connected. They never see or enter any API key/secret.
- **Scheduler** — `cron/scheduler.php`, run by your Hostinger cron job,
  finds pins whose `publish_at` time has passed and are still `pending`,
  refreshes the Pinterest access token if needed, and calls the Pinterest
  API to publish the pin. Failed attempts are retried up to 3 times before
  being marked `failed` (visible + retryable from the admin Scheduler page).

## Notes on image hosting

Pins are created using Pinterest's `image_url` media source — meaning the
image you upload is stored in `/uploads/pins/` on your own server, and its
public URL on your domain is handed to Pinterest to fetch. Because of this,
`APP_URL` in `config/config.php` must be the real, publicly reachable URL of
your site.

## AI Article Writer (new)

This update adds an AI-powered article writer that can auto-publish to WordPress.

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once in your browser — it safely adds the
   new tables (`ai_providers`, `article_settings`, `websites`, `articles`) without touching
   your existing data. Delete `migrate.php` afterwards.

### If this is a fresh install

`install.php` already creates every table, including the new ones — just follow the normal
install steps in the section above.

### Setup

1. **Admin → AI Api**: paste in API keys for whichever text/image providers you want to use
   (ChatGPT, Claude, OpenRouter, DeepInfra, Google for text; DeepInfra for images — Google image
   generation isn't implemented yet). Also add a free [Pexels API key](https://www.pexels.com/api/)
   if you want the "web image with credit" option to work.
2. **Admin → Article Write**: pick your default text model and default image model from the
   providers you just added keys for.
3. **User → Add Websites**: each user downloads the included WordPress plugin
   (`wp-plugin/pinscheduler-publisher`, also downloadable in-app from that page), installs +
   activates it on their WordPress site, copies the Site URL + Site Key from
   **WP Admin → Settings → PinScheduler Publisher**, and pastes them in to connect the site.
4. **User → Write Article**: enter a keyword/title (or a competitor URL for inspiration), review
   the generated outline, choose an image source per section (none / web with credit / AI
   generated), generate the full article, edit it in the built-in rich text editor, set a
   featured image (upload or AI-generate), pick the website/category/tags, and publish.

### Notes & limitations

- **Video generation** (DeepInfra/Google) — the settings UI is there to save API keys, but the
  actual generate button isn't wired up yet; the API shapes need to be verified before shipping
  video generation.
- **Google image generation** isn't implemented yet (it needs a different Vertex AI-style setup
  than a simple API key) — use DeepInfra (FLUX-1-schnell) for AI images for now.
- The article editor is the standard CKEditor 5 rich text editor — it does **not** include
  real-time multi-user collaboration/track-changes (that's a separate paid CKEditor plan); the
  license key you supplied is the free self-hosted tier.
- The WordPress plugin authenticates with a random per-site "site key" (like an API key) sent
  in a custom header — no WordPress username/password is ever shared with this app.

## Security notes

- `config/.htaccess` blocks direct web access to `config/config.php`.
- `uploads/.htaccess` blocks PHP execution inside the uploads folder.
- Change the default admin password after your first login (there's no
  in-app "change password" screen yet — update the `admin_users` table
  directly, or ask me to add one).

## Bulk Pin Scheduler (new)

This update adds a bulk pin scheduling workflow, on top of the existing single-pin "Create Schedule" page.

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once in your browser — it safely adds the new
   columns (`pinterest_boards.board_description`/`status`, `scheduled_pins.board_row_id`/`alt_text`/
   `source`/`batch_id`, `article_settings.pin_text_provider`/`pin_text_model`) without touching your
   existing data. Delete `migrate.php` afterwards.

### If this is a fresh install

`install.php` already creates every table with these columns included — just follow the normal
install steps above.

### What's new

- **User → Bulk Scheduler**: upload many pin images at once ("Upload Bulk Pin Images"), or add
  rows manually. Each row has its own optional title/description/link/alt text — leave any of
  them blank and it falls back to the Global Title/Description/Link you set once for the whole
  batch. Set a first-pin publish time + interval and click "Apply times to all rows" to
  auto-schedule every pin sequentially (each row's time stays editable afterwards). Rows can be
  reordered by dragging the handle, or removed with the trash icon.
- **Bulk Insert boxes**: paste titles / descriptions / links / alt text one per line and click
  Insert to fill the rows top-to-bottom in one go.
- **Create with AI**: enter your main keywords one per line (one pin per keyword), choose
  "with hashtags" or "without", and the configured AI model writes a title + description for
  each pin — descriptions always end with a call-to-action to visit your Global Link/website.
- **Create a new board from either scheduler**: pick "+ Create New Board", name it and add a
  description. The board is **not** created on Pinterest immediately — the cron job creates it
  automatically about 5 minutes before the first pin scheduled to that board is due to publish,
  then publishes the pin normally. This also works from the single-pin "Create Schedule" page now.
- **Admin → Bulk Pin Scheduler**: choose which AI text model writes bulk pin titles/descriptions
  (falls back to the Article Write default model if left unset), and see an overview of every
  user's bulk batches (pin counts by status, board, date range) with an option to cancel a
  batch's still-pending pins.

### Notes & limitations

- A pin whose board hasn't been created on Pinterest yet is simply skipped by the cron job
  (without counting as a failed attempt) until the board is ready; if board creation fails 3
  times, the board and its still-pending pins are marked failed.
- Bulk batches are capped at 200 pins per "Schedule All Pins" click — split larger campaigns
  into multiple batches.

## Bulk Pin Scheduler v2 (new)

A larger redesign of the user-side Bulk Scheduler, built on top of everything above.

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once in your browser — it safely adds the
   `pin_batches` table plus `scheduled_pins.tags`/`keywords`/`product_link`, without touching
   your existing data. Delete `migrate.php` afterwards.

### What's new

- **Batches**: a new "Batches" sidebar page lists every bulk batch (draft, scheduled, or
  completed) with the Pinterest account, board, publish progress bar, created/first-pin/last-pin
  dates, and filter tabs. Draft batches can be edited or deleted; scheduled/completed batches
  have a **View Full** page listing every pin in the batch, filterable by published/unpublished,
  showing days left until publish, with an inline **Edit** + Save for any pin that hasn't
  published yet.
- **Batch name**: name a batch before scheduling it, shown throughout the Batches list.
- **Account & Board redesign**: after picking an account, choose **Select Board** (searchable
  grid/list of existing boards) or **Create Board** — either type a name + description manually,
  or **Create with AI** from a single keyword/topic.
- **Scheduling modes**: the original fixed-interval mode, plus a new **Pins Per Day** mode —
  set pins/day, the gap in hours between them, a start date and a first-pin time of day, and
  every row's publish time is computed automatically (e.g. 3 pins/day, 8 hours apart).
- **Tags & Keywords**: a tag-pill input (Tags) and a comma-separated Keywords field, both applied
  automatically to every pin in the batch.
- **Create with AI**: now also generates alt text and keywords per pin (inserted into each row),
  and accepts optional custom instructions to steer the writing style.
- **Product Tags**: paste one product link per line — assigned to pins in order — or check "use
  only the first link" to apply a single product link to every pin. A pin's product link becomes
  its outbound destination when it has no other link set.
- **CSV upload**: upload a CSV (`imageUrl` required; `title`, `description`, `outboundURL`,
  `altText`, `baseTitle`, `baseDescription`, `boardName`, `scheduleDate` optional) — remote images
  are downloaded automatically, and any row missing a title/description gets one AI-written from
  its `baseTitle`/`baseDescription`/`altText` hint. A **Sample CSV** download and an in-app guide
  are one click away from the upload button.
- **Autosave drafts**: the whole builder (settings + rows) autosaves every ~25 seconds to a draft
  batch, resumable later from Batches → Edit — nothing is lost if you navigate away.

### Notes & limitations

- Pinterest's public API has no generic "tag a product on a pin" endpoint for arbitrary retailer
  links (that's a separate Shopping/catalog feature); a Product Tag is applied as the pin's
  outbound link instead, and keywords are folded into the pin description (where Pinterest
  actually indexes them), since there's no dedicated keywords field on a pin.
- CSV import downloads each `imageUrl` one at a time server-side, so very large CSVs may take a
  little while — the modal shows progress as it goes.

## Bulk Pin Scheduler v3 (new)

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once — it adds the new `cloudflare_accounts` /
   `cloudflare_usage` tables, `users.credits` (existing users get 1000 automatically), and
   `article_settings.pin_image_*` columns, without touching existing data. Delete it afterwards.
3. The **PHP GD extension** is required for AI pin-image generation (near-universal on shared
   hosting; if unsure, ask your host or check `phpinfo()`).

### Panel redesign

- The left settings panel is now a **collapsible accordion**: Batch Name stays fixed at the top,
  then **1. Account, Board & Scheduling**, **2. Bulk Insert**, **3. Create with AI**,
  **4. Global Defaults**, **5. Tag Product**, **6. Create Pin Image with AI**.
- Each pin row on the right now has its own **Board** dropdown (defaults to the batch's board,
  but any pin can be sent to a different existing board) and its own **Tag Product** button
  (opens a modal with "Search Pins" — a placeholder, since Pinterest's API doesn't support
  catalog search — and "Use a Link", which is fully working).

### Create Pin Image with AI

A full AI pin-image generator, right inside the Bulk Scheduler:

- **Titles or Links, one per line** — a link's page title (`og:title` or `<title>`) is fetched
  automatically and swapped into the list in place of the link.
- **Size**: 1000×1500 (2:3), 1080×1920 (9:16), 1000×2100 (1:2.1), or 1000×1000 (1:1).
- **Website** (optional) is printed at the bottom of the image; **CTA** is Auto (a short phrase
  picked automatically based on the title), Custom text, or None.
- **Custom Prompt** (optional) — leave blank and the tool builds an "attractive Pinterest
  background" prompt itself, picks from a few curated color palettes, and (if CTA is on) an
  automatic CTA badge.
- **Single or Collage** (3–6 images tiled into a grid) and a **Budget/High/Ultra** quality tier
  (2/3/4 DeepInfra inference steps — 0.2/0.7/1 credit/pin), unless the admin has switched the
  whole feature to the free Cloudflare model (flat 0.2 credit/pin).
- Each image is added to the Pins list on the right **as soon as it's ready**, not all at once.
  A failing image is retried up to 3 times (immediately, then +3s, then +4s); if it still fails,
  an **empty pin row is still added** (so ordering/count isn't lost) and the status list below the
  button shows that line in red with a **Retry** button.
- Credits are only deducted on a *successful* image (never charged for a failed attempt).

**Design note**: "automatically attractive" design is implemented as a heuristic — a handful of
curated color palettes and a CTA-phrase picker (with light keyword matching, e.g. "recipe" →
"Get the Recipe") — rather than true AI-driven layout judgment, which isn't something a script can
genuinely do. It produces clean, legible pins (title + CTA badge + website footer over the
generated background) but isn't a creative-AI design engine.

### Credits

- Every user gets **1000 credits** by default (shown in the sidebar); admins can set any user's
  balance from **Users**. AI pin images are the only feature that currently spends credits.

### Admin: Models & AI Setting By Features

- **Models** (renamed from "AI Api") now holds only **API keys** — Text Platforms (ChatGPT,
  Claude, OpenRouter, DeepInfra, Google) and the Image Platform (DeepInfra) — plus a new
  **Cloudflare Accounts** manager: add unlimited accounts (Worker URL, API key, daily image
  limit), edit, enable/disable, or delete them. Once one account hits its daily limit, generation
  automatically rolls over to the next active account, and so on. The Video Models and Image
  Search (Pexels) sections have been removed.
- **AI Setting By Features → Bulk Pin Scheduler** (new collapsible nav group) is where you pick
  *which* model each feature actually uses: a Text Platform/Model pair (with the exact model
  lists you specified per platform) for pin writing, and an Image Generation Mode — DeepInfra
  Budget/High/Ultra, or the free Cloudflare model — for AI pin images.

### Notes & limitations (v3)

- The **Cloudflare Worker contract** is a best-effort guess (POST `{"prompt": "..."}`, expecting
  either raw image bytes or `{"image": "<base64>"}` back) since no worker source/API spec was
  provided — if your worker's request/response shape differs, adjust `ai_generate_pin_image_raw()`
  in `includes/ai_functions.php` (the `cloudflare` branch) to match it.
- A collage of several images, each retried up to 3 times with deliberate delays, can legitimately
  take a couple of minutes; very strict hosting timeouts may need a larger `max_execution_time` or
  smaller collage counts.
- Per-pin board selection only offers **existing** boards for the chosen account — creating a new
  board is still a batch-level (Account, Board & Scheduling) action.

## Bulk Pin Scheduler v4 (new)

### Fixes

- **Cloudflare images now work with your actual worker.** It returns a full data URI
  (`"image": "data:image/jpeg;base64,..."`), not bare base64 — the code was decoding the whole
  string including that prefix. Fixed to strip the prefix first.
- **DeepInfra quality tiers now actually differ.** Switched from DeepInfra's OpenAI-compatible
  endpoint (which silently ignores `num_inference_steps`) to their native inference endpoint,
  which honors it. Budget/High/Ultra now genuinely run 2/3/4 steps.
- **Text no longer gets baked into the AI-generated background.** The image prompt now explicitly
  and repeatedly forbids text/letters/words/watermarks, plus a real `negative_prompt` is sent to
  DeepInfra's native endpoint (and folded into the prompt text for the Cloudflare worker, which
  has no separate negative-prompt field).
- **Collages render clearly.** Rebuilt the grid into clean row-based layouts (e.g. 5 images →
  a 3-across row + a 2-across row, the way real Pinterest collage pins are laid out) — every tile
  is fully filled, no gaps or blank cells.
- **Credit cost per quality tier is now admin-editable** (Budget/High/Ultra), under AI Setting By
  Features → Bulk Pin Scheduler, instead of hardcoded 0.2/0.7/1.0.

### Image Style presets

The Create Pin Image with AI panel now has an **Image Style** dropdown with 12 presets plus an
"AI Select Auto" option (picks one from keywords in the title):

- **High Attractive Multi Colored** — the original bold stacked headline, alternating colors,
  bottom bar + CTA badge.
- **Simply** / **Hairstyles Simple** — single white color, simple outline, no bar, small plain
  site text at the bottom.
- **Simple 2** — same multi-color stacked look, but with a grey (`#808080`) outline and a colored
  bottom bar + "Learn More"-style CTA badge.
- **Unique Multi Colored** — a decorative serif display font (Abril Fatface), each line a
  different vivid color, no outline stroke, small script credit line at the bottom.
- **Recipe Food** — two stacked photos with a dark band sandwiched between them, plain white
  title text.
- **Recipe Food 2** — same two-photo sandwich, but a white band with a different vivid color per
  line.
- **Pet Recipe Foods** — a playful yellow "banner" title (script font) + a dark brown subtitle
  banner + a footer bar, in the PetPostHub color scheme. (A simplified take on the illustrated
  brush-stroke/badge style — GD can't easily draw brush textures or icon badges.)
- **Home Decor** — a solid light band across the top with dark bold text, a single clear photo
  filling the rest.
- **Home Decor 2** — like the default style, but single-color text that dominates almost the
  whole canvas, small plain site text, no bar.
- **Fashion Outfits** — single-color stacked text with one italic/script accent line (e.g. a name
  like "Curvy Women"), solid black bottom bar.
- **Fashion Outfits 2** — the same multi-color stacked look as the default style, but vertically
  centered instead of bottom-anchored.
- **Fashion Outfits 3** — built specifically for **Collage** images: a row of photos on top, a
  text band with a circular number badge + bold headline + colored subtitle box, and another row
  of photos on the bottom.

Every style shares the same underlying text-fitting logic, which was hardened while building
this: it now genuinely shrinks the font until the real (untruncated) title fits within the
line-count and width limits, only falling back to an ellipsis if the title still doesn't fit even
at the smallest readable size — a bug where truncation could mask the shrink logic (causing
long titles to cut off unnecessarily) was found and fixed in the process.

### Notes & limitations (v4)

- These are genuinely different templates, not pixel-perfect clones of the reference screenshots —
  each captures the key colors/layout/typography choices of its reference, built with GD (no
  brush-stroke textures, no icon badges).
- `Fashion Outfits 3`'s automatic "number + headline + boxed subtitle" split from a title is a
  heuristic (first ~45% of the words become the headline, the rest go in the box) — it won't
  always land exactly where a human designer would break the line.

## Bulk Pin Scheduler v5

### The real fix for hallucinated on-image text

The recurring "duplicate text baked into the background" bug came from the image prompt itself,
not from a compositing bug: it wrapped the title in quotes and asked the model to "illustrate"
it, and mentioned "Pinterest pin" — all patterns that strongly cue text-to-image models (FLUX
included) to render that phrase as visible on-image text, since so much of their Pinterest-pin
training data has baked-in captions. Fixed by:

- Dropping the quotes and "illustrating" framing entirely, and never calling it a "Pinterest pin"
  in the prompt — it's now framed as a plain, natural photograph.
- Stripping listicle scaffolding ("18", "Ideas", "Tips", "Ways") out of the title before it goes
  into the prompt, since that phrasing is exactly what cues "this is a pin template" rather than
  "this is a photo."
- Every style that places text over a photo now also applies a light darkening scrim to that
  photo region as a second line of defense — `pet_recipe`, `home_decor`, `recipe_food`/`recipe_food2`,
  and the collage strips in `fashion_outfits3` didn't have one before and now do (only
  `high_attractive_multi`/`unique_multi` and similar had it previously).
- DeepInfra gets a real `negative_prompt`; the Cloudflare worker (which has no separate
  negative-prompt field) gets the same "avoid" list folded into its prompt text.

This substantially reduces hallucinated text, but FLUX-schnell (a fast, distilled model) is known
to follow negative instructions weakly, so it isn't a 100% guarantee — the scrim is there as a
backstop for whatever gets through.

### Fashion Outfits 3 (collage) fixes

- The number badge is now smaller and straddles the boundary between the top photo row and the
  text band (half over the photo, half over the band) — matching the reference layout instead of
  floating fully inside the band.
- The gap between the headline and the subtitle box is tight now, matching the reference.
- The bottom photo row now hugs the actual text content instead of sitting a fixed distance down
  — no more large empty white gap when the title is short.

### Long titles are shortened by AI before generating

Very long titles were the root cause of tiny/illegible fonts or ellipsis-truncation on several
styles. Before generating, any title over ~6 words / 40 characters is now sent to the configured
text model to be condensed into a punchier short version (keeping any leading number) — the
shortened title is then used for the image prompt, the overlay text, and reflected back into the
"Titles or Links" box so what you see matches what was generated. If no text model is configured,
or the request fails, the original title is used unchanged (the per-style text-fitting logic
still applies as a fallback).

## Auto Article (new)

A full content pipeline: batches of AI-written, AI-illustrated articles published to WordPress
on a daily schedule, with optional fully-automatic Pinterest pin creation per published article.

### Setup

1. Upload everything and run `migrate.php` once (adds `article_batches`, extends `articles` and
   `scheduled_pins`, adds feature-image model columns). Delete it after.
2. **Re-install the PinScheduler Publisher WordPress plugin** on each connected site — it was
   extended to support custom slugs, meta descriptions, and inline content images
   (`assets/downloads/pinscheduler-publisher.zip`, now v1.1.0). Sites still on the old plugin
   version can still publish, just without those three fields.
3. Set up a new cron job (same idea as the pin scheduler's): `php cron/article-scheduler.php`
   every ~10 minutes. Each article's full pipeline (outline → draft → images → publish →
   optional pin scheduling) involves several AI calls and can take a minute or more, so this
   only processes a few due articles per run rather than the whole queue at once.
4. In admin → **AI Setting By Features → Auto Article Pin**, configure the article text model,
   the article content-image model, the feature-image model, and the pin text/image models
   (the pin ones are shared with Bulk Pin Scheduler — changing either page updates both).

### User side

- **Auto Article → Your Batch**: lists every batch with website, article/pin progress, category,
  Pin Auto on/off, and a Stop/Resume action; **View** shows published/remaining article counts,
  pins published/scheduled, and the full title list with per-article status and a link to the
  live post once published.
- **Auto Article → Create New Batch**: a 4-step wizard —
  1. **Type & Website** — Ideas or Recipe article, which connected WordPress site, its category.
  2. **Titles** — one per line, or paste competitor article links and click **Resolve Links →
     Titles** (fetches each page's real title) then **Rewrite to Your Original Title (AI)** to
     turn them into non-plagiarized originals before anything is queued.
  3. **Images** — feature image size (1200×630 default), idea-image aspect ratio (3:4 default)
     or recipe image count (3 default), and a quality/credit tier.
  4. **Publish** — Publish Now, or **Pin Auto** with its own full settings: Pinterest account,
     pins/day with a monthly ramp-up, pins per article, the gap between that one article's own
     pins (30 days/"1 month" default), board mode (a separate board per article, or AI picks/
     creates one per topic), and all the same pin options as Bulk Pin Scheduler — size, website,
     CTA, the full 12-style image-style picker (+ AI Auto), and collage settings.

### How the pipeline actually runs

For each due article (`daily_count` articles/day, spread across days from batch creation):
outline → full HTML draft (with `{{IMAGE:n}}` placeholders) → the featured image and every
content image generated as **plain, text-free photos** (not pins — no title/CTA overlay) →
a clean, numberless slug and an AI meta description → published to WordPress (category, tags if
enabled, featured image, inline images uploaded to the media library in place of their
placeholders). If the batch is in Pin Auto mode, that article's pins are generated and scheduled
immediately after: image (single or collage, in the chosen — or AI-auto-picked — style),
AI-written title/description/alt/keywords, and a resolved board, all queued into the existing
pin-publishing cron. Credits: 1 for the article text, plus each image's own per-tier cost,
charged only once the pipeline actually runs (not at batch-creation time).

### Auto-pin scheduling in detail

- Each article gets `pins_per_article` pins, spaced `article_pin_gap_days` (default 30) apart
  from each other — pin 2 publishes 30 days after pin 1, pin 3 another 30 days after that, etc.
- All of a batch's auto-pins share one **daily quota** (`daily_pin_count`, optionally increased
  by `daily_pin_ramp` per elapsed month since the batch was created) — if a day is already full,
  that pin's slot rolls to the next day with room. Pins landing on the same day are spread
  automatically across it (24h ÷ that day's quota).
- All of a batch's auto-pins are grouped under one `pin_batches` row (created on first use), so
  they show up in the existing **Batches / Batch View** pages alongside manually bulk-scheduled
  pins — Batch View's pin stats and the auto-article Batch View page both read from it.

### Notes & limitations

- **No live web search.** "Check top articles" is done via the AI model's own knowledge and
  prompting (asked to write as an expert who knows what performs well), not a real web search —
  wiring in an actual search API (Google/Bing/SerpAPI) is a separate integration this doesn't
  include. Competitor **links** you paste are fetched directly for their real title/content,
  which is real, not simulated.
- **Board-matching in "AI Auto" mode is a simple heuristic** (does an existing board's name
  appear in the article title), not true semantic matching — for tighter control per topic, use
  "Separate board for each article" instead.
- The daily article/pin pacing math assumes the cron job runs at least a few times a day; if it's
  set to run far less often than that, publishing will lag behind the configured pace rather than
  catching up all at once.

## Auto Article v2

### "Articles stay Queued forever" — fixed with a manual "Process Now" option

I reviewed the whole pipeline carefully: every real failure path already marks an article
`failed` with a reason, so an article stuck on `Queued` almost always means the
`cron/article-scheduler.php` scheduled task (a separate manual setup step on your host, not
something file upload alone configures) isn't running yet. Rather than leave that as a silent
dependency, **Batch View now has a "Process Now" button** — it runs due articles for that batch
immediately from the browser, one at a time (so a single web request stays inside typical
shared-hosting time limits), with a live status list. This works whether or not the cron job is
set up, and is also the fastest way to test a batch right after creating it.

### "View Error" on failed titles

Batch View's title list now shows a red **View Error** button next to any title that failed,
which expands to show the exact error message (outline/draft/image/publish/credits — whatever
actually went wrong) — no more guessing why an article didn't publish.

### Ideas-article structure now matches proven listicle formatting

Based on the reference articles provided, Ideas-type articles now generate with the same
structure: an intro, then one **numbered `<h2>` per idea** (e.g. `<h2>1. Cream Blonde Soft Crop
Pixie</h2>`) with exactly two short paragraphs under each — one describing the look, one a direct
actionable tip ("Ask your stylist for…", "Try pairing this with…", etc., phrased naturally for
the topic) — followed by an `<h2>FAQs</h2>` section (five AI-generated, topic-relevant questions
as `<h3>` + short answers) and a closing `<h2>Wrap Up</h2>` section.

## Auto Article v3 — the real fix for stuck/silent articles

Your report (article stuck on `draft`, images already generated in `uploads/articles/` but
never published, no error shown, and "Network error while processing" from Process Now) pointed
to the actual root cause: the whole pipeline was running in **one** request, and on shared
hosting the web server itself (not PHP) kills a request that runs too long — which nothing inside
PHP can catch. That's exactly what "Network error" was: the connection got cut mid-request, after
the article had already been claimed (and some images already generated) but before it could
either finish or record an error.

### The fix: a resumable, step-at-a-time pipeline

The pipeline is now broken into small steps — outline+draft (one call), **one image per step**
(not the whole batch of images in one shot), then publish — and every step is short enough to
comfortably finish inside a normal request. Concretely:

- `articles.status` now moves through `queued → drafting → imaging → ready → publishing →
  published`/`failed`, with progress (`image_progress_json`, `image_slots_needed`) saved to the
  database after every single step.
- If a step's request dies for any reason, the article simply stays at its last *completed* step
  — it's never left in an ambiguous state. The next cron run or "Process Now" click resumes
  exactly where it left off (already-generated images are kept, not regenerated).
- Every step also has genuine crash protection: a `try/catch` around the whole step (so even an
  unexpected PHP error marks the article `failed` with the real message instead of vanishing),
  plus a `register_shutdown_function` safety net in the web endpoint for truly fatal errors (like
  memory exhaustion) that even `try/catch` can't catch.
- If a claim itself gets abandoned mid-step (the "busy" marker never clears because the request
  died before finishing), a 10-minute stale-lock timeout lets the next attempt safely reclaim and
  retry it — so a single bad run can never brick an article forever.
- Credits are now deducted **per step** as each one actually succeeds (1 for the text, then each
  image's own cost as it's generated) rather than one lump sum at the end — more accurate, and
  nothing is charged twice if a step is retried after a crash.
- **"Process Now" and the cron job both now resume in-progress articles**, not just brand-new
  queued ones — so your currently-stuck article (still shown as `draft`) will be picked up and
  restarted cleanly the next time either runs.

### Migration note

Run `migrate.php` again — it widens `articles.status` further and adds the two new progress
columns. Non-destructive as always.

### Known remaining limitation

Two connected features run concurrently (e.g. two browser tabs both open on the same batch, or
"Process Now" clicked while cron also happens to run) could in rare cases both claim the very
same image step. This isn't fully locked against — a low-likelihood edge case, not the failure
mode you hit, but worth knowing about if you routinely run multiple triggers at once.

## Auto Article v4 — two more real bugs fixed

- **`Call to undefined function website_publish_post()`** — a genuine missing `require_once`.
  `includes/auto_article_functions.php` called a function defined in `includes/website_functions.php`
  but never loaded that file, and neither did its callers. Fixed by having
  `auto_article_functions.php` self-require its own dependencies (`functions.php`,
  `ai_functions.php`, `website_functions.php`) rather than relying on every caller to remember
  all three — this closes off the whole class of "works in some entry points, undefined function
  in others" bugs for this subsystem going forward.
- **13 images instead of 3 for a 2-idea article** — the outline generator had a hardcoded default
  of "12 ideas" that was never actually replaced with the number the title implies. Now the title
  is parsed for its leading number (e.g. "20 Plus Size Outfits" → 20) and that count is what gets
  requested, so a 2-idea article generates 2 content images (+1 feature = 3 total), not 12+1. If a
  title has no leading number at all, it falls back to a more conservative default of 10 (down
  from 12) — if you want an exact small count with no number in the title, that's a case this
  can't infer and is worth knowing about.

### On hosting — shared hosting vs. your Spaceship VPS

These two specific bugs were pure code bugs, unrelated to hosting — they'd have happened
anywhere. But the *earlier* "stuck in draft / Network error" issue was a real interaction with
hosting limits: shared hosting's web server (not PHP) kills long-running HTTP requests, typically
around 30-60 seconds, often in a way that can't be overridden from inside the app at all.

**Practical recommendation regardless of which host you use:** treat `cron/article-scheduler.php`
(run via your hosting control panel's cron jobs, as a CLI process) as the primary way batches get
processed, and use the "Process Now" button mainly for quick testing of a step or two — a CLI
cron process has no web-server request timeout at all (`set_time_limit(0)` is already set in that
script), while anything triggered from the browser is inherently bound by normal HTTP request
limits no matter how the pipeline is staged.

That said, a VPS is a genuinely better fit for this specific feature's workload (heavy, repeated
AI + image generation): you'd have full control to raise `php.ini` limits, adjust or remove
web-server/proxy timeouts, and generally not be at the mercy of a shared host's shared-resource
policies. If you move to the Spaceship VPS, just make sure PHP, MySQL, and the `gd`, `curl`, and
`mbstring` extensions are installed, and set up the same cron job there.

## Auto Article v6

### Background processing that no longer depends on a browser tab

- Refactored the step-loop into one shared `run_due_article_steps()` function used by the cron
  job, the "Process Now" button, and a new background mechanism — all three stay in sync.
- **New "poor man's cron"**: `user/ajax-tick.php` is a throttled (once per 3 minutes), unauthenticated
  endpoint that nudges a few pipeline steps forward, using `ignore_user_abort(true)` +
  `fastcgi_finish_request()` so it keeps running server-side even after the browser that
  triggered it disconnects. Every logged-in page now fires a throttled `fetch(..., {keepalive:
  true})` ping to it on load — `keepalive` specifically means that request is still delivered
  even if the tab closes or navigates away right after firing it. In effect: anyone visiting any
  page of the app nudges the whole queue forward, tab or no tab.
- Creating a batch now also immediately kicks off a detached background processing run the same
  way, instead of waiting for the next tick or a manual click.
- The real cron job (`cron/article-scheduler.php`) remains the most reliable method — see the
  hosting note further up — these two additions are a working fallback for hosts where cron is
  hard to set up, not a replacement for it.
- **Note on `ajax-tick.php`**: it's intentionally reachable without login (it does no
  user-specific work and returns nothing sensitive) so any page can trigger it — the 3-minute
  throttle bounds how often it can actually do work even under deliberate repeated hits.

### Cloudflare accounts now auto-fail-over mid-request

Previously, if a chosen Cloudflare account's *own* rate/neuron/quota limit was hit — which can
happen even when our internal daily-usage counter still shows room — the whole generation
attempt failed (retried the same account 3 times, not a different one). Now `ai_generate_pin_image_raw()`
tries up to 8 different accounts within one call: a genuine limit-looking failure (429, or an
error mentioning "limit"/"quota"/"neuron"/"rate") benches that account for the rest of the day so
it's skipped by everyone going forward; any other failure just moves on to the next account for
that call without penalizing it. The user should no longer see a failure just because one
particular account happened to be out of capacity.

### Author selection

The batch wizard's first step now has an **Author** dropdown (optional — defaults to the site's
default author), populated from real WordPress users the same way categories already are. The
WP plugin (now v1.2.0 — re-download/reinstall it) exposes the site's author list over ping and
accepts `author_id` on publish, setting `post_author` accordingly.

### Models page: Export / Import

Admin → Models now has **Download Settings** (a JSON export of every provider API key and every
Cloudflare Worker account) and **Import Settings** (upload that file on a fresh install to
restore them in one go instead of re-typing everything). Import upserts by provider/model-type
and by worker URL — existing entries get updated, new ones get added, nothing is deleted, and
per-account daily usage is deliberately not included in the export since it's a daily counter,
not a setting.

## v7 — author fix, Pinterest character limits, CSV export

### Author dropdown not populating — fixed

The WP plugin's author query (`'who' => 'authors', 'has_published_posts' => false`) was a
fragile, legacy combination that could silently return an empty list depending on WP/role-plugin
setup. Replaced with the reliable `role__in` approach (with a `capability` fallback for fully
custom roles). **Reinstall the plugin (now v1.2.1)** and click **Re-check** on the Add Websites
page for each site, then the author list should populate. Author selection is now also
**required** in the wizard (both client-side and server-side validation), matching the request.

### Pinterest's real character limits, enforced

The AI pin-writer prompt now explicitly states Pinterest's actual hard limits (title 100 chars,
description 500, alt text 500) instead of an arbitrary looser guess. More importantly, there's
now a real enforcement layer, not just a prompt instruction the model might ignore:
`pin_enforce_max_chars()` (in `includes/functions.php`, the foundational file everything else
already depends on) trims at a word boundary wherever possible, applied both where the AI
generates pin copy (`ai_generate_pin_batch()` — used by both Bulk Pin Scheduler's AI writer and
Auto Article's per-article pin copy) and again at the point pins are actually saved
(`ajax-bulk-save.php`), so a manually-typed-too-long title/description is caught too, not just
AI-generated ones.

### Download CSV (Pinterest's own bulk-upload format)

- **Bulk Pin Scheduler**: a new "Download CSV" button sits next to "Upload CSV" in the Pins
  panel header — exports the pins currently staged in the builder (before you even click
  "Schedule All Pins") in Pinterest's official bulk-upload column format (Title, Media URL,
  Pinterest board, Thumbnail, Description, Link, Publish date, Keywords), so you can upload
  directly to Pinterest's own native bulk tool instead of (or alongside) this app's scheduler.
- **Batches list and Batch View**: every already-scheduled batch now has its own "Download CSV"
  button, exporting that batch's actual `scheduled_pins` rows the same way.
- Media URLs point at this app's own domain (where the images are actually hosted), so they're
  publicly reachable the way Pinterest's bulk uploader requires.

## v8 — Navigation, header, and dark mode redesign

### Sidebar restructure

- "Create Schedule" → **Schedule Single Pin**
- **Bulk Scheduler** is now a collapsible group: Bulk Scheduler + **Scheduled Pin Batches** (the
  page previously reachable as a standalone "Batches" link)
- "Auto Article" group label → **Auto Website to Daily Pin** (its Your Batch / Create New Batch
  submenu is unchanged)
- "Write Article" → collapsible group: **Write Single Article** + **My Created Articles** (was
  the standalone "My Articles" link)
- **Add Websites** is now a group: All Websites, WordPress (both point at the existing
  `websites.php`, since that's the one real platform today), plus Shopify / Wix / Custom
  Websites (new — Under Development)
- New top-level items, all "Under Development" for now: Add Ecommerce Platform, Storage, Pages,
  Classic Wizard, Settings, Upgrade Plan, Become Affiliate (40%), Tutorial, Support
- New **More Features & Tools** section: Keyword Research, Analytics, Trend Find (all Under
  Development) and **Free Tools** — a real grid page of 13 rounded buttons (Pinterest Pin Maker,
  Hashtag Generator, Title/Description Generator, Bio Generator, Board Name Generator, Username
  Generator, Alt Text Generator, Keyword Research Tool, Font Generator, plus the four Etsy
  tools), each currently leading to its own "Under Development" page.

### New top header bar

A persistent bar above the sidebar: logo, a sidebar collapse toggle (real — hides/shows the
sidebar, state remembered via localStorage), an **Upgrade Now** button, a notification bell (a
lightweight placeholder dropdown — there's no real notifications system yet), a **Tutorial**
button, a **dark/light theme toggle** (real, see below), and an account avatar (the user's
initial) linking to the new Account Settings placeholder.

### Dark mode — genuinely functional, with an honest scope note

Toggling the header's moon/sun icon sets `data-theme="dark"` on `<html>`, persisted via
`localStorage` and applied before first paint (no flash of the wrong theme). This isn't a
component-by-component re-theme of literally every page — the CSS override targets the app's
main structural surfaces (body, sidebar, cards, tables, form inputs, modals, the new free-tools
grid) broadly rather than hand-tuning every single hardcoded color in what's now a very large
stylesheet. It should look and function correctly across the app, but a few less-common
components may not be perfectly tuned — worth a look over your own pages and flagging anything
that reads oddly in dark mode so it can be fixed specifically.

### Everything "Under Development" shares one real page

Every placeholder destination routes to `user/coming-soon.php?feature=<name>` (one reusable page,
not 20 separate stub files) with a clear "Under Development" badge and a Back to Dashboard button.

## v9 — Page Crawler (Phase 1 of "Auto Website to Daily Pin" / Storage)

**Scope note first:** your latest message described two full new subsystems — a website
page-crawler with bulk pin automation, and a multi-provider cloud storage system (uploads,
Pexels, AI generation, per-provider admin setup with account rotation). Each of those is
comparable in size to the entire Auto Article feature, which took many rounds to build. I could
not build all of it in one pass, so this round is **Phase 1**: the page-discovery/selection
engine both larger features depend on. Storage, the full pin-scheduling half of "Auto Website to
Daily Pin" (per-page pin settings, board assignment, gaps, image generation), and the admin
model/storage-provider settings are **not built yet**.

### What's real and working in this round

- **Database**: `crawl_sites`, `crawl_sitemaps`, `crawl_pages`, `crawler_providers`.
- **`includes/page_crawler_functions.php`** — a genuine sitemap discovery + parsing engine:
  checks `robots.txt` for `Sitemap:` directives, falls back to common paths
  (`/sitemap_index.xml`, `/sitemap.xml`, `/wp-sitemap.xml`); recursively resolves sitemap
  *indexes* into their child sitemaps; parses `<urlset>` pages with `lastmod` dates; infers rough
  category/tag labels from URL path segments. **I tested the XML-parsing and robots.txt-regex
  logic directly** (synthetic sitemap index + urlset + robots.txt) rather than just reading the
  code back — confirmed it correctly separates index vs. urlset, extracts lastmod dates, and
  infers tags like "category, hairstyles" from a URL path.
- **Firecrawl fallback** for sites with no sitemap at all — a real HTTP integration
  (`POST /v1/map`) built from Firecrawl's documented API shape. I can't reach firecrawl.dev from
  my sandbox to test it live, so this is unverified against a real account — worth checking
  against Firecrawl's current docs if it doesn't behave as expected.
- **`user/website-pages.php`** — scan a URL or a connected website, see discovered pages in a
  table (URL, last modified, inferred category/tags, priority), select/deselect individually or
  in bulk, save the selection. This is the real "Pages To Use For Pins" list from your
  screenshots, though simplified to one unified table rather than the separate Prefix/Sitemap/
  Categories tabs shown — those three views are just different groupings of the same
  `crawl_pages` data and are a reasonable next addition.
- **Admin → Page Crawler**: Firecrawl API key setup with a short guide.

### Not built yet (next phases)

- The Prefix / Sitemap / Categories & Tags tabbed grouping, list/table toggle, date-range filter,
  bulk CSV keyword upload, "Set Active URLs via CSV", custom page add, sitemap-URL override.
- The actual "Automate Daily Pin" flow from selected pages (pin-per-page settings, board
  assignment/creation, the 1-month-default gap between a page's own pins, daily pin pacing) — the
  button exists but currently leads to a placeholder.
- Storage (Manage Images, Manual Upload, Stock Images/Pexels, AI Generation, Website Images scan)
  and its admin-side Backblaze B2 / Amazon S3 / Cloudflare R2 settings with multi-account
  rotation.
- The admin "Auto Websites to Daily Pin" AI-model settings submenu.

Given the size of what's left, I'd suggest tackling it in the same phased way — happy to continue
with whichever piece matters most to you next (the pin-automation flow, or Storage).

## v10 — Auto Website to Daily Pin: fully built and live

Built as its own complete feature (not a placeholder) — genuinely working end to end, reusing the
same crash-safe, resumable, one-step-per-request architecture that fixed Auto Article's
reliability problems, since this involves the same kind of long-running AI + image generation work.

### User side

- **Website Pages** (`website-pages.php`) — scan a site or pick a connected one, select which
  pages to use, then **Automate Daily Pin** carries your selection straight into the new schedule
  wizard.
- **Create New Schedule** (`auto-website-create.php`) — pins per page (default 3), gap between
  that page's own pins (default 1 month), pins published per day (same-day pins auto-spaced —
  e.g. 6/day → every 4 hours), board mode (one existing board, or AI creates a separate board per
  page named from that page's intent), image quality/size/style/CTA (the same options as Bulk Pin
  Scheduler), website-text-on-image on/off, and a content-source choice: **AI writes everything**
  (extracts each page's title and writes genuinely distinct, non-duplicate title/description/alt/
  keywords for every one of that page's pins, all sharing one board) or **your own CSV**
  (url/title/description/alt/keywords — AI then only generates the images, your text is used
  as-is for every pin on that page).
- **Your Scheduled Websites** (`auto-website-batches.php`) — id, name, website, first/last pin
  date, total pages, status, stop/resume, and **View More** (`auto-website-batch-view.php`):
  published pins, all pins, remaining pins, boards created, pages completed, plus a **Process
  Now** button and per-page **View Error** — the same reliability features Auto Article has.
- Runs on the **same cron job and the same background "tick"** as Auto Article — nothing new to
  set up, one scheduled task drives both.

### Admin side

**AI Setting By Features → Auto Website to Daily Pin**: its own pin-writer text model and image
model, entirely separate from Bulk Pin Scheduler/Auto Article's settings. Cloudflare accounts are
never shown to the end user anywhere in this feature — only you configure them (under Models),
and if you set this feature's image mode to Cloudflare, every image uses it regardless of which
quality tier a user picks in their own panel.

### Honest limitations

- **Prefix / Sitemap / Categories & Tags as separate filter tabs, and bulk keyword-CSV page
  import** from your original spec aren't built — page selection is currently one unified table
  (built in the previous round). A reasonable next addition.
- **No live end-to-end test.** I verified every SQL statement's placeholder counts by hand (a
  real bug class I've hit before), checked every function signature against its actual
  definition, and every file lints/parses clean — but none of this has run against a real
  database, a real Pinterest account, or real AI/image API calls. Please test a small schedule
  (1-2 pages) first before trusting it with a big batch.
- **Storage system is not started** — per your instruction to do Auto Website to Daily Pin first,
  it's next.

## v11 — Storage: fully built and live

### User side (`storage.php`)

- **Storage tab** with 4 sub-tabs: **Manage Images** (grid of everything you've stored, filter by
  tag, tag/download/delete per image, bulk select + bulk delete/download as a ZIP, a usage bar
  with a Clear Space shortcut), **Manual Upload** (files or a direct image URL), **Stock Images**
  (Pexels search, 10 results + Load More, Add to Storage per image), **AI Generation** (prompt +
  size + quality .2/.7/1, auto-added to storage on success).
- **Website Images tab**: enter a page URL, scan it — every `<img>` (including common lazy-load
  attributes) gets downloaded and added to storage, grouped by source page.
- **Pins & Article images show here too**, exactly as asked — pulled read-only from
  `scheduled_pins` and `articles`, no delete button at all (the safest way to guarantee a pin
  that's still scheduled — or a published one — can never be broken from the Storage screen).
- **1GB/user quota**, enforced before every save (upload, URL add, Pexels add, AI generation,
  website scan all check it).

### Admin side

- **Storage Settings**: add Backblaze B2 / Amazon S3 / Cloudflare R2 accounts (all three are
  S3-compatible, so one signed-request client handles all of them) with sizing, tier (free/paid),
  and priority — new uploads automatically roll over to the next account once one fills up, free
  tiers before paid, with setup guides for each provider including running several free
  Cloudflare R2 accounts (~10GB each) for automatically-growing free capacity. No provider
  configured → images just go to local disk, exactly as requested.
- Pexels API key setup (needed for Stock Images to work).

### What I verified, and what I couldn't

The trickiest piece here is the S3-compatible signing (AWS Signature Version 4) — the same
algorithm Backblaze B2, Cloudflare R2, and Amazon S3 all use. **I tested it against AWS's own
official published test vectors** (exact secret key, date, region, service → expected signing key
and signature), not just written from the spec and hoped for the best — it matches exactly. That
gives real confidence the signing math itself is correct.

What I could not do from this environment: actually upload to a live B2/S3/R2 bucket (no network
access to those endpoints here), or exercise Pexels/website-scan against the real internet. So
while the code is sound by the parts I could verify, **please test one real upload against
whichever provider you configure** before relying on it for anything important — if something's
off, it's most likely in a provider-specific detail (bucket path-style vs virtual-host-style
URLs, a region quirk) rather than the core signing algorithm, which checks out against AWS's own
reference values.

### Scope note

AI Generation and Website Images reuse the existing "Article Content Image" model setting
(admin → AI Setting By Features → Auto Article Pin) rather than a new dedicated setting — it's a
generic plain-photo generator either way, so this avoids yet another settings page for the same
underlying capability.

## v12 — three real bugs fixed

### Fatal error on Storage ("Argument must be of type int, null given")

A real, systemic bug: `current_user($pdo)` returns `null` whenever the logged-in session's
`user_id` doesn't match an actual row in `users` — which happens if a login cookie survives a
database reset/reseed during testing. Every page that reads `$user['id']` right after
`current_user()` (this is most pages in the app, not just Storage) was vulnerable to this; it
only surfaced as a hard crash on Storage because `get_user_storage_used()` has a strict `int`
type hint that throws instead of silently misbehaving. Fixed at the source — `require_login()`
now also verifies the session's user actually still exists, and cleanly logs out and redirects to
the login page if not, instead of continuing on into a crash. This protects every page across the
whole app, not just the new ones.

### Website image scanning wasn't fetching anything

The real cause: the page-fetching code sent `User-Agent: PageCrawlerBot/1.0` — which openly
self-identifies as a bot, and is exactly the kind of request WordPress security plugins
(Wordfence, etc.) and hosting-level bot protection commonly block outright, silently returning
nothing useful. Switched to a real browser User-Agent — this is reading pages the calling user
already owns/manages, not evading anything. This single fix also benefits Auto Website to Daily
Pin's sitemap discovery, since it shares the same fetch function.

At the same time, rebuilt the extraction to match the full spec: `<img src>`, the
highest-resolution candidate from `srcset`, lazy-load attributes (`data-src`, `data-lazy-src`,
`data-original`), the Open Graph image, and inline CSS `background-image` URLs as a fallback.
WordPress featured images and gallery images need no special-casing — they're just ordinary
`<img>` tags in the rendered HTML, so the general scan already covers them. **I tested this
against a synthetic page covering every one of those cases** (not just read back) — all 8 extract
correctly, including correctly preferring a real lazy-loaded URL over a `data:` placeholder, and
picking the largest image from a `srcset` list rather than the smallest.

### Removed the "Pages" sidebar item

Confirmed not needed — removed from the sidebar entirely.

## v13 — Add Websites: WordPress, Shopify, Wix and custom (webhook) sites

**Upload, then run `migrate.php` once (then delete it).** It adds columns to `websites` and
`crawl_pages`, creates `platform_settings`, and widens `articles.wp_post_id` to VARCHAR (Wix post IDs are
GUIDs). Existing WordPress sites keep working — they become `platform = wordpress`. It also removes a
stale line that narrowed `scheduled_pins.source` back after it had been widened (this dropped `website_pin`).

### User side (sidebar → Add Websites)
- **All Websites** (`websites.php`) — ID, website URL, platform, Connected / Unconnected, filter tabs.
  Every row has **Automate Pin** (creates the crawl site and opens Auto Website to Daily Pin; a site with
  no pages yet is scanned automatically). Unconnected rows have a **Connect** menu
  (WordPress / Shopify / Wix / Custom). A plain URL can be added without any platform.
- **WordPress** (`website-wordpress.php`) — the existing plugin + site key flow, unchanged.
- **Shopify** (`shopify-stores.php`) — "Connect with Shopify" (OAuth, needs the app set up in Admin) or
  manual: Client ID + secret (Dev Dashboard app, token renewed automatically) or an Admin API token.
  Each store has **View Products** / **View Blogs** (`shopify-items.php`): sync from Shopify, tick items,
  **Automate Pin** sends the ticked items to Auto Website to Daily Pin using their real titles.
- **Wix** (`wix-sites.php`) — Site URL + Site ID + API key (generated in Wix API Keys).
- **Custom** (`custom-websites.php`) — signed webhook. The app POSTs `ping` and `article.publish` events
  with `X-PinScheduler-Signature: sha256=HMAC(secret, timestamp + "." + body)`. Sample receiver on the page.
- **Auto Article / Write Article** list every connected website with its platform. Author is required
  only for WordPress; Shopify's "category" is the blog. Shopify / Wix / custom sites get public URLs to the
  images in `/uploads` instead of base64.

### Admin side
- Sidebar → **All Websites** → *All Websites* (ID, URL, platform, status, user; filter + search) and
  *Settings* (Shopify app Client ID / secret / scopes / API version, redirect URL to whitelist, and
  editable Shopify + Wix guides shown to users).

### What was tested, and what wasn't
Tested end to end against **mock** Shopify / Wix / webhook servers: connect, re-check, product/blog sync,
selection, Automate Pin hand-off, article publishing for all three platforms, signature check, dedupe on
reconnect, CSRF rejection, admin pages, and `migrate.php` on an old-schema database. **Not** tested
against live Shopify / Wix accounts — do one real connect + publish per platform after uploading.
Wix publishing needs an author (member ID); Shopify products not published to the Online Store are skipped.

## SEO Setting (Admin Panel)

A new **SEO Setting** menu was added to the admin sidebar (`admin/seo-settings.php`), with six tabs:

| Tab | What it controls |
| --- | --- |
| **Meta & Branding** | Website meta title, meta description, meta keywords (comma separated), canonical URL, favicon, logo, social share image, and an optional extra `<head>` code box for analytics/verification tags. |
| **Indexing** | The "Allow search engines to index this website" switch. **Off by default** — while off, every public page outputs `noindex, nofollow`, `robots.txt` blocks all crawlers and no schema markup is printed. There's also a custom `robots.txt` box. |
| **Software App Schema** | The `WebApplication` / `SoftwareApplication` JSON-LD: type, name, URL, applicationCategory, operatingSystem, browserRequirements, description, screenshot, aggregateRating (value/count/best/worst) and the publisher Organization with its logo. |
| **Pricing Offers** | Add, edit, enable/disable and delete pricing plans. Each row becomes one `Offer` inside the `AggregateOffer` — `lowPrice`, `highPrice` and `offerCount` are calculated from the active rows automatically. |
| **Review Schema** | Add, edit, enable/disable and delete individual `Review` blocks (author, rating, review text, reviewed item name/URL/OS/category). |
| **Preview** | Shows the exact JSON-LD that will be printed, ready to paste into Google's Rich Results Test. |

Uploaded favicons and logos are stored in `uploads/seo/`.

### Files added / changed

- **Added:** `includes/seo_functions.php`, `admin/seo-settings.php`, `robots.php`, `uploads/seo/`
- **Changed:** `admin/includes/admin-header.php` (menu item), `index.php`, `privacy-policy.php`, `auth/login.php`, `auth/register.php` (render the SEO head), `database/schema.sql`, `migrate.php`, `install.php` (new tables + seeding)

### Upgrading an existing install

1. Upload the new/changed files.
2. Visit `https://yourdomain.com/migrate.php` once — it creates `seo_settings`, `seo_offers` and `seo_reviews` and seeds one settings row. Nothing existing is touched.
3. Delete `migrate.php`.
4. Log into the admin panel → **SEO Setting**, fill in your meta and schema details, then turn indexing on when you're ready.

Optional: to serve `robots.txt` dynamically instead of the static file the admin page writes on save, add this to your root `.htaccess`:

```
RewriteEngine On
RewriteRule ^robots\.txt$ robots.php [L]
```

## Auto Website to Daily Pin — Upload URLs from CSV

On **Website Pages** there's now an **Upload URLs From a CSV** card next to the website scanner. Instead of
relying on a sitemap, you can upload a list of URLs and save it as a named **archive** — which then works
exactly like a scanned site when you create a schedule.

- **Archive Name** — name the archive as you upload it (e.g. "Recipe Posts — Batch 1").
- **Save Into** — create a new archive, or append the URLs to one you already have.
- **Download Sample CSV** — grabs `assets/downloads/website-urls-sample.csv`, a filled-in example.
- Re-uploading a URL that's already in the archive updates that row instead of creating a duplicate.
- Limits: 2,000 URLs and 5MB per file.

### CSV format

Only `url` is required. Everything else is optional:

| Column | Notes |
| --- | --- |
| `url` | Required. A missing `https://` is added for you. |
| `title` | Pin title, used as-is when you pick "Use My Uploaded Archive Data". |
| `description` | Pin description. |
| `alt` | Alt text; used as the description if that column is blank. |
| `keywords` | Comma separated — wrap the cell in quotes. |
| `image_url` | An existing image for the page. |
| `category` | Category/tags; worked out from the URL when left blank. |
| `priority` | `low`, `normal` or `high`. Defaults to `normal`. |

A headerless file also works — the first column is then read as the URL.

### Using the data in a schedule

When the selected pages carry titles/descriptions from an uploaded CSV, the schedule builder shows a third
Content Source button: **Use My Uploaded Archive Data**. Pick it and AI only generates the images — your
title/description/alt/keywords are used as-is, with AI filling in any page where those fields are blank.

### Files added / changed

- **Added:** `user/ajax-upload-url-csv.php`, `assets/downloads/website-urls-sample.csv`
- **Changed:** `includes/page_crawler_functions.php` (`parse_url_csv()`, `import_urls_from_csv()`),
  `user/website-pages.php` (upload card + JS), `user/auto-website-create.php` (archive content source),
  `database/schema.sql` and `migrate.php` (`crawl_sites.source` column)

Run `migrate.php` once after uploading to add the `crawl_sites.source` column.

## Settings (User Panel) + User Setting / Email Setting (Admin) — new

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once — it adds the new tables (`user_ai_settings`,
   `team_members`, `user_oauth_connections`, `user_tokens`) and the `users.email_verified_at`
   column, without touching existing data. Delete `migrate.php` afterwards.

### User → Settings (new sidebar link, replacing the old placeholder)

- **Account**: change name, change email (password-confirmed, sends an old-email notice if
  enabled below), change password, and Connect/Disconnect a Google account (shown only once the
  admin turns Google login on).
- **AI Api — "Use your own AI model (OpenRouter)"**: a user pastes their own OpenRouter API key
  and optional model (blank = automatic). Once enabled, pin text and article text generation
  tries their key first; if it errors or hits a limit, generation **automatically falls back**
  to the platform's own configured model so the request still completes. Wired into the Bulk
  Pin Scheduler's pin writer and the Write Article text generation.
- **Image Generation Models**: the same idea for AI image generation (also OpenRouter — routed
  through a chat-completions call to an image-output-capable model such as
  `google/gemini-2.5-flash-image-preview`, since OpenRouter has no dedicated image endpoint;
  best-effort, verify against OpenRouter's current docs for your chosen model). Note: this
  powers the general `ai_generate_image()` helper but is **not yet** wired into the specialized
  "Create Pin Image with AI" GD-compositing pipeline in the Bulk Scheduler (that one keeps using
  the admin's DeepInfra/Cloudflare setup, since it has its own quality-tier/negative-prompt
  system built specifically around those providers).
- **Team Management**: invite people by email to share your credits and BYOK AI keys. A member
  signs in with their own login and never sees your (or another member's) pins/websites/
  articles/data — only shared resource usage. If the invited email doesn't have an account yet,
  the invite auto-activates the moment they sign up with that email.

### Admin → User Setting (new)

Toggle which sign-up/login methods appear on the public Sign Up / Log In pages: password+email,
Google, Facebook, Microsoft, and "Login with Pinterest" (reuses the Pinterest App credentials
already in Pinterest Settings — no separate keys needed). Google/Facebook/Microsoft each need
their own Client ID/Secret and the shown Redirect URI added in that provider's developer console.
Also includes a Firebase setup guide + reference config fields, for anyone who'd rather run auth
through Firebase in a future custom integration (the built-in providers above work standalone,
without Firebase).

### Admin → Email Setting (new)

SMTP configuration (host/port/username/password/encryption, From name/email) used for password
reset, email verification, and email-change notices — falls back to PHP's `mail()` if SMTP is
left off. Includes a "Send a Test Email" button, setup guides for Hostinger email and Gmail SMTP
(App Password required), Mailchimp API key/Audience ID reference fields, and on/off switches for
each of the three system emails (password reset / signup verification / email-change notice).

### Removed from the user sidebar

Per request: **Keyword Research**, **Analytics**, **Trend Find**, **Free Tools**, and **Add
Ecommerce Platform** (all were "coming soon" placeholders) have been removed from the sidebar.

### Notes & limitations

- Facebook/Microsoft/Pinterest login use standard OAuth2 flows implemented directly (no vendor
  SDKs) — test each against your own app credentials after enabling.
- The minimal built-in SMTP client (no external library) supports STARTTLS/SSL + AUTH LOGIN,
  which covers Gmail and most standard SMTP providers, but isn't a full RFC implementation
  (no attachments, no DKIM signing).

## Plan Pricing (subscriptions, payments, coupons) — new

### If you already installed the app before this update

1. Upload all the new/changed files (overwrite existing ones).
2. Visit `https://yourdomain.com/migrate.php` once — it adds the plan/coupon/payment/
   notification tables and the new `users`/`team_members` columns, without touching existing
   data. Delete `migrate.php` afterwards.
3. Set up a daily cron job for renewal reminders (see `cron/plan-renewal.php`'s header comment
   for the exact command).

### Admin → Plan Pricing (new sidebar group)

- **Create Plan / All Plans**: name, monthly price, monthly/yearly discount %, short description,
  a tag (Popular/Recommended/Budget Friendly/Best Value/New) with a color, buy-button text/colors/
  position, structured feature limits (AI credits, pin scheduling, Pinterest accounts, websites,
  uploaded pins, bulk scheduling, team invites, auto article/website-pin, single article writer,
  cloud storage), and free-form feature bullet rows (text/tooltip/checkmark style/size/color/font).
  Only one plan can be marked "Free" at a time.
- **Payment Gateway Integration**: on/off + credentials + setup guide for Stripe, PayPal,
  NOWPayments, and Binance Pay, plus unlimited admin-defined **custom payment methods** (bank
  transfer, Easypaisa, etc.) with a rich-text detail editor — a user picks one, follows the
  instructions, and uploads a screenshot for manual approval.
- **Coupons**: name, code, end date, discount %, apply to all plans or specific ones, a
  shareable auto-apply link (`upgrade.php?coupon=CODE`), and usage counts. A link to an
  inactive/expired coupon shows "This coupon has expired" on the pricing page.
- **Contact Sales**: view submitted requests (name/WhatsApp/email/budget/message) and toggle the
  "Need custom limits?" call-to-action on the Upgrade page.
- **Users**: revenue totals (overall and by plan), pending custom-payment-method approvals
  (approve/reject with a message — approving activates the plan immediately), and the current
  paid subscriber list with plan/end date/auto-renew status. The existing admin **Users** page
  also gained a quick plan-assignment dropdown for manually upgrading/downgrading anyone.
- **Setting**: image-generation credit cost by quality tier (Low/Medium/High multipliers),
  text-generation credit cost per call, and how many days before expiry the renewal reminder
  starts (default 7, sent once per day automatically via the new cron job).

### User → Upgrade (new, replacing the old placeholder + now live in the sidebar and header)

Pricing cards for every active plan, a "Your Usage" panel (AI credits, Pinterest accounts,
websites, pins — against the current plan's limits, with upsell cards for higher plans only),
monthly/yearly billing toggle, and the Contact Sales CTA when enabled. Checkout supports coupon
codes and any enabled payment method. New users are automatically placed on the admin's
configured Free Plan (if one exists) on signup, including via social login. Settings → Account
now shows the current plan/renewal date and an auto-renew toggle (on by default). Settings → Team
now shows each active member's "limit share %" (how much of the owner's feature limits that
member can draw on) and the plan's team-invite limit.

### Notes & limitations

- **Live gateway charging isn't wired up.** Stripe/PayPal/NOWPayments/Binance can be turned on
  and their credentials saved, and a user can select them at checkout, but the actual
  redirect-to-gateway-and-charge flow needs webhook endpoints (stub URLs are shown in each
  gateway's guide) built out and tested against real API credentials, which wasn't possible to
  do blind. The **custom payment method** flow (manual proof-of-payment + admin approval) is
  fully working end-to-end and is the reliable path today. Auto-renew for paid plans currently
  sends a "renewal payment due" notification rather than silently re-charging a saved card.
- **Feature-limit enforcement is partial.** The team-invite limit is enforced (Settings → Team).
  The other structured limits (pin scheduling, Pinterest accounts, websites, uploaded pins,
  bulk/auto-article/auto-website-pin/single-article-writer flags, cloud storage) are stored per
  plan and shown on the Upgrade page's usage panel, but aren't yet actively blocking the
  relevant actions elsewhere in the app (e.g. scheduling past the daily limit). Wiring each of
  those checks into their existing feature pages is the natural next step.

## Custom Design editor (Canva-style)

- **User:** sidebar → 🎨 Custom Design → *Create New Design* (`user/design-editor.php`), *Your Designs* and *Design Templates* (`user/designs.php`).
- **Editor** (Fabric.js 5, `assets/js/design-editor.js`): blank canvas with any width × height (+ presets), resize with content scaling,
  collage layouts (20) and single frames (square / portrait / landscape / rounded / circle / arch) — drag photos onto frames,
  crop/zoom a photo inside its frame; uploads, free stock photos (Pexels key from Storage Settings), text with 38 Google fonts,
  size, colour, bold, italic, underline, strikethrough, UPPER / lower / Title / Sentence case, alignment, letter & line spacing,
  effects (shadow, lift, hollow, outline, splice, neon, 3D thick, highlight) with thickness/colour/blur/offset controls,
  25 shapes, 9 lines/arrows, stickers, admin graphics, background colour / gradient / image, transparency, rotate, flip,
  duplicate, delete, lock, group/ungroup, layers panel, align to page, snapping to centre, undo/redo, zoom,
  keyboard shortcuts, image adjustments (brightness, contrast, saturation, B&W, sepia), rounded image corners.
- **Save** stores the design (JSON + thumbnail) in `user_designs`; autosaves every minute once saved. **Download** PNG / JPG / 2× PNG / design JSON.
  **Use This Design** saves the image to `uploads/pins/` and opens *Schedule Single Pin* with it attached.
- **Admin:** 🎨 Canva → *Add Elements* (`admin/design-elements.php`, SVG/PNG/JPG/WEBP/GIF, SVGs sanitised) and
  *User Designs* (`admin/user-designs.php`) — publish any design as a template for all users (with a category), unpublish, delete.
- Tables: `user_designs`, `design_uploads`, `design_elements` — run `migrate.php` once.
