<?php
require 'fungsi.php';
wajib_login();
wajib_role(['admin']); // Hanya admin yang boleh kelola user

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
        $role     = $_POST['role'] ?? 'bendahara';

        if (!in_array($role, ['admin','bendahara','ketua'], true)) $role = 'bendahara';

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama lengkap wajib diisi dan maksimal 100 karakter.');
        } elseif ($username === '' || mb_strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            flash('danger', 'Username hanya boleh berisi huruf, angka, garis bawah (_), titik, dan strip (-).');
        } elseif (mb_strlen($password) < 8) {
            flash('danger', 'Password minimal terdiri dari 8 karakter.');
        } else {
            $duplikat = q_one($conn, "SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1", 's', [$username]);
            if ($duplikat) {
                flash('danger', "Username '$username' sudah terdaftar. Silakan gunakan username lain.");
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                q($conn, "INSERT INTO users (nama_lengkap, username, password, role, ganti_password) VALUES (?, ?, ?, ?, 1)",
                    'ssssi', [$nama, $username, $hash, $role, 1]);
                $new_id = mysqli_insert_id($conn);
                catat_audit($conn, 'tambah_user', 'users', $new_id, null,
                    ['nama_lengkap' => $nama, 'username' => $username, 'role' => $role]);
                flash('success', "Pengguna baru '$username' berhasil ditambahkan (role: $role). User wajib ganti password saat login pertama.");
            }
        }
        redirect('users.php');
    }

    // ---------- EDIT PENGGUNA ----------
    elseif ($aksi === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = trim($_POST['nama'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? '';

        if (!in_array($role, ['admin','bendahara','ketua'], true)) $role = '';

        $target = q_one($conn, "SELECT * FROM users WHERE id = ?", 'i', [$id]);
        if (!$target) {
            flash('danger', 'Pengguna tidak ditemukan.');
            redirect('users.php');
        }

        // Cegah admin menghapus role admin dirinya sendiri jika hanya ada 1 admin
        if ($role !== 'admin' && strtolower($_SESSION['username'] ?? '') === strtolower($target['username'])) {
            $jml_admin = (int)q_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin'")['n'];
            if ($jml_admin <= 1) {
                flash('danger', 'Tidak dapat mengubah role satu-satunya akun admin yang tersisa.');
                redirect('users.php');
            }
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('danger', 'Nama lengkap wajib diisi dan maksimal 100 karakter.');
        } else {
            $data_lama = ['nama_lengkap' => $target['nama_lengkap'], 'role' => $target['role']];
            if ($password !== '') {
                if (mb_strlen($password) < 8) {
                    flash('danger', 'Password baru minimal terdiri dari 8 karakter.');
                    redirect('users.php');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($role !== '') {
                    q($conn, "UPDATE users SET nama_lengkap = ?, password = ?, role = ? WHERE id = ?", 'sssi', [$nama, $hash, $role, $id]);
                } else {
                    q($conn, "UPDATE users SET nama_lengkap = ?, password = ? WHERE id = ?", 'ssi', [$nama, $hash, $id]);
                }
            } else {
                if ($role !== '') {
                    q($conn, "UPDATE users SET nama_lengkap = ?, role = ? WHERE id = ?", 'ssi', [$nama, $role, $id]);
                } else {
                    q($conn, "UPDATE users SET nama_lengkap = ? WHERE id = ?", 'si', [$nama, $id]);
                }
            }

            if (strtolower($_SESSION['username'] ?? '') === strtolower($target['username'])) {
                $_SESSION['nama'] = $nama;
                if ($role !== '') $_SESSION['role'] = $role;
            }

            $data_baru = ['nama_lengkap' => $nama, 'role' => $role ?: $target['role']];
            catat_audit($conn, 'edit_user', 'users', $id, $data_lama, $data_baru);
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
        if (strtolower($_SESSION['username'] ?? '') === strtolower($target['username'])) {
            flash('danger', 'Anda tidak dapat menghapus akun yang sedang Anda gunakan saat ini.');
            redirect('users.php');
        }
        $totalUser = (int)q_one($conn, "SELECT COUNT(*) AS n FROM users")['n'];
        if ($totalUser <= 1) {
            flash('danger', 'Tidak dapat menghapus satu-satunya akun pengguna yang tersisa dalam sistem.');
            redirect('users.php');
        }

        catat_audit($conn, 'hapus_user', 'users', $id,
            ['nama_lengkap' => $target['nama_lengkap'], 'username' => $target['username'], 'role' => $target['role']],
            null);
        q($conn, "DELETE FROM users WHERE id = ?", 'i', [$id]);
        flash('success', "Pengguna '{$target['username']}' berhasil dihapus.");
        redirect('users.php');
    }
}

// Ambil semua pengguna
$daftar_user = q_all($conn, "SELECT id, nama_lengkap, username, role, ganti_password FROM users ORDER BY id ASC");

$judul = 'Kelola Pengguna';
$aktif = 'users';
require 'partials/header.php';

$badge_role = ['admin' => 'badge-masuk', 'bendahara' => 'badge-keluar', 'ketua' => 'badge-info'];
$label_role = ['admin' => 'Admin', 'bendahara' => 'Bendahara', 'ketua' => 'Ketua'];
?>

<!-- Tombol Tambah Pengguna -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Manajemen Akses &amp; Staf</h2>
        <p class="text-muted small mb-0">Kelola akun petugas berdasarkan role: Admin, Bendahara, dan Ketua.</p>
    </div>
    <button type="button" class="btn-petro-green" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
    </button>
</div>

<!-- Info Role -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dashboard-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon-box stat-icon-blue mb-0"><i class="bi bi-shield-fill-check"></i></div>
            <div><div class="fw-bold text-dark">Admin</div><div class="text-muted small">Kelola semua fitur &amp; pengguna</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon-box stat-icon-green mb-0"><i class="bi bi-wallet2"></i></div>
            <div><div class="fw-bold text-dark">Bendahara</div><div class="text-muted small">Input &amp; edit transaksi</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon-box mb-0" style="background:var(--info-soft,#e0f2fe);color:#0284c7;"><i class="bi bi-eye"></i></div>
            <div><div class="fw-bold text-dark">Ketua</div><div class="text-muted small">Lihat dashboard &amp; laporan saja</div></div>
        </div>
    </div>
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
                    <th style="width: 60px;">No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar_user): ?>
                <tr><td colspan="6">
                    <div class="empty-state-friendly">
                        <div class="empty-doc-icon"><i class="bi bi-people text-primary" style="font-size:2rem;"></i></div>
                        <div class="empty-state-title">Belum Ada Pengguna</div>
                    </div>
                </td></tr>
            <?php endif; ?>

            <?php
            $no = 1;
            foreach ($daftar_user as $u):
                $isSaya  = (strtolower($_SESSION['username'] ?? '') === strtolower($u['username']));
                $inisial = strtoupper(substr($u['nama_lengkap'] ?: $u['username'], 0, 1));
                $r       = $u['role'] ?? 'admin';
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
                    <td><code class="text-primary bg-light px-2 py-1 rounded" style="font-size:0.85rem;">@<?= e($u['username']) ?></code></td>
                    <td>
                        <span class="badge-friendly <?= e($badge_role[$r] ?? 'badge-info') ?>">
                            <span class="badge-dot"></span><?= e($label_role[$r] ?? $r) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isSaya): ?>
                            <span class="badge-friendly badge-masuk"><span class="badge-dot"></span>Sedang Aktif (Anda)</span>
                        <?php elseif ((int)$u['ganti_password'] === 1): ?>
                            <span class="badge-friendly" style="background:#fff8e1;color:#856404;">
                                <span class="badge-dot" style="background:#f59e0b;"></span>Belum ganti PW
                            </span>
                        <?php else: ?>
                            <span class="badge-friendly" style="background:#f0fdf4;color:#166534;">
                                <span class="badge-dot" style="background:#22c55e;"></span>Aktif
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn-icon-action me-1" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#modalEditUser"
                                data-id="<?= (int)$u['id'] ?>"
                                data-nama="<?= e($u['nama_lengkap']) ?>"
                                data-username="<?= e($u['username']) ?>"
                                data-role="<?= e($r) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (!$isSaya): ?>
                            <button type="button" class="btn-icon-action danger" title="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#modalHapusUser"
                                    data-id="<?= (int)$u['id'] ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-nama="<?= e($u['nama_lengkap']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-icon-action" disabled style="opacity:0.35;cursor:not-allowed;" title="Tidak dapat menghapus akun sendiri">
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
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-green mb-0"><i class="bi bi-person-plus"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold">Tambah Pengguna Baru</h5>
                        <p class="text-muted small mb-0">Daftarkan akun petugas dengan role yang sesuai</p>
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
                    <div class="form-text text-muted small">Hanya huruf, angka, garis bawah, atau titik tanpa spasi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="tambahRole">Role / Jabatan</label>
                    <select id="tambahRole" name="role" class="form-select" required>
                        <option value="bendahara">Bendahara (input & edit transaksi)</option>
                        <option value="ketua">Ketua (lihat laporan saja)</option>
                        <option value="admin">Admin (kelola semua fitur)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="tambahPassword">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" id="tambahPassword" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                        <button type="button" class="btn btn-petro-outline btn-toggle-pw" tabindex="-1"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>User wajib mengganti password saat login pertama.</div>
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
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-box stat-icon-blue mb-0"><i class="bi bi-pencil-square"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold">Edit Data Pengguna</h5>
                        <p class="text-muted small mb-0">Perbarui identitas, role, atau ganti password</p>
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
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted" for="editRole">Role / Jabatan</label>
                    <select id="editRole" name="role" class="form-select">
                        <option value="bendahara">Bendahara</option>
                        <option value="ketua">Ketua</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted" for="editPassword">Password Baru (Opsional)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" id="editPassword" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah" minlength="8">
                        <button type="button" class="btn btn-petro-outline btn-toggle-pw" tabindex="-1"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="form-text text-muted small">Hanya diisi bila ingin mengganti password baru (min. 8 karakter).</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn-petro-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-petro-primary px-4">Perbarui Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalHapusUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" id="hapusUserId">
            <div class="modal-body text-center p-4">
                <div class="stat-icon-box mx-auto mb-3" style="background:var(--danger-soft);color:var(--danger);width:54px;height:54px;font-size:1.6rem;">
                    <i class="bi bi-person-x"></i>
                </div>
                <h5 class="fw-bold mb-1">Hapus Pengguna?</h5>
                <p class="text-muted small mb-0">Akun <strong id="hapusUserUsername" class="text-dark"></strong> akan dihapus permanen dari sistem.</p>
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
    document.getElementById('modalEditUser').addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        document.getElementById('editId').value       = b.dataset.id;
        document.getElementById('editNama').value     = b.dataset.nama;
        document.getElementById('editUsername').value = b.dataset.username;
        document.getElementById('editPassword').value = '';
        var roleEl = document.getElementById('editRole');
        roleEl.value = b.dataset.role || 'bendahara';
    });
    document.getElementById('modalHapusUser').addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        document.getElementById('hapusUserId').value    = b.dataset.id;
        document.getElementById('hapusUserUsername').textContent = '@' + b.dataset.username;
    });
    document.querySelectorAll('.btn-toggle-pw').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = this.closest('.input-group').querySelector('input[type="password"], input[type="text"]');
            var icon  = this.querySelector('i');
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
</script>
</body>
</html>