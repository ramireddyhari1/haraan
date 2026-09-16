<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Support\Membership\MemberFeature;

/**
 * What one member is entitled to, resolved once: their effective plan, the subscription that
 * gave it to them (if any), and a value for every registered feature.
 */
final class EntitlementSet
{
    /**
     * @param  array<string, array{enabled: bool, limit: int|null, source: string}>  $values
     */
    public function __construct(
        public readonly MemberPlan $plan,
        public readonly ?MemberSubscription $subscription,
        private readonly array $values,
    ) {}

    public function allows(string $key): bool
    {
        $value = $this->value($key);

        if (! $value['enabled']) {
            return false;
        }

        return MemberFeature::isBoolean($key) || $value['limit'] === null || $value['limit'] > 0;
    }

    /** The ceiling for a limit/quota feature: null = unlimited, 0 = none at all. */
    public function limit(string $key): ?int
    {
        $value = $this->value($key);

        return $value['enabled'] ? $value['limit'] : 0;
    }

    public function source(string $key): string
    {
        return $this->value($key)['source'];
    }

    /** @return array<string, array{enabled: bool, limit: int|null, source: string}> */
    public function all(): array
    {
        return $this->values;
    }

    /** @return array{enabled: bool, limit: int|null, source: string} */
    private function value(string $key): array
    {
        if (! MemberFeature::exists($key)) {
            throw new \InvalidArgumentException("Unknown member feature [{$key}].");
        }

        // A feature registered in code but missing from the plan's rows is OFF. The catalogue
        // being incomplete must fail toward the free experience, never toward paid.
        return $this->values[$key] ?? ['enabled' => false, 'limit' => 0, 'source' => 'missing'];
    }
}
