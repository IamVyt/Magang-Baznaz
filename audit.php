<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin']); // Hanya admin

// ---- Filter ----
$tgl_mulai = tanggal_valid($_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : '';
$tgl_selesai = tanggal_valid($_GET['tgl_selesai'] ?? '') ? $_GET['tgl_selesai'] : '';
$filter_user = trim($_GET['user'] ?? '');
$filter_aksi = trim($_GET['aksi'] ?? '');

$where = '1=1'; $types = ''; $params = [];

$aksi_valid = ['login','logout','tambah','edit','void','hapus_user','tambah_user','edit_user','ganti_password'];

if ($tgl_mulai !== '') {
    $where .= " AND al.waktu >= ?"; $types .= 's'; $params[] = $tgl_mulai . ' 00:00:00';
}
if ($tgl_selesai !== '') {
    $where .= " AND al.waktu < DATE_ADD(?, INTERVAL 1 DAY)"; $types .= 's'; $params[] = $tgl_selesai;
}
if ($filter_user !== '') {
    $where .= " AND al.username LIKE ?"; $types .= 's'; $params[] = '%' . addcslashes($filter_user, '%_\\') . '%';
}
if ($filter_aksi !== '' && in_array($filter_aksi, $aksi_valid, true)) {
    $where .= " AND al.aksi = ?"; $types .= 's'; $params[] = $filter_aksi;
}

// Pagination
$halaman = max(1, (int)($_GET['hal'] ?? 1));
$per_hal  = 25;
$offset   = ($halaman - 1) * $per_hal;

$total_row = (int)q_one($conn, "SELECT COUNT(*) AS n FROM audit_log al WHERE $where", $types, $params)['n'];
$total_hal = max(1, (int)ceil($total_row / $per_hal));
if ($halaman > $total_hal) $halaman = $total_hal;

$log_types = $types . 'ii';
$log_params = array_merge($params, [$per_hal, $offset]);
$logs = q_all($conn,
    "SELECT al.id, al.username, al.aksi, al.tabel, al.record_id, al.ip, al.waktu,
            al.data_lama, al.data_baru
       FROM audit_log al
      WHERE $where
      ORDER BY al.waktu DESC
      LIMIT ? OFFSET ?",
    $log_types, $log_params);

// Daftar user untuk filter dropdown
$user_list = q_all($conn, "SELECT DISTINCT username FROM audit_log ORDER BY username");

$ikon_aksi = [
    'login'          => ['bi bi-box-arrow-in-right', '#22c55e'],
    'logout'         => ['bi bi-box-arrow-right',    '#64748b'],
    'tambah'         => ['bi bi-plus-circle-fill',   '#3b82f6'],
    'edit'           => ['bi bi-pencil-fill',         '#f59e0b'],
    'void'           => ['bi bi-slash-circle-fill',   '#ef4444'],
    'hapus_user'     => ['bi bi-person-dash-fill',    '#ef4444'],
    'tambah_user'    => ['bi bi-person-plus-fill',    '#3b82f6'],
    'edit_user'      => ['bi bi-person-gear',         '#f59e0b'],
    'ganti_password' => ['bi bi-key-fill',            '#8b5cf6'],
];

$judul = 'Riwayat Aktivitas';
$aktif = 'audit';
require 'partials/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Riwayat Aktivitas Sistem</h2>
        <p class="text-muted small mb-0">Log semua aktivitas petugas: login, transaksi, perubahan data.</p>
    </div>
    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-2" style="border-radius:10px;">
        <i class="bi bi-list-check me-1"></i><?= number_format($total_row) ?> aktivitas tercatat
    </span>
</div>

<!-- Filter -->
<div class="dashboard-card mb-4">
    <div class="dashboard-card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= e($tgl_mulai) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= e($tgl_selesai) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Username</label>
                <input type="text" name="user" class="form-control form-control-sm" placeholder="Cari username..." value="<?= e($filter_user) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Jenis Aksi</label>
                <select name="aksi" class="form-select form-select-sm">
                    <option value="">-- Semua Aksi --</option>
                    <?php foreach ($aksi_valid as $a): ?>
                        <option value="<?= e($a) ?>" <?= $filter_aksi === $a ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $a))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn-petro-primary w-100 btn-sm">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="audit.php" class="btn-petro-outline w-100 btn-sm text-center">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Log -->
<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th style="width:160px;">Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Target</th>
                    <th>IP</th>
                    <th style="width:80px;">Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="7">
                    <div class="empty-state-friendly">
                        <div class="empty-doc-icon"><i class="bi bi-journal-text text-primary" style="font-size:2rem;"></i></div>
                        <div class="empty-state-title">Belum Ada Aktivitas</div>
                        <div class="empty-state-sub">Belum ada aktivitas yang cocok dengan filter.</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php $no = $offset + 1; foreach ($logs as $log):
                $ikon = $ikon_aksi[$log['aksi']] ?? ['bi bi-activity', '#64748b'];
            ?>
                <tr>
                    <td class="text-muted fw-bold"><?= $no++ ?></td>
                    <td class="small text-muted"><?= e(date('d/m/Y H:i:s', strtotime($log['waktu']))) ?></td>
                    <td>
                        <span class="user-avatar-circle" style="width:28px;height:28px;font-size:0.72rem;">
                            <?= e(strtoupper(substr($log['username'] ?? '?', 0, 1))) ?>
                        </span>
                        <span class="ms-2 fw-semibold text-dark small"><?= e($log['username'] ?? '-') ?></span>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;color:<?= e($ikon[1]) ?>;">
                            <i class="<?= e($ikon[0]) ?>"></i>
                            <?= e(ucfirst(str_replace('_', ' ', $log['aksi']))) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?php if ($log['tabel']): ?>
                            <code class="bg-light text-dark px-2 py-1 rounded"><?= e($log['tabel']) ?></code>
                            <?php if ($log['record_id']): ?> #<?= (int)$log['record_id'] ?><?php endif; ?>
                        <?php else: ?> — <?php endif; ?>
                    </td>
                    <td class="small text-muted"><code><?= e($log['ip'] ?? '-') ?></code></td>
                    <td class="text-center">
                        <?php if ($log['data_lama'] || $log['data_baru']): ?>
                            <button type="button" class="btn-icon-action btn-sm"
                                    onclick="tampilDetail(this)"
                                    data-lama="<?= e($log['data_lama'] ?? '') ?>"
                                    data-baru="<?= e($log['data_baru'] ?? '') ?>"
                                    title="Lihat Perubahan Data">
                                <i class="bi bi-eye"></i>
                            </button>
                        <?php else: ?> — <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_hal > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
        <span class="text-muted small">Halaman <?= $halaman ?> dari <?= $total_hal ?></span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $q_params = http_build_query(array_filter(['tgl_mulai'=>$tgl_mulai,'tgl_selesai'=>$tgl_selesai,'user'=>$filter_user,'aksi'=>$filter_aksi]));
                for ($p = max(1, $halaman-2); $p <= min($total_hal, $halaman+2); $p++):
                ?>
                <li class="page-item <?= $p === $halaman ? 'active' : '' ?>">
                    <a class="page-link" href="?hal=<?= $p ?>&<?= e($q_params) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Detail Perubahan -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius:20px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-git me-2 text-primary"></i>Detail Perubahan Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-bold text-muted small mb-2"><i class="bi bi-dash-circle text-danger me-1"></i>Data Sebelum</div>
                        <pre id="detailLama" class="bg-light p-3 rounded small" style="max-height:300px;overflow:auto;white-space:pre-wrap;"></pre>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold text-muted small mb-2"><i class="bi bi-plus-circle text-success me-1"></i>Data Sesudah</div>
                        <pre id="detailBaru" class="bg-light p-3 rounded small" style="max-height:300px;overflow:auto;white-space:pre-wrap;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>
<script>
function tampilDetail(btn) {
    var lama = btn.dataset.lama;
    var baru = btn.dataset.baru;
    try { lama = JSON.stringify(JSON.parse(lama), null, 2); } catch(e) {}
    try { baru = JSON.stringify(JSON.parse(baru), null, 2); } catch(e) {}
    document.getElementById('detailLama').textContent = lama || '(kosong)';
    document.getElementById('detailBaru').textContent = baru || '(kosong)';
    new bootstrap.Modal(document.getElementById('modalDetail')).show();
}
</script>
</body>
</html>