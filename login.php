<?php
// login.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: index.php');
    } else {
        header('Location: kasir.php');
    }
    exit;
}

$pengaturan = get_pengaturan($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE username = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            set_flash('success', 'Selamat Datang!', 'Login berhasil sebagai ' . ucfirst($user['role']) . ' (' . $user['nama_lengkap'] . ')');

            if ($user['role'] === 'admin') {
                header('Location: index.php');
            } else {
                header('Location: kasir.php');
            }
            exit;
        } else {
            $error = 'Username atau password salah, atau akun dinonaktifkan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Masuk - <?= htmlspecialchars($pengaturan['nama_toko']) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans text-slate-800">

    <div class="max-w-md w-full">
        <!-- Logo & Toko Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 items-center justify-center text-white shadow-lg shadow-emerald-500/25 mb-3">
                <i class="fa-solid fa-store text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($pengaturan['nama_toko']) ?></h1>
            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($pengaturan['slogan'] ?? 'Sistem Kasir & Pembukuan Keuangan Usaha') ?></p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-200/50 p-6 sm:p-8">
            
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-800">Masuk Akun</h2>
                <p class="text-xs text-slate-400 mt-0.5">Pilih role dan masukkan kredensial akun Anda</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-sm shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="username" id="inputUsername" required placeholder="Masukkan username" 
                            class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="password" name="password" id="inputPassword" required placeholder="Masukkan password" 
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                        <button type="button" onclick="togglePassword()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/25 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Masuk ke Aplikasi</span>
                </button>
            </form>

            <!-- Quick Auto-Fill for Testing -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 text-center">Akun Bawaan Sistem</span>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="fillAccount('admin', 'admin123')" class="p-2.5 rounded-xl border border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/70 text-left transition-colors group">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-emerald-800">Admin</span>
                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-emerald-200 text-emerald-800">Full</span>
                        </div>
                        <p class="text-[10px] text-emerald-600 mt-0.5">admin / admin123</p>
                    </button>

                    <button type="button" onclick="fillAccount('kasir', 'kasir123')" class="p-2.5 rounded-xl border border-blue-200 bg-blue-50/60 hover:bg-blue-100/70 text-left transition-colors group">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-blue-800">Kasir</span>
                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-blue-200 text-blue-800">POS</span>
                        </div>
                        <p class="text-[10px] text-blue-600 mt-0.5">kasir / kasir123</p>
                    </button>
                </div>
            </div>

        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($pengaturan['nama_toko']) ?>. Aplikasi Keuangan Usaha.
        </p>
    </div>

<script>
    function togglePassword() {
        const pass = document.getElementById('inputPassword');
        const eye = document.getElementById('eyeIcon');
        if (pass.type === 'password') {
            pass.type = 'text';
            eye.className = 'fa-regular fa-eye-slash';
        } else {
            pass.type = 'password';
            eye.className = 'fa-regular fa-eye';
        }
    }

    function fillAccount(u, p) {
        document.getElementById('inputUsername').value = u;
        document.getElementById('inputPassword').value = p;
    }
</script>

</body>
</html>

