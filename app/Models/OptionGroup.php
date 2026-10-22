<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'nama', 'wajib', 'min_pilih', 'max_pilih', 'urutan'])]
#[WithoutTimestamps]
class OptionGroup extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'min_pilih' => 'integer',
            'max_pilih' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class, 'group_id')->orderBy('id');
    }
}
