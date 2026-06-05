<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invite = $this->invite($token);

        return response()->json([
            'data' => [
                'token' => $invite->token,
                'table_code' => $invite->table_code,
                'paid_at' => $invite->paid_at?->toIso8601String(),
                'submitted' => $invite->review()->exists(),
            ],
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $invite = $this->invite($token);

        if ($invite->review()->exists()) {
            throw ValidationException::withMessages([
                'token' => 'Deze review is al ingevuld. Bedankt!',
            ]);
        }

        $validated = $request->validate([
            'overall_score' => ['required', 'integer', 'min:1', 'max:5'],
            'food_score' => ['required', 'integer', 'min:1', 'max:5'],
            'service_score' => ['required', 'integer', 'min:1', 'max:5'],
            'speed_score' => ['required', 'integer', 'min:1', 'max:5'],
            'favorite_dish' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'contact_permission' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($invite, $validated): void {
            Review::create([
                ...$validated,
                'review_invite_id' => $invite->id,
                'contact_permission' => (bool) ($validated['contact_permission'] ?? false),
                'metadata' => [
                    'source' => 'receipt_qr',
                ],
            ]);

            $invite->update([
                'submitted_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Bedankt voor uw review.',
        ], 201);
    }

    private function invite(string $token): ReviewInvite
    {
        return ReviewInvite::where('token', $token)->firstOrFail();
    }
}
