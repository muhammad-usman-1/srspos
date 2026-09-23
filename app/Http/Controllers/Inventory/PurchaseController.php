<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Purchases from suppliers: what was bought, when, how many, at what cost, what has
 * been paid to the supplier and what is still owed. A purchase adds stock only once it
 * is "completed" (received); receiving stock moves the product cost to the weighted
 * average cost, which is the cost used for profit.
 */
class PurchaseController extends Controller
{
    private const SORTABLE = ['purchase_date', 'id', 'total_amount', 'status', 'created_at'];

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'supplier_id', 'date_from', 'date_to', 'search']);

        $base = Purchase::query()->filter($filters);

        $purchases = (clone $base)
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->withSum('payments', 'amount')
            ->orderBy($this->sortColumn($request), $request->get('sort_order') === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Totals for everything matching the filters (not just this page); cancelled ones cost nothing.
        $active = (clone $base)->where('status', '!=', 'cancelled');
        $totalAmount = (float) (clone $active)->sum('total_amount');
        $totalPaid = (float) DB::table('purchase_payments')->whereIn('purchase_id', (clone $active)->select('id'))->sum('amount');

        return view('purchases.index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('first_name')->get(),
            'summary' => [
                'count' => (clone $base)->count(),
                'total' => $totalAmount,
                'paid' => $totalPaid,
                'due' => max($totalAmount - $totalPaid, 0),
            ],
        ]);
    }

    /** JSON list (kept for API/AJAX use). */
    public function data(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'user'])
            ->withCount('items')
            ->withSum('payments', 'amount')
            ->filter($request->only(['status', 'supplier_id', 'date_from', 'date_to', 'search']))
            ->orderBy($this->sortColumn($request), $request->get('sort_order') === 'asc' ? 'asc' : 'desc')
            ->paginate(10);

        return response()->json($purchases);
    }

    public function create(): View
    {
        return view('purchases.create', ['suppliers' => Supplier::all()]);
    }

    public function store(PurchaseStoreRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $purchase = DB::transaction(function () use ($request): Purchase {
                // The total is always worked out here from the lines, never trusted from the browser.
                $items = collect($request->items)->map(fn(array $i): array => [
                    'product_id' => (int) $i['product_id'],
                    'quantity' => (int) $i['quantity'],
                    'purchase_price' => round((float) $i['purchase_price'], 2),
                ]);
                $total = round($items->sum(fn(array $i): float => $i['quantity'] * $i['purchase_price']), 2);

                $purchase = Purchase::create([
                    'supplier_id' => $request->supplier_id,
                    'user_id' => Auth::id(),
                    'purchase_date' => $request->purchase_date,
                    'reference_no' => $request->reference_no,
                    'total_amount' => $total,
                    'status' => $request->status ?? 'pending',
                    'notes' => $request->notes,
                ]);

                foreach ($items as $item) {
                    $purchase->items()->create($item);
                    if ($purchase->status === 'completed') {
                        Product::findOrFail($item['product_id'])
                            ->receiveStock($item['quantity'], $item['purchase_price'], 'purchase', 'Purchase #' . $purchase->id);
                    }
                }

                $paid = min(round((float) $request->input('paid_amount', 0), 2), $total);
                if ($paid > 0 && $purchase->status !== 'cancelled') {
                    $purchase->payments()->create([
                        'user_id' => Auth::id(),
                        'amount' => $paid,
                        'method' => $this->method($request->input('payment_method')),
                        'note' => __('Paid when the purchase was entered'),
                    ]);
                }

                activity_log('purchase.created', sprintf('Purchase #%d from %s: %d items, total %s, paid %s, status %s%s',
                    $purchase->id, $purchase->supplier?->first_name . ' ' . $purchase->supplier?->last_name, $items->sum('quantity'),
                    number_format($total, 2), number_format($paid, 2), $purchase->status,
                    $purchase->reference_no ? ', invoice ' . $purchase->reference_no : ''), 'purchase', $purchase->id);

                return $purchase;
            });

            $request->user()->purchaseCart()->detach();

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'purchase_id' => $purchase->id, 'url' => route('purchases.show', $purchase)]);
            }

            return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase created successfully!'));
        } catch (\Throwable $e) {
            report($e);
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Failed to create purchase: ') . $e->getMessage()], 422);
            }

            return back()->with('error', __('Failed to create purchase: ') . $e->getMessage())->withInput();
        }
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'user', 'items.product', 'payments.user']);

        return view('purchases.show', [
            'purchase' => $purchase,
            'methods' => Payment::METHODS,
            'movements' => \App\Models\StockMovement::with('product')
                ->where(fn($q) => $q->where('reference', 'Purchase #' . $purchase->id)
                    ->orWhere('reference', 'like', 'Purchase #' . $purchase->id . ' %'))
                ->latest('id')->get(),
        ]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $purchase): void {
                $purchase->update([
                    'supplier_id' => $request->supplier_id,
                    'purchase_date' => $request->purchase_date,
                    'reference_no' => $request->reference_no,
                    'notes' => $request->notes,
                ]);
                $this->changeStatus($purchase, $request->status);
            });

            return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase updated successfully!'));
        } catch (\Throwable $e) {
            return back()->with('error', __('Failed to update purchase: ') . $e->getMessage())->withInput();
        }
    }

    /** Receive (complete), put back to pending, or cancel a purchase. */
    public function updateStatus(Request $request, Purchase $purchase): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:pending,completed,cancelled']]);

        try {
            DB::transaction(fn() => $this->changeStatus($purchase, $request->input('status')));

            return back()->with('success', __('Purchase #:id is now :status.', ['id' => $purchase->id, 'status' => __($purchase->status)]));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Record a payment made to the supplier against this purchase. */
    public function addPayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $due = $purchase->dueAmount();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . max($due, 0.01)],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => __('Only :due is still owed on this purchase.', ['due' => number_format($due, 2)])]);

        if ($purchase->status === 'cancelled') {
            return back()->with('error', __('A cancelled purchase cannot take payments.'));
        }

        $purchase->payments()->create([
            'user_id' => Auth::id(),
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
        ]);

        activity_log('purchase.payment', sprintf('Paid %s (%s) to supplier for purchase #%d, still owed %s',
            number_format((float) $data['amount'], 2), Payment::METHODS[$data['method']], $purchase->id,
            number_format($purchase->fresh()->dueAmount(), 2)), 'purchase', $purchase->id);

        return back()->with('success', __('Payment recorded.'));
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            DB::transaction(function () use ($purchase): void {
                if ($purchase->status === 'completed') {
                    foreach ($purchase->items as $item) {
                        $item->product?->adjustStock(-$item->quantity, 'purchase_reversal', 'Purchase #' . $purchase->id . ' deleted');
                    }
                }
                activity_log('purchase.deleted', sprintf('Purchase #%d (total %s, status %s) deleted%s', $purchase->id,
                    number_format((float) $purchase->total_amount, 2), $purchase->status,
                    $purchase->status === 'completed' ? ' — its stock was taken back out' : ''), 'purchase', null);
                $purchase->delete();
            });

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('purchases.index')->with('success', __('Purchase deleted successfully!'));
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', __('Failed to delete purchase: ') . $e->getMessage());
        }
    }

    /** 80mm thermal receipt PDF */
    public function receipt(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product', 'payments']);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('purchases.receipt', ['purchase' => $purchase]);
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm width

        return $pdf->stream("purchase-receipt-{$purchase->id}.pdf");
    }

    /**
     * Apply a status change and its stock effect: completed adds the stock (at weighted
     * average cost), moving away from completed takes it back out.
     */
    private function changeStatus(Purchase $purchase, string $newStatus): void
    {
        $oldStatus = $purchase->status;
        if ($oldStatus === $newStatus) {
            return;
        }

        foreach ($purchase->items()->with('product')->get() as $item) {
            if (!$item->product) {
                continue;
            }
            if ($oldStatus === 'completed') {
                $item->product->adjustStock(-$item->quantity, 'purchase_reversal', 'Purchase #' . $purchase->id . ' ' . $newStatus);
            } elseif ($newStatus === 'completed') {
                $item->product->receiveStock($item->quantity, (float) $item->purchase_price, 'purchase', 'Purchase #' . $purchase->id);
            }
        }

        $purchase->update(['status' => $newStatus]);

        activity_log('purchase.status', sprintf('Purchase #%d status %s -> %s%s', $purchase->id, $oldStatus, $newStatus,
            $newStatus === 'completed' ? ' (stock received)' : ($oldStatus === 'completed' ? ' (stock taken back out)' : '')),
            'purchase', $purchase->id);
    }

    private function sortColumn(Request $request): string
    {
        return in_array($request->get('sort_by'), self::SORTABLE, true) ? $request->get('sort_by') : 'purchase_date';
    }

    private function method(?string $method): string
    {
        return array_key_exists((string) $method, Payment::METHODS) ? $method : 'cash';
    }
}
