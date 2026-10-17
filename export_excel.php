<?php
require __DIR__ . '/php/fungsi.php';
wajib_staff();

// Ekspor mengikuti filter yang sedang aktif di dashboard, tanpa batas halaman
$f = filter_transaksi(true);
$daftar = q_all($conn, "SELECT * FROM " . TRANSAKSI . " WHERE dibatalkan_at IS NULL " . $f['sql'] . " ORDER BY tanggal ASC, id ASC", $f['types'], $f['params']);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Laporan_Keuangan_UPZ_' . date('Ymd_His') . '.xls"');
header('Cache-Control: max-age=0');

$masuk = 0; $keluar = 0;
if ($f['mulai'] !== '' && $f['selesai'] !== '') $periode = date('d/m/Y', strtotime($f['mulai'])) . ' s.d. ' . date('d/m/Y', strtotime($f['selesai']));
elseif ($f['mulai'] !== '') $periode = 'Mulai ' . date('d/m/Y', strtotime($f['mulai']));
elseif ($f['selesai'] !== '') $periode = 'Sampai ' . date('d/m/Y', strtotime($f['selesai']));
else $periode = 'Semua transaksi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:11pt;color:#222}
.c{text-align:center}.r{text-align:right}
table{border-collapse:collapse;width:100%}
th{background:#191A23;color:#B9FF66;border:1px solid #999;padding:8px 6px}
td{border:1px solid #ccc;padding:6px 8px}
.sum{background:#F3F3F3;font-weight:bold}
</style>
</head>
<body>
<div class="c"><b style="font-size:15pt">UNIT PENGUMPUL ZAKAT (UPZ) BAZNAS</b><br><b>PT PETROKIMIA GRESIK</b><br>LAPORAN ARUS KAS PEMASUKAN DAN PENGELUARAN DANA<br>
Periode: <?= e($periode) ?> | Dicetak: <?= date('d/m/Y H:i') ?> WIB oleh <?= e($_SESSION['nama'] ?? 'Petugas') ?></div>

<table>
 <thead><tr><th>No</th><th>Tanggal</th><th>Jenis</th><th>Nama donatur atau penerima manfaat</th><th>Kategori</th><th>Pemasukan (Rp)</th><th>Pengeluaran (Rp)</th></tr></thead>
 <tbody>
 <?php if (!$daftar): ?><tr><td colspan="7" class="c">Tidak ada data yang sesuai dengan filter.</td></tr><?php endif; ?>
 <?php foreach ($daftar as $i => $d): $in = $d['tipe'] === 'masuk'; if ($in) { $masuk += $d['jumlah']; } else { $keluar += $d['jumlah']; } ?>
 <tr>
  <td class="c"><?= $i + 1 ?></td>
  <td class="c"><?= date('d/m/Y', strtotime($d['tanggal'])) ?></td>
  <td class="c"><?= $in ? 'Pemasukan' : 'Pengeluaran' ?></td>
  <td><?= e($d['nama']) ?></td>
  <td><?= e($d['kategori_nama'] ?? '-') ?></td>
  <td class="r"><?= $in ? number_format($d['jumlah'], 0, ',', '.') : '-' ?></td>
  <td class="r"><?= $in ? '-' : number_format($d['jumlah'], 0, ',', '.') ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 <tfoot>
  <tr class="sum"><td colspan="5" class="r">TOTAL PERIODE INI</td><td class="r">Rp <?= number_format($masuk, 0, ',', '.') ?></td><td class="r">Rp <?= number_format($keluar, 0, ',', '.') ?></td></tr>
  <tr class="sum"><td colspan="5" class="r">SURPLUS ATAU DEFISIT PERIODE INI</td><td colspan="2" class="r">Rp <?= number_format($masuk - $keluar, 0, ',', '.') ?></td></tr>
  <tr class="sum"><td colspan="5" class="r">SALDO KAS SAAT INI (KESELURUHAN)</td><td colspan="2" class="r">Rp <?= number_format(hitung_saldo($conn), 0, ',', '.') ?></td></tr>
 </tfoot>
</table>

<p class="r" style="margin-top:35px">Gresik, <?= date('d-m-Y') ?><br>Petugas Pengelola Kas UPZ BAZNAS,<br><br><br><br><b><?= e($_SESSION['nama'] ?? 'Petugas') ?></b><br>ID: @<?= e($_SESSION['username'] ?? '') ?></p>
</body>
</html>
