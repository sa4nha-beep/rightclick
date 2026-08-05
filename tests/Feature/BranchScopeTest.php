<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * T1.3 AC — "Test unit membuktikan ... BranchScope menyaring otomatis."
 *
 * Tabel dan model di sini adalah perancah pengujian murni untuk lapisan
 * fondasi (HasUuidV7, BelongsToBranch, macro `uuidPrimaryKey`) — bukan
 * bagian skema produksi. Tabel nyata dengan `branch_id` (stock_batches,
 * sales, dst.) dibangun mulai T2.x/T3.x memakai trait dan macro yang sama.
 */
beforeEach(function () {
    Schema::create('branch_scope_test_products', function (Blueprint $table) {
        $table->uuidPrimaryKey();
        $table->uuid('branch_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('branch_scope_test_products');
    app(BranchContext::class)->clear();
});

function branchScopeTestModel(): Model
{
    return new class extends Model
    {
        use BelongsToBranch;
        use HasUuidV7;

        protected $table = 'branch_scope_test_products';

        protected $fillable = ['name', 'branch_id'];
    };
}

it('hanya menampilkan baris milik cabang yang sedang aktif', function () {
    $branchA = (string) Str::uuid7();
    $branchB = (string) Str::uuid7();

    app(BranchContext::class)->set($branchA);
    branchScopeTestModel()::create(['name' => 'Produk A']);

    app(BranchContext::class)->set($branchB);
    branchScopeTestModel()::create(['name' => 'Produk B']);

    app(BranchContext::class)->set($branchA);

    expect(branchScopeTestModel()::query()->pluck('name')->all())->toBe(['Produk A']);
});

it('mengisi branch_id otomatis dari konteks aktif saat membuat baris', function () {
    $branchA = (string) Str::uuid7();
    app(BranchContext::class)->set($branchA);

    $created = branchScopeTestModel()::create(['name' => 'Produk C']);

    expect($created->branch_id)->toBe($branchA);
});

it('tidak menimpa branch_id yang sudah diisi eksplisit', function () {
    $branchA = (string) Str::uuid7();
    $explicitBranch = (string) Str::uuid7();
    app(BranchContext::class)->set($branchA);

    $created = branchScopeTestModel()::create(['name' => 'Produk sinkron', 'branch_id' => $explicitBranch]);

    expect($created->branch_id)->toBe($explicitBranch);
});

it('tidak menyaring apa pun saat tidak ada cabang aktif (konteks konsol/sistem)', function () {
    $branchA = (string) Str::uuid7();

    app(BranchContext::class)->set($branchA);
    branchScopeTestModel()::create(['name' => 'Produk D']);

    app(BranchContext::class)->clear();

    expect(branchScopeTestModel()::query()->count())->toBe(1);
});

it('dapat dilewati secara sengaja untuk laporan lintas cabang', function () {
    $branchA = (string) Str::uuid7();
    $branchB = (string) Str::uuid7();

    app(BranchContext::class)->set($branchA);
    branchScopeTestModel()::create(['name' => 'Produk E']);

    app(BranchContext::class)->set($branchB);
    branchScopeTestModel()::create(['name' => 'Produk F']);

    $crossBranchCount = branchScopeTestModel()::withoutGlobalScope(BranchScope::class)->count();

    expect($crossBranchCount)->toBe(2);
});
