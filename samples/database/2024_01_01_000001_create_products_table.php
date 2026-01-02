<?php

/**
 * Products Migration
 * 
 * Creates the products table with all necessary columns
 * for enterprise retail management.
 * 
 * @note This is a portfolio sample, not production code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Product identification
            $table->string('barcode', 50)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0)->nullable();

            // Relationships
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('users')->nullOnDelete();

            // Media
            $table->string('image', 255)->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('barcode');
            $table->index('category_id');
            $table->index('supplier_id');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
