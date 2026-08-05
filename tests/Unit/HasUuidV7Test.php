<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

/**
 * DB Design §1 / T1.3 AC — "Test unit membuktikan UUID v7 terurut waktu."
 *
 * Tidak menyentuh database: `newUniqueId()` murni membangkitkan nilai,
 * baris disisipkan belakangan oleh `performInsert()` saat `save()`.
 */
function uuidV7TestModel(): Model
{
    return new class extends Model
    {
        use HasUuidV7;
    };
}

it('menjadikan key non-incrementing bertipe string', function () {
    $model = uuidV7TestModel();

    expect($model->getIncrementing())->toBeFalse()
        ->and($model->getKeyType())->toBe('string');
});

it('membangkitkan UUID versi 7, bukan versi 4 acak', function () {
    $uuid = uuidV7TestModel()->newUniqueId();

    // Nibble versi UUID tertulis pada karakter ke-15 representasi string
    // (xxxxxxxx-xxxx-Vxxx-...). orderedUuid() Laravel lama menghasilkan '4' di
    // posisi ini; Str::uuid7() yang dipakai HasUuids sejak Laravel 11
    // menghasilkan '7'.
    expect($uuid)->toBeString()
        ->and(mb_substr($uuid, 14, 1))->toBe('7');
});

it('membangkitkan UUID v7 yang terurut waktu', function () {
    $model = uuidV7TestModel();

    $generated = [];

    for ($i = 0; $i < 20; $i++) {
        $generated[] = $model->newUniqueId();
        usleep(1_000); // 1ms — cukup agar segmen waktu UUIDv7 berbeda antar iterasi
    }

    $sortedLexically = $generated;
    sort($sortedLexically, SORT_STRING);

    // Terurut waktu berarti: urutan pembangkitan == urutan setelah diurutkan
    // secara leksikografis, tanpa perlu mem-parsing UUID sama sekali.
    expect($sortedLexically)->toBe($generated);
});
