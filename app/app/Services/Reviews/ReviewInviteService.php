<?php

namespace App\Services\Reviews;

use App\Models\Order;
use App\Models\ReviewInvite;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReviewInviteService
{
    /**
     * @param  Collection<int, Order>  $orders
     */
    public function findOrCreateForReceipt(string $tableCode, Collection $orders): ReviewInvite
    {
        $orderIds = $orders
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $fingerprint = $this->fingerprint($tableCode, $orderIds);

        return ReviewInvite::firstOrCreate(
            ['order_fingerprint' => $fingerprint],
            [
                'token' => $this->uniqueToken(),
                'table_code' => $tableCode,
                'order_ids' => $orderIds,
                'paid_at' => $orders
                    ->pluck('paid_at')
                    ->filter()
                    ->sort()
                    ->last(),
            ],
        );
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function fingerprint(string $tableCode, array $orderIds): string
    {
        return hash('sha256', $tableCode.'|'.implode(',', $orderIds));
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(40);
        } while (ReviewInvite::where('token', $token)->exists());

        return $token;
    }
}
