<?php
// Koneksi ke database "magang" (sama dengan database sistem upz_sim di XAMPP)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'magang';

try {
    $conn = mysqli_connect($host, $user, $pass, $db);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('Koneksi database gagal. Pastikan MySQL di XAMPP sudah berjalan dan database "' . htmlspecialchars($db) . '" tersedia.');
}
