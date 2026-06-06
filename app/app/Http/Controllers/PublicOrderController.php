<?php

namespace App\Http\Controllers;

use App\Events\CheckoutCompleted;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Orders\OrderQrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        return DB::transaction(function () use ($validated) {
            $tableCode = null;
            $attempts = 0;

            while (!$tableCode && $attempts < 10) {
                $candidate = 'WEB-' . Str::upper(Str::random(6));
                if (!Order::where('table_code', $candidate)->exists()) {
                    $tableCode = $candidate;
                }
                $attempts++;
            }

            if (!$tableCode) {
                throw new \Exception('Kon geen unieke bestelcode genereren. Probeer het opnieuw.');
            }

            $order = Order::create([
                'channel' => 'web',
                'status' => 'paid', // For this simulation, we'll mark it as paid immediately
                'paid_at' => now(),
                'subtotal' => 0,
                'total' => 0,
                // We'll use a token for secure access to the confirmation page
                'source_order_id' => null, 
                'table_code' => $tableCode,
            ]);

            $total = 0;
            foreach ($validated['items'] as $itemData) {
                $menuItem = MenuItem::findOrFail($itemData['id']);
                $lineTotal = $menuItem->price * $itemData['quantity'];
                
                OrderLine::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $menuItem->price,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $order->update([
                'subtotal' => $total,
                'total' => $total,
            ]);

            CheckoutCompleted::dispatch($order);

            return response()->json([
                'order_id' => $order->id,
                'token' => $order->table_code, // Using table_code as a simple token for now
            ], 201);
        });
    }

    public function show(Request $request, string $token, OrderQrCodeService $qrCodeService, \App\Services\TranslationService $translator): JsonResponse
    {
        $targetLang = $request->header('X-Locale', 'nl');
        $order = Order::where('table_code', $token)
            ->where('channel', 'web')
            ->with('lines.menuItem')
            ->firstOrFail();

        $lines = $order->lines->map(fn($line) => [
            'name' => $line->menuItem?->name,
            'number' => trim(($line->menuItem?->number ?? '').($line->menuItem?->suffix ?? '')),
            'quantity' => $line->quantity,
            'price' => (float) $line->unit_price,
        ]);

        if (strtolower($targetLang) !== 'nl') {
            $names = array_values(array_unique(array_filter($lines->pluck('name')->toArray())));
            $translatedMap = $translator->translateArray($names, $targetLang);
            $lookup = array_combine($names, $translatedMap);

            $lines = $lines->map(function($line) use ($lookup) {
                $line['name'] = $lookup[$line['name']] ?? $line['name'];
                return $line;
            });
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'total' => (float) $order->total,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'lines' => $lines,
            ],
            'qr_code' => $qrCodeService->generateForOrder($order),
        ]);
    }
}
