<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Order::whereDate('created_at', today());
        $counts = (clone $today)->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return view('admin.dashboard', [
            'counts' => collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            // Pendapatan hari ini: pesanan yang dibayar hari ini dan tidak dibatalkan atau kedaluwarsa.
            'revenue' => (int) Order::whereDate('paid_at', today())
                ->whereNotIn('status', [OrderStatus::Dibatalkan, OrderStatus::Kedaluwarsa])->sum('total'),
            'refunds' => Order::awaitingRefund()->with('user')->latest('id')->limit(10)->get(),
            'refundCount' => Order::awaitingRefund()->count(),
        ]);
    }
}
