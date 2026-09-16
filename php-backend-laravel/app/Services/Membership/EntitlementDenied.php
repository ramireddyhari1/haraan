<?php

declare(strict_types=1);

namespace App\Services\Membership;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by MemberEntitlements when a member's plan doesn't cover what they asked for.
 * Renders the same JSON everywhere so the app handles every gate with one code path.
 */
final class EntitlementDenied extends RuntimeException
{
    public const CODE_UPGRADE_REQUIRED = 'upgrade_required';

    public const CODE_LIMIT_REACHED = 'limit_reached';

    /** The plan allows a set number of choices (e.g. insight sports) and this isn't one of them. */
    public const CODE_SELECTION_REQUIRED = 'selection_required';

    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly string $feature,
        public readonly string $planCode,
        public readonly ?string $upgradePlanCode,
        public readonly ?int $limit = null,
        public readonly ?int $used = null,
        /** @var array<string, mixed> extra fields for the client, e.g. the sport refused */
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse($this->toArray(), 403);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => $this->reason,
            'feature' => $this->feature,
            'plan' => $this->planCode,
            'upgrade_plan' => $this->upgradePlanCode,
            'limit' => $this->limit,
            'used' => $this->used,
        ] + $this->context;
    }
}
