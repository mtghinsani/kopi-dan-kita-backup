<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\StoreSettingSeeder;

/** Data kedai, waktu di jam buka, dan keranjang berisi Kopi Susu Gula Aren (Large, Dingin, Kurang, Extra Shot) x2 = Rp64.000. */
trait BuildsOrders
{
    protected function setUpShop(): void
    {
        $this->seed([MenuSeeder::class, StoreSettingSeeder::class]);
        $this->travelTo(today()->setTime(10, 0));
    }

    protected function kopiSusu(): Product
    {
        return Product::where('nama', 'Kopi Susu Gula Aren')->with('optionGroups.options')->firstOrFail();
    }

    protected function fillCart(User $user, int $qty = 2): void
    {
        $kopi = $this->kopiSusu();
        $pick = fn (string $group, string $option) => $kopi->optionGroups->firstWhere('nama', $group)->options->firstWhere('nama', $option)->id;

        app(CartService::class)->add($user, [
            'product_id' => $kopi->id,
            'qty' => $qty,
            'options' => [$pick('Ukuran', 'Large'), $pick('Suhu', 'Dingin'), $pick('Gula', 'Kurang'), $pick('Tambahan', 'Extra Shot')],
            'catatan' => 'Es dipisah',
        ]);
    }

    /** Checkout Bayar di Kasir lewat HTTP dan kembalikan pesanannya. */
    protected function checkoutKasir(User $user, array $extra = []): Order
    {
        $this->fillCart($user);
        $this->actingAs($user)->post('/checkout', ['metode' => 'KASIR', ...$extra])->assertRedirect();

        return $user->orders()->latest('id')->firstOrFail();
    }
}
