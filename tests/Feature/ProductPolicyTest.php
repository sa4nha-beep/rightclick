<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use App\Infrastructure\Persistence\Policies\ProductPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes);
}

it('viewAny memerlukan permission view_products', function () {
    $policy = new ProductPolicy;

    expect($policy->viewAny(makeTestUser(['view_products'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_products', function () {
    $policy = new ProductPolicy;
    $product = makeTestProduct();

    expect($policy->view(makeTestUser(['view_products']), $product))->toBeTrue()
        ->and($policy->view(makeTestUser(), $product))->toBeFalse();
});

it('create ditolak tanpa permission create_products', function () {
    $policy = new ProductPolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_products di node HQ', function () {
    $policy = new ProductPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new ProductPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeFalse();
});

it('update memerlukan permission edit_products dan node HQ', function () {
    $policy = new ProductPolicy;
    $product = makeTestProduct();
    $authorized = makeTestUser(['edit_products']);

    expect($policy->update($authorized, $product))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $product))->toBeFalse();
});

it('delete memerlukan permission delete_products dan node HQ', function () {
    $policy = new ProductPolicy;
    $product = makeTestProduct();
    $authorized = makeTestUser(['delete_products']);

    expect($policy->delete($authorized, $product))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $product))->toBeFalse();
});

it('restore memerlukan permission delete_products dan node HQ', function () {
    $policy = new ProductPolicy;
    $product = makeTestProduct();

    expect((new ProductPolicy)->restore(makeTestUser(['delete_products']), $product))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new ProductPolicy;
    $product = makeTestProduct();
    $owner = makeTestUser(['delete_products']);

    expect($policy->forceDelete($owner, $product))->toBeFalse();
});

it('selling_price nol atau negatif ditolak database — CHECK constraint', function () {
    $category = ProductCategory::factory()->create();
    $unit = Unit::factory()->create();

    expect(fn () => Product::factory()->create([
        'product_category_id' => $category->id,
        'base_unit_id' => $unit->id,
        'selling_price' => 0,
    ]))->toThrow(QueryException::class);
});

it('produk merujuk kategori dan satuan lewat relasi', function () {
    $category = ProductCategory::factory()->create(['name' => 'Komponen']);
    $unit = Unit::factory()->create(['name' => 'Pieces']);
    $product = makeTestProduct(['product_category_id' => $category->id, 'base_unit_id' => $unit->id]);

    expect($product->category->id)->toBe($category->id)
        ->and($product->baseUnit->id)->toBe($unit->id);
});

it('model Product tidak memiliki kolom harga beli — §16 peringatan #5', function () {
    $product = makeTestProduct();

    $forbiddenColumns = ['cost', 'purchase_price', 'hpp', 'buy_price', 'cost_price'];

    foreach ($forbiddenColumns as $column) {
        expect($product->getAttributes())->not->toHaveKey($column);
    }
});
