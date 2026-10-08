<?php
$judul = $judul ?? 'Dashboard';
$aktif = $aktif ?? 'dashboard';
$flash = ambil_flash();
$css_ver = @filemtime(__DIR__ . '/../assets/style.css') ?: time();
$namaUser = $_SESSION['nama'] ?? 'Administrator';
$inisialUser = strtoupper(substr($namaUser, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | UPZ BAZNAS Petrokimia Gresik</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=<?= $css_ver ?>" rel="stylesheet">
</head>
<body>

<!-- Overlay untuk Sidebar di Layar HP -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-container">
    <!-- SIDEBAR KIRI (SEPERTI MOCKUP OLATELINK) -->
    <aside class="app-sidebar" id="appSidebar">
        <div>
            <!-- Brand Logo -->
            <a href="index.php" class="sidebar-brand">
                <div class="brand-icon-sq">UPZ</div>
                <div>
                    <div class="brand-title">SIM Keuangan</div>
                    <div class="brand-subtitle">BAZNAS Petrokimia</div>
                </div>
            </a>

            <!-- Brand / Unit Selector Pill -->
            <div class="brand-selector-pill">
                <span><i class="bi bi-building me-2 text-primary"></i>Unit Pengumpul Zakat</span>
                <i class="bi bi-chevron-down"></i>
            </div>

            <!-- Menu Navigasi -->
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item-btn <?= $aktif === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Transaksi Kas</span>
                </a>
                <a href="users.php" class="nav-item-btn <?= $aktif === 'users' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Kelola Pengguna</span>
                </a>
                <a href="export_excel.php" class="nav-item-btn">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>Ekspor Excel</span>
                </a>
                <a href="logout.php" class="nav-item-btn text-danger mt-2" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar Sistem</span>
                </a>
            </nav>
        </div>

        <!-- Kartu Bawah Sidebar -->
        <div class="sidebar-footer-card">
            <span class="mini-badge">PETROKIMIA GRESIK</span>
            <div class="fw-bold small text-dark">Sistem Informasi Manajemen</div>
            <div class="text-muted" style="font-size: 0.72rem;">Dana Zakat & Donasi</div>
        </div>
    </aside>

    <!-- MAIN AREA KONTEN -->
    <main class="app-main">
        <!-- TOPBAR ATAS -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button type="button" class="btn btn-petro-outline btn-sm d-lg-none" id="btnToggleSidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="topbar-title"><?= e($judul) ?></h1>
                    <p class="topbar-sub">Selamat datang kembali, <strong><?= e($namaUser) ?></strong></p>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Live Clock Badge (seperti di mockup) -->
                <div class="time-badge">
                    <i class="bi bi-clock me-1 text-primary"></i>
                    <span id="realtimeClock">--:-- WIB</span>
                </div>

                <!-- Notifikasi / Cepat Pengguna -->
                <a href="users.php" class="icon-circle-btn" title="Kelola Pengguna & Akun">
                    <i class="bi bi-bell"></i>
                </a>

                <!-- User Profile Pill (seperti Audrey Manager di mockup) -->
                <div class="dropdown">
                    <button class="user-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-circle"><?= e($inisialUser) ?></div>
                        <div class="d-none d-sm-block text-start">
                            <strong><?= e($namaUser) ?></strong>
                            <small>Petugas UPZ</small>
                        </div>
                        <i class="bi bi-chevron-down ms-1 text-muted" style="font-size: 0.75rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 14px; padding: 8px;">
                        <li><a class="dropdown-item py-2" href="users.php"><i class="bi bi-person-gear me-2 text-primary"></i>Kelola Pengguna</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Alert Notifikasi -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipe']) ?> alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius: 14px;" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= e($flash['pesan']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>
