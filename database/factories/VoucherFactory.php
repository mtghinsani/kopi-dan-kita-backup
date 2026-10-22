<?php

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('VC####')),
            'jenis' => VoucherType::Persen,
            'nilai' => 10,
            'kuota' => 50,
            'terpakai' => 0,
            'berlaku_mulai' => now()->subDay(),
            'berlaku_sampai' => now()->addDays(30),
        ];
    }
}
