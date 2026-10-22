<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'voucher_id', 'kode_pickup', 'status', 'subtotal', 'diskon', 'total',
    'alasan_batal', 'dibatalkan_oleh', 'paid_at', 'ready_at', 'completed_at',
])]
class Order extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Pembayaran yang masih menunggu (paling banyak satu per pesanan). */
    public function activePayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', PaymentStatus::Menunggu));
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * Ringkasan item untuk JSON antrean, kasir, dan status.
     *
     * @return array<int, array{nama: string, qty: int, opsi: array<string>, catatan: ?string}>
     */
    public function itemLines(): array
    {
        $this->loadMissing('items.options');

        return $this->items->map(fn (OrderItem $item) => [
            'nama' => $item->nama_snapshot,
            'qty' => $item->qty,
            'opsi' => $item->options->pluck('nama_snapshot')->all(),
            'catatan' => $item->catatan,
        ])->all();
    }

    /** Struk tersedia untuk pesanan yang sudah dibayar dan tidak dibatalkan (BR-25). */
    public function hasReceipt(): bool
    {
        return in_array($this->status, [OrderStatus::Dibayar, OrderStatus::Diproses, OrderStatus::SiapDiambil, OrderStatus::Selesai], true);
    }

    /** Perlu Refund: ada pembayaran LUNAS tetapi pesanan dibatalkan atau kedaluwarsa, dan refund belum selesai. */
    public function needsRefund(): bool
    {
        return static::whereKey($this->id)->awaitingRefund()->exists();
    }

    public function scopeAwaitingRefund(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Dibatalkan, OrderStatus::Kedaluwarsa])
            ->whereHas('payments', fn ($q) => $q->where('status', PaymentStatus::Lunas))
            ->whereDoesntHave('refund', fn ($q) => $q->where('status', RefundStatus::Selesai));
    }

    /** Pembayaran lunas terakhir, untuk struk dan refund. */
    public function paidPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', PaymentStatus::Lunas));
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('changed_at')->orderBy('id');
    }
}
