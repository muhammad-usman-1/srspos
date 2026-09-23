<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStoreRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Models\Product;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Factory|View|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->search($request->search)
            ->latest()
            ->paginate($request->wantsJson() ? 200 : 10);

        return $request->wantsJson()
            ? response()->json($products)
            : view('products.index', ['products' => $products]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create(): View|Factory
    {
        return view('products.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(ProductStoreRequest $request)
    {
        $productData = $request->validated();

        if ($request->hasFile('image')) {
            $productData['image'] = $request->file('image')->store('products', 'public');
        }

        $quantity = (int) ($productData['quantity'] ?? 0);
        $productData['quantity'] = 0;
        $product = Product::create($productData);
        if ($quantity > 0) {
            $product->adjustStock($quantity, 'opening', null, 'Initial stock');
        }

        activity_log('product.created', sprintf('Product "%s" created: sale price %s%s%s', $product->name,
            number_format((float) $product->price, 2),
            $product->purchase_price !== null ? ', cost ' . number_format((float) $product->purchase_price, 2) : '',
            $quantity > 0 ? ', opening stock ' . $quantity : ''), 'product', $product->id);

        return redirect()->route('products.index')
            ->with('success', __('product.success_creating'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Factory|View|\Illuminate\View\View
     */
    public function edit(Product $product)
    {
        return view('products.edit')->with('product', $product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(ProductUpdateRequest $request, Product $product)
    {
        $productData = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $productData['image'] = $request->file('image')->store('products', 'public');
        }

        $newQuantity = (int) ($productData['quantity'] ?? $product->quantity);
        $delta = $newQuantity - $product->quantity;
        unset($productData['quantity']);

        // Record what changed on prices, for the activity log.
        $changes = [];
        foreach (['price' => 'sale price', 'purchase_price' => 'cost'] as $field => $label) {
            if (array_key_exists($field, $productData) && (float) $productData[$field] !== (float) $product->{$field}) {
                $changes[] = sprintf('%s %s -> %s', $label, number_format((float) $product->{$field}, 2), number_format((float) $productData[$field], 2));
            }
        }

        $product->update($productData);
        if ($delta !== 0) {
            $product->adjustStock($delta, 'adjustment', null, 'Edited from product form');
            $changes[] = sprintf('stock %+d (now %d)', $delta, $product->quantity);
        }

        activity_log('product.updated', sprintf('Product "%s" updated%s', $product->name, $changes ? ': ' . implode(', ', $changes) : ''), 'product', $product->id);

        return redirect()->route('products.index')
            ->with('success', __('product.success_updating'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Deleting a product would cascade-delete its sale and purchase lines and wipe that
        // history (and the profit figures) — keep it and let the admin mark it Inactive.
        if (\App\Models\OrderItem::where('product_id', $product->id)->exists()
            || \App\Models\PurchaseItem::where('product_id', $product->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('This product has sales or purchase history, so it cannot be deleted. Edit it and set Status to Inactive instead.'),
            ], 422);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        activity_log('product.deleted', sprintf('Product "%s" (%s) deleted', $product->name, $product->barcode), 'product', null);
        $product->delete();

        return response()->json(['success' => true]);
    }
}
