<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'nama' => ucwords(fake()->unique()->words(2, true)),
            'deskripsi' => fake()->sentence(),
            'gambar' => null,
            'harga_dasar' => fake()->randomElement([15000, 18000, 22000, 25000]),
            'tersedia' => true,
        ];
    }

    public function habis(): static
    {
        return $this->state(fn () => ['tersedia' => false]);
    }
}
