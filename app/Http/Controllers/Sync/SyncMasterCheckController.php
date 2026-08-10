<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * `POST /api/v1/sync/master-check` (T5.8, CLAUDE.md §8) — "Verifikasi
 * replikasi master data (terjadwal 15 menit)". Replikasi master data
 * SENDIRI berjalan lewat PostgreSQL logical replication (CLAUDE.md §7) —
 * DI LUAR kendali kode aplikasi ini sama sekali. Endpoint ini murni
 * MEMBANDINGKAN JUMLAH BARIS yang dilaporkan cabang terhadap jumlah baris
 * HQ untuk tabel yang sama — deteksi dini "replikasi berhenti/rusak"
 * SEBELUM cabang menyadarinya sendiri lewat gejala tidak langsung
 * (mis. produk baru tidak muncul).
 *
 * SENGAJA hanya perbandingan JUMLAH (bukan checksum per baris) — memadai
 * untuk deteksi "berhenti total"/"tertinggal jauh", cakupan MVP; deteksi
 * korupsi baris-per-baris yang jumlahnya tetap sama adalah kasus lebih
 * jarang, di luar cakupan T5.8.
 *
 * `{table}` DIBATASI ke tabel REPLICATED (CLAUDE.md §7) — menolak nama
 * tabel lain (mencegah membocorkan jumlah baris tabel SYNCED/LOCAL yang
 * tidak relevan untuk pemulihan replikasi).
 */
class SyncMasterCheckController extends Controller
{
    /**
     * Tabel REPLICATED (CLAUDE.md §7) — sama whitelist dengan
     * `SyncMasterSnapshotController`.
     */
    public const REPLICATED_TABLES = [
        'branches', 'users', 'roles', 'permissions', 'user_branches',
        'partners', 'products', 'product_categories', 'units', 'services',
        'employees', 'settings',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'table' => ['required', 'string', 'in:'.implode(',', self::REPLICATED_TABLES)],
            'count' => ['required', 'integer', 'min:0'],
        ])->validate();

        $hqCount = DB::table($validated['table'])->count();
        $branchCount = $validated['count'];

        return response()->json([
            'table' => $validated['table'],
            'hq_count' => $hqCount,
            'branch_count' => $branchCount,
            'match' => $hqCount === $branchCount,
            'difference' => $hqCount - $branchCount,
        ]);
    }
}
