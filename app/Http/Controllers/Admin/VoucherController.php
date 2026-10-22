<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(): View
    {
        return view('admin.vouchers.index', ['vouchers' => Voucher::orderByDesc('berlaku_sampai')->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.vouchers.form', ['voucher' => new Voucher([
            'jenis' => VoucherType::Persen, 'terpakai' => 0, 'berlaku_mulai' => today(), 'berlaku_sampai' => today()->addDays(30)->endOfDay(),
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $voucher = Voucher::create($this->validated($request) + ['terpakai' => 0]);
        AuditLog::record($request->user(), 'UBAH_VOUCHER', $voucher, null, $this->snapshot($voucher));

        return redirect()->route('admin.vouchers.index')->with('status', "Voucher {$voucher->kode} dibuat.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('admin.vouchers.form', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $old = $this->snapshot($voucher);
        $voucher->update($this->validated($request, $voucher));
        AuditLog::record($request->user(), 'UBAH_VOUCHER', $voucher, $old, $this->snapshot($voucher));

        return redirect()->route('admin.vouchers.index')->with('status', "Voucher {$voucher->kode} diperbarui.");
    }

    /** Voucher yang sudah dipakai tidak dihapus (US-SA-06). */
    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        if ($voucher->terpakai > 0 || $voucher->orders()->exists()) {
            return back()->withErrors(['hapus' => "Voucher {$voucher->kode} sudah dipakai sehingga tidak dapat dihapus. Ubah masa berlakunya untuk menghentikan."]);
        }

        AuditLog::record($request->user(), 'UBAH_VOUCHER', $voucher, $this->snapshot($voucher), null);
        $voucher->delete();

        return redirect()->route('admin.vouchers.index')->with('status', 'Voucher dihapus.');
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $request->merge(['kode' => Str::upper(trim((string) $request->input('kode')))]);

        return $request->validate([
            'kode' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique('vouchers', 'kode')->ignore($voucher)],
            'jenis' => ['required', Rule::enum(VoucherType::class)],
            'nilai' => ['required', 'integer', 'min:1', Rule::when($request->input('jenis') === 'PERSEN', ['max:100'], ['max:10000000'])],
            'kuota' => ['required', 'integer', 'min:'.max(1, $voucher?->terpakai ?? 0), 'max:100000'],
            'berlaku_mulai' => ['required', 'date'],
            'berlaku_sampai' => ['required', 'date', 'after:berlaku_mulai'],
        ], [
            'kode.regex' => 'Kode voucher hanya huruf besar dan angka.',
            'kuota.min' => 'Kuota tidak boleh di bawah jumlah yang sudah terpakai (:min).',
        ]);
    }

    private function snapshot(Voucher $voucher): array
    {
        return [
            'kode' => $voucher->kode, 'jenis' => $voucher->jenis->value, 'nilai' => (int) $voucher->nilai, 'kuota' => $voucher->kuota,
            'berlaku_mulai' => $voucher->berlaku_mulai->toDateTimeString(), 'berlaku_sampai' => $voucher->berlaku_sampai->toDateTimeString(),
        ];
    }
}
