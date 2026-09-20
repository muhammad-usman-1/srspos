<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        $orders = Order::query()
            ->with(['items.product', 'payments', 'customer'])
            ->when($request->input('start_date'), function ($query, $startDate): void {
                $query->where('created_at', '>=', $startDate);
            })
            ->when($request->input('end_date'), function ($query, string $endDate): void {
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            })
            ->latest()
            ->paginate(10);

        $total = $orders->sum(fn($order) => $order->total());
        $receivedAmount = $orders->sum(fn($order) => $order->receivedAmount());

        return view('orders.index', ['orders' => $orders, 'total' => $total, 'receivedAmount' => $receivedAmount]);
    }

    public function store(OrderStoreRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($request) {
                // Create order
                $order = Order::create([
                    'customer_id' => $request->customer_id,
                    'user_id' => $request->user()->id,
                ]);

                // Get cart items
                $cartItems = $request->user()->cart()->get();

                if ($cartItems->isEmpty()) {
                    throw new \Exception(__('cart.empty'));
                }

                // Create order items and update product quantities
                $orderTotal = 0.0;
                foreach ($cartItems as $item) {
                    if ($item->quantity < $item->pivot->quantity) {
                        throw new \Exception(__('cart.available', ['quantity' => $item->quantity]) . ' (' . $item->name . ')');
                    }
                    $this->createOrderItem($order, $item);
                    $this->reduceProductStock($item, $order);
                    $orderTotal += $item->price * $item->pivot->quantity;
                }

                // Discount and tax (only when the admin has enabled them in Settings)
                $subtotal = round($orderTotal, 2);
                $discount = 0.0;
                if (config('settings.enable_discount') && $request->filled('discount_value')) {
                    $value = (float) $request->discount_value;
                    $discount = $request->input('discount_type') === 'percent' ? $subtotal * min($value, 100) / 100 : $value;
                    $discount = round(min($discount, $subtotal), 2);
                }
                $taxRate = config('settings.enable_tax') ? (float) $request->input('tax_rate', config('settings.tax_rate', 0)) : 0.0;
                $taxAmount = round(($subtotal - $discount) * $taxRate / 100, 2);
                $grandTotal = round($subtotal - $discount + $taxAmount, 2);
                $order->update(['discount' => $discount, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount]);

                // Clear cart
                $request->user()->cart()->detach();

                // Create payment
                $order->payments()->create([
                    // Cash handed over beyond the bill is change, not revenue.
                    'amount' => min((float) $request->amount, $grandTotal),
                    'method' => $request->input('method', 'cash') ?: 'cash',
                    'tendered' => (float) $request->amount,
                    'user_id' => $request->user()->id,
                ]);

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => __('order.created_successfully'),
                'order_id' => $order->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function partialPayment(Request $request)
    {
        $order = Order::findOrFail($request->input('order_id'));
        $remainingAmount = $order->total() - $order->receivedAmount();

        if ($request->input('amount') > $remainingAmount) {
            return redirect()->route('orders.index')
                ->withErrors(__('order.amount_exceeds_balance'));
        }

        DB::transaction(function () use ($order, $request): void {
            $order->payments()->create([
                'amount' => $request->amount,
                'method' => array_key_exists((string) $request->input('method'), Payment::METHODS) ? $request->input('method') : 'cash',
                'user_id' => auth()->id(),
            ]);
        });

        return redirect()->route('orders.index')
            ->with('success', __('order.partial_payment_success', [
                'amount' => config('settings.currency_symbol') . number_format($request->amount, 2)
            ]));
    }

    /**
     * Create an order item from cart item.
     */
    private function createOrderItem(Order $order, $item): void
    {
        $order->items()->create([
            'price' => $item->price * $item->pivot->quantity,
            'mkt_price' => $item->mkt_price,
            'quantity' => $item->pivot->quantity,
            'product_id' => $item->id,
        ]);
    }

    /**
     * Reduce product stock based on cart quantity.
     */
    private function reduceProductStock($item, Order $order): void
    {
        $item->adjustStock(-$item->pivot->quantity, 'sale', 'Order #' . $order->id);
    }

    /**
     * Printable 80mm receipt (auto prints when ?print=1).
     */
    public function receipt(Order $order): \Illuminate\Contracts\View\View
    {
        $order->load(['items.product', 'payments', 'customer', 'user']);

        return view('orders.receipt', ['order' => $order]);
    }
}
