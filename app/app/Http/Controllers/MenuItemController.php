<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    public function index(): JsonResponse
    {
        $items = MenuItem::query()
            ->with('category')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->select('menu_items.*')
            ->where('menu_items.is_active', true)
            ->orderBy('menu_categories.sort_order')
            ->orderByRaw('menu_items.number IS NULL')
            ->orderBy('menu_items.number')
            ->orderBy('menu_items.suffix')
            ->orderBy('menu_items.name')
            ->get()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'number' => $item->number,
                'suffix' => $item->suffix,
                'display_number' => trim(($item->number ?? '').($item->suffix ?? '')),
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'category' => $item->category?->name ?? 'Overig',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }
}
