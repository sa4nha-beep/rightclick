<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Presentation\Filament\Resources\StockBatches\StockBatchResource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman batch stok dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_batches']));

    $this->get(StockBatchResource::getUrl('index'))->assertOk();
});

it('halaman batch stok ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockBatchResource::getUrl('index'))->assertForbidden();
});

it('halaman detail batch stok dapat diakses pengguna berwenang', function () {
    $user = makeTestUser(['view_batches']);
    $this->actingAs($user);
    $batch = StockBatch::factory()->create(['branch_id' => $user->default_branch_id]);

    $this->get(StockBatchResource::getUrl('view', ['record' => $batch]))->assertOk();
});

it('P6 — unit_cost TIDAK ikut ter-select dari database bagi peran tanpa view_stock_cost', function () {
    $user = makeTestUser(['view_batches']); // tanpa view_stock_cost — pola Gudang
    $this->actingAs($user);
    StockBatch::factory()->create(['branch_id' => $user->default_branch_id]);

    $record = StockBatchResource::getEloquentQuery()->first();

    expect($record)->not->toBeNull()
        ->and($record->getAttributes())->not->toHaveKey('unit_cost');
});

it('P6 — unit_cost ikut ter-select bagi peran dengan view_stock_cost', function () {
    $user = makeTestUser(['view_batches', 'view_stock_cost']);
    $this->actingAs($user);
    StockBatch::factory()->create(['branch_id' => $user->default_branch_id]);

    $record = StockBatchResource::getEloquentQuery()->first();

    expect($record)->not->toBeNull()
        ->and($record->getAttributes())->toHaveKey('unit_cost');
});
