<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jasa (T2.7) — katalog harga jasa yang dapat dijual sebagai baris
 * transaksi POS. Bukan sistem booking/tiket servis — lihat catatan di
 * migration untuk pembeda dengan modul "Servis" yang di luar MVP.
 */
class Service extends Model
{
    use Auditable;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;
    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'price',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
