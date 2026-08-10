<?php

declare(strict_types=1);

use App\Application\Actions\ApproveStockAdjustmentAction;
use App\Application\Actions\FinalizeStockAdjustmentAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeStockAdjustmentAction::class);
    $this->approveAction = app(ApproveStockAdjustmentAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('menyetujui permintaan tertunda lalu benar-benar menerapkan ke ledger', function () {
    $requester = makeTestUser(['perform_adjustment']);
    $this->actingAs($requester);

    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);
    $adjustment->lines()->create([
        'product_id' => $this->product->id,
        'direction' => 'increase',
        'quantity' => '100.0000',
        'unit_cost' => '100000.00',
        'reason' => 'Uji otomatis',
    ]);

    $pending = $this->finalizeAction->execute($adjustment);
    expect($pending->state)->toBe(DocumentState::Draft);

    $approver = makeTestUser(['approve_stock_adjustment']);
    $this->actingAs($approver);

    $approved = $this->approveAction->execute($pending);

    expect($approved->state)->toBe(DocumentState::Final)
        ->and($approved->document_number)->toContain('ADJ')
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('100.0000');

    $approval = Approval::query()
        ->where('approvable_type', $adjustment->getMorphClass())
        ->where('approvable_id', $adjustment->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->approver_id)->toBe($approver->id);
});

it('menolak bila tidak ada permintaan approval tertunda', function () {
    $this->actingAs(makeTestUser(['approve_stock_adjustment']));
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);

    expect(fn () => $this->approveAction->execute($adjustment))
        ->toThrow(ApprovalException::class);
});
