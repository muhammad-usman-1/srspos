<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\ProductScopes;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $image
 * @property string $barcode
 * @property numeric $price
 * @property string|null $purchase_price
 * @property int $quantity
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $image_url
 * @method static Builder<static>|Product active()
 * @method static Builder<static>|Product bestSelling()
 * @method static Builder<static>|Product currentMonthBestSelling()
 * @method static \Database\Factories\ProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|Product lowStock()
 * @method static Builder<static>|Product newModelQuery()
 * @method static Builder<static>|Product newQuery()
 * @method static Builder<static>|Product pastMonthsHotProducts()
 * @method static Builder<static>|Product query()
 * @method static Builder<static>|Product search($term)
 * @method static Builder<static>|Product whereBarcode($value)
 * @method static Builder<static>|Product whereCreatedAt($value)
 * @method static Builder<static>|Product whereDescription($value)
 * @method static Builder<static>|Product whereId($value)
 * @method static Builder<static>|Product whereImage($value)
 * @method static Builder<static>|Product whereName($value)
 * @method static Builder<static>|Product wherePrice($value)
 * @method static Builder<static>|Product wherePurchasePrice($value)
 * @method static Builder<static>|Product whereQuantity($value)
 * @method static Builder<static>|Product whereStatus($value)
 * @method static Builder<static>|Product whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Product extends Model
{
    use BelongsToStore;
    use HasFactory;
    use ProductScopes;

    protected $fillable = [
        'name',
        'description',
        'image',
        'barcode',
        'price',
        'mkt_price',
        'quantity',
        'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = ['image_url'];
    /**
     * Get the product image URL.
     */
    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return Storage::disk('public')->url($this->image);
        }

        return asset('images/img-placeholder.jpg');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Change stock by a signed amount and record it in the stock ledger.
     */
    public function adjustStock(int $delta, string $type, ?string $reference = null, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($delta, $type, $reference, $note): StockMovement {
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);
            $locked->quantity += $delta;
            $locked->save();

            $this->quantity = $locked->quantity;
            $this->syncOriginalAttribute('quantity');

            return $this->stockMovements()->create([
                'user_id' => auth()->id(),
                'store_id' => $this->store_id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $locked->quantity,
                'reference' => $reference,
                'note' => $note,
            ]);
        });
    }
}
