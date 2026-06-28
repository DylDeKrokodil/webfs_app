# OTAP runbook

Dit project gebruikt OTAP als vaste stroom voor code, configuratie en data:

- **O - Ontwikkel:** lokale bouwomgeving voor dagelijkse development.
- **T - Test:** technische controle met eigen database en testconfiguratie.
- **A - Acceptatie:** klikbare release candidate voor eigenaar, zonen en team.
- **P - Productie:** live omgeving. Alleen geteste releases.

Code stroomt van **O -> T -> A -> P**. Data stroomt nooit terug naar productie. Productiedata mag alleen naar lagere omgevingen na anonimisering.

## Omgevingen

| Omgeving | URL | Database | Compose |
| --- | --- | --- | --- |
| Ontwikkel | `http://localhost:8080` | `gouden_draak_app` | `docker-compose.yml` |
| Test | `http://localhost:8082` | `gouden_draak_test` | `docker-compose.yml` + `docker-compose.test.yml` |
| Acceptatie | `http://localhost:8084` | `gouden_draak_acc` | `docker-compose.yml` + `docker-compose.acceptance.yml` |
| Productie | echte domeinnaam | `gouden_draak_prod` | hosting/deploypakket |

De lokale OTAP-omgevingen publiceren de app via nginx. De nginx-container is de publieke ingang per omgeving en proxy't intern naar de Laravel app-service. Hierdoor blijft de PHP/Laravel runtime afgeschermd en kan de webserverlaag later apart worden uitgebreid met TLS, caching, security headers of extra upstreams.

## Ontwikkel

```bash
cp app/.env.example app/.env
docker compose up --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan legacy:import-menu --fresh
docker compose exec app php artisan db:seed --class=AdminUserSeeder --force
```

Open:

- App: `http://localhost:8080`
- Vite: `http://localhost:5173`
- phpMyAdmin: `http://localhost:8081`

In ontwikkel draait nginx op poort `8080`; de app-container zelf publiceert geen hostpoort.

## Test

Maak een lokale testconfiguratie op basis van de template:

```bash
cp app/.env.test.example app/.env.test
```

Genereer een sleutel en vul in `app/.env.test` minimaal `APP_KEY` en `ADMIN_USER_PASSWORD`.

```bash
docker compose exec app php artisan key:generate --show
```

Start test:

```bash
docker compose -p gouden_draak_test -f docker-compose.yml -f docker-compose.test.yml up --build
docker compose -p gouden_draak_test -f docker-compose.yml -f docker-compose.test.yml exec app php artisan migrate:fresh --seed --force
docker compose -p gouden_draak_test -f docker-compose.yml -f docker-compose.test.yml exec app php artisan legacy:import-menu --fresh
docker compose -p gouden_draak_test -f docker-compose.yml -f docker-compose.test.yml exec app php artisan test
```

Technische smoke test:

- Homepage laadt.
- Menukaart laadt uit database.
- `/menukaart.pdf` downloadt.
- Login werkt met test-admin.
- Admin menu CRUD opent.
- Kassa kan zoeken/filteren.
- Tabletbestelling kan worden geplaatst.
- Tafelrekening-PDF bevat logo en QR-code.
- Reviewlink toont formulier of nette verlopen-linkmelding.
- Onbekende route toont 404.

In test draait nginx op poort `8082`; de app-container zelf publiceert geen hostpoort.

## Acceptatie

Maak acceptatieconfiguratie:

```bash
cp app/.env.acceptance.example app/.env.acceptance
```

Genereer een sleutel en vul in `app/.env.acceptance` minimaal `APP_KEY` en `ADMIN_USER_PASSWORD`.

```bash
docker compose exec app php artisan key:generate --show
```

Start acceptatie:

```bash
docker compose -p gouden_draak_acc -f docker-compose.yml -f docker-compose.acceptance.yml up --build
docker compose -p gouden_draak_acc -f docker-compose.yml -f docker-compose.acceptance.yml exec app php artisan migrate --force
docker compose -p gouden_draak_acc -f docker-compose.yml -f docker-compose.acceptance.yml exec app php artisan legacy:import-menu --fresh
docker compose -p gouden_draak_acc -f docker-compose.yml -f docker-compose.acceptance.yml exec app php artisan db:seed --class=AdminUserSeeder --force
```

Acceptatie mag alleen release candidates bevatten. Geen experimentele commits direct naar acceptatie.

Acceptatiescenario's:

- Publieke homepage en contactpagina.
- Menukaart, favorieten en menu-PDF.
- Tabletbestelling inclusief ronde/cooldown.
- Herhaalbestelling uit historie.
- Cocktail-inspiratie.
- Kassa zoeken/filteren en opmerkingen.
- Tafelbeheer en rekening-PDF.
- Review QR en reviewformulier.
- Admin verkooprapportage en downloads.
- Admin menu CRUD.
- 404-pagina.

In acceptatie draait nginx op poort `8084`; de app-container zelf publiceert geen hostpoort.

## Productie

Productie draait niet met de Vite dev-server. Gebruik alleen gecompileerde assets.

Voor release:

```bash
cd app
npm ci
npm run build
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Deploy-checklist:

1. Maak databasebackup.
2. Maak bestandenbackup.
3. Zet `APP_ENV=production`.
4. Zet `APP_DEBUG=false`.
5. Controleer `APP_URL`.
6. Upload releasepakket met `public/build`.
7. Draai migrations: `php artisan migrate --force`.
8. Seed admin alleen als nodig: `php artisan db:seed --class=AdminUserSeeder --force`.
9. Draai smoke tests.
10. Noteer releaseversie en datum.

Rollback:

- Zet vorige bestandenbackup terug.
- Zet databasebackup terug als migrations of datamutaties de fout veroorzaakten.
- Exporteer eerst nieuwe orders/reviews als SQL of CSV als productie al gebruikt is.

## Dataregels

- Ontwikkeldata mag weggegooid worden.
- Testdata mag nooit naar productie.
- Acceptatiedata mag nooit naar productie.
- Productiedata mag alleen naar test/acceptatie na anonimisering.
- Legacydata wordt alleen via gecontroleerde importcommando's naar de nieuwe database gebracht.

## Configregels

- Echte `.env` bestanden worden niet gecommit.
- Alleen `.env.*.example` templates worden gecommit.
- Wachtwoorden en productiegegevens staan nooit in Git.
- In Test/Acceptatie/Productie staat `APP_DEBUG=false`.
