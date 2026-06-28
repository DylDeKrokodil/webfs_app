<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_invites', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('table_code');
            $table->json('order_ids');
            $table->string('order_fingerprint', 64)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['table_code', 'paid_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_invite_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_score');
            $table->unsignedTinyInteger('food_score');
            $table->unsignedTinyInteger('service_score');
            $table->unsignedTinyInteger('speed_score');
            $table->string('favorite_dish', 120)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('contact_permission')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('overall_score');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_invites');
    }
};
