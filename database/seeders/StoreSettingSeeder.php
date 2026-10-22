<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        // qris_image kosong: tampilan memakai QRIS contoh di public/images/qris-contoh.svg.
        StoreSetting::create([
            'nama_kedai' => 'Kopi & Kita',
            'alamat' => 'Jl. Contoh Raya No. 12, Bandung',
            'kontak' => '0812-0000-0000',
            'qris_image' => null,
            'jam_buka' => '07:00:00',
            'jam_tutup' => '22:00:00',
            'batas_bayar_menit' => 15,
            'pemesanan_dibuka' => true,
        ]);
    }
}
