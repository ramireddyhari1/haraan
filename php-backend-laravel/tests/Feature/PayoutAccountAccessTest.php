<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PartnerPayoutAccounts\PartnerPayoutAccountResource;
use App\Models\PartnerManager;
use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Support\JwtService;
use App\Support\PayoutAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may see and change a partner's settlement destination: the owner (not their
 * desk staff), the partner's Haraan manager (only their own partners, can't verify),
 * and finance/admin. Every change clears verification and says who made it.
 */
final class PayoutAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private function as(User $u)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($u, $secret));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->manager = User::factory()->create(['role' => 'OPS', 'name' => 'Priya Raman']);
        PartnerManager::create(['partner_id' => $this->owner->id, 'manager_id' => $this->manager->id]);
    }

    public function test_owner_saves_and_sees_it_was_them(): void
    {
        $this->as($this->owner)->postJson('/api/partner/payouts/account', [
            'method' => 'upi', 'accountHolder' => 'Vadi Sports', 'upiVpa' => 'vadi@okhdfc',
        ])->assertOk();

        $this->as($this->owner)->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('account.verified', false)
            ->assertJsonPath('account.changed.name', 'You')
            ->assertJsonPath('account.changed.kind', 'partner')
            ->assertJsonPath('can_edit', true)
            ->assertJsonPath('manager.name', 'Priya Raman');
    }

    public function test_desk_staff_cannot_redirect_the_money(): void
    {
        $staff = User::factory()->create(['role' => 'partner', 'parent_partner_id' => $this->owner->id, 'status' => 'active', 'staff_permissions' => ['reports']]);

        $this->as($staff)->postJson('/api/partner/payouts/account', [
            'method' => 'upi', 'accountHolder' => 'Thief', 'upiVpa' => 'thief@ybl',
        ])->assertForbidden();
        $this->assertDatabaseMissing('partner_payout_accounts', ['upi_vpa' => 'thief@ybl']);
    }

    public function test_manager_edit_clears_verification_and_is_named_to_the_partner(): void
    {
        $acc = PartnerPayoutAccount::create(['partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'Vadi', 'upi_vpa' => 'old@ybl', 'verified_at' => now()]);

        app(PayoutAccountEditor::class)->save($this->owner->id, [
            'method' => 'bank', 'account_holder' => 'Vadi Sports', 'bank_name' => 'HDFC', 'account_number' => '50100012344321', 'ifsc_code' => 'hdfc0001234',
        ], $this->manager);

        $acc->refresh();
        $this->assertNull($acc->verified_at);
        $this->assertSame('HDFC0001234', $acc->ifsc_code);
        $this->assertNull($acc->upi_vpa);
        $this->assertSame('manager', $acc->updated_by_kind);

        $this->as($this->owner)->getJson('/api/partner/payouts')
            ->assertJsonPath('account.changed.name', 'Priya Raman')
            ->assertJsonPath('account.changed.kind', 'manager')
            ->assertJsonPath('account.masked', 'HDFC •••• 4321');
    }

    public function test_manager_sees_only_their_partners_and_cannot_verify(): void
    {
        $other = User::factory()->create(['role' => 'partner', 'status' => 'active']);
        PartnerPayoutAccount::create(['partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'A', 'upi_vpa' => 'a@ybl']);
        PartnerPayoutAccount::create(['partner_id' => $other->id, 'method' => 'upi', 'account_holder' => 'B', 'upi_vpa' => 'b@ybl']);

        $this->actingAs($this->manager);
        $this->assertTrue(PartnerPayoutAccountResource::canAccess());
        $this->assertSame([$this->owner->id], PartnerPayoutAccountResource::getEloquentQuery()->pluck('partner_id')->map(fn ($i) => (int) $i)->all());
        $this->assertTrue(PayoutAccountEditor::staffMayEdit($this->manager, $this->owner->id));
        $this->assertFalse(PayoutAccountEditor::staffMayEdit($this->manager, $other->id));
        $this->assertFalse(PayoutAccountEditor::staffMayVerify($this->manager));
    }

    public function test_unassigned_ops_user_has_no_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'OPS']));
        $this->assertFalse(PartnerPayoutAccountResource::canAccess());
    }

    public function test_finance_edit_is_shown_as_haraan_finance(): void
    {
        $finance = User::factory()->create(['role' => 'FINANCE', 'name' => 'Ravi Kumar']);
        app(PayoutAccountEditor::class)->save($this->owner->id, ['method' => 'upi', 'account_holder' => 'Vadi', 'upi_vpa' => 'Vadi@OKSBI'], $finance);

        $this->assertSame('vadi@oksbi', PartnerPayoutAccount::where('partner_id', $this->owner->id)->value('upi_vpa'));
        $this->as($this->owner)->getJson('/api/partner/payouts')
            ->assertJsonPath('account.changed.name', 'Haraan finance');
    }

    public function test_finance_sees_the_full_destination_on_partner_settlements(): void
    {
        $finance = User::factory()->create(['role' => 'FINANCE']);
        PartnerPayoutAccount::create(['partner_id' => $this->owner->id, 'method' => 'upi', 'account_holder' => 'Vadi Sports', 'upi_vpa' => '6300112233@ibl']);
        \App\Models\PayoutBatch::create(['partner_id' => $this->owner->id, 'amount' => 20, 'status' => 'processing']);

        $this->actingAs($finance);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Livewire\Livewire::test(\App\Filament\Resources\PayoutBatches\Pages\ListPayoutBatches::class)
            ->assertSee('6300112233@ibl')
            ->assertSee('Vadi Sports')
            ->assertDontSee('63••••');
    }
}
