<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\PartnerUpdated;
use App\Models\MessageTemplate;
use App\Models\PartnerManager;
use App\Models\PartnerPayoutAccount;
use App\Models\PartnerUpdate;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\Venue;
use App\Services\WhatsAppService;
use App\Support\JwtService;
use App\Support\PayoutAccountEditor;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Whatever Haraan changes on a partner's account reaches the partner: a feed row, a
 * live event on their private channel, and WhatsApp. Changes the partner made
 * themselves, and anything a customer's action touched, are not news.
 */
final class PartnerUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $finance;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active', 'name' => 'Gouse Shaik', 'phone' => '+91 63001 12233']);
        $this->finance = User::factory()->create(['role' => 'FINANCE', 'name' => 'Ravi Finance']);
        $this->ops = User::factory()->create(['role' => 'OPS', 'name' => 'Priya Raman']);
        PartnerPayoutAccount::create(['partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'Gouse', 'upi_vpa' => '6300112233@ibl']);
    }

    private function as(User $u)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer ' . JwtService::issueForUser($u, $secret));
    }

    public function test_settlement_paid_by_finance_reaches_the_partner_live(): void
    {
        Event::fake([PartnerUpdated::class]);
        $this->actingAs($this->finance);
        $b = PayoutBatch::create(['partner_id' => $this->owner->id, 'amount' => 2400, 'status' => 'processing']);
        $b->update(['status' => 'paid', 'reference' => 'UTR998877', 'processed_at' => now()]);

        $paid = PartnerUpdate::query()->where('kind', 'payout_batch.paid')->first();
        $this->assertNotNull($paid);
        $this->assertSame('₹2,400 settled to your account', $paid->title);
        $this->assertStringContainsString('UTR UTR998877', (string) $paid->body);
        $this->assertSame('Haraan finance', $paid->actor_name);
        $this->assertSame('payouts', $paid->screen);
        $this->assertSame(1, PartnerUpdate::query()->where('kind', 'payout_batch.started')->count());
        Event::assertDispatched(PartnerUpdated::class, fn (PartnerUpdated $e) => $e->update->id === $paid->id
            && $e->broadcastOn()->name === 'private-partner.' . $this->owner->id);
    }

    public function test_manager_editing_the_account_is_named_and_partner_own_edit_is_not_news(): void
    {
        PartnerManager::create(['partner_id' => $this->owner->id, 'manager_id' => $this->ops->id]);
        PartnerUpdate::query()->delete(); // the assignment itself is an update; start clean

        app(PayoutAccountEditor::class)->save($this->owner->id, ['method' => 'upi', 'account_holder' => 'Gouse', 'upi_vpa' => 'new@ybl'], $this->ops);
        $u = PartnerUpdate::query()->where('kind', 'payout_account.changed')->firstOrFail();
        $this->assertSame('Priya Raman (your Haraan manager)', $u->actor_name);

        app(PayoutAccountEditor::class)->save($this->owner->id, ['method' => 'upi', 'account_holder' => 'Gouse', 'upi_vpa' => 'mine@ybl'], $this->owner);
        $this->assertSame(1, PartnerUpdate::query()->where('kind', 'payout_account.changed')->count());
    }

    public function test_manager_assignment_is_told(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        PartnerManager::create(['partner_id' => $this->owner->id, 'manager_id' => $this->ops->id]);
        $this->assertSame('Priya Raman is now your Haraan manager', PartnerUpdate::query()->where('kind', 'manager.assigned')->value('title'));
    }

    public function test_venue_edits_by_haraan_collapse_and_member_actions_are_ignored(): void
    {
        $venue = Venue::query()->create(['name' => 'Vadi Turf', 'location' => 'Vaddeswaram', 'partner_id' => $this->owner->id, 'status' => 'published']);
        PartnerUpdate::query()->delete();

        $this->actingAs($this->ops);
        $venue->update(['hours' => '6 AM – 11 PM']);
        $venue->update(['price' => 600]);
        $rows = PartnerUpdate::query()->where('kind', 'venue.updated')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Haraan updated Vadi Turf', $rows[0]->title);
        $this->assertSame('Changed: prices.', $rows[0]->body);

        PartnerUpdate::query()->delete();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $venue->update(['price' => 700]);
        $this->assertSame(0, PartnerUpdate::query()->count());
    }

    public function test_whatsapp_goes_out_once_per_kind_through_the_template(): void
    {
        $this->seed(MessageTemplateSeeder::class);
        MessageTemplate::query()->where('key', 'partner.account_update')->update(['status' => 'approved']);
        $this->mock(WhatsAppService::class, function ($m): void {
            $m->shouldReceive('sendTemplate')->once()
                ->withArgs(fn ($phone, $name, $vars) => $name === 'partner_account_update' && $vars[0] === 'Gouse' && str_contains($vars[1], 'verified') && $vars[2] === 'Haraan finance')
                ->andReturn(true);
        });

        $acc = PartnerPayoutAccount::query()->where('partner_id', $this->owner->id)->firstOrFail();
        app(PayoutAccountEditor::class)->verify($acc, $this->finance);
        // Same kind again inside half an hour: no second WhatsApp (mock allows once).
        PartnerUpdate::query()->update(['seen_at' => now()]);
        app(PayoutAccountEditor::class)->verify($acc, $this->finance);
        $this->assertNotNull(PartnerUpdate::query()->orderBy('id')->value('whatsapp_sent_at'));
    }

    public function test_feed_api_and_private_channel_auth(): void
    {
        $this->actingAs($this->finance);
        PayoutBatch::create(['partner_id' => $this->owner->id, 'amount' => 500, 'status' => 'processing']);

        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k1', 'broadcasting.connections.reverb.secret' => 's3cret']);

        $this->as($this->owner)->getJson('/api/partner/updates')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.kind', 'payout_batch.started')
            ->assertJsonPath('realtime.channel', 'private-partner.' . $this->owner->id);

        $channel = 'private-partner.' . $this->owner->id;
        $this->as($this->owner)->postJson('/api/partner/realtime/auth', ['socket_id' => '123.456', 'channel_name' => $channel])
            ->assertOk()->assertJsonPath('auth', 'k1:' . hash_hmac('sha256', '123.456:' . $channel, 's3cret'));
        $this->as($this->owner)->postJson('/api/partner/realtime/auth', ['socket_id' => '123.456', 'channel_name' => 'private-partner.999'])
            ->assertForbidden();

        $staff = User::factory()->create(['role' => 'partner', 'parent_partner_id' => $this->owner->id, 'status' => 'active']);
        $this->as($staff)->getJson('/api/partner/updates')->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'data');

        $this->as($this->owner)->postJson('/api/partner/updates/seen')->assertOk();
        $this->as($this->owner)->getJson('/api/partner/updates')->assertJsonPath('unread', 0);
    }
}
