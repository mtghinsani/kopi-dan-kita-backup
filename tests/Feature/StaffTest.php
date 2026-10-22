<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** T-16, T-17, T-18 bagian kedai, ketersediaan, riwayat, struk karyawan. */
class StaffTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
        $this->staff = User::factory()->karyawan()->create();
    }

    private function paidOrder(int $minutesAgo = 0, ?User $customer = null): Order
    {
        $order = $this->checkoutKasir($customer ?? User::factory()->create());
        $this->travel(-$minutesAgo)->minutes();
        app(OrderService::class)->confirmCashierPayment($order, $this->staff);
        $this->travelBack();
        $this->travelTo(today()->setTime(10, 0));

        return $order->fresh();
    }

    public function test_queue_is_ordered_by_paid_at_without_unpaid_orders(): void
    {
        $later = $this->paidOrder(1);
        $first = $this->paidOrder(10);
        $unpaid = $this->checkoutKasir(User::factory()->create());
        $ready = $this->paidOrder(20);
        app(OrderService::class)->transition($ready, OrderStatus::Diproses, $this->staff);
        app(OrderService::class)->transition($ready, OrderStatus::SiapDiambil, $this->staff);

        $response = $this->actingAs($this->staff)->getJson('/karyawan/antrean')->assertOk();

        $this->assertSame([$first->kode_pickup, $later->kode_pickup], array_column($response->json('data.antrean'), 'kode'));
        $this->assertSame([$ready->kode_pickup], array_column($response->json('data.siap_diambil'), 'kode'));
        $this->assertNotContains($unpaid->kode_pickup, array_column($response->json('data.antrean'), 'kode'));
        $response->assertJsonPath('data.antrean.0.items.0.opsi', ['Large', 'Dingin', 'Kurang', 'Extra Shot'])
            ->assertJsonPath('data.antrean.0.items.0.catatan', 'Es dipisah')
            ->assertJsonPath('data.antrean.0.metode', 'KASIR');

        $this->get('/karyawan')->assertOk()->assertSee('Antrean');
    }

    public function test_valid_transitions_are_logged_and_invalid_ones_conflict(): void
    {
        $order = $this->paidOrder();
        $url = "/karyawan/pesanan/{$order->kode_pickup}/status";

        $this->actingAs($this->staff)->patchJson($url, ['status' => 'SIAP_DIAMBIL'])
            ->assertConflict()
            ->assertJsonPath('message', 'Perubahan status dari DIBAYAR ke SIAP_DIAMBIL tidak diizinkan.');

        $this->patchJson($url, ['status' => 'DIPROSES'])->assertOk()->assertJsonPath('data.status_label', 'Diproses');
        $this->patchJson($url, ['status' => 'SIAP_DIAMBIL'])->assertOk();
        $this->patchJson($url, ['status' => 'SELESAI'])->assertUnprocessable();

        $order->refresh();
        $this->assertNotNull($order->ready_at);
        $this->assertSame($this->staff->id, $order->statusLogs->last()->diubah_oleh);
        $this->assertSame(OrderStatus::SiapDiambil, $order->statusLogs->last()->status_baru);
    }

    public function test_pickup_only_when_ready(): void
    {
        $order = $this->paidOrder();
        $url = "/karyawan/pesanan/{$order->kode_pickup}/ambil";

        $this->actingAs($this->staff)->postJson($url)->assertConflict()->assertJsonPath('message', 'Pesanan belum siap diambil.');

        app(OrderService::class)->transition($order, OrderStatus::Diproses, $this->staff);
        app(OrderService::class)->transition($order, OrderStatus::SiapDiambil, $this->staff);

        $this->getJson('/karyawan/pesanan/cari?kode='.$order->kode_pickup)->assertJsonPath('data.can_pickup', true);
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'SELESAI');
        $this->assertNotNull($order->fresh()->completed_at);
        $this->postJson($url)->assertConflict();

        $this->get("/karyawan/pesanan/{$order->kode_pickup}/struk")->assertOk()->assertSee($order->kode_pickup);
        $this->get('/karyawan/pengambilan')->assertOk();
    }

    public function test_store_cancel_requires_reason_and_marks_paid_order_for_refund(): void
    {
        $order = $this->paidOrder();
        $url = "/karyawan/pesanan/{$order->kode_pickup}/batal";

        $this->actingAs($this->staff)->postJson($url, ['alasan' => ''])->assertUnprocessable()->assertJsonValidationErrors('alasan');
        $this->postJson($url, ['alasan' => str_repeat('a', 256)])->assertUnprocessable();
        $this->assertSame(0, AuditLog::where('aksi', 'BATAL_KEDAI')->count());

        $this->postJson($url, ['alasan' => 'Susu oat habis'])
            ->assertOk()
            ->assertJsonPath('data.status', 'DIBATALKAN')
            ->assertJsonPath('data.perlu_refund', true);

        $order->refresh();
        $this->assertSame('Susu oat habis', $order->alasan_batal);
        $this->assertSame($this->staff->id, $order->dibatalkan_oleh);
        $this->assertSame(PaymentStatus::Lunas, $order->payments()->sole()->status);
        $this->assertSame(1, AuditLog::where('aksi', 'BATAL_KEDAI')->count());

        // Pelanggan melihat alasan di halaman pesanannya.
        $this->actingAs($order->user)->get("/pesanan/{$order->kode_pickup}")->assertSee('Alasan dari kedai: Susu oat habis');

        $this->actingAs($this->staff)->postJson($url, ['alasan' => 'Lagi'])->assertConflict();
    }

    public function test_store_cancel_before_payment_returns_voucher_quota(): void
    {
        $voucher = Voucher::factory()->create(['kode' => 'HEMAT10']);
        $order = $this->checkoutKasir(User::factory()->create(), ['voucher' => 'HEMAT10']);

        $this->actingAs($this->staff)->postJson("/karyawan/pesanan/{$order->kode_pickup}/batal", ['alasan' => 'Kedai tutup lebih awal'])
            ->assertOk()
            ->assertJsonPath('data.perlu_refund', false);

        $this->assertSame(0, $voucher->fresh()->terpakai);
        $this->assertSame(PaymentStatus::Batal, $order->payments()->sole()->status);
    }

    public function test_availability_toggle_is_audited_and_affects_catalog(): void
    {
        $kopi = $this->kopiSusu();
        $option = $kopi->optionGroups->firstWhere('nama', 'Tambahan')->options->first();

        $this->actingAs($this->staff)->get('/karyawan/ketersediaan')->assertOk()->assertSee('Kopi Susu Gula Aren');

        $this->patchJson("/karyawan/produk/{$kopi->id}/ketersediaan", ['tersedia' => false])
            ->assertOk()
            ->assertJsonPath('data.label', 'Habis');
        $this->patchJson("/karyawan/opsi/{$option->id}/ketersediaan", ['tersedia' => false])->assertOk();

        $this->assertFalse($kopi->fresh()->tersedia);
        $this->assertFalse($option->fresh()->tersedia);
        $this->assertSame(2, AuditLog::where('aksi', 'UBAH_KETERSEDIAAN')->count());

        $this->actingAs(User::factory()->create())->get('/menu?q=gula')->assertSee('Kopi Susu Gula Aren habis');
    }

    public function test_history_search_and_detail(): void
    {
        $order = $this->paidOrder(0, User::factory()->create(['nama' => 'Bagas Saputra']));

        $this->actingAs($this->staff)->get('/karyawan/riwayat?q=bagas')->assertOk()->assertSee($order->kode_pickup);
        $this->get('/karyawan/riwayat?q='.$order->kode_pickup)->assertSee('Bagas Saputra');
        $this->get('/karyawan/riwayat?q=tidakada')->assertDontSee($order->kode_pickup);
        $this->get('/karyawan/riwayat?dari=2026-01-02&sampai=2026-01-01')->assertSessionHasErrors('sampai');

        $this->get("/karyawan/riwayat/{$order->kode_pickup}")
            ->assertOk()
            ->assertSee('Riwayat status')
            ->assertSee('Dibayar')
            ->assertSee($this->staff->nama);
    }
}
