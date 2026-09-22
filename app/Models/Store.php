<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    /** Quantities are tracked, adjustable, with low-stock alerts (the original, only behaviour). */
    public const STOCK_MODE_TRACKED = 'tracked';
    /** Products are just a catalogue: no quantity, no low-stock alerts, always sellable. */
    public const STOCK_MODE_SIMPLE = 'simple';

    protected $fillable = ['name', 'is_active', 'stock_mode'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** True once the store has picked a stock mode (it can never be changed after that). */
    public function hasChosenStockMode(): bool
    {
        return $this->stock_mode !== null;
    }

    /** Unset defaults to tracked, matching every store created before this feature existed. */
    public function tracksStock(): bool
    {
        return $this->stock_mode !== self::STOCK_MODE_SIMPLE;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The login created for the store owner. */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', User::ROLE_STORE_ADMIN)->oldestOfMany();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
