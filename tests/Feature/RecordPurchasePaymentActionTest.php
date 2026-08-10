<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\RecordPurchasePaymentAction;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

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

it('menolak pembayaran atas faktur yang masih draft', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '10.0000', 'unit_cost' => '10000.00']);
    $invoice = PurchaseInvoice::factory()->create([
        'branch_id' => $this->branch->id,
        'goods_receipt_id' => $gr->id,
        'partner_id' => $this->supplier->id,
    ]);

    expect(fn () => $this->action->execute($invoice, ['method' => 'cash', 'amount' => '10000.00']))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('menolak jumlah pembayaran nol atau negatif', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    expect(fn () => $this->action->execute($invoice, ['method' => 'cash', 'amount' => '0']))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('mencatat cicilan dan memperbarui saldo secara bertahap — payment_status berubah unpaid → partial → paid', function () {
    // total_amount = 10 x 10.000 = 100.000
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Unpaid)
        ->and($invoice->balanceDue())->toEqual('100000.00');

    $this->action->execute($invoice, ['method' => 'cash', 'amount' => '40000.00']);
    $invoice->refresh();

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Partial)
        ->and($invoice->amountPaid())->toEqual('40000.00')
        ->and($invoice->balanceDue())->toEqual('60000.00');

    $this->action->execute($invoice, ['method' => 'transfer', 'amount' => '60000.00', 'reference_no' => 'TRX-001']);
    $invoice->refresh();

    expect($invoice->paymentStatus())->toBe(PaymentStatus::Paid)
        ->and($invoice->balanceDue())->toEqual('0.00')
        ->and($invoice->payments()->count())->toBe(2);
});

it('menolak pembayaran yang melebihi sisa hutang', function () {
    $invoice = makeFinalizedInvoice($this->branch, $this->supplier, $this->product, '10.0000', '10000.00');
    $this->action->execute($invoice, ['method' => 'cash', 'amount' => '70000.00']);

    expect(fn () => $this->action->execute($invoice->refresh(), ['method' => 'cash', 'amount' => '40000.00']))
        ->toThrow(PurchaseInvoiceValidationException::class);
});

it('outstandingBalanceForPartner menjumlahkan sisa hutang lintas faktur pemasok yang sama', function () {
    $firstProduct = Product::factory()->create();
    $secondProduct = Product::factory()->create();

    $firstInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $firstProduct, '10.0000', '10000.00');
    $secondInvoice = makeFinalizedInvoice($this->branch, $this->supplier, $secondProduct, '5.0000', '20000.00');

    $this->action->execute($firstInvoice, ['method' => 'cash', 'amount' => '30000.00']);

    // firstInvoice: 100.000 - 30.000 = 70.000 sisa. secondInvoice: 100.000 sisa (belum dibayar).
    $outstanding = PurchaseInvoice::outstandingBalanceForPartner($this->branch->id, $this->supplier->id);

    expect($outstanding)->toEqual('170000.00')
        ->and($secondInvoice->balanceDue())->toEqual('100000.00');
});
