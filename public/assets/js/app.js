/* SasthoBazar - base JS */
(function () {
    'use strict';

    // CSRF token for AJAX: use SB.fetch() instead of fetch() for POST requests
    var meta = document.querySelector('meta[name="csrf-token"]');
    window.SB = {
        csrf: meta ? meta.content : '',
        fetch: function (url, opts) {
            opts = opts || {};
            opts.headers = Object.assign({ 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
            opts.credentials = 'same-origin';
            return fetch(url, opts);
        }
    };

    // Confirm before submitting forms marked with data-confirm="message".
    // Uses a Bootstrap modal when available (data-confirm-danger="1" = red button), else window.confirm.
    var confirmModal = null;
    function getConfirmModal() {
        if (confirmModal || !window.bootstrap) return confirmModal;
        var lang = document.documentElement.lang === 'bn';
        var el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">' +
            '<div class="modal-body pt-4 text-center"><i class="bi bi-exclamation-triangle text-danger fs-1 d-block mb-2" data-icon></i>' +
            '<p class="mb-0" data-msg></p></div>' +
            '<div class="modal-footer justify-content-center border-0 pt-0">' +
            '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' + (lang ? 'বাতিল' : 'Cancel') + '</button>' +
            '<button type="button" class="btn btn-danger" data-ok>' + (lang ? 'হ্যাঁ, নিশ্চিত' : 'Yes, continue') + '</button>' +
            '</div></div></div>';
        document.body.appendChild(el);
        confirmModal = { el: el, modal: new bootstrap.Modal(el), form: null };
        el.querySelector('[data-ok]').addEventListener('click', function () {
            var f = confirmModal.form;
            confirmModal.modal.hide();
            if (!f) return;
            f.dataset.confirmed = '1';
            if (f.requestSubmit) f.requestSubmit(); else f.submit();
        });
        el.addEventListener('shown.bs.modal', function () { el.querySelector('[data-ok]').focus(); });
        return confirmModal;
    }
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute && form.getAttribute('data-confirm');
        if (!msg) return;
        if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
        e.preventDefault();
        var m = getConfirmModal();
        if (!m) {
            if (window.confirm(msg)) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
            return;
        }
        var danger = form.dataset.confirmDanger === '1';
        m.el.querySelector('[data-msg]').textContent = msg;
        m.el.querySelector('[data-ok]').className = 'btn ' + (danger ? 'btn-danger' : 'btn-brand');
        m.el.querySelector('[data-icon]').className = 'bi fs-1 d-block mb-2 ' + (danger ? 'bi-exclamation-triangle text-danger' : 'bi-question-circle text-primary');
        m.form = form;
        m.modal.show();
    });

    // Prevent double submits (slow uploads): disable submit buttons once the form really submits
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || form.method.toLowerCase() !== 'post') return;
        // after the browser has built the form data (so the clicked button's name/value is still sent)
        setTimeout(function () {
            document.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (b) {
                if (b.form === form && !b.disabled) { b.disabled = true; b.dataset.autoDisabled = '1'; }
            });
        }, 0);
    });
    // Back/forward cache restores the page with disabled buttons - re-enable the ones we disabled
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[data-auto-disabled]').forEach(function (b) {
            b.disabled = false;
            delete b.dataset.autoDisabled;
        });
    });

    // Cascading Division > District > Upazila picker
    document.querySelectorAll('#locationPicker, [data-location-picker]').forEach(function (box) {
        var api = box.dataset.api;
        var levels = ['division', 'district', 'upazila'];
        box.addEventListener('change', function (e) {
            var level = e.target.dataset.level;
            var idx = levels.indexOf(level);
            if (idx < 0 || idx === levels.length - 1) return;
            for (var i = idx + 1; i < levels.length; i++) {
                var s = box.querySelector('[data-level="' + levels[i] + '"]');
                s.length = 1; s.disabled = true;
            }
            if (!e.target.value) return;
            var next = box.querySelector('[data-level="' + levels[idx + 1] + '"]');
            fetch(api + '/' + encodeURIComponent(e.target.value), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    rows.forEach(function (row) { next.add(new Option(row.name, row.id)); });
                    next.disabled = false;
                });
        });
    });

    // Role permission matrix: column / row / all toggles + live counter
    var form = document.getElementById('roleForm');
    if (form) {
        var boxes = function (scope) {
            return Array.prototype.slice.call((scope || form).querySelectorAll('input.perm-cb:not(:disabled)'));
        };
        var counter = document.getElementById('permCount');
        var refresh = function () {
            if (counter) counter.textContent = form.querySelectorAll('input.perm-cb:checked').length;
            form.querySelectorAll('[data-row-toggle]').forEach(function (t) {
                var row = form.querySelector('tr[data-row="' + t.dataset.rowToggle + '"]');
                var b = boxes(row);
                t.checked = b.length > 0 && b.every(function (x) { return x.checked; });
            });
            form.querySelectorAll('thead input[data-col]').forEach(function (t) {
                var b = boxes().filter(function (x) { return x.dataset.col === t.dataset.col; });
                t.checked = b.length > 0 && b.every(function (x) { return x.checked; });
            });
        };
        form.addEventListener('change', function (e) {
            var t = e.target;
            if (t.matches('thead input[data-col]')) {
                boxes().forEach(function (x) { if (x.dataset.col === t.dataset.col) x.checked = t.checked; });
            } else if (t.dataset.rowToggle) {
                boxes(form.querySelector('tr[data-row="' + t.dataset.rowToggle + '"]')).forEach(function (x) { x.checked = t.checked; });
            } else if (t.classList.contains('perm-cb') && t.checked && t.dataset.col !== 'view') {
                // granting any action also needs "view" of that module
                var view = t.closest('tr').querySelector('input.perm-cb[data-col="view"]:not(:disabled)');
                if (view) view.checked = true;
            }
            refresh();
        });
        form.querySelectorAll('[data-matrix]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var on = btn.dataset.matrix === 'all';
                boxes().forEach(function (x) { x.checked = on; });
                refresh();
            });
        });
        refresh();
    }

    /* ================= Catalog forms ================= */

    var isBn = document.documentElement.lang === 'bn';
    var t = function (bn, en) { return isBn ? bn : en; };

    // Slug: fill from the name until the user edits the slug field (Bangla letters kept)
    function slugify(s) {
        return s.toLowerCase().trim()
            .replace(/[^\p{L}\p{N}ঀ-৿]+/gu, '-')
            .replace(/^-+|-+$/g, '');
    }
    document.querySelectorAll('[data-slug-source]').forEach(function (src) {
        var target = document.querySelector(src.dataset.slugSource);
        if (!target) return;
        var touched = target.value !== '';
        target.addEventListener('input', function () { touched = target.value !== ''; });
        target.addEventListener('blur', function () { if (target.value) target.value = slugify(target.value); });
        src.addEventListener('input', function () { if (!touched) target.placeholder = slugify(src.value) || target.placeholder; });
    });

    // Character counters: data-counter="70" shows "12 / 70" (recommended length)
    document.querySelectorAll('[data-counter]').forEach(function (el) {
        var rec = parseInt(el.dataset.counter, 10);
        var out = document.createElement('div');
        out.className = 'form-text text-end';
        el.insertAdjacentElement('afterend', out);
        var upd = function () {
            var n = el.value.length;
            out.textContent = n + ' / ' + rec;
            out.classList.toggle('text-warning', n > rec);
        };
        el.addEventListener('input', upd);
        upd();
    });

    // Icon preview (bootstrap icon class)
    var iconInput = document.getElementById('icon');
    var iconPreview = document.querySelector('[data-icon-preview]');
    if (iconInput && iconPreview) {
        iconInput.addEventListener('input', function () {
            iconPreview.className = 'bi ' + (/^bi-[a-z0-9-]+$/.test(iconInput.value) ? iconInput.value : 'bi-folder');
        });
    }

    // Image inputs: client-side type/size/count check + thumbnail preview (server re-validates everything)
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    document.querySelectorAll('input[type=file][data-image-input]').forEach(function (input) {
        var box = input.parentElement.querySelector('.image-preview');
        var maxMb = parseFloat(input.dataset.maxMb || '5');
        var maxCount = parseInt(input.dataset.maxCount || '1', 10);
        var postMax = parseFloat(input.dataset.postMaxMb || '0');
        var radioName = input.dataset.primaryRadio;
        var feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        input.insertAdjacentElement('afterend', feedback);

        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files || []);
            var errors = [];
            var total = 0;
            if (files.length > maxCount) {
                errors.push(t('সর্বোচ্চ ' + maxCount + 'টি ছবি দেওয়া যাবে।', 'You can add at most ' + maxCount + ' image(s).'));
            }
            files.forEach(function (f) {
                total += f.size;
                if (IMAGE_TYPES.indexOf(f.type) < 0) errors.push(f.name + ': ' + t('শুধু JPG, PNG, WEBP বা GIF।', 'only JPG, PNG, WEBP or GIF.'));
                else if (f.size > maxMb * 1048576) errors.push(f.name + ': ' + t('সর্বোচ্চ ' + maxMb + ' MB।', 'max ' + maxMb + ' MB.'));
            });
            if (!errors.length && postMax && total > postMax * 1048576 * 0.95) {
                errors.push(t('সব ছবি মিলিয়ে ' + postMax + ' MB-এর বেশি — কম ছবি দিন।', 'All images together exceed ' + postMax + ' MB — upload fewer at a time.'));
            }
            if (box) box.innerHTML = '';
            if (errors.length) {
                input.value = '';
                input.classList.add('is-invalid');
                feedback.textContent = errors.join(' ');
                return;
            }
            input.classList.remove('is-invalid');
            feedback.textContent = '';
            if (!box) return;
            files.forEach(function (f, i) {
                var wrap = document.createElement('div');
                wrap.className = 'border rounded p-1 text-center small preview-item';
                var img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(f);
                img.onload = function () { URL.revokeObjectURL(img.src); };
                wrap.appendChild(img);
                if (radioName) {
                    var lbl = document.createElement('label');
                    lbl.className = 'd-block mt-1';
                    var r = document.createElement('input');
                    r.type = 'radio'; r.name = radioName; r.value = 'n' + i; r.className = 'form-check-input me-1';
                    lbl.appendChild(r);
                    lbl.appendChild(document.createTextNode(t('প্রধান', 'Primary')));
                    wrap.appendChild(lbl);
                }
                box.appendChild(wrap);
            });
        });
    });

    // Existing product images: dim the card when marked for deletion
    document.querySelectorAll('[data-image-delete]').forEach(function (cb) {
        cb.addEventListener('change', function () { cb.closest('.image-card').classList.toggle('opacity-50', cb.checked); });
    });

    // CSV file input check
    document.querySelectorAll('input[type=file][data-csv-input]').forEach(function (input) {
        var fb = input.parentElement.querySelector('[data-csv-error]');
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            var msg = '';
            if (f && !/\.(csv|txt)$/i.test(f.name)) msg = t('শুধু .csv ফাইল দিন।', 'Please choose a .csv file.');
            else if (f && f.size > parseFloat(input.dataset.maxMb) * 1048576) msg = t('ফাইল খুব বড়।', 'File is too large.');
            input.classList.toggle('is-invalid', !!msg);
            if (fb) fb.textContent = msg;
            if (msg) input.value = '';
        });
    });

    var productForm = document.getElementById('productForm');
    if (productForm) {
        // Variants repeater
        var body = productForm.querySelector('[data-variant-body]');
        var tpl = document.getElementById('variantTemplate');
        var table = productForm.querySelector('[data-variant-table]');
        var counter = productForm.querySelector('[data-variant-count]');
        var addBtn = productForm.querySelector('[data-variant-add]');
        var next = body ? body.querySelectorAll('[data-variant-row]').length : 0;
        var refreshVariants = function () {
            var n = body.querySelectorAll('[data-variant-row]').length;
            if (counter) counter.textContent = n;
            table.classList.toggle('d-none', n === 0);
            addBtn.disabled = n >= parseInt(addBtn.dataset.max, 10);
            if (n && !body.querySelector('input[name="variants_default"]:checked')) {
                body.querySelector('input[name="variants_default"]').checked = true;
            }
        };
        if (addBtn && tpl && body) {
            addBtn.addEventListener('click', function () {
                body.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, 'n' + (next++)));
                refreshVariants();
                var rows = body.querySelectorAll('[data-variant-row]');
                rows[rows.length - 1].querySelector('input[type=text], input:not([type])').focus();
            });
            body.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-variant-remove]');
                if (!btn) return;
                var row = btn.closest('[data-variant-row]');
                var hasId = row.querySelector('input[name$="[id]"]').value !== '';
                if (hasId && !window.confirm(t('এই ভ্যারিয়েন্ট মুছে ফেলবেন? সেভ করলে কার্যকর হবে।', 'Remove this variant? It is deleted when you save.'))) return;
                row.remove();
                refreshVariants();
            });
        }

        // Category requires prescription -> suggest Rx flag
        var cat = productForm.querySelector('[data-rx-source]');
        var rx = document.getElementById('requires_prescription');
        var rxHint = productForm.querySelector('[data-rx-hint]');
        var syncRx = function (auto) {
            var opt = cat.options[cat.selectedIndex];
            var needs = opt && opt.dataset.rx === '1';
            if (needs && auto) rx.checked = true;
            rxHint.classList.toggle('d-none', !(needs && !rx.checked));
        };
        if (cat && rx && rxHint) {
            cat.addEventListener('change', function () { syncRx(true); });
            rx.addEventListener('change', function () { syncRx(false); });
            syncRx(false);
        }

        // Status "rejected" -> reason field
        var status = productForm.querySelector('[data-status-select]');
        var rej = productForm.querySelector('[data-rejection]');
        if (status && rej) {
            status.addEventListener('change', function () { rej.classList.toggle('d-none', status.value !== 'rejected'); });
        }

        // Generic name required only for prescription medicine type
        var type = productForm.querySelector('[data-product-type]');
        var star = productForm.querySelector('[data-medicine-only]');
        if (type && star) {
            var syncType = function () { star.classList.toggle('d-none', type.value !== 'medicine'); };
            type.addEventListener('change', syncType);
            syncType();
        }

        // Pricing hints: discount % and margin
        var mrp = productForm.querySelector('[data-price="mrp"]');
        var sale = productForm.querySelector('[data-price="sale"]');
        var cost = productForm.querySelector('[data-price="cost"]');
        var disc = productForm.querySelector('[data-price-discount]');
        var marg = productForm.querySelector('[data-price-margin]');
        var num = function (el) { var v = parseFloat((el && el.value || '').replace(/,/g, '')); return isNaN(v) ? null : v; };
        var syncPrice = function () {
            var m = num(mrp), s = num(sale), c = num(cost);
            var sell = s !== null ? s : m;
            disc.textContent = '';
            disc.className = 'form-text';
            if (m && s !== null) {
                if (s > m) { disc.textContent = t('বিক্রয় মূল্য MRP-এর বেশি!', 'Sale price is higher than MRP!'); disc.className = 'form-text text-danger'; }
                else disc.textContent = t('ছাড়: ', 'Discount: ') + Math.round((1 - s / m) * 1000) / 10 + '%';
            }
            marg.textContent = '';
            marg.className = 'form-text';
            if (sell && c !== null) {
                var pct = Math.round(((sell - c) / sell) * 1000) / 10;
                marg.textContent = t('মার্জিন: ', 'Margin: ') + '৳' + (sell - c).toFixed(2) + ' (' + pct + '%)';
                if (pct < 0) marg.className = 'form-text text-danger';
            }
        };
        if (mrp && sale && cost && disc && marg) {
            [mrp, sale, cost].forEach(function (el) { el.addEventListener('input', syncPrice); });
            syncPrice();
        }

        // Warn before leaving with unsaved changes
        var dirty = false;
        productForm.addEventListener('input', function () { dirty = true; });
        productForm.addEventListener('change', function () { dirty = true; });
        productForm.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });
    }

    /* ================= Inventory / purchase orders ================= */

    var money = function (n) {
        return '৳' + (Math.round(n * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // Product picker: type 2+ letters -> live search -> pick -> hidden id + variant select.
    // Fires "product:selected" (detail = product) on the picker; bubbles.
    function initPicker(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        var input = box.querySelector('[data-picker-input]');
        var hid = box.querySelector('[data-picker-id]');
        var list = box.querySelector('[data-picker-results]');
        var variant = box.querySelector('[data-picker-variant]');
        var meta = box.querySelector('[data-picker-meta]');
        var timer = null, results = [], active = -1, ctrl = null;

        var close = function () { list.classList.add('d-none'); active = -1; };
        var render = function () {
            list.innerHTML = '';
            if (!results.length) {
                var none = document.createElement('div');
                none.className = 'list-group-item small text-muted';
                none.textContent = t('কোনো পণ্য পাওয়া যায়নি', 'No products found');
                list.appendChild(none);
            }
            results.forEach(function (p, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'list-group-item list-group-item-action small py-1' + (i === active ? ' active' : '');
                var title = document.createElement('div');
                title.className = 'fw-semibold';
                title.textContent = p.label;
                var sub = document.createElement('div');
                sub.className = i === active ? '' : 'text-muted';
                sub.textContent = (p.sku ? p.sku + ' · ' : '') + t('স্টক ', 'Stock ') + p.stock_qty +
                    (p.variants.length ? ' · ' + p.variants.length + t(' ভ্যারিয়েন্ট', ' variants') : '') +
                    (p.vendor_name ? ' · ' + p.vendor_name : '');
                b.appendChild(title);
                b.appendChild(sub);
                b.addEventListener('mousedown', function (e) { e.preventDefault(); choose(p); });
                list.appendChild(b);
            });
            list.classList.remove('d-none');
        };
        var choose = function (p) {
            hid.value = p.id;
            input.value = p.label;
            input.classList.remove('is-invalid');
            meta.textContent = 'SKU: ' + (p.sku || '—') + ' · ' + t('স্টক', 'Stock') + ': ' + p.stock_qty;
            variant.length = 1;
            p.variants.forEach(function (v) { variant.add(new Option(v.name_en + (v.sku ? ' · ' + v.sku : ''), v.id)); });
            variant.classList.toggle('d-none', !p.variants.length);
            if (p.variants.length === 1) variant.value = p.variants[0].id;
            box._product = p;
            close();
            box.dispatchEvent(new CustomEvent('product:selected', { bubbles: true, detail: p }));
        };
        input.addEventListener('input', function () {
            hid.value = '';
            variant.length = 1;
            variant.classList.add('d-none');
            meta.textContent = '';
            box._product = null;
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(function () {
                if (ctrl) ctrl.abort();
                ctrl = window.AbortController ? new AbortController() : null;
                fetch(box.dataset.lookup + '?q=' + encodeURIComponent(q), {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined
                }).then(function (r) { return r.json(); }).then(function (rows) {
                    results = Array.isArray(rows) ? rows : [];
                    active = results.length ? 0 : -1;
                    render();
                }).catch(function () { /* aborted or offline */ });
            }, 250);
        });
        input.addEventListener('keydown', function (e) {
            if (list.classList.contains('d-none') || !results.length) return;
            if (e.key === 'ArrowDown') { active = Math.min(results.length - 1, active + 1); render(); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
            else if (e.key === 'Enter') { if (active >= 0) choose(results[active]); e.preventDefault(); }
            else if (e.key === 'Escape') { close(); }
        });
        input.addEventListener('blur', function () { setTimeout(close, 150); });
        variant.addEventListener('change', function () {
            box.dispatchEvent(new CustomEvent('variant:selected', { bubbles: true, detail: { product: box._product, variantId: variant.value } }));
        });
    }
    document.querySelectorAll('[data-product-picker]').forEach(initPicker);

    // Default unit cost from the product/variant purchase price
    var costFor = function (p, variantId) {
        if (!p) return null;
        var v = (p.variants || []).filter(function (x) { return String(x.id) === String(variantId); })[0];
        return (v && v.cost_price) || p.cost_price || null;
    };

    // Stock-in: suggest the purchase price
    document.querySelectorAll('[data-stock-form]').forEach(function (form) {
        var cost = form.querySelector('[data-cost-input]');
        form.addEventListener('product:selected', function (e) { if (cost) cost.placeholder = e.detail.cost_price || '0.00'; });
    });

    // Stock-out: load the product's batches into the batch select
    document.querySelectorAll('form[data-stock-out]').forEach(function (form) {
        var sel = form.querySelector('[data-batch-select]');
        var load = function () {
            var pid = form.querySelector('[data-picker-id]').value;
            var vid = form.querySelector('[data-picker-variant]').value;
            sel.length = 1;
            if (!pid) return;
            fetch(form.dataset.batchesUrl + '?product_id=' + pid + '&variant_id=' + encodeURIComponent(vid), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    rows.forEach(function (b) {
                        var label = b.batch_no + ' · ' + t('মজুত ', 'available ') + b.quantity_available +
                            (b.expiry_date ? ' · ' + t('মেয়াদ ', 'exp ') + b.expiry_date : '') + (Number(b.is_expired) ? ' · ' + t('মেয়াদোত্তীর্ণ', 'EXPIRED') : '');
                        sel.add(new Option(label, b.id, false, String(b.id) === sel.dataset.selected));
                    });
                });
        };
        form.addEventListener('product:selected', load);
        form.addEventListener('variant:selected', load);
        var syncType = function () {
            var damage = form.querySelector('input[name="type"]:checked').value === 'damage';
            sel.options[0].textContent = damage ? t('— ব্যাচ বাছাই করুন —', '— choose a batch —') : t('স্বয়ংক্রিয় (আগে মেয়াদ শেষ হবে এমন ব্যাচ আগে)', 'Automatic (earliest expiry first)');
        };
        form.querySelectorAll('input[name="type"]').forEach(function (r) { r.addEventListener('change', syncType); });
        syncType();
    });

    // Batch write-off: recall always takes everything left
    document.querySelectorAll('[data-writeoff-type]').forEach(function (sel) {
        var qty = sel.form.querySelector('[data-writeoff-qty]');
        var sync = function () { qty.disabled = sel.value === 'recall'; if (qty.disabled) qty.value = ''; };
        sel.addEventListener('change', sync);
        sync();
    });

    // Purchase order form: line rows, live totals
    var poForm = document.getElementById('poForm');
    if (poForm) {
        var poBody = poForm.querySelector('[data-po-body]');
        var poTpl = document.getElementById('poRowTemplate');
        var poNext = poBody.querySelectorAll('[data-po-row]').length;
        var num2 = function (el) { var v = parseFloat((el && el.value || '').replace(/,/g, '')); return isNaN(v) ? 0 : v; };
        var recalc = function () {
            var sub = 0;
            poBody.querySelectorAll('[data-po-row]').forEach(function (row) {
                var line = num2(row.querySelector('[data-po-qty]')) * num2(row.querySelector('[data-po-cost]'));
                row.querySelector('[data-po-line-total]').textContent = line ? money(line) : '—';
                sub += line;
            });
            var disc = num2(poForm.querySelector('[data-po-discount]'));
            var tax = num2(poForm.querySelector('[data-po-tax]'));
            poForm.querySelector('[data-po-subtotal]').textContent = money(sub);
            poForm.querySelector('[data-po-total]').textContent = money(Math.max(0, sub - disc + tax));
        };
        poForm.addEventListener('input', recalc);
        poForm.querySelector('[data-po-add]').addEventListener('click', function () {
            poBody.insertAdjacentHTML('beforeend', poTpl.innerHTML.replace(/__i__/g, 'n' + (poNext++)));
            var row = poBody.lastElementChild;
            initPicker(row.querySelector('[data-product-picker]'));
            row.querySelector('[data-picker-input]').focus();
        });
        poBody.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-po-remove]');
            if (!btn) return;
            var rows = poBody.querySelectorAll('[data-po-row]');
            var row = btn.closest('[data-po-row]');
            if (rows.length === 1) { // keep one (empty) row
                row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
                row.querySelector('[data-picker-variant]').classList.add('d-none');
            } else {
                row.remove();
            }
            recalc();
        });
        var fillCost = function (e) {
            var row = e.target.closest('[data-po-row]');
            if (!row) return;
            var p = e.detail.product || e.detail;
            var c = costFor(p, row.querySelector('[data-picker-variant]').value);
            var cost = row.querySelector('[data-po-cost]');
            if (c && !cost.value) cost.value = c;
            var qty = row.querySelector('[data-po-qty]');
            if (!qty.value) qty.focus();
            recalc();
        };
        poForm.addEventListener('product:selected', fillCost);
        poForm.addEventListener('variant:selected', fillCost);
        recalc();
    }

    // GRN: "receive everything remaining"
    var grnForm = document.getElementById('grnForm');
    if (grnForm) {
        var fill = grnForm.querySelector('[data-grn-fill]');
        if (fill) fill.addEventListener('click', function () {
            grnForm.querySelectorAll('[data-grn-row]').forEach(function (row) {
                var q = row.querySelector('[data-grn-qty]');
                if (q) q.value = row.dataset.remaining;
            });
        });
    }

    /* ================= Orders (Step 6) ================= */
    // Bulk selection on the order list
    var bulk = document.getElementById('bulkForm');
    if (bulk) {
        var all = bulk.querySelector('[data-check-all]');
        var rows = function () { return Array.prototype.slice.call(bulk.querySelectorAll('[data-row-check]')); };
        var refreshBulk = function () {
            var n = rows().filter(function (c) { return c.checked; }).length;
            var cnt = bulk.querySelector('[data-bulk-count]'); if (cnt) cnt.textContent = n;
            var btn = bulk.querySelector('[data-bulk-submit]'); if (btn) btn.disabled = n === 0;
            if (all) { all.checked = n > 0 && n === rows().length; all.indeterminate = n > 0 && n < rows().length; }
        };
        bulk.addEventListener('change', function (e) {
            if (e.target === all) rows().forEach(function (c) { c.checked = all.checked; });
            refreshBulk();
        });
        refreshBulk();
    }
    // Simple confirm for single buttons (e.g. reject prescription)
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-confirm-click]');
        if (b && !window.confirm(b.getAttribute('data-confirm-click'))) e.preventDefault();
    });
    // Coupon form: show fields for the chosen type / scope
    var ctype = document.querySelector('[data-coupon-type]'), capp = document.querySelector('[data-coupon-applies]');
    if (ctype && capp) {
        var syncCoupon = function () {
            document.querySelectorAll('[data-show-type]').forEach(function (el) {
                el.hidden = el.getAttribute('data-show-type').split(' ').indexOf(ctype.value) < 0;
            });
            document.querySelectorAll('[data-show-applies]').forEach(function (el) {
                var on = el.getAttribute('data-show-applies') === capp.value;
                el.hidden = !on;
                el.querySelectorAll('select, input').forEach(function (i) { i.disabled = !on; });
            });
        };
        ctype.addEventListener('change', syncCoupon);
        capp.addEventListener('change', syncCoupon);
        syncCoupon();
    }
    // Generic: <div data-show-when="field:value1 value2"> is shown while the form field has one of the values
    document.querySelectorAll('form').forEach(function (form) {
        var deps = form.querySelectorAll('[data-show-when]');
        if (!deps.length) { return; }
        var sync = function () {
            deps.forEach(function (el) {
                var rule = el.getAttribute('data-show-when').split(':');
                var f = form.elements[rule[0]];
                var v = !f ? '' : (f.type === 'checkbox' ? (f.checked ? '1' : '0') : f.value);
                el.hidden = rule[1].split(' ').indexOf(v) < 0;
            });
        };
        form.addEventListener('change', sync);
        sync();
    });
    // Countdown: <span data-countdown="ISO date"> with <b data-cd="d|h|m|s">; [data-cd-days] is hidden under one day.
    // The page reloads when it reaches zero (sale over -> regular prices) unless data-no-reload is set.
    var cds = document.querySelectorAll('[data-countdown]');
    if (cds.length) {
        var isBnCd = (document.documentElement.lang || '').indexOf('bn') === 0, BNCD = '০১২৩৪৫৬৭৮৯';
        var dg = function (v) { v = String(v); return isBnCd ? v.replace(/[0-9]/g, function (d) { return BNCD[d]; }) : v; };
        var pad2 = function (n) { return dg(n < 10 ? '0' + n : String(n)); };
        var set = function (c, k, v) { var el = c.querySelector('[data-cd="' + k + '"]'); if (el) { el.textContent = v; } return el; };
        var tickCd = function () {
            cds.forEach(function (c) {
                var left = Math.max(0, Math.floor((new Date(c.getAttribute('data-countdown')).getTime() - Date.now()) / 1000));
                var days = Math.floor(left / 86400), hasDays = !!c.querySelector('[data-cd="d"]');
                set(c, 'd', dg(days));
                var wrap = c.querySelector('[data-cd-days]');
                if (wrap) { wrap.hidden = days < 1; }
                set(c, 'h', pad2(hasDays && days >= 1 ? Math.floor(left % 86400 / 3600) : Math.floor(left / 3600)));
                set(c, 'm', pad2(Math.floor(left % 3600 / 60)));
                set(c, 's', pad2(left % 60));
                if (left === 0 && !c.getAttribute('data-done') && !c.hasAttribute('data-no-reload')) {
                    c.setAttribute('data-done', '1');
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            });
        };
        tickCd();
        setInterval(tickCd, 1000);
    }
    // CMS editor: wrap the selection in tags, insert links / images, preview (scripts and event handlers stripped)
    document.querySelectorAll('[data-editor]').forEach(function (ed) {
        var ta = ed.querySelector('textarea'), out = ed.querySelector('[data-ed-output]');
        ed.querySelectorAll('[data-ed-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                var open = b.getAttribute('data-ed-open'), close = b.getAttribute('data-ed-close');
                var s0 = ta.selectionStart, s1 = ta.selectionEnd, sel = ta.value.substring(s0, s1), ins;
                if (open === 'link') {
                    var href = window.prompt('URL (https://... or /page/...)', 'https://');
                    if (!href) { return; }
                    ins = '<a href="' + href.replace(/"/g, '&quot;') + '">' + (sel || href) + '</a>';
                } else if (open === 'image') {
                    var src = window.prompt('Image URL', 'https://');
                    if (!src) { return; }
                    ins = '<img src="' + src.replace(/"/g, '&quot;') + '" alt="' + sel.replace(/"/g, '') + '">';
                } else {
                    ins = open + sel + close;
                }
                ta.setRangeText(ins, s0, s1, 'end');
                ta.focus();
            });
        });
        var pv = ed.querySelector('[data-ed-preview]');
        if (pv && out) {
            pv.addEventListener('click', function () {
                if (!out.hidden) { out.hidden = true; ta.hidden = false; return; }
                var doc = new DOMParser().parseFromString('<div>' + ta.value + '</div>', 'text/html');
                doc.querySelectorAll('script,style,iframe,object,embed,form').forEach(function (n) { n.remove(); });
                doc.querySelectorAll('*').forEach(function (n) {
                    Array.prototype.slice.call(n.attributes).forEach(function (a) {
                        if (/^on/i.test(a.name) || /^\s*javascript:/i.test(a.value)) { n.removeAttribute(a.name); }
                    });
                });
                out.innerHTML = doc.body.firstChild.innerHTML;
                out.hidden = false;
                ta.hidden = true;
            });
        }
    });
    // Admin notification bell: poll every 30 s (paused while the tab is hidden), update the badge and list,
    // show a toast for each new notification.
    var bell = document.querySelector('[data-notif-poll]');
    if (bell && window.fetch) {
        var lastId = parseInt(bell.getAttribute('data-notif-last') || '0', 10);
        var badge = bell.querySelector('[data-notif-count]'), list = bell.querySelector('[data-notif-list]');
        var readAll = bell.querySelector('[data-notif-readall]'), baseTitle = document.title;
        var isBnN = (document.documentElement.lang || '').indexOf('bn') === 0, BNN = '০১২৩৪৫৬৭৮৯';
        var dgn = function (v) { v = String(v); return isBnN ? v.replace(/[0-9]/g, function (d) { return BNN[d]; }) : v; };
        var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
        var setCount = function (n) {
            badge.textContent = n > 99 ? dgn('99') + '+' : dgn(n);
            badge.hidden = n <= 0;
            if (readAll) { readAll.hidden = n <= 0; }
            document.title = (n > 0 ? '(' + dgn(n) + ') ' : '') + baseTitle;
        };
        var render = function (items) {
            if (!items.length) {
                list.innerHTML = '<div class="text-center text-muted small py-4"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i>' + esc(bell.getAttribute('data-notif-empty')) + '</div>';
                return;
            }
            list.innerHTML = items.map(function (n) {
                return '<a class="dropdown-item small py-2 d-flex gap-2 ' + (n.read ? '' : 'fw-semibold bg-light') + '" href="' + esc(n.url) + '">'
                    + '<i class="bi ' + esc(n.icon) + ' text-brand"></i><span class="min-w-0"><span class="d-block text-truncate">' + esc(n.title) + '</span>'
                    + '<span class="d-block text-muted fw-normal" style="font-size:.75rem">' + esc(n.time) + '</span></span></a>';
            }).join('');
        };
        var toast = function (n) {
            var box = document.getElementById('notifToasts');
            if (!box || !window.bootstrap) { return; }
            var el = document.createElement('div');
            el.className = 'toast';
            el.setAttribute('role', 'status');
            el.innerHTML = '<div class="toast-header"><i class="bi ' + esc(n.icon) + ' me-2 text-brand"></i><strong class="me-auto small">' + esc(n.title) + '</strong>'
                + '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>'
                + (n.text ? '<div class="toast-body small">' + esc(n.text) + '</div>' : '')
                + '<a class="stretched-link" href="' + esc(n.url) + '" aria-label="' + esc(n.title) + '"></a>';
            box.appendChild(el);
            new bootstrap.Toast(el, { delay: 8000 }).show();
            el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        };
        var busy = false;
        var poll = function () {
            if (busy || document.hidden) { return; }
            busy = true;
            fetch(bell.getAttribute('data-notif-poll') + '?since=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
                .then(function (d) {
                    setCount(d.unread || 0);
                    render(d.items || []);
                    (d['new'] || []).forEach(toast);
                    if (d.max_id > lastId) { lastId = d.max_id; }
                })
                .catch(function () { /* offline or logged out: try again next time */ })
                .then(function () { busy = false; });
        };
        setInterval(poll, 30000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
        if (readAll) {
            readAll.addEventListener('click', function (ev) {
                ev.preventDefault();
                var token = document.querySelector('meta[name="csrf-token"]');
                var body = new FormData();
                body.append('_csrf', token ? token.getAttribute('content') : '');
                fetch(bell.getAttribute('data-notif-read'), { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function () { setCount(0); list.querySelectorAll('.fw-semibold').forEach(function (a) { a.classList.remove('fw-semibold', 'bg-light'); }); });
            });
        }
    }
})();

/* ---------------- Vendor sign-up: documents follow the chosen business type ---------------- */
(function () {
    'use strict';
    var form = document.querySelector('[data-vr-form]');
    if (!form) { return; }
    var sync = function () {
        var r = form.querySelector('input[name=vendor_type_id]:checked');
        var req = r ? (r.getAttribute('data-req') || '').split(',') : [];
        var opt = r ? (r.getAttribute('data-opt') || '').split(',') : [];
        var hint = form.querySelector('[data-vr-docs-hint]');
        if (hint) { hint.hidden = !!r; }
        form.querySelectorAll('[data-doc]').forEach(function (box) {
            var key = box.getAttribute('data-doc'), isReq = req.indexOf(key) !== -1, show = isReq || opt.indexOf(key) !== -1;
            box.hidden = !show;
            box.querySelector('[data-doc-req]').hidden = !isReq;
            box.querySelector('[data-doc-opt]').hidden = isReq;
            box.querySelectorAll('input').forEach(function (i) { i.disabled = !show; });
        });
    };
    form.querySelectorAll('input[name=vendor_type_id]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
    // show / hide password (the storefront has its own copy in shop.js)
    form.querySelectorAll('[data-toggle-password]').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = document.querySelector(b.getAttribute('data-toggle-password'));
            if (!f) { return; }
            var show = f.type === 'password';
            f.type = show ? 'text' : 'password';
            b.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        });
    });
})();

/* ---------------- Admin sidebar: collapsible groups (remembered) + quick search ---------------- */
(function () {
    'use strict';
    var nav = document.querySelector('[data-side-nav]');
    if (!nav) { return; }
    var KEY = 'sb_side_collapsed', saved = {};
    try { saved = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { saved = {}; }
    var groups = nav.querySelectorAll('[data-side-group]');
    groups.forEach(function (g) {
        var name = g.getAttribute('data-side-group'), btn = g.querySelector('[data-side-toggle]');
        if (!btn) { return; }
        var collapsed = g.getAttribute('data-active') === '1' ? false : !!saved[name];
        g.classList.toggle('collapsed', collapsed);
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.addEventListener('click', function () {
            var now = !g.classList.contains('collapsed');
            g.classList.toggle('collapsed', now);
            btn.setAttribute('aria-expanded', now ? 'false' : 'true');
            saved[name] = now;
            try { localStorage.setItem(KEY, JSON.stringify(saved)); } catch (e) { /* private mode */ }
        });
    });
    var filter = nav.querySelector('[data-side-filter]');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase();
            groups.forEach(function (g) {
                var any = false;
                g.querySelectorAll('[data-side-item]').forEach(function (a) {
                    var hit = q === '' || a.getAttribute('data-side-item').indexOf(q) !== -1 || a.textContent.toLowerCase().indexOf(q) !== -1;
                    a.style.display = hit ? '' : 'none';
                    if (hit) { any = true; }
                });
                g.style.display = any ? '' : 'none';
                g.classList.toggle('filtering', q !== '');
            });
        });
    }
})();

/* ---------------- Page builder form: repeaters, page -> positions, source-dependent fields ---------------- */
(function () {
    'use strict';
    // repeater rows: add (copy of the last row, emptied) / remove; indexes renumbered so PHP gets a clean list
    document.querySelectorAll('[data-repeater]').forEach(function (rep) {
        var list = rep.querySelector('[data-repeater-list]');
        var renumber = function () {
            list.querySelectorAll('[data-repeater-item]').forEach(function (item, i) {
                item.querySelectorAll('[name]').forEach(function (f) { f.name = f.name.replace(/\]\[\d+\]\[/, '][' + i + ']['); });
            });
        };
        rep.querySelector('[data-repeater-add]').addEventListener('click', function () {
            var items = list.querySelectorAll('[data-repeater-item]');
            if (items.length >= 30) { return; }
            var copy = items[items.length - 1].cloneNode(true);
            copy.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
            copy.querySelectorAll('select').forEach(function (f) { f.selectedIndex = 0; });
            list.appendChild(copy);
            renumber();
            var first = copy.querySelector('input, textarea');
            if (first) { first.focus(); }
        });
        list.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-repeater-remove]');
            if (!btn) { return; }
            var items = list.querySelectorAll('[data-repeater-item]');
            var item = btn.closest('[data-repeater-item]');
            if (items.length > 1) { item.remove(); } else { item.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; }); }
            renumber();
        });
    });
    // positions available for the chosen page (home has extra positions)
    var pageSel = document.querySelector('[data-pb-page]'), posSel = document.querySelector('[data-pb-position]');
    if (pageSel && posSel) {
        var syncPos = function () {
            var allowed = (pageSel.selectedOptions[0].getAttribute('data-positions') || '').split(',');
            Array.prototype.forEach.call(posSel.options, function (o) { o.hidden = allowed.indexOf(o.value) === -1; o.disabled = o.hidden; });
            if (posSel.selectedOptions[0] && posSel.selectedOptions[0].disabled) { posSel.value = 'bottom'; }
        };
        pageSel.addEventListener('change', syncPos);
        syncPos();
    }
    // products section: category / brand / IDs fields only for that source
    var src = document.querySelector('[data-pb-source]');
    if (src) {
        var syncSrc = function () {
            document.querySelectorAll('[data-pb-when]').forEach(function (box) {
                box.hidden = box.getAttribute('data-pb-when') !== 'source:' + src.value;
            });
        };
        src.addEventListener('change', syncSrc);
        syncSrc();
    }
})();

/* ---------------- Settings → Watermark: live preview ---------------- */
(function () {
    'use strict';
    var form = document.querySelector('[data-wm-form]'), img = document.querySelector('[data-wm-preview]');
    if (!form || !img) { return; }
    var timer = null;
    var refresh = function () {
        form.querySelectorAll('[data-wm-out]').forEach(function () {});
        document.querySelectorAll('[data-wm-out]').forEach(function (b) {
            var f = document.getElementById(b.getAttribute('data-wm-out'));
            if (f) { b.textContent = f.value; }
        });
        clearTimeout(timer);
        timer = setTimeout(function () {
            var type = (form.querySelector('input[name=watermark_type]:checked') || {}).value || 'text';
            var q = new URLSearchParams({
                type: type,
                text: form.querySelector('[name=watermark_text]').value,
                position: form.querySelector('[name=watermark_position]').value,
                opacity: form.querySelector('[name=watermark_opacity]').value,
                size: form.querySelector('[name=watermark_size]').value,
                color: form.querySelector('[name=watermark_color]').value,
                _: Date.now()
            });
            img.src = img.getAttribute('data-src') + '?' + q.toString();
        }, 300);
    };
    form.querySelectorAll('[data-wm]').forEach(function (f) { f.addEventListener('input', refresh); f.addEventListener('change', refresh); });
})();
