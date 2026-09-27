<?php
/**
 * Regenerate popup → "Regenerate Text": writes a fresh, similar title + description (+ alt
 * text and keywords) for a top pin, using the same AI model as the Bulk Pin Scheduler
 * (Admin → AI Setting By Features → Bulk Pin Scheduler) and the user's own OpenRouter key
 * first when they set one (Settings → AI). Costs the plan's text-credit-per-call price.
 */
@set_time_limit(120);
require_once __DIR__ . '/includes/pa-ajax.php';
require_once __DIR__ . '/../includes/ai_functions.php';

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$link = trim((string)($_POST['link'] ?? ''));
$customPrompt = trim((string)($_POST['custom_prompt'] ?? ''));
$withTags = ($_POST['with_tags'] ?? '0') === '1';

if ($title === '' && $description === '' && trim((string)($_POST['keyword'] ?? '')) === '') {
    pa_json(['ok' => false, 'error' => 'This pin has no title or description to work from — type a topic in the Title box first.']);
}

$usesOwnKey = (bool)get_user_ai_credentials($pdo, (int)$user['id'], 'text');
$cost = (float)(credit_pricing_get($pdo)['text_credit_per_call'] ?? 1);
if (!$usesOwnKey && $cost > 0) {
    $balance = get_user_text_credits($pdo, (int)$user['id']);
    if ($balance < $cost) {
        pa_json(['ok' => false, 'error' => "Your text AI credits are low ({$balance} left, need {$cost}) — upgrade your plan to get more."]);
    }
}

$keyword = trim(mb_substr((string)($_POST['keyword'] ?? ''), 0, 150));
if (($_POST['mode'] ?? '') === 'keyword' && $keyword !== '') {
    // Keyword Research → Create Pin: a brand-new pin built around a trending keyword.
    $topic = $keyword;
    $instructions = 'Write a brand-new Pinterest pin built around the trending search keyword "' . $keyword . '". '
        . 'Use the keyword naturally in the title and early in the description, match what people searching it want, '
        . 'and make the pin promote the linked page.';
} else {
    $topic = $title !== '' ? $title : mb_substr($description, 0, 120);
    $instructions = 'This is a proven, high-performing Pinterest pin. Write a NEW, similar variation that keeps the same topic, '
        . 'search intent and audience, but with a completely fresh angle and wording so Pinterest treats it as a new pin.';
    if ($description !== '') {
        $instructions .= ' Original description for context (do not copy it): "' . mb_substr($description, 0, 400) . '"';
    }
}
if ($customPrompt !== '') $instructions .= ' ' . $customPrompt;

$result = ai_generate_pin_batch($pdo, [$topic], $withTags, $link, $instructions, (int)$user['id']);
$item = $result['items'][0] ?? null;
if (!$item || trim((string)$item['title']) === '') {
    pa_json(['ok' => false, 'error' => $result['error'] ?: 'The AI could not write a new title right now. Please try again.']);
}

if (!$usesOwnKey && $cost > 0) deduct_text_credits($pdo, (int)$user['id'], $cost);
log_event($pdo, 'ai', 'Pinterest Analytics: regenerated title/description for a top pin', (int)$user['id']);

pa_json([
    'ok' => true,
    'title' => $item['title'],
    'description' => $item['description'],
    'alt_text' => $item['alt_text'],
    'keywords' => $item['keywords'],
    'cost' => $usesOwnKey ? 0 : $cost,
    'remaining_text_credits' => get_user_text_credits($pdo, (int)$user['id']),
]);
