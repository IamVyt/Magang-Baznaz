-- ============================================================
-- Migrasi database "magang" untuk dashboard keuangan versi baru
-- Aman dijalankan berulang kali (memakai IF NOT EXISTS) dan tidak menghapus data lama.
-- Cara: phpMyAdmin > pilih database magang > tab Import > pilih file ini.
-- Data lama di tabel penyumbang dan penerima disalin ke tabel transaksi satu kali saja.
-- ============================================================

-- 1. Peran pengguna: admin (semua akses), bendahara (input transaksi), ketua (hanya melihat)
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('admin','bendahara','ketua') NOT NULL DEFAULT 'admin' AFTER password;
-- Tambah peran user untuk pendaftar umum (donatur). Akun lama tetap memakai peran yang sudah ada
ALTER TABLE users MODIFY COLUMN role ENUM('admin','bendahara','ketua','user') NOT NULL DEFAULT 'user';

-- 2. Riwayat aktivitas
CREATE TABLE IF NOT EXISTS audit_log (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT(11)     DEFAULT NULL,
    username  VARCHAR(50) DEFAULT NULL,
    aksi      VARCHAR(50) NOT NULL,
    tabel     VARCHAR(50) DEFAULT NULL,
    record_id INT(11)     DEFAULT NULL,
    data_lama JSON        DEFAULT NULL,
    data_baru JSON        DEFAULT NULL,
    ip        VARCHAR(45) DEFAULT NULL,
    waktu     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_aksi (aksi),
    INDEX idx_audit_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Kategori pemasukan dan program penyaluran
CREATE TABLE IF NOT EXISTS kategori_dana (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama       VARCHAR(100) NOT NULL,
    kode       VARCHAR(30)  NOT NULL,
    tipe       ENUM('masuk','keluar') NOT NULL,
    keterangan TEXT DEFAULT NULL,
    aktif      TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kd_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO kategori_dana (nama, kode, tipe, keterangan) VALUES
    ('Zakat Maal',   'ZAKAT_MAAL',   'masuk', 'Zakat harta atau penghasilan'),
    ('Zakat Fitrah', 'ZAKAT_FITRAH', 'masuk', 'Zakat jiwa di bulan Ramadan'),
    ('Infak',        'INFAK',        'masuk', 'Infak sukarela'),
    ('Sedekah',      'SEDEKAH',      'masuk', 'Sedekah atau donasi umum'),
    ('Dana Sosial',  'DANA_SOSIAL',  'masuk', 'Dana sosial keagamaan dan kemanusiaan'),
    ('Fidyah',       'FIDYAH',       'masuk', 'Pembayaran fidyah'),
    ('Wakaf',        'WAKAF',        'masuk', 'Dana wakaf tunai'),
    ('Bantuan Fakir Miskin',    'FAKIR_MISKIN', 'keluar', 'Penyaluran untuk fakir dan miskin'),
    ('Beasiswa Pendidikan',     'BEASISWA',     'keluar', 'Bantuan biaya pendidikan'),
    ('Bantuan Kesehatan',       'KESEHATAN',    'keluar', 'Biaya pengobatan dan kesehatan'),
    ('Bantuan Muallaf',         'MUALLAF',      'keluar', 'Bantuan bagi muallaf'),
    ('Bantuan Gharim (Hutang)', 'GHARIM',       'keluar', 'Bantuan melunasi hutang mendesak'),
    ('Dakwah dan Sabilillah',   'DAKWAH',       'keluar', 'Kegiatan dakwah dan sabilillah'),
    ('Bantuan Ibnu Sabil',      'IBNU_SABIL',   'keluar', 'Bantuan musafir'),
    ('Program Umum / Lainnya',  'UMUM',         'keluar', 'Program umum atau insidental');

-- 4. Master donatur (muzakki) dan penerima manfaat (mustahik)
CREATE TABLE IF NOT EXISTS muzakki (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama      VARCHAR(100) NOT NULL,
    nik_nip   VARCHAR(30) DEFAULT NULL,
    kontak    VARCHAR(50) DEFAULT NULL,
    alamat    TEXT DEFAULT NULL,
    aktif     TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), INDEX idx_mzk_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS mustahik (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama      VARCHAR(100) NOT NULL,
    nik       VARCHAR(20) DEFAULT NULL,
    kontak    VARCHAR(50) DEFAULT NULL,
    alamat    TEXT DEFAULT NULL,
    asnaf     ENUM('fakir','miskin','amil','muallaf','riqab','gharim','sabilillah','ibnu_sabil') NOT NULL DEFAULT 'fakir',
    aktif     TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), INDEX idx_mtk_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Transaksi terpadu (pemasukan dan pengeluaran dalam satu tabel, pembatalan memakai void)
CREATE TABLE IF NOT EXISTS transaksi (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipe          ENUM('masuk','keluar') NOT NULL,
    kategori_id   INT UNSIGNED DEFAULT NULL,
    pihak_id      INT UNSIGNED DEFAULT NULL,
    nama_pihak    VARCHAR(150) DEFAULT NULL,
    jumlah        DECIMAL(15,2) NOT NULL,
    tanggal       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    keterangan    TEXT DEFAULT NULL,
    dibuat_oleh   INT(11) DEFAULT NULL,
    dibatalkan_at DATETIME DEFAULT NULL,
    alasan_batal  TEXT DEFAULT NULL,
    dibuat_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_trx_tipe (tipe), INDEX idx_trx_tanggal (tanggal),
    INDEX idx_trx_kategori (kategori_id), INDEX idx_trx_dibatalkan (dibatalkan_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Salin data lama sekali saja (hanya bila tabel transaksi masih kosong)
INSERT INTO transaksi (tipe, kategori_id, nama_pihak, jumlah, tanggal)
SELECT x.tipe, x.kategori_id, x.nama, x.jumlah, x.tanggal FROM (
    SELECT 'masuk' AS tipe, (SELECT id FROM kategori_dana WHERE kode = 'INFAK') AS kategori_id,
           nama_penyumbang AS nama, jumlah_diterima AS jumlah, tanggal FROM penyumbang
    UNION ALL
    SELECT 'keluar', (SELECT id FROM kategori_dana WHERE kode = 'UMUM'),
           nama_penerima, jumlah_keluar, tanggal FROM penerima
) x
WHERE NOT EXISTS (SELECT 1 FROM transaksi);

-- 7. Donasi online dari halaman Salurkan.
-- Donasi ditampung dulu dengan status 'menunggu'. Setelah petugas memastikan pembayaran diterima dan menekan
-- Konfirmasi di menu Donasi masuk, barulah donasi dicatat ke tabel transaksi (kas) dan saldo bertambah.
-- Tabel ini juga dibuat otomatis oleh aplikasi bila belum ada, jadi langkah ini hanya untuk dokumentasi.
CREATE TABLE IF NOT EXISTS donasi (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Tahapan status donasi, pilihan bank atau e-wallet, dan bukti pembayaran.
-- Status: menunggu (menunggu pembayaran) -> dibayar (menunggu pengecekan) -> diterima (sudah tersalurkan),
-- atau ditolak atau dibatalkan. Hanya status diterima yang dicatat ke kas.
-- Bagian ini juga dijalankan otomatis oleh aplikasi, jadi hanya untuk dokumentasi dan pemasangan manual.
ALTER TABLE donasi MODIFY COLUMN status ENUM('menunggu','dibayar','diterima','ditolak','dibatalkan') NOT NULL DEFAULT 'menunggu';
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS channel       VARCHAR(30)  DEFAULT NULL AFTER metode;
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS channel_nama  VARCHAR(60)  DEFAULT NULL AFTER channel;
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS channel_info  VARCHAR(200) DEFAULT NULL AFTER channel_nama;
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS dibayar_at    DATETIME     DEFAULT NULL AFTER status;
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS bukti         VARCHAR(60)  DEFAULT NULL AFTER dibayar_at;
ALTER TABLE donasi ADD COLUMN IF NOT EXISTS dibatalkan_at DATETIME     DEFAULT NULL AFTER alasan_tolak;

-- Daftar rekening bank dan e-wallet. Diatur admin di menu Metode pembayaran dan baru tampil
-- sebagai pilihan donatur setelah diaktifkan dan nomornya diisi.
CREATE TABLE IF NOT EXISTS rekening_bayar (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO rekening_bayar (jenis, kode, nama, urutan)
SELECT x.jenis, x.kode, x.nama, x.urutan FROM (
    SELECT 'bank' AS jenis, 'bca' AS kode, 'Bank BCA' AS nama, 1 AS urutan
    UNION ALL SELECT 'bank', 'bni', 'Bank BNI', 2
    UNION ALL SELECT 'bank', 'bri', 'Bank BRI', 3
    UNION ALL SELECT 'bank', 'mandiri', 'Bank Mandiri', 4
    UNION ALL SELECT 'bank', 'bsi', 'Bank Syariah Indonesia', 5
    UNION ALL SELECT 'ewallet', 'gopay', 'GoPay', 1
    UNION ALL SELECT 'ewallet', 'ovo', 'OVO', 2
    UNION ALL SELECT 'ewallet', 'dana', 'DANA', 3
    UNION ALL SELECT 'ewallet', 'shopeepay', 'ShopeePay', 4
) x
WHERE NOT EXISTS (SELECT 1 FROM rekening_bayar);
