<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'nama', 'deskripsi', 'gambar', 'harga_dasar', 'tersedia'])]
#[WithoutTimestamps]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'harga_dasar' => 'decimal:2',
            'tersedia' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class)->orderBy('urutan')->orderBy('id');
    }

    /** Grup yang punya opsi; grup kosong tidak tampil di katalog dan tidak diwajibkan (US-SA-05). */
    public function activeOptionGroups(): HasMany
    {
        return $this->optionGroups()->has('options');
    }

    /** URL gambar produk, atau gambar placeholder bila belum diunggah. */
    protected function gambarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->gambar
            ? Storage::disk('public')->url($this->gambar)
            : asset('images/produk-placeholder.svg'));
    }
}
