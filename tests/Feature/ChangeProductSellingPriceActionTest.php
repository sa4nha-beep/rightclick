<?php

declare(strict_types=1);

use App\Application\Actions\ChangeProductSellingPriceAction;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\StockBatch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * TH5a/TH5b/TH5c (penutupan PT16). Ambang default dari `SettingSeeder`
 * TIDAK diseed di sini (test dibungkus transaksi, tidak menjalankan seeder
 * penuh) — Action jatuh ke default hardcoded (0.10/0.05/true) yang identik
 * dengan nilai seed, jadi angka di test ini sengaja dipilih relatif
 * terhadap default itu.
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(ChangeProductSellingPriceAction::class);
    $this->user = makeTestUser(['manage_product_prices']);
    $this->actingAs($this->user);
});

afterEach(function () {
    DB::rollBack();
});

it('menolak aktor tanpa permission manage_product_prices', function () {
    $this->actingAs(makeTestUser());
    $product = Product::factory()->create(['selling_price' => '10000.00']);

    expect(fn () => $this->action->execute($product, '11000.00'))->toThrow(AuthorizationException::class);

    expect((string) $product->fresh()->selling_price)->toEqual('10000.00');
});

it('tidak melakukan apa pun bila harga baru sama dengan harga lama', function () {
    $product = Product::factory()->create(['selling_price' => '10000.00']);

    $result = $this->action->execute($product, '10000.00');

    expect($result)->toBeInstanceOf(Product::class)
        ->and(Approval::query()->count())->toBe(0);
});

it('di bawah ambang TH5a/TH5b — diterapkan langsung tanpa approval', function () {
    // Naik 5% (< TH5a 10%), turun 3% (< TH5b 5%) — keduanya dalam satu
    // rangkaian assert independen lewat produk terpisah.
    $productUp = Product::factory()->create(['selling_price' => '100000.00']);
    $resultUp = $this->action->execute($productUp, '105000.00');

    expect($resultUp)->toBeInstanceOf(Product::class)
        ->and((string) $resultUp->selling_price)->toEqual('105000.00');

    $productDown = Product::factory()->create(['selling_price' => '100000.00']);
    $resultDown = $this->action->execute($productDown, '97000.00');

    expect($resultDown)->toBeInstanceOf(Product::class)
        ->and((string) $resultDown->selling_price)->toEqual('97000.00');

    expect(Approval::query()->count())->toBe(0);
});

it('TH5a — kenaikan di atas 10% membuat Approval tertunda, harga TIDAK berubah', function () {
    $product = Product::factory()->create(['selling_price' => '100000.00']);

    $result = $this->action->execute($product, '115000.00');

    expect($result)->toBeInstanceOf(Approval::class)
        ->and($result->status)->toBe(ApprovalStatus::Pending)
        ->and($result->payload)->toEqual([
            'proposed_selling_price' => '115000.00',
            'previous_selling_price' => '100000.00',
        ]);

    expect((string) $product->fresh()->selling_price)->toEqual('100000.00');
});

it('TH5b — penurunan di atas 5% membuat Approval tertunda, harga TIDAK berubah', function () {
    $product = Product::factory()->create(['selling_price' => '100000.00']);

    $result = $this->action->execute($product, '90000.00');

    expect($result)->toBeInstanceOf(Approval::class)
        ->and($result->status)->toBe(ApprovalStatus::Pending);

    expect((string) $product->fresh()->selling_price)->toEqual('100000.00');
});

it('TH5c — harga di bawah HPP batch tertua SELALU approval, walau perubahan persentasenya kecil', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['selling_price' => '100000.00']);

    // Batch tertua Rp96.000 (DI ATAS lantai TH5b 5% = Rp95.000), batch
    // termuda lebih murah — HARUS tetap membandingkan ke batch TERTUA
    // (Rp96.000), bukan yang termuda/termurah.
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '96000.00',
        'qty_received' => '5.0000',
        'qty_remaining' => '5.0000',
        'received_at' => now()->subDays(10),
    ]);
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '80000.00',
        'qty_received' => '5.0000',
        'qty_remaining' => '5.0000',
        'received_at' => now()->subDay(),
    ]);

    // Rp95.500: turun hanya 4,5% dari Rp100.000 (di BAWAH TH5b 5% —
    // TIDAK memicu TH5a/TH5b sama sekali) tapi DI BAWAH HPP batch tertua
    // (Rp96.000) — memisahkan murni TH5c dari TH5b, bukan kebetulan
    // keduanya sama-sama terlampaui.
    $result = $this->action->execute($product, '95500.00');

    expect($result)->toBeInstanceOf(Approval::class)
        ->and($result->status)->toBe(ApprovalStatus::Pending);

    expect((string) $product->fresh()->selling_price)->toEqual('100000.00');
});

it('TH5c mengabaikan batch dengan qty_remaining nol', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['selling_price' => '100000.00']);

    // Batch tertua HABIS (qty_remaining 0, HPP Rp99.000) — TIDAK boleh
    // dipakai sebagai acuan HPP. Batch berikutnya yang MASIH tersisa
    // (HPP Rp80.000) yang seharusnya jadi acuan TH5c.
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '99000.00',
        'qty_remaining' => '0.0000',
        'received_at' => now()->subDays(10),
    ]);
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '80000.00',
        'qty_received' => '5.0000',
        'qty_remaining' => '5.0000',
        'received_at' => now()->subDay(),
    ]);

    // Rp97.000: turun 3% dari Rp100.000 (di BAWAH TH5b 5% — tidak memicu
    // TH5a/TH5b), berada DI ATAS HPP batch tersisa (Rp80.000, tidak
    // memicu TH5c) tapi DI BAWAH HPP batch yang sudah habis (Rp99.000).
    // Bila implementasi keliru memakai batch habis sebagai acuan, test
    // ini akan gagal (mengharap penerapan langsung, bukan Approval).
    $result = $this->action->execute($product, '97000.00');

    expect($result)->toBeInstanceOf(Product::class)
        ->and((string) $result->selling_price)->toEqual('97000.00')
        ->and(Approval::query()->count())->toBe(0);
});

it('Owner dikecualikan dari TH5a/TH5b/TH5c — perubahan besar tetap diterapkan langsung', function () {
    $branch = Branch::factory()->create();
    $owner = makeTestUser(['manage_product_prices']);
    Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $product = Product::factory()->create(['selling_price' => '100000.00']);
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '95000.00',
        'qty_received' => '5.0000',
        'qty_remaining' => '5.0000',
        'received_at' => now()->subDay(),
    ]);

    // Rp50.000 — jauh di bawah HPP (TH5c) DAN turun >50% (TH5b) — Owner
    // tetap lolos langsung tanpa Approval sama sekali.
    $result = $this->action->execute($product, '50000.00');

    expect($result)->toBeInstanceOf(Product::class)
        ->and((string) $result->selling_price)->toEqual('50000.00')
        ->and(Approval::query()->count())->toBe(0);
});
