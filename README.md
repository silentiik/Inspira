# INSPIRA — web centra

Web vzdělávacího centra INSPIRA (Mladá Boleslav), inspirovaný obsahem
[centruminspira.cz](https://centruminspira.cz), rozšířený o pár interaktivních
nástrojů navíc.

## Struktura

```
index.html                     Domovská stránka
o-nas.html                     O nás
cenik.html                     Ceník + kalkulačka ceny
rozvrh.html                    Interaktivní týdenní rozvrh
kontakt.html                   Kontakt + přihlášky (s validací)
portal.html                    Portál pro rodiče a učitele (demo)
sluzby/
  inspirka.html                Dětský klub INSPIRKA
  domskolacka-akademie.html    Domškolácká akademie
  volnocasove-aktivity.html    Volnočasové aktivity
assets/
  css/style.css                Styly (jeden sdílený soubor)
  js/main.js                   Mobilní menu, aktivní odkaz, tlačítko "nahoru"
  js/calculator.js             Logika kalkulačky ceny
  js/schedule.js                Data a filtrování rozvrhu
  js/form.js                   Validace formulářů + mailto odeslání
  js/portal.js                 Demo přihlášení, nástěnka, výběr obědů
```

Čistě statický web (HTML/CSS/JS), žádný build krok, žádný backend.

## Spuštění lokálně

Stránky používají relativní odkazy, takže je nejlepší je spouštět přes
lokální server (ne přímým otevřením souboru):

```bash
python -m http.server 8000
```

a pak otevřít `http://localhost:8000`.

## Nové nástroje oproti originálu

- **Kalkulačka ceny** (`cenik.html`) — spočítá odhad měsíční platby podle
  programu, počtu dní a slevy na sourozence. Ceny odpovídají zveřejněnému
  ceníku na centruminspira.cz.
- **Interaktivní rozvrh** (`rozvrh.html`) — týdenní přehled INSPIRKY,
  Domškolácké akademie a kroužků, filtrovatelný podle programu.
- **Formuláře s validací** (`kontakt.html`) — přihlášky a kontaktní formulář
  s kontrolou vyplnění; po odeslání se otevře e-mailový klient s předvyplněnou
  zprávou (žádný backend zatím není napojen).
- **Portál pro rodiče a učitele** (`portal.html`) — ukázkové přihlášení,
  nástěnka s novinkami (učitelé mohou přidávat příspěvky) a výběr obědů na
  týden. **Je to jen ukázka** — data se ukládají do `localStorage`
  prohlížeče, nejsou sdílená mezi zařízeními ani rodinami. Pro ostrý provoz
  by bylo potřeba napojit skutečný backend a přihlašování.

## Poznámka k obrázkům

Aby web nekopíroval fotografie z originálního webu, jsou zde místo fotek
použité jednoduché SVG ilustrace v barvách INSPIRA.
