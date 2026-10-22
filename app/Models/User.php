<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

// role dan aktif sengaja tidak fillable: hanya diubah lewat aksi Super Admin atau seeder.
#[Fillable(['nama', 'email', 'no_hp', 'password_hash', 'privasi_versi', 'privasi_disetujui_at'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Kolom password mengikuti ERD (password_hash). */
    protected $authPasswordName = 'password_hash';

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => Role::class,
            'aktif' => 'boolean',
            'privasi_disetujui_at' => 'datetime',
        ];
    }

    /** Halaman awal setelah login sesuai role. */
    public function homePath(): string
    {
        return match ($this->role) {
            Role::SuperAdmin => '/admin',
            Role::Karyawan => '/karyawan',
            Role::Pelanggan => '/menu',
        };
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }
}
