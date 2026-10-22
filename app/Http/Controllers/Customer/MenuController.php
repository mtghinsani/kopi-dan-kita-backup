<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'integer'],
        ]);
        $q = trim($filter['q'] ?? '');
        $kategori = $filter['kategori'] ?? null;

        $categories = Category::orderBy('urutan')->orderBy('id')->get();

        // Collation MySQL utf8mb4_unicode_ci membuat LIKE tidak peka huruf besar kecil (US-PL-05).
        $sections = Category::orderBy('urutan')->orderBy('id')
            ->when($kategori, fn ($query) => $query->whereKey($kategori))
            ->with(['products' => fn ($query) => $query
                ->when($q !== '', fn ($p) => $p->where('nama', 'like', '%'.addcslashes($q, '%_\\').'%'))
                ->orderBy('id')])
            ->get()
            ->filter(fn ($category) => $category->products->isNotEmpty());

        return view('customer.menu', compact('categories', 'sections', 'q', 'kategori'));
    }

    public function options(Product $product, CartService $cart): JsonResponse
    {
        return response()->json(['data' => $cart->optionsPayload($product)]);
    }
}
