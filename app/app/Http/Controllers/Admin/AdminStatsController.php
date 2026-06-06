<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        // 3. Sales by Category
        $categories = (clone $baseQuery)
            ->join('menu_items', 'order_lines.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->select(
                'menu_categories.name',
                DB::raw('SUM(order_lines.line_total) as total_revenue')
            )
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($item) => [
                'name' => $item->name,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        // 4. Sales Trends (Daily)
        $trends = (clone $baseQuery)
            ->select(
                DB::raw('DATE(orders.paid_at) as date'),
                DB::raw('SUM(order_lines.line_total) as total_revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        return response()->json([
            'top_items' => $topItems,
            'channels' => $channels,
            'categories' => $categories,
            'trends' => $trends,
        ]);
    }
}
