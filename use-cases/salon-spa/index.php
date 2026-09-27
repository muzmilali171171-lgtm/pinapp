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
'tools' => ['pinterest-pin-maker', 'ai-pinterest-pin-create', 'pinterest-title-description-generator', 'pinterest-keyword-research-tool', 'pinterest-board-name-generator', 'pinterest-bio-generator', 'pinterest-image-resizer', 'ai-image-creater'],
'slug' => 'salon-spa', 'name' => 'Salons & Spas', 'short' => 'Salon Website', 'site' => 'Website', 'accent' => '#db2777', 'noun' => 'pages', 'niche' => 'hair, nails, beauty and spa treatments',
'title' => 'Pinterest for Salons & Spas: Bring New Clients', 'desc' => 'Pin your looks, treatments and offers for local clients. AI designs salon pins from your work and schedules them in 1 click.',
'kw' => 'salon Pinterest marketing, spa Pinterest, hair salon pins, nail salon Pinterest, beauty salon marketing, local business Pinterest, spa treatments pins',
'badge' => '💆 For Salons, Spas & Studios', 'h1' => 'Pinterest Automation for Salons & Spas', 'h1_accent' => 'Looks That Book Appointments',
'sub' => 'Clients bring Pinterest pictures to their appointments. Make sure some of them are yours — pin your hair, nail and beauty work, treatments and offers, and bring local clients through the door.',
'bullets' => [['💇', 'Pins From Your Real Client Work'], ['📍', 'Local Titles With Your City & Services'], ['🎁', 'Holiday Offers & Gift Cards Pinned Early'], ['⚡', 'All Your Pages Pinned in 1 Click'], ['✍️', 'Auto Blog Writes Beauty & Care Posts']],
'chips' => ['💇 Look pinned', '💾 Saved to Next Haircut', '📅 Appointment'],
'placeholder' => 'https://yoursalon.com/balayage-portfolio/',
'marquee' => ['Balayage', 'Haircuts', 'Nail art', 'Lash lifts', 'Facials', 'Massage', 'Bridal hair', 'Brows', 'Spa days', 'Gift cards'],
'results' => ['Looks That Turn Into Bookings', 'Clients save looks for their next visit — and pins keep your work in front of them.'],
'features' => ['Why Salons & Spas Automate Pinterest', 'Marketing that runs between appointments.', [
    ['💇', 'Portfolio pins', 'Your real work, beautifully framed.'],
    ['📍', 'Local copy', 'City and service in every title.'],
    ['🎁', 'Offer timing', 'Holiday gift cards and seasonal offers pinned early.'],
    ['🎨', 'Brand look', 'Your salon colours on every pin.'],
    ['🗂️', 'Service boards', 'Hair, nails, spa — sorted.'],
    ['✍️', 'Auto Blog', 'AI writes care-tip posts that feature your services.'],
]],
'playbook' => ['Pinterest Tips for Salons & Spas', 'From saved pin to booked appointment.', [
    ['Post real client work', 'With your clients’ permission.'],
    ['Name the service and city', '“Blonde Balayage in Austin”.'],
    ['Pin care guides', 'Aftercare tips build trust.'],
    ['Push gift cards early', 'Holiday gift cards from November.'],
    ['Link to booking', 'Every pinned page should make booking easy.'],
]],
'who' => ['beauty businesses', 'Salons, spas and studios.', [['Hair salons', 'Portfolio pins that bring bookings.'], ['Nail & lash studios', 'Show off every set.'], ['Spas & wellness', 'Pin treatments and packages.']]],
'faq' => [
    ['Does Pinterest work for local salons?', 'Yes — local words in pins and pages help nearby clients find you.'],
    ['Can I post client photos?', 'Only with your clients’ permission.'],
    ['Can pins link to my booking page?', 'Pins link to the page they were made from — make sure it has a booking button.'],
],
'cta_red' => ['Looks that book appointments.', 'Pin your work automatically.'],
'cta_dark' => 'Ready to bring new clients from Pinterest?',
]);
uc_render_page($pdo, $user, $uc);
