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
'slug' => 'fitness-blog', 'name' => 'Fitness Blogs', 'short' => 'Fitness Blog', 'site' => 'Blog', 'accent' => '#ef4444', 'noun' => 'posts', 'niche' => 'workouts, fitness plans and healthy habits',
'title' => 'Pinterest for Fitness Blogs & Workout Sites', 'desc' => 'Pin every workout, plan and fitness guide automatically. Bold pin designs, AI-written titles and months of pins scheduled in 1 click.',
'kw' => 'Pinterest for fitness blogs, workout pins, fitness Pinterest marketing, home workout pins, workout plan Pinterest, fitness blog traffic, personal trainer Pinterest',
'badge' => '🏋️ For Fitness Bloggers & Trainers', 'h1' => 'Pinterest Automation for Fitness Blogs', 'h1_accent' => 'Workouts People Actually Save',
'sub' => 'People save workouts, challenges and meal plans on Pinterest to follow later. Turn every post into bold, motivating pins and keep them publishing every day — just like your training.',
'bullets' => [['💪', 'Bold Templates for Workouts, Challenges & Plans'], ['📅', 'New-Year & Summer Peaks Scheduled Ahead'], ['🤖', 'AI Writes Titles by Goal, Level & Equipment'], ['⚡', 'Your Whole Blog Pinned in 1 Click'], ['🗂️', 'Boards for Home Workouts, Gym, Yoga & Meal Prep']],
'chips' => ['💪 Workout pinned', '💾 Saved to Home Workouts', '📈 Visits up'],
'placeholder' => 'https://yourfitnessblog.com/20-minute-home-workout/',
'marquee' => ['Home workouts', '30-day challenges', 'Beginner routines', 'Glute workouts', 'Yoga flows', 'Meal prep', 'Running plans', 'Dumbbell workouts', 'Stretching', 'HIIT'],
'results' => ['Motivation That Compounds', 'Fitness searches spike in January and before summer — and every year again. Pin ahead and consistently to catch every wave.'],
'features' => ['Why Fitness Bloggers Automate Pinterest', 'Consistency wins in fitness — and on Pinterest.', [
    ['💪', 'Bold workout pins', 'Heavy outlines and highlight lines that stand out.'],
    ['📅', 'Challenge pins', 'Number templates for “30-Day Ab Challenge” posts.'],
    ['🗓️', 'Peak-season scheduling', 'New Year and pre-summer content pinned early.'],
    ['🔤', 'Goal-aware copy', 'AI writes by goal, level and equipment.'],
    ['🗂️', 'Workout boards', 'Home, gym, yoga, running, meal prep.'],
    ['✍️', 'Auto Blog', 'AI writes fitness posts with images and pins them.'],
]],
'playbook' => ['Pinterest Tips for Fitness Bloggers', 'What makes workout pins get saved.', [
    ['Be specific about the workout', '“20-Minute No-Equipment Full-Body Workout” beats “Today’s Sweat”.'],
    ['Say the level', 'Beginner-friendly content gets huge saves.'],
    ['Pin challenges and plans', 'People save programs to follow later.'],
    ['Pin early for January', 'New-Year fitness searches start in December.'],
    ['Pair with meal prep', 'Nutrition posts extend your reach.'],
]],
'who' => ['fitness creators', 'Bloggers, trainers and studios.', [['Fitness bloggers', 'Keep every workout in front of motivated readers.'], ['Personal trainers', 'Bring clients for programs and coaching.'], ['Studios & gyms', 'Pin classes and workouts for local members.']]],
'faq' => [
    ['Can I pin workout programs I sell?', 'Yes — pin the sales page or a free sample workout that leads to it.'],
    ['Do I need to add health disclaimers?', 'For health or fitness advice, keep your page accurate and include any disclaimers your content needs.'],
    ['When should I pin New Year content?', 'Start in early December.'],
],
'cta_red' => ['Consistency wins. Automate it.', 'Your workouts, pinned every day.'],
'cta_dark' => 'Ready to grow your fitness blog on Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
