<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FavoriteMenuItem;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $quantities = collect($validated['lines'])
            ->groupBy('menu_item_id')
            ->map(fn ($lines): int => (int) $lines->sum('quantity'));

        $menuItems = MenuItem::query()
            ->whereIn('id', $quantities->keys())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== $quantities->count()) {
            return response()->json([
                'message' => 'Een of meer gerechten zijn niet meer actief.',
            ], 422);
        }

        $order = DB::transaction(function () use ($menuItems, $quantities): Order {
            $total = $quantities->reduce(
                fn (float $sum, int $quantity, int $menuItemId): float =>
                    $sum + ((float) $menuItems[$menuItemId]->price * $quantity),
                0.0,
            );

            $order = Order::create([
                'channel' => 'takeaway',
                'status' => 'paid',
                'subtotal' => $total,
                'total' => $total,
                'paid_at' => now(),
            ]);

            $quantities->each(function (int $quantity, int $menuItemId) use ($menuItems, $order): void {
                $menuItem = $menuItems[$menuItemId];
                $unitPrice = (float) $menuItem->price;

                $order->lines()->create([
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $quantity,
                ]);

                FavoriteMenuItem::firstOrCreate(
                    ['menu_item_id' => $menuItem->id],
                    ['count' => 0],
                )->increment('count', $quantity);
            });

            return $order;
        });

        return response()->json([
            'message' => 'Verkoop succesvol',
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ], 201);
    }
}
