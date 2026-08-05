<?php

declare(strict_types=1);

/**
 * Pengujian arsitektur atas aturan lapisan yang mengikat pada
 * HS-ARCH-RIGHTCLICK-v1.1 bagian 2.1.
 *
 * Aturan lapisan yang hanya tertulis di dokumen akan luntur seiring waktu.
 * Berkas ini menjadikannya gate CI.
 *
 * Architecture test untuk Policy (G2) ditambahkan pada T1.5, dan untuk
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
