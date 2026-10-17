<?php
require __DIR__ . '/php/fungsi.php';
wajib_staff();

// ===== Proses catat, ubah, dan batalkan (void) transaksi =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!can_input()) {
        flash('danger', 'Anda tidak memiliki izin untuk mengubah transaksi.');
        redirect('dashboard.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('dashboard.php');
    }
    $aksi       = $_POST['aksi'] ?? 'tambah';
    $id         = (int)($_POST['id'] ?? 0);
    $tipe       = $_POST['tipe'] ?? '';
    $kategori   = (int)($_POST['kategori_id'] ?? 0);
    $pihak      = (int)($_POST['pihak_id'] ?? 0) ?: null;
    $nama       = trim($_POST['nama'] ?? '');
    $jumlah     = validasi_jumlah($_POST['jumlah'] ?? '');
    $tgl        = $_POST['tanggal'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
    $alasan     = trim($_POST['alasan'] ?? '');
    $userId     = $_SESSION['user_id'] ?? null;

    if ($aksi === 'void') {
        $lama = $id > 0 ? q_one($conn, "SELECT * FROM transaksi WHERE id = ? AND dibatalkan_at IS NULL", 'i', [$id]) : null;
        if (!$lama) {
            flash('danger', 'Transaksi tidak ditemukan atau sudah dibatalkan.');
        } elseif ($alasan === '' || mb_strlen($alasan) > 255) {
            flash('danger', 'Alasan pembatalan wajib diisi (maksimal 255 karakter).');
        } else {
            $galat = jalankan_transaksi($conn, fn() => q($conn, "UPDATE transaksi SET dibatalkan_at = NOW(), alasan_batal = ? WHERE id = ?", 'si', [$alasan, $id]));
            if ($galat) {
                flash('danger', $galat);
            } else {
                catat_audit($conn, 'void', 'transaksi', $id, ['nama' => $lama['nama_pihak'], 'jumlah' => $lama['jumlah']], ['alasan' => $alasan]);
                flash('success', 'Transaksi dibatalkan. Data tetap tersimpan dengan penanda void.');
            }
        }
        redirect('dashboard.php');
    }

    $lama = ($aksi === 'ubah' && $id > 0) ? q_one($conn, "SELECT * FROM transaksi WHERE id = ? AND dibatalkan_at IS NULL", 'i', [$id]) : null;
    if ($aksi === 'ubah' && !$lama) {
        flash('danger', 'Transaksi tidak ditemukan atau sudah dibatalkan.');
        redirect('dashboard.php');
    }
    if ($lama) $tipe = $lama['tipe'];   // jenis transaksi tidak boleh diganti saat ubah
    if ($nama === '' && $tipe === 'masuk') $nama = 'Hamba Allah';

    // Kategori harus ada, aktif, dan sesuai jenis transaksinya
    $kat = in_array($tipe, ['masuk', 'keluar'], true)
        ? q_one($conn, "SELECT id FROM kategori_dana WHERE id = ? AND tipe = ? AND aktif = 1", 'is', [$kategori, $tipe]) : null;

    if (!in_array($aksi, ['tambah', 'ubah'], true) || !in_array($tipe, ['masuk', 'keluar'], true)) {
        flash('danger', 'Permintaan tidak valid.');
    } elseif ($nama === '' || mb_strlen($nama) > 150) {
        flash('danger', 'Nama pihak wajib diisi dan maksimal 150 karakter.');
    } elseif (!$kat) {
        flash('danger', 'Kategori transaksi wajib dipilih.');
    } elseif ($jumlah === false) {
        flash('danger', 'Jumlah harus berupa angka bulat lebih dari 0.');
    } elseif (!tanggal_valid($tgl) || $tgl > date('Y-m-d')) {
        flash('danger', 'Tanggal tidak valid atau melebihi hari ini.');
    } else {
        $baru = ['nama' => $nama, 'tipe' => $tipe, 'kategori_id' => $kategori, 'jumlah' => $jumlah, 'tanggal' => $tgl];
        $newId = $id;
        if ($aksi === 'ubah') {
            $galat = jalankan_transaksi($conn, fn() => q($conn,
                "UPDATE transaksi SET nama_pihak = ?, kategori_id = ?, jumlah = ?, tanggal = TIMESTAMP(?, CURTIME()), keterangan = ? WHERE id = ?",
                'sidssi', [$nama, $kategori, $jumlah, $tgl, $keterangan, $id]));
        } else {
            $galat = jalankan_transaksi($conn, function () use ($conn, $tipe, $kategori, $pihak, $nama, $jumlah, $tgl, $keterangan, $userId, &$newId) {
                q($conn, "INSERT INTO transaksi (tipe, kategori_id, pihak_id, nama_pihak, jumlah, tanggal, keterangan, dibuat_oleh) VALUES (?, ?, ?, ?, ?, TIMESTAMP(?, CURTIME()), ?, ?)",
                    'siisdssi', [$tipe, $kategori, $pihak, $nama, $jumlah, $tgl, $keterangan, $userId]);
                $newId = mysqli_insert_id($conn);
            });
        }
        if ($galat) {
            flash('danger', $galat);
        } else {
            catat_audit($conn, $aksi === 'ubah' ? 'edit' : 'tambah', 'transaksi', (int)$newId,
                $lama ? ['nama' => $lama['nama_pihak'], 'jumlah' => $lama['jumlah'], 'kategori_id' => $lama['kategori_id']] : null, $baru);
            flash('success', $aksi === 'ubah' ? 'Perubahan data transaksi berhasil disimpan.' : ($tipe === 'masuk' ? 'Pemasukan' : 'Pengeluaran') . ' kas berhasil dicatat.');
        }
    }
    redirect('dashboard.php');
}

// ===== Data dashboard =====
$katAll  = q_all($conn, "SELECT id, nama, tipe FROM kategori_dana WHERE aktif = 1 ORDER BY tipe ASC, nama ASC");
$katMap  = ['masuk' => [], 'keluar' => []];
foreach ($katAll as $k) $katMap[$k['tipe']][] = ['id' => (int)$k['id'], 'nama' => $k['nama']];
$muzakki  = q_all($conn, "SELECT id, nama FROM muzakki WHERE aktif = 1 ORDER BY nama ASC");
$mustahik = q_all($conn, "SELECT id, nama FROM mustahik WHERE aktif = 1 ORDER BY nama ASC");

$f  = filter_transaksi(true);   // untuk tabel
$ft = filter_transaksi(false);  // untuk kartu total
$adaFilter = $f['cari'] !== '' || $f['mulai'] !== '' || $f['selesai'] !== '' || $f['tipe'] !== 'semua' || $f['kategori'] > 0;
$saldo = hitung_saldo($conn);

$total = ['masuk' => 0, 'keluar' => 0];
$jml   = ['masuk' => 0, 'keluar' => 0];
foreach (q_all($conn, "SELECT tipe, COALESCE(SUM(jumlah),0) AS total, COUNT(*) AS n FROM " . TRANSAKSI . " WHERE dibatalkan_at IS NULL " . $ft['sql'] . " GROUP BY tipe", $ft['types'], $ft['params']) as $r) {
    $total[$r['tipe']] = (float)$r['total'];
    $jml[$r['tipe']]   = (int)$r['n'];
}

$perHal = 8;
$baris  = (int)q_one($conn, "SELECT COUNT(*) AS c FROM " . TRANSAKSI . " WHERE 1=1 " . $f['sql'], $f['types'], $f['params'])['c'];
$totalHal = max(1, (int)ceil($baris / $perHal));
$hal = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$riwayat = q_all($conn, "SELECT * FROM " . TRANSAKSI . " WHERE 1=1 " . $f['sql'] . " ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?",
    $f['types'] . 'ii', array_merge($f['params'], [$perHal, ($hal - 1) * $perHal]));

$qs = array_filter(['cari' => $f['cari'], 'tgl_mulai' => $f['mulai'], 'tgl_selesai' => $f['selesai'],
    'tipe' => $f['tipe'] === 'semua' ? '' : $f['tipe'], 'kategori' => $f['kategori'] > 0 ? $f['kategori'] : ''], fn($v) => $v !== '');

// Grafik 6 bulan terakhir (transaksi void tidak dihitung)
$namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulan = []; $label = [];
$d = new DateTime('first day of this month');
$d->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $bulan[$d->format('Y-m')] = ['masuk' => 0, 'keluar' => 0];
    $label[] = $namaBulan[(int)$d->format('n') - 1] . ' ' . $d->format('y');
    $d->modify('+1 month');
}
foreach (q_all($conn, "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bln, tipe, SUM(jumlah) AS total FROM " . TRANSAKSI . " WHERE dibatalkan_at IS NULL AND tanggal >= ? GROUP BY bln, tipe",
    's', [array_key_first($bulan) . '-01 00:00:00']) as $r) {
    if (isset($bulan[$r['bln']])) $bulan[$r['bln']][$r['tipe']] = (float)$r['total'];
}

$title = 'Transaksi kas';
$aktif = 'dashboard';
$scripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'];
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Transaksi kas</h1><p>Selamat datang kembali, <b><?= e($_SESSION['nama'] ?? 'Petugas') ?></b>. Peran Anda: <b><?= e(ucfirst($_SESSION['role'] ?? 'admin')) ?></b><?= can_input() ? '.' : ', hanya dapat melihat data.' ?></p></div>

 <?php if ($menunggu > 0): ?>
 <div class="alert info" role="status"><b><?= (int)$menunggu ?> donasi online sudah dibayar donatur dan menunggu pengecekan.</b> Donasi baru masuk ke saldo setelah Anda mengonfirmasinya. <a class="ul" href="donasi_masuk.php">Cek sekarang</a></div>
 <?php endif; ?>

 <div class="grid4">
  <div class="c g stat"><small>Saldo kas saat ini</small><b><?= rupiah($saldo) ?></b><small>Akumulasi total dana kas</small></div>
  <div class="c l stat"><small>Total pemasukan</small><b><?= rupiah($total['masuk']) ?></b><small><?= $jml['masuk'] ?> donasi diterima</small></div>
  <div class="c l stat"><small>Total pengeluaran</small><b><?= rupiah($total['keluar']) ?></b><small><?= $jml['keluar'] ?> transaksi tersalurkan</small></div>
  <div class="c d stat"><small>Aktivitas transaksi</small><b><?= $jml['masuk'] + $jml['keluar'] ?></b><small>Total mutasi kas tercatat</small></div>
 </div>

 <div class="bar">
  <div class="pills">
   <?php foreach (['semua' => 'Semua transaksi', 'masuk' => 'Pemasukan', 'keluar' => 'Pengeluaran'] as $k => $lbl): ?>
   <a class="pill <?= $f['tipe'] === $k ? 'on' : '' ?>" href="?<?= e(http_build_query(array_merge($qs, ['tipe' => $k, 'hal' => 1]))) ?>"><?= $lbl ?></a>
   <?php endforeach; ?>
  </div>
  <div class="acts">
   <?php if (can_input()): ?>
   <button class="btn g sm" type="button" data-dialog="dlg-form" data-aksi="tambah" data-tipe="masuk">+ Pemasukan</button>
   <button class="btn sm" type="button" data-dialog="dlg-form" data-aksi="tambah" data-tipe="keluar">+ Pengeluaran</button>
   <?php endif; ?>
   <button class="btn o sm" type="button" data-toggle="filter">Filter<?= $adaFilter ? ' (aktif)' : '' ?></button>
   <a class="btn o sm" href="export_excel.php?<?= e(http_build_query($qs)) ?>">Excel</a>
  </div>
 </div>

 <form class="card fbox" id="filter" method="get" <?= $adaFilter ? '' : 'hidden' ?>>
  <input type="hidden" name="tipe" value="<?= e($f['tipe']) ?>">
  <div><label for="cari">Cari nama atau keterangan</label><input id="cari" type="text" name="cari" value="<?= e($f['cari']) ?>" placeholder="Ketik nama atau catatan"></div>
  <div><label for="kt">Kategori dana</label>
   <select id="kt" name="kategori"><option value="">Semua kategori</option>
    <?php foreach ($katAll as $k): ?><option value="<?= (int)$k['id'] ?>" <?= $f['kategori'] === (int)$k['id'] ? 'selected' : '' ?>>[<?= $k['tipe'] === 'masuk' ? 'Masuk' : 'Keluar' ?>] <?= e($k['nama']) ?></option><?php endforeach; ?>
   </select></div>
  <div><label for="m">Dari tanggal</label><input id="m" type="date" name="tgl_mulai" value="<?= e($f['mulai']) ?>"></div>
  <div><label for="s">Sampai tanggal</label><input id="s" type="date" name="tgl_selesai" value="<?= e($f['selesai']) ?>"></div>
  <div class="acts"><button class="btn sm" type="submit">Terapkan</button><a class="btn o sm" href="dashboard.php">Reset</a></div>
 </form>
</section>

<section class="cols">
 <div class="card">
  <h2 class="sub">Riwayat transaksi <small><?= $baris ?> data<?= $adaFilter ? ', filter diterapkan' : '' ?></small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Nama dan keterangan</th><th>Jenis</th><th class="r">Jumlah</th><th class="r">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$riwayat): ?>
     <tr><td colspan="5"><div class="empty"><b>Tidak ada transaksi</b><p>Belum ada pencatatan yang cocok dengan kriteria Anda.</p>
      <?php if (can_input()): ?><button class="btn g sm" type="button" data-dialog="dlg-form" data-aksi="tambah" data-tipe="masuk">Catat transaksi pertama</button><?php endif; ?></div></td></tr>
    <?php endif; ?>
    <?php foreach ($riwayat as $r): $in = $r['tipe'] === 'masuk'; $void = $r['dibatalkan_at'] !== null; ?>
     <tr class="<?= $void ? 'void' : '' ?>" <?= $void ? 'title="Void: ' . e($r['alasan_batal'] ?? '-') . '"' : '' ?>>
      <td class="nw"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
      <td><b><?= e($r['nama']) ?></b>
       <span class="meta"><?php if ($r['kategori_nama']): ?><i class="cat"><?= e($r['kategori_nama']) ?></i><?php endif; ?><?php if ($r['keterangan']): ?><small title="<?= e($r['keterangan']) ?>"><?= e($r['keterangan']) ?></small><?php endif; ?></span></td>
      <td><?php if ($void): ?><span class="badge void">Void</span><?php else: ?><span class="badge <?= $in ? 'in' : 'out' ?>"><?= $in ? 'Pemasukan' : 'Pengeluaran' ?></span><?php endif; ?></td>
      <td class="r nw"><b><?= $in ? '+' : '-' ?> <?= rupiah($r['jumlah']) ?></b></td>
      <td class="r nw">
       <?php if ($void): ?><small>Dibatalkan</small>
       <?php elseif (can_input()): ?>
       <button class="lnk" type="button" data-dialog="dlg-form" data-aksi="ubah" data-tipe="<?= e($r['tipe']) ?>" data-id="<?= (int)$r['id'] ?>" data-nama="<?= e($r['nama']) ?>" data-jumlah="<?= (int)$r['jumlah'] ?>" data-tanggal="<?= e(date('Y-m-d', strtotime($r['tanggal']))) ?>" data-kategori="<?= (int)$r['kategori_id'] ?>" data-keterangan="<?= e($r['keterangan']) ?>">Ubah</button>
       <button class="lnk del" type="button" data-dialog="dlg-void" data-id="<?= (int)$r['id'] ?>" data-nama="<?= e($r['nama']) ?>">Batalkan</button>
       <?php endif; ?>
      </td>
     </tr>
    <?php endforeach; ?>
    </tbody>
   </table>
  </div>
  <?php if ($totalHal > 1): ?>
  <div class="pg">
   <small>Halaman <?= $hal ?> dari <?= $totalHal ?></small>
   <div>
    <?php if ($hal > 1): ?><a href="?<?= e(http_build_query($qs + ['hal' => $hal - 1])) ?>">←</a><?php endif; ?>
    <?php for ($i = max(1, $hal - 2); $i <= min($totalHal, $hal + 2); $i++): ?>
    <a class="<?= $i === $hal ? 'on' : '' ?>" href="?<?= e(http_build_query($qs + ['hal' => $i])) ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($hal < $totalHal): ?><a href="?<?= e(http_build_query($qs + ['hal' => $hal + 1])) ?>">→</a><?php endif; ?>
   </div>
  </div>
  <?php endif; ?>
 </div>

 <div class="side">
  <div class="card">
   <h2 class="sub">Arus kas bulanan <small>6 bulan terakhir</small></h2>
   <div class="chart"><canvas id="grafik" aria-label="Grafik arus kas 6 bulan terakhir"></canvas></div>
  </div>
  <div class="card c d info">
   <span class="tg">Unit Pengumpul Zakat</span>
   <p>Pengelolaan dana zakat, infak, dan sedekah PT Petrokimia Gresik secara aman, transparan, dan akuntabel.</p>
   <p>Petugas aktif: <b><?= e($_SESSION['nama'] ?? 'Petugas') ?></b></p>
  </div>
 </div>
</section>

<?php if (can_input()): ?>
<dialog class="dlg" id="dlg-form" aria-labelledby="f-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="f-judul"><span>Catat pemasukan</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" id="f-aksi" value="tambah">
  <input type="hidden" name="tipe" id="f-tipe" value="masuk">
  <input type="hidden" name="id" id="f-id" value="0">
  <input type="hidden" name="pihak_id" id="f-pihak" value="">
  <div id="g-masuk"><label for="f-muzakki">Pilih muzakki (master donatur)</label>
   <select id="f-muzakki" data-pihak><option value="">Donatur baru atau input cepat</option>
    <?php foreach ($muzakki as $m): ?><option value="<?= (int)$m['id'] ?>" data-nama="<?= e($m['nama']) ?>"><?= e($m['nama']) ?></option><?php endforeach; ?></select></div>
  <div id="g-keluar" hidden><label for="f-mustahik">Pilih mustahik (master penerima)</label>
   <select id="f-mustahik" data-pihak><option value="">Penerima baru atau input cepat</option>
    <?php foreach ($mustahik as $m): ?><option value="<?= (int)$m['id'] ?>" data-nama="<?= e($m['nama']) ?>"><?= e($m['nama']) ?></option><?php endforeach; ?></select></div>
  <label for="f-nama" id="f-lnama">Nama penyumbang atau donatur <button class="lnk" type="button" id="f-hamba">Isi "Hamba Allah"</button></label>
  <input id="f-nama" type="text" name="nama" maxlength="150" required>
  <label for="f-kat" id="f-lkat">Kategori pemasukan</label>
  <select id="f-kat" name="kategori_id" required></select>
  <label for="f-jumlah">Nominal (Rp)</label>
  <input id="f-jumlah" type="number" name="jumlah" min="1" step="1" required>
  <label for="f-tgl">Tanggal transaksi</label>
  <input id="f-tgl" type="date" name="tanggal" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
  <label for="f-ket">Keterangan atau catatan tambahan</label>
  <textarea id="f-ket" name="keterangan" rows="2" placeholder="Catatan transaksi bila ada"></textarea>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Simpan</button></div>
 </form>
</dialog>

<dialog class="dlg" id="dlg-void" aria-labelledby="v-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="v-judul"><span>Batalkan transaksi?</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="void">
  <input type="hidden" name="id" id="v-id">
  <p>Transaksi atas nama <b id="v-nama"></b> akan dibatalkan (void). Data tetap tersimpan dengan penanda void dan tidak dihitung ke saldo.</p>
  <label for="v-alasan">Alasan pembatalan</label>
  <textarea id="v-alasan" name="alasan" rows="2" maxlength="255" placeholder="Contoh: data duplikat atau salah input" required></textarea>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Batalkan (void)</button></div>
 </form>
</dialog>
<?php endif; ?>

<script>
window.KATEGORI = <?= json_encode($katMap) ?>;
window.GRAFIK = {
  label: <?= json_encode($label) ?>,
  masuk: <?= json_encode(array_column($bulan, 'masuk')) ?>,
  keluar: <?= json_encode(array_column($bulan, 'keluar')) ?>
};
</script>

<?php include __DIR__ . '/php/app_footer.php'; ?>
