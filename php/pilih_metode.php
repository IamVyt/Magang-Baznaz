<?php
// Pemilih metode pembayaran beserta pilihan bank atau e-wallet. Dipakai di bayar.php dan donasi_sukses.php.
// Variabel yang dibutuhkan: $metode, $ada (metode_tersedia), $bank, $ewallet, $pilihMetode, $pilihChannel
?>
<fieldset class="metode">
 <legend>Metode pembayaran</legend>
 <?php foreach ($metode as $km => $m): $siap = !empty($ada[$km]); ?>
 <label class="opsi <?= $siap ? '' : 'mati' ?>">
  <input type="radio" name="metode" value="<?= e($km) ?>" <?= $siap ? '' : 'disabled' ?> <?= $pilihMetode === $km ? 'checked' : '' ?> required>
  <span><b><?= e($m[0]) ?></b><small><?= $siap ? e($m[1]) : 'Belum tersedia. Pengurus belum mengaktifkan metode ini.' ?></small></span>
 </label>
 <?php if ($siap && isset(JENIS_CHANNEL[$km])): $daftarChannel = JENIS_CHANNEL[$km] === 'bank' ? $bank : $ewallet; ?>
 <div class="channel" data-untuk="<?= e($km) ?>" <?= $pilihMetode === $km ? '' : 'hidden' ?>>
  <small class="bantu"><?= JENIS_CHANNEL[$km] === 'bank' ? 'Pilih bank tujuan:' : 'Pilih e-wallet tujuan:' ?></small>
  <div class="pilih-bank">
   <?php foreach ($daftarChannel as $kc => $c): ?>
   <label class="bank"><input type="radio" name="channel_<?= e($km) ?>" value="<?= e($kc) ?>" <?= ($pilihMetode === $km && $pilihChannel === $kc) ? 'checked' : '' ?>><span><?= e($c['nama']) ?></span></label>
   <?php endforeach; ?>
  </div>
 </div>
 <?php endif; ?>
 <?php endforeach; ?>
</fieldset>
