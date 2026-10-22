<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreSettingController extends Controller
{
    private const AUDITED = ['nama_kedai', 'alamat', 'kontak', 'qris_image', 'jam_buka', 'jam_tutup', 'batas_bayar_menit', 'pemesanan_dibuka'];

    public function edit(): View
    {
        return view('admin.settings', ['setting' => StoreSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = StoreSetting::current();
        $data = $request->validate([
            'nama_kedai' => ['required', 'string', 'max:100'],
            'alamat' => ['required', 'string', 'max:500'],
            'kontak' => ['required', 'string', 'max:50'],
            'jam_buka' => ['required', 'date_format:H:i'],
            'jam_tutup' => ['required', 'date_format:H:i', 'after:jam_buka'],
            'batas_bayar_menit' => ['required', 'integer', 'min:5', 'max:60'],
            'pemesanan_dibuka' => ['boolean'],
            'qris_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['jam_tutup.after' => 'Jam tutup harus setelah jam buka.']);

        $data['pemesanan_dibuka'] = $request->boolean('pemesanan_dibuka');
        // Kolom TIME tersimpan HH:MM:SS; samakan agar perbandingan audit tidak mencatat perubahan palsu.
        $data['jam_buka'] .= ':00';
        $data['jam_tutup'] .= ':00';
        unset($data['qris_image']);
        $old = $setting->only(self::AUDITED);

        if ($request->hasFile('qris_image')) {
            $data['qris_image'] = $request->file('qris_image')->store('qris', 'public');
            if ($setting->qris_image) {
                Storage::disk('public')->delete($setting->qris_image);
            }
        }

        $setting->update($data);
        $new = $setting->only(self::AUDITED);
        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', $old)));

        if ($changed) {
            AuditLog::record($request->user(), 'UBAH_PENGATURAN', $setting, array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)));
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Pengaturan kedai disimpan.');
    }
}
