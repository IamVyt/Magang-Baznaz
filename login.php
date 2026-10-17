<?php
require __DIR__ . '/php/fungsi.php';

// Sudah login: kembali ke halaman yang tadi dituju (mis. form donasi), atau petugas ke dashboard dan user biasa ke beranda
function tujuan_setelah_login() { return ambil_tujuan() ?? (is_staff() ? 'dashboard.php' : 'index.php'); }
if (($_SESSION['status'] ?? '') === 'login') redirect(tujuan_setelah_login());

$pesan = '';
$maks_gagal = 5;
$detik_kunci = 60;

$terkunci = time() < ($_SESSION['kunci_sampai'] ?? 0);
if ($terkunci) {
    $pesan = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($_SESSION['kunci_sampai'] - time()) . ' detik.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$terkunci) {
    if (!csrf_valid()) {
        $pesan = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $user = q_one($conn, "SELECT id, nama_lengkap, username, password, role FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1", 's', [$username]);

        $ok = false;
        if ($user) {
            $hash = (string)$user['password'];
            if (preg_match('/^\$2[ayb]\$|^\$argon2/', $hash)) {
                $ok = password_verify($password, $hash);
            } elseif ($hash !== '' && hash_equals($hash, $password)) {
                // Password lama yang belum di-hash: langsung diamankan
                $ok = true;
                q($conn, "UPDATE users SET password = ? WHERE id = ?", 'si', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama_lengkap'] ?: $user['username'];
            $_SESSION['status']   = 'login';
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['role']     = $user['role'] ?: 'user';
            catat_audit($conn, 'login');
            unset($_SESSION['gagal_login'], $_SESSION['kunci_sampai']);
            redirect(tujuan_setelah_login());
        }

        $_SESSION['gagal_login'] = ($_SESSION['gagal_login'] ?? 0) + 1;
        if ($_SESSION['gagal_login'] >= $maks_gagal) {
            $_SESSION['kunci_sampai'] = time() + $detik_kunci;
            $_SESSION['gagal_login'] = 0;
        }
        $pesan = 'Username atau password yang Anda masukkan salah.';
    }
}

$title = 'Login';
include __DIR__ . '/php/app_header.php';
?>

<section class="auth">
 <div class="c g">
  <h1>Selamat datang di UPZ BAZNAS</h1>
  <p>Layanan pencatatan dan pengelolaan dana zakat, infak, dan sedekah Petrokimia Gresik yang transparan dan amanah.</p>
  <img class="mas" src="img/maskot.svg" alt="Maskot UPZ BAZNAS: koin ramah yang membawa hati" width="340" height="342" decoding="async">
 </div>

 <form class="c l form" method="post" autocomplete="off">
  <h2><span class="tg">Masuk</span></h2>
  <p>Masukkan username dan kata sandi Anda.</p>
  <?php if ($pesan !== ''): ?><div class="alert danger" role="alert"><?= e($pesan) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label for="username">Username</label>
  <input id="username" type="text" name="username" required autofocus>
  <label for="password">Password</label>
  <div class="pw">
   <input id="password" type="password" name="password" required>
   <button type="button" data-pw="password" aria-label="Tampilkan password"><svg><use href="#eye"/></svg></button>
  </div>
  <button class="btn" type="submit">Login</button>
  <small>Belum punya akun? <a class="ul" href="register.php">Daftar sekarang</a></small>
 </form>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
