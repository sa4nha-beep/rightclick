<?php

declare(strict_types=1);

namespace App\Presentation\Pos\Http\Controllers;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Nota penjualan (T4.4, cetak/cetak ulang) — GET murni, tidak mengubah
 * data. Bisa diakses berkali-kali kapan pun (NT-05: "coba cetak ulang" —
 * cetak ulang HANYA berarti sesuatu bila jalur ini stateless/idempoten).
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
    public function __invoke(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        abort_if($sale->state === DocumentState::Draft, 404);

        $sale->loadMissing(['branch', 'partner', 'items.product', 'items.service', 'payments', 'cashierShift.cashier']);

        return view('pos.receipt', ['sale' => $sale]);
    }
}
