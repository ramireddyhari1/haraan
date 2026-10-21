<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PartnerApplications\Pages\ViewPartnerApplication;
use App\Filament\Resources\PartnerApplications\PartnerApplicationResource;
use App\Models\MessageTemplate;
use App\Models\PartnerApplication;
use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Models\Venue;
use App\Services\WhatsAppService;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** The public "Become a partner" form and its review in /control. */
class PartnerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function answers(array $overrides = []): array
    {
        return array_merge([
            'business_type' => 'venue',
            'business_name' => 'Green Field Arena',
            'sports' => ['Cricket', 'Football'],
            'tagline' => '3 floodlit turfs',
            'about' => 'Box cricket and 5-a-side.',
            'area' => 'Near RTC bus stand',
            'city' => 'Kadapa',
            'address' => '12 Main Road, Kadapa 516001',
            'map_link' => 'https://maps.app.goo.gl/abc',
            'latitude' => 14.4673,
            'longitude' => 78.8242,
            'courts_detail' => [
                ['name' => 'Turf A', 'sports' => ['Cricket', 'Football'], 'price' => 800],
                ['name' => 'Turf B', 'sports' => ['Football', 'Tennis'], 'price' => 600],
            ],
            'hours_json' => ['Mon' => ['open' => '06:00', 'close' => '10:00'], 'Sat' => ['open' => '06:00', 'close' => '08:00']],
            'slot_minutes' => 60,
            'cancel_free_hours' => 24,
            'cancel_refund_percent' => 50,
            'amenities' => ['Parking', 'Floodlights'],
            'rules' => ['No smoking'],
            'booking_channels' => ['walk_in', 'whatsapp'],
            'monthly_bookings' => '100_500',
            'goals' => ['online_payments'],
            'contact_name' => 'Ravi Kumar',
            'contact_role' => 'owner',
            'phone' => '98765 43210',
            'email' => 'Ravi@Example.com',
            'call_time' => 'evening',
            'payout_method' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_holder' => 'Green Field Sports LLP',
            'account_number' => '5010 0012 3443 21',
            'account_number_confirmation' => '50100012344321',
            'ifsc_code' => 'hdfc0001234',
            'upi_vpa' => 'Ravi@okhdfcbank',
            'consent' => true,
        ], $overrides);
    }

    private function apply(array $overrides = [], array $photos = []): TestResponse
    {
        return $this->post('/become-a-partner', [
            'payload' => json_encode($this->answers($overrides)),
            'photos' => $photos,
        ], ['Accept' => 'application/json']);
    }

    /** A real 1×1 PNG — this PHP has no GD, so fake()->image() is unavailable. */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

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

    public function test_form_page_renders_with_admin_copy_and_venue_vocabulary(): void
    {
        $this->get('/become-a-partner')
            ->assertOk()
            ->assertSee(PlatformRules::string('partner_onboarding.headline'))
            ->assertSee('Changing room')
            ->assertSee('Non-marking shoes mandatory');
    }

    public function test_submitting_files_every_venue_detail_with_photos(): void
    {
        $res = $this->apply([], [$this->png('a.png'), $this->png('b.png')])->assertCreated();

        $app = PartnerApplication::sole();
        $this->assertSame($app->reference, $res->json('reference'));
        $this->assertMatchesRegularExpression('/^HP-\d{6}$/', $app->reference);
        $this->assertSame('+919876543210', $app->phone);
        $this->assertSame('ravi@example.com', $app->email);
        $this->assertSame(['Cricket', 'Football'], $app->sports);
        $this->assertSame(2, $app->courts);
        $this->assertSame(600, $app->base_price);
        // A court only plays what the venue offers: Tennis is dropped from Turf B.
        $this->assertSame(['Football'], $app->courts_detail[1]['sports']);
        $this->assertSame(['Mon' => ['open' => '06:00', 'close' => '10:00'], 'Sat' => ['open' => '06:00', 'close' => '08:00']], $app->hours_json);
        $this->assertSame(24, $app->cancel_free_hours);
        $this->assertSame(50, $app->cancel_refund_percent);
        $this->assertEqualsWithDelta(14.4673, $app->latitude, 0.0001);
        $this->assertCount(2, $app->images);
        Storage::disk('public')->assertExists($app->images[0]);
    }

    public function test_bank_details_are_stored_encrypted_and_shown_masked(): void
    {
        $this->apply()->assertCreated();

        $app = PartnerApplication::sole();
        $this->assertSame('bank', $app->payout_method);
        $this->assertSame('50100012344321', $app->account_number);
        $this->assertSame('HDFC0001234', $app->ifsc_code);
        $this->assertSame('ravi@okhdfcbank', $app->upi_vpa);
        $this->assertSame('•••• 4321', $app->maskedAccount());
        $this->assertSame('ra••@okhdfcbank', $app->maskedUpi());

        $raw = DB::table('partner_applications')->value('account_number');
        $this->assertStringNotContainsString('50100012344321', (string) $raw);
        $this->assertStringNotContainsString('ravi@', (string) DB::table('partner_applications')->value('upi_vpa'));
    }

    public function test_payout_details_are_checked(): void
    {
        $this->apply(['account_number_confirmation' => '50100012344320'])
            ->assertStatus(422)->assertJsonValidationErrors(['account_number']);
        $this->apply(['ifsc_code' => 'HDFC1001234'])
            ->assertStatus(422)->assertJsonValidationErrors(['ifsc_code']);
        $this->apply(['account_number' => '1234', 'account_number_confirmation' => '1234'])
            ->assertStatus(422)->assertJsonValidationErrors(['account_number']);
        $this->apply(['payout_method' => 'upi', 'upi_vpa' => 'not-a-upi'])
            ->assertStatus(422)->assertJsonValidationErrors(['upi_vpa']);

        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_upi_only_and_later_need_no_bank(): void
    {
        $this->apply(['payout_method' => 'upi', 'bank_name' => '', 'account_number' => '', 'account_number_confirmation' => '', 'ifsc_code' => ''])->assertCreated();
        $upi = PartnerApplication::sole();
        $this->assertSame('upi', $upi->payout_method);
        $this->assertNull($upi->account_number);
        $this->assertSame('ravi@okhdfcbank', $upi->upi_vpa);
        $upi->delete();

        $this->apply(['payout_method' => 'later', 'account_number' => '', 'ifsc_code' => 'junk'])->assertCreated();
        $later = PartnerApplication::sole();
        $this->assertFalse($later->hasPayoutDetails());
        $this->assertNull($later->ifsc_code);
    }

    public function test_whatsapp_number_can_differ_from_the_call_number(): void
    {
        $this->apply(['whatsapp_same' => false, 'whatsapp_phone' => '91234 56789'])->assertCreated();
        $this->assertSame('+919123456789', PartnerApplication::sole()->whatsapp_phone);
        PartnerApplication::query()->delete();

        // The same number twice is just "this number".
        $this->apply(['whatsapp_same' => false, 'whatsapp_phone' => '9876543210'])->assertCreated();
        $this->assertNull(PartnerApplication::sole()->whatsapp_phone);
        PartnerApplication::query()->delete();

        $this->apply(['whatsapp_same' => false, 'whatsapp_phone' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['whatsapp_phone']);
        $this->apply(['whatsapp_same' => false, 'whatsapp_phone' => '12345'])
            ->assertStatus(422)->assertJsonValidationErrors(['whatsapp_phone']);
    }

    public function test_whatsapp_confirmation_goes_to_the_chosen_number_once_approved(): void
    {
        MessageTemplate::query()->where('key', 'partner.application_received')->update(['status' => 'approved']);

        $this->mock(WhatsAppService::class)
            ->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(fn (string $phone, string $name, array $vars): bool => $phone === '+919123456789'
                && $name === 'partner_application_received'
                && $vars[0] === 'Ravi Kumar' && $vars[1] === 'Green Field Arena' && str_starts_with($vars[2], 'HP-') && $vars[3] === '24')
            ->andReturn(true);

        $this->apply(['whatsapp_same' => false, 'whatsapp_phone' => '9123456789'])->assertCreated();

        $this->assertNotNull(PartnerApplication::sole()->whatsapp_confirmed_at);
    }

    public function test_no_whatsapp_while_the_template_is_draft_or_switched_off(): void
    {
        $this->mock(WhatsAppService::class)->shouldNotReceive('sendTemplate');

        // Registered as a draft by the migration: blocked, nothing leaves.
        $this->assertSame('draft', MessageTemplate::query()->where('key', 'partner.application_received')->value('status'));
        $this->apply()->assertCreated();
        $this->assertNull(PartnerApplication::sole()->whatsapp_confirmed_at);

        MessageTemplate::query()->where('key', 'partner.application_received')->update(['status' => 'approved']);
        PlatformRules::save(['partner_onboarding.whatsapp_confirm' => false]);
        $this->apply(['phone' => '9000000001'])->assertCreated();
        $this->assertSame(0, PartnerApplication::query()->whereNotNull('whatsapp_confirmed_at')->count());
    }

    public function test_same_phone_twice_returns_the_first_application(): void
    {
        $first = $this->apply()->json('reference');
        $again = $this->apply(['phone' => '+91 98765 43210'])->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame($first, $again->json('reference'));
        $this->assertSame(1, PartnerApplication::count());
    }

    public function test_validation_names_the_fields(): void
    {
        $this->apply(['business_name' => '', 'consent' => false, 'gstin' => 'nope', 'area' => '', 'courts_detail' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_name', 'consent', 'gstin', 'area', 'courts_detail']);

        $this->apply(['hours_json' => ['Mon' => ['open' => '06:00', 'close' => '06:00']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hours_json']);

        $this->apply(['phone' => '12345'])->assertStatus(422)->assertJsonValidationErrors(['phone']);

        $this->apply([], [UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);

        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_event_hosts_skip_the_place_questions(): void
    {
        $this->apply(['business_type' => 'event', 'area' => '', 'courts_detail' => null, 'hours_json' => null])->assertCreated();

        $app = PartnerApplication::sole();
        $this->assertNull($app->courts_detail);
        $this->assertNull($app->hours_json);
        $this->assertNull($app->amenities);
    }

    public function test_honeypot_is_dropped_silently(): void
    {
        $this->post('/become-a-partner', ['payload' => json_encode($this->answers()), 'website' => 'http://spam.example'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['reference']);

        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_admin_can_switch_the_form_off(): void
    {
        PlatformRules::save(['partner_onboarding.enabled' => false]);

        $this->get('/become-a-partner')->assertOk()->assertSee('Not taking applications right now');
        $this->apply()->assertForbidden();
        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_admin_sees_the_application(): void
    {
        $this->apply();
        $app = PartnerApplication::sole();

        $this->actingAs($this->admin());

        $this->get(PartnerApplicationResource::getUrl('index', panel: 'control'))
            ->assertOk()
            ->assertSee('Green Field Arena');

        $this->get(PartnerApplicationResource::getUrl('view', ['record' => $app], panel: 'control'))
            ->assertOk()
            ->assertSee('Turf A')
            ->assertSee('Floodlights')
            ->assertSee('Free up to 24 h before')
            // Payout details in full for the admin, and the opening is on the audit trail.
            ->assertSee('5010 0012 3443 21')
            ->assertSee('HDFC0001234')
            ->assertSee('ravi@okhdfcbank');

        $this->assertTrue(\App\Models\AdminAction::query()->where('action', 'partner_application.payout_viewed')->exists());
    }

    public function test_application_becomes_a_partner_and_a_draft_venue(): void
    {
        $this->apply([], [$this->png('cover.png')]);
        $app = PartnerApplication::sole();

        $this->actingAs($this->admin());

        Livewire::test(ViewPartnerApplication::class, ['record' => $app->getKey()])
            ->callAction('approve', data: [
                'name' => 'Green Field Arena',
                'email' => 'ravi@example.com',
                'phone' => '+919876543210',
                'partner_type' => 'venue',
            ])
            ->assertHasNoActionErrors();

        $app->refresh();
        $partner = User::where('email', 'ravi@example.com')->sole();
        $this->assertSame('PARTNER', $partner->role);
        $this->assertSame($partner->id, $app->partner_id);
        $this->assertSame(PartnerApplication::STATUS_APPROVED, $app->status);

        // The bank details became their settlement account — unverified, for finance to check.
        $payout = PartnerPayoutAccount::where('partner_id', $partner->id)->sole();
        $this->assertSame('bank', $payout->method);
        $this->assertSame('50100012344321', $payout->account_number);
        $this->assertSame('HDFC0001234', $payout->ifsc_code);
        $this->assertSame('Green Field Sports LLP', $payout->account_holder);
        $this->assertNull($payout->verified_at);

        Livewire::test(ViewPartnerApplication::class, ['record' => $app->getKey()])
            ->callAction('createVenue')
            ->assertHasNoActionErrors();

        $venue = Venue::sole();
        $this->assertSame($venue->id, $app->fresh()->venue_id);
        $this->assertSame('Green Field Arena', $venue->name);
        $this->assertSame('Cricket', $venue->category);
        $this->assertSame('Near RTC bus stand', $venue->location);
        $this->assertSame($partner->id, $venue->partner_id);
        $this->assertSame(600, $venue->price);
        $this->assertSame(['Parking', 'Floodlights'], $venue->amenities);
        $this->assertFalse($venue->is_active);
        $this->assertSame(['Turf A', 'Turf B'], $venue->courts()->orderBy('sort_order')->pluck('name')->all());
        $this->assertGreaterThan(0, $venue->slots()->count());
        $this->assertCount(1, $venue->images);
        Storage::disk('public')->assertExists($venue->images[0]);
    }
}
