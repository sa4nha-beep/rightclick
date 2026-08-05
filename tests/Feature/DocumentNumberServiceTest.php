<?php

declare(strict_types=1);

use App\Application\Services\DocumentNumberService;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T1.7 AC — AC-04: "50 permintaan nomor bersamaan menghasilkan 50 nomor unik
 * tanpa kegagalan." Dibuktikan pada PostgreSQL nyata (B3): kontiguitas +
 * keunikan lewat penghitung ber-`FOR UPDATE`, dan pembuktian langsung bahwa
 * kunci baris benar-benar menyerialkan dua koneksi.
 *
 * Berkas ini TIDAK memakai pola `DB::beginTransaction()/rollBack()` global
 * seperti test Policy: uji kunci butuh dua koneksi yang saling melihat baris
 * ter-commit, dan uji "wajib dalam transaksi" justru menuntut transactionLevel
 * bernilai nol. Pembersihan dilakukan manual di afterEach.
 */
beforeEach(function () {
    $this->branch = Branch::create([
        'code' => 'HKA',
        'name' => 'Cabang Uji Penomoran',
        'is_hq' => false,
        'is_active' => true,
    ]);

    $this->service = new DocumentNumberService;
});

afterEach(function () {
    // FK document_sequences.branch_id → branches (RESTRICT): hapus penghitung
    // dahulu, baru cabangnya.
    DB::table('document_sequences')->delete();
    Branch::withoutGlobalScopes()->whereKey($this->branch->getKey())->forceDelete();

    DB::purge('doc_seq_conn_a');
    DB::purge('doc_seq_conn_b');
});

it('memformat nomor sesuai HKA/SAL/2608/00001', function () {
    $number = DB::transaction(fn () => $this->service->next(
        DocumentType::Sale,
        $this->branch,
        CarbonImmutable::create(2026, 8, 6, 10, 0, 0, 'Asia/Jakarta'),
    ));

    expect($number)->toBe('HKA/SAL/2608/00001');
});

it('AC-04 — 50 permintaan menghasilkan 50 nomor unik dan berurutan', function () {
    $at = CarbonImmutable::create(2026, 8, 6, 10, 0, 0, 'Asia/Jakarta');

    $numbers = [];
    for ($i = 0; $i < 50; $i++) {
        $numbers[] = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $at));
    }

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50)
        ->and($numbers[0])->toBe('HKA/SAL/2608/00001')
        ->and($numbers[49])->toBe('HKA/SAL/2608/00050');

    expect((int) DB::table('document_sequences')
        ->where('branch_id', $this->branch->getKey())
        ->where('document_type', DocumentType::Sale->value)
        ->where('period', '2026-08')
        ->value('last_number'))->toBe(50);
});

it('menyerialkan dua koneksi: kunci FOR UPDATE menahan pesaing (bukti AC-04)', function () {
    // Baris penghitung harus ter-commit agar terlihat oleh kedua koneksi.
    DB::table('document_sequences')->insert([
        'id' => (string) Str::uuid7(),
        'branch_id' => $this->branch->getKey(),
        'document_type' => DocumentType::Sale->value,
        'period' => '2026-08',
        'last_number' => 7,
    ]);

    config([
        'database.connections.doc_seq_conn_a' => config('database.connections.pgsql'),
        'database.connections.doc_seq_conn_b' => config('database.connections.pgsql'),
    ]);

    $connA = DB::connection('doc_seq_conn_a');
    $connB = DB::connection('doc_seq_conn_b');

    $lockRow = fn ($conn) => $conn->table('document_sequences')
        ->where('branch_id', $this->branch->getKey())
        ->where('document_type', DocumentType::Sale->value)
        ->where('period', '2026-08')
        ->lockForUpdate()
        ->value('last_number');

    // A mengunci baris dan menahannya (transaksi belum selesai).
    $connA->beginTransaction();
    expect((int) $lockRow($connA))->toBe(7);

    // B gagal cepat saat mencoba mengunci baris yang sama — bukti ia benar
    // terblokir oleh kunci A, bukan melewatinya.
    $connB->statement("SET lock_timeout = '500ms'");
    $connB->beginTransaction();

    expect(fn () => $lockRow($connB))->toThrow(QueryException::class);

    $connB->rollBack();
    $connA->rollBack();
});

it('menolak pemanggilan di luar transaksi (kunci akan lepas seketika)', function () {
    expect(DB::transactionLevel())->toBe(0);

    expect(fn () => $this->service->next(DocumentType::Sale, $this->branch))
        ->toThrow(RuntimeException::class);
});

it('mengulang penghitung dari 00001 pada periode berikutnya', function () {
    $august = CarbonImmutable::create(2026, 8, 31, 23, 0, 0, 'Asia/Jakarta');
    $september = CarbonImmutable::create(2026, 9, 1, 8, 0, 0, 'Asia/Jakarta');

    $last = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $august));
    $first = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $september));

    expect($last)->toBe('HKA/SAL/2608/00001')
        ->and($first)->toBe('HKA/SAL/2609/00001');
});

it('menghitung periode pada zona tampilan, bukan UTC', function () {
    // 2026-08-31 23:30 Asia/Jakarta = 2026-08-31 16:30 UTC — keduanya Agustus,
    // tetapi 2026-09-01 06:00 Jakarta = 2026-08-31 23:00 UTC. Nomor harus
    // mengikuti tanggal bisnis lokal pada nota (September), bukan UTC.
    $jakartaSeptember = CarbonImmutable::create(2026, 9, 1, 6, 0, 0, 'Asia/Jakarta');

    $number = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $jakartaSeptember));

    expect($number)->toBe('HKA/SAL/2609/00001');
});

it('memisahkan penghitung per cabang', function () {
    $other = Branch::create([
        'code' => 'HKB',
        'name' => 'Cabang Uji Kedua',
        'is_hq' => false,
        'is_active' => true,
    ]);

    $at = CarbonImmutable::create(2026, 8, 6, 10, 0, 0, 'Asia/Jakarta');

    try {
        $a1 = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $at));
        $a2 = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $this->branch, $at));
        $b1 = DB::transaction(fn () => $this->service->next(DocumentType::Sale, $other, $at));

        expect($a1)->toBe('HKA/SAL/2608/00001')
            ->and($a2)->toBe('HKA/SAL/2608/00002')
            ->and($b1)->toBe('HKB/SAL/2608/00001');
    } finally {
        DB::table('document_sequences')->where('branch_id', $other->getKey())->delete();
        Branch::withoutGlobalScopes()->whereKey($other->getKey())->forceDelete();
    }
});
