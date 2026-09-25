<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a blocked account tries to obtain a session token.
 *
 * There are six login controllers (password, email OTP, phone OTP, Firebase phone,
 * Google, WhatsApp) and they all mint through {@see \App\Support\JwtService::issueForUser}.
 * Putting the refusal in that one method rather than in each controller means a seventh
 * login path cannot forget it — the same reasoning that put the `tv` claim there.
 *
 * Laravel calls render() on an exception that defines it, so every one of those paths
 * returns this response without needing its own catch block.
 */
final class AccountSuspendedException extends \RuntimeException
{
    public function __construct(string $message = 'This account has been suspended.')
    {
        parent::__construct($message);
    }

    /** Same shape and status as the middleware's refusal, so clients handle one case. */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'account_suspended',
            'message' => 'This account has been suspended. Contact support if you think this is a mistake.',
        ], 403);
    }
}
