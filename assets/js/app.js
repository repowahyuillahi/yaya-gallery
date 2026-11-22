(function () {
    'use strict';

    // Drawer menu mobile: buka/tutup, Escape menutup, fokus kembali ke pemicu.
    var menuBtn = document.getElementById('menuBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('overlay');

    function setDrawer(open) {
        if (!sidebar || !menuBtn) return;
        sidebar.classList.toggle('open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.hidden = !open;
        document.body.style.overflow = open ? 'hidden' : '';
        if (!open) menuBtn.focus();
    }

    if (menuBtn && sidebar) {
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
})();
