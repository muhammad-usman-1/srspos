<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\HeldBill;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeldBillController extends Controller
{
    /**
     * List the bills currently on hold.
     */
    public function index(): JsonResponse
    {
        $bills = HeldBill::with('customer')->latest()->get()->map(function (HeldBill $bill): array {
            $products = Product::whereIn('id', collect($bill->items)->pluck('product_id'))->get()->keyBy('id');
            $total = collect($bill->items)->sum(fn(array $i): float => ($products[$i['product_id']]->price ?? 0) * $i['quantity']);

            return [
                'id' => $bill->id,
                'note' => $bill->note,
                'customer' => $bill->customer ? $bill->customer->first_name . ' ' . $bill->customer->last_name : null,
                'items_count' => collect($bill->items)->sum('quantity'),
                'total' => round($total, 2),
                'created_at' => $bill->created_at->format('d M H:i'),
            ];
        });

        return response()->json($bills);
    }

    /**
     * Put the current cart on hold and empty it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $cart = $request->user()->cart()->get();
        if ($cart->isEmpty()) {
            return response()->json(['message' => __('cart.empty')], 422);
        }

        $bill = DB::transaction(function () use ($request, $cart, $data): HeldBill {
            $bill = HeldBill::create([
                'user_id' => $request->user()->id,
                'customer_id' => $data['customer_id'] ?? null,
                'note' => $data['note'] ?? null,
                'items' => $cart->map(fn($p): array => [
                    'product_id' => $p->id,
                    'quantity' => (int) $p->pivot->quantity,
                ])->all(),
            ]);
            $request->user()->cart()->detach();

            return $bill;
        });

        return response()->json(['success' => true, 'id' => $bill->id], 201);
    }

    /**
     * Restore a held bill into the (empty) cart.
     */
    public function resume(Request $request, HeldBill $heldBill): JsonResponse
    {
        if ($request->user()->cart()->exists()) {
            return response()->json(['message' => __('Hold or cancel the current bill before resuming another one.')], 422);
        }

        $skipped = [];
        DB::transaction(function () use ($request, $heldBill, &$skipped): void {
            foreach ($heldBill->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product || $product->quantity < 1) {
                    $skipped[] = $product->name ?? ('#' . $item['product_id']);
                    continue;
                }
                $qty = min($item['quantity'], $product->quantity);
                if ($qty < $item['quantity']) {
                    $skipped[] = $product->name . ' (' . $qty . ' of ' . $item['quantity'] . ')';
                }
                $request->user()->cart()->attach($product->id, ['quantity' => $qty]);
            }
            $heldBill->delete();
        });

        return response()->json([
            'success' => true,
            'customer_id' => $heldBill->customer_id,
            'skipped' => $skipped,
        ]);
    }

    public function destroy(HeldBill $heldBill): JsonResponse
    {
        $heldBill->delete();

        return response()->json(['success' => true]);
    }
}
