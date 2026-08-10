<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger idempotensi sisi HQ — T5.8 (CLAUDE.md §8: "Idempotensi
     * mutlak: `event_id` sebagai primary key `processed_events`").
     *
     * `id` BUKAN dibangkitkan di sini — nilainya adalah `outbox_events.id`
     * milik cabang pengirim (UUID v7 yang sama persis, ikut di payload
     * `POST /api/v1/sync/events`). Pengiriman ulang batch yang sama
     * (jaringan putus sebelum ack diterima cabang, dsb.) adalah KONDISI
     * NORMAL, bukan pengecualian — `SyncEventsController` mengecek
     * keberadaan baris ini LEBIH DULU sebelum mencoba menerapkan apa pun;
     * bila sudah ada, respons `duplicate` tanpa efek samping kedua.
     *
     * HANYA event yang BENAR-BENAR diterapkan (`accepted`) yang masuk ke
     * sini — event `deferred` SENGAJA TIDAK dicatat (supaya percobaan
     * berikutnya benar-benar mencoba ulang, bukan dianggap "sudah
     * diproses"); event `rejected` juga tidak dicatat di sini (cabang
     * yang menandai `failed` secara lokal dan tidak akan mengirim ulang
     * secara otomatis — lihat `OutboxDispatcher`).
     *
     * Append-only, sama bentuknya dengan `stock_mutations`/`audit_logs` —
     * tidak ada soft delete, tidak ada `updated_at`. LOCAL (CLAUDE.md §7)
     * — hanya bermakna di node HQ, tidak pernah disinkronkan ke mana pun.
     */
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->timestampTz('processed_at');
            $table->timestamp('created_at');

            $table->index(['branch_id', 'processed_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
