<?php

namespace App\Models;

use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'jenis', 'nilai', 'kuota', 'terpakai', 'berlaku_mulai', 'berlaku_sampai'])]
#[WithoutTimestamps]
class Voucher extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jenis' => VoucherType::class,
            'nilai' => 'decimal:2',
            'kuota' => 'integer',
            'terpakai' => 'integer',
            'berlaku_mulai' => 'datetime',
            'berlaku_sampai' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
