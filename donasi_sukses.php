<?php
// Langkah 3 penyaluran donasi: status, petunjuk pembayaran, ganti metode, lapor sudah bayar, dan cetak
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';
wajib_login();
siapkan_donasi($conn);

$kode = $_GET['kode'] ?? '';
$d = is_string($kode) && $kode !== '' ? q_one($conn, "SELECT * FROM donasi WHERE kode = ?", 's', [$kode]) : null;

// Donasi hanya boleh dilihat pemiliknya atau petugas
if (!$d || ((int)$d['user_id'] !== (int)($_SESSION['user_id'] ?? 0) && !is_staff())) {
    flash('danger', 'Donasi tidak ditemukan.');
    redirect('donasi_saya.php');
}

$milik = (int)$d['user_id'] === (int)($_SESSION['user_id'] ?? 0);   // petugas hanya melihat, tidak mengubah
$baru  = isset($_GET['baru']);
$st    = $d['status'];
[$labelStatus, $kelasStatus] = label_status_donasi($st);
$m = $metode[$d['metode']] ?? [$d['metode'], '', []];

// Tujuan pembayaran sesuai pilihan donatur: nomor dan atas nama disimpan saat memilih
$bagian = explode(' a.n. ', (string)$d['channel_info'], 2);
$nomor  = trim($bagian[0]);
$atasNama = $bagian[1] ?? '';
$qris = qris_berkas();

$judul = [
    'menunggu'   => $baru ? 'Terima kasih atas kebaikan Anda' : 'Selesaikan pembayaran Anda',
    'dibayar'    => 'Pembayaran Anda sedang dicek',
    'diterima'   => 'Alhamdulillah, donasi Anda sudah tersalurkan',
    'ditolak'    => 'Donasi tidak dapat diproses',
    'dibatalkan' => 'Donasi dibatalkan',
][$st] ?? 'Detail donasi';
$kalimat = [
    'menunggu'   => 'Pesanan donasi Anda sudah dibuat. Bayar sesuai petunjuk, lalu tekan Saya sudah membayar. Anda masih bisa mengganti metode pembayaran sebelum membayar.',
    'dibayar'    => 'Pembayaran sudah Anda laporkan. Pengurus akan memeriksanya, lalu statusnya berubah menjadi Sudah tersalurkan. Sementara itu Anda bisa mencetak tanda terima sementara.',
    'diterima'   => 'Pembayaran sudah diterima dan dicatat di kas UPZ. Cetak bukti penyaluran di bawah ini untuk arsip Anda.',
    'ditolak'    => 'Pengurus tidak dapat memproses donasi ini.' . ($d['alasan_tolak'] ? ' Alasan: ' . $d['alasan_tolak'] : ''),
    'dibatalkan' => 'Donasi ini dibatalkan. Anda bisa membuat donasi baru kapan saja.',
][$st] ?? '';

// Tahapan: 1 pesanan, 2 pembayaran, 3 tersalurkan
$tahap = ['menunggu' => ['done', 'on', ''], 'dibayar' => ['done', 'done', 'on'], 'diterima' => ['done', 'done', 'done']][$st] ?? null;

$pilihMetode  = $d['metode'];
$pilihChannel = (string)$d['channel'];
if ($milik && $st === 'menunggu') {
    $ada     = metode_tersedia($conn);
    $bank    = channel_aktif($conn, 'bank');
    $ewallet = channel_aktif($conn, 'ewallet');
}

$title  = 'Donasi ' . $d['kode'];
$publik = true;
include __DIR__ . '/php/app_header.php';
?>

<section class="auth">
 <div class="c g">
  <h1><?= e($judul) ?></h1>
  <p><?= e($kalimat) ?></p>
  <img class="mas" src="img/maskot.svg" alt="Maskot UPZ BAZNAS: koin ramah yang membawa hati" width="340" height="342" decoding="async">
 </div>

 <div class="c l">
  <h2><span class="tg">Detail donasi</span></h2>

  <?php if ($tahap): ?>
  <ol class="steps" aria-label="Tahapan donasi">
   <li class="<?= $tahap[0] ?>"><b><?= $tahap[0] === 'done' ? '✓' : '1' ?></b>Pesanan</li>
   <li class="<?= $tahap[1] ?>"><b><?= $tahap[1] === 'done' ? '✓' : '2' ?></b>Pembayaran</li>
   <li class="<?= $tahap[2] ?>"><b><?= $tahap[2] === 'done' ? '✓' : '3' ?></b>Tersalurkan</li>
  </ol>
  <?php endif; ?>

  <dl class="rinci">
   <div><dt>Nomor referensi</dt><dd class="kode"><?= e($d['kode']) ?></dd></div>
   <div><dt>Status</dt><dd><span class="badge <?= e($kelasStatus) ?>"><?= e($labelStatus) ?></span></dd></div>
   <div><dt>Program</dt><dd><?= e($d['program_nama']) ?></dd></div>
   <div><dt>Paket</dt><dd><?= e($d['paket']) ?></dd></div>
   <div><dt>Nominal</dt><dd><?= rupiah($d['jumlah']) ?></dd></div>
   <div><dt>Metode</dt><dd><?= e($m[0]) ?><?= ($d['channel_nama'] && $d['channel_nama'] !== $m[0]) ? ', ' . e($d['channel_nama']) : '' ?></dd></div>
   <div><dt>Dikirim</dt><dd><?= e(date('d/m/Y H:i', strtotime($d['dibuat_at']))) ?></dd></div>
   <?php if ($d['dibayar_at']): ?><div><dt>Dilaporkan dibayar</dt><dd><?= e(date('d/m/Y H:i', strtotime($d['dibayar_at']))) ?></dd></div><?php endif; ?>
   <?php if ($st === 'diterima' && $d['diproses_at']): ?><div><dt>Diterima pengurus</dt><dd><?= e(date('d/m/Y H:i', strtotime($d['diproses_at']))) ?></dd></div><?php endif; ?>
  </dl>

  <?php if ($st === 'menunggu'): ?>
  <div class="petunjuk">
   <h3>Cara pembayaran: <?= e($d['channel_nama'] ?: $m[0]) ?></h3>
   <p class="tagihan">Bayar tepat <b><?= rupiah($d['jumlah']) ?></b></p>
   <?php if ($d['metode'] === 'qris'): ?>
    <?php if ($qris): ?>
    <img class="qris" src="<?= e($qris) ?>" alt="Kode QRIS UPZ BAZNAS Petrokimia Gresik" width="224" height="224">
    <?php else: ?>
    <p class="peringatan">Kode QRIS belum tersedia saat ini. Silakan ganti metode pembayaran di bawah atau hubungi pengurus UPZ.</p>
    <?php endif; ?>
   <?php elseif ($nomor !== ''): ?>
    <div class="tujuan">
     <small><?= e($d['channel_nama']) ?></small>
     <b class="nomor"><?= e($nomor) ?></b>
     <?php if ($atasNama !== ''): ?><small>a.n. <?= e($atasNama) ?></small><?php endif; ?>
     <button class="btn o sm" type="button" data-salin="<?= e(preg_replace('/\D/', '', $nomor)) ?>">Salin nomor</button>
    </div>
   <?php endif; ?>
   <ol>
    <?php foreach ($m[2] as $langkah): ?><li><?= e($langkah) ?></li><?php endforeach; ?>
   </ol>
   <p class="bantu">Nomor referensi Anda: <b><?= e($d['kode']) ?></b></p>
  </div>
  <?php endif; ?>

  <?php if ($d['bukti'] && ($milik || is_staff())): ?>
  <div class="petunjuk">
   <h3>Bukti pembayaran</h3>
   <a href="bukti.php?kode=<?= urlencode($d['kode']) ?>" target="_blank" rel="noopener"><img class="bukti" src="bukti.php?kode=<?= urlencode($d['kode']) ?>" alt="Bukti pembayaran yang Anda unggah" width="200" loading="lazy"></a>
  </div>
  <?php endif; ?>

  <?php if ($milik && $st === 'menunggu'): ?>
  <form class="lapor" method="post" action="donasi_aksi.php" enctype="multipart/form-data" data-sekali>
   <?= csrf_field() ?>
   <input type="hidden" name="aksi" value="lapor_bayar">
   <input type="hidden" name="kode" value="<?= e($d['kode']) ?>">
   <label for="bukti">Bukti pembayaran (disarankan, gambar JPG, PNG, atau WebP, maksimal 2 MB)</label>
   <input id="bukti" type="file" name="bukti" accept="image/jpeg,image/png,image/webp">
   <button class="btn" type="submit">Saya sudah membayar</button>
  </form>

  <details class="ganti">
   <summary>Ganti metode pembayaran</summary>
   <form method="post" action="donasi_aksi.php" data-sekali>
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="ganti_metode">
    <input type="hidden" name="kode" value="<?= e($d['kode']) ?>">
    <?php include __DIR__ . '/php/pilih_metode.php'; ?>
    <button class="btn o" type="submit">Simpan metode baru</button>
   </form>
  </details>

  <form class="batal" method="post" action="donasi_aksi.php" data-konfirmasi="Batalkan donasi ini? Donasi yang dibatalkan tidak bisa dipakai lagi.">
   <?= csrf_field() ?>
   <input type="hidden" name="aksi" value="batal">
   <input type="hidden" name="kode" value="<?= e($d['kode']) ?>">
   <button class="lnk del" type="submit">Batalkan donasi ini</button>
  </form>
  <?php elseif ($milik && $st === 'dibayar'): ?>
  <form class="lapor" method="post" action="donasi_aksi.php" enctype="multipart/form-data" data-sekali>
   <?= csrf_field() ?>
   <input type="hidden" name="aksi" value="lapor_bayar">
   <input type="hidden" name="kode" value="<?= e($d['kode']) ?>">
   <label for="bukti">Unggah atau ganti bukti pembayaran (JPG, PNG, atau WebP, maksimal 2 MB)</label>
   <input id="bukti" type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required>
   <button class="btn o" type="submit">Simpan bukti</button>
  </form>
  <?php endif; ?>

  <div class="acts">
   <?php if ($st === 'diterima'): ?>
   <a class="btn g" href="cetak_donasi.php?kode=<?= urlencode($d['kode']) ?>" target="_blank" rel="noopener">Cetak bukti penyaluran</a>
   <?php elseif (in_array($st, ['menunggu', 'dibayar'], true)): ?>
   <a class="btn o" href="cetak_donasi.php?kode=<?= urlencode($d['kode']) ?>" target="_blank" rel="noopener">Cetak tanda terima sementara</a>
   <?php endif; ?>
   <a class="btn o" href="donasi_saya.php">Donasi saya</a>
   <a class="btn o" href="salurkan.php">Salurkan lagi</a>
  </div>
 </div>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
