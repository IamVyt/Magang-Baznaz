<?php
require 'fungsi.php';
wajib_login();
catat_audit($conn, 'logout', 'users', $_SESSION['user_id'] ?? null);
session_unset();
session_destroy();
session_start();
flash('success', 'Anda telah berhasil keluar dari sistem.');
redirect('login.php');