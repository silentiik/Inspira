<?php
$pageTitle = 'Rozvrh | INSPIRA';
$pageDescription = 'Interaktivní týdenní rozvrh INSPIRKY, Domškolácké akademie a kroužků centra INSPIRA.';
$activeNav = 'rozvrh';
require __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/index.php">Domů</a> / Rozvrh</p>
      <h1>Týdenní rozvrh</h1>
      <p class="lead">Klikněte na štítek programu a zobrazte nebo skryjte jeho bloky v rozvrhu.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="schedule-filters" id="scheduleFilters"></div>
      <div class="schedule-table-wrap">
        <div class="schedule-days" id="scheduleDays"></div>
      </div>
      <p class="hint-text" style="margin-top:16px;">Rozvrh je orientační, aktuální časy kroužků potvrdíme při zápisu. Časy INSPIRKY a Domškolácké akademie jsou pevné.</p>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <div class="banner">
        <h2>Chcete vědět víc o konkrétním programu?</h2>
        <div class="banner-actions">
          <a href="/sluzby/inspirka.php" class="btn btn--accent">INSPIRKA</a>
          <a href="/sluzby/domskolacka-akademie.php" class="btn btn--accent">Domškolácká akademie</a>
          <a href="/sluzby/volnocasove-aktivity.php" class="btn btn--outline">Kroužky</a>
        </div>
      </div>
    </div>
  </section>

<?php
$extraScripts = ['/assets/js/schedule.js'];
require __DIR__ . '/includes/footer.php';
?>
