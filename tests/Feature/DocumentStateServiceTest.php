<?php

declare(strict_types=1);

use App\Application\Services\DocumentStateService;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Exceptions\DocumentStateException;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Support\MigrationMacros;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T1.8 AC — AC-02: "Dokumen final tidak dapat diedit"; void tanpa alasan
 * ditolak. Diuji lewat tabel dan model perancah murni (pola sama dengan
 * BranchScopeTest T1.3) karena T1.8 membangun trait + service yang dipakai
 * ulang oleh tabel dokumen nyata mulai T3.5/T4.3/T5.1 — bukan tabel itu
 * sendiri.
 */
beforeEach(function () {
    Schema::create('document_state_test_documents', function (Blueprint $table) {
        $table->uuidPrimaryKey();
        $table->string('title');
        $table->decimal('total_amount', 18, 2)->default(0);
        $table->documentStateColumns();
        $table->timestamps();
    });
    DB::statement(MigrationMacros::documentStateVoidCheckSql('document_state_test_documents'));

    $this->service = new DocumentStateService;
});

afterEach(function () {
    Schema::dropIfExists('document_state_test_documents');
});

function documentStateTestModel(): Model
{
    return new class extends Model
    {
        use HasDocumentState;
        use HasUuidV7;

        protected $table = 'document_state_test_documents';

        protected $fillable = ['title', 'total_amount', 'state', 'finalized_at', 'voided_at', 'voided_by', 'void_reason'];
    };
}

it('dokumen baru berstatus draft secara default', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);

    expect($document->state)->toBe(DocumentState::Draft);
});

it('dokumen draft dapat diedit bebas', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);

    $document->update(['title' => 'Dokumen A (revisi)', 'total_amount' => 50000]);

    expect($document->fresh()->title)->toBe('Dokumen A (revisi)');
});

it('finalize menaikkan draft ke final dan mengisi finalized_at', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);

    $this->service->finalize($document);

    expect($document->state)->toBe(DocumentState::Final)
        ->and($document->finalized_at)->not->toBeNull();
});

it('finalize menolak dokumen yang sudah final', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    expect(fn () => $this->service->finalize($document))
        ->toThrow(DocumentStateException::class);
});

it('AC-02 — dokumen final menolak perubahan langsung', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    expect(fn () => $document->update(['title' => 'Diedit diam-diam']))
        ->toThrow(DocumentStateException::class);

    expect($document->fresh()->title)->toBe('Dokumen A');
});

it('AC-02 — void tanpa alasan ditolak', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    expect(fn () => $this->service->void($document, ''))
        ->toThrow(DocumentStateException::class);

    expect(fn () => $this->service->void($document, '   '))
        ->toThrow(DocumentStateException::class);

    expect($document->fresh()->state)->toBe(DocumentState::Final);
});

it('void dokumen final dengan alasan mengisi seluruh field void (C7)', function () {
    $user = makeTestUser();
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    $this->service->void($document, 'Salah input nominal', $user->id);

    $fresh = $document->fresh();
    expect($fresh->state)->toBe(DocumentState::Void)
        ->and($fresh->voided_at)->not->toBeNull()
        ->and($fresh->voided_by)->toBe($user->id)
        ->and($fresh->void_reason)->toBe('Salah input nominal');
});

it('void menolak dokumen yang masih draft', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);

    expect(fn () => $this->service->void($document, 'Alasan apa pun'))
        ->toThrow(DocumentStateException::class);
});

it('dokumen void bersifat terminal — tidak dapat diubah lagi', function () {
    $user = makeTestUser();
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);
    $this->service->void($document, 'Alasan void', $user->id);

    expect(fn () => $document->update(['title' => 'Coba edit lagi']))
        ->toThrow(DocumentStateException::class);

    expect(fn () => $this->service->void($document, 'Void kedua'))
        ->toThrow(DocumentStateException::class);
});

it('transisi ke void menolak perubahan field lain yang menumpang', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    expect(fn () => $document->update([
        'state' => DocumentState::Void,
        'voided_at' => now(),
        'void_reason' => 'Alasan sah',
        'title' => 'Menumpang koreksi diam-diam',
    ]))->toThrow(DocumentStateException::class);

    expect($document->fresh()->title)->toBe('Dokumen A');
});

it('C7 — database menolak state void tanpa voided_at/voided_by/void_reason terisi', function () {
    $document = documentStateTestModel()::create(['title' => 'Dokumen A']);
    $this->service->finalize($document);

    // Melewati trait & service untuk membuktikan constraint C7 pada database
    // itu sendiri, bukan hanya penjagaan di lapisan aplikasi.
    expect(fn () => DB::table('document_state_test_documents')
        ->where('id', $document->id)
        ->update(['state' => DocumentState::Void->value]))
        ->toThrow(QueryException::class);
});
