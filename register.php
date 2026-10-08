<?php
require 'fungsi.php';

if (($_SESSION['status'] ?? '') === 'login') {
    redirect('index.php');
}

$pesan = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['register'])) {
    if (!csrf_valid()) {
        $pesan = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        $nama       = trim($_POST['nama'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $konfirmasi = (string)($_POST['konfirmasi'] ?? '');

        if ($nama === '' || mb_strlen($nama) > 100) {
            $pesan = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
        } elseif ($username === '' || mb_strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $pesan = 'Username hanya boleh berisi huruf, angka, garis bawah (_), atau titik tanpa spasi.';
        } elseif (mb_strlen($password) < 5) {
            $pesan = 'Password minimal terdiri dari 5 karakter.';
        } elseif ($password !== $konfirmasi) {
            $pesan = 'Konfirmasi password tidak sesuai dengan password yang dimasukkan.';
        } else {
            // Cek apakah username sudah ada
            $duplikat = q_one($conn, "SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1", 's', [$username]);
            if ($duplikat) {
                $pesan = "Username '$username' sudah terdaftar. Silakan pilih username lain.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                q($conn, "INSERT INTO users (nama_lengkap, username, password) VALUES (?, ?, ?)",
                    'sss', [$nama, $username, $hash]);

                flash('success', "Akun '$username' berhasil didaftarkan! Silakan masuk dengan akun baru Anda.");
                redirect('login.php');
            }
        }
    }
}
$css_ver = @filemtime(__DIR__ . '/assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | UPZ BAZNAS Petrokimia Gresik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=<?= $css_ver ?>" rel="stylesheet">
</head>
<body>

<div class="auth-fullscreen">
    <!-- NAVIGASI ATAS (HANYA BRAND & SIGN IN / REGISTER) -->
    <header class="auth-nav-container">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-icon-sq" style="width: 40px; height: 40px; font-size: 0.9rem;">UPZ</div>
            <div>
                <div class="fw-bold text-dark" style="font-size: 1.05rem; line-height: 1.1; font-weight: 800;">SIM Keuangan</div>
                <div class="text-muted" style="font-size: 0.72rem;">BAZNAS Petrokimia Gresik</div>
            </div>
        </div>

        <div class="auth-nav-actions">
            <a href="login.php" class="auth-btn-pill">Sign In</a>
            <a href="register.php" class="auth-nav-link active">Register</a>
        </div>
    </header>

    <!-- WRAPPER UTAMA DUA KOLOM -->
    <main class="auth-main-wrapper">
        <div class="auth-grid-split">
            <!-- Kolom Kiri: Teks Sambutan & Maskot BAZNAS Alami (Center Sejajar Teks) -->
            <div class="auth-hero-col">
                <h1 class="auth-hero-title">
                    Daftar Akun Baru
                    <span>UPZ BAZNAS</span>
                </h1>
                <p class="auth-hero-sub">
                    Pendaftaran akun petugas dan pengelola kas UPZ BAZNAS PT Petrokimia Gresik untuk transparansi dana zakat & donasi.
                </p>

                <!-- Maskot BAZNAS Sejajar Center dengan Teks di Atas -->
                <div class="auth-mascot-box">
                    <img src="assets/img/baznas_mascot.jpg" alt="Maskot UPZ BAZNAS" class="auth-mascot-img">
                </div>
            </div>

            <!-- Kolom Kanan: Formulir Pendaftaran (Register) -->
            <div>
                <div class="auth-form-card">
                    <div class="mb-4">
                        <h2 class="h4 fw-bold text-dark mb-1">Buat Akun Petugas</h2>
                        <p class="text-muted small mb-0">Lengkapi data diri untuk membuat akun baru</p>
                    </div>

                    <?php if ($pesan !== ''): ?>
                        <div class="alert alert-danger py-2 px-3 small border-0 shadow-sm mb-3" style="border-radius: 14px;" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-1"></i><?= e($pesan) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <?= csrf_field() ?>

                        <div class="auth-input-group">
                            <input type="text" name="nama" class="form-control auth-input-pill"
                                   placeholder="Full Name (Nama Lengkap)" value="<?= e($_POST['nama'] ?? '') ?>" required autofocus>
                        </div>

                        <div class="auth-input-group">
                            <input type="text" name="username" class="form-control auth-input-pill"
                                   placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" required>
                        </div>

                        <div class="auth-input-group">
                            <input type="password" id="regPassword" name="password" class="form-control auth-input-pill"
                                   placeholder="Password (Minimal 5 Karakter)" required>
                            <button type="button" class="auth-input-toggle" id="btnToggleRegPass" aria-label="Lihat password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>

                        <div class="auth-input-group">
                            <input type="password" name="konfirmasi" class="form-control auth-input-pill"
                                   placeholder="Confirm Password (Ulangi Password)" required>
                        </div>

                        <!-- Tombol Register Biru Petrokimia -->
                        <button type="submit" name="register" class="auth-btn-action mt-2">
                            REGISTER
                        </button>

                        <!-- Pembatas -->
                        <div class="auth-divider">
                            <span>Sudah memiliki akun?</span>
                        </div>

                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none fw-bold" style="color: #0B4A8F;">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk dengan Akun yang Ada
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER RESMI DI BAGIAN BAWAH LAYAR PENUH -->
    <footer class="auth-footer-bar">
        <div>&copy; <?= date('Y') ?> UPZ BAZNAS PT Petrokimia Gresik &bull; SIM Keuangan</div>
        <div>Amanah &bull; Transparan &bull; Akuntabel</div>
    </footer>
</div>

<script>
    // Toggle sembunyikan / tampilkan password
    document.getElementById('btnToggleRegPass').addEventListener('click', function () {
        var input = document.getElementById('regPassword');
        var icon = this.querySelector('i');
        var isPass = (input.type === 'password');
        input.type = isPass ? 'text' : 'password';
        icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
</body>
</html>
