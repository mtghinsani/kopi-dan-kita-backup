<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Refund;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** BR-08: satu refund penuh per pesanan; ONLINE lewat Refund API, KASIR dan cadangan lewat refund manual. */
class RefundController extends Controller
{
    public function online(Request $request, Order $order, MidtransService $midtrans): RedirectResponse
    {
        $payment = $order->paidPayment()->first();
        if (! $order->needsRefund() || $payment?->metode !== PaymentMethod::Online || ! $payment->transaction_id) {
            return back()->withErrors(['refund' => 'Refund online hanya untuk pesanan dibatalkan dengan pembayaran online yang lunas dan belum direfund.']);
        }

        // Baris MENUNGGU dibuat dulu (refund_key tetap), sehingga percobaan ulang memakai kunci yang sama.
        $refund = DB::transaction(function () use ($order, $payment) {
            Order::whereKey($order->id)->lockForUpdate()->first();

            return Refund::firstOrCreate(['order_id' => $order->id], [
                'nominal' => $payment->nominal,
                'status' => RefundStatus::Menunggu,
                'refund_key' => 'RF-'.$payment->gateway_order_id,
            ]);
        });

        if ($refund->status === RefundStatus::Selesai) {
            return back()->withErrors(['refund' => 'Pesanan ini sudah direfund.']);
        }

        $error = $midtrans->refund($payment, $refund, 'Dibatalkan kedai: '.($order->alasan_batal ?? 'pesanan kedaluwarsa'));
        if ($error) {
            return back()->withErrors(['refund' => "Refund API gagal: {$error}. Refund tetap menunggu; Anda dapat mencatat refund manual."]);
        }

        $refund->update(['status' => RefundStatus::Selesai, 'diproses_oleh' => $request->user()->id, 'processed_at' => now()]);
        AuditLog::record($request->user(), 'REFUND_ONLINE', $refund, ['status' => RefundStatus::Menunggu->value], ['status' => RefundStatus::Selesai->value, 'nominal' => (int) $refund->nominal]);

        return back()->with('status', 'Refund online berhasil.');
    }

    public function manual(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['no_referensi' => ['required', 'string', 'max:50']], [
            'no_referensi.required' => 'Nomor referensi transfer wajib diisi untuk refund manual.',
        ]);

        $result = DB::transaction(function () use ($order, $data, $request) {
            Order::whereKey($order->id)->lockForUpdate()->first();
            $payment = $order->payments()->where('status', PaymentStatus::Lunas)->latest('id')->first();

            if (! $order->needsRefund() || ! $payment) {
                return null;
            }

            $refund = Refund::firstOrNew(['order_id' => $order->id], ['nominal' => $payment->nominal]);
            $old = $refund->exists ? ['status' => $refund->status->value] : null;
            $refund->fill([
                'status' => RefundStatus::Selesai,
                'no_referensi' => $data['no_referensi'],
                'diproses_oleh' => $request->user()->id,
                'processed_at' => now(),
            ])->save();

            AuditLog::record($request->user(), 'REFUND_MANUAL', $refund, $old, ['status' => RefundStatus::Selesai->value, 'no_referensi' => $data['no_referensi'], 'nominal' => (int) $refund->nominal]);

            return $refund;
        });

        if (! $result) {
            return back()->withErrors(['refund' => 'Pesanan ini tidak memerlukan refund atau sudah direfund.']);
        }

        return back()->with('status', 'Refund manual dicatat.');
    }
}
