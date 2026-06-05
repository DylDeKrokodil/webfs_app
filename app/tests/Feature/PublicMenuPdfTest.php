<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_current_menu_pdf_with_active_promotions(): void
    {
        $category = MenuCategory::create([
            'name' => 'Soepen',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => 12,
            'suffix' => 'A',
            'name' => 'Tomatensoep',
            'description' => 'Vegetarische soep',
            'price' => 6.75,
            'is_active' => true,
        ]);

        $promotion = Promotion::create([
            'title' => 'Lunchactie',
            'description' => 'Alleen deze week.',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'discount_type' => 'fixed_amount',
            'is_active' => true,
        ]);

        $promotion->menuItems()->attach($menuItem->id, [
            'discount_amount' => 1.50,
        ]);

        $response = $this->get('/menukaart.pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="menukaart-de-gouden-draak.pdf"');

        $this->assertStringStartsWith('%PDF', $response->baseResponse->getContent());
    }
}
