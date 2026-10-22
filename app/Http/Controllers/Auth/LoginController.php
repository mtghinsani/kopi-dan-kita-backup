<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:64'],
        ]);

        // Kunci "password" dicocokkan Laravel ke kolom password_hash melalui $authPasswordName di model User.
        if (! Auth::attempt([...$credentials, 'aktif' => true])) {
            return back()
                ->withErrors(['email' => 'Email atau password salah, atau akun tidak aktif.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($request->user()->homePath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
