<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara', 'ketua']);

$daftar_asnaf = [
    'fakir'       => 'Fakir',
    'miskin'      => 'Miskin',
    'amil'        => 'Amil',
    'muallaf'     => 'Muallaf',
    'riqab'       => 'Riqab (Hamba Sahaya)',
    'gharim'      => 'Gharim (Terlilit Hutang)',
    'sabilillah'  => 'Fi Sabilillah',
    'ibnu_sabil'  => 'Ibnu Sabil (Musafir)',
];

// ============================================================
// PROSES AKSI MUSTAHIK (TAMBAH, EDIT, HAPUS)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!can_input()) {
        flash('danger', 'Anda tidak memiliki izin untuk mengelola data mustahik.');
        redirect('mustahik.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('mustahik.php');
    }

    $aksi = $_POST['aksi'] ?? '';

    // ---------- TAMBAH MUSTAHIK ----------
    if ($aksi === 'tambah') {
        $nama   = trim($_POST['nama'] ?? '');
        $nik    = trim($_POST['nik'] ?? '');
        $kontak = trim($_POST['kontak'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $asnaf  = $_POST['asnaf'] ?? 'fakir';

        if (!array_key_exists($asnaf, $daftar_asnaf)) $asnaf = 'fakir';

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama mustahik wajib diisi dan maksimal 100 karakter.');
        } else {
            q($conn, "INSERT INTO mustahik (nama, nik, kontak, alamat, asnaf) VALUES (?, ?, ?, ?, ?)",
                'sssss', [$nama, $nik ?: null, $kontak ?: null, $alamat ?: null, $asnaf]);
            $new_id = mysqli_insert_id($conn);
            catat_audit($conn, 'tambah', 'mustahik', $new_id, null,
                ['nama' => $nama, 'nik' => $nik, 'asnaf' => $asnaf]);
            flash('success', "Mustahik '$nama' (Asnaf: {$daftar_asnaf[$asnaf]}) berhasil ditambahkan.");
        }
        redirect('mustahik.php');
    }

    // ---------- EDIT MUSTAHIK ----------
    elseif ($aksi === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $nama   = trim($_POST['nama'] ?? '');
        $nik    = trim($_POST['nik'] ?? '');
        $kontak = trim($_POST['kontak'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $asnaf  = $_POST['asnaf'] ?? 'fakir';

        if (!array_key_exists($asnaf, $daftar_asnaf)) $asnaf = 'fakir';

        $target = q_one($conn, "SELECT * FROM mustahik WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Data mustahik tidak ditemukan.');
            redirect('mustahik.php');
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama mustahik wajib diisi dan maksimal 100 karakter.');
        } else {
            $data_lama = $target;
            $data_baru = [
                'nama'   => $nama,
                'nik'    => $nik,
                'kontak' => $kontak,
                'alamat' => $alamat,
                'asnaf'  => $asnaf,
            ];
            q($conn, "UPDATE mustahik SET nama = ?, nik = ?, kontak = ?, alamat = ?, asnaf = ? WHERE id = ?",
                'sssssi', [$nama, $nik ?: null, $kontak ?: null, $alamat ?: null, $asnaf, $id]);
            catat_audit($conn, 'edit', 'mustahik', $id, $data_lama, $data_baru);
            flash('success', "Data mustahik '$nama' berhasil diperbarui.");
        }
        redirect('mustahik.php');
    }

    // ---------- HAPUS / NONAKTIFKAN MUSTAHIK ----------
    elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $target = q_one($conn, "SELECT * FROM mustahik WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Data mustahik tidak ditemukan.');
            redirect('mustahik.php');
        }

        // Cek apakah ada riwayat penyaluran
        $adaTrx = (int)q_one($conn, "SELECT COUNT(*) AS n FROM transaksi WHERE pihak_id = ? AND tipe = 'keluar'", 'i', [$id])['n'];
        if ($adaTrx > 0) {
            q($conn, "UPDATE mustahik SET aktif = 0 WHERE id = ?", 'i', [$id]);
            flash('warning', "Mustahik '{$target['nama']}' dinonaktifkan karena memiliki {$adaTrx} riwayat penyaluran.");
        } else {
            q($conn, "DELETE FROM mustahik WHERE id = ?", 'i', [$id]);
            flash('success', "Mustahik '{$target['nama']}' berhasil dihapus.");
        }
        catat_audit($conn, 'hapus', 'mustahik', $id, $target, null);
        redirect('mustahik.php');
    }
}

// ============================================================
// DATA & FILTER
// ============================================================
$cari        = trim($_GET['cari'] ?? '');
$filterAsnaf = trim($_GET['asnaf'] ?? '');
if (!array_key_exists($filterAsnaf, $daftar_asnaf)) $filterAsnaf = '';

$where = '1=1'; $types = ''; $params = [];

if ($cari !== '') {
    $where .= " AND (nama LIKE ? OR nik LIKE ? OR kontak LIKE ? OR alamat LIKE ?)";
    $types .= 'ssss';
    $kw = '%' . addcslashes($cari, '%_\\') . '%';
    $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
}
if ($filterAsnaf !== '') {
    $where .= " AND asnaf = ?";
    $types .= 's';
    $params[] = $filterAsnaf;
}

// Pagination
$perHal = 10;
$totalBaris = (int)q_one($conn, "SELECT COUNT(*) AS n FROM mustahik WHERE $where", $types, $params)['n'];
$totalHal = max(1, (int)ceil($totalBaris / $perHal));
$hal = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$offset = ($hal - 1) * $perHal;

$typesPagination = $types . 'ii';
$paramsPagination = array_merge($params, [$perHal, $offset]);

$daftar = q_all($conn,
    "SELECT m.*,
            (SELECT COUNT(*) FROM transaksi t WHERE t.pihak_id = m.id AND t.tipe = 'keluar' AND t.dibatalkan_at IS NULL) AS total_bantuan,
            (SELECT COALESCE(SUM(t.jumlah),0) FROM transaksi t WHERE t.pihak_id = m.id AND t.tipe = 'keluar' AND t.dibatalkan_at IS NULL) AS nominal_terima
       FROM mustahik m
      WHERE $where
      ORDER BY m.id DESC
      LIMIT ? OFFSET ?",
    $typesPagination, $paramsPagination);

$totalMustahikAktif = (int)q_one($conn, "SELECT COUNT(*) AS n FROM mustahik WHERE aktif = 1")['n'];

$badge_asnaf = [
    'fakir'      => ['#fee2e2', '#991b1b'],
    'miskin'     => ['#fef3c7', '#92400e'],
    'amil'       => ['#e0f2fe', '#075985'],
    'muallaf'    => ['#f3e8ff', '#6b21a8'],
    'riqab'      => ['#f1f5f9', '#475569'],
    'gharim'     => ['#ffedd5', '#9a3412'],
    'sabilillah' => ['#dcfce7', '#166534'],
    'ibnu_sabil' => ['#ccfbf1', '#115e59'],
];

$judul = 'Master Mustahik';
$aktif = 'mustahik';
require 'partials/header.php';
?>

<!-- Header & Tombol Tambah -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Master Data Mustahik (Penerima Manfaat)</h2>
        <p class="text-muted small mb-0">Direktori penerima bantuan zakat dan infak berdasarkan 8 golongan asnaf.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-2" style="border-radius:10px;">
            <i class="bi bi-people-fill me-1"></i><?= number_format($totalMustahikAktif) ?> Mustahik Aktif
        </span>
        <?php if (can_input()): ?>
        <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambahMustahik">
            <i class="bi bi-person-plus me-1"></i>Tambah Mustahik
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Kotak Filter Pencarian & Asnaf -->
<div class="dashboard-card mb-4">
    <div class="dashboard-card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6 col-sm-6">
                <label class="form-label small fw-bold text-muted mb-1">Cari Mustahik</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="cari" class="form-control" placeholder="Cari nama, NIK, kontak, atau alamat..." value="<?= e($cari) ?>">
                </div>
            </div>
            <div class="col-md-4 col-sm-4">
                <label class="form-label small fw-bold text-muted mb-1">Golongan Asnaf</label>
                <select name="asnaf" class="form-select">
                    <option value="">-- Semua Golongan Asnaf --</option>
                    <?php foreach ($daftar_asnaf as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $filterAsnaf === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-2 d-flex gap-2">
                <button type="submit" class="btn-petro-primary flex-fill"><i class="bi bi-filter me-1"></i>Filter</button>
                <a href="mustahik.php" class="btn-petro-outline" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Data Mustahik -->
<div class="dashboard-card shadow-sm">
    <div class="dashboard-card-head">
        <div>
            <h2 class="dashboard-card-title">
                <i class="bi bi-people text-primary"></i>Daftar Mustahik
            </h2>
            <span class="text-muted small">Menampilkan <?= count($daftar) ?> dari <?= $totalBaris ?> data mustahik</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Mustahik</th>
                    <th>Asnaf</th>
                    <th>NIK</th>
                    <th>Kontak / Telp</th>
                    <th>Alamat</th>
                    <th class="text-center">Penyaluran</th>
                    <th class="text-end">Total Diterima</th>
                    <?php if (can_input()): ?>
                    <th class="text-end" style="width: 100px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?>
                <tr>
                    <td colspan="<?= can_input() ? 9 : 8 ?>">
                        <div class="empty-state-friendly">
                            <div class="empty-doc-icon"><i class="bi bi-person-slash text-primary" style="font-size:2rem;"></i></div>
                            <div class="empty-state-title">Tidak Ada Data Mustahik</div>
                            <div class="empty-state-sub">Belum ada penerima manfaat yang cocok dengan kriteria filter.</div>
                            <?php if (can_input()): ?>
                            <button type="button" class="btn-petro-green mt-3" data-bs-toggle="modal" data-bs-target="#modalTambahMustahik">
                                <i class="bi bi-person-plus me-1"></i>Tambah Mustahik Pertama
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = $offset + 1; foreach ($daftar as $m):
                    $clr = $badge_asnaf[$m['asnaf']] ?? ['#f1f5f9', '#334155'];
                ?>
                    <tr class="<?= (int)$m['aktif'] === 0 ? 'opacity-50' : '' ?>">
                        <td class="text-muted fw-bold"><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($m['nama']) ?></div>
                            <?php if ((int)$m['aktif'] === 0): ?>
                                <span class="badge bg-secondary" style="font-size:0.65rem;">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-friendly" style="background:<?= e($clr[0]) ?>;color:<?= e($clr[1]) ?>;">
                                <?= e($daftar_asnaf[$m['asnaf']] ?? $m['asnaf']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= e($m['nik'] ?: '—') ?></td>
                        <td class="text-muted small">
                            <?php if (!empty($m['kontak'])): ?>
                                <i class="bi bi-telephone me-1 text-primary"></i><?= e($m['kontak']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small text-truncate" style="max-width: 180px;" title="<?= e($m['alamat'] ?? '') ?>">
                            <?= e($m['alamat'] ?: '—') ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= (int)$m['total_bantuan'] ?>x
                            </span>
                        </td>
                        <td class="text-end text-nowrap nominal-out">
                            <?= rupiah($m['nominal_terima']) ?>
                        </td>
                        <?php if (can_input()): ?>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn-icon-action" title="Ubah Data"
                                    onclick="editMustahik(this)"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-nama="<?= e($m['nama']) ?>"
                                    data-nik="<?= e($m['nik'] ?? '') ?>"
                                    data-kontak="<?= e($m['kontak'] ?? '') ?>"
                                    data-alamat="<?= e($m['alamat'] ?? '') ?>"
                                    data-asnaf="<?= e($m['asnaf']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn-icon-action danger" title="Hapus / Nonaktifkan"
                                    onclick="hapusMustahik(this)"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-nama="<?= e($m['nama']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginasi -->
    <?php if ($totalHal > 1): ?>
    <div class="dashboard-card-head border-top mt-auto py-3">
        <span class="text-muted small">Halaman <?= $hal ?> dari <?= $totalHal ?></span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link rounded-circle me-1 border-0" href="?cari=<?= urlencode($cari) ?>&asnaf=<?= urlencode($filterAsnaf) ?>&hal=<?= $hal - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($i = max(1, $hal - 2); $i <= min($totalHal, $hal + 2); $i++): ?>
                    <li class="page-item <?= $i === $hal ? 'active' : '' ?>">
                        <a class="page-link rounded-circle me-1 border-0" href="?cari=<?= urlencode($cari) ?>&asnaf=<?= urlencode($filterAsnaf) ?>&hal=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $hal >= $totalHal ? 'disabled' : '' ?>">
                    <a class="page-link rounded-circle border-0" href="?cari=<?= urlencode($cari) ?>&asnaf=<?= urlencode($filterAsnaf) ?>&hal=<?= $hal + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php if (can_input()): ?>
<!-- Modal Tambah Mustahik -->
<div class="modal fade" id="modalTambahMustahik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--pg-green-soft); color: var(--pg-green-dark); margin-bottom:0;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Tambah Mustahik Baru</h5>
                        <p class="text-muted small mb-0">Masukkan data penerima manfaat</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: Siti Aisyah" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Golongan Asnaf <span class="text-danger">*</span></label>
                    <select name="asnaf" class="form-select" required>
                        <?php foreach ($daftar_asnaf as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NIK KTP</label>
                    <input type="text" name="nik" class="form-control" placeholder="16 digit NIK..." maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nomor Kontak / WhatsApp</label>
                    <input type="text" name="kontak" class="form-control" placeholder="Contoh: 08123456789" maxlength="50">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Alamat Domisili</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap mustahik"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-green px-4">Simpan Mustahik</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Mustahik -->
<div class="modal fade" id="modalEditMustahik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="editMtkId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--pg-yellow-soft); color: var(--pg-yellow-dark); margin-bottom:0;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Ubah Data Mustahik</h5>
                        <p class="text-muted small mb-0">Perbarui rincian penerima manfaat</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="editMtkNama" class="form-control" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Golongan Asnaf <span class="text-danger">*</span></label>
                    <select name="asnaf" id="editMtkAsnaf" class="form-select" required>
                        <?php foreach ($daftar_asnaf as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NIK KTP</label>
                    <input type="text" name="nik" id="editMtkNik" class="form-control" maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nomor Kontak / WhatsApp</label>
                    <input type="text" name="kontak" id="editMtkKontak" class="form-control" maxlength="50">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Alamat Domisili</label>
                    <textarea name="alamat" id="editMtkAlamat" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-primary px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Mustahik -->
<div class="modal fade" id="modalHapusMustahik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" id="hapusMtkId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--danger-soft); color: var(--danger); margin-bottom:0;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                        <p class="text-muted small mb-0">Hapus atau nonaktifkan data mustahik</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-0">
                    Apakah Anda yakin ingin menghapus data mustahik <strong id="hapusMtkNama" class="text-dark"></strong>?
                    Jika mustahik memiliki riwayat penyaluran, data akan dinonaktifkan secara aman.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4">Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
function editMustahik(btn) {
    document.getElementById('editMtkId').value = btn.dataset.id;
    document.getElementById('editMtkNama').value = btn.dataset.nama;
    document.getElementById('editMtkNik').value = btn.dataset.nik;
    document.getElementById('editMtkKontak').value = btn.dataset.kontak;
    document.getElementById('editMtkAlamat').value = btn.dataset.alamat;
    document.getElementById('editMtkAsnaf').value = btn.dataset.asnaf;
    new bootstrap.Modal(document.getElementById('modalEditMustahik')).show();
}

function hapusMustahik(btn) {
    document.getElementById('hapusMtkId').value = btn.dataset.id;
    document.getElementById('hapusMtkNama').textContent = btn.dataset.nama;
    new bootstrap.Modal(document.getElementById('modalHapusMustahik')).show();
}
</script>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
</body>
</html>
