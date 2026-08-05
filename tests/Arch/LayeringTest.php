<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;

/**
 * Pengujian arsitektur atas aturan lapisan yang mengikat pada
 * HS-ARCH-RIGHTCLICK-v1.1 bagian 2.1.
 *
 * Aturan lapisan yang hanya tertulis di dokumen akan luntur seiring waktu.
 * Berkas ini menjadikannya gate CI.
 *
 * Architecture test untuk Policy (P4) ditambahkan pada T1.6, dan untuk
 * penulis tunggal `stock_mutations` pada T3.2.
 */
arch('lapisan Domain tidak bergantung pada framework')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate',
        'Filament',
        'Livewire',
    ]);

arch('lapisan Domain tidak bergantung pada lapisan luar')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Application',
        'App\Infrastructure',
        'App\Presentation',
        'App\Http',
    ]);

arch('lapisan Application tidak bergantung pada Presentation')
    ->expect('App\Application')
    ->not->toUse([
        'App\Presentation',
        'Filament',
        'Livewire',
    ]);

arch('Presentation tidak diimpor oleh Infrastructure')
    ->expect('App\Infrastructure')
    ->not->toUse('App\Presentation');

arch('kode tidak memakai fungsi debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

it('menyediakan struktur direktori Clean Architecture', function (string $path) {
    expect(is_dir(app_path($path)))->toBeTrue("Direktori app/{$path} tidak ditemukan.");
})->with([
    'Domain/Inventory',
    'Domain/Sales',
    'Domain/Procurement',
    'Domain/Finance',
    'Domain/Identity',
    'Domain/Shared',
    'Application/Services',
    'Application/Actions',
    'Application/DTOs',
    'Infrastructure/Persistence',
    'Infrastructure/Sync',
    'Infrastructure/Printing',
    'Infrastructure/Queue',
    'Presentation/Filament',
    'Presentation/Pos',
]);

it('tidak menaruh kode di app/Models', function () {
    // Model Eloquent berada di App\Infrastructure\Persistence\Models.
    expect(is_dir(app_path('Models')))->toBeFalse(
        'app/Models tidak boleh dihidupkan kembali — model Eloquent milik lapisan Infrastructure.',
    );
});

it('setiap model Eloquent RIGHTCLICK memiliki Policy (P4)', function () {
    // Model milik paket spatie/laravel-permission, diperluas hanya untuk
    // primary key UUID v7 (T1.5). Otorisasinya dikelola package itu
    // sendiri lewat permission check, bukan Policy per model.
    $excluded = ['Permission', 'Role'];

    $modelFiles = glob(app_path('Infrastructure/Persistence/Models/*.php'));

    expect($modelFiles)->not->toBeEmpty();

    foreach ($modelFiles as $file) {
        $modelClass = 'App\\Infrastructure\\Persistence\\Models\\'.basename($file, '.php');

        if (in_array(class_basename($modelClass), $excluded, true)) {
            continue;
        }

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            continue;
        }

        // Guesser bawaan Laravel: App\...\Models\Foo -> App\...\Policies\FooPolicy.
        $policyClass = str_replace('\\Models\\', '\\Policies\\', $modelClass).'Policy';

        expect(class_exists($policyClass))->toBeTrue(
            "{$modelClass} tidak memiliki Policy ({$policyClass}) — wajib per P4.",
        );
    }
});
