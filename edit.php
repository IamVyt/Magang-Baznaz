<?php
require 'fungsi.php';
wajib_login();

$tipe = $_GET['tipe'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!in_array($tipe, ['masuk', 'keluar'], true) || $id <= 0) {
    flash('danger', 'Parameter data yang ingin diubah tidak valid.');
    redirect('index.php');
}

$tabel = ($tipe === 'masuk') ? 'penyumbang' : 'penerima';
$kolomNama = ($tipe === 'masuk') ? 'nama_penyumbang' : 'nama_penerima';
$kolomJumlah = ($tipe === 'masuk') ? 'jumlah_diterima' : 'jumlah_keluar';

// Ambil data lama
$data = q_one($conn, "SELECT id, $kolomNama AS nama, $kolomJumlah AS jumlah, DATE(tanggal) AS tgl FROM $tabel WHERE id = ?", 'i', [$id]);
if (!$data) {
    flash('danger', 'Data transaksi tidak ditemukan.');
    redirect('index.php');
}

// Proses form update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect("edit.php?tipe=$tipe&id=$id");
    }

    $namaBaru   = trim($_POST['nama'] ?? '');
    $jumlahBaru = validasi_jumlah($_POST['jumlah'] ?? '');
    $tglBaru    = $_POST['tanggal'] ?? '';

    if ($namaBaru === '' || mb_strlen($namaBaru) > 100) {
        flash('danger', 'Nama wajib diisi dan maksimal 100 karakter.');
    } elseif ($jumlahBaru === false) {
        flash('danger', 'Jumlah harus berupa angka bulat positif.');
    } elseif (!tanggal_valid($tglBaru) || $tglBaru > date('Y-m-d')) {
        flash('danger', 'Tanggal transaksi tidak valid atau melebihi hari ini.');
    } else {
        $galat = jalankan_transaksi($conn, function () use ($conn, $tabel, $kolomNama, $kolomJumlah, $namaBaru, $jumlahBaru, $tglBaru, $id) {
            q($conn, "UPDATE $tabel SET $kolomNama = ?, $kolomJumlah = ?, tanggal = TIMESTAMP(?, CURTIME()) WHERE id = ?",
                'sdsi', [$namaBaru, $jumlahBaru, $tglBaru, $id]);
        });

        if ($galat) {
            flash('danger', $galat);
        } else {
            flash('success', 'Perubahan data transaksi berhasil disimpan.');
            redirect('index.php');
        }
    }
}

$isMasuk = ($tipe === 'masuk');
$judul = 'Edit ' . ($isMasuk ? 'Pemasukan' : 'Pengeluaran');
$aktif = 'dashboard';
require 'partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
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
                    <i class="bi bi-pencil-square text-primary"></i>Formulir Perubahan Data
                </h2>
                <span class="text-muted small">ID Transaksi: #<?= $id ?></span>
            </div>
            <div class="dashboard-card-body p-4">
                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="nama">
                            <?= $isMasuk ? 'Nama Penyumbang / Donatur' : 'Nama Penerima Manfaat' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" id="nama" name="nama" class="form-control"
                                   value="<?= e($_POST['nama'] ?? $data['nama']) ?>" maxlength="100" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted" for="jumlah">Nominal (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold text-muted">Rp</span>
                            <input type="number" id="jumlah" name="jumlah" class="form-control" min="1" step="1"
                                   value="<?= e((int)($_POST['jumlah'] ?? $data['jumlah'])) ?>" required>
                        </div>
                        <div class="form-text text-muted small">Pastikan saldo kas tidak bernilai negatif setelah perubahan.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted" for="tanggal">Tanggal Transaksi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                            <input type="date" id="tanggal" name="tanggal" class="form-control"
                                   value="<?= e($_POST['tanggal'] ?? $data['tgl']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
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

<?php require 'partials/footer.php'; ?>
</body>
</html>