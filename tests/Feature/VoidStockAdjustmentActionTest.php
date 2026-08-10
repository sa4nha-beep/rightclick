<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeStockAdjustmentAction;
use App\Application\Actions\VoidStockAdjustmentAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeStockAdjustmentAction::class);
    $this->voidAction = app(VoidStockAdjustmentAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_adjustment', 'void_stock_document']));
});

afterEach(function () {
    DB::rollBack();
});

it('void membalik penyesuaian naik yang belum tersentuh', function () {
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);
    $adjustment->lines()->create([
        'product_id' => $this->product->id,
        'direction' => 'increase',
        'quantity' => '5.0000',
        'unit_cost' => '20000.00',
        'reason' => 'Uji otomatis',
    ]);

    $finalized = $this->finalizeAction->execute($adjustment);
    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('5.0000');

    $voided = $this->voidAction->execute($finalized, 'Salah input');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('0.0000');
});
