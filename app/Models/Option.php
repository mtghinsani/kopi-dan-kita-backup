<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'nama', 'selisih_harga', 'tersedia'])]
#[WithoutTimestamps]
class Option extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'selisih_harga' => 'decimal:2',
            'tersedia' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'group_id');
    }
}
