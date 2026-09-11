<?php
require_once __DIR__ . '/app/content.php';

$pageTitle = 'INSPIRA — Vzdělávací centrum Mladá Boleslav';
$pageDescription = 'Vzdělávací centrum INSPIRA v Mladé Boleslavi — dětský klub INSPIRKA, Domškolácká akademie a volnočasové aktivity pro děti od 3 do 15 let.';
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>

  <section class="hero">
    <div class="container hero-grid">
      <div>
        <span class="section-eyebrow">Mladá Boleslav · Českobratrské náměstí 133</span>
        <h1><?= nl2br(htmlspecialchars(get_content('home.hero.title', "Podporujeme růst dítěte\nvlastním tempem"), ENT_QUOTES, 'UTF-8')) ?></h1>
        <p class="lead"><?= content_html('home.hero.lead', 'Propojujeme moderní pedagogiku s respektujícím přístupem. Rozvíjíme dovednosti, kreativitu i samostatné myšlení — a necháváme se přitom inspirovat pohledem dětí na svět.') ?></p>

        <ul class="hero-quicklinks">
          <li><a href="/sluzby/inspirka.php">Dětský klub INSPIRKA (3–6 let)</a></li>
          <li><a href="/sluzby/domskolacka-akademie.php">Domškolácká akademie (6–15 let)</a></li>
          <li><a href="/sluzby/volnocasove-aktivity.php">Volnočasové aktivity</a></li>
        </ul>

        <div class="hero-actions">
          <a href="/kontakt.php#prihlaska" class="btn btn--primary">Podat přihlášku</a>
          <a href="/cenik.php" class="btn btn--outline">Spočítat cenu</a>
        </div>

        <div class="hero-meta">
          <span class="hero-meta-item">📍 Českobratrské náměstí 133, Mladá Boleslav</span>
          <span class="hero-meta-item"><?= content_html('home.event.text', '🎉 Rodinný den v přírodě — 24. 9. 2026, 15–18 h, zdarma') ?></span>
        </div>
      </div>

      <div>
        <svg class="hero-illustration illustration" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrace dětí při hře">
          <rect width="400" height="300" fill="#f7dee1"/>
          <circle cx="320" cy="60" r="46" fill="#f2b872" opacity="0.7"/>
          <circle cx="60" cy="250" r="60" fill="#d88a95" opacity="0.5"/>
          <rect x="90" y="120" width="220" height="130" rx="18" fill="#ffffff"/>
          <circle cx="150" cy="185" r="26" fill="#d88a95"/>
          <circle cx="220" cy="185" r="26" fill="#f2b872"/>
          <circle cx="255" cy="150" r="18" fill="#b96877"/>
          <path d="M110 250 q90 -40 180 0" stroke="#d88a95" stroke-width="6" fill="none" stroke-linecap="round"/>
          <text x="200" y="90" text-anchor="middle" font-family="Quicksand, sans-serif" font-size="22" font-weight="700" fill="#b96877">INSPIRA</text>
        </svg>
      </div>
    </div>
  </section>

  <section class="section" id="o-inspire">
    <div class="container two-col">
      <div>
        <span class="section-eyebrow">Co je INSPIRA?</span>
        <h2>Jsme vzdělávací centrum, které učí hrou</h2>
        <p>Propojujeme prvky moderní pedagogiky s alternativními směry. Důležité je pro nás učení prostřednictvím her — podněcujeme děti k samostatnému myšlení a kreativitě.</p>
        <div class="tag-list">
          <span class="tag">Respektující přístup</span>
          <span class="tag">Učení hrou</span>
          <span class="tag">Individuální tempo</span>
          <span class="tag">Pobyt venku denně</span>
        </div>
      </div>
      <svg class="illustration" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrace učení hrou">
        <rect width="400" height="300" fill="#fcefee"/>
        <rect x="40" y="60" width="320" height="180" rx="24" fill="#ffffff"/>
        <circle cx="120" cy="150" r="40" fill="#f2b872" opacity="0.85"/>
        <rect x="200" y="110" width="120" height="80" rx="14" fill="#d88a95" opacity="0.85"/>
        <path d="M60 240 q150 30 300 -10" stroke="#f2b872" stroke-width="6" fill="none" stroke-linecap="round"/>
      </svg>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Naše motto</span>
        <h2>Přátelské a bezpečné prostředí pro objevování</h2>
        <p class="lead">Naším cílem je vytvořit prostor, kde děti mohou tvořit, rozvíjet své dovednosti a objevovat nové možnosti vzdělávání s nadšením.</p>
      </div>
      <div class="value-grid">
        <div class="value-item">
          <span class="card-icon">🌱</span>
          <div>
            <h3>Respektujeme individualitu</h3>
            <p>Každé dítě má prostor být samo sebou a růst svým vlastním tempem.</p>
          </div>
        </div>
        <div class="value-item">
          <span class="card-icon">🧩</span>
          <div>
            <h3>Rozvíjíme všechny stránky osobnosti</h3>
            <p>Nejen znalosti, ale i dovednosti, které děti potřebují v běžném životě.</p>
          </div>
        </div>
        <div class="value-item">
          <span class="card-icon">💪</span>
          <div>
            <h3>Budujeme sebedůvěru</h3>
            <p>Podporujeme děti v jejich pokroku, aby zvládaly výzvy a adaptaci na nové situace.</p>
          </div>
        </div>
        <div class="value-item">
          <span class="card-icon">✨</span>
          <div>
            <h3>Necháváme se inspirovat</h3>
            <p>Inspirujeme děti a zároveň se necháváme inspirovat jejich pohledem na svět.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="sluzby">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Co nabízíme</span>
        <h2>Tři cesty, jak může vaše dítě růst s Inspirou</h2>
      </div>
      <div class="card-grid">
        <article class="card">
          <span class="card-icon">🧸</span>
          <h3>INSPIRKA — dětský klub</h3>
          <p>Laskavý prostor pro děti od 3 let. Každý den od 8 do 16 hodin, hravé vzdělávání s individuálním přístupem a denními venkovními aktivitami.</p>
          <a href="/sluzby/inspirka.php" class="btn btn--outline btn--sm">Více informací →</a>
        </article>
        <article class="card">
          <span class="card-icon">📚</span>
          <h3>Domškolácká akademie</h3>
          <p>Pravidelné vzdělávání domškoláků (1. i 2. stupeň ZŠ), po–st od 9 do 14 hodin, možnost docházky 2–3 dny týdně.</p>
          <a href="/sluzby/domskolacka-akademie.php" class="btn btn--outline btn--sm">Více informací →</a>
        </article>
        <article class="card">
          <span class="card-icon">🎨</span>
          <h3>Volnočasové aktivity</h3>
          <p>Tematické workshopy a kroužky — Hravá angličtina s Miškou, Muzikohrátky, Tanečky a Rodinný den v přírodě.</p>
          <a href="/sluzby/volnocasove-aktivity.php" class="btn btn--outline btn--sm">Více informací →</a>
        </article>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="banner">
        <span class="badge-pill">POSLEDNÍ VOLNÁ MÍSTA</span>
        <h2><?= htmlspecialchars(get_content('home.banner.title', 'Aktuálně přijímáme přihlášky na září 2026'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="lead"><?= content_html('home.banner.lead', 'Právě probíhá přihlašování na školní rok 2026/2027. Domluvte si s námi schůzku, přijďte se podívat, jak to u nás funguje, a zeptejte se na cokoliv, co vás zajímá.') ?></p>
        <p>Nabízíme půlden nebo celý den <strong>ZDARMA</strong> na zkoušku. V případě zájmu se můžeme domluvit i na pozvolné adaptaci.</p>
        <div class="banner-actions">
          <a href="/kontakt.php#prihlaska-inspirka" class="btn btn--accent">Přihláška INSPIRKA</a>
          <a href="/kontakt.php#prihlaska-domskolaci" class="btn btn--accent">Přihláška domškoláci</a>
          <a href="/kontakt.php#schuzka" class="btn btn--outline">Domluvit schůzku zdarma</a>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Nové nástroje</span>
        <h2>Užitečné nástroje pro rodiče</h2>
        <p class="lead">Kromě informací o centru nabízíme i pár praktických pomocníků.</p>
      </div>
      <div class="card-grid">
        <article class="card">
          <span class="card-icon">🗓️</span>
          <h3>Interaktivní rozvrh</h3>
          <p>Přehledný týdenní rozvrh INSPIRKY, Domškolácké akademie i kroužků s možností filtrování podle programu.</p>
          <a href="/rozvrh.php" class="btn btn--outline btn--sm">Zobrazit rozvrh →</a>
        </article>
        <article class="card">
          <span class="card-icon">🧮</span>
          <h3>Kalkulačka ceny</h3>
          <p>Vyberte program a počet dní v týdnu a hned uvidíte odhad měsíční platby.</p>
          <a href="/cenik.php" class="btn btn--outline btn--sm">Spočítat cenu →</a>
        </article>
        <article class="card">
          <span class="card-icon">👨‍👩‍👧</span>
          <h3>Portál pro rodiče a učitele</h3>
          <p>Novinky z centra a výběr obědů na příští týden na jednom místě.</p>
          <a href="/dashboard.php" class="btn btn--outline btn--sm">Přihlásit se do portálu →</a>
        </article>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container text-center">
      <span class="section-eyebrow">Co o nás píšete</span>
      <h2>Zpětná vazba od rodičů</h2>
      <p class="lead">Pro více informací nás sledujte na sociálních sítích, nebo nám napište — rádi vám odpovíme na cokoliv, co vás ohledně Inspiry zajímá.</p>
      <div class="hero-actions" style="justify-content:center">
        <a href="https://www.facebook.com/centruminspira" class="btn btn--outline">Facebook</a>
        <a href="https://www.instagram.com/centruminspira" class="btn btn--outline">Instagram</a>
        <a href="/kontakt.php" class="btn btn--primary">Napsat nám</a>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
