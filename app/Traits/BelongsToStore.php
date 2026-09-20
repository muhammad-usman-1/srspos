<?php

namespace App\Traits;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts a model to the logged-in user's store and stamps new rows with it.
 * Console code and the superadmin (no store) are not filtered.
 */
trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder): void {
            $storeId = auth()->user()?->store_id;

            if ($storeId) {
                $builder->where($builder->getModel()->getTable() . '.store_id', $storeId);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->store_id) && ($storeId = auth()->user()?->store_id)) {
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
