<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTableReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_receipts_require_admin_login(): void
    {
        $this->getJson('/api/admin/table-receipts')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_active_unpaid_tables(): void
    {
        $menuItem = $this->createMenuItem(number: 101, name: 'Babi pangang', price: 12.50);
        $this->createTabletOrder(tableCode: '3', menuItem: $menuItem, quantity: 2);
        $this->createTabletOrder(tableCode: '3', menuItem: $menuItem, quantity: 1);
        $this->createTabletOrder(tableCode: '4', menuItem: $menuItem, quantity: 1, status: 'paid');

        $this->actingAs($this->adminUser())
            ->getJson('/api/admin/table-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'tables')
            ->assertJsonPath('tables.0.table_code', '3')
            ->assertJsonPath('tables.0.orders_count', 2)
            ->assertJsonPath('tables.0.items_count', 3)
            ->assertJsonPath('tables.0.total', 37.5)
            ->assertJsonPath('tables.0.lines.0.name', 'Babi pangang');
    }

    public function test_admin_checkout_marks_table_paid_and_returns_receipt_url(): void
    {
        $menuItem = $this->createMenuItem();
        $order = $this->createTabletOrder(tableCode: '8', menuItem: $menuItem, quantity: 2);

        $this->actingAs($this->adminUser())
            ->postJson('/api/admin/table-receipts/8/checkout')
            ->assertOk()
            ->assertJsonPath('table.table_code', '8')
            ->assertJsonPath('table.total', 19)
            ->assertJsonPath('receipt_url', route('admin.table-receipts.pdf', [
                'tableCode' => '8',
                'orders' => (string) $order->id,
            ]));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_admin_can_download_pdf_receipt_for_35_products(): void
    {
        $orderIds = [];

        foreach (range(1, 35) as $index) {
            $menuItem = $this->createMenuItem(
                number: 200 + $index,
                name: "PDF gerecht {$index}",
                price: 4 + $index / 10,
            );

            $orderIds[] = $this->createTabletOrder(
                tableCode: '12',
                menuItem: $menuItem,
                quantity: 1,
                status: 'paid',
            )->id;
        }

        $response = $this->actingAs($this->adminUser())
            ->get('/admin/table-receipts/12.pdf?orders='.implode(',', $orderIds));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->baseResponse->getContent());
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createMenuItem(
        int $number = 701,
        string $name = 'Tablet gerecht',
        float $price = 9.50,
    ): MenuItem {
        $category = MenuCategory::firstOrCreate(
            ['name' => 'Receipt test'],
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

    private function createTabletOrder(
        string $tableCode,
        MenuItem $menuItem,
        int $quantity,
        string $status = 'submitted',
    ): Order {
        $total = (float) $menuItem->price * $quantity;

        $order = Order::create([
            'channel' => 'tablet',
            'status' => $status,
            'table_code' => $tableCode,
            'subtotal' => $total,
            'total' => $total,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        $order->lines()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => $quantity,
            'unit_price' => (float) $menuItem->price,
            'line_total' => $total,
        ]);

        return $order;
    }
}
