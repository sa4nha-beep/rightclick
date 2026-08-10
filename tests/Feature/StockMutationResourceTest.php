<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\StockMutation;
use App\Presentation\Filament\Resources\StockMutations\StockMutationResource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman mutasi stok dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_stock_mutations']));

    $this->get(StockMutationResource::getUrl('index'))->assertOk();
});

it('halaman mutasi stok ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockMutationResource::getUrl('index'))->assertForbidden();
});

it('halaman detail mutasi stok dapat diakses pengguna berwenang', function () {
    $user = makeTestUser(['view_stock_mutations']);
    $this->actingAs($user);
    $mutation = StockMutation::factory()->create(['branch_id' => $user->default_branch_id]);

    $this->get(StockMutationResource::getUrl('view', ['record' => $mutation]))->assertOk();
});

it('P6 — unit_cost TIDAK ikut ter-select dari database bagi peran tanpa view_stock_cost', function () {
    $user = makeTestUser(['view_stock_mutations']); // pola Gudang
    $this->actingAs($user);
    StockMutation::factory()->create(['branch_id' => $user->default_branch_id]);

    $record = StockMutationResource::getEloquentQuery()->first();

    expect($record)->not->toBeNull()
        ->and($record->getAttributes())->not->toHaveKey('unit_cost');
});

it('P6 — unit_cost ikut ter-select bagi peran dengan view_stock_cost', function () {
    $user = makeTestUser(['view_stock_mutations', 'view_stock_cost']);
    $this->actingAs($user);
    StockMutation::factory()->create(['branch_id' => $user->default_branch_id]);

    $record = StockMutationResource::getEloquentQuery()->first();

    expect($record)->not->toBeNull()
        ->and($record->getAttributes())->toHaveKey('unit_cost');
});
