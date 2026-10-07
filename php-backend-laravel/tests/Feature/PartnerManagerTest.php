<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Models\AppSetting;
use App\Models\PartnerManager;
use App\Models\User;
use App\Support\JwtService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An admin assigns a Haraan employee to a partner in /control; the partner app and
 * web read it from GET /api/partner/manager for the card on top of Venues.
 */
final class PartnerManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->employee = User::factory()->create(['role' => 'OPS', 'name' => 'Priya Raman', 'phone' => '+91 99999 11111']);
    }

    private function getAs(User $user)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($user, $secret))
            ->getJson('/api/partner/manager');
    }

    public function test_nobody_assigned_returns_null_with_support_number(): void
    {
        AppSetting::set('support_whatsapp', '+91 90000 00000', 'branding');

        $this->getAs($this->partner)->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('support_whatsapp', '+91 90000 00000');
    }

    public function test_assigned_manager_card_uses_support_number_not_personal_phone(): void
    {
        AppSetting::set('support_whatsapp', '+91 90000 00000', 'branding');
        PartnerManager::create(['partner_id' => $this->partner->id, 'manager_id' => $this->employee->id, 'hours' => 'Mon–Sat · 9–8']);

        $this->getAs($this->partner)->assertOk()
            ->assertJsonPath('data.name', 'Priya Raman')
            ->assertJsonPath('data.title', PartnerManager::DEFAULT_TITLE)
            ->assertJsonPath('data.hours', 'Mon–Sat · 9–8')
            ->assertJsonPath('data.phone', '+919000000000')
            ->assertJsonPath('data.show_call', true);
    }

    public function test_admin_phone_override_and_hidden_card(): void
    {
        $row = PartnerManager::create([
            'partner_id' => $this->partner->id, 'manager_id' => $this->employee->id,
            'phone' => '98480 12345', 'show_whatsapp' => false,
        ]);

        $this->getAs($this->partner)
            ->assertJsonPath('data.phone', '9848012345')
            ->assertJsonPath('data.show_whatsapp', false);

        $row->update(['is_visible' => false]);
        $this->getAs($this->partner)->assertJsonPath('data', null);
    }

    public function test_no_number_anywhere_hides_call_and_whatsapp(): void
    {
        PartnerManager::create(['partner_id' => $this->partner->id, 'manager_id' => $this->employee->id]);

        $this->getAs($this->partner)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.show_call', false)
            ->assertJsonPath('data.show_whatsapp', false)
            ->assertJsonPath('data.show_chat', true);
    }

    public function test_desk_staff_see_their_owners_manager(): void
    {
        PartnerManager::create(['partner_id' => $this->partner->id, 'manager_id' => $this->employee->id]);
        $desk = User::factory()->create([
            'role' => 'partner', 'partner_type' => 'venue', 'status' => 'active',
            'parent_partner_id' => $this->partner->id,
        ]);

        $this->getAs($desk)->assertOk()->assertJsonPath('data.name', 'Priya Raman');
    }

    public function test_admin_assigns_manager_from_partner_edit_page(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        Livewire::test(EditPartner::class, ['record' => $this->partner->getRouteKey()])
            ->fillForm([
                'partnerManager.manager_id' => $this->employee->id,
                'partnerManager.hours' => 'Daily · 10–7',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = PartnerManager::where('partner_id', $this->partner->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->employee->id, $row->manager_id);
        $this->assertSame('Daily · 10–7', $row->hours);
    }
}
