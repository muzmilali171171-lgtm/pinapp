/* Use-case pages: reveal on scroll, count-up numbers, scroll progress bar, parallax blobs, pricing toggle. */
(function () {
    'use strict';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Reveal on scroll (also used by the step rows)
    var els = document.querySelectorAll('.rg-reveal, .uc-step-row');
    if (!('IntersectionObserver' in window) || reduce) {
        els.forEach(function (e) { e.classList.add('rg-in'); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('rg-in'); io.unobserve(en.target); } });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        els.forEach(function (e) { io.observe(e); });
    }

    // Count-up numbers
    var nums = document.querySelectorAll('[data-count]');
    function countUp(el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce) { el.textContent = end.toLocaleString(); return; }
        var start = null, dur = 1400;
        function step(t) {
            if (!start) start = t;
            var p = Math.min(1, (t - start) / dur), eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(end * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    if ('IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { countUp(en.target); cio.unobserve(en.target); } });
        }, { threshold: 0.6 });
        nums.forEach(function (n) { cio.observe(n); });
    } else nums.forEach(countUp);

    // Scroll progress + parallax (one rAF per frame)
    var bar = document.querySelector('.uc-progress span');
    var blobs = document.querySelectorAll('[data-parallax]');
    var ticking = false;
    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            var h = document.documentElement.scrollHeight - innerHeight;
            if (bar) bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, scrollY / h) : 0) + ')';
            if (!reduce) blobs.forEach(function (b) { b.style.transform = 'translateY(' + (scrollY * parseFloat(b.getAttribute('data-parallax'))) + 'px)'; });
            ticking = false;
        });
    }
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Pricing monthly / annual toggle
    document.querySelectorAll('.uc-billing-toggle').forEach(function (tg) {
        tg.addEventListener('change', function () {
            var yearly = tg.checked;
            document.querySelectorAll('.uc-plan').forEach(function (c) {
                var d = c.dataset, disc = +(yearly ? d.yearlyDiscount : d.monthlyDiscount);
                var q = function (s) { return c.querySelector(s); };
                if (q('.js-price')) q('.js-price').textContent = yearly ? d.yearlyMonthlyEquiv : d.monthlyFinal;
                if (q('.js-off')) { q('.js-off').style.display = disc > 0 ? 'inline-block' : 'none'; q('.js-off').textContent = disc + '% OFF'; }
                if (q('.js-was')) { q('.js-was').style.display = disc > 0 ? 'block' : 'none'; q('.js-was').textContent = 'Was $' + (yearly ? (d.yearlyBase / 12).toFixed(2) : d.monthlyBase) + '/month'; }
                if (q('.js-note')) { q('.js-note').hidden = !yearly; q('.js-note').textContent = 'Billed annually at $' + d.yearlyFinal; }
                if (q('.js-buy')) q('.js-buy').setAttribute('href', yearly ? d.checkoutYearly : d.checkoutMonthly);
            });
        });
    });

    // Speed: pause animations in sections that are off-screen, and load/play step videos only
    // while they're visible (they used to download on page load).
    if ('IntersectionObserver' in window) {
        var secIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { en.target.classList.toggle('is-off', !en.isIntersecting); });
        }, { rootMargin: '150px 0px' });
        document.querySelectorAll('body > section, .uc-marquee').forEach(function (s) { secIO.observe(s); });
        var vidIO = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var v = en.target;
                if (en.isIntersecting) {
                    if (!v.dataset.loaded) {
                        v.querySelectorAll('source[data-src]').forEach(function (s) { s.src = s.getAttribute('data-src'); });
                        v.load();
                        v.dataset.loaded = '1';
                    }
                    var p = v.play(); if (p && p.catch) p.catch(function () {});
                } else if (!v.paused) v.pause();
            });
        }, { rootMargin: '200px 0px' });
        document.querySelectorAll('video[data-lazy-video]').forEach(function (v) { vidIO.observe(v); });
    } else {
        document.querySelectorAll('video[data-lazy-video]').forEach(function (v) {
            v.querySelectorAll('source[data-src]').forEach(function (s) { s.src = s.getAttribute('data-src'); });
            v.load(); v.play();
        });
    }
})();
