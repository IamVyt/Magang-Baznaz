<?php
// Donasi online dari halaman Salurkan. Admin dan bendahara mengecek pembayaran, ketua hanya melihat.
// Donasi baru masuk ke kas (tabel transaksi) setelah dikonfirmasi, sehingga status Sudah tersalurkan
// selalu berarti petugas sudah memastikan uangnya diterima.
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';
wajib_staff();
siapkan_donasi($conn);

// ===== Proses konfirmasi dan penolakan =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!can_input()) {
        flash('danger', 'Anda tidak memiliki izin untuk memproses donasi.');
        redirect('donasi_masuk.php');
    }
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('donasi_masuk.php');
    }
    $aksi   = $_POST['aksi'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? null;

    if ($aksi === 'terima') {
        $d = null;
        $trxId = 0;
        $pesan = null;
        // Satu transaksi database: baris donasi dikunci, dicatat ke kas, lalu ditandai Sudah tersalurkan.
        // Bila salah satu langkah gagal, semuanya dibatalkan sehingga kas dan donasi tidak pernah selisih.
        $galat = jalankan_transaksi($conn, function () use ($conn, $id, $userId, $metode, &$d, &$trxId, &$pesan) {
            $d = q_one($conn, "SELECT * FROM donasi WHERE id = ? AND status IN ('menunggu', 'dibayar') FOR UPDATE", 'i', [$id]);
            if (!$d) {
                $pesan = 'Donasi tidak ditemukan atau sudah diproses petugas lain.';
                return;
            }
            $kat = q_one($conn, "SELECT id FROM kategori_dana WHERE kode = ? AND tipe = 'masuk' AND aktif = 1", 's', [$d['kategori_kode']]);
            if (!$kat) {
                $pesan = 'Kategori dana ' . $d['kategori_kode'] . ' tidak ditemukan atau tidak aktif. Periksa menu Program dan kategori.';
                return;
            }
            $namaPihak = $d['samarkan_nama'] ? 'Hamba Allah' : $d['nama'];
            $namaMetode = $metode[$d['metode']][0] ?? $d['metode'];
            $via = $namaMetode . (($d['channel_nama'] && $d['channel_nama'] !== $namaMetode) ? ' (' . $d['channel_nama'] . ')' : '');
            $ket = 'Donasi online ' . $d['kode'] . ', ' . $d['program_nama'] . ', ' . $d['paket'] . ', via ' . $via;
            q($conn, "INSERT INTO transaksi (tipe, kategori_id, nama_pihak, jumlah, tanggal, keterangan, dibuat_oleh) VALUES ('masuk', ?, ?, ?, NOW(), ?, ?)",
                'isdsi', [$kat['id'], $namaPihak, $d['jumlah'], $ket, $userId]);
            $trxId = (int)mysqli_insert_id($conn);
            q($conn, "UPDATE donasi SET status = 'diterima', transaksi_id = ?, diproses_oleh = ?, diproses_at = NOW() WHERE id = ?", 'iii', [$trxId, $userId, $id]);
        });
        if ($galat) {
            flash('danger', $galat);
        } elseif ($pesan) {
            flash('danger', $pesan);
        } else {
            catat_audit($conn, 'donasi_terima', 'donasi', $id, ['status' => $d['status']],
                ['status' => 'diterima', 'kode' => $d['kode'], 'jumlah' => $d['jumlah'], 'transaksi_id' => $trxId]);
            flash('success', 'Donasi ' . $d['kode'] . ' dikonfirmasi: status Sudah tersalurkan dan tercatat sebagai pemasukan kas.');
        }
    } elseif ($aksi === 'tolak') {
        $alasan = trim((string)($_POST['alasan'] ?? ''));
        if ($alasan === '' || mb_strlen($alasan) > 255) {
            flash('danger', 'Alasan penolakan wajib diisi (maksimal 255 karakter).');
        } else {
            $d = q_one($conn, "SELECT kode, status FROM donasi WHERE id = ? AND status IN ('menunggu', 'dibayar')", 'i', [$id]);
            $stmt = $d ? q($conn, "UPDATE donasi SET status = 'ditolak', alasan_tolak = ?, diproses_oleh = ?, diproses_at = NOW() WHERE id = ? AND status IN ('menunggu', 'dibayar')",
                'sii', [$alasan, $userId, $id]) : null;
            if (!$stmt || mysqli_stmt_affected_rows($stmt) < 1) {
                flash('danger', 'Donasi tidak ditemukan atau sudah diproses petugas lain.');
            } else {
                catat_audit($conn, 'donasi_tolak', 'donasi', $id, ['status' => $d['status']],
                    ['status' => 'ditolak', 'kode' => $d['kode'], 'alasan' => $alasan]);
                flash('success', 'Donasi ' . $d['kode'] . ' ditandai ditolak.');
            }
        }
    } else {
        flash('danger', 'Permintaan tidak valid.');
    }
    redirect('donasi_masuk.php');
}

// ===== Data halaman =====
// Tab: kode => [judul, daftar status]
$tab = [
    'perlu'    => ['Perlu dicek', ['dibayar']],
    'menunggu' => ['Menunggu pembayaran', ['menunggu']],
    'diterima' => ['Sudah tersalurkan', ['diterima']],
    'batal'    => ['Ditolak atau dibatalkan', ['ditolak', 'dibatalkan']],
    'semua'    => ['Semua', []],
];
$status = $_GET['status'] ?? 'perlu';
if (!is_string($status) || !isset($tab[$status])) $status = 'perlu';
$cari = trim((string)($_GET['cari'] ?? ''));

$hitung = [];
foreach (['menunggu', 'dibayar', 'diterima', 'ditolak', 'dibatalkan'] as $s) $hitung[$s] = ['n' => 0, 'total' => 0];
foreach (q_all($conn, "SELECT status, COUNT(*) AS n, COALESCE(SUM(jumlah), 0) AS total FROM donasi GROUP BY status") as $r) {
    $hitung[$r['status']] = ['n' => (int)$r['n'], 'total' => (float)$r['total']];
}
$batal = ['n' => $hitung['ditolak']['n'] + $hitung['dibatalkan']['n'], 'total' => $hitung['ditolak']['total'] + $hitung['dibatalkan']['total']];

$sql = ''; $types = ''; $params = [];
$dipilih = $tab[$status][1];
if ($dipilih) {
    $sql .= " AND status IN (" . implode(',', array_fill(0, count($dipilih), '?')) . ")";
    $types .= str_repeat('s', count($dipilih));
    $params = array_merge($params, $dipilih);
}
if ($cari !== '') {
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    $sql .= " AND (nama LIKE ? OR kode LIKE ? OR kontak LIKE ?)";
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}

$perHal   = 8;
$baris    = (int)q_one($conn, "SELECT COUNT(*) AS c FROM donasi WHERE 1=1 " . $sql, $types, $params)['c'];
$totalHal = max(1, (int)ceil($baris / $perHal));
$hal      = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$daftar   = q_all($conn, "SELECT * FROM donasi WHERE 1=1 " . $sql . " ORDER BY COALESCE(dibayar_at, dibuat_at) DESC, id DESC LIMIT ? OFFSET ?",
    $types . 'ii', array_merge($params, [$perHal, ($hal - 1) * $perHal]));

$qs = array_filter(['status' => $status, 'cari' => $cari], fn($v) => $v !== '');

$title = 'Donasi masuk';
$aktif = 'donasi';
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Donasi masuk</h1><p>Donasi online dari halaman Salurkan. Donatur yang sudah membayar muncul di tab Perlu dicek. Cocokkan dengan mutasi rekening atau e-wallet sebelum menekan Konfirmasi, karena donasi yang dikonfirmasi langsung tercatat sebagai pemasukan kas<?= can_input() ? '.' : '. Peran Anda hanya dapat melihat data.' ?></p></div>

 <div class="grid4">
  <div class="c g stat"><small>Perlu dicek</small><b><?= $hitung['dibayar']['n'] ?> donasi</b><small><?= rupiah($hitung['dibayar']['total']) ?> sudah dibayar donatur</small></div>
  <div class="c l stat"><small>Menunggu pembayaran</small><b><?= $hitung['menunggu']['n'] ?> donasi</b><small><?= rupiah($hitung['menunggu']['total']) ?> belum dibayar</small></div>
  <div class="c l stat"><small>Sudah tersalurkan</small><b><?= $hitung['diterima']['n'] ?> donasi</b><small><?= rupiah($hitung['diterima']['total']) ?> tercatat di kas</small></div>
  <div class="c d stat"><small>Ditolak atau dibatalkan</small><b><?= $batal['n'] ?> donasi</b><small><?= rupiah($batal['total']) ?> tidak diproses</small></div>
 </div>

 <div class="bar">
  <div class="pills">
   <?php foreach ($tab as $k => $t): ?>
   <a class="pill <?= $status === $k ? 'on' : '' ?>" href="?<?= e(http_build_query(array_merge($qs, ['status' => $k, 'hal' => 1]))) ?>"><?= e($t[0]) ?></a>
   <?php endforeach; ?>
  </div>
  <form class="acts" method="get">
   <input type="hidden" name="status" value="<?= e($status) ?>">
   <input class="cari" type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama, kode, atau kontak" aria-label="Cari donasi">
   <button class="btn o sm" type="submit">Cari</button>
  </form>
 </div>

 <div class="card">
  <h2 class="sub">Daftar donasi <small><?= $baris ?> data</small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>Masuk</th><th>Donatur</th><th>Program dan paket</th><th>Metode</th><th class="r">Nominal</th><th>Status</th><?php if (can_input()): ?><th class="r">Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php if (!$daftar): ?>
     <tr><td colspan="<?= can_input() ? 7 : 6 ?>"><div class="empty"><b>Tidak ada donasi</b><p>Belum ada donasi yang cocok dengan filter ini.</p></div></td></tr>
    <?php endif; ?>
    <?php foreach ($daftar as $r): [$lbl, $kls] = label_status_donasi($r['status']); $namaMetode = $metode[$r['metode']][0] ?? $r['metode']; ?>
     <tr>
      <td class="nw"><?= e(date('d/m/Y', strtotime($r['dibuat_at']))) ?><small><?= e(date('H:i', strtotime($r['dibuat_at']))) ?></small></td>
      <td><b><?= e($r['nama']) ?></b><?= $r['samarkan_nama'] ? ' <i class="cat">disamarkan</i>' : '' ?>
       <small><?= e($r['kontak']) ?><?= $r['email'] ? ', ' . e($r['email']) : '' ?><?= $r['nip'] ? ', NIP ' . e($r['nip']) : '' ?></small>
       <?php if ($r['catatan']): ?><small title="<?= e($r['catatan']) ?>">Catatan: <?= e(mb_strimwidth($r['catatan'], 0, 60, '...')) ?></small><?php endif; ?></td>
      <td><b><?= e($r['program_nama']) ?></b><small><?= e($r['paket']) ?>, <?= e($r['kode']) ?></small></td>
      <td class="nw"><?= e($namaMetode) ?><?php if ($r['channel_nama'] && $r['channel_nama'] !== $namaMetode): ?><small><?= e($r['channel_nama']) ?></small><?php endif; ?></td>
      <td class="r nw"><b><?= rupiah($r['jumlah']) ?></b></td>
      <td><span class="badge <?= e($kls) ?>"><?= e($lbl) ?></span>
       <?php if ($r['dibayar_at'] && $r['status'] === 'dibayar'): ?><small>Dibayar <?= e(date('d/m/Y H:i', strtotime($r['dibayar_at']))) ?></small><?php endif; ?>
       <?php if ($r['bukti']): ?><small><a class="ul" href="bukti.php?kode=<?= urlencode($r['kode']) ?>" target="_blank" rel="noopener">Lihat bukti</a></small><?php endif; ?>
       <?php if (in_array($r['status'], ['ditolak'], true) && $r['alasan_tolak']): ?><small><?= e($r['alasan_tolak']) ?></small><?php endif; ?>
       <?php if ($r['status'] === 'diterima' && $r['diproses_at']): ?><small>Masuk kas <?= e(date('d/m/Y', strtotime($r['diproses_at']))) ?></small><?php endif; ?></td>
      <?php if (can_input()): ?>
      <td class="r nw">
       <?php if (in_array($r['status'], ['menunggu', 'dibayar'], true)): $ringkasan = $r['kode'] . ', ' . $r['nama'] . ', ' . rupiah($r['jumlah']); ?>
       <button class="lnk" type="button" data-dialog="dlg-terima" data-proses="t" data-id="<?= (int)$r['id'] ?>" data-ringkas="<?= e($ringkasan) ?>">Konfirmasi</button>
       <button class="lnk del" type="button" data-dialog="dlg-tolak" data-proses="k" data-id="<?= (int)$r['id'] ?>" data-ringkas="<?= e($ringkasan) ?>">Tolak</button>
       <?php else: ?><small>Selesai</small><?php endif; ?>
      </td>
      <?php endif; ?>
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
</section>

<?php if (can_input()): ?>
<dialog class="dlg" id="dlg-terima" aria-labelledby="t-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="t-judul"><span>Konfirmasi donasi diterima?</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="terima">
  <input type="hidden" name="id" id="t-id">
  <p>Donasi <b id="t-ringkas"></b> akan berstatus Sudah tersalurkan dan dicatat sebagai pemasukan kas, sehingga saldo bertambah. Lanjutkan hanya bila uangnya sudah benar-benar terlihat di mutasi rekening, e-wallet, atau kas tunai.</p>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Ya, uang sudah diterima</button></div>
 </form>
</dialog>

<dialog class="dlg" id="dlg-tolak" aria-labelledby="k-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="k-judul"><span>Tolak donasi?</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="tolak">
  <input type="hidden" name="id" id="k-id">
  <p>Donasi <b id="k-ringkas"></b> akan ditandai ditolak dan tidak masuk ke kas. Alasannya terlihat oleh donatur.</p>
  <label for="k-alasan">Alasan penolakan</label>
  <textarea id="k-alasan" name="alasan" rows="2" maxlength="255" placeholder="Contoh: pembayaran tidak ditemukan di mutasi rekening" required></textarea>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Tolak donasi</button></div>
 </form>
</dialog>
<?php endif; ?>

<?php include __DIR__ . '/php/app_footer.php'; ?>
