<?php

declare(strict_types=1);

use App\Application\Actions\CloseCashierShiftAction;
use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sales\Exceptions\CashierShiftException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\DocumentStateException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->closeAction = app(CloseCashierShiftAction::class);
    $this->branch = Branch::factory()->create();
    $this->user = makeTestUser(['close_cashier_shift', 'create_sale']);
    $this->actingAs($this->user);
});

afterEach(function () {
    DB::rollBack();
});

/**
 * Bagian AC-16 asli (T4.2: "hitung per pecahan") — helper ringkas untuk
 * merepresentasikan satu angka agregat sebagai SATU baris pecahan, cukup
 * untuk test yang tidak menguji breakdown itu sendiri (lihat test khusus
 * "hitung kas per pecahan" di bawah).
 *
 * @return array<int, array{denomination: string, quantity: int}>
 */
function denominationRow(string $amount): array
{
    if (bccomp($amount, '0', 2) === 0) {
        return [['denomination' => '1000.00', 'quantity' => 0]];
    }

    return [['denomination' => $amount, 'quantity' => 1]];
}

it('menutup shift tanpa penjualan — kas fisik sama dengan kas awal (variance 0)', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id, 'opening_cash' => '500000.00']);

    $result = $this->closeAction->execute($shift, denominationRow('500000.00'));

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('SFT')
        ->and((string) $result->closing_cash_expected)->toEqual('500000.00')
        ->and((string) $result->variance)->toEqual('0.00');
});

it('kas diharapkan dihitung dari pembayaran tunai penjualan FINAL milik shift ini saja', function () {
    $product = Product::factory()->create();
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id, 'opening_cash' => '200000.00']);

    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $product, '10.0000', '5000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '2.0000', 'unit_price' => '9000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '18000.00']);
    app(FinalizeSaleAction::class)->execute($sale);

    // Sale draft (belum final) tidak ikut terhitung.
    Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id])
        ->payments()->create(['method' => 'cash', 'amount' => '999999.00']);

    $result = $this->closeAction->execute($shift, denominationRow('218000.00'));

    expect((string) $result->closing_cash_expected)->toEqual('218000.00')
        ->and((string) $result->variance)->toEqual('0.00');
});

it('mencatat selisih bila kas fisik tidak sama dengan yang diharapkan', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id, 'opening_cash' => '300000.00']);

    $result = $this->closeAction->execute($shift, denominationRow('295000.00'));

    expect((string) $result->variance)->toEqual('-5000.00');
});

it('menolak menutup shift yang sudah tidak terbuka', function () {
    $shift = CashierShift::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->user->id,
        'state' => DocumentState::Final,
        'finalized_at' => now(),
    ]);

    expect(fn () => $this->closeAction->execute($shift, denominationRow('100000.00')))->toThrow(CashierShiftException::class);
});

it('AC-16 — begitu final, field kas tidak bisa diedit langsung tanpa void', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id, 'opening_cash' => '100000.00']);
    $final = $this->closeAction->execute($shift, denominationRow('100000.00'));

    expect(fn () => $final->update(['closing_cash_counted' => '999999.00']))->toThrow(DocumentStateException::class);
});

it('bagian AC-16 asli — hitung kas per pecahan, closing_cash_counted dijumlahkan dari baris breakdown', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id, 'opening_cash' => '0']);

    $result = $this->closeAction->execute($shift, [
        ['denomination' => '100000.00', 'quantity' => 3],
        ['denomination' => '50000.00', 'quantity' => 2],
        ['denomination' => '5000.00', 'quantity' => 4],
    ]);

    // 3*100.000 + 2*50.000 + 4*5.000 = 300.000 + 100.000 + 20.000 = 420.000
    expect((string) $result->closing_cash_counted)->toEqual('420000.00')
        ->and($result->counts)->toHaveCount(3);

    $hundredK = $result->counts->firstWhere('denomination', '100000.00');
    expect((string) $hundredK->quantity)->toEqual('3')
        ->and((string) $hundredK->subtotal)->toEqual('300000.00');
});

it('bagian AC-16 asli — pecahan nol atau negatif ditolak', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);

    expect(fn () => $this->closeAction->execute($shift, [['denomination' => '0', 'quantity' => 1]]))
        ->toThrow(CashierShiftException::class);
});

it('bagian AC-16 asli — jumlah lembar negatif ditolak', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);

    expect(fn () => $this->closeAction->execute($shift, [['denomination' => '10000.00', 'quantity' => -1]]))
        ->toThrow(CashierShiftException::class);
});

it('bagian AC-16 asli — breakdown pecahan kosong ditolak', function () {
    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $this->user->id]);

    expect(fn () => $this->closeAction->execute($shift, []))->toThrow(CashierShiftException::class);
});
