<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserTourProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $progress = UserTourProgress::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn (UserTourProgress $progress): array => $this->serialize($progress))
            ->values();

        return response()->json([
            'progress' => $progress,
        ]);
    }

    public function update(Request $request, string $tourKey): JsonResponse
    {
        $attributes = $request->validate([
            'tour_version' => ['required', 'integer', 'min:1', 'max:65535'],
            'current_step' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $progress = $this->progressFor($request, $tourKey, (int) $attributes['tour_version']);
        $progress->update([
            'current_step' => (int) $attributes['current_step'],
        ]);

        return response()->json([
            'progress' => $this->serialize($progress->refresh()),
        ]);
    }

    public function complete(Request $request, string $tourKey): JsonResponse
    {
        $attributes = $request->validate([
            'tour_version' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $progress = $this->progressFor($request, $tourKey, (int) $attributes['tour_version']);
        $progress->update([
            'completed_at' => now(),
            'dismissed_at' => null,
        ]);

        return response()->json([
            'progress' => $this->serialize($progress->refresh()),
        ]);
    }

    public function dismiss(Request $request, string $tourKey): JsonResponse
    {
        $attributes = $request->validate([
            'tour_version' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $progress = $this->progressFor($request, $tourKey, (int) $attributes['tour_version']);
        $progress->update([
            'dismissed_at' => now(),
        ]);

        return response()->json([
            'progress' => $this->serialize($progress->refresh()),
        ]);
    }

    private function progressFor(Request $request, string $tourKey, int $tourVersion): UserTourProgress
    {
        return UserTourProgress::firstOrCreate([
            'user_id' => $request->user()->id,
            'tour_key' => $tourKey,
            'tour_version' => $tourVersion,
        ]);
    }

    private function serialize(UserTourProgress $progress): array
    {
        return [
            'tour_key' => $progress->tour_key,
            'tour_version' => $progress->tour_version,
            'current_step' => $progress->current_step,
            'completed_at' => $progress->completed_at?->toISOString(),
            'dismissed_at' => $progress->dismissed_at?->toISOString(),
        ];
    }
}
