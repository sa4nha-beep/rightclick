<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeGr = app(FinalizeGoodsReceiptAction::class);
    $this->action = app(FinalizePurchaseInvoiceAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_goods_receipt', 'approve_goods_receipt']));
});

afterEach(function () {
    DB::rollBack();
});

function makeFinalizedGoodsReceipt(FinalizeGoodsReceiptAction $action, Branch $branch, Partner $partner, Product $product, string $qty, string $unitCost): GoodsReceipt
{
    $gr = GoodsReceipt::factory()->create(['branch_id' => $branch->id, 'partner_id' => $partner->id]);
    $gr->lines()->create(['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => $unitCost]);

    return $action->execute($gr);
}

it('menolak faktur tanpa nomor', function () {
    $gr = makeFinalizedGoodsReceipt($this->finalizeGr, $this->branch, $this->supplier, $this->product, '10.0000', '10000.00');
    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $gr->id,
        'partner_id' => $this->supplier->id,
        'invoice_number' => '',
    ]);

    expect(fn () => $this->action->execute($invoice))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('menolak faktur atas penerimaan barang yang belum final', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '10.0000', 'unit_cost' => '10000.00']);
    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $gr->id,
        'partner_id' => $this->supplier->id,
    ]);

    expect(fn () => $this->action->execute($invoice))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('mengunci total_amount dari goods_receipts.total_amount (AC-09)', function () {
    $gr = makeFinalizedGoodsReceipt($this->finalizeGr, $this->branch, $this->supplier, $this->product, '10.0000', '115000.00');
    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $gr->id,
        'partner_id' => $this->supplier->id,
    ]);

    $result = $this->action->execute($invoice);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('INV')
        ->and((string) $result->total_amount)->toEqual((string) $gr->total_amount)
        ->and((string) $result->total_amount)->toEqual('1150000.00');
});
