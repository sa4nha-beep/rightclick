<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Policies\ProductCategoryPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestProductCategory(array $attributes = []): ProductCategory
{
    return ProductCategory::factory()->create($attributes);
}

it('viewAny memerlukan permission view_products', function () {
    $policy = new ProductCategoryPolicy;

    expect($policy->viewAny(makeTestUser(['view_products'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_products', function () {
    $policy = new ProductCategoryPolicy;
    $category = makeTestProductCategory();

    expect($policy->view(makeTestUser(['view_products']), $category))->toBeTrue()
        ->and($policy->view(makeTestUser(), $category))->toBeFalse();
});

it('create ditolak tanpa permission create_products', function () {
    $policy = new ProductCategoryPolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_products di node HQ', function () {
    $policy = new ProductCategoryPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new ProductCategoryPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeFalse();
});

it('update memerlukan permission edit_products dan node HQ', function () {
    $policy = new ProductCategoryPolicy;
    $category = makeTestProductCategory();
    $authorized = makeTestUser(['edit_products']);

    expect($policy->update($authorized, $category))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $category))->toBeFalse();
});

it('delete memerlukan permission delete_products dan node HQ', function () {
    $policy = new ProductCategoryPolicy;
    $category = makeTestProductCategory();
    $authorized = makeTestUser(['delete_products']);

    expect($policy->delete($authorized, $category))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $category))->toBeFalse();
});

it('restore memerlukan permission delete_products dan node HQ', function () {
    $policy = new ProductCategoryPolicy;
    $category = makeTestProductCategory();

    expect((new ProductCategoryPolicy)->restore(makeTestUser(['delete_products']), $category))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new ProductCategoryPolicy;
    $category = makeTestProductCategory();
    $owner = makeTestUser(['delete_products']);

    expect($policy->forceDelete($owner, $category))->toBeFalse();
});

it('subkategori dapat merujuk induknya lewat parent_id', function () {
    $parent = makeTestProductCategory(['code' => 'PARENT-1', 'name' => 'Komponen']);
    $child = makeTestProductCategory(['code' => 'CHILD-1', 'name' => 'Motherboard', 'parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children->pluck('id'))->toContain($child->id);
});

it('kategori yang masih punya anak tidak dapat dihapus fisik dari database', function () {
    $parent = makeTestProductCategory(['code' => 'PARENT-2']);
    makeTestProductCategory(['code' => 'CHILD-2', 'parent_id' => $parent->id]);

    expect(fn () => DB::table('product_categories')->where('id', $parent->id)->delete())
        ->toThrow(QueryException::class);
});
