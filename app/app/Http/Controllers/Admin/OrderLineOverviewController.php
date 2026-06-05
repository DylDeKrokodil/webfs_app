<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderLineOverviewController extends Controller
{
    private const VAT_PERCENTAGE = 9;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $start = CarbonImmutable::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();

        $lines = OrderLine::query()
            ->with(['menuItem:id,number,suffix,name', 'order:id,channel,status,table_code,created_at'])
            ->whereHas('order', function ($query) use ($start, $end): void {
                $query
                    ->where('status', 'paid')
                    ->whereNotNull('paid_at')
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->orderBy('orders.created_at')
            ->orderBy('order_lines.id')
            ->select('order_lines.*')
            ->get()
            ->map(function (OrderLine $line): array {
                $menuItem = $line->menuItem;

                return [
                    'id' => $line->id,
                    'order_id' => $line->order_id,
                    'date' => $line->order?->created_at?->toIso8601String(),
                    'channel' => $line->order?->channel,
                    'status' => $line->order?->status,
                    'table_code' => $line->order?->table_code,
                    'display_number' => trim(($menuItem?->number ?? '').($menuItem?->suffix ?? '')),
                    'name' => $menuItem?->name ?? 'Onbekend gerecht',
                    'unit_price' => (float) $line->unit_price,
                    'quantity' => (int) $line->quantity,
                    'line_total' => (float) $line->line_total,
                ];
            });

        $grossTotal = (float) $lines->sum('line_total');
        $netTotal = round($grossTotal / (1 + (self::VAT_PERCENTAGE / 100)), 2);
        $vatAmount = round($grossTotal - $netTotal, 2);

        return response()->json([
            'data' => $lines,
            'summary' => [
                'lines_count' => $lines->count(),
                'items_count' => (int) $lines->sum('quantity'),
                'total' => $netTotal,
                'gross_total' => $grossTotal,
                'vat_amount' => $vatAmount,
                'vat_percentage' => self::VAT_PERCENTAGE,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ],
        ]);
    }
}
