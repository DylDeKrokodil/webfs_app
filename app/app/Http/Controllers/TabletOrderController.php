<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TabletOrderController extends Controller
{
    private const MAX_ROUNDS_PER_TABLE = 5;
    private const ORDER_COOLDOWN_MINUTES = 10;

    public function status(int $tableNumber): JsonResponse
    {
        return response()->json([
            'data' => $this->tableStatus($tableNumber),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'integer', 'min:1', 'max:999'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $status = $this->tableStatus((int) $validated['table_number']);

        if (! $status['can_order']) {
            return response()->json([
                'message' => $status['message'],
                'status' => $status,
            ], 429);
        }

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

        $order = DB::transaction(function () use ($menuItems, $quantities, $validated): Order {
            $total = $quantities->reduce(
                fn (float $sum, int $quantity, int $menuItemId): float =>
                    $sum + ((float) $menuItems[$menuItemId]->price * $quantity),
                0.0,
            );

            $order = Order::create([
                'channel' => 'tablet',
                'status' => 'submitted',
                'table_code' => (string) $validated['table_number'],
                'subtotal' => $total,
                'total' => $total,
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
            });

            return $order;
        });

        return response()->json([
            'message' => 'Bestelling ontvangen',
            'order' => [
                'id' => $order->id,
                'table_number' => (int) $order->table_code,
                'status' => $order->status,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function tableStatus(int $tableNumber): array
    {
        $orders = Order::query()
            ->where('channel', 'tablet')
            ->where('table_code', (string) $tableNumber)
            ->whereDate('created_at', today())
            ->latest()
            ->get(['id', 'created_at']);

        $lastOrder = $orders->first();
        $roundsUsed = $orders->count();
        $cooldownEndsAt = $lastOrder?->created_at?->copy()->addMinutes(self::ORDER_COOLDOWN_MINUTES);
        $cooldownSeconds = $cooldownEndsAt?->isFuture()
            ? max(0, (int) now()->diffInSeconds($cooldownEndsAt, false))
            : 0;
        $hasRoundsAvailable = $roundsUsed < self::MAX_ROUNDS_PER_TABLE;

        $message = match (true) {
            ! $hasRoundsAvailable => 'Deze tafel heeft het maximum van 5 rondes bereikt.',
            $cooldownSeconds > 0 => 'Deze tafel kan maar 1x per 10 minuten bestellen.',
            default => 'Deze tafel kan bestellen.',
        };

        return [
            'table_number' => $tableNumber,
            'can_order' => $hasRoundsAvailable && $cooldownSeconds === 0,
            'rounds_used' => $roundsUsed,
            'max_rounds' => self::MAX_ROUNDS_PER_TABLE,
            'cooldown_seconds' => $cooldownSeconds,
            'next_order_at' => $cooldownSeconds > 0 ? $cooldownEndsAt?->toIso8601String() : null,
            'message' => $message,
        ];
    }
}
