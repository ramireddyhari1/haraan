<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mid-shift cash drop or expense taken directly out of the register.
 *
 * @property int    $id
 * @property int    $shift_session_id
 * @property int    $user_id
 * @property float  $amount
 * @property string $category
 * @property string|null $reason
 */
class ShiftDrop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_session_id',
        'user_id',
        'amount',
        'category',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ShiftSession::class, 'shift_session_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
