<?php

declare(strict_types=1);

use App\Application\Actions\ApprovePurchaseOrderAction;
use App\Application\Actions\FinalizePurchaseOrderAction;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\PartnerType;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizePurchaseOrderAction::class);
    $this->approveAction = app(ApprovePurchaseOrderAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create(['partner_type' => PartnerType::Supplier]);
    $this->product = Product::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('menyetujui permintaan tertunda lalu benar-benar memfinalisasi dokumen', function () {
    $requester = makeTestUser(['create_purchase_order']);
    $this->actingAs($requester);

    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $po->lines()->create(['product_id' => $this->product->id, 'quantity' => '100.0000', 'unit_price' => '200000.00']);

    $pending = $this->finalizeAction->execute($po);
    expect($pending->state)->toBe(DocumentState::Draft);

    $approver = makeTestUser(['approve_purchase_order']);
    $this->actingAs($approver);

    $approved = $this->approveAction->execute($pending);

    expect($approved->state)->toBe(DocumentState::Final)
        ->and($approved->document_number)->toContain('PO')
        ->and((string) $approved->total_amount)->toEqual('20000000.00');

    $approval = Approval::query()
        ->where('approvable_type', $po->getMorphClass())
        ->where('approvable_id', $po->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->approver_id)->toBe($approver->id);
});

it('menolak bila tidak ada permintaan approval tertunda', function () {
    $this->actingAs(makeTestUser(['approve_purchase_order']));
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);

    expect(fn () => $this->approveAction->execute($po))
        ->toThrow(ApprovalException::class);
});
