<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Perbaikan audit UI/UX: beranda, banner tutup di katalog, footer kedai, keranjang ponsel, riwayat ponsel. */
class StorefrontTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
    }

    public function test_home_shows_open_state_and_hides_login_for_signed_in_customer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Buka sekarang')
            ->assertSee('Masuk')
            ->assertSee('Jl. Contoh Raya No. 12, Bandung');

        $this->actingAs(User::factory()->create())->get('/')
            ->assertDontSee('>Masuk<', false)
            ->assertSee('Pesanan saya');

        $this->travelTo(today()->setTime(23, 0));
        $this->get('/')->assertSee('Sedang tutup');
    }

    public function test_menu_warns_when_ordering_is_closed(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/menu')->assertDontSee('Kamu tetap bisa melihat menu');

        StoreSetting::current()->update(['pemesanan_dibuka' => false]);
        $this->get('/menu')->assertSee('Pemesanan sedang ditutup sementara.');

        StoreSetting::current()->update(['pemesanan_dibuka' => true]);
        $this->travelTo(today()->setTime(6, 0));
        $this->get('/menu')->assertSee('Kedai sedang tutup.');
    }

    public function test_mobile_cart_button_and_history_cards(): void
    {
        $customer = User::factory()->create();
        $order = $this->checkoutKasir($customer);
        $this->fillCart($customer, 3);

        $this->actingAs($customer)->get('/pesanan')
            ->assertSee('kk-cart-mobile', false)
            ->assertSee('list-group d-md-none', false)
            ->assertSee($order->kode_pickup);
        $this->assertSame(2, substr_count($this->get('/menu')->getContent(), 'js-cart-count">3<'));

        $this->get("/pesanan/{$order->kode_pickup}")->assertSee('<h1', false)->assertSee('Salin kode');
    }

    public function test_pages_render_without_store_settings(): void
    {
        StoreSetting::query()->delete();

        $this->get('/login')->assertOk();
        $this->get('/')->assertOk()->assertDontSee('Buka sekarang');
    }
}
