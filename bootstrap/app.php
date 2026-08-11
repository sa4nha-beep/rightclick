<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bug nyata ditemukan lewat log produksi (bukan test): route `/pos`
        // (routes/web.php) memakai middleware `auth` bawaan Laravel, yang
        // secara default me-redirect tamu ke `route('login')` — TIDAK ADA
        // route bernama itu di aplikasi ini (satu-satunya login ada di
        // `/admin/login` lewat sistem Filament sendiri, terdaftar sebagai
        // `filament.admin.auth.login`). Akibatnya SETIAP akses `/pos` tanpa
        // sesi aktif (sesi kedaluwarsa, tab lama, browser baru) melempar
        // `RouteNotFoundException` mentah (500), bukan redirect ke login.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
