<?php

namespace App\Events;

use App\Models\MenuItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuItemUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MenuItem $menuItem
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('menu-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MenuItemUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->menuItem->id,
            'is_active' => (bool) $this->menuItem->is_active,
            'number' => $this->menuItem->number,
            'suffix' => $this->menuItem->suffix,
            'name' => $this->menuItem->name,
            'price' => (float) $this->menuItem->price,
            'description' => $this->menuItem->description,
            'display_number' => trim(($this->menuItem->number ?? '').($this->menuItem->suffix ?? '')),
            'category' => $this->menuItem->category?->name ?? 'Overig',
            'category_sort_order' => (int) ($this->menuItem->category?->sort_order ?? 999),
        ];
    }
}
