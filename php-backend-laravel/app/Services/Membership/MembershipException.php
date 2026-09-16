<?php

declare(strict_types=1);

namespace App\Services\Membership;

use RuntimeException;

/**
 * A member billing failure with the HTTP status it should surface as, and a stable
 * machine-readable code for the app.
 */
final class MembershipException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $errorCode = 'membership_error',
    ) {
        parent::__construct($message, $status);
    }
}
