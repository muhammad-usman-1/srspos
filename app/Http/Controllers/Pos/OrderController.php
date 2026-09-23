<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Every sale with its full money trail: who rang it up, what was charged, what the
     * customer handed over, the change given back, what is still owed.
     */
    public function index(Request $request): View
    {
        $query = $this->filteredOrders($request);

        $orders = (clone $query)
            ->with(['items', 'payments.user', 'customer', 'user'])
            // order by id, not created_at: an offline sale keeps the timestamp of when it was
            // made, so it can upload after a later online sale and sort out of position by date
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Totals for every order matching the filters (not just the current page).
        $all = (clone $query)->with(['items', 'payments'])->get();
        $summary = [
            'count' => $all->count(),
            'total' => $all->sum(fn(Order $o) => $o->total()),
            'received' => $all->sum(fn(Order $o) => min($o->receivedAmount(), $o->total())),
            'due' => $all->sum(fn(Order $o) => max($o->total() - $o->receivedAmount(), 0)),
            'discount' => (float) $all->sum('discount'),
            'tax' => (float) $all->sum('tax_amount'),
            'profit' => store_tracks_stock() ? $all->sum(fn(Order $o) => $this->profit($o)) : null,
        ];

        return view('orders.index', [
            'orders' => $orders,
            'summary' => $summary,
            'cashiers' => User::where('store_id', $request->user()->store_id)->orderBy('first_name')->get(),
            'methods' => Payment::METHODS,
        ]);
    }

    /** Everything about one sale: lines, cost/profit, every payment, stock movements, activity. */
    public function show(Order $order): View
    {
        $order->load(['items.product', 'payments.user', 'customer', 'user']);

        return view('orders.show', [
            'order' => $order,
            'profit' => store_tracks_stock() ? $this->profit($order) : null,
            'movements' => StockMovement::with('product')->where('reference', 'Order #' . $order->id)->latest('id')->get(),
            'activity' => ActivityLog::with('user')->where('subject_type', 'order')->where('subject_id', $order->id)->latest('id')->get(),
            'methods' => Payment::METHODS,
        ]);
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

    /** Take a later payment against a sale that was not fully paid. */
    public function partialPayment(Request $request): RedirectResponse
    {
        $order = Order::findOrFail($request->input('order_id'));
        $remainingAmount = round($order->total() - $order->receivedAmount(), 2);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
        ]);

        if ((float) $request->input('amount') > $remainingAmount + 0.001) {
            return back()->withErrors(__('order.amount_exceeds_balance'));
        }

        DB::transaction(function () use ($order, $request, $remainingAmount): void {
            $method = $request->input('method') ?: 'cash';
            $amount = round((float) $request->input('amount'), 2);
            $order->payments()->create([
                'amount' => $amount,
                'tendered' => $amount,
                'method' => $method,
                'user_id' => auth()->id(),
            ]);
            activity_log('sale.payment', sprintf('Received %s (%s) against sale #%d, still due %s',
                number_format($amount, 2), Payment::METHODS[$method], $order->id,
                number_format(max($remainingAmount - $amount, 0), 2)), 'order', $order->id);
        });

        return back()->with('success', __('order.partial_payment_success', [
            'amount' => config('settings.currency_symbol') . number_format((float) $request->amount, 2),
        ]));
    }

    /**
     * Printable 80mm receipt (auto prints when ?print=1). Shows sale prices only — the
     * cost price is never printed.
     */
    public function receipt(Order $order): View
    {
        $order->load(['items.product', 'payments', 'customer', 'user']);

        return view('orders.receipt', ['order' => $order]);
    }

    /** Sales revenue (after discount, before tax) minus the cost of the goods sold. */
    private function profit(Order $order): float
    {
        $revenue = $order->subtotal() - (float) $order->discount;
        $cost = $order->items->sum(fn($i) => (float) $i->cost_price * $i->quantity);

        return round($revenue - $cost, 2);
    }

    private function filteredOrders(Request $request): Builder
    {
        return Order::query()
            ->when($request->input('start_date'), fn($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->input('end_date'), fn($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->when($request->input('cashier'), fn($q, $id) => $q->where('user_id', $id))
            ->when($request->input('method'), fn($q, $m) => $q->whereHas('payments', fn($p) => $p->where('method', $m)))
            ->when($request->input('search'), function ($q, $term): void {
                $term = ltrim(trim((string) $term), '#');
                $q->where(fn($w) => $w->where('id', $term)
                    ->orWhere('offline_ref', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn($c) => $c->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")));
            })
            ->when($request->input('status'), function ($q, $status): void {
                // paid / partial / unpaid, worked out from the order total vs its payments
                $paid = '(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.order_id = orders.id)';
                $total = '((SELECT COALESCE(SUM(price),0) FROM order_items WHERE order_items.order_id = orders.id) - orders.discount + orders.tax_amount)';
                match ($status) {
                    'paid' => $q->whereRaw("{$paid} >= {$total} - 0.005"),
                    'partial' => $q->whereRaw("{$paid} > 0 AND {$paid} < {$total} - 0.005"),
                    'unpaid' => $q->whereRaw("{$paid} <= 0"),
                    default => null,
                };
            });
    }
}
