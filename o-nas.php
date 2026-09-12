<?php
$pageTitle = 'O nás | INSPIRA';
$pageDescription = 'O vzdělávacím centru INSPIRA — náš tým, hodnoty a přístup ke vzdělávání dětí v Mladé Boleslavi.';
$activeNav = 'o-nas';
require_once __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>O nás</h1>
      <p class="lead">Jsme tým, který věří, že vzdělávání může být hravé, respektující a zároveň kvalitní.</p>
    </div>
  </section>

  <section class="section">
    <div class="container two-col">
      <div>
        <span class="section-eyebrow">Náš příběh</span>
        <h2>Vzdělávací centrum INSPIRA</h2>
        <p>INSPIRA vznikla z touhy vytvořit v Mladé Boleslavi místo, kde se děti mohou vzdělávat bez zbytečného spěchu a srovnávání — v prostředí, které respektuje jejich individualitu a přirozené tempo.</p>
        <p>Propojujeme prvky moderní pedagogiky s alternativními směry a klademe důraz na učení hrou. Podněcujeme děti k samostatnému myšlení, kreativitě a odvaze zkoušet nové věci.</p>
      </div>
      <svg class="illustration" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrace týmu Inspira">
        <rect width="400" height="300" fill="#f7dee1"/>
        <circle cx="140" cy="160" r="44" fill="#ffffff"/>
        <circle cx="230" cy="140" r="52" fill="#ffffff"/>
        <circle cx="140" cy="160" r="16" fill="#d88a95"/>
        <circle cx="230" cy="140" r="20" fill="#f2b872"/>
        <path d="M70 240 q150 -20 280 10" stroke="#b96877" stroke-width="6" fill="none" stroke-linecap="round"/>
      </svg>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Naše hodnoty</span>
        <h2>Na čem nám záleží</h2>
      </div>
      <div class="value-grid">
        <div class="value-item">
          <span class="card-icon">🌱</span>
          <div><h3>Individualita</h3><p>Respektujeme jedinečnost každého dítěte a dáváme mu prostor být sám sebou.</p></div>
        </div>
        <div class="value-item">
          <span class="card-icon">🧩</span>
          <div><h3>Celostní rozvoj</h3><p>Zaměřujeme se na rozvoj všech stránek dětské osobnosti, nejen na znalosti.</p></div>
        </div>
        <div class="value-item">
          <span class="card-icon">💪</span>
          <div><h3>Sebedůvěra</h3><p>Pomáháme dětem budovat sebedůvěru a zvládat výzvy s adaptací na nové situace.</p></div>
        </div>
        <div class="value-item">
          <span class="card-icon">✨</span>
          <div><h3>Vzájemná inspirace</h3><p>Inspirujeme děti a zároveň se necháváme inspirovat jejich pohledem na svět.</p></div>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container text-center">
      <span class="section-eyebrow">Kde nás najdete</span>
      <h2>Českobratrské náměstí 133, Mladá Boleslav</h2>
      <p class="lead">Přijďte se k nám podívat osobně — rádi vám ukážeme, jak INSPIRA funguje.</p>
      <a href="/kontakt.php" class="btn btn--primary">Domluvit návštěvu</a>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
