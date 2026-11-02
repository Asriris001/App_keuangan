<?php
// laporan.php
$pageTitle = 'Laporan Keuangan & Laba Rugi';
$headerTitle = 'Laporan Keuangan & Laba Rugi';
$headerSubtitle = 'Laporan rekapitulasi performa penjualan, laba kotor, dan laba bersih';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Khusus Administrator
require_admin();

$bulanPilihan = $_GET['bulan'] ?? date('Y-m');
$tglAwal = $bulanPilihan . '-01';
$tglAkhir = date('Y-m-t', strtotime($tglAwal));

// 1. Data Penjualan Periode Ini
$stmtSales = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_penjualan), 0) as gross_sales,
        COALESCE(SUM(diskon), 0) as total_diskon,
        COALESCE(SUM(total_akhir), 0) as net_sales,
        COALESCE(SUM(total_modal), 0) as total_cogs,
        COUNT(id) as total_trx
    FROM `transaksi` 
    WHERE tanggal BETWEEN ? AND ?
");
$stmtSales->execute([$tglAwal, $tglAkhir]);
$sales = $stmtSales->fetch();

$grossSales = (float)$sales['gross_sales'];
$totalDiskon = (float)$sales['total_diskon'];
$netSales = (float)$sales['net_sales'];
$totalCogs = (float)$sales['total_cogs'];
$grossProfit = $netSales - $totalCogs;
$grossMarginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0;

// 2. Data Pengeluaran per Kategori Periode Ini
$stmtExpCat = $pdo->prepare("
    SELECT 
        COALESCE(k.nama, 'Lain-lain / Tanpa Kategori') as kategori,
        SUM(e.nominal) as total_nominal,
        COUNT(e.id) as total_catatan
    FROM `pengeluaran` e
    LEFT JOIN `kategori` k ON e.kategori_id = k.id
    WHERE e.tanggal BETWEEN ? AND ?
    GROUP BY e.kategori_id
    ORDER BY total_nominal DESC
");
$stmtExpCat->execute([$tglAwal, $tglAkhir]);
$expCategories = $stmtExpCat->fetchAll();

$totalExpenses = 0;
foreach ($expCategories as $ec) {
    $totalExpenses += (float)$ec['total_nominal'];
}

// 3. Laba Bersih
$netProfit = $grossProfit - $totalExpenses;
$netMarginPct = $netSales > 0 ? round(($netProfit / $netSales) * 100, 1) : 0;

// 4. Rekapitulasi Harian dalam Bulan Tersebut
$stmtDaily = $pdo->prepare("
    SELECT 
        t.tanggal,
        COUNT(t.id) as jml_trx,
        SUM(t.total_akhir) as omset_hari,
        SUM(t.total_modal) as modal_hari
    FROM `transaksi` t
    WHERE t.tanggal BETWEEN ? AND ?
    GROUP BY t.tanggal
    ORDER BY t.tanggal ASC
");
$stmtDaily->execute([$tglAwal, $tglAkhir]);
$dailySales = $stmtDaily->fetchAll();

// Mapping pengeluaran harian
$stmtDailyExp = $pdo->prepare("
    SELECT tanggal, SUM(nominal) as biaya_hari 
    FROM `pengeluaran` 
    WHERE tanggal BETWEEN ? AND ? 
    GROUP BY tanggal
");
$stmtDailyExp->execute([$tglAwal, $tglAkhir]);
$dailyExpensesMap = [];
while ($row = $stmtDailyExp->fetch()) {
    $dailyExpensesMap[$row['tanggal']] = (float)$row['biaya_hari'];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <!-- Filter & Print Action Bar (Hidden when printed) -->
        <div class="no-print bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <form method="GET" action="laporan.php" class="flex items-center gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Pilih Bulan & Tahun</label>
                    <input type="month" name="bulan" value="<?= htmlspecialchars($bulanPilihan) ?>" onchange="this.form.submit()" 
                        class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-emerald-500">
                </div>
                <div class="pt-4">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition-colors">
                        Tampilkan
                    </button>
                </div>
            </form>

            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-print"></i>
                    <span>Cetak Laporan PDF / Fisik</span>
                </button>
            </div>
        </div>

        <!-- Printable Area -->
        <div class="printable-area space-y-6">

            <!-- Print Header (Official letterhead for print) -->
            <div class="hidden print-only text-center pb-4 border-b-2 border-slate-900 mb-6">
                <h1 class="text-xl font-bold uppercase tracking-wide text-slate-900"><?= htmlspecialchars($appPengaturan['nama_toko']) ?></h1>
                <p class="text-xs text-slate-600"><?= htmlspecialchars($appPengaturan['alamat']) ?> | Telp: <?= htmlspecialchars($appPengaturan['telepon']) ?></p>
                <div class="mt-2 pt-2 border-t border-slate-300">
                    <h2 class="text-sm font-bold uppercase text-slate-800">Laporan Laba Rugi Usaha</h2>
                    <p class="text-xs text-slate-500">Periode: <?= date('F Y', strtotime($tglAwal)) ?></p>
                </div>
            </div>

            <!-- Top Executive Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Penjualan Bersih (Omset)</span>
                    <h3 class="text-xl font-extrabold text-slate-800 mt-2"><?= rupiah($netSales) ?></h3>
                    <p class="text-[11px] text-slate-400 mt-1"><?= $sales['total_trx'] ?> Transaksi</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Beban Pokok (HPP)</span>
                    <h3 class="text-xl font-extrabold text-slate-800 mt-2"><?= rupiah($totalCogs) ?></h3>
                    <p class="text-[11px] text-slate-400 mt-1">Biaya modal produk terjual</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Beban Operasional</span>
                    <h3 class="text-xl font-extrabold text-rose-600 mt-2"><?= rupiah($totalExpenses) ?></h3>
                    <p class="text-[11px] text-slate-400 mt-1">Total biaya toko</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Laba Bersih Akhir</span>
                    <h3 class="text-xl font-extrabold <?= $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-600' ?> mt-2">
                        <?= rupiah($netProfit) ?>
                    </h3>
                    <p class="text-[11px] font-semibold <?= $netProfit >= 0 ? 'text-emerald-600' : 'text-rose-500' ?> mt-1">
                        Margin Laba: <?= $netMarginPct ?>%
                    </p>
                </div>
            </div>

            <!-- LAPORAN LABA RUGI FORMAL (INCOME STATEMENT) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Laporan Laba / Rugi Komprehensif</h3>
                        <p class="text-xs text-slate-500">Periode: <?= date('F Y', strtotime($tglAwal)) ?> (1 s/d <?= date('t', strtotime($tglAwal)) ?> <?= date('F Y', strtotime($tglAwal)) ?>)</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $netProfit >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                        <?= $netProfit >= 0 ? 'SURPLUS / UNTUNG' : 'DEFISIT / RUGI' ?>
                    </span>
                </div>

                <div class="p-6 space-y-6 text-xs">
                    
                    <!-- 1. Pendapatan Penjualan -->
                    <div class="space-y-2">
                        <h4 class="font-bold text-sm text-slate-800 uppercase tracking-wide flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs">A</span>
                            <span>Pendapatan Penjualan (Revenue)</span>
                        </h4>
                        <div class="pl-8 space-y-1.5">
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600">Total Penjualan Kotor (Gross Sales)</span>
                                <span class="font-medium text-slate-800"><?= rupiah($grossSales) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 text-rose-600">
                                <span>Potongan & Diskon Penjualan</span>
                                <span>(<?= rupiah($totalDiskon) ?>)</span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-slate-800 bg-slate-50 px-2 rounded-lg">
                                <span>Total Penjualan Bersih (Net Sales)</span>
                                <span class="text-emerald-700"><?= rupiah($netSales) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Harga Pokok Penjualan -->
                    <div class="space-y-2">
                        <h4 class="font-bold text-sm text-slate-800 uppercase tracking-wide flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center text-xs">B</span>
                            <span>Harga Pokok Penjualan (HPP / Biaya Modal)</span>
                        </h4>
                        <div class="pl-8 space-y-1.5">
                            <div class="flex justify-between py-1 border-b border-slate-100 text-slate-600">
                                <span>Total Modal Barang / Layanan Terjual</span>
                                <span>(<?= rupiah($totalCogs) ?>)</span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-slate-800 bg-slate-50 px-2 rounded-lg">
                                <span>LABA KOTOR (GROSS PROFIT) [A - B]</span>
                                <span class="text-blue-700"><?= rupiah($grossProfit) ?> (Margin: <?= $grossMarginPct ?>%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Beban Operasional -->
                    <div class="space-y-2">
                        <h4 class="font-bold text-sm text-slate-800 uppercase tracking-wide flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-800 flex items-center justify-center text-xs">C</span>
                            <span>Beban & Pengeluaran Operasional</span>
                        </h4>
                        <div class="pl-8 space-y-1.5">
                            <?php if (empty($expCategories)): ?>
                                <div class="text-slate-400 py-1">Tidak ada catatan beban pengeluaran operasional pada periode ini.</div>
                            <?php else: ?>
                                <?php foreach ($expCategories as $ec): 
                                    $pct = $totalExpenses > 0 ? round(($ec['total_nominal'] / $totalExpenses) * 100, 1) : 0;
                                ?>
                                    <div class="flex justify-between py-1 border-b border-slate-100 text-slate-600">
                                        <span><?= htmlspecialchars($ec['kategori']) ?> (<?= $ec['total_catatan'] ?>x)</span>
                                        <span><?= rupiah($ec['total_nominal']) ?> <span class="text-slate-400 text-[10px]">(<?= $pct ?>%)</span></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="flex justify-between py-1.5 font-bold text-slate-800 bg-rose-50 px-2 rounded-lg text-rose-700">
                                <span>Total Beban Operasional</span>
                                <span>(<?= rupiah($totalExpenses) ?>)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Laba Bersih Akhir -->
                    <div class="pt-4 border-t-2 border-slate-200">
                        <div class="flex justify-between items-center p-4 rounded-xl <?= $netProfit >= 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-rose-50 border border-rose-200' ?>">
                            <div>
                                <h3 class="text-base font-extrabold uppercase <?= $netProfit >= 0 ? 'text-emerald-900' : 'text-rose-900' ?>">
                                    LABA BERSIH USAHA (NET PROFIT)
                                </h3>
                                <p class="text-[11px] <?= $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                                    Hasil akhir setelah dikurangi biaya modal barang dan seluruh operasional
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-2xl font-extrabold <?= $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                                    <?= rupiah($netProfit) ?>
                                </span>
                                <div class="text-[11px] font-semibold text-slate-500">Margin Bersih: <?= $netMarginPct ?>%</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- TABEL REKAPITULASI PENJUALAN HARIAN -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Rincian Penjualan Harian</h4>
                        <p class="text-xs text-slate-500">Omset, modal, dan laba per hari dalam bulan <?= date('F Y', strtotime($tglAwal)) ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200/80">
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4 text-center">Transaksi</th>
                                <th class="py-3 px-4 text-right">Omset Penjualan</th>
                                <th class="py-3 px-4 text-right">Modal (HPP)</th>
                                <th class="py-3 px-4 text-right">Laba Kotor</th>
                                <th class="py-3 px-4 text-right">Beban Operasional</th>
                                <th class="py-3 px-4 text-right font-bold">Laba Bersih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($dailySales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-slate-400">Tidak ada riwayat transaksi pada bulan ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dailySales as $ds): 
                                    $omset = (float)$ds['omset_hari'];
                                    $modal = (float)$ds['modal_hari'];
                                    $labaKotor = $omset - $modal;
                                    $expHari = $dailyExpensesMap[$ds['tanggal']] ?? 0;
                                    $labaBersihHari = $labaKotor - $expHari;
                                ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-3 px-4 font-semibold text-slate-800">
                                            <?= tanggal_indo($ds['tanggal'], true) ?>
                                        </td>
                                        <td class="py-3 px-4 text-center font-bold text-slate-600">
                                            <?= $ds['jml_trx'] ?>x
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-slate-900">
                                            <?= rupiah($omset) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right text-slate-500">
                                            <?= rupiah($modal) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right text-blue-700 font-semibold">
                                            <?= rupiah($labaKotor) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right text-rose-600">
                                            <?= $expHari > 0 ? '-' . rupiah($expHari) : '-' ?>
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold <?= $labaBersihHari >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
                                            <?= rupiah($labaBersihHari) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tanda Tangan untuk Cetak -->
            <div class="hidden print-only pt-12">
                <div class="flex justify-between text-xs text-slate-700">
                    <div class="text-center w-48">
                        <p>Dibuat Oleh,</p>
                        <div class="h-16"></div>
                        <p class="font-bold border-t border-slate-400 pt-1">Kasir / Bagian Keuangan</p>
                    </div>
                    <div class="text-center w-48">
                        <p>Mengetahui,</p>
                        <div class="h-16"></div>
                        <p class="font-bold border-t border-slate-400 pt-1">Pemilik Usaha</p>
                    </div>
                </div>
            </div>

        </div>

    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

