<?php

/**
 * Transactions Migration
 * 
 * Creates the transactions and transaction_details tables
 * for POS sales recording.
 * 
 * @note This is a portfolio sample, not production code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Main transactions table
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Invoice number (unique identifier)
            $table->string('invoice_number', 50)->unique();

            // Relationships
            $table->foreignId('user_id')->constrained(); // Cashier
            $table->foreignId('location_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Financial data
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('cash_received', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);

            // Payment info
            $table->enum('payment_method', ['cash', 'transfer', 'card'])->default('cash');

            // Status
            $table->enum('status', ['completed', 'cancelled', 'deleted'])->default('completed');

            // Additional info
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_number');
            $table->index('user_id');
            $table->index('location_id');
            $table->index('created_at');
            $table->index('status');
            $table->index(['location_id', 'created_at']);
        });

        // Transaction details (items)
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            // Snapshot of product at time of sale
            $table->string('product_name', 255);
            $table->decimal('price', 15, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            // Indexes
            $table->index('transaction_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
    }
};
