<?php

/**
 * Product Controller
 * 
 * Handles product CRUD operations with approval workflow integration.
 * Products are managed by suppliers and require supervisor approval
 * for certain operations.
 * 
 * @note This is a portfolio sample, not production code.
 */

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EditProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display product listing with filters.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category:id,name', 'supplier:id,name'])
            ->where('is_active', true);

        // Search filter
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'LIKE', "%{$request->search}%")
                    ->orWhere('barcode', 'LIKE', "%{$request->search}%");
            });
        }

        // Category filter
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Supplier filter (for admin/supervisor view)
        if ($request->supplier_id && auth()->user()->hasAnyRole(['admin', 'supervisor', 'it'])) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Regular suppliers only see their own products
        if (auth()->user()->hasRole('supplier')) {
            $query->where('supplier_id', auth()->id());
        }

        $products = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'category_id', 'supplier_id'])
        ]);
    }

    /**
     * Show product creation form.
     */
    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::select('id', 'name')->get()
        ]);
    }

    /**
     * Store new product.
     * New products are created with pending status and require approval.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|unique:products,barcode',
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')
                ->store('products', 'public');
        }

        $validated['supplier_id'] = auth()->id();
        $validated['is_active'] = true; // or false if requires approval

        $product = Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully');
    }

    /**
     * Show product edit form.
     */
    public function edit(Product $product)
    {
        // Authorization check
        if (auth()->user()->hasRole('supplier') && $product->supplier_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Products/Edit', [
            'product' => $product->load('category'),
            'categories' => Category::select('id', 'name')->get()
        ]);
    }

    /**
     * Request product update.
     * Creates an approval request instead of direct update.
     */
    public function requestUpdate(Request $request, Product $product)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'reason' => 'required|string|max:500'
        ]);

        // Check for pending request
        $existingRequest = EditProductRequest::where('product_id', $product->id)
            ->where('status_id', 1) // pending
            ->exists();

        if ($existingRequest) {
            return back()->with('error', 'There is already a pending edit request for this product');
        }

        // Create edit request
        EditProductRequest::create([
            'product_id' => $product->id,
            'requested_by' => auth()->id(),
            'new_title' => $validated['title'],
            'new_price' => $validated['price'],
            'new_category_id' => $validated['category_id'],
            'reason' => $validated['reason'],
            'status_id' => 1 // pending
        ]);

        return redirect()->route('products.index')
            ->with('success', 'Edit request submitted for approval');
    }

    /**
     * Request product deletion.
     * Products are soft deleted after approval.
     */
    public function requestDelete(Request $request, Product $product)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        // Create delete request - similar pattern to edit request
        // Implementation would create DeleteProductRequest model

        return redirect()->route('products.index')
            ->with('success', 'Delete request submitted for approval');
    }

    /**
     * Generate barcode label for printing.
     */
    public function printBarcode(Product $product)
    {
        return Inertia::render('Products/Barcode', [
            'product' => $product->only('id', 'barcode', 'title', 'price')
        ]);
    }

    /**
     * Bulk barcode printing for multiple products.
     */
    public function printBarcodesBulk(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'copies' => 'nullable|integer|min:1|max:100'
        ]);

        $products = Product::whereIn('id', $request->product_ids)
            ->select('id', 'barcode', 'title', 'price')
            ->get();

        return Inertia::render('Products/BarcodesBulk', [
            'products' => $products,
            'copies' => $request->copies ?? 1
        ]);
    }

    /**
     * Check product availability.
     * Public endpoint for customer-facing displays.
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $product = Product::where('barcode', $request->barcode)
            ->where('is_active', true)
            ->with(['stocks' => fn($q) => $q->where('status', 'approved')])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ]);
        }

        $totalStock = $product->stocks->sum('quantity');

        return response()->json([
            'success' => true,
            'product' => [
                'title' => $product->title,
                'price' => $product->price,
                'available' => $totalStock > 0,
                'stock' => $totalStock
            ]
        ]);
    }
}
