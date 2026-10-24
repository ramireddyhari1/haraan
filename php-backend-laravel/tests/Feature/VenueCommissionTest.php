<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\ViewVenue;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\Venue;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Haraan's commission is set per venue in /control (blank = platform default) and the
 * partner reads it; Haraan's named fees on the player's bill never reach the partner.
 */
final class VenueCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::create([
            'name'         => 'Venue Owner',
            'email'        => 'owner' . uniqid() . '@haraan.test',
            'password'     => bcrypt('Password123!'),
            'role'         => 'PARTNER',
            'partner_type' => 'venue',
            'status'       => 'ACTIVE',
        ]);
    }

    private function venue(User $owner, array $attributes = []): Venue
    {
        return Venue::create(array_merge([
            'name'       => 'Blasters Arena',
            'category'   => 'Cricket',
            'location'   => 'Tadepalli',
            'city'       => 'Vijayawada',
            'price'      => 900,
            'is_active'  => true,
            'partner_id' => $owner->id,
            'fees'       => [['label' => 'GATEWAY FEE', 'type' => 'percent', 'value' => 2]],
        ], $attributes));
    }

    public function test_a_venue_rate_overrides_the_platform_default_and_zero_is_free(): void
    {
        AppSetting::set(PlatformRules::storageKey('fees.venue_commission_percent'), '8');
        $owner = $this->owner();

        $this->assertSame(8.0, $this->venue($owner)->commissionPercent());
        $this->assertSame(12.5, $this->venue($owner, ['commission_percent' => 12.5])->commissionPercent());

        $free = $this->venue($owner, ['commission_percent' => 0]);
        $this->assertSame(0.0, $free->commissionPercent());
        $this->assertSame('Free — no commission', $free->commissionLabel());
    }

    public function test_the_partner_api_tells_the_partner_their_commission(): void
    {
        $owner = $this->owner();
        $this->venue($owner, ['commission_percent' => 10]);

        $this->withHeader('Authorization', 'Bearer ' . JwtService::issueForUser(
            $owner,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        ))
            ->getJson('/api/partner/venues')
            ->assertOk()
            ->assertJsonPath('data.0.commission_percent', 10)
            ->assertJsonPath('data.0.commission_label', '10% of each online booking');
    }

    public function test_control_sees_the_named_fees_and_the_partner_does_not(): void
    {
        $owner = $this->owner();
        $venue = $this->venue($owner, ['commission_percent' => 5]);

        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs(User::create([
            'name' => 'Ops', 'email' => 'ops@haraan.test', 'password' => bcrypt('Password123!'),
            'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]));
        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->assertSee('GATEWAY FEE')
            ->assertSee('Haraan commission')
            ->assertSee('5% of each online booking');

        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($owner);
        Livewire::test(ViewVenue::class, ['record' => $venue->id])
            ->assertDontSee('GATEWAY FEE')
            ->assertDontSee('Other fees charged to the customer')
            ->assertSee('5% of each online booking');
    }
}
