<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemStoreRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(Request $request): View
    {
        $cart = $this->cart->cartFor($request->user());
        $items = $cart->items()->with('product', 'options.option.group')->get();

        return view('customer.cart', [
            'items' => $items,
            'summary' => $this->cart->summary($cart),
            'cart' => $this->cart,
        ]);
    }

    public function store(CartItemStoreRequest $request): JsonResponse
    {
        $item = $this->cart->add($request->user(), $request->validated());

        return response()->json([
            'message' => 'Item ditambahkan ke keranjang.',
            'data' => [
                'cart_count' => $this->cart->count($request->user()),
                'item' => $this->cart->itemPayload($item),
            ],
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        Gate::authorize('update', $cartItem);
        $data = $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:10']], [
            'qty.min' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 10.',
        ]);

        $item = $this->cart->updateQty($cartItem, $data['qty']);

        return response()->json([
            'message' => 'Jumlah diperbarui.',
            'data' => [
                'item' => ['id' => $item->id, 'qty' => $item->qty, 'subtotal' => $this->cart->subtotal($item)],
                'cart' => $this->cart->summary($item->cart),
            ],
        ]);
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        Gate::authorize('delete', $cartItem);
        $cart = $cartItem->cart;
        $this->cart->remove($cartItem);

        return response()->json(['message' => 'Item dihapus.', 'data' => ['cart' => $this->cart->summary($cart)]]);
    }
}
