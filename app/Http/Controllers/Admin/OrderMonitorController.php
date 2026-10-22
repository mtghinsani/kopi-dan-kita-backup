<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'metode' => ['nullable', Rule::enum(PaymentMethod::class)],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'perlu_refund' => ['nullable', 'boolean'],
        ]);

        $orders = Order::with('user', 'payments')
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // Metode pesanan = metode pembayaran terakhirnya.
            ->when($filter['metode'] ?? null, fn ($q, $metode) => $q->whereHas('payments', fn ($p) => $p
                ->where('metode', $metode)->whereRaw('payments.id = (select max(p2.id) from payments p2 where p2.order_id = orders.id)')))
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->where('created_at', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->where('created_at', '<', now()->parse($sampai)->addDay()))
            ->when($request->boolean('perlu_refund'), fn ($q) => $q->awaitingRefund())
            ->latest('created_at')->latest('id')
            ->paginate(20)->withQueryString();

        $refundIds = Order::awaitingRefund()->whereIn('id', $orders->pluck('id'))->pluck('id')->all();

        return view('admin.orders.index', compact('orders', 'filter', 'refundIds'));
    }

    public function show(Order $order): View
    {
        $order->load('user', 'items.options', 'payments.verifier', 'statusLogs.actor', 'refund.processor', 'voucher', 'cancelledBy');

        return view('admin.orders.show', [
            'order' => $order,
            'needsRefund' => $order->needsRefund(),
            'paid' => $order->payments->where('status', PaymentStatus::Lunas)->sortByDesc('id')->first(),
        ]);
    }

    public function payments(Request $request): View
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'metode' => ['nullable', Rule::enum(PaymentMethod::class)],
        ]);

        $payments = Payment::with('order')
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['metode'] ?? null, fn ($q, $metode) => $q->where('metode', $metode))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.orders.payments', compact('payments', 'filter'));
    }
}
