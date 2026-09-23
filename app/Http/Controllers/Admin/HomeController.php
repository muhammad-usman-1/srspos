<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('superadmin.stores.index');
        }

        // What was actually collected: never more than the bill itself.
        $income = fn(Order $order): float => min($order->receivedAmount(), $order->total());

        $orders = Order::with(['items', 'payments'])->get();
        $todayOrders = $orders->where('created_at', '>=', today());

        // Last 7 days, oldest first
        $days = collect(range(6, 0))->map(function (int $ago) use ($orders, $income): array {
            $day = today()->subDays($ago);
            $dayOrders = $orders->filter(fn(Order $o) => $o->created_at->isSameDay($day));

            return ['label' => $day->format('D'), 'date' => $day->format('d M'), 'total' => round($dayOrders->sum($income), 2), 'orders' => $dayOrders->count()];
        });

        $threshold = (int) config('settings.warning_quantity', 10);
        $tracksStock = store_tracks_stock();

        return view('home', [
            'orders_count' => $orders->count(),
            'orders_today' => $todayOrders->count(),
            'income' => $orders->sum($income),
            'income_today' => $todayOrders->sum($income),
            'income_month' => $orders->where('created_at', '>=', today()->startOfMonth())->sum($income),
            'customers_count' => Customer::count(),
            'threshold' => $threshold,
            // "Low stock" only means anything for a store that tracks quantity. A "simple"
            // store shows its product count and top sellers instead, so nothing sits empty.
            'tracks_stock' => $tracksStock,
            'low_stock_count' => $tracksStock ? Product::where('quantity', '<=', $threshold)->count() : 0,
            'low_stock_products' => $tracksStock ? Product::where('quantity', '<=', $threshold)->orderBy('quantity')->limit(8)->get() : collect(),
            'products_count' => $tracksStock ? 0 : Product::count(),
            'top_products' => $tracksStock ? collect() : Product::query()
                ->selectRaw('products.*, SUM(order_items.quantity) as total_sold')
                ->join('order_items', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.created_at', '>=', today()->subDays(30))
                ->groupBy('products.id')
                ->orderByDesc('total_sold')
                ->limit(8)
                ->get(),
            'recent_orders' => Order::with(['items', 'payments', 'customer'])->latest()->limit(8)->get(),
            'days' => $days,
        ]);
    }
}
