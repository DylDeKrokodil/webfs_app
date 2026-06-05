<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tablet_page_is_available(): void
    {
        $this->get('/tablet/1')
            ->assertOk();
    }

    public function test_tablet_page_requires_numeric_table_route(): void
    {
        $this->get('/tablet/abc')
            ->assertNotFound();
    }

    public function test_tablet_order_requires_a_valid_table_number(): void
    {
        $menuItem = $this->createMenuItem();

        $this->postJson('/api/tablet/orders', [
            'table_number' => 0,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }

    public function test_tablet_order_creates_submitted_order_for_table(): void
    {
        $menuItem = $this->createMenuItem(price: 12.50);

        $this->postJson('/api/tablet/orders', [
            'table_number' => 7,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 2],
            ],
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'message' => 'Bestelling ontvangen',
                'table_number' => 7,
                'status' => 'submitted',
                'total' => 25,
            ]);

        $order = Order::query()->with('lines')->firstOrFail();

        $this->assertSame('tablet', $order->channel);
        $this->assertSame('submitted', $order->status);
        $this->assertSame('7', $order->table_code);
        $this->assertCount(1, $order->lines);
        $this->assertSame(2, $order->lines->first()->quantity);
    }

    public function test_tablet_status_reports_table_rounds_and_cooldown(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $this->createTabletOrder(tableNumber: 4, minutesAgo: 3);

        $this->getJson('/api/tablet/tables/4/status')
            ->assertOk()
            ->assertJsonPath('data.table_number', 4)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.rounds_used', 1)
            ->assertJsonPath('data.max_rounds', 5)
            ->assertJsonPath('data.cooldown_seconds', 420);
    }

    public function test_tablet_order_blocks_second_order_within_ten_minutes(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $menuItem = $this->createMenuItem();

        $payload = [
            'table_number' => 3,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/tablet/orders', $payload)
            ->assertCreated();

        $this->postJson('/api/tablet/orders', $payload)
            ->assertStatus(429)
            ->assertJsonFragment([
                'message' => 'Deze tafel kan maar 1x per 10 minuten bestellen.',
            ]);
    }

    public function test_tablet_order_blocks_more_than_five_rounds(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $menuItem = $this->createMenuItem();

        foreach (range(1, 5) as $round) {
            $this->createTabletOrder(tableNumber: 8, minutesAgo: 60 + $round);
        }

        $this->postJson('/api/tablet/orders', [
            'table_number' => 8,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 1],
            ],
        ])
            ->assertStatus(429)
            ->assertJsonFragment([
                'message' => 'Deze tafel heeft het maximum van 5 rondes bereikt.',
            ]);
    }

    public function test_paid_table_orders_do_not_count_towards_new_table_rounds(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');

        $this->createTabletOrder(tableNumber: 1, minutesAgo: 20)
            ->update([
                'status' => 'paid',
                'paid_at' => now()->subMinutes(5),
            ]);
        $this->createTabletOrder(tableNumber: 1, minutesAgo: 15)
            ->update([
                'status' => 'paid',
                'paid_at' => now()->subMinutes(5),
            ]);

        $this->getJson('/api/tablet/tables/1/status')
            ->assertOk()
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.rounds_used', 0)
            ->assertJsonPath('data.cooldown_seconds', 0);
    }

    public function test_tablet_history_returns_open_orders_for_the_current_table(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $menuItem = $this->createMenuItem(price: 12.50);
        $visibleOrder = $this->createTabletOrder(tableNumber: 6, minutesAgo: 20);
        $visibleOrder->lines()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'unit_price' => 12.50,
            'line_total' => 25,
        ]);

        $this->createTabletOrder(tableNumber: 7, minutesAgo: 20);
        $this->createTabletOrder(tableNumber: 6, minutesAgo: 30)
            ->update([
                'status' => 'paid',
                'paid_at' => now()->subMinutes(5),
            ]);

        $this->getJson('/api/tablet/tables/6/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleOrder->id)
            ->assertJsonPath('data.0.lines.0.menu_item_id', $menuItem->id)
            ->assertJsonPath('data.0.lines.0.quantity', 2)
            ->assertJsonPath('data.0.lines.0.is_active', true);
    }

    public function test_tablet_repeat_order_records_source_order_for_the_same_table(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $menuItem = $this->createMenuItem(price: 8.25);
        $sourceOrder = $this->createTabletOrder(tableNumber: 9, minutesAgo: 20);

        $this->postJson('/api/tablet/orders', [
            'table_number' => 9,
            'source_order_id' => $sourceOrder->id,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $repeatOrder = Order::query()->latest('id')->firstOrFail();

        $this->assertSame($sourceOrder->id, $repeatOrder->source_order_id);
        $this->assertSame('9', $repeatOrder->table_code);
    }

    public function test_tablet_repeat_order_rejects_source_order_from_another_table(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');
        $menuItem = $this->createMenuItem();
        $sourceOrder = $this->createTabletOrder(tableNumber: 2, minutesAgo: 20);

        $this->postJson('/api/tablet/orders', [
            'table_number' => 3,
            'source_order_id' => $sourceOrder->id,
            'lines' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_order_id');
    }

    private function createMenuItem(float $price = 9.50, int $number = 701, string $name = 'Tablet gerecht'): MenuItem
    {
        $category = MenuCategory::firstOrCreate([
            'name' => 'Tablet test',
        ], [
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => $number,
            'name' => $name,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function createTabletOrder(int $tableNumber, int $minutesAgo): Order
    {
        $createdAt = now()->subMinutes($minutesAgo);

        $order = Order::create([
            'channel' => 'tablet',
            'status' => 'submitted',
            'table_code' => (string) $tableNumber,
            'subtotal' => 10,
            'total' => 10,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }
}
