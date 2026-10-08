<?php
require 'fungsi.php';
wajib_login();

// Ambil filter sesuai pencarian aktif
$f = filter_transaksi(true);

// Ambil semua data transaksi sesuai filter (tanpa limit/pagination)
$daftar = q_all($conn,
    "SELECT * FROM " . TRANSAKSI . " WHERE 1=1 " . $f['sql'] . " ORDER BY tanggal ASC, id ASC",
    $f['types'], $f['params']);

// Atur header download file Excel
$namaFile = "Laporan_Keuangan_UPZ_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$namaFile\"");
header("Cache-Control: max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$totalMasuk  = 0;
$totalKeluar = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 11pt; color: #222; }
    .header-box { text-align: center; margin-bottom: 20px; }
    .title-main { font-size: 15pt; font-weight: bold; color: #0B4A8F; }
    .title-sub { font-size: 12pt; font-weight: bold; color: #1E9B52; }
    .title-meta { font-size: 9.5pt; color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th {
        background-color: #0B4A8F;
        color: #FFFFFF;
        font-weight: bold;
        text-align: center;
        border: 1px solid #999;
        padding: 8px 6px;
    }
    td {
        border: 1px solid #CCCCCC;
        padding: 6px 8px;
        vertical-align: middle;
    }
    .text-center { text-align: center; }
    .text-end { text-align: right; }
    .text-success { color: #1E9B52; font-weight: bold; }
    .text-danger { color: #D64545; font-weight: bold; }
    .bg-summary { background-color: #F4F7FA; font-weight: bold; }
    .bg-total { background-color: #E8F0FA; font-weight: bold; font-size: 11pt; }
    .footer-sign { margin-top: 35px; width: 100%; }
</style>
</head>
<body>

<div class="header-box">
    <div class="title-main">UNIT PENGUMPUL ZAKAT (UPZ) BAZNAS</div>
    <div class="title-sub">PT PETROKIMIA GRESIK</div>
    <div class="title-meta">LAPORAN ARUS KAS PEMASUKAN & PENGELUARAN DANA</div>
    <div class="title-meta">
        Periode: 
        <?php
        if ($f['mulai'] !== '' && $f['selesai'] !== '') {
            echo e(date('d/m/Y', strtotime($f['mulai']))) . ' s.d. ' . e(date('d/m/Y', strtotime($f['selesai'])));
        } elseif ($f['mulai'] !== '') {
            echo 'Mulai ' . e(date('d/m/Y', strtotime($f['mulai'])));
        } elseif ($f['selesai'] !== '') {
            echo 'Sampai ' . e(date('d/m/Y', strtotime($f['selesai'])));
        } else {
            echo 'Semua Transaksi';
        }
        ?>
        | Dicetak pada: <?= date('d/m/Y H:i') ?> WIB oleh <?= e($_SESSION['nama'] ?? 'Administrator') ?>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 40px;">No</th>
            <th style="width: 100px;">Tanggal</th>
            <th style="width: 110px;">Jenis</th>
            <th>Nama Donatur / Penerima Manfaat</th>
            <th style="width: 140px;">Pemasukan (Rp)</th>
            <th style="width: 140px;">Pengeluaran (Rp)</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$daftar): ?>
        <tr>
            <td colspan="6" class="text-center" style="padding: 20px; color: #777;">
                Tidak ada data transaksi yang sesuai dengan kriteria filter.
            </td>
        </tr>
    <?php else: ?>
        <?php
        $no = 1;
        foreach ($daftar as $d):
            $isMasuk = ($d['tipe'] === 'masuk');
            $jml = (float)$d['jumlah'];
            if ($isMasuk) {
                $totalMasuk += $jml;
            } else {
                $totalKeluar += $jml;
            }
        ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td class="text-center"><?= date('d/m/Y', strtotime($d['tanggal'])) ?></td>
            <td class="text-center" style="font-weight: bold; color: <?= $isMasuk ? '#1E9B52' : '#B7791F' ?>;">
                <?= $isMasuk ? 'Pemasukan' : 'Pengeluaran' ?>
            </td>
            <td><?= e($d['nama']) ?></td>
            <td class="text-end text-success">
                <?= $isMasuk ? number_format($jml, 0, ',', '.') : '-' ?>
            </td>
            <td class="text-end text-danger">
                <?= !$isMasuk ? number_format($jml, 0, ',', '.') : '-' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="bg-summary">
            <td colspan="4" class="text-end">TOTAL PERIODE INI:</td>
            <td class="text-end text-success">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></td>
            <td class="text-end text-danger">Rp <?= number_format($totalKeluar, 0, ',', '.') ?></td>
        </tr>
        <tr class="bg-total">
            <td colspan="4" class="text-end">SURPLUS / (DEFISIT) PERIODE INI:</td>
            <td colspan="2" class="text-end" style="color: <?= ($totalMasuk - $totalKeluar >= 0) ? '#1E9B52' : '#D64545' ?>;">
                Rp <?= number_format($totalMasuk - $totalKeluar, 0, ',', '.') ?>
            </td>
        </tr>
        <tr class="bg-total">
            <td colspan="4" class="text-end">SALDO KAS SAAT INI (KESELURUHAN):</td>
            <td colspan="2" class="text-end" style="color: #0B4A8F;">
                Rp <?= number_format(hitung_saldo($conn), 0, ',', '.') ?>
            </td>
        </tr>
    </tfoot>
</table>

<table class="footer-sign" style="border: none; margin-top: 40px;">
    <tr style="border: none;">
        <td style="border: none; width: 60%;"></td>
        <td style="border: none; width: 40%; text-align: center;">
            Gresik, <?= date('d F Y') ?><br>
            Petugas Pengelola Kas UPZ BAZNAS,<br><br><br><br>
            <strong><?= e($_SESSION['nama'] ?? 'Administrator') ?></strong><br>
            <span style="font-size: 9pt; color: #555;">NIP / ID: @<?= e($_SESSION['username'] ?? 'admin') ?></span>
        </td>
    </tr>
</table>

</body>
</html>
