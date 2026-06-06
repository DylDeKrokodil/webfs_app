<?php

namespace App\Http\Controllers\Admin;

use App\Events\MenuItemUpdated;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => MenuCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order', 'is_active']),
            'items' => MenuItem::query()
                ->with('category')
                ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
                ->select('menu_items.*')
                ->orderBy('menu_categories.sort_order')
                ->orderByRaw('menu_items.number IS NULL')
                ->orderBy('menu_items.number')
                ->orderBy('menu_items.suffix')
                ->orderBy('menu_items.name')
                ->get()
                ->map(fn (MenuItem $item): array => $this->serializeItem($item)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $item = MenuItem::create($this->validatedAttributes($request));

        MenuItemUpdated::dispatch($item->load('category'));

        return response()->json([
            'item' => $this->serializeItem($item),
        ], 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $attributes = $this->validatedAttributes($request, $menuItem);

        // De nummering moet gelijk blijven voor bestaande gerechten.
        // We toestaan alleen wijzigingen aan de suffix (bijv. 10 -> 10a).
        if ($menuItem->number !== null && isset($attributes['number']) && (int) $attributes['number'] !== (int) $menuItem->number) {
            return response()->json([
                'message' => 'Het menu-nummer mag niet aangepast worden voor bestaande gerechten.',
                'errors' => [
                    'number' => ['Het menu-nummer mag niet aangepast worden voor bestaande gerechten.'],
                ],
            ], 422);
        }

        $menuItem->update($attributes);

        MenuItemUpdated::dispatch($menuItem->load('category'));

        return response()->json([
            'item' => $this->serializeItem($menuItem),
        ]);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        try {
            $menuItem->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Dit gerecht kan niet verwijderd worden omdat het al in bestellingen is gebruikt. Zet het gerecht in plaats daarvan op "Inactief" om het van de kaart te halen.',
                ], 422);
            }
            throw $e;
        }

        return response()->json(status: 204);
    }

    private function validatedAttributes(Request $request, ?MenuItem $item = null): array
    {
        return $request->validate([
            'menu_category_id' => ['required', Rule::exists('menu_categories', 'id')],
            'number' => [
                'nullable',
                'integer',
                'min:1',
                'max:9999',
                Rule::unique('menu_items')->where(fn ($query) => $query->where('suffix', $request->suffix))
                    ->ignore($item?->id),
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('menu_items')->where(fn ($query) => $query->where('number', $request->number))
                    ->ignore($item?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['required', 'boolean'],
        ], [
            'number.unique' => 'Deze combinatie van nummer en suffix bestaat al.',
            'suffix.unique' => 'Deze combinatie van nummer en suffix bestaat al.',
        ]);
    }

    private function serializeItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'menu_category_id' => $item->menu_category_id,
            'number' => $item->number,
            'suffix' => $item->suffix,
            'display_number' => trim(($item->number ?? '').($item->suffix ?? '')),
            'name' => $item->name,
            'description' => $item->description,
            'price' => (float) $item->price,
            'is_active' => (bool) $item->is_active,
            'category' => $item->category?->name ?? 'Overig',
            'category_sort_order' => (int) ($item->category?->sort_order ?? 999),
        ];
    }
}
