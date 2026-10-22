<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => '08'.fake()->numerify('##########'),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => Role::Pelanggan,
            'aktif' => true,
            'privasi_versi' => '1.0',
            'privasi_disetujui_at' => now(),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => Role::SuperAdmin, 'privasi_versi' => null, 'privasi_disetujui_at' => null]);
    }

    public function karyawan(): static
    {
        return $this->state(fn () => ['role' => Role::Karyawan, 'privasi_versi' => null, 'privasi_disetujui_at' => null]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
