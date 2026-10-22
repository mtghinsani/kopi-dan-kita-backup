<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VoucherType;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** T-06, T-07, T-08 dan BR-10. */
class CheckoutTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
        $this->user = User::factory()->create();
    }

    public function test_checkout_ignores_forged_prices_and_copies_snapshots(): void
    {
        $this->fillCart($this->user);

        $response = $this->actingAs($this->user)->post('/checkout', [
            'metode' => 'KASIR', 'total' => 1000, 'harga' => 1, 'subtotal' => 1000, 'status' => 'DIBAYAR',
        ]);

        $order = Order::sole();
        $response->assertRedirect("/pesanan/{$order->kode_pickup}");

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $order->kode_pickup);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertSame(64000, (int) $order->total);
        $this->assertSame(64000, (int) $order->subtotal);

        $item = OrderItem::sole();
        $this->assertSame('Kopi Susu Gula Aren', $item->nama_snapshot);
        $this->assertSame(22000, (int) $item->harga_snapshot);
        $this->assertSame(64000, (int) $item->subtotal);
        $this->assertSame('Es dipisah', $item->catatan);
        $this->assertSame(['Large', 'Dingin', 'Kurang', 'Extra Shot'], $item->options->pluck('nama_snapshot')->all());
        $this->assertSame(5000, (int) $item->options->firstWhere('nama_snapshot', 'Large')->selisih_snapshot);

        $this->assertSame(0, CartItem::count());

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Menunggu, $payment->status);
        $this->assertSame(PaymentMethod::Kasir, $payment->metode);
        $this->assertNull($payment->gateway_order_id);
        $this->assertSame(64000, (int) $payment->nominal);
        $this->assertTrue($payment->expired_at->equalTo(today()->setTime(22, 0)));

        $this->assertCount(1, $order->statusLogs);
        $this->assertNull($order->statusLogs->first()->status_lama);
        $this->assertSame($this->user->id, $order->statusLogs->first()->diubah_oleh);
    }

    public function test_snapshot_keeps_old_price_after_product_price_changes(): void
    {
        $order = $this->checkoutKasir($this->user);
        $this->kopiSusu()->update(['harga_dasar' => 99000]);

        $this->assertSame(64000, (int) $order->fresh()->total);
        $this->assertSame(22000, (int) $order->items()->first()->harga_snapshot);
    }

    public function test_sold_out_product_rejects_checkout_without_saving_anything(): void
    {
        $this->fillCart($this->user);
        $this->kopiSusu()->update(['tersedia' => false]);

        $this->actingAs($this->user)->from('/checkout')->post('/checkout', ['metode' => 'KASIR'])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['checkout' => 'Kopi Susu Gula Aren sedang habis. Hapus dari keranjang untuk melanjutkan.']);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, CartItem::count());
    }

    public function test_sold_out_option_also_rejects_checkout(): void
    {
        $this->fillCart($this->user);
        $this->kopiSusu()->optionGroups->firstWhere('nama', 'Tambahan')->options->firstWhere('nama', 'Extra Shot')->update(['tersedia' => false]);

        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR'])->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejected_when_store_closed_or_outside_hours(): void
    {
        $this->fillCart($this->user);

        StoreSetting::current()->update(['pemesanan_dibuka' => false]);
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR'])->assertSessionHasErrors('checkout');

        StoreSetting::current()->update(['pemesanan_dibuka' => true]);
        $this->travelTo(today()->setTime(22, 30));
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR'])->assertSessionHasErrors('checkout');

        $this->travelTo(today()->setTime(6, 59));
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR'])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_empty_cart_and_unconfigured_online_payment_are_rejected(): void
    {
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR'])->assertSessionHasErrors('checkout');

        $this->fillCart($this->user);
        config(['services.midtrans.server_key' => null]);
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'ONLINE'])->assertSessionHasErrors('metode');
        $this->actingAs($this->user)->post('/checkout', ['metode' => 'TRANSFER'])->assertSessionHasErrors('metode');
        $this->assertSame(0, Order::count());
    }

    public function test_percent_voucher_discount_and_quota(): void
    {
        $voucher = Voucher::factory()->create(['kode' => 'HEMAT10', 'jenis' => VoucherType::Persen, 'nilai' => 10]);
        $this->fillCart($this->user);

        $this->actingAs($this->user)->postJson('/checkout/voucher', ['kode' => 'hemat10'])
            ->assertOk()
            ->assertJsonPath('data', ['kode' => 'HEMAT10', 'jenis' => 'PERSEN', 'subtotal' => 64000, 'diskon' => 6400, 'total' => 57600]);

        $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR', 'voucher' => 'hemat10']);

        $order = Order::sole();
        $this->assertSame(6400, (int) $order->diskon);
        $this->assertSame(57600, (int) $order->total);
        $this->assertSame(57600, (int) $order->payments()->first()->nominal);
        $this->assertSame($voucher->id, $order->voucher_id);
        $this->assertSame(1, $voucher->fresh()->terpakai);
    }

    public function test_nominal_voucher_and_minimum_total(): void
    {
        Voucher::factory()->create(['kode' => 'POTONG5K', 'jenis' => VoucherType::Nominal, 'nilai' => 5000]);
        Voucher::factory()->create(['kode' => 'GRATIS', 'jenis' => VoucherType::Nominal, 'nilai' => 500000]);
        Voucher::factory()->create(['kode' => 'SERATUS', 'jenis' => VoucherType::Persen, 'nilai' => 100]);
        $this->fillCart($this->user);

        $this->actingAs($this->user)->postJson('/checkout/voucher', ['kode' => 'POTONG5K'])->assertJsonPath('data.total', 59000);
        // BR-15: diskon dibatasi agar total minimal Rp1.000.
        $this->postJson('/checkout/voucher', ['kode' => 'GRATIS'])->assertJsonPath('data.diskon', 63000)->assertJsonPath('data.total', 1000);
        $this->postJson('/checkout/voucher', ['kode' => 'SERATUS'])->assertJsonPath('data.total', 1000);
    }

    public function test_invalid_vouchers_are_rejected_without_creating_order(): void
    {
        Voucher::factory()->create(['kode' => 'HABIS', 'kuota' => 5, 'terpakai' => 5]);
        Voucher::factory()->create(['kode' => 'NANTI', 'berlaku_mulai' => now()->addDay(), 'berlaku_sampai' => now()->addDays(5)]);
        Voucher::factory()->create(['kode' => 'LEWAT', 'berlaku_mulai' => now()->subDays(5), 'berlaku_sampai' => now()->subMinute()]);
        $this->fillCart($this->user);

        $cases = [
            'HABIS' => 'Kuota voucher sudah habis.',
            'NANTI' => 'Voucher belum berlaku.',
            'LEWAT' => 'Voucher sudah kedaluwarsa.',
            'TIDAKADA' => 'Kode voucher tidak ditemukan.',
        ];
        foreach ($cases as $kode => $message) {
            $this->actingAs($this->user)->postJson('/checkout/voucher', ['kode' => $kode])
                ->assertUnprocessable()
                ->assertJsonPath('errors.kode.0', $message);
            $this->actingAs($this->user)->post('/checkout', ['metode' => 'KASIR', 'voucher' => $kode])
                ->assertSessionHasErrors(['voucher' => $message]);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(5, Voucher::where('kode', 'HABIS')->value('terpakai'));
    }

    public function test_voucher_quota_returns_when_order_cancelled_before_payment(): void
    {
        $voucher = Voucher::factory()->create(['kode' => 'HEMAT10', 'kuota' => 1]);
        $order = $this->checkoutKasir($this->user, ['voucher' => 'HEMAT10']);
        $this->assertSame(1, $voucher->fresh()->terpakai);

        $this->actingAs($this->user)->post("/pesanan/{$order->kode_pickup}/batal")->assertRedirect();

        $this->assertSame(0, $voucher->fresh()->terpakai);
        $this->assertSame(PaymentStatus::Batal, $order->payments()->first()->status);
    }

    public function test_checkout_page_shows_cart_and_closing_time(): void
    {
        $this->fillCart($this->user);

        $this->actingAs($this->user)->get('/checkout')
            ->assertOk()
            ->assertSee('Kopi Susu Gula Aren')
            ->assertSee('Rp64.000')
            ->assertSee('Buat Pesanan');

        CartItem::query()->delete();
        $this->get('/checkout')->assertRedirect('/keranjang');
    }
}
