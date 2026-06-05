<?php

namespace Tests\Feature;

use App\Models\TableAssistanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableAssistanceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_request_assistance_for_a_table(): void
    {
        $this->postJson('/api/tablet/tables/12/assistance-requests')
            ->assertCreated()
            ->assertJsonPath('message', 'Een ober komt zo naar uw tafel.')
            ->assertJsonPath('data.table_code', '12');

        $this->assertDatabaseHas('table_assistance_requests', [
            'table_code' => '12',
            'resolved_at' => null,
        ]);
    }

    public function test_open_assistance_request_is_returned_in_table_status(): void
    {
        $request = TableAssistanceRequest::create([
            'table_code' => '5',
        ]);

        $this->getJson('/api/tablet/tables/5/status')
            ->assertOk()
            ->assertJsonPath('data.assistance_request.id', $request->id)
            ->assertJsonPath('data.assistance_request.table_code', '5');
    }

    public function test_duplicate_open_assistance_request_is_not_created_for_same_table(): void
    {
        $firstResponse = $this->postJson('/api/tablet/tables/3/assistance-requests')
            ->assertCreated();

        $this->postJson('/api/tablet/tables/3/assistance-requests')
            ->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $this->assertSame(1, TableAssistanceRequest::query()->count());
    }

    public function test_admin_can_list_open_assistance_requests(): void
    {
        TableAssistanceRequest::create(['table_code' => '2']);
        TableAssistanceRequest::create([
            'table_code' => '4',
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->adminUser())
            ->getJson('/api/admin/table-assistance-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.table_code', '2')
            ->assertJsonPath('data.0.resolved_at', null);
    }

    public function test_admin_can_resolve_assistance_request(): void
    {
        $request = TableAssistanceRequest::create(['table_code' => '9']);

        $this->actingAs($this->adminUser())
            ->postJson("/api/admin/table-assistance-requests/{$request->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.table_code', '9');

        $this->assertNotNull($request->fresh()->resolved_at);
    }

    public function test_assistance_admin_routes_require_login(): void
    {
        $this->getJson('/api/admin/table-assistance-requests')
            ->assertUnauthorized();
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
