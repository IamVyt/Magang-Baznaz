<?php
require __DIR__ . '/php/fungsi.php';
wajib_login();

$galat = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $lama  = (string)($_POST['lama'] ?? '');
    $baru  = (string)($_POST['baru'] ?? '');
    $ulang = (string)($_POST['ulang'] ?? '');
    $user  = q_one($conn, "SELECT id, password FROM users WHERE id = ?", 'i', [(int)$_SESSION['user_id']]);

    if (!csrf_valid()) {
        $galat = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } elseif (!$user || !password_verify($lama, (string)$user['password'])) {
        $galat = 'Password lama tidak sesuai.';
    } elseif (strlen($baru) < 8) {
        $galat = 'Password baru minimal 8 karakter.';
    } elseif ($baru !== $ulang) {
        $galat = 'Konfirmasi password baru tidak sama.';
    } elseif ($baru === $lama) {
        $galat = 'Password baru harus berbeda dari password lama.';
    } else {
        q($conn, "UPDATE users SET password = ? WHERE id = ?", 'si', [password_hash($baru, PASSWORD_DEFAULT), $user['id']]);
        catat_audit($conn, 'ganti_password', 'users', (int)$user['id']);
        flash('success', 'Password berhasil diganti.');
        redirect(is_staff() ? 'dashboard.php' : 'index.php');
    }
}

$title = 'Ganti password';
$aktif = 'password';
include __DIR__ . '/php/app_header.php';
?>
<section class="top">
 <div class="head"><h1 class="tag">Ganti password</h1><p>Gunakan password yang kuat dan jangan dibagikan ke siapa pun.</p></div>
 <form class="card pwform" method="post" autocomplete="off">
  <?php if ($galat !== ''): ?><div class="alert danger" role="alert"><?= e($galat) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <?php foreach (['lama' => 'Password lama', 'baru' => 'Password baru (minimal 8 karakter)', 'ulang' => 'Ulangi password baru'] as $n => $lbl): ?>
  <label for="<?= $n ?>"><?= $lbl ?></label>
  <div class="pw"><input id="<?= $n ?>" type="password" name="<?= $n ?>" required autocomplete="new-password"><button type="button" data-pw="<?= $n ?>" aria-label="Tampilkan password"><svg><use href="#eye"/></svg></button></div>
  <?php endforeach; ?>
  <button class="btn" type="submit">Simpan password</button>
 </form>
</section>
<?php include __DIR__ . '/php/app_footer.php'; ?>
