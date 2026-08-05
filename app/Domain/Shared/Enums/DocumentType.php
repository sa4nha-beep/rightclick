<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Jenis dokumen transaksi yang memperoleh nomor terpusat dari
 * `App\Application\Services\DocumentNumberService` (T1.7).
 *
 * Nilai enum (`value`) adalah string yang disimpan pada kolom
 * `document_sequences.document_type` (DB Design §4.1). `prefix()` adalah kode
 * tiga huruf yang muncul pada nomor dokumen tercetak, mis. `HKA/SAL/2608/00142`
 * (HS-UI-RIGHTCLICK-v1.1 §8.4, HS-API-RIGHTCLICK-v1.0).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire (LayeringTest).
 *
 * Sengaja baru memuat satu case. Hanya `sale` → `SAL` yang formatnya
 * terdokumentasi di seluruh dokumen (satu-satunya contoh nomor yang muncul).
 * Kode prefix untuk `po`, `gr`, `opname`, transfer, adjustment, dan retur
 * belum ditetapkan dokumen mana pun; menebaknya berarti membakukan kode yang
 * akan tercetak pada dokumen resmi tanpa dasar (G4 — bila dokumentasi tidak
 * lengkap, hentikan dan tanyakan). Setiap case ditambahkan oleh task yang
 * membangun dokumen bersangkutan (T4.6 penjualan sudah tercakup di sini; T5.1
 * PO, T5.2 penerimaan, T3.5 opname, T3.6 adjustment, T3.7 transfer, T4.10
 * retur) dengan kode yang telah dikonfirmasi.
 */
enum DocumentType: string
{
    case Sale = 'sale';

    /**
     * Kode tiga huruf pada nomor dokumen tercetak.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Sale => 'SAL',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Penjualan',
        };
    }
}
