<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Enums\NodeRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute penerima sinkronisasi (`/api/v1/sync/events`, `/ack`, dsb.) HANYA
 * bermakna di node HQ (T5.8, satu-satunya penulis master data DAN
 * satu-satunya penerima event transaksi cabang, CLAUDE.md §5). Node cabang
 * memakai basis kode yang SAMA (§5 B5: "satu basis kode melayani tiga
 * node") tapi TIDAK PERNAH menerima panggilan `/sync/events` masuk — node
 * cabang adalah PENGIRIM (`OutboxDispatcher`), bukan penerima.
 */
class EnsureNodeIsHq
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = config('rightclick.node.role');
        $role = $role instanceof NodeRole ? $role : NodeRole::from((string) $role);

        if (! $role->isHq()) {
            return response()->json(['message' => 'Endpoint ini hanya tersedia di node HQ.'], 404);
        }

        return $next($request);
    }
}
