<?php

namespace App\Http\Controllers\Admin;

use App\Events\TableCheckoutInitiated;
use App\Http\Controllers\Controller;
use App\Models\FavoriteMenuItem;
use App\Models\Order;
use App\Services\Reviews\ReceiptQrCodeService;
use App\Services\Reviews\ReviewInviteService;
use App\Support\OrderLineNoteService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TableReceiptController extends Controller
{
    private const RECEIPT_WIDTH_MM = 85;

    private const RECEIPT_HEIGHT_MM = 130;

    public function index(): JsonResponse
    {
        $orders = $this->activeTableOrders()->get();

        return response()->json([
            'tables' => $orders
                ->groupBy('table_code')
                ->sortKeysUsing(
                    fn (string $left, string $right): int => ((int) $left <=> (int) $right) ?: strcmp($left, $right),
                )
                ->map(fn (Collection $tableOrders, string $tableCode): array => $this->serializeTable(
                    $tableCode,
                    $tableOrders,
                ))
                ->values(),
        ]);
    }

    public function checkout(Request $request, string $tableCode): JsonResponse
    {
        $orders = DB::transaction(function () use ($tableCode): Collection {
            $orders = $this->activeTableOrders()
                ->where('table_code', $tableCode)
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return $orders;
            }

            $paidAt = now();
            $orderIds = $orders->pluck('id');

            Order::query()
                ->whereIn('id', $orderIds)
                ->update([
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);

            $orders->each(function (Order $order) use ($paidAt): void {
                $order->forceFill([
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                ]);

                $order->lines->each(function ($line): void {
                    FavoriteMenuItem::firstOrCreate(
                        ['menu_item_id' => $line->menu_item_id],
                        ['count' => 0],
                    )->increment('count', $line->quantity);
                });
            });

            return $orders;
        });

        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'Deze tafel heeft geen openstaande bestellingen.',
            ], 404);
        }

        $receiptUrl = route('admin.table-receipts.pdf', [
            'tableCode' => $tableCode,
            'orders' => $orders->pluck('id')->implode(','),
        ]);

        $reviewInvite = app(ReviewInviteService::class)->findOrCreateForReceipt($tableCode, $orders);
        $totalAmount = $orders->sum('total');

        TableCheckoutInitiated::dispatch(
            $tableCode,
            $reviewInvite->token,
            (float) $totalAmount
        );

        return response()->json([
            'message' => "Tafel {$tableCode} is afgerekend.",
            'receipt_url' => $receiptUrl,
            'table' => $this->serializeTable($tableCode, $orders),
        ]);
    }

    public function pdf(Request $request, string $tableCode): Response
    {
        $orderIds = $this->parseOrderIds((string) $request->query('orders', ''));

        abort_if($orderIds === [], 404);

        $orders = Order::query()
            ->with(['lines.menuItem', 'lines.notes'])
            ->whereIn('id', $orderIds)
            ->where('channel', 'tablet')
            ->where('table_code', $tableCode)
            ->orderBy('created_at')
            ->get();

        abort_if($orders->count() !== count($orderIds), 404);

        $receipt = $this->serializeTable($tableCode, $orders);
        $receipt['paid_at'] = $orders
            ->pluck('paid_at')
            ->filter()
            ->sort()
            ->last()
            ?->format('d-m-Y H:i');
        $receipt['order_ids'] = $orders->pluck('id')->all();

        $reviewInvite = app(ReviewInviteService::class)->findOrCreateForReceipt($tableCode, $orders);
        $reviewUrl = route('reviews.show', ['token' => $reviewInvite->token]);

        $html = view('pdf.table-receipt', [
            'receipt' => $receipt,
            'reviewUrl' => $reviewUrl,
            'reviewQrCode' => app(ReceiptQrCodeService::class)->dataUri($reviewUrl),
        ])->render();

        $options = new Options;
        $options->set('chroot', public_path());
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([
            0,
            0,
            $this->millimetersToPoints(self::RECEIPT_WIDTH_MM),
            $this->millimetersToPoints(self::RECEIPT_HEIGHT_MM),
        ]);
        $dompdf->render();

        $filename = sprintf('rekening-tafel-%s-%s.pdf', $tableCode, now()->format('Ymd-His'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function activeTableOrders()
    {
        return Order::query()
            ->with(['lines.menuItem', 'lines.notes'])
            ->where('channel', 'tablet')
            ->where('status', 'submitted')
            ->whereNull('paid_at')
            ->whereNotNull('table_code')
            ->orderBy('table_code')
            ->orderBy('created_at');
    }

    private function serializeTable(string $tableCode, Collection $orders): array
    {
        $orderedByDate = $orders->sortBy('created_at');
        $lines = $orders
            ->flatMap->lines
            ->groupBy(function ($line): string {
                $notes = app(OrderLineNoteService::class)->serializeNotes($line);

                return implode(':', [
                    $line->menu_item_id,
                    number_format((float) $line->unit_price, 2, '.', ''),
                    implode("\n", $notes),
                ]);
            })
            ->map(function (Collection $lines): array {
                $firstLine = $lines->first();
                $menuItem = $firstLine->menuItem;
                $quantity = (int) $lines->sum('quantity');
                $unitPrice = (float) $firstLine->unit_price;

                return [
                    'menu_item_id' => $firstLine->menu_item_id,
                    'display_number' => trim(($menuItem?->number ?? '').($menuItem?->suffix ?? '')),
                    'name' => $menuItem?->name ?? 'Onbekend gerecht',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'notes' => app(OrderLineNoteService::class)->serializeNotes($firstLine),
                ];
            })
            ->sortBy('display_number', SORT_NATURAL)
            ->values();

        return [
            'table_code' => $tableCode,
            'orders_count' => $orders->count(),
            'items_count' => (int) $lines->sum('quantity'),
            'subtotal' => (float) $lines->sum('line_total'),
            'total' => (float) $lines->sum('line_total'),
            'first_order_at' => $orderedByDate->first()?->created_at?->toIso8601String(),
            'last_order_at' => $orderedByDate->last()?->created_at?->toIso8601String(),
            'lines' => $lines->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private function parseOrderIds(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function millimetersToPoints(int $millimeters): float
    {
        return $millimeters * 72 / 25.4;
    }
}
