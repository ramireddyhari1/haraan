<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A Google ID token that we refused to trust. The message is user-facing and the
 * status is the HTTP code the caller should return.
 */
final class GoogleAuthException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 401)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
