<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'aksi', 'objek', 'objek_id', 'nilai_lama', 'nilai_baru'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nilai_lama' => 'array',
            'nilai_baru' => 'array',
        ];
    }

    public static function record(User $actor, string $aksi, Model $objek, ?array $lama, ?array $baru): self
    {
        return static::create([
            'user_id' => $actor->id,
            'aksi' => $aksi,
            'objek' => $objek->getTable(),
            'objek_id' => $objek->getKey(),
            'nilai_lama' => $lama,
            'nilai_baru' => $baru,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
