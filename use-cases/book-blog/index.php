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
'slug' => 'book-blog', 'name' => 'Book Blogs', 'short' => 'Book Blog', 'site' => 'Blog', 'accent' => '#92400e', 'noun' => 'posts', 'niche' => 'book lists, reviews and reading guides',
'title' => 'Pinterest for Book Blogs & Book Reviewers', 'desc' => 'Readers build TBR lists on Pinterest. AI turns your reading lists and book reviews into pins and schedules them in 1 click.',
'kw' => 'Pinterest for book blogs, book list pins, reading list Pinterest, book review pins, TBR Pinterest, bookish Pinterest, book blog traffic',
'badge' => '📚 For Book Bloggers & Reviewers', 'h1' => 'Pinterest Automation for Book Blogs', 'h1_accent' => 'Onto Every TBR Board',
'sub' => 'Readers plan their next reads on Pinterest — “books like…”, “best thrillers of the year”, “cozy fall reads”. Turn every list and review into pins that land on readers’ boards.',
'bullets' => [['📚', 'List Templates for “25 Books Like…” Posts'], ['⭐', 'Review Pins That Lead to Your Full Review'], ['🤖', 'AI Writes Titles by Genre, Mood & Season'], ['⚡', 'Your Whole Blog Pinned in 1 Click'], ['🗂️', 'Boards by Genre, Age Group & Reading Challenge']],
'chips' => ['📚 List pinned', '💾 Saved to TBR', '📈 Readers up'],
'placeholder' => 'https://yourbookblog.com/books-like-the-hunger-games/',
'marquee' => ['Books like…', 'Cozy mysteries', 'Fantasy series', 'Book club picks', 'Summer reads', 'Thrillers', 'YA books', 'Romance', 'Non-fiction', 'Reading challenges'],
'results' => ['Reading Lists That Get Saved', 'Readers save lists and come back each time they need a new book. Your lists keep sending visits for years.'],
'features' => ['Why Book Bloggers Automate Pinterest', 'Readers are some of Pinterest’s most loyal savers.', [
    ['📚', 'List templates', 'Number templates for reading lists.'],
    ['⭐', 'Review pins', 'Clean designs that lead to your full review.'],
    ['🍂', 'Seasonal reads', 'Summer, fall and holiday lists scheduled ahead.'],
    ['🔤', 'Genre-aware copy', 'AI writes by genre, mood and “books like” comparisons.'],
    ['🗂️', 'Genre boards', 'Pins sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes list posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Book Bloggers', 'How to land on readers’ boards.', [
    ['Use “books like” titles', 'Readers search by books they loved.'],
    ['Say the mood and season', '“Cozy Fall Mysteries” is a real search.'],
    ['Use your own photos', 'Use your own shelf and flat-lay photos rather than copying publishers’ images.'],
    ['Pin lists more than single reviews', 'Lists get far more saves.'],
    ['Refresh yearly lists', '“Best Books of the Year” posts get a new version and new pins.'],
]],
'who' => ['book lovers', 'Bloggers, authors and bookshops.', [['Book bloggers', 'Grow readers for lists and reviews.'], ['Authors', 'Pin your books and reader guides.'], ['Independent bookshops', 'Pin recommendations that bring in buyers.']]],
'faq' => [
    ['Can I use book cover images?', 'Covers are copyrighted artwork. Using your own photos of books is the safer choice; check the publisher’s guidelines for promotional use.'],
    ['Which templates suit reading lists?', 'Number templates and collages of your own book photos.'],
    ['Can I pin affiliate book links?', 'Pin your list page with clear affiliate disclosure, and follow your affiliate programme’s rules.'],
],
'cta_red' => ['Get on every reader’s TBR board.', 'Pin your reading lists automatically.'],
'cta_dark' => 'Ready to grow your book blog on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
