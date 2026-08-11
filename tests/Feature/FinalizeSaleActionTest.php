<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Service;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(FinalizeSaleAction::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = makeTestUser(['create_sale']);
    $this->actingAs($this->user);
    $this->shift = CashierShift::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->user->id,
    ]);
});

afterEach(function () {
    DB::rollBack();
});

function makeSaleWithProductLine(Branch $branch, CashierShift $shift, Product $product, string $qty, string $unitPrice): Sale
{
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_price' => $unitPrice,
    ]);

    return $sale;
}

it('menolak penjualan tanpa baris', function () {
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);

    expect(fn () => $this->action->execute($sale))->toThrow(SaleValidationException::class);
});

it('menolak bila shift terkait sudah tidak terbuka', function () {
    $this->shift->update(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');

    expect(fn () => $this->action->execute($sale))->toThrow(SaleValidationException::class);
});

it('menolak DP tanpa partner — walk-in wajib lunas', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '2.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '10000.00']);

    expect(fn () => $this->action->execute($sale))->toThrow(SaleValidationException::class);
});

it('menolak pembayaran nol pada penjualan bertotal positif', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');

    expect(fn () => $this->action->execute($sale))->toThrow(SaleValidationException::class);
});

it('menolak pembayaran yang melebihi total penjualan', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '20000.00']);

    expect(fn () => $this->action->execute($sale))->toThrow(SaleValidationException::class);
});

it('DP diizinkan bila partner terisi — payment_status Partial, balance_due terhitung', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $partner = Partner::factory()->create();
    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '2.0000', '15000.00');
    $sale->update(['partner_id' => $partner->id]);
    $sale->payments()->create(['method' => 'cash', 'amount' => '10000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->payment_status)->toBe(PaymentStatus::Partial)
        ->and((string) $result->amount_paid)->toEqual('10000.00')
        ->and((string) $result->balance_due)->toEqual('20000.00');
});

it('payment_status Paid saat pembayaran pas dengan total', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '15000.00']);

    $result = $this->action->execute($sale);

    expect($result->payment_status)->toBe(PaymentStatus::Paid)
        ->and((string) $result->balance_due)->toEqual('0.00');
});

it('finalisasi berhasil — FIFO consume, COGS presisi, nomor dokumen SAL', function () {
    DB::transaction(function () {
        app(StockLedgerService::class)->receive(
            $this->branch, $this->product, '5.0000', '8000.00', now()->subDay(), Branch::factory()->create(),
            StockMutationType::Receipt,
        );
        app(StockLedgerService::class)->receive(
            $this->branch, $this->product, '5.0000', '12000.00', now(), Branch::factory()->create(),
            StockMutationType::Receipt,
        );
    });

    // Konsumsi 7 unit: 5 dari batch Rp8.000 + 2 dari batch Rp12.000 (AC-08 FIFO).
    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '7.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '105000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('SAL')
        ->and((string) $result->subtotal)->toEqual('105000.00')
        ->and((string) $result->total_amount)->toEqual('105000.00');

    $line = $result->items->first();
    // (5*8000 + 2*12000) / 7 = 64000/7 = 9142.857... dibulatkan skala 2.
    expect((string) $line->unit_cost_snapshot)->toEqual(bcdiv('64000', '7', 2));

    expect(app(StockLedgerService::class)->availableQuantity($this->branch, $this->product))->toEqual('3.0000');
});

it('diskon total dikurangi dari subtotal', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');
    $sale->update(['discount_amount' => '5000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '10000.00']);

    $result = $this->action->execute($sale);

    expect((string) $result->subtotal)->toEqual('15000.00')
        ->and((string) $result->total_amount)->toEqual('10000.00');
});

it('baris jasa tidak menyentuh stock ledger', function () {
    $service = Service::factory()->create(['price' => '50000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '50000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '50000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->items->first()->unit_cost_snapshot)->toBeNull();
});

it('TH1 — diskon Kasir di bawah ambang langsung difinalisasi', function () {
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id, 'discount_amount' => '50000.00']);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '450000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final);
});

it('TH1 — diskon Kasir melebihi ambang tetap draft, membuat Approval tertunda (AP-01)', function () {
    // Rp150.000 > TH1 (Rp100.000 default).
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id, 'discount_amount' => '150000.00']);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '350000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Draft)
        ->and($result->document_number)->toBeNull();

    $approval = Approval::query()
        ->where('approvable_type', $sale->getMorphClass())
        ->where('approvable_id', $sale->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->requested_by)->toBe($this->user->id);
});

it('TH2 — Admin boleh diskon di atas TH1 tanpa approval selama masih di bawah TH2', function () {
    $admin = makeTestUser(['create_sale']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $admin->id]);
    // Rp200.000 > TH1 (100rb) tapi < TH2 (300rb default).
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id, 'discount_amount' => '200000.00']);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '300000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final);
});

it('TH2 — diskon Admin melebihi ambang tetap draft menunggu approval', function () {
    $admin = makeTestUser(['create_sale']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $admin->id]);
    // Rp350.000 > TH2 (300rb default).
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id, 'discount_amount' => '350000.00']);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '150000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Draft);
});

it('Owner dikecualikan dari TH1/TH2 — diskon besar tetap langsung difinalisasi', function () {
    $owner = makeTestUser(['create_sale']);
    Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $owner->id]);
    $service = Service::factory()->create(['price' => '1000000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id, 'discount_amount' => '500000.00']);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '1000000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '500000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final);
});

it('T5.4 — pembayaran tunai menerbitkan CashEntry kas masuk yang merujuk Sale', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '15000.00']);

    $result = $this->action->execute($sale);

    $entry = CashEntry::query()
        ->where('reference_type', $result->getMorphClass())
        ->where('reference_id', $result->id)
        ->sole();

    expect($entry->entry_type)->toBe(CashEntryType::SalePayment)
        ->and((string) $entry->amount)->toEqual('15000.00');
});

it('T4.9/UT5 — produk is_serialized tanpa serial number ditolak, stok tidak tersentuh', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $serialized, '2.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $serialized, '2.0000', '15000.00');
    $sale->payments()->create(['method' => 'cash', 'amount' => '30000.00']);

    expect(fn () => $this->action->execute($sale))->toThrow(StockDocumentValidationException::class);
    expect(app(StockLedgerService::class)->availableQuantity($this->branch, $serialized))->toEqual('2.0000');
});

it('T4.9/UT5 — produk is_serialized dengan jumlah serial tidak sesuai quantity ditolak', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $serialized, '2.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $sale->items()->create([
        'product_id' => $serialized->id,
        'quantity' => '2.0000',
        'unit_price' => '15000.00',
        'serial_numbers' => ['SN-001'],
    ]);
    $sale->payments()->create(['method' => 'cash', 'amount' => '30000.00']);

    expect(fn () => $this->action->execute($sale))->toThrow(StockDocumentValidationException::class);
});

it('T4.9/UT5 — finalisasi berhasil saat serial number lengkap dan sesuai quantity', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $serialized, '2.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $sale->items()->create([
        'product_id' => $serialized->id,
        'quantity' => '2.0000',
        'unit_price' => '15000.00',
        'serial_numbers' => ['SN-001', 'SN-002'],
    ]);
    $sale->payments()->create(['method' => 'cash', 'amount' => '30000.00']);

    $result = $this->action->execute($sale);

    expect($result->state)->toBe(DocumentState::Final);
});

it('T5.4 — pembayaran non-tunai TIDAK menerbitkan CashEntry', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(),
        StockMutationType::Receipt,
    ));

    $sale = makeSaleWithProductLine($this->branch, $this->shift, $this->product, '1.0000', '15000.00');
    $sale->payments()->create(['method' => 'card', 'amount' => '15000.00']);

    $result = $this->action->execute($sale);

    expect(CashEntry::query()->where('reference_type', $result->getMorphClass())->where('reference_id', $result->id)->exists())
        ->toBeFalse();
});
