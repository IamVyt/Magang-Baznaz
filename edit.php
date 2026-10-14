<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Parameter data transaksi tidak valid.');
    redirect('index.php');
}

// Ambil data transaksi lama
$data = q_one($conn,
    "SELECT id, tipe, kategori_id, pihak_id, nama_pihak AS nama, jumlah, DATE(tanggal) AS tgl,
            keterangan, dibatalkan_at
     FROM transaksi WHERE id = ?", 'i', [$id]);

if (!$data) {
    flash('danger', 'Data transaksi tidak ditemukan.');
    redirect('index.php');
}
if ($data['dibatalkan_at'] !== null) {
    flash('danger', 'Transaksi yang sudah dibatalkan (void) tidak dapat diedit.');
    redirect('index.php');
}

$tipe    = $data['tipe'];
$isMasuk = ($tipe === 'masuk');

// Daftar kategori dan master pihak terkait
$kategori_list = q_all($conn,
    "SELECT id, nama FROM kategori_dana WHERE tipe = ? AND aktif = 1 ORDER BY nama ASC",
    's', [$tipe]);

$pihak_list = $isMasuk
    ? q_all($conn, "SELECT id, nama, nik_nip FROM muzakki WHERE aktif = 1 ORDER BY nama ASC")
    : q_all($conn, "SELECT id, nama, asnaf FROM mustahik WHERE aktif = 1 ORDER BY nama ASC");

// Proses form update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect("edit.php?id=$id");
    }

    $namaBaru     = trim($_POST['nama'] ?? '');
    $kategoriBaru = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $pihakBaru    = (int)($_POST['pihak_id'] ?? 0) ?: null;
    $jumlahBaru   = validasi_jumlah($_POST['jumlah'] ?? '');
    $tglBaru      = $_POST['tanggal'] ?? '';
    $ketBaru      = trim($_POST['keterangan'] ?? '');

    if ($namaBaru === '' && $pihakBaru) {
        $p = q_one($conn, $isMasuk ? "SELECT nama FROM muzakki WHERE id = ?" : "SELECT nama FROM mustahik WHERE id = ?", 'i', [$pihakBaru]);
        if ($p) $namaBaru = $p['nama'];
    }

    if ($namaBaru === '' && $isMasuk) {
        $namaBaru = 'Hamba Allah';
    }

    if ($namaBaru === '' || mb_strlen($namaBaru) > 150) {
        flash('danger', 'Nama pihak wajib diisi dan maksimal 150 karakter.');
    } elseif ($jumlahBaru === false) {
        flash('danger', 'Jumlah harus berupa angka bulat positif.');
    } elseif (!tanggal_valid($tglBaru) || $tglBaru > date('Y-m-d')) {
        flash('danger', 'Tanggal transaksi tidak valid atau melebihi hari ini.');
    } else {
        $data_lama = [
            'nama'        => $data['nama'],
            'kategori_id' => $data['kategori_id'],
            'pihak_id'    => $data['pihak_id'],
            'jumlah'      => $data['jumlah'],
            'tanggal'     => $data['tgl'],
            'keterangan'  => $data['keterangan'],
        ];
        $data_baru = [
            'nama'        => $namaBaru,
            'kategori_id' => $kategoriBaru,
            'pihak_id'    => $pihakBaru,
            'jumlah'      => $jumlahBaru,
            'tanggal'     => $tglBaru,
            'keterangan'  => $ketBaru,
        ];

        $galat = jalankan_transaksi($conn, function () use ($conn, $namaBaru, $kategoriBaru, $pihakBaru, $jumlahBaru, $tglBaru, $ketBaru, $id) {
            q($conn,
                "UPDATE transaksi
                    SET nama_pihak = ?, kategori_id = ?, pihak_id = ?, jumlah = ?,
                        tanggal = TIMESTAMP(?, CURTIME()), keterangan = ?
                  WHERE id = ?",
                'siidssi',
                [$namaBaru, $kategoriBaru, $pihakBaru, $jumlahBaru, $tglBaru, $ketBaru, $id]);
        });

        if ($galat) {
            flash('danger', $galat);
        } else {
            catat_audit($conn, 'edit', 'transaksi', $id, $data_lama, $data_baru);
            flash('success', 'Perubahan data transaksi berhasil disimpan.');
            redirect('index.php');
        }
    }
}

$judul = 'Edit ' . ($isMasuk ? 'Pemasukan' : 'Pengeluaran');
$aktif = 'dashboard';
require 'partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="index.php" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Transaksi
                </a>
                <h2 class="h5 fw-bold text-dark mt-1"><?= e($judul) ?></h2>
            </div>
            <span class="badge-friendly <?= $isMasuk ? 'badge-masuk' : 'badge-keluar' ?>">
                <span class="badge-dot"></span>
                <?= $isMasuk ? 'Pemasukan Kas' : 'Pengeluaran Kas' ?>
            </span>
        </div>

        <div class="dashboard-card shadow-sm">
            <div class="dashboard-card-head">
                <h2 class="dashboard-card-title h6 mb-0">
                    <i class="bi bi-pencil-square text-primary"></i>Formulir Perubahan Data Transaksi
                </h2>
                <span class="text-muted small">ID Transaksi: #<?= $id ?></span>
            </div>
            <div class="dashboard-card-body p-4">
                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <!-- Pilih dari Master (Opsional) -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">
                            Hubungkan ke Master <?= $isMasuk ? 'Muzakki (Donatur)' : 'Mustahik (Penerima)' ?>
                        </label>
                        <select name="pihak_id" id="selectPihak" class="form-select">
                            <option value="">-- Pilih dari Master (Opsional) --</option>
                            <?php foreach ($pihak_list as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"
                                        data-nama="<?= e($p['nama']) ?>"
                                        <?= (int)($data['pihak_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['nama']) ?> <?= !empty($p['nik_nip']) ? '('.e($p['nik_nip']).')' : (!empty($p['asnaf']) ? '('.e(ucfirst($p['asnaf'])).')' : '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Input Nama -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-muted mb-0" for="inputNama">
                                <?= $isMasuk ? 'Nama Penyumbang / Muzakki' : 'Nama Penerima / Mustahik' ?> <span class="text-danger">*</span>
                            </label>
                            <?php if ($isMasuk): ?>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 text-muted"
                                    onclick="document.getElementById('inputNama').value = 'Hamba Allah';">
                                <i class="bi bi-magic me-1"></i>Hamba Allah
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" id="inputNama" name="nama" class="form-control"
                                   value="<?= e($_POST['nama'] ?? $data['nama']) ?>" maxlength="150" required>
                        </div>
                    </div>

                    <!-- Dropdown Kategori Dana -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="selectKategori">
                            Kategori <?= $isMasuk ? 'Pemasukan' : 'Penyaluran' ?> <span class="text-danger">*</span>
                        </label>
                        <select name="kategori_id" id="selectKategori" class="form-select" required>
                            <option value="">-- Pilih Kategori Dana --</option>
                            <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= (int)$k['id'] ?>"
                                        <?= (int)($_POST['kategori_id'] ?? $data['kategori_id']) === (int)$k['id'] ? 'selected' : '' ?>>
                                    <?= e($k['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Nominal -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="inputJumlah">Nominal (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold text-muted">Rp</span>
                            <input type="number" id="inputJumlah" name="jumlah" class="form-control" min="1" step="1"
                                   value="<?= e((int)($_POST['jumlah'] ?? $data['jumlah'])) ?>" required>
                        </div>
                        <div class="form-text text-muted small">Pastikan saldo kas tidak bernilai negatif setelah perubahan.</div>
                    </div>

                    <!-- Tanggal -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="inputTanggal">Tanggal Transaksi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                            <input type="date" id="inputTanggal" name="tanggal" class="form-control"
                                   value="<?= e($_POST['tanggal'] ?? $data['tgl']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Keterangan / Catatan -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted" for="inputKeterangan">Keterangan / Catatan Tambahan</label>
                        <textarea id="inputKeterangan" name="keterangan" class="form-control" rows="2"
                                  placeholder="Catatan tambahan bila ada..."><?= e($_POST['keterangan'] ?? $data['keterangan']) ?></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 pt-2 border-top">
                        <a href="index.php" class="btn-petro-outline">
                            <i class="bi bi-x-circle me-1"></i>Batal
                        </a>
                        <button type="submit" class="btn <?= $isMasuk ? 'btn-petro-green' : 'btn-petro-yellow' ?> px-4">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('selectPihak').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.nama) {
            document.getElementById('inputNama').value = opt.dataset.nama;
        }
    });
</script>

<?php require 'partials/footer.php'; ?>
</body>
</html>