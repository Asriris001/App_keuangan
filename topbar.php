<?php
// includes/topbar.php
?>
<header class="bg-white border-b border-slate-200/80 px-4 md:px-8 py-3.5 flex items-center justify-between sticky top-0 z-30">
    <div class="flex items-center gap-3">
        <!-- Mobile Toggle Button -->
        <button id="sidebarToggle" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>

        <div>
            <h2 class="text-lg md:text-xl font-bold text-slate-800 leading-tight">
                <?= isset($headerTitle) ? htmlspecialchars($headerTitle) : 'Ringkasan Keuangan' ?>
            </h2>
            <p class="text-xs text-slate-500 hidden sm:block">
                <?= isset($headerSubtitle) ? htmlspecialchars($headerSubtitle) : 'Pantau kinerja penjualan dan laba usaha Anda' ?>
            </p>
        </div>
    </div>

    <!-- Right Controls -->
    <div class="flex items-center gap-3 md:gap-4">
        <!-- Date display -->
        <div class="hidden lg:flex items-center gap-2 px-3.5 py-1.5 bg-slate-100 rounded-xl text-xs font-semibold text-slate-600">
            <i class="fa-regular fa-calendar-days text-emerald-600"></i>
            <span><?= tanggal_indo(date('Y-m-d'), true) ?></span>
            <span class="text-slate-300">|</span>
            <span id="liveClock" class="font-mono text-slate-700"><?= date('H:i') ?> WIB</span>
        </div>

        <!-- User Profile Pill & Logout -->
        <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
            <div class="hidden sm:flex flex-col text-right">
                <span class="text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($loggedUser['nama_lengkap'] ?? 'Pengguna') ?></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider <?= is_admin() ? 'text-emerald-700' : 'text-blue-700' ?>">
                    <?= htmlspecialchars($loggedUser['role'] ?? 'Kasir') ?>
                </span>
            </div>
            <a href="logout.php" title="Keluar / Logout" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
                <i class="fa-solid fa-power-off text-sm"></i>
            </a>
        </div>

        <!-- Quick POS Link -->
        <a href="kasir.php" class="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm shadow-emerald-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]">
            <i class="fa-solid fa-cash-register"></i>
            <span class="hidden sm:inline">Kasir POS</span>
        </a>
    </div>
</header>

<script>
    // Update live clock
    function updateClock() {
        const now = new Date();
        const clockEl = document.getElementById('liveClock');
        if (clockEl) {
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = `${h}:${m}:${s} WIB`;
        }
    }
    setInterval(updateClock, 1000);
</script>

