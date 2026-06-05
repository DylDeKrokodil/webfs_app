# De Gouden Draak

Modernisering van de oude website en kassa van De Gouden Draak.

## Scope eerste implementatie

De implementatie volgt de geselecteerde user stories uit `WEBFS - Nieuwe Functionaliteit v2.xlsx`:

- `US-1`: tablet bestellen per tafel
- `US-4`: rekening opslaan als PDF
- `US-6`: herhalingsbestelling vanuit historie
- `US-7`: cocktail-inspiratie via externe API
- `US-9`: kassa zoeken en filteren
- `US-10`: opmerkingen per gerecht
- `UC-14`: menu-PDF genereren vanuit database
- `UC-15`: favoriete gerechten via cookie
- `UC-19`: dagelijkse verkooprapportage
- `UC-20`: gerechten toevoegen, aanpassen en verwijderen

## Technische richting

- Backend: Laravel
- Frontend: Vue 3 met Vite
- Webserver: nginx als front-door, Apache/PHP als interne Laravel-runtime
- Database: MySQL 8
- Development: Docker Compose

## Starten

Zorg eerst dat Docker Desktop draait.

Daarna scaffold je de Laravel/Vue-app:

```bash
docker compose run --rm composer create-project laravel/laravel app
docker compose run --rm node npm --prefix app install
docker compose run --rm node npm --prefix app install vue @vitejs/plugin-vue
```

Kopieer daarna de voorbeeldconfiguratie:

```bash
cp app/.env.example app/.env
```

Zet in `app/.env` minimaal:

```env
APP_NAME="De Gouden Draak"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=gouden_draak_app
DB_USERNAME=gouden_draak
DB_PASSWORD=gouden_draak

ADMIN_USER_NAME=Admin
ADMIN_USER_EMAIL=admin@goudendraak.local
ADMIN_USER_PASSWORD=vervang-door-een-sterk-lokaal-wachtwoord
```

Start de omgeving:

```bash
docker compose up --build
```

Dit start nginx, Laravel, Vite met live reload, MySQL en phpMyAdmin. nginx is de publieke ingang op poort 8080 en proxy't intern naar de Laravel-runtime.

De nieuwe Laravel-app gebruikt database `gouden_draak_app`. De legacy-app gebruikt database `gouden_draak_legacy`, zodat legacy-data en nieuwe Laravel-migraties elkaar niet raken.

Migreer daarna de legacy-menudata naar de nieuwe Laravel-tabellen:

```bash
docker compose exec app php artisan legacy:import-menu --fresh
```

Deze import vult `menu_categories` en `menu_items` vanuit de legacy-tabel `menu`. Legacy-verkoopregels uit `sales` worden als populariteitsstatistiek opgeslagen in `favorite_menu_items`.

Maak of herstel de admin user:

```bash
docker compose exec app php artisan db:seed --class=AdminUserSeeder --force
```

Standaard login voor de nieuwe admin/kassa:

- E-mail: `admin@goudendraak.local`
- Wachtwoord: `admin1234`

Open daarna:

- Applicatie: http://localhost:8080
- Oude website/kassa: http://localhost:8090
- phpMyAdmin: http://localhost:8081

## OTAP

De repo bevat een concrete OTAP-inrichting:

- Ontwikkel: `docker-compose.yml`, app op http://localhost:8080
- Test: `docker-compose.yml` + `docker-compose.test.yml`, app op http://localhost:8082
- Acceptatie: `docker-compose.yml` + `docker-compose.acceptance.yml`, app op http://localhost:8084
- Productie: build/deploy volgens het runbook

De OTAP-poorten worden door nginx gepubliceerd. De Laravel app-container blijft intern beschikbaar via Docker service naam `app`, zodat de webserverlaag per omgeving kan meegroeien zonder de applicatiecontainer zelf publiek te maken.

Gebruik alleen echte `.env` bestanden lokaal of op de server. Commit alleen templates zoals:

- `app/.env.example`
- `app/.env.test.example`
- `app/.env.acceptance.example`
- `app/.env.production.example`

Het volledige proces staat in `docs/otap.md`.

## Oude site

De oude website en SQL-dump blijven beschikbaar in `webfs_old/`. Deze map is bronmateriaal voor migratie en bewijsvoering, niet de plek voor nieuwe code.

De legacy-runtime draait als aparte Docker Compose service, zodat de oude app naast Laravel beschikbaar blijft:

```bash
docker compose up --build legacy mysql
```

Open daarna:

- Oude website: http://localhost:8090
- Oude kassa: http://localhost:8090/kassa

Test-login voor de kassa volgens de legacy dump:

- Medewerker: `1`
- Wachtwoord: `test`
