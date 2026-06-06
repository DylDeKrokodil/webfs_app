<?php

namespace App\Http\Controllers;

use App\Models\OrderLineNote;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderLineNoteSuggestionController extends Controller
{
    public function index(Request $request, TranslationService $translator): JsonResponse
    {
        $targetLang = $request->header('X-Locale', 'nl');
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
            ->get();

        $mapped = $suggestions->map(fn (OrderLineNote $note): array => [
            'note' => $note->note,
            'usage_count' => (int) $note->usage_count,
        ]);

        if (strtolower($targetLang) !== 'nl') {
            $notes = $mapped->pluck('note')->toArray();
            $translatedMap = $translator->translateArray($notes, $targetLang);
            $lookup = array_combine($notes, $translatedMap);

            $mapped = $mapped->map(function($item) use ($lookup) {
                $item['note'] = $lookup[$item['note']] ?? $item['note'];
                return $item;
            });
        }

        return response()->json([
            'data' => $mapped,
        ]);
    }
}
