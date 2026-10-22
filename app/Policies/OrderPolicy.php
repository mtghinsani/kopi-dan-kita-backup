<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Pelanggan hanya mengakses pesanannya sendiri (lainnya 404); Karyawan dan Super Admin boleh melihat semua. */
class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        if (! $user->hasRole(Role::Pelanggan)) {
            return Response::allow();
        }

        return $order->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Order $order): Response
    {
        return $order->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
