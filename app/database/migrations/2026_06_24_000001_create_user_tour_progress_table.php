<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tour_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 80);
            $table->unsignedSmallInteger('tour_version')->default(1);
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key', 'tour_version']);
            $table->index(['user_id', 'completed_at', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tour_progress');
    }
};
