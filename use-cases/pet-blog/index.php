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
'slug' => 'pet-blog', 'name' => 'Pet Blogs', 'short' => 'Pet Blog', 'site' => 'Blog', 'accent' => '#f97316', 'noun' => 'posts', 'niche' => 'pet care, pet recipes and pet products',
'title' => 'Pinterest for Pet Blogs: Dog & Cat Content', 'desc' => 'Grow your pet blog with Pinterest on autopilot. AI turns pet care tips, homemade treat recipes and product guides into pins and schedules them in 1 click.',
'kw' => 'Pinterest for pet blogs, dog blog Pinterest, cat blog pins, pet care tips Pinterest, homemade dog treats pins, pet product pins, pet blog traffic',
'badge' => '🐶 For Dog, Cat & Pet Bloggers', 'h1' => 'Pinterest Automation for Pet Blogs', 'h1_accent' => 'Tails Wagging, Traffic Growing',
'sub' => 'Pet owners save training tips, homemade treat recipes, product picks and cute ideas all day long. Turn every post into irresistible pet pins and keep them going out daily.',
'bullets' => [['🦴', 'Pins for Care Tips, Treat Recipes & Product Picks'], ['🐾', 'Pet-Recipe Templates Modelled on Top Food Pins'], ['🤖', 'AI Writes Titles by Breed, Age & Problem'], ['⚡', 'Your Whole Blog Pinned in 1 Click'], ['🗂️', 'Boards for Dogs, Cats, Training & DIY']],
'chips' => ['🐶 Tip pinned', '💾 Saved to Dog Treats', '📈 Blog visits up'],
'placeholder' => 'https://yourpetblog.com/homemade-peanut-butter-dog-treats/',
'marquee' => ['Dog training', 'Homemade dog treats', 'Cat care', 'Puppy tips', 'Pet DIY', 'Dog names', 'Pet products', 'Senior dogs', 'Cat toys', 'Pet travel'],
'results' => ['Pet Owners Keep Coming Back', 'Pet questions never stop — new puppies and kittens arrive every day. Pinned consistently, your answers keep reaching owners for years.'],
'features' => ['Why Pet Bloggers Automate Pinterest', 'Spend your time with your pets, not in Canva.', [
    ['🦴', 'Treat recipe pins', 'Food-style layouts for homemade dog and cat treats.'],
    ['🎓', 'Training tip pins', 'Clear, friendly designs for how-tos and routines.'],
    ['🛍️', 'Product roundups', 'Number templates for “15 Best Dog Toys” posts.'],
    ['🔤', 'Breed-aware copy', 'AI writes titles with breed, age and problem where your post covers them.'],
    ['🗂️', 'Pet boards', 'Dogs, cats, training, DIY — sorted automatically.'],
    ['✍️', 'Auto Blog', 'AI writes pet posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Pet Bloggers', 'What gets pet pins saved.', [
    ['Show the pet', 'Real pet photos beat stock images every time.'],
    ['Solve a specific problem', '“How to Stop a Puppy From Biting” gets more clicks than “Puppy Life”.'],
    ['Pin recipes like food bloggers do', 'Treat recipes with a clear title band get saved like human recipes.'],
    ['Use breed keywords', 'Owners search for their breed specifically.'],
    ['Pin holiday pet content early', 'Halloween costumes and gift guides 6–8 weeks ahead.'],
]],
'who' => ['pet creators', 'Bloggers, trainers and pet brands.', [['Pet bloggers', 'Keep every tip and recipe in front of owners.'], ['Trainers & groomers', 'Pin your advice and bring in local clients.'], ['Pet product brands', 'Pin guides that feature your products.']]],
'faq' => [
    ['Are there templates for pet treat recipes?', 'Yes — the recipe-style templates work perfectly for homemade treats.'],
    ['Can I pin affiliate product roundups?', 'Yes. Keep a clear affiliate disclosure on your page and follow Pinterest’s rules.'],
    ['Should I check health advice?', 'Yes — for health or nutrition advice, make sure your post is accurate and, where needed, reviewed by a vet.'],
],
'cta_red' => ['More pet owners, less pin-making.', 'Pin every post on your pet blog automatically.'],
'cta_dark' => 'Ready to grow your pet blog on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
