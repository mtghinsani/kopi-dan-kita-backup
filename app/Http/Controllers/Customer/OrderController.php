<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\CartService;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->latest('created_at')->latest('id')->paginate(10);

        return view('customer.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        Gate::authorize('update', $order);
        $order->load('items.options', 'activePayment', 'payments');

        return view('customer.orders.show', [
            'order' => $order,
            'payment' => $order->activePayment ?? $order->payments->sortByDesc('id')->first(),
            'setting' => StoreSetting::current(),
            'onlineEnabled' => MidtransService::enabled(),
        ]);
    }

    public function status(Request $request, Order $order, MidtransService $midtrans): JsonResponse
    {
        Gate::authorize('update', $order);

        // Cadangan bila webhook tidak sampai: tanya Status API paling sering sekali per 30 detik
        // (tombol Cek Status: sekali per 5 detik) per pembayaran.
        $active = $order->activePayment;
        $window = $request->boolean('cek') ? 5 : 30;
        if ($midtrans->isOnlinePending($active) && Cache::add("midtrans_check:{$active->id}:{$window}", true, $window)) {
            try {
                if ($remote = $midtrans->status($active->gateway_order_id)) {
                    $midtrans->applyStatus($active, $remote);
                    $order->refresh();
                }
            } catch (HttpException) {
                // Midtrans tidak terjangkau: tampilkan status yang tersimpan, polling berikutnya mencoba lagi.
            }
        }

        $payment = $order->activePayment()->first() ?? $order->payments()->latest('id')->first();

        return response()->json(['data' => [
            'kode' => $order->kode_pickup,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'metode' => $payment?->metode->value,
            'payment_status' => $payment?->status->value,
            'payment_type' => $payment?->payment_type,
            'total' => (int) $order->total,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expired_at' => $payment?->expired_at?->toIso8601String(),
            'alasan_batal' => $order->alasan_batal,
            'is_final' => $order->status->isFinal(),
        ]]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        Gate::authorize('update', $order);
        $orders->cancelByCustomer($order, $request->user());

        return redirect()->route('orders.show', $order)->with('status', 'Pesanan dibatalkan.');
    }

    /** Pesan Ulang: item dan opsi lama masuk keranjang dengan harga terbaru; yang Habis dilewati. */
    public function reorder(Request $request, Order $order, CartService $cart): RedirectResponse
    {
        Gate::authorize('update', $order);
        $order->load('items.options');

        $added = 0;
        $skipped = [];
        foreach ($order->items as $item) {
            try {
                $cart->add($request->user(), [
                    'product_id' => $item->product_id,
                    'qty' => $item->qty,
                    'options' => $item->options->pluck('option_id')->all(),
                    'catatan' => $item->catatan,
                ]);
                $added++;
            } catch (ValidationException) {
                $skipped[] = $item->nama_snapshot;
            }
        }

        $message = $added ? "{$added} item ditambahkan ke keranjang dengan harga terbaru." : 'Tidak ada item yang dapat ditambahkan.';
        if ($skipped) {
            $message .= ' Dilewati karena habis atau berubah: '.implode(', ', $skipped).'.';
        }

        return redirect()->route('cart.index')->with('status', $message);
    }
}
