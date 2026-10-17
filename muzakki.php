<?php
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/crud.php';

halaman_crud([
    'judul'  => 'Data muzakki',
    'sub'    => 'Master data donatur UPZ. Dipakai sebagai pilihan saat mencatat pemasukan.',
    'aktif'  => 'muzakki',
    'tabel'  => 'muzakki',
    'soft'   => true,
    'peran'  => ['admin', 'bendahara', 'ketua'],
    'ubah'   => ['admin', 'bendahara'],
    'fields' => [
        ['n' => 'nama',    'l' => 'Nama lengkap',          't' => 'text', 'w' => true],
        ['n' => 'nik_nip', 'l' => 'NIP atau NIK karyawan', 't' => 'text'],
        ['n' => 'kontak',  'l' => 'Nomor kontak atau WhatsApp', 't' => 'text'],
        ['n' => 'alamat',  'l' => 'Alamat atau unit kerja', 't' => 'textarea'],
    ],
    'cari'   => ['nama', 'nik_nip', 'kontak'],
    'urut'   => 't.nama ASC',
    'extra'  => ", (SELECT COUNT(*) FROM transaksi x WHERE x.tipe = 'masuk' AND x.pihak_id = t.id AND x.dibatalkan_at IS NULL) AS riwayat,
                  (SELECT COALESCE(SUM(x.jumlah), 0) FROM transaksi x WHERE x.tipe = 'masuk' AND x.pihak_id = t.id AND x.dibatalkan_at IS NULL) AS total",
    'kolom'  => [['nama', 'Nama muzakki'], ['nik_nip', 'NIP atau NIK'], ['kontak', 'Kontak'], ['alamat', 'Alamat'], ['riwayat', 'Riwayat'], ['total', 'Total donasi', 'rupiah']],
]);
