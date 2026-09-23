<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Stock overview with quick add/remove/set actions.
     */
    public function index(Request $request): View
    {
        $threshold = (int) config('settings.warning_quantity', 10);

        $products = Product::query()
            ->when($request->input('search'), function ($query, string $term): void {
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%");
                });
            })
            ->when($request->boolean('low'), fn($query) => $query->where('quantity', '<=', $threshold))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('stock.index', [
            'products' => $products,
            'threshold' => $threshold,
            'stockValue' => Product::query()->selectRaw('COALESCE(SUM(quantity * COALESCE(purchase_price, 0)), 0) as v')->value('v'),
            'retailValue' => Product::query()->selectRaw('COALESCE(SUM(quantity * price), 0) as v')->value('v'),
            'lowCount' => Product::where('quantity', '<=', $threshold)->count(),
        ]);
    }

    /**
     * Add, remove or set stock for an existing product.
     */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:add,remove,set'],
            'quantity' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $qty = (int) $data['quantity'];
        $delta = match ($data['mode']) {
            'add' => $qty,
            'remove' => -$qty,
            'set' => $qty - $product->quantity,
        };

        if ($product->quantity + $delta < 0) {
            return back()->with('error', __('Cannot remove more than the :qty in stock.', ['qty' => $product->quantity]));
        }
        if ($delta === 0) {
            return back()->with('error', __('No change in stock.'));
        }

        $type = match ($data['mode']) {
            'add' => 'stock_in',
            'remove' => 'stock_out',
            'set' => 'adjustment',
        };
        $before = $product->quantity;
        $oldCost = $product->purchase_price;

        if ($data['mode'] === 'add' && isset($data['purchase_price'])) {
            // New stock at a known cost: cost becomes the weighted average (used for profit).
            $product->receiveStock($delta, (float) $data['purchase_price'], $type, null, $data['note'] ?? null);
        } else {
            $product->adjustStock($delta, $type, null, $data['note'] ?? null);
        }

        activity_log('stock.adjusted', sprintf('Stock of "%s" %s %d -> %d (%+d)%s%s', $product->name, $data['mode'], $before, $product->quantity, $delta,
            ($oldCost != $product->purchase_price) ? sprintf(', cost %s -> %s', number_format((float) $oldCost, 2), number_format((float) $product->purchase_price, 2)) : '',
            !empty($data['note']) ? ' — ' . $data['note'] : ''), 'product', $product->id);

        return back()->with('success', __(':name stock updated to :qty.', ['name' => $product->name, 'qty' => $product->quantity]));
    }

    /**
     * Full stock ledger.
     */
    public function movements(Request $request): View
    {
        $movements = StockMovement::with(['product', 'user'])
            ->when($request->input('product_id'), fn($q, $id) => $q->where('product_id', $id))
            ->when($request->input('type'), fn($q, $type) => $q->where('type', $type))
            ->when($request->input('date_from'), fn($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->input('date_to'), fn($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('stock.movements', [
            'movements' => $movements,
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
