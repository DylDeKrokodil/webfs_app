<?php

namespace App\Http\Controllers\Admin;

use App\Events\CheckoutCompleted;
use App\Http\Controllers\Controller;
use App\Models\FavoriteMenuItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Support\OrderLineNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request, OrderLineNoteService $noteService): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            ...$noteService->validationRules(),
        ]);

        $orderLines = $noteService->prepareLines($validated['lines']);
        $quantities = $orderLines
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

        $order = DB::transaction(function () use ($menuItems, $orderLines, $noteService): Order {
            $total = $orderLines->reduce(
                fn (float $sum, array $line): float =>
                    $sum + ((float) $menuItems[$line['menu_item_id']]->price * $line['quantity']),
                0.0,
            );

            $order = Order::create([
                'channel' => 'takeaway',
                'status' => 'paid',
                'subtotal' => $total,
                'total' => $total,
                'paid_at' => now(),
            ]);

            $orderLines->each(function (array $line) use ($menuItems, $noteService, $order): void {
                $quantity = $line['quantity'];
                $menuItemId = $line['menu_item_id'];
                $menuItem = $menuItems[$menuItemId];
                $unitPrice = (float) $menuItem->price;

                $orderLine = $order->lines()->create([
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $quantity,
                ]);

                $noteService->createNotes($orderLine, $line['notes']);

                FavoriteMenuItem::firstOrCreate(
                    ['menu_item_id' => $menuItem->id],
                    ['count' => 0],
                )->increment('count', $quantity);
            });

            return $order;
        });

        CheckoutCompleted::dispatch($order);

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
