<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kode_pickup' => fake()->unique()->regexify('[A-HJ-NP-Z2-9]{6}'),
            'status' => OrderStatus::MenungguPembayaran,
            'subtotal' => 25000,
            'diskon' => 0,
            'total' => 25000,
        ];
    }
}
