<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'status_lama', 'status_baru', 'diubah_oleh'])]
class OrderStatusLog extends Model
{
    public const CREATED_AT = 'changed_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status_lama' => OrderStatus::class,
            'status_baru' => OrderStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Pengguna yang mengubah status; null bila diubah sistem. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
