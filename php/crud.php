<?php
// crud.php : halaman master data generik (daftar, cari, tambah, ubah, hapus) dengan tampilan dashboard.
// Dipakai oleh muzakki.php, mustahik.php, program.php, dan users.php. Pengaturannya ada di tiap halaman.
//
// Kunci pengaturan: judul, sub, aktif (menu sidebar), tabel, peran (boleh membuka), ubah (boleh mengubah),
// fields [n=nama kolom, l=label, t=text|textarea|select|password, w=wajib, o=pilihan], cari, kolom, extra, urut, soft, unik, jaga_diri

function halaman_crud(array $c) {
    global $conn;
    wajib_peran($c['peran']);
    $tabel = $c['tabel'];
    $soft  = !empty($c['soft']);
    $bisaUbah = in_array($_SESSION['role'], $c['ubah'], true);
    $self  = basename($_SERVER['SCRIPT_NAME']);

    // ===== Proses simpan dan hapus =====
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $aksi = $_POST['aksi'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        if (!$bisaUbah) {
            flash('danger', 'Anda tidak memiliki izin untuk mengubah data ini.');
        } elseif (!csrf_valid()) {
            flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        } elseif ($aksi === 'hapus') {
            $lama = q_one($conn, "SELECT * FROM $tabel WHERE id = ?", 'i', [$id]);
            if (!$lama) {
                flash('danger', 'Data tidak ditemukan.');
            } elseif (!empty($c['jaga_diri']) && $id === (int)($_SESSION['user_id'] ?? 0)) {
                flash('danger', 'Akun yang sedang Anda pakai tidak bisa dihapus.');
            } else {
                q($conn, $soft ? "UPDATE $tabel SET aktif = 0 WHERE id = ?" : "DELETE FROM $tabel WHERE id = ?", 'i', [$id]);
                unset($lama['password']);
                catat_audit($conn, 'hapus_' . $tabel, $tabel, $id, $lama);
                flash('success', 'Data berhasil dihapus.');
            }
        } elseif (in_array($aksi, ['tambah', 'ubah'], true)) {
            $nilai = []; $galat = '';
            foreach ($c['fields'] as $f) {
                $v = trim($_POST[$f['n']] ?? '');
                if ($f['t'] === 'password') {
                    if ($v === '' && $aksi === 'ubah') continue;       // kosong saat ubah = tidak diganti
                    if (strlen($v) < 8) { $galat = $f['l'] . ' minimal 8 karakter.'; break; }
                    $v = password_hash($v, PASSWORD_DEFAULT);
                } elseif (!empty($f['w']) && $v === '') {
                    $galat = $f['l'] . ' wajib diisi.'; break;
                } elseif ($f['t'] === 'select' && $v !== '' && !isset($f['o'][$v])) {
                    $galat = $f['l'] . ' tidak valid.'; break;
                } elseif (mb_strlen($v) > ($f['t'] === 'textarea' ? 500 : 150)) {
                    $galat = $f['l'] . ' terlalu panjang.'; break;
                }
                $nilai[$f['n']] = $v === '' ? null : $v;
            }
            if (!$galat && !empty($c['unik']) && q_one($conn, "SELECT id FROM $tabel WHERE LOWER({$c['unik']}) = LOWER(?) AND id <> ?", 'si', [$nilai[$c['unik']] ?? '', $id])) {
                $galat = 'Data dengan ' . $c['unik'] . ' tersebut sudah ada.';
            }
            if ($galat) {
                flash('danger', $galat);
            } else {
                $kolom = array_keys($nilai);
                if ($aksi === 'tambah') {
                    q($conn, "INSERT INTO $tabel (" . implode(',', $kolom) . ") VALUES (" . implode(',', array_fill(0, count($kolom), '?')) . ")", str_repeat('s', count($kolom)), array_values($nilai));
                    $id = (int)mysqli_insert_id($conn);
                } else {
                    q($conn, "UPDATE $tabel SET " . implode(',', array_map(fn($k) => "$k = ?", $kolom)) . " WHERE id = ?", str_repeat('s', count($kolom)) . 'i', array_merge(array_values($nilai), [$id]));
                }
                unset($nilai['password']);
                catat_audit($conn, ($aksi === 'tambah' ? 'tambah_' : 'ubah_') . $tabel, $tabel, $id, null, $nilai);
                flash('success', $aksi === 'tambah' ? 'Data baru berhasil disimpan.' : 'Perubahan berhasil disimpan.');
            }
        }
        redirect($self);
    }

    // ===== Data daftar =====
    $cari = trim($_GET['cari'] ?? '');
    $per = 10;
    $where = $soft ? 'aktif = 1' : '1 = 1';
    $types = ''; $params = [];
    if ($cari !== '') {
        $where .= ' AND (' . implode(' OR ', array_map(fn($k) => "$k LIKE ?", $c['cari'])) . ')';
        foreach ($c['cari'] as $_) { $types .= 's'; $params[] = '%' . addcslashes($cari, '%_\\') . '%'; }
    }
    $total = (int)q_one($conn, "SELECT COUNT(*) AS c FROM $tabel WHERE $where", $types, $params)['c'];
    $jmlHal = max(1, (int)ceil($total / $per));
    $hal = min(max(1, (int)($_GET['hal'] ?? 1)), $jmlHal);
    $baris = q_all($conn, "SELECT t.* " . ($c['extra'] ?? '') . " FROM $tabel t WHERE $where ORDER BY " . ($c['urut'] ?? 't.id DESC') . " LIMIT ? OFFSET ?",
        $types . 'ii', array_merge($params, [$per, ($hal - 1) * $per]));

    $title = $c['judul'];
    $aktif = $c['aktif'];
    include __DIR__ . '/app_header.php';
    ?>
<section class="top">
 <div class="head"><h1 class="tag"><?= e($c['judul']) ?></h1><p><?= e($c['sub']) ?></p></div>
 <div class="bar">
  <form class="acts" method="get">
   <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari data" aria-label="Cari" style="margin:0;width:16rem;max-width:100%">
   <button class="btn sm" type="submit">Cari</button>
   <?php if ($cari !== ''): ?><a class="btn o sm" href="<?= e($self) ?>">Reset</a><?php endif; ?>
  </form>
  <?php if ($bisaUbah): ?><button class="btn g sm" type="button" data-dialog="crud-form" data-crud="tambah" data-judul="Tambah data">+ Tambah data</button><?php endif; ?>
 </div>

 <div class="card">
  <h2 class="sub">Daftar data <small><?= $total ?> data</small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>No</th><?php foreach ($c['kolom'] as $k): ?><th<?= ($k[2] ?? '') === 'rupiah' ? ' class="r"' : '' ?>><?= e($k[1]) ?></th><?php endforeach; ?><?php if ($bisaUbah): ?><th class="r">Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php if (!$baris): ?><tr><td colspan="9"><div class="empty"><b>Belum ada data</b><p>Data yang cocok dengan pencarian tidak ditemukan.</p></div></td></tr><?php endif; ?>
    <?php foreach ($baris as $i => $r): ?>
     <tr>
      <td><?= ($hal - 1) * $per + $i + 1 ?></td>
      <?php foreach ($c['kolom'] as $k): $v = $r[$k[0]] ?? ''; $fmt = $k[2] ?? ''; ?>
       <td class="<?= $fmt === 'rupiah' ? 'r nw' : '' ?>">
        <?php if ($fmt === 'rupiah'): ?><b><?= rupiah($v) ?></b>
        <?php elseif ($fmt === 'chip'): ?><i class="cat"><?= e($k[3][$v] ?? $v) ?></i>
        <?php elseif ($k[0] === $c['fields'][0]['n']): ?><b><?= e($v) ?></b>
        <?php else: ?><?= e($v === null || $v === '' ? '-' : $v) ?><?php endif; ?>
       </td>
      <?php endforeach; ?>
      <?php if ($bisaUbah): $baris_json = array_diff_key($r, ['password' => 1]); ?>
      <td class="r nw">
       <button class="lnk" type="button" data-dialog="crud-form" data-crud="ubah" data-judul="Ubah data" data-row="<?= e(json_encode($baris_json)) ?>">Ubah</button>
       <button class="lnk del" type="button" data-dialog="crud-hapus" data-id="<?= (int)$r['id'] ?>" data-nama="<?= e($r[$c['fields'][0]['n']]) ?>">Hapus</button>
      </td>
      <?php endif; ?>
     </tr>
    <?php endforeach; ?>
    </tbody>
   </table>
  </div>
  <?php if ($jmlHal > 1): ?>
  <div class="pg"><small>Halaman <?= $hal ?> dari <?= $jmlHal ?></small>
   <div><?php for ($i = max(1, $hal - 2); $i <= min($jmlHal, $hal + 2); $i++): ?><a class="<?= $i === $hal ? 'on' : '' ?>" href="?<?= e(http_build_query(array_filter(['cari' => $cari, 'hal' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></div>
  </div>
  <?php endif; ?>
 </div>
</section>

<?php if ($bisaUbah): ?>
<dialog class="dlg" id="crud-form" aria-labelledby="crud-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="crud-judul"><span>Tambah data</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="tambah">
  <input type="hidden" name="id" value="0">
  <?php foreach ($c['fields'] as $f): ?>
  <label for="cf-<?= e($f['n']) ?>"><?= e($f['l']) ?><?= !empty($f['w']) ? ' *' : '' ?></label>
   <?php if ($f['t'] === 'textarea'): ?><textarea id="cf-<?= e($f['n']) ?>" name="<?= e($f['n']) ?>" rows="2" data-f></textarea>
   <?php elseif ($f['t'] === 'select'): ?><select id="cf-<?= e($f['n']) ?>" name="<?= e($f['n']) ?>" data-f><?php foreach ($f['o'] as $kv => $lb): ?><option value="<?= e($kv) ?>"><?= e($lb) ?></option><?php endforeach; ?></select>
   <?php elseif ($f['t'] === 'password'): ?><div class="pw"><input id="cf-<?= e($f['n']) ?>" type="password" name="<?= e($f['n']) ?>" minlength="8" autocomplete="new-password" placeholder="Kosongkan bila tidak diganti" data-f><button type="button" data-pw="cf-<?= e($f['n']) ?>" aria-label="Tampilkan password"><svg><use href="#eye"/></svg></button></div>
   <?php else: ?><input id="cf-<?= e($f['n']) ?>" type="text" name="<?= e($f['n']) ?>" maxlength="150" <?= !empty($f['w']) ? 'required' : '' ?> data-f>
   <?php endif; ?>
  <?php endforeach; ?>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Simpan</button></div>
 </form>
</dialog>

<dialog class="dlg" id="crud-hapus" aria-labelledby="ch-judul">
 <form class="dbox" method="post">
  <button class="x" type="button" data-close aria-label="Tutup">×</button>
  <h3 id="ch-judul"><span>Hapus data?</span></h3>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="hapus">
  <input type="hidden" name="id" id="ch-id">
  <p>Data <b id="ch-nama"></b> akan dihapus<?= $soft ? ' dari daftar. Riwayat transaksinya tetap tersimpan.' : '.' ?></p>
  <div class="acts end"><button class="btn o" type="button" data-close>Batal</button><button class="btn" type="submit">Ya, hapus</button></div>
 </form>
</dialog>
<?php endif; ?>

<?php
    include __DIR__ . '/app_footer.php';
}
