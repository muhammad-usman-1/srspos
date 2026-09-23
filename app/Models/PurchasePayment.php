<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A payment made to the supplier against a purchase. */
class PurchasePayment extends Model
{
    use BelongsToStore;

    protected $fillable = ['purchase_id', 'store_id', 'user_id', 'amount', 'method', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function methodLabel(): string
    {
        return Payment::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
