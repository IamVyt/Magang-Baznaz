<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara']);

// ============================================================
// PROSES AKSI (POST)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('program.php');
    }

    $aksi    = $_POST['aksi']    ?? '';
    $jenis   = $_POST['jenis']   ?? '';   // 'kategori' atau 'program'
    $id      = (int)($_POST['id'] ?? 0);
    $nama    = trim($_POST['nama'] ?? '');
    $kode    = strtoupper(trim(preg_replace('/[^A-Za-z0-9_]/', '_', $_POST['kode'] ?? '')));
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (!in_array($jenis, ['kategori', 'program'], true)) {
        flash('danger', 'Jenis data tidak valid.'); redirect('program.php');
    }
    $tipe = ($jenis === 'kategori') ? 'masuk' : 'keluar';

    if ($aksi === 'tambah') {
        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama wajib diisi dan maksimal 100 karakter.');
        } elseif ($kode === '' || mb_strlen($kode) > 20) {
            flash('danger', 'Kode wajib diisi dan maksimal 20 karakter (huruf/angka/garis bawah).');
        } else {
            $dup = q_one($conn, "SELECT id FROM kategori_dana WHERE kode = ? LIMIT 1", 's', [$kode]);
            if ($dup) {
                flash('danger', "Kode '$kode' sudah digunakan. Pilih kode lain.");
            } else {
                q($conn, "INSERT INTO kategori_dana (nama, kode, tipe, keterangan) VALUES (?, ?, ?, ?)", 'ssss', [$nama, $kode, $tipe, $keterangan]);
                $new_id = mysqli_insert_id($conn);
                catat_audit($conn, "tambah_$jenis", 'kategori_dana', $new_id, null, ['nama'=>$nama,'kode'=>$kode,'tipe'=>$tipe]);
                flash('success', ucfirst($jenis) . " '$nama' berhasil ditambahkan.");
            }
        }
    }

    elseif ($aksi === 'edit') {
        $data = q_one($conn, "SELECT * FROM kategori_dana WHERE id = ?", 'i', [$id]);
        if (!$data) { flash('danger', 'Data tidak ditemukan.'); redirect('program.php'); }

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama wajib diisi dan maksimal 100 karakter.');
        } else {
            $data_lama = ['nama' => $data['nama'], 'keterangan' => $data['keterangan']];
            q($conn, "UPDATE kategori_dana SET nama = ?, keterangan = ? WHERE id = ?", 'ssi', [$nama, $keterangan, $id]);
            catat_audit($conn, "edit_$jenis", 'kategori_dana', $id, $data_lama, ['nama'=>$nama]);
            flash('success', ucfirst($jenis) . " berhasil diperbarui.");
        }
    }

    elseif ($aksi === 'toggle') {
        $data = q_one($conn, "SELECT aktif FROM kategori_dana WHERE id = ?", 'i', [$id]);
        if (!$data) { flash('danger', 'Data tidak ditemukan.'); redirect('program.php'); }
        $aktif_baru = $data['aktif'] ? 0 : 1;
        q($conn, "UPDATE kategori_dana SET aktif = ? WHERE id = ?", 'ii', [$aktif_baru, $id]);
        catat_audit($conn, $aktif_baru ? "aktifkan_$jenis" : "nonaktifkan_$jenis", 'kategori_dana', $id);
        flash('success', ucfirst($jenis) . ' berhasil ' . ($aktif_baru ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    redirect('program.php');
}

// Ambil data
$daftar_kategori = q_all($conn, "SELECT *, (SELECT COUNT(*) FROM transaksi WHERE kategori_id = kategori_dana.id AND dibatalkan_at IS NULL) AS jml FROM kategori_dana WHERE tipe = 'masuk' ORDER BY aktif DESC, id ASC");
$daftar_program  = q_all($conn, "SELECT *, (SELECT COUNT(*) FROM transaksi WHERE kategori_id = kategori_dana.id AND dibatalkan_at IS NULL) AS jml FROM kategori_dana WHERE tipe = 'keluar' ORDER BY aktif DESC, id ASC");

$judul = 'Kategori & Program';
$aktif = 'program';
require 'partials/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Kategori Pemasukan & Program Penyaluran</h2>
        <p class="text-muted small mb-0">Kelola jenis zakat/infak masuk dan program distribusi dana UPZ.</p>
    </div>
    <?php if (can_input()): ?>
    <div class="d-flex gap-2">
        <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambah" data-jenis="kategori">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kategori
        </button>
        <button type="button" class="btn-petro-primary" data-bs-toggle="modal" data-bs-target="#modalTambah" data-jenis="program">
            <i class="bi bi-plus-lg me-1"></i>Tambah Program
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Kategori Pemasukan -->
    <div class="col-lg-6">
        <div class="dashboard-card h-100">
            <div class="dashboard-card-head">
                <h2 class="dashboard-card-title">
                    <i class="bi bi-tags text-success"></i>Kategori Pemasukan
                </h2>
                <span class="text-muted small"><?= count($daftar_kategori) ?> kategori</span>
            </div>
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th class="text-center">Kode</th>
                            <th class="text-center">Transaksi</th>
                            <th class="text-center">Status</th>
                            <?php if (can_input()): ?><th class="text-end">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar_kategori): ?>
                        <tr><td colspan="5">
                            <div class="empty-state-friendly"><div class="empty-state-title">Belum Ada Kategori</div></div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar_kategori as $k): ?>
                        <tr class="<?= !$k['aktif'] ? 'opacity-50' : '' ?>">
                            <td>
                                <div class="fw-bold text-dark small"><?= e($k['nama']) ?></div>
                                <?php if ($k['keterangan']): ?>
                                    <div class="text-muted" style="font-size:0.72rem;"><?= e(mb_substr($k['keterangan'], 0, 60)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><code class="bg-light px-2 py-1 rounded small"><?= e($k['kode']) ?></code></td>
                            <td class="text-center">
                                <span class="badge-friendly badge-masuk" style="font-size:0.72rem;">
                                    <?= (int)$k['jml'] ?> trx
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($k['aktif']): ?>
                                    <span class="badge-friendly" style="background:#f0fdf4;color:#166534;font-size:0.72rem;"><span class="badge-dot" style="background:#22c55e;"></span>Aktif</span>
                                <?php else: ?>
                                    <span class="badge-friendly" style="background:#f1f5f9;color:#64748b;font-size:0.72rem;"><span class="badge-dot" style="background:#94a3b8;"></span>Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <?php if (can_input()): ?>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn-icon-action me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                        data-id="<?= (int)$k['id'] ?>" data-jenis="kategori"
                                        data-nama="<?= e($k['nama']) ?>" data-keterangan="<?= e($k['keterangan'] ?? '') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('<?= $k['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="toggle">
                                    <input type="hidden" name="jenis" value="kategori">
                                    <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                    <button type="submit" class="btn-icon-action <?= $k['aktif'] ? 'danger' : '' ?>" title="<?= $k['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi bi-<?= $k['aktif'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Program Penyaluran -->
    <div class="col-lg-6">
        <div class="dashboard-card h-100">
            <div class="dashboard-card-head">
                <h2 class="dashboard-card-title">
                    <i class="bi bi-diagram-3 text-primary"></i>Program Penyaluran
                </h2>
                <span class="text-muted small"><?= count($daftar_program) ?> program</span>
            </div>
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Nama Program</th>
                            <th class="text-center">Kode</th>
                            <th class="text-center">Penerima</th>
                            <th class="text-center">Status</th>
                            <?php if (can_input()): ?><th class="text-end">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar_program): ?>
                        <tr><td colspan="5">
                            <div class="empty-state-friendly"><div class="empty-state-title">Belum Ada Program</div></div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar_program as $pr): ?>
                        <tr class="<?= !$pr['aktif'] ? 'opacity-50' : '' ?>">
                            <td>
                                <div class="fw-bold text-dark small"><?= e($pr['nama']) ?></div>
                                <?php if ($pr['keterangan']): ?>
                                    <div class="text-muted" style="font-size:0.72rem;"><?= e(mb_substr($pr['keterangan'], 0, 60)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><code class="bg-light px-2 py-1 rounded small"><?= e($pr['kode']) ?></code></td>
                            <td class="text-center">
                                <span class="badge-friendly badge-keluar" style="font-size:0.72rem;">
                                    <?= (int)$pr['jml'] ?> penerima
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($pr['aktif']): ?>
                                    <span class="badge-friendly" style="background:#f0fdf4;color:#166534;font-size:0.72rem;"><span class="badge-dot" style="background:#22c55e;"></span>Aktif</span>
                                <?php else: ?>
                                    <span class="badge-friendly" style="background:#f1f5f9;color:#64748b;font-size:0.72rem;"><span class="badge-dot" style="background:#94a3b8;"></span>Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <?php if (can_input()): ?>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn-icon-action me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                        data-id="<?= (int)$pr['id'] ?>" data-jenis="program"
                                        data-nama="<?= e($pr['nama']) ?>" data-keterangan="<?= e($pr['keterangan'] ?? '') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('<?= $pr['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> program ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="toggle">
                                    <input type="hidden" name="jenis" value="program">
                                    <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                                    <button type="submit" class="btn-icon-action <?= $pr['aktif'] ? 'danger' : '' ?>" title="<?= $pr['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi bi-<?= $pr['aktif'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="jenis" id="tambahJenis" value="kategori">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-green mb-0" id="tambahIconBox"><i class="bi bi-tags" id="tambahIcon"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold" id="tambahJudul">Tambah Kategori Baru</h5>
                        <p class="text-muted small mb-0" id="tambahSub">Kategori untuk jenis pemasukan kas UPZ</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="tambahNama">Nama <span class="text-danger">*</span></label>
                    <input type="text" id="tambahNama" name="nama" class="form-control" maxlength="100" placeholder="Contoh: Zakat Maal / Beasiswa Pendidikan" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="tambahKode">Kode Unik <span class="text-danger">*</span></label>
                    <input type="text" id="tambahKode" name="kode" class="form-control" maxlength="20"
                           placeholder="Contoh: ZAKAT_MAAL" pattern="[A-Za-z0-9_]+" required>
                    <div class="form-text text-muted small">Hanya huruf, angka, dan garis bawah. Otomatis jadi kapital.</div>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="tambahKeterangan">Keterangan (Opsional)</label>
                    <textarea id="tambahKeterangan" name="keterangan" class="form-control" rows="2" maxlength="500" placeholder="Deskripsi singkat..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-green px-4" id="tambahBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="jenis" id="editJenis">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-blue mb-0"><i class="bi bi-pencil-square"></i></div>
                    <h5 class="modal-title fw-bold">Edit <span id="editJudulLabel">Data</span></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="editNama">Nama <span class="text-danger">*</span></label>
                    <input type="text" id="editNama" name="nama" class="form-control" maxlength="100" required>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="editKeterangan">Keterangan (Opsional)</label>
                    <textarea id="editKeterangan" name="keterangan" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="form-text text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Kode unik tidak bisa diubah setelah dibuat.</div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-primary px-4">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<?php require 'partials/footer.php'; ?>
<script>
document.getElementById('modalTambah').addEventListener('show.bs.modal', function(ev) {
    var jenis = ev.relatedTarget ? ev.relatedTarget.dataset.jenis : 'kategori';
    var isKat = (jenis === 'kategori');
    document.getElementById('tambahJenis').value = jenis;
    document.getElementById('tambahJudul').textContent = isKat ? 'Tambah Kategori Baru' : 'Tambah Program Baru';
    document.getElementById('tambahSub').textContent   = isKat ? 'Kategori jenis pemasukan kas UPZ' : 'Program penyaluran / distribusi dana';
    document.getElementById('tambahIcon').className    = isKat ? 'bi bi-tags' : 'bi bi-diagram-3';
    var box = document.getElementById('tambahIconBox');
    box.style.background = isKat ? 'var(--pg-green-soft)' : 'var(--pg-blue-soft,#dbeafe)';
    box.style.color      = isKat ? 'var(--pg-green-dark)' : '#1d4ed8';
    document.getElementById('tambahBtn').className = isKat ? 'btn-petro-green px-4' : 'btn-petro-primary px-4';
    document.getElementById('tambahNama').value = '';
    document.getElementById('tambahKode').value = '';
    document.getElementById('tambahKeterangan').value = '';
});

document.getElementById('modalEdit').addEventListener('show.bs.modal', function(ev) {
    var b = ev.relatedTarget;
    document.getElementById('editId').value         = b.dataset.id;
    document.getElementById('editJenis').value      = b.dataset.jenis;
    document.getElementById('editNama').value       = b.dataset.nama;
    document.getElementById('editKeterangan').value = b.dataset.keterangan || '';
    document.getElementById('editJudulLabel').textContent = b.dataset.jenis === 'kategori' ? 'Kategori' : 'Program';
});

// Auto-uppercase kode
document.getElementById('tambahKode').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '_');
});
</script>
</body>
</html>