<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** T-04, T-15, T-18 bagian pelanggan, halaman pesanan, status JSON, ganti metode, pesan ulang, struk. */
class OrderTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
        $this->customer = User::factory()->create(['nama' => 'Rina']);
        $this->order = $this->checkoutKasir($this->customer);
    }

    public function test_other_customer_gets_404_on_every_order_route(): void
    {
        $kode = $this->order->kode_pickup;
        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        $this->get("/pesanan/{$kode}")->assertNotFound();
        $this->getJson("/pesanan/{$kode}/status")->assertNotFound();
        $this->get("/pesanan/{$kode}/struk")->assertNotFound();
        $this->post("/pesanan/{$kode}/batal")->assertNotFound();
        $this->postJson("/pesanan/{$kode}/bayar-kasir")->assertNotFound();
        $this->post("/pesanan/{$kode}/pesan-ulang")->assertNotFound();

        $this->assertSame(OrderStatus::MenungguPembayaran, $this->order->fresh()->status);
    }

    public function test_owner_sees_order_page_and_status_json(): void
    {
        $this->actingAs($this->customer)->get("/pesanan/{$this->order->kode_pickup}")
            ->assertOk()
            ->assertSee($this->order->kode_pickup)
            ->assertSee('Menunggu Pembayaran')
            ->assertSee('Rp64.000')
            ->assertSee('qris-contoh.svg');

        $this->getJson("/pesanan/{$this->order->kode_pickup}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'MENUNGGU_PEMBAYARAN')
            ->assertJsonPath('data.status_label', 'Menunggu Pembayaran')
            ->assertJsonPath('data.metode', 'KASIR')
            ->assertJsonPath('data.payment_status', 'MENUNGGU')
            ->assertJsonPath('data.total', 64000)
            ->assertJsonPath('data.is_final', false);

        $this->get('/pesanan')->assertOk()->assertSee($this->order->kode_pickup);
    }

    public function test_cashier_confirmation_marks_paid_once_with_audit(): void
    {
        $staff = User::factory()->karyawan()->create();
        $url = "/karyawan/pesanan/{$this->order->kode_pickup}/konfirmasi-bayar";

        $this->actingAs($staff)->postJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Pembayaran dikonfirmasi.')
            ->assertJsonPath('data.status', 'DIBAYAR');

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Dibayar, $order->status);
        $this->assertNotNull($order->paid_at);
        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Lunas, $payment->status);
        $this->assertSame($staff->id, $payment->diverifikasi_oleh);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame($staff->id, $order->statusLogs->last()->diubah_oleh);
        $this->assertSame(1, AuditLog::where('aksi', 'KONFIRMASI_BAYAR')->count());

        $this->actingAs($staff)->postJson($url)->assertConflict()->assertJsonPath('message', 'Pesanan sudah dibayar.');
        $this->assertSame(1, AuditLog::where('aksi', 'KONFIRMASI_BAYAR')->count());
        $this->assertCount(2, $order->statusLogs);

        // Halaman pelanggan sekarang menampilkan Pembayaran Berhasil dan struk tersedia.
        $this->actingAs($this->customer)->getJson("/pesanan/{$this->order->kode_pickup}/status")->assertJsonPath('data.status', 'DIBAYAR');
        $this->get("/pesanan/{$this->order->kode_pickup}/struk")->assertOk()->assertSee('Kopi Susu Gula Aren')->assertSee('Bayar di Kasir');
    }

    public function test_cashier_confirmation_rejects_online_payment_and_customers(): void
    {
        $this->order->payments()->update(['metode' => PaymentMethod::Online, 'gateway_order_id' => $this->order->kode_pickup.'-1']);
        $staff = User::factory()->karyawan()->create();
        $url = "/karyawan/pesanan/{$this->order->kode_pickup}/konfirmasi-bayar";

        $this->actingAs($staff)->postJson($url)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Konfirmasi hanya untuk metode Bayar di Kasir.');
        $this->actingAs($this->customer)->postJson($url)->assertForbidden();

        $this->assertSame(OrderStatus::MenungguPembayaran, $this->order->fresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_staff_find_by_code(): void
    {
        $staff = User::factory()->karyawan()->create();

        $this->actingAs($staff)->getJson('/karyawan/pesanan/cari?kode='.strtolower($this->order->kode_pickup))
            ->assertOk()
            ->assertJsonPath('data.nama_pelanggan', 'Rina')
            ->assertJsonPath('data.metode', 'KASIR')
            ->assertJsonPath('data.total', 64000)
            ->assertJsonPath('data.items.0.opsi', ['Large', 'Dingin', 'Kurang', 'Extra Shot'])
            ->assertJsonPath('data.can_confirm_payment', true)
            ->assertJsonPath('data.can_pickup', false);

        $this->getJson('/karyawan/pesanan/cari?kode=ZZZZZZ')->assertNotFound()->assertJsonPath('message', 'Kode pesanan tidak valid.');
        $this->get('/karyawan/kasir')->assertOk()->assertSee('Konfirmasi pembayaran');
    }

    public function test_customer_can_cancel_only_while_waiting_for_payment(): void
    {
        $this->actingAs($this->customer)->post("/pesanan/{$this->order->kode_pickup}/batal")
            ->assertRedirect("/pesanan/{$this->order->kode_pickup}");

        $order = $this->order->fresh();
        $this->assertSame(OrderStatus::Dibatalkan, $order->status);
        $this->assertSame($this->customer->id, $order->dibatalkan_oleh);
        $this->assertSame(PaymentStatus::Batal, $order->payments()->sole()->status);

        $paid = $this->checkoutKasir($this->customer);
        app(OrderService::class)->confirmCashierPayment($paid, User::factory()->karyawan()->create());

        $this->actingAs($this->customer)->post("/pesanan/{$paid->kode_pickup}/batal")->assertConflict();
        $this->assertSame(OrderStatus::Dibayar, $paid->fresh()->status);
    }

    public function test_invalid_transition_is_conflict(): void
    {
        $this->expectException(ConflictHttpException::class);
        app(OrderService::class)->transition($this->order, OrderStatus::Selesai, null);
    }

    public function test_switch_to_cashier_cancels_old_online_payment(): void
    {
        $this->order->payments()->update(['metode' => PaymentMethod::Online, 'gateway_order_id' => $this->order->kode_pickup.'-1']);

        $this->actingAs($this->customer)->postJson("/pesanan/{$this->order->kode_pickup}/bayar-kasir")
            ->assertOk()
            ->assertJsonPath('data.metode', 'KASIR')
            ->assertJsonPath('data.total', 64000);

        $payments = $this->order->payments()->orderBy('id')->get();
        $this->assertSame([PaymentStatus::Batal, PaymentStatus::Menunggu], $payments->pluck('status')->all());
        $this->assertSame(1, $this->order->payments()->where('status', PaymentStatus::Menunggu)->count());

        // Sudah KASIR: tidak membuat pembayaran baru.
        $this->postJson("/pesanan/{$this->order->kode_pickup}/bayar-kasir")->assertOk();
        $this->assertSame(2, $this->order->payments()->count());
    }

    public function test_reorder_adds_items_with_latest_price_and_skips_sold_out(): void
    {
        $this->kopiSusu()->update(['harga_dasar' => 25000]);

        $this->actingAs($this->customer)->post("/pesanan/{$this->order->kode_pickup}/pesan-ulang")
            ->assertRedirect('/keranjang')
            ->assertSessionHas('status', '1 item ditambahkan ke keranjang dengan harga terbaru.');
        $this->get('/keranjang')->assertSee('Rp70.000');

        CartItem::query()->delete();
        $this->kopiSusu()->update(['tersedia' => false]);
        $this->post("/pesanan/{$this->order->kode_pickup}/pesan-ulang")
            ->assertSessionHas('status', 'Tidak ada item yang dapat ditambahkan. Dilewati karena habis atau berubah: Kopi Susu Gula Aren.');
        $this->assertSame(0, CartItem::count());
    }

    public function test_receipt_unavailable_before_payment(): void
    {
        $this->actingAs($this->customer)->get("/pesanan/{$this->order->kode_pickup}/struk")->assertNotFound();
    }
}
