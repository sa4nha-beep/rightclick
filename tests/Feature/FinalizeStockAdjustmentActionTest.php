<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeStockAdjustmentAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(FinalizeStockAdjustmentAction::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

function makeAdjustmentWithLine(Branch $branch, Product $product, string $qty, string $unitCost, string $direction = 'increase'): StockAdjustment
{
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $branch->id]);
    $adjustment->lines()->create([
        'product_id' => $product->id,
        'direction' => $direction,
        'quantity' => $qty,
        'unit_cost' => $unitCost,
        'reason' => 'Uji otomatis',
    ]);

    return $adjustment;
}

it('menolak dokumen tanpa baris', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);

    expect(fn () => $this->action->execute($adjustment))
        ->toThrow(StockDocumentValidationException::class);
});

it('menolak baris naik tanpa unit_cost', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));
    $adjustment = makeAdjustmentWithLine($this->branch, $this->product, '2.0000', '0');
    $adjustment->lines()->update(['unit_cost' => null]);

    expect(fn () => $this->action->execute($adjustment))
        ->toThrow(StockDocumentValidationException::class);
});

it('di bawah ambang TH3 langsung diterapkan dan difinalisasi', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));
    $adjustment = makeAdjustmentWithLine($this->branch, $this->product, '2.0000', '100000.00');

    $result = $this->action->execute($adjustment);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('ADJ');
});

it('melebihi ambang TH3 per dokumen — tetap draft, membuat Approval tertunda (PT15)', function () {
    $user = makeTestUser(['perform_adjustment']);
    $this->actingAs($user);
    // 100 x Rp100.000 = Rp10.000.000 > TH3 (Rp5.000.000 default)
    $adjustment = makeAdjustmentWithLine($this->branch, $this->product, '100.0000', '100000.00');

    $result = $this->action->execute($adjustment);

    expect($result->state)->toBe(DocumentState::Draft)
        ->and($result->document_number)->toBeNull();

    $approval = Approval::query()
        ->where('approvable_type', $adjustment->getMorphClass())
        ->where('approvable_id', $adjustment->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->requested_by)->toBe($user->id);
});

it('Owner dikecualikan dari TH3/TH3b — diterapkan langsung meski melebihi ambang', function () {
    $owner = makeTestUser(['perform_adjustment']);
    Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $adjustment = makeAdjustmentWithLine($this->branch, $this->product, '100.0000', '100000.00');

    $result = $this->action->execute($adjustment);

    expect($result->state)->toBe(DocumentState::Final);
});

it('PT15 — kumulatif bulanan melebihi TH3b meski dokumen ini sendiri di bawah TH3', function () {
    $user = makeTestUser(['perform_adjustment']);
    $this->actingAs($user);

    // 3 dokumen x Rp4.000.000 (di bawah TH3 Rp5.000.000) = Rp12.000.000 diterapkan.
    foreach (range(1, 3) as $i) {
        $product = Product::factory()->create();
        $adjustment = makeAdjustmentWithLine($this->branch, $product, '40.0000', '100000.00');
        $result = $this->action->execute($adjustment);
        expect($result->state)->toBe(DocumentState::Final);
    }

    // Dokumen ke-4: Rp4.000.000 lagi (masih di bawah TH3 sendiri) tapi
    // proyeksi kumulatif Rp16.000.000 > TH3b (Rp15.000.000 default) — wajib approval.
    $fourthProduct = Product::factory()->create();
    $fourth = makeAdjustmentWithLine($this->branch, $fourthProduct, '40.0000', '100000.00');
    $result = $this->action->execute($fourth);

    expect($result->state)->toBe(DocumentState::Draft);

    $approval = Approval::query()
        ->where('approvable_type', $fourth->getMorphClass())
        ->where('approvable_id', $fourth->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending);
});

it('baris turun memakai rata-rata tertimbang batch tersedia untuk estimasi ambang', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));

    DB::transaction(function () {
        app(StockLedgerService::class)->receive(
            $this->branch,
            $this->product,
            '50.0000',
            '10000.00',
            now(),
            Branch::factory()->create(),
            StockMutationType::Receipt,
        );
    });

    $adjustment = makeAdjustmentWithLine($this->branch, $this->product, '5.0000', '0', StockAdjustmentDirection::Decrease->value);

    $result = $this->action->execute($adjustment);

    expect($result->state)->toBe(DocumentState::Final);
});

it('T3.7 — baris naik produk serial wajib mengisi serial number sejumlah kuantitas', function () {
    $this->actingAs(makeTestUser(['perform_adjustment']));
    $serialized = Product::factory()->create(['is_serialized' => true]);
    $adjustment = StockAdjustment::factory()->create(['branch_id' => $this->branch->id]);
    $adjustment->lines()->create([
        'product_id' => $serialized->id,
        'direction' => 'increase',
        'quantity' => '2.0000',
        'unit_cost' => '500000.00',
        'reason' => 'Uji otomatis',
        'serial_numbers' => ['SN-X'],
    ]);

    expect(fn () => $this->action->execute($adjustment))
        ->toThrow(StockDocumentValidationException::class);

    $adjustment->lines()->first()->update(['serial_numbers' => ['SN-X', 'SN-Y']]);

    $result = $this->action->execute($adjustment->fresh());
    expect($result->state)->toBe(DocumentState::Final);
});
