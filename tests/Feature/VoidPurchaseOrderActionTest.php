<?php

declare(strict_types=1);

use App\Application\Actions\FinalizePurchaseOrderAction;
use App\Application\Actions\VoidPurchaseOrderAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\PartnerType;
use App\Domain\Shared\Exceptions\DocumentStateException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizePurchaseOrderAction::class);
    $this->voidAction = app(VoidPurchaseOrderAction::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create(['partner_type' => PartnerType::Supplier]);
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['create_purchase_order', 'void_purchase_order']));
});

afterEach(function () {
    DB::rollBack();
});

it('void mengubah dokumen final menjadi void dengan alasan tercatat', function () {
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $po->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_price' => '20000.00']);

    $finalized = $this->finalizeAction->execute($po);

    $voided = $this->voidAction->execute($finalized, 'Pemasok membatalkan kesepakatan');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($voided->void_reason)->toBe('Pemasok membatalkan kesepakatan');
});

it('menolak void tanpa alasan', function () {
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $po->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000', 'unit_price' => '20000.00']);

    $finalized = $this->finalizeAction->execute($po);

    expect(fn () => $this->voidAction->execute($finalized, ''))
        ->toThrow(DocumentStateException::class);
});

it('menolak void dokumen yang masih draft', function () {
    $po = PurchaseOrder::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);

    expect(fn () => $this->voidAction->execute($po, 'Alasan apapun'))
        ->toThrow(DocumentStateException::class);
});
