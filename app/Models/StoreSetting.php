<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

// Kolom cadangan slot (panjang_slot_menit, lead_time_menit, kapasitas_slot, mulai_buat_menit, batas_tahan_menit) tidak dipakai.
#[Fillable(['nama_kedai', 'alamat', 'kontak', 'qris_image', 'jam_buka', 'jam_tutup', 'batas_bayar_menit', 'pemesanan_dibuka'])]
#[WithoutTimestamps]
class StoreSetting extends Model
{
    protected function casts(): array
    {
        return [
            'batas_bayar_menit' => 'integer',
            'pemesanan_dibuka' => 'boolean',
        ];
    }

    /** Pengaturan kedai selalu satu baris. */
    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    /** URL gambar QRIS statis, atau QRIS contoh bila belum diunggah. */
    protected function qrisUrl(): Attribute
    {
        return Attribute::get(fn () => $this->qris_image
            ? Storage::disk('public')->url($this->qris_image)
            : asset('images/qris-contoh.svg'));
    }
}
