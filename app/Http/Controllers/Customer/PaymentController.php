<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentController extends Controller
{
    public function __construct(private OrderService $orders, private MidtransService $midtrans) {}

    /** Lanjutkan Pembayaran, Coba Bayar Lagi, atau ganti ke Bayar Online; token Snap dipakai ulang selama masih berlaku. */
    public function online(Order $order): JsonResponse
    {
        Gate::authorize('update', $order);
        if (! MidtransService::enabled()) {
            throw new UnprocessableEntityHttpException('Pembayaran online belum tersedia.');
        }

        $payment = $this->orders->switchPayment($order, PaymentMethod::Online);
        $token = $this->midtrans->tokenFor($payment);

        return response()->json(['data' => [
            'snap_token' => $token,
            'gateway_order_id' => $payment->gateway_order_id,
            'expired_at' => $payment->fresh()->expired_at->toIso8601String(),
        ]]);
    }

    /** Ganti ke Bayar di Kasir (BR-18). */
    public function cashier(Order $order): JsonResponse
    {
        Gate::authorize('update', $order);
        $payment = $this->orders->switchPayment($order, PaymentMethod::Kasir);

        return response()->json([
            'message' => 'Silakan bayar dengan QRIS kedai dan tunjukkan kode pesanan ke kasir.',
            'data' => [
                'metode' => $payment->metode->value,
                'total' => (int) $payment->nominal,
                'expired_at' => $payment->expired_at->toIso8601String(),
                'qris_image_url' => StoreSetting::current()->qris_url,
            ],
        ]);
    }
}
