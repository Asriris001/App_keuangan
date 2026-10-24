<?php
// kategori.php
$pageTitle = 'Kategori';
$headerTitle = 'Kategori Transaksi & Produk';
$headerSubtitle = 'Kelola kategori untuk pengelompokan pemasukan dan pengeluaran usaha';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Khusus Administrator
require_admin();

// Handle Hapus Kategori
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `kategori` WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Berhasil Dihapus', 'Kategori berhasil dihapus.');
    } catch (Exception $e) {
        set_flash('error', 'Gagal Menghapus', 'Kategori ini sedang digunakan oleh data produk atau pengeluaran.');
    }
    header('Location: kategori.php');
    exit;
}

// Handle Tambah / Edit Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $tipe = $_POST['tipe'] ?? 'pemasukan';

    if (empty($nama)) {
        set_flash('error', 'Nama Kosong', 'Nama kategori tidak boleh kosong.');
        header('Location: kategori.php');
        exit;
    }

    if ($act === 'tambah') {
        $stmt = $pdo->prepare("INSERT INTO `kategori` (nama, tipe) VALUES (?, ?)");
        $stmt->execute([$nama, $tipe]);
        set_flash('success', 'Kategori Ditambahkan', "Kategori '$nama' berhasil ditambahkan.");
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE `kategori` SET nama = ?, tipe = ? WHERE id = ?");
        $stmt->execute([$nama, $tipe, $id]);
        set_flash('success', 'Perubahan Disimpan', "Kategori '$nama' berhasil diperbarui.");
    }

    header('Location: kategori.php');
    exit;
}

// Ambil Kategori Pemasukan
$kategoriPemasukan = $pdo->query("
    SELECT k.*, COUNT(p.id) as total_produk 
    FROM `kategori` k 
    LEFT JOIN `produk` p ON k.id = p.kategori_id 
    WHERE k.tipe = 'pemasukan' 
    GROUP BY k.id 
    ORDER BY k.nama ASC
")->fetchAll();

// Ambil Kategori Pengeluaran
$kategoriPengeluaran = $pdo->query("
    SELECT k.*, COUNT(e.id) as total_pengeluaran 
    FROM `kategori` k 
    LEFT JOIN `pengeluaran` e ON k.id = e.kategori_id 
    WHERE k.tipe = 'pengeluaran' 
    GROUP BY k.id 
    ORDER BY k.nama ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">Kelompokkan produk pemasukan dan pos biaya usaha agar laporan Anda lebih terperinci.</p>
            <button type="button" onclick="openModalTambahKategori()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>Tambah Kategori</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Kategori Pemasukan & Produk -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">
                            <i class="fa-solid fa-arrow-down-long"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm">Kategori Pemasukan & Produk</h4>
                            <p class="text-[11px] text-slate-400">Total <?= count($kategoriPemasukan) ?> kategori</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 flex-1">
                    <div class="space-y-2">
                        <?php if (empty($kategoriPemasukan)): ?>
                            <div class="text-center py-8 text-slate-400 text-xs">Belum ada kategori pemasukan.</div>
                        <?php else: ?>
                            <?php foreach ($kategoriPemasukan as $kat): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-slate-200 bg-slate-50/40 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <div>
                                            <h5 class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($kat['nama']) ?></h5>
                                            <span class="text-[10px] text-slate-400"><?= $kat['total_produk'] ?> Produk Terkait</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" onclick='openModalEditKategori(<?= json_encode($kat) ?>)' class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button type="button" onclick="confirmDelete('kategori.php?action=delete&id=<?= $kat['id'] ?>', 'Hapus Kategori <?= addslashes($kat['nama']) ?>?', 'Pastikan tidak ada produk yang menggunakan kategori ini!')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kategori Pengeluaran & Biaya -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold">
                            <i class="fa-solid fa-arrow-up-long"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm">Kategori Pengeluaran & Biaya</h4>
                            <p class="text-[11px] text-slate-400">Total <?= count($kategoriPengeluaran) ?> kategori</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 flex-1">
                    <div class="space-y-2">
                        <?php if (empty($kategoriPengeluaran)): ?>
                            <div class="text-center py-8 text-slate-400 text-xs">Belum ada kategori pengeluaran.</div>
                        <?php else: ?>
                            <?php foreach ($kategoriPengeluaran as $kat): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-slate-200 bg-slate-50/40 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                        <div>
                                            <h5 class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($kat['nama']) ?></h5>
                                            <span class="text-[10px] text-slate-400"><?= $kat['total_pengeluaran'] ?> Biaya Terkait</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" onclick='openModalEditKategori(<?= json_encode($kat) ?>)' class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button type="button" onclick="confirmDelete('kategori.php?action=delete&id=<?= $kat['id'] ?>', 'Hapus Kategori <?= addslashes($kat['nama']) ?>?', 'Pastikan tidak ada pengeluaran yang menggunakan kategori ini!')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal Tambah / Edit Kategori -->
    <div id="modalKategori" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-sm w-full overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 id="modalKatTitle" class="font-bold text-slate-800 text-sm">Tambah Kategori Baru</h3>
                <button type="button" onclick="closeModalKategori()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="kategori.php" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formKatAction" value="tambah">
                <input type="hidden" name="id" id="formKatId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kategori <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" id="inputKatNama" required placeholder="Contoh: Makanan Berat" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tipe Kategori</label>
                    <select name="tipe" id="inputKatTipe" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                        <option value="pemasukan">Pemasukan / Produk</option>
                        <option value="pengeluaran">Pengeluaran / Beban Biaya</option>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModalKategori()" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

<script>
    const modalKat = document.getElementById('modalKategori');

    function openModalTambahKategori() {
        document.getElementById('modalKatTitle').innerText = 'Tambah Kategori Baru';
        document.getElementById('formKatAction').value = 'tambah';
        document.getElementById('formKatId').value = '';
        document.getElementById('inputKatNama').value = '';
        document.getElementById('inputKatTipe').value = 'pemasukan';
        modalKat.classList.remove('hidden');
    }

    function openModalEditKategori(k) {
        document.getElementById('modalKatTitle').innerText = 'Edit Kategori';
        document.getElementById('formKatAction').value = 'edit';
        document.getElementById('formKatId').value = k.id;
        document.getElementById('inputKatNama').value = k.nama;
        document.getElementById('inputKatTipe').value = k.tipe;
        modalKat.classList.remove('hidden');
    }

    function closeModalKategori() {
        modalKat.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

