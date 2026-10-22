<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('urutan')->orderBy('id')
            ->with(['products' => fn ($q) => $q->orderBy('id'), 'products.activeOptionGroups.options'])
            ->get();

        return view('staff.availability', compact('categories'));
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        return $this->toggle($request, $product);
    }

    public function option(Request $request, Option $option): JsonResponse
    {
        return $this->toggle($request, $option);
    }

    private function toggle(Request $request, Product|Option $model): JsonResponse
    {
        $data = $request->validate(['tersedia' => ['required', 'boolean']]);
        $old = $model->tersedia;
        $model->update(['tersedia' => $data['tersedia']]);

        if ($old !== $model->tersedia) {
            AuditLog::record($request->user(), 'UBAH_KETERSEDIAAN', $model, ['tersedia' => $old], ['tersedia' => $model->tersedia, 'nama' => $model->nama]);
        }

        return response()->json([
            'message' => 'Ketersediaan diperbarui.',
            'data' => ['id' => $model->id, 'tersedia' => $model->tersedia, 'label' => $model->tersedia ? 'Tersedia' : 'Habis'],
        ]);
    }
}
