<?php defined( 'ABSPATH' ) || exit; ?>
<script>
(function () {
    document.querySelectorAll('.sc-store-notice').forEach(function (el) {
        var id = el.dataset.id;
        if (id && sessionStorage.getItem('sc_notice_' + id)) el.style.display = 'none';
    });
    document.querySelectorAll('.sc-notice-dismiss').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var notice = btn.closest('.sc-store-notice');
            var id = notice ? notice.dataset.id : '';
            if (notice) notice.style.display = 'none';
            if (id) {
                try {
                    sessionStorage.setItem('sc_notice_' + id, '1');
                } catch (e) {
                }
            }
        });
    });
})();
</script>
