<?php

namespace App\Http\Controllers;

use App\Events\TableAssistanceRequestCreated;
use App\Models\TableAssistanceRequest;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableAssistanceRequestController extends Controller
{
    public function store(Request $request, int $tableNumber, TranslationService $translator): JsonResponse
    {
        $targetLang = $request->header('X-Locale', 'nl');
        $tableCode = (string) $tableNumber;

        $request = TableAssistanceRequest::query()
            ->where('table_code', $tableCode)
            ->whereNull('resolved_at')
            ->latest()
            ->first();

        if ($request === null) {
            $request = TableAssistanceRequest::create([
                'table_code' => $tableCode,
            ]);

            \Illuminate\Support\Facades\Log::info("Broadcasting assistance request for table {$tableCode}");
            TableAssistanceRequestCreated::dispatch($request);
        }

        $message = 'Een ober komt zo naar uw tafel.';
        if (strtolower($targetLang) !== 'nl') {
            $message = $translator->translate($message, $targetLang);
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $request->id,
                'table_code' => $request->table_code,
                'created_at' => $request->created_at?->toIso8601String(),
            ],
        ], $request->wasRecentlyCreated ? 201 : 200);
    }
}
