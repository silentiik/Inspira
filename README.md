# INSPIRA — web centra

Web vzdělávacího centra INSPIRA (Mladá Boleslav), inspirovaný obsahem
[centruminspira.cz](https://centruminspira.cz), rozšířený o interaktivní
nástroje a skutečný backend s přihlašováním a rolemi.

## Technologie

**PHP + SQLite.** Zvoleno konkrétně proto, že tohle běžný sdílený
webhosting (typicky PHP 8.x + Apache) podporuje bez čehokoliv navíc —
žádný Node.js, žádný samostatný databázový server k správě, žádný build
krok. SQLite je jeden soubor (`data/inspira.sqlite`), který se vytvoří
a naplní strukturou automaticky při prvním požadavku.

## Struktura

```
index.php, o-nas.php, cenik.php, rozvrh.php, kontakt.php   Veřejné stránky
sluzby/*.php                                                Podstránky služeb
setup.php                    Jednorázové vytvoření prvního administrátora
dashboard.php                Nástěnka po přihlášení (role-aware)
auth/
  login.php                  Přihlášení
  logout.php
  forgot-password.php        Žádost o odkaz pro obnovení hesla
  reset-password.php         Nastavení hesla (obnova i první pozvánka)
admin/
  users.php                  Správa uživatelů a rolí (jen admin)
  content.php                Úprava textů na webu (admin + učitel)
  pricing.php                 Úprava ceníku (jen admin)
app/
  config.php                 Nastavení — MAIL_FROM_ADDRESS, SITE_BASE_URL, ...
  db.php                     Připojení k SQLite + automatická migrace schématu
  auth.php                   Přihlašování, session, tokeny, uzamykání účtu
  csrf.php, flash.php        Ochrana formulářů, zprávy po přesměrování
  content.php, pricing.php, news.php, children.php, invites.php, mailer.php
includes/
  header.php, footer.php     Sdílená hlavička a patička (role-aware menu)
assets/
  css/style.css              Styly (jeden sdílený soubor)
  js/*.js                    Mobilní menu, kalkulačka, rozvrh, validace formulářů
data/                        SQLite soubor + mail.log (mimo git, chráněno .htaccess)
```

## Role a co kdo může

| | Admin | Učitel/ka | Rodič |
|---|---|---|---|
| Vidět nástěnku s novinkami | ✅ | ✅ | ✅ |
| Přidat/smazat novinku | ✅ | ✅ | ❌ |
| Pozvat rodiče do portálu (přes Uživatelé a role) | ✅ | ❌ | ❌ |
| Spravovat všechny účty a role | ✅ | ❌ | ❌ |
| Upravovat texty na webu | ✅ (vše) | ✅ (jen vybrané) | ❌ |
| Upravovat ceník | ✅ | ❌ | ❌ |
| Přidat dítě a vybrat obědy | — | — | ✅ (jen svoje děti) |

Nové účty (učitel, rodič) vznikají pozvánkou (e-mail s odkazem pro
nastavení hesla) — není tu veřejná registrace, aby si kdokoliv nemohl
založit účet a vydávat se za rodiče.

## První nasazení

1. Nahrajte celý obsah složky na hosting (FTP/SFTP/Git) tak, aby
   `index.php` byl přímo v kořeni webu.
2. Ověřte, že hosting používá PHP 8.2 nebo novější (nastavuje se v
   klientské sekci hostingu).
3. Otevřete `https://vase-domena.cz/setup.php` — jednorázově vytvoří
   první administrátorský účet. Formulář se sám vypne, jakmile první
   uživatel existuje.
4. V `app/config.php` nastavte:
   - `SITE_BASE_URL` na skutečnou doménu (používá se v odkazech v
     e-mailech — **nutné nastavit před ostrým provozem**)
   - `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`
   - `MAIL_DEV_MODE` — začíná na `true` (e-maily se jen zapisují do
     `data/mail.log`, nic se neodesílá doopravdy). Jakmile ověříte, že
     e-maily z formuláře pro obnovení hesla skutečně chodí, přepněte na
     `false`.
5. Přihlaste se jako admin a přes „Uživatelé a role" pozvěte první
   učitele; učitelé pak už mohou sami zvát rodiče přímo z nástěnky.

## Nové nástroje oproti originálu

- **Kalkulačka ceny** (`cenik.php`) — cena se čte přímo z databáze, takže
  úprava v „Ceník" (admin) se hned projeví i v kalkulačce.
- **Interaktivní rozvrh** (`rozvrh.php`) — týdenní přehled INSPIRKY,
  Domškolácké akademie a kroužků, filtrovatelný podle programu (zatím
  statická data, needitovatelná přes administraci).
- **Formuláře s validací** (`kontakt.php`) — přihlášky a kontaktní formulář
  s kontrolou vyplnění; po odeslání se otevře e-mailový klient s předvyplněnou
  zprávou (nejde do databáze, jen mailto).
- **Portál pro rodiče a učitele** (`dashboard.php` a `auth/*`) — skutečné
  přihlašování s hashovanými hesly, obnova zapomenutého hesla e-mailem,
  nástěnka s novinkami, výběr obědů na týden (vázaný na konkrétní dítě a
  rodiče) a správa uživatelů/rolí pro admina.

## Bezpečnostní poznámky

- Hesla jsou hashovaná (`password_hash`/bcrypt), nikdy neukládaná v
  čitelné podobě.
- Po 5 neúspěšných přihlášeních se účet na 15 minut dočasně uzamkne.
- Formuláře jsou chráněné proti CSRF (token v každém POST požadavku).
- `data/` a `app/` mají `.htaccess` s `Deny from all` — funguje to jen
  pokud hosting povoluje `.htaccess` přepisy (`AllowOverride`), což tenhle
  hosting podle specifikace podporuje.
- Odkazy v e-mailech (obnova hesla, pozvánky) používají pevně nastavenou
  `SITE_BASE_URL` z konfigurace, ne doménu z requestu — to schválně brání
  podvržení odkazu přes falešnou hlavičku `Host`.

## Poznámka k testování

Tenhle kód nebylo možné spustit a otestovat lokálně (v prostředí, kde
vznikl, není nainstalované PHP) — je napsaný pečlivě podle běžných a
dobře otestovaných vzorů, ale **potřebuje reálné otestování po nasazení**
na hostingu, hlavně: přihlášení/odhlášení, obnova hesla (v `MAIL_DEV_MODE`
přes `data/mail.log`), pozvánky, ukládání obědů, a role-based oprávnění
(zkusit přihlásit se jako každá role a ověřit, co vidí/může).

## Poznámka k obrázkům

Aby web nekopíroval fotografie z originálního webu, jsou zde místo fotek
použité jednoduché SVG ilustrace v barvách INSPIRA.
