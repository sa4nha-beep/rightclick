<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\DocumentStateException;

/**
 * Siklus hidup dokumen transaksi draft → final → void (R4, AC-02).
 *
 * Model yang memakai trait ini wajib memiliki kolom `state`, `finalized_at`,
 * `voided_at`, `voided_by`, `void_reason` — lihat
 * `MigrationMacros::documentStateColumns()` dan `MigrationMacros::documentStateVoidCheckSql()`
 * (C7) untuk migration yang serasi.
 *
 * Trait ini adalah penjaga di lapisan model (pertahanan berlapis, bukan
 * pengganti) — `App\Application\Services\DocumentStateService` adalah jalur
 * normal untuk transisi state dan bertanggung jawab mengisi kolom yang
 * relevan dengan benar. Trait ini memastikan pelanggaran R4 gagal bahkan
 * bila kode lain mengubah model secara langsung tanpa lewat service:
 *
 *  - Dokumen `final`: satu-satunya perubahan yang diterima adalah transisi
 *    ke `void`, dan HANYA kolom bawaan void (`state`, `voided_at`,
 *    `voided_by`, `void_reason`, plus `updated_by`/`updated_at` yang diisi
 *    otomatis) yang boleh ikut berubah pada baris update yang sama —
 *    mencegah koreksi diam-diam menumpang pada void.
 *  - Dokumen `void`: terminal, tidak ada perubahan apa pun yang diterima.
 *  - Dokumen `draft`: bebas diedit — belum ada nilai yang dikunci.
 */
trait HasDocumentState
{
    public static function bootHasDocumentState(): void
    {
        static::creating(function ($model): void {
            if ($model->state === null) {
                $model->state = DocumentState::Draft;
            }
        });

        static::updating(function ($model): void {
            $original = $model->getOriginal('state');
            $originalState = $original instanceof DocumentState ? $original : DocumentState::from($original);

            if ($originalState === DocumentState::Void) {
                throw new DocumentStateException(
                    'Dokumen berstatus void bersifat terminal — tidak dapat diubah lagi.',
                );
            }

            if ($originalState !== DocumentState::Final) {
                return;
            }

            $newState = $model->state instanceof DocumentState ? $model->state : DocumentState::from($model->state);

            if ($newState !== DocumentState::Void) {
                throw new DocumentStateException(
                    'Dokumen final tidak dapat diedit (R4) — koreksi hanya melalui void beralasan.',
                );
            }

            $illegalChanges = array_diff(
                array_keys($model->getDirty()),
                ['state', 'voided_at', 'voided_by', 'void_reason', 'updated_by', 'updated_at'],
            );

            if ($illegalChanges !== []) {
                throw new DocumentStateException(
                    'Transisi ke void hanya boleh mengubah field void — field lain ikut berubah: '
                    .implode(', ', $illegalChanges),
                );
            }

            if (blank($model->void_reason)) {
                throw new DocumentStateException('Void wajib menyertakan alasan (R4).');
            }
        });
    }

    public function initializeHasDocumentState(): void
    {
        $this->mergeCasts(['state' => DocumentState::class]);
    }
}
