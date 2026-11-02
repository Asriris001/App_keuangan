<?php
// transaksi.php
$pageTitle = 'Riwayat Penjualan';
$headerTitle = 'Riwayat Transaksi Penjualan';
$headerSubtitle = 'Daftar seluruh transaksi pemasukan toko dengan filter dan export';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Handle Hapus Transaksi (Khusus Admin)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!is_admin()) {
        set_flash('error', 'Akses Ditolak', 'Hanya Administrator yang memiliki hak untuk membatalkan atau menghapus transaksi.');
        header('Location: transaksi.php');
        exit;
    }
    $id = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();

        // 1. Kembalikan stok produk jika item bersangkutan dari katalog
        $stmtItems = $pdo->prepare("SELECT produk_id, qty FROM `transaksi_detail` WHERE transaksi_id = ? AND produk_id IS NOT NULL");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        $stmtBalikkanStok = $pdo->prepare("UPDATE `produk` SET stok = stok + ? WHERE id = ?");
        foreach ($items as $it) {
            $stmtBalikkanStok->execute([$it['qty'], $it['produk_id']]);
        }

        // 2. Hapus Transaksi (detail terhapus otomatis karena ON DELETE CASCADE)
        $stmtDel = $pdo->prepare("DELETE FROM `transaksi` WHERE id = ?");
        $stmtDel->execute([$id]);

        $pdo->commit();
        set_flash('success', 'Berhasil Dihapus', 'Data transaksi berhasil dihapus dan stok produk telah dikembalikan.');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Gagal Menghapus', $e->getMessage());
    }
    header('Location: transaksi.php');
    exit;
}

// Filter Parameters
$filterPeriode = $_GET['periode'] ?? 'bulan_ini';
$tglMulai = $_GET['tgl_mulai'] ?? '';
$tglSelesai = $_GET['tgl_selesai'] ?? '';
$filterMetode = $_GET['metode'] ?? '';
$search = trim($_GET['search'] ?? '');

$today = date('Y-m-d');
if ($filterPeriode === 'hari_ini') {
    $tglMulai = $today;
    $tglSelesai = $today;
} elseif ($filterPeriode === 'minggu_ini') {
    $tglMulai = date('Y-m-d', strtotime('-6 days'));
    $tglSelesai = $today;
} elseif ($filterPeriode === 'bulan_ini') {
    $tglMulai = date('Y-m-01');
    $tglSelesai = date('Y-m-t');
} elseif ($filterPeriode === 'semua') {
    $tglMulai = '';
    $tglSelesai = '';
}

// Build SQL Query
$sql = "
    SELECT t.*, 
           COUNT(td.id) as total_item, 
           COALESCE(SUM(td.qty), 0) as total_qty,
           GROUP_CONCAT(CONCAT(td.nama_item, ':::', td.qty, ':::', td.harga_satuan) SEPARATOR '|||') as rincian_produk_raw
    FROM `transaksi` t
    LEFT JOIN `transaksi_detail` td ON t.id = td.transaksi_id
    WHERE 1=1
";
$params = [];

if (!empty($tglMulai) && !empty($tglSelesai)) {
    $sql .= " AND t.tanggal BETWEEN ? AND ?";
    $params[] = $tglMulai;
    $params[] = $tglSelesai;
}

if (!empty($filterMetode)) {
    $sql .= " AND t.metode_pembayaran = ?";
    $params[] = $filterMetode;
}

if (!empty($search)) {
    $sql .= " AND (t.nomor_transaksi LIKE ? OR t.nama_pelanggan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY t.id ORDER BY t.tanggal DESC, t.waktu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// Ambil Rekapitulasi Semua Jus & Menu yang Dipesan pada Filter Aktif
$sqlRekap = "
    SELECT td.nama_item, 
           SUM(td.qty) as total_qty, 
           SUM(td.subtotal) as total_omset
    FROM `transaksi_detail` td
    JOIN `transaksi` t ON td.transaksi_id = t.id
    WHERE 1=1
";
$paramsRekap = [];
if (!empty($tglMulai) && !empty($tglSelesai)) {
    $sqlRekap .= " AND t.tanggal BETWEEN ? AND ?";
    $paramsRekap[] = $tglMulai;
    $paramsRekap[] = $tglSelesai;
}
if (!empty($filterMetode)) {
    $sqlRekap .= " AND t.metode_pembayaran = ?";
    $paramsRekap[] = $filterMetode;
}
if (!empty($search)) {
    $sqlRekap .= " AND (t.nomor_transaksi LIKE ? OR t.nama_pelanggan LIKE ?)";
    $paramsRekap[] = "%$search%";
    $paramsRekap[] = "%$search%";
}
$sqlRekap .= " GROUP BY td.nama_item ORDER BY total_qty DESC";

$stmtRekap = $pdo->prepare($sqlRekap);
$stmtRekap->execute($paramsRekap);
$rekapJusDanMenu = $stmtRekap->fetchAll();

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Penjualan_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($output, ['No.', 'Nomor Invoice', 'Tanggal', 'Waktu', 'Nama Pelanggan', 'Kasir', 'Jus / Menu yang Dipesan', 'Jumlah Item', 'Metode Bayar', 'Subtotal (Rp)', 'Diskon (Rp)', 'Total Akhir (Rp)', 'Status']);
    
    $no = 1;
    foreach ($transaksiList as $row) {
        $formattedItems = [];
        if (!empty($row['rincian_produk_raw'])) {
            foreach (explode('|||', $row['rincian_produk_raw']) as $raw) {
                $p = explode(':::', $raw);
                $formattedItems[] = ($p[0] ?? '') . ' (' . ($p[1] ?? 1) . 'x)';
            }
        }

        fputcsv($output, [
            $no++,
            $row['nomor_transaksi'],
            $row['tanggal'],
            $row['waktu'],
            $row['nama_pelanggan'],
            $row['kasir_nama'] ?: 'Kasir',
            implode(', ', $formattedItems),
            $row['total_qty'],
            $row['metode_pembayaran'],
            $row['total_penjualan'],
            $row['diskon'],
            $row['total_akhir'],
            $row['status_pembayaran']
        ]);
    }
    fclose($output);
    exit;
}

// Summary Metrics for Current Filter
$totalFilteredGross = 0;
$totalFilteredDiskon = 0;
$totalFilteredOmset = 0;
$totalFilteredQty = 0;
$totalFilteredTrx = count($transaksiList);
$metodeSummary = ['Tunai' => 0, 'QRIS' => 0, 'Transfer Bank' => 0, 'Debit/Kredit' => 0];

foreach ($transaksiList as $t) {
    $totalFilteredGross += (float)$t['total_penjualan'];
    $totalFilteredDiskon += (float)$t['diskon'];
    $totalFilteredOmset += (float)$t['total_akhir'];
    $totalFilteredQty += (int)$t['total_qty'];
    $m = $t['metode_pembayaran'] ?? 'Tunai';
    if (!isset($metodeSummary[$m])) $metodeSummary[$m] = 0;
    $metodeSummary[$m] += (float)$t['total_akhir'];
}
$avgTrx = $totalFilteredTrx > 0 ? $totalFilteredOmset / $totalFilteredTrx : 0;

// Grand Total Sepanjang Waktu
$allTimeOmset = (float)$pdo->query("SELECT COALESCE(SUM(total_akhir), 0) FROM `transaksi`")->fetchColumn();
$allTimeTrx = (int)$pdo->query("SELECT COUNT(*) FROM `transaksi`")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <!-- GRAND TOTAL BANNER: TOTAL SEMUA HARGA & STRUK PEMASUKAN -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 rounded-3xl p-6 text-white shadow-xl flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold backdrop-blur-xs border border-emerald-500/30">
                    <i class="fa-solid fa-coins text-amber-300"></i>
                    <span>Rekapitulasi Total Semua Pemasukan</span>
                </div>
                <div>
                    <span class="text-xs text-slate-300 block uppercase tracking-wider font-semibold">Total Pemasukan Bersih (Sesuai Filter)</span>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-emerald-400 tracking-tight mt-0.5">
                        <?= rupiah($totalFilteredOmset) ?>
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-300 pt-1">
                    <span>Penjualan Kotor: <strong class="text-white"><?= rupiah($totalFilteredGross) ?></strong></span>
                    <span>•</span>
                    <span>Diskon: <strong class="text-rose-300"><?= $totalFilteredDiskon > 0 ? '-' . rupiah($totalFilteredDiskon) : 'Rp 0' ?></strong></span>
                    <span>•</span>
                    <span>Total Item: <strong class="text-white"><?= number_format($totalFilteredQty) ?> pcs</strong></span>
                    <span>•</span>
                    <span>Transaksi: <strong class="text-white"><?= $totalFilteredTrx ?> Nota</strong></span>
                </div>
            </div>

            <!-- Action Buttons Cetak Struk Rekap -->
            <?php 
            $rekapQuery = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
            ?>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto">
                <a href="cetak-struk-rekap.php<?= $rekapQuery ?>" target="_blank" 
                   class="px-5 py-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white rounded-2xl text-xs font-bold shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98]">
                    <i class="fa-solid fa-print text-sm"></i>
                    <span>Cetak Struk Rekap Semua Pemasukan</span>
                </a>
            </div>
        </div>

        <!-- Metode Pembayaran Breakdown Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Pemasukan Tunai</span>
                    <span class="font-extrabold text-slate-800 text-sm"><?= rupiah($metodeSummary['Tunai'] ?? 0) ?></span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Pemasukan QRIS</span>
                    <span class="font-extrabold text-slate-800 text-sm"><?= rupiah($metodeSummary['QRIS'] ?? 0) ?></span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Transfer Bank</span>
                    <span class="font-extrabold text-slate-800 text-sm"><?= rupiah($metodeSummary['Transfer Bank'] ?? 0) ?></span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Debit / Kredit</span>
                    <span class="font-extrabold text-slate-800 text-sm"><?= rupiah($metodeSummary['Debit/Kredit'] ?? 0) ?></span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <form method="GET" action="transaksi.php" class="space-y-4">
                
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Quick Periods -->
                    <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                        <a href="transaksi.php?periode=hari_ini" class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $filterPeriode === 'hari_ini' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            Hari Ini
                        </a>
                        <a href="transaksi.php?periode=minggu_ini" class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $filterPeriode === 'minggu_ini' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            7 Hari Terakhir
                        </a>
                        <a href="transaksi.php?periode=bulan_ini" class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $filterPeriode === 'bulan_ini' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            Bulan Ini
                        </a>
                        <a href="transaksi.php?periode=semua" class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $filterPeriode === 'semua' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                            Semua Riwayat
                        </a>
                    </div>

                    <!-- Right Buttons: Struk Rekap, Export CSV & Kasir -->
                    <div class="flex items-center gap-2">
                        <a href="cetak-struk-rekap.php<?= $rekapQuery ?>" target="_blank" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5">
                            <i class="fa-solid fa-receipt text-emerald-600"></i>
                            <span>Cetak Struk Rekap</span>
                        </a>
                        <?php 
                        $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] . '&export=csv' : '?export=csv';
                        ?>
                        <a href="transaksi.php<?= $queryString ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                            <i class="fa-solid fa-file-excel text-emerald-600"></i>
                            <span>Export CSV</span>
                        </a>
                        <a href="kasir.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-plus"></i>
                            <span>Transaksi Baru</span>
                        </a>
                    </div>
                </div>

                <!-- Detail Filters Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-slate-100">
                    <input type="hidden" name="periode" value="kustom">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Dari Tanggal</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tglMulai) ?>" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Sampai Tanggal</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tglSelesai) ?>" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Metode Bayar</label>
                        <select name="metode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                            <option value="">Semua Metode</option>
                            <option value="Tunai" <?= $filterMetode === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                            <option value="QRIS" <?= $filterMetode === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                            <option value="Transfer Bank" <?= $filterMetode === 'Transfer Bank' ? 'selected' : '' ?>>Transfer Bank</option>
                            <option value="Debit/Kredit" <?= $filterMetode === 'Debit/Kredit' ? 'selected' : '' ?>>Debit/Kredit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Cari Transaksi</label>
                        <div class="flex gap-2">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="No. Invoice / Nama..." 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                            <button type="submit" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition-colors">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200/80">
                            <th class="py-3 px-4">No. Invoice</th>
                            <th class="py-3 px-4">Tanggal & Waktu</th>
                            <th class="py-3 px-4">Pelanggan</th>
                            <th class="py-3 px-4 text-center">Jml Item</th>
                            <th class="py-3 px-4">Metode</th>
                            <th class="py-3 px-4 text-right">Subtotal</th>
                            <th class="py-3 px-4 text-right">Diskon</th>
                            <th class="py-3 px-4 text-right">Total Akhir</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($transaksiList)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-12 text-slate-400">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-2 text-slate-400">
                                        <i class="fa-solid fa-inbox text-xl"></i>
                                    </div>
                                    <p class="font-medium text-slate-600">Tidak ada transaksi ditemukan</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Coba sesuaikan filter atau rentang tanggal Anda.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transaksiList as $row): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-emerald-700">
                                        <a href="transaksi-detail.php?id=<?= $row['id'] ?>" class="hover:underline">
                                            <?= htmlspecialchars($row['nomor_transaksi']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">
                                        <div class="font-medium text-slate-700"><?= tanggal_indo($row['tanggal'], false, true) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= substr($row['waktu'], 0, 5) ?> WIB</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($row['nama_pelanggan']) ?></div>
                                        <?php if (!empty($row['kasir_nama'])): ?>
                                            <div class="text-[10px] text-slate-400">Kasir: <?= htmlspecialchars($row['kasir_nama']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-semibold text-[11px]">
                                            <?= $row['total_qty'] ?: 0 ?> pcs
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold 
                                            <?= $row['metode_pembayaran'] === 'Tunai' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 
                                               ($row['metode_pembayaran'] === 'QRIS' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200') ?>">
                                            <?= htmlspecialchars($row['metode_pembayaran']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-600">
                                        <?= rupiah($row['total_penjualan']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-right text-rose-600">
                                        <?= $row['diskon'] > 0 ? '-' . rupiah($row['diskon']) : '-' ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900 text-sm">
                                        <?= rupiah($row['total_akhir']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="transaksi-detail.php?id=<?= $row['id'] ?>" title="Lihat Detail" class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-600 hover:bg-slate-100 transition-colors">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                            <a href="cetak-struk.php?id=<?= $row['id'] ?>" target="_blank" title="Cetak Struk" class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-colors">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                            <?php if (is_admin()): ?>
                                            <button type="button" onclick="confirmDelete('transaksi.php?action=delete&id=<?= $row['id'] ?>', 'Hapus Transaksi <?= $row['nomor_transaksi'] ?>?', 'Stok produk yang terjual dalam transaksi ini akan otomatis dikembalikan ke inventaris!')" title="Hapus Transaksi" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

