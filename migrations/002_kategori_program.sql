-- ============================================================
-- Migrasi 002 : Kategori Pemasukan & Program Penyaluran
-- Jalankan via: http://localhost/Magang-Baznaz-main/run_migration.php
-- (setelah ganti isi file run_migration.php atau langsung di phpMyAdmin)
-- ============================================================

-- 1. Tabel kategori pemasukan (Zakat Maal, Zakat Fitrah, Infak, Sedekah, dll.)
CREATE TABLE IF NOT EXISTS kategori_pemasukan (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama        VARCHAR(100) NOT NULL,
    kode        VARCHAR(20)  NOT NULL,
    keterangan  TEXT         DEFAULT NULL,
    aktif       TINYINT(1)   NOT NULL DEFAULT 1,
    dibuat_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Seed data kategori pemasukan default
INSERT IGNORE INTO kategori_pemasukan (nama, kode, keterangan) VALUES
    ('Zakat Maal',   'ZAKAT_MAAL',   'Zakat harta kekayaan wajib atas penghasilan'),
    ('Zakat Fitrah', 'ZAKAT_FITRAH', 'Zakat jiwa yang dikeluarkan di bulan Ramadan'),
    ('Infak',        'INFAK',        'Infak sukarela dari karyawan/mitra'),
    ('Sedekah',      'SEDEKAH',      'Sedekah / donasi umum'),
    ('Fidyah',       'FIDYAH',       'Pembayaran fidyah pengganti puasa'),
    ('Wakaf',        'WAKAF',        'Dana wakaf yang diterima UPZ');

-- 3. Tabel program penyaluran (Fakir Miskin, Beasiswa, Kesehatan, dll.)
CREATE TABLE IF NOT EXISTS program_penyaluran (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama        VARCHAR(100) NOT NULL,
    kode        VARCHAR(20)  NOT NULL,
    keterangan  TEXT         DEFAULT NULL,
    aktif       TINYINT(1)   NOT NULL DEFAULT 1,
    dibuat_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kode_prog (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Seed data program penyaluran default (8 asnaf + program internal)
INSERT IGNORE INTO program_penyaluran (nama, kode, keterangan) VALUES
    ('Bantuan Fakir Miskin',    'FAKIR_MISKIN',  'Penyaluran untuk golongan fakir dan miskin'),
    ('Beasiswa Pendidikan',     'BEASISWA',      'Bantuan biaya pendidikan anak kurang mampu'),
    ('Bantuan Kesehatan',       'KESEHATAN',     'Biaya pengobatan dan kesehatan mustahiq'),
    ('Bantuan Muallaf',         'MUALLAF',       'Bantuan bagi mualaf yang baru masuk Islam'),
    ('Bantuan Gharim (Hutang)', 'GHARIM',        'Bantuan melunasi hutang yang mencekik'),
    ('Dakwah & Sabilillah',     'DAKWAH',        'Kegiatan dakwah dan di jalan Allah'),
    ('Bantuan Ibnu Sabil',      'IBNU_SABIL',    'Bantuan musafir / orang dalam perjalanan'),
    ('Program Umum / Lainnya',  'UMUM',          'Program penyaluran umum atau tidak terkategori');

-- 5. Tambah kolom kategori_id ke tabel penyumbang
ALTER TABLE penyumbang
    ADD COLUMN IF NOT EXISTS kategori_id INT UNSIGNED DEFAULT NULL AFTER nama_penyumbang,
    ADD COLUMN IF NOT EXISTS catatan     TEXT         DEFAULT NULL AFTER alasan_batal;

-- 6. Tambah kolom program_id dan catatan ke tabel penerima
ALTER TABLE penerima
    ADD COLUMN IF NOT EXISTS program_id INT UNSIGNED DEFAULT NULL AFTER nama_penerima,
    ADD COLUMN IF NOT EXISTS catatan    TEXT         DEFAULT NULL AFTER alasan_batal;

-- 7. Update TRANSAKSI view agar sertakan kategori & program
CREATE OR REPLACE VIEW v_transaksi_aktif AS
    SELECT
        p.id,
        'masuk'             AS tipe,
        p.nama_penyumbang   AS nama,
        p.jumlah_diterima   AS jumlah,
        p.tanggal,
        kp.nama             AS kategori_nama,
        kp.kode             AS kategori_kode,
        NULL                AS program_nama,
        NULL                AS program_kode,
        p.catatan
    FROM penyumbang p
    LEFT JOIN kategori_pemasukan kp ON kp.id = p.kategori_id
    WHERE p.dibatalkan_at IS NULL
    UNION ALL
    SELECT
        r.id,
        'keluar'            AS tipe,
        r.nama_penerima     AS nama,
        r.jumlah_keluar     AS jumlah,
        r.tanggal,
        NULL                AS kategori_nama,
        NULL                AS kategori_kode,
        pp.nama             AS program_nama,
        pp.kode             AS program_kode,
        r.catatan
    FROM penerima r
    LEFT JOIN program_penyaluran pp ON pp.id = r.program_id
    WHERE r.dibatalkan_at IS NULL;

-- 8. Indeks FK untuk performa
ALTER TABLE penyumbang ADD INDEX IF NOT EXISTS idx_kategori (kategori_id);
ALTER TABLE penerima   ADD INDEX IF NOT EXISTS idx_program  (program_id);