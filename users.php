<?php
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/crud.php';

$peran = ['admin' => 'Admin', 'bendahara' => 'Bendahara', 'ketua' => 'Ketua', 'user' => 'User (donatur)'];

halaman_crud([
    'judul'     => 'Kelola pengguna',
    'sub'       => 'Akun petugas dan pendaftar. Hanya admin yang dapat memberi peran admin, bendahara, atau ketua.',
    'aktif'     => 'users',
    'tabel'     => 'users',
    'peran'     => ['admin'],
    'ubah'      => ['admin'],
    'unik'      => 'username',
    'jaga_diri' => true,
    'fields'    => [
        ['n' => 'nama_lengkap', 'l' => 'Nama lengkap', 't' => 'text', 'w' => true],
        ['n' => 'username',     'l' => 'Username', 't' => 'text', 'w' => true],
        ['n' => 'password',     'l' => 'Password (minimal 8 karakter)', 't' => 'password'],
        ['n' => 'role',         'l' => 'Peran', 't' => 'select', 'w' => true, 'o' => $peran],
    ],
    'cari'      => ['nama_lengkap', 'username'],
    'kolom'     => [['nama_lengkap', 'Nama lengkap'], ['username', 'Username'], ['role', 'Peran', 'chip', $peran]],
]);
