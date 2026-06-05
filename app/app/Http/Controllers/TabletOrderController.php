<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function history(int $tableNumber): JsonResponse
    {
        $orders = $this->openTableOrders($tableNumber)
            ->with(['lines.menuItem.category'])
            ->limit(self::MAX_ROUNDS_PER_TABLE)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'table_number' => (int) $order->table_code,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'lines' => $order->lines
                    ->filter(fn ($line): bool => $line->menuItem !== null)
                    ->map(fn ($line): array => [
                        'menu_item_id' => $line->menu_item_id,
                        'display_number' => trim(($line->menuItem->number ?? '').($line->menuItem->suffix ?? '')),
                        'name' => $line->menuItem->name,
                        'category' => $line->menuItem->category?->name ?? 'Overig',
                        'quantity' => $line->quantity,
                        'unit_price' => (float) $line->unit_price,
                        'current_price' => (float) $line->menuItem->price,
                        'is_active' => (bool) $line->menuItem->is_active,
                    ])
                    ->values(),
            ])
            ->filter(fn (array $order): bool => $order['lines']->isNotEmpty())
            ->values();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'integer', 'min:1', 'max:999'],
            'source_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $tableNumber = (int) $validated['table_number'];
        $status = $this->tableStatus($tableNumber);

        if (! $status['can_order']) {
            return response()->json([
                'message' => $status['message'],
                'status' => $status,
            ], 429);
        }

        $sourceOrderId = $this->validSourceOrderId($validated['source_order_id'] ?? null, $tableNumber);
        ['menuItems' => $menuItems, 'quantities' => $quantities] = $this->validatedOrderLines($validated['lines']);

        $order = DB::transaction(function () use ($menuItems, $quantities, $sourceOrderId, $validated): Order {
            $total = $quantities->reduce(
                fn (float $sum, int $quantity, int $menuItemId): float =>
                    $sum + ((float) $menuItems[$menuItemId]->price * $quantity),
                0.0,
            );

            $order = Order::create([
                'source_order_id' => $sourceOrderId,
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
        $orders = $this->openTableOrders($tableNumber)
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

    private function openTableOrders(int $tableNumber)
    {
        return Order::query()
            ->where('channel', 'tablet')
            ->where('status', 'submitted')
            ->whereNull('paid_at')
            ->where('table_code', (string) $tableNumber)
            ->latest();
    }

    private function validSourceOrderId(?int $sourceOrderId, int $tableNumber): ?int
    {
        if ($sourceOrderId === null) {
            return null;
        }

        $existsForTable = $this->openTableOrders($tableNumber)
            ->whereKey($sourceOrderId)
            ->exists();

        if (! $existsForTable) {
            throw ValidationException::withMessages([
                'source_order_id' => 'De herhaalbestelling hoort niet bij deze tafel.',
            ]);
        }

        return $sourceOrderId;
    }

    private function validatedOrderLines(array $lines): array
    {
        $quantities = collect($lines)
            ->groupBy('menu_item_id')
            ->map(fn (Collection $lines): int => (int) $lines->sum('quantity'));

        $menuItems = MenuItem::query()
            ->whereIn('id', $quantities->keys())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== $quantities->count()) {
            throw ValidationException::withMessages([
                'lines' => 'Een of meer gerechten zijn niet meer actief.',
            ]);
        }

        return [
            'menuItems' => $menuItems,
            'quantities' => $quantities,
        ];
    }
}
