<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Presentation\Filament\Resources\StockOpnames\StockOpnameResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar stock opname dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['perform_opname']));

    $this->get(StockOpnameResource::getUrl('index'))->assertOk();
});

it('halaman daftar stock opname ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockOpnameResource::getUrl('index'))->assertForbidden();
});

it('form Buat Stock Opname menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['perform_opname', 'view_stock_mutations']);
    $this->actingAs($user);
    // Livewire::test() tidak melewati middleware HTTP penuh (SetActiveBranchContext) —
    // isi BranchContext manual, sama pola dengan BranchScopeTest (T1.3).
    app(BranchContext::class)->set($user->default_branch_id);
    $product = Product::factory()->create();

    Livewire::test(CreateStockOpname::class)
        ->fillForm([
            'type' => StockOpnameType::Periodic->value,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'counted_qty' => 5,
                    'unit_cost' => 10000,
                    'reason' => null,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('stock_opnames', [
        'type' => StockOpnameType::Periodic->value,
        'state' => 'draft',
    ]);

    $this->assertDatabaseHas('stock_opname_lines', [
        'product_id' => $product->id,
        'counted_qty' => 5,
    ]);
});
