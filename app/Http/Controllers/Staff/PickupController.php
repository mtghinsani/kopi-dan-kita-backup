<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PickupController extends Controller
{
    public function index(): View
    {
        return view('staff.pickup');
    }

    public function confirm(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        if ($order->status !== OrderStatus::SiapDiambil) {
            throw new ConflictHttpException('Pesanan belum siap diambil.');
        }

        $orders->transition($order, OrderStatus::Selesai, $request->user());

        return response()->json(['message' => 'Pesanan diserahkan.', 'data' => ['status' => $order->status->value]]);
    }
}
