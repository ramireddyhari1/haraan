<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\MemberPlan;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shared setup for the membership suites. The Free / Pro / Hero catalogue itself comes from
 * the migration, so every test runs against the rows production will actually have.
 */
trait MembershipFixtures
{
    protected const KEY_SECRET = 'rzp_test_secret';

    protected const MEMBER_WEBHOOK_SECRET = 'member-whsec-test';

    protected function configureRazorpay(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::KEY_SECRET,
            'services.razorpay.member_webhook_secret' => self::MEMBER_WEBHOOK_SECRET,
            'services.razorpay.webhook_secret' => 'partner-whsec-test',
        ]);
    }

    protected function member(array $attributes = []): User
    {
        $suffix = Str::lower(Str::random(8));

        $user = User::create(array_merge([
            'name' => 'Member ' . $suffix,
            'email' => "member-{$suffix}@haraan.test",
            'password' => bcrypt('secret'),
            'role' => 'USER',
            'status' => 'active',
        ], $attributes));

        return $user;
    }

    /** A member who can reach actionboard.profile-gated routes. */
    protected function player(): User
    {
        $user = $this->member();
        $user->forceFill([
            'state' => 'Andhra Pradesh',
            'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'Batter', 'batting' => 'Right', 'bowling' => 'Right arm medium'],
            'trust_score' => 100,
        ])->save();

        return $user;
    }

    protected function asMember(User $user): static
    {
        $token = JwtService::issueForUser($user, (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')));

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function plan(string $code): MemberPlan
    {
        return MemberPlan::query()->where('code', $code)->firstOrFail();
    }

    /** A price that's linked and on sale — the state /control leaves it in before launch. */
    protected function sellable(string $planCode, string $interval = MemberPlanPrice::INTERVAL_MONTH): MemberPlanPrice
    {
        $price = MemberPlanPrice::query()
            ->where('plan_id', $this->plan($planCode)->id)
            ->where('interval', $interval)
            ->firstOrFail();

        $price->forceFill([
            'razorpay_plan_id' => $price->razorpay_plan_id ?? 'plan_' . $planCode . '_' . $interval,
            'is_active' => true,
        ])->save();

        return $price->refresh();
    }

    /** A paying Razorpay subscription in a given state. */
    protected function paidSubscription(User $user, string $planCode, array $attributes = []): MemberSubscription
    {
        $price = $this->sellable($planCode);

        return MemberSubscription::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $price->plan_id,
            'price_id' => $price->id,
            'provider' => MemberSubscription::PROVIDER_RAZORPAY,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'provider_subscription_id' => 'sub_' . Str::random(14),
            'current_period_start' => Carbon::now()->subDays(5),
            'current_period_end' => Carbon::now()->addDays(25),
            'paid_count' => 1,
        ], $attributes));
    }

    /**
     * A Razorpay subscription entity, as the API and webhooks shape it.
     *
     * @return array<string, mixed>
     */
    protected function entity(string $id, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'entity' => 'subscription',
            'plan_id' => 'plan_pro_month',
            'status' => $status,
            'current_start' => Carbon::now()->subMinute()->getTimestamp(),
            'current_end' => Carbon::now()->addMonth()->getTimestamp(),
            'paid_count' => $status === 'active' ? 1 : 0,
            'notes' => ['kind' => 'member'],
        ], $overrides);
    }

    /** @param array<string, mixed> $payload */
    protected function memberWebhook(array $payload, ?string $eventId = null, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha256', $raw, self::MEMBER_WEBHOOK_SECRET);

        $server = ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'];
        if ($eventId !== null) {
            $server['HTTP_X-Razorpay-Event-Id'] = $eventId;
        }

        return $this->call('POST', '/api/webhooks/razorpay/members', [], [], [], $server, $raw);
    }

    /** @return array<string, mixed> */
    protected function subscriptionEvent(string $event, array $entity, array $payment = [], ?int $createdAt = null): array
    {
        $payload = ['subscription' => ['entity' => $entity]];
        if ($payment !== []) {
            $payload['payment'] = ['entity' => $payment];
        }

        return [
            'entity' => 'event',
            'event' => $event,
            'contains' => array_keys($payload),
            'payload' => $payload,
            'created_at' => $createdAt ?? Carbon::now()->getTimestamp(),
        ];
    }
}
