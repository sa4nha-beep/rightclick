<?php

declare(strict_types=1);

use App\Application\Services\SerialNumberValidationService;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Infrastructure\Persistence\Models\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->service = app(SerialNumberValidationService::class);
});

afterEach(function () {
    DB::rollBack();
});

it('produk non-serial lolos tanpa serial number', function () {
    $product = Product::factory()->create(['is_serialized' => false]);

    $this->service->validate($product, '3', null);

    expect(true)->toBeTrue();
});

it('produk non-serial ditolak bila serial number tetap diisi', function () {
    $product = Product::factory()->create(['is_serialized' => false]);

    expect(fn () => $this->service->validate($product, '1', ['SN-001']))
        ->toThrow(StockDocumentValidationException::class);
});

it('produk serial ditolak bila jumlah serial tidak sama dengan kuantitas', function () {
    $product = Product::factory()->create(['is_serialized' => true]);

    expect(fn () => $this->service->validate($product, '3', ['SN-001', 'SN-002']))
        ->toThrow(StockDocumentValidationException::class);
});

it('produk serial ditolak bila ada serial duplikat', function () {
    $product = Product::factory()->create(['is_serialized' => true]);

    expect(fn () => $this->service->validate($product, '2', ['SN-001', 'SN-001']))
        ->toThrow(StockDocumentValidationException::class);
});

it('produk serial ditolak bila ada serial kosong', function () {
    $product = Product::factory()->create(['is_serialized' => true]);

    expect(fn () => $this->service->validate($product, '2', ['SN-001', '   ']))
        ->toThrow(StockDocumentValidationException::class);
});

it('produk serial lolos bila jumlah dan keunikan serial sesuai', function () {
    $product = Product::factory()->create(['is_serialized' => true]);

    $this->service->validate($product, '2', ['SN-001', 'SN-002']);

    expect(true)->toBeTrue();
});
