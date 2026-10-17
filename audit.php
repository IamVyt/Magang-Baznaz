<?php
require __DIR__ . '/php/fungsi.php';
wajib_peran(['admin']);

$cari = trim($_GET['cari'] ?? '');
$aksi = trim($_GET['aksi'] ?? '');
$per  = 15;

$where = '1 = 1'; $types = ''; $params = [];
if ($cari !== '') {
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    $where .= ' AND (username LIKE ? OR tabel LIKE ? OR ip LIKE ?)'; $types .= 'sss'; array_push($params, $like, $like, $like);
}
if ($aksi !== '') { $where .= ' AND aksi = ?'; $types .= 's'; $params[] = $aksi; }

$daftarAksi = array_column(q_all($conn, "SELECT DISTINCT aksi FROM audit_log ORDER BY aksi"), 'aksi');
$total  = (int)q_one($conn, "SELECT COUNT(*) AS c FROM audit_log WHERE $where", $types, $params)['c'];
$jmlHal = max(1, (int)ceil($total / $per));
$hal    = min(max(1, (int)($_GET['hal'] ?? 1)), $jmlHal);
$baris  = q_all($conn, "SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$per, ($hal - 1) * $per]));
$qs = array_filter(['cari' => $cari, 'aksi' => $aksi]);

$title = 'Riwayat aktivitas';
$aktif = 'audit';
include __DIR__ . '/php/app_header.php';
?>
<section class="top">
 <div class="head"><h1 class="tag">Riwayat aktivitas</h1><p>Catatan login, perubahan transaksi, dan perubahan data oleh seluruh pengguna.</p></div>

 <form class="card fbox audit" method="get">
  <div><label for="cari">Cari pengguna, tabel, atau IP</label><input id="cari" type="text" name="cari" value="<?= e($cari) ?>"></div>
  <div><label for="aksi">Jenis aktivitas</label>
   <select id="aksi" name="aksi"><option value="">Semua aktivitas</option>
    <?php foreach ($daftarAksi as $a): ?><option value="<?= e($a) ?>" <?= $aksi === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="acts"><button class="btn sm" type="submit">Terapkan</button><a class="btn o sm" href="audit.php">Reset</a></div>
 </form>

 <div class="card">
  <h2 class="sub">Log aktivitas <small><?= $total ?> catatan</small></h2>
  <div class="scroll">
   <table class="tbl">
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$baris): ?><tr><td colspan="6"><div class="empty"><b>Belum ada catatan</b><p>Aktivitas akan muncul di sini setelah ada yang login atau mengubah data.</p></div></td></tr><?php endif; ?>
    <?php foreach ($baris as $r): $detail = $r['data_baru'] ?: $r['data_lama']; ?>
     <tr>
      <td class="nw"><?= date('d/m/Y H:i', strtotime($r['waktu'])) ?></td>
      <td><b><?= e($r['username'] ?? '-') ?></b></td>
      <td><i class="cat"><?= e($r['aksi']) ?></i></td>
      <td class="nw"><?= $r['tabel'] ? e($r['tabel']) . ' #' . (int)$r['record_id'] : '-' ?></td>
      <td><span class="meta"><small title="<?= e($detail) ?>"><?= e($detail ?: '-') ?></small></span></td>
      <td class="nw"><?= e($r['ip']) ?></td>
     </tr>
    <?php endforeach; ?>
    </tbody>
   </table>
  </div>
  <?php if ($jmlHal > 1): ?>
  <div class="pg"><small>Halaman <?= $hal ?> dari <?= $jmlHal ?></small>
   <div><?php for ($i = max(1, $hal - 2); $i <= min($jmlHal, $hal + 2); $i++): ?><a class="<?= $i === $hal ? 'on' : '' ?>" href="?<?= e(http_build_query($qs + ['hal' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div>
  </div>
  <?php endif; ?>
 </div>
</section>
<?php include __DIR__ . '/php/app_footer.php'; ?>
