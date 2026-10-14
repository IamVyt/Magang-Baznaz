-- ============================================================
-- Migrasi 001 : Role, Audit Log, Void/Soft-Delete, Paksa Ganti PW
-- Jalankan sekali di phpMyAdmin atau CLI:
--   mysql -u root magang < migrations/001_roles_audit_void.sql
-- ============================================================

-- 1. Tambah kolom role ke tabel users (default admin agar akun lama tetap berfungsi)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('admin','bendahara','ketua') NOT NULL DEFAULT 'admin' AFTER password;

-- 2. Tambah kolom ganti_password (paksa ganti sandi saat login pertama)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS ganti_password TINYINT(1) NOT NULL DEFAULT 0 AFTER role;

-- 3. Tandai akun admin awal agar wajib ganti password pertama kali
UPDATE users SET ganti_password = 1;

-- 4. Tabel audit_log
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT(11)         DEFAULT NULL,
    username    VARCHAR(50)     DEFAULT NULL,
    aksi        VARCHAR(50)     NOT NULL,
    tabel       VARCHAR(50)     DEFAULT NULL,
    record_id   INT(11)         DEFAULT NULL,
    data_lama   JSON            DEFAULT NULL,
    data_baru   JSON            DEFAULT NULL,
    ip          VARCHAR(45)     DEFAULT NULL,
    waktu       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_user  (user_id),
    INDEX idx_audit_aksi  (aksi),
    INDEX idx_audit_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Kolom soft-delete (void) di tabel penyumbang
ALTER TABLE penyumbang
    ADD COLUMN IF NOT EXISTS dibatalkan_at DATETIME DEFAULT NULL AFTER tanggal,
    ADD COLUMN IF NOT EXISTS alasan_batal  TEXT      DEFAULT NULL AFTER dibatalkan_at,
    ADD COLUMN IF NOT EXISTS dibuat_oleh   INT(11)   DEFAULT NULL AFTER alasan_batal;

-- 6. Kolom soft-delete (void) di tabel penerima
ALTER TABLE penerima
    ADD COLUMN IF NOT EXISTS dibatalkan_at DATETIME DEFAULT NULL AFTER tanggal,
    ADD COLUMN IF NOT EXISTS alasan_batal  TEXT      DEFAULT NULL AFTER dibatalkan_at,
    ADD COLUMN IF NOT EXISTS dibuat_oleh   INT(11)   DEFAULT NULL AFTER alasan_batal;

-- 7. View transaksi aktif (belum di-void)
CREATE OR REPLACE VIEW v_transaksi_aktif AS
    SELECT id, 'masuk' AS tipe, nama_penyumbang AS nama, jumlah_diterima AS jumlah, tanggal
      FROM penyumbang WHERE dibatalkan_at IS NULL
    UNION ALL
    SELECT id, 'keluar' AS tipe, nama_penerima AS nama, jumlah_keluar AS jumlah, tanggal
      FROM penerima  WHERE dibatalkan_at IS NULL;
