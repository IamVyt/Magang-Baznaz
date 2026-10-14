# SIM Keuangan UPZ BAZNAS Petrokimia Gresik

Sistem Informasi Manajemen Keuangan untuk Unit Pengumpul Zakat (UPZ) BAZNAS PT Petrokimia Gresik.
Aplikasi web berbasis PHP 8 & MySQL untuk pencatatan dan pengelolaan dana zakat, infak, dan sedekah yang transparan dan amanah.

## Fitur
- **Dashboard** — Ringkasan statistik saldo, grafik cashflow 6 bulan
- **Role-based Access Control** — Admin, Bendahara, Ketua (hak akses berbeda)
- **Manajemen Transaksi** — Catat pemasukan/pengeluaran, void/soft-delete dengan alasan
- **Audit Log** — Riwayat semua aktivitas (login, transaksi, perubahan user)
- **Manajemen User** — CRUD akun petugas dengan role, paksa ganti password saat login pertama
- **Export Excel** — Laporan keuangan dengan blok tanda tangan resmi
- **Keamanan** — CSRF protection, prepared statements, login lockout, session management

## Teknologi
- PHP 8.x (native, tanpa framework)
- MySQL / MariaDB
- Bootstrap 5.3
- Chart.js 4
- Google Fonts (Plus Jakarta Sans, Inter)

## Instalasi
1. Clone/extract ke folder `htdocs` XAMPP
2. Buka phpMyAdmin → buat database baru bernama `magang`
3. Import file `database.sql`
4. Jalankan file migrasi: `migrations/001_roles_audit_void.sql`
5. Sesuaikan konfigurasi database di `koneksi.php` jika diperlukan
6. Akses melalui browser: `http://localhost/Magang-Baznaz-main/`

## Keamanan Akun Awal & Kredensial
> **PENTING:** Repositori ini tidak menyimpan kata sandi default dalam bentuk teks terbuka (plaintext).
> Semua akun administrator awal yang disertakan dalam seed wajib mengganti kata sandi pada saat pertama kali masuk (login) ke dalam sistem (`ganti_password = 1`).
> Pendaftaran publik dinonaktifkan (`register.php`). Akun petugas baru hanya dapat dibuat oleh Administrator melalui menu **Kelola Pengguna**.

## Peran (Role) Pengguna
| Role       | Hak Akses |
|------------|-----------|
| **Admin**     | Semua fitur + kelola user + audit log |
| **Bendahara** | Input/edit transaksi |
| **Ketua**     | Lihat dashboard & laporan saja |

## Struktur Folder
```
├── assets/
│   ├── style.css          # Stylesheet utama
│   └── img/               # Gambar & maskot
├── migrations/            # File migrasi database
├── partials/              # Template partial (header/footer)
├── sistem_donasi/         # SQL dump asli
├── database.sql           # Database schema awal
├── koneksi.php            # Koneksi database
├── fungsi.php             # Helper functions, CSRF, role, audit
├── login.php              # Halaman login
├── logout.php             # Logout + audit
├── ganti_password.php     # Paksa ganti password (login pertama)
├── register.php           # DINONAKTIFKAN (redirect ke login)
├── index.php              # Dashboard + transaksi
├── edit.php               # Edit transaksi (admin/bendahara)
├── hapus.php              # Void/batalkan transaksi
├── users.php              # Manajemen user (admin only)
├── audit.php              # Riwayat aktivitas (admin only)
└── export_excel.php       # Export laporan Excel
```

## Lisensi
Proyek magang — PT Petrokimia Gresik / BAZNAS.