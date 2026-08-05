<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\Branch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penomoran dokumen terpusat (T1.7). Menghasilkan nomor unik berurutan per
 * (cabang, jenis dokumen, periode) dalam format `HKA/SAL/2608/00142`
 * (HS-UI-RIGHTCLICK-v1.1 §8.4).
 *
 * Kontrak penguncian (R6, DB Design §8.1):
 *
 *  - Nomor diambil dengan `SELECT ... FOR UPDATE` pada baris sequence, sehingga
 *    dua permintaan bersamaan terserialisasi oleh basis data — salah satu
 *    menunggu, keduanya memperoleh nomor berbeda (AC-04).
 *  - Pemanggilan WAJIB berada di dalam transaksi milik dokumen. Kunci baris
 *    dipegang sampai transaksi itu commit; bila dokumen gagal dan di-rollback,
 *    nomor ikut batal — tidak ada nomor yang lepas dari dokumennya. Memanggil
 *    tanpa transaksi akan melepas kunci seketika dan membuka celah balapan,
 *    maka hal itu ditolak sebagai kesalahan pemakaian.
 *  - `document_sequences` dikunci PERTAMA, sebelum `stock_batches`
 *    (DB Design §8.1). Urutan penguncian yang konsisten mencegah deadlock.
 */
final class DocumentNumberService
{
    /**
     * Ambil nomor berikutnya untuk sebuah dokumen dan naikkan penghitungnya.
     *
     * @param  DocumentType  $type  Jenis dokumen (menentukan prefix, mis. `SAL`).
     * @param  Branch  $branch  Cabang penerbit (menyumbang prefix kode cabang).
     * @param  CarbonInterface|null  $at  Saat penerbitan; default sekarang.
     *                                    Periode dihitung pada zona tampilan
     *                                    (Asia/Jakarta) agar nomor mengikuti
     *                                    tanggal bisnis pada nota, bukan UTC.
     */
    public function next(DocumentType $type, Branch $branch, ?CarbonInterface $at = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                'DocumentNumberService::next() harus dipanggil di dalam transaksi '
                .'dokumen (R6, DB Design §8.1) — kunci FOR UPDATE hanya bermakna '
                .'selama transaksi berjalan.',
            );
        }

        $moment = ($at ? $at->toImmutable() : CarbonImmutable::now())
            ->setTimezone(config('rightclick.display_timezone'));

        $period = $moment->format('Y-m'); // disimpan: '2026-08'
        $periodSegment = $moment->format('ym'); // tercetak: '2608'

        $branchId = $branch->getKey();

        // Pastikan baris penghitung ada tanpa balapan. Pada PostgreSQL ini
        // menjadi INSERT ... ON CONFLICT DO NOTHING: aman bila dua transaksi
        // periode-baru berjalan bersamaan — satu menyisipkan, yang lain
        // terserialisasi pada kunci unik dan tidak menghasilkan duplikat.
        DB::table('document_sequences')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'branch_id' => $branchId,
            'document_type' => $type->value,
            'period' => $period,
            'last_number' => 0,
        ]);

        // Kunci baris untuk sisa transaksi caller (R6). Baris dijamin ada
        // setelah insertOrIgnore di atas.
        $current = DB::table('document_sequences')
            ->where('branch_id', $branchId)
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->lockForUpdate()
            ->value('last_number');

        $nextNumber = (int) $current + 1;

        DB::table('document_sequences')
            ->where('branch_id', $branchId)
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->update(['last_number' => $nextNumber]);

        return sprintf(
            '%s/%s/%s/%05d',
            $branch->code,
            $type->prefix(),
            $periodSegment,
            $nextNumber,
        );
    }
}
