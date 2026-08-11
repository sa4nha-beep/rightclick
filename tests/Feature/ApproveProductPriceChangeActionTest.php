<?php

declare(strict_types=1);

use App\Application\Actions\ApproveProductPriceChangeAction;
use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(ApproveProductPriceChangeAction::class);
    $this->user = makeTestUser(['manage_product_prices']);
    $this->actingAs($this->user);
});

afterEach(function () {
    DB::rollBack();
});

it('menyetujui permintaan tertunda dan menerapkan harga yang diajukan', function () {
    $product = Product::factory()->create(['selling_price' => '100000.00']);
    $approval = app(ApprovalService::class)->request($product, payload: [
        'proposed_selling_price' => '120000.00',
        'previous_selling_price' => '100000.00',
    ]);

    $result = $this->action->execute($approval);

    expect((string) $result->selling_price)->toEqual('120000.00')
        ->and($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('menolak Approval yang bukan untuk Product', function () {
    $branch = Branch::factory()->create();
    $approval = app(ApprovalService::class)->request($branch, payload: ['proposed_selling_price' => '1000.00']);

    expect(fn () => $this->action->execute($approval))->toThrow(ApprovalException::class);
});

it('menolak Approval tanpa proposed_selling_price di payload', function () {
    $product = Product::factory()->create(['selling_price' => '100000.00']);
    $approval = app(ApprovalService::class)->request($product);

    expect(fn () => $this->action->execute($approval))->toThrow(ApprovalException::class);
});

it('menolak Approval yang sudah diputuskan sebelumnya', function () {
    $product = Product::factory()->create(['selling_price' => '100000.00']);
    $approval = app(ApprovalService::class)->request($product, payload: ['proposed_selling_price' => '120000.00']);

    $this->action->execute($approval);

    expect(fn () => $this->action->execute($approval->fresh()))->toThrow(ApprovalException::class);
});
