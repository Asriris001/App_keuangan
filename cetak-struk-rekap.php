<?php
// cetak-struk-rekap.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

check_login();

$pengaturan = get_pengaturan($pdo);
$loggedUser = get_logged_user();

// Parameters
$filterPeriode = $_GET['periode'] ?? 'semua';
$tglMulai = $_GET['tgl_mulai'] ?? '';
$tglSelesai = $_GET['tgl_selesai'] ?? '';
$filterMetode = $_GET['metode'] ?? '';
$search = trim($_GET['search'] ?? '');
$mode = $_GET['mode'] ?? 'rekap'; // 'rekap' (struk rekap total) atau 'semua_nota' (cetak seluruh nota)

$today = date('Y-m-d');
$labelPeriode = 'Semua Riwayat Pemasukan';

if ($filterPeriode === 'hari_ini') {
    $tglMulai = $today;
    $tglSelesai = $today;
    $labelPeriode = 'Hari Ini (' . tanggal_indo($today, true) . ')';
} elseif ($filterPeriode === 'minggu_ini') {
    $tglMulai = date('Y-m-d', strtotime('-6 days'));
    $tglSelesai = $today;
    $labelPeriode = '7 Hari Terakhir (' . tanggal_indo($tglMulai) . ' s/d ' . tanggal_indo($tglSelesai) . ')';
} elseif ($filterPeriode === 'bulan_ini') {
    $tglMulai = date('Y-m-01');
    $tglSelesai = date('Y-m-t');
    $labelPeriode = 'Bulan ' . date('F Y');
} elseif (!empty($tglMulai) && !empty($tglSelesai)) {
    $labelPeriode = tanggal_indo($tglMulai) . ' s/d ' . tanggal_indo($tglSelesai);
}

// Build SQL
$whereSql = " WHERE 1=1 ";
$params = [];

if (!empty($tglMulai) && !empty($tglSelesai)) {
    $whereSql .= " AND t.tanggal BETWEEN ? AND ?";
    $params[] = $tglMulai;
    $params[] = $tglSelesai;
}

if (!empty($filterMetode)) {
    $whereSql .= " AND t.metode_pembayaran = ?";
    $params[] = $filterMetode;
}

if (!empty($search)) {
    $whereSql .= " AND (t.nomor_transaksi LIKE ? OR t.nama_pelanggan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 1. Ambil Semua Transaksi
$sqlTrx = "
    SELECT t.*, 
           COUNT(td.id) as total_item, 
           COALESCE(SUM(td.qty), 0) as total_qty
    FROM `transaksi` t
    LEFT JOIN `transaksi_detail` td ON t.id = td.transaksi_id
    $whereSql
    GROUP BY t.id
    ORDER BY t.tanggal ASC, t.waktu ASC
";
$stmt = $pdo->prepare($sqlTrx);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// 2. Ringkasan Total Semua Harga & Pemasukan
$grandPenjualanKotor = 0;
$grandDiskon = 0;
$grandTotalAkhir = 0;
$grandTotalQty = 0;
$totalTransaksi = count($transaksiList);

foreach ($transaksiList as $row) {
    $grandPenjualanKotor += (float)$row['total_penjualan'];
    $grandDiskon += (float)$row['diskon'];
    $grandTotalAkhir += (float)$row['total_akhir'];
    $grandTotalQty += (int)$row['total_qty'];
}

// 3. Ringkasan per Metode Pembayaran
$sqlMetode = "
    SELECT t.metode_pembayaran, COUNT(t.id) as jml_trx, COALESCE(SUM(t.total_akhir), 0) as total_nominal
    FROM `transaksi` t
    $whereSql
    GROUP BY t.metode_pembayaran
    ORDER BY total_nominal DESC
";
$stmtMetode = $pdo->prepare($sqlMetode);
$stmtMetode->execute($params);
$metodeList = $stmtMetode->fetchAll();

// 4. Rincian Semua Item / Menu yang Terjual
$sqlItems = "
    SELECT td.nama_item, SUM(td.qty) as qty_terjual, SUM(td.subtotal) as total_omset
    FROM `transaksi_detail` td
    JOIN `transaksi` t ON td.transaksi_id = t.id
    $whereSql
    GROUP BY td.nama_item
    ORDER BY qty_terjual DESC, total_omset DESC
";
$stmtItems = $pdo->prepare($sqlItems);
$stmtItems->execute($params);
$rekapItemList = $stmtItems->fetchAll();

// Jika mode 'semua_nota', ambil juga detail item untuk setiap nota
$detailPerTransaksi = [];
if ($mode === 'semua_nota' && !empty($transaksiList)) {
    $trxIds = array_column($transaksiList, 'id');
    $inClause = implode(',', array_map('intval', $trxIds));
    if (!empty($inClause)) {
        $stmtAllDetails = $pdo->query("SELECT * FROM `transaksi_detail` WHERE transaksi_id IN ($inClause)");
        while ($d = $stmtAllDetails->fetch()) {
            $detailPerTransaksi[$d['transaksi_id']][] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Rekap Pemasukan - <?= htmlspecialchars($pengaturan['nama_toko']) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-box {
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .page-break-receipt {
                page-break-after: always;
                border-bottom: 2px dashed #000;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
        }
        @page {
            margin: 5mm;
            size: auto;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col items-center py-6 px-4 font-mono text-slate-800">

    <!-- Action Toolbar (Hidden during print) -->
    <div class="no-print w-full max-w-[420px] mb-5 space-y-3">
        <div class="flex items-center justify-between">
            <a href="transaksi.php" class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-xs font-sans font-semibold text-slate-700 shadow-xs flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <button onclick="window.print()" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-sans font-bold shadow-md shadow-emerald-600/30 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-print text-sm"></i>
                <span>Cetak Struk Sekarang</span>
            </button>
        </div>

        <!-- Mode Toggle Tabs -->
        <div class="bg-white p-1 rounded-xl border border-slate-200 shadow-xs flex text-xs font-sans">
            <?php 
            $queryUrl = $_GET;
            $queryUrl['mode'] = 'rekap';
            $urlRekap = '?' . http_build_query($queryUrl);
            $queryUrl['mode'] = 'semua_nota';
            $urlSemuaNota = '?' . http_build_query($queryUrl);
            ?>
            <a href="<?= $urlRekap ?>" class="flex-1 py-1.5 text-center font-bold rounded-lg transition-all <?= $mode === 'rekap' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                <i class="fa-solid fa-receipt mr-1"></i> Struk Rekap Total
            </a>
            <a href="<?= $urlSemuaNota ?>" class="flex-1 py-1.5 text-center font-bold rounded-lg transition-all <?= $mode === 'semua_nota' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                <i class="fa-solid fa-copy mr-1"></i> Cetak Seluruh Lembar Nota
            </a>
        </div>
    </div>

    <?php if ($mode === 'rekap'): ?>
    <!-- ========================================== -->
    <!-- TAMPILAN 1: STRUK REKAPITULASI PEMASUKAN  -->
    <!-- ========================================== -->
    <div class="receipt-box w-full max-w-[420px] bg-white p-6 rounded-2xl shadow-lg border border-slate-200/80 text-xs">
        
        <!-- Header Toko -->
        <div class="text-center pb-3 border-b-2 border-dashed border-slate-300">
            <h2 class="text-base font-bold font-sans tracking-tight text-slate-900 uppercase">
                <?= htmlspecialchars($pengaturan['nama_toko']) ?>
            </h2>
            <?php if (!empty($pengaturan['slogan'])): ?>
                <p class="text-[10px] text-slate-500 font-sans mt-0.5"><?= htmlspecialchars($pengaturan['slogan']) ?></p>
            <?php endif; ?>
            <p class="text-[10px] text-slate-500 mt-1 leading-tight font-sans">
                <?= nl2br(htmlspecialchars($pengaturan['alamat'])) ?>
            </p>
            <p class="text-[10px] text-slate-500 font-sans">Telp: <?= htmlspecialchars($pengaturan['telepon']) ?></p>
            
            <div class="mt-2.5 pt-2 border-t border-dotted border-slate-300">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-900 block font-sans">
                    STRUK REKAPITULASI SEMUA PEMASUKAN
                </span>
                <span class="text-[10px] text-emerald-700 font-semibold font-sans">
                    <?= htmlspecialchars($labelPeriode) ?>
                </span>
            </div>
        </div>

        <!-- Meta Info Cetak -->
        <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1 text-[11px]">
            <div class="flex justify-between">
                <span class="text-slate-500">Waktu Cetak</span>
                <span><?= tanggal_indo(date('Y-m-d'), false, true) ?> <?= date('H:i') ?> WIB</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Operator / Kasir</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($loggedUser['nama_lengkap'] ?? 'Admin') ?> (<?= ucfirst($loggedUser['role'] ?? 'admin') ?>)</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Total Transaksi</span>
                <span class="font-bold text-slate-800"><?= $totalTransaksi ?> Nota</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Total Item Terjual</span>
                <span class="font-bold text-slate-800"><?= $grandTotalQty ?> Pcs</span>
            </div>
        </div>

        <!-- RINGKASAN GRAND TOTAL SEMUA HARGA (HIGHLIGHT) -->
        <div class="py-3 border-b-2 border-dashed border-slate-300 space-y-1.5 text-xs bg-slate-50/70 p-3 rounded-xl my-2">
            <div class="flex justify-between text-slate-600">
                <span>Total Penjualan Kotor</span>
                <span><?= rupiah($grandPenjualanKotor) ?></span>
            </div>
            <?php if ($grandDiskon > 0): ?>
                <div class="flex justify-between text-rose-600">
                    <span>Total Potongan Diskon</span>
                    <span>-<?= rupiah($grandDiskon) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-1.5 border-t border-dotted border-slate-300">
                <span class="uppercase">TOTAL SEMUA PEMASUKAN</span>
                <span class="text-emerald-700 text-base"><?= rupiah($grandTotalAkhir) ?></span>
            </div>
        </div>

        <!-- BREAKDOWN METODE PEMBAYARAN -->
        <div class="py-2.5 border-b border-dashed border-slate-300 text-[11px]">
            <div class="font-bold uppercase tracking-wider text-slate-700 mb-1.5 text-[10px]">
                Rincian Metode Pembayaran:
            </div>
            <?php if (empty($metodeList)): ?>
                <div class="text-slate-400">Belum ada transaksi pada periode ini.</div>
            <?php else: ?>
                <div class="space-y-1">
                    <?php foreach ($metodeList as $m): ?>
                        <div class="flex justify-between">
                            <span class="text-slate-600"><?= htmlspecialchars($m['metode_pembayaran']) ?> (<?= $m['jml_trx'] ?>x)</span>
                            <span class="font-bold text-slate-800"><?= rupiah($m['total_nominal']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- DAFTAR SEMUA NOTA INVOICE -->
        <div class="py-3 border-b border-dashed border-slate-300 text-[11px]">
            <div class="font-bold uppercase tracking-wider text-slate-700 mb-2 text-[10px] flex justify-between">
                <span>Daftar Transaksi Pemasukan</span>
                <span>Nominal</span>
            </div>

            <?php if (empty($transaksiList)): ?>
                <div class="text-center py-4 text-slate-400 text-xs">Tidak ada transaksi yang tercatat.</div>
            <?php else: ?>
                <div class="space-y-1.5">
                    <?php foreach ($transaksiList as $t): ?>
                        <div class="flex justify-between items-start border-b border-dotted border-slate-100 pb-1">
                            <div class="pr-2">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($t['nomor_transaksi']) ?></div>
                                <div class="text-[10px] text-slate-400">
                                    <?= date('d/m/y', strtotime($t['tanggal'])) ?> <?= substr($t['waktu'], 0, 5) ?> | <?= htmlspecialchars($t['nama_pelanggan']) ?> (<?= $t['metode_pembayaran'] ?>)
                                </div>
                            </div>
                            <div class="font-bold text-slate-800 whitespace-nowrap text-right">
                                <?= rupiah($t['total_akhir']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- REKAP SEMUA ITEM TERJUAL -->
        <?php if (!empty($rekapItemList)): ?>
        <div class="py-3 border-b border-dashed border-slate-300 text-[11px]">
            <div class="font-bold uppercase tracking-wider text-slate-700 mb-2 text-[10px] flex justify-between">
                <span>Rincian Produk Terjual</span>
                <span>Qty / Total</span>
            </div>
            <div class="space-y-1">
                <?php foreach ($rekapItemList as $it): ?>
                    <div class="flex justify-between text-slate-700">
                        <span class="truncate pr-2"><?= htmlspecialchars($it['nama_item']) ?></span>
                        <span class="whitespace-nowrap font-medium text-slate-800">
                            <?= $it['qty_terjual'] ?>x = <?= rupiah($it['total_omset']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tanda Tangan Pengesahan -->
        <div class="pt-4 text-center space-y-1">
            <div class="grid grid-cols-2 gap-4 text-[10px] text-slate-600 pt-2 pb-4">
                <div>
                    <p>Petugas Kasir,</p>
                    <div class="h-10"></div>
                    <p class="font-bold border-t border-dotted border-slate-300 pt-1"><?= htmlspecialchars($loggedUser['nama_lengkap'] ?? 'Kasir') ?></p>
                </div>
                <div>
                    <p>Penanggung Jawab,</p>
                    <div class="h-10"></div>
                    <p class="font-bold border-t border-dotted border-slate-300 pt-1">Pemilik Usaha</p>
                </div>
            </div>
            <p class="text-[9px] text-slate-400 font-sans border-t border-dashed border-slate-300 pt-2">
                Struk rekapitulasi ini adalah bukti sah perolehan seluruh pemasukan penjualan toko.
            </p>
        </div>

    </div>

    <?php else: ?>
    <!-- ======================================================== -->
    <!-- TAMPILAN 2: CETAK SELURUH LEMBAR NOTA SEKALIGUS (BATCH) -->
    <!-- ======================================================== -->
    <div class="w-full max-w-[360px] space-y-6">
        
        <?php if (empty($transaksiList)): ?>
            <div class="bg-white p-6 rounded-2xl text-center text-slate-400 text-xs">
                Tidak ada nota transaksi yang ditemukan pada periode ini.
            </div>
        <?php else: ?>
            <?php foreach ($transaksiList as $trx): 
                $items = $detailPerTransaksi[$trx['id']] ?? [];
            ?>
                <!-- Lembar Struk Per Nota -->
                <div class="receipt-box page-break-receipt bg-white p-5 rounded-2xl shadow-md border border-slate-200/80 text-xs">
                    
                    <!-- Header Nota -->
                    <div class="text-center pb-2.5 border-b border-dashed border-slate-300">
                        <h3 class="text-sm font-bold font-sans tracking-tight text-slate-900 uppercase">
                            <?= htmlspecialchars($pengaturan['nama_toko']) ?>
                        </h3>
                        <p class="text-[10px] text-slate-500 font-sans"><?= htmlspecialchars($pengaturan['telepon']) ?></p>
                    </div>

                    <!-- Meta Nota -->
                    <div class="py-2 border-b border-dashed border-slate-300 space-y-0.5 text-[11px]">
                        <div class="flex justify-between">
                            <span class="text-slate-500">No. Nota</span>
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($trx['nomor_transaksi']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Waktu</span>
                            <span><?= date('d/m/Y', strtotime($trx['tanggal'])) ?> <?= substr($trx['waktu'], 0, 5) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kasir</span>
                            <span class="font-medium text-slate-700"><?= htmlspecialchars($trx['kasir_nama'] ?: 'Kasir') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Pelanggan</span>
                            <span class="truncate max-w-[170px]"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span>
                        </div>
                    </div>

                    <!-- Item Belanja -->
                    <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1.5 text-[11px]">
                        <?php foreach ($items as $item): ?>
                            <div>
                                <div class="font-semibold text-slate-800 leading-tight"><?= htmlspecialchars($item['nama_item']) ?></div>
                                <div class="flex justify-between text-slate-500 text-[10px]">
                                    <span><?= $item['qty'] ?> x <?= rupiah($item['harga_satuan']) ?></span>
                                    <span class="font-medium text-slate-800"><?= rupiah($item['subtotal']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Total & Pembayaran -->
                    <div class="py-2 border-b border-dashed border-slate-300 space-y-1 text-[11px]">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal</span>
                            <span><?= rupiah($trx['total_penjualan']) ?></span>
                        </div>
                        <?php if ($trx['diskon'] > 0): ?>
                            <div class="flex justify-between text-rose-600">
                                <span>Diskon</span>
                                <span>-<?= rupiah($trx['diskon']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between font-bold text-slate-900 pt-1 border-t border-dotted border-slate-200">
                            <span>TOTAL</span>
                            <span class="text-emerald-700"><?= rupiah($trx['total_akhir']) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500 text-[10px]">
                            <span>Bayar (<?= $trx['metode_pembayaran'] ?>)</span>
                            <span><?= rupiah($trx['bayar']) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500 text-[10px]">
                            <span>Kembalian</span>
                            <span><?= rupiah($trx['kembalian']) ?></span>
                        </div>
                    </div>

                    <div class="pt-2 text-center text-[9px] text-slate-400 font-sans">
                        <?= htmlspecialchars($pengaturan['pesan_struk']) ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
    <?php endif; ?>

</body>
</html>

