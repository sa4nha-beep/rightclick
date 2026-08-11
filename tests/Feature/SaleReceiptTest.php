<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

function makeFinalizedSaleForReceipt(): Sale
{
    $user = makeTestUser(['create_sale', 'view_sales']);
    test()->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);

    $branch = Branch::find($user->default_branch_id);
    $product = Product::factory()->create(['selling_price' => '20000.00']);

    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $branch, $product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $shift = CashierShift::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '2.0000', 'unit_price' => '20000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '40000.00']);

    return app(FinalizeSaleAction::class)->execute($sale);
}

it('AC-14/R13 — nota tidak mengandung PPN dalam bentuk apa pun', function () {
    $sale = makeFinalizedSaleForReceipt();

    $response = test()->get(route('pos.receipt', $sale));

    $response->assertOk();
    $content = $response->getContent();

    // UT17 (T4.11 asli, sebelumnya belum tertutup) — logo WAJIB tampil di
    // nota, varian 1-Color-Positive (hitam solid, aman untuk printer
    // thermal), BUKAN varian cyan HK-LOGO-01 yang dipakai topbar/POS.
    expect($content)->toContain('HK-LOGO-04-1Color-Positive.svg')
        ->not->toContain('HK-LOGO-01-Primary-Horizontal.svg');

    expect($content)->not->toContain('PPN')
        ->not->toContain('Ppn')
        ->not->toContain('ppn')
        ->not->toContain('Pajak')
        ->not->toContain('pajak')
        ->not->toContain('VAT')
        ->and($content)->toContain($sale->document_number)
        ->toContain('40.000')
        ->toContain('20.000');
});

it('menolak nota untuk penjualan yang masih draft (404)', function () {
    $user = makeTestUser(['create_sale', 'view_sales']);
    test()->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);

    $shift = CashierShift::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_shift_id' => $shift->id]);

    test()->get(route('pos.receipt', $sale))->assertNotFound();
});

it('menolak akses bagi pengguna tanpa permission view_sales', function () {
    $sale = makeFinalizedSaleForReceipt();
    test()->actingAs(makeTestUser());

    test()->get(route('pos.receipt', $sale))->assertForbidden();
});

it('nota bisa diakses berulang kali (cetak ulang, NT-05) tanpa mengubah data', function () {
    $sale = makeFinalizedSaleForReceipt();

    test()->get(route('pos.receipt', $sale))->assertOk();
    test()->get(route('pos.receipt', $sale))->assertOk();

    expect($sale->fresh()->state)->toBe(DocumentState::Final);
});

it('T4.11/UT14 — cetak pertama TIDAK bertanda SALINAN, tapi tetap tercatat di audit log', function () {
    $sale = makeFinalizedSaleForReceipt();

    $response = test()->get(route('pos.receipt', $sale));

    $response->assertOk();
    expect($response->getContent())->not->toContain('SALINAN');

    $entry = AuditLog::query()
        ->where('model_type', Sale::class)
        ->where('model_id', $sale->id)
        ->where('action', AuditAction::Reprinted)
        ->sole();

    expect($entry->metadata['event'])->toBe('first_print');
});

it('T4.11/UT14 — cetak kedua dan seterusnya bertanda SALINAN dan tercatat sebagai reprint di audit log', function () {
    $sale = makeFinalizedSaleForReceipt();

    test()->get(route('pos.receipt', $sale))->assertOk();
    $response = test()->get(route('pos.receipt', $sale));

    $response->assertOk();
    expect($response->getContent())->toContain('SALINAN');

    // Diurutkan lewat `id` (UUID v7, presisi tinggi), BUKAN `created_at`
    // (kolom `timestamp` presisi detik — dua entri dalam detik yang sama
    // bisa punya `created_at` identik, gotcha yang sama dengan T5.7).
    $entries = AuditLog::query()
        ->where('model_type', Sale::class)
        ->where('model_id', $sale->id)
        ->where('action', AuditAction::Reprinted)
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->metadata['event'])->toBe('first_print')
        ->and($entries->last()->metadata['event'])->toBe('reprint');
});
