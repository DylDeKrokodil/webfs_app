<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('legacy:import-menu {--fresh : Clear imported menu categories and items first}', function () {
    $legacyDatabase = 'gouden_draak_legacy';

    if ($this->option('fresh')) {
        Schema::disableForeignKeyConstraints();
        DB::table('favorite_menu_items')->truncate();
        DB::table('menu_items')->truncate();
        DB::table('menu_categories')->truncate();
        Schema::enableForeignKeyConstraints();
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

    $this->info("Imported {$imported} legacy menu items.");

    $this->comment("Importing historical orders...");

    // Group sales by date to reconstruct orders
    $legacySales = DB::table("{$legacyDatabase}.sales")
        ->orderBy('saleDate')
        ->get()
        ->groupBy(function ($sale) {
            return $sale->saleDate;
        });

    $ordersImported = 0;
    $orderLinesImported = 0;

    foreach ($legacySales as $timestamp => $salesInOrder) {
        $order = DB::table('orders')->insertGetId([
            'channel' => 'legacy',
            'status' => 'paid',
            'paid_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $orderSubtotal = 0;

        foreach ($salesInOrder as $sale) {
            $menuItem = MenuItem::where('legacy_menu_id', $sale->itemId)->first();

            if (!$menuItem) continue;

            $lineTotal = (float) $menuItem->price * (int) $sale->amount;

            DB::table('order_lines')->insert([
                'order_id' => $order,
                'menu_item_id' => $menuItem->id,
                'quantity' => (int) $sale->amount,
                'unit_price' => $menuItem->price,
                'line_total' => $lineTotal,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $orderSubtotal += $lineTotal;
            $orderLinesImported++;
        }

        DB::table('orders')->where('id', $order)->update([
            'subtotal' => $orderSubtotal,
            'total' => $orderSubtotal,
        ]);

        $ordersImported++;
    }

    $this->info("Imported {$ordersImported} historical orders with {$orderLinesImported} lines.");
    })->purpose('Import legacy menu data and sales history into the modern schema');


if (! function_exists('clean_legacy_menu_text')) {
    function clean_legacy_menu_text(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutBreaks = preg_replace('/<br\s*\/?>/i', ' - ', $decoded) ?? $decoded;
        $stripped = trim(strip_tags($withoutBreaks));

        return preg_replace('/\s+/', ' ', $stripped) ?? $stripped;
    }
}
