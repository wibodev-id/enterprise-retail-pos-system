<?php

/**
 * Approval Requests Migration
 * 
 * Creates tables for approval workflow system.
 * Supports multiple entity types (products, stocks, transactions).
 * 
 * @note This is a portfolio sample, not production code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Edit Product Requests
        Schema::create('edit_product_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');

            // Proposed changes (nullable = no change)
            $table->string('new_title', 255)->nullable();
            $table->decimal('new_price', 15, 2)->nullable();
            $table->foreignId('new_category_id')->nullable()->constrained('categories');

            // Request info
            $table->text('reason');
            $table->tinyInteger('status_id')->default(1); // 1=pending, 2=approved, 3=rejected

            // Approval tracking
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('status_id');
            $table->index('requested_by');
            $table->index(['status_id', 'created_at']);
        });

        // Stock Adjustment Requests
        Schema::create('stock_adjustment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');

            // Adjustment details
            $table->enum('adjustment_type', ['add', 'subtract', 'set']);
            $table->integer('quantity');
            $table->text('reason');

            // Status
            $table->tinyInteger('status_id')->default(1);

            // Approval tracking
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index('status_id');
        });

        // Delete Transaction Requests
        Schema::create('delete_transaction_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');

            $table->text('reason');
            $table->tinyInteger('status_id')->default(1);

            // Approval tracking (requires director level)
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('transaction_id');
            $table->index('status_id');
        });

        // Approval Logs (audit trail)
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');

            // Action taken
            $table->enum('action', ['submitted', 'approved', 'rejected']);
            $table->foreignId('user_id')->constrained();
            $table->text('notes')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['approvable_type', 'approvable_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('delete_transaction_requests');
        Schema::dropIfExists('stock_adjustment_requests');
        Schema::dropIfExists('edit_product_requests');
    }
};
