<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/** 5 kategori dan 30 produk; minuman mendapat grup opsi kustomisasi. */
class MenuSeeder extends Seeder
{
    private const MENU = [
        'Kopi' => [
            ['Espresso', 15000, 'Satu shot espresso dari biji arabika pilihan.'],
            ['Americano', 18000, 'Espresso dengan air panas, ringan dan bersih.'],
            ['Cappuccino', 25000, 'Espresso, susu, dan busa susu yang tebal.'],
            ['Caffe Latte', 25000, 'Espresso dengan susu steamed yang lembut.'],
            ['Kopi Susu Gula Aren', 22000, 'Kopi susu khas dengan manis gula aren.'],
            ['Caramel Macchiato', 28000, 'Susu, espresso, dan saus karamel.'],
            ['Mocha', 27000, 'Perpaduan espresso, cokelat, dan susu.'],
            ['Kopi Tubruk', 15000, 'Kopi hitam tradisional diseduh langsung.'],
            ['V60 Arabika Gayo', 30000, 'Seduh manual dengan rasa buah yang cerah.'],
            ['Vietnam Drip', 22000, 'Kopi tetes dengan susu kental manis.'],
        ],
        'Non-Kopi' => [
            ['Cokelat', 22000, 'Cokelat pekat dengan susu.'],
            ['Matcha Latte', 26000, 'Matcha Jepang dengan susu segar.'],
            ['Red Velvet Latte', 25000, 'Minuman red velvet manis dan creamy.'],
            ['Taro Latte', 24000, 'Taro dengan susu, rasa manis yang lembut.'],
            ['Susu Segar', 18000, 'Susu segar murni.'],
            ['Lemonade', 20000, 'Lemon segar dengan sedikit soda.'],
        ],
        'Teh' => [
            ['Teh Melati', 12000, 'Teh melati wangi.'],
            ['Teh Tarik', 18000, 'Teh susu ditarik hingga berbusa.'],
            ['Lemon Tea', 16000, 'Teh dengan perasan lemon.'],
            ['Lychee Tea', 20000, 'Teh dengan buah leci.'],
            ['Thai Tea', 20000, 'Teh Thailand dengan susu.'],
        ],
        'Makanan Ringan' => [
            ['Kentang Goreng', 18000, 'Kentang goreng renyah dengan saus.'],
            ['Pisang Goreng', 15000, 'Pisang goreng dengan taburan gula.'],
            ['Cireng Rujak', 15000, 'Cireng dengan bumbu rujak pedas manis.'],
            ['Tahu Cabai Garam', 16000, 'Tahu goreng dengan cabai dan garam.'],
            ['Dimsum Ayam', 20000, 'Empat potong dimsum ayam kukus.'],
        ],
        'Roti' => [
            ['Croissant Mentega', 18000, 'Croissant berlapis mentega.'],
            ['Roti Bakar Cokelat Keju', 20000, 'Roti bakar dengan cokelat dan keju.'],
            ['Banana Bread', 17000, 'Roti pisang lembut.'],
            ['Cinnamon Roll', 20000, 'Roti kayu manis dengan glaze.'],
        ],
    ];

    private const MINUMAN = ['Kopi', 'Non-Kopi', 'Teh'];

    public function run(): void
    {
        $urutan = 1;
        foreach (self::MENU as $kategori => $produk) {
            $category = Category::create(['nama' => $kategori, 'urutan' => $urutan++]);

            foreach ($produk as [$nama, $harga, $deskripsi]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'nama' => $nama,
                    'deskripsi' => $deskripsi,
                    'harga_dasar' => $harga,
                    'tersedia' => true,
                ]);

                if (in_array($kategori, self::MINUMAN, true)) {
                    $this->addDrinkOptions($product);
                }
            }
        }
    }

    private function addDrinkOptions(Product $product): void
    {
        $groups = [
            ['Ukuran', true, 1, 1, [['Regular', 0], ['Large', 5000]]],
            ['Suhu', true, 1, 1, [['Panas', 0], ['Dingin', 0]]],
            ['Es', false, 0, 1, [['Normal', 0], ['Sedikit', 0], ['Tanpa Es', 0]]],
            ['Gula', true, 1, 1, [['Normal', 0], ['Kurang', 0], ['Tanpa Gula', 0]]],
            ['Tambahan', false, 0, 3, [['Extra Shot', 5000], ['Oat Milk', 7000], ['Whipped Cream', 4000]]],
        ];

        foreach ($groups as $i => [$nama, $wajib, $min, $max, $options]) {
            $group = $product->optionGroups()->create([
                'nama' => $nama, 'wajib' => $wajib, 'min_pilih' => $min, 'max_pilih' => $max, 'urutan' => $i + 1,
            ]);

            foreach ($options as [$opsi, $selisih]) {
                $group->options()->create(['nama' => $opsi, 'selisih_harga' => $selisih, 'tersedia' => true]);
            }
        }
    }
}
