<?php

declare(strict_types=1);

namespace App\Presentation\Pos\Http\Controllers;

use App\Application\Services\AuditService;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Nota penjualan (T4.4, cetak/cetak ulang). Tidak pernah mengubah dokumen
 * `Sale` itu sendiri — bisa diakses berkali-kali kapan pun (NT-05: "coba
 * cetak ulang" — cetak ulang HANYA berarti sesuatu bila jalur ini tidak
 * pernah gagal karena state dokumen).
 *
 * T4.11/UT14 (menutup gap terhadap `HS-TASKS-RIGHTCLICK-v1.1`): cetak ulang
 * wajib bertanda "SALINAN" dan tercatat di audit log. **Sengaja TIDAK**
 * memakai kolom baru (mis. `sales.printed_at`) — `Sale` memakai
 * `HasDocumentState` (R4), yang menolak SELURUH perubahan pada dokumen
 * `final` di luar transisi ke void (lihat guard `updating` di trait
 * tersebut); menambah kolom stateful di sana berarti menambal whitelist
 * generik itu demi satu model, atau menulis lewat query builder mentah yang
 * melewati guard-nya sendiri — dua-duanya pelanggaran R4/arsitektur. Sebagai
 * gantinya, riwayat cetak dibaca/ditulis SELURUHNYA lewat `audit_logs`
 * (append-only, memang dirancang untuk mencatat aksi tanpa memodifikasi
 * dokumen sumber, R11): keberadaan entri `AuditAction::Reprinted`
 * sebelumnya pada `Sale` ini menentukan apakah permintaan SEKARANG adalah
 * cetak pertama atau cetak ulang, dan setiap kunjungan (termasuk yang
 * pertama) menulis satu entri baru — `Sale` sendiri tidak tersentuh sama
 * sekali, GET tetap bebas efek samping pada dokumennya.
 *
 * AC-14/R13 ditegakkan di VIEW (`pos.receipt`) dengan cara paling kuat:
 * TIDAK ADA baris atau perhitungan PPN yang ditulis di sana sama sekali —
 * bukan disembunyikan lewat kondisi, karena field itu tidak pernah ada.
 *
 * Draft ditolak (404, bukan 403 — R12/§10 pola "sembunyikan keberadaan
 * dokumen") karena belum punya `document_number`; nota untuk dokumen yang
 * belum final tidak bermakna.
 */
final class ShowSaleReceiptController
{
    public function __invoke(Sale $sale, AuditService $auditService): View
    {
        Gate::authorize('view', $sale);

        abort_if($sale->state === DocumentState::Draft, 404);

        $sale->loadMissing(['branch', 'partner', 'items.product', 'items.service', 'payments', 'cashierShift.cashier']);

        $isReprint = AuditLog::query()
            ->where('model_type', Sale::class)
            ->where('model_id', $sale->id)
            ->where('action', AuditAction::Reprinted)
            ->exists();

        $auditService->log(
            $sale,
            AuditAction::Reprinted,
            metadata: ['event' => $isReprint ? 'reprint' : 'first_print'],
        );

        return view('pos.receipt', ['sale' => $sale, 'isReprint' => $isReprint]);
    }
}
