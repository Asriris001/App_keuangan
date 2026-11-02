<?php
// cetak-struk.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID Transaksi tidak valid!");
}

$pengaturan = get_pengaturan($pdo);

// Ambil data transaksi
$stmt = $pdo->prepare("SELECT * FROM `transaksi` WHERE id = ?");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    die("Transaksi tidak ditemukan!");
}

// Ambil detail item transaksi
$stmtItems = $pdo->prepare("SELECT * FROM `transaksi_detail` WHERE transaksi_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$autoPrint = isset($_GET['auto']) && $_GET['auto'] === '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Penjualan - <?= htmlspecialchars($trx['nomor_transaksi']) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
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
        }
        @page {
            margin: 0;
            size: auto;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col items-center py-6 px-4 font-mono text-slate-800">

    <!-- Action Toolbar (Hidden during print) -->
    <div class="no-print w-full max-w-[340px] mb-4 flex items-center justify-between gap-2">
        <a href="kasir.php" class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-xs font-sans font-semibold text-slate-700 shadow-xs flex items-center gap-1.5 transition-all">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kasir</span>
        </a>
        <div class="flex items-center gap-2">
            <a href="transaksi.php" class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-xs font-sans font-semibold text-slate-700 shadow-xs flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-list"></i>
                <span>Riwayat</span>
            </a>
            <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-sans font-bold shadow-md shadow-emerald-600/30 flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-print"></i>
                <span>Cetak Struk</span>
            </button>
        </div>
    </div>

    <!-- Thermal Receipt Card -->
    <div class="receipt-box w-full max-w-[340px] bg-white p-5 rounded-2xl shadow-lg border border-slate-200/80 text-xs">
        
        <!-- Header Toko -->
        <div class="text-center pb-3 border-b border-dashed border-slate-300">
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
        </div>

        <!-- Meta Info -->
        <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1 text-[11px]">
            <div class="flex justify-between">
                <span class="text-slate-500">No. Nota</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($trx['nomor_transaksi']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Tanggal</span>
                <span><?= tanggal_indo($trx['tanggal'], false, true) ?> <?= substr($trx['waktu'], 0, 5) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Kasir</span>
                <span class="font-medium text-slate-700 truncate max-w-[170px]"><?= htmlspecialchars($trx['kasir_nama'] ?: 'Kasir') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Pelanggan</span>
                <span class="truncate max-w-[170px]"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Metode Bayar</span>
                <span class="font-semibold uppercase"><?= htmlspecialchars($trx['metode_pembayaran']) ?></span>
            </div>
        </div>

        <!-- Itemized List -->
        <div class="py-3 border-b border-dashed border-slate-300 space-y-2">
            <?php foreach ($items as $item): ?>
                <div>
                    <div class="font-semibold text-slate-800 break-words leading-tight">
                        <?= htmlspecialchars($item['nama_item']) ?>
                    </div>
                    <div class="flex justify-between text-slate-500 text-[11px] mt-0.5">
                        <span><?= $item['qty'] ?> x <?= rupiah($item['harga_satuan']) ?></span>
                        <span class="font-medium text-slate-800"><?= rupiah($item['subtotal']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Totals & Payment -->
        <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1.5 text-[11px]">
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
            <div class="flex justify-between text-sm font-bold text-slate-900 pt-1 border-t border-dotted border-slate-200">
                <span>TOTAL AKHIR</span>
                <span class="text-emerald-700"><?= rupiah($trx['total_akhir']) ?></span>
            </div>
            <div class="flex justify-between text-slate-600 pt-1">
                <span>Bayar (<?= htmlspecialchars($trx['metode_pembayaran']) ?>)</span>
                <span><?= rupiah($trx['bayar']) ?></span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Kembalian</span>
                <span class="font-semibold text-slate-800"><?= rupiah($trx['kembalian']) ?></span>
            </div>
        </div>

        <!-- Catatan jika ada -->
        <?php if (!empty($trx['catatan'])): ?>
            <div class="py-2 border-b border-dashed border-slate-300 text-[10px] text-slate-500">
                <span class="font-semibold">Catatan:</span> <?= htmlspecialchars($trx['catatan']) ?>
            </div>
        <?php endif; ?>

        <!-- Footer Struk -->
        <div class="pt-4 text-center space-y-1">
            <p class="text-[10px] text-slate-500 font-sans leading-tight">
                <?= nl2br(htmlspecialchars($pengaturan['pesan_struk'])) ?>
            </p>
            <p class="text-[9px] text-slate-400 font-sans pt-2">
                Simpan struk ini sebagai bukti pembayaran yang sah.
            </p>
        </div>

    </div>

    <?php if ($autoPrint): ?>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
    <?php endif; ?>

</body>
</html>

