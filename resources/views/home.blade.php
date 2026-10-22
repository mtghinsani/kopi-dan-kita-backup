@extends('layouts.app')

@section('title', 'Beranda')

@php
    $customer = auth()->user()?->hasRole(\App\Enums\Role::Pelanggan);
@endphp

@section('content')
    <section class="kk-hero row align-items-center gx-4 gy-5 py-3 py-lg-5">
        <div class="col-lg-7">
            @if ($kkStore)
                <p class="kk-open-state {{ $kkOpen ? 'is-open' : 'is-closed' }} mb-3">
                    {{ $kkOpen ? 'Buka sekarang' : 'Sedang tutup' }}, pesanan diterima {{ substr($kkStore->jam_buka, 0, 5) }} sampai {{ substr($kkStore->jam_tutup, 0, 5) }}
                </p>
            @endif
            <h1 class="kk-hero-title mb-3">Pesan dulu, ambil tanpa antre.</h1>
            <p class="fs-5 text-body-secondary kk-lead mb-4">
                Pilih kopi favoritmu dan atur ukuran, es, serta gulanya. Bayar online atau di kasir, lalu ambil di kedai dengan kode pesanan.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('menu.index') }}" class="btn btn-primary btn-lg px-4">Lihat menu</a>
                @guest
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-link btn-lg">Daftar akun baru</a>
                @endguest
                @if ($customer)
                    <a href="{{ route('orders.index') }}" class="btn btn-outline-primary btn-lg">Pesanan saya</a>
                @endif
            </div>
        </div>

        {{-- Contoh tiket: menunjukkan bahwa pengambilan cukup dengan kode pesanan. --}}
        <div class="col-lg-5">
            <figure class="kk-ticket kk-hero-ticket text-center mx-auto mb-0">
                <span class="d-block text-body-secondary mb-1">Kode pesanan</span>
                <span class="d-block display-4 font-monospace fw-bold kk-code">K7QF3M</span>
                <span class="badge text-bg-info fs-6 mt-2">Siap Diambil</span>
                <figcaption class="small text-body-secondary mt-3">Contoh kode yang kamu tunjukkan ke kasir saat mengambil pesanan.</figcaption>
            </figure>
        </div>
    </section>

    <section class="py-4 border-top" aria-labelledby="cara-pesan">
        <h2 class="h3 mb-4" id="cara-pesan">Cara pesan</h2>
        <ol class="kk-howto list-unstyled row g-4 mb-0">
            <li class="col-md-4">
                <h3 class="h5">Pilih menu</h3>
                <p class="mb-0 text-body-secondary">Atur ukuran, suhu, es, gula, dan tambahan. Harga langsung terlihat sebelum masuk keranjang.</p>
            </li>
            <li class="col-md-4">
                <h3 class="h5">Bayar</h3>
                <p class="mb-0 text-body-secondary">Bayar online dengan QRIS atau e-wallet, atau pilih bayar di kasir dengan QRIS kedai.</p>
            </li>
            <li class="col-md-4">
                <h3 class="h5">Ambil dengan kode</h3>
                <p class="mb-0 text-body-secondary">Halaman pesanan memberi tahu saat minumanmu siap. Tunjukkan kode pesanan ke kasir.</p>
            </li>
        </ol>
    </section>
@endsection
