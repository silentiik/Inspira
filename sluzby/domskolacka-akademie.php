<?php
$pageTitle = 'Domškolácká akademie | INSPIRA';
$pageDescription = 'Domškolácká akademie — pravidelné vzdělávání domškoláků 6-15 let v Mladé Boleslavi, po až st od 9 do 14 hodin.';
$activeNav = 'sluzby';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Domškolácká akademie — prostor pro smysluplné vzdělávání</h1>
      <p class="lead">Pravidelné vzdělávání domškoláků, od 9 do 14 hodin, po–st, v Mladé Boleslavi.</p>
    </div>
  </section>

  <section class="section">
    <div class="container two-col">
      <div>
        <span class="section-eyebrow">Proč akademie vznikla</span>
        <h2>Vzdělávání může být hravé, respektující, a zároveň kvalitní</h2>
        <p>Domškolácká akademie je součástí vzdělávacího centra INSPIRA a vznikla jako odpověď na potřebu smysluplného a podpůrného vzdělávání pro děti na domácí výuce.</p>
        <p>Nabízíme prostor, kde se děti učí v malých skupinkách, v klidném tempu, ale s důrazem na rozvoj znalostí, dovedností a samostatnosti.</p>
        <div class="tag-list">
          <span class="tag">6–15 let</span>
          <span class="tag">1. i 2. stupeň ZŠ</span>
          <span class="tag">Po–St, 9:00–14:00</span>
          <span class="tag">2–3 dny/týden možné</span>
        </div>
      </div>
      <svg class="illustration" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrace domškolácké akademie">
        <rect width="400" height="300" fill="#fcefee"/>
        <rect x="60" y="70" width="130" height="170" rx="14" fill="#ffffff"/>
        <rect x="210" y="70" width="130" height="170" rx="14" fill="#ffffff"/>
        <rect x="80" y="95" width="90" height="10" rx="5" fill="#d88a95"/>
        <rect x="80" y="120" width="70" height="10" rx="5" fill="#f2b872"/>
        <rect x="230" y="95" width="90" height="10" rx="5" fill="#f2b872"/>
        <rect x="230" y="120" width="70" height="10" rx="5" fill="#d88a95"/>
      </svg>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Jak vzdělávání probíhá</span>
        <h2>Malé skupinky, klidné tempo, jasný cíl</h2>
      </div>
      <div class="card-grid">
        <div class="card">
          <span class="card-icon">👥</span>
          <h3>Malé skupinky</h3>
          <p>Děti se učí ve skupinkách, kde na ně máme dostatek času a prostoru.</p>
        </div>
        <div class="card">
          <span class="card-icon">🧠</span>
          <h3>Moderní pedagogické metody</h3>
          <p>Propojujeme moderní pedagogické metody s individuální podporou a hravým přístupem.</p>
        </div>
        <div class="card">
          <span class="card-icon">📅</span>
          <h3>Flexibilní docházka</h3>
          <p>Možnost zvolit docházku 2–3 dny týdně podle potřeb rodiny.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="banner">
        <span class="badge-pill">Přijímáme přihlášky na září 2026</span>
        <h2>Domluvte si s námi schůzku</h2>
        <p class="lead">Přijďte se podívat, jak Domškolácká akademie funguje, a zeptejte se na cokoliv, co vás zajímá.</p>
        <div class="banner-actions">
          <a href="/kontakt.php#prihlaska-domskolaci" class="btn btn--accent">Podat přihlášku</a>
          <a href="/rozvrh.php" class="btn btn--outline">Zobrazit rozvrh</a>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
