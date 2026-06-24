<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTourProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_empty_tour_progress(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/tour-progress');

        $response->assertOk()
            ->assertJson([
                'progress' => [],
            ]);
    }

    public function test_admin_can_update_tour_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->patchJson('/api/admin/tour-progress/firstAdminLogin', [
                'tour_version' => 1,
                'current_step' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('progress.tour_key', 'firstAdminLogin')
            ->assertJsonPath('progress.tour_version', 1)
            ->assertJsonPath('progress.current_step', 2);

        $this->assertDatabaseHas('user_tour_progress', [
            'user_id' => $admin->id,
            'tour_key' => 'firstAdminLogin',
            'tour_version' => 1,
            'current_step' => 2,
        ]);
    }

    public function test_admin_can_complete_tour(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/tour-progress/firstAdminLogin/complete', [
                'tour_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('progress.tour_key', 'firstAdminLogin');

        $this->assertDatabaseHas('user_tour_progress', [
            'user_id' => $admin->id,
            'tour_key' => 'firstAdminLogin',
            'tour_version' => 1,
        ]);

        $this->assertNotNull($admin->fresh()->tourProgress ?? null);
    }

    public function test_cashier_cannot_access_admin_tour_progress(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->getJson('/api/admin/tour-progress')
            ->assertForbidden();
    }
}
