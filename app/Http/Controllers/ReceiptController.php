<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Struk 58 mm untuk pelanggan pemilik pesanan dan karyawan. */
class ReceiptController extends Controller
{
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);
        abort_unless($order->hasReceipt(), 404, 'Struk tersedia setelah pesanan dibayar.');

        $order->load('items.options', 'user');

        return view('receipt', [
            'order' => $order,
            'payment' => $order->payments()->where('status', PaymentStatus::Lunas)->latest('id')->first(),
            'setting' => StoreSetting::current(),
        ]);
    }
}
