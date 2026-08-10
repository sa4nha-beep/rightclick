<?php

declare(strict_types=1);

use App\Application\Actions\ApproveSaleDiscountAction;
use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Service;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeSaleAction::class);
    $this->approveAction = app(ApproveSaleDiscountAction::class);
    $this->branch = Branch::factory()->create();
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

it('menyetujui approval tertunda lalu menerapkan ke ledger dan memfinalisasi', function () {
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_shift_id' => $this->shift->id,
        'discount_amount' => '150000.00',
    ]);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '350000.00']);

    $pending = $this->finalizeAction->execute($sale);
    expect($pending->state)->toBe(DocumentState::Draft);

    $approved = $this->approveAction->execute($pending);

    expect($approved->state)->toBe(DocumentState::Final)
        ->and($approved->document_number)->toContain('SAL');

    $approval = Approval::query()
        ->where('approvable_type', $sale->getMorphClass())
        ->where('approvable_id', $sale->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Approved);
});

it('konsumsi FIFO tetap berjalan untuk baris produk setelah approval', function () {
    $product = Product::factory()->create();
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_shift_id' => $this->shift->id,
        'discount_amount' => '150000.00',
    ]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '5.0000', 'unit_price' => '50000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '100000.00']);

    $pending = $this->finalizeAction->execute($sale);
    $approved = $this->approveAction->execute($pending);

    expect($approved->state)->toBe(DocumentState::Final)
        ->and(app(StockLedgerService::class)->availableQuantity($this->branch, $product))->toEqual('5.0000');
});

it('menolak approve bila tidak ada Approval tertunda', function () {
    $service = Service::factory()->create(['price' => '10000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '10000.00']);

    expect(fn () => $this->approveAction->execute($sale))->toThrow(ApprovalException::class);
});
