<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Shared\Enums\NodeRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deteksi konektivitas node cabang ke HQ (T1.11, U8/§8.2 HS-UI-RIGHTCLICK-v1.1).
 *
 * Master data (branches, users, products, dst. — REPLICATED per CLAUDE.md §7)
 * disebarkan lewat PostgreSQL logical replication, BUKAN lewat outbox/API
 * sinkronisasi aplikasi (itu untuk tabel SYNCED, dibangun Fase 5). Sinyal
 * "terputus dari pusat" yang paling langsung dan tersedia sejak Fase 1
 * adalah katalog sistem `pg_stat_subscription` — daftar subscription
 * replikasi logikal aktif pada database node ini beserta waktu pesan
 * terakhir diterima.
 *
 * Node HQ selalu dianggap "tersambung" — ia adalah sumbernya sendiri.
 *
 * CATATAN CAKUPAN: docker-compose.yml pengembangan lokal (satu node) tidak
 * mengonfigurasi subscription replikasi sungguhan antar node — topologi tiga
 * node adalah kondisi produksi/staging multi-server. Di lingkungan
 * pengembangan solo, `pg_stat_subscription` akan selalu kosong, dan node
 * `branch` akan selalu terbaca "terputus". Ini BUKAN bug — node tersebut
 * memang belum direplikasi dari mana pun. Mekanisme deteksi di sini benar
 * secara desain; topologi replikasi sungguhan adalah pekerjaan operasional
 * terpisah (di luar cakupan kode aplikasi).
 */
final class NodeConnectivityService
{
    private const int STALE_THRESHOLD_MINUTES = 5;

    public function isConnectedToHq(): bool
    {
        $role = NodeRole::from(config('rightclick.node.role'));

        if ($role->isHq()) {
            return true;
        }

        return $this->hasRecentReplicationActivity();
    }

    private function hasRecentReplicationActivity(): bool
    {
        try {
            $rows = DB::select('SELECT latest_end_time FROM pg_stat_subscription');
        } catch (Throwable) {
            // Katalog tidak terjangkau sama sekali (mis. koneksi database
            // bermasalah) — perlakukan sebagai terputus, bukan gagal keras.
            // Halaman login harus tetap tampil dengan banner yang jujur,
            // bukan error 500.
            return false;
        }

        foreach ($rows as $row) {
            if ($row->latest_end_time === null) {
                continue;
            }

            if (CarbonImmutable::parse($row->latest_end_time)->diffInMinutes(now()) <= self::STALE_THRESHOLD_MINUTES) {
                return true;
            }
        }

        return false;
    }
}
