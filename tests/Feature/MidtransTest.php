<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** T-09 sampai T-14, T-18 (Cancel API), T-19, dengan Http::fake() tanpa jaringan. */
class MidtransTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TESTKEY';

    private User $customer;

    /** Status yang dikembalikan Status API palsu, per gateway_order_id; default 404. */
    private array $remote = [];

    private bool $snapFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShop();
        config([
            'services.midtrans.server_key' => self::SERVER_KEY,
            'services.midtrans.client_key' => 'SB-Mid-client-TESTKEY',
            'services.midtrans.notification_url' => 'https://contoh.ngrok-free.app/midtrans/notification',
        ]);
        Cache::flush();
        $this->customer = User::factory()->create(['nama' => 'Rina', 'no_hp' => '081234567890']);

        Http::fake([
            'app.sandbox.midtrans.com/snap/*' => fn () => $this->snapFails
                ? Http::response(['error_messages' => ['Access denied']], 401)
                : Http::response(['token' => 'snap-token-123', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/x']),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response(['status_code' => '200', 'transaction_status' => 'cancel']),
            'api.sandbox.midtrans.com/v2/*/status' => function (Request $request) {
                preg_match('#/v2/([^/]+)/status#', $request->url(), $m);

                return Http::response($this->remote[$m[1]] ?? ['status_code' => '404', 'status_message' => "Transaction doesn't exist."]);
            },
        ]);
    }

    private function checkoutOnline(array $extra = []): Order
    {
        $this->fillCart($this->customer);
        $this->actingAs($this->customer)->post('/checkout', ['metode' => 'ONLINE', ...$extra])->assertRedirect();

        return $this->customer->orders()->latest('id')->firstOrFail();
    }

    private function notify(Payment $payment, string $status, array $overrides = [])
    {
        $payload = array_merge([
            'transaction_status' => $status,
            'status_code' => $status === 'pending' ? '201' : ($status === 'expire' ? '407' : '200'),
            'order_id' => $payment->gateway_order_id,
            'gross_amount' => number_format((float) $payment->nominal, 2, '.', ''),
            'transaction_id' => 'trx-'.$payment->id,
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ], $overrides);
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::SERVER_KEY);

        return $this->postJson('/midtrans/notification', $payload);
    }

    public function test_snap_token_request_is_built_correctly_and_cached(): void
    {
        Voucher::factory()->create(['kode' => 'HEMAT10', 'jenis' => VoucherType::Persen, 'nilai' => 10]);

        $order = $this->checkoutOnline(['voucher' => 'HEMAT10']);
        $payment = $order->payments()->sole();

        $this->assertSame(PaymentMethod::Online, $payment->metode);
        $this->assertMatchesRegularExpression('/^'.$order->kode_pickup.'-\d{10}-1$/', $payment->gateway_order_id);

        Http::assertSent(function (Request $request) use ($payment) {
            if (! str_contains($request->url(), '/snap/v1/transactions')) {
                return false;
            }
            $body = $request->data();
            $sum = collect($body['item_details'])->sum(fn ($i) => $i['price'] * $i['quantity']);
            $voucher = collect($body['item_details'])->firstWhere('id', 'VOUCHER');

            return $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SERVER_KEY.':'))
                && $request->hasHeader('X-Override-Notification', 'https://contoh.ngrok-free.app/midtrans/notification')
                && $body['transaction_details'] === ['order_id' => $payment->gateway_order_id, 'gross_amount' => 57600]
                && $sum === 57600
                && $voucher['price'] === -6400
                && $body['item_details'][0]['price'] === 32000
                && $body['expiry']['duration'] === 15
                && $body['expiry']['unit'] === 'minute'
                && $body['enabled_payments'] === ['gopay', 'shopeepay']
                && $body['customer_details']['first_name'] === 'Rina';
        });

        $this->assertSame('snap-token-123', Cache::get("snap_token:{$payment->id}"));
        $this->assertTrue($payment->fresh()->expired_at->equalTo(now()->addMinutes(15)));

        // Lanjutkan Pembayaran memakai token di cache tanpa request baru ke Snap.
        $this->actingAs($this->customer)->postJson("/pesanan/{$order->kode_pickup}/bayar-online")
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-123')
            ->assertJsonPath('data.gateway_order_id', $payment->gateway_order_id);
        Http::assertSentCount(1);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_snap_failure_becomes_502_and_order_page_offers_retry(): void
    {
        $this->snapFails = true;

        $order = $this->checkoutOnline();
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->get("/pesanan/{$order->kode_pickup}")->assertOk()->assertSee('Coba Bayar Lagi');

        $this->postJson("/pesanan/{$order->kode_pickup}/bayar-online")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Gateway pembayaran tidak dapat dihubungi, coba lagi.');
    }

    public function test_wrong_signature_is_rejected_without_changes(): void
    {
        $payment = $this->checkoutOnline()->payments()->sole();

        $this->notify($payment, 'settlement', ['signature_key' => str_repeat('a', 128)])
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid signature');

        $this->assertSame(PaymentStatus::Menunggu, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MenungguPembayaran, $payment->order->fresh()->status);
    }

    public function test_valid_settlement_marks_payment_and_order_paid_by_system(): void
    {
        $order = $this->checkoutOnline();
        $payment = $order->payments()->sole();

        $this->notify($payment, 'settlement')->assertOk()->assertJsonPath('message', 'OK');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Lunas, $payment->status);
        $this->assertSame("trx-{$payment->id}", $payment->transaction_id);
        $this->assertSame('qris', $payment->payment_type);
        $this->assertNotNull($payment->verified_at);
        $this->assertNull($payment->diverifikasi_oleh);

        $order->refresh();
        $this->assertSame(OrderStatus::Dibayar, $order->status);
        $this->assertNotNull($order->paid_at);
        $log = $order->statusLogs->last();
        $this->assertSame(OrderStatus::Dibayar, $log->status_baru);
        $this->assertNull($log->diubah_oleh);
    }

    public function test_duplicate_and_late_notifications_are_idempotent(): void
    {
        $order = $this->checkoutOnline();
        $payment = $order->payments()->sole();

        $this->notify($payment, 'settlement')->assertOk();
        $this->notify($payment, 'settlement')->assertOk();
        $this->notify($payment, 'pending', ['transaction_id' => 'trx-lain', 'payment_type' => 'gopay'])->assertOk();

        $this->assertSame(1, OrderStatusLog::where('order_id', $order->id)->where('status_baru', OrderStatus::Dibayar)->count());
        $this->assertSame(OrderStatus::Dibayar, $order->fresh()->status);
        $this->assertSame("trx-{$payment->id}", $payment->fresh()->transaction_id);
        $this->assertSame(PaymentStatus::Lunas, $payment->fresh()->status);
    }

    public function test_gross_amount_mismatch_is_ignored(): void
    {
        $payment = $this->checkoutOnline()->payments()->sole();

        $this->notify($payment, 'settlement', ['gross_amount' => '1000.00'])->assertOk();

        $this->assertSame(PaymentStatus::Menunggu, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MenungguPembayaran, $payment->order->fresh()->status);
    }

    public function test_unknown_order_id_is_acknowledged(): void
    {
        $payload = ['order_id' => 'TIDAKADA-1', 'status_code' => '200', 'gross_amount' => '1000.00', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', 'TIDAKADA-1'.'200'.'1000.00'.self::SERVER_KEY);

        $this->postJson('/midtrans/notification', $payload)->assertOk();
    }

    public function test_expire_then_late_settlement_needs_refund(): void
    {
        $voucher = Voucher::factory()->create(['kode' => 'HEMAT10']);
        $order = $this->checkoutOnline(['voucher' => 'HEMAT10']);
        $payment = $order->payments()->sole();

        $this->notify($payment, 'expire')->assertOk();
        $this->assertSame(PaymentStatus::Kedaluwarsa, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Kedaluwarsa, $order->fresh()->status);
        $this->assertSame(0, $voucher->fresh()->terpakai);

        $this->notify($payment, 'settlement')->assertOk();
        $this->assertSame(PaymentStatus::Lunas, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Kedaluwarsa, $order->fresh()->status);
        $this->assertTrue($order->fresh()->needsRefund());
    }

    public function test_deny_cancels_payment_but_keeps_order_waiting(): void
    {
        $order = $this->checkoutOnline();
        $payment = $order->payments()->sole();

        $this->notify($payment, 'deny', ['status_code' => '202'])->assertOk();

        $this->assertSame(PaymentStatus::Batal, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->fresh()->status);
    }

    public function test_customer_cancel_calls_cancel_api_for_online_payment(): void
    {
        $order = $this->checkoutOnline();
        $payment = $order->payments()->sole();

        $this->actingAs($this->customer)->post("/pesanan/{$order->kode_pickup}/batal")->assertRedirect();

        // Tanpa body: sandbox membalas 500 bila Cancel API dikirimi body "[]".
        Http::assertSent(fn (Request $r) => $r->url() === "https://api.sandbox.midtrans.com/v2/{$payment->gateway_order_id}/cancel" && $r->body() === '');
        $this->assertSame(OrderStatus::Dibatalkan, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Batal, $payment->fresh()->status);
    }

    public function test_switching_methods_cancels_old_online_transaction(): void
    {
        $order = $this->checkoutOnline();
        $online = $order->payments()->sole();

        $this->actingAs($this->customer)->postJson("/pesanan/{$order->kode_pickup}/bayar-kasir")->assertOk();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/{$online->gateway_order_id}/cancel"));

        $this->postJson("/pesanan/{$order->kode_pickup}/bayar-online")->assertOk();
        $payments = $order->payments()->orderBy('id')->get();
        $this->assertSame([PaymentStatus::Batal, PaymentStatus::Batal, PaymentStatus::Menunggu], $payments->pluck('status')->all());
        $this->assertNotSame($online->gateway_order_id, $payments->last()->gateway_order_id);

        // Settlement transaksi lama yang terlambat tetap melunasi pesanan dan menutup pembayaran yang menunggu.
        $this->notify($online, 'settlement')->assertOk();
        $this->assertSame(OrderStatus::Dibayar, $order->fresh()->status);
        $this->assertSame(0, $order->payments()->where('status', PaymentStatus::Menunggu)->count());
    }

    public function test_polling_uses_status_api_at_most_every_30_seconds(): void
    {
        $order = $this->checkoutOnline();
        $payment = $order->payments()->sole();
        $this->remote[$payment->gateway_order_id] = [
            'status_code' => '201', 'transaction_status' => 'pending', 'order_id' => $payment->gateway_order_id,
            'gross_amount' => '64000.00', 'transaction_id' => 'trx-pending', 'payment_type' => 'qris',
        ];

        $this->actingAs($this->customer)->getJson("/pesanan/{$order->kode_pickup}/status")->assertJsonPath('data.status', 'MENUNGGU_PEMBAYARAN');
        $this->getJson("/pesanan/{$order->kode_pickup}/status");
        Http::assertSentCount(2); // Snap + satu Status API
        $this->assertSame('trx-pending', $payment->fresh()->transaction_id);

        $this->remote[$payment->gateway_order_id] = ['transaction_status' => 'settlement', 'status_code' => '200'] + $this->remote[$payment->gateway_order_id];
        $this->travel(31)->seconds();
        $this->getJson("/pesanan/{$order->kode_pickup}/status")
            ->assertJsonPath('data.status', 'DIBAYAR')
            ->assertJsonPath('data.payment_status', 'LUNAS')
            ->assertJsonPath('data.is_final', false);
    }

    public function test_scheduler_expires_due_payments(): void
    {
        $voucher = Voucher::factory()->create(['kode' => 'HEMAT10']);
        $pending = $this->checkoutOnline(['voucher' => 'HEMAT10']);
        $paidLate = $this->checkoutOnline();
        $kasir = $this->checkoutKasir($this->customer);

        $pendingPayment = $pending->payments()->sole();
        $paidPayment = $paidLate->payments()->sole();
        $this->remote[$pendingPayment->gateway_order_id] = ['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '57600.00'];
        $this->remote[$paidPayment->gateway_order_id] = ['status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '64000.00', 'transaction_id' => 'trx-ok', 'payment_type' => 'qris'];
        $this->assertSame(1, $voucher->fresh()->terpakai);

        $this->travel(16)->minutes();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Kedaluwarsa, $pending->fresh()->status);
        $this->assertSame(PaymentStatus::Kedaluwarsa, $pendingPayment->fresh()->status);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/{$pendingPayment->gateway_order_id}/cancel"));
        $this->assertSame(0, $voucher->fresh()->terpakai);

        $this->assertSame(OrderStatus::Dibayar, $paidLate->fresh()->status);
        $this->assertSame(OrderStatus::MenungguPembayaran, $kasir->fresh()->status);

        // Kasir berlaku sampai jam tutup (22:00).
        $this->travelTo(today()->setTime(22, 1));
        $this->artisan('orders:expire')->assertSuccessful();
        $this->assertSame(OrderStatus::Kedaluwarsa, $kasir->fresh()->status);
        $this->assertSame(PaymentStatus::Kedaluwarsa, $kasir->payments()->sole()->status);
    }

    public function test_order_page_loads_snap_with_client_key_only(): void
    {
        $order = $this->checkoutOnline();

        $this->get("/pesanan/{$order->kode_pickup}")
            ->assertOk()
            ->assertSee('https://app.sandbox.midtrans.com/snap/snap.js', false)
            ->assertSee('data-client-key="SB-Mid-client-TESTKEY"', false)
            ->assertDontSee(self::SERVER_KEY)
            ->assertSee('Lanjutkan Pembayaran')
            ->assertSee('simulator QRIS Midtrans');
    }
}
