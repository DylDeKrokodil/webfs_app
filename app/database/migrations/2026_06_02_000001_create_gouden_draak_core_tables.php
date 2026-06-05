<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('number')->nullable();
            $table->string('suffix', 10)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->unsignedBigInteger('legacy_menu_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('legacy_menu_id');
            $table->unique(['number', 'suffix']);
            $table->index(['menu_category_id', 'is_active']);
            $table->index('name');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('discount_type')->default('fixed_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['promotion_id', 'menu_item_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('channel')->default('cashdesk');
            $table->string('status')->default('draft');
            $table->string('table_code')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index('created_at');
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index(['order_id', 'menu_item_id']);
        });

        Schema::create('order_line_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->string('normalized_note')->index();
            $table->timestamps();
        });

        Schema::create('generated_files', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'generated_at']);
        });

        Schema::create('api_cache', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('cache_key');
            $table->json('payload');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['source', 'cache_key']);
            $table->index('expires_at');
        });

        Schema::create('favorite_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_menu_items');
        Schema::dropIfExists('api_cache');
        Schema::dropIfExists('generated_files');
        Schema::dropIfExists('order_line_notes');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promotion_items');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};

