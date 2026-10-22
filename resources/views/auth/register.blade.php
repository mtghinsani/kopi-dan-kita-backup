@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Daftar Akun Pelanggan</h1>
                    <form method="POST" action="{{ url('/register') }}" novalidate>
                        @csrf
                        @foreach ([
                            ['nama', 'Nama lengkap', 'text', 'name'],
                            ['email', 'Email', 'email', 'email'],
                            ['no_hp', 'Nomor HP', 'tel', 'tel'],
                        ] as [$field, $label, $type, $autocomplete])
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required autocomplete="{{ $autocomplete }}"
                                    class="form-control @error($field) is-invalid @enderror" @error($field) aria-describedby="{{ $field }}-error" @enderror>
                                @error($field)
                                    <div class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" required minlength="8" maxlength="64" autocomplete="new-password"
                                class="form-control @error('password') is-invalid @enderror" aria-describedby="password-help @error('password') password-error @enderror">
                            <div class="form-text" id="password-help">8 sampai 64 karakter.</div>
                            @error('password')
                                <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Ulangi password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" id="setuju_privasi" name="setuju_privasi" value="1" @checked(old('setuju_privasi'))
                                class="form-check-input @error('setuju_privasi') is-invalid @enderror" @error('setuju_privasi') aria-describedby="setuju_privasi-error" @enderror>
                            <label for="setuju_privasi" class="form-check-label">
                                Saya menyetujui <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Kebijakan Privasi</a> Kopi &amp; Kita.
                            </label>
                            @error('setuju_privasi')
                                <div class="invalid-feedback" id="setuju_privasi-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Daftar</button>
                    </form>
                    <p class="mt-3 mb-0 small text-center">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
