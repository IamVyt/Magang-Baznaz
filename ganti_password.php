<?php
require 'fungsi.php';
wajib_login();

// Ambil data user saat ini
$user = q_one($conn, "SELECT id, username, ganti_password FROM users WHERE id = ?", 'i', [$_SESSION['user_id']]);
if (!$user || (int)$user['ganti_password'] === 0) {
    redirect('index.php');
}

$pesan = '';
$tipe_pesan = 'danger';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ganti'])) {
    if (!csrf_valid()) {
        $pesan = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        $pw_baru       = (string)($_POST['pw_baru'] ?? '');
        $pw_konfirmasi = (string)($_POST['pw_konfirmasi'] ?? '');

        if (mb_strlen($pw_baru) < 8) {
            $pesan = 'Password baru minimal 8 karakter.';
        } elseif ($pw_baru !== $pw_konfirmasi) {
            $pesan = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($pw_baru, PASSWORD_DEFAULT);
            q($conn, "UPDATE users SET password = ?, ganti_password = 0 WHERE id = ?", 'si', [$hash, (int)$_SESSION['user_id']]);
            $_SESSION['ganti_password'] = 0;
            catat_audit($conn, 'ganti_password', 'users', (int)$_SESSION['user_id']);
            flash('success', 'Password berhasil diperbarui. Selamat datang!');
            redirect('index.php');
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
    <title>Ganti Password | UPZ BAZNAS Petrokimia Gresik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=<?= $css_ver ?>" rel="stylesheet">
</head>
<body>
<div class="auth-fullscreen">
    <header class="auth-nav-container">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-icon-sq" style="width:40px;height:40px;font-size:0.9rem;">UPZ</div>
            <div>
                <div class="fw-bold text-dark" style="font-size:1.05rem;font-weight:800;">SIM Keuangan</div>
                <div class="text-muted" style="font-size:0.72rem;">BAZNAS Petrokimia Gresik</div>
            </div>
        </div>
    </header>

    <main class="auth-main-wrapper" style="align-items:center;justify-content:center;">
        <div style="width:100%;max-width:460px;margin:0 auto;">
            <div class="auth-form-card">
                <div class="text-center mb-4">
                    <div class="stat-icon-box mx-auto mb-3" style="background:#fff3cd;color:#856404;width:56px;height:56px;font-size:1.6rem;">
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-1">Ganti Password</h2>
                    <p class="text-muted small mb-0">
                        Halo, <strong><?= e($_SESSION['nama'] ?? '') ?></strong>! Demi keamanan akun, Anda wajib mengganti password sebelum melanjutkan.
                    </p>
                </div>

                <?php if ($pesan !== ''): ?>
                    <div class="alert alert-<?= e($tipe_pesan) ?> py-2 px-3 small border-0 shadow-sm mb-3" style="border-radius:14px;" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1"></i><?= e($pesan) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="pw_baru">Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" id="pw_baru" name="pw_baru" class="form-control"
                                   placeholder="Minimal 8 karakter" minlength="8" required autofocus>
                            <button type="button" class="btn btn-petro-outline btn-toggle-pw" tabindex="-1"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted" for="pw_konfirmasi">Ulangi Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock-fill text-muted"></i></span>
                            <input type="password" id="pw_konfirmasi" name="pw_konfirmasi" class="form-control"
                                   placeholder="Ulangi password baru" minlength="8" required>
                        </div>
                    </div>
                    <button type="submit" name="ganti" class="auth-btn-action">
                        <i class="bi bi-shield-check me-2"></i>Simpan & Lanjutkan
                    </button>
                </form>
            </div>
        </div>
    </main>

    <footer class="auth-footer-bar">
        <div>&copy; <?= date('Y') ?> UPZ BAZNAS PT Petrokimia Gresik</div>
        <div>Amanah &bull; Transparan &bull; Akuntabel</div>
    </footer>
</div>
<script>
document.querySelectorAll('.btn-toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = this.closest('.input-group').querySelector('input');
        var icon  = this.querySelector('i');
        var isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});
</script>
</body>
</html>