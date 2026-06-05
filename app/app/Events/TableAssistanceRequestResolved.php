<?php

namespace App\Events;

use App\Models\TableAssistanceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableAssistanceRequestResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TableAssistanceRequest $request
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TableAssistanceRequestResolved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
        ];
    }
}
