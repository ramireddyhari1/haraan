<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Exceptions\PaymentsPaused;
use App\Filament\Pages\OperationsPage;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
use App\Support\AiGate;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * /control → Operations. Every switch is enforced on the server — these tests go through the
 * real endpoints and services, never a client flag.
 */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'user'): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JwtService::issue(['sub' => $user->id], (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')))];
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'partner_id' => $this->user('PARTNER')->id, 'title' => 'Gig', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad', 'date' => now()->addDays(5),
            'time' => '19:00', 'price' => 500, 'total_slots' => 20, 'available_slots' => 20,
            'images' => [], 'status' => 'published',
        ], $overrides));
    }

    // ── Maintenance ─────────────────────────────────────────────────────────────

    public function test_maintenance_blocks_the_app_api_and_website_but_not_config_or_webhooks(): void
    {
        PlatformRules::save(['ops.maintenance_mode' => true, 'ops.maintenance_message' => 'Back at 6am.']);

        $this->getJson('/api/events')->assertStatus(503)->assertJsonPath('error', 'maintenance')->assertJsonPath('message', 'Back at 6am.');
        $this->get('/events')->assertStatus(503)->assertSee('Back at 6am.');

        // The app must still learn WHY — and money in flight must still land.
        $this->getJson('/api/config')->assertOk()->assertJsonPath('operations.maintenance.enabled', true);
        $this->postJson('/api/webhooks/razorpay', [])->assertDontSee('Back at 6am.');
    }

    public function test_maintenance_off_changes_nothing(): void
    {
        $this->getJson('/api/events')->assertOk();
        $this->getJson('/api/config')->assertOk()
            ->assertJsonPath('operations.maintenance.enabled', false)
            ->assertJsonPath('operations.payments_enabled', true);
    }

    // ── App version ─────────────────────────────────────────────────────────────

    public function test_minimum_version_blocks_older_apps_only(): void
    {
        PlatformRules::save(['app.min_version' => '1.0.40']);

        $this->withHeader('X-App-Version', '1.0.38')->getJson('/api/events')
            ->assertStatus(426)->assertJsonPath('error', 'update_required')->assertJsonPath('min_version', '1.0.40');
        $this->withHeader('X-App-Version', '1.0.40')->getJson('/api/events')->assertOk();
        // A client that doesn't report a version can't be judged, so it isn't blocked.
        $this->withHeaders([])->getJson('/api/events')->assertOk();
        // Config stays reachable so the old app can show the update screen.
        $this->withHeader('X-App-Version', '1.0.38')->getJson('/api/config')
            ->assertOk()->assertJsonPath('operations.update.required', true);
    }

    public function test_force_update_promotes_latest_to_minimum(): void
    {
        PlatformRules::save(['app.latest_version' => '1.0.41']);
        $this->withHeader('X-App-Version', '1.0.39')->getJson('/api/events')->assertOk();
        $this->withHeader('X-App-Version', '1.0.39')->getJson('/api/config')->assertJsonPath('operations.update.available', true);

        PlatformRules::save(['app.force_update' => true]);
        $this->withHeader('X-App-Version', '1.0.39')->getJson('/api/events')->assertStatus(426);
    }

    // ── Bookings & payments ─────────────────────────────────────────────────────

    public function test_paused_event_bookings_are_refused_with_the_admin_message(): void
    {
        PlatformRules::save(['ops.pause_event_bookings' => true, 'ops.pause_message' => 'Sales resume at noon.']);
        $event = $this->event();

        $this->withHeaders($this->bearer($this->user()))
            ->postJson('/api/bookings', ['eventId' => $event->id, 'quantity' => 1, 'pay' => true])
            ->assertStatus(409)->assertJsonPath('message', 'Sales resume at noon.');

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(20, $event->fresh()->available_slots);
    }

    public function test_pause_all_bookings_also_stops_venue_bookings(): void
    {
        PlatformRules::save(['ops.pause_all_bookings' => true]);

        $this->expectException(ConflictHttpException::class);
        app(BookingService::class)->createVenueBooking($this->user(), 999, null, now()->addDay()->toDateString());
    }

    public function test_payments_off_refuses_paid_orders_but_lets_free_ones_through(): void
    {
        PlatformRules::save(['ops.payments_disabled' => true]);
        $buyer = $this->user();

        $paid = $this->event();
        $this->withHeaders($this->bearer($buyer))
            ->postJson('/api/bookings', ['eventId' => $paid->id, 'quantity' => 1, 'pay' => true])
            ->assertStatus(409);
        $this->assertSame(20, $paid->fresh()->available_slots);

        $free = $this->event(['price' => 0]);
        $this->withHeaders($this->bearer($buyer))
            ->postJson('/api/bookings', ['eventId' => $free->id, 'quantity' => 1, 'pay' => true])
            ->assertCreated()->assertJsonPath('data.status', 'CONFIRMED');
    }

    public function test_payments_off_stops_the_gateway_itself(): void
    {
        config(['services.razorpay.key' => 'k', 'services.razorpay.secret' => 's']);
        PlatformRules::save(['ops.payments_disabled' => true]);

        $this->expectException(PaymentsPaused::class);
        app(RazorpayGateway::class)->createOrder(50000, 'test');
    }

    // ── Creation & AI ───────────────────────────────────────────────────────────

    public function test_match_and_tournament_creation_can_be_paused(): void
    {
        PlatformRules::save(['ops.pause_match_creation' => true, 'ops.pause_tournament_creation' => true]);
        $player = $this->user();
        // Creation sits behind the ActionBoard profile gate; a complete profile gets past it.
        $player->forceFill(['state' => 'Andhra Pradesh', 'district' => 'Kadapa', 'primary_sport' => 'Football', 'sport_attributes' => ['position' => 'Forward', 'foot' => 'Right']])->save();

        // A valid match — so the refusal is the pause, not validation.
        $this->withHeaders($this->bearer($player))->postJson('/api/matches', [
            'matchType' => 'casual', 'playersPerSide' => 7, 'teamA' => 'Home FC', 'teamB' => 'Away FC',
            'sport' => 'football', 'venue' => 'Village ground', 'locality' => 'Keerthipalle',
            'latitude' => 14.42, 'longitude' => 78.22,
            'format' => ['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 25],
        ])->assertStatus(409);
        $this->assertSame(0, LiveMatch::query()->count());
        $this->withHeaders($this->bearer($player))->postJson('/api/tournaments', [])->assertStatus(409);
    }

    public function test_ai_switches_and_daily_budget(): void
    {
        $this->assertTrue(AiGate::attempt(AiGate::MATCH_COMMENTARY));
        $this->assertSame(1, AiGate::usedToday()); // counted even with no cap, for /control

        PlatformRules::save(['ai.match_commentary' => false]);
        $this->assertFalse(AiGate::attempt(AiGate::MATCH_COMMENTARY));
        $this->assertSame(1, AiGate::usedToday()); // a refused call costs nothing

        PlatformRules::save(['ai.daily_call_budget' => 2]);
        $this->assertTrue(AiGate::attempt(AiGate::CAREER_READ));   // 2nd call today
        $this->assertFalse(AiGate::attempt(AiGate::CAREER_READ));  // 3rd — over the cap

        PlatformRules::save(['ops.ai_disabled' => true]);
        $this->assertFalse(AiGate::enabled(AiGate::EVENT_COPY));
    }

    // ── The page ────────────────────────────────────────────────────────────────

    public function test_only_super_admins_run_operations_and_every_change_is_audited(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->actingAs($this->user('FINANCE'));
        $this->assertFalse(OperationsPage::canAccess());

        $admin = $this->user('ADMIN');
        $this->actingAs($admin);
        $this->assertTrue(OperationsPage::canAccess());

        Livewire::test(OperationsPage::class)
            ->set('data.ops__pause_venue_bookings', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(PlatformRules::bool('ops.pause_venue_bookings'));
        $this->assertSame('1', OperationsPage::getNavigationBadge());

        $log = AdminAction::query()->where('action', 'operations.updated')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['from' => false, 'to' => true], $log->meta['changes']['ops.pause_venue_bookings']);
    }
}
