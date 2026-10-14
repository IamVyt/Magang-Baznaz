-- ============================================================
-- Migrasi 003 : Tabel Transaksi Terpadu, Kategori Dana, Muzakki, Mustahik
-- Jalankan via: http://localhost/Magang-Baznaz-main/run_migration.php
-- Data lama dari penyumbang & penerima dimigrasikan otomatis (tidak ada data hilang).
-- ============================================================

-- 1. Tabel kategori_dana (gabungan masuk & keluar)
CREATE TABLE IF NOT EXISTS kategori_dana (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama       VARCHAR(100) NOT NULL,
    kode       VARCHAR(30)  NOT NULL,
    tipe       ENUM('masuk','keluar') NOT NULL,
    keterangan TEXT         DEFAULT NULL,
    aktif      TINYINT(1)   NOT NULL DEFAULT 1,
    dibuat_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kd_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Seed data kategori_dana masuk (Zakat Maal, Zakat Fitrah, Infak, Sedekah, Dana Sosial, dll)
INSERT IGNORE INTO kategori_dana (nama, kode, tipe, keterangan) VALUES
    ('Zakat Maal',    'ZAKAT_MAAL',   'masuk',  'Zakat harta kekayaan wajib atas penghasilan'),
    ('Zakat Fitrah',  'ZAKAT_FITRAH', 'masuk',  'Zakat jiwa yang dikeluarkan di bulan Ramadan'),
    ('Infak',         'INFAK',        'masuk',  'Infak sukarela dari karyawan/mitra'),
    ('Sedekah',       'SEDEKAH',      'masuk',  'Sedekah / donasi umum sukarela'),
    ('Dana Sosial',   'DANA_SOSIAL',  'masuk',  'Dana sosial keagamaan dan kemanusiaan lainnya'),
    ('Fidyah',        'FIDYAH',       'masuk',  'Pembayaran fidyah pengganti puasa'),
    ('Wakaf',         'WAKAF',        'masuk',  'Dana wakaf tunai/uang yang diterima UPZ');

-- 3. Seed data kategori_dana keluar (Program Penyaluran 8 Asnaf + Umum)
INSERT IGNORE INTO kategori_dana (nama, kode, tipe, keterangan) VALUES
    ('Bantuan Fakir Miskin',    'FAKIR_MISKIN',  'keluar', 'Penyaluran untuk golongan fakir dan miskin'),
    ('Beasiswa Pendidikan',     'BEASISWA',      'keluar', 'Bantuan biaya pendidikan mustahik'),
    ('Bantuan Kesehatan',       'KESEHATAN',     'keluar', 'Biaya pengobatan dan kesehatan mustahik'),
    ('Bantuan Muallaf',         'MUALLAF',       'keluar', 'Bantuan bagi mualaf yang baru masuk Islam'),
    ('Bantuan Gharim (Hutang)', 'GHARIM',        'keluar', 'Bantuan melunasi hutang mendesak'),
    ('Dakwah & Sabilillah',     'DAKWAH',        'keluar', 'Kegiatan dakwah dan sabilillah'),
    ('Bantuan Ibnu Sabil',      'IBNU_SABIL',    'keluar', 'Bantuan musafir / perjalanan'),
    ('Program Umum / Lainnya',  'UMUM',          'keluar', 'Program penyaluran umum atau insidental');

-- Salin juga dari kategori_pemasukan / program_penyaluran jika ada kode kustom lain
INSERT IGNORE INTO kategori_dana (nama, kode, tipe, keterangan)
SELECT nama, kode, 'masuk', keterangan FROM kategori_pemasukan;

INSERT IGNORE INTO kategori_dana (nama, kode, tipe, keterangan)
SELECT nama, kode, 'keluar', keterangan FROM program_penyaluran;

-- 4. Tabel muzakki (master donatur / muzakki)
CREATE TABLE IF NOT EXISTS muzakki (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama      VARCHAR(100) NOT NULL,
    nik_nip   VARCHAR(30)  DEFAULT NULL,
    kontak    VARCHAR(50)  DEFAULT NULL,
    alamat    TEXT         DEFAULT NULL,
    aktif     TINYINT(1)   NOT NULL DEFAULT 1,
    dibuat_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_mzk_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Tabel mustahik (master penerima manfaat berdasarkan 8 asnaf)
CREATE TABLE IF NOT EXISTS mustahik (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama      VARCHAR(100) NOT NULL,
    nik       VARCHAR(20)  DEFAULT NULL,
    kontak    VARCHAR(50)  DEFAULT NULL,
    alamat    TEXT         DEFAULT NULL,
    asnaf     ENUM('fakir','miskin','amil','muallaf','riqab','gharim','sabilillah','ibnu_sabil') NOT NULL DEFAULT 'fakir',
    aktif     TINYINT(1)   NOT NULL DEFAULT 1,
    dibuat_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_mtk_nama  (nama),
    INDEX idx_mtk_asnaf (asnaf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Tabel transaksi terpadu (menggabungkan penyumbang & penerima)
CREATE TABLE IF NOT EXISTS transaksi (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tipe          ENUM('masuk','keluar') NOT NULL,
    kategori_id   INT UNSIGNED  DEFAULT NULL,
    pihak_id      INT UNSIGNED  DEFAULT NULL,
    nama_pihak    VARCHAR(150)  DEFAULT NULL,
    jumlah        DECIMAL(15,2) NOT NULL,
    tanggal       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    keterangan    TEXT          DEFAULT NULL,
    nomor_bukti   VARCHAR(50)   DEFAULT NULL,
    bukti_file    VARCHAR(255)  DEFAULT NULL,
    dibuat_oleh   INT(11)       DEFAULT NULL,
    dibatalkan_at DATETIME      DEFAULT NULL,
    alasan_batal  TEXT          DEFAULT NULL,
    dibuat_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_trx_tipe       (tipe),
    INDEX idx_trx_tanggal    (tanggal),
    INDEX idx_trx_kategori   (kategori_id),
    INDEX idx_trx_pihak      (pihak_id),
    INDEX idx_trx_dibatalkan (dibatalkan_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Migrasi data penyumbang → transaksi (tipe=masuk)
INSERT INTO transaksi
    (tipe, kategori_id, nama_pihak, jumlah, tanggal, keterangan, dibuat_oleh, dibatalkan_at, alasan_batal)
SELECT
    'masuk',
    COALESCE(
        (SELECT kd.id FROM kategori_dana kd
         JOIN kategori_pemasukan kp ON kp.kode = kd.kode AND kd.tipe = 'masuk'
         WHERE kp.id = p.kategori_id LIMIT 1),
        (SELECT id FROM kategori_dana WHERE kode = 'INFAK' LIMIT 1)
    ),
    p.nama_penyumbang,
    p.jumlah_diterima,
    p.tanggal,
    p.catatan,
    p.dibuat_oleh,
    p.dibatalkan_at,
    p.alasan_batal
FROM penyumbang p
WHERE NOT EXISTS (
    SELECT 1 FROM transaksi t
    WHERE t.tipe = 'masuk'
      AND t.tanggal = p.tanggal
      AND t.jumlah = p.jumlah_diterima
      AND t.nama_pihak = p.nama_penyumbang
);

-- 8. Migrasi data penerima → transaksi (tipe=keluar)
INSERT INTO transaksi
    (tipe, kategori_id, nama_pihak, jumlah, tanggal, keterangan, dibuat_oleh, dibatalkan_at, alasan_batal)
SELECT
    'keluar',
    COALESCE(
        (SELECT kd.id FROM kategori_dana kd
         JOIN program_penyaluran pp ON pp.kode = kd.kode AND kd.tipe = 'keluar'
         WHERE pp.id = r.program_id LIMIT 1),
        (SELECT id FROM kategori_dana WHERE kode = 'UMUM' LIMIT 1)
    ),
    r.nama_penerima,
    r.jumlah_keluar,
    r.tanggal,
    r.catatan,
    r.dibuat_oleh,
    r.dibatalkan_at,
    r.alasan_batal
FROM penerima r
WHERE NOT EXISTS (
    SELECT 1 FROM transaksi t
    WHERE t.tipe = 'keluar'
      AND t.tanggal = r.tanggal
      AND t.jumlah = r.jumlah_keluar
      AND t.nama_pihak = r.nama_penerima
);

-- 9. Sinkronisasi saldo kas dari tabel transaksi terpadu
UPDATE saldo SET jumlah_total = (
    SELECT COALESCE(SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE -jumlah END), 0)
    FROM transaksi WHERE dibatalkan_at IS NULL
) WHERE id = 1;
