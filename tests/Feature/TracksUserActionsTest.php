<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::create('user_action_test_notes', function (Blueprint $table) {
        $table->uuidPrimaryKey();
        $table->string('body');
        $table->uuid('created_by')->nullable();
        $table->uuid('updated_by')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('user_action_test_notes');
    Auth::logout();
});

function userActionTestModel(): Model
{
    return new class extends Model
    {
        use HasUuidV7;
        use TracksUserActions;

        protected $table = 'user_action_test_notes';

        protected $fillable = ['body', 'created_by', 'updated_by'];
    };
}

it('mengisi created_by dan updated_by dari pengguna terautentikasi', function () {
    $userId = (string) Str::uuid7();
    Auth::login(new GenericUser(['id' => $userId, 'remember_token' => null]));

    $note = userActionTestModel()::create(['body' => 'Catatan pertama']);

    expect($note->created_by)->toBe($userId)
        ->and($note->updated_by)->toBe($userId);
});

it('memperbarui hanya updated_by saat dokumen diubah', function () {
    $creatorId = (string) Str::uuid7();
    $editorId = (string) Str::uuid7();

    Auth::login(new GenericUser(['id' => $creatorId, 'remember_token' => null]));
    $note = userActionTestModel()::create(['body' => 'Catatan']);

    Auth::login(new GenericUser(['id' => $editorId, 'remember_token' => null]));
    $note->update(['body' => 'Catatan diperbarui']);

    expect($note->created_by)->toBe($creatorId)
        ->and($note->updated_by)->toBe($editorId);
});

it('membiarkan created_by kosong bila tidak ada pengguna terautentikasi', function () {
    // Seeder `branches` berjalan sebelum akun Owner ada (DB Design §9.2).
    Auth::logout();

    $note = userActionTestModel()::create(['body' => 'Dibuat seeder']);

    expect($note->created_by)->toBeNull()
        ->and($note->updated_by)->toBeNull();
});

it('tidak menimpa created_by yang sudah diset eksplisit', function () {
    $syncedActorId = (string) Str::uuid7();
    $currentUserId = (string) Str::uuid7();

    Auth::login(new GenericUser(['id' => $currentUserId, 'remember_token' => null]));

    $note = userActionTestModel()::create([
        'body' => 'Diterima dari sinkronisasi',
        'created_by' => $syncedActorId,
    ]);

    expect($note->created_by)->toBe($syncedActorId);
});
