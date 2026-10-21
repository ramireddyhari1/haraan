<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\PartnerSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PartnerSettlementCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retained_settlement_commission_is_not_left_available_for_a_second_payout(): void
    {
        $partner = User::factory()->create(['role' => 'PARTNER', 'partner_type' => 'venue']);

        PayoutBatch::create([
            'partner_id' => $partner->id,
            'amount' => 900,
            'commission_percent' => 10,
            'commission_amount' => 100,
            'status' => 'paid',
        ]);

        $settlement = app(PartnerSettlement::class);

        $this->assertSame(1000.0, $settlement->settled($partner->id));
        $this->assertSame(0.0, $settlement->inFlight($partner->id));
    }

    public function test_processing_commission_reserves_the_full_settlement_balance(): void
    {
        $partner = User::factory()->create(['role' => 'PARTNER', 'partner_type' => 'venue']);

        PayoutBatch::create([
            'partner_id' => $partner->id,
            'amount' => 760,
            'commission_percent' => 5,
            'commission_amount' => 40,
            'status' => 'processing',
        ]);

        $this->assertSame(800.0, app(PartnerSettlement::class)->inFlight($partner->id));
    }
}
