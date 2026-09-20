<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToStore;

    public const TYPES = [
        'opening' => 'Opening Stock',
        'purchase' => 'Purchase',
        'purchase_reversal' => 'Purchase Reversal',
        'sale' => 'Sale',
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
        'product_id',
        'store_id',
        'user_id',
        'type',
        'quantity',
        'balance_after',
        'reference',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? ucfirst($this->type));
    }
}
