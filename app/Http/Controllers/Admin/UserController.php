<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SA-02: akun tidak pernah dihapus, hanya dinonaktifkan (BR-23). */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
        ]);
        $q = trim($filter['q'] ?? '');

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('nama', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($filter['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->orderBy('nama')
            ->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users', 'filter'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => Role::Karyawan])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ]);

        $user = new User([
            'nama' => $data['nama'], 'email' => $data['email'], 'no_hp' => $data['no_hp'], 'password_hash' => $data['password'],
        ]);
        $user->role = Role::from($data['role']);
        $user->aktif = true;
        $user->save();

        AuditLog::record($request->user(), 'BUAT_AKUN', $user, null, $user->only('nama', 'email', 'no_hp') + ['role' => $user->role->value]);

        return redirect()->route('admin.users.index')->with('status', "Akun {$user->nama} dibuat.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user));
        $old = $user->only('nama', 'email', 'no_hp') + ['role' => $user->role->value];

        $user->fill(['nama' => $data['nama'], 'email' => $data['email'], 'no_hp' => $data['no_hp']]);
        // Super Admin tidak dapat menurunkan role-nya sendiri agar panel admin tidak terkunci.
        if (! $user->is($request->user())) {
            $user->role = Role::from($data['role']);
        }
        $user->save();

        AuditLog::record($request->user(), 'UBAH_AKUN', $user, $old, $user->only('nama', 'email', 'no_hp') + ['role' => $user->role->value]);

        return redirect()->route('admin.users.index')->with('status', "Akun {$user->nama} diperbarui.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['aktif' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $user->aktif = ! $user->aktif;
        $user->save();
        AuditLog::record($request->user(), $user->aktif ? 'AKTIF_AKUN' : 'NONAKTIF_AKUN', $user, ['aktif' => ! $user->aktif], ['aktif' => $user->aktif]);

        return back()->with('status', "Akun {$user->nama} ".($user->aktif ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:64', 'confirmed']]);
        $user->update(['password_hash' => $data['password']]);
        AuditLog::record($request->user(), 'RESET_PASSWORD', $user, null, null);

        return back()->with('status', "Password {$user->nama} diatur ulang.");
    }

    private function rules(?User $user = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^(\+62|0)8[0-9]{8,12}$/'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
