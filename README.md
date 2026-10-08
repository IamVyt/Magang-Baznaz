# SIM Keuangan UPZ BAZNAS Petrokimia Gresik

Sistem Informasi Manajemen Keuangan untuk Unit Pengumpul Zakat (UPZ) BAZNAS PT Petrokimia Gresik. Aplikasi web berbasis PHP & MySQL untuk pencatatan dan pengelolaan dana zakat, infak, dan sedekah.

## Fitur
- **Dashboard** — Ringkasan statistik kas masuk/keluar, grafik cashflow, dan tabel transaksi terbaru
- **Manajemen Transaksi** — CRUD penyumbang (kas masuk) dan penerima (kas keluar) dengan saldo otomatis
- **Manajemen User** — CRUD akun petugas dengan hashing password
- **Export Excel** — Laporan keuangan format `.xls` dengan blok tanda tangan resmi
- **Keamanan** — CSRF protection, prepared statements, login lockout, session management

## Teknologi
- PHP 8.x
- MySQL / MariaDB
- Bootstrap 5
- Chart.js
- Google Fonts (Plus Jakarta Sans, Inter)

## Instalasi
1. Clone repository ini ke folder `htdocs` XAMPP:
   ```bash
   git clone https://github.com/IamVyt/Magang-Baznaz.git
   ```
2. Import database:
   - Buka phpMyAdmin → buat database baru bernama `magang`
   - Import file `database.sql` atau `sistem_donasi/sim_keuangan.sql`
3. Sesuaikan konfigurasi database di `koneksi.php` jika diperlukan
4. Akses melalui browser: `http://localhost/Magang-Baznaz/`

## Login Default
- **Username:** `admin`
- **Password:** `admin123`

## Struktur Folder
```
├── assets/
│   ├── style.css          # Stylesheet utama
│   └── img/               # Gambar & maskot
├── partials/              # Template partial
├── sistem_donasi/         # SQL dump asli
├── database.sql           # Full database dump
├── koneksi.php            # Koneksi database
├── fungsi.php             # Helper functions & CSRF
├── login.php              # Halaman login
├── register.php           # Halaman registrasi
├── index.php              # Dashboard utama
├── edit.php               # Edit transaksi
├── hapus.php              # Hapus transaksi
├── users.php              # Manajemen user
├── export_excel.php       # Export laporan Excel
└── logout.php             # Logout
```

## Lisensi
Proyek tugas kuliah / magang — PT Petrokimia Gresik.
