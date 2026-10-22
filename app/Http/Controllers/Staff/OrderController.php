<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Cari pesanan dengan kode untuk kasir dan pengambilan. */
    public function find(Request $request): JsonResponse
    {
        $data = $request->validate(['kode' => ['required', 'string', 'max:6']]);
        $order = Order::with('user', 'activePayment', 'items.options')
            ->where('kode_pickup', Str::upper(trim($data['kode'])))
            ->first();

        abort_unless($order, 404, 'Kode pesanan tidak valid.');

        $payment = $order->activePayment ?? $order->payments()->latest('id')->first();

        return response()->json(['data' => [
            'kode' => $order->kode_pickup,
            'nama_pelanggan' => $order->user->nama,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'metode' => $payment?->metode->value,
            'total' => (int) $order->total,
            'items' => $order->itemLines(),
            'can_confirm_payment' => $order->status === OrderStatus::MenungguPembayaran
                && $order->activePayment?->metode === PaymentMethod::Kasir,
            'can_pickup' => $order->status === OrderStatus::SiapDiambil,
            'receipt_url' => $order->hasReceipt() ? route('staff.orders.receipt', $order) : null,
        ]]);
    }

    /** Barista: DIBAYAR ke DIPROSES, DIPROSES ke SIAP_DIAMBIL; selain itu 409 dari transition(). */
    public function updateStatus(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:DIPROSES,SIAP_DIAMBIL']]);
        $orders->transition($order, OrderStatus::from($data['status']), $request->user());

        return response()->json([
            'message' => 'Status diperbarui.',
            'data' => ['status' => $order->status->value, 'status_label' => $order->status->label()],
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
        ]);
        $orders->cancelByStore($order, $request->user(), $data['alasan']);

        return response()->json([
            'message' => 'Pesanan dibatalkan.',
            'data' => ['status' => $order->status->value, 'perlu_refund' => $order->needsRefund()],
        ]);
    }

    /** Riwayat: cari kode atau nama pelanggan, filter tanggal pesanan. */
    public function history(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);
        $q = trim($filter['q'] ?? '');

        $orders = Order::with('user')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('kode_pickup', Str::upper($q))
                ->orWhereRelation('user', 'nama', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($filter['dari'] ?? null, fn ($query, $dari) => $query->where('created_at', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($query, $sampai) => $query->where('created_at', '<', now()->parse($sampai)->addDay()))
            ->latest('created_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('staff.history', compact('orders', 'filter'));
    }

    public function show(Order $order): View
    {
        $order->load('user', 'items.options', 'payments', 'statusLogs.actor', 'cancelledBy');

        return view('staff.order', compact('order'));
    }
}
