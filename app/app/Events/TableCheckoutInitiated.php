<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableCheckoutInitiated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tableCode,
        public string $reviewToken,
        public float $totalAmount
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-notifications'),
            new Channel("table.{$this->tableCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TableCheckoutInitiated';
    }

    public function broadcastWith(): array
    {
        return [
            'table_code' => $this->tableCode,
            'review_token' => $this->reviewToken,
            'review_url' => route('reviews.show', ['token' => $this->reviewToken]),
            'total_amount' => $this->totalAmount,
        ];
    }
}
