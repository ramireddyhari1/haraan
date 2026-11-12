<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Why going on air (or listening) was refused, in words the app shows as-is. */
final class CommentaryRefused extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly int $status)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['error' => $this->getMessage(), 'code' => $this->reason], $this->status);
    }
}
