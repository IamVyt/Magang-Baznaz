<?php
require __DIR__ . '/php/fungsi.php';

if (($_SESSION['status'] ?? '') === 'login') redirect(is_staff() ? 'dashboard.php' : 'index.php');

$galat = [];
$isi = ['nama' => '', 'username' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isi['nama']     = trim($_POST['nama'] ?? '');
    $isi['username'] = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $ulang    = (string)($_POST['ulang'] ?? '');

    if (!csrf_valid()) {
        $galat[] = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        if (mb_strlen($isi['nama']) < 2 || mb_strlen($isi['nama']) > 100) $galat[] = 'Nama lengkap harus 2 sampai 100 karakter.';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $isi['username'])) $galat[] = 'Username 4 sampai 30 karakter, hanya huruf, angka, titik, dan garis bawah.';
        if (strlen($password) < 8) $galat[] = 'Password minimal 8 karakter.';
        if ($password !== $ulang) $galat[] = 'Konfirmasi password tidak sama.';
        if (!$galat && q_one($conn, "SELECT id FROM users WHERE LOWER(username) = LOWER(?)", 's', [$isi['username']])) {
            $galat[] = 'Username sudah dipakai. Silakan pilih yang lain.';
        }
    }

    if (!$galat) {
        // Pendaftaran publik selalu berperan "user". Peran petugas hanya bisa diberikan admin
        q($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'user')",
            'sss', [$isi['nama'], $isi['username'], password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)mysqli_insert_id($conn);
        $_SESSION['username'] = $isi['username'];
        $_SESSION['nama']     = $isi['nama'];
        $_SESSION['role']     = 'user';
        $_SESSION['status']   = 'login';
        catat_audit($conn, 'register');
        flash('success', 'Pendaftaran berhasil. Selamat datang, ' . $isi['nama'] . '!');
        redirect(ambil_tujuan() ?? 'index.php');   // kembali ke form donasi bila pendaftar datang dari sana
    }
}

$title = 'Daftar';
include __DIR__ . '/php/app_header.php';
?>

<section class="auth">
 <div class="c g">
  <h1>Mari berbagi kebaikan bersama</h1>
  <p>Buat akun untuk mengikuti program zakat, infak, dan sedekah UPZ BAZNAS Petrokimia Gresik.</p>
  <img class="mas" src="img/maskot.svg" alt="Maskot UPZ BAZNAS: koin ramah yang membawa hati" width="340" height="342" decoding="async">
 </div>

 <form class="c l form" method="post" autocomplete="off">
  <h2><span class="tg">Daftar</span></h2>
  <p>Isi data berikut untuk membuat akun.</p>
  <?php if ($galat): ?><div class="alert danger" role="alert"><?= e(implode(' ', $galat)) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label for="nama">Nama lengkap</label>
  <input id="nama" type="text" name="nama" value="<?= e($isi['nama']) ?>" maxlength="100" required autofocus>
  <label for="username">Username</label>
  <input id="username" type="text" name="username" value="<?= e($isi['username']) ?>" maxlength="30" required>
  <label for="password">Password</label>
  <div class="pw">
   <input id="password" type="password" name="password" minlength="8" required>
   <button type="button" data-pw="password" aria-label="Tampilkan password"><svg><use href="#eye"/></svg></button>
  </div>
  <label for="ulang">Ulangi password</label>
  <div class="pw">
   <input id="ulang" type="password" name="ulang" minlength="8" required>
   <button type="button" data-pw="ulang" aria-label="Tampilkan password"><svg><use href="#eye"/></svg></button>
  </div>
  <button class="btn" type="submit">Buat akun</button>
  <small>Sudah punya akun? <a class="ul" href="login.php">Login di sini</a></small>
 </form>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
