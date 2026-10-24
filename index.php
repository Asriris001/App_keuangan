<?php
// index.php
$pageTitle = 'Dashboard Ringkasan Keuangan';
$headerTitle = 'Dashboard Pemasukan & Keuangan';
$headerSubtitle = 'Ringkasan performa penjualan, omset, dan laba usaha real-time';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <?php
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $thisMonth = date('Y-m');

        // 1. Data Hari Ini
        $stmtToday = $pdo->prepare("SELECT COALESCE(SUM(total_akhir), 0) as omset, COUNT(*) as total_trx, COALESCE(SUM(total_modal), 0) as modal FROM `transaksi` WHERE tanggal = ?");
        $stmtToday->execute([$today]);
        $dataToday = $stmtToday->fetch();

        // 2. Data Kemarin (untuk komparasi)
        $stmtYest = $pdo->prepare("SELECT COALESCE(SUM(total_akhir), 0) as omset FROM `transaksi` WHERE tanggal = ?");
        $stmtYest->execute([$yesterday]);
        $omsetYest = (float)$stmtYest->fetchColumn();

        $omsetDiff = $dataToday['omset'] - $omsetYest;

        // 3. Data Bulan Ini
        $stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(total_akhir), 0) as omset, COUNT(*) as total_trx, COALESCE(SUM(total_modal), 0) as modal FROM `transaksi` WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
        $stmtMonth->execute([$thisMonth]);
        $dataMonth = $stmtMonth->fetch();

        // 4. Data Pengeluaran Bulan Ini
        $stmtExp = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) as total FROM `pengeluaran` WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
        $stmtExp->execute([$thisMonth]);
        $pengeluaranBulanIni = (float)$stmtExp->fetchColumn();

        // 5. Laba Bersih Bulan Ini = (Omset Bulan Ini - Modal HPP Bulan Ini) - Pengeluaran Operasional
        $labaKotorBulanIni = $dataMonth['omset'] - $dataMonth['modal'];
        $labaBersihBulanIni = $labaKotorBulanIni - $pengeluaranBulanIni;

        // 6. Data Tren 7 Hari Terakhir
        $chartDates = [];
        $chartOmset = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chartDates[] = date('d M', strtotime($d));

            $stmtD = $pdo->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM `transaksi` WHERE tanggal = ?");
            $stmtD->execute([$d]);
            $chartOmset[] = (float)$stmtD->fetchColumn();
        }

        // 7. Data Metode Pembayaran Bulan Ini
        $stmtPayment = $pdo->prepare("SELECT metode_pembayaran, COUNT(*) as count, SUM(total_akhir) as total FROM `transaksi` WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? GROUP BY metode_pembayaran");
        $stmtPayment->execute([$thisMonth]);
        $paymentData = $stmtPayment->fetchAll();

        $paymentLabels = [];
        $paymentCounts = [];
        foreach ($paymentData as $pm) {
            $paymentLabels[] = $pm['metode_pembayaran'];
            $paymentCounts[] = (int)$pm['count'];
        }

        // 8. Top 5 Produk Terlaris Bulan Ini
        $stmtTop = $pdo->prepare("
            SELECT td.nama_item, SUM(td.qty) as total_qty, SUM(td.subtotal) as total_omset 
            FROM `transaksi_detail` td 
            JOIN `transaksi` t ON td.transaksi_id = t.id 
            WHERE DATE_FORMAT(t.tanggal, '%Y-%m') = ?
            GROUP BY td.nama_item 
            ORDER BY total_qty DESC 
            LIMIT 5
        ");
        $stmtTop->execute([$thisMonth]);
        $topProducts = $stmtTop->fetchAll();

        // 9. Transaksi Terbaru
        $stmtRecent = $pdo->query("SELECT * FROM `transaksi` ORDER BY id DESC LIMIT 5");
        $recentTrx = $stmtRecent->fetchAll();
        ?>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Pemasukan Hari Ini -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Omset Hari Ini</span>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= rupiah($dataToday['omset']) ?></h3>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500"><i class="fa-solid fa-receipt mr-1 text-slate-400"></i><?= $dataToday['total_trx'] ?> Transaksi</span>
                        <?php if ($omsetDiff >= 0): ?>
                            <span class="text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full">+<?= rupiah($omsetDiff) ?></span>
                        <?php else: ?>
                            <span class="text-rose-600 font-semibold bg-rose-50 px-2 py-0.5 rounded-full"><?= rupiah($omsetDiff) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pemasukan Bulan Ini -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Omset Bulan Ini</span>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= rupiah($dataMonth['omset']) ?></h3>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Total: <?= $dataMonth['total_trx'] ?> Penjualan</span>
                        <span class="font-medium text-slate-600"><?= date('F Y') ?></span>
                    </div>
                </div>
            </div>

            <?php if (is_admin()): ?>
            <!-- Estimasi Laba Bersih (Khusus Admin) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Laba Bersih Bulan Ini</span>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold <?= $labaBersihBulanIni >= 0 ? 'text-purple-700' : 'text-rose-600' ?> tracking-tight">
                        <?= rupiah($labaBersihBulanIni) ?>
                    </h3>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Laba Kotor: <?= rupiah($labaKotorBulanIni) ?></span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $labaBersihBulanIni >= 0 ? 'bg-purple-100 text-purple-700' : 'bg-rose-100 text-rose-700' ?>">
                            <?= $dataMonth['omset'] > 0 ? round(($labaBersihBulanIni / $dataMonth['omset']) * 100, 1) . '%' : '0%' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Total Pengeluaran Operasional (Khusus Admin) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Biaya / Pengeluaran</span>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= rupiah($pengeluaranBulanIni) ?></h3>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Biaya operasional toko</span>
                        <a href="pengeluaran.php" class="text-rose-600 hover:text-rose-700 font-medium">Detail &rarr;</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Card Pengganti untuk Kasir: Rata-rata Penjualan -->
            <?php $avgKasir = $dataMonth['total_trx'] > 0 ? $dataMonth['omset'] / $dataMonth['total_trx'] : 0; ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Rata-rata Transaksi</span>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= rupiah($avgKasir) ?></h3>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Rata-rata per pelanggan</span>
                        <span class="font-medium text-slate-600">Bulan Ini</span>
                    </div>
                </div>
            </div>

            <!-- Card Pengganti untuk Kasir: Status Shift / Kasir -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Petugas Bertugas</span>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="text-xl font-extrabold text-teal-700 tracking-tight truncate"><?= htmlspecialchars($loggedUser['nama_lengkap'] ?? 'Kasir') ?></h3>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Siap Melayani</span>
                        <a href="kasir.php" class="text-teal-600 hover:text-teal-700 font-bold">Buka Kasir &rarr;</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick POS Banner / Shortcut -->
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl p-6 text-white shadow-md flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center md:text-left">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 text-xs font-semibold backdrop-blur-sm">
                    <i class="fa-solid fa-sparkles text-amber-300"></i> Mode Kasir & Pemasukan Cepat
                </div>
                <h3 class="text-xl md:text-2xl font-bold">Siap Mencatat Penjualan Baru?</h3>
                <p class="text-sm text-emerald-100 max-w-xl">
                    Input transaksi dengan cepat, pilih produk atau nominal langsung, dan cetak struk kasir otomatis untuk pelanggan Anda.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="cetak-struk-rekap.php?periode=hari_ini" target="_blank" class="px-4 py-3 bg-white/20 hover:bg-white/30 text-white rounded-xl font-bold text-xs backdrop-blur-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Struk Rekap Hari Ini</span>
                </a>
                <a href="kasir.php" class="px-5 py-3 bg-white text-emerald-700 hover:bg-emerald-50 rounded-xl font-bold text-xs shadow-md transition-all hover:scale-105 active:scale-95 flex items-center gap-2">
                    <i class="fa-solid fa-cash-register text-sm"></i>
                    <span>Buka Mesin Kasir</span>
                </a>
            </div>
        </div>

        <!-- Charts Section (Tren 7 Hari & Metode Bayar) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tren Penjualan 7 Hari -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Tren Pemasukan (7 Hari Terakhir)</h4>
                        <p class="text-xs text-slate-500">Grafik omset harian penjualan toko</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700">Real-time</span>
                </div>
                <div class="h-64">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Metode Pembayaran Donut Chart -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="font-bold text-slate-800 text-base">Metode Pembayaran</h4>
                            <p class="text-xs text-slate-500">Distribusi transaksi bulan ini</p>
                        </div>
                    </div>
                    <div class="h-52 relative flex items-center justify-center">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                    <?php if (empty($paymentData)): ?>
                        <div class="col-span-2 text-center text-slate-400 py-2">Belum ada data pembayaran</div>
                    <?php else: ?>
                        <?php foreach ($paymentData as $pm): ?>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                                <span class="font-medium text-slate-600 truncate"><?= htmlspecialchars($pm['metode_pembayaran']) ?></span>
                                <span class="font-bold text-slate-800"><?= $pm['count'] ?>x</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bottom Grid: Top Selling Products & Recent Transactions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Top 5 Produk Terlaris -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Produk Terlaris</h4>
                        <p class="text-xs text-slate-500">Paling banyak terjual bulan ini</p>
                    </div>
                    <a href="produk.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">Lihat Semua &rarr;</a>
                </div>

                <div class="space-y-4">
                    <?php if (empty($topProducts)): ?>
                        <div class="text-center py-8 text-slate-400 text-sm">
                            <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300"></i>
                            <p>Belum ada penjualan bulan ini.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $maxQty = !empty($topProducts) ? max(array_column($topProducts, 'total_qty')) : 1;
                        $rank = 1;
                        foreach ($topProducts as $tp): 
                            $percent = $maxQty > 0 ? round(($tp['total_qty'] / $maxQty) * 100) : 0;
                        ?>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] <?= $rank == 1 ? 'bg-amber-100 text-amber-700' : ($rank == 2 ? 'bg-slate-200 text-slate-700' : 'bg-slate-100 text-slate-500') ?>">
                                            <?= $rank++ ?>
                                        </span>
                                        <span class="font-semibold text-slate-700 truncate"><?= htmlspecialchars($tp['nama_item']) ?></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold text-slate-800"><?= $tp['total_qty'] ?> terjual</span>
                                    </div>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= $percent ?>%"></div>
                                </div>
                                <div class="text-[11px] text-right text-slate-400">
                                    Omset: <?= rupiah($tp['total_omset']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transaksi Penjualan Terbaru -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Transaksi Penjualan Terbaru</h4>
                        <p class="text-xs text-slate-500">5 transaksi pemasukan terakhir yang tercatat</p>
                    </div>
                    <a href="transaksi.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">Riwayat Lengkap &rarr;</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase tracking-wider border-b border-slate-100 pb-3">
                                <th class="py-2.5 font-semibold">No. Invoice</th>
                                <th class="py-2.5 font-semibold">Waktu</th>
                                <th class="py-2.5 font-semibold">Pelanggan</th>
                                <th class="py-2.5 font-semibold">Metode</th>
                                <th class="py-2.5 font-semibold text-right">Total Akhir</th>
                                <th class="py-2.5 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentTrx)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-6 text-slate-400">Belum ada transaksi.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTrx as $trx): ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-3 font-semibold text-emerald-700">
                                            <a href="transaksi-detail.php?id=<?= $trx['id'] ?>" class="hover:underline">
                                                <?= htmlspecialchars($trx['nomor_transaksi']) ?>
                                            </a>
                                        </td>
                                        <td class="py-3 text-slate-500">
                                            <div><?= tanggal_indo($trx['tanggal'], false, true) ?></div>
                                            <div class="text-[10px] text-slate-400"><?= substr($trx['waktu'], 0, 5) ?> WIB</div>
                                        </td>
                                        <td class="py-3 text-slate-700 font-medium truncate max-w-[120px]">
                                            <?= htmlspecialchars($trx['nama_pelanggan']) ?>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold 
                                                <?= $trx['metode_pembayaran'] === 'Tunai' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 
                                                   ($trx['metode_pembayaran'] === 'QRIS' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200') ?>">
                                                <?= htmlspecialchars($trx['metode_pembayaran']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right font-bold text-slate-800">
                                            <?= rupiah($trx['total_akhir']) ?>
                                        </td>
                                        <td class="py-3 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <a href="transaksi-detail.php?id=<?= $trx['id'] ?>" title="Detail" class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-600 hover:bg-slate-100 transition-colors">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                                <a href="cetak-struk.php?id=<?= $trx['id'] ?>" target="_blank" title="Cetak Struk" class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-colors">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

<script>
    // Inisialisasi Grafik Tren Penjualan 7 Hari
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartDates) ?>,
            datasets: [{
                label: 'Omset Penjualan (Rp)',
                data: <?= json_encode($chartOmset) ?>,
                borderColor: '#059669',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#059669',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Omset: ' + formatRupiah(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(val) {
                            return 'Rp ' + (val / 1000).toLocaleString() + 'k';
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });

    // Inisialisasi Donut Chart Metode Pembayaran
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    const paymentLabels = <?= json_encode($paymentLabels) ?>;
    const paymentCounts = <?= json_encode($paymentCounts) ?>;

    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: paymentLabels.length ? paymentLabels : ['Belum Ada Data'],
            datasets: [{
                data: paymentCounts.length ? paymentCounts : [1],
                backgroundColor: paymentLabels.length ? ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'] : ['#f1f5f9'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            },
            cutout: '68%'
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

