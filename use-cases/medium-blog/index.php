<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/footer_functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/seo_functions.php";
require_once __DIR__ . "/../../includes/use_case_functions.php";

$user = current_user($pdo);
$app = SITE_BRAND;

$uc = uc_build([
'slug' => 'medium-blog', 'name' => 'Medium Writers', 'short' => 'Medium Writer', 'site' => 'Publication', 'accent' => '#111827', 'noun' => 'stories', 'niche' => 'your writing topics',
'title' => 'Pinterest for Medium Writers & Publications', 'desc' => 'Promote your Medium stories on Pinterest and reach readers outside Medium. AI designs pins for your stories and schedules them in 1 click.',
'kw' => 'Pinterest for Medium writers, promote Medium stories, Medium traffic Pinterest, Medium publication marketing, blog writers Pinterest, get more Medium reads',
'badge' => '✒️ For Medium Writers & Publications', 'h1' => 'Pinterest Automation for Medium Writers', 'h1_accent' => 'Readers Beyond the Feed',
'sub' => 'Medium’s feed decides who sees your stories. Pinterest gives your writing a second, long-lasting home — every story turned into pins that keep bringing readers for months.',
'bullets' => [['📝', 'Pins for Every Story You Publish'], ['🔤', 'Typography Templates That Suit Long-Form Writing'], ['🤖', 'AI Writes Pin Titles From Your Story'], ['⚡', 'Your Whole Archive Pinned in 1 Click'], ['🗂️', 'Boards by Topic and Publication']],
'chips' => ['✒️ Story pinned', '💾 Saved to Read Later', '📈 Reads up'],
'placeholder' => 'https://medium.com/@you/your-story-slug',
'marquee' => ['Self-improvement', 'Productivity', 'Writing tips', 'Tech essays', 'Personal finance', 'Mental health', 'Startups', 'Relationships', 'Creativity', 'Career advice'],
'results' => ['Stories That Keep Getting Read', 'Medium stories fade from the feed in days. Pins keep sending readers for months.'],
'design_title' => 'Let the Headline Lead', 'design_point' => 'Editorial, minimal and typography templates suit essays and ideas.',
'features' => ['Why Medium Writers Use Pinterest', 'A traffic source you control.', [
    ['📝', 'Every story, pinned', 'Old stories get new readers.'],
    ['🔤', 'Editorial templates', 'Clean type-led designs for ideas and essays.'],
    ['🤖', 'Hook-driven copy', 'AI turns your story into a pin title that earns clicks.'],
    ['🔁', 'Multiple pins per story', 'Different quotes and angles from one piece.'],
    ['🗂️', 'Topic boards', 'Pins sorted into your topics.'],
    ['🌐', 'Own-site ready', 'Move to your own blog later — the same setup works.'],
]],
'playbook' => ['Pinterest Tips for Medium Writers', 'Bring readers from outside Medium.', [
    ['Pin your evergreen stories first', 'Guides and how-tos outlast news pieces.'],
    ['Pull a strong line for the pin', 'A sharp idea from the story makes a great headline.'],
    ['Use clear topic boards', 'Productivity, writing, money — how readers browse.'],
    ['Pin several angles', 'One story, three different hooks.'],
    ['Consider a custom domain', 'Publications on your own domain are easier to scan and pin in bulk.'],
]],
'who' => ['writers', 'Solo writers to publications.', [['Medium writers', 'More readers for every story.'], ['Publication editors', 'Promote every writer’s work consistently.'], ['Newsletter writers', 'Pin stories that lead to your list.']]],
'faq' => [
    ['Can I pin Medium stories?', 'Yes — paste your story links. If a page can’t be read automatically it shows as skipped; stories on a custom-domain publication usually work best.'],
    ['Where do the pin images come from?', 'From the images in your story. Stories without images can use a text-led template or an image you upload in the pin editor.'],
    ['Will pins link to Medium?', 'Yes — each pin links to the story it was made from.'],
],
'cta_red' => ['Give your stories a second life.', 'Pin every Medium story automatically.'],
'cta_dark' => 'Ready to find new readers on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
