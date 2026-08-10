<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use Auditable;

    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'partner_type',
        'tax_id',
        'phone',
        'email',
        'address',
        'city',
        'contact_person',
        'credit_limit',
        'payment_terms_days',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'partner_type' => PartnerType::class,
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
