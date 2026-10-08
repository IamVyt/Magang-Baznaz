<?php
// ============================================================
// fungsi.php : session, keamanan, helper query, dan logika saldo
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/koneksi.php';

// Gabungan data pemasukan dan pengeluaran dalam satu "tabel" virtual
const TRANSAKSI = "(SELECT id, 'masuk' AS tipe, nama_penyumbang AS nama, jumlah_diterima AS jumlah, tanggal FROM penyumbang
                    UNION ALL
                    SELECT id, 'keluar' AS tipe, nama_penerima AS nama, jumlah_keluar AS jumlah, tanggal FROM penerima) t";

// ---------- Helper tampilan ----------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function redirect($url) { header("Location: $url"); exit(); }

// ---------- Autentikasi ----------
function wajib_login() {
    if (($_SESSION['status'] ?? '') !== 'login') {
        redirect('login.php');
    }
}

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_valid() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// ---------- Flash message ----------
function flash($tipe, $pesan) { $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan]; }
function ambil_flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ---------- Validasi ----------
function tanggal_valid($d) {
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}
function validasi_jumlah($v) {
    return filter_var(trim((string)$v), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999999999]]);
}

// ---------- Helper query (prepared statement) ----------
function q($conn, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}
function q_all($conn, $sql, $types = '', $params = []) {
    $stmt = q($conn, $sql, $types, $params);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}
function q_one($conn, $sql, $types = '', $params = []) {
    $rows = q_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

// ---------- Saldo ----------
// Saldo selalu dihitung dari data transaksi, lalu disalin ke tabel saldo
// sehingga tidak mungkin selisih antara riwayat dan saldo.
function hitung_saldo($conn) {
    $r = mysqli_fetch_row(mysqli_query($conn,
        "SELECT (SELECT COALESCE(SUM(jumlah_diterima),0) FROM penyumbang)
              - (SELECT COALESCE(SUM(jumlah_keluar),0) FROM penerima)"));
    return round((float)$r[0], 2);
}
function sinkron_saldo($conn) {
    $saldo = hitung_saldo($conn);
    q($conn, "INSERT INTO saldo (id, jumlah_total) VALUES (1, ?) ON DUPLICATE KEY UPDATE jumlah_total = ?", 'dd', [$saldo, $saldo]);
    return $saldo;
}

// Jalankan perubahan data dalam transaksi database.
// Mengembalikan null jika sukses, atau pesan error jika gagal.
function jalankan_transaksi($conn, callable $aksi) {
    mysqli_begin_transaction($conn);
    try {
        $aksi();
        $saldo = sinkron_saldo($conn);
        if ($saldo < 0) {
            mysqli_rollback($conn);
            return 'Saldo kas tidak mencukupi, perubahan dibatalkan.';
        }
        mysqli_commit($conn);
        return null;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return 'Terjadi kesalahan pada database. Silakan coba lagi.';
    }
}

// ---------- Filter pencarian riwayat ----------
function filter_transaksi($dengan_tipe = true) {
    $cari    = trim($_GET['cari'] ?? '');
    $mulai   = tanggal_valid($_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : '';
    $selesai = tanggal_valid($_GET['tgl_selesai'] ?? '') ? $_GET['tgl_selesai'] : '';
    $tipe    = $_GET['tipe'] ?? 'semua';
    if (!in_array($tipe, ['masuk', 'keluar'], true)) $tipe = 'semua';

    if ($mulai !== '' && $selesai !== '' && $mulai > $selesai) {
        [$mulai, $selesai] = [$selesai, $mulai];
    }

    $sql = ''; $types = ''; $params = [];
    if ($cari !== '') {
        $sql .= " AND nama LIKE ?"; $types .= 's';
        $params[] = '%' . addcslashes($cari, '%_\\') . '%';
    }
    if ($mulai !== '') {
        $sql .= " AND tanggal >= ?"; $types .= 's';
        $params[] = $mulai . ' 00:00:00';
    }
    if ($selesai !== '') {
        $sql .= " AND tanggal < DATE_ADD(?, INTERVAL 1 DAY)"; $types .= 's';
        $params[] = $selesai;
    }
    if ($dengan_tipe && $tipe !== 'semua') {
        $sql .= " AND tipe = ?"; $types .= 's';
        $params[] = $tipe;
    }
    return compact('sql', 'types', 'params', 'cari', 'mulai', 'selesai', 'tipe');
}
