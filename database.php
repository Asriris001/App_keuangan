<?php
// config/database.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'db_keuangan_jualan';

try {
    // 1. Koneksi awal ke server MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Buat database jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 3. Buat tabel-tabel utama jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `pengaturan` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nama_toko` VARCHAR(150) NOT NULL DEFAULT 'Toko Berkah Sejahtera',
            `slogan` VARCHAR(255) DEFAULT 'Solusi Belanja Hemat & Terpercaya',
            `alamat` TEXT,
            `telepon` VARCHAR(50) DEFAULT '0812-3456-7890',
            `email` VARCHAR(100) DEFAULT 'kontak@tokoberkah.com',
            `pesan_struk` TEXT,
            `mata_uang` VARCHAR(10) DEFAULT 'Rp',
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kategori` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nama` VARCHAR(100) NOT NULL,
            `tipe` ENUM('pemasukan', 'pengeluaran') NOT NULL DEFAULT 'pemasukan',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `produk` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kode_produk` VARCHAR(50) UNIQUE,
            `nama_produk` VARCHAR(150) NOT NULL,
            `kategori_id` INT NULL,
            `harga_modal` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `harga_jual` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `stok` INT NOT NULL DEFAULT 0,
            `satuan` VARCHAR(20) DEFAULT 'pcs',
            `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `transaksi` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nomor_transaksi` VARCHAR(50) UNIQUE NOT NULL,
            `tanggal` DATE NOT NULL,
            `waktu` TIME NOT NULL,
            `nama_pelanggan` VARCHAR(100) DEFAULT 'Pelanggan Umum',
            `total_modal` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `total_penjualan` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `diskon` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `total_akhir` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `bayar` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `kembalian` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `metode_pembayaran` ENUM('Tunai', 'QRIS', 'Transfer Bank', 'Debit/Kredit') NOT NULL DEFAULT 'Tunai',
            `status_pembayaran` ENUM('Lunas', 'Belum Lunas') NOT NULL DEFAULT 'Lunas',
            `catatan` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `transaksi_detail` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transaksi_id` INT NOT NULL,
            `produk_id` INT NULL,
            `nama_item` VARCHAR(150) NOT NULL,
            `harga_modal` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `harga_satuan` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `qty` INT NOT NULL DEFAULT 1,
            `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_detail_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `pengeluaran` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nomor_pengeluaran` VARCHAR(50) UNIQUE NOT NULL,
            `tanggal` DATE NOT NULL,
            `kategori_id` INT NULL,
            `nama_pengeluaran` VARCHAR(150) NOT NULL,
            `nominal` DECIMAL(15,2) NOT NULL,
            `metode_pembayaran` ENUM('Tunai', 'Transfer Bank', 'Lainnya') NOT NULL DEFAULT 'Tunai',
            `catatan` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_pengeluaran_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nama_lengkap` VARCHAR(100) NOT NULL,
            `username` VARCHAR(50) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
            `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Pastikan kolom kasir_nama ada pada tabel transaksi
    try {
        $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `kasir_nama` VARCHAR(100) DEFAULT 'Kasir' AFTER `nama_pelanggan`");
    } catch (Exception $e) {
        // Kolom sudah ada
    }

    // 4. Inisialisasi pengaturan toko dan user dasar jika belum ada
    initDefaultData($pdo);

} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:10px;margin:20px;'>
        <h2 style='margin-top:0;'>Terjadi Kendala Koneksi Database!</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Pastikan MySQL di XAMPP Control Panel sudah berstatus <strong>Running</strong>.</p>
    </div>");
}

function initDefaultData($pdo) {
    // 1. Hanya siapkan 1 baris pengaturan toko default (dapat diedit di menu Pengaturan Toko)
    $cekPengaturan = $pdo->query("SELECT COUNT(*) FROM `pengaturan`")->fetchColumn();
    if ($cekPengaturan == 0) {
        $stmt = $pdo->prepare("INSERT INTO `pengaturan` (nama_toko, slogan, alamat, telepon, email, pesan_struk) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Toko Saya',
            'Pencatatan Penjualan & Keuangan Usaha',
            'Alamat Toko / Usaha',
            '0812-0000-0000',
            'kontak@tokosaya.com',
            'Terima kasih telah berbelanja! Barang yang sudah dibeli tidak dapat ditukar kecuali ada perjanjian sebelumnya.'
        ]);
    }

    // 2. Siapkan akun default Admin & Kasir jika tabel users masih kosong
    $cekUsers = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($cekUsers == 0) {
        $stmtUser = $pdo->prepare("INSERT INTO `users` (nama_lengkap, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
        
        // Akun Admin (Full Akses ke semua menu, laporan, pengaturan, master data)
        $stmtUser->execute([
            'Administrator',
            'admin',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            'aktif'
        ]);

        // Akun Kasir (Akses khusus transaksi kasir & riwayat penjualan saja)
        $stmtUser->execute([
            'Petugas Kasir',
            'kasir',
            password_hash('kasir123', PASSWORD_DEFAULT),
            'kasir',
            'aktif'
        ]);
    }
}


