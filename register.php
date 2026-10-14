<?php
require 'fungsi.php';
// Pendaftaran publik dinonaktifkan. Akun baru hanya dibuat Admin lewat users.php.
if (($_SESSION['status'] ?? '') === 'login') {
    redirect('index.php');
}
flash('danger', 'Pendaftaran akun tidak tersedia. Hubungi Administrator UPZ untuk pembuatan akun.');
redirect('login.php');