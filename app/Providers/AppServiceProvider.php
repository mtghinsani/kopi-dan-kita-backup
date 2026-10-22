<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\StoreSetting;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // @rp($angka) menulis Rupiah tanpa desimal, misalnya Rp22.000 (tanpa ekstensi intl).
        Blade::directive('rp', fn (string $expression) => "<?php echo 'Rp'.number_format((float) ({$expression}), 0, ',', '.'); ?>");

        // Saat development, atribut di luar #[Fillable] memicu exception, bukan diabaikan diam-diam.
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        // Lima percobaan login per menit untuk kombinasi email dan IP; permintaan keenam dibalas 429.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Badge jumlah keranjang di navbar pelanggan (termasuk tombol keranjang ponsel di luar menu).
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('cartCount', $user?->role === Role::Pelanggan ? app(CartService::class)->count($user) : 0);
        });

        // Info kedai untuk footer, beranda, dan banner tutup di katalog. first(), bukan current(), agar halaman
        // tetap tampil walau pengaturan belum di-seed (misalnya pada tes autentikasi).
        View::composer(['layouts.app', 'home', 'customer.menu'], function ($view) {
            $store = StoreSetting::first();
            $view->with(['kkStore' => $store, 'kkOpen' => $store && app(OrderService::class)->isOpen($store)]);
        });
    }
}
