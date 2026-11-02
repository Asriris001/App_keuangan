<?php
// transaksi-detail.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: transaksi.php');
    exit;
}

// Ambil Transaksi
$stmt = $pdo->prepare("SELECT * FROM `transaksi` WHERE id = ?");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    set_flash('error', 'Tidak Ditemukan', 'Data transaksi tidak ditemukan.');
    header('Location: transaksi.php');
    exit;
}

// Ambil Detail Items
$stmtItems = $pdo->prepare("
    SELECT td.*, p.kode_produk, p.satuan 
    FROM `transaksi_detail` td 
    LEFT JOIN `produk` p ON td.produk_id = p.id 
    WHERE td.transaksi_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Detail Transaksi ' . $trx['nomor_transaksi'];
$headerTitle = 'Detail Transaksi';
$headerSubtitle = 'Rincian transaksi invoice ' . $trx['nomor_transaksi'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Hitung total laba kotor transaksi ini
$labaTransaksi = $trx['total_akhir'] - $trx['total_modal'];
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6 max-w-5xl">

        <!-- Back & Action Bar -->
        <div class="flex items-center justify-between">
            <a href="transaksi.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali ke Riwayat</span>
            </a>
            <div class="flex items-center gap-2">
                <a href="cetak-struk.php?id=<?= $trx['id'] ?>" target="_blank" class="px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-all shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-emerald-600"></i>
                    <span>Cetak Struk</span>
                </a>
                <?php if (is_admin()): ?>
                <button type="button" onclick="confirmDelete('transaksi.php?action=delete&id=<?= $trx['id'] ?>', 'Hapus Transaksi <?= $trx['nomor_transaksi'] ?>?', 'Stok produk akan otomatis dikembalikan ke inventaris!')" class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                    <i class="fa-regular fa-trash-can"></i>
                    <span>Hapus</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            
            <!-- Invoice Header Banner -->
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bukti Transaksi Pemasukan</span>
                    <h3 class="text-xl font-extrabold text-slate-800 font-mono mt-0.5"><?= htmlspecialchars($trx['nomor_transaksi']) ?></h3>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        <i class="fa-solid fa-circle-check mr-1"></i> <?= htmlspecialchars($trx['status_pembayaran']) ?>
                    </span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-200/70 text-slate-700">
                        <?= htmlspecialchars($trx['metode_pembayaran']) ?>
                    </span>
                </div>
            </div>

            <!-- Meta Data Grid -->
            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs border-b border-slate-100">
                <div>
                    <span class="text-slate-400 block font-semibold mb-1">Tanggal Transaksi</span>
                    <span class="font-bold text-slate-800"><?= tanggal_indo($trx['tanggal'], true) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-semibold mb-1">Waktu Transaksi</span>
                    <span class="font-bold text-slate-800"><?= substr($trx['waktu'], 0, 5) ?> WIB</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-semibold mb-1">Nama Pelanggan</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-semibold mb-1"><?= is_admin() ? 'Perkiraan Laba' : 'Petugas Kasir' ?></span>
                    <?php if (is_admin()): ?>
                        <span class="font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md"><?= rupiah($labaTransaksi) ?></span>
                    <?php else: ?>
                        <span class="font-bold text-slate-700"><?= htmlspecialchars($trx['kasir_nama'] ?: 'Kasir') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items Table -->
            <div class="p-6">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Daftar Produk / Layanan</h4>
                
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100">
                                <th class="py-2.5 px-4">#</th>
                                <th class="py-2.5 px-4">Nama Item</th>
                                <th class="py-2.5 px-4 text-right">Harga Satuan</th>
                                <th class="py-2.5 px-4 text-center">Qty</th>
                                <th class="py-2.5 px-4 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php 
                            $no = 1;
                            foreach ($items as $it): 
                            ?>
                                <tr>
                                    <td class="py-3 px-4 text-slate-400 font-mono"><?= $no++ ?></td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($it['nama_item']) ?></div>
                                        <?php if (!empty($it['kode_produk'])): ?>
                                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($it['kode_produk']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-600"><?= rupiah($it['harga_satuan']) ?></td>
                                    <td class="py-3 px-4 text-center font-bold text-slate-800"><?= $it['qty'] ?></td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900"><?= rupiah($it['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Breakdown Section -->
                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-6">
                    <div class="sm:w-1/2 text-xs text-slate-500 space-y-2">
                        <?php if (!empty($trx['catatan'])): ?>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                                <span class="font-bold text-slate-700 block mb-1">Catatan Tambahan:</span>
                                <?= nl2br(htmlspecialchars($trx['catatan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sm:w-1/2 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Total Penjualan (Kotor)</span>
                            <span class="font-medium"><?= rupiah($trx['total_penjualan']) ?></span>
                        </div>
                        <?php if ($trx['diskon'] > 0): ?>
                            <div class="flex justify-between text-rose-600">
                                <span>Potongan / Diskon</span>
                                <span class="font-medium">-<?= rupiah($trx['diskon']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm font-bold text-slate-900 pt-2 border-t border-slate-100">
                            <span>Total Akhir</span>
                            <span class="text-emerald-700 text-base font-extrabold"><?= rupiah($trx['total_akhir']) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-600 pt-1">
                            <span>Uang Diterima</span>
                            <span><?= rupiah($trx['bayar']) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Kembalian</span>
                            <span class="font-bold text-slate-800"><?= rupiah($trx['kembalian']) ?></span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

