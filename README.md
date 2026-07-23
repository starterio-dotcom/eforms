# eForms felkészítő szakmai tájékoztató nap · 2026 — rendezvény-regisztrációs felület

Drupal 11 alapú rendezvényoldal és regisztrációs űrlap, a mellékelt Claude Design
felület (`eForms felkészítő 2026 - teljes felület.html`) alapján, a magyar
**DÁP Design System 1.5** stílusaival (Inter betűtípus, RemixIcon, Figma design tokenek).

## Mit tud?

- **Eseményoldal** (`/esemeny`, egyben címlap): hero, témák, időpontok élő
  kapacitás-kijelzéssel (szabad helyek + folyamatjelző), program-idővonal,
  előadók, helyszín Leaflet/OpenStreetMap térképpel.
- **Regisztrációs űrlap** (`/regisztracio`): alkalomválasztó kártyák (személyes /
  online), személyes adatok, adatkezelési hozzájárulás — a design szerinti
  hibakezeléssel (felső Callout + mezőnkénti hibaüzenetek). A betelt alkalom
  nem választható.
- **Sikeres regisztráció oldal** (`/regisztracio/kesz`): összegző kártya a
  megadott adatokkal, visszaigazoló e-mail értesítéssel.
- **Adminisztráció**: a beérkezett regisztrációk a
  `/admin/content/eforms-registrations` oldalon listázhatók és törölhetők
  (Tartalom menü → „eForms regisztrációk”).
- A kapacitások, az induló foglaltság, a program és a szövegek nagy része a
  `eforms_event.settings` konfigurációban szerkeszthető
  (`/admin/config/development/configuration/single/export`-tal megnézhető).

## Indítás (Docker)

Követelmény: Docker Desktop.

```bash
docker compose up --build
```

Az első indítás letölti a Drupal 11-et (Composer), majd automatikusan telepíti
a site-ot SQLite adatbázissal — ez néhány percet vesz igénybe. Utána:

- Felület: <http://localhost:8090>
- Admin belépés: `admin` / `admin` (felülírható a `EFORMS_ADMIN_PASSWORD`
  környezeti változóval a `docker-compose.yml`-ben)

A site állapota (adatbázis + fájlok) a `eforms-files` nevű Docker kötetben él.
Teljes újratelepítés: `docker compose down -v && docker compose up --build`.

## Fejlesztés

A `web/modules/custom` és `web/themes/custom` könyvtárak bind mountként be
vannak kötve a konténerbe, így a módosítások azonnal a konténerben landolnak.
Twig/CSS módosítás után gyorsítótár-ürítés:

```bash
docker compose exec web vendor/bin/drush cr
```

## Szerkezet

| Útvonal | Tartalom |
| --- | --- |
| `web/modules/custom/eforms_event` | Rendezvénymodul: útvonalak, vezérlő, regisztrációs űrlap, `eforms_registration` entitás, kapacitás-szolgáltatás, admin lista, Leaflet térkép |
| `web/themes/custom/eforms_theme` | Téma: DÁP Design System CSS + tokenek, RemixIcon, Inter webfontok, oldalstílusok, sablonok |
| `docker/` | Dockerfile + entrypoint (első indításkor automatikus site-telepítés) |
| `eForms felkészítő 2026 - teljes felület.html` | Az eredeti Claude Design referencia-felület |

## Megjegyzések

- **E-mail**: a demó konténerben nincs SMTP, ezért a levélküldés a Drupal
  `test_mail_collector` gyűjtőjébe történik. Élesben állítsd át a
  `system.mail` konfigurációt (pl. [SMTP](https://www.drupal.org/project/smtp)
  vagy [Symfony Mailer](https://www.drupal.org/project/symfony_mailer) modul).
- **Kapacitás**: szabad helyek = kapacitás − (induló foglaltság + beérkezett
  regisztrációk). Az induló foglaltság (41/50, 37/100 — a designnal egyezően) a
  `eforms_event.settings` konfigban módosítható.
- **Teams-meghívó**: az online regisztrálók a visszaigazolás mellé automatikusan
  megkapják a Microsoft Teams meghívót és csatlakozási segédletet, amint a
  Teams-link be van állítva az admin **Kapacitások** fülön. A link beállítása
  előtt beérkezett regisztrációk „függőben” állapotba kerülnek, és a link
  mentésekor (vagy cronból) automatikusan megkapják a meghívót.
- **Előadói fotók**: az Előadók szekcióban helyőrző SVG-k vannak
  (`web/modules/custom/eforms_event/images/eloadok/`). Valódi fotóhoz elég a
  helyőrző mellé azonos néven `jpg`/`jpeg`/`png`/`webp` fájlt tenni
  (pl. `totka-tamas.jpg`) — a fotó automatikusan felülírja az SVG-t; utána
  `drush cr`.
- **Sötét mód**: nincs — a DÁP Design System 1.5 világos témájú tokenkészletére
  épül a felület, a designnal egyezően. A `prefers-reduced-motion` beállítást
  a téma tiszteletben tartja (az átmenetek kikapcsolnak).
- **Nyelv**: a felület szövegei magyarul, közvetlenül a modulban/sablonokban
  szerepelnek; a Drupal admin felülete angol marad (a magyar admin fordítás a
  `locale` modullal utólag telepíthető).
- **Harmadik feles eszközök**: Inter (SIL OFL), RemixIcon v4.5 (Apache-2.0),
  Leaflet 1.9.4 (BSD-2), OpenStreetMap csempék (© OpenStreetMap contributors —
  futásidőben, hálózatról töltődnek).

## Licenc

A Drupal-kód GPL-2.0-or-later.
