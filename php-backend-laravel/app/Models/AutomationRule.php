<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One "if they say X, reply Y" rule. See the migration for how scope and
 * priority interact.
 */
final class AutomationRule extends Model
{
    public const TRIGGER_KEYWORD = 'keyword';

    public const TRIGGER_FALLBACK = 'fallback';

    protected $fillable = [
        'partner_id', 'channel', 'name', 'trigger_type', 'keywords',
        'match_type', 'reply_body', 'priority', 'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Active rules that could apply to this partner: their own, plus the
     * platform's. Partner rules are returned first so they win a tie.
     *
     * @return Collection<int, self>
     */
    public static function forPartner(?int $partnerId, string $channel = 'whatsapp'): Collection
    {
        return static::query()
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(function ($q) use ($partnerId): void {
                $q->whereNull('partner_id');
                if ($partnerId !== null) {
                    $q->orWhere('partner_id', $partnerId);
                }
            })
            // Partner-owned first, then by priority: a venue's own copy beats the
            // shared default without having to out-prioritise it.
            ->orderByRaw('case when partner_id is null then 1 else 0 end')
            ->orderBy('priority')
            ->get();
    }

    /** Whether this rule fires for the given (already lowercased) message. */
    public function matches(string $text): bool
    {
        if ($this->trigger_type === self::TRIGGER_FALLBACK) {
            return true;
        }

        foreach ($this->keywords ?? [] as $keyword) {
            $keyword = mb_strtolower(trim((string) $keyword));

            if ($keyword === '') {
                continue;
            }

            if ($this->match_type === 'exact' ? $text === $keyword : str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
