<?php
require_once __DIR__ . '/app/pricing.php';

$pageTitle = 'Ceník | INSPIRA';
$pageDescription = 'Ceník a interaktivní kalkulačka ceny pro INSPIRKU a Domškolácku akademii centra INSPIRA.';
$activeNav = 'cenik';
require_once __DIR__ . '/includes/header.php';

$pricingRows = all_pricing();
$byProgram = ['inspirka' => [], 'domskolaci' => []];
foreach ($pricingRows as $row) {
    $byProgram[$row['program']][] = $row;
}
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/index.php">Domů</a> / Ceník</p>
      <h1>Ceník</h1>
      <p class="lead">Vyberte program a počet dní v týdnu — kalkulačka spočítá odhad měsíční platby.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="calc-layout">
        <div>
          <h2 class="mt-0">Kalkulačka ceny</h2>

          <div class="field">
            <label>Program</label>
            <div class="option-grid">
              <div class="option-chip js-program-chip is-selected" data-program="inspirka">INSPIRKA (3–6 let)</div>
              <div class="option-chip js-program-chip" data-program="domskolaci">Domškolácká akademie</div>
            </div>
          </div>

          <div class="field">
            <label>Počet dní v týdnu</label>
            <div class="option-grid" id="calcDays"></div>
          </div>

          <p class="hint-text">Docházka: <strong id="calcSchedule"></strong></p>

          <div class="extra-row">
            <div>
              <strong>Sleva na sourozence</strong>
              <p class="hint-text mt-0" style="margin:0;">10 % sleva na druhé a další dítě ze stejné rodiny.</p>
            </div>
            <label class="switch">
              <input type="checkbox" id="calcSibling">
              <span class="switch-track"></span>
            </label>
          </div>

          <div class="notice-box" style="margin-top:20px;">
            Cena zahrnuje vzdělávací program, materiály a aktivity. <strong>Strava není zahrnuta</strong> — obědy si rodiče vybírají zvlášť v <a href="/dashboard.php">portálu pro rodiče</a>. Ceny kroužků z Volnočasových aktivit řešíme individuálně, napište nám na <a href="/kontakt.php">kontaktním formuláři</a>.
          </div>
        </div>

        <aside class="calc-summary">
          <h3 class="mt-0">Odhad měsíční platby</h3>
          <div class="calc-summary-row"><span>Program</span><span id="summaryProgram"></span></div>
          <div class="calc-summary-row"><span>Docházka</span><span id="summaryDays"></span></div>
          <div class="calc-summary-row"><span>Základní cena</span><span id="summaryBase"></span></div>
          <div class="calc-summary-row" id="summaryDiscountRow" style="display:none;"><span>Sleva na sourozence</span><span id="summaryDiscount"></span></div>
          <div class="calc-summary-total">
            <span>Celkem / měsíc</span>
            <span class="amount" id="summaryTotal"></span>
          </div>
          <a href="/kontakt.php#prihlaska" class="btn btn--primary btn--block" style="margin-top:20px;">Podat přihlášku</a>
        </aside>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="section-head section-head--center">
        <span class="section-eyebrow">Kompletní ceník</span>
        <h2>Ceny podle počtu dní v týdnu</h2>
      </div>
      <div class="two-col">
        <div>
          <h3><?= htmlspecialchars(PROGRAM_LABELS['inspirka']['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="hint-text"><?= htmlspecialchars(PROGRAM_LABELS['inspirka']['schedule'], ENT_QUOTES, 'UTF-8') ?></p>
          <table class="price-table">
            <thead><tr><th>Počet dní týdně</th><th>Měsíční cena</th></tr></thead>
            <tbody>
              <?php foreach ($byProgram['inspirka'] as $row): ?>
              <tr>
                <td><?= (int) $row['days'] ?> <?= (int) $row['days'] === 1 ? 'den' : 'dny/dní' ?><?= $row['note'] ? ' (' . htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') . ')' : '' ?></td>
                <td><?= number_format((int) $row['price'], 0, ',', ' ') ?> Kč</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div>
          <h3><?= htmlspecialchars(PROGRAM_LABELS['domskolaci']['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="hint-text"><?= htmlspecialchars(PROGRAM_LABELS['domskolaci']['schedule'], ENT_QUOTES, 'UTF-8') ?></p>
          <table class="price-table">
            <thead><tr><th>Počet dní týdně</th><th>Měsíční cena</th></tr></thead>
            <tbody>
              <?php foreach ($byProgram['domskolaci'] as $row): ?>
              <tr>
                <td><?= (int) $row['days'] ?> dny/dní<?= $row['note'] ? ' (' . htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') . ')' : '' ?></td>
                <td><?= number_format((int) $row['price'], 0, ',', ' ') ?> Kč</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p class="hint-text" style="margin-top:20px;">Sleva na sourozence: 10 %. Cena zahrnuje vzdělávací program, materiály a aktivity. Strava není zahrnuta v ceně.</p>
    </div>
  </section>

<?php
echo '<script>window.INSPIRA_PRICING = ' . json_encode(pricing_for_calculator(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
$extraScripts = ['/assets/js/calculator.js'];
require_once __DIR__ . '/includes/footer.php';
?>
