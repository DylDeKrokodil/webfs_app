<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = MenuCategory::create(['name' => 'Test Cat', 'sort_order' => 1]);
    }

    public function test_admin_can_create_menu_item_with_number(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/menu-items', [
                'menu_category_id' => $this->category->id,
                'number' => 10,
                'name' => 'Nieuw Item',
                'price' => 15.00,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('menu_items', ['number' => 10, 'name' => 'Nieuw Item']);
    }

    public function test_admin_can_add_suffix_to_existing_item(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'number' => 10,
            'name' => 'Item 10',
            'price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/menu-items/{$item->id}", [
                'menu_category_id' => $this->category->id,
                'number' => 10,
                'suffix' => 'a',
                'name' => 'Item 10a',
                'price' => 10.00,
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'number' => 10, 'suffix' => 'a']);
    }

    public function test_admin_cannot_change_base_number_of_existing_item(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'number' => 10,
            'name' => 'Item 10',
            'price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/menu-items/{$item->id}", [
                'menu_category_id' => $this->category->id,
                'number' => 11, // Changing 10 to 11
                'name' => 'Item 11',
                'price' => 10.00,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number']);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'number' => 10]);
    }

    public function test_admin_can_create_new_item_with_existing_number_and_different_suffix(): void
    {
        MenuItem::create([
            'menu_category_id' => $this->category->id,
            'number' => 10,
            'name' => 'Item 10',
            'price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/menu-items', [
                'menu_category_id' => $this->category->id,
                'number' => 10,
                'suffix' => 'b',
                'name' => 'Item 10b',
                'price' => 12.00,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('menu_items', ['number' => 10, 'suffix' => 'b', 'name' => 'Item 10b']);
    }

    public function test_admin_cannot_create_duplicate_number_suffix_combination(): void
    {
        MenuItem::create([
            'menu_category_id' => $this->category->id,
            'number' => 10,
            'suffix' => 'a',
            'name' => 'Item 10a',
            'price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/menu-items', [
                'menu_category_id' => $this->category->id,
                'number' => 10,
                'suffix' => 'a',
                'name' => 'Nieuw Item 10a',
                'price' => 15.00,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number', 'suffix']);
    }

    public function test_admin_gets_friendly_error_when_deleting_item_with_orders(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'number' => 1,
            'name' => 'Item met bestelling',
            'price' => 10.00,
            'is_active' => true,
        ]);

        // Handmatig een order_line aanmaken om de constraint te triggeren
        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'id' => 999,
            'subtotal' => 10,
            'total' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        \Illuminate\Support\Facades\DB::table('order_lines')->insert([
            'order_id' => 999,
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/menu-items/{$item->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Dit gerecht kan niet verwijderd worden omdat het al in bestellingen is gebruikt. Zet het gerecht in plaats daarvan op "Inactief" om het van de kaart te halen.',
        ]);
    }
}
