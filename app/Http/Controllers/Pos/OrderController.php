<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
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

    public function store(OrderStoreRequest $request, OrderService $orders): \Illuminate\Http\JsonResponse
    {
        try {
            $cartItems = $request->user()->cart()->get();
            if ($cartItems->isEmpty()) {
                throw new \Exception(__('cart.empty'));
            }

            $order = $orders->create($request->user(), [
                'customer_id' => $request->customer_id,
                'method' => $request->input('method', 'cash') ?: 'cash',
                'amount' => $request->amount,
                'discount_type' => $request->input('discount_type'),
                'discount_value' => $request->input('discount_value'),
                'tax_rate' => $request->input('tax_rate'),
                'items' => $cartItems->map(fn($p): array => [
                    'product_id' => $p->id,
                    'quantity' => (int) $p->pivot->quantity,
                ])->all(),
            ]);
            $request->user()->cart()->detach();

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
     * Printable 80mm receipt (auto prints when ?print=1).
     */
    public function receipt(Order $order): \Illuminate\Contracts\View\View
    {
        $order->load(['items.product', 'payments', 'customer', 'user']);

        return view('orders.receipt', ['order' => $order]);
    }
}
