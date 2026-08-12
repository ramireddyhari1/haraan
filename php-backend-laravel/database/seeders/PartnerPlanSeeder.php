<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PartnerPlan;
use Illuminate\Database\Seeder;

/**
 * The plan catalogue.
 *
 * Only Starter is active. Growth and Pro are seeded INACTIVE and priced at zero
 * on purpose: their real numbers have to come from measured conversation volume
 * (that's what the messaging ledger is for), and a ₹0 paid tier sitting live in
 * a picker is how someone accidentally gets Pro for nothing. Set the price and
 * quota, then flip is_active.
 *
 * Idempotent — safe to re-run, and it won't stomp numbers set in the admin.
 */
class PartnerPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Ticket delivery and booking confirmations. Always included, always free.',
                'price_inr' => 0,
                'included_conversations' => 0,
                'features' => [],
                'is_default' => true,
                'is_active' => true,
                'sort' => 10,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'description' => 'Inbound auto-replies: keyword answers and an away message.',
                'price_inr' => 0,
                'included_conversations' => 0,
                'features' => [PartnerPlan::FEATURE_INBOUND],
                'is_default' => false,
                'is_active' => false,
                'sort' => 20,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Everything in Growth plus outbound journeys — reminders and review requests.',
                'price_inr' => 0,
                'included_conversations' => 0,
                'features' => [PartnerPlan::FEATURE_INBOUND, PartnerPlan::FEATURE_JOURNEYS],
                'is_default' => false,
                'is_active' => false,
                'sort' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            // firstOrCreate, not updateOrCreate: once a plan exists, its price and
            // quota belong to whoever set them in the admin, not to this file.
            PartnerPlan::query()->firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
