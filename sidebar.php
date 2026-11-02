<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside id="sidebar" class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 flex flex-col justify-between transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 shadow-lg md:shadow-none">
    <div class="flex flex-col h-full">
        <!-- Logo & Toko Header -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-store text-lg"></i>
                </div>
                <div class="overflow-hidden">
                    <h1 class="font-bold text-slate-800 text-base leading-tight truncate"><?= htmlspecialchars($appPengaturan['nama_toko']) ?></h1>
                    <p class="text-xs text-emerald-600 font-semibold tracking-wide flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Aplikasi Kasir & Keuangan
                    </p>
                </div>
            </a>
            <button id="sidebarClose" class="md:hidden text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <div class="px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Ringkasan</div>
            
            <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('index.php') ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center text-base"></i>
                <span>Dashboard</span>
            </a>

            <div class="pt-4 px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Pemasukan & Penjualan</div>

            <a href="kasir.php" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active(['kasir.php', 'transaksi-baru.php']) ?>">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-cash-register w-5 text-center text-base"></i>
                    <span>Kasir / Penjualan</span>
                </div>
                <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-emerald-100 text-emerald-700">POS</span>
            </a>

            <a href="transaksi.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active(['transaksi.php', 'transaksi-detail.php']) ?>">
                <i class="fa-solid fa-receipt w-5 text-center text-base"></i>
                <span>Riwayat Penjualan</span>
            </a>

            <a href="produk.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('produk.php') ?>">
                <i class="fa-solid fa-boxes-stacked w-5 text-center text-base"></i>
                <span><?= is_admin() ? 'Produk & Stok' : 'Cek Stok Produk' ?></span>
            </a>

            <?php if (is_admin()): ?>
                <div class="pt-4 px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Master Data & Beban</div>

                <a href="kategori.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('kategori.php') ?>">
                    <i class="fa-solid fa-tags w-5 text-center text-base"></i>
                    <span>Kategori</span>
                </a>

                <a href="pengeluaran.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('pengeluaran.php') ?>">
                    <i class="fa-solid fa-wallet w-5 text-center text-base"></i>
                    <span>Biaya / Pengeluaran</span>
                </a>

                <div class="pt-4 px-3 pb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Analisis & Sistem</div>

                <a href="laporan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('laporan.php') ?>">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-base"></i>
                    <span>Laporan & Laba Rugi</span>
                </a>

                <a href="users.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('users.php') ?>">
                    <i class="fa-solid fa-users-gear w-5 text-center text-base"></i>
                    <span>Kelola Pengguna</span>
                </a>

                <a href="pengaturan.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-colors <?= is_active('pengaturan.php') ?>">
                    <i class="fa-solid fa-gear w-5 text-center text-base"></i>
                    <span>Pengaturan Toko</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Bottom User / Quick Action -->
        <div class="p-3 border-t border-slate-100 bg-slate-50/70 space-y-2">
            <div class="p-2.5 bg-white rounded-xl border border-slate-200/80 flex items-center justify-between">
                <div class="flex items-center gap-2 overflow-hidden">
                    <div class="w-8 h-8 rounded-lg <?= is_admin() ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?> flex items-center justify-center font-bold text-xs shrink-0">
                        <?= strtoupper(substr($loggedUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="font-bold text-xs text-slate-800 truncate"><?= htmlspecialchars($loggedUser['nama_lengkap'] ?? 'User') ?></div>
                        <span class="text-[10px] font-semibold uppercase tracking-wider <?= is_admin() ? 'text-emerald-700' : 'text-blue-700' ?>">
                            <?= htmlspecialchars($loggedUser['role'] ?? 'Kasir') ?>
                        </span>
                    </div>
                </div>
                <a href="logout.php" title="Keluar / Logout" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                    <i class="fa-solid fa-power-off text-xs"></i>
                </a>
            </div>

            <a href="kasir.php" class="w-full flex items-center justify-center gap-2 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl text-xs shadow-sm transition-all">
                <i class="fa-solid fa-cash-register"></i>
                <span>Buka Kasir POS</span>
            </a>
        </div>
    </div>
</aside>

