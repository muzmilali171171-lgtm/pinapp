<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = APP_NAME;

$uc = uc_build([
'slug' => 'quote-graphics', 'name' => 'Quote Graphics Sites', 'short' => 'Quote Site', 'site' => 'Site', 'accent' => '#8b5cf6', 'noun' => 'quote posts', 'niche' => 'quotes, sayings and captions',
'title' => 'Pinterest for Quote Sites & Quote Graphics', 'desc' => 'Turn quote collections into shareable text pins automatically. Bold typography templates, 130+ fonts, AI-written titles and hundreds of pins in 1 click.',
'kw' => 'quote pins, Pinterest quotes, quote graphics Pinterest, inspirational quotes pins, quote website traffic, sayings Pinterest, caption ideas pins',
'badge' => '💬 For Quote & Caption Sites', 'h1' => 'Pinterest Automation for Quote Websites', 'h1_accent' => 'Words Worth Saving',
'sub' => 'Quotes are among the most-shared pins on Pinterest. Turn every quote collection into bold, beautiful text pins with the fonts and colours of your brand — and schedule them for months.',
'bullets' => [['🔤', 'Typography-First Templates With 130+ Fonts'], ['🎨', '56 Palettes for Moods & Seasons'], ['🔢', '“100 Motivational Quotes” Number Pins'], ['⚡', 'Hundreds of Collections Pinned in 1 Click'], ['🗂️', 'Boards by Mood, Theme & Occasion']],
'chips' => ['💬 Quote pinned', '💾 Saved to Motivation', '🔁 Shared'],
'placeholder' => 'https://yourquotesite.com/motivational-quotes/',
'marquee' => ['Motivational quotes', 'Love quotes', 'Instagram captions', 'Self-care quotes', 'Friendship quotes', 'Funny sayings', 'Bible verses', 'Birthday wishes', 'Mom quotes', 'Monday motivation'],
'results' => ['Quotes That Keep Getting Shared', 'People save quotes to come back to and share. Each collection can collect saves for years.'],
'design_title' => 'Make the Words the Design', 'design_point' => 'Bold text templates, script accents and outlined type with 130+ fonts.',
'features' => ['Why Quote Sites Automate Pinterest', 'A quote site can have thousands of pages — pin them all.', [
    ['🔤', 'Type-led templates', 'Outlined, stacked and highlight-line styles.'],
    ['🎨', 'Mood palettes', 'Soft, bold, dark or seasonal colours.'],
    ['🔢', 'Collection pins', 'Number templates for big quote lists.'],
    ['🔁', 'Many pins per collection', 'Different quotes, different designs.'],
    ['🗂️', 'Mood boards', 'Love, motivation, faith, funny — sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes new quote collections and pins them.'],
]],
'playbook' => ['Pinterest Tips for Quote Sites', 'How quote pins get saved.', [
    ['Keep quotes short on the pin', 'Short lines are easier to read in the feed.'],
    ['Say the theme in the title', '“Short Motivational Quotes for Work”.'],
    ['Match colour to mood', 'Soft palettes for self-care, bold for motivation.'],
    ['Pin occasion quotes early', 'Mother’s Day and Valentine’s quotes weeks ahead.'],
    ['Credit authors', 'Always attribute quotes correctly on your page.'],
]],
'who' => ['quote creators', 'Quote sites, caption blogs and faith sites.', [['Quote websites', 'Pin thousands of collections consistently.'], ['Caption & greetings blogs', 'Reach people looking for the right words.'], ['Faith & devotional sites', 'Share verses and reflections daily.']]],
'faq' => [
    ['Can I use any quote?', 'Attribute quotes correctly, and be careful with long passages from copyrighted works.'],
    ['Which templates work best?', 'Typography templates — outlined, stacked and highlight lines — with bold or script fonts.'],
    ['Can I use my own fonts?', 'Choose from 130+ Google fonts, or import your own Canva design as SVG.'],
],
'cta_red' => ['Words worth saving, pinned daily.', 'Every quote collection, automatically.'],
'cta_dark' => 'Ready to get your quotes shared?',
]);
uc_render_page($pdo, $user, $uc);
