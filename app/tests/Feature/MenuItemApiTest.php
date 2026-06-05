<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_endpoint_returns_modern_menu_data(): void
    {
        $category = MenuCategory::create([
            'name' => 'Test categorie',
            'sort_order' => 999,
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_category_id' => $category->id,
            'number' => 901,
            'suffix' => 'A',
            'name' => 'Test gerecht',
            'description' => 'Voor API-controle',
            'price' => 12.50,
            'is_active' => true,
        ]);

        $this->getJson('/api/menu-items')
            ->assertOk()
            ->assertJsonFragment([
                'display_number' => '901A',
                'name' => 'Test gerecht',
                'category' => 'Test categorie',
            ]);
    }
}
