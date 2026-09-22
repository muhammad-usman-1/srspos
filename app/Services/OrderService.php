<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single place that turns a list of sold lines into an order, its payment
 * and the stock movements. Used by normal checkout and by offline sync.
 */
class OrderService
{
    /**
     * @param  array{
     *     customer_id?: int|null, uuid?: string|null, offline_ref?: string|null, created_at?: string|null,
     *     method?: string|null, amount: float|int|string, discount_type?: string|null,
     *     discount_value?: float|int|string|null, tax_rate?: float|int|string|null,
     *     items: list<array{product_id: int, quantity: int, price?: float|null, mkt_price?: float|null}>
     * }  $data
     * @param  bool  $strictStock  refuse to sell more than is in stock (offline sales are always accepted)
     *
     * @throws \Exception
     */
    public function create(User $user, array $data, bool $strictStock = true): Order
    {
        // A sale uploaded twice must only be saved once.
        if (!empty($data['uuid']) && ($existing = Order::where('client_uuid', $data['uuid'])->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $data, $strictStock): Order {
            $order = Order::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $user->id,
            ]);
            $order->client_uuid = $data['uuid'] ?? null;
            $order->offline_ref = $data['offline_ref'] ?? null;
            if (!empty($data['created_at'])) {
                $order->created_at = Carbon::parse($data['created_at']);
            }
            $order->save();

            // A store that opted for the "simple" stock mode never tracks quantity: no stock
            // check, no stock ledger entry, quantity stays whatever it already is (unused).
            $tracksStock = store_tracks_stock();

            $subtotal = 0.0;
            foreach ($data['items'] as $line) {
                $product = Product::lockForUpdate()->find($line['product_id']);
                if (!$product) {
                    throw new \Exception(__('Product #:id no longer exists.', ['id' => $line['product_id']]));
                }
                $qty = (int) $line['quantity'];
                if ($qty < 1) {
                    throw new \Exception(__('Invalid quantity for :name.', ['name' => $product->name]));
                }
                if ($tracksStock && $strictStock && $product->quantity < $qty) {
                    throw new \Exception(__('cart.available', ['quantity' => $product->quantity]) . ' (' . $product->name . ')');
                }

                $unit = isset($line['price']) ? (float) $line['price'] : (float) $product->price;
                $order->items()->create([
                    'price' => $unit * $qty,
                    'mkt_price' => $line['mkt_price'] ?? $product->mkt_price,
                    'quantity' => $qty,
                    'product_id' => $product->id,
                ]);
                if ($tracksStock) {
                    $product->adjustStock(-$qty, 'sale', 'Order #' . $order->id);
                }
                $subtotal += $unit * $qty;
            }

            // Discount and tax (only when the admin has enabled them in Settings)
            $subtotal = round($subtotal, 2);
            $discount = 0.0;
            if (config('settings.enable_discount') && isset($data['discount_value']) && $data['discount_value'] !== '') {
                $value = (float) $data['discount_value'];
                $discount = ($data['discount_type'] ?? 'fixed') === 'percent' ? $subtotal * min($value, 100) / 100 : $value;
                $discount = round(min($discount, $subtotal), 2);
            }
            $taxRate = config('settings.enable_tax') ? (float) ($data['tax_rate'] ?? config('settings.tax_rate', 0)) : 0.0;
            $taxAmount = round(($subtotal - $discount) * $taxRate / 100, 2);
            $grandTotal = round($subtotal - $discount + $taxAmount, 2);
            $order->update(['discount' => $discount, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount]);

            $order->payments()->create([
                // Cash handed over beyond the bill is change, not revenue.
                'amount' => min((float) $data['amount'], $grandTotal),
                'tendered' => (float) $data['amount'],
                'method' => array_key_exists((string) ($data['method'] ?? ''), Payment::METHODS) ? $data['method'] : 'cash',
                'user_id' => $user->id,
            ]);

            return $order;
        });
    }
}
