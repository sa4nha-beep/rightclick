<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\RecordPurchasePaymentAction;
use App\Application\Services\CashLedgerService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(RecordPurchasePaymentAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_goods_receipt', 'approve_goods_receipt', 'record_cash_entry']));
});

afterEach(function () {
    DB::rollBack();
});

function makeFinalizedInvoice(Branch $branch, Partner $supplier, Product $product, string $qty, string $unitCost): PurchaseInvoice
{
    $gr = GoodsReceipt::factory()->create(['branch_id' => $branch->id, 'partner_id' => $supplier->id]);
    $gr->lines()->create(['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => $unitCost]);
    $finalizedGr = app(FinalizeGoodsReceiptAction::class)->execute($gr);

    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $branch->id,
        'goods_receipt_id' => $finalizedGr->id,
        'partner_id' => $supplier->id,
    ]);

    return app(FinalizePurchaseInvoiceAction::class)->execute($invoice);
}

/**
 * @return array<int, array{payable_id: string, amount: string}>
 */
function allocationForInvoice(PurchaseInvoice $invoice, string $amount): array
{
    return [['payable_id' => (string) $invoice->refresh()->payable->id, 'amount' => $amount]];
}

it('menolak alokasi ke payable yang tidak ditemukan (mis. faktur masih draft, belum punya Payable)', function () {
    expect(fn () => $this->action->execute(
        [['payable_id' => (string) Str::uuid(), 'amount' => '10000.00']],
        'cash',
        '10000.00',
    ))->toThrow(PurchaseInvoiceValidationException::class);
});

it('menolak jumlah pembayaran nol atau negatif', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    expect(fn () => $this->action->execute(allocationForInvoice($invoice, '0'), 'cash', '0'))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('menolak bila total alokasi tidak sama dengan jumlah pembayaran', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    expect(fn () => $this->action->execute(allocationForInvoice($invoice, '40000.00'), 'cash', '50000.00'))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('mencatat cicilan dan memperbarui saldo secara bertahap — payment_status berubah unpaid → partial → paid', function () {
    // total_amount = 10 x 10.000 = 100.000
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Unpaid)
        ->and($invoice->balanceDue())->toEqual('100000.00');

    $this->action->execute(allocationForInvoice($invoice, '40000.00'), 'cash', '40000.00');
    $invoice->refresh();

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Partial)
        ->and($invoice->amountPaid())->toEqual('40000.00')
        ->and($invoice->balanceDue())->toEqual('60000.00');

    $this->action->execute(allocationForInvoice($invoice, '60000.00'), 'transfer', '60000.00', 'TRX-001');
    $invoice->refresh();

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Paid)
        ->and($invoice->balanceDue())->toEqual('0.00')
        ->and($invoice->payable->allocations()->count())->toBe(2);
});

it('menolak pembayaran yang melebihi sisa hutang', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');
    $this->action->execute(allocationForInvoice($invoice, '70000.00'), 'cash', '70000.00');

    expect(fn () => $this->action->execute(allocationForInvoice($invoice, '40000.00'), 'cash', '40000.00'))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('outstandingBalanceForPartner menjumlahkan sisa hutang lintas faktur pemasok yang sama', function () {
    $firstProduct = Product::factory()->create();
    $secondProduct = Product::factory()->create();

    $firstInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $firstProduct, '10.0000', '10000.00');
    $secondInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $secondProduct, '5.0000', '20000.00');

    $this->action->execute(allocationForInvoice($firstInvoice, '30000.00'), 'cash', '30000.00');

    // firstInvoice: 100.000 - 30.000 = 70.000 sisa. secondInvoice: 100.000 sisa (belum dibayar).
    $outstanding = PurchaseInvoice::outstandingBalanceForPartner($this->branch->id, $this->supplier->id);

    expect($outstanding)->toEqual('170000.00')
        ->and($secondInvoice->balanceDue())->toEqual('100000.00');
});

it('T5.4 — pembayaran tunai menerbitkan CashEntry kas keluar yang merujuk header PurchasePayment', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    $purchasePayment = $this->action->execute(allocationForInvoice($invoice, '40000.00'), 'cash', '40000.00');

    $entry = CashEntry::query()
        ->where('reference_type', $purchasePayment->getMorphClass())
        ->where('reference_id', $purchasePayment->id)
        ->sole();

    expect($entry->entry_type)->toBe(CashEntryType::PurchasePayment)
        ->and((string) $entry->amount)->toEqual('-40000.00')
        ->and(app(CashLedgerService::class)->balance($this->branch))->toEqual('-40000.00');
});

it('T5.4 — pembayaran non-tunai TIDAK menerbitkan CashEntry', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    $purchasePayment = $this->action->execute(allocationForInvoice($invoice, '40000.00'), 'transfer', '40000.00');

    expect(CashEntry::query()->where('reference_type', $purchasePayment->getMorphClass())->where('reference_id', $purchasePayment->id)->exists())
        ->toBeFalse();
});

it('FR-M11a-05 — satu pembayaran dialokasikan ke DUA faktur sekaligus', function () {
    $firstProduct = Product::factory()->create();
    $secondProduct = Product::factory()->create();

    $firstInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $firstProduct, '10.0000', '10000.00');
    $secondInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $secondProduct, '5.0000', '20000.00');

    // firstInvoice sisa 100.000, secondInvoice sisa 100.000 — SATU pembayaran
    // Rp150.000 dialokasikan 90.000 ke firstInvoice + 60.000 ke secondInvoice.
    $purchasePayment = $this->action->execute(
        [
            ['payable_id' => (string) $firstInvoice->payable->id, 'amount' => '90000.00'],
            ['payable_id' => (string) $secondInvoice->payable->id, 'amount' => '60000.00'],
        ],
        'cash',
        '150000.00',
    );

    expect($purchasePayment->allocations)->toHaveCount(2)
        ->and((string) $firstInvoice->refresh()->balanceDue())->toEqual('10000.00')
        ->and((string) $secondInvoice->refresh()->balanceDue())->toEqual('40000.00');

    $entry = CashEntry::query()
        ->where('reference_type', $purchasePayment->getMorphClass())
        ->where('reference_id', $purchasePayment->id)
        ->sole();
    expect((string) $entry->amount)->toEqual('-150000.00');
});

it('menolak alokasi lintas partner berbeda dalam satu pemanggilan', function () {
    $firstInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    $otherSupplier = Partner::factory()->create();
    $otherProduct = Product::factory()->create();
    $otherInvoice = makeFinalizedInvoice($this->branch, $otherSupplier, $otherProduct, '10.0000', '10000.00');

    expect(fn () => $this->action->execute(
        [
            ['payable_id' => (string) $firstInvoice->payable->id, 'amount' => '50000.00'],
            ['payable_id' => (string) $otherInvoice->payable->id, 'amount' => '25000.00'],
        ],
        'cash',
        '75000.00',
    ))->toThrow(PurchaseInvoiceValidationException::class);
});
