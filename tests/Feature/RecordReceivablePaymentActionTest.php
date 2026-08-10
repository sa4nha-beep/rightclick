<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\RecordReceivablePaymentAction;
use App\Application\Services\CashLedgerService;
use App\Application\Services\StockLedgerService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(RecordReceivablePaymentAction::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->partner = Partner::factory()->create();
    $this->user = makeTestUser(['create_sale', 'record_cash_entry']);
    $this->actingAs($this->user);
    $this->shift = CashierShift::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->user->id,
    ]);
});

afterEach(function () {
    DB::rollBack();
});

/**
 * Penjualan DP: total 150.000 (10 x 15.000), DP tunai 50.000, sisa piutang 100.000.
 */
function makeSaleWithReceivable(Branch $branch, CashierShift $shift, Partner $partner, Product $product): Sale
{
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $branch, $product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'cashier_shift_id' => $shift->id, 'partner_id' => $partner->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '10.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '50000.00']);

    return app(FinalizeSaleAction::class)->execute($sale);
}

it('menolak pelunasan atas penjualan yang masih draft', function () {
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);

    expect(fn () => $this->action->execute($sale, ['method' => 'cash', 'amount' => '10000.00']))
        ->toThrow(SaleValidationException::class);
});

it('menolak jumlah pelunasan nol atau negatif', function () {
    $sale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);

    expect(fn () => $this->action->execute($sale, ['method' => 'cash', 'amount' => '0']))
        ->toThrow(SaleValidationException::class);
});

it('mencatat cicilan dan memperbarui saldo secara bertahap — receivableStatus berubah unpaid → partial → paid', function () {
    $sale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);

    expect((string) $sale->balance_due)->toEqual('100000.00')
        ->and($sale->receivableStatus())->toBe(PaymentStatus::Unpaid);

    $this->action->execute($sale, ['method' => 'cash', 'amount' => '40000.00']);
    $sale->refresh();

    expect($sale->receivableStatus())->toBe(PaymentStatus::Partial)
        ->and($sale->amountCollected())->toEqual('40000.00')
        ->and($sale->remainingReceivable())->toEqual('60000.00');

    $this->action->execute($sale, ['method' => 'transfer', 'amount' => '60000.00', 'reference_no' => 'TRX-002']);
    $sale->refresh();

    expect($sale->receivableStatus())->toBe(PaymentStatus::Paid)
        ->and($sale->remainingReceivable())->toEqual('0.00')
        ->and($sale->receivablePayments()->count())->toBe(2);
});

it('menolak pelunasan yang melebihi sisa piutang', function () {
    $sale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);
    $this->action->execute($sale, ['method' => 'cash', 'amount' => '70000.00']);

    expect(fn () => $this->action->execute($sale->refresh(), ['method' => 'cash', 'amount' => '40000.00']))
        ->toThrow(SaleValidationException::class);
});

it('T5.4/T5.5 — pelunasan tunai menerbitkan CashEntry ReceivableCollection, BUKAN SalePayment', function () {
    $sale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);

    $this->action->execute($sale, ['method' => 'cash', 'amount' => '40000.00']);

    $entries = CashEntry::query()
        ->where('reference_type', $sale->getMorphClass())
        ->where('reference_id', $sale->id)
        ->get();

    // Satu dari DP saat finalisasi (SalePayment, 50.000) + satu dari pelunasan (ReceivableCollection, 40.000).
    expect($entries)->toHaveCount(2);

    $collection = $entries->firstWhere('entry_type', CashEntryType::ReceivableCollection);
    expect($collection)->not->toBeNull()
        ->and((string) $collection->amount)->toEqual('40000.00')
        ->and(app(CashLedgerService::class)->balance($this->branch))->toEqual('90000.00');
});

it('pelunasan non-tunai TIDAK menerbitkan CashEntry tambahan', function () {
    $sale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);

    $this->action->execute($sale, ['method' => 'transfer', 'amount' => '40000.00']);

    $collectionCount = CashEntry::query()
        ->where('reference_type', $sale->getMorphClass())
        ->where('reference_id', $sale->id)
        ->where('entry_type', CashEntryType::ReceivableCollection->value)
        ->count();

    expect($collectionCount)->toBe(0);
});

it('outstandingReceivableForPartner menjumlahkan sisa piutang lintas penjualan pelanggan yang sama', function () {
    $firstSale = makeSaleWithReceivable($this->branch, $this->shift, $this->partner, $this->product);

    $secondProduct = Product::factory()->create();
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $secondProduct, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));
    $secondSale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id, 'partner_id' => $this->partner->id]);
    $secondSale->items()->create(['product_id' => $secondProduct->id, 'quantity' => '10.0000', 'unit_price' => '15000.00']);
    $secondSale->payments()->create(['method' => 'cash', 'amount' => '100000.00']);
    $secondSale = app(FinalizeSaleAction::class)->execute($secondSale);

    $this->action->execute($firstSale, ['method' => 'cash', 'amount' => '30000.00']);

    // firstSale: 100.000 - 30.000 = 70.000 sisa. secondSale: 150.000 - 100.000 = 50.000 sisa.
    $outstanding = Sale::outstandingReceivableForPartner($this->branch->id, $this->partner->id);

    expect($outstanding)->toEqual('120000.00')
        ->and($secondSale->remainingReceivable())->toEqual('50000.00');
});
