<?php

namespace Database\Seeders;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        Voucher::create([
            'kode' => 'HEMAT10', 'jenis' => VoucherType::Persen, 'nilai' => 10, 'kuota' => 50,
            'berlaku_mulai' => now()->startOfDay(), 'berlaku_sampai' => now()->addDays(30)->endOfDay(),
        ]);
        Voucher::create([
            'kode' => 'POTONG5K', 'jenis' => VoucherType::Nominal, 'nilai' => 5000, 'kuota' => 20,
            'berlaku_mulai' => now()->startOfDay(), 'berlaku_sampai' => now()->addDays(30)->endOfDay(),
        ]);
        // Masa berlaku sudah lewat, untuk menguji penolakan voucher kedaluwarsa.
        Voucher::create([
            'kode' => 'KADALUWARSA', 'jenis' => VoucherType::Persen, 'nilai' => 20, 'kuota' => 10,
            'berlaku_mulai' => now()->subDays(60), 'berlaku_sampai' => now()->subDays(30),
        ]);
    }
}
