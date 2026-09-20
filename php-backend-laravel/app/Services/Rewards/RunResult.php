<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\PlayerBadge;
use App\Models\RewardGrant;

/** What one engine run gave one player — the notifier turns it into one message, not many. */
final class RunResult
{
    /** @var list<RewardGrant> */
    public array $granted = [];

    /** @var list<RewardGrant> */
    public array $unlocked = [];

    /** @var list<RewardGrant> unlocked from verification but still waiting on the player's video */
    public array $waitingForAd = [];

    /** @var list<PlayerBadge> */
    public array $badges = [];

    /** @var array{extended: bool, current: int, best: int, period: string}|null */
    public ?array $streak = null;

    public ?RewardGrant $streakGrant = null;

    public function isEmpty(): bool
    {
        return $this->granted === [] && $this->unlocked === [] && $this->badges === [] && $this->streakGrant === null;
    }
}
