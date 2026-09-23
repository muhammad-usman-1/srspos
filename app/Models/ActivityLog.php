<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail: one row per thing that happened (a sale, a payment, a purchase,
 * a stock or price change), with who did it and when.
 */
class ActivityLog extends Model
{
    use BelongsToStore;

    /** action => [label, badge colour] */
    public const ACTIONS = [
        'sale.created' => ['Sale', 'success'],
        'sale.payment' => ['Sale payment', 'success'],
        'purchase.created' => ['Purchase created', 'primary'],
        'purchase.status' => ['Purchase status', 'primary'],
        'purchase.payment' => ['Supplier payment', 'info'],
        'purchase.deleted' => ['Purchase deleted', 'danger'],
        'stock.adjusted' => ['Stock adjusted', 'warning'],
        'product.created' => ['Product created', 'secondary'],
        'product.updated' => ['Product updated', 'secondary'],
        'product.deleted' => ['Product deleted', 'danger'],
        'settings.updated' => ['Settings saved', 'dark'],
    ];

    protected $fillable = [
        'store_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return __(self::ACTIONS[$this->action][0] ?? ucfirst(str_replace('.', ' ', $this->action)));
    }

    public function badge(): string
    {
        return self::ACTIONS[$this->action][1] ?? 'secondary';
    }

    /** Link to the record this entry is about, when there is a page for it. */
    public function subjectUrl(): ?string
    {
        if (!$this->subject_id) {
            return null;
        }

        return match ($this->subject_type) {
            'order' => route('orders.show', $this->subject_id),
            'purchase' => store_tracks_stock() ? route('purchases.show', $this->subject_id) : null,
            'product' => route('products.edit', $this->subject_id),
            default => null,
        };
    }
}
