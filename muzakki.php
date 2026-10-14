<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara', 'ketua']);

// ============================================================
// PROSES AKSI MUZAKKI (TAMBAH, EDIT, HAPUS)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!can_input()) {
        flash('danger', 'Anda tidak memiliki izin untuk mengelola data muzakki.');
        redirect('muzakki.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('muzakki.php');
    }

    $aksi = $_POST['aksi'] ?? '';

    // ---------- TAMBAH MUZAKKI ----------
    if ($aksi === 'tambah') {
        $nama    = trim($_POST['nama'] ?? '');
        $nik_nip = trim($_POST['nik_nip'] ?? '');
        $kontak  = trim($_POST['kontak'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama muzakki wajib diisi dan maksimal 100 karakter.');
        } else {
            q($conn, "INSERT INTO muzakki (nama, nik_nip, kontak, alamat) VALUES (?, ?, ?, ?)",
                'ssss', [$nama, $nik_nip ?: null, $kontak ?: null, $alamat ?: null]);
            $new_id = mysqli_insert_id($conn);
            catat_audit($conn, 'tambah', 'muzakki', $new_id, null,
                ['nama' => $nama, 'nik_nip' => $nik_nip, 'kontak' => $kontak]);
            flash('success', "Muzakki '$nama' berhasil ditambahkan.");
        }
        redirect('muzakki.php');
    }

    // ---------- EDIT MUZAKKI ----------
    elseif ($aksi === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $nik_nip = trim($_POST['nik_nip'] ?? '');
        $kontak  = trim($_POST['kontak'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');

        $target = q_one($conn, "SELECT * FROM muzakki WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Data muzakki tidak ditemukan.');
            redirect('muzakki.php');
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama muzakki wajib diisi dan maksimal 100 karakter.');
        } else {
            $data_lama = $target;
            $data_baru = [
                'nama'    => $nama,
                'nik_nip' => $nik_nip,
                'kontak'  => $kontak,
                'alamat'  => $alamat,
            ];
            q($conn, "UPDATE muzakki SET nama = ?, nik_nip = ?, kontak = ?, alamat = ? WHERE id = ?",
                'ssssi', [$nama, $nik_nip ?: null, $kontak ?: null, $alamat ?: null, $id]);
            catat_audit($conn, 'edit', 'muzakki', $id, $data_lama, $data_baru);
            flash('success', "Data muzakki '$nama' berhasil diperbarui.");
        }
        redirect('muzakki.php');
    }

    // ---------- HAPUS / NONAKTIFKAN MUZAKKI ----------
    elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $target = q_one($conn, "SELECT * FROM muzakki WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Data muzakki tidak ditemukan.');
            redirect('muzakki.php');
        }

        // Cek apakah pernah ada transaksi terkait
        $adaTrx = (int)q_one($conn, "SELECT COUNT(*) AS n FROM transaksi WHERE pihak_id = ? AND tipe = 'masuk'", 'i', [$id])['n'];
        if ($adaTrx > 0) {
            // Soft deactivate jika ada histori transaksi
            q($conn, "UPDATE muzakki SET aktif = 0 WHERE id = ?", 'i', [$id]);
            flash('warning', "Muzakki '{$target['nama']}' dinonaktifkan karena memiliki {$adaTrx} riwayat transaksi.");
        } else {
            q($conn, "DELETE FROM muzakki WHERE id = ?", 'i', [$id]);
            flash('success', "Muzakki '{$target['nama']}' berhasil dihapus.");
        }
        catat_audit($conn, 'hapus', 'muzakki', $id, $target, null);
        redirect('muzakki.php');
    }
}

// ============================================================
// DATA & FILTER
// ============================================================
$cari = trim($_GET['cari'] ?? '');
$where = '1=1'; $types = ''; $params = [];

if ($cari !== '') {
    $where .= " AND (nama LIKE ? OR nik_nip LIKE ? OR kontak LIKE ? OR alamat LIKE ?)";
    $types .= 'ssss';
    $kw = '%' . addcslashes($cari, '%_\\') . '%';
    $params = [$kw, $kw, $kw, $kw];
}

// Pagination
$perHal = 10;
$totalBaris = (int)q_one($conn, "SELECT COUNT(*) AS n FROM muzakki WHERE $where", $types, $params)['n'];
$totalHal = max(1, (int)ceil($totalBaris / $perHal));
$hal = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$offset = ($hal - 1) * $perHal;

$typesPagination = $types . 'ii';
$paramsPagination = array_merge($params, [$perHal, $offset]);

$daftar = q_all($conn,
    "SELECT m.*,
            (SELECT COUNT(*) FROM transaksi t WHERE t.pihak_id = m.id AND t.tipe = 'masuk' AND t.dibatalkan_at IS NULL) AS total_trx,
            (SELECT COALESCE(SUM(t.jumlah),0) FROM transaksi t WHERE t.pihak_id = m.id AND t.tipe = 'masuk' AND t.dibatalkan_at IS NULL) AS total_donasi
       FROM muzakki m
      WHERE $where
      ORDER BY m.id DESC
      LIMIT ? OFFSET ?",
    $typesPagination, $paramsPagination);

$totalMuzakkiAktif = (int)q_one($conn, "SELECT COUNT(*) AS n FROM muzakki WHERE aktif = 1")['n'];

$judul = 'Master Muzakki';
$aktif = 'muzakki';
require 'partials/header.php';
?>

<!-- Header & Tombol Tambah -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Master Data Muzakki (Donatur)</h2>
        <p class="text-muted small mb-0">Kelola direktori donatur zakat, infak, dan sedekah PT Petrokimia Gresik.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-2" style="border-radius:10px;">
            <i class="bi bi-people me-1"></i><?= number_format($totalMuzakkiAktif) ?> Muzakki Aktif
        </span>
        <?php if (can_input()): ?>
        <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambahMuzakki">
            <i class="bi bi-person-plus me-1"></i>Tambah Muzakki
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Kotak Filter Pencarian -->
<div class="dashboard-card mb-4">
    <div class="dashboard-card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-9 col-sm-8">
                <label class="form-label small fw-bold text-muted mb-1">Cari Muzakki</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="cari" class="form-control" placeholder="Cari nama, NIP/NIK, nomor kontak, atau alamat..." value="<?= e($cari) ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-4 d-flex gap-2">
                <button type="submit" class="btn-petro-primary flex-fill"><i class="bi bi-search me-1"></i>Cari</button>
                <a href="muzakki.php" class="btn-petro-outline" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Data Muzakki -->
<div class="dashboard-card shadow-sm">
    <div class="dashboard-card-head">
        <div>
            <h2 class="dashboard-card-title">
                <i class="bi bi-person-heart text-primary"></i>Daftar Muzakki
            </h2>
            <span class="text-muted small">Menampilkan <?= count($daftar) ?> dari <?= $totalBaris ?> data donatur</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Muzakki</th>
                    <th>NIP / NIK</th>
                    <th>Kontak / Telp</th>
                    <th>Alamat</th>
                    <th class="text-center">Riwayat</th>
                    <th class="text-end">Total Donasi</th>
                    <?php if (can_input()): ?>
                    <th class="text-end" style="width: 100px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?>
                <tr>
                    <td colspan="<?= can_input() ? 8 : 7 ?>">
                        <div class="empty-state-friendly">
                            <div class="empty-doc-icon"><i class="bi bi-person-slash text-primary" style="font-size:2rem;"></i></div>
                            <div class="empty-state-title">Tidak Ada Data Muzakki</div>
                            <div class="empty-state-sub">Belum ada donatur yang cocok dengan pencarian Anda.</div>
                            <?php if (can_input()): ?>
                            <button type="button" class="btn-petro-green mt-3" data-bs-toggle="modal" data-bs-target="#modalTambahMuzakki">
                                <i class="bi bi-person-plus me-1"></i>Tambah Muzakki Pertama
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = $offset + 1; foreach ($daftar as $m): ?>
                    <tr class="<?= (int)$m['aktif'] === 0 ? 'opacity-50' : '' ?>">
                        <td class="text-muted fw-bold"><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($m['nama']) ?></div>
                            <?php if ((int)$m['aktif'] === 0): ?>
                                <span class="badge bg-secondary" style="font-size:0.65rem;">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($m['nik_nip'] ?: '—') ?></td>
                        <td class="text-muted small">
                            <?php if (!empty($m['kontak'])): ?>
                                <i class="bi bi-telephone me-1 text-primary"></i><?= e($m['kontak']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small text-truncate" style="max-width: 200px;" title="<?= e($m['alamat'] ?? '') ?>">
                            <?= e($m['alamat'] ?: '—') ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= (int)$m['total_trx'] ?>x
                            </span>
                        </td>
                        <td class="text-end text-nowrap nominal-in">
                            <?= rupiah($m['total_donasi']) ?>
                        </td>
                        <?php if (can_input()): ?>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn-icon-action" title="Ubah Data"
                                    onclick="editMuzakki(this)"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-nama="<?= e($m['nama']) ?>"
                                    data-nik_nip="<?= e($m['nik_nip'] ?? '') ?>"
                                    data-kontak="<?= e($m['kontak'] ?? '') ?>"
                                    data-alamat="<?= e($m['alamat'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn-icon-action danger" title="Hapus / Nonaktifkan"
                                    onclick="hapusMuzakki(this)"
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
                    <a class="page-link rounded-circle me-1 border-0" href="?cari=<?= urlencode($cari) ?>&hal=<?= $hal - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($i = max(1, $hal - 2); $i <= min($totalHal, $hal + 2); $i++): ?>
                    <li class="page-item <?= $i === $hal ? 'active' : '' ?>">
                        <a class="page-link rounded-circle me-1 border-0" href="?cari=<?= urlencode($cari) ?>&hal=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $hal >= $totalHal ? 'disabled' : '' ?>">
                    <a class="page-link rounded-circle border-0" href="?cari=<?= urlencode($cari) ?>&hal=<?= $hal + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php if (can_input()): ?>
<!-- Modal Tambah Muzakki -->
<div class="modal fade" id="modalTambahMuzakki" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--pg-blue-soft); color: var(--pg-blue); margin-bottom:0;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Tambah Muzakki Baru</h5>
                        <p class="text-muted small mb-0">Masukkan data donatur / muzakki</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: Budi Santoso" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NIP / NIK Karyawan</label>
                    <input type="text" name="nik_nip" class="form-control" placeholder="Contoh: 19850123... / NIP Petrokimia" maxlength="30">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nomor Kontak / WhatsApp</label>
                    <input type="text" name="kontak" class="form-control" placeholder="Contoh: 08123456789" maxlength="50">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Alamat / Unit Kerja</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Departemen / Unit Kerja / Alamat domisili"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-green px-4">Simpan Muzakki</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Muzakki -->
<div class="modal fade" id="modalEditMuzakki" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="editMzkId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--pg-yellow-soft); color: var(--pg-yellow-dark); margin-bottom:0;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Ubah Data Muzakki</h5>
                        <p class="text-muted small mb-0">Perbarui rincian donatur</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="editMzkNama" class="form-control" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NIP / NIK Karyawan</label>
                    <input type="text" name="nik_nip" id="editMzkNik" class="form-control" maxlength="30">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Nomor Kontak / WhatsApp</label>
                    <input type="text" name="kontak" id="editMzkKontak" class="form-control" maxlength="50">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Alamat / Unit Kerja</label>
                    <textarea name="alamat" id="editMzkAlamat" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-primary px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Muzakki -->
<div class="modal fade" id="modalHapusMuzakki" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" id="hapusMzkId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box" style="background: var(--danger-soft); color: var(--danger); margin-bottom:0;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                        <p class="text-muted small mb-0">Hapus atau nonaktifkan data muzakki</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-0">
                    Apakah Anda yakin ingin menghapus data muzakki <strong id="hapusMzkNama" class="text-dark"></strong>?
                    Jika muzakki memiliki riwayat transaksi, data akan dinonaktifkan secara aman.
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
function editMuzakki(btn) {
    document.getElementById('editMzkId').value = btn.dataset.id;
    document.getElementById('editMzkNama').value = btn.dataset.nama;
    document.getElementById('editMzkNik').value = btn.dataset.nik_nip;
    document.getElementById('editMzkKontak').value = btn.dataset.kontak;
    document.getElementById('editMzkAlamat').value = btn.dataset.alamat;
    new bootstrap.Modal(document.getElementById('modalEditMuzakki')).show();
}

function hapusMuzakki(btn) {
    document.getElementById('hapusMzkId').value = btn.dataset.id;
    document.getElementById('hapusMzkNama').textContent = btn.dataset.nama;
    new bootstrap.Modal(document.getElementById('modalHapusMuzakki')).show();
}
</script>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
</body>
</html>
