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

        return view('home', [
            'orders_count' => $orders->count(),
            'orders_today' => $todayOrders->count(),
            'income' => $orders->sum($income),
            'income_today' => $todayOrders->sum($income),
            'income_month' => $orders->where('created_at', '>=', today()->startOfMonth())->sum($income),
            'customers_count' => Customer::count(),
            'threshold' => $threshold,
            'low_stock_count' => Product::where('quantity', '<=', $threshold)->count(),
            'low_stock_products' => Product::where('quantity', '<=', $threshold)->orderBy('quantity')->limit(8)->get(),
            'recent_orders' => Order::with(['items', 'payments', 'customer'])->latest()->limit(8)->get(),
            'days' => $days,
        ]);
    }
}
