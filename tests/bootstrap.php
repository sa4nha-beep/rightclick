<?php

declare(strict_types=1);

/**
 * RIGHTCLICK — bootstrap PHPUnit kustom.
 *
 * Kenapa berkas ini ada: `<env force="true">` di phpunit.xml hanya
 * menyentuh `putenv()` dan `$_ENV` (lihat
 * PHPUnit\TextUI\Configuration\PhpHandler::handleEnvVariables()) — TIDAK
 * PERNAH `$_SERVER`. Tapi vlucas/phpdotenv (dipakai Laravel untuk
 * env()/config()) membaca lewat `ServerConstAdapter` LEBIH DULU daripada
 * `EnvConstAdapter`/`PutenvAdapter` (RepositoryBuilder::DEFAULT_ADAPTERS).
 *
 * Docker mengisi `$_SERVER` dari env_file container (.env) sejak proses PHP
 * dimulai — nilai itu menang atas `<env force="true">` phpunit.xml begitu
 * saja, walau `force="true"` sudah benar dipakai. Akibatnya (ditemukan
 * T1.11): `APP_ENV` terbaca "local" bukan "testing" sepanjang test suite,
 * `app()->runningUnitTests()` selalu false, dan — lebih serius — test
 * suite diam-diam berjalan di atas database `rightclick` (dev), BUKAN
 * `rightclick_testing`, melanggar B3 (CLAUDE.md §18).
 *
 * Skrip ini berjalan SETELAH PhpHandler menerapkan seluruh nilai `<env>`
 * (urutan `PHPUnit\TextUI\Application::run()`: PhpHandler dulu, baru
 * `bootstrap` dimuat) — jadi `$_ENV` di titik ini sudah benar; tinggal
 * disalin ke `$_SERVER` supaya ServerConstAdapter juga melihat nilai yang
 * sama, bukan nilai lama dari container.
 */
require __DIR__.'/../vendor/autoload.php';

foreach ($_ENV as $key => $value) {
    $_SERVER[$key] = $value;
}
