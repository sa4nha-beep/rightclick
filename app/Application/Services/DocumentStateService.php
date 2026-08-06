<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\DocumentStateException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Jalur normal transisi siklus hidup dokumen (R4, AC-02): draft → final,
 * lalu final → void. `App\Infrastructure\Persistence\Concerns\HasDocumentState`
 * menjaga invarian yang sama di lapisan model sebagai pertahanan berlapis;
 * layanan ini adalah API yang dipakai kode pemanggil (mis. finalisasi
 * penjualan T4.6, penerimaan barang T5.2) agar kolom yang relevan terisi
 * konsisten pada setiap transisi.
 *
 * Voiding TIDAK menghapus atau mengubah mutasi/nilai lama pada dokumen —
 * modul yang menerbitkan mutasi pembalik (DB Design §8.3) memanggil
 * `void()` semata untuk mengunci status dokumen; pembalikan datanya adalah
 * tanggung jawab modul tersebut, bukan layanan ini.
 */
final class DocumentStateService
{
    /**
     * Naikkan dokumen dari draft ke final.
     */
    public function finalize(Model $document): void
    {
        if ($this->currentState($document) !== DocumentState::Draft) {
            throw new DocumentStateException('Hanya dokumen berstatus draft yang dapat difinalisasi.');
        }

        $document->setAttribute('state', DocumentState::Final);
        $document->setAttribute('finalized_at', now());
        $document->save();
    }

    /**
     * Batalkan dokumen final. Alasan wajib diisi (R4, AC-02).
     */
    public function void(Model $document, string $reason, ?string $voidedBy = null): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DocumentStateException('Void wajib menyertakan alasan (R4).');
        }

        if ($this->currentState($document) !== DocumentState::Final) {
            throw new DocumentStateException(
                'Hanya dokumen final yang dapat dibatalkan (void) — dokumen draft cukup diedit atau dihapus.',
            );
        }

        $document->setAttribute('state', DocumentState::Void);
        $document->setAttribute('voided_at', now());
        $document->setAttribute('voided_by', $voidedBy ?? Auth::id());
        $document->setAttribute('void_reason', $reason);
        $document->save();
    }

    private function currentState(Model $document): DocumentState
    {
        $state = $document->getAttribute('state');

        return $state instanceof DocumentState ? $state : DocumentState::from($state);
    }
}
