<?php
// Langkah 1 penyaluran donasi: pilih program, lalu pilih paket penyaluran
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';

// Hanya program yang punya paket di $penyaluran (php/data.php) yang bisa dipilih
$tersedia = [];
foreach ($program as $p) {
    if (isset($penyaluran[slug($p[0])])) $tersedia[slug($p[0])] = $p;
}

$slug = $_GET['program'] ?? '';
$slug = is_string($slug) ? $slug : '';
$prog = $tersedia[$slug] ?? null;   // [nama, warna, penjelasan, poin]

$title  = 'Salurkan donasi';
$publik = true;   // tampil dengan navigasi atas, juga untuk petugas
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Salurkan</h1><p>Tunaikan zakat, infak, dan sedekah Anda melalui UPZ BAZNAS Petrokimia Gresik. Pilih program dan paket, isi data, lalu lakukan pembayaran.</p></div>

 <ol class="steps" aria-label="Langkah penyaluran">
  <li class="on"><b>1</b>Pilih paket</li>
  <li><b>2</b>Isi data</li>
  <li><b>3</b>Bayar</li>
 </ol>

 <div class="pills pilih" aria-label="Pilih program">
  <?php foreach ($tersedia as $s => $p): ?>
  <a class="pill <?= $s === $slug ? 'on' : '' ?>" href="salurkan.php?program=<?= e($s) ?>"><?= e($p[0]) ?></a>
  <?php endforeach; ?>
 </div>

 <?php if ($prog): ?>
 <p class="lead"><?= e($prog[2]) ?></p>
 <div class="grid2">
  <?php foreach ($penyaluran[$slug]['paket'] as $i => $pk): $warna = ['l', 'g', 'd'][$i % 3]; ?>
  <a class="c <?= $warna ?> paket" href="bayar.php?program=<?= e($slug) ?>&amp;paket=<?= (int)$i ?>">
   <h3><span><?= e($pk[0]) ?></span></h3>
   <b class="harga"><?= $pk[1] > 0 ? rupiah($pk[1]) : 'Nominal bebas' ?></b>
   <p><?= e($pk[2]) ?></p>
   <span class="more"><i>↗</i>Pilih paket ini</span>
  </a>
  <?php endforeach; ?>
 </div>
 <?php else: ?>
 <p class="lead">Pilih program yang ingin Anda dukung.</p>
 <div class="grid2">
  <?php foreach ($tersedia as $s => $p): ?>
  <a class="c <?= e($p[1]) ?> paket" href="salurkan.php?program=<?= e($s) ?>">
   <h3><span><?= e($p[0]) ?></span></h3>
   <p><?= e($p[2]) ?></p>
   <span class="more"><i>↗</i>Lihat paket</span>
  </a>
  <?php endforeach; ?>
 </div>
 <?php endif; ?>

 <p class="catatan">
  <?php if ($masuk): ?>
  Sudah pernah berdonasi? <a class="ul" href="donasi_saya.php">Lihat donasi saya</a>.
  <?php else: ?>
  Untuk menyalurkan donasi, Anda akan diminta masuk atau mendaftar terlebih dahulu. Pilihan paket Anda tetap tersimpan.
  <?php endif; ?>
 </p>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
