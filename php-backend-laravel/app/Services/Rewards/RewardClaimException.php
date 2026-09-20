<?php

declare(strict_types=1);

namespace App\Services\Rewards;

/** A claim or rewarded-ad request the player can't make, with the HTTP status and a stable code. */
final class RewardClaimException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly int $status)
    {
        parent::__construct($message);
    }
}
