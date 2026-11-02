<?php
// pengeluaran.php
$pageTitle = 'Biaya & Pengeluaran';
$headerTitle = 'Pencatatan Biaya & Pengeluaran';
$headerSubtitle = 'Catat beban operasional, tagihan, dan pengeluaran usaha';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Khusus Administrator
require_admin();

// Handle Hapus Pengeluaran
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM `pengeluaran` WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('success', 'Berhasil Dihapus', 'Data pengeluaran berhasil dihapus.');
    header('Location: pengeluaran.php');
    exit;
}

// Handle Tambah Pengeluaran (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor = trim($_POST['nomor_pengeluaran'] ?? generate_nomor_pengeluaran($pdo));
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $nama = trim($_POST['nama_pengeluaran'] ?? '');
    $nominal = (float)str_replace(['.', ','], '', $_POST['nominal'] ?? '0');
    $metode = $_POST['metode_pembayaran'] ?? 'Tunai';
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($nama) || $nominal <= 0) {
        set_flash('error', 'Data Belum Lengkap', 'Nama pengeluaran dan nominal harus diisi!');
        header('Location: pengeluaran.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `pengeluaran` (nomor_pengeluaran, tanggal, kategori_id, nama_pengeluaran, nominal, metode_pembayaran, catatan) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nomor, $tanggal, $kategori_id, $nama, $nominal, $metode, $catatan]);
        set_flash('success', 'Pengeluaran Dicatat', "Pengeluaran '$nama' senilai " . rupiah($nominal) . " berhasil disimpan.");
    } catch (Exception $e) {
        set_flash('error', 'Gagal Menyimpan', $e->getMessage());
    }

    header('Location: pengeluaran.php');
    exit;
}

// Filters
$tglMulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-t');
$filterKat = $_GET['kategori'] ?? '';

$sql = "
    SELECT e.*, k.nama as nama_kategori 
    FROM `pengeluaran` e 
    LEFT JOIN `kategori` k ON e.kategori_id = k.id 
    WHERE 1=1
";
$params = [];

if (!empty($tglMulai) && !empty($tglSelesai)) {
    $sql .= " AND e.tanggal BETWEEN ? AND ?";
    $params[] = $tglMulai;
    $params[] = $tglSelesai;
}

if (!empty($filterKat)) {
    $sql .= " AND e.kategori_id = ?";
    $params[] = $filterKat;
}

$sql .= " ORDER BY e.tanggal DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengeluaranList = $stmt->fetchAll();

// Kategori options
$kategoriList = $pdo->query("SELECT * FROM `kategori` WHERE tipe = 'pengeluaran' ORDER BY nama ASC")->fetchAll();

// Metrics
$totalNominal = 0;
foreach ($pengeluaranList as $e) {
    $totalNominal += (float)$e['nominal'];
}
$autoNomor = generate_nomor_pengeluaran($pdo);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <!-- Top Stat & CTA -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Pengeluaran Filter</span>
                    <h3 class="text-xl font-extrabold text-rose-600 mt-1"><?= rupiah($totalNominal) ?></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Jumlah Transaksi Biaya</span>
                    <h3 class="text-xl font-extrabold text-slate-800 mt-1"><?= count($pengeluaranList) ?> Catatan</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-end">
                <button type="button" onclick="openModalTambahPengeluaran()" class="w-full sm:w-auto px-5 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-600/20 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Catat Biaya Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <form method="GET" action="pengeluaran.php" class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tglMulai) ?>" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Sampai Tanggal</label>
                    <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tglSelesai) ?>" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Kategori Biaya</label>
                    <select name="kategori" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filterKat == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold">
                        Filter
                    </button>
                    <a href="pengeluaran.php" class="px-3 py-2 text-xs text-slate-500 hover:underline">Reset</a>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200/80">
                            <th class="py-3 px-4">No. Bukti</th>
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Nama Pengeluaran / Keperluan</th>
                            <th class="py-3 px-4">Kategori</th>
                            <th class="py-3 px-4">Metode Bayar</th>
                            <th class="py-3 px-4 text-right">Nominal</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pengeluaranList)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-12 text-slate-400">
                                    <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                                    <p class="font-medium text-slate-600">Belum ada data pengeluaran</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pengeluaranList as $row): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-700">
                                        <?= htmlspecialchars($row['nomor_pengeluaran']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?= tanggal_indo($row['tanggal'], false, true) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($row['nama_pengeluaran']) ?></div>
                                        <?php if (!empty($row['catatan'])): ?>
                                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($row['catatan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 font-medium text-[11px]">
                                            <?= htmlspecialchars($row['nama_kategori'] ?: 'Umum') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?= htmlspecialchars($row['metode_pembayaran']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-rose-600 text-sm">
                                        -<?= rupiah($row['nominal']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" onclick="confirmDelete('pengeluaran.php?action=delete&id=<?= $row['id'] ?>', 'Hapus Pengeluaran <?= addslashes($row['nama_pengeluaran']) ?>?', 'Data pengeluaran akan dihapus secara permanen.')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Catat Pengeluaran -->
    <div id="modalPengeluaran" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 text-sm">Catat Pengeluaran / Beban Toko</h3>
                <button type="button" onclick="closeModalPengeluaran()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="pengeluaran.php" class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">No. Bukti</label>
                        <input type="text" name="nomor_pengeluaran" value="<?= $autoNomor ?>" readonly 
                            class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori Biaya</label>
                    <select name="kategori_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pengeluaran / Kebutuhan <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_pengeluaran" required placeholder="Contoh: Pembelian Plastik & Kantong" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nominal (Rp) <span class="text-rose-500">*</span></label>
                        <input type="number" name="nominal" required min="1" placeholder="0" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-rose-600 focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Metode Bayar</label>
                        <select name="metode_pembayaran" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                    <textarea name="catatan" rows="2" placeholder="Keterangan tambahan..." 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModalPengeluaran()" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-rose-600 text-white rounded-xl text-xs font-bold shadow-sm">Simpan Biaya</button>
                </div>
            </form>
        </div>
    </div>

<script>
    const modalExp = document.getElementById('modalPengeluaran');
    function openModalTambahPengeluaran() {
        modalExp.classList.remove('hidden');
    }
    function closeModalPengeluaran() {
        modalExp.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

