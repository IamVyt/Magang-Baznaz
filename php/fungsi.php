<?php
// fungsi.php : session, peran pengguna, keamanan, helper query, saldo, dan riwayat aktivitas
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/koneksi.php';

// Tabel transaksi terpadu beserta nama kategorinya
const TRANSAKSI = "(SELECT t.id, t.tipe, COALESCE(t.nama_pihak, 'Hamba Allah') AS nama, t.jumlah, t.tanggal, t.keterangan,
                           t.kategori_id, t.pihak_id, t.dibatalkan_at, t.alasan_batal, kd.nama AS kategori_nama
                    FROM transaksi t LEFT JOIN kategori_dana kd ON kd.id = t.kategori_id) t";

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function redirect($url) { header("Location: $url"); exit(); }

function wajib_login() {
    if (($_SESSION['status'] ?? '') !== 'login') redirect('login.php');
}
// Admin dan bendahara boleh mencatat, mengubah, dan membatalkan transaksi. Ketua hanya melihat
function can_input() { return in_array($_SESSION['role'] ?? '', ['admin', 'bendahara'], true); }

// Petugas (admin, bendahara, ketua) memakai dashboard. Peran user (pendaftar umum) hanya memakai beranda
function is_staff() { return in_array($_SESSION['role'] ?? '', ['admin', 'bendahara', 'ketua'], true); }
function wajib_staff() {
    wajib_login();
    if (!is_staff()) redirect('index.php');
}
function wajib_peran(array $peran) {
    wajib_staff();
    if (!in_array($_SESSION['role'], $peran, true)) {
        flash('danger', 'Anda tidak memiliki izin untuk membuka halaman tersebut.');
        redirect('dashboard.php');
    }
}

function ip_client() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// Pesan sekali tampil
function flash($tipe, $pesan) { $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan]; }
function ambil_flash() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Validasi
function tanggal_valid($d) {
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}
function validasi_jumlah($v) {
    return filter_var(trim((string)$v), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999999999]]);
}

// Query dengan prepared statement
function q($conn, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
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
    return q_all($conn, $sql, $types, $params)[0] ?? null;
}

// Saldo hanya menghitung transaksi yang belum dibatalkan (void)
function hitung_saldo($conn) {
    $r = q_one($conn, "SELECT COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE -jumlah END), 0) AS saldo FROM transaksi WHERE dibatalkan_at IS NULL");
    return round((float)($r['saldo'] ?? 0), 2);
}
function sinkron_saldo($conn) {
    $saldo = hitung_saldo($conn);
    q($conn, "INSERT INTO saldo (id, jumlah_total) VALUES (1, ?) ON DUPLICATE KEY UPDATE jumlah_total = ?", 'dd', [$saldo, $saldo]);
    return $saldo;
}

// Ubah data dalam transaksi database. Mengembalikan null bila sukses, atau pesan galat
function jalankan_transaksi($conn, callable $aksi) {
    mysqli_begin_transaction($conn);
    try {
        $aksi();
        if (sinkron_saldo($conn) < 0) {
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

// Catat aktivitas ke audit_log. Kegagalan mencatat tidak boleh menggagalkan proses utama
function catat_audit($conn, string $aksi, ?string $tabel = null, ?int $record_id = null, ?array $lama = null, ?array $baru = null): void {
    try {
        q($conn, "INSERT INTO audit_log (user_id, username, aksi, tabel, record_id, data_lama, data_baru, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'isssisss', [$_SESSION['user_id'] ?? null, $_SESSION['username'] ?? null, $aksi, $tabel, $record_id,
                $lama ? json_encode($lama, JSON_UNESCAPED_UNICODE) : null, $baru ? json_encode($baru, JSON_UNESCAPED_UNICODE) : null, ip_client()]);
    } catch (Throwable $e) {
    }
}

// Filter pencarian riwayat dari parameter URL
function filter_transaksi($dengan_tipe = true) {
    $cari     = trim($_GET['cari'] ?? '');
    $mulai    = tanggal_valid($_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : '';
    $selesai  = tanggal_valid($_GET['tgl_selesai'] ?? '') ? $_GET['tgl_selesai'] : '';
    $tipe     = $_GET['tipe'] ?? 'semua';
    $kategori = (int)($_GET['kategori'] ?? 0);
    if (!in_array($tipe, ['masuk', 'keluar'], true)) $tipe = 'semua';
    if ($mulai !== '' && $selesai !== '' && $mulai > $selesai) [$mulai, $selesai] = [$selesai, $mulai];

    $sql = ''; $types = ''; $params = [];
    if ($cari !== '') {
        $like = '%' . addcslashes($cari, '%_\\') . '%';
        $sql .= " AND (nama LIKE ? OR keterangan LIKE ?)"; $types .= 'ss'; array_push($params, $like, $like);
    }
    if ($mulai !== '') { $sql .= " AND tanggal >= ?"; $types .= 's'; $params[] = $mulai . ' 00:00:00'; }
    if ($selesai !== '') { $sql .= " AND tanggal < DATE_ADD(?, INTERVAL 1 DAY)"; $types .= 's'; $params[] = $selesai; }
    if ($dengan_tipe && $tipe !== 'semua') { $sql .= " AND tipe = ?"; $types .= 's'; $params[] = $tipe; }
    if ($kategori > 0) { $sql .= " AND kategori_id = ?"; $types .= 'i'; $params[] = $kategori; }
    return compact('sql', 'types', 'params', 'cari', 'mulai', 'selesai', 'tipe', 'kategori');
}

// ===== Donasi online (halaman Salurkan) =====
// Donasi dari pengguna ditampung di tabel donasi dengan status "menunggu". Baru setelah petugas memastikan uangnya
// diterima, donasi dicatat ke tabel transaksi (kas). Dengan begitu saldo tidak terisi donasi yang belum dibayar.
// Struktur tabel yang sama juga ada di database/migrasi.sql
const SQL_TABEL_DONASI = "CREATE TABLE IF NOT EXISTS donasi (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    kode          VARCHAR(24)   NOT NULL,
    user_id       INT(11)       DEFAULT NULL,
    program       VARCHAR(60)   NOT NULL,
    program_nama  VARCHAR(100)  NOT NULL,
    paket         VARCHAR(100)  NOT NULL,
    kategori_kode VARCHAR(30)   NOT NULL,
    nama          VARCHAR(150)  NOT NULL,
    samarkan_nama TINYINT(1)    NOT NULL DEFAULT 0,
    nip           VARCHAR(30)   DEFAULT NULL,
    kontak        VARCHAR(30)   NOT NULL,
    email         VARCHAR(100)  DEFAULT NULL,
    jumlah        DECIMAL(15,2) NOT NULL,
    metode        VARCHAR(30)   NOT NULL,
    catatan       TEXT          DEFAULT NULL,
    status        ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
    transaksi_id  INT UNSIGNED  DEFAULT NULL,
    diproses_oleh INT(11)       DEFAULT NULL,
    diproses_at   DATETIME      DEFAULT NULL,
    alasan_tolak  VARCHAR(255)  DEFAULT NULL,
    dibuat_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_donasi_kode (kode),
    INDEX idx_donasi_user (user_id),
    INDEX idx_donasi_status (status),
    INDEX idx_donasi_dibuat (dibuat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

// Daftar rekening bank dan e-wallet yang bisa dipilih donatur. Diatur admin di menu Metode pembayaran
const SQL_TABEL_REKENING = "CREATE TABLE IF NOT EXISTS rekening_bayar (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    jenis     ENUM('bank','ewallet') NOT NULL,
    kode      VARCHAR(20)  NOT NULL,
    nama      VARCHAR(60)  NOT NULL,
    nomor     VARCHAR(40)  NOT NULL DEFAULT '',
    atas_nama VARCHAR(100) NOT NULL DEFAULT '',
    aktif     TINYINT(1)   NOT NULL DEFAULT 0,
    urutan    INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rekening (jenis, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

// Metode yang punya pilihan bank atau e-wallet: kode metode => jenis di tabel rekening_bayar
const JENIS_CHANNEL = ['transfer' => 'bank', 'ewallet' => 'ewallet'];

// Menyiapkan tabel donasi dan rekening_bayar, serta memperbarui tabel donasi versi lama (status dan kolom baru).
// Dipanggil otomatis oleh halaman donasi, jadi tidak wajib mengimpor migrasi.sql lagi
function siapkan_donasi($conn): void {
    static $siap = false;
    if ($siap) return;
    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'donasi'")) === 0) mysqli_query($conn, SQL_TABEL_DONASI);

    $kolom = [];
    foreach (mysqli_fetch_all(mysqli_query($conn, "SHOW COLUMNS FROM donasi"), MYSQLI_ASSOC) as $k) $kolom[$k['Field']] = $k['Type'];
    if (strpos($kolom['status'] ?? '', 'dibayar') === false) {
        mysqli_query($conn, "ALTER TABLE donasi MODIFY status ENUM('menunggu','dibayar','diterima','ditolak','dibatalkan') NOT NULL DEFAULT 'menunggu'");
    }
    $tambahan = [
        'channel'       => 'VARCHAR(30) DEFAULT NULL AFTER metode',
        'channel_nama'  => 'VARCHAR(60) DEFAULT NULL AFTER channel',
        'channel_info'  => 'VARCHAR(200) DEFAULT NULL AFTER channel_nama',
        'dibayar_at'    => 'DATETIME DEFAULT NULL AFTER status',
        'bukti'         => 'VARCHAR(60) DEFAULT NULL AFTER dibayar_at',
        'dibatalkan_at' => 'DATETIME DEFAULT NULL AFTER alasan_tolak',
    ];
    foreach ($tambahan as $nama => $definisi) {
        if (!isset($kolom[$nama])) mysqli_query($conn, "ALTER TABLE donasi ADD COLUMN $nama $definisi");
    }

    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'rekening_bayar'")) === 0) {
        mysqli_query($conn, SQL_TABEL_REKENING);
        // Daftar awal, semuanya nonaktif sampai admin mengisi nomor dan mengaktifkannya
        mysqli_query($conn, "INSERT IGNORE INTO rekening_bayar (jenis, kode, nama, urutan) VALUES
            ('bank','bca','Bank BCA',1), ('bank','bni','Bank BNI',2), ('bank','bri','Bank BRI',3),
            ('bank','mandiri','Bank Mandiri',4), ('bank','bsi','Bank Syariah Indonesia',5),
            ('ewallet','gopay','GoPay',1), ('ewallet','ovo','OVO',2), ('ewallet','dana','DANA',3), ('ewallet','shopeepay','ShopeePay',4)");
    }
    $siap = true;
}

// Jumlah donasi yang sudah dibayar donatur dan menunggu dicek petugas (lencana sidebar). Aman dipanggil walau tabelnya belum ada
function donasi_menunggu($conn): int {
    try {
        return (int)(q_one($conn, "SELECT COUNT(*) AS n FROM donasi WHERE status = 'dibayar'")['n'] ?? 0);
    } catch (Throwable $galat) {
        return 0;
    }
}

// Teks dan kelas lencana untuk status donasi
function label_status_donasi(string $status): array {
    return [
        'menunggu'   => ['Menunggu pembayaran', 'wait'],
        'dibayar'    => ['Menunggu pengecekan', 'check'],
        'diterima'   => ['Sudah tersalurkan', 'in'],
        'ditolak'    => ['Ditolak', 'void'],
        'dibatalkan' => ['Dibatalkan', 'off'],
    ][$status] ?? [$status, ''];
}

// Halaman tujuan setelah login atau daftar (mis. kembali ke form donasi). Hanya menerima nama file lokal
function simpan_tujuan(string $url): void {
    if (preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $url)) $_SESSION['tujuan'] = $url;
}
function ambil_tujuan(): ?string {
    $t = $_SESSION['tujuan'] ?? null;
    unset($_SESSION['tujuan']);
    return (is_string($t) && preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $t)) ? $t : null;
}

// ===== Pilihan pembayaran, QRIS, dan bukti pembayaran =====

// Rekening bank atau e-wallet yang aktif dan sudah berisi nomor: kode => baris. Jenis: 'bank' atau 'ewallet'
function channel_aktif($conn, string $jenis): array {
    $hasil = [];
    foreach (q_all($conn, "SELECT * FROM rekening_bayar WHERE jenis = ? AND aktif = 1 AND nomor <> '' ORDER BY urutan, nama", 's', [$jenis]) as $r) {
        $hasil[$r['kode']] = $r;
    }
    return $hasil;
}

// Alamat gambar QRIS resmi UPZ (diunggah admin di menu Metode pembayaran), atau null bila belum ada
function qris_berkas(): ?string {
    foreach (['png', 'jpg', 'webp'] as $ext) {
        if (is_file(dirname(__DIR__) . "/img/qris.$ext")) return "img/qris.$ext";
    }
    return null;
}

// Metode yang boleh dipilih donatur sekarang. Tunai selalu ada, yang lain tersedia setelah admin mengaturnya
function metode_tersedia($conn): array {
    return [
        'transfer' => (bool)channel_aktif($conn, 'bank'),
        'ewallet'  => (bool)channel_aktif($conn, 'ewallet'),
        'qris'     => qris_berkas() !== null,
        'tunai'    => true,
    ];
}

// Memeriksa pilihan metode dan bank atau e-wallet dari donatur.
// Mengembalikan [pesanGalat, kodeChannel, namaChannel, infoChannel]
function pilih_pembayaran($conn, array $metode, string $kodeMetode, string $kodeChannel): array {
    if (!isset($metode[$kodeMetode])) return ['Pilih metode pembayaran.', null, null, null];
    $ada = metode_tersedia($conn);
    if (empty($ada[$kodeMetode])) return ['Metode ' . $metode[$kodeMetode][0] . ' belum tersedia. Pilih metode lain.', null, null, null];
    if (isset(JENIS_CHANNEL[$kodeMetode])) {
        $jenis   = JENIS_CHANNEL[$kodeMetode];
        $pilihan = channel_aktif($conn, $jenis);
        if (!isset($pilihan[$kodeChannel])) return ['Pilih ' . ($jenis === 'bank' ? 'bank tujuan' : 'e-wallet') . ' untuk pembayaran Anda.', null, null, null];
        $c = $pilihan[$kodeChannel];
        return [null, $c['kode'], $c['nama'], $c['nomor'] . ($c['atas_nama'] !== '' ? ' a.n. ' . $c['atas_nama'] : '')];
    }
    return [null, null, $metode[$kodeMetode][0], null];
}

// Validasi gambar unggahan (JPG, PNG, WebP). Mengembalikan [ekstensi, pesanGalat]
function validasi_gambar(array $f, int $maksByte = 2097152): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, 'Unggahan gagal. Coba lagi dengan berkas yang lebih kecil.'];
    if (($f['size'] ?? 0) > $maksByte) return [null, 'Ukuran gambar maksimal ' . round($maksByte / 1048576) . ' MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || !@getimagesize($f['tmp_name'])) return [null, 'Berkas harus berupa gambar JPG, PNG, atau WebP.'];
    return [$ext, null];
}

// Folder bukti pembayaran. Dibuat otomatis dan ditutup dari akses langsung, bukti hanya dibuka lewat bukti.php
function folder_bukti(): string {
    $akar = dirname(__DIR__) . '/uploads';
    if (!is_dir($akar . '/bukti')) mkdir($akar . '/bukti', 0755, true);
    if (!is_file($akar . '/.htaccess')) {
        file_put_contents($akar . '/.htaccess', "# Bukti pembayaran hanya boleh dibuka lewat bukti.php\nRequire all denied\n");
    }
    return $akar . '/bukti';
}

// Simpan bukti pembayaran. Mengembalikan [namaBerkas, pesanGalat]. Tanpa berkas dianggap tidak ada bukti
function simpan_bukti(array $f): array {
    if (empty($f['name'])) return [null, null];
    [$ext, $galat] = validasi_gambar($f);
    if ($galat) return [null, $galat];
    $nama = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], folder_bukti() . '/' . $nama)) return [null, 'Bukti pembayaran belum tersimpan. Silakan coba lagi.'];
    return [$nama, null];
}
function nama_bukti_valid($nama): bool { return is_string($nama) && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $nama) === 1; }
function hapus_bukti($nama): void {
    if (nama_bukti_valid($nama)) @unlink(folder_bukti() . '/' . $nama);
}

// Simpan atau hapus gambar QRIS resmi. Mengembalikan pesan galat atau null bila berhasil
function hapus_qris(): void {
    foreach (['png', 'jpg', 'webp'] as $ext) @unlink(dirname(__DIR__) . "/img/qris.$ext");
}
function simpan_qris(array $f): ?string {
    if (empty($f['name'])) return 'Pilih gambar QRIS terlebih dahulu.';
    [$ext, $galat] = validasi_gambar($f);
    if ($galat) return $galat;
    hapus_qris();
    if (!move_uploaded_file($f['tmp_name'], dirname(__DIR__) . "/img/qris.$ext")) return 'Gambar QRIS belum tersimpan. Pastikan folder img dapat ditulis.';
    return null;
}
