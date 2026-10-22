<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function index(): View
    {
        return view('staff.queue');
    }

    /** BR-03: antrean DIBAYAR dan DIPROSES menurut paid_at naik; SIAP_DIAMBIL di kolom terpisah. */
    public function data(): JsonResponse
    {
        $card = fn (Order $order) => [
            'kode' => $order->kode_pickup,
            'nama_pelanggan' => $order->user->nama,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'metode' => $order->paidPayment?->metode->value,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'items' => $order->itemLines(),
        ];

        $queue = Order::with('user', 'items.options', 'paidPayment')
            ->whereIn('status', [OrderStatus::Dibayar, OrderStatus::Diproses])
            ->orderBy('paid_at')->orderBy('id')->get();
        $ready = Order::with('user', 'items.options', 'paidPayment')
            ->where('status', OrderStatus::SiapDiambil)
            ->orderBy('ready_at')->orderBy('id')->get();

        return response()->json(['data' => [
            'generated_at' => now()->toIso8601String(),
            'antrean' => $queue->map($card)->all(),
            'siap_diambil' => $ready->map($card)->all(),
        ]]);
    }
}
