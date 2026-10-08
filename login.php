<?php
require 'fungsi.php';

if (($_SESSION['status'] ?? '') === 'login') {
    redirect('index.php');
}

$pesan = '';
$flash = ambil_flash();
$maks_gagal = 5;
$detik_kunci = 60;

$terkunci = time() < ($_SESSION['kunci_sampai'] ?? 0);
if ($terkunci) {
    $sisa = $_SESSION['kunci_sampai'] - time();
    $pesan = "Terlalu banyak percobaan. Coba lagi dalam {$sisa} detik.";
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login']) && !$terkunci) {
    if (!csrf_valid()) {
        $pesan = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $user = q_one($conn,
            "SELECT id, nama_lengkap, username, password FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1",
            's', [$username]);

        $ok = false;
        if ($user) {
            $hash = (string)$user['password'];
            if (preg_match('/^\$2[ayb]\$|^\$argon2/', $hash)) {
                $ok = password_verify($password, $hash);
            } elseif ($hash !== '' && hash_equals($hash, $password)) {
                $ok = true;
                q($conn, "UPDATE users SET password = ? WHERE id = ?", 'si',
                    [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama_lengkap'] ?: $user['username'];
            $_SESSION['status']   = 'login';
            unset($_SESSION['gagal_login'], $_SESSION['kunci_sampai']);
            redirect('index.php');
        }

        $_SESSION['gagal_login'] = ($_SESSION['gagal_login'] ?? 0) + 1;
        if ($_SESSION['gagal_login'] >= $maks_gagal) {
            $_SESSION['kunci_sampai'] = time() + $detik_kunci;
            $_SESSION['gagal_login'] = 0;
        }
        $pesan = 'Username atau password yang Anda masukkan salah.';
    }
}
$css_ver = @filemtime(__DIR__ . '/assets/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | UPZ BAZNAS Petrokimia Gresik</title>
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
            <a href="login.php" class="auth-nav-link active">Sign In</a>
            <a href="register.php" class="auth-btn-pill">Register</a>
        </div>
    </header>

    <!-- WRAPPER UTAMA DUA KOLOM -->
    <main class="auth-main-wrapper">
        <div class="auth-grid-split">
            <!-- Kolom Kiri: Teks Sambutan & Maskot BAZNAS Alami (Center Sejajar Teks) -->
            <div class="auth-hero-col">
                <h1 class="auth-hero-title">
                    Selamat Datang di
                    <span>UPZ BAZNAS</span>
                </h1>
                <p class="auth-hero-sub">
                    Unit Pengumpul Zakat PT Petrokimia Gresik. Layanan pencatatan dan pengelolaan dana zakat, infak, dan sedekah yang transparan dan amanah.
                </p>

                <!-- Maskot BAZNAS Sejajar Center dengan Teks di Atas -->
                <div class="auth-mascot-box">
                    <img src="assets/img/baznas_mascot.jpg" alt="Maskot UPZ BAZNAS" class="auth-mascot-img">
                </div>
            </div>

            <!-- Kolom Kanan: Formulir Masuk (Sign In) -->
            <div>
                <div class="auth-form-card">
                    <div class="mb-4">
                        <h2 class="h4 fw-bold text-dark mb-1">Masuk ke Akun Anda</h2>
                        <p class="text-muted small mb-0">Masukkan username dan kata sandi petugas</p>
                    </div>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['tipe']) ?> py-2 px-3 small border-0 shadow-sm mb-3" style="border-radius: 14px;" role="alert">
                            <i class="bi bi-check-circle-fill me-1"></i><?= e($flash['pesan']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($pesan !== ''): ?>
                        <div class="alert alert-danger py-2 px-3 small border-0 shadow-sm mb-3" style="border-radius: 14px;" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-1"></i><?= e($pesan) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <?= csrf_field() ?>

                        <div class="auth-input-group">
                            <input type="text" name="username" class="form-control auth-input-pill"
                                   placeholder="Username" required autofocus>
                        </div>

                        <div class="auth-input-group">
                            <input type="password" id="inputPassword" name="password" class="form-control auth-input-pill"
                                   placeholder="Password" required>
                            <button type="button" class="auth-input-toggle" id="btnTogglePassword" aria-label="Lihat password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>

                        <!-- Checkbox & Bantuan Password -->
                        <div class="d-flex align-items-center justify-content-between mb-4 small">
                            <label class="d-flex align-items-center gap-2 text-dark user-select-none" style="cursor: pointer;">
                                <input type="checkbox" name="ingat" class="form-check-input mt-0" style="width: 17px; height: 17px; border-radius: 5px;">
                                <span class="fw-semibold text-secondary">Keep me login</span>
                            </label>
                            <a href="javascript:void(0)" onclick="alert('Untuk keamanan akun petugas, silakan hubungi Administrator UPZ Petrokimia untuk pemulihan akses.');"
                               class="text-decoration-none text-muted fw-semibold">
                                Recovery Password
                            </a>
                        </div>

                        <!-- Tombol Sign In Biru Petrokimia -->
                        <button type="submit" name="login" class="auth-btn-action">
                            SIGN IN
                        </button>
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
    document.getElementById('btnTogglePassword').addEventListener('click', function () {
        var input = document.getElementById('inputPassword');
        var icon = this.querySelector('i');
        var isPass = (input.type === 'password');
        input.type = isPass ? 'text' : 'password';
        icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
</body>
</html>
