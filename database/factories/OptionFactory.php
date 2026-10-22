<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => OptionGroup::factory(),
            'nama' => ucfirst(fake()->unique()->word()),
            'selisih_harga' => 0,
            'tersedia' => true,
        ];
    }
}
