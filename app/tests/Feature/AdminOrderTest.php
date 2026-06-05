<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_order_stores_notes_on_order_lines(): void
    {
        $menuItem = $this->createMenuItem(price: 12.50);

        $this->actingAs($this->adminUser())
            ->postJson('/api/admin/orders', [
                'lines' => [
                    [
                        'menu_item_id' => $menuItem->id,
                        'quantity' => 2,
                        'notes' => ['Geen ui toevoegen', 'Extra sambal'],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Verkoop succesvol');

        $order = Order::query()->with('lines.notes')->firstOrFail();

        $this->assertCount(1, $order->lines);
        $this->assertSame(['Geen ui toevoegen', 'Extra sambal'], $order->lines->first()->notes->pluck('note')->all());
    }

    public function test_note_suggestions_are_generated_from_previous_orders(): void
    {
        $menuItem = $this->createMenuItem();

        foreach (range(1, 2) as $index) {
            $order = Order::create([
                'channel' => 'takeaway',
                'status' => 'paid',
                'subtotal' => 10,
                'total' => 10,
                'paid_at' => now(),
            ]);

            $line = $order->lines()->create([
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]);

            $line->notes()->create([
                'note' => 'Geen ui toevoegen',
                'normalized_note' => 'geen ui toevoegen',
            ]);
        }

        $this->getJson('/api/order-line-note-suggestions')
            ->assertOk()
            ->assertJsonPath('data.0.note', 'Geen ui toevoegen')
            ->assertJsonPath('data.0.usage_count', 2);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createMenuItem(float $price = 9.50): MenuItem
    {
        $category = MenuCategory::firstOrCreate([
            'name' => 'Admin order test',
        ], [
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => 801,
            'name' => 'Kassa gerecht',
            'price' => $price,
            'is_active' => true,
        ]);
    }
}
