<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints used by the offline-capable POS screen.
 */
class PosSyncController extends Controller
{
    /**
     * Everything the POS needs to keep selling without a connection.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'products' => Product::orderBy('name')->get(['id', 'name', 'barcode', 'price', 'mkt_price', 'quantity'])
                ->map(fn(Product $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'barcode' => $p->barcode,
                    'price' => (float) $p->price,
                    'mkt_price' => $p->mkt_price !== null ? (float) $p->mkt_price : null,
                    'quantity' => (int) $p->quantity,
                ])->all(),
            'customers' => Customer::orderBy('first_name')->get(['id', 'first_name', 'last_name'])->all(),
            'settings' => [
                'app_name' => config('app.name'),
                'currency_symbol' => config('settings.currency_symbol'),
                'warning_quantity' => (int) config('settings.warning_quantity', 10),
                'enable_discount' => (bool) config('settings.enable_discount'),
                'enable_tax' => (bool) config('settings.enable_tax'),
                'tax_name' => config('settings.tax_name') ?: 'Tax',
                'tax_rate' => (float) config('settings.tax_rate', 0),
                'store_address' => config('settings.store_address'),
                'store_phone' => config('settings.store_phone'),
                'receipt_title' => config('settings.receipt_title'),
                'receipt_policy' => config('settings.receipt_policy'),
                'receipt_footer' => config('settings.receipt_footer'),
                'receipt_credit' => config('settings.receipt_credit'),
                'logo_url' => app_logo_url(),
            ],
            'methods' => Payment::METHODS,
            'cashier' => $user->getFullname(),
            'csrf' => csrf_token(),
        ]);
    }

    /**
     * Save sales made in the browser (online or offline). Safe to call twice:
     * a sale with an already-saved uuid is not saved again.
     */
    public function sales(Request $request, OrderService $orders): JsonResponse
    {
        $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:200'],
            'sales.*.uuid' => ['required', 'uuid'],
            'sales.*.offline_ref' => ['nullable', 'string', 'max:30'],
            'sales.*.created_at' => ['nullable', 'date'],
            'sales.*.customer_id' => ['nullable', 'integer'],
            'sales.*.method' => ['nullable', 'string'],
            'sales.*.amount' => ['required', 'numeric', 'min:0'],
            'sales.*.discount_type' => ['nullable', 'in:fixed,percent'],
            'sales.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'sales.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sales.*.items' => ['required', 'array', 'min:1'],
            'sales.*.items.*.product_id' => ['required', 'integer'],
            'sales.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'sales.*.items.*.price' => ['nullable', 'numeric', 'min:0'],
            'sales.*.items.*.mkt_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $results = [];
        foreach ($request->input('sales') as $sale) {
            try {
                // Only sales rung up while offline skip the stock check: the customer already left with the goods.
                $order = $orders->create($request->user(), $sale, strictStock: false);
                $results[] = ['uuid' => $sale['uuid'], 'ok' => true, 'order_id' => $order->id];
            } catch (\Throwable $e) {
                $results[] = ['uuid' => $sale['uuid'], 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }
}
