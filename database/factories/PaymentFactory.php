<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'nominal' => 25000,
            'status' => PaymentStatus::Menunggu,
            'metode' => PaymentMethod::Kasir,
            'gateway_order_id' => null,
            'expired_at' => now()->addMinutes(15),
        ];
    }
}
