</div>

<footer>
 <div class="w">
  <div class="top">
   <a class="logo" href="#"><svg width="26" height="26" fill="#B9FF66"><use href="#st"/></svg><?= e($site['nama']) ?></a>
   <ul>
    <?php foreach ($menu as $id => $label): ?>
    <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
   </ul>
  </div>
  <div class="mid">
   <div>
    <span class="tg">Kontak</span>
    <p>Email: <?= e($site['email']) ?></p>
    <p>Telepon: <?= e($site['telp']) ?></p>
    <p>Alamat: <?= e($site['alamat']) ?></p>
   </div>
   <div class="nl">
    <input type="email" placeholder="Email" aria-label="Email">
    <button class="btn g" type="button">Berlangganan info</button>
   </div>
  </div>
  <div class="cp">© <?= date('Y') ?> <?= e($site['lengkap']) ?>. Hak cipta dilindungi.</div>
 </div>
</footer>
</div></div>

<script src="js/main.js"></script>
</body>
</html>
