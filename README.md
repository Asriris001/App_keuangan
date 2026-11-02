# Aplikasi Keuangan & Pemasukan Penjualan Modern (POS & Finance)

Aplikasi pencatatan pemasukan penjualan dan pembukuan keuangan usaha yang dirancang lengkap, modern, rapi, dan memiliki pemisahan hak akses antara **Kasir** dan **Admin**.

---

## 🔐 Akun & Pembagian Hak Akses (Role-Based Access)

Sistem telah dilengkapi pembagian hak akses untuk membedakan tugas dan menjaga kerahasiaan data keuangan toko:

| Peran (Role) | Kredensial Login | Hak Akses & Wewenang |
| :--- | :--- | :--- |
| **👑 Administrator** | Username: `admin`<br>Password: `admin123` | • **Full Akses** ke semua menu dan data sistem.<br>• Melihat **Laporan Keuangan & Laba Rugi Komprehensif**.<br>• Melihat harga modal (HPP), nilai aset, dan persentase margin keuntungan.<br>• Mengelola produk (*Tambah, Edit, Hapus*).<br>• Mencatat & memantau biaya pengeluaran toko.<br>• Mengelola kategori dan akun pengguna (*tambah akun Kasir baru*).<br>• Mengubah profil usaha toko dan menghapus transaksi. |
| **🛒 Kasir (POS)** | Username: `kasir`<br>Password: `kasir123` | • **Fokus pada operasional penjualan**: Akses mesin kasir POS (`kasir.php`) & cetak struk.<br>• Melihat riwayat penjualan dan cetak ulang nota pembeli.<br>• Melihat ketersediaan stok produk & harga jual (**rahasia HPP / modal produk dan margin laba disembunyikan**).<br>• **Dilarang mengakses**: Laporan Laba Rugi, Pengeluaran Toko, Pengaturan Toko, Manajemen Pengguna, serta tidak dapat menghapus produk / transaksi. |

---

## 🌟 Fitur Utama Aplikasi

### 1. 📊 Dashboard Ringkasan Eksekutif (`index.php`)
- **Kartu KPI Finansial**: Total Omset Hari Ini (dengan indikator naik/turun vs kemarin), Omset Bulan Ini, Estimasi Laba Bersih Bulan Ini, dan Total Biaya Operasional.
- **Grafik Tren Penjualan 7 Hari**: Visualisasi omset harian interaktif berbasis Chart.js.
- **Diagram Metode Pembayaran**: Proporsi pembayaran Tunai, QRIS, Transfer Bank, dan Kartu Debit.
- **Top 5 Produk Terlaris**: Pemeringkatan produk paling laku beserta omset per produk.
- **Transaksi Terbaru**: Tabel transaksi real-time dengan akses cepat ke detail dan cetak struk.

### 2. 🛒 Kasir Penjualan / Point of Sales Cepat (`kasir.php`)
- **Tampilan Split POS**: Katalog di sisi kiri dan Keranjang di sisi kanan.
- **Pencarian Cepat & Filter Kategori**: Cari barang dengan nama/kode SKU atau filter berdasarkan kategori.
- **Input Item Manual / Bebas**: Dapat mencatat penjualan jasa atau barang non-katalog secara langsung.
- **Perhitungan Otomatis**: Subtotal, diskon potongan harga, total tagihan, uang bayar, dan kembalian.
- **Tombol Uang Cepat**: Pilihan "Uang Pas", "50.000", "100.000", "200.000".
- **Multi-Metode Pembayaran**: Tunai, QRIS, Transfer Bank, dan Debit/Kredit.
- **Pengurangan Stok Otomatis**: Stok fisik berkurang saat transaksi berhasil disimpan.

### 3. 🧾 Cetak Struk Pembayaran Kasir (`cetak-struk.php`)
- Format struk belanja standar printer thermal (58mm / 80mm) yang rapi.
- Header toko, nomor invoice, tanggal & jam, rincian barang, diskon, pembayaran, dan kembalian.
- Pesan terima kasih / footer toko yang dapat disesuaikan.
- Tombol cetak langsung (`window.print()`).

### 4. 📋 Riwayat Transaksi & Filter Lengkap (`transaksi.php` & `transaksi-detail.php`)
- **Banner Grand Total**: Menampilkan Total Semua Pemasukan Bersih, Penjualan Kotor, Diskon, Total Item, dan Total Transaksi.
- **Rincian Metode Pembayaran**: Ringkasan pemasukan Tunai, QRIS, Transfer Bank, dan Debit.
- **Filter Cepat**: Hari Ini, 7 Hari Terakhir, Bulan Ini, atau Rentang Tanggal Kustom.
- **Filter Metode Pembayaran & Pencarian**: Berdasarkan nomor nota atau nama pembeli.
- **Export Data**: Ekspor seluruh riwayat transaksi ke format CSV / Excel.
- **Detail Invoice & Hapus Aman**: Menghapus transaksi akan mengembalikan stok produk ke inventaris secara otomatis.

### 5. 🧾 Cetak Struk Rekap Semua Pemasukan (`cetak-struk-rekap.php`)
- **Struk Rekap Total (Z-Report)**: Format thermal 58mm/80mm yang merangkum total semua harga/omset, rincian pembayaran (Tunai, QRIS, Transfer), daftar semua invoice, serta rekap produk terlaris yang terjual.
- **Mode Cetak Seluruh Nota**: Opsi mencetak semua lembar nota pelanggan sekaligus secara beruntun.
- Siap cetak langsung dengan tombol cetak (`window.print()`).

### 6. 📦 Manajemen Master Produk & Inventaris (`produk.php`)
- Kelola kode produk (SKU), nama, kategori, harga modal (HPP), harga jual, dan stok.
- **Kalkulasi Margin Laba Otomatis**: Menampilkan persentase margin keuntungan per produk.
- Indikator peringatan stok menipis (jika stok &le; 10 unit).
- Tambah & Edit produk dengan modal popup yang rapi.

### 6. 🏷️ Manajemen Kategori (`kategori.php`)
- Pengelompokan kategori untuk Pemasukan (Makanan, Minuman, Sembako, Layanan, dll).
- Pengelompokan kategori untuk Pengeluaran (Listrik, Sewa, Gaji, Bahan Baku, dll).

### 7. 💸 Pencatatan Biaya & Pengeluaran Toko (`pengeluaran.php`)
- Catat beban operasional toko untuk mendapatkan laporan laba bersih yang akurat.
- Filter berdasarkan rentang tanggal dan kategori biaya.

### 8. 📑 Laporan Keuangan & Laba Rugi Komprehensif (`laporan.php`)
- **Format Laporan Resmi Laba/Rugi (Income Statement)**:
  - Total Pendapatan Kotor (Gross Sales)
  - Diskon & Potongan Penjualan
  - Pendapatan Bersih (Net Revenue)
  - Total Harga Pokok Penjualan (HPP Modal Barang)
  - **Laba Kotor (Gross Profit)**
  - Total Rincian Beban & Biaya Operasional
  - **Laba Bersih Akhir Usaha (Net Profit)**
- **Rekapitulasi Penjualan Harian**: Tabel omset, modal, beban, dan laba per hari.
- **Siap Cetak / Print PDF**: Tata letak otomatis menyesuaikan ke kertas cetak standar dilengkapi tempat tanda tangan penanggung jawab & kasir.

### 9. ⚙️ Pengaturan Profil Usaha & Toko (`pengaturan.php`)
- Kustomisasi Nama Toko, Slogan, Alamat, No. Telepon / WhatsApp, dan Pesan Footer Nota.
- Dilengkapi **Live Preview Struk Kasir** saat Anda mengetik informasi toko.

---

## 🚀 Cara Menjalankan Aplikasi

Aplikasi sudah terpasang langsung di direktori web server XAMPP Anda:
1. Pastikan modul **Apache** dan **MySQL** di **XAMPP Control Panel** berstatus **Running** (berwarna hijau).
2. Buka browser (Chrome, Edge, atau Firefox).
3. Akses alamat:
   ```
   http://localhost/App_keuangan
   ```
4. **Selesai!** Database `db_keuangan_jualan` beserta tabel dan data contoh otomatis terpasang tanpa perlu konfigurasi manual di phpMyAdmin.

---

## 💻 Teknologi yang Digunakan
- **Backend**: PHP 8.2 (Pure Native, Aman dengan PDO Prepared Statements & ACID Transactions)
- **Database**: MySQL / MariaDB (Auto-migration & Auto-seeding)
- **Frontend Styling**: Tailwind CSS via CDN + Google Fonts (Plus Jakarta Sans)
- **Komponen & Visual**: FontAwesome 6 Icons, Chart.js 4 (Grafik Interaktif), SweetAlert2 (Notifikasi & Dialog Konfirmasi)

