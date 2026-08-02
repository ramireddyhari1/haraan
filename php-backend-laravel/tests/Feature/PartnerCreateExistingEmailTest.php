<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Models\User;
use App\Support\PartnerAccountResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Creating a partner with an email that is already a Haraan member.
 *
 * haraan.app/login and haraan.app/partner/login are two doors into one `users`
 * table, so that email cannot become a second row. The Create Partner form used to
 * dead-end on "The email has already been taken"; it now upgrades the member in
 * place, keeping their id and history, while still refusing the two cases that
 * must never be merged (an existing partner, an internal staff login).
 */
class PartnerCreateExistingEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('control'));

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->actingAs($admin);
    }

    private function member(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Agent Cat',
            'email' => 'AgentCat31@gmail.com',
            'password' => Hash::make('member-password'),
            'role' => 'user',
            'status' => 'active',
        ], $attributes));
    }

    public function test_resolver_matches_email_case_insensitively(): void
    {
        $member = $this->member();

        $this->assertTrue($member->is(PartnerAccountResolver::findByEmail('agentcat31@gmail.com')));
        $this->assertTrue($member->is(PartnerAccountResolver::findByEmail('  AGENTCAT31@GMAIL.COM ')));
        $this->assertNull(PartnerAccountResolver::findByEmail('nobody@haraan.test'));
        $this->assertNull(PartnerAccountResolver::findByEmail(''));
    }

    public function test_a_plain_member_may_be_upgraded_but_partners_and_staff_may_not(): void
    {
        $this->assertNull(PartnerAccountResolver::blockReason($this->member()));

        $partner = $this->member(['email' => 'partner@haraan.test', 'role' => 'PARTNER']);
        $this->assertStringContainsString('already a partner', (string) PartnerAccountResolver::blockReason($partner));

        $ops = $this->member(['email' => 'ops@haraan.test', 'role' => 'OPS']);
        $this->assertStringContainsString('internal staff', (string) PartnerAccountResolver::blockReason($ops));

        $superAdmin = $this->member(['email' => 'boss@haraan.test', 'role' => 'ADMIN']);
        $this->assertStringContainsString('internal staff', (string) PartnerAccountResolver::blockReason($superAdmin));
    }

    public function test_creating_a_partner_with_a_member_email_upgrades_that_account_in_place(): void
    {
        $member = $this->member();
        $originalId = $member->id;
        $originalHash = $member->password;

        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'TEST VENUE 1',
                'email' => 'agentcat31@gmail.com',
                'phone' => '9542270851',
                'password' => null,
                'partner_type' => 'venue',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::whereRaw('lower(email) = ?', ['agentcat31@gmail.com'])->count(),
            'Upgrading must not create a second row for the same email.');

        $member->refresh();

        $this->assertSame($originalId, $member->id, 'The member keeps their id, so every foreign key stays valid.');
        $this->assertTrue($member->hasRoleEither(['PARTNER']));
        $this->assertSame('venue', $member->partner_type);
        $this->assertSame('TEST VENUE 1', $member->name);
        $this->assertSame('9542270851', $member->phone);
        $this->assertSame($originalHash, $member->password,
            'A blank password field must keep the password the member already signs in with.');
        $this->assertTrue(Hash::check('member-password', $member->password));
    }

    public function test_a_typed_password_replaces_the_members_own(): void
    {
        $member = $this->member();

        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'TEST VENUE 1',
                'email' => 'agentcat31@gmail.com',
                'password' => 'brand-new-pass',
                'partner_type' => 'venue',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member->refresh();

        $this->assertTrue(Hash::check('brand-new-pass', $member->password));
    }

    public function test_an_unknown_email_still_creates_a_brand_new_partner(): void
    {
        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Fresh Turf',
                'email' => 'fresh@haraan.test',
                'password' => 'turf-pass',
                'partner_type' => 'venue',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::whereRaw('lower(email) = ?', ['fresh@haraan.test'])->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRoleEither(['PARTNER']));
        $this->assertTrue(Hash::check('turf-pass', $created->password));
    }

    public function test_an_existing_partner_email_is_still_rejected(): void
    {
        $this->member(['email' => 'taken@haraan.test', 'role' => 'PARTNER']);

        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => 'taken@haraan.test',
                'password' => 'x-password',
                'partner_type' => 'venue',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_an_internal_staff_email_is_still_rejected(): void
    {
        $this->member(['email' => 'finance@haraan.test', 'role' => 'FINANCE']);

        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Not allowed',
                'email' => 'finance@haraan.test',
                'password' => 'x-password',
                'partner_type' => 'venue',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertFalse(User::whereRaw('lower(email) = ?', ['finance@haraan.test'])->first()->hasRoleEither(['PARTNER']));
    }

    /**
     * The custom rule replaced `unique(ignoreRecord: true)`, so editing must still
     * accept a partner's own email and still reject somebody else's.
     */
    public function test_editing_a_partner_keeps_its_own_email_and_rejects_another_accounts(): void
    {
        $partner = $this->member(['email' => 'owner@haraan.test', 'role' => 'PARTNER', 'partner_type' => 'venue']);
        $this->member(['email' => 'someone.else@haraan.test']);

        Livewire::test(EditPartner::class, ['record' => $partner->getKey()])
            ->fillForm(['name' => 'Renamed Venue'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Venue', $partner->refresh()->name);

        Livewire::test(EditPartner::class, ['record' => $partner->getKey()])
            ->fillForm(['email' => 'someone.else@haraan.test'])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertSame('owner@haraan.test', $partner->refresh()->email);
    }
}
