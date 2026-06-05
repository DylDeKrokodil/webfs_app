<?php

namespace App\Http\Controllers\Admin;

use App\Events\TableAssistanceRequestResolved;
use App\Http\Controllers\Controller;
use App\Models\TableAssistanceRequest;
use Illuminate\Http\JsonResponse;

class TableAssistanceRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = TableAssistanceRequest::query()
            ->whereNull('resolved_at')
            ->oldest()
            ->get()
            ->map(fn (TableAssistanceRequest $request): array => $this->serialize($request))
            ->values();

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function resolve(TableAssistanceRequest $tableAssistanceRequest): JsonResponse
    {
        if ($tableAssistanceRequest->resolved_at === null) {
            $tableAssistanceRequest->update([
                'resolved_at' => now(),
            ]);

            TableAssistanceRequestResolved::dispatch($tableAssistanceRequest);
        }

        return response()->json([
            'message' => "Hulpvraag voor tafel {$tableAssistanceRequest->table_code} is afgemeld.",
            'data' => $this->serialize($tableAssistanceRequest),
        ]);
    }

    private function serialize(TableAssistanceRequest $request): array
    {
        return [
            'id' => $request->id,
            'table_code' => $request->table_code,
            'created_at' => $request->created_at?->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ];
    }
}
