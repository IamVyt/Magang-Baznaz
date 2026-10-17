<?php
// Menampilkan bukti pembayaran. Berkasnya disimpan di folder tertutup (uploads/bukti),
// jadi hanya pemilik donasi dan petugas yang bisa membukanya lewat skrip ini.
require __DIR__ . '/php/fungsi.php';
wajib_login();
siapkan_donasi($conn);

$kode = $_GET['kode'] ?? '';
$d = is_string($kode) && $kode !== '' ? q_one($conn, "SELECT user_id, bukti FROM donasi WHERE kode = ?", 's', [$kode]) : null;

$boleh = $d && $d['bukti'] && nama_bukti_valid($d['bukti'])
    && ((int)$d['user_id'] === (int)($_SESSION['user_id'] ?? 0) || is_staff());
$berkas = $boleh ? folder_bukti() . '/' . $d['bukti'] : '';

if (!$boleh || !is_file($berkas)) {
    http_response_code(404);
    exit('Bukti tidak ditemukan.');
}

$tipe = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($berkas, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
header('Content-Type: ' . $tipe);
header('Content-Length: ' . filesize($berkas));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($berkas);
