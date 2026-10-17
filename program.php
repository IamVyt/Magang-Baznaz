<?php
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/crud.php';

$jenis = ['masuk' => 'Pemasukan', 'keluar' => 'Penyaluran (pengeluaran)'];

halaman_crud([
    'judul'  => 'Program dan kategori',
    'sub'    => 'Kategori pemasukan (zakat, infak, sedekah) dan program penyaluran yang muncul di form transaksi.',
    'aktif'  => 'program',
    'tabel'  => 'kategori_dana',
    'soft'   => true,
    'peran'  => ['admin', 'bendahara', 'ketua'],
    'ubah'   => ['admin'],
    'unik'   => 'kode',
    'fields' => [
        ['n' => 'nama',       'l' => 'Nama kategori atau program', 't' => 'text', 'w' => true],
        ['n' => 'kode',       'l' => 'Kode singkat (contoh: ZAKAT_MAAL)', 't' => 'text', 'w' => true],
        ['n' => 'tipe',       'l' => 'Jenis', 't' => 'select', 'w' => true, 'o' => $jenis],
        ['n' => 'keterangan', 'l' => 'Keterangan', 't' => 'textarea'],
    ],
    'cari'   => ['nama', 'kode', 'keterangan'],
    'urut'   => 't.tipe ASC, t.nama ASC',
    'extra'  => ", (SELECT COUNT(*) FROM transaksi x WHERE x.kategori_id = t.id AND x.dibatalkan_at IS NULL) AS dipakai",
    'kolom'  => [['nama', 'Nama'], ['kode', 'Kode'], ['tipe', 'Jenis', 'chip', $jenis], ['keterangan', 'Keterangan'], ['dipakai', 'Dipakai']],
]);
