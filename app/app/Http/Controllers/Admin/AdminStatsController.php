<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $start = CarbonImmutable::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();

        $baseQuery = OrderLine::query()
            ->join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.status', 'paid')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$start, $end]);

        // 1. Top Selling Items
        $topItems = (clone $baseQuery)
            ->join('menu_items', 'order_lines.menu_item_id', '=', 'menu_items.id')
            ->select(
                'menu_items.name',
                'menu_items.number',
                'menu_items.suffix',
                DB::raw('SUM(order_lines.quantity) as total_quantity'),
                DB::raw('SUM(order_lines.line_total) as total_revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.number', 'menu_items.suffix')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'name' => $item->name,
                'display_number' => trim(($item->number ?? '').($item->suffix ?? '')),
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        // 2. Sales per Channel
        $channels = (clone $baseQuery)
            ->select(
                'orders.channel',
                DB::raw('SUM(order_lines.line_total) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('orders.channel')
            ->get()
            ->map(fn($item) => [
                'channel' => $item->channel,
                'total_revenue' => (float) $item->total_revenue,
                'order_count' => (int) $item->order_count,
            ]);

        // 4. Sales Trends (Daily)
        $trendsData = (clone $baseQuery)
            ->select(
                DB::raw('DATE(orders.paid_at) as date'),
                DB::raw('SUM(order_lines.line_total) as total_revenue')
            )
            ->groupBy('date')
            ->get()
            ->pluck('total_revenue', 'date');

        // 5. Review Trends (Daily Average)
        $reviewsData = Review::query()
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('AVG(overall_score) as avg_score')
            )
            ->groupBy('date')
            ->get()
            ->pluck('avg_score', 'date');

        $trends = [];
        $reviewTrends = [];
        $current = $start;
        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            
            $trends[] = [
                'date' => $date,
                'total_revenue' => (float) ($trendsData[$date] ?? 0),
            ];

            $reviewTrends[] = [
                'date' => $date,
                'avg_score' => isset($reviewsData[$date]) ? (float) $reviewsData[$date] : null,
            ];

            $current = $current->addDay();
        }

        return response()->json([
            'top_items' => $topItems,
            'channels' => $channels,
            'trends' => $trends,
            'review_trends' => $reviewTrends,
        ]);
    }
}
