<?php
// Langkah 2 penyaluran donasi: isi data, nominal, metode pembayaran, dan bank atau e-wallet tujuan
require __DIR__ . '/php/fungsi.php';
require __DIR__ . '/php/data.php';

$slug = $_GET['program'] ?? $_POST['program'] ?? '';
$slug = is_string($slug) ? $slug : '';
$idx  = filter_var($_GET['paket'] ?? $_POST['paket'] ?? null, FILTER_VALIDATE_INT);
$prog = cari_program($program, $slug);
$info = $penyaluran[$slug] ?? null;
if (!$prog || !$info || $idx === false || !isset($info['paket'][$idx])) {
    flash('danger', 'Pilih program dan paket terlebih dahulu.');
    redirect('salurkan.php');
}

// Donasi hanya untuk pengguna yang sudah masuk. Pilihan paket disimpan supaya
// pengguna kembali ke halaman ini setelah login atau mendaftar
if (($_SESSION['status'] ?? '') !== 'login') {
    simpan_tujuan('bayar.php?program=' . $slug . '&paket=' . $idx);
    flash('info', 'Masuk atau daftar dulu untuk menyalurkan donasi. Pilihan paket Anda tetap tersimpan.');
    redirect('login.php');
}

[$namaPaket, $nominalTetap, $deskPaket] = $info['paket'][$idx];
$opsiPaket = $info['paket'][$idx][3] ?? '';
$bebas     = $nominalTetap <= 0;
$minimal   = (int)$batasDonasi['min'];
$maksimal  = (int)$batasDonasi['maks'];
siapkan_donasi($conn);

$ada     = metode_tersedia($conn);
$bank    = channel_aktif($conn, 'bank');
$ewallet = channel_aktif($conn, 'ewallet');

$isi = [
    'nama' => (string)($_SESSION['nama'] ?? ''), 'kontak' => '', 'email' => '', 'nip' => '',
    'jumlah' => '', 'metode' => '', 'catatan' => '', 'samarkan' => '',
];
$pilihChannel = '';
$galat = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach (['nama', 'kontak', 'email', 'nip', 'jumlah', 'metode', 'catatan'] as $k) {
        $v = $_POST[$k] ?? '';
        $isi[$k] = is_string($v) ? trim($v) : '';
    }
    $isi['samarkan'] = isset($_POST['samarkan']) ? '1' : '';
    $c = $_POST['channel_' . $isi['metode']] ?? '';
    $pilihChannel = is_string($c) ? $c : '';

    // Nominal paket tetap diambil dari server, bukan dari kiriman pengguna
    $jumlah = $bebas ? validasi_jumlah($isi['jumlah']) : (int)$nominalTetap;

    if (!csrf_valid()) {
        $galat[] = 'Sesi formulir kedaluwarsa. Silakan ulangi.';
    } else {
        if (mb_strlen($isi['nama']) < 2 || mb_strlen($isi['nama']) > 150) $galat[] = 'Nama lengkap harus 2 sampai 150 karakter.';
        if (!preg_match('/^\+?[0-9][0-9\s\-]{6,18}[0-9]$/', $isi['kontak'])) $galat[] = 'Nomor WhatsApp atau telepon tidak valid. Contoh: 081234567890.';
        if ($isi['email'] !== '' && (mb_strlen($isi['email']) > 100 || !filter_var($isi['email'], FILTER_VALIDATE_EMAIL))) $galat[] = 'Format email tidak valid.';
        if ($isi['nip'] !== '' && !preg_match('/^[A-Za-z0-9.\-\/ ]{1,30}$/', $isi['nip'])) $galat[] = 'NIP atau ID karyawan hanya boleh berisi huruf, angka, titik, garis miring, dan strip (maksimal 30 karakter).';
        if ($bebas && ($jumlah === false || $jumlah < $minimal || $jumlah > $maksimal)) $galat[] = 'Nominal minimal ' . rupiah($minimal) . ' dan maksimal ' . rupiah($maksimal) . '.';
        [$galatBayar, $kanal, $kanalNama, $kanalInfo] = pilih_pembayaran($conn, $metode, $isi['metode'], $pilihChannel);
        if ($galatBayar) $galat[] = $galatBayar;
        if (mb_strlen($isi['catatan']) > 500) $galat[] = 'Catatan maksimal 500 karakter.';
    }

    if (!$galat) {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // Klik ganda atau muat ulang: pakai donasi yang baru saja dibuat, jangan buat ganda
        $kembar = q_one($conn, "SELECT kode FROM donasi WHERE user_id = ? AND program = ? AND paket = ? AND jumlah = ? AND metode = ?
                                AND dibuat_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1",
            'issds', [$userId, $slug, $namaPaket, $jumlah, $isi['metode']]);
        if ($kembar) redirect('donasi_sukses.php?kode=' . urlencode($kembar['kode']) . '&baru=1');

        // Batasi antrean agar daftar pengecekan admin tidak dibanjiri
        $antre = (int)(q_one($conn, "SELECT COUNT(*) AS n FROM donasi WHERE user_id = ? AND status IN ('menunggu', 'dibayar')", 'i', [$userId])['n'] ?? 0);
        if ($antre >= 10) {
            $galat[] = 'Anda masih memiliki 10 donasi yang belum selesai. Selesaikan atau batalkan salah satunya terlebih dahulu.';
        } else {
            $kode = null;
            $idBaru = 0;
            for ($coba = 0; $coba < 5 && $kode === null; $coba++) {
                $calon = 'UPZ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                try {
                    q($conn, "INSERT INTO donasi (kode, user_id, program, program_nama, paket, kategori_kode, nama, samarkan_nama, nip, kontak, email, jumlah, metode, channel, channel_nama, channel_info, catatan)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'sisssssisssdsssss', [
                            $calon, $userId ?: null, $slug, $prog['nama'], $namaPaket, $info['kategori'], $isi['nama'],
                            $isi['samarkan'] === '1' ? 1 : 0, $isi['nip'] !== '' ? $isi['nip'] : null, $isi['kontak'],
                            $isi['email'] !== '' ? $isi['email'] : null, $jumlah, $isi['metode'], $kanal, $kanalNama, $kanalInfo,
                            $isi['catatan'] !== '' ? $isi['catatan'] : null,
                        ]);
                    $kode   = $calon;
                    $idBaru = (int)mysqli_insert_id($conn);
                } catch (mysqli_sql_exception $ex) {
                    if ((int)$ex->getCode() !== 1062) throw $ex;   // 1062 = kode kebetulan kembar, coba kode lain
                }
            }
            if ($kode === null) {
                $galat[] = 'Donasi belum tersimpan. Silakan coba lagi.';
            } else {
                catat_audit($conn, 'donasi_kirim', 'donasi', $idBaru, null, [
                    'kode' => $kode, 'program' => $prog['nama'], 'paket' => $namaPaket, 'jumlah' => $jumlah,
                    'metode' => $isi['metode'], 'channel' => $kanal,
                ]);
                redirect('donasi_sukses.php?kode=' . urlencode($kode) . '&baru=1');
            }
        }
    }
}

$pilihMetode = $isi['metode'];
$title  = 'Isi data donasi';
$publik = true;
include __DIR__ . '/php/app_header.php';
?>

<section class="top">
 <div class="head"><h1 class="tag">Isi data</h1><p>Lengkapi data berikut. Setelah dikirim, Anda akan mendapat nomor referensi dan petunjuk pembayaran. Metode pembayaran masih bisa diganti sebelum Anda membayar.</p></div>
 <ol class="steps" aria-label="Langkah penyaluran">
  <li class="done"><b>✓</b>Pilih paket</li>
  <li class="on"><b>2</b>Isi data</li>
  <li><b>3</b>Bayar</li>
 </ol>
</section>

<section class="bayar">
 <div class="c g ringkas">
  <small>Paket pilihan Anda</small>
  <h2><?= e($prog['nama']) ?></h2>
  <p class="pk"><?= e($namaPaket) ?></p>
  <b class="besar"><?= $bebas ? 'Nominal bebas' : rupiah($nominalTetap) ?></b>
  <p><?= e($deskPaket) ?></p>
  <a class="ul" href="salurkan.php?program=<?= e($slug) ?>">Ganti paket</a>
 </div>

 <form class="c l form" method="post" autocomplete="off" data-sekali>
  <h2><span class="tg">Data donasi</span></h2>
  <?php if ($galat): ?><div class="alert danger" role="alert"><?= e(implode(' ', $galat)) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="program" value="<?= e($slug) ?>">
  <input type="hidden" name="paket" value="<?= (int)$idx ?>">

  <label for="nama">Nama lengkap</label>
  <input id="nama" type="text" name="nama" value="<?= e($isi['nama']) ?>" maxlength="150" required>

  <div class="kolom2">
   <div>
    <label for="kontak">WhatsApp atau telepon</label>
    <input id="kontak" type="tel" name="kontak" value="<?= e($isi['kontak']) ?>" inputmode="tel" maxlength="20" placeholder="081234567890" required>
   </div>
   <div>
    <label for="nip">NIP atau ID karyawan (opsional)</label>
    <input id="nip" type="text" name="nip" value="<?= e($isi['nip']) ?>" maxlength="30">
   </div>
  </div>

  <label for="email">Email (opsional)</label>
  <input id="email" type="email" name="email" value="<?= e($isi['email']) ?>" maxlength="100">

  <label class="cek"><input type="checkbox" name="samarkan" value="1" <?= $isi['samarkan'] === '1' ? 'checked' : '' ?>><span>Tampilkan nama saya sebagai Hamba Allah di pembukuan kas. Pengurus tetap melihat data asli untuk verifikasi.</span></label>

  <?php if ($bebas): ?>
   <?php if ($opsiPaket === 'hitung'): ?>
   <label for="gaji">Penghasilan yang ingin dihitung zakatnya (Rp, opsional)</label>
   <div class="hitung">
    <input id="gaji" type="number" min="0" step="1" inputmode="numeric" placeholder="Contoh: 8000000">
    <button class="btn o" type="button" data-hitung="2.5" data-sumber="gaji" data-target="jumlah">Hitung 2,5%</button>
   </div>
   <small class="bantu">Zakat penghasilan umumnya 2,5 persen dari penghasilan yang sudah mencapai nisab. Cek besaran nisab terbaru di BAZNAS sebelum menghitung.</small>
   <?php endif; ?>
   <label for="jumlah">Nominal (Rp)</label>
   <input id="jumlah" type="number" name="jumlah" min="<?= $minimal ?>" max="<?= $maksimal ?>" step="1" inputmode="numeric" value="<?= e($isi['jumlah']) ?>" placeholder="Minimal <?= number_format($minimal, 0, ',', '.') ?>" required>
   <div class="chips" aria-label="Pilihan nominal cepat">
    <?php foreach ([25000, 50000, 100000, 250000, 500000] as $n): ?>
    <button class="pill" type="button" data-isi="<?= $n ?>" data-target="jumlah"><?= rupiah($n) ?></button>
    <?php endforeach; ?>
   </div>
  <?php else: ?>
   <div class="nominal-tetap"><span>Nominal paket</span><b><?= rupiah($nominalTetap) ?></b></div>
  <?php endif; ?>

  <?php include __DIR__ . '/php/pilih_metode.php'; ?>

  <label for="catatan">Catatan (opsional)</label>
  <textarea id="catatan" name="catatan" rows="3" maxlength="500" placeholder="Mis. niat donasi atau pesan untuk pengurus"><?= e($isi['catatan']) ?></textarea>

  <button class="btn" type="submit">Kirim donasi</button>
  <small class="bantu">Donasi tercatat di kas UPZ setelah pembayaran Anda dicek dan dikonfirmasi pengurus.</small>
 </form>
</section>

<?php include __DIR__ . '/php/app_footer.php'; ?>
