<?php

declare(strict_types=1);

namespace App\Domain\Sync\Exceptions;

use RuntimeException;

/**
 * Dilempar `SyncEventApplier` saat sebuah event VALID tapi bergantung pada
 * entitas yang belum tiba di HQ (CLAUDE.md §8, contoh: `sale.finalized`
 * merujuk `batch_id` dari `goods_receipt.finalized` yang belum diproses).
 * BEDA dari exception generik — `SyncEventProcessor` menangkap ini secara
 * KHUSUS untuk memutuskan status `deferred` (coba lagi nanti), bukan
 * `rejected` (gagal permanen).
 */
final class SyncEventDeferredException extends RuntimeException {}
