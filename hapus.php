<?php
require 'fungsi.php';
wajib_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('index.php');
    }

    $tipe = $_POST['tipe'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if (!in_array($tipe, ['masuk', 'keluar'], true) || $id <= 0) {
        flash('danger', 'Data transaksi yang akan dihapus tidak valid.');
        redirect('index.php');
    }

    $tabel = ($tipe === 'masuk') ? 'penyumbang' : 'penerima';

    // Cek apakah data tersedia
    $ada = q_one($conn, "SELECT id FROM $tabel WHERE id = ?", 'i', [$id]);
    if (!$ada) {
        flash('danger', 'Data transaksi tidak ditemukan.');
        redirect('index.php');
    }

    $galat = jalankan_transaksi($conn, function () use ($conn, $tabel, $id) {
        q($conn, "DELETE FROM $tabel WHERE id = ?", 'i', [$id]);
    });

    if ($galat) {
        flash('danger', $galat);
    } else {
        flash('success', 'Data transaksi berhasil dihapus dan saldo kas diperbarui.');
    }

    redirect('index.php');
}

// Jika diakses langsung via GET
redirect('index.php');