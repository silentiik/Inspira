<?php
$pageTitle = 'Kontakt | INSPIRA';
$pageDescription = 'Kontaktujte vzdělávací centrum INSPIRA nebo podejte přihlášku do INSPIRKY či Domškolácké akademie.';
$activeNav = 'kontakt';
require __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/index.php">Domů</a> / Kontakt</p>
      <h1>Kontakt a přihlášky</h1>
      <p class="lead">Aktuálně přijímáme přihlášky na září 2026. Vyberte si formulář, který vám sedí, nebo nám rovnou napište.</p>
    </div>
  </section>

  <section class="section" id="prihlaska">
    <div class="container">
      <div class="calc-layout">
        <div>
          <div class="form-tabs">
            <button type="button" class="form-tab is-active" data-tab="prihlaska-inspirka">Přihláška INSPIRKA</button>
            <button type="button" class="form-tab" data-tab="prihlaska-domskolaci">Přihláška domškoláci</button>
            <button type="button" class="form-tab" data-tab="schuzka">Schůzka zdarma</button>
            <button type="button" class="form-tab" data-tab="dotaz">Obecný dotaz</button>
          </div>

          <div class="form-card">

            <!-- INSPIRKA -->
            <form class="form-panel is-active js-validated-form" novalidate id="prihlaska-inspirka" data-subject="Přihláška – INSPIRKA">
              <div class="alert"></div>
              <h3 class="mt-0">Přihláška do dětského klubu INSPIRKA</h3>
              <p class="hint-text">Pro děti od 3 do 6 let. Nabízíme půlden nebo celý den zdarma na zkoušku.</p>

              <div class="field-row">
                <div class="field">
                  <label for="ins-jmeno">Jméno dítěte</label>
                  <input type="text" id="ins-jmeno" data-label="Jméno dítěte" required>
                  <span class="field-error">Vyplňte prosím jméno dítěte.</span>
                </div>
                <div class="field">
                  <label for="ins-vek">Věk dítěte</label>
                  <select id="ins-vek" data-label="Věk dítěte" required>
                    <option value="">Vyberte</option>
                    <option>3 roky</option><option>4 roky</option><option>5 let</option><option>6 let</option>
                  </select>
                  <span class="field-error">Vyberte prosím věk dítěte.</span>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="ins-rodic">Jméno rodiče</label>
                  <input type="text" id="ins-rodic" data-label="Jméno rodiče" required>
                  <span class="field-error">Vyplňte prosím jméno rodiče.</span>
                </div>
                <div class="field">
                  <label for="ins-dny">Počet dní v týdnu</label>
                  <select id="ins-dny" data-label="Počet dní v týdnu" required>
                    <option value="">Vyberte</option>
                    <option>2 dny</option><option>3 dny</option><option>4 dny</option><option>5 dní</option>
                  </select>
                  <span class="field-error">Vyberte prosím počet dní.</span>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="ins-email">E-mail</label>
                  <input type="email" id="ins-email" data-label="E-mail" required>
                  <span class="field-error">Zadejte prosím platný e-mail.</span>
                </div>
                <div class="field">
                  <label for="ins-telefon">Telefon</label>
                  <input type="tel" id="ins-telefon" data-label="Telefon" pattern="[0-9+ ]{9,}" required>
                  <span class="field-error">Zadejte prosím platné telefonní číslo.</span>
                </div>
              </div>

              <div class="field">
                <label for="ins-zprava">Vzkaz (nepovinné)</label>
                <textarea id="ins-zprava" data-label="Vzkaz"></textarea>
              </div>

              <div class="field checkbox-field">
                <input type="checkbox" id="ins-souhlas" data-label="Souhlas se zpracováním údajů" required>
                <label for="ins-souhlas" style="margin:0;">Souhlasím se zpracováním osobních údajů za účelem vyřízení přihlášky.</label>
                <span class="field-error">Je potřeba potvrdit souhlas.</span>
              </div>

              <button type="submit" class="btn btn--primary btn--block">Odeslat přihlášku</button>
            </form>

            <!-- DOMŠKOLÁCI -->
            <form class="form-panel js-validated-form" novalidate id="prihlaska-domskolaci" data-subject="Přihláška – Domškolácká akademie">
              <div class="alert"></div>
              <h3 class="mt-0">Přihláška do Domškolácké akademie</h3>
              <p class="hint-text">Pro domškoláky 1.–9. ročníku. Po–St, 9:00–14:00.</p>

              <div class="field-row">
                <div class="field">
                  <label for="dom-jmeno">Jméno dítěte</label>
                  <input type="text" id="dom-jmeno" data-label="Jméno dítěte" required>
                  <span class="field-error">Vyplňte prosím jméno dítěte.</span>
                </div>
                <div class="field">
                  <label for="dom-rocnik">Ročník</label>
                  <select id="dom-rocnik" data-label="Ročník" required>
                    <option value="">Vyberte</option>
                    <option>1. ročník</option><option>2. ročník</option><option>3. ročník</option>
                    <option>4. ročník</option><option>5. ročník</option><option>6. ročník</option>
                    <option>7. ročník</option><option>8. ročník</option><option>9. ročník</option>
                  </select>
                  <span class="field-error">Vyberte prosím ročník.</span>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="dom-rodic">Jméno rodiče</label>
                  <input type="text" id="dom-rodic" data-label="Jméno rodiče" required>
                  <span class="field-error">Vyplňte prosím jméno rodiče.</span>
                </div>
                <div class="field">
                  <label for="dom-dny">Počet dní v týdnu</label>
                  <select id="dom-dny" data-label="Počet dní v týdnu" required>
                    <option value="">Vyberte</option>
                    <option>2 dny</option><option>3 dny</option>
                  </select>
                  <span class="field-error">Vyberte prosím počet dní.</span>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="dom-email">E-mail</label>
                  <input type="email" id="dom-email" data-label="E-mail" required>
                  <span class="field-error">Zadejte prosím platný e-mail.</span>
                </div>
                <div class="field">
                  <label for="dom-telefon">Telefon</label>
                  <input type="tel" id="dom-telefon" data-label="Telefon" pattern="[0-9+ ]{9,}" required>
                  <span class="field-error">Zadejte prosím platné telefonní číslo.</span>
                </div>
              </div>

              <div class="field">
                <label for="dom-zprava">Vzkaz (nepovinné)</label>
                <textarea id="dom-zprava" data-label="Vzkaz"></textarea>
              </div>

              <div class="field checkbox-field">
                <input type="checkbox" id="dom-souhlas" data-label="Souhlas se zpracováním údajů" required>
                <label for="dom-souhlas" style="margin:0;">Souhlasím se zpracováním osobních údajů za účelem vyřízení přihlášky.</label>
                <span class="field-error">Je potřeba potvrdit souhlas.</span>
              </div>

              <button type="submit" class="btn btn--primary btn--block">Odeslat přihlášku</button>
            </form>

            <!-- SCHŮZKA -->
            <form class="form-panel js-validated-form" novalidate id="schuzka" data-subject="Žádost o schůzku zdarma">
              <div class="alert"></div>
              <h3 class="mt-0">Domluvte si s námi schůzku zdarma</h3>
              <p class="hint-text">Přijďte se podívat, jak to u nás funguje, a zeptejte se na cokoliv.</p>

              <div class="field-row">
                <div class="field">
                  <label for="sch-jmeno">Vaše jméno</label>
                  <input type="text" id="sch-jmeno" data-label="Jméno" required>
                  <span class="field-error">Vyplňte prosím jméno.</span>
                </div>
                <div class="field">
                  <label for="sch-termin">Preferovaný termín</label>
                  <input type="date" id="sch-termin" data-label="Preferovaný termín" required>
                  <span class="field-error">Vyberte prosím termín.</span>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="sch-email">E-mail</label>
                  <input type="email" id="sch-email" data-label="E-mail" required>
                  <span class="field-error">Zadejte prosím platný e-mail.</span>
                </div>
                <div class="field">
                  <label for="sch-telefon">Telefon</label>
                  <input type="tel" id="sch-telefon" data-label="Telefon" pattern="[0-9+ ]{9,}" required>
                  <span class="field-error">Zadejte prosím platné telefonní číslo.</span>
                </div>
              </div>

              <div class="field">
                <label for="sch-program">Zajímá mě</label>
                <select id="sch-program" data-label="Zajímá mě" required>
                  <option value="">Vyberte</option>
                  <option>INSPIRKA</option>
                  <option>Domškolácká akademie</option>
                  <option>Volnočasové aktivity</option>
                  <option>Ještě nevím</option>
                </select>
                <span class="field-error">Vyberte prosím možnost.</span>
              </div>

              <button type="submit" class="btn btn--primary btn--block">Odeslat žádost o schůzku</button>
            </form>

            <!-- DOTAZ -->
            <form class="form-panel js-validated-form" novalidate id="dotaz" data-subject="Dotaz z webu INSPIRA">
              <div class="alert"></div>
              <h3 class="mt-0">Obecný dotaz</h3>

              <div class="field">
                <label for="dot-jmeno">Vaše jméno</label>
                <input type="text" id="dot-jmeno" data-label="Jméno" required>
                <span class="field-error">Vyplňte prosím jméno.</span>
              </div>

              <div class="field">
                <label for="dot-email">E-mail</label>
                <input type="email" id="dot-email" data-label="E-mail" required>
                <span class="field-error">Zadejte prosím platný e-mail.</span>
              </div>

              <div class="field">
                <label for="dot-zprava">Zpráva</label>
                <textarea id="dot-zprava" data-label="Zpráva" required></textarea>
                <span class="field-error">Napište nám prosím zprávu.</span>
              </div>

              <button type="submit" class="btn btn--primary btn--block">Odeslat dotaz</button>
            </form>

          </div>
        </div>

        <aside class="calc-summary">
          <h3 class="mt-0">Kontaktní informace</h3>
          <p><strong>E-mail:</strong> <a href="mailto:centruminspira@gmail.com">centruminspira@gmail.com</a><br>
          <strong>Telefon:</strong> <a href="tel:+420732728012">732 728 012</a></p>
          <p><strong>Adresa:</strong><br>Českobratrské náměstí 133<br>Mladá Boleslav<br>(velké zelené dveře, naproti Sboru českých bratří)</p>
          <p><strong>Jak se k nám dostanete:</strong><br>Autobusem: zastávka Jaselská (cca 200 m).<br>Parkování: krátké zastavení přímo před Inspirou, parkoviště Českobratrské náměstí (MB15) nebo parkovací dům Jaselská.</p>
          <p class="hint-text">IČ: 22456023<br>Zapsaná v obchodním rejstříku, sp. zn. C 416834 u Městského soudu v Praze.</p>
          <div class="footer-social" style="margin-top:14px;">
            <a href="https://www.facebook.com/centruminspira" aria-label="Facebook" style="background:var(--color-bg-alt); color:var(--color-primary-dark);">FB</a>
            <a href="https://www.instagram.com/centruminspira" aria-label="Instagram" style="background:var(--color-bg-alt); color:var(--color-primary-dark);">IG</a>
          </div>
        </aside>
      </div>
    </div>
  </section>

<?php
$extraScripts = ['/assets/js/form.js'];
require __DIR__ . '/includes/footer.php';
?>
