<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\CartService;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders, private MidtransService $midtrans) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->cartFor($request->user());
        $items = $cart->items()->with('product', 'options.option')->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $setting = StoreSetting::current();

        return view('customer.checkout', [
            'items' => $items,
            'summary' => $this->cart->summary($cart),
            'cart' => $this->cart,
            'setting' => $setting,
            'isOpen' => $this->orders->isOpen($setting),
            'onlineEnabled' => MidtransService::enabled(),
        ]);
    }

    public function voucher(Request $request): JsonResponse
    {
        $data = $request->validate(['kode' => ['required', 'string', 'max:20']]);

        try {
            $preview = $this->orders->previewVoucher($request->user(), $data['kode']);
        } catch (ValidationException $e) {
            // Pesan voucher dikembalikan pada field kode, sesuai input di form.
            throw ValidationException::withMessages(['kode' => $e->errors()['voucher']]);
        }

        return response()->json(['message' => 'Voucher dapat dipakai.', 'data' => $preview]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'metode' => ['required', 'in:ONLINE,KASIR'],
            'voucher' => ['nullable', 'string', 'max:20'],
        ]);

        if ($data['metode'] === 'ONLINE' && ! MidtransService::enabled()) {
            throw ValidationException::withMessages(['metode' => 'Pembayaran online belum tersedia. Pilih Bayar di Kasir.']);
        }

        $order = $this->orders->checkout($request->user(), $data);

        if ($data['metode'] === 'KASIR') {
            return redirect()->route('orders.show', $order)->with('status', 'Pesanan dibuat. Simpan kode pesananmu.');
        }

        // Token Snap dibuat setelah transaksi basis data selesai; bila gagal, halaman pesanan menawarkan Coba Bayar Lagi.
        try {
            $this->midtrans->createSnapToken($order->activePayment()->firstOrFail());
        } catch (HttpException) {
            return redirect()->route('orders.show', $order)->with('warning', 'Pesanan dibuat, tetapi halaman pembayaran belum bisa dibuka. Tekan Coba Bayar Lagi.');
        }

        return redirect()->route('orders.show', ['order' => $order, 'bayar' => 1]);
    }
}
