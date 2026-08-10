<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Infrastructure\Persistence\Models\Product;

/**
 * Validasi serial number pada baris transaksi (T3.7, R3): "Serial dicatat
 * sebagai field pada baris transaksi sejak MVP." Dipakai bersama oleh
 * `FinalizeStockOpnameAction`, `FinalizeStockAdjustmentAction`,
 * `DispatchStockTransferAction` — aturannya identik di ketiganya.
 *
 * SENGAJA tidak memeriksa keunikan LINTAS transaksi (mis. serial yang sama
 * dipakai di dua dokumen berbeda, status sedang di gudang mana) — itu
 * adalah "unit registry penuh" yang CLAUDE.md §3 secara eksplisit
 * mengecualikannya dari MVP. Ini murni validasi bentuk field: jumlah dan
 * keunikan DALAM baris yang sama.
 */
final class SerialNumberValidationService
{
    /**
     * @param  array<int, string>|null  $serialNumbers
     */
    public function validate(Product $product, string $quantity, ?array $serialNumbers): void
    {
        $hasSerials = $serialNumbers !== null && $serialNumbers !== [];

        if (! $product->is_serialized) {
            if ($hasSerials) {
                throw new StockDocumentValidationException(sprintf(
                    'Produk %s tidak dilacak serial number — baris ini tidak seharusnya mengisi serial number.',
                    $product->sku,
                ));
            }

            return;
        }

        $serials = $serialNumbers ?? [];
        $expectedCount = (int) $quantity;

        if (count($serials) !== $expectedCount) {
            throw new StockDocumentValidationException(sprintf(
                'Produk %s wajib mengisi serial number (R3): %d unit tapi %d serial number diisi.',
                $product->sku,
                $expectedCount,
                count($serials),
            ));
        }

        $trimmed = array_map(static fn (string $serial): string => trim($serial), $serials);

        if (in_array('', $trimmed, true)) {
            throw new StockDocumentValidationException(sprintf(
                'Produk %s: serial number tidak boleh kosong.',
                $product->sku,
            ));
        }

        if (count(array_unique($trimmed)) !== count($trimmed)) {
            throw new StockDocumentValidationException(sprintf(
                'Produk %s: ditemukan serial number duplikat pada baris yang sama.',
                $product->sku,
            ));
        }
    }
}
