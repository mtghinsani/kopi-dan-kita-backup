<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StoreSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Satu-satunya pintu ke Midtrans (Snap, Status, Cancel, Refund) lewat HTTP Client Laravel. */
class MidtransService
{
    public const GATEWAY_ERROR = 'Gateway pembayaran tidak dapat dihubungi, coba lagi.';

    public static function enabled(): bool
    {
        return filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.midtrans.server_key'), '')->acceptJson()->timeout(15);
    }

    private function fail(string $what, Response|\Throwable $error): HttpException
    {
        Log::warning("Midtrans {$what} gagal", $error instanceof Response
            ? ['status' => $error->status(), 'body' => $error->json() ?? $error->body()]
            : ['error' => $error->getMessage()]);

        return new HttpException(502, self::GATEWAY_ERROR);
    }

    /** Token Snap dari cache bila masih berlaku, kalau tidak buat transaksi Snap baru. */
    public function tokenFor(Payment $payment): string
    {
        return Cache::get("snap_token:{$payment->id}") ?? $this->createSnapToken($payment);
    }

    public function createSnapToken(Payment $payment): string
    {
        $payment->loadMissing('order.items', 'order.voucher', 'order.user');
        $order = $payment->order;
        $grossAmount = (int) $payment->nominal;
        $minutes = StoreSetting::current()->batas_bayar_menit;
        $start = now();

        $items = $order->items->map(fn ($item) => [
            'id' => "OI-{$item->id}",
            'price' => intdiv((int) $item->subtotal, $item->qty),
            'quantity' => $item->qty,
            'name' => Str::limit($item->nama_snapshot, 50, ''),
        ])->all();
        if ((int) $order->diskon > 0) {
            $items[] = ['id' => 'VOUCHER', 'price' => -(int) $order->diskon, 'quantity' => 1, 'name' => Str::limit('Voucher '.$order->voucher?->kode, 50, '')];
        }

        $sum = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));
        if ($sum !== $grossAmount) {
            throw new \LogicException("Jumlah item_details ({$sum}) tidak sama dengan gross_amount ({$grossAmount}) untuk {$payment->gateway_order_id}.");
        }

        $request = $this->http();
        if ($url = config('services.midtrans.notification_url')) {
            $request = $request->withHeaders(['X-Override-Notification' => $url]);
        }

        try {
            $response = $request->post(config('services.midtrans.snap_url'), [
                'transaction_details' => ['order_id' => $payment->gateway_order_id, 'gross_amount' => $grossAmount],
                'item_details' => $items,
                'customer_details' => [
                    'first_name' => $order->user->nama,
                    'email' => $order->user->email,
                    'phone' => $order->user->no_hp,
                ],
                'enabled_payments' => config('services.midtrans.enabled_payments'),
                'expiry' => ['start_time' => $start->format('Y-m-d H:i:s O'), 'unit' => 'minute', 'duration' => $minutes],
                'page_expiry' => ['duration' => $minutes, 'unit' => 'minute'],
                'callbacks' => ['finish' => route('orders.show', $order)],
            ]);
        } catch (ConnectionException $e) {
            throw $this->fail('Snap', $e);
        }

        if (! $response->successful() || ! $response->json('token')) {
            throw $this->fail('Snap', $response);
        }

        $payment->update(['expired_at' => $start->copy()->addMinutes($minutes)]);
        Cache::put("snap_token:{$payment->id}", $response->json('token'), $payment->expired_at);

        return $response->json('token');
    }

    /** Status transaksi; null bila transaksi belum dibuat di Midtrans (404). */
    public function status(string $gatewayOrderId): ?array
    {
        try {
            $response = $this->http()->get(config('services.midtrans.api_url')."/{$gatewayOrderId}/status");
        } catch (ConnectionException $e) {
            throw $this->fail('Status API', $e);
        }

        // Status API membalas HTTP 200 dengan status_code 404 di body untuk transaksi yang belum ada.
        if ($response->status() === 404 || $response->json('status_code') === '404') {
            return null;
        }
        if (! $response->successful()) {
            throw $this->fail('Status API', $response);
        }

        return $response->json();
    }

    /** Batalkan transaksi pending; 404 (belum dibuat) dan 412 (status tidak bisa diubah) diabaikan. */
    public function cancel(string $gatewayOrderId): void
    {
        try {
            // Tanpa body: Http::post() tanpa data tetap mengirim "[]" dan sandbox membalasnya dengan 500.
            $response = $this->http()->send('POST', config('services.midtrans.api_url')."/{$gatewayOrderId}/cancel");
        } catch (ConnectionException $e) {
            throw $this->fail('Cancel API', $e);
        }

        // Midtrans menaruh kode sebenarnya di body status_code; 404 dan 412 bukan galat bagi kita.
        $code = (int) ($response->json('status_code') ?? $response->status());
        if (! in_array($code, [200, 404, 412], true)) {
            throw $this->fail('Cancel API', $response);
        }
    }

    /**
     * Refund penuh lewat transaction_id. Mengembalikan pesan galat dari Midtrans, atau null bila sukses.
     */
    public function refund(Payment $payment, Refund $refund, string $reason): ?string
    {
        try {
            $response = $this->http()->post(config('services.midtrans.api_url')."/{$payment->transaction_id}/refund", [
                'refund_key' => $refund->refund_key,
                'amount' => (int) $refund->nominal,
                'reason' => Str::limit($reason, 255, ''),
            ]);
        } catch (ConnectionException $e) {
            $this->fail('Refund API', $e);

            return self::GATEWAY_ERROR;
        }

        if ($response->successful() && in_array($response->json('transaction_status'), ['refund', 'partial_refund'], true)) {
            return null;
        }

        Log::warning('Midtrans Refund API ditolak', ['order_id' => $payment->gateway_order_id, 'body' => $response->json()]);

        return $response->json('status_message') ?? 'Refund ditolak Midtrans.';
    }

    /**
     * Terapkan status dari webhook atau Status API. Idempoten: perubahan hanya terjadi bila status saat ini
     * mengizinkan, di dalam transaksi dengan lock pada pembayaran dan pesanan.
     */
    public function applyStatus(Payment $payment, array $payload): void
    {
        if ((int) round((float) ($payload['gross_amount'] ?? 0)) !== (int) $payment->nominal) {
            Log::warning('Midtrans nominal tidak cocok', ['order_id' => $payment->gateway_order_id, 'gross_amount' => $payload['gross_amount'] ?? null]);

            return;
        }

        $status = $payload['transaction_status'] ?? null;
        $paid = ($status === 'settlement' && ($payload['status_code'] ?? '200') === '200')
            || ($status === 'capture' && ($payload['fraud_status'] ?? null) === 'accept');

        // Di-resolve lewat container karena OrderService juga bergantung pada MidtransService.
        $orders = app(OrderService::class);

        DB::transaction(function () use ($payment, $payload, $status, $paid, $orders) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $gatewayFields = array_filter([
                'transaction_id' => $payload['transaction_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
            ]);

            match (true) {
                $paid => $this->markPaid($order, $locked, $gatewayFields, $orders),
                $status === 'pending' && $locked->status === PaymentStatus::Menunggu => $locked->update($gatewayFields),
                $status === 'expire' => $orders->expirePayment($locked),
                in_array($status, ['deny', 'cancel', 'failure'], true) && $locked->status === PaymentStatus::Menunggu => $locked->update(['status' => PaymentStatus::Batal]),
                in_array($status, ['refund', 'partial_refund'], true) => Refund::where('order_id', $order->id)->where('status', RefundStatus::Menunggu)
                    ->update(['status' => RefundStatus::Selesai, 'processed_at' => now()]),
                default => Log::info('Midtrans status diabaikan', ['order_id' => $locked->gateway_order_id, 'status' => $status]),
            };
        }, attempts: 3);

        $payment->refresh();
    }

    /** Settlement: pembayaran LUNAS; pesanan DIBAYAR bila masih menunggu, kalau sudah final dibiarkan (Perlu Refund). */
    private function markPaid(Order $order, Payment $payment, array $gatewayFields, OrderService $orders): void
    {
        if ($payment->status === PaymentStatus::Lunas) {
            return;
        }

        $payment->update(['status' => PaymentStatus::Lunas, 'verified_at' => now(), ...$gatewayFields]);

        if ($order->status === OrderStatus::MenungguPembayaran) {
            // Pembayaran lain yang masih menunggu (misalnya setelah ganti ke kasir) tidak dipakai lagi.
            $order->payments()->whereKeyNot($payment->id)->where('status', PaymentStatus::Menunggu)->update(['status' => PaymentStatus::Batal]);
            $orders->transition($order, OrderStatus::Dibayar, null);
        } else {
            Log::warning('Pembayaran lunas untuk pesanan yang sudah final, perlu refund', ['kode' => $order->kode_pickup]);
        }
    }

    public function isOnlinePending(?Payment $payment): bool
    {
        return $payment?->metode === PaymentMethod::Online && $payment->status === PaymentStatus::Menunggu;
    }
}
