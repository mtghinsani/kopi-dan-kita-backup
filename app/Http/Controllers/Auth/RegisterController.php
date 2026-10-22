<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public const PRIVACY_VERSION = '1.0';

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Role tidak diambil dari request: kolom role berdefault PELANGGAN dan tidak fillable (BR-27).
        $user = User::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'no_hp' => $data['no_hp'],
            'password_hash' => $data['password'],
            'privasi_versi' => self::PRIVACY_VERSION,
            'privasi_disetujui_at' => now(),
        ])->refresh();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->homePath())->with('status', 'Akun berhasil dibuat. Selamat datang di Kopi & Kita.');
    }
}
