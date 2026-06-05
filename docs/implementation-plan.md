# Implementatieplan

## Fase 1: Basisapplicatie

1. Scaffold Laravel in `app/`.
2. Configureer MySQL via Docker Compose.
3. Voeg Vue 3 toe via Vite.
4. Maak basislayout met publieke website, kassa en admin-ingang.

## Fase 2: Database en migratie

1. Maak Laravel migrations voor:
   - `menu_categories`
   - `menu_items`
   - `orders`
   - `order_lines`
   - `order_line_notes`
   - `promotions`
   - `generated_files`
   - `api_cache`
2. Maak een import command voor de oude `menu`- en `sales`-tabellen.
3. Valideer aantallen tegen `webfs_old/gouden_draak_create_script.sql`.

## Fase 3: Geselecteerde user stories

1. `UC-20`: menu CRUD.
2. `US-9`: kassa zoeken en filteren.
3. `US-10`: opmerkingen per gerecht.
4. `US-6`: herhalingsbestelling.
5. `US-4`: rekening-PDF.
6. `UC-14`: menu-PDF.
7. `UC-15`: favorieten via cookie.
8. `US-7`: cocktail-inspiratie.
9. `UC-19`: dagelijkse verkooprapportage.

## Fase 4: Acceptatie

1. Test per user story de acceptatiecriteria.
2. Controleer responsive gedrag.
3. Maak screenshots voor oplevering.
4. Exporteer nieuwe DevDoc/bewijsbijlage indien nodig.

