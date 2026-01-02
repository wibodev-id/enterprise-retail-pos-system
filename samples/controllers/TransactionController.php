<?php

/**
 * Transaction Controller
 * 
 * Handles POS transaction operations including cart management,
 * checkout, and receipt generation.
 * 
 * @note This is a portfolio sample, not production code.
 */

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Display POS interface.
     * Shows cart items and product search.
     */
    public function index()
    {
        $locationId = session('location_id');

        if (!$locationId) {
            return redirect()->route('select-location');
        }

        $cartItems = Cart::where('user_id', auth()->id())
            ->where('location_id', $locationId)
            ->with('product:id,barcode,title,price,image')
            ->get();

        $cartTotal = $cartItems->sum(fn($item) => $item->quantity * $item->product->price);

        return Inertia::render('Transactions/Index', [
            'cartItems' => $cartItems,
            'cartTotal' => $cartTotal,
            'location' => session('location_name'),
        ]);
    }

    /**
     * Search product by barcode or name.
     * Optimized for fast response time (< 100ms target).
     */
    public function searchProduct(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1'
        ]);

        $query = $request->input('query');
        $locationId = session('location_id');

        // First try exact barcode match (fastest)
        $product = Product::where('barcode', $query)
            ->where('is_active', true)
            ->select('id', 'barcode', 'title', 'price', 'image')
            ->first();

        // If not found, search by name (LIKE query)
        if (!$product) {
            $product = Product::where('title', 'LIKE', "%{$query}%")
                ->where('is_active', true)
                ->select('id', 'barcode', 'title', 'price', 'image')
                ->first();
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ]);
        }

        // Get available stock
        $availableStock = $this->cartService->getAvailableStock($product, $locationId);

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'title' => $product->title,
                'price' => $product->price,
                'image' => $product->image,
                'available_stock' => $availableStock
            ]
        ]);
    }

    /**
     * Add product to cart.
     * Validates stock availability before adding.
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $locationId = session('location_id');

        $result = $this->cartService->addToCart(
            $request->product_id,
            $request->quantity,
            $locationId
        );

        return response()->json($result);
    }

    /**
     * Update cart item quantity.
     */
    public function updateQuantity(Request $request, int $cartId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::where('id', $cartId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $locationId = session('location_id');
        $availableStock = $this->cartService->getAvailableStock($cart->product, $locationId);

        // Check if new quantity exceeds available stock
        if ($request->quantity > $availableStock + $cart->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Only " . ($availableStock + $cart->quantity) . " units available"
            ]);
        }

        $cart->update(['quantity' => $request->quantity]);

        return response()->json([
            'success' => true,
            'cart' => $cart->fresh()->load('product')
        ]);
    }

    /**
     * Remove item from cart.
     */
    public function destroyCart(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:carts,id'
        ]);

        Cart::where('id', $request->cart_id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    /**
     * Clear all items from cart.
     */
    public function clearCart()
    {
        $locationId = session('location_id');

        Cart::where('user_id', auth()->id())
            ->where('location_id', $locationId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared'
        ]);
    }

    /**
     * Process checkout and create transaction.
     * Uses database transaction for atomicity.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cash_received' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string|max:500'
        ]);

        $locationId = session('location_id');

        try {
            $transaction = $this->cartService->checkout($locationId);

            // Update with payment info
            $transaction->update([
                'cash_received' => $request->cash_received,
                'change_amount' => $request->cash_received - $transaction->total,
                'customer_id' => $request->customer_id,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed',
                'transaction' => $transaction->load('details.product'),
                'redirect' => route('transactions.print', ['id' => $transaction->id])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Print receipt page.
     */
    public function print(Request $request)
    {
        $transaction = Transaction::with(['details.product', 'user', 'location'])
            ->findOrFail($request->id);

        return Inertia::render('Transactions/Print', [
            'transaction' => $transaction
        ]);
    }

    /**
     * Get transaction history with filters.
     */
    public function history(Request $request)
    {
        $query = Transaction::with(['user:id,name', 'location:id,name'])
            ->where('status', 'completed');

        // Filter by date range
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by location
        if ($request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Transactions/History', [
            'transactions' => $transactions,
            'filters' => $request->only(['start_date', 'end_date', 'location_id'])
        ]);
    }
}
