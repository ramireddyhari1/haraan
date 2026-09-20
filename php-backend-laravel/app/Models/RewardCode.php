<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sponsor code. The code is encrypted at rest (APP_KEY) and hidden from serialisation;
 * it is decrypted only for its owner after they claim it, or for a super-admin through the
 * audited reveal action. Deliberately NOT AuditsAdminChanges: that trait would write the
 * decrypted value into the audit log. Imports and reveals are logged by count/id instead.
 */
final class RewardCode extends Model
{
    protected $fillable = ['pool_id', 'code', 'code_hash', 'grant_id', 'assigned_at'];

    protected $hidden = ['code', 'code_hash'];

    protected $casts = [
        'code' => 'encrypted',
        'assigned_at' => 'datetime',
    ];

    public static function hashOf(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), (string) config('app.key'));
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(RewardCodePool::class, 'pool_id');
    }

    /** "••••1234" — what /control shows. */
    public function masked(): string
    {
        return '••••'.mb_substr((string) $this->code, -4);
    }
}
