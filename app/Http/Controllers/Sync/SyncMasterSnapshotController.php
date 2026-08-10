<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * `GET /api/v1/sync/master-snapshot/{table}` (T5.8, CLAUDE.md §8) —
 * "Pemulihan bila replikasi rusak". Mengembalikan SELURUH baris tabel
 * REPLICATED apa adanya (`SELECT *`, paginasi) supaya cabang bisa
 * membangun ulang salinan lokalnya secara manual saat PostgreSQL logical
 * replication (§7) berhenti/rusak dan operator memutuskan pemulihan
 * manual lebih cepat daripada menunggu replikasi pulih sendiri.
 *
 * `{table}` DIBATASI ke whitelist `SyncMasterCheckController::REPLICATED_TABLES`
 * yang SAMA — mencegah endpoint ini disalahgunakan untuk membaca tabel
 * SYNCED/LOCAL sembarangan (data transaksi/finansial cabang lain).
 *
 * Paginasi (`page`/`per_page`, maks 1000/halaman) — §13 menyebut uji beban
 * "20.000 SKU"; mengirim seluruh `products` dalam satu respons berisiko
 * payload sangat besar tanpa alasan kuat, paginasi adalah pertahanan
 * murah untuk kasus itu.
 */
class SyncMasterSnapshotController extends Controller
{
    private const MAX_PER_PAGE = 1000;

    public function __invoke(Request $request, string $table): JsonResponse
    {
        if (! in_array($table, SyncMasterCheckController::REPLICATED_TABLES, true)) {
            return response()->json(['message' => "Tabel '{$table}' bukan tabel REPLICATED yang diizinkan."], 404);
        }

        $perPage = min((int) $request->query('per_page', (string) self::MAX_PER_PAGE), self::MAX_PER_PAGE);
        $perPage = max($perPage, 1);
        $page = max((int) $request->query('page', '1'), 1);

        $totalCount = DB::table($table)->count();

        $rows = DB::table($table)
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'table' => $table,
            'page' => $page,
            'per_page' => $perPage,
            'total_count' => $totalCount,
            'rows' => $rows,
        ]);
    }
}
