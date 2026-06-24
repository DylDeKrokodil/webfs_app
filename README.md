# De Gouden Draak

Modernisering van de oude website en kassa van restaurant De Gouden Draak.

De applicatie bestaat uit een Laravel-backend met een Vue 3/Vite-frontend. Docker Compose levert de ontwikkelomgeving met nginx, Apache/PHP, MySQL, Vite, Reverb, scheduler en phpMyAdmin.

## Huidige Functionaliteit

De huidige implementatie dekt deze geselecteerde user stories:

- `US-1`: tablet bestellen per tafel met rondelimiet, cooldown en tafelstatus
- `US-4`: tafelrekening genereren als PDF bij het afrekenen
- `US-6`: herhalingsbestelling vanuit de open bestelhistorie van dezelfde tafel
- `US-7`: cocktail-inspiratie via TheCocktailDB binnen de tabletinterface
- `US-9`: kassa zoeken en filteren op naam, nummer en categorie
- `US-10`: opmerkingen per gerecht, inclusief suggesties uit eerdere opmerkingen
- `UC-14`: actuele menukaart als PDF genereren vanuit de database
- `UC-15`: favoriete gerechten bewaren via een browser-cookie
- `UC-19`: dagelijkse verkooprapportage als Excel-bestand
- `UC-20`: gerechten toevoegen, aanpassen, inactief zetten en verwijderen waar dat veilig kan

Daarnaast bevat de applicatie:

- publieke menukaart en webbestellingen met bestelbevestiging
- admin-kassa, menubeheer, tafeloverzicht, verkoopoverzicht en statistieken
- QR/review-flow na afrekenen
- tafel-assistentieverzoeken vanuit de tabletinterface
- realtime events via Laravel Reverb
- meertalige UI en menuvertalingen
- import van legacy-menudata en historische verkopen uit `webfs_old/`
- admin-tour voortgang per gebruiker

## Technische Richting

- Backend: Laravel 13 op PHP 8.4
- Frontend: Vue 3, Vite en Tailwind CSS
- Realtime: Laravel Reverb
- PDF: Dompdf
- Rapportage: PhpSpreadsheet
- Database: MySQL 8.4
- Development: Docker Compose

## Starten

Zorg eerst dat Docker Desktop draait.

Maak een lokale environment-file:

```bash
cp app/.env.example app/.env
```

Zet in `app/.env` minimaal een admin-wachtwoord en Reverb-waarden:

```env
ADMIN_USER_NAME=Admin
ADMIN_USER_EMAIL=admin@goudendraak.local
ADMIN_USER_PASSWORD=admin1234

REVERB_APP_ID=gouden-draak
REVERB_APP_KEY=local-reverb-key
REVERB_APP_SECRET=local-reverb-secret
```

Installeer dependencies:

```bash
docker compose run --rm composer --working-dir=app install
docker compose run --rm node npm --prefix app install
```

Genereer de Laravel key:

```bash
docker compose run --rm app php artisan key:generate
```

Start de omgeving:

```bash
docker compose up --build
```

Voer daarna migraties, import en seeders uit:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan legacy:import-menu --fresh
docker compose exec app php artisan db:seed --class=AdminUserSeeder --force
```

Open daarna:

- Applicatie: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- Vite dev server: http://localhost:5173
- Reverb server: http://localhost:8008

Standaard admin-login als je bovenstaande voorbeeldwaarden gebruikt:

- E-mail: `admin@goudendraak.local`
- Wachtwoord: `admin1234`

## Belangrijke Routes

- `/`: publieke startpagina
- `/menukaart`: publieke menukaart met favorieten-cookie
- `/menukaart.pdf`: menu-PDF vanuit de database
- `/tablet/{tafelnummer}`: tabletbestellen per tafel
- `/bestelling/{token}`: webbestelling-bevestiging
- `/review/{token}`: reviewformulier na afrekenen
- `/admin/menu`: menubeheer
- `/admin/kassa`: kassa
- `/admin/tafels`: tafeloverzicht, assistentie en afrekenen
- `/admin/overzicht`: verkoopoverzicht
- `/admin/statistieken`: statistieken en rapportages

## OTAP

De repo bevat Docker Compose-profielen voor meerdere omgevingen:

- Ontwikkel: `docker-compose.yml`, applicatie op http://localhost:8080
- Test: `docker-compose.yml` + `docker-compose.test.yml`, applicatie op http://localhost:8082
- Acceptatie: `docker-compose.yml` + `docker-compose.acceptance.yml`, applicatie op http://localhost:8084
- Productie: build/deploy op basis van de Laravel-app, environment templates en serverconfiguratie

Gebruik alleen echte `.env` bestanden lokaal of op de server. Commit alleen templates zoals:

- `app/.env.example`
- `app/.env.test.example`
- `app/.env.acceptance.example`
- `app/.env.production.example`

## Legacy Data

De oude SQL-dump staat in `webfs_old/gouden_draak_create_script.sql`. De MySQL-init scripts laden deze dump in database `gouden_draak_legacy`. De Laravel-app gebruikt zelf database `gouden_draak_app`, zodat legacy-data en nieuwe migraties gescheiden blijven.

Importeer legacy-data naar de moderne tabellen met:

```bash
docker compose exec app php artisan legacy:import-menu --fresh
```

Deze import vult `menu_categories`, `menu_items`, `orders` en `order_lines`. Favoriete gerechten voor bezoekers worden niet meer server-side als populariteitstabel bijgehouden; die feature gebruikt een browser-cookie op de publieke menukaart.

## Rapportages

De scheduler-container draait `php artisan schedule:work`. Laravel plant `sales:generate-daily-summary` dagelijks om 00:10. De gegenereerde Excel-bestanden zijn in de admin beschikbaar via de verkooprapportages.

Handmatig genereren kan ook:

```bash
docker compose exec app php artisan sales:generate-daily-summary 2026-06-24
```

## Testen

Gebruik de testomgeving of draai tests in de app-container:

```bash
docker compose run --rm app php artisan test
```

Voor een specifieke test:

```bash
docker compose run --rm app php artisan test --filter=AdminOrderTest
```
