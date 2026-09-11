<?php
$pageTitle = 'Volnočasové aktivity | INSPIRA';
$pageDescription = 'Volnočasové aktivity INSPIRA — kroužky, workshopy a besedy pro děti i dospělé v Mladé Boleslavi.';
$activeNav = 'sluzby';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/index.php">Domů</a> / Služby / Volnočasové aktivity</p>
      <h1>Volnočasové aktivity</h1>
      <p class="lead">Tematické workshopy a kroužky, besedy a přednášky pro děti i dospělé různých věkových skupin.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Naše kroužky</span>
        <h2>Výtvarné, pohybové, jazykové a tematické kroužky</h2>
      </div>
      <div class="card-grid">
        <div class="card">
          <span class="card-icon">🇬🇧</span>
          <h3>Hravá angličtina s Miškou</h3>
          <p>Angličtina formou písniček, her a obrázků pro nejmenší i školáky.</p>
        </div>
        <div class="card">
          <span class="card-icon">🎶</span>
          <h3>Muzikohrátky</h3>
          <p>Hudební kroužek plný zpěvu, rytmu a jednoduchých nástrojů.</p>
        </div>
        <div class="card">
          <span class="card-icon">💃</span>
          <h3>Tanečky</h3>
          <p>Pohybový kroužek rozvíjející rytmus, koordinaci a radost z pohybu.</p>
        </div>
        <div class="card">
          <span class="card-icon">🌳</span>
          <h3>Rodinný den v přírodě</h3>
          <p>Poznáním k radosti — 24. září 2026, 15–18 hodin, zdarma pro celou rodinu.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="banner">
        <span class="badge-pill">Zdarma</span>
        <h2>Rodinný den v přírodě — poznáním k radosti</h2>
        <p class="lead">24. září 2026, 15:00–18:00, Mladá Boleslav. Akce je zdarma a otevřená pro celou rodinu.</p>
        <div class="banner-actions">
          <a href="/kontakt.php" class="btn btn--accent">Přihlásit se na akci</a>
          <a href="/rozvrh.php" class="btn btn--outline">Zobrazit rozvrh kroužků</a>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
