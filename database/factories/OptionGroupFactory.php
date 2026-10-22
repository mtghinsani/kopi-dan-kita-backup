<?php

namespace Database\Factories;

use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'nama' => 'Ukuran',
            'wajib' => true,
            'min_pilih' => 1,
            'max_pilih' => 1,
            'urutan' => 0,
        ];
    }
}
