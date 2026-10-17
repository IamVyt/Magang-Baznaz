<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include __DIR__ . '/sprite.php'; ?>

<div class="stage"><div class="sheet"><div class="w">
<nav>
 <a class="logo" href="#"><svg width="26" height="26" fill="currentColor"><use href="#st"/></svg><?= e($site['nama']) ?></a>
 <button class="burger" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="menu"><span></span></button>
 <ul id="menu">
  <?php foreach ($menu as $id => $label): ?>
  <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
  <?php endforeach; ?>
  <li><a href="salurkan.php">Salurkan</a></li>
  <?php if (($_SESSION['status'] ?? '') === 'login'): ?>
  <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'bendahara', 'ketua'], true)): ?><li><a href="dashboard.php">Dashboard</a></li><?php endif; ?>
  <li><a href="donasi_saya.php">Donasi saya</a></li>
  <li><a class="btn o" href="logout.php">Keluar (<?= e(strtok($_SESSION['nama'] ?? 'Akun', ' ')) ?>)</a></li>
  <?php else: ?>
  <li><a class="btn o" href="login.php">Login</a></li>
  <?php endif; ?>
 </ul>
</nav>
<?php if (!empty($_SESSION['flash'])): $fl = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<div class="alert <?= e($fl['tipe']) ?>" role="status"><?= e($fl['pesan']) ?></div>
<?php endif; ?>
