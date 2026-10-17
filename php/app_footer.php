<?php if ($sidebar): ?>
 </main>
</div>
<?php else: ?>
</div>

<!-- Pengganti footer gelap: deretan mitra. Ganti teks dengan logo resmi (taruh di folder img) bila sudah ada -->
<div class="w">
 <div class="mitra">
  <small>Didukung oleh</small>
  <div class="logos"><span>BAZNAS</span><span>Petrokimia Gresik</span><span>Pupuk Indonesia</span></div>
  <p>© <?= date('Y') ?> UPZ BAZNAS Petrokimia Gresik. Amanah, transparan, dan akuntabel.</p>
 </div>
</div>
<?php endif; ?>
</div></div>

<?php foreach (($scripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<script src="js/main.js"></script>
<script src="js/app.js"></script>
</body>
</html>
