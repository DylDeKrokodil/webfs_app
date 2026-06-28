<?php

namespace App\Services\Reports;

use App\Models\GeneratedFile;
use App\Models\Order;
use App\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DailySalesSummaryService
{
    public const FILE_TYPE = 'daily_sales_summary';

    private const VAT_PERCENTAGE = 9;

    public function __construct(
        private readonly DailySalesSummaryWorkbook $workbook,
    ) {}

    public function generate(CarbonImmutable $date): GeneratedFile
    {
        $date = $date->startOfDay();
        $summary = $this->buildSummary($date);
        $relativePath = $this->relativePath($date);
        $absolutePath = Storage::disk('local')->path($relativePath);

        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $this->workbook->write($summary, $absolutePath);

        return GeneratedFile::updateOrCreate(
            [
                'type' => self::FILE_TYPE,
                'path' => $relativePath,
            ],
            [
                'original_name' => $this->filename($date),
                'generated_by' => null,
                'generated_at' => now(),
                'metadata' => [
                    'date' => $date->toDateString(),
                    'orders_count' => $summary['totals']['orders_count'],
                    'items_count' => $summary['totals']['items_count'],
                    'gross_total' => $summary['totals']['gross_total'],
                    'net_total' => $summary['totals']['net_total'],
                    'vat_amount' => $summary['totals']['vat_amount'],
                    'vat_percentage' => self::VAT_PERCENTAGE,
                ],
            ],
        );
    }

    /**
     * @return array{
     *     date: string,
     *     generated_at: string,
     *     totals: array{orders_count: int, items_count: int, gross_total: float, net_total: float, vat_amount: float, average_order_value: float, vat_percentage: int},
     *     channels: list<array{channel: string, orders_count: int, gross_total: float}>,
     *     items: list<array{display_number: string, name: string, category: string, quantity: int, gross_total: float}>
     * }
     */
    public function buildSummary(CarbonImmutable $date): array
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $orders = Order::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'channel', 'total']);

        $grossTotal = round((float) $orders->sum('total'), 2);
        $netTotal = round($grossTotal / (1 + (self::VAT_PERCENTAGE / 100)), 2);
        $vatAmount = round($grossTotal - $netTotal, 2);
        $ordersCount = $orders->count();

        return [
            'date' => $date->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'orders_count' => $ordersCount,
                'items_count' => $this->soldItemsCount($orders->pluck('id')),
                'gross_total' => $grossTotal,
                'net_total' => $netTotal,
                'vat_amount' => $vatAmount,
                'average_order_value' => $ordersCount > 0 ? round($grossTotal / $ordersCount, 2) : 0.0,
                'vat_percentage' => self::VAT_PERCENTAGE,
            ],
            'channels' => $this->channelRows($orders),
            'items' => $this->itemRows($orders->pluck('id')),
        ];
    }

    public function filename(CarbonImmutable $date): string
    {
        return sprintf('verkoop-samenvatting-%s.xlsx', $date->toDateString());
    }

    private function relativePath(CarbonImmutable $date): string
    {
        return sprintf(
            'sales-summaries/%s/%s',
            $date->format('Y'),
            $this->filename($date),
        );
    }

    /**
     * @param  Collection<int, int>  $orderIds
     */
    private function soldItemsCount(Collection $orderIds): int
    {
        if ($orderIds->isEmpty()) {
            return 0;
        }

        return (int) OrderLine::query()
            ->whereIn('order_id', $orderIds)
            ->sum('quantity');
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{channel: string, orders_count: int, gross_total: float}>
     */
    private function channelRows(Collection $orders): array
    {
        return $orders
            ->groupBy('channel')
            ->map(fn (Collection $channelOrders, string $channel): array => [
                'channel' => $channel,
                'orders_count' => $channelOrders->count(),
                'gross_total' => round((float) $channelOrders->sum('total'), 2),
            ])
            ->sortBy('channel', SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $orderIds
     * @return list<array{display_number: string, name: string, category: string, quantity: int, gross_total: float}>
     */
    private function itemRows(Collection $orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        return OrderLine::query()
            ->with(['menuItem:id,menu_category_id,number,suffix,name', 'menuItem.category:id,name'])
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy(fn (OrderLine $line): int => $line->menu_item_id)
            ->map(function (Collection $lines): array {
                /** @var OrderLine $firstLine */
                $firstLine = $lines->first();
                $menuItem = $firstLine->menuItem;

                return [
                    'display_number' => trim(($menuItem?->number ?? '').($menuItem?->suffix ?? '')),
                    'name' => $menuItem?->name ?? 'Onbekend gerecht',
                    'category' => $menuItem?->category?->name ?? 'Onbekend',
                    'quantity' => (int) $lines->sum('quantity'),
                    'gross_total' => round((float) $lines->sum('line_total'), 2),
                ];
            })
            ->sortByDesc('gross_total')
            ->values()
            ->all();
    }
}
