<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbox transaksi — T5.7, SIMPUL KRITIS. LOCAL (CLAUDE.md §7) — tabel
     * ini sendiri TIDAK disinkronkan; ISINYA yang dikirim ke HQ lewat
     * `POST /api/v1/sync/events` (T5.8, belum dibangun di sini).
     *
     * `id` adalah idempotency key (`event_id` pada payload API §8) — WAJIB
     * ditulis DALAM TRANSAKSI YANG SAMA dengan dokumen yang menerbitkannya
     * (`OutboxService::record()`, dipanggil dari setiap
     * `Finalize*Action`/`Void*Action` atas dokumen SYNCED). Di luar
     * transaksi = dokumen final yang tidak pernah sampai HQ dan tidak ada
     * yang tahu (§11 catatan simpul kritis).
     *
     * `aggregate_type`/`aggregate_id` menunjuk DOKUMEN INDUK (mis. `Sale`),
     * BUKAN baris anak — satu event per TRANSISI SIKLUS HIDUP dokumen
     * (finalize/void), sama filosofi "aggregate root" yang sudah tersirat
     * dari contoh CLAUDE.md §8 ("`sale.finalized` merujuk `batch_id` dari
     * `goods_receipt.finalized`"). Payload (T5.8, `OutboxService::record()`)
     * TETAP membawa snapshot relasional LENGKAP baris anak (`sale_items`/
     * `sale_payments`/`stock_mutations`/`stock_batches`/`cash_entries`) —
     * "aggregate root" di sini soal identitas EVENT (satu per transisi
     * dokumen induk), bukan soal payload-nya cuma kolom dokumen induk saja
     * (desain awal T5.7 yang diperkaya di T5.8 setelah disadari HQ tidak
     * punya cara lain merekonstruksi tabel SYNCED anak tanpa round-trip
     * tambahan).
     *
     * `status` DI SINI hanya `pending`/`sent`/`failed` — penyederhanaan
     * lokal, BUKAN 4 status protokol penuh (`accepted`/`duplicate`/
     * `deferred`/`rejected`, CLAUDE.md §8) yang baru bermakna setelah ada
     * worker sinkronisasi (T5.8) yang menerjemahkan respons API ke status
     * lokal ini.
     */
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->string('event_type', 60);
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->jsonb('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        DB::statement(
            'ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_status_check '.
            "CHECK (status IN ('pending', 'sent', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
