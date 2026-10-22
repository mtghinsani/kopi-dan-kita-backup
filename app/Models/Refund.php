<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'nominal', 'status', 'refund_key', 'no_referensi', 'diproses_oleh', 'processed_at'])]
#[WithoutTimestamps]
class Refund extends Model
{
    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
