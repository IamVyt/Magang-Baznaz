<?php
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/crud.php';

$asnaf = ['fakir' => 'Fakir', 'miskin' => 'Miskin', 'amil' => 'Amil', 'muallaf' => 'Muallaf', 'riqab' => 'Riqab',
          'gharim' => 'Gharim', 'sabilillah' => 'Sabilillah', 'ibnu_sabil' => 'Ibnu sabil'];

halaman_crud([
    'judul'  => 'Data mustahik',
    'sub'    => 'Master data penerima manfaat berdasarkan delapan golongan asnaf. Dipakai saat mencatat pengeluaran.',
    'aktif'  => 'mustahik',
    'tabel'  => 'mustahik',
    'soft'   => true,
    'peran'  => ['admin', 'bendahara', 'ketua'],
    'ubah'   => ['admin', 'bendahara'],
    'fields' => [
        ['n' => 'nama',   'l' => 'Nama lengkap', 't' => 'text', 'w' => true],
        ['n' => 'nik',    'l' => 'NIK', 't' => 'text'],
        ['n' => 'asnaf',  'l' => 'Golongan asnaf', 't' => 'select', 'w' => true, 'o' => $asnaf],
        ['n' => 'kontak', 'l' => 'Nomor kontak', 't' => 'text'],
        ['n' => 'alamat', 'l' => 'Alamat', 't' => 'textarea'],
    ],
    'cari'   => ['nama', 'nik', 'kontak'],
    'urut'   => 't.nama ASC',
    'extra'  => ", (SELECT COUNT(*) FROM transaksi x WHERE x.tipe = 'keluar' AND x.pihak_id = t.id AND x.dibatalkan_at IS NULL) AS riwayat,
                  (SELECT COALESCE(SUM(x.jumlah), 0) FROM transaksi x WHERE x.tipe = 'keluar' AND x.pihak_id = t.id AND x.dibatalkan_at IS NULL) AS total",
    'kolom'  => [['nama', 'Nama mustahik'], ['asnaf', 'Asnaf', 'chip', $asnaf], ['kontak', 'Kontak'], ['alamat', 'Alamat'], ['riwayat', 'Riwayat'], ['total', 'Total diterima', 'rupiah']],
]);
