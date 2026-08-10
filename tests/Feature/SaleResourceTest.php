<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\Sales\Pages\CreateSale;
use App\Presentation\Filament\Resources\Sales\SaleResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar penjualan dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_sales']));

    $this->get(SaleResource::getUrl('index'))->assertOk();
});

it('halaman daftar penjualan ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(SaleResource::getUrl('index'))->assertForbidden();
});

it('form Buat Penjualan menyimpan header, baris, dan pembayaran lewat Livewire', function () {
    $user = makeTestUser(['create_sale', 'view_sales']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);

    $shift = CashierShift::factory()->create([
        'branch_id' => $user->default_branch_id,
        'cashier_id' => $user->id,
    ]);
    $product = Product::factory()->create(['selling_price' => '20000.00']);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'cashier_shift_id' => $shift->id,
            'discount_amount' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 20000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 40000,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('sales', [
        'cashier_shift_id' => $shift->id,
        'state' => 'draft',
    ]);
    $this->assertDatabaseHas('sale_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('sale_payments', [
        'method' => 'cash',
        'amount' => 40000,
    ]);
});
