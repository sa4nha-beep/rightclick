<?php

declare(strict_types=1);
use Filament\Resources\Resource;
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

/**
 * T2.10 — daftar REPLICATED persis salinan CLAUDE.md §7 (dikurangi
 * `roles`/`permissions`, dikecualikan test Policy generik di atas karena
 * dikelola paket spatie/laravel-permission sendiri). Bila dokumen berubah,
 * daftar ini WAJIB ikut diperbarui — dokumen yang berlaku (§17), test ini
 * hanya menjaga kode tidak diam-diam menyimpang dari dokumen saat ini.
 *
 * Setiap Policy tabel REPLICATED wajib memakai `GuardsMasterDataWrites`
 * (M02) — tanpanya, node cabang bisa menulis master data yang seharusnya
 * hanya boleh ditulis HQ, lolos tanpa terdeteksi karena tidak ada RLS
 * (CLAUDE.md §4) yang mengompensasi di lapisan database.
 */
it('setiap Policy tabel REPLICATED memakai trait GuardsMasterDataWrites (M02)', function (string $modelName) {
    $policyClass = "App\\Infrastructure\\Persistence\\Policies\\{$modelName}Policy";

    expect(class_exists($policyClass))->toBeTrue("{$policyClass} tidak ditemukan.");

    $traits = class_uses($policyClass);

    expect(array_key_exists('App\\Infrastructure\\Persistence\\Concerns\\GuardsMasterDataWrites', $traits))->toBeTrue(
        "{$policyClass} mengelola tabel REPLICATED tapi tidak memakai GuardsMasterDataWrites — node cabang bisa menulis master data (M02).",
    );
})->with([
    'Branch',
    'User',
    'UserBranch',
    'Partner',
    'Product',
    'ProductCategory',
    'Unit',
    'Service',
    'Employee',
    'Setting',
]);

/**
 * T2.10 — setiap entitas Master Data Fase 2 wajib punya Filament Resource
 * terdaftar (bukan hanya Model+Policy tanpa antarmuka) DAN Resource itu
 * menunjuk model yang benar. Auto-discovery Filament (`discoverResources`
 * di `AdminPanelProvider`) tidak punya konvensi penamaan kaku seperti
 * guesser Policy Laravel, sehingga daftar di sini ditulis eksplisit,
 * bukan diturunkan dari nama model.
 */
it('setiap entitas Master Data Fase 2 memiliki Filament Resource yang menunjuk model yang benar', function (string $resourceClass, string $modelClass) {
    expect(class_exists($resourceClass))->toBeTrue("{$resourceClass} tidak ditemukan.");
    expect(is_subclass_of($resourceClass, Filament\Resources\Resource::class))->toBeTrue(
        "{$resourceClass} bukan turunan Filament\\Resources\\Resource.",
    );
    expect($resourceClass::getModel())->toBe($modelClass);
})->with([
    ['App\Presentation\Filament\Resources\Branches\BranchResource', 'App\Infrastructure\Persistence\Models\Branch'],
    ['App\Presentation\Filament\Resources\Partners\PartnerResource', 'App\Infrastructure\Persistence\Models\Partner'],
    ['App\Presentation\Filament\Resources\ProductCategories\ProductCategoryResource', 'App\Infrastructure\Persistence\Models\ProductCategory'],
    ['App\Presentation\Filament\Resources\Units\UnitResource', 'App\Infrastructure\Persistence\Models\Unit'],
    ['App\Presentation\Filament\Resources\Products\ProductResource', 'App\Infrastructure\Persistence\Models\Product'],
    ['App\Presentation\Filament\Resources\Employees\EmployeeResource', 'App\Infrastructure\Persistence\Models\Employee'],
    ['App\Presentation\Filament\Resources\Services\ServiceResource', 'App\Infrastructure\Persistence\Models\Service'],
]);

/**
 * T3.8 — sama pola dengan T2.10 di atas, diperluas untuk entitas Inventory
 * Core Fase 3 (batch, ledger mutasi, opname, adjustment, transfer,
 * penerimaan transfer).
 */
it('setiap entitas Inventory Core Fase 3 memiliki Filament Resource yang menunjuk model yang benar', function (string $resourceClass, string $modelClass) {
    expect(class_exists($resourceClass))->toBeTrue("{$resourceClass} tidak ditemukan.");
    expect(is_subclass_of($resourceClass, Filament\Resources\Resource::class))->toBeTrue(
        "{$resourceClass} bukan turunan Filament\\Resources\\Resource.",
    );
    expect($resourceClass::getModel())->toBe($modelClass);
})->with([
    ['App\Presentation\Filament\Resources\StockBalances\StockBalanceResource', 'App\Infrastructure\Persistence\Models\StockBalance'],
    ['App\Presentation\Filament\Resources\StockBatches\StockBatchResource', 'App\Infrastructure\Persistence\Models\StockBatch'],
    ['App\Presentation\Filament\Resources\StockMutations\StockMutationResource', 'App\Infrastructure\Persistence\Models\StockMutation'],
    ['App\Presentation\Filament\Resources\StockOpnames\StockOpnameResource', 'App\Infrastructure\Persistence\Models\StockOpname'],
    ['App\Presentation\Filament\Resources\StockAdjustments\StockAdjustmentResource', 'App\Infrastructure\Persistence\Models\StockAdjustment'],
    ['App\Presentation\Filament\Resources\StockTransfers\StockTransferResource', 'App\Infrastructure\Persistence\Models\StockTransfer'],
    ['App\Presentation\Filament\Resources\StockTransferReceipts\StockTransferReceiptResource', 'App\Infrastructure\Persistence\Models\StockTransferReceipt'],
]);
