
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4>INSPIRA</h4>
        <p>Vzdělávací centrum v Mladé Boleslavi. Podporujeme růst dítěte vlastním tempem.</p>
        <div class="footer-social">
          <a href="https://www.facebook.com/centruminspira" aria-label="Facebook">FB</a>
          <a href="https://www.instagram.com/centruminspira" aria-label="Instagram">IG</a>
        </div>
      </div>
      <div>
        <h4>Rychlé odkazy</h4>
        <ul class="footer-links">
          <li><a href="/sluzby/inspirka.php">Dětský klub INSPIRKA</a></li>
          <li><a href="/sluzby/domskolacka-akademie.php">Domškolácká akademie</a></li>
          <li><a href="/rozvrh.php">Rozvrh</a></li>
          <li><a href="/cenik.php">Ceník</a></li>
        </ul>
      </div>
      <div>
        <h4>Kontakt</h4>
        <ul class="footer-links">
          <li>Českobratrské náměstí 133, Mladá Boleslav</li>
          <li><a href="mailto:centruminspira@gmail.com">centruminspira@gmail.com</a><br>
          <a href="tel:+420732728012">732 728 012</a></li>
          <li><a href="/kontakt.php">Kontaktní formulář →</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 Vzdělávací centrum INSPIRA</span>
      <span>Web inspirovaný centruminspira.cz</span>
    </div>
  </div>
</footer>

<button class="back-to-top" aria-label="Zpět nahoru">↑</button>
<script src="/assets/js/main.js"></script>
<?php foreach ($extraScripts ?? [] as $script): ?>
<script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
</body>
</html>
