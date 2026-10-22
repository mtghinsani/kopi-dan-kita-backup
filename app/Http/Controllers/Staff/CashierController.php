<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierController extends Controller
{
    public function index(): View
    {
        return view('staff.cashier');
    }

    public function confirm(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $orders->confirmCashierPayment($order, $request->user());

        return response()->json([
            'message' => 'Pembayaran dikonfirmasi.',
            'data' => ['status' => $order->status->value, 'paid_at' => $order->paid_at?->toIso8601String()],
        ]);
    }
}
