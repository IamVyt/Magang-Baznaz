<?php
require 'fungsi.php';
wajib_login();

// ============================================================
// PROSES AKSI PENGGUNA (TAMBAH, EDIT, HAPUS)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid()) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect('users.php');
    }

    $aksi = $_POST['aksi'] ?? '';

    // ---------- TAMBAH PENGGUNA ----------
    if ($aksi === 'tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama lengkap wajib diisi dan maksimal 100 karakter.');
        } elseif ($username === '' || mb_strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            flash('danger', 'Username hanya boleh berisi huruf, angka, garis bawah (_), titik, dan strip (-).');
        } elseif (mb_strlen($password) < 5) {
            flash('danger', 'Password minimal terdiri dari 5 karakter.');
        } else {
            // Cek apakah username sudah ada
            $duplikat = q_one($conn, "SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1", 's', [$username]);
            if ($duplikat) {
                flash('danger', "Username '$username' sudah terdaftar. Silakan gunakan username lain.");
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                q($conn, "INSERT INTO users (nama_lengkap, username, password) VALUES (?, ?, ?)",
                    'sss', [$nama, $username, $hash]);
                flash('success', "Pengguna baru '$username' berhasil ditambahkan.");
            }
        }
        redirect('users.php');
    }

    // ---------- EDIT PENGGUNA ----------
    elseif ($aksi === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = trim($_POST['nama'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $target = q_one($conn, "SELECT * FROM users WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Pengguna tidak ditemukan.');
            redirect('users.php');
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama lengkap wajib diisi dan maksimal 100 karakter.');
        } else {
            if ($password !== '') {
                if (mb_strlen($password) < 5) {
                    flash('danger', 'Password baru minimal terdiri dari 5 karakter.');
                    redirect('users.php');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                q($conn, "UPDATE users SET nama_lengkap = ?, password = ? WHERE id = ?", 'ssi', [$nama, $hash, $id]);
            } else {
                q($conn, "UPDATE users SET nama_lengkap = ? WHERE id = ?", 'si', [$nama, $id]);
            }

            // Jika user mengedit profilnya sendiri, perbarui sesi namanya
            if (strtolower($_SESSION['username'] ?? '') === strtolower($target['username'])) {
                $_SESSION['nama'] = $nama;
            }

            flash('success', "Data pengguna '{$target['username']}' berhasil diperbarui.");
        }
        redirect('users.php');
    }

    // ---------- HAPUS PENGGUNA ----------
    elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);

        $target = q_one($conn, "SELECT * FROM users WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Pengguna tidak ditemukan.');
            redirect('users.php');
        }

        // Cegah menghapus akun yang sedang aktif digunakan
        if (strtolower($_SESSION['username'] ?? '') === strtolower($target['username'])) {
            flash('danger', 'Anda tidak dapat menghapus akun yang sedang Anda gunakan saat ini.');
            redirect('users.php');
        }

        // Cegah menghapus jika hanya tersisa 1 user
        $totalUser = (int)q_one($conn, "SELECT COUNT(*) AS n FROM users")['n'];
        if ($totalUser <= 1) {
            flash('danger', 'Tidak dapat menghapus satu-satunya akun pengguna yang tersisa dalam sistem.');
            redirect('users.php');
        }

        q($conn, "DELETE FROM users WHERE id = ?", 'i', [$id]);
        flash('success', "Pengguna '{$target['username']}' berhasil dihapus.");
        redirect('users.php');
    }
}

// Ambil semua pengguna
$daftar_user = q_all($conn, "SELECT id, nama_lengkap, username FROM users ORDER BY id ASC");

$judul = 'Kelola Pengguna';
$aktif = 'users';
require 'partials/header.php';
?>

<!-- Tombol Tambah Pengguna Baris Atas -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Manajemen Akses & Staf</h2>
        <p class="text-muted small mb-0">Kelola akun administrator untuk mengelola dana zakat dan donasi.</p>
    </div>
    <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
    </button>
</div>

<!-- Daftar Pengguna -->
<div class="dashboard-card">
    <div class="dashboard-card-head">
        <h2 class="dashboard-card-title">
            <i class="bi bi-people-fill text-primary"></i>Daftar Pengguna Aktif
        </h2>
        <span class="text-muted small"><?= count($daftar_user) ?> akun terdaftar</span>
    </div>

    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 70px;">No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Status Akun</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar_user): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state-friendly">
                            <div class="empty-doc-icon">
                                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                            </div>
                            <div class="empty-state-title">Belum Ada Pengguna</div>
                            <div class="empty-state-sub">Belum ada akun pengguna tambahan dalam sistem ini.</div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php
            $no = 1;
            foreach ($daftar_user as $u):
                $isSaya = (strtolower($_SESSION['username'] ?? '') === strtolower($u['username']));
                $inisial = strtoupper(substr($u['nama_lengkap'] ?: $u['username'], 0, 1));
            ?>
                <tr>
                    <td class="text-muted fw-bold"><?= $no++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="user-avatar-circle"><?= e($inisial) ?></span>
                            <div>
                                <span class="fw-bold text-dark d-block"><?= e($u['nama_lengkap'] ?: '-') ?></span>
                                <small class="text-muted">ID: #<?= (int)$u['id'] ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <code class="text-primary bg-light px-2 py-1 rounded" style="font-size: 0.85rem;">@<?= e($u['username']) ?></code>
                    </td>
                    <td>
                        <?php if ($isSaya): ?>
                            <span class="badge-friendly badge-masuk">
                                <span class="badge-dot"></span> Sedang Aktif (Anda)
                            </span>
                        <?php else: ?>
                            <span class="badge-friendly badge-keluar">
                                <span class="badge-dot"></span> Administrator
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <!-- Tombol Edit -->
                        <button type="button" class="btn-icon-action me-1" title="Edit Profil"
                                data-bs-toggle="modal" data-bs-target="#modalEditUser"
                                data-id="<?= (int)$u['id'] ?>"
                                data-nama="<?= e($u['nama_lengkap']) ?>"
                                data-username="<?= e($u['username']) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Tombol Hapus -->
                        <?php if (!$isSaya): ?>
                            <button type="button" class="btn-icon-action danger" title="Hapus Pengguna"
                                    data-bs-toggle="modal" data-bs-target="#modalHapusUser"
                                    data-id="<?= (int)$u['id'] ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-nama="<?= e($u['nama_lengkap']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-icon-action" disabled style="opacity: 0.35; cursor: not-allowed;" title="Tidak dapat menghapus akun sendiri">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-green mb-0">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Tambah Pengguna Baru</h5>
                        <p class="text-muted small mb-0">Daftarkan akun petugas baru untuk sistem</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="tambahNama">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" id="tambahNama" name="nama" class="form-control" placeholder="Contoh: Muhammad Rian" maxlength="100" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="tambahUsername">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-at text-muted"></i></span>
                        <input type="text" id="tambahUsername" name="username" class="form-control" placeholder="Contoh: rian_upz" maxlength="50" required>
                    </div>
                    <div class="form-text text-muted small">Hanya huruf, angka, garis bawah (_), atau titik tanpa spasi.</div>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="tambahPassword">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" id="tambahPassword" name="password" class="form-control" placeholder="Minimal 5 karakter" minlength="5" required>
                        <button type="button" class="btn btn-petro-outline btn-toggle-pw" tabindex="-1"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-green px-4">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-blue mb-0">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold">Edit Data Pengguna</h5>
                        <p class="text-muted small mb-0">Perbarui identitas atau ganti password akun</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Username</label>
                    <input type="text" id="editUsername" class="form-control bg-light" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="editNama">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" id="editNama" name="nama" class="form-control" maxlength="100" required>
                    </div>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="editPassword">Password Baru (Opsional)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" id="editPassword" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah">
                        <button type="button" class="btn btn-petro-outline btn-toggle-pw" tabindex="-1"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="form-text text-muted small">Hanya diisi bila ingin mengganti password baru.</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-primary px-4">Perbarui Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Pengguna -->
<div class="modal fade" id="modalHapusUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" id="hapusUserId">
            <div class="modal-body text-center p-4">
                <div class="stat-icon-box mx-auto mb-3" style="background: var(--danger-soft); color: var(--danger); width: 54px; height: 54px; font-size: 1.6rem;">
                    <i class="bi bi-person-x"></i>
                </div>
                <h5 class="fw-bold mb-1">Hapus Pengguna?</h5>
                <p class="text-muted small mb-0">Akun dengan username <strong id="hapusUserUsername" class="text-dark"></strong> akan dihapus permanen.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger rounded-3 fw-bold px-3">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<?php require 'partials/footer.php'; ?>

<script>
    // Inisialisasi modal edit
    document.getElementById('modalEditUser').addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        document.getElementById('editId').value = b.dataset.id;
        document.getElementById('editNama').value = b.dataset.nama;
        document.getElementById('editUsername').value = b.dataset.username;
        document.getElementById('editPassword').value = '';
    });

    // Inisialisasi modal hapus
    document.getElementById('modalHapusUser').addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        document.getElementById('hapusUserId').value = b.dataset.id;
        document.getElementById('hapusUserUsername').textContent = '@' + b.dataset.username;
    });

    // Toggle tampilan password
    document.querySelectorAll('.btn-toggle-pw').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var group = this.closest('.input-group');
            var input = group.querySelector('input[type="password"], input[type="text"]');
            var icon = this.querySelector('i');
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
</script>
</body>
</html>