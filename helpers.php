<?php
// config/helpers.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Format angka ke format mata uang Rupiah
 */
function rupiah($angka, $withPrefix = true) {
    if ($angka === null || $angka === '') $angka = 0;
    $hasil = number_format((float)$angka, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $hasil : $hasil;
}

/**
 * Format tanggal Indonesia
 */
function tanggal_indo($tanggal, $cetak_hari = false, $singkat = false) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';

    $hari = [
        1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'
    ];

    $bulanPanjang = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $bulanSingkat = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'
    ];

    $timestamp = strtotime($tanggal);
    $bulanArray = $singkat ? $bulanSingkat : $bulanPanjang;

    $tgl = date('d', $timestamp);
    $bln = $bulanArray[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);

    $hasil = "$tgl $bln $thn";

    if ($cetak_hari) {
        $numHari = date('N', $timestamp);
        $hasil = $hari[$numHari] . ', ' . $hasil;
    }

    return $hasil;
}

/**
 * Set flash message untuk notifikasi SweetAlert2
 */
function set_flash($type, $title, $text = '') {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'title' => $title,
        'text' => $text
    ];
}

/**
 * Ambil dan hapus flash message
 */
function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Ambil pengaturan toko
 */
function get_pengaturan($pdo) {
    static $pengaturan = null;
    if ($pengaturan === null) {
        $stmt = $pdo->query("SELECT * FROM `pengaturan` ORDER BY id ASC LIMIT 1");
        $pengaturan = $stmt->fetch();
        if (!$pengaturan) {
            $pengaturan = [
                'nama_toko' => 'Toko Berkah Mandiri',
                'slogan' => 'Pencatatan Penjualan & Keuangan Usaha',
                'alamat' => 'Alamat belum diatur',
                'telepon' => '-',
                'email' => '-',
                'pesan_struk' => 'Terima kasih telah berbelanja!',
                'mata_uang' => 'Rp'
            ];
        }
    }
    return $pengaturan;
}

/**
 * Buat nomor transaksi otomatis (contoh: TRX-20261023-0008)
 */
function generate_nomor_transaksi($pdo) {
    $today = date('Ymd');
    $prefix = "TRX-$today-";
    $stmt = $pdo->prepare("SELECT nomor_transaksi FROM `transaksi` WHERE nomor_transaksi LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -4);
        $newSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newSeq = '0001';
    }
    return $prefix . $newSeq;
}

/**
 * Buat nomor pengeluaran otomatis (contoh: EXP-20261023-0004)
 */
function generate_nomor_pengeluaran($pdo) {
    $today = date('Ymd');
    $prefix = "EXP-$today-";
    $stmt = $pdo->prepare("SELECT nomor_pengeluaran FROM `pengeluaran` WHERE nomor_pengeluaran LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -4);
        $newSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newSeq = '0001';
    }
    return $prefix . $newSeq;
}

/**
 * Cek halaman aktif untuk menu navigasi
 */
function is_active($pages) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (is_array($pages)) {
        return in_array($currentPage, $pages) ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    }
    return $currentPage === $pages ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
}

/**
 * Cek apakah user sudah login, jika belum arahkan ke login.php
 */
function check_login() {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Ambil data user yang sedang login
 */
function get_logged_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Cek apakah user adalah admin
 */
function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

/**
 * Cek apakah user adalah kasir
 */
function is_kasir() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'kasir';
}

/**
 * Pastikan hanya role Admin yang bisa mengakses halaman tertentu
 */
function require_admin() {
    check_login();
    if (!is_admin()) {
        set_flash('error', 'Akses Ditolak!', 'Halaman ini khusus untuk Administrator.');
        header('Location: kasir.php');
        exit;
    }
}


