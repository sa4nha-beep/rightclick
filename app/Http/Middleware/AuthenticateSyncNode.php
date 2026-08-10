<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otentikasi node sinkronisasi (T5.8, CLAUDE.md §8: "diakses melalui VPN
 * dengan token per node"). WireGuard (§14) membatasi lapisan jaringan;
 * middleware ini adalah lapisan APLIKASI di atasnya — pertahanan berlapis,
 * BUKAN pengganti VPN (rute `/api/v1/sync/*` tetap harus hanya bisa
 * dijangkau lewat VPN antar node di produksi, konfigurasi jaringan/firewall
 * di luar cakupan kode aplikasi).
 *
 * Header `Authorization: Bearer <token>` dicari lewat `WHERE sync_token_hash
 * = SHA-256(token)` — lookup terindeks langsung (`unique`, T5.8 migration),
 * BUKAN `hash_equals()` manual per baris — pola sama Laravel Sanctum
 * (`PersonalAccessToken::findToken()`): timing-safety dijamin oleh
 * membandingkan HASH-ke-HASH via index database, bukan token-ke-token di
 * kode aplikasi. Cabang yang cocok diletakkan di `$request->attributes`
 * sebagai `syncBranch` untuk dipakai controller (mis. mengisi
 * `outbox_events.branch_id` dari SIAPA yang mengirim, bukan dipercaya dari
 * body permintaan).
 */
class AuthenticateSyncNode
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['message' => 'Token sinkronisasi wajib disertakan.'], 401);
        }

        $branch = Branch::query()
            ->where('is_active', true)
            ->where('sync_token_hash', hash('sha256', $token))
            ->first();

        if ($branch === null) {
            return response()->json(['message' => 'Token sinkronisasi tidak valid.'], 401);
        }

        $request->attributes->set('syncBranch', $branch);

        return $next($request);
    }
}
