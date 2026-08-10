<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * `POST /api/v1/sync/partner-upsert` (T5.8, CLAUDE.md §8) — "Partner
 * dibuat lokal saat HQ tak terjangkau". SATU-SATUNYA jalan `partners`
 * (tabel REPLICATED — normalnya HANYA ditulis di HQ, M02) menerima
 * tulisan yang BERASAL dari cabang: R8 mensyaratkan POS tetap jalan saat
 * terputus dari HQ, dan Kasir/Admin cabang kadang perlu mencatat
 * pelanggan/pemasok baru SAAT ITU JUGA tanpa menunggu HQ — cabang
 * membuatnya LOKAL dengan `id` (UUID v7) yang sudah pasti unik lintas
 * node (R6), lalu MENDORONGNYA ke sini begitu koneksi ke HQ pulih supaya
 * HQ mengadopsinya sebagai salinan otoritatif — yang kemudian
 * direplikasi KELUAR ke SEMUA cabang secara normal (membalik arah
 * REPLIKASI untuk SATU kasus pengecualian ini saja).
 *
 * Upsert BY ID (bukan by `code`) — `id` dijamin unik oleh cabang pembuat
 * (UUID v7, R6), `code` yang bentrok dengan partner HQ yang sudah ada
 * (constraint unique) genuinely DITOLAK (422) — operator harus
 * menyelesaikan konflik penomoran secara manual, di luar cakupan
 * otomatisasi endpoint ini.
 */
class SyncPartnerUpsertController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string'],
            'partner_type' => ['required', 'string', 'in:supplier,customer,both'],
            'tax_id' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric'],
            'payment_terms_days' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $validated['is_active'] ??= true;
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        try {
            DB::table('partners')->upsert([$validated], ['id']);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Partner tidak dapat diadopsi HQ — kemungkinan bentrok kode dengan partner yang sudah ada.',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['id' => $validated['id'], 'adopted' => true]);
    }
}
