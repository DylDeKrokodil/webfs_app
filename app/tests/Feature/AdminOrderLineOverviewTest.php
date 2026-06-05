<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderLineOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_line_overview_requires_admin_login(): void
    {
        $this->getJson('/api/admin/order-line-overview?start_date=2026-06-01&end_date=2026-06-05')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_paid_order_lines_for_date_range(): void
    {
        $babi = $this->createMenuItem(number: 101, name: 'Babi pangang', price: 12.50);
        $nasi = $this->createMenuItem(number: 201, name: 'Nasi goreng', price: 8.25);

        $this->createOrderLine(
            menuItem: $babi,
            quantity: 2,
            createdAt: '2026-06-02 10:15:00',
            channel: 'takeaway',
            status: 'paid',
        );
        $this->createOrderLine(
            menuItem: $nasi,
            quantity: 1,
            createdAt: '2026-06-03 22:45:00',
            channel: 'tablet',
            status: 'submitted',
            tableCode: '7',
        );
        $this->createOrderLine(
            menuItem: $babi,
            quantity: 1,
            createdAt: '2026-06-01 23:59:59',
            channel: 'takeaway',
            status: 'paid',
        );
        $this->createOrderLine(
            menuItem: $babi,
            quantity: 1,
            createdAt: '2026-06-03 12:00:00',
            channel: 'takeaway',
            status: 'draft',
        );

        $this->actingAs($this->adminUser())
            ->getJson('/api/admin/order-line-overview?start_date=2026-06-02&end_date=2026-06-03')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Babi pangang')
            ->assertJsonPath('data.0.quantity', 2)
            ->assertJsonPath('data.0.line_total', 25)
            ->assertJsonPath('summary.lines_count', 1)
            ->assertJsonPath('summary.items_count', 2)
            ->assertJsonPath('summary.total', 22.94)
            ->assertJsonPath('summary.gross_total', 25)
            ->assertJsonPath('summary.vat_amount', 2.06)
            ->assertJsonPath('summary.vat_percentage', 9);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createMenuItem(int $number, string $name, float $price): MenuItem
    {
        $category = MenuCategory::firstOrCreate(
            ['name' => 'Overview test'],
            ['sort_order' => 1, 'is_active' => true],
        );

        return MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => $number,
            'name' => $name,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function createOrderLine(
        MenuItem $menuItem,
        int $quantity,
        string $createdAt,
        string $channel,
        string $status,
        ?string $tableCode = null,
    ): void {
        $lineTotal = (float) $menuItem->price * $quantity;

        $order = Order::create([
            'channel' => $channel,
            'status' => $status,
            'table_code' => $tableCode,
            'subtotal' => $lineTotal,
            'total' => $lineTotal,
            'paid_at' => $status === 'paid' ? $createdAt : null,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $order->lines()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => $quantity,
            'unit_price' => (float) $menuItem->price,
            'line_total' => $lineTotal,
        ]);
    }
}
