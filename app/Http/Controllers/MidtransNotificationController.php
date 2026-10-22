<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Webhook Midtrans: tanpa CSRF dan tanpa login, dipercaya hanya lewat signature SHA512. */
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans): JsonResponse
    {
        $payload = $request->json()->all();
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! is_string($payload[$field] ?? null)) {
                Log::warning('Notifikasi Midtrans tanpa field wajib', ['field' => $field]);

                return response()->json(['message' => 'Invalid payload'], 400);
            }
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key'));
        if (! hash_equals($expected, $payload['signature_key'])) {
            Log::warning('Notifikasi Midtrans dengan signature salah', ['order_id' => $payload['order_id']]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('gateway_order_id', $payload['order_id'])->first();
        if (! $payment) {
            Log::warning('Notifikasi Midtrans untuk order_id tidak dikenal', ['order_id' => $payload['order_id']]);

            return response()->json(['message' => 'OK']);
        }

        $midtrans->applyStatus($payment, $payload);

        return response()->json(['message' => 'OK']);
    }
}
