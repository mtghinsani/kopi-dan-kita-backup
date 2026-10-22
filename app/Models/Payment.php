<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Kolom cadangan (bukti_path, uploaded_at, alasan_tolak) tidak dipakai.
#[Fillable([
    'order_id', 'nominal', 'status', 'metode', 'gateway_order_id', 'transaction_id',
    'payment_type', 'diverifikasi_oleh', 'verified_at', 'expired_at',
])]
class Payment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'status' => PaymentStatus::class,
            'metode' => PaymentMethod::class,
            'verified_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
