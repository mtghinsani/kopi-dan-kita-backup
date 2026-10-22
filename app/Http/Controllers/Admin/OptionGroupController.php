<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionGroup;
use App\Models\OrderItemOption;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** SA-05: grup opsi per produk; form di satu halaman pengelolaan opsi. */
class OptionGroupController extends Controller
{
    public function index(Product $product): View
    {
        $product->load('optionGroups.options');

        return view('admin.products.options', compact('product'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $product->optionGroups()->create($this->validated($request));

        return back()->with('status', 'Grup opsi ditambahkan.');
    }

    public function update(Request $request, OptionGroup $group): RedirectResponse
    {
        $group->update($this->validated($request, $group));

        return back()->with('status', "Grup {$group->nama} diperbarui.");
    }

    public function destroy(OptionGroup $group): RedirectResponse
    {
        if (OrderItemOption::whereIn('option_id', $group->options()->select('id'))->exists()) {
            return back()->withErrors(['hapus' => "Opsi di grup {$group->nama} sudah pernah dipesan. Tandai opsinya Habis sebagai gantinya."]);
        }

        $group->delete();

        return back()->with('status', 'Grup opsi dihapus.');
    }

    /** min_pilih <= max_pilih, max_pilih >= 1, grup wajib min_pilih >= 1. */
    private function validated(Request $request, ?OptionGroup $group = null): array
    {
        $bag = $group ? "group{$group->id}" : 'newGroup';
        $data = $request->validateWithBag($bag, [
            'nama' => ['required', 'string', 'max:50'],
            'wajib' => ['boolean'],
            'min_pilih' => ['required', 'integer', 'min:0', 'max:20'],
            'max_pilih' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_pilih'],
            'urutan' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'max_pilih.gte' => 'Maksimal pilihan tidak boleh lebih kecil dari minimal pilihan.',
        ]);
        $data['wajib'] = $request->boolean('wajib');

        if ($data['wajib'] && $data['min_pilih'] < 1) {
            $data['min_pilih'] = 1;
        }

        return $data;
    }
}
