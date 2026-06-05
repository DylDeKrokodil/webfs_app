<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableAssistanceRequest;
use App\Support\OrderLineNoteService;
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
            ->with(['lines.menuItem.category', 'lines.notes'])
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
                        'notes' => app(OrderLineNoteService::class)->serializeNotes($line),
                    ])
                    ->values(),
            ])
            ->filter(fn (array $order): bool => $order['lines']->isNotEmpty())
            ->values();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function store(Request $request, OrderLineNoteService $noteService): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'integer', 'min:1', 'max:999'],
            'source_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            ...$noteService->validationRules(),
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
        ['menuItems' => $menuItems, 'orderLines' => $orderLines] = $this->validatedOrderLines(
            $validated['lines'],
            $noteService,
        );

        $order = DB::transaction(function () use ($menuItems, $orderLines, $sourceOrderId, $validated, $noteService): Order {
            $total = $orderLines->reduce(
                fn (float $sum, array $line): float =>
                    $sum + ((float) $menuItems[$line['menu_item_id']]->price * $line['quantity']),
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
            'assistance_request' => $this->openAssistanceRequest($tableNumber),
            'message' => $message,
        ];
    }

    private function openAssistanceRequest(int $tableNumber): ?array
    {
        $request = TableAssistanceRequest::query()
            ->where('table_code', (string) $tableNumber)
            ->whereNull('resolved_at')
            ->latest()
            ->first();

        if ($request === null) {
            return null;
        }

        return [
            'id' => $request->id,
            'table_code' => $request->table_code,
            'created_at' => $request->created_at?->toIso8601String(),
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

    private function validatedOrderLines(array $lines, OrderLineNoteService $noteService): array
    {
        $orderLines = $noteService->prepareLines($lines);
        $quantities = $orderLines
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
            'orderLines' => $orderLines,
        ];
    }
}
