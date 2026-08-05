<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Primary key UUID v7, dibangkitkan aplikasi.
 *
 * DB Design 1 / HS-ARCH-RIGHTCLICK-v1.1 §5.1 — primary key harus unik lintas
 * tiga node tanpa koordinasi; auto-increment akan bertabrakan saat transaksi
 * dua cabang digabung di HQ. UUID v7 bersifat time-ordered sehingga tidak
 * menyebabkan fragmentasi indeks seperti UUID v4.
 *
 * Laravel 12 sudah membangkitkan UUID v7 asli lewat `Str::uuid7()` di dalam
 * `Illuminate\Database\Eloquent\Concerns\HasUuids` (bukan `orderedUuid()`,
 * yang merupakan COMB berbasis UUID v4). Trait ini mengganti nama pemakaian
 * di seluruh model transaksi agar niat "UUID v7 wajib" tertulis eksplisit di
 * lapisan Infrastructure kita, bukan bergantung diam-diam pada detail
 * implementasi framework.
 */
trait HasUuidV7
{
    use HasUuids;
}
