<?php
// Kerangka atas halaman sistem. Petugas (admin, bendahara, ketua) memakai sidebar.
// Tamu dan user biasa memakai navigasi atas seperti landing page.
// Variabel: $title, $aktif (menu sidebar yang menyala), $scripts (opsional)
$flash   = ambil_flash();
$masuk   = (($_SESSION['status'] ?? '') === 'login');
$sidebar = $masuk && is_staff() && empty($publik);   // halaman donatur memasang $publik = true agar tampil dengan navigasi atas
$menunggu = $sidebar ? donasi_menunggu($conn) : 0;   // donasi online yang menunggu verifikasi (lencana menu)
$aktif   = $aktif ?? '';
$peran   = $_SESSION['role'] ?? '';
$semua   = ['admin', 'bendahara', 'ketua'];
$menuSamping = [
    ['dashboard', 'dashboard.php', 'Dashboard kas', 'i-home', $semua],
    ['donasi', 'donasi_masuk.php', 'Donasi masuk', 'i-inbox', $semua],
    ['pembayaran', 'pembayaran.php', 'Metode pembayaran', 'i-card', $semua],
    ['muzakki', 'muzakki.php', 'Data muzakki', 'i-heart', $semua],
    ['mustahik', 'mustahik.php', 'Data mustahik', 'i-user', $semua],
    ['program', 'program.php', 'Program dan kategori', 'i-tag', $semua],
    ['users', 'users.php', 'Kelola pengguna', 'i-users', ['admin']],
    ['audit', 'audit.php', 'Riwayat aktivitas', 'i-clock', ['admin']],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> | UPZ BAZNAS Petrokimia Gresik</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/app.css">
</head>
<body class="<?= $sidebar ? 'dash' : '' ?>">
<?php include __DIR__ . '/sprite.php'; ?>

<div class="stage"><div class="sheet">
<?php if ($sidebar): ?>
<div class="shell">
 <aside class="sidenav" id="sidenav">
  <a class="logo" href="dashboard.php"><svg width="26" height="26" fill="currentColor"><use href="#st"/></svg>UPZ BAZNAS PG</a>
  <span class="rolechip">Panel <?= e($peran) ?></span>
  <div class="menu">
   <?php foreach ($menuSamping as [$kode, $url, $label, $ikon, $boleh]): if (!in_array($peran, $boleh, true)) continue; ?>
   <a class="item <?= $aktif === $kode ? 'on' : '' ?>" href="<?= $url ?>"><svg class="ico"><use href="#<?= $ikon ?>"/></svg><?= e($label) ?><?php if ($kode === 'donasi' && $menunggu > 0): ?><span class="lencana" title="Menunggu verifikasi"><?= (int)$menunggu ?></span><?php endif; ?></a>
   <?php endforeach; ?>
  </div>
  <div class="me">
   <b><?= e($_SESSION['nama'] ?? 'Petugas') ?></b><small><?= e($peran) ?></small>
   <a class="item" href="index.php"><svg class="ico"><use href="#i-site"/></svg>Beranda situs</a>
   <a class="item <?= $aktif === 'password' ? 'on' : '' ?>" href="ganti_password.php"><svg class="ico"><use href="#i-key"/></svg>Ganti password</a>
   <a class="item" href="logout.php"><svg class="ico"><use href="#i-out"/></svg>Keluar</a>
  </div>
 </aside>
 <div class="scrim" data-sb></div>
 <main class="main">
  <div class="mbar">
   <button class="sbbtn" type="button" data-sb aria-label="Buka menu"><svg class="ico"><use href="#i-menu"/></svg></button>
   <span class="logo"><svg width="22" height="22" fill="currentColor"><use href="#st"/></svg>UPZ BAZNAS PG</span>
  </div>
  <?php if ($flash): ?><div class="alert <?= e($flash['tipe']) ?>" role="status"><?= e($flash['pesan']) ?></div><?php endif; ?>
<?php else: ?>
<div class="w">
<nav>
 <a class="logo" href="index.php"><svg width="26" height="26" fill="currentColor"><use href="#st"/></svg>UPZ BAZNAS PG</a>
 <button class="burger" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="menu"><span></span></button>
 <ul id="menu">
  <li><a href="index.php">Beranda</a></li>
  <li><a href="salurkan.php">Salurkan</a></li>
  <?php if ($masuk): ?>
  <?php if (is_staff()): ?><li><a href="dashboard.php">Dashboard</a></li><?php endif; ?>
  <li><a href="donasi_saya.php">Donasi saya</a></li>
  <li><a href="ganti_password.php">Ganti password</a></li>
  <li><a class="btn o" href="logout.php">Keluar (<?= e(strtok($_SESSION['nama'] ?? 'Akun', ' ')) ?>)</a></li>
  <?php else: ?>
  <?php if (!in_array(basename($_SERVER['SCRIPT_NAME']), ['login.php', 'register.php'], true)): ?><li><a href="login.php">Login</a></li><?php endif; ?>
  <?php if (basename($_SERVER['SCRIPT_NAME']) === 'register.php'): ?><li><a class="btn o" href="login.php">Login</a></li><?php else: ?><li><a class="btn o" href="register.php">Daftar</a></li><?php endif; ?>
  <?php endif; ?>
 </ul>
</nav>
<?php if ($flash): ?><div class="alert <?= e($flash['tipe']) ?>" role="status"><?= e($flash['pesan']) ?></div><?php endif; ?>
<?php endif; ?>
