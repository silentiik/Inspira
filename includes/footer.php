
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

<div class="modal-overlay" data-confirm-modal hidden>
  <div class="modal-box" role="alertdialog" aria-modal="true">
    <p class="modal-message" data-confirm-message></p>
    <div class="modal-actions">
      <button type="button" class="btn btn--outline btn--sm" data-confirm-cancel>Zrušit</button>
      <button type="button" class="btn btn--danger btn--sm" data-confirm-ok>Potvrdit</button>
    </div>
  </div>
</div>

<div class="modal-overlay" data-menu-change-modal hidden>
  <div class="modal-box" role="alertdialog" aria-modal="true">
    <p class="modal-message">Změnili jste již zadané jídlo</p>
    <div class="modal-actions">
      <button type="button" class="btn btn--outline btn--sm" data-menu-change-cancel>Zrušit</button>
      <button type="button" class="btn btn--primary btn--sm" data-menu-change-text-only>Pouze úprava textu</button>
      <button type="button" class="btn btn--danger btn--sm" data-menu-change-reset>Změna jídla</button>
    </div>
  </div>
</div>

<div class="modal-overlay lightbox-overlay" data-lightbox-modal hidden>
  <button type="button" class="lightbox-close" data-lightbox-close aria-label="Zavřít">✕</button>
  <img class="lightbox-image" data-lightbox-image src="" alt="">
</div>

<script src="<?= htmlspecialchars(asset_url('/assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php foreach ($extraScripts ?? [] as $script): ?>
<script src="<?= htmlspecialchars(asset_url($script), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
</body>
</html>
