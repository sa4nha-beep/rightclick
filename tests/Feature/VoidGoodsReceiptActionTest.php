<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\VoidGoodsReceiptAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeGoodsReceiptAction::class);
    $this->voidAction = app(VoidGoodsReceiptAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_goods_receipt', 'review_goods_receipt', 'approve_goods_receipt']));
});

afterEach(function () {
    DB::rollBack();
});

it('void membalik penerimaan yang belum tersentuh', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_cost' => '20000.00']);

    $finalized = $this->finalizeAction->execute($gr);
    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('5.0000');

    $voided = $this->voidAction->execute($finalized, 'Barang dikembalikan ke pemasok');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('0.0000');
});

it('menolak void bila masih ada faktur pembelian aktif', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_cost' => '20000.00']);
    $finalized = $this->finalizeAction->execute($gr);

    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $finalized->id,
        'partner_id' => $this->supplier->id,
    ]);
    app(FinalizePurchaseInvoiceAction::class)->execute($invoice);

    expect(fn () => $this->voidAction->execute($finalized->fresh(), 'Coba batalkan'))
        ->toThrow(StockDocumentValidationException::class);
});
