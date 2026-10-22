<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Item keranjang hanya dapat diubah pemiliknya; milik orang lain dibalas 404 agar tidak membocorkan keberadaannya. */
class CartItemPolicy
{
    public function update(User $user, CartItem $cartItem): Response
    {
        return $cartItem->cart->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, CartItem $cartItem): Response
    {
        return $this->update($user, $cartItem);
    }
}
