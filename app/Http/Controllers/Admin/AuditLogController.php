<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public const ACTIONS = [
        'KONFIRMASI_BAYAR', 'BATAL_KEDAI', 'REFUND_ONLINE', 'REFUND_MANUAL', 'UBAH_HARGA', 'UBAH_KETERSEDIAAN', 'BUAT_AKUN',
        'UBAH_AKUN', 'NONAKTIF_AKUN', 'AKTIF_AKUN', 'RESET_PASSWORD', 'UBAH_PENGATURAN', 'UBAH_VOUCHER',
    ];

    public function __invoke(Request $request): View
    {
        $filter = $request->validate([
            'aksi' => ['nullable', 'in:'.implode(',', self::ACTIONS)],
            'user_id' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::with('user')
            ->when($filter['aksi'] ?? null, fn ($q, $aksi) => $q->where('aksi', $aksi))
            ->when($filter['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->latest('created_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'filter' => $filter,
            'actions' => self::ACTIONS,
            'actors' => User::whereIn('id', AuditLog::select('user_id')->distinct())->orderBy('nama')->get(['id', 'nama']),
        ]);
    }
}
