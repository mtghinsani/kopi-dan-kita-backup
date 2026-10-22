<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Kedaluwarsakan pembayaran yang lewat batas bayar (ONLINE dicek ke Status API dulu)')]
class ExpireOrders extends Command
{
    public function handle(OrderService $orders): int
    {
        $this->info($orders->expireDuePayments().' pembayaran diproses.');

        return self::SUCCESS;
    }
}
