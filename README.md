# SIM Keuangan & Donasi Online UPZ BAZNAS Petrokimia Gresik

Sistem Informasi Manajemen Keuangan dan Layanan Donasi Online untuk Unit Pengumpul Zakat (UPZ) BAZNAS PT Petrokimia Gresik. Aplikasi web ini dibangun menggunakan **PHP 8 (native)**, **MySQL / MariaDB**, antarmuka modern yang responsif dan elegan, serta dilengkapi sistem keamanan berlapis.

---

## ✨ Fitur Utama

### 1. Portal Publik & Donasi Online
- **Landing Page Interaktif:** Informasi profil UPZ, program penyaluran, mitra, dan kontak.
- **Kalkulator & Pilihan Program Donasi:** Menyalurkan Zakat Maal, Zakat Fitrah, Infak, Sedekah, Dana Sosial, Fidyah, dan Wakaf.
- **Metode Pembayaran Lengkap:**
  - **Transfer Bank:** Rekening resmi BCA, BNI, BRI, Mandiri, dan BSI.
  - **E-Wallet:** GoPay, OVO, DANA, dan ShopeePay.
  - **QRIS:** Pembayaran instan via scan QR code.
- **Unggah Bukti Transfer:** Donatur dapat mengunggah bukti pembayaran secara aman.
- **Kuitansi / Bukti Donasi Digital:** Bukti setor resmi dengan format siap cetak (`window.print()`).
- **Portal Donatur (Muzakki):** Pendaftaran akun donatur dan pelacakan status riwayat donasi pribadi.

### 2. Back-Office & Manajemen Keuangan
- **Dashboard Keuangan:** Statistik ringkasan kas (Saldo, Pemasukan, Penyaluran), grafik arus kas 6 bulan terakhir dengan Chart.js.
- **Verifikasi Donasi Masuk:** Petugas dapat memeriksa bukti pembayaran, menerima (otomatis mencatat ke kas dan menambah saldo), atau menolak donasi dengan alasan tertentu.
- **Manajemen Transaksi Terpadu:** Pencatatan kas masuk dan keluar secara langsung, dilengkapi fitur pembatalan (*void*) bersyarat dan pencatatan alasan batal.
- **Pengaturan Metode Pembayaran:** Admin dapat mengaktifkan, menonaktifkan, dan mengatur nomor rekening bank atau e-wallet.
- **Master Data:**
  - **Mustahik:** Klasifikasi 8 Asnaf (Fakir, Miskin, Amil, Muallaf, Riqab, Gharim, Sabilillah, Ibnu Sabil).
  - **Muzakki:** Data donatur terdaftar.
  - **Program & Kategori Dana:** Kategori pemasukan dan program penyaluran aktif.
- **Laporan & Ekspor Excel:** Unduh rekapitulasi laporan transaksi kas dalam format spreadsheet dengan format resmi.

### 3. Keamanan & Tata Kelola
- **Role-Based Access Control (RBAC):**
  - `admin`: Akses penuh semua modul, manajemen pengguna, pengaturan pembayaran, dan audit log.
  - `bendahara`: Akses kelola transaksi kas, verifikasi donasi, dan master data.
  - `ketua`: Hak akses monitor data (view-only laporan dan dashboard).
  - `user`: Akses donatur umum (portal donasi dan riwayat transaksi pribadi).
- **Audit Log Terperinci:** Mencatat setiap aktivitas krusial (login, transaksi kas, void, perubahan pengguna) beserta snapshot data JSON, waktu, dan IP address.
- **Proteksi Keamanan:**
  - Token CSRF pada seluruh form POST.
  - Prepared statements untuk mencegah SQL Injection.
  - Password hashing menggunakan algoritma `PASSWORD_DEFAULT` (Bcrypt).
  - Validasi ketat unggahan berkas (tipe MIME, ukuran berkas, penamaan acak unik).

---

## 📁 Struktur Direktori

```text
├── css/                   # Stylesheet utama & stylesheet modul aplikasi
│   ├── app.css
│   └── style.css
├── database/              # Skrip skema dan migrasi database
│   └── migrasi.sql
├── img/                   # Aset gambar, maskot SVG, dan QRIS
│   ├── maskot.svg
│   └── qris.jpg
├── js/                    # Skrip JavaScript interaktif
│   ├── app.js
│   └── main.js
├── php/                   # Logika backend, partials, dan fungsi inti
│   ├── app_footer.php
│   ├── app_header.php
│   ├── crud.php
│   ├── data.php
│   ├── footer.php
│   ├── fungsi.php
│   ├── header.php
│   ├── koneksi.php
│   ├── pilih_metode.php
│   └── sprite.php
├── uploads/               # Direktori penyimpanan bukti transfer (terproteksi)
├── audit.php              # Log aktivitas sistem
├── bayar.php              # Halaman instruksi transfer & upload bukti
├── bukti.php              # Secure image viewer untuk bukti pembayaran
├── cetak_donasi.php       # Kuitansi donasi resmi
├── dashboard.php          # Dashboard keuangan & ringkasan transaksi
├── donasi_aksi.php        # Endpoint aksi pembatalan / penanganan donasi
├── donasi_masuk.php       # Verifikasi donasi oleh petugas
├── donasi_saya.php        # Riwayat donasi pengguna
├── donasi_sukses.php      # Halaman konfirmasi setelah donasi diajukan
├── export_excel.php       # Ekspor laporan kas ke Excel
├── ganti_password.php     # Fitur ganti kata sandi
├── index.php              # Beranda / Landing page publik
├── login.php              # Halaman masuk
├── logout.php             # Halaman keluar sistem
├── mustahik.php           # Kelola data mustahik
├── muzakki.php            # Kelola data muzakki
├── pembayaran.php         # Pengaturan rekening bank & e-wallet
├── program.php            # Kelola program penyaluran
├── register.php           # Pendaftaran akun publik
├── salurkan.php           # Formulir donasi online
└── users.php              # Manajemen akun & hak akses petugas
```

---

## 🚀 Panduan Instalasi

### 1. Kebutuhan Sistem
- **Web Server:** Apache (XAMPP / Laragon) atau PHP Built-in Server
- **PHP:** Versi 8.1 atau lebih baru (ekstensi `mysqli`, `gd`, `json`, `mbstring` aktif)
- **Database:** MySQL / MariaDB

### 2. Langkah Pemasangan
1. Tempatkan proyek ini di direktori web server Anda (misal `C:\xampp\htdocs\magang`).
2. Nyalakan layanan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) dan buat database baru dengan nama `magang`.
4. Import file migrasi database:
   - Pilih database `magang` → klik tab **Import** → pilih berkas `database/migrasi.sql` → klik **Import**.
5. Sesuaikan konfigurasi database jika diperlukan pada file `php/koneksi.php`:
   ```php
   $host = 'localhost';
   $user = 'root';
   $pass = '';
   $db   = 'magang';
   ```
6. Jalankan aplikasi melalui browser:
   - Via XAMPP: `http://localhost/magang/`
   - Atau via PHP built-in server:
     ```bash
     php -S localhost:8080
     ```
     Lalu akses di `http://localhost:8080/`.

---

## 👥 Hak Cipta & Lisensi
Dikembangkan untuk kegiatan magang di **UPZ BAZNAS PT Petrokimia Gresik**.  
Seluruh hak cipta dilindungi.
