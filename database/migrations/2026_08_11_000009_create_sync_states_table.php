<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status sinkronisasi per cabang — T5.8. LOCAL (CLAUDE.md §7), hanya
     * bermakna di node HQ: satu baris per cabang pengirim, diperbarui
     * `SyncEventsController` setiap kali sebuah batch DARI cabang tersebut
     * selesai diproses. Sumber "lag"/"jumlah tertunda" pada
     * `GET /api/v1/sync/health` (§8).
     *
     * `pending_count`/`deferred_count` di sini adalah ANGKA TERAKHIR YANG
     * DILAPORKAN cabang saat memanggil endpoint ini — HQ TIDAK PERNAH bisa
     * tahu langsung berapa banyak `outbox_events` cabang yang masih
     * `pending` secara lokal (basis data terpisah secara fisik, R6 tidak
     * ada koneksi DB lintas node) — cabang melaporkannya sendiri sebagai
     * bagian permintaan `/health` (lihat `SyncHealthController`).
     *
     * Bukan append-only — satu baris per `branch_id` (`unique`), diperbarui
     * di tempat (`updateOrCreate`), sama filosofi `stock_balances` sebagai
     * cache turunan yang boleh ditimpa, bukan ledger permanen.
     */
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('branch_id')->unique()->constrained('branches')->restrictOnDelete();
            $table->uuid('last_event_id')->nullable();
            $table->timestampTz('last_event_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->unsignedBigInteger('events_processed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('deferred_count')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
