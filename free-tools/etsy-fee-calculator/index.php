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
    'title' => 'Etsy Fee Calculator: See Your Real Profit | ' . SITE_BRAND,
    'description' => 'Calculate Etsy listing, transaction, payment processing and shipping fees in seconds and see your true profit and margin per order. Free, no sign-up.',
    'canonical' => rtrim(APP_URL, '/') . '/free-tools/etsy-fee-calculator/',
    'breadcrumbs' => [['Free Tools', 'free-tools/'], ['Etsy Fee Calculator', 'free-tools/etsy-fee-calculator/']],
]); ?>
<link rel="stylesheet" href="../../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="../../assets/css/free-tool.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/free-tool.css') ?: time() ?>">
</head>
<body>

<?php render_site_header($pdo ?? null); ?>

<div class="container ft-page">
    <div class="ft-hero">
        <h1>Free Etsy Fee Calculator</h1>
        <p class="ft-sub">Estimate Etsy listing, transaction, and payment processing fees in seconds — see your true profit per order before you publish.</p>
    </div>

    <div class="ft-wrap">
        <div class="ft-panel ft-panel-left">
            <div class="form-row"><label>Selling Price ($)</label>
                <input type="number" id="ecPrice" value="20" min="0" step="0.01">
            </div>
            <div class="form-row"><label>Your Cost ($) <span class="muted">(materials, labor, etc.)</span></label>
                <input type="number" id="ecCost" value="10" min="0" step="0.01">
            </div>
            <div class="form-row"><label>Shipping Charged To Buyer ($) <span class="muted">(optional)</span></label>
                <input type="number" id="ecShipping" value="0" min="0" step="0.01">
            </div>
            <div class="ft-switch-row" style="margin-bottom:10px;">
                <div class="ft-switch-label">Include Offsite Ads Fee</div>
                <label class="ft-switch"><input type="checkbox" id="ecOffsiteAds"><span></span></label>
            </div>
            <div class="form-row" id="ecOffsiteWrap" style="display:none;">
                <label>Offsite Ads Fee</label>
                <select id="ecOffsiteRate">
                    <option value="0.12">12% (Star Seller)</option>
                    <option value="0.15">15% (Standard)</option>
                </select>
            </div>
            <button type="button" id="ecCalcBtn" class="btn-primary ft-generate-btn">Calculate Profit</button>
        </div>

        <div class="ft-panel ft-panel-right">
            <div id="ecResult">
                <table style="width:100%;border-collapse:collapse;font-size:15px;">
                    <tbody id="ecTableBody"></tbody>
                </table>
                <div style="margin-top:16px;padding:16px;border-radius:10px;background:var(--light);text-align:center;">
                    <div class="muted" style="font-size:13px;">Your Profit</div>
                    <div id="ecProfit" style="font-size:32px;font-weight:800;color:var(--red);">$0.00</div>
                    <div id="ecMargin" class="muted" style="font-size:13px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="ft-marketing">
        <div class="ft-marketing-badge">⏱ Limited Time Offer: Use code PIN20 for 20% off today!</div>
        <h2>Want your Etsy listings pinned automatically?</h2>
        <p>Sign up free to turn your Etsy listings into scheduled Pinterest pins that send ready-to-buy shoppers back to your shop.</p>
        <a href="../../auth/register" class="btn-primary ft-marketing-cta">Start Pinning For Free →</a>
    </div>

    <div class="ft-faq" style="max-width:780px;">
        <h2 style="text-align:left;">Understanding What Etsy Actually Takes From Every Sale</h2>
        <p>Etsy's pricing can feel deceptively simple from the seller dashboard — you set a price, someone buys, money shows up. What's easy to lose track of is how many separate fees stack on top of each other between that sale price and what actually lands in your account. A $20 sale doesn't net you $20 minus your materials; it nets you $20 minus a listing fee, minus a transaction fee, minus a payment processing fee, and potentially minus an advertising fee too, before your cost of goods ever enters the picture.</p>
        <p>The listing fee is the simplest piece: a flat $0.20 charged when you publish or renew a listing, regardless of whether it sells. It's a small number per item, but it adds up across a catalog of dozens or hundreds of listings, especially for sellers who let listings auto-renew every four months whether or not they're selling.</p>
        <p>The transaction fee is where the real cost lives — 6.5% of the total amount a buyer pays, which importantly includes shipping, not just the item price. A seller who builds shipping into the item price and one who charges shipping separately end up paying roughly the same transaction fee on the same total sale, so "free shipping" doesn't actually reduce what Etsy takes; it just moves where the cost sits inside your price.</p>
        <p>On top of that sits the payment processing fee — 3% of the total plus a flat $0.25 per order — which covers the cost of actually moving the buyer's money through Etsy Payments. This one is a genuinely fixed cost of doing business online; it's the same category of fee you'd pay through Stripe, PayPal, or any other payment processor, just bundled into Etsy's system instead of a separate one you'd manage yourself.</p>
        <p>Offsite Ads is the fee most sellers underestimate, mostly because it's conditional — it only applies when a specific sale is actually attributed to one of Etsy's external ads (on Google, Facebook, Instagram, and similar). When it applies, it's a meaningful cut: 12% of that sale for Star Sellers, 15% for everyone else, on top of the standard transaction and payment fees. Shops under a certain revenue threshold can opt out of Offsite Ads entirely; shops above it are automatically enrolled and can't turn it off, which makes it worth knowing whether your shop falls into that mandatory bucket.</p>
        <p>Run all four together and the gap between "sale price" and "money you keep" is bigger than most new sellers expect — often somewhere around 15-20% of the total sale before your own materials and labor cost are even subtracted. That's exactly the blind spot this calculator is built to close: plug in a price and a cost, and see the actual profit per order, not the sale price you were hoping meant the same thing.</p>
    </div>

    <div class="ft-faq">
        <h2>Frequently Asked Questions</h2>
        <details><summary>What fees does Etsy charge on every sale?</summary><p>A $0.20 flat listing fee per item, a 6.5% transaction fee on the total sale amount (including shipping), and a 3% + $0.25 payment processing fee. Offsite Ads fees (12-15%) apply only when a specific sale comes through one of Etsy's external ads.</p></details>
        <details><summary>Does the transaction fee apply to shipping too?</summary><p>Yes — Etsy's 6.5% transaction fee is calculated on the total amount the buyer pays, including whatever you charge for shipping, not just the item price itself.</p></details>
        <details><summary>Does "free shipping" actually save me money on fees?</summary><p>Not really. If you roll your shipping cost into the item price and offer "free" shipping, Etsy still takes its percentage on that same total amount — the fee cost is essentially identical either way, just distributed differently across the price you display.</p></details>
        <details><summary>Can I opt out of Offsite Ads fees?</summary><p>It depends on your shop's revenue. Shops under Etsy's revenue threshold can opt out of Offsite Ads entirely. Shops above that threshold are automatically enrolled and cannot opt out — check your Shop Manager settings to see which category your shop falls into.</p></details>
        <details><summary>What's the difference between the transaction fee and the payment processing fee?</summary><p>The transaction fee (6.5%) is what Etsy charges for the use of its marketplace and search platform. The payment processing fee (3% + $0.25) covers the cost of actually processing the buyer's card or payment method through Etsy Payments — they're billed separately but both come out of every sale.</p></details>
        <details><summary>Do digital and printable products pay the same fees?</summary><p>Yes — digital products still incur the listing fee, transaction fee, and payment processing fee, even though there's no shipping involved. Your "cost" for a digital product typically includes design time and any software or licensing costs rather than materials.</p></details>
        <details><summary>How much should I mark up my products to stay profitable?</summary><p>There's no universal number, but many sellers aim for a healthy margin after all fees and costs — commonly cited targets sit in the 40-50%+ range once materials, time, and Etsy's fees are all accounted for, though this varies a lot by category and how you value your own labor.</p></details>
        <details><summary>Are these fee percentages the same worldwide?</summary><p>They reflect Etsy's standard US rates. Fees can vary somewhat by seller location, currency, and local tax rules (like VAT in the EU), so always cross-check against your own Shop Manager for the exact rates that apply to your account.</p></details>
        <details><summary>Does this calculator include sales tax?</summary><p>No — sales tax is collected separately by Etsy in many jurisdictions and passed directly to tax authorities, so it doesn't factor into your profit calculation. It's a pass-through cost to the buyer, not a fee that reduces your revenue the way listing, transaction, and processing fees do.</p></details>
    </div>
</div>

<?php render_site_footer($pdo); ?>
<script>
document.getElementById('ecOffsiteAds').addEventListener('change', function () {
    document.getElementById('ecOffsiteWrap').style.display = this.checked ? '' : 'none';
    calc();
});
['ecPrice', 'ecCost', 'ecShipping', 'ecOffsiteRate'].forEach(id => {
    document.getElementById(id).addEventListener('input', calc);
});
document.getElementById('ecCalcBtn').addEventListener('click', calc);

function calc() {
    const price = parseFloat(document.getElementById('ecPrice').value) || 0;
    const cost = parseFloat(document.getElementById('ecCost').value) || 0;
    const shipping = parseFloat(document.getElementById('ecShipping').value) || 0;
    const total = price + shipping;

    const listingFee = 0.20;
    const transactionFee = total * 0.065;
    const paymentFee = total * 0.03 + 0.25;
    const offsiteEnabled = document.getElementById('ecOffsiteAds').checked;
    const offsiteRate = parseFloat(document.getElementById('ecOffsiteRate').value) || 0;
    const offsiteFee = offsiteEnabled ? total * offsiteRate : 0;

    const totalFees = listingFee + transactionFee + paymentFee + offsiteFee;
    const profit = total - totalFees - cost;
    const margin = total > 0 ? (profit / total) * 100 : 0;

    const rows = [
        ['Sale Total (price + shipping)', total],
        ['Listing Fee', -listingFee],
        ['Transaction Fee (6.5%)', -transactionFee],
        ['Payment Processing (3% + $0.25)', -paymentFee],
    ];
    if (offsiteEnabled) rows.push(['Offsite Ads Fee (' + (offsiteRate * 100) + '%)', -offsiteFee]);
    rows.push(['Your Cost', -cost]);

    document.getElementById('ecTableBody').innerHTML = rows.map(([label, val]) => `
        <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 6px;">${label}</td>
            <td style="padding:8px 6px;text-align:right;${val < 0 ? 'color:var(--red);' : ''}">${val < 0 ? '-' : ''}$${Math.abs(val).toFixed(2)}</td>
        </tr>`).join('');

    const profitEl = document.getElementById('ecProfit');
    profitEl.textContent = (profit < 0 ? '-' : '') + '$' + Math.abs(profit).toFixed(2);
    profitEl.style.color = profit >= 0 ? 'var(--green)' : 'var(--red)';
    document.getElementById('ecMargin').textContent = total > 0 ? margin.toFixed(1) + '% margin' : '';
}
calc();
</script>

</body>
</html>
