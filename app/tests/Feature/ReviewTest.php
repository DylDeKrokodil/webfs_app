<?php

namespace Tests\Feature;

use App\Models\ReviewInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_review_invite(): void
    {
        $invite = $this->createInvite();

        $this->getJson("/api/reviews/{$invite->token}")
            ->assertOk()
            ->assertJsonPath('data.token', $invite->token)
            ->assertJsonPath('data.table_code', '7')
            ->assertJsonPath('data.submitted', false);
    }

    public function test_customer_can_submit_review_once(): void
    {
        $invite = $this->createInvite();

        $this->postJson("/api/reviews/{$invite->token}", [
            'overall_score' => 5,
            'food_score' => 4,
            'service_score' => 5,
            'speed_score' => 4,
            'favorite_dish' => 'Babi pangang',
            'comment' => 'Goede service en lekker gegeten.',
            'contact_permission' => true,
        ])->assertCreated()
            ->assertJsonPath('message', 'Bedankt voor uw review.');

        $this->assertDatabaseHas('reviews', [
            'review_invite_id' => $invite->id,
            'overall_score' => 5,
            'favorite_dish' => 'Babi pangang',
            'contact_permission' => true,
        ]);
        $this->assertNotNull($invite->fresh()->submitted_at);

        $this->postJson("/api/reviews/{$invite->token}", [
            'overall_score' => 5,
            'food_score' => 5,
            'service_score' => 5,
            'speed_score' => 5,
        ])->assertUnprocessable();
    }

    public function test_review_scores_are_required_between_one_and_five(): void
    {
        $invite = $this->createInvite();

        $this->postJson("/api/reviews/{$invite->token}", [
            'overall_score' => 6,
            'food_score' => 0,
            'service_score' => 5,
            'speed_score' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['overall_score', 'food_score']);
    }

    private function createInvite(): ReviewInvite
    {
        return ReviewInvite::create([
            'token' => 'reviewtoken123',
            'table_code' => '7',
            'order_ids' => [1, 2],
            'order_fingerprint' => hash('sha256', '7|1,2'),
            'paid_at' => now(),
        ]);
    }
}
