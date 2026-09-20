<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bonus XP — reward currency earned from post-match rewards, badges and streaks.
 *
 * It is a SEPARATE ledger on purpose. Competitive XP lives in match_xp_ledger and is
 * aggregated into users.casual_xp / users.ranked_xp by PlayerXpLedgerService; leaderboards rank
 * on those. Nothing in that path reads this table, and nothing here writes to those columns,
 * so no sponsor, ad or membership can buy rank. Append-only: a reversal is a negative row.
 */
final class BonusXpEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bonus_xp_ledger';

    protected $fillable = ['user_id', 'amount', 'reason', 'grant_id', 'match_id', 'dedupe_key', 'created_at'];

    protected $casts = ['amount' => 'integer'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Bonus XP entries are append-only.'));
    }

    public static function totalFor(int $userId): int
    {
        return (int) self::query()->where('user_id', $userId)->sum('amount');
    }
}
