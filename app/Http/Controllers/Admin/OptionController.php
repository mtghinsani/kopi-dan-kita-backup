<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\OrderItemOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    public function store(Request $request, OptionGroup $group): RedirectResponse
    {
        $group->options()->create($this->validated($request, "newOption{$group->id}"));

        return back()->with('status', 'Opsi ditambahkan.');
    }

    public function update(Request $request, Option $option): RedirectResponse
    {
        $data = $this->validated($request, "option{$option->id}");
        $oldPrice = (int) $option->selisih_harga;
        $option->update($data);

        if ($oldPrice !== (int) $option->selisih_harga) {
            AuditLog::record($request->user(), 'UBAH_HARGA', $option, ['selisih_harga' => $oldPrice], ['selisih_harga' => (int) $option->selisih_harga]);
        }

        return back()->with('status', "Opsi {$option->nama} diperbarui.");
    }

    /** BR-22: opsi yang pernah dipesan tidak dihapus. */
    public function destroy(Option $option): RedirectResponse
    {
        if (OrderItemOption::where('option_id', $option->id)->exists()) {
            return back()->withErrors(['hapus' => "Opsi {$option->nama} sudah pernah dipesan. Tandai Habis sebagai gantinya."]);
        }

        $option->delete();

        return back()->with('status', 'Opsi dihapus.');
    }

    private function validated(Request $request, string $bag): array
    {
        $data = $request->validateWithBag($bag, [
            'nama' => ['required', 'string', 'max:50'],
            'selisih_harga' => ['required', 'integer', 'min:0', 'max:1000000'],
            'tersedia' => ['boolean'],
        ]);

        return ['tersedia' => $request->boolean('tersedia')] + $data;
    }
}
