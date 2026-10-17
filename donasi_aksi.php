<?php
// Aksi donatur pada donasinya sendiri: ganti metode, laporkan pembayaran (dengan bukti), atau batalkan.
// Perubahan status dijaga di sisi server, jadi tombol yang disembunyikan tidak bisa dilewati.
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';
wajib_login();
siapkan_donasi($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect('donasi_saya.php');

$kode   = $_POST['kode'] ?? '';
$kode   = is_string($kode) ? $kode : '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Hanya pemilik donasi yang boleh mengubahnya
$d = $kode !== '' ? q_one($conn, "SELECT * FROM donasi WHERE kode = ? AND user_id = ?", 'si', [$kode, $userId]) : null;
if (!$d) {
    flash('danger', 'Donasi tidak ditemukan.');
    redirect('donasi_saya.php');
}
$kembali = 'donasi_sukses.php?kode=' . urlencode($d['kode']);

if (!csrf_valid()) {
    flash('danger', 'Sesi formulir kedaluwarsa atau berkas terlalu besar. Silakan ulangi.');
    redirect($kembali);
}

$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'ganti_metode') {
    $m = $_POST['metode'] ?? '';
    $m = is_string($m) ? $m : '';
    $c = $_POST['channel_' . $m] ?? '';
    $c = is_string($c) ? $c : '';
    if ($d['status'] !== 'menunggu') {
        flash('danger', 'Metode tidak bisa diganti karena pembayaran sudah dilaporkan atau donasi sudah diproses.');
    } else {
        [$galat, $kanal, $kanalNama, $kanalInfo] = pilih_pembayaran($conn, $metode, $m, $c);
        if ($galat) {
            flash('danger', $galat);
        } else {
            q($conn, "UPDATE donasi SET metode = ?, channel = ?, channel_nama = ?, channel_info = ? WHERE id = ? AND status = 'menunggu'",
                'ssssi', [$m, $kanal, $kanalNama, $kanalInfo, $d['id']]);
            catat_audit($conn, 'donasi_ganti_metode', 'donasi', (int)$d['id'],
                ['metode' => $d['metode'], 'channel' => $d['channel']], ['metode' => $m, 'channel' => $kanal]);
            flash('success', 'Metode pembayaran diganti ke ' . $kanalNama . '. Silakan selesaikan pembayaran sesuai petunjuk di bawah.');
        }
    }
} elseif ($aksi === 'lapor_bayar') {
    if (!in_array($d['status'], ['menunggu', 'dibayar'], true)) {
        flash('danger', 'Donasi ini sudah diproses, pembayaran tidak bisa dilaporkan lagi.');
    } else {
        [$namaBukti, $galatBukti] = simpan_bukti($_FILES['bukti'] ?? []);
        if ($galatBukti) {
            flash('danger', $galatBukti);
        } else {
            q($conn, "UPDATE donasi SET status = 'dibayar', dibayar_at = COALESCE(dibayar_at, NOW()), bukti = COALESCE(?, bukti)
                      WHERE id = ? AND status IN ('menunggu', 'dibayar')", 'si', [$namaBukti, $d['id']]);
            if ($namaBukti && $d['bukti']) hapus_bukti($d['bukti']);   // bukti lama diganti
            if ($d['status'] === 'menunggu') {
                catat_audit($conn, 'donasi_lapor_bayar', 'donasi', (int)$d['id'], ['status' => 'menunggu'], ['status' => 'dibayar', 'bukti' => $namaBukti ? 'ada' : 'tidak ada']);
            }
            flash('success', $d['status'] === 'menunggu'
                ? 'Terima kasih. Pembayaran Anda sudah dilaporkan dan akan dicek pengurus. Anda bisa mencetak tanda terima sementara.'
                : 'Bukti pembayaran diperbarui.');
        }
    }
} elseif ($aksi === 'batal') {
    if ($d['status'] !== 'menunggu') {
        flash('danger', 'Donasi yang sudah dibayar tidak bisa dibatalkan sendiri. Hubungi pengurus UPZ.');
    } else {
        q($conn, "UPDATE donasi SET status = 'dibatalkan', dibatalkan_at = NOW() WHERE id = ? AND status = 'menunggu'", 'i', [$d['id']]);
        catat_audit($conn, 'donasi_batal', 'donasi', (int)$d['id'], ['status' => 'menunggu'], ['status' => 'dibatalkan']);
        flash('success', 'Donasi dibatalkan. Anda bisa membuat donasi baru kapan saja.');
    }
} else {
    flash('danger', 'Permintaan tidak valid.');
}

redirect($kembali);
