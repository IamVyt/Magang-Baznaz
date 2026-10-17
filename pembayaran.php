<?php
// Pengaturan metode pembayaran: QRIS resmi, rekening bank, dan e-wallet.
// Hanya admin yang boleh mengubah, karena nomor di sini menentukan ke mana donasi dikirim.
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';
wajib_staff();
siapkan_donasi($conn);

$boleh = ($_SESSION['role'] ?? '') === 'admin';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$boleh) {
        flash('danger', 'Hanya admin yang boleh mengubah metode pembayaran.');
        redirect('pembayaran.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa atau berkas terlalu besar. Silakan ulangi.');
        redirect('pembayaran.php');
    }
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'qris_unggah') {
        $galat = simpan_qris($_FILES['qris'] ?? []);
        if ($galat) {
            flash('danger', $galat);
        } else {
            catat_audit($conn, 'qris_unggah', 'pengaturan', null, null, ['qris' => 'diperbarui']);
            flash('success', 'Gambar QRIS diperbarui. Metode QRIS sekarang bisa dipilih donatur.');
        }
    } elseif ($aksi === 'qris_hapus') {
        hapus_qris();
        catat_audit($conn, 'qris_hapus', 'pengaturan', null, null, ['qris' => 'dihapus']);
        flash('success', 'Gambar QRIS dihapus. Metode QRIS tidak lagi bisa dipilih donatur.');
    } elseif ($aksi === 'simpan') {
        $id     = (int)($_POST['id'] ?? 0);
        $jenis  = $_POST['jenis'] ?? '';
        $nama   = trim((string)($_POST['nama'] ?? ''));
        $nomor  = trim((string)($_POST['nomor'] ?? ''));
        $an     = trim((string)($_POST['atas_nama'] ?? ''));
        $aktif  = isset($_POST['aktif']) ? 1 : 0;
        $galat  = [];
        if ($id === 0 && !in_array($jenis, ['bank', 'ewallet'], true)) $galat[] = 'Pilih jenis bank atau e-wallet.';
        if (mb_strlen($nama) < 2 || mb_strlen($nama) > 60) $galat[] = 'Nama harus 2 sampai 60 karakter.';
        if ($nomor !== '' && !preg_match('/^[0-9][0-9 .\-]{4,38}[0-9]$/', $nomor)) $galat[] = 'Nomor hanya boleh berisi angka, spasi, titik, dan strip (6 sampai 40 karakter).';
        if ($aktif && $nomor === '') $galat[] = 'Isi nomor terlebih dahulu sebelum mengaktifkan.';
        if (mb_strlen($an) > 100) $galat[] = 'Atas nama maksimal 100 karakter.';

        if (!$galat) {
            if ($id > 0) {
                $lama = q_one($conn, "SELECT * FROM rekening_bayar WHERE id = ?", 'i', [$id]);
                if (!$lama) {
                    $galat[] = 'Data tidak ditemukan.';
                } else {
                    q($conn, "UPDATE rekening_bayar SET nama = ?, nomor = ?, atas_nama = ?, aktif = ? WHERE id = ?", 'sssii', [$nama, $nomor, $an, $aktif, $id]);
                    catat_audit($conn, 'rekening_ubah', 'rekening_bayar', $id,
                        ['nama' => $lama['nama'], 'nomor' => $lama['nomor'], 'aktif' => (int)$lama['aktif']], ['nama' => $nama, 'nomor' => $nomor, 'aktif' => $aktif]);
                    flash('success', $nama . ' diperbarui. Perubahan berlaku untuk donasi baru.');
                }
            } else {
                $kodeBaru = substr(slug($nama), 0, 20);
                if ($kodeBaru === '' || q_one($conn, "SELECT id FROM rekening_bayar WHERE jenis = ? AND kode = ?", 'ss', [$jenis, $kodeBaru])) {
                    $galat[] = 'Nama tersebut sudah ada atau tidak valid. Gunakan nama lain.';
                } else {
                    $urutan = (int)(q_one($conn, "SELECT COALESCE(MAX(urutan), 0) + 1 AS n FROM rekening_bayar WHERE jenis = ?", 's', [$jenis])['n'] ?? 1);
                    q($conn, "INSERT INTO rekening_bayar (jenis, kode, nama, nomor, atas_nama, aktif, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)", 'sssssii', [$jenis, $kodeBaru, $nama, $nomor, $an, $aktif, $urutan]);
                    catat_audit($conn, 'rekening_tambah', 'rekening_bayar', (int)mysqli_insert_id($conn), null, ['nama' => $nama, 'nomor' => $nomor, 'aktif' => $aktif]);
                    flash('success', $nama . ' ditambahkan.');
                }
            }
        }
        if ($galat) flash('danger', implode(' ', $galat));
    } elseif ($aksi === 'hapus') {
        $id   = (int)($_POST['id'] ?? 0);
        $lama = q_one($conn, "SELECT * FROM rekening_bayar WHERE id = ?", 'i', [$id]);
        if ($lama) {
            q($conn, "DELETE FROM rekening_bayar WHERE id = ?", 'i', [$id]);
            catat_audit($conn, 'rekening_hapus', 'rekening_bayar', $id, ['nama' => $lama['nama'], 'nomor' => $lama['nomor']], null);
            flash('success', $lama['nama'] . ' dihapus. Donasi yang sudah dibuat tetap menyimpan data pembayarannya.');
        } else {
            flash('danger', 'Data tidak ditemukan.');
        }
    } else {
        flash('danger', 'Permintaan tidak valid.');
    }
    redirect('pembayaran.php');
}

$daftar = q_all($conn, "SELECT * FROM rekening_bayar ORDER BY jenis, urutan, nama");
$qris   = qris_berkas();
$siap   = metode_tersedia($conn);

$title = 'Metode pembayaran';
$aktif = 'pembayaran';
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Metode pembayaran</h1><p>Atur QRIS, rekening bank, dan e-wallet UPZ. Donatur hanya bisa memilih metode yang sudah diaktifkan dan terisi nomornya<?= $boleh ? '.' : '. Peran Anda hanya dapat melihat data.' ?></p></div>

 <div class="grid4">
  <?php foreach (['transfer' => 'Transfer bank', 'ewallet' => 'E-wallet', 'qris' => 'QRIS', 'tunai' => 'Tunai'] as $k => $lbl): ?>
  <div class="c <?= $siap[$k] ? 'g' : 'l' ?> stat"><small><?= e($lbl) ?></small><b><?= $siap[$k] ? 'Tersedia' : 'Belum aktif' ?></b><small><?= $siap[$k] ? 'Bisa dipilih donatur' : 'Tidak tampil sebagai pilihan' ?></small></div>
  <?php endforeach; ?>
 </div>

 <div class="card">
  <h2 class="sub">Kode QRIS <small>Gambar resmi dari bank atau penyedia pembayaran UPZ</small></h2>
  <div class="qris-admin">
   <?php if ($qris): ?>
   <img class="qris" src="<?= e($qris) ?>?v=<?= (int)@filemtime(__DIR__ . '/' . $qris) ?>" alt="QRIS UPZ BAZNAS" width="180" height="180">
   <?php else: ?>
   <div class="kosong-qris">QRIS belum diunggah</div>
   <?php endif; ?>
   <div>
    <p class="bantu">Unggah gambar kode QRIS yang diterbitkan resmi untuk UPZ (JPG, PNG, atau WebP, maksimal 2 MB). Sistem ini tidak membuat kode QRIS sendiri, karena kode harus berasal dari bank atau penyedia pembayaran yang menampung dana UPZ.</p>
    <?php if ($boleh): ?>
    <form method="post" enctype="multipart/form-data" class="acts">
     <?= csrf_field() ?>
     <input type="hidden" name="aksi" value="qris_unggah">
     <input type="file" name="qris" accept="image/jpeg,image/png,image/webp" required>
     <button class="btn sm" type="submit"><?= $qris ? 'Ganti QRIS' : 'Unggah QRIS' ?></button>
    </form>
    <?php if ($qris): ?>
    <form method="post" data-konfirmasi="Hapus gambar QRIS? Donatur tidak bisa lagi memilih QRIS.">
     <?= csrf_field() ?>
     <input type="hidden" name="aksi" value="qris_hapus">
     <button class="lnk del" type="submit">Hapus QRIS</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
   </div>
  </div>
 </div>

 <div class="bar">
  <span></span>
  <?php if ($boleh): ?><div class="acts"><button class="btn g sm" type="button" data-dialog="dlg-rek" data-rek data-judul="Tambah rekening atau e-wallet">+ Tambah</button></div><?php endif; ?>
 </div>

 <div class="card">
  <h2 class="sub">Rekening bank dan e-wallet <small><?= count($daftar) ?> data</small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>Nama</th><th>Jenis</th><th>Nomor</th><th>Atas nama</th><th>Status</th><?php if ($boleh): ?><th class="r">Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php if (!$daftar): ?><tr><td colspan="<?= $boleh ? 6 : 5 ?>"><div class="empty"><b>Belum ada data</b></div></td></tr><?php endif; ?>
    <?php foreach ($daftar as $r): $tampil = $r['aktif'] && $r['nomor'] !== ''; ?>
     <tr>
      <td><b><?= e($r['nama']) ?></b></td>
      <td><?= $r['jenis'] === 'bank' ? 'Bank' : 'E-wallet' ?></td>
      <td class="nw"><?= $r['nomor'] !== '' ? e($r['nomor']) : '<small>Belum diisi</small>' ?></td>
      <td><?= $r['atas_nama'] !== '' ? e($r['atas_nama']) : '<small>-</small>' ?></td>
      <td><span class="badge <?= $tampil ? 'in' : 'off' ?>"><?= $tampil ? 'Aktif' : 'Nonaktif' ?></span></td>
      <?php if ($boleh): ?>
      <td class="r nw">
       <button class="lnk" type="button" data-dialog="dlg-rek" data-rek data-judul="Ubah <?= e($r['nama']) ?>" data-row="<?= e(json_encode(['id' => (int)$r['id'], 'jenis' => $r['jenis'], 'nama' => $r['nama'], 'nomor' => $r['nomor'], 'atas_nama' => $r['atas_nama'], 'aktif' => (int)$r['aktif']])) ?>">Ubah</button>
       <button class="lnk del" type="button" data-dialog="dlg-hapus-rek" data-hapus-rek="<?= (int)$r['id'] ?>" data-nama="<?= e($r['nama']) ?>">Hapus</button>
      </td>
      <?php endif; ?>
     </tr>
    <?php endforeach; ?>
    </tbody>
   </table>
  </div>
 </div>
</section>

<?php if ($boleh): ?>
<dialog class="dlg" id="dlg-rek" aria-labelledby="r-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="r-judul"><span>Tambah rekening atau e-wallet</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <input type="hidden" name="id" id="r-id" value="0">
  <label for="r-jenis">Jenis</label>
  <select id="r-jenis" name="jenis"><option value="bank">Bank</option><option value="ewallet">E-wallet</option></select>
  <label for="r-nama">Nama bank atau e-wallet</label>
  <input id="r-nama" type="text" name="nama" maxlength="60" required>
  <label for="r-nomor">Nomor rekening atau nomor e-wallet</label>
  <input id="r-nomor" type="text" name="nomor" maxlength="40" inputmode="numeric">
  <label for="r-an">Atas nama</label>
  <input id="r-an" type="text" name="atas_nama" maxlength="100">
  <label class="cek"><input id="r-aktif" type="checkbox" name="aktif" value="1"><span>Aktif, tampil sebagai pilihan bagi donatur</span></label>
  <small class="bantu">Perubahan nomor hanya berlaku untuk donasi baru. Donasi yang sudah dibuat tetap menyimpan nomor yang dipilih donatur saat itu.</small>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Simpan</button></div>
 </form>
</dialog>

<dialog class="dlg" id="dlg-hapus-rek" aria-labelledby="h-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="h-judul"><span>Hapus data ini?</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="hapus">
  <input type="hidden" name="id" id="h-id">
  <p><b id="h-nama"></b> akan dihapus dari daftar pilihan donatur.</p>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Hapus</button></div>
 </form>
</dialog>
<?php endif; ?>

<?php include __DIR__ . '/php/app_footer.php'; ?>
