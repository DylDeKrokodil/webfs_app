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

        // Determine grouping based on total days
        $daysDiff = $start->diffInDays($end);
        $grouping = match (true) {
            $daysDiff <= 31 => 'day',
            $daysDiff <= 182 => 'week',
            default => 'month',
        };

        // 4. Sales Trends
        $trendQuery = (clone $baseQuery);
        if ($grouping === 'week') {
            $trendQuery->select(DB::raw('YEARWEEK(orders.paid_at, 1) as period'), DB::raw('SUM(order_lines.line_total) as total_revenue'));
        } elseif ($grouping === 'month') {
            $trendQuery->select(DB::raw('DATE_FORMAT(orders.paid_at, "%Y-%m") as period'), DB::raw('SUM(order_lines.line_total) as total_revenue'));
        } else {
            $trendQuery->select(DB::raw('DATE(orders.paid_at) as period'), DB::raw('SUM(order_lines.line_total) as total_revenue'));
        }
        $trendsData = $trendQuery->groupBy('period')->get()->pluck('total_revenue', 'period');

        // 5. Review Trends
        $reviewQuery = Review::query()->whereBetween('created_at', [$start, $end]);
        if ($grouping === 'week') {
            $reviewQuery->select(DB::raw('YEARWEEK(created_at, 1) as period'), DB::raw('AVG(overall_score) as avg_score'));
        } elseif ($grouping === 'month') {
            $reviewQuery->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'), DB::raw('AVG(overall_score) as avg_score'));
        } else {
            $reviewQuery->select(DB::raw('DATE(created_at) as period'), DB::raw('AVG(overall_score) as avg_score'));
        }
        $reviewsData = $reviewQuery->groupBy('period')->get()->pluck('avg_score', 'period');

        $trends = [];
        $reviewTrends = [];
        $current = $start;

        while ($current->lte($end)) {
            $key = match ($grouping) {
                'week' => $current->format('oW'), // YEARWEEK format
                'month' => $current->format('Y-m'),
                default => $current->format('Y-m-d'),
            };

            $label = match ($grouping) {
                'week' => 'Week ' . $current->weekOfYear . ', ' . $current->year,
                'month' => $current->translatedFormat('F Y'),
                default => $current->translatedFormat('j M'),
            };

            // Only add unique keys for weeks/months
            if (!isset($trends[$key])) {
                $trends[$key] = [
                    'label' => $label,
                    'total_revenue' => (float) ($trendsData[$key] ?? 0),
                ];
                $reviewTrends[$key] = [
                    'label' => $label,
                    'avg_score' => isset($reviewsData[$key]) ? (float) $reviewsData[$key] : null,
                ];
            }

            $current = match ($grouping) {
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                default => $current->addDay(),
            };
        }

        return response()->json([
            'top_items' => $topItems,
            'channels' => $channels,
            'trends' => array_values($trends),
            'review_trends' => array_values($reviewTrends),
            'grouping' => $grouping,
        ]);
    }
}
