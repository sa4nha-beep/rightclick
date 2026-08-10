<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeStockOpnameAction;
use App\Application\Actions\VoidStockOpnameAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockOpname;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeStockOpnameAction::class);
    $this->voidAction = app(VoidStockOpnameAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    // DocumentStateService::void() mengisi voided_by dari Auth::id() bila
    // tidak diberikan eksplisit — constraint C7 mensyaratkan itu terisi.
    $this->actingAs(makeTestUser(['void_stock_document']));
});

afterEach(function () {
    DB::rollBack();
});

it('void membalik koreksi naik yang belum tersentuh', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '10.0000',
        'unit_cost' => '10000.00',
        'reason' => 'Ditemukan',
    ]);

    $finalized = $this->finalizeAction->execute($opname);
    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('10.0000');

    $voided = $this->voidAction->execute($finalized, 'Salah hitung');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($voided->void_reason)->toBe('Salah hitung')
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('0.0000');
});
