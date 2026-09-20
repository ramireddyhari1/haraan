<?php

declare(strict_types=1);

namespace App\Services\Rewards;

/**
 * Thrown inside a grant transaction to roll it back when the program's budget is spent or its
 * code pool is empty. Never escapes RewardLedger.
 */
final class RewardUnavailable extends \RuntimeException {}
