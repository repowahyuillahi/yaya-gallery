(function () {
    'use strict';

    // Drawer menu mobile: buka/tutup, Escape menutup, fokus dikelola, dan sidebar
    // keluar dari urutan tab saat tertutup (inert) supaya tautan tak terlihat tidak bisa difokus.
    var menuBtn = document.getElementById('menuBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('overlay');
    var mq = window.matchMedia('(max-width: 900px)');

    function syncInert() {
        if (!sidebar || !('inert' in sidebar)) return;
        sidebar.inert = mq.matches && !sidebar.classList.contains('open');
    }

    function setDrawer(open) {
        if (!sidebar || !menuBtn) return;
        sidebar.classList.toggle('open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.hidden = !open;
        document.body.style.overflow = open ? 'hidden' : '';
        syncInert();
        if (open) {
            var first = sidebar.querySelector('a');
            if (first) first.focus();
        } else {
            menuBtn.focus();
        }
    }

    if (menuBtn && sidebar) {
        syncInert();
        if (mq.addEventListener) mq.addEventListener('change', syncInert);
        menuBtn.addEventListener('click', function () {
            setDrawer(!sidebar.classList.contains('open'));
        });
        if (overlay) overlay.addEventListener('click', function () { setDrawer(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) setDrawer(false);
        });
        sidebar.addEventListener('click', function (e) {
            if (e.target.closest('a')) setDrawer(false);
        });
    }

    // Konfirmasi untuk aksi yang menghapus data.
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    // Notifikasi bisa ditutup dan hilang sendiri setelah beberapa detik.
    document.addEventListener('click', function (e) {
        var x = e.target.closest('.flash-x');
        if (x) x.closest('.flash').remove();
    });
    document.querySelectorAll('.flash').forEach(function (f) {
        setTimeout(function () { f.remove(); }, 7000);
    });

    // Pencarian baju pada form penyewaan.
    var pickSearch = document.getElementById('pickSearch');
    var pickCat = document.getElementById('pickCat');
    if (pickSearch) {
        var applyFilter = function () {
            var q = pickSearch.value.toLowerCase();
            var cat = pickCat ? pickCat.value : '';
            document.querySelectorAll('.pick').forEach(function (p) {
                var text = (p.getAttribute('data-search') || '').toLowerCase();
                var pcat = p.getAttribute('data-cat') || '';
                p.hidden = (q !== '' && text.indexOf(q) === -1) || (cat !== '' && pcat !== cat);
            });
        };
        pickSearch.addEventListener('input', applyFilter);
        if (pickCat) pickCat.addEventListener('change', applyFilter);
    }

    // Pratinjau total pada form penyewaan.
    var sewaForm = document.querySelector('form[action="sewa_form.php"]');
    if (sewaForm) {
        var sumSubtotal = document.getElementById('sumSubtotal');
        var sumDiscount = document.getElementById('sumDiscount');
        var sumExtra = document.getElementById('sumExtra');
        var sumTotal = document.getElementById('sumTotal');
        var sumDp = document.getElementById('sumDp');
        var sumSisa = document.getElementById('sumSisa');
        var discountInput = document.getElementById('discount');
        var extraInput = document.getElementById('extra_fee');
        var dpInput = document.getElementById('dp');

        function fmt(n) { return 'Rp ' + n.toLocaleString('id-ID'); }
        function num(v) { return parseInt((v || '').replace(/[^0-9]/g, ''), 10) || 0; }

        function updateSummary() {
            if (!sumTotal) return;
            var subtotal = 0;
            sewaForm.querySelectorAll('input[name="items[]"]:checked').forEach(function (cb) {
                subtotal += parseInt(cb.getAttribute('data-price'), 10) || 0;
            });
            var discount = num(discountInput ? discountInput.value : 0);
            var extra = num(extraInput ? extraInput.value : 0);
            var dp = num(dpInput ? dpInput.value : 0);
            var total = subtotal - discount + extra;
            var sisa = total - dp;
            sumSubtotal.textContent = fmt(subtotal);
            sumDiscount.textContent = '- ' + fmt(discount);
            sumExtra.textContent = fmt(extra);
            sumTotal.textContent = fmt(total);
            sumDp.textContent = fmt(dp);
            sumSisa.textContent = fmt(sisa);
        }

        sewaForm.addEventListener('input', updateSummary);
        sewaForm.addEventListener('change', updateSummary);
        updateSummary();
    }

    // Pratinjau foto sebelum upload.
    var photoInput = document.getElementById('photoInput');
    var photoPreview = document.getElementById('photoPreview');
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            var file = photoInput.files && photoInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function () {
                photoPreview.src = reader.result;
                photoPreview.hidden = false;
            };
            reader.readAsDataURL(file);
        });
    }

    // Tombol cetak pada invoice.
    var printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }

    // Modal pembayaran (piutang/utang): buka dialog dan isi field dari tombol.
    document.querySelectorAll('[data-bayar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = document.getElementById(btn.getAttribute('data-dialog'));
            if (!dlg || typeof dlg.showModal !== 'function') return;
            var idField = dlg.querySelector('[data-field="id"]');
            var jumlahField = dlg.querySelector('[data-field="jumlah"]');
            if (idField) idField.value = btn.getAttribute('data-id');
            if (jumlahField) jumlahField.value = btn.getAttribute('data-sisa');
            dlg.showModal();
        });
    });
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = btn.closest('dialog');
            if (dlg && typeof dlg.close === 'function') dlg.close();
        });
    });
})();
