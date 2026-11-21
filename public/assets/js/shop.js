/* SasthoBazar storefront JS (customer side). Needs app.js (window.SB) loaded first. */
(function () {
    'use strict';

    var body = document.body;
    var isBn = (body.dataset.lang || document.documentElement.lang) === 'bn';
    var BN = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    function digits(s) { s = String(s); return isBn ? s.replace(/[0-9]/g, function (d) { return BN[d]; }) : s; }
    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    }

    /* ---------------- Toast ---------------- */
    var toastEl = document.getElementById('sbToast');
    function toast(msg) {
        if (!toastEl || !msg) return;
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2400);
    }
    window.SB = window.SB || {};
    window.SB.toast = toast;

    /* ---------------- Banner slider ---------------- */
    document.querySelectorAll('[data-slider]').forEach(function (box) {
        var slides = box.querySelectorAll('.sb-slide');
        var dots = box.querySelectorAll('[data-slide-to]');
        if (slides.length < 2) return;
        var cur = 0, timer = null;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function go(i) {
            i = (i + slides.length) % slides.length;
            slides[cur].classList.remove('show');
            if (dots[cur]) dots[cur].classList.remove('on');
            cur = i;
            slides[cur].classList.add('show');
            if (dots[cur]) dots[cur].classList.add('on');
        }
        function start() { if (!reduce) { stop(); timer = setInterval(function () { go(cur + 1); }, 5000); } }
        function stop() { if (timer) clearInterval(timer); timer = null; }
        dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.slideTo); start(); }); });
        var prev = box.querySelector('[data-slide-prev]'), next = box.querySelector('[data-slide-next]');
        if (prev) prev.addEventListener('click', function () { go(cur - 1); start(); });
        if (next) next.addEventListener('click', function () { go(cur + 1); start(); });
        box.addEventListener('mouseenter', stop);
        box.addEventListener('mouseleave', start);
        // swipe on touch screens
        var x0 = null;
        box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1));
            x0 = null; start();
        });
        document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
        start();
    });

    /* ---------------- Live search suggestions ---------------- */
    var form = document.querySelector('.sb-search[data-suggest-url]');
    if (form) {
        var input = form.querySelector('input[name="q"]');
        var box = form.querySelector('.sb-suggest');
        var api = form.dataset.suggestUrl;
        var debounce = null, ctrl = null, lastQ = null, cache = {};
        var labels = isBn
            ? { products: 'পণ্য', cats: 'ক্যাটাগরি', brands: 'ব্র্যান্ড', all: 'সব ফলাফল দেখুন', none: 'কিছু পাওয়া যায়নি', out: 'স্টক নেই' }
            : { products: 'Products', cats: 'Categories', brands: 'Brands', all: 'See all results', none: 'No matches found', out: 'Out of stock' };

        function close() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); }
        function items() { return Array.prototype.slice.call(box.querySelectorAll('a')); }
        function setActive(i) {
            var list = items();
            list.forEach(function (a) { a.classList.remove('active'); a.removeAttribute('aria-selected'); });
            if (i >= 0 && list[i]) { list[i].classList.add('active'); list[i].setAttribute('aria-selected', 'true'); list[i].scrollIntoView({ block: 'nearest' }); }
            box._active = i;
        }
        function render(data) {
            box.textContent = '';
            box._active = -1;
            if (data.categories.length || data.brands.length) {
                if (data.categories.length) {
                    box.appendChild(el('div', 'sb-sg-head', labels.cats));
                    data.categories.forEach(function (c) { var a = el('a', 'sb-sg-chip', c.name); a.href = c.url; a.setAttribute('role', 'option'); box.appendChild(a); });
                }
                if (data.brands.length) {
                    box.appendChild(el('div', 'sb-sg-head', labels.brands));
                    data.brands.forEach(function (b) { var a = el('a', 'sb-sg-chip', b.name); a.href = b.url; a.setAttribute('role', 'option'); box.appendChild(a); });
                }
            }
            if (data.products.length) {
                box.appendChild(el('div', 'sb-sg-head', labels.products));
                data.products.forEach(function (p) {
                    var a = el('a', 'sb-sg-item');
                    a.href = p.url;
                    a.setAttribute('role', 'option');
                    var img = el('span', 'sb-sg-img');
                    if (p.image) { var im = el('img'); im.src = p.image; im.alt = ''; im.loading = 'lazy'; img.appendChild(im); }
                    else img.appendChild(el('i', 'bi bi-capsule'));
                    var mid = el('span', 'sb-sg-body');
                    mid.appendChild(el('span', 'sb-sg-name', p.name));
                    var sub = [p.sub, p.brand].filter(Boolean).join(' · ');
                    if (p.out) sub = labels.out + (sub ? ' · ' + sub : '');
                    if (sub) mid.appendChild(el('span', 'sb-sg-sub', sub));
                    var price = el('span', 'sb-sg-price');
                    price.appendChild(el('b', null, p.price));
                    if (p.mrp) price.appendChild(el('s', null, p.mrp));
                    a.appendChild(img); a.appendChild(mid); a.appendChild(price);
                    box.appendChild(a);
                });
            }
            if (!data.products.length && !data.categories.length && !data.brands.length) {
                box.appendChild(el('div', 'sb-sg-empty', labels.none));
            } else {
                var all = el('a', 'sb-sg-all', labels.all + ' →');
                all.href = data.all_url;
                box.appendChild(all);
            }
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        function lookup(q) {
            if (cache[q]) { render(cache[q]); return; }
            if (ctrl) ctrl.abort();
            ctrl = window.AbortController ? new AbortController() : null;
            fetch(api + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || input.value.trim() !== q) return;
                    cache[q] = data;
                    render(data);
                })
                .catch(function () { /* aborted or offline */ });
        }
        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(debounce);
            if (q.length < 2) { close(); lastQ = null; return; }
            if (q === lastQ) return;
            debounce = setTimeout(function () { lastQ = q; lookup(q); }, 220);
        });
        input.addEventListener('focus', function () { if (box.childNodes.length && input.value.trim().length >= 2) { box.hidden = false; input.setAttribute('aria-expanded', 'true'); } });
        input.addEventListener('keydown', function (e) {
            if (box.hidden) return;
            var n = items().length, i = box._active == null ? -1 : box._active;
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(n ? (i + 1) % n : -1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(n ? (i - 1 + n) % n : -1); }
            else if (e.key === 'Enter' && i >= 0 && items()[i]) { e.preventDefault(); window.location.href = items()[i].href; }
            else if (e.key === 'Escape') { close(); }
        });
        document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
        form.addEventListener('submit', function (e) { if (!input.value.trim()) { e.preventDefault(); input.focus(); } });
    }

    /* ---------------- Wishlist (heart buttons) ---------------- */
    var wishUrl = body.dataset.wishUrl, loginUrl = body.dataset.loginUrl;
    function setCount(n) {
        document.querySelectorAll('[data-wish-count]').forEach(function (c) { c.textContent = digits(n); c.hidden = !n; });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wish]');
        if (!btn || !wishUrl) return;
        e.preventDefault();
        if (btn.classList.contains('busy')) return;
        btn.classList.add('busy');
        var fd = new FormData();
        fd.append('product_id', btn.dataset.wish);
        SB.fetch(wishUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) { window.location.href = loginUrl; return null; }
                return r.json().then(function (d) { return { ok: r.ok, d: d }; });
            })
            .then(function (res) {
                if (!res) return;
                if (!res.ok) { toast(res.d.message || res.d.error); return; }
                var on = !!res.d.in_wishlist;
                document.querySelectorAll('[data-wish="' + btn.dataset.wish + '"]').forEach(function (b) {
                    b.classList.toggle('on', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    var i = b.querySelector('i');
                    if (i) i.className = 'bi ' + (on ? 'bi-heart-fill' : 'bi-heart');
                });
                setCount(res.d.count);
                toast(res.d.message);
                // on the wishlist page, removing a product removes its card
                var page = document.querySelector('[data-wishlist-page]');
                if (page && !on) {
                    var card = btn.closest('.sb-card');
                    if (card) card.remove();
                    if (!page.querySelector('.sb-card')) {
                        var empty = page.querySelector('[data-wishlist-empty]');
                        if (empty) empty.hidden = false;
                    }
                }
            })
            .catch(function () { toast(isBn ? 'নেটওয়ার্ক সমস্যা, আবার চেষ্টা করুন' : 'Network problem, please try again'); })
            .then(function () { btn.classList.remove('busy'); });
    });

    /* ---------------- Product gallery ---------------- */
    var mainImg = document.getElementById('pdMainImg');
    document.querySelectorAll('[data-gallery-thumb]').forEach(function (t) {
        t.addEventListener('click', function () {
            if (!mainImg) return;
            mainImg.src = t.dataset.galleryThumb;
            mainImg.alt = t.dataset.alt || '';
            document.querySelectorAll('[data-gallery-thumb]').forEach(function (x) { x.classList.toggle('active', x === t); });
        });
    });

    /* ---------------- Variant switcher (price, discount, stock) ---------------- */
    document.querySelectorAll('input[name="variant"][data-price]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (!r.checked) return;
            var q = function (s) { return document.querySelector(s); };
            var price = q('[data-pd-price]'), mrp = q('[data-pd-mrp]'), save = q('[data-pd-save]'), badge = q('[data-pd-offbadge]'), stock = q('[data-pd-stock]');
            if (price) price.textContent = r.dataset.price;
            if (mrp) { mrp.textContent = r.dataset.mrp; mrp.hidden = !r.dataset.off; }
            if (save) { save.textContent = r.dataset.save; save.hidden = !r.dataset.save; }
            if (badge) { badge.textContent = r.dataset.off; badge.hidden = !r.dataset.off; }
            if (stock) {
                var st = r.dataset.stock;
                stock.className = 'sb-stock sb-stock-' + st;
                stock.querySelector('i').className = 'bi ' + (st === 'out' ? 'bi-x-circle' : st === 'low' ? 'bi-exclamation-triangle' : 'bi-check-circle');
                stock.querySelector('span').textContent = r.dataset.stockText;
            }
            var vi = q('[data-variant-input]');
            if (vi) vi.value = r.value;
            document.querySelectorAll('[data-buy-btn]').forEach(function (b) { b.disabled = r.dataset.stock === 'out'; });
            if (window.history && history.replaceState) {
                var u = new URL(window.location.href);
                u.searchParams.set('variant', r.value);
                history.replaceState(null, '', u.toString());
            }
        });
    });

    /* ---------------- Quantity +/- ---------------- */
    document.querySelectorAll('[data-qty]').forEach(function (box) {
        var input = box.querySelector('input');
        function clamp(v) {
            var min = +input.min || 1, max = +input.max || 99;
            v = parseInt(v, 10);
            return isNaN(v) ? min : Math.max(min, Math.min(max, v));
        }
        box.addEventListener('click', function (e) {
            var b = e.target.closest('[data-qty-step]');
            if (!b) return;
            var v = clamp(+input.value + +b.dataset.qtyStep);
            if (String(v) !== String(input.value)) { input.value = v; input.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        input.addEventListener('change', function () { input.value = clamp(input.value); });
    });

    /* Countdown (flash sale): see app.js */

    /* ---------------- Auth gate: Guest checkout / Login tabs, guest contact form ---------------- */
    var authBox = document.querySelector('[data-auth-tabs]');
    if (authBox) {
        var showTab = function (name) {
            var panel = authBox.querySelector('[data-auth-panel="' + name + '"]');
            if (!panel) { return false; }
            authBox.querySelectorAll('[data-auth-panel]').forEach(function (p) { p.hidden = p !== panel; });
            authBox.querySelectorAll('[data-auth-tab]').forEach(function (t) {
                var on = t.getAttribute('data-auth-tab') === name;
                t.classList.toggle('on', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            if (window.history && history.replaceState) { history.replaceState(null, '', '?tab=' + name); }
            var first = panel.querySelector('input:not([type=hidden]):not([type=radio]):not([disabled])');
            if (first) { first.focus(); }
            return true;
        };
        authBox.addEventListener('click', function (ev) {
            var t = ev.target.closest('[data-auth-tab], [data-auth-switch]');
            if (t && showTab(t.getAttribute('data-auth-tab') || t.getAttribute('data-auth-switch'))) { ev.preventDefault(); }
        });
        var gForm = authBox.querySelector('[data-guest-form]');
        if (gForm) {
            var phoneRe = /^01[3-9]\d{8}$/, emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            var bnToEn = function (v) { return v.replace(/[০-৯]/g, function (d) { return String('০১২৩৪৫৬৭৮৯'.indexOf(d)); }); };
            var setType = function (type) {
                gForm.querySelectorAll('[data-guest-field]').forEach(function (box) {
                    var on = box.getAttribute('data-guest-field') === type, input = box.querySelector('input');
                    box.hidden = !on;
                    input.disabled = !on;
                    input.required = on;
                    if (!on) { input.value = ''; input.classList.remove('is-invalid'); box.querySelector('.invalid-feedback').classList.remove('d-block'); }
                });
            };
            gForm.querySelectorAll('input[name=contact_type]').forEach(function (r) {
                r.addEventListener('change', function () { setType(r.value); var i = gForm.querySelector('[data-guest-field="' + r.value + '"] input'); if (i) { i.focus(); } });
            });
            gForm.addEventListener('submit', function (ev) {
                var type = (gForm.querySelector('input[name=contact_type]:checked') || {}).value || 'phone';
                var input = gForm.querySelector('[data-guest-field="' + type + '"] input');
                var v = bnToEn(input.value.trim()).replace(/[\s-]/g, '');
                if (type === 'phone') { v = v.replace(/^\+?88/, ''); }
                var ok = type === 'phone' ? phoneRe.test(v) : emailRe.test(v);
                input.classList.toggle('is-invalid', !ok);
                gForm.querySelector('[data-guest-error="' + type + '"]').classList.toggle('d-block', !ok);
                if (!ok) { ev.preventDefault(); input.focus(); return; }
                input.value = v;
            });
        }
    }

    /* ---------------- Guest checkout: verify mobile / email by OTP ---------------- */
    var otp = document.querySelector('[data-otp-box]');
    if (otp) {
        var oMeta = document.querySelector('meta[name="csrf-token"]');
        var oSend = otp.querySelector('[data-otp-send]'), oLabel = otp.querySelector('[data-otp-send-label]');
        var oCode = otp.querySelector('[data-otp-code]'), oVerify = otp.querySelector('[data-otp-verify]');
        var oMsg = otp.querySelector('[data-otp-msg]'), oTimer = null, oSendText = oLabel ? oLabel.textContent : '';
        var oPost = function (url, data) {
            var body = new URLSearchParams(data || {});
            return fetch(url, {
                method: 'POST', credentials: 'same-origin', body: body,
                headers: { 'X-CSRF-TOKEN': oMeta ? oMeta.content : '', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Error ' + r.status }; }); });
        };
        var oSay = function (text, bad) { oMsg.textContent = text || ''; oMsg.className = 'small mt-2 ' + (bad ? 'text-danger' : 'text-success'); };
        var oDone = function (text) {
            otp.classList.add('ok');
            otp.querySelector('[data-otp-todo]').hidden = true;
            otp.querySelector('[data-otp-done]').hidden = false;
            var fb = otp.querySelector('.invalid-feedback'); if (fb) { fb.remove(); }
            if (text) { oSay(text, false); }
        };
        var oCountdown = function (sec) {
            clearInterval(oTimer);
            oSend.disabled = true;
            var left = sec;
            var tick = function () {
                if (left <= 0) { clearInterval(oTimer); oSend.disabled = false; oLabel.textContent = oSendText; return; }
                oLabel.textContent = oSendText + ' (' + left + ')';
                left--;
            };
            tick(); oTimer = setInterval(tick, 1000);
        };
        oSend.addEventListener('click', function () {
            oSend.disabled = true;
            oPost(otp.getAttribute('data-send-url')).then(function (r) {
                if (r.verified) { oDone(r.message); return; }
                if (!r.ok) { oSay(r.error, true); oCountdown(r.wait || 5); return; }
                var t = r.message || '';
                if (r.dev_code) { t += ' [TEST CODE: ' + r.dev_code + ']'; }
                oSay(t, false);
                oCode.hidden = false; oVerify.hidden = false; oCode.focus();
                oCountdown(r.wait || 60);
            }).catch(function () { oSend.disabled = false; });
        });
        var oCheck = function () {
            var code = oCode.value.replace(/[০-৯]/g, function (d) { return String('০১২৩৪৫৬৭৮৯'.indexOf(d)); }).replace(/\D/g, '');
            if (code.length !== 6) { oCode.classList.add('is-invalid'); oCode.focus(); return; }
            oCode.classList.remove('is-invalid');
            oVerify.disabled = true;
            oPost(otp.getAttribute('data-verify-url'), { code: code }).then(function (r) {
                oVerify.disabled = false;
                if (r.ok) { oDone(r.message); } else { oSay(r.error, true); oCode.select(); }
            }).catch(function () { oVerify.disabled = false; });
        };
        oVerify.addEventListener('click', oCheck);
        oCode.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); oCheck(); } });
        oCode.addEventListener('input', function () { if (oCode.value.replace(/\D/g, '').length === 6 && /^\d+$/.test(oCode.value)) { oCheck(); } });
    }

    /* ---------------- Order by prescription: file picker preview + drag & drop ---------------- */
    var rxForm = document.querySelector('[data-rx-form]');
    if (rxForm) {
        var rxInput = rxForm.querySelector('[data-rx-input]'), rxList = rxForm.querySelector('[data-rx-list]'), rxDrop = rxForm.querySelector('[data-rx-drop]');
        var rxMax = parseInt(rxInput.getAttribute('data-max'), 10) || 5;
        var rxShow = function () {
            rxList.innerHTML = '';
            Array.prototype.forEach.call(rxInput.files, function (f, i) {
                var li = document.createElement('li'), th = document.createElement('span'), nm = document.createElement('span');
                th.className = 'thumb'; nm.className = 'nm'; nm.textContent = f.name;
                if (/^image\//.test(f.type)) { th.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')'; }
                else { th.innerHTML = '<i class="bi bi-file-earmark-pdf"></i>'; }
                if (i >= rxMax || !/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type)) { li.className = 'bad'; }
                li.appendChild(th); li.appendChild(nm); rxList.appendChild(li);
            });
            rxDrop.classList.toggle('is-invalid', rxInput.files.length > rxMax);
        };
        rxInput.addEventListener('change', rxShow);
        ['dragenter', 'dragover'].forEach(function (t) { rxDrop.addEventListener(t, function (ev) { ev.preventDefault(); rxDrop.classList.add('drag'); }); });
        ['dragleave', 'drop'].forEach(function (t) { rxDrop.addEventListener(t, function () { rxDrop.classList.remove('drag'); }); });
        rxDrop.addEventListener('drop', function (ev) {
            ev.preventDefault();
            if (ev.dataTransfer && ev.dataTransfer.files.length) { rxInput.files = ev.dataTransfer.files; rxShow(); }
        });
        rxForm.addEventListener('submit', function (ev) {
            if (!rxInput.files.length || rxInput.files.length > rxMax) { ev.preventDefault(); rxDrop.classList.add('is-invalid'); rxDrop.scrollIntoView({ block: 'center' }); }
        });
    }

    /* ---------------- Promotional popup (once a day) ---------------- */
    var popup = document.getElementById('sbPopup');
    if (popup) {
        var pKey = popup.getAttribute('data-popup-key'), seen = null;
        try { seen = localStorage.getItem(pKey); } catch (e) { seen = null; }
        if (!seen || Date.now() - parseInt(seen, 10) > 86400000) {
            var closePopup = function () {
                popup.hidden = true;
                try { localStorage.setItem(pKey, String(Date.now())); } catch (e) { /* private mode */ }
                document.removeEventListener('keydown', onEsc);
            };
            var onEsc = function (ev) { if (ev.key === 'Escape') { closePopup(); } };
            setTimeout(function () {
                popup.hidden = false;
                document.addEventListener('keydown', onEsc);
                var x = popup.querySelector('[data-popup-close]');
                if (x) { x.focus(); }
            }, Math.max(0, parseInt(popup.getAttribute('data-popup-delay') || '3', 10)) * 1000);
            popup.addEventListener('click', function (ev) {
                if (ev.target === popup || ev.target.closest('[data-popup-close]')) { closePopup(); }
                else if (ev.target.closest('[data-popup-close-go]')) { try { localStorage.setItem(pKey, String(Date.now())); } catch (e) { /* ignore */ } }
            });
        }
    }

    /* ---------------- Cart ---------------- */
    var cartUrl = body.dataset.cartUrl;
    function setCartCount(n) {
        document.querySelectorAll('[data-cart-count]').forEach(function (c) { c.textContent = digits(n); c.hidden = !n; });
    }
    function addToCart(fd, btn) {
        if (btn) btn.disabled = true;
        return SB.fetch(cartUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                toast(res.d.message || res.d.error);
                if (res.ok) setCartCount(res.d.count);
            })
            .catch(function () { toast(isBn ? 'নেটওয়ার্ক সমস্যা, আবার চেষ্টা করুন' : 'Network problem, please try again'); })
            .then(function () { if (btn) btn.disabled = false; });
    }
    // "Add to cart" on product cards
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-add-cart]');
        if (!btn || !cartUrl) return;
        e.preventDefault();
        var fd = new FormData();
        fd.append('product_id', btn.dataset.addCart);
        fd.append('qty', '1');
        addToCart(fd, btn);
    });
    // Product page form: AJAX for "Add to cart", normal submit for "Buy now"
    document.querySelectorAll('[data-add-form]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var submitter = e.submitter || document.activeElement;
            if (submitter && submitter.name === 'buy_now') return; // full page: goes to checkout
            e.preventDefault();
            addToCart(new FormData(f), submitter && submitter.tagName === 'BUTTON' ? submitter : null);
        });
    });
    // Cart page: quantity change saves the line
    document.querySelectorAll('[data-cart-qty]').forEach(function (f) {
        var t = null;
        f.addEventListener('change', function () {
            clearTimeout(t);
            t = setTimeout(function () { f.submit(); }, 450);
        });
    });

    /* ---------------- Checkout ---------------- */
    var co = document.getElementById('checkoutForm');
    if (co) {
        var newBox = co.querySelector('[data-new-address]');
        var quoteUrl = co.dataset.quoteUrl;
        var qTimer = null;
        function checked(name) { var r = co.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
        function quote() {
            clearTimeout(qTimer);
            qTimer = setTimeout(function () {
                var addr = checked('address_id') || 'new';
                var params = new URLSearchParams({ payment_method: checked('payment_method'), delivery_option: checked('delivery_option') || 'standard' });
                if (addr === 'new') params.set('district_id', (co.querySelector('[name="district_id"]') || {}).value || '');
                else params.set('address_id', addr);
                fetch(quoteUrl + '?' + params.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (!d) return;
                        ['subtotal', 'shipping', 'grand_total', 'eta'].forEach(function (k) {
                            var el = co.querySelector('[data-sum="' + k + '"]'); if (el) el.textContent = d[k] || '';
                        });
                        var hint = co.querySelector('[data-sum="free_hint"]'); if (hint) hint.textContent = d.free_hint || '';
                        var codRow = co.querySelector('[data-row="cod"]');
                        if (codRow) { codRow.hidden = !d.cod_charge; codRow.querySelector('[data-sum="cod_charge"]').textContent = d.cod_charge || ''; }
                        var disRow = co.querySelector('[data-row="discount"]');
                        if (disRow) { disRow.hidden = !d.discount; disRow.querySelector('[data-sum="discount"]').textContent = d.discount || ''; }
                        co.querySelectorAll('[data-pay-amount]').forEach(function (el) { el.textContent = d.grand_total; });
                        // per-seller shipments + which delivery options are possible here
                        var list = co.querySelector('[data-ship-groups]');
                        if (list) {
                            list.textContent = '';
                            (d.groups || []).forEach(function (g) {
                                var li = document.createElement('li');
                                var a = document.createElement('span'); a.textContent = g.seller + ' ';
                                var z = document.createElement('small'); z.className = 'text-muted'; z.textContent = g.zone; a.appendChild(z);
                                var b = document.createElement('span'); b.textContent = g.fee;
                                li.appendChild(a); li.appendChild(b); list.appendChild(li);
                            });
                            list.hidden = !(d.ok && d.groups && d.groups.length > 1);
                        }
                        var hint = co.querySelector('[data-sum="free_hint"]');
                        if (hint) hint.textContent = (d.groups || []).map(function (g) { return g.hint; }).filter(Boolean).join(' · ');
                        Object.keys(d.available || {}).forEach(function (op) {
                            var lab = co.querySelector('[data-option="' + op + '"]');
                            if (!lab) return;
                            var inp = lab.querySelector('input');
                            lab.classList.toggle('is-off', !d.available[op]);
                            inp.disabled = !d.available[op] && !inp.checked;
                        });
                    })
                    .catch(function () {});
            }, 200);
        }
        function syncAddress() {
            var addr = checked('address_id');
            if (newBox && addr) newBox.hidden = addr !== 'new';
            var pickup = checked('delivery_option') === 'pickup';
            var sec = co.querySelector('[data-address-section]'); if (sec) sec.hidden = pickup;
            var pn = co.querySelector('[data-pickup-note]'); if (pn) pn.hidden = !pickup;
        }
        function syncPayment() {
            var m = checked('payment_method');
            co.querySelectorAll('[data-manual]').forEach(function (box) {
                var on = box.dataset.manual === m;
                box.hidden = !on;
                box.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
            });
        }
        var rxSel = co.querySelector('#rxExisting'), rxUp = co.querySelector('[data-rx-upload]');
        function syncRx() { if (rxSel && rxUp) rxUp.hidden = rxSel.value !== ''; }
        co.addEventListener('change', function (e) {
            var n = e.target.name;
            if (n === 'address_id' || n === 'delivery_option') { syncAddress(); quote(); }
            else if (n === 'payment_method') { syncPayment(); quote(); }
            else if (n === 'district_id') { quote(); }
            else if (e.target === rxSel) { syncRx(); }
        });
        syncAddress(); syncPayment(); syncRx();
        var trx = co.querySelectorAll('input[name="trx_id"]');
        trx.forEach(function (i) { i.addEventListener('input', function () { i.value = i.value.toUpperCase().replace(/\s+/g, ''); }); });
    }

    /* ---------------- Misc ---------------- */
    // Auto-submit selects (sort)
    document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });
    // Show / hide password
    document.querySelectorAll('[data-toggle-password]').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = document.querySelector(b.dataset.togglePassword);
            if (!f) return;
            var show = f.type === 'password';
            f.type = show ? 'text' : 'password';
            b.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        });
    });
})();
