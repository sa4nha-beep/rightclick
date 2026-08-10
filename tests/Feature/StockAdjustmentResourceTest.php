<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Presentation\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar penyesuaian stok dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));

    $this->get(StockAdjustmentResource::getUrl('index'))->assertOk();
});

it('halaman daftar penyesuaian stok ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockAdjustmentResource::getUrl('index'))->assertForbidden();
});

it('form Buat Penyesuaian Stok menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['perform_adjustment', 'view_stock_mutations']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);
    $product = Product::factory()->create();

    Livewire::test(CreateStockAdjustment::class)
        ->fillForm([
            'lines' => [
                [
                    'product_id' => $product->id,
                    'direction' => StockAdjustmentDirection::Increase->value,
                    'quantity' => 3,
                    'unit_cost' => 25000,
                    'reason' => 'Ditemukan saat pengecekan',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('stock_adjustments', ['state' => 'draft']);
    $this->assertDatabaseHas('stock_adjustment_lines', [
        'product_id' => $product->id,
        'direction' => StockAdjustmentDirection::Increase->value,
    ]);
});
