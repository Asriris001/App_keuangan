<?php
// pengaturan.php
$pageTitle = 'Pengaturan Toko';
$headerTitle = 'Pengaturan Profil Usaha & Toko';
$headerSubtitle = 'Atur informasi toko, kontak, dan format nota struk belanja';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Khusus Administrator
require_admin();

// Handle Save Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_toko'] ?? '');
    $slogan = trim($_POST['slogan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pesan_struk = trim($_POST['pesan_struk'] ?? '');

    if (empty($nama)) {
        set_flash('error', 'Nama Toko Wajib', 'Nama toko tidak boleh dikosongkan.');
        header('Location: pengaturan.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE `pengaturan` 
            SET nama_toko = ?, slogan = ?, alamat = ?, telepon = ?, email = ?, pesan_struk = ? 
            WHERE id = (SELECT id FROM (SELECT id FROM `pengaturan` ORDER BY id ASC LIMIT 1) as t)
        ");
        $stmt->execute([$nama, $slogan, $alamat, $telepon, $email, $pesan_struk]);
        set_flash('success', 'Pengaturan Disimpan', 'Informasi toko dan pesan nota struk berhasil diperbarui.');
    } catch (Exception $e) {
        set_flash('error', 'Gagal Menyimpan', $e->getMessage());
    }

    header('Location: pengaturan.php');
    exit;
}

$pengaturan = get_pengaturan($pdo);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Form Pengaturan (2 Kolom) -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Informasi Profil Toko</h3>
                        <p class="text-[11px] text-slate-400">Data ini akan tampil di bagian header aplikasi dan nota kasir pelanggan.</p>
                    </div>
                </div>

                <form method="POST" action="pengaturan.php" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Toko / Usaha <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_toko" id="inputNamaToko" value="<?= htmlspecialchars($pengaturan['nama_toko']) ?>" required 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none"
                                oninput="updateReceiptPreview()">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Slogan / Keterangan Singkat</label>
                            <input type="text" name="slogan" id="inputSlogan" value="<?= htmlspecialchars($pengaturan['slogan'] ?? '') ?>" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none"
                                oninput="updateReceiptPreview()">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="telepon" id="inputTelepon" value="<?= htmlspecialchars($pengaturan['telepon']) ?>" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none"
                                oninput="updateReceiptPreview()">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Email Toko</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($pengaturan['email'] ?? '') ?>" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap Toko</label>
                        <textarea name="alamat" id="inputAlamat" rows="2" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none"
                            oninput="updateReceiptPreview()"><?= htmlspecialchars($pengaturan['alamat']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pesan Footer Pada Struk Kasir</label>
                        <textarea name="pesan_struk" id="inputPesanStruk" rows="2" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none"
                            oninput="updateReceiptPreview()"><?= htmlspecialchars($pengaturan['pesan_struk']) ?></textarea>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 flex items-center gap-2 transition-all">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview Struk Kasir (1 Kolom) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Preview Struk Kasir</h4>
                    <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full">Live Preview</span>
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-dashed border-slate-300 font-mono text-[11px] space-y-3">
                    <div class="text-center pb-2 border-b border-dashed border-slate-300">
                        <div id="prevNamaToko" class="font-bold text-slate-900 uppercase text-xs"><?= htmlspecialchars($pengaturan['nama_toko']) ?></div>
                        <div id="prevSlogan" class="text-[10px] text-slate-500"><?= htmlspecialchars($pengaturan['slogan'] ?? '') ?></div>
                        <div id="prevAlamat" class="text-[10px] text-slate-500 mt-1"><?= htmlspecialchars($pengaturan['alamat']) ?></div>
                        <div id="prevTelepon" class="text-[10px] text-slate-500">Telp: <?= htmlspecialchars($pengaturan['telepon']) ?></div>
                    </div>

                    <div class="text-[10px] space-y-0.5 text-slate-500 border-b border-dashed border-slate-300 pb-2">
                        <div class="flex justify-between"><span>No. Nota</span><span class="font-bold text-slate-800">TRX-DEMO-001</span></div>
                        <div class="flex justify-between"><span>Tanggal</span><span><?= date('d M Y H:i') ?></span></div>
                        <div class="flex justify-between"><span>Kasir</span><span>Admin Toko</span></div>
                    </div>

                    <div class="space-y-1 text-[11px] border-b border-dashed border-slate-300 pb-2">
                        <div class="flex justify-between"><span>2x Produk Contoh</span><span>Rp 36.000</span></div>
                        <div class="flex justify-between"><span>1x Minuman Segar</span><span>Rp 10.000</span></div>
                    </div>

                    <div class="space-y-0.5 text-[11px]">
                        <div class="flex justify-between font-bold text-slate-900 pt-1"><span>TOTAL</span><span>Rp 46.000</span></div>
                        <div class="flex justify-between text-slate-500 text-[10px]"><span>TUNAI</span><span>Rp 50.000</span></div>
                        <div class="flex justify-between text-slate-500 text-[10px]"><span>KEMBALI</span><span>Rp 4.000</span></div>
                    </div>

                    <div class="pt-2 text-center text-[10px] text-slate-500 border-t border-dashed border-slate-300">
                        <p id="prevPesanStruk"><?= htmlspecialchars($pengaturan['pesan_struk']) ?></p>
                    </div>
                </div>
            </div>

        </div>

    </main>

<script>
    function updateReceiptPreview() {
        document.getElementById('prevNamaToko').innerText = document.getElementById('inputNamaToko').value || 'Nama Toko';
        document.getElementById('prevSlogan').innerText = document.getElementById('inputSlogan').value || '';
        document.getElementById('prevAlamat').innerText = document.getElementById('inputAlamat').value || '';
        document.getElementById('prevTelepon').innerText = 'Telp: ' + (document.getElementById('inputTelepon').value || '-');
        document.getElementById('prevPesanStruk').innerText = document.getElementById('inputPesanStruk').value || '';
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

