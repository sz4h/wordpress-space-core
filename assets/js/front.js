/* Space Core – Frontend JS */
(function () {
    'use strict';

    // ── Stock Notifier Form ───────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sc-sn-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var productId = form.dataset.product;
                var nonce     = form.dataset.nonce;
                var msgEl     = form.querySelector('.sc-sn-msg');
                var btn       = form.querySelector('button[type="submit"]');

                var emailInput = form.querySelector('input[name="sc_contact_email"]');
                var phoneInput = form.querySelector('input[name="sc_contact_phone"]');

                var body = new URLSearchParams();
                body.append('action',     'sc_stock_subscribe');
                body.append('product_id', productId);
                body.append('nonce',      nonce);
                if (emailInput && emailInput.value) body.append('sc_contact_email', emailInput.value);
                if (phoneInput && phoneInput.value) body.append('sc_contact_phone', phoneInput.value);

                btn.disabled = true;
                msgEl.className = 'sc-sn-msg';
                msgEl.textContent = '';

                fetch(spaceCore ? spaceCore.ajaxUrl : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        msgEl.classList.add('sc-success');
                        msgEl.textContent = data.data.message;
                        if (emailInput) emailInput.value = '';
                        if (phoneInput) phoneInput.value = '';
                    } else {
                        msgEl.classList.add('sc-error');
                        msgEl.textContent = data.data.message;
                    }
                })
                .catch(function () {
                    msgEl.classList.add('sc-error');
                    msgEl.textContent = 'An error occurred. Please try again.';
                })
                .finally(function () {
                    btn.disabled = false;
                });
            });
        });
    });

    // ── Expose ajaxUrl for front.js (injected via wp_localize_script) ─
    // spaceCore.ajaxUrl is expected — no fallback needed in production.
}());
