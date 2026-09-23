<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales and profit reports.
 *
 *   Net sales    = line totals - bill discounts          (tax is collected for the government, not income)
 *   Cost of goods = unit cost frozen at the moment of sale x quantity
 *   Gross profit = net sales - cost of goods
 *
 * Profit is only shown for stores that track stock (they are the ones with cost prices).
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = in_array($request->input('period'), ['daily', 'monthly', 'yearly'], true) ? $request->input('period') : 'daily';
        $tracksStock = store_tracks_stock();

        $month = $this->parseMonth($request->input('month'));
        $year = (int) ($request->input('year') ?: now()->year);
        $year = max(min($year, now()->year + 1), 2000);

        [$from, $to, $format, $labels] = match ($period) {
            'daily' => [
                $month->copy()->startOfMonth(), $month->copy()->endOfMonth(), '%Y-%m-%d',
                collect(range(1, $month->daysInMonth))->mapWithKeys(fn(int $d) => [
                    $month->copy()->day($d)->format('Y-m-d') => $month->copy()->day($d)->format('D, d M'),
                ]),
            ],
            'monthly' => [
                Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay(), '%Y-%m',
                collect(range(1, 12))->mapWithKeys(fn(int $m) => [
                    sprintf('%d-%02d', $year, $m) => Carbon::create($year, $m, 1)->format('F Y'),
                ]),
            ],
            'yearly' => $this->yearlyRange(),
        };

        $rows = $this->aggregate($from, $to, $format)->keyBy('period');
        $table = $labels->map(fn(string $label, string $key) => $this->finish($rows->get($key), $label, $key))->values();
        if ($period === 'daily') {
            // don't list days that haven't happened yet
            $table = $table->filter(fn(array $r) => $r['key'] <= now()->format('Y-m-d'))->values();
        }

        return view('reports.index', [
            'period' => $period,
            'month' => $month,
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'tracksStock' => $tracksStock,
            'table' => $table,
            'totals' => $this->finish($this->sumRows($rows->values()), __('Total'), 'total'),
            'cards' => [
                'today' => $this->finish($this->aggregate(now()->startOfDay(), now()->endOfDay(), '%Y')->first(), __('Today'), 'today'),
                'month' => $this->finish($this->aggregate(now()->startOfMonth(), now()->endOfMonth(), '%Y')->first(), now()->format('F Y'), 'month'),
                'year' => $this->finish($this->aggregate(now()->startOfYear(), now()->endOfYear(), '%Y')->first(), now()->format('Y'), 'year'),
            ],
            'topProducts' => $this->topProducts($from, $to),
            'purchases' => $tracksStock ? $this->purchases($from, $to) : null,
            'years' => range(now()->year, max((int) ($this->firstSaleYear() ?? now()->year), now()->year - 10)),
        ]);
    }

    /** Per-period totals. Aggregated per order first so bill discount/tax is counted once. */
    private function aggregate(Carbon $from, Carbon $to, string $format): Collection
    {
        $perOrder = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.store_id', auth()->user()->store_id)
            ->whereBetween('o.created_at', [$from, $to])
            ->groupBy('o.id', 'o.created_at', 'o.discount', 'o.tax_amount')
            ->selectRaw('o.id, o.created_at, o.discount, o.tax_amount,
                SUM(oi.price) as gross,
                SUM(COALESCE(oi.cost_price, 0) * oi.quantity) as cost,
                SUM(oi.quantity) as qty,
                SUM(CASE WHEN oi.cost_price IS NULL THEN 1 ELSE 0 END) as nocost,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = o.id) as paid');

        return DB::query()->fromSub($perOrder, 't')
            ->selectRaw("DATE_FORMAT(t.created_at, '{$format}') as period, COUNT(*) as orders, SUM(t.qty) as qty,
                SUM(t.gross) as gross, SUM(t.discount) as discount, SUM(t.tax_amount) as tax, SUM(t.cost) as cost,
                SUM(t.nocost) as nocost, SUM(t.paid) as paid")
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /** Turn raw sums into the figures shown on screen. */
    private function finish(?object $r, string $label, string $key): array
    {
        $gross = (float) ($r->gross ?? 0);
        $discount = (float) ($r->discount ?? 0);
        $tax = (float) ($r->tax ?? 0);
        $cost = (float) ($r->cost ?? 0);
        $net = $gross - $discount;
        $profit = $net - $cost;
        $total = $net + $tax;

        return [
            'key' => $key,
            'label' => $label,
            'orders' => (int) ($r->orders ?? 0),
            'qty' => (int) ($r->qty ?? 0),
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax,
            'total' => $total,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $net > 0 ? $profit / $net * 100 : 0,
            'paid' => (float) ($r->paid ?? 0),
            'due' => max($total - (float) ($r->paid ?? 0), 0),
            'nocost' => (int) ($r->nocost ?? 0),
        ];
    }

    private function sumRows(Collection $rows): object
    {
        $sum = fn(string $f) => $rows->sum(fn($r) => (float) $r->{$f});

        return (object) [
            'orders' => $sum('orders'), 'qty' => $sum('qty'), 'gross' => $sum('gross'), 'discount' => $sum('discount'),
            'tax' => $sum('tax'), 'cost' => $sum('cost'), 'nocost' => $sum('nocost'), 'paid' => $sum('paid'),
        ];
    }

    /** Best products by profit (line totals, before bill-level discounts). */
    private function topProducts(Carbon $from, Carbon $to): Collection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.store_id', auth()->user()->store_id)
            ->whereBetween('o.created_at', [$from, $to])
            ->groupBy('oi.product_id', 'p.name', 'p.barcode')
            ->selectRaw('p.name, p.barcode, SUM(oi.quantity) as qty, SUM(oi.price) as sales,
                SUM(COALESCE(oi.cost_price, 0) * oi.quantity) as cost')
            ->orderByRaw(store_tracks_stock() ? 'SUM(oi.price) - SUM(COALESCE(oi.cost_price, 0) * oi.quantity) DESC' : 'SUM(oi.price) DESC')
            ->limit(10)
            ->get();
    }

    /** Stock bought in the range (received purchases) and what is owed for it. */
    private function purchases(Carbon $from, Carbon $to): array
    {
        $ids = DB::table('purchases')
            ->where('store_id', auth()->user()->store_id)
            ->where('status', 'completed')
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()]);

        $total = (float) (clone $ids)->sum('total_amount');
        $paid = (float) DB::table('purchase_payments')->whereIn('purchase_id', (clone $ids)->select('id'))->sum('amount');

        return ['count' => (clone $ids)->count(), 'total' => $total, 'paid' => $paid, 'due' => max($total - $paid, 0)];
    }

    private function yearlyRange(): array
    {
        $first = (int) ($this->firstSaleYear() ?? now()->year);
        $years = collect(range($first, now()->year))->mapWithKeys(fn(int $y) => [(string) $y => (string) $y]);

        return [Carbon::create($first, 1, 1)->startOfDay(), now()->endOfYear(), '%Y', $years];
    }

    private function firstSaleYear(): ?int
    {
        $first = DB::table('orders')->where('store_id', auth()->user()->store_id)->min('created_at');

        return $first ? (int) Carbon::parse($first)->year : null;
    }

    private function parseMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
