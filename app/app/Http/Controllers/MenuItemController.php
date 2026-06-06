<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index(Request $request, TranslationService $translator): JsonResponse
    {
        $targetLang = $request->header('X-Locale', 'nl');

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
            ->get();

        // If target is not Dutch, we translate
        if (strtolower($targetLang) !== 'nl') {
            $stringsToTranslate = [];
            foreach ($items as $item) {
                $stringsToTranslate[] = $item->name;
                if ($item->description) $stringsToTranslate[] = $item->description;
                if ($item->category?->name) $stringsToTranslate[] = $item->category->name;
            }

            $uniqueStrings = array_values(array_unique(array_filter($stringsToTranslate)));
            $translatedMap = $translator->translateArray($uniqueStrings, $targetLang);
            $translationLookup = array_combine($uniqueStrings, $translatedMap);

            $mappedItems = $items->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'number' => $item->number,
                'suffix' => $item->suffix,
                'display_number' => trim(($item->number ?? '').($item->suffix ?? '')),
                'name' => $translationLookup[$item->name] ?? $item->name,
                'description' => $item->description ? ($translationLookup[$item->description] ?? $item->description) : null,
                'price' => (float) $item->price,
                'category' => $item->category?->name ? ($translationLookup[$item->category->name] ?? $item->category->name) : 'Other',
                'category_sort_order' => (int) ($item->category?->sort_order ?? 999),
            ]);
        } else {
            $mappedItems = $items->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'number' => $item->number,
                'suffix' => $item->suffix,
                'display_number' => trim(($item->number ?? '').($item->suffix ?? '')),
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'category' => $item->category?->name ?? 'Overig',
                'category_sort_order' => (int) ($item->category?->sort_order ?? 999),
            ]);
        }

        return response()->json([
            'data' => $mappedItems,
        ]);
    }
}
