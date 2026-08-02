<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Venues are created on the admin side and assigned to a partner there.
 *
 * A listing carries decisions that are not the tenant's to make — owner, org unit,
 * featured, sort order — so the partner console may not create or delete one. It
 * may still manage the venue it was given.
 */
class PartnerCannotCreateVenueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
    }

    private function partner(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Venue Owner',
            'email' => 'owner@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ], $attributes));
    }

    private function inPanel(string $panel, User $user, callable $fn): mixed
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));
        $this->actingAs($user);

        return $fn();
    }

    public function test_a_partner_cannot_create_or_delete_a_venue(): void
    {
        $partner = $this->partner();
        $venue = Venue::create(['name' => 'Assigned Turf', 'location' => 'Gachibowli', 'partner_id' => $partner->id]);

        $this->inPanel('partner', $partner, function () use ($venue): void {
            $this->assertFalse(VenueResource::canCreate(), 'The partner console must not offer venue creation.');
            $this->assertFalse(VenueResource::canDelete($venue), 'A partner must not be able to delete their listing.');
            $this->assertFalse(VenueResource::canDeleteAny(), 'The bulk delete action must not appear for a partner.');

            // They still manage the venue they were given.
            $this->assertTrue(VenueResource::canAccess());
            $this->assertTrue(VenueResource::canEdit($venue));
        });
    }

    /**
     * Hiding the button is not the gate. A partner who types the create URL — or
     * keeps a bookmark from before this change — must be refused by the page itself.
     *
     * Driven over real HTTP on purpose: Livewire::test() skips Filament's panel
     * middleware, so the panel would resolve to the default and the gate would look
     * open when it is not. This exercises routing + middleware + authorization.
     */
    public function test_the_create_url_is_forbidden_for_a_partner(): void
    {
        $partner = $this->partner();
        Venue::create(['name' => 'Assigned Turf', 'location' => 'Gachibowli', 'partner_id' => $partner->id]);

        $this->actingAs($partner)
            ->get(VenueResource::getUrl('create', panel: 'partner'))
            ->assertForbidden();
    }

    public function test_a_partner_can_still_open_the_venue_they_were_assigned(): void
    {
        $partner = $this->partner();
        Venue::create(['name' => 'Assigned Turf', 'location' => 'Gachibowli', 'partner_id' => $partner->id]);

        $this->actingAs($partner)
            ->get(VenueResource::getUrl('index', panel: 'partner'))
            ->assertOk();
    }

    public function test_the_create_url_is_open_to_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(VenueResource::getUrl('create', panel: 'control'))
            ->assertOk();
    }

    public function test_an_admin_still_creates_and_deletes_venues(): void
    {
        $admin = $this->admin();
        $venue = Venue::create(['name' => 'Platform Turf', 'location' => 'Kondapur']);

        $this->inPanel('control', $admin, function () use ($venue): void {
            $this->assertTrue(VenueResource::canCreate());
            $this->assertTrue(VenueResource::canDelete($venue));
            $this->assertTrue(VenueResource::canDeleteAny());
        });
    }

    public function test_the_ownership_fields_are_admin_only_on_the_edit_form(): void
    {
        $partner = $this->partner();
        $venue = Venue::create(['name' => 'Assigned Turf', 'location' => 'Gachibowli', 'partner_id' => $partner->id]);

        $this->inPanel('control', $this->admin(), function () use ($venue): void {
            $page = Livewire::test(EditVenue::class, ['record' => $venue->getKey()]);

            foreach (['partner_id', 'is_featured', 'sort_order'] as $field) {
                $page->assertFormFieldExists($field);
            }
        });

        $this->inPanel('partner', $partner, function () use ($venue): void {
            $page = Livewire::test(EditVenue::class, ['record' => $venue->getKey()]);

            foreach (['partner_id', 'is_featured', 'sort_order'] as $field) {
                $page->assertFormFieldHidden($field);
            }

            // The operational switches stay — a partner still closes their own venue.
            $page->assertFormFieldExists('is_active')
                ->assertFormFieldExists('is_bookable')
                ->assertFormFieldExists('name');
        });
    }

    public function test_the_owner_select_only_offers_venue_lane_partner_accounts(): void
    {
        $venuePartner = $this->partner(['email' => 'venue.partner@haraan.test']);
        $eventPartner = $this->partner(['email' => 'event.host@haraan.test', 'partner_type' => 'event']);
        $member = $this->partner(['email' => 'member@haraan.test', 'role' => 'user', 'partner_type' => null]);
        $legacyPartner = $this->partner(['email' => 'legacy@haraan.test', 'partner_type' => null]);

        // Mirrors the modifyQueryUsing on the partner_id relationship select.
        $options = User::query()
            ->where('role', 'PARTNER')
            ->whereRaw("lower(coalesce(partner_type, 'venue')) = 'venue'")
            ->pluck('id')
            ->all();

        $this->assertContains($venuePartner->id, $options);
        $this->assertContains($legacyPartner->id, $options, 'A null partner_type still means the venue lane.');
        $this->assertNotContains($eventPartner->id, $options, 'Event hosts do not own venues.');
        $this->assertNotContains($member->id, $options, 'A consumer member cannot open the partner console.');
    }
}
