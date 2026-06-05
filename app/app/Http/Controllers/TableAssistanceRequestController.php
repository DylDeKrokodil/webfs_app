<?php

namespace App\Http\Controllers;

use App\Models\TableAssistanceRequest;
use Illuminate\Http\JsonResponse;

class TableAssistanceRequestController extends Controller
{
    public function store(int $tableNumber): JsonResponse
    {
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
        }

        return response()->json([
            'message' => 'Een ober komt zo naar uw tafel.',
            'data' => [
                'id' => $request->id,
                'table_code' => $request->table_code,
                'created_at' => $request->created_at?->toIso8601String(),
            ],
        ], $request->wasRecentlyCreated ? 201 : 200);
    }
}
