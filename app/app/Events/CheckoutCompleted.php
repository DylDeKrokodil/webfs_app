<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CheckoutCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'table_code' => $this->order->table_code,
            'channel' => $this->order->channel,
            'total' => (float) $this->order->total,
            'paid_at' => $this->order->paid_at?->toIso8601String(),
        ];
    }
}
