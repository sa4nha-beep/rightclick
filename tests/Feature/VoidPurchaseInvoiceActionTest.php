<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\VoidPurchaseInvoiceAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\DocumentStateException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeGr = app(FinalizeGoodsReceiptAction::class);
    $this->finalizeInvoice = app(FinalizePurchaseInvoiceAction::class);
    $this->voidAction = app(VoidPurchaseInvoiceAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_goods_receipt', 'approve_goods_receipt']));
});

afterEach(function () {
    DB::rollBack();
});

it('void mengubah faktur final menjadi void tanpa menyentuh stok', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_cost' => '20000.00']);
    $finalizedGr = $this->finalizeGr->execute($gr);

    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $finalizedGr->id,
        'partner_id' => $this->supplier->id,
    ]);
    $finalizedInvoice = $this->finalizeInvoice->execute($invoice);

    $voided = $this->voidAction->execute($finalizedInvoice, 'Salah nomor faktur');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($voided->void_reason)->toBe('Salah nomor faktur')
        ->and($finalizedGr->fresh()->state)->toBe(DocumentState::Final);
});

it('menolak void tanpa alasan', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_cost' => '20000.00']);
    $finalizedGr = $this->finalizeGr->execute($gr);

    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $finalizedGr->id,
        'partner_id' => $this->supplier->id,
    ]);
    $finalizedInvoice = $this->finalizeInvoice->execute($invoice);

    expect(fn () => $this->voidAction->execute($finalizedInvoice, ''))
        ->toThrow(DocumentStateException::class);
});
