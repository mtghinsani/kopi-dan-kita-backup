<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'integer'],
        ]);
        $q = trim($filter['q'] ?? '');

        $products = Product::with('category')->withCount('optionGroups')
            ->when($q !== '', fn ($query) => $query->where('nama', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($filter['kategori'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy('category_id')->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('admin.products.index', ['products' => $products, 'filter' => $filter, 'categories' => Category::orderBy('urutan')->get()]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(['tersedia' => true]), 'categories' => Category::orderBy('urutan')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['gambar'] = $request->file('gambar')?->store('produk', 'public');
        $product = Product::create($data);

        return redirect()->route('admin.products.options', $product)->with('status', "{$product->nama} ditambahkan. Atur opsi kustomisasinya bila perlu.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('urutan')->get()]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);
        $oldPrice = (int) $product->harga_dasar;

        if ($request->hasFile('gambar')) {
            $old = $product->gambar;
            $data['gambar'] = $request->file('gambar')->store('produk', 'public');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $product->update($data);

        if ($oldPrice !== (int) $product->harga_dasar) {
            AuditLog::record($request->user(), 'UBAH_HARGA', $product, ['harga_dasar' => $oldPrice], ['harga_dasar' => (int) $product->harga_dasar]);
        }

        return redirect()->route('admin.products.index')->with('status', "{$product->nama} diperbarui.");
    }

    /** BR-22: produk yang pernah dipesan tidak dihapus; tandai Habis sebagai gantinya. */
    public function destroy(Product $product): RedirectResponse
    {
        if (OrderItem::where('product_id', $product->id)->exists()) {
            return back()->withErrors(['hapus' => "{$product->nama} sudah pernah dipesan sehingga tidak dapat dihapus. Tandai Habis sebagai gantinya."]);
        }

        if ($product->gambar) {
            Storage::disk('public')->delete($product->gambar);
        }
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Produk dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'nama' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'harga_dasar' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'tersedia' => ['boolean'],
            // image di Laravel 13 menolak SVG secara bawaan; mimes membatasi ekstensi yang diterima.
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        unset($data['gambar']);

        return $data + ['tersedia' => $request->boolean('tersedia')];
    }
}
