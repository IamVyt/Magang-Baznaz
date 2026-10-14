<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin', 'bendahara']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('index.php');
}
if (!csrf_valid()) {
    flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
    redirect('index.php');
}

$id     = (int)($_POST['id'] ?? 0);
$tipe   = $_POST['tipe'] ?? '';  // hanya untuk pesan, tidak menentukan tabel
$alasan = trim($_POST['alasan'] ?? '');

if ($id <= 0) {
    flash('danger', 'Data transaksi yang akan dibatalkan tidak valid.');
    redirect('index.php');
}

// Cek apakah data tersedia dan belum di-void
$ada = q_one($conn, "SELECT * FROM transaksi WHERE id = ?", 'i', [$id]);
if (!$ada) {
    flash('danger', 'Data transaksi tidak ditemukan.');
    redirect('index.php');
}
if ($ada['dibatalkan_at'] !== null) {
    flash('danger', 'Transaksi ini sudah pernah dibatalkan sebelumnya.');
    redirect('index.php');
}
if ($alasan === '') {
    flash('danger', 'Alasan pembatalan wajib diisi.');
    redirect('index.php');
}

// Soft delete: set dibatalkan_at dan alasan_batal di tabel transaksi terpadu
$galat = jalankan_transaksi($conn, function () use ($conn, $id, $alasan) {
    q($conn,
        "UPDATE transaksi SET dibatalkan_at = NOW(), alasan_batal = ? WHERE id = ?",
        'si', [$alasan, $id]);
});

if ($galat) {
    flash('danger', $galat);
} else {
    $data_baru = array_merge($ada, ['dibatalkan_at' => date('Y-m-d H:i:s'), 'alasan_batal' => $alasan]);
    catat_audit($conn, 'void', 'transaksi', $id, $ada, $data_baru);
    flash('success', 'Transaksi berhasil dibatalkan (void). Data tetap tersimpan dalam riwayat.');
}

redirect('index.php');