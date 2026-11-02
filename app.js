// assets/js/app.js

// Format integer to Rupiah string
function formatRupiah(angka, prefix = 'Rp ') {
    let number_string = Math.round(angka).toString().replace(/[^,\d]/g, '');
    let split = number_string.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return (prefix ? prefix : '') + (rupiah || '0');
}

// Parse Rupiah string or formatted number to plain number
function parseRupiah(str) {
    if (typeof str === 'number') return str;
    let clean = (str || '').toString().replace(/[^0-9]/g, '');
    return parseInt(clean, 10) || 0;
}

// Generic SweetAlert Delete Confirmation
function confirmDelete(url, title = 'Apakah Anda yakin?', text = 'Data yang dihapus tidak dapat dikembalikan!') {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

// Mobile sidebar toggle handler
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggleBtn && sidebar && overlay) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });
    }
});

