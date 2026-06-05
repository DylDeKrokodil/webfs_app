<?php

namespace Tests\Feature;

use App\Models\GeneratedFile;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\DailySalesSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSalesSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_daily_sales_summary_for_given_date(): void
    {
        Storage::fake('local');

        $menuItem = $this->createMenuItem(price: 12.50);
        $this->createPaidOrder('2026-06-04 18:30:00', $menuItem, 2);
        $this->createPaidOrder('2026-06-05 18:30:00', $menuItem, 1);

        $this->artisan('sales:generate-daily-summary 2026-06-04')
            ->assertSuccessful();

        $file = GeneratedFile::query()
            ->where('type', DailySalesSummaryService::FILE_TYPE)
            ->firstOrFail();

        Storage::disk('local')->assertExists($file->path);

        $this->assertSame('verkoop-samenvatting-2026-06-04.xlsx', $file->original_name);
        $this->assertSame('2026-06-04', $file->metadata['date']);
        $this->assertSame(1, $file->metadata['orders_count']);
        $this->assertSame(2, $file->metadata['items_count']);
        $this->assertEquals(25.0, $file->metadata['gross_total']);
    }

    public function test_command_defaults_to_previous_day(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-05 00:10:00'));

        $menuItem = $this->createMenuItem(price: 10);
        $this->createPaidOrder('2026-06-04 21:00:00', $menuItem, 1);

        try {
            $this->artisan('sales:generate-daily-summary')
                ->assertSuccessful();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertDatabaseHas('generated_files', [
            'type' => DailySalesSummaryService::FILE_TYPE,
            'original_name' => 'verkoop-samenvatting-2026-06-04.xlsx',
        ]);
    }

    public function test_command_generates_summary_for_day_without_sales(): void
    {
        Storage::fake('local');

        $this->artisan('sales:generate-daily-summary 2026-06-04')
            ->assertSuccessful();

        $file = GeneratedFile::query()
            ->where('type', DailySalesSummaryService::FILE_TYPE)
            ->firstOrFail();

        Storage::disk('local')->assertExists($file->path);
        $this->assertSame(0, $file->metadata['orders_count']);
        $this->assertSame(0, $file->metadata['items_count']);
        $this->assertEquals(0.0, $file->metadata['gross_total']);
    }

    public function test_admin_can_list_and_download_sales_summaries(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sales-summaries/2026/verkoop-samenvatting-2026-06-04.xlsx', 'xlsx');

        $file = GeneratedFile::create([
            'type' => DailySalesSummaryService::FILE_TYPE,
            'path' => 'sales-summaries/2026/verkoop-samenvatting-2026-06-04.xlsx',
            'original_name' => 'verkoop-samenvatting-2026-06-04.xlsx',
            'generated_at' => now(),
            'metadata' => [
                'date' => '2026-06-04',
                'orders_count' => 3,
                'items_count' => 8,
                'gross_total' => 98.50,
            ],
        ]);

        $this->actingAs($this->adminUser())
            ->getJson('/api/admin/sales-summaries')
            ->assertOk()
            ->assertJsonPath('data.0.id', $file->id)
            ->assertJsonPath('data.0.date', '2026-06-04')
            ->assertJsonPath('data.0.download_url', route('admin.sales-summaries.download', $file));

        $this->actingAs($this->adminUser())
            ->get(route('admin.sales-summaries.download', $file))
            ->assertOk()
            ->assertDownload('verkoop-samenvatting-2026-06-04.xlsx');
    }

    public function test_sales_summaries_require_admin_login(): void
    {
        $this->getJson('/api/admin/sales-summaries')
            ->assertUnauthorized();
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createMenuItem(float $price): MenuItem
    {
        $category = MenuCategory::firstOrCreate(
            ['name' => 'Sales summary test'],
            ['sort_order' => 1, 'is_active' => true],
        );

        return MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => 901,
            'name' => 'Samenvatting gerecht',
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function createPaidOrder(string $paidAt, MenuItem $menuItem, int $quantity): Order
    {
        $total = (float) $menuItem->price * $quantity;

        $order = Order::create([
            'channel' => 'takeaway',
            'status' => 'paid',
            'subtotal' => $total,
            'total' => $total,
            'paid_at' => CarbonImmutable::parse($paidAt),
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
