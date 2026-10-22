<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Akun demo; semua memakai password "password". */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'nama' => 'Admin Kopi & Kita', 'email' => 'admin@kopikita.test', 'no_hp' => '081200000001',
        ]);

        User::factory()->karyawan()->create([
            'nama' => 'Dimas Barista', 'email' => 'karyawan1@kopikita.test', 'no_hp' => '081200000002',
        ]);
        User::factory()->karyawan()->create([
            'nama' => 'Ayu Kasir', 'email' => 'karyawan2@kopikita.test', 'no_hp' => '081200000003',
        ]);

        foreach ([1 => 'Rina Pratiwi', 2 => 'Bagas Saputra', 3 => 'Nadia Putri'] as $i => $nama) {
            User::factory()->create([
                'nama' => $nama, 'email' => "pelanggan{$i}@kopikita.test", 'no_hp' => '08130000000'.$i,
            ]);
        }
    }
}
