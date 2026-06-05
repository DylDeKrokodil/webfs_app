<?php

namespace App\Http\Controllers;

use App\Models\OrderLineNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderLineNoteSuggestionController extends Controller
{
    public function index(): JsonResponse
    {
        $suggestions = OrderLineNote::query()
            ->select([
                'normalized_note',
                DB::raw('MAX(note) as note'),
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('MAX(created_at) as last_used_at'),
            ])
            ->groupBy('normalized_note')
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->limit(10)
            ->get()
            ->map(fn (OrderLineNote $note): array => [
                'note' => $note->note,
                'usage_count' => (int) $note->usage_count,
            ]);

        return response()->json([
            'data' => $suggestions,
        ]);
    }
}
