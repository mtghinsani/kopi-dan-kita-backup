<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** SA-10: hanya pesanan SELESAI yang selesai di dalam rentang tanggal. */
class ReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);

        $dari = Carbon::parse($data['dari'] ?? today()->subDays(6))->startOfDay();
        $sampai = Carbon::parse($data['sampai'] ?? today())->endOfDay();

        $done = Order::where('status', OrderStatus::Selesai)->whereBetween('completed_at', [$dari, $sampai]);

        $topProducts = OrderItem::whereIn('order_id', (clone $done)->select('id'))
            ->selectRaw('nama_snapshot, sum(qty) as jumlah, sum(subtotal) as pendapatan')
            ->groupBy('nama_snapshot')
            ->orderByDesc('jumlah')->orderBy('nama_snapshot')
            ->limit(10)->get();

        return view('admin.report', [
            'dari' => $dari,
            'sampai' => $sampai,
            'count' => (clone $done)->count(),
            'revenue' => (int) (clone $done)->sum('total'),
            'discount' => (int) (clone $done)->sum('diskon'),
            'topProducts' => $topProducts,
        ]);
    }
}
