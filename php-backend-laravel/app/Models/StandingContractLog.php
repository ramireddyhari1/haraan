<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of administrative operations performed on a standing contract.
 *
 * @property int $id
 * @property int $standing_contract_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $details
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon $created_at
 */
class StandingContractLog extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'standing_contract_id',
        'user_id',
        'action',
        'details',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StandingContract::class, 'standing_contract_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
