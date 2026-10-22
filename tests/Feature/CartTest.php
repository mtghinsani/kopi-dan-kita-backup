<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-05: validasi server untuk tambah item keranjang, ditambah ubah jumlah, hapus, dan kepemilikan item.
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
        $this->user = User::factory()->create();
        $this->kopi = Product::where('nama', 'Kopi Susu Gula Aren')->with('optionGroups.options')->firstOrFail();
    }

    /** Ukuran Large, Suhu Dingin, Gula Kurang, Tambahan Extra Shot. */
    private function validOptions(): array
    {
        $pick = fn (string $group, string $option) => $this->kopi->optionGroups->firstWhere('nama', $group)->options->firstWhere('nama', $option)->id;

        return [$pick('Ukuran', 'Large'), $pick('Suhu', 'Dingin'), $pick('Gula', 'Kurang'), $pick('Tambahan', 'Extra Shot')];
    }

    private function add(array $overrides = [])
    {
        return $this->actingAs($this->user)->postJson('/keranjang/items', array_merge([
            'product_id' => $this->kopi->id,
            'qty' => 2,
            'options' => $this->validOptions(),
            'catatan' => '<script>alert(1)</script>',
            'harga' => 1,
        ], $overrides));
    }

    public function test_valid_item_is_added_with_server_side_price(): void
    {
        // 22.000 + Large 5.000 + Extra Shot 5.000 = 32.000; field harga dari request diabaikan.
        $this->add()
            ->assertCreated()
            ->assertJsonPath('message', 'Item ditambahkan ke keranjang.')
            ->assertJsonPath('data.cart_count', 2)
            ->assertJsonPath('data.item.harga_satuan', 32000)
            ->assertJsonPath('data.item.subtotal', 64000)
            ->assertJsonPath('data.item.opsi', ['Large', 'Dingin', 'Kurang', 'Extra Shot']);

        $item = CartItem::sole();
        $this->assertSame($this->user->id, $item->cart->user_id);
        $this->assertCount(4, $item->options);
        $this->assertSame('<script>alert(1)</script>', $item->catatan);
    }

    public function test_option_from_another_product_is_rejected(): void
    {
        $other = Product::where('nama', 'Americano')->firstOrFail()->optionGroups->first()->options->first();
        $options = $this->validOptions();
        $options[0] = $other->id;

        $this->add(['options' => $options])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->assertSame(0, CartItem::count());
    }

    public function test_empty_mandatory_group_is_rejected(): void
    {
        $this->add(['options' => array_slice($this->validOptions(), 0, 1)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.options.0', 'Pilih minimal 1 untuk Suhu.');
    }

    public function test_exceeding_max_selection_is_rejected(): void
    {
        $ukuran = $this->kopi->optionGroups->firstWhere('nama', 'Ukuran')->options->pluck('id')->all();
        $options = array_merge($ukuran, array_slice($this->validOptions(), 1));

        $this->add(['options' => $options])
            ->assertUnprocessable()
            ->assertJsonPath('errors.options.0', 'Pilih maksimal 1 untuk Ukuran.');
    }

    public function test_qty_outside_range_is_rejected(): void
    {
        $this->add(['qty' => 11])->assertUnprocessable()->assertJsonValidationErrors('qty');
        $this->add(['qty' => 0])->assertUnprocessable()->assertJsonValidationErrors('qty');
    }

    public function test_sold_out_product_and_option_are_rejected(): void
    {
        $this->kopi->update(['tersedia' => false]);
        $this->add()->assertUnprocessable()->assertJsonValidationErrors('product_id');

        $this->kopi->update(['tersedia' => true]);
        Option::whereKey($this->validOptions()[3])->update(['tersedia' => false]);
        $this->add()->assertUnprocessable()->assertJsonPath('errors.options.0', 'Opsi Extra Shot sedang habis.');

        $this->assertSame(0, CartItem::count());
    }

    public function test_note_longer_than_100_characters_is_rejected(): void
    {
        $this->add(['catatan' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('catatan');
    }

    public function test_qty_update_and_delete_return_new_summary(): void
    {
        $id = $this->add()->json('data.item.id');

        $this->patchJson("/keranjang/items/{$id}", ['qty' => 3])
            ->assertOk()
            ->assertJsonPath('data.item.subtotal', 96000)
            ->assertJsonPath('data.cart', ['count' => 3, 'subtotal' => 96000]);

        $this->patchJson("/keranjang/items/{$id}", ['qty' => 11])->assertUnprocessable();

        $this->deleteJson("/keranjang/items/{$id}")
            ->assertOk()
            ->assertJsonPath('data.cart', ['count' => 0, 'subtotal' => 0]);
        $this->assertSame(0, CartItem::count());
    }

    public function test_other_customer_cannot_touch_item(): void
    {
        $id = $this->add()->json('data.item.id');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patchJson("/keranjang/items/{$id}", ['qty' => 5])->assertNotFound();
        $this->actingAs($intruder)->deleteJson("/keranjang/items/{$id}")->assertNotFound();
        $this->assertSame(2, CartItem::sole()->qty);
    }

    public function test_cart_persists_and_badge_shows_count_after_relogin(): void
    {
        $this->add();
        $this->post('/logout');

        $this->actingAs($this->user)->get('/keranjang')
            ->assertOk()
            ->assertSee('Kopi Susu Gula Aren')
            ->assertSee('Rp64.000')
            ->assertSee('js-cart-count">2<', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_options_endpoint_returns_groups(): void
    {
        $this->actingAs($this->user)->getJson("/menu/{$this->kopi->id}/opsi")
            ->assertOk()
            ->assertJsonPath('data.harga_dasar', 22000)
            ->assertJsonPath('data.groups.0.nama', 'Ukuran')
            ->assertJsonPath('data.groups.0.options.1.selisih_harga', 5000)
            ->assertJsonPath('data.groups.4.max_pilih', 3);
    }

    public function test_menu_search_and_category_filter(): void
    {
        $kopiCategory = $this->kopi->category_id;

        $this->actingAs($this->user)->get('/menu')->assertOk()->assertSee('Cinnamon Roll')->assertSee('Rp22.000');
        $this->get('/menu?q=LATTE')->assertSee('Caffe Latte')->assertSee('Matcha Latte')->assertDontSee('Americano');
        $this->get("/menu?q=latte&kategori={$kopiCategory}")->assertSee('Caffe Latte')->assertDontSee('Matcha Latte');
        $this->get('/menu?q=tidakada')->assertSee('Menu tidak ditemukan');

        Product::where('nama', 'Espresso')->update(['tersedia' => false]);
        $this->get('/menu?q=espresso')->assertSee('Habis')->assertSee('Espresso habis');
    }
}
