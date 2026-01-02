<?php

/**
 * Cart Service - Stock Validation & Cart Management
 * 
 * Conceptual implementation demonstrating patterns used in
 * enterprise retail POS systems.
 * 
 * @note This is a portfolio sample, not production code.
 */

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Add product to cart with real-time stock validation.
     * 
     * Key considerations:
     * - Check available stock before adding
     * - Account for items already in other carts
     * - Handle concurrent requests gracefully
     */
    public function addToCart(int $productId, int $quantity, int $locationId): array
    {
        $product = Product::findOrFail($productId);
        $availableStock = $this->getAvailableStock($product, $locationId);

        // Validate stock availability
        if ($quantity > $availableStock) {
            return [
                'success' => false,
                'message' => "Only {$availableStock} units available",
                'available' => $availableStock
            ];
        }

        // Use updateOrCreate to handle existing cart items
        $cart = Cart::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'product_id' => $productId,
                'location_id' => $locationId
            ],
            ['quantity' => DB::raw("quantity + {$quantity}")]
        );

        return [
            'success' => true,
            'cart' => $cart->fresh()->load('product'),
            'message' => 'Product added to cart'
        ];
    }

    /**
     * Calculate available stock considering:
     * - Current stock at location
     * - Items reserved in active carts
     * - Pending withdrawal requests
     */
    public function getAvailableStock(Product $product, int $locationId): int
    {
        // Get total approved stock
        $totalStock = $product->stocks()
            ->where('location_id', $locationId)
            ->where('status', 'approved')
            ->sum('quantity');

        // Subtract items in active carts (reserved)
        $cartReserved = $product->cartItems()
            ->whereHas('cart', fn($q) => $q->where('status', 'active'))
            ->sum('quantity');

        // Subtract pending withdrawals
        $pendingWithdrawals = $product->withdrawalRequests()
            ->where('status', 'pending')
            ->where('location_id', $locationId)
            ->sum('quantity');

        return max(0, $totalStock - $cartReserved - $pendingWithdrawals);
    }

    /**
     * Batch stock calculation to avoid N+1 queries.
     * Used when displaying product lists with stock info.
     */
    public static function getAvailableStockBatch(array $productIds, int $locationId): array
    {
        return DB::table('stocks')
            ->whereIn('product_id', $productIds)
            ->where('location_id', $locationId)
            ->where('status', 'approved')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id')
            ->toArray();
    }

    /**
     * Checkout process with transaction isolation.
     * 
     * Uses database transactions to:
     * - Lock cart items during checkout
     * - Re-validate stock before finalizing
     * - Ensure atomic transaction creation
     */
    public function checkout(int $locationId): Transaction
    {
        return DB::transaction(function () use ($locationId) {
            // Lock cart items to prevent concurrent modifications
            $cartItems = Cart::where('user_id', auth()->id())
                ->where('location_id', $locationId)
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception('Cart is empty');
            }

            // Re-validate stock before checkout (double-check)
            foreach ($cartItems as $item) {
                $available = $this->getAvailableStock($item->product, $locationId);
                if ($item->quantity > $available) {
                    throw new \Exception(
                        "Insufficient stock for {$item->product->name}. " .
                        "Available: {$available}, Requested: {$item->quantity}"
                    );
                }
            }

            // Create transaction
            $transaction = Transaction::create([
                'invoice_number' => $this->generateInvoice(),
                'user_id' => auth()->id(),
                'location_id' => $locationId,
                'total' => $cartItems->sum(fn($i) => $i->quantity * $i->product->price),
                'status' => 'completed'
            ]);

            // Create transaction details
            foreach ($cartItems as $item) {
                $transaction->details()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'subtotal' => $item->quantity * $item->product->price
                ]);

                // Deduct stock
                $this->deductStock($item->product_id, $item->quantity, $locationId);
            }

            // Clear user's cart
            Cart::where('user_id', auth()->id())
                ->where('location_id', $locationId)
                ->delete();

            return $transaction->load('details.product');
        });
    }

    /**
     * Generate unique invoice number.
     * Format: INV-YYYYMMDD-XXXXX
     */
    protected function generateInvoice(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -5));
        return "INV-{$date}-{$random}";
    }

    /**
     * Deduct stock after successful transaction.
     */
    protected function deductStock(int $productId, int $quantity, int $locationId): void
    {
        DB::table('stocks')
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('status', 'approved')
            ->decrement('quantity', $quantity);
    }
}
