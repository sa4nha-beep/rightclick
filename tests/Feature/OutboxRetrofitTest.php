<?php

declare(strict_types=1);

/**
 * T5.7, simpul kritis — membuktikan SELURUH `Finalize*Action`/`Void*Action`/
 * `Record*Action` atas dokumen SYNCED benar-benar menerbitkan
 * `OutboxEvent` dengan `event_type`/`aggregate_type` yang tepat, di
 * transaksi yang sama. Satu berkas konsolidasi (bukan menyebar assertion
 * ke 22 berkas test Action yang sudah ada) — tujuannya membuktikan
 * KELENGKAPAN retrofit lintas seluruh domain (Sales/Inventory/Procurement)
 * dalam satu tempat yang mudah ditinjau, bukan menduplikasi cakupan test
 * bisnis yang sudah ada di masing-masing `*ActionTest.php`.
 */

use App\Application\Actions\CloseCashierShiftAction;
use App\Application\Actions\DispatchStockTransferAction;
use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\FinalizePurchaseOrderAction;
use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\FinalizeSaleReturnAction;
use App\Application\Actions\FinalizeStockAdjustmentAction;
use App\Application\Actions\FinalizeStockOpnameAction;
use App\Application\Actions\ReceiveStockTransferAction;
use App\Application\Actions\RecordPurchasePaymentAction;
use App\Application\Actions\RecordReceivablePaymentAction;
use App\Application\Actions\VoidCashierShiftAction;
use App\Application\Actions\VoidGoodsReceiptAction;
use App\Application\Actions\VoidPurchaseInvoiceAction;
use App\Application\Actions\VoidPurchaseOrderAction;
use App\Application\Actions\VoidSaleAction;
use App\Application\Actions\VoidSaleReturnAction;
use App\Application\Actions\VoidStockAdjustmentAction;
use App\Application\Actions\VoidStockOpnameAction;
use App\Application\Actions\VoidStockTransferAction;
use App\Application\Actions\VoidStockTransferReceiptAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Infrastructure\Persistence\Models\StockOpname;
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = makeTestUser([
        'create_sale', 'void_sale', 'create_sale_return', 'process_sale_return', 'close_cashier_shift',
        'perform_adjustment', 'void_stock_document', 'perform_opname', 'perform_transfer',
        'create_purchase_order', 'void_purchase_order', 'perform_goods_receipt', 'review_goods_receipt',
        'approve_goods_receipt', 'record_cash_entry',
    ]);
    $this->actingAs($this->user);
});

afterEach(function () {
    DB::rollBack();
});

/**
 * Urut berdasarkan `id` (UUID v7, terurut waktu presisi tinggi) — BUKAN
 * `created_at` (`timestampsTz()` presisi detik, beberapa event dalam satu
 * test bisa jatuh di detik yang sama dan berakhir seri/tidak deterministik
 * bila diurutkan dari kolom itu).
 */
function outboxEventFor(string $aggregateType, string $aggregateId): ?OutboxEvent
{
    return OutboxEvent::query()
        ->where('aggregate_type', $aggregateType)
        ->where('aggregate_id', $aggregateId)
        ->orderByDesc('id')
        ->first();
}

it('sale.finalized dan sale.voided tercatat di outbox', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '1.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '15000.00']);

    $finalized = app(FinalizeSaleAction::class)->execute($sale);
    $finalizedEvent = outboxEventFor($finalized->getMorphClass(), $finalized->id);
    expect($finalizedEvent->event_type)->toBe('sale.finalized');

    // T5.8 — payload harus membawa snapshot relasional lengkap (bukan
    // cuma kolom Sale), supaya HQ punya cukup data merekonstruksi
    // SELURUH tabel SYNCED anak tanpa round-trip tambahan.
    expect($finalizedEvent->payload['items'])->toHaveCount(1)
        ->and($finalizedEvent->payload['payments'])->toHaveCount(1)
        ->and($finalizedEvent->payload['stock_mutations'])->toHaveCount(1)
        ->and($finalizedEvent->payload['stock_mutations'][0]['quantity'])->toEqual('-1.0000')
        ->and($finalizedEvent->payload['cash_entries'])->toHaveCount(1)
        ->and($finalizedEvent->payload['cash_entries'][0]['entry_type'])->toBe('sale_payment');

    app(VoidSaleAction::class)->execute($finalized, 'Uji outbox');
    $voidedEvent = outboxEventFor($finalized->getMorphClass(), $finalized->id);
    expect($voidedEvent->event_type)->toBe('sale.voided')
        // Void tidak punya baris items/payments baru — hanya mutasi/kas
        // pembalik (VoidReversal), keduanya tetap merujuk $sale yang sama.
        ->and($voidedEvent->payload['stock_mutations'])->toHaveCount(2)
        ->and($voidedEvent->payload['cash_entries'])->toHaveCount(2);
});

it('sale_return.finalized dan sale_return.voided tercatat di outbox', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '30000.00']);
    $finalizedSale = app(FinalizeSaleAction::class)->execute($sale);
    $saleItem = $finalizedSale->items->first();

    $saleReturn = SaleReturn::factory()->create([
        'branch_id' => $this->branch->id,
        'sale_id' => $finalizedSale->id,
    ]);
    $saleReturn->lines()->create([
        'sale_item_id' => $saleItem->id,
        'quantity' => '1.0000',
        'reason' => 'Uji outbox',
    ]);

    $finalizedReturn = app(FinalizeSaleReturnAction::class)->execute($saleReturn);
    expect(outboxEventFor($finalizedReturn->getMorphClass(), $finalizedReturn->id)->event_type)->toBe('sale_return.finalized');

    app(VoidSaleReturnAction::class)->execute($finalizedReturn, 'Uji outbox');
    expect(outboxEventFor($finalizedReturn->getMorphClass(), $finalizedReturn->id)->event_type)->toBe('sale_return.voided');
});

it('cashier_shift.finalized dan cashier_shift.voided tercatat di outbox', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);

    $closed = app(CloseCashierShiftAction::class)->execute($shift, [['denomination' => '1000.00', 'quantity' => 0]]);
    expect(outboxEventFor($closed->getMorphClass(), $closed->id)->event_type)->toBe('cashier_shift.finalized');

    app(VoidCashierShiftAction::class)->execute($closed, 'Uji outbox');
    expect(outboxEventFor($closed->getMorphClass(), $closed->id)->event_type)->toBe('cashier_shift.voided');
});

it('stock_adjustment.finalized dan stock_adjustment.voided tercatat di outbox', function () {
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);
    $adjustment->lines()->create([
        'product_id' => $this->product->id,
        'direction' => 'increase',
        'quantity' => '2.0000',
        'unit_cost' => '10000.00',
        'reason' => 'Uji outbox',
    ]);

    $finalized = app(FinalizeStockAdjustmentAction::class)->execute($adjustment);
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('stock_adjustment.finalized');

    app(VoidStockAdjustmentAction::class)->execute($finalized, 'Uji outbox');
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('stock_adjustment.voided');
});

it('stock_opname.finalized dan stock_opname.voided tercatat di outbox', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '5.0000',
        'unit_cost' => '10000.00',
        'reason' => 'Uji outbox',
    ]);

    $finalized = app(FinalizeStockOpnameAction::class)->execute($opname);
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('stock_opname.finalized');

    app(VoidStockOpnameAction::class)->execute($finalized, 'Uji outbox');
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('stock_opname.voided');
});

it('stock_transfer.finalized/.voided dan stock_transfer_receipt.finalized/.voided tercatat di outbox', function () {
    $dest = Branch::factory()->create();

    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $transfer = StockTransfer::factory()->create(['branch_id' => $this->branch->id, 'dest_branch_id' => $dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '3.0000']);

    $dispatched = app(DispatchStockTransferAction::class)->execute($transfer);
    expect(outboxEventFor($dispatched->getMorphClass(), $dispatched->id)->event_type)->toBe('stock_transfer.finalized');

    $receipt = app(ReceiveStockTransferAction::class)->execute($dispatched);
    expect(outboxEventFor($receipt->getMorphClass(), $receipt->id)->event_type)->toBe('stock_transfer_receipt.finalized');

    app(VoidStockTransferReceiptAction::class)->execute($receipt, 'Uji outbox');
    expect(outboxEventFor($receipt->getMorphClass(), $receipt->id)->event_type)->toBe('stock_transfer_receipt.voided');

    app(VoidStockTransferAction::class)->execute($dispatched->fresh(), 'Uji outbox');
    expect(outboxEventFor($dispatched->getMorphClass(), $dispatched->id)->event_type)->toBe('stock_transfer.voided');
});

it('purchase_order.finalized dan purchase_order.voided tercatat di outbox', function () {
    $supplier = Partner::factory()->create(['partner_type' => PartnerType::Supplier]);
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $supplier->id]);
    $po->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_price' => '10000.00']);

    $finalized = app(FinalizePurchaseOrderAction::class)->execute($po);
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('purchase_order.finalized');

    app(VoidPurchaseOrderAction::class)->execute($finalized, 'Uji outbox');
    expect(outboxEventFor($finalized->getMorphClass(), $finalized->id)->event_type)->toBe('purchase_order.voided');
});

it('goods_receipt.finalized/.voided dan purchase_invoice.finalized/.voided serta purchase_payment.recorded tercatat di outbox', function () {
    $supplier = Partner::factory()->create();
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_cost' => '10000.00']);

    $finalizedGr = app(FinalizeGoodsReceiptAction::class)->execute($gr);
    $grEvent = outboxEventFor($finalizedGr->getMorphClass(), $finalizedGr->id);
    expect($grEvent->event_type)->toBe('goods_receipt.finalized')
        ->and($grEvent->payload['lines'])->toHaveCount(1)
        ->and($grEvent->payload['stock_mutations'])->toHaveCount(1)
        ->and($grEvent->payload['stock_batches'])->toHaveCount(1)
        ->and($grEvent->payload['stock_batches'][0]['unit_cost'])->toEqual('10000.00');

    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $finalizedGr->id,
        'partner_id' => $supplier->id,
    ]);
    $finalizedInvoice = app(FinalizePurchaseInvoiceAction::class)->execute($invoice);
    expect(outboxEventFor($finalizedInvoice->getMorphClass(), $finalizedInvoice->id)->event_type)->toBe('purchase_invoice.finalized');

    $payment = app(RecordPurchasePaymentAction::class)->execute($finalizedInvoice, ['method' => 'cash', 'amount' => '20000.00']);
    $paymentEvent = outboxEventFor($payment->getMorphClass(), $payment->id);
    expect($paymentEvent->event_type)->toBe('purchase_payment.recorded')
        // CashEntry merujuk PurchaseInvoice, bukan PurchasePayment (aggregate
        // event ini) — auto-attach OutboxService tidak menemukannya sendiri,
        // dilampirkan manual lewat $extra (lihat RecordPurchasePaymentAction).
        ->and($paymentEvent->payload['cash_entries'])->toHaveCount(1)
        ->and($paymentEvent->payload['cash_entries'][0]['amount'])->toEqual('-20000.00');

    // Faktur belum bisa dibatalkan karena sudah menerima pembayaran (T5.3) —
    // uji void faktur dengan dokumen TERPISAH yang belum dibayar.
    $secondGr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $supplier->id]);
    $secondGr->lines()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_cost' => '10000.00']);
    $finalizedSecondGr = app(FinalizeGoodsReceiptAction::class)->execute($secondGr);
    $secondInvoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $finalizedSecondGr->id,
        'partner_id' => $supplier->id,
    ]);
    $finalizedSecondInvoice = app(FinalizePurchaseInvoiceAction::class)->execute($secondInvoice);

    app(VoidPurchaseInvoiceAction::class)->execute($finalizedSecondInvoice, 'Uji outbox');
    expect(outboxEventFor($finalizedSecondInvoice->getMorphClass(), $finalizedSecondInvoice->id)->event_type)->toBe('purchase_invoice.voided');

    app(VoidGoodsReceiptAction::class)->execute($finalizedSecondGr->fresh(), 'Uji outbox');
    expect(outboxEventFor($finalizedSecondGr->getMorphClass(), $finalizedSecondGr->id)->event_type)->toBe('goods_receipt.voided');
});

it('receivable_payment.recorded tercatat di outbox', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $partner = Partner::factory()->create();
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id, 'partner_id' => $partner->id]);
    $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '10000.00']);
    $finalizedSale = app(FinalizeSaleAction::class)->execute($sale);

    $collection = app(RecordReceivablePaymentAction::class)->execute($finalizedSale, ['method' => 'cash', 'amount' => '5000.00']);

    expect(outboxEventFor($collection->getMorphClass(), $collection->id)->event_type)->toBe('receivable_payment.recorded');
});
