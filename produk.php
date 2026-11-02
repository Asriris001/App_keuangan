<?php
// produk.php
$pageTitle = 'Data Produk & Stok';
$headerTitle = 'Manajemen Produk & Stok';
$headerSubtitle = 'Kelola harga jual, harga modal (HPP), kategori, dan inventaris produk';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Handle Hapus Produk (Khusus Admin)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!is_admin()) {
        set_flash('error', 'Akses Ditolak', 'Hanya Administrator yang memiliki wewenang menghapus produk.');
        header('Location: produk.php');
        exit;
    }
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `produk` WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Berhasil Dihapus', 'Produk berhasil dihapus.');
    } catch (Exception $e) {
        set_flash('error', 'Gagal Menghapus', 'Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi.');
    }
    header('Location: produk.php');
    exit;
}

// Handle Tambah / Edit Produk (POST - Khusus Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) {
        set_flash('error', 'Akses Ditolak', 'Hanya Administrator yang dapat menambah atau mengubah data produk.');
        header('Location: produk.php');
        exit;
    }
    $act = $_POST['action'] ?? '';

    $kode = trim($_POST['kode_produk'] ?? '');
    $nama = trim($_POST['nama_produk'] ?? '');
    $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $harga_modal = (float)str_replace(['.', ','], '', $_POST['harga_modal'] ?? '0');
    $harga_jual = (float)str_replace(['.', ','], '', $_POST['harga_jual'] ?? '0');
    $stok = (int)($_POST['stok'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? 'pcs');
    $status = $_POST['status'] ?? 'aktif';

    if (empty($nama) || $harga_jual <= 0) {
        set_flash('error', 'Input Tidak Lengkap', 'Nama produk dan harga jual wajib diisi dengan benar.');
        header('Location: produk.php');
        exit;
    }

    if ($act === 'tambah') {
        // Buat kode otomatis jika kosong
        if (empty($kode)) {
            $lastId = (int)$pdo->query("SELECT MAX(id) FROM `produk`")->fetchColumn();
            $kode = 'PRD-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `produk` (kode_produk, nama_produk, kategori_id, harga_modal, harga_jual, stok, satuan, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$kode, $nama, $kategori_id, $harga_modal, $harga_jual, $stok, $satuan, $status]);
            set_flash('success', 'Produk Ditambahkan', "Produk '$nama' berhasil ditambahkan.");
        } catch (Exception $e) {
            set_flash('error', 'Gagal Menambah', 'Kode produk sudah digunakan atau terjadi kesalahan.');
        }
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("
                UPDATE `produk` 
                SET kode_produk = ?, nama_produk = ?, kategori_id = ?, harga_modal = ?, harga_jual = ?, stok = ?, satuan = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$kode, $nama, $kategori_id, $harga_modal, $harga_jual, $stok, $satuan, $status, $id]);
            set_flash('success', 'Perubahan Disimpan', "Data produk '$nama' berhasil diperbarui.");
        } catch (Exception $e) {
            set_flash('error', 'Gagal Mengupdate', $e->getMessage());
        }
    }

    header('Location: produk.php');
    exit;
}

// Filter parameters
$search = trim($_GET['search'] ?? '');
$filterKat = $_GET['kategori'] ?? '';

$sql = "
    SELECT p.*, k.nama as nama_kategori 
    FROM `produk` p 
    LEFT JOIN `kategori` k ON p.kategori_id = k.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nama_produk LIKE ? OR p.kode_produk LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterKat)) {
    $sql .= " AND p.kategori_id = ?";
    $params[] = $filterKat;
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produkList = $stmt->fetchAll();

// Categories for dropdown
$kategoriList = $pdo->query("SELECT * FROM `kategori` WHERE tipe = 'pemasukan' ORDER BY nama ASC")->fetchAll();

// Statistics
$totalProduk = count($produkList);
$totalStok = 0;
$totalNilaiAset = 0;
$stokMenipis = 0;

foreach ($produkList as $p) {
    $totalStok += $p['stok'];
    $totalNilaiAset += ($p['stok'] * $p['harga_modal']);
    if ($p['stok'] <= 10) {
        $stokMenipis++;
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 <?= is_admin() ? 'lg:grid-cols-4' : 'lg:grid-cols-3' ?> gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Produk</span>
                    <h3 class="text-xl font-extrabold text-slate-800 mt-1"><?= $totalProduk ?> Item</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Stok Fisik</span>
                    <h3 class="text-xl font-extrabold text-slate-800 mt-1"><?= number_format($totalStok) ?> Unit</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
            </div>

            <?php if (is_admin()): ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Nilai Aset Modal</span>
                    <h3 class="text-xl font-extrabold text-purple-700 mt-1"><?= rupiah($totalNilaiAset) ?></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Stok Menipis (&le; 10)</span>
                    <h3 class="text-xl font-extrabold <?= $stokMenipis > 0 ? 'text-amber-600' : 'text-slate-800' ?> mt-1"><?= $stokMenipis ?> Produk</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>

        <!-- Filter & Header Bar -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <form method="GET" action="produk.php" class="flex flex-wrap items-center gap-2 flex-1">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama atau kode produk..." 
                        class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                </div>

                <select name="kategori" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:border-emerald-500">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoriList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filterKat == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold">
                    Filter
                </button>

                <?php if (!empty($search) || !empty($filterKat)): ?>
                    <a href="produk.php" class="text-xs text-rose-500 hover:underline px-2">Reset</a>
                <?php endif; ?>
            </form>

            <?php if (is_admin()): ?>
            <button type="button" onclick="openModalTambah()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>Tambah Produk Baru</span>
            </button>
            <?php endif; ?>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200/80">
                            <th class="py-3 px-4">Kode & Nama Produk</th>
                            <th class="py-3 px-4">Kategori</th>
                            <?php if (is_admin()): ?>
                                <th class="py-3 px-4 text-right">Harga Modal (HPP)</th>
                            <?php endif; ?>
                            <th class="py-3 px-4 text-right">Harga Jual</th>
                            <?php if (is_admin()): ?>
                                <th class="py-3 px-4 text-center">Margin Laba</th>
                            <?php endif; ?>
                            <th class="py-3 px-4 text-center">Stok</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <?php if (is_admin()): ?>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($produkList)): ?>
                            <tr>
                                <td colspan="<?= is_admin() ? '8' : '5' ?>" class="text-center py-12 text-slate-400">
                                    <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300"></i>
                                    <p class="font-medium text-slate-600">Belum ada produk yang cocok</p>
                                    <?php if (is_admin()): ?>
                                        <p class="text-[11px] text-slate-400 mt-0.5">Klik tombol Tambah Produk Baru di atas.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produkList as $p): 
                                $profit = $p['harga_jual'] - $p['harga_modal'];
                                $marginPct = $p['harga_jual'] > 0 ? round(($profit / $p['harga_jual']) * 100, 1) : 0;
                            ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                        <div class="font-mono text-[10px] text-slate-400"><?= htmlspecialchars($p['kode_produk']) ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-medium text-[11px]">
                                            <?= htmlspecialchars($p['nama_kategori'] ?: 'Tanpa Kategori') ?>
                                        </span>
                                    </td>
                                    <?php if (is_admin()): ?>
                                        <td class="py-3 px-4 text-right text-slate-500 font-medium">
                                            <?= rupiah($p['harga_modal']) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="py-3 px-4 text-right font-bold text-emerald-700">
                                        <?= rupiah($p['harga_jual']) ?>
                                    </td>
                                    <?php if (is_admin()): ?>
                                        <td class="py-3 px-4 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $profit >= 0 ? 'bg-purple-50 text-purple-700' : 'bg-rose-50 text-rose-700' ?>">
                                                +<?= rupiah($profit) ?> (<?= $marginPct ?>%)
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-bold <?= $p['stok'] <= 10 ? 'text-amber-600 bg-amber-50' : 'text-slate-800 bg-slate-100' ?> px-2.5 py-0.5 rounded-lg text-xs">
                                            <?= $p['stok'] ?> <?= htmlspecialchars($p['satuan']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="w-2 h-2 rounded-full inline-block mr-1 <?= $p['status'] === 'aktif' ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                                        <span class="capitalize text-slate-600"><?= $p['status'] ?></span>
                                    </td>
                                    <?php if (is_admin()): ?>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button" onclick='openModalEdit(<?= json_encode($p) ?>)' title="Edit Produk" class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-600 hover:bg-slate-100 transition-colors">
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" onclick="confirmDelete('produk.php?action=delete&id=<?= $p['id'] ?>', 'Hapus Produk <?= addslashes($p['nama_produk']) ?>?', 'Data produk ini akan dihapus dari sistem.')" title="Hapus Produk" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php if (is_admin()): ?>
    <!-- Modal Form Tambah / Edit Produk -->
    <div id="modalProduk" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 id="modalTitle" class="font-bold text-slate-800 text-base">Tambah Produk Baru</h3>
                <button type="button" onclick="closeModalProduk()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="produk.php" class="p-6 space-y-4">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId" value="">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kode Produk (SKU)</label>
                        <input type="text" name="kode_produk" id="inputKode" placeholder="Auto / Contoh: PRD-001" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori</label>
                        <select name="kategori_id" id="inputKategori" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($kategoriList as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Produk <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_produk" id="inputNama" required placeholder="Contoh: Kopi Susu Aren 250ml" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Harga Modal (HPP) Rp</label>
                        <input type="number" name="harga_modal" id="inputModal" placeholder="0" min="0" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Harga Jual Rp <span class="text-rose-500">*</span></label>
                        <input type="number" name="harga_jual" id="inputJual" required placeholder="0" min="0" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jumlah Stok</label>
                        <input type="number" name="stok" id="inputStok" value="0" min="0" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Satuan</label>
                        <input type="text" name="satuan" id="inputSatuan" value="pcs" placeholder="pcs/cup/kg" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                        <select name="status" id="inputStatus" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModalProduk()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                        Simpan Produk
                    </button>
                </div>

            </form>

        </div>
    </div>

<script>
    const modalProduk = document.getElementById('modalProduk');

    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Produk Baru';
        document.getElementById('formAction').value = 'tambah';
        document.getElementById('formId').value = '';
        document.getElementById('inputKode').value = '';
        document.getElementById('inputNama').value = '';
        document.getElementById('inputKategori').value = '';
        document.getElementById('inputModal').value = '';
        document.getElementById('inputJual').value = '';
        document.getElementById('inputStok').value = '0';
        document.getElementById('inputSatuan').value = 'pcs';
        document.getElementById('inputStatus').value = 'aktif';

        modalProduk.classList.remove('hidden');
    }

    function openModalEdit(p) {
        document.getElementById('modalTitle').innerText = 'Edit Produk: ' + p.nama_produk;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = p.id;
        document.getElementById('inputKode').value = p.kode_produk;
        document.getElementById('inputNama').value = p.nama_produk;
        document.getElementById('inputKategori').value = p.kategori_id || '';
        document.getElementById('inputModal').value = Math.round(p.harga_modal);
        document.getElementById('inputJual').value = Math.round(p.harga_jual);
        document.getElementById('inputStok').value = p.stok;
        document.getElementById('inputSatuan').value = p.satuan;
        document.getElementById('inputStatus').value = p.status;

        modalProduk.classList.remove('hidden');
    }

    function closeModalProduk() {
        modalProduk.classList.add('hidden');
    }
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

