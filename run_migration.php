<?php
// KEAMANAN: Hanya bisa diakses dari localhost. Hapus file ini setelah digunakan!
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/koneksi.php';
header('Content-Type: text/plain; charset=UTF-8');

$files = [
    '001' => __DIR__ . '/migrations/001_roles_audit_void.sql',
    '002' => __DIR__ . '/migrations/002_kategori_program.sql',
    '003' => __DIR__ . '/migrations/003_transaksi_terpadu.sql',
];

$grand_ok = 0; $grand_err = 0;

foreach ($files as $num => $path) {
    echo "\n====== Migrasi $num: " . basename($path) . " ======\n";
    $sql    = file_get_contents($path);
    $stmts  = explode(';', $sql);
    $ok = 0; $err = 0;

    foreach ($stmts as $s) {
        $lines = array_filter(explode("\n", $s), function($l) {
            $l = ltrim($l);
            return $l !== '' && strpos($l, '--') !== 0 && strpos($l, '/*') !== 0;
        });
        $s = trim(implode("\n", $lines));
        if (empty($s)) continue;

        try {
            mysqli_query($conn, $s);
            $ok++;
            echo "  OK : " . substr(preg_replace('/\s+/', ' ', $s), 0, 90) . "\n";
        } catch (mysqli_sql_exception $e) {
            $code = $e->getCode();
            // 1050=tbl exist, 1060=col exist, 1061=idx exist, 1062=dup key, 1065=empty
            if (in_array($code, [1050, 1060, 1061, 1062, 1065], true)) {
                $ok++;
                echo "  SKIP (#{$code}): " . substr(preg_replace('/\s+/', ' ', $s), 0, 70) . "\n";
            } else {
                $err++;
                echo "  ERR [#{$code}]: " . $e->getMessage() . "\n";
                echo "       SQL: " . substr(preg_replace('/\s+/', ' ', $s), 0, 80) . "\n";
            }
        }
    }
    echo "  --> OK=$ok, ERR=$err\n";
    $grand_ok += $ok; $grand_err += $err;
}

echo "\n============================================\n";
echo "TOTAL KESELURUHAN: OK=$grand_ok, ERR=$grand_err\n";
echo "============================================\n";
if ($grand_err === 0) {
    echo "\nMigrasi BERHASIL! Hapus file run_migration.php ini sekarang.\n";
} else {
    echo "\nAda error — cek log di atas.\n";
}