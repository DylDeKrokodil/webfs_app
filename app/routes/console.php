<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('legacy:import-menu {--fresh : Clear imported menu categories and items first}', function () {
    $legacyDatabase = 'gouden_draak_legacy';

    if ($this->option('fresh')) {
        DB::table('favorite_menu_items')->delete();
        DB::table('menu_items')->delete();
        DB::table('menu_categories')->delete();
    }

    $legacyItems = DB::table("{$legacyDatabase}.menu")
        ->orderBy('id')
        ->get();

    $categories = [];
    $sortOrder = 0;
    $imported = 0;

    foreach ($legacyItems as $legacyItem) {
        $categoryName = trim((string) ($legacyItem->soortgerecht ?: 'Overig'));

        if (! array_key_exists($categoryName, $categories)) {
            $categories[$categoryName] = MenuCategory::firstOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $sortOrder++, 'is_active' => true],
            );
        }

        MenuItem::updateOrCreate(
            ['legacy_menu_id' => $legacyItem->id],
            [
                'menu_category_id' => $categories[$categoryName]->id,
                'number' => $legacyItem->menunummer,
                'suffix' => $legacyItem->menu_toevoeging ? trim((string) $legacyItem->menu_toevoeging) : null,
                'name' => clean_legacy_menu_text((string) $legacyItem->naam),
                'description' => $legacyItem->beschrijving ? clean_legacy_menu_text((string) $legacyItem->beschrijving) : null,
                'price' => number_format((float) $legacyItem->price, 2, '.', ''),
                'is_active' => true,
            ],
        );

        $imported++;
    }

    $legacySales = DB::table("{$legacyDatabase}.sales")
        ->select('itemId', DB::raw('SUM(amount) as total'))
        ->groupBy('itemId')
        ->get();

    $favoriteStats = 0;

    foreach ($legacySales as $legacySale) {
        $menuItem = MenuItem::where('legacy_menu_id', $legacySale->itemId)->first();

        if (! $menuItem) {
            continue;
        }

        DB::table('favorite_menu_items')->updateOrInsert(
            ['menu_item_id' => $menuItem->id],
            [
                'count' => (int) $legacySale->total,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $favoriteStats++;
    }

    $this->info("Imported {$imported} legacy menu items.");
    $this->info("Imported {$favoriteStats} legacy sales totals as favorite menu item stats.");
})->purpose('Import legacy menu data into the modern menu schema');

if (! function_exists('clean_legacy_menu_text')) {
    function clean_legacy_menu_text(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutBreaks = preg_replace('/<br\s*\/?>/i', ' - ', $decoded) ?? $decoded;
        $stripped = trim(strip_tags($withoutBreaks));

        return preg_replace('/\s+/', ' ', $stripped) ?? $stripped;
    }
}
