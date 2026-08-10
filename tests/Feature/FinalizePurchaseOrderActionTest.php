<?php

declare(strict_types=1);

use App\Application\Actions\FinalizePurchaseOrderAction;
use App\Domain\Procurement\Exceptions\PurchaseOrderValidationException;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Infrastructure\Persistence\Models\Role;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(FinalizePurchaseOrderAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create(['partner_type' => PartnerType::Supplier]);
    $this->product = Product::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

function makePurchaseOrderWithLine(Branch $branch, Partner $partner, Product $product, string $qty, string $unitPrice): PurchaseOrder
{
    $po = PurchaseOrder::factory()->create(['branch_id' => $branch->id, 'partner_id' => $partner->id]);
    $po->lines()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_price' => $unitPrice,
    ]);

    return $po;
}

it('menolak dokumen tanpa baris', function () {
    $this->actingAs(makeTestUser(['create_purchase_order']));
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);

    expect(fn () => $this->action->execute($po))
        ->toThrow(PurchaseOrderValidationException::class);
});

it('menolak pemasok yang bertipe customer murni', function () {
    $this->actingAs(makeTestUser(['create_purchase_order']));
    $customer = Partner::factory()->create(['partner_type' => PartnerType::Customer]);
    $po = makePurchaseOrderWithLine($this->branch, $customer, $this->product, '10.0000', '50000.00');

    expect(fn () => $this->action->execute($po))
        ->toThrow(PurchaseOrderValidationException::class);
});

it('di bawah ambang TH4 langsung diterapkan dan difinalisasi', function () {
    $this->actingAs(makeTestUser(['create_purchase_order']));
    $po = makePurchaseOrderWithLine($this->branch, $this->supplier, $this->product, '10.0000', '50000.00');

    $result = $this->action->execute($po);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('PO')
        ->and((string) $result->total_amount)->toEqual('500000.00');
});

it('melebihi ambang TH4 — tetap draft, membuat Approval tertunda', function () {
    $user = makeTestUser(['create_purchase_order']);
    $this->actingAs($user);
    // 100 x Rp200.000 = Rp20.000.000 > TH4 (Rp10.000.000 default)
    $po = makePurchaseOrderWithLine($this->branch, $this->supplier, $this->product, '100.0000', '200000.00');

    $result = $this->action->execute($po);

    expect($result->state)->toBe(DocumentState::Draft)
        ->and($result->document_number)->toBeNull();

    $approval = Approval::query()
        ->where('approvable_type', $po->getMorphClass())
        ->where('approvable_id', $po->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->requested_by)->toBe($user->id);
});

it('Owner dikecualikan dari TH4 — diterapkan langsung meski melebihi ambang', function () {
    $owner = makeTestUser(['create_purchase_order']);
    Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $po = makePurchaseOrderWithLine($this->branch, $this->supplier, $this->product, '100.0000', '200000.00');

    $result = $this->action->execute($po);

    expect($result->state)->toBe(DocumentState::Final);
});

it('total_amount dijumlahkan dari seluruh baris, bukan hanya baris pertama', function () {
    $this->actingAs(makeTestUser(['create_purchase_order']));
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $po->lines()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_price' => '10000.00']);
    $secondProduct = Product::factory()->create();
    $po->lines()->create(['product_id' => $secondProduct->id, 'quantity' => '3.0000', 'unit_price' => '5000.00']);

    $result = $this->action->execute($po->fresh());

    // (2 x 10.000) + (3 x 5.000) = 20.000 + 15.000 = 35.000
    expect((string) $result->total_amount)->toEqual('35000.00');
});
