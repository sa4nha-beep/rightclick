<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'pic_name',
        'is_hq',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_hq' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'default_branch_id');
    }

    /**
     * @return HasMany<UserBranch>
     */
    public function userBranches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }
}
