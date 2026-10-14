<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara', 'ketua']);

// ============================================================
// PROSES TAMBAH TRANSAKSI (Hanya Admin & Bendahara)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!can_input()) {
        flash('danger', 'Anda tidak memiliki izin untuk mencatat transaksi.');
        redirect('index.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('index.php');
    }
    $tipe        = $_POST['tipe'] ?? '';
    $kategori_id = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $pihak_id    = (int)($_POST['pihak_id'] ?? 0) ?: null;
    $nama        = trim($_POST['nama'] ?? '');
    $jumlah      = validasi_jumlah($_POST['jumlah'] ?? '');
    $tgl         = $_POST['tanggal'] ?? '';
    $keterangan  = trim($_POST['keterangan'] ?? '');

    if ($nama === '' && $pihak_id) {
        $p = q_one($conn, $tipe === 'masuk' ? "SELECT nama FROM muzakki WHERE id = ?" : "SELECT nama FROM mustahik WHERE id = ?", 'i', [$pihak_id]);
        if ($p) $nama = $p['nama'];
    }

    if ($nama === '' && $tipe === 'masuk') {
        $nama = 'Hamba Allah';
    }

    if (!in_array($tipe, ['masuk', 'keluar'], true)) {
        flash('danger', 'Jenis transaksi tidak valid.');
    } elseif ($nama === '' || mb_strlen($nama) > 150) {
        flash('danger', 'Nama pihak wajib diisi dan maksimal 150 karakter.');
    } elseif (!$kategori_id) {
        flash('danger', 'Kategori transaksi wajib dipilih.');
    } elseif ($jumlah === false) {
        flash('danger', 'Jumlah harus berupa angka bulat lebih dari 0.');
    } elseif (!tanggal_valid($tgl) || $tgl > date('Y-m-d')) {
        flash('danger', 'Tanggal tidak valid atau melebihi hari ini.');
    } else {
        $new_id  = null;
        $user_id = $_SESSION['user_id'] ?? null;
        $galat   = jalankan_transaksi($conn, function () use ($conn, $tipe, $kategori_id, $pihak_id, $nama, $jumlah, $tgl, $keterangan, $user_id, &$new_id) {
            q($conn,
                "INSERT INTO transaksi (tipe, kategori_id, pihak_id, nama_pihak, jumlah, tanggal, keterangan, dibuat_oleh)
                 VALUES (?, ?, ?, ?, ?, TIMESTAMP(?, CURTIME()), ?, ?)",
                'siisdssi',
                [$tipe, $kategori_id, $pihak_id, $nama, $jumlah, $tgl, $keterangan ?: null, $user_id]);
            $new_id = mysqli_insert_id($conn);
        });
        if ($galat) {
            flash('danger', $galat);
        } else {
            catat_audit($conn, 'tambah', 'transaksi', $new_id, null, [
                'nama'        => $nama,
                'tipe'        => $tipe,
                'kategori_id' => $kategori_id,
                'jumlah'      => $jumlah,
                'tanggal'     => $tgl,
            ]);
            flash('success', ($tipe === 'masuk' ? 'Pemasukan' : 'Pengeluaran') . ' kas berhasil dicatat.');
        }
    }
    redirect('index.php');
}

// ============================================================
// DATA DASHBOARD & FILTER
// ============================================================
$kategori_masuk  = q_all($conn, "SELECT id, nama FROM kategori_dana WHERE tipe = 'masuk' AND aktif = 1 ORDER BY nama ASC");
$kategori_keluar = q_all($conn, "SELECT id, nama FROM kategori_dana WHERE tipe = 'keluar' AND aktif = 1 ORDER BY nama ASC");
$semua_kategori  = q_all($conn, "SELECT id, nama, tipe FROM kategori_dana WHERE aktif = 1 ORDER BY tipe ASC, nama ASC");
$muzakki_list    = q_all($conn, "SELECT id, nama, nik_nip FROM muzakki WHERE aktif = 1 ORDER BY nama ASC");
$mustahik_list   = q_all($conn, "SELECT id, nama, asnaf FROM mustahik WHERE aktif = 1 ORDER BY nama ASC");

$f  = filter_transaksi(true);   // untuk tabel riwayat
$ft = filter_transaksi(false);  // untuk kartu total
$adaFilter = $f['cari'] !== '' || $f['mulai'] !== '' || $f['selesai'] !== '' || $f['tipe'] !== 'semua' || $f['kategori'] > 0;

$saldo = hitung_saldo($conn);

$total = ['masuk' => 0, 'keluar' => 0];
$jumlahTrx = ['masuk' => 0, 'keluar' => 0];
foreach (q_all($conn,
    "SELECT tipe, COALESCE(SUM(jumlah),0) AS total, COUNT(*) AS n FROM " . TRANSAKSI . " WHERE dibatalkan_at IS NULL " . $ft['sql'] . " GROUP BY tipe",
    $ft['types'], $ft['params']) as $r) {
    $total[$r['tipe']] = (float)$r['total'];
    $jumlahTrx[$r['tipe']] = (int)$r['n'];
}

// Riwayat + pagination
$perHal = 8;
$totalBaris = (int)q_one($conn, "SELECT COUNT(*) AS c FROM " . TRANSAKSI . " WHERE 1=1 " . $f['sql'], $f['types'], $f['params'])['c'];
$totalHal = max(1, (int)ceil($totalBaris / $perHal));
$hal = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$offset = ($hal - 1) * $perHal;
$riwayat = q_all($conn,
    "SELECT * FROM " . TRANSAKSI . " WHERE 1=1 " . $f['sql'] . " ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?",
    $f['types'] . 'ii', array_merge($f['params'], [$perHal, $offset]));

$qs = array_filter([
    'cari'        => $f['cari'],
    'tgl_mulai'   => $f['mulai'],
    'tgl_selesai' => $f['selesai'],
    'tipe'        => $f['tipe'] === 'semua' ? '' : $f['tipe'],
    'kategori'    => $f['kategori'] > 0 ? $f['kategori'] : '',
], fn($v) => $v !== '');

// Data grafik 6 bulan terakhir
$namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulan = []; $label = [];
$d = new DateTime('first day of this month');
$d->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $bulan[$d->format('Y-m')] = ['masuk' => 0, 'keluar' => 0];
    $label[] = $namaBulan[(int)$d->format('n') - 1] . ' ' . $d->format('y');
    $d->modify('+1 month');
}
foreach (q_all($conn,
    "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bln, tipe, SUM(jumlah) AS total FROM " . TRANSAKSI . " WHERE dibatalkan_at IS NULL AND tanggal >= ? GROUP BY bln, tipe",
    's', [array_key_first($bulan) . '-01 00:00:00']) as $r) {
    if (isset($bulan[$r['bln']])) $bulan[$r['bln']][$r['tipe']] = (float)$r['total'];
}
$grafikMasuk  = array_column($bulan, 'masuk');
$grafikKeluar = array_column($bulan, 'keluar');

$judul = 'Dashboard Kas';
$aktif = 'dashboard';
require 'partials/header.php';
?>

<!-- ============================================================
     1. KARTU STATISTIK (4 KARTU BERSIH SEPERTI MOCKUP)
     ============================================================ -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-modern">
            <div>
                <div class="stat-icon-box stat-icon-blue">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-card-label">Saldo Kas Saat Ini</div>
                <div class="stat-card-val text-primary"><?= rupiah($saldo) ?></div>
            </div>
            <div class="stat-card-sub text-muted">Akumulasi total dana kas</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-modern">
            <div>
                <div class="stat-icon-box stat-icon-green">
                    <i class="bi bi-arrow-down-left-circle"></i>
                </div>
                <div class="stat-card-label">Total Pemasukan</div>
                <div class="stat-card-val text-success"><?= rupiah($total['masuk']) ?></div>
            </div>
            <div class="stat-card-sub text-muted"><?= $jumlahTrx['masuk'] ?> donasi diterima</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-modern">
            <div>
                <div class="stat-icon-box stat-icon-yellow">
                    <i class="bi bi-arrow-up-right-circle"></i>
                </div>
                <div class="stat-card-label">Total Pengeluaran</div>
                <div class="stat-card-val" style="color: #D97706;"><?= rupiah($total['keluar']) ?></div>
            </div>
            <div class="stat-card-sub text-muted"><?= $jumlahTrx['keluar'] ?> transaksi tersalurkan</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-modern">
            <div>
                <div class="stat-icon-box stat-icon-purple">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div class="stat-card-label">Aktivitas Transaksi</div>
                <div class="stat-card-val text-dark"><?= $jumlahTrx['masuk'] + $jumlahTrx['keluar'] ?></div>
            </div>
            <div class="stat-card-sub text-muted">Total mutasi kas tercatat</div>
        </div>
    </div>
</div>

<!-- ============================================================
     2. SEGMENTED FILTER / TAB PILL (SEPERTI NEW/IN PROGRESS DI MOCKUP)
     ============================================================ -->
<div class="filter-segmented-bar">
    <div class="filter-pills-group">
        <a href="?<?= e(http_build_query(array_merge($qs, ['tipe' => 'semua', 'hal' => 1]))) ?>"
           class="filter-pill <?= $f['tipe'] === 'semua' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i> Semua Transaksi
        </a>
        <a href="?<?= e(http_build_query(array_merge($qs, ['tipe' => 'masuk', 'hal' => 1]))) ?>"
           class="filter-pill <?= $f['tipe'] === 'masuk' ? 'active' : '' ?>">
            <i class="bi bi-arrow-down-left text-success"></i> Pemasukan
        </a>
        <a href="?<?= e(http_build_query(array_merge($qs, ['tipe' => 'keluar', 'hal' => 1]))) ?>"
           class="filter-pill <?= $f['tipe'] === 'keluar' ? 'active' : '' ?>">
            <i class="bi bi-arrow-up-right text-warning"></i> Pengeluaran
        </a>
    </div>

    <div class="filter-actions-group">
        <!-- Tombol Catat Transaksi Cepat -->
        <?php if (can_input()): ?>
        <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambah" data-tipe="masuk">
            <i class="bi bi-plus-lg me-1"></i>Pemasukan
        </button>
        <button type="button" class="btn-petro-yellow" data-bs-toggle="modal" data-bs-target="#modalTambah" data-tipe="keluar">
            <i class="bi bi-dash-lg me-1"></i>Pengeluaran
        </button>
        <?php endif; ?>

        <!-- Tombol Buka Filter Pencarian -->
        <button type="button" class="btn-petro-outline" data-bs-toggle="collapse" data-bs-target="#boxFilterLanjutan" aria-expanded="<?= $adaFilter ? 'true' : 'false' ?>">
            <i class="bi bi-funnel me-1"></i>Filter
            <?php if ($adaFilter): ?><span class="badge bg-primary ms-1" style="font-size:0.65rem;">Aktif</span><?php endif; ?>
        </button>

        <!-- Unduh Excel -->
        <a href="export_excel.php?<?= e(http_build_query($qs)) ?>" class="btn-petro-outline" title="Unduh Laporan Excel">
            <i class="bi bi-file-earmark-spreadsheet text-success me-1"></i>Excel
        </a>
    </div>
</div>

<!-- Kotak Filter Pencarian (Bisa di-collapse) -->
<div class="collapse <?= $adaFilter ? 'show' : '' ?> mb-4" id="boxFilterLanjutan">
    <div class="dashboard-card p-3 shadow-sm border">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tipe" value="<?= e($f['tipe']) ?>">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Cari Nama / Keterangan</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="cari" class="form-control border-start-0" placeholder="Ketik nama / catatan..." value="<?= e($f['cari']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Kategori Dana</label>
                <select name="kategori" class="form-select">
                    <option value="">-- Semua Kategori Dana --</option>
                    <?php foreach ($semua_kategori as $k): ?>
                        <option value="<?= (int)$k['id'] ?>" <?= $f['kategori'] === (int)$k['id'] ? 'selected' : '' ?>>
                            [<?= $k['tipe'] === 'masuk' ? 'Masuk' : 'Keluar' ?>] <?= e($k['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control" value="<?= e($f['mulai']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control" value="<?= e($f['selesai']) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn-petro-primary flex-fill"><i class="bi bi-check2 me-1"></i>Terapkan</button>
                <a href="index.php" class="btn-petro-outline" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     3. SPLIT DUA KOLOM: KIRI (TABEL DATA) & KANAN (GRAFIK RINGKASAN)
     ============================================================ -->
<div class="row g-4">
    <!-- Kolom Kiri: Tabel Riwayat Transaksi (8 Kolom) -->
    <div class="col-lg-8">
        <div class="dashboard-card">
            <div class="dashboard-card-head">
                <div>
                    <h2 class="dashboard-card-title">
                        <i class="bi bi-clock-history text-primary"></i>Riwayat Transaksi
                    </h2>
                    <span class="text-muted small">Menampilkan <?= $totalBaris ?> data transaksi</span>
                </div>
                <?php if ($adaFilter): ?>
                    <span class="badge-friendly badge-masuk">
                        <i class="bi bi-funnel-fill"></i> Filter Diterapkan
                    </span>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama / Keterangan</th>
                            <th>Jenis</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$riwayat): ?>
                        <tr>
                            <td colspan="5">
                                <!-- EMPTY STATE RAMAH & IMUT SEPERTI DI MOCKUP "NO ORDER FOUND" -->
                                <div class="empty-state-friendly">
                                    <div class="empty-doc-icon">
                                        <i class="bi bi-file-earmark-text text-primary" style="font-size: 2rem;"></i>
                                        <div class="empty-doc-plus"><i class="bi bi-plus-lg"></i></div>
                                    </div>
                                    <div class="empty-state-title">Tidak Ada Transaksi</div>
                                    <div class="empty-state-sub">Belum ada pencatatan transaksi yang cocok dengan kriteria yang Anda cari.</div>
                                    <?php if (can_input()): ?>
                                    <button type="button" class="btn-petro-green mt-3" data-bs-toggle="modal" data-bs-target="#modalTambah" data-tipe="masuk">
                                        <i class="bi bi-plus-lg me-1"></i>Catat Transaksi Pertama
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($riwayat as $r): $masuk = ($r['tipe'] === 'masuk'); $isVoid = ($r['dibatalkan_at'] !== null); ?>
                            <tr class="<?= $isVoid ? 'opacity-50' : '' ?>" title="<?= $isVoid ? 'Void: '.e($r['alasan_batal']??'-') : '' ?>">
                                <td class="text-muted small text-nowrap">
                                    <i class="bi bi-calendar3 me-1 text-primary"></i><?= date('d/m/Y', strtotime($r['tanggal'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($r['nama']) ?></div>
                                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                        <?php if (!empty($r['kategori_nama'])): ?>
                                            <span class="badge bg-light text-primary border" style="font-size: 0.68rem; font-weight: 600;">
                                                <i class="bi bi-tag me-1"></i><?= e($r['kategori_nama']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['keterangan'])): ?>
                                            <small class="text-muted text-truncate" style="max-width: 180px;" title="<?= e($r['keterangan']) ?>">
                                                <i class="bi bi-info-circle me-1"></i><?= e($r['keterangan']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isVoid): ?>
                                    <span class="badge-friendly" style="background:#fee2e2;color:#b91c1c;">
                                        <span class="badge-dot" style="background:#ef4444;"></span>Void
                                    </span>
                                    <?php else: ?>
                                    <span class="badge-friendly <?= $masuk ? 'badge-masuk' : 'badge-keluar' ?>">
                                        <span class="badge-dot"></span>
                                        <?= $masuk ? 'Pemasukan' : 'Pengeluaran' ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap <?= $masuk ? 'nominal-in' : 'nominal-out' ?>">
                                    <?= $masuk ? '+' : '-' ?> <?= rupiah($r['jumlah']) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($isVoid): ?>
                                    <span class="text-danger small"><i class="bi bi-slash-circle"></i> Void</span>
                                    <?php elseif (can_input()): ?>
                                    <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn-icon-action" title="Ubah Data">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn-icon-action danger" title="Batalkan (Void)"
                                            data-bs-toggle="modal" data-bs-target="#modalVoid"
                                            data-tipe="<?= e($r['tipe']) ?>" data-id="<?= (int)$r['id'] ?>" data-nama="<?= e($r['nama']) ?>">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination di Bagian Bawah Tabel -->
            <?php if ($totalHal > 1): ?>
            <div class="dashboard-card-head border-top mt-auto py-3">
                <span class="text-muted small">Halaman <?= $hal ?> dari <?= $totalHal ?></span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link rounded-circle me-1 border-0" href="?<?= e(http_build_query($qs + ['hal' => $hal - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($i = max(1, $hal - 2); $i <= min($totalHal, $hal + 2); $i++): ?>
                            <li class="page-item <?= $i === $hal ? 'active' : '' ?>">
                                <a class="page-link rounded-circle me-1 border-0" href="?<?= e(http_build_query($qs + ['hal' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $hal >= $totalHal ? 'disabled' : '' ?>">
                            <a class="page-link rounded-circle border-0" href="?<?= e(http_build_query($qs + ['hal' => $hal + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Kanan: Ringkasan Arus Kas & Informasi UPZ (4 Kolom) -->
    <div class="col-lg-4">
        <!-- Card Grafik Arus Kas -->
        <div class="dashboard-card mb-4">
            <div class="dashboard-card-head">
                <h2 class="dashboard-card-title">
                    <i class="bi bi-bar-chart-line text-success"></i>Arus Kas Bulanan
                </h2>
                <span class="text-muted small">6 Bulan Terakhir</span>
            </div>
            <div class="dashboard-card-body">
                <div style="height: 220px; position: relative;">
                    <canvas id="grafikKeuangan"></canvas>
                </div>
            </div>
        </div>

        <!-- Card Informasi & Panduan UPZ BAZNAS -->
        <div class="dashboard-card">
            <div class="dashboard-card-head">
                <h2 class="dashboard-card-title">
                    <i class="bi bi-shield-check text-primary"></i>Unit Pengumpul Zakat
                </h2>
            </div>
            <div class="dashboard-card-body">
                <div class="p-3 mb-3 rounded-4" style="background: #F8FAFC; border: 1px solid var(--line-border);">
                    <div class="small fw-bold text-dark mb-1">PT Petrokimia Gresik</div>
                    <div class="text-muted small">Pengelolaan dana zakat, infak, dan sedekah terintegrasi secara aman, transparan, dan akuntabel.</div>
                </div>

                <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3" style="background: #F1F5F9;">
                    <span class="small text-muted"><i class="bi bi-person-circle me-2 text-primary"></i>Petugas Aktif</span>
                    <strong class="small text-dark"><?= e($_SESSION['nama'] ?? 'Admin') ?></strong>
                </div>

                <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background: #F1F5F9;">
                    <span class="small text-muted"><i class="bi bi-database me-2 text-success"></i>Status Database</span>
                    <span class="badge bg-success" style="font-size: 0.7rem;">Terhubung</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (can_input()): ?>
<!-- ============================================================
     4. MODAL CATAT TRANSAKSI (PEMASUKAN / PENGELUARAN)
     ============================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="tipe" id="tambahTipe" value="masuk">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" id="tambahIconBox" style="background: var(--pg-green-soft); color: var(--pg-green-dark); margin-bottom: 0;">
                        <i class="bi bi-arrow-down-left" id="tambahIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold" id="tambahJudul">Catat Pemasukan Kas</h5>
                        <p class="text-muted small mb-0">Masukkan rincian dana masuk atau keluar</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Pemilihan Pihak (Muzakki / Mustahik) -->
                <div class="mb-3" id="groupPihakMasuk">
                    <label class="form-label small fw-bold text-muted">Pilih Muzakki (Master Donatur)</label>
                    <select id="selectMuzakki" class="form-select select-pihak">
                        <option value="">-- Donatur Baru / Input Cepat --</option>
                        <?php foreach ($muzakki_list as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" data-nama="<?= e($m['nama']) ?>">
                                <?= e($m['nama']) ?> <?= !empty($m['nik_nip']) ? '('.e($m['nik_nip']).')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3" id="groupPihakKeluar" style="display:none;">
                    <label class="form-label small fw-bold text-muted">Pilih Mustahik (Master Penerima)</label>
                    <select id="selectMustahik" class="form-select select-pihak">
                        <option value="">-- Penerima Baru / Input Cepat --</option>
                        <?php foreach ($mustahik_list as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" data-nama="<?= e($m['nama']) ?>">
                                <?= e($m['nama']) ?> <?= !empty($m['asnaf']) ? '('.e(ucfirst($m['asnaf'])).')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" name="pihak_id" id="tambahPihakId" value="">

                <!-- Nama Donatur / Penerima -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-muted mb-0" id="tambahLabelNama">Nama Penyumbang / Donatur</label>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 text-muted" id="btnHambaAllah">
                            <i class="bi bi-magic me-1"></i>Isi "Hamba Allah"
                        </button>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="nama" id="tambahInputNama" class="form-control" placeholder="Contoh: Hamba Allah / Nama Donatur" maxlength="150" required>
                    </div>
                </div>

                <!-- Dropdown Kategori Dana -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" id="tambahLabelKategori">Kategori Dana <span class="text-danger">*</span></label>
                    <select name="kategori_id" id="tambahKategori" class="form-select" required>
                        <!-- Diisi secara dinamis via JavaScript -->
                    </select>
                </div>

                <!-- Nominal -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nominal (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-bold text-muted">Rp</span>
                        <input type="number" name="jumlah" class="form-control" min="1" step="1" placeholder="Contoh: 500000" required>
                    </div>
                </div>

                <!-- Tanggal Transaksi -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Tanggal Transaksi <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- Keterangan -->
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Keterangan / Catatan Tambahan</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan transaksi bila ada..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-green px-4" id="tambahSimpan">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     5. MODAL VOID (BATALKAN) TRANSAKSI
     ============================================================ -->
<div class="modal fade" id="modalVoid" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="hapus.php" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="tipe" id="voidTipe">
            <input type="hidden" name="id" id="voidId">
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon-box mx-0" style="background:#fee2e2;color:#b91c1c;width:48px;height:48px;font-size:1.4rem;flex-shrink:0;">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Batalkan Transaksi?</h5>
                        <p class="text-muted small mb-0">Transaksi atas nama <strong id="voidNama" class="text-dark"></strong> akan di-void. Data tetap tersimpan dalam riwayat.</p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="voidAlasan">Alasan Pembatalan <span class="text-danger">*</span></label>
                    <textarea id="voidAlasan" name="alasan" class="form-control" rows="2" placeholder="Contoh: Data duplikat / Kesalahan input" required maxlength="255"></textarea>
                </div>
                <div class="alert alert-warning py-2 px-3 small border-0 mb-0" style="border-radius:10px;">
                    <i class="bi bi-info-circle me-1"></i>Transaksi yang dibatalkan tidak dihitung ke saldo, namun tetap tampil dengan penanda void.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger rounded-3 fw-bold px-4">Batalkan (Void)</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    var kategoriMasukData  = <?= json_encode($kategori_masuk) ?>;
    var kategoriKeluarData = <?= json_encode($kategori_keluar) ?>;

    // Modal Tambah: Konfigurasi dinamis jenis Pemasukan / Pengeluaran
    var modalTambahEl = document.getElementById('modalTambah');
    if (modalTambahEl) {
        modalTambahEl.addEventListener('show.bs.modal', function (ev) {
            var tipe = (ev.relatedTarget && ev.relatedTarget.dataset.tipe) || 'masuk';
            var masuk = (tipe === 'masuk');
            document.getElementById('tambahTipe').value = tipe;
            document.getElementById('tambahJudul').textContent = masuk ? 'Catat Pemasukan Kas' : 'Catat Penyaluran / Pengeluaran';
            document.getElementById('tambahLabelNama').textContent = masuk ? 'Nama Penyumbang / Donatur' : 'Nama Penerima Manfaat';
            document.getElementById('tambahLabelKategori').textContent = masuk ? 'Kategori Pemasukan' : 'Program Penyaluran';
            
            var iconBox = document.getElementById('tambahIconBox');
            var icon = document.getElementById('tambahIcon');
            var btn = document.getElementById('tambahSimpan');
            var groupMasuk  = document.getElementById('groupPihakMasuk');
            var groupKeluar = document.getElementById('groupPihakKeluar');
            var btnHamba    = document.getElementById('btnHambaAllah');
            var katSelect   = document.getElementById('tambahKategori');

            // Reset pihak
            document.getElementById('tambahPihakId').value = '';
            document.getElementById('selectMuzakki').value = '';
            document.getElementById('selectMustahik').value = '';
            document.getElementById('tambahInputNama').value = '';

            // Populate kategori sesuai tipe
            katSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
            var kList = masuk ? kategoriMasukData : kategoriKeluarData;
            kList.forEach(function (k) {
                var opt = document.createElement('option');
                opt.value = k.id;
                opt.textContent = k.nama;
                katSelect.appendChild(opt);
            });

            if (masuk) {
                iconBox.style.background = 'var(--pg-green-soft)';
                iconBox.style.color = 'var(--pg-green-dark)';
                icon.className = 'bi bi-arrow-down-left';
                btn.className = 'btn-petro-green px-4';
                groupMasuk.style.display = 'block';
                groupKeluar.style.display = 'none';
                btnHamba.style.display = 'inline-block';
            } else {
                iconBox.style.background = 'var(--pg-yellow-soft)';
                iconBox.style.color = 'var(--pg-yellow-dark)';
                icon.className = 'bi bi-arrow-up-right';
                btn.className = 'btn-petro-yellow px-4';
                groupMasuk.style.display = 'none';
                groupKeluar.style.display = 'block';
                btnHamba.style.display = 'none';
            }
        });

        // Event listener saat memilih Muzakki
        document.getElementById('selectMuzakki').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.dataset.nama) {
                document.getElementById('tambahInputNama').value = opt.dataset.nama;
                document.getElementById('tambahPihakId').value = opt.value;
            } else {
                document.getElementById('tambahPihakId').value = '';
            }
        });

        // Event listener saat memilih Mustahik
        document.getElementById('selectMustahik').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.dataset.nama) {
                document.getElementById('tambahInputNama').value = opt.dataset.nama;
                document.getElementById('tambahPihakId').value = opt.value;
            } else {
                document.getElementById('tambahPihakId').value = '';
            }
        });

        // Tombol cepat Isi Hamba Allah
        document.getElementById('btnHambaAllah').addEventListener('click', function () {
            document.getElementById('tambahInputNama').value = 'Hamba Allah';
            document.getElementById('tambahPihakId').value = '';
            document.getElementById('selectMuzakki').value = '';
        });
    }

    // Modal Void: Isi data transaksi yang dipilih
    var modalVoidEl = document.getElementById('modalVoid');
    if (modalVoidEl) {
        modalVoidEl.addEventListener('show.bs.modal', function (ev) {
            var b = ev.relatedTarget;
            document.getElementById('voidTipe').value = b.dataset.tipe;
            document.getElementById('voidId').value = b.dataset.id;
            document.getElementById('voidNama').textContent = b.dataset.nama;
            document.getElementById('voidAlasan').value = '';
        });
    }

    // Chart.js Arus Kas 6 Bulan (Clean & Modern)
    function ringkasAngka(v) {
        if (v >= 1000000000) return 'Rp ' + (v / 1000000000).toLocaleString('id-ID') + ' M';
        if (v >= 1000000) return 'Rp ' + (v / 1000000).toLocaleString('id-ID') + ' jt';
        if (v >= 1000) return 'Rp ' + (v / 1000).toLocaleString('id-ID') + ' rb';
        return 'Rp ' + v;
    }

    new Chart(document.getElementById('grafikKeuangan'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($label) ?>,
            datasets: [
                {
                    label: 'Pemasukan',
                    data: <?= json_encode($grafikMasuk) ?>,
                    backgroundColor: '#10B981',
                    borderRadius: 8,
                    maxBarThickness: 18
                },
                {
                    label: 'Pengeluaran',
                    data: <?= json_encode($grafikKeluar) ?>,
                    backgroundColor: '#F59E0B',
                    borderRadius: 8,
                    maxBarThickness: 18
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: { family: 'Plus Jakarta Sans', size: 11 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            return c.dataset.label + ': Rp ' + c.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } } },
                y: { beginAtZero: true, ticks: { callback: ringkasAngka, font: { family: 'Plus Jakarta Sans', size: 10 } }, grid: { color: '#F1F5F9' } }
            }
        }
    });
</script>
</body>
</html>
