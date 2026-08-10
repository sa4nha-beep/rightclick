<?php

declare(strict_types=1);

use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Service;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Pos\Livewire\PosTerminal;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    $this->user = makeTestUser(['create_sale', 'close_cashier_shift']);
    $this->actingAs($this->user);
    app(BranchContext::class)->set($this->user->default_branch_id);
    $this->branch = Branch::find($this->user->default_branch_id);
});

afterEach(function () {
    DB::rollBack();
});

it('menolak akses tanpa permission create_sale', function () {
    $this->actingAs(makeTestUser());

    // Livewire::test() menonaktifkan exception handler Laravel untuk
    // permintaan awal (InitialRender::makeInitialRequest) — abort(403) di
    // mount() TIDAK terlempar sebagai exception PHP di proses test,
    // melainkan dirender sebagai halaman error HTML dengan instance null
    // (ditemukan T4.3 saat debugging pola serupa pada SaleReturnForm).
    $test = Livewire::test(PosTerminal::class);

    expect($test->instance())->toBeNull();
});

it('menampilkan form buka shift bila belum ada shift terbuka', function () {
    Livewire::test(PosTerminal::class)
        ->assertSet('activeShift', null)
        ->assertSee('Buka Shift');
});

it('openShift membuka shift baru untuk kasir yang login', function () {
    Livewire::test(PosTerminal::class)
        ->set('openingCashInput', '500000')
        ->call('openShift')
        ->assertSet('shiftError', null);

    $this->assertDatabaseHas('cashier_shifts', [
        'cashier_id' => $this->user->id,
        'opening_cash' => 500000,
        'state' => 'draft',
    ]);
});

it('POS-05 — produk berstok nol tetap tampil di katalog dengan qty_on_hand nol', function () {
    $product = Product::factory()->create(['name' => 'Produk Kosong']);

    $component = Livewire::test(PosTerminal::class);
    $products = $component->instance()->products();

    $found = $products->firstWhere('id', $product->id);

    expect($found)->not->toBeNull()
        ->and((string) $found->qty_on_hand)->toEqual('0.0000');
});

it('addProduct menambah baris ke keranjang, menambah kuantitas bila produk sama ditambah lagi', function () {
    $product = Product::factory()->create(['selling_price' => '20000.00']);

    Livewire::test(PosTerminal::class)
        ->call('addProduct', $product->id)
        ->assertCount('cart', 1)
        ->call('addProduct', $product->id)
        ->assertCount('cart', 1)
        ->assertSet('cart.0.quantity', '2.0000');
});

it('checkout berhasil — sale final, stok berkurang, keranjang dikosongkan', function () {
    $product = Product::factory()->create(['selling_price' => '20000.00']);
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $test = Livewire::test(PosTerminal::class)
        ->call('openShift')
        ->call('addProduct', $product->id)
        ->set('payments.0.amount', '20000');

    $test->call('checkout')
        ->assertSet('checkoutError', null)
        ->assertCount('cart', 0);

    $sale = Sale::query()->latest('created_at')->first();
    expect($sale->state)->toBe(DocumentState::Final)
        ->and($sale->payment_status)->toBe(PaymentStatus::Paid);

    expect(app(StockLedgerService::class)->availableQuantity($this->branch, $product))->toEqual('9.0000');
});

it('checkout menolak stok tidak cukup — pesan error tampil, keranjang TETAP utuh', function () {
    $product = Product::factory()->create(['selling_price' => '20000.00']);
    // Tanpa stok sama sekali.

    $test = Livewire::test(PosTerminal::class)
        ->call('openShift')
        ->call('addProduct', $product->id)
        ->set('payments.0.amount', '20000');

    $test->call('checkout')
        ->assertCount('cart', 1);

    expect($test->get('checkoutError'))->not->toBeNull();

    $this->assertDatabaseMissing('sales', ['branch_id' => $this->branch->id]);
});

it('checkout dengan diskon melebihi TH1 — draft menunggu approval, stok belum berkurang', function () {
    $service = Service::factory()->create(['price' => '500000.00']);

    $test = Livewire::test(PosTerminal::class)
        ->call('openShift')
        ->call('addService', $service->id)
        ->set('discountAmount', '150000')
        ->set('payments.0.amount', '350000');

    $test->call('checkout')
        ->assertSet('checkoutError', null);

    expect($test->get('pendingApprovalMessage'))->not->toBeNull();

    $sale = Sale::query()->latest('created_at')->first();
    expect($sale->state)->toBe(DocumentState::Draft);

    $approval = Approval::query()->where('approvable_id', $sale->id)->sole();
    expect($approval->status)->toBe(ApprovalStatus::Pending);
});
