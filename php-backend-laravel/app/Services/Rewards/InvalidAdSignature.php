<?php

declare(strict_types=1);

namespace App\Services\Rewards;

/** An AdMob SSV callback whose signature can't be verified. Never unlocks anything. */
final class InvalidAdSignature extends \RuntimeException {}
