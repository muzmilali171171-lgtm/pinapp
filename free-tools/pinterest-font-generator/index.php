<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/footer_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require_once __DIR__ . '/../../includes/seo_functions.php'; seo_render_head($pdo, [
    'title' => 'Pinterest Font Generator: Fancy Text | ' . SITE_BRAND,
    'description' => 'Turn plain text into fancy fonts and symbols you can copy and paste into Pinterest titles, bios and board names. Dozens of styles, free to use.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/pinterest-font-generator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Pinterest Font Generator', 'free-tools/pinterest-font-generator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Pinterest Font Generator</h1>
        <p class="ft-sub">Transform your text into unique styles for pins, boards, and your profile. Just type and copy!</p>
    </div>

    <div class="form-row" style="max-width:640px;margin:0 auto 24px;">
        <label>Enter Your Text</label>
        <input type="text" id="fgInput" placeholder="Your Pinterest Text" value="Your Pinterest Text" maxlength="200" style="font-size:17px;padding:12px 14px;">
    </div>

    <div id="fgList" style="max-width:640px;margin:0 auto;display:flex;flex-direction:column;gap:10px;"></div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want your pins as eye-catching as your fonts?</h2>
        <p>Sign up free and let AI design, write, and schedule your Pinterest pins — no design skills needed.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Using Stylized Fonts on Pinterest the Right Way</h2>
        <p>The fancy text this tool produces isn't actually a different font in the typography sense — Pinterest, like every other platform, only renders whatever standard font its app or website is built with. What these tools actually do is swap your regular letters for lookalike characters from other parts of the Unicode standard: the same underlying technology that lets emoji, accented letters, and characters from other languages all show up correctly on any device. Mathematical alphanumeric symbols, circled letters, and small caps all have their own dedicated Unicode codepoints, and because they're technically just "characters" rather than "formatting," you can paste them into a plain text field — a pin title, a board name, a bio — and they render exactly as styled, everywhere.</p>
        <p>That portability is the whole appeal. Pinterest's own text fields don't support bold, italic, or any real text formatting — there's no button for it in the app. Stylized Unicode text is the only way to make a title, board name, or bio visually stand out from the plain text around it in a Pinterest feed full of otherwise identical-looking sans-serif type. A bio that opens with a small caps or bold-styled name catches the eye a half-second faster than one that blends into everything above and below it.</p>
        <p>That said, restraint matters more here than volume. A whole sentence in a heavily decorative script or a glitch-style font can become genuinely hard to read, and some styles — especially the more decorative circled, squared, or flag-style ones — render inconsistently across older devices or specific apps, occasionally showing up as a blank box (☐) instead of the intended character. The safest, most broadly compatible styles for real use are the mathematical bold, italic, and sans-serif variants; more decorative styles (circled, script, fraktur) work best for a single short word — a name, a single board title, an accent word — rather than a full paragraph.</p>
        <p>It's also worth knowing that stylized Unicode text isn't searchable the same way normal text is. Pinterest's search index is built to match plain, normal-alphabet text — a title in mathematical bold "𝗕𝗼𝗹𝗱" characters generally won't match a search for the plain word "Bold," because to a search algorithm those are technically different characters, not a font variant of the same letter. That means stylized text is a purely visual, attention-grabbing tool — great for a bio, a board name, or a short accent phrase where you want visual pop, but the wrong choice for anything you actually want to rank in Pinterest search. Keep your searchable keywords in plain text, and save the stylized fonts for the parts of your profile that are meant to be looked at rather than searched.</p>
        <p>This generator runs entirely in your browser — type your text once, and every style updates instantly below it, ready to copy with one click. Nothing you type is sent anywhere, so it works just as well for a quick one-off as it does for testing a dozen options before you settle on the look you want.</p>
        <p>A quick note on why some letters in styles like Script, Fraktur, and Double-Struck look slightly inconsistent with the rest of the word: a handful of capital letters in those specific Unicode blocks were never assigned their own "matching" character when the standard was created decades ago, because an older, differently-styled symbol already existed at a nearby position and was reused instead. It's a known quirk of the Unicode standard itself rather than a rendering bug, and it only affects a small number of capital letters in those particular decorative styles — lowercase letters and the more common bold, italic, and sans-serif variants are unaffected and render perfectly consistently.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>Is this actually a different font, or something else?</summary><p>Something else, technically — these are special Unicode characters that look like styled letters (bold, italic, circled, and so on), not a different font file. Because they're just characters, they paste and display correctly in any text field, including Pinterest's, with no special formatting support needed.</p></details>
        <details><summary>Will stylized Pinterest text show up in Pinterest search?</summary><p>Generally no. Pinterest's search matches plain, normal-alphabet text, and a stylized character is technically a different character from its plain counterpart — even though it looks similar. Use stylized text for visual impact in your bio or board names, and keep your actual searchable keywords in normal text.</p></details>
        <details><summary>Will these fonts display correctly for everyone?</summary><p>Most styles (bold, italic, sans-serif variants, monospace) display reliably on virtually all modern devices. More decorative styles — circled, squared, fraktur, some symbol-based ones — can occasionally show as a blank box on older devices or in apps with limited font support, so it's worth testing on your own phone before using an unusual style somewhere important.</p></details>
        <details><summary>Where should I use stylized Pinterest fonts?</summary><p>Bios, board names, and short accent phrases work best — anywhere a bit of visual pop matters more than search discoverability. Avoid using them for your whole pin description or anywhere you need Pinterest's search algorithm to actually read and match the text.</p></details>
        <details><summary>Can I mix stylized text with emoji?</summary><p>Yes — both are just Unicode characters, so they combine without any issue. A stylized name followed by a couple of relevant emoji is a common, safe combination for a Pinterest bio.</p></details>
        <details><summary>Does this tool store or send my text anywhere?</summary><p>No — the conversion happens entirely in your browser using JavaScript. Nothing you type is sent to a server, logged, or stored.</p></details>
        <details><summary>Why do some letters look slightly different from the rest in a style like Script or Fraktur?</summary><p>A handful of letters in those specific styles don't have a dedicated "matching" Unicode character, so the closest available symbol is used instead. It's a known quirk of the Unicode standard itself, not an error in this tool — you may notice it on a few less common capital letters in Script, Fraktur, and Double-Struck styles.</p></details>
        <details><summary>Is this Pinterest Font Generator free?</summary><p>Yes — completely free, unlimited, with no account required, since it runs entirely in your browser.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
const FONT_MAPS = {"bold": {"A": "𝐀", "B": "𝐁", "C": "𝐂", "D": "𝐃", "E": "𝐄", "F": "𝐅", "G": "𝐆", "H": "𝐇", "I": "𝐈", "J": "𝐉", "K": "𝐊", "L": "𝐋", "M": "𝐌", "N": "𝐍", "O": "𝐎", "P": "𝐏", "Q": "𝐐", "R": "𝐑", "S": "𝐒", "T": "𝐓", "U": "𝐔", "V": "𝐕", "W": "𝐖", "X": "𝐗", "Y": "𝐘", "Z": "𝐙", "a": "𝐚", "b": "𝐛", "c": "𝐜", "d": "𝐝", "e": "𝐞", "f": "𝐟", "g": "𝐠", "h": "𝐡", "i": "𝐢", "j": "𝐣", "k": "𝐤", "l": "𝐥", "m": "𝐦", "n": "𝐧", "o": "𝐨", "p": "𝐩", "q": "𝐪", "r": "𝐫", "s": "𝐬", "t": "𝐭", "u": "𝐮", "v": "𝐯", "w": "𝐰", "x": "𝐱", "y": "𝐲", "z": "𝐳", "0": "𝟎", "1": "𝟏", "2": "𝟐", "3": "𝟑", "4": "𝟒", "5": "𝟓", "6": "𝟔", "7": "𝟕", "8": "𝟖", "9": "𝟗"}, "italic": {"A": "𝐴", "B": "𝐵", "C": "𝐶", "D": "𝐷", "E": "𝐸", "F": "𝐹", "G": "𝐺", "H": "𝐻", "I": "𝐼", "J": "𝐽", "K": "𝐾", "L": "𝐿", "M": "𝑀", "N": "𝑁", "O": "𝑂", "P": "𝑃", "Q": "𝑄", "R": "𝑅", "S": "𝑆", "T": "𝑇", "U": "𝑈", "V": "𝑉", "W": "𝑊", "X": "𝑋", "Y": "𝑌", "Z": "𝑍", "a": "𝑎", "b": "𝑏", "c": "𝑐", "d": "𝑑", "e": "𝑒", "f": "𝑓", "g": "𝑔", "h": "𝑕", "i": "𝑖", "j": "𝑗", "k": "𝑘", "l": "𝑙", "m": "𝑚", "n": "𝑛", "o": "𝑜", "p": "𝑝", "q": "𝑞", "r": "𝑟", "s": "𝑠", "t": "𝑡", "u": "𝑢", "v": "𝑣", "w": "𝑤", "x": "𝑥", "y": "𝑦", "z": "𝑧"}, "boldItalic": {"A": "𝑨", "B": "𝑩", "C": "𝑪", "D": "𝑫", "E": "𝑬", "F": "𝑭", "G": "𝑮", "H": "𝑯", "I": "𝑰", "J": "𝑱", "K": "𝑲", "L": "𝑳", "M": "𝑴", "N": "𝑵", "O": "𝑶", "P": "𝑷", "Q": "𝑸", "R": "𝑹", "S": "𝑺", "T": "𝑻", "U": "𝑼", "V": "𝑽", "W": "𝑾", "X": "𝑿", "Y": "𝒀", "Z": "𝒁", "a": "𝒂", "b": "𝒃", "c": "𝒄", "d": "𝒅", "e": "𝒆", "f": "𝒇", "g": "𝒈", "h": "𝒉", "i": "𝒊", "j": "𝒋", "k": "𝒌", "l": "𝒍", "m": "𝒎", "n": "𝒏", "o": "𝒐", "p": "𝒑", "q": "𝒒", "r": "𝒓", "s": "𝒔", "t": "𝒕", "u": "𝒖", "v": "𝒗", "w": "𝒘", "x": "𝒙", "y": "𝒚", "z": "𝒛"}, "sansBold": {"A": "𝗔", "B": "𝗕", "C": "𝗖", "D": "𝗗", "E": "𝗘", "F": "𝗙", "G": "𝗚", "H": "𝗛", "I": "𝗜", "J": "𝗝", "K": "𝗞", "L": "𝗟", "M": "𝗠", "N": "𝗡", "O": "𝗢", "P": "𝗣", "Q": "𝗤", "R": "𝗥", "S": "𝗦", "T": "𝗧", "U": "𝗨", "V": "𝗩", "W": "𝗪", "X": "𝗫", "Y": "𝗬", "Z": "𝗭", "a": "𝗮", "b": "𝗯", "c": "𝗰", "d": "𝗱", "e": "𝗲", "f": "𝗳", "g": "𝗴", "h": "𝗵", "i": "𝗶", "j": "𝗷", "k": "𝗸", "l": "𝗹", "m": "𝗺", "n": "𝗻", "o": "𝗼", "p": "𝗽", "q": "𝗾", "r": "𝗿", "s": "𝘀", "t": "𝘁", "u": "𝘂", "v": "𝘃", "w": "𝘄", "x": "𝘅", "y": "𝘆", "z": "𝘇", "0": "𝟬", "1": "𝟭", "2": "𝟮", "3": "𝟯", "4": "𝟰", "5": "𝟱", "6": "𝟲", "7": "𝟳", "8": "𝟴", "9": "𝟵"}, "sansItalic": {"A": "𝘈", "B": "𝘉", "C": "𝘊", "D": "𝘋", "E": "𝘌", "F": "𝘍", "G": "𝘎", "H": "𝘏", "I": "𝘐", "J": "𝘑", "K": "𝘒", "L": "𝘓", "M": "𝘔", "N": "𝘕", "O": "𝘖", "P": "𝘗", "Q": "𝘘", "R": "𝘙", "S": "𝘚", "T": "𝘛", "U": "𝘜", "V": "𝘝", "W": "𝘞", "X": "𝘟", "Y": "𝘠", "Z": "𝘡", "a": "𝘢", "b": "𝘣", "c": "𝘤", "d": "𝘥", "e": "𝘦", "f": "𝘧", "g": "𝘨", "h": "𝘩", "i": "𝘪", "j": "𝘫", "k": "𝘬", "l": "𝘭", "m": "𝘮", "n": "𝘯", "o": "𝘰", "p": "𝘱", "q": "𝘲", "r": "𝘳", "s": "𝘴", "t": "𝘵", "u": "𝘶", "v": "𝘷", "w": "𝘸", "x": "𝘹", "y": "𝘺", "z": "𝘻"}, "sansBoldItalic": {"A": "𝘼", "B": "𝘽", "C": "𝘾", "D": "𝘿", "E": "𝙀", "F": "𝙁", "G": "𝙂", "H": "𝙃", "I": "𝙄", "J": "𝙅", "K": "𝙆", "L": "𝙇", "M": "𝙈", "N": "𝙉", "O": "𝙊", "P": "𝙋", "Q": "𝙌", "R": "𝙍", "S": "𝙎", "T": "𝙏", "U": "𝙐", "V": "𝙑", "W": "𝙒", "X": "𝙓", "Y": "𝙔", "Z": "𝙕", "a": "𝙖", "b": "𝙗", "c": "𝙘", "d": "𝙙", "e": "𝙚", "f": "𝙛", "g": "𝙜", "h": "𝙝", "i": "𝙞", "j": "𝙟", "k": "𝙠", "l": "𝙡", "m": "𝙢", "n": "𝙣", "o": "𝙤", "p": "𝙥", "q": "𝙦", "r": "𝙧", "s": "𝙨", "t": "𝙩", "u": "𝙪", "v": "𝙫", "w": "𝙬", "x": "𝙭", "y": "𝙮", "z": "𝙯"}, "sans": {"A": "𝖠", "B": "𝖡", "C": "𝖢", "D": "𝖣", "E": "𝖤", "F": "𝖥", "G": "𝖦", "H": "𝖧", "I": "𝖨", "J": "𝖩", "K": "𝖪", "L": "𝖫", "M": "𝖬", "N": "𝖭", "O": "𝖮", "P": "𝖯", "Q": "𝖰", "R": "𝖱", "S": "𝖲", "T": "𝖳", "U": "𝖴", "V": "𝖵", "W": "𝖶", "X": "𝖷", "Y": "𝖸", "Z": "𝖹", "a": "𝖺", "b": "𝖻", "c": "𝖼", "d": "𝖽", "e": "𝖾", "f": "𝖿", "g": "𝗀", "h": "𝗁", "i": "𝗂", "j": "𝗃", "k": "𝗄", "l": "𝗅", "m": "𝗆", "n": "𝗇", "o": "𝗈", "p": "𝗉", "q": "𝗊", "r": "𝗋", "s": "𝗌", "t": "𝗍", "u": "𝗎", "v": "𝗏", "w": "𝗐", "x": "𝗑", "y": "𝗒", "z": "𝗓", "0": "𝟢", "1": "𝟣", "2": "𝟤", "3": "𝟥", "4": "𝟦", "5": "𝟧", "6": "𝟨", "7": "𝟩", "8": "𝟪", "9": "𝟫"}, "script": {"A": "𝒜", "B": "ℬ", "C": "𝒞", "D": "𝒟", "E": "ℰ", "F": "ℱ", "G": "𝒢", "H": "ℋ", "I": "ℐ", "J": "𝒥", "K": "𝒦", "L": "ℒ", "M": "ℳ", "N": "𝒩", "O": "𝒪", "P": "𝒫", "Q": "𝒬", "R": "ℛ", "S": "𝒮", "T": "𝒯", "U": "𝒰", "V": "𝒱", "W": "𝒲", "X": "𝒳", "Y": "𝒴", "Z": "𝒵", "a": "𝒶", "b": "𝒷", "c": "𝒸", "d": "𝒹", "e": "𝒺", "f": "𝒻", "g": "𝒼", "h": "𝒽", "i": "𝒾", "j": "𝒿", "k": "𝓀", "l": "𝓁", "m": "𝓂", "n": "𝓃", "o": "𝓄", "p": "𝓅", "q": "𝓆", "r": "𝓇", "s": "𝓈", "t": "𝓉", "u": "𝓊", "v": "𝓋", "w": "𝓌", "x": "𝓍", "y": "𝓎", "z": "𝓏"}, "boldScript": {"A": "𝓐", "B": "𝓑", "C": "𝓒", "D": "𝓓", "E": "𝓔", "F": "𝓕", "G": "𝓖", "H": "𝓗", "I": "𝓘", "J": "𝓙", "K": "𝓚", "L": "𝓛", "M": "𝓜", "N": "𝓝", "O": "𝓞", "P": "𝓟", "Q": "𝓠", "R": "𝓡", "S": "𝓢", "T": "𝓣", "U": "𝓤", "V": "𝓥", "W": "𝓦", "X": "𝓧", "Y": "𝓨", "Z": "𝓩", "a": "𝓪", "b": "𝓫", "c": "𝓬", "d": "𝓭", "e": "𝓮", "f": "𝓯", "g": "𝓰", "h": "𝓱", "i": "𝓲", "j": "𝓳", "k": "𝓴", "l": "𝓵", "m": "𝓶", "n": "𝓷", "o": "𝓸", "p": "𝓹", "q": "𝓺", "r": "𝓻", "s": "𝓼", "t": "𝓽", "u": "𝓾", "v": "𝓿", "w": "𝔀", "x": "𝔁", "y": "𝔂", "z": "𝔃"}, "fraktur": {"A": "𝔄", "B": "𝔅", "C": "ℭ", "D": "𝔇", "E": "𝔈", "F": "𝔉", "G": "𝔊", "H": "ℌ", "I": "ℑ", "J": "𝔍", "K": "𝔎", "L": "𝔏", "M": "𝔐", "N": "𝔑", "O": "𝔒", "P": "𝔓", "Q": "𝔔", "R": "ℜ", "S": "𝔖", "T": "𝔗", "U": "𝔘", "V": "𝔙", "W": "𝔚", "X": "𝔛", "Y": "𝔜", "Z": "ℨ", "a": "𝔞", "b": "𝔟", "c": "𝔠", "d": "𝔡", "e": "𝔢", "f": "𝔣", "g": "𝔤", "h": "𝔥", "i": "𝔦", "j": "𝔧", "k": "𝔨", "l": "𝔩", "m": "𝔪", "n": "𝔫", "o": "𝔬", "p": "𝔭", "q": "𝔮", "r": "𝔯", "s": "𝔰", "t": "𝔱", "u": "𝔲", "v": "𝔳", "w": "𝔴", "x": "𝔵", "y": "𝔶", "z": "𝔷"}, "boldFraktur": {"A": "𝕬", "B": "𝕭", "C": "𝕮", "D": "𝕯", "E": "𝕰", "F": "𝕱", "G": "𝕲", "H": "𝕳", "I": "𝕴", "J": "𝕵", "K": "𝕶", "L": "𝕷", "M": "𝕸", "N": "𝕹", "O": "𝕺", "P": "𝕻", "Q": "𝕼", "R": "𝕽", "S": "𝕾", "T": "𝕿", "U": "𝖀", "V": "𝖁", "W": "𝖂", "X": "𝖃", "Y": "𝖄", "Z": "𝖅", "a": "𝖆", "b": "𝖇", "c": "𝖈", "d": "𝖉", "e": "𝖊", "f": "𝖋", "g": "𝖌", "h": "𝖍", "i": "𝖎", "j": "𝖏", "k": "𝖐", "l": "𝖑", "m": "𝖒", "n": "𝖓", "o": "𝖔", "p": "𝖕", "q": "𝖖", "r": "𝖗", "s": "𝖘", "t": "𝖙", "u": "𝖚", "v": "𝖛", "w": "𝖜", "x": "𝖝", "y": "𝖞", "z": "𝖟"}, "doubleStruck": {"A": "𝔸", "B": "𝔹", "C": "ℂ", "D": "𝔻", "E": "𝔼", "F": "𝔽", "G": "𝔾", "H": "ℍ", "I": "𝕀", "J": "𝕁", "K": "𝕂", "L": "𝕃", "M": "𝕄", "N": "ℕ", "O": "𝕆", "P": "ℙ", "Q": "ℚ", "R": "ℝ", "S": "𝕊", "T": "𝕋", "U": "𝕌", "V": "𝕍", "W": "𝕎", "X": "𝕏", "Y": "𝕐", "Z": "ℤ", "a": "𝕒", "b": "𝕓", "c": "𝕔", "d": "𝕕", "e": "𝕖", "f": "𝕗", "g": "𝕘", "h": "𝕙", "i": "𝕚", "j": "𝕛", "k": "𝕜", "l": "𝕝", "m": "𝕞", "n": "𝕟", "o": "𝕠", "p": "𝕡", "q": "𝕢", "r": "𝕣", "s": "𝕤", "t": "𝕥", "u": "𝕦", "v": "𝕧", "w": "𝕨", "x": "𝕩", "y": "𝕪", "z": "𝕫", "0": "𝟘", "1": "𝟙", "2": "𝟚", "3": "𝟛", "4": "𝟜", "5": "𝟝", "6": "𝟞", "7": "𝟟", "8": "𝟠", "9": "𝟡"}, "monospace": {"A": "𝙰", "B": "𝙱", "C": "𝙲", "D": "𝙳", "E": "𝙴", "F": "𝙵", "G": "𝙶", "H": "𝙷", "I": "𝙸", "J": "𝙹", "K": "𝙺", "L": "𝙻", "M": "𝙼", "N": "𝙽", "O": "𝙾", "P": "𝙿", "Q": "𝚀", "R": "𝚁", "S": "𝚂", "T": "𝚃", "U": "𝚄", "V": "𝚅", "W": "𝚆", "X": "𝚇", "Y": "𝚈", "Z": "𝚉", "a": "𝚊", "b": "𝚋", "c": "𝚌", "d": "𝚍", "e": "𝚎", "f": "𝚏", "g": "𝚐", "h": "𝚑", "i": "𝚒", "j": "𝚓", "k": "𝚔", "l": "𝚕", "m": "𝚖", "n": "𝚗", "o": "𝚘", "p": "𝚙", "q": "𝚚", "r": "𝚛", "s": "𝚜", "t": "𝚝", "u": "𝚞", "v": "𝚟", "w": "𝚠", "x": "𝚡", "y": "𝚢", "z": "𝚣", "0": "𝟶", "1": "𝟷", "2": "𝟸", "3": "𝟹", "4": "𝟺", "5": "𝟻", "6": "𝟼", "7": "𝟽", "8": "𝟾", "9": "𝟿"}, "circled": {"A": "Ⓐ", "B": "Ⓑ", "C": "Ⓒ", "D": "Ⓓ", "E": "Ⓔ", "F": "Ⓕ", "G": "Ⓖ", "H": "Ⓗ", "I": "Ⓘ", "J": "Ⓙ", "K": "Ⓚ", "L": "Ⓛ", "M": "Ⓜ", "N": "Ⓝ", "O": "Ⓞ", "P": "Ⓟ", "Q": "Ⓠ", "R": "Ⓡ", "S": "Ⓢ", "T": "Ⓣ", "U": "Ⓤ", "V": "Ⓥ", "W": "Ⓦ", "X": "Ⓧ", "Y": "Ⓨ", "Z": "Ⓩ", "a": "ⓐ", "b": "ⓑ", "c": "ⓒ", "d": "ⓓ", "e": "ⓔ", "f": "ⓕ", "g": "ⓖ", "h": "ⓗ", "i": "ⓘ", "j": "ⓙ", "k": "ⓚ", "l": "ⓛ", "m": "ⓜ", "n": "ⓝ", "o": "ⓞ", "p": "ⓟ", "q": "ⓠ", "r": "ⓡ", "s": "ⓢ", "t": "ⓣ", "u": "ⓤ", "v": "ⓥ", "w": "ⓦ", "x": "ⓧ", "y": "ⓨ", "z": "ⓩ", "1": "①", "2": "②", "3": "③", "4": "④", "5": "⑤", "6": "⑥", "7": "⑦", "8": "⑧", "9": "⑨", "0": "⓪"}, "negCircled": {"A": "🅐", "B": "🅑", "C": "🅒", "D": "🅓", "E": "🅔", "F": "🅕", "G": "🅖", "H": "🅗", "I": "🅘", "J": "🅙", "K": "🅚", "L": "🅛", "M": "🅜", "N": "🅝", "O": "🅞", "P": "🅟", "Q": "🅠", "R": "🅡", "S": "🅢", "T": "🅣", "U": "🅤", "V": "🅥", "W": "🅦", "X": "🅧", "Y": "🅨", "Z": "🅩", "a": "🅐", "b": "🅑", "c": "🅒", "d": "🅓", "e": "🅔", "f": "🅕", "g": "🅖", "h": "🅗", "i": "🅘", "j": "🅙", "k": "🅚", "l": "🅛", "m": "🅜", "n": "🅝", "o": "🅞", "p": "🅟", "q": "🅠", "r": "🅡", "s": "🅢", "t": "🅣", "u": "🅤", "v": "🅥", "w": "🅦", "x": "🅧", "y": "🅨", "z": "🅩"}, "squared": {"A": "🄰", "B": "🄱", "C": "🄲", "D": "🄳", "E": "🄴", "F": "🄵", "G": "🄶", "H": "🄷", "I": "🄸", "J": "🄹", "K": "🄺", "L": "🄻", "M": "🄼", "N": "🄽", "O": "🄾", "P": "🄿", "Q": "🅀", "R": "🅁", "S": "🅂", "T": "🅃", "U": "🅄", "V": "🅅", "W": "🅆", "X": "🅇", "Y": "🅈", "Z": "🅉", "a": "🄰", "b": "🄱", "c": "🄲", "d": "🄳", "e": "🄴", "f": "🄵", "g": "🄶", "h": "🄷", "i": "🄸", "j": "🄹", "k": "🄺", "l": "🄻", "m": "🄼", "n": "🄽", "o": "🄾", "p": "🄿", "q": "🅀", "r": "🅁", "s": "🅂", "t": "🅃", "u": "🅄", "v": "🅅", "w": "🅆", "x": "🅇", "y": "🅈", "z": "🅉"}, "negSquared": {"A": "🅰", "B": "🅱", "C": "🅲", "D": "🅳", "E": "🅴", "F": "🅵", "G": "🅶", "H": "🅷", "I": "🅸", "J": "🅹", "K": "🅺", "L": "🅻", "M": "🅼", "N": "🅽", "O": "🅾", "P": "🅿", "Q": "🆀", "R": "🆁", "S": "🆂", "T": "🆃", "U": "🆄", "V": "🆅", "W": "🆆", "X": "🆇", "Y": "🆈", "Z": "🆉", "a": "🅰", "b": "🅱", "c": "🅲", "d": "🅳", "e": "🅴", "f": "🅵", "g": "🅶", "h": "🅷", "i": "🅸", "j": "🅹", "k": "🅺", "l": "🅻", "m": "🅼", "n": "🅽", "o": "🅾", "p": "🅿", "q": "🆀", "r": "🆁", "s": "🆂", "t": "🆃", "u": "🆄", "v": "🆅", "w": "🆆", "x": "🆇", "y": "🆈", "z": "🆉"}, "parenthesized": {"a": "⒜", "b": "⒝", "c": "⒞", "d": "⒟", "e": "⒠", "f": "⒡", "g": "⒢", "h": "⒣", "i": "⒤", "j": "⒥", "k": "⒦", "l": "⒧", "m": "⒨", "n": "⒩", "o": "⒪", "p": "⒫", "q": "⒬", "r": "⒭", "s": "⒮", "t": "⒯", "u": "⒰", "v": "⒱", "w": "⒲", "x": "⒳", "y": "⒴", "z": "⒵", "A": "⒜", "B": "⒝", "C": "⒞", "D": "⒟", "E": "⒠", "F": "⒡", "G": "⒢", "H": "⒣", "I": "⒤", "J": "⒥", "K": "⒦", "L": "⒧", "M": "⒨", "N": "⒩", "O": "⒪", "P": "⒫", "Q": "⒬", "R": "⒭", "S": "⒮", "T": "⒯", "U": "⒰", "V": "⒱", "W": "⒲", "X": "⒳", "Y": "⒴", "Z": "⒵"}, "fullwidth": {"A": "Ａ", "B": "Ｂ", "C": "Ｃ", "D": "Ｄ", "E": "Ｅ", "F": "Ｆ", "G": "Ｇ", "H": "Ｈ", "I": "Ｉ", "J": "Ｊ", "K": "Ｋ", "L": "Ｌ", "M": "Ｍ", "N": "Ｎ", "O": "Ｏ", "P": "Ｐ", "Q": "Ｑ", "R": "Ｒ", "S": "Ｓ", "T": "Ｔ", "U": "Ｕ", "V": "Ｖ", "W": "Ｗ", "X": "Ｘ", "Y": "Ｙ", "Z": "Ｚ", "a": "ａ", "b": "ｂ", "c": "ｃ", "d": "ｄ", "e": "ｅ", "f": "ｆ", "g": "ｇ", "h": "ｈ", "i": "ｉ", "j": "ｊ", "k": "ｋ", "l": "ｌ", "m": "ｍ", "n": "ｎ", "o": "ｏ", "p": "ｐ", "q": "ｑ", "r": "ｒ", "s": "ｓ", "t": "ｔ", "u": "ｕ", "v": "ｖ", "w": "ｗ", "x": "ｘ", "y": "ｙ", "z": "ｚ", "0": "０", "1": "１", "2": "２", "3": "３", "4": "４", "5": "５", "6": "６", "7": "７", "8": "８", "9": "９", " ": "　"}, "regional": {"A": "🇦", "B": "🇧", "C": "🇨", "D": "🇩", "E": "🇪", "F": "🇫", "G": "🇬", "H": "🇭", "I": "🇮", "J": "🇯", "K": "🇰", "L": "🇱", "M": "🇲", "N": "🇳", "O": "🇴", "P": "🇵", "Q": "🇶", "R": "🇷", "S": "🇸", "T": "🇹", "U": "🇺", "V": "🇻", "W": "🇼", "X": "🇽", "Y": "🇾", "Z": "🇿", "a": "🇦", "b": "🇧", "c": "🇨", "d": "🇩", "e": "🇪", "f": "🇫", "g": "🇬", "h": "🇭", "i": "🇮", "j": "🇯", "k": "🇰", "l": "🇱", "m": "🇲", "n": "🇳", "o": "🇴", "p": "🇵", "q": "🇶", "r": "🇷", "s": "🇸", "t": "🇹", "u": "🇺", "v": "🇻", "w": "🇼", "x": "🇽", "y": "🇾", "z": "🇿"}, "smallCaps": {"a": "ᴀ", "b": "ʙ", "c": "ᴄ", "d": "ᴅ", "e": "ᴇ", "f": "ꜰ", "g": "ɢ", "h": "ʜ", "i": "ɪ", "j": "ᴊ", "k": "ᴋ", "l": "ʟ", "m": "ᴍ", "n": "ɴ", "o": "ᴏ", "p": "ᴘ", "q": "ǫ", "r": "ʀ", "s": "s", "t": "ᴛ", "u": "ᴜ", "v": "ᴠ", "w": "ᴡ", "x": "x", "y": "ʏ", "z": "ᴢ", "A": "ᴀ", "B": "ʙ", "C": "ᴄ", "D": "ᴅ", "E": "ᴇ", "F": "ꜰ", "G": "ɢ", "H": "ʜ", "I": "ɪ", "J": "ᴊ", "K": "ᴋ", "L": "ʟ", "M": "ᴍ", "N": "ɴ", "O": "ᴏ", "P": "ᴘ", "Q": "ǫ", "R": "ʀ", "S": "s", "T": "ᴛ", "U": "ᴜ", "V": "ᴠ", "W": "ᴡ", "X": "x", "Y": "ʏ", "Z": "ᴢ"}, "upsideDown": {"a": "ɐ", "b": "q", "c": "ɔ", "d": "p", "e": "ǝ", "f": "ɟ", "g": "ƃ", "h": "ɥ", "i": "ᴉ", "j": "ɾ", "k": "ʞ", "l": "l", "m": "ɯ", "n": "u", "o": "o", "p": "d", "q": "b", "r": "ɹ", "s": "s", "t": "ʇ", "u": "n", "v": "ʌ", "w": "ʍ", "x": "x", "y": "ʎ", "z": "z", "A": "∀", "B": "B", "C": "Ɔ", "D": "D", "E": "Ǝ", "F": "Ⅎ", "G": "פ", "H": "H", "I": "I", "J": "ſ", "K": "ʞ", "L": "˥", "M": "W", "N": "N", "O": "O", "P": "Ԁ", "Q": "Q", "R": "ᴚ", "S": "S", "T": "┴", "U": "∩", "V": "Λ", "W": "M", "X": "X", "Y": "⅄", "Z": "Z"}};

const STYLE_LABELS = [
    ['bold', 'Bold (Sans-Serif)'],
    ['italic', 'Italic (Sans-Serif)'],
    ['boldItalic', 'Bold Italic'],
    ['sansBold', 'Sans Bold'],
    ['sansItalic', 'Sans Italic'],
    ['script', 'Script'],
    ['boldScript', 'Script Bold'],
    ['fraktur', 'Fraktur'],
    ['boldFraktur', 'Fraktur Bold'],
    ['doubleStruck', 'Double-Struck'],
    ['monospace', 'Monospace'],
    ['circled', 'Circled'],
    ['negCircled', 'Negative Circled'],
    ['squared', 'Squared'],
    ['negSquared', 'Negative Squared'],
    ['parenthesized', 'Parenthesized'],
    ['smallCaps', 'Small Caps'],
    ['fullwidth', 'Wide Text'],
    ['regional', 'Regional Indicators (Flags)'],
    ['upsideDown', 'Upside Down'],
];

function mapText(text, map) {
    return Array.from(text).map(ch => map[ch] !== undefined ? map[ch] : ch).join('');
}
function reversed(text) { return Array.from(text).reverse().join(''); }
function combine(text, mark) { return Array.from(text).map(ch => ch === ' ' ? ch : ch + mark).join(''); }
function surround(text, left, right) { return left + ' ' + text + ' ' + right; }

function renderAll() {
    const text = document.getElementById('fgInput').value || 'Your Pinterest Text';
    const rows = [];
    rows.push(['Normal Text', text]);
    STYLE_LABELS.forEach(([key, label]) => rows.push([label, mapText(text, FONT_MAPS[key])]));
    rows.push(['Reversed', reversed(text)]);
    rows.push(['Strikethrough', combine(text, '\u0336')]);
    rows.push(['Underline', combine(text, '\u0332')]);
    rows.push(['Underline (Double)', combine(text, '\u0333')]);
    rows.push(['Overline', combine(text, '\u0305')]);
    rows.push(['Surround with Stars', surround(text, '✨', '✨')]);
    rows.push(['Surround with Hearts', surround(text, '♡', '♡')]);
    rows.push(['Surround with Arrows', surround(text, '➤', '➤')]);
    rows.push(['Surround with Flowers', surround(text, '✿', '✿')]);
    rows.push(['Surround with Diamonds', surround(text, '◆', '◆')]);
    rows.push(['Surround with Circles', surround(text, '●', '●')]);
    rows.push(['Surround with Music Notes', surround(text, '♪', '♪')]);
    rows.push(['Surround with Waves', surround(text, '〜', '〜')]);
    rows.push(['Surround with Angle Brackets', surround(text, '《', '》')]);
    rows.push(['Surround with Braces', surround(text, '【', '】')]);

    const list = document.getElementById('fgList');
    list.innerHTML = rows.map(([label, styled], i) => `
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;">
            <div class="muted" style="font-size:12px;margin-bottom:4px;">${label}</div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="font-size:17px;word-break:break-word;">${styled}</div>
                <button type="button" class="btn-secondary btn-small" data-text="${styled.replace(/"/g, '&quot;')}" style="flex:none;">Copy</button>
            </div>
        </div>`).join('');

    list.querySelectorAll('[data-text]').forEach(btn => {
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(this.dataset.text).then(() => {
                const original = this.textContent;
                this.textContent = '✓ Copied';
                setTimeout(() => { this.textContent = original; }, 1200);
            });
        });
    });
}

document.getElementById('fgInput').addEventListener('input', renderAll);
renderAll();
</script>

</body>
</html>
