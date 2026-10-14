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

// Virtual tabel transaksi terpadu dengan alias backward-compatible
// Memuat nama_pihak sebagai 'nama', kategori_nama dari JOIN kategori_dana
const TRANSAKSI = "(SELECT t.id, t.tipe,
                            COALESCE(t.nama_pihak,'Hamba Allah') AS nama,
                            t.jumlah, t.tanggal, t.keterangan,
                            t.kategori_id, t.pihak_id, t.nomor_bukti,
                            t.dibatalkan_at, t.alasan_batal, t.dibuat_oleh,
                            kd.nama AS kategori_nama, kd.kode AS kategori_kode
                     FROM transaksi t
                     LEFT JOIN kategori_dana kd ON kd.id = t.kategori_id) t";

// ---------- Helper tampilan ----------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function redirect($url) { header("Location: $url"); exit(); }

// ---------- Autentikasi & Role ----------
function wajib_login() {
    if (($_SESSION['status'] ?? '') !== 'login') {
        redirect('login.php');
    }
    if (!empty($_SESSION['ganti_password'])) {
        $curPage = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        if ($curPage !== 'ganti_password.php' && $curPage !== 'logout.php') {
            redirect('ganti_password.php');
        }
    }
}

/**
 * Cek apakah user yang login memiliki salah satu role yang diizinkan.
 * Jika tidak, redirect ke index.php dengan pesan error.
 */
function wajib_role(array $roles) {
    wajib_login();
    $roleUser = $_SESSION['role'] ?? 'admin';
    if (!in_array($roleUser, $roles, true)) {
        flash('danger', 'Anda tidak memiliki izin untuk mengakses halaman tersebut.');
        redirect('index.php');
    }
}

/** Apakah user saat ini adalah admin? */
function is_admin()  { return ($_SESSION['role'] ?? '') === 'admin'; }
/** Apakah user saat ini bisa input transaksi? (admin atau bendahara) */
function can_input() { return in_array($_SESSION['role'] ?? '', ['admin', 'bendahara'], true); }

// ---------- IP Client ----------
function ip_client() {
    foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
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
// Saldo dihitung dari transaksi terpadu yang BELUM di-void (dibatalkan_at IS NULL)
function hitung_saldo($conn) {
    $r = q_one($conn,
        "SELECT COALESCE(SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE -jumlah END), 0) AS saldo
         FROM transaksi WHERE dibatalkan_at IS NULL");
    return round((float)($r['saldo'] ?? 0), 2);
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

// ---------- Audit Log ----------
/**
 * Catat aktivitas ke tabel audit_log.
 * @param mixed       $conn      koneksi mysqli (opsional, pakai global jika null)
 * @param string      $aksi      login|logout|tambah|edit|void|hapus_user|tambah_user|edit_user
 * @param string|null $tabel     nama tabel yang terpengaruh
 * @param int|null    $record_id id record yang terpengaruh
 * @param array|null  $data_lama data sebelum perubahan
 * @param array|null  $data_baru data sesudah perubahan
 */
function catat_audit($conn, string $aksi, ?string $tabel = null, ?int $record_id = null,
                     ?array $data_lama = null, ?array $data_baru = null): void {
    $user_id   = $_SESSION['user_id'] ?? null;
    $username  = $_SESSION['username'] ?? null;
    $ip        = ip_client();
    $lama_json = $data_lama ? json_encode($data_lama, JSON_UNESCAPED_UNICODE) : null;
    $baru_json = $data_baru ? json_encode($data_baru, JSON_UNESCAPED_UNICODE) : null;

    try {
        q($conn,
            "INSERT INTO audit_log (user_id, username, aksi, tabel, record_id, data_lama, data_baru, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'isssisss',
            [$user_id, $username, $aksi, $tabel, $record_id, $lama_json, $baru_json, $ip]);
    } catch (Throwable $e) {
        // Jangan gagalkan proses utama hanya karena log gagal dicatat
    }
}

// ---------- Filter pencarian riwayat ----------
function filter_transaksi($dengan_tipe = true) {
    $cari    = trim($_GET['cari'] ?? '');
    $mulai   = tanggal_valid($_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : '';
    $selesai = tanggal_valid($_GET['tgl_selesai'] ?? '') ? $_GET['tgl_selesai'] : '';
    $tipe    = $_GET['tipe'] ?? 'semua';
    $kategori = (int)($_GET['kategori'] ?? 0);
    if (!in_array($tipe, ['masuk', 'keluar'], true)) $tipe = 'semua';

    if ($mulai !== '' && $selesai !== '' && $mulai > $selesai) {
        [$mulai, $selesai] = [$selesai, $mulai];
    }

    $sql = ''; $types = ''; $params = [];
    if ($cari !== '') {
        $sql .= " AND (nama LIKE ? OR keterangan LIKE ?)"; $types .= 'ss';
        $params[] = '%' . addcslashes($cari, '%_\\') . '%';
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
    if ($kategori > 0) {
        $sql .= " AND kategori_id = ?"; $types .= 'i';
        $params[] = $kategori;
    }
    return compact('sql', 'types', 'params', 'cari', 'mulai', 'selesai', 'tipe', 'kategori');
}