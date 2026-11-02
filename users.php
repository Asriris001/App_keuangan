<?php
// users.php
$pageTitle = 'Kelola Pengguna';
$headerTitle = 'Manajemen Akun & Pengguna';
$headerSubtitle = 'Kelola akun kasir dan administrator untuk pembagian hak akses sistem';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Pastikan hanya role admin yang boleh mengakses
require_admin();

$loggedUser = get_logged_user();

// Handle Hapus Pengguna
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id === (int)$loggedUser['id']) {
        set_flash('error', 'Tidak Dapat Dihapus', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM `users` WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Berhasil Dihapus', 'Akun pengguna berhasil dihapus.');
    }
    header('Location: users.php');
    exit;
}

// Handle Tambah / Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'kasir';
    $status = $_POST['status'] ?? 'aktif';

    if (empty($nama) || empty($username)) {
        set_flash('error', 'Data Belum Lengkap', 'Nama lengkap dan username wajib diisi!');
        header('Location: users.php');
        exit;
    }

    if ($act === 'tambah') {
        if (empty($password)) {
            set_flash('error', 'Password Wajib', 'Password wajib diisi untuk pengguna baru.');
            header('Location: users.php');
            exit;
        }

        // Cek username unik
        $cek = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetchColumn() > 0) {
            set_flash('error', 'Username Sudah Dipakai', "Username '$username' sudah terdaftar. Gunakan username lain.");
            header('Location: users.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (nama_lengkap, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $username, $hash, $role, $status]);
        set_flash('success', 'Pengguna Ditambahkan', "Akun $nama ($role) berhasil didaftarkan.");

    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];

        // Cek username bentrok
        $cek = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE username = ? AND id != ?");
        $cek->execute([$username, $id]);
        if ($cek->fetchColumn() > 0) {
            set_flash('error', 'Username Sudah Dipakai', "Username '$username' sudah dipakai akun lain.");
            header('Location: users.php');
            exit;
        }

        if (!empty($password)) {
            // Update dengan password baru
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE `users` SET nama_lengkap = ?, username = ?, password = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama, $username, $hash, $role, $status, $id]);
        } else {
            // Update tanpa mengganti password lama
            $stmt = $pdo->prepare("UPDATE `users` SET nama_lengkap = ?, username = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama, $username, $role, $status, $id]);
        }

        set_flash('success', 'Perubahan Disimpan', "Data pengguna $nama berhasil diperbarui.");
    }

    header('Location: users.php');
    exit;
}

// Ambil semua pengguna
$stmt = $pdo->query("SELECT * FROM `users` ORDER BY role ASC, nama_lengkap ASC");
$userList = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-4 md:p-8 space-y-6">

        <!-- Top Header & Role Explanation -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Hak Akses Sistem</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-xl">
                        <strong>Admin:</strong> Memiliki akses penuh ke seluruh laporan laba rugi, master data harga modal, pengeluaran, dan pengaturan toko.<br>
                        <strong>Kasir:</strong> Dikhususkan untuk melayani transaksi penjualan di kasir, mencetak nota, dan mengecek ketersediaan stok produk tanpa melihat laba/HPP.
                    </p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-end">
                <button type="button" onclick="openModalTambahUser()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Tambah Pengguna</span>
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200/80">
                            <th class="py-3 px-4">Nama Lengkap</th>
                            <th class="py-3 px-4">Username</th>
                            <th class="py-3 px-4 text-center">Hak Akses (Role)</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Terdaftar</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($userList as $u): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full <?= $u['role'] === 'admin' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?> flex items-center justify-center font-bold text-xs">
                                            <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                        <?php if ((int)$u['id'] === (int)$loggedUser['id']): ?>
                                            <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.2 rounded font-semibold">(Anda)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-slate-600">
                                    @<?= htmlspecialchars($u['username']) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-shield-halved mr-1"></i> Administrator
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                            <i class="fa-solid fa-cash-register mr-1"></i> Kasir
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="w-2 h-2 rounded-full inline-block mr-1 <?= $u['status'] === 'aktif' ? 'bg-emerald-500' : 'bg-rose-400' ?>"></span>
                                    <span class="capitalize font-medium text-slate-700"><?= $u['status'] ?></span>
                                </td>
                                <td class="py-3 px-4 text-slate-500">
                                    <?= tanggal_indo(substr($u['created_at'], 0, 10), false, true) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" onclick='openModalEditUser(<?= json_encode($u) ?>)' title="Edit Pengguna" class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg transition-colors">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </button>
                                        <?php if ((int)$u['id'] !== (int)$loggedUser['id']): ?>
                                            <button type="button" onclick="confirmDelete('users.php?action=delete&id=<?= $u['id'] ?>', 'Hapus Akun <?= addslashes($u['nama_lengkap']) ?>?', 'Pengguna ini tidak akan dapat login lagi ke sistem.')" title="Hapus Pengguna" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Form Tambah / Edit Pengguna -->
    <div id="modalUser" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 id="modalUserTitle" class="font-bold text-slate-800 text-sm">Tambah Pengguna Baru</h3>
                <button type="button" onclick="closeModalUser()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="users.php" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formUserAction" value="tambah">
                <input type="hidden" name="id" id="formUserId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_lengkap" id="inputUserNama" required placeholder="Contoh: Rian Anggara" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" id="inputUserUsername" required placeholder="rian99" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Peran (Role) <span class="text-rose-500">*</span></label>
                        <select name="role" id="inputUserRole" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                            <option value="kasir">Kasir (POS Saja)</option>
                            <option value="admin">Administrator (Full)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Password <span id="passRequiredStar" class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="password" id="inputUserPass" placeholder="Minimal 6 karakter" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:outline-none">
                    <p id="passHelpText" class="text-[10px] text-slate-400 mt-1 hidden">Kosongkan jika tidak ingin mengubah password lama.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Akun</label>
                    <select name="status" id="inputUserStatus" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModalUser()" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

<script>
    const modalUser = document.getElementById('modalUser');

    function openModalTambahUser() {
        document.getElementById('modalUserTitle').innerText = 'Tambah Pengguna Baru';
        document.getElementById('formUserAction').value = 'tambah';
        document.getElementById('formUserId').value = '';
        document.getElementById('inputUserNama').value = '';
        document.getElementById('inputUserUsername').value = '';
        document.getElementById('inputUserPass').value = '';
        document.getElementById('inputUserPass').required = true;
        document.getElementById('passRequiredStar').classList.remove('hidden');
        document.getElementById('passHelpText').classList.add('hidden');
        document.getElementById('inputUserRole').value = 'kasir';
        document.getElementById('inputUserStatus').value = 'aktif';

        modalUser.classList.remove('hidden');
    }

    function openModalEditUser(u) {
        document.getElementById('modalUserTitle').innerText = 'Edit Pengguna: ' + u.nama_lengkap;
        document.getElementById('formUserAction').value = 'edit';
        document.getElementById('formUserId').value = u.id;
        document.getElementById('inputUserNama').value = u.nama_lengkap;
        document.getElementById('inputUserUsername').value = u.username;
        document.getElementById('inputUserPass').value = '';
        document.getElementById('inputUserPass').required = false;
        document.getElementById('passRequiredStar').classList.add('hidden');
        document.getElementById('passHelpText').classList.remove('hidden');
        document.getElementById('inputUserRole').value = u.role;
        document.getElementById('inputUserStatus').value = u.status;

        modalUser.classList.remove('hidden');
    }

    function closeModalUser() {
        modalUser.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

