<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Pengguna yang sudah login dan membuka halaman tamu dikirim ke beranda sesuai role.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homePath());

        // Akses lewat tunnel HTTPS (ngrok): percayai header X-Forwarded-* agar URL dan cookie memakai https.
        $middleware->trustProxies(at: '*');

        // Webhook Midtrans tidak membawa token CSRF; keasliannya dicek lewat signature SHA512.
        $middleware->preventRequestForgery(except: ['midtrans/notification']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
