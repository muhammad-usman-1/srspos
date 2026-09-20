<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldBill extends Model
{
    use BelongsToStore;

    protected $fillable = ['user_id', 'customer_id', 'note', 'items', 'store_id'];

    protected $casts = [
        'items' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
