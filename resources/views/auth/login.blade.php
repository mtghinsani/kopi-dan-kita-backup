@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Masuk</h1>
                    <form method="POST" action="{{ url('/login') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                class="form-control @error('email') is-invalid @enderror" @error('email') aria-describedby="email-error" @enderror>
                            @error('email')
                                <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="form-control @error('password') is-invalid @enderror" @error('password') aria-describedby="password-error" @enderror>
                            @error('password')
                                <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Masuk</button>
                    </form>
                    <p class="mt-3 mb-0 small text-center">Belum punya akun? <a href="{{ route('register') }}">Daftar di sini</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
