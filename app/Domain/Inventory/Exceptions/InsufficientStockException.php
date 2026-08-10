<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

/**
 * Stok tersedia tidak mencukupi permintaan konsumsi (R7, AC-10): "Stok
 * tidak pernah boleh negatif." Dilempar oleh `StockLedgerService::consume()`
 * di dalam transaksi FIFO — pemanggil membiarkannya menggagalkan (rollback)
 * seluruh transaksi dokumen, bukan menangkapnya untuk melanjutkan sebagian.
 *
 * `DomainException` (SPL bawaan PHP), konsisten dengan `DocumentStateException`
 * — pelanggaran aturan bisnis, bukan kegagalan infrastruktur.
 */
final class InsufficientStockException extends DomainException {}
