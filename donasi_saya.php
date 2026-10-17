<?php
// Riwayat donasi milik pengguna yang sedang masuk
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';
wajib_login();
siapkan_donasi($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);

$ringkas = q_one($conn, "SELECT COUNT(*) AS n,
        COALESCE(SUM(CASE WHEN status = 'diterima' THEN jumlah END), 0) AS tersalurkan,
        COALESCE(SUM(CASE WHEN status IN ('menunggu', 'dibayar') THEN jumlah END), 0) AS berjalan
    FROM donasi WHERE user_id = ?", 'i', [$userId]);

$perHal   = 8;
$baris    = (int)$ringkas['n'];
$totalHal = max(1, (int)ceil($baris / $perHal));
$hal      = min(max(1, (int)($_GET['hal'] ?? 1)), $totalHal);
$riwayat  = q_all($conn, "SELECT * FROM donasi WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?",
    'iii', [$userId, $perHal, ($hal - 1) * $perHal]);

$title  = 'Donasi saya';
$publik = true;
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Donasi saya</h1><p>Pantau status donasi Anda. Donasi berstatus Sudah tersalurkan setelah pengurus memeriksa pembayaran, dan buktinya bisa dicetak.</p></div>

 <div class="grid3">
  <div class="c g stat"><small>Sudah tersalurkan</small><b><?= rupiah($ringkas['tersalurkan']) ?></b><small>Tercatat di kas UPZ</small></div>
  <div class="c l stat"><small>Sedang berjalan</small><b><?= rupiah($ringkas['berjalan']) ?></b><small>Menunggu pembayaran atau pengecekan</small></div>
  <div class="c d stat"><small>Total donasi dikirim</small><b><?= $baris ?></b><small>Semua status</small></div>
 </div>

 <div class="bar">
  <span></span>
  <div class="acts"><a class="btn g sm" href="salurkan.php">+ Salurkan donasi</a></div>
 </div>

 <div class="card">
  <h2 class="sub">Riwayat donasi <small><?= $baris ?> data</small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Program dan paket</th><th>Metode</th><th class="r">Nominal</th><th>Status</th><th class="r">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$riwayat): ?>
     <tr><td colspan="6"><div class="empty"><b>Belum ada donasi</b><p>Donasi yang Anda kirim akan muncul di sini beserta statusnya.</p>
      <a class="btn g sm" href="salurkan.php">Salurkan sekarang</a></div></td></tr>
    <?php endif; ?>
    <?php foreach ($riwayat as $r): [$lbl, $kls] = label_status_donasi($r['status']); $namaMetode = $metode[$r['metode']][0] ?? $r['metode']; ?>
     <tr>
      <td class="nw"><?= e(date('d/m/Y', strtotime($r['dibuat_at']))) ?><small><?= e(date('H:i', strtotime($r['dibuat_at']))) ?></small></td>
      <td><b><?= e($r['program_nama']) ?></b>
       <span class="meta"><i class="cat"><?= e($r['paket']) ?></i><small><?= e($r['kode']) ?></small></span></td>
      <td class="nw"><?= e($namaMetode) ?><?php if ($r['channel_nama'] && $r['channel_nama'] !== $namaMetode): ?><small><?= e($r['channel_nama']) ?></small><?php endif; ?></td>
      <td class="r nw"><b><?= rupiah($r['jumlah']) ?></b></td>
      <td><span class="badge <?= e($kls) ?>"><?= e($lbl) ?></span>
       <?php if ($r['status'] === 'ditolak' && $r['alasan_tolak']): ?><small><?= e($r['alasan_tolak']) ?></small><?php endif; ?></td>
      <td class="r nw">
       <?php if ($r['status'] === 'menunggu'): ?>
        <a class="lnk" href="donasi_sukses.php?kode=<?= urlencode($r['kode']) ?>">Bayar</a>
       <?php elseif ($r['status'] === 'diterima'): ?>
        <a class="lnk" href="cetak_donasi.php?kode=<?= urlencode($r['kode']) ?>" target="_blank" rel="noopener">Cetak bukti</a>
       <?php else: ?>
        <a class="lnk" href="donasi_sukses.php?kode=<?= urlencode($r['kode']) ?>">Detail</a>
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
    <?php if ($hal > 1): ?><a href="?hal=<?= $hal - 1 ?>">←</a><?php endif; ?>
    <?php for ($i = max(1, $hal - 2); $i <= min($totalHal, $hal + 2); $i++): ?>
    <a class="<?= $i === $hal ? 'on' : '' ?>" href="?hal=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($hal < $totalHal): ?><a href="?hal=<?= $hal + 1 ?>">→</a><?php endif; ?>
   </div>
  </div>
  <?php endif; ?>
 </div>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
