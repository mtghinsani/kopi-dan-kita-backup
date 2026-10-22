<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** T-20 sampai T-23 dan CRUD panel admin. */
class AdminTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
        $this->admin = User::factory()->superAdmin()->create();
        config(['services.midtrans.server_key' => 'SB-Mid-server-TESTKEY', 'services.midtrans.client_key' => 'SB-Mid-client-TESTKEY']);
    }

    /** Pesanan online lunas lalu dibatalkan kedai: Perlu Refund. */
    private function cancelledOnlineOrder(): Order
    {
        $order = $this->checkoutKasir(User::factory()->create());
        $order->payments()->update(['metode' => PaymentMethod::Online, 'gateway_order_id' => $order->kode_pickup.'-1-1', 'transaction_id' => 'trx-123', 'payment_type' => 'qris']);
        app(MidtransService::class)->applyStatus($order->payments()->sole(), ['transaction_status' => 'settlement', 'status_code' => '200', 'gross_amount' => '64000.00']);
        app(OrderService::class)->cancelByStore($order->fresh(), $this->admin, 'Mesin rusak');

        return $order->fresh();
    }

    public function test_online_refund_success(): void
    {
        $order = $this->cancelledOnlineOrder();
        $this->assertTrue($order->needsRefund());
        Http::fake(['api.sandbox.midtrans.com/v2/trx-123/refund' => Http::response(['status_code' => '200', 'transaction_status' => 'refund'])]);

        $this->actingAs($this->admin)->post("/admin/pesanan/{$order->kode_pickup}/refund")->assertSessionHas('status', 'Refund online berhasil.');

        $refund = Refund::sole();
        $this->assertSame(RefundStatus::Selesai, $refund->status);
        $this->assertSame('RF-'.$order->kode_pickup.'-1-1', $refund->refund_key);
        $this->assertSame($this->admin->id, $refund->diproses_oleh);
        $this->assertNotNull($refund->processed_at);
        $this->assertFalse($order->needsRefund());
        $this->assertSame(1, AuditLog::where('aksi', 'REFUND_ONLINE')->count());
        Http::assertSent(fn (Request $r) => $r['amount'] === 64000 && $r['refund_key'] === $refund->refund_key && str_contains($r['reason'], 'Mesin rusak'));

        // Refund kedua ditolak tanpa request baru.
        $this->post("/admin/pesanan/{$order->kode_pickup}/refund")->assertSessionHasErrors('refund');
        $this->post("/admin/pesanan/{$order->kode_pickup}/refund-manual", ['no_referensi' => 'X'])->assertSessionHasErrors('refund');
        Http::assertSentCount(1);
    }

    public function test_online_refund_rejected_stays_waiting_and_manual_fallback_works(): void
    {
        $order = $this->cancelledOnlineOrder();
        Http::fake(['*/refund' => Http::response(['status_code' => '412', 'status_message' => 'Merchant cannot modify the status of the transaction'])]);

        $this->actingAs($this->admin)->post("/admin/pesanan/{$order->kode_pickup}/refund")
            ->assertSessionHasErrors(['refund' => 'Refund API gagal: Merchant cannot modify the status of the transaction. Refund tetap menunggu; Anda dapat mencatat refund manual.']);
        $this->assertSame(RefundStatus::Menunggu, Refund::sole()->status);
        $this->assertTrue($order->needsRefund());

        $this->post("/admin/pesanan/{$order->kode_pickup}/refund-manual", ['no_referensi' => 'TRF-001'])->assertSessionHas('status');
        $this->assertSame(RefundStatus::Selesai, Refund::sole()->status);
        $this->assertSame('TRF-001', Refund::sole()->no_referensi);
        $this->assertSame(1, AuditLog::where('aksi', 'REFUND_MANUAL')->count());
    }

    public function test_cashier_refund_requires_reference(): void
    {
        $order = $this->checkoutKasir(User::factory()->create());
        app(OrderService::class)->confirmCashierPayment($order, $this->admin);
        app(OrderService::class)->cancelByStore($order->fresh(), $this->admin, 'Tumpah');

        $this->actingAs($this->admin)->post("/admin/pesanan/{$order->kode_pickup}/refund-manual", ['no_referensi' => ''])
            ->assertSessionHasErrors('no_referensi');
        $this->post("/admin/pesanan/{$order->kode_pickup}/refund")->assertSessionHasErrors('refund');
        $this->assertSame(0, Refund::count());

        $this->get("/admin/pesanan/{$order->kode_pickup}")->assertOk()->assertSee('Perlu Refund')->assertSee('Catat Refund Manual')->assertDontSee('Proses Refund lewat Midtrans');
        $this->get('/admin/pesanan?perlu_refund=1')->assertSee($order->kode_pickup);
        $this->get('/admin')->assertOk()->assertSee($order->kode_pickup);
    }

    public function test_uploads_reject_php_svg_and_large_files(): void
    {
        Storage::fake('public');
        $product = $this->kopiSusu();
        $base = ['category_id' => $product->category_id, 'nama' => $product->nama, 'harga_dasar' => 22000, 'tersedia' => 1];
        $this->actingAs($this->admin);

        foreach ([
            UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('besar.jpg')->size(3000),
        ] as $file) {
            $this->put("/admin/products/{$product->id}", $base + ['gambar' => $file])->assertSessionHasErrors('gambar');
            $this->put('/admin/pengaturan', $this->settingsPayload() + ['qris_image' => $file])->assertSessionHasErrors('qris_image');
        }
        $this->assertNull($product->fresh()->gambar);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->put("/admin/products/{$product->id}", $base + ['gambar' => UploadedFile::fake()->image('kopi.png', 400, 300)])->assertRedirect();
        Storage::disk('public')->assertExists($product->fresh()->gambar);
    }

    private function settingsPayload(): array
    {
        return [
            'nama_kedai' => 'Kopi & Kita', 'alamat' => 'Jl. Contoh Raya No. 12, Bandung', 'kontak' => '0812-0000-0000', 'jam_buka' => '07:00', 'jam_tutup' => '22:00',
            'batas_bayar_menit' => 15, 'pemesanan_dibuka' => 1,
        ];
    }

    public function test_settings_validation_and_audit(): void
    {
        $this->actingAs($this->admin);

        $this->put('/admin/pengaturan', ['jam_tutup' => '06:00', 'batas_bayar_menit' => 61] + $this->settingsPayload())
            ->assertSessionHasErrors(['jam_tutup', 'batas_bayar_menit']);

        $this->put('/admin/pengaturan', $this->settingsPayload())->assertRedirect('/admin/pengaturan');
        $this->assertSame(0, AuditLog::where('aksi', 'UBAH_PENGATURAN')->count());

        $this->put('/admin/pengaturan', ['jam_tutup' => '21:00', 'pemesanan_dibuka' => 0] + $this->settingsPayload())->assertRedirect();
        $setting = StoreSetting::current();
        $this->assertFalse($setting->pemesanan_dibuka);
        $log = AuditLog::where('aksi', 'UBAH_PENGATURAN')->sole();
        $this->assertSame(['jam_tutup' => '22:00:00', 'pemesanan_dibuka' => true], $log->nilai_lama);
        $this->assertSame(['jam_tutup' => '21:00:00', 'pemesanan_dibuka' => false], $log->nilai_baru);
    }

    public function test_report_counts_only_completed_orders_in_range(): void
    {
        $service = app(OrderService::class);
        $now = now()->toImmutable();
        $complete = function (int $daysAgo) use ($service, $now) {
            $order = $this->checkoutKasir(User::factory()->create());
            $this->travelTo($now->subDays($daysAgo)->setTime(9, 0));
            $service->confirmCashierPayment($order, $this->admin);
            $service->transition($order, OrderStatus::Diproses, $this->admin);
            $service->transition($order, OrderStatus::SiapDiambil, $this->admin);
            $service->transition($order, OrderStatus::Selesai, $this->admin);
            $this->travelTo($now);

            return $order;
        };

        $complete(0);
        $complete(2);
        $complete(10);
        $paidOnly = $this->checkoutKasir(User::factory()->create());
        $service->confirmCashierPayment($paidOnly, $this->admin);
        $this->checkoutKasir(User::factory()->create());

        $response = $this->actingAs($this->admin)->get('/admin/laporan')->assertOk();
        $this->assertSame(2, $response->viewData('count'));
        $this->assertSame(128000, $response->viewData('revenue'));
        $this->assertSame(4, (int) $response->viewData('topProducts')->first()->jumlah);
        $response->assertSee('Kopi Susu Gula Aren');

        $range = $this->get('/admin/laporan?dari='.today()->subDays(11)->toDateString().'&sampai='.today()->subDays(9)->toDateString());
        $this->assertSame(1, $range->viewData('count'));

        $this->get('/admin/laporan?dari=2026-10-10&sampai=2026-10-01')->assertSessionHasErrors('sampai');
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = $this->kopiSusu()->category;
        $empty = Category::create(['nama' => 'Musiman', 'urutan' => 9]);
        $this->actingAs($this->admin);

        $this->delete("/admin/categories/{$category->id}")->assertSessionHasErrors('hapus');
        $this->assertModelExists($category);

        $this->delete("/admin/categories/{$empty->id}")->assertRedirect('/admin/categories');
        $this->assertModelMissing($empty);

        $this->post('/admin/categories', ['nama' => 'Kopi', 'urutan' => 1])->assertSessionHasErrors('nama');
    }

    public function test_price_change_is_audited_and_ordered_product_cannot_be_deleted(): void
    {
        $product = $this->kopiSusu();
        $this->checkoutKasir(User::factory()->create());
        $this->actingAs($this->admin);

        $this->put("/admin/products/{$product->id}", [
            'category_id' => $product->category_id, 'nama' => $product->nama, 'harga_dasar' => 24000, 'tersedia' => 1,
        ])->assertRedirect('/admin/products');

        $log = AuditLog::where('aksi', 'UBAH_HARGA')->sole();
        $this->assertSame(['harga_dasar' => 22000], $log->nilai_lama);
        $this->assertSame(['harga_dasar' => 24000], $log->nilai_baru);

        $this->delete("/admin/products/{$product->id}")->assertSessionHasErrors('hapus');
        $this->assertModelExists($product);

        $fresh = Product::create(['category_id' => $product->category_id, 'nama' => 'Menu Baru', 'harga_dasar' => 10000, 'tersedia' => true]);
        $this->delete("/admin/products/{$fresh->id}")->assertRedirect();
        $this->assertModelMissing($fresh);
    }

    public function test_option_group_rules_and_empty_group_hidden_from_catalog(): void
    {
        $product = Product::where('nama', 'Croissant Mentega')->firstOrFail();
        $this->actingAs($this->admin);

        $this->post("/admin/products/{$product->id}/option-groups", ['nama' => 'Saus', 'min_pilih' => 2, 'max_pilih' => 1, 'urutan' => 1])
            ->assertSessionHasErrorsIn('newGroup', 'max_pilih');

        $this->post("/admin/products/{$product->id}/option-groups", ['nama' => 'Saus', 'wajib' => 1, 'min_pilih' => 0, 'max_pilih' => 1, 'urutan' => 1])->assertRedirect();
        $group = $product->optionGroups()->sole();
        $this->assertTrue($group->wajib);
        $this->assertSame(1, $group->min_pilih);

        // Grup wajib tanpa opsi tidak tampil dan tidak menghalangi tambah ke keranjang.
        $customer = User::factory()->create();
        $this->actingAs($customer)->getJson("/menu/{$product->id}/opsi")->assertJsonCount(0, 'data.groups');
        $this->postJson('/keranjang/items', ['product_id' => $product->id, 'qty' => 1])->assertCreated();

        $this->actingAs($this->admin)->post("/admin/option-groups/{$group->id}/options", ['nama' => 'Cokelat', 'selisih_harga' => 2000, 'tersedia' => 1])->assertRedirect();
        $this->actingAs($customer)->getJson("/menu/{$product->id}/opsi")->assertJsonPath('data.groups.0.options.0.nama', 'Cokelat');
        $this->get("/admin/products/{$product->id}/opsi")->assertForbidden();
        $this->actingAs($this->admin)->get("/admin/products/{$product->id}/opsi")->assertOk()->assertSee('Cokelat');
    }

    public function test_voucher_validation(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/vouchers', ['kode' => 'hemat 10', 'jenis' => 'PERSEN', 'nilai' => 150, 'kuota' => 0, 'berlaku_mulai' => '2026-10-10 00:00', 'berlaku_sampai' => '2026-10-01 00:00'])
            ->assertSessionHasErrors(['kode', 'nilai', 'kuota', 'berlaku_sampai']);

        $this->post('/admin/vouchers', ['kode' => 'kopi20', 'jenis' => 'PERSEN', 'nilai' => 20, 'kuota' => 10, 'berlaku_mulai' => '2026-10-01T00:00', 'berlaku_sampai' => '2026-12-31T23:59'])
            ->assertRedirect('/admin/vouchers');
        $voucher = Voucher::where('kode', 'KOPI20')->sole();
        $voucher->update(['terpakai' => 3]);

        $this->put("/admin/vouchers/{$voucher->id}", ['kode' => 'KOPI20', 'jenis' => 'PERSEN', 'nilai' => 20, 'kuota' => 2, 'berlaku_mulai' => '2026-10-01T00:00', 'berlaku_sampai' => '2026-12-31T23:59'])
            ->assertSessionHasErrors('kuota');
        $this->delete("/admin/vouchers/{$voucher->id}")->assertSessionHasErrors('hapus');
        $this->assertSame(1, AuditLog::where('aksi', 'UBAH_VOUCHER')->count());
    }

    public function test_user_management(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/users', ['nama' => 'Dewi', 'email' => 'dewi@kopikita.test', 'no_hp' => '081211112222', 'role' => 'KARYAWAN', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect('/admin/users');
        $dewi = User::where('email', 'dewi@kopikita.test')->sole();
        $this->assertSame('KARYAWAN', $dewi->role->value);
        $this->assertTrue($dewi->aktif);

        $this->patch("/admin/users/{$dewi->id}/aktif")->assertRedirect();
        $this->assertFalse($dewi->fresh()->aktif);
        $this->patch("/admin/users/{$this->admin->id}/aktif")->assertSessionHasErrors('aktif');
        $this->assertTrue($this->admin->fresh()->aktif);

        $this->put("/admin/users/{$dewi->id}/password", ['password' => 'barupass1', 'password_confirmation' => 'barupass1'])->assertRedirect();
        $this->assertTrue(password_verify('barupass1', $dewi->fresh()->password_hash));

        $this->put("/admin/users/{$this->admin->id}", ['nama' => 'Admin Baru', 'email' => $this->admin->email, 'no_hp' => '081299990000', 'role' => 'PELANGGAN'])->assertRedirect();
        $this->assertSame('SUPER_ADMIN', $this->admin->fresh()->role->value);

        $this->assertSame(
            ['BUAT_AKUN', 'NONAKTIF_AKUN', 'RESET_PASSWORD', 'UBAH_AKUN'],
            AuditLog::orderBy('id')->pluck('aksi')->all(),
        );
        $this->get('/admin/users?q=dewi')->assertOk()->assertSee('dewi@kopikita.test');
        $this->get('/admin/audit-log?aksi=BUAT_AKUN')->assertOk()->assertSee('BUAT_AKUN')->assertDontSee('RESET_PASSWORD</code>', false);
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/pesanan', '/admin/transaksi', '/admin/products', '/admin/products/create', '/admin/categories',
            '/admin/categories/create', '/admin/vouchers', '/admin/vouchers/create', '/admin/users/create', '/admin/pengaturan', '/admin/laporan', '/admin/audit-log'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs(User::factory()->karyawan()->create())->get('/admin/products')->assertForbidden();
    }
}
