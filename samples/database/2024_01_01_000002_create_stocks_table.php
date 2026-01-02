<?php

/**
 * Stocks Migration
 * 
 * Creates the stocks table for multi-location inventory tracking.
 * Each product can have stock entries for different locations.
 * 
 * @note This is a portfolio sample, not production code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Quantity
            $table->integer('quantity')->default(0);

            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Audit trail
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Composite index for common queries
            $table->index(['product_id', 'location_id']);
            $table->index(['product_id', 'location_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
