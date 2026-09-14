<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Player-hosted cricket tournaments: the create wizard's endpoint, the detail read, and the
 * profile's Tournaments tab.
 */
class PlayerTournamentsTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $username, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($username),
            'email' => $username . '@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
            'username' => $username,
            'player_id' => 'HRN' . strtoupper(substr(md5($username), 0, 7)),
            'is_guest' => false,
            'district' => 'YSR Kadapa',
            'state' => 'Andhra Pradesh',
            'primary_sport' => 'cricket',
            'trust_score' => 100,
        ], $overrides));
    }

    private function token(User $user): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $user->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'sport' => 'cricket',
            'name' => 'Kadapa Premier League',
            'category' => 'open',
            'gender' => 'men',
            'age_group' => 'open',
            'city' => 'Kadapa',
            'venue' => 'YSR Stadium',
            'start_date' => Carbon::today()->addDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'match_format' => 't20',
            'players_per_side' => 11,
            'ball_type' => 'leather',
            'surface' => 'turf',
            'structure' => 'league_knockout',
            'teams_count' => 16,
            'entry_fee' => 5000,
            'prize_pool' => '₹1,00,000 + trophy',
            'organizer_name' => 'Reddy',
            'organizer_phone' => '+91 98765 43210',
        ], $overrides);
    }

    private function create(User $host, array $overrides = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($host))
            ->post('/api/tournaments', $this->form($overrides), ['Accept' => 'application/json']);
    }

    public function test_host_creates_a_tournament_with_banner_logo_and_format_rules(): void
    {
        Storage::fake('public');
        $host = $this->player('reddy');

        $response = $this->create($host, [
            'banner' => UploadedFile::fake()->create('banner.jpg', 300, 'image/jpeg'),
            'logo' => UploadedFile::fake()->create('logo.png', 80, 'image/png'),
            // A client can't stretch a T20 — the server owns what the format means.
            'overs_per_innings' => 35,
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Kadapa Premier League', $data['name']);
        $this->assertSame(20, $data['overs_per_innings']);
        $this->assertSame('T20 · 20 overs', $data['format_label']);
        $this->assertSame('9876543210', $data['organizer_phone']);
        $this->assertSame('upcoming', $data['phase']);
        $this->assertTrue($data['mine']);
        $this->assertStringStartsWith('/storage/tournaments/banners/', $data['banner']);
        Storage::disk('public')->assertExists(substr($data['banner'], strlen('/storage/')));
        Storage::disk('public')->assertExists(substr($data['logo'], strlen('/storage/')));
    }

    public function test_custom_and_box_formats_need_their_own_details(): void
    {
        $host = $this->player('reddy');

        $this->create($host, ['match_format' => 'custom'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format_name', 'overs_per_innings']);

        $this->create($host, ['match_format' => 'custom', 'format_name' => 'Super 8', 'overs_per_innings' => 8])
            ->assertCreated()
            ->assertJsonPath('data.format_label', 'Super 8 · 8 overs');

        $this->create($host, ['match_format' => 'hundred'])
            ->assertCreated()
            ->assertJsonPath('data.balls_per_innings', 100)
            ->assertJsonPath('data.overs_per_innings', null);

        $this->create($host, ['match_format' => 'test', 'match_days' => 3])
            ->assertCreated()
            ->assertJsonPath('data.innings_per_side', 2)
            ->assertJsonPath('data.format_label', 'Test / Multi-day · 3 days');
    }

    public function test_rejects_bad_dates_phone_and_other_category_without_a_name(): void
    {
        $host = $this->player('reddy');

        $this->create($host, [
            'start_date' => Carbon::yesterday()->toDateString(),
            'end_date' => Carbon::today()->subDays(3)->toDateString(),
            'organizer_phone' => '12345',
            'category' => 'other',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date', 'category_other']);

        $this->create($host, ['organizer_phone' => '12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organizer_phone']);
    }

    public function test_low_trust_players_cannot_host(): void
    {
        $host = $this->player('reddy', ['trust_score' => 10]);

        $this->create($host)->assertForbidden();
        $this->assertSame(0, Tournament::query()->count());
    }

    public function test_profile_lists_the_hosts_tournaments_running_first(): void
    {
        $host = $this->player('reddy');
        $other = $this->player('virat');

        $base = [
            'user_id' => $host->id, 'category' => 'open', 'city' => 'Kadapa',
            'match_format' => 't20', 'overs_per_innings' => 20, 'ball_type' => 'tennis',
            'surface' => 'turf', 'structure' => 'knockout', 'players_per_side' => 11,
            'organizer_name' => 'Reddy', 'organizer_phone' => '9876543210',
        ];
        $finished = Tournament::create($base + ['name' => 'Last year cup', 'start_date' => Carbon::today()->subDays(30), 'end_date' => Carbon::today()->subDays(20)]);
        $upcoming = Tournament::create($base + ['name' => 'Monsoon cup', 'start_date' => Carbon::today()->addDays(5), 'end_date' => Carbon::today()->addDays(8)]);
        $running = Tournament::create($base + ['name' => 'Festival cup', 'start_date' => Carbon::today()->subDay(), 'end_date' => Carbon::today()->addDay()]);
        Tournament::create(array_merge($base, ['user_id' => $other->id, 'name' => 'Not theirs', 'start_date' => Carbon::today(), 'end_date' => Carbon::today()]));

        $rows = $this->getJson('/api/players/@reddy/tournaments')->assertOk()->json('results');

        $this->assertSame(
            [(string) $running->id, (string) $upcoming->id, (string) $finished->id],
            array_column($rows, 'id'),
        );
        $this->assertSame(['ongoing', 'upcoming', 'completed'], array_column($rows, 'phase'));
        $this->assertFalse($rows[0]['mine']);

        $this->getJson('/api/tournaments/' . $running->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Festival cup')
            ->assertJsonPath('data.host.username', 'reddy');
    }

    /** The shared fields every sport's form carries; the sport-specific ones are merged on top. */
    private function otherSport(string $sport, array $fields): array
    {
        $form = $this->form(['sport' => $sport] + $fields);
        unset($form['ball_type']);
        foreach (['gender', 'players_per_side'] as $cricketDefault) {
            if (! array_key_exists($cricketDefault, $fields)) {
                unset($form[$cricketDefault]);
            }
        }

        return $form;
    }

    private function createRaw(User $host, array $form)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($host))
            ->post('/api/tournaments', $form, ['Accept' => 'application/json']);
    }

    public function test_football_presets_own_their_numbers_and_options_default(): void
    {
        $host = $this->player('reddy');

        $data = $this->createRaw($host, $this->otherSport('football', [
            'gender' => 'women',
            'age_group' => 'u16',
            'match_format' => 'seven',
            'surface' => 'artificial',
            // A preset ignores whatever numbers the client sends.
            'format_rules' => ['halves' => 1, 'half_minutes' => 90],
            'players_per_side' => 11,
        ]))->assertCreated()->json('data');

        $this->assertSame('football', $data['sport']);
        $this->assertSame(['halves' => 2, 'half_minutes' => 25], $data['format_rules']);
        $this->assertSame(7, $data['players_per_side']);
        $this->assertSame('7-a-side · 2 × 25 min', $data['format_label']);
        $this->assertSame(['knockout_tiebreak' => 'penalties'], $data['options']);
        $this->assertSame('Women · Under 16', $data['eligibility_label']);
        $this->assertSame('team', $data['entry_noun']);
        $this->assertContains(['label' => 'Surface', 'value' => 'Artificial turf'], $data['format_details']);
        $this->assertContains(['label' => 'Knockout draws decided by', 'value' => 'Penalty shoot-out'], $data['format_details']);
    }

    public function test_racquet_sports_enter_events_not_a_gender(): void
    {
        $host = $this->player('reddy');

        $this->createRaw($host, $this->otherSport('badminton', [
            'match_format' => 'standard',
            'surface' => 'wooden',
            'gender' => 'men',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['events', 'gender']);

        $data = $this->createRaw($host, $this->otherSport('badminton', [
            'match_format' => 'standard',
            'surface' => 'wooden',
            'events' => ['mens_singles', 'mixed_doubles'],
            'options' => ['shuttle' => 'nylon'],
        ]))->assertCreated()->json('data');

        $this->assertNull($data['gender']);
        $this->assertNull($data['players_per_side']);
        $this->assertSame(["Men's singles", 'Mixed doubles'], $data['event_labels']);
        $this->assertSame('Standard · best of 3 to 21', $data['format_label']);
        $this->assertSame('entry', $data['entry_noun']);
        $this->assertContains(['label' => 'Shuttle', 'value' => 'Nylon'], $data['format_details']);

        // Table tennis has no surface to choose, so one is refused.
        $this->createRaw($host, $this->otherSport('table_tennis', [
            'match_format' => 'best5',
            'surface' => 'wooden',
            'events' => ['womens_singles'],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['surface']);
    }

    public function test_custom_formats_are_checked_against_the_sports_rule_ranges(): void
    {
        $host = $this->player('reddy');

        $this->createRaw($host, $this->otherSport('volleyball', [
            'gender' => 'mixed',
            'match_format' => 'custom',
            'format_name' => 'Monsoon sets',
            'surface' => 'sand',
            'players_per_side' => 12,
            'format_rules' => ['best_of' => 4, 'points_to' => 40, 'decider_to' => 15],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['players_per_side', 'format_rules.best_of', 'format_rules.points_to']);

        $this->createRaw($host, $this->otherSport('tennis', [
            'match_format' => 'custom',
            'format_name' => 'Club doubles',
            'surface' => 'clay',
            'events' => ['mens_doubles'],
            'format_rules' => ['best_of' => 3, 'games_to' => 4, 'final_set' => 'super_tiebreak'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.format_label', 'Club doubles · best of 3 sets to 4 games, super tiebreak');

        $this->createRaw($host, $this->otherSport('kabaddi', [
            'gender' => 'men',
            'match_format' => 'custom',
            'format_name' => 'Village 6s',
            'surface' => 'mud',
            'players_per_side' => 6,
            'format_rules' => ['halves' => 2, 'half_minutes' => 12],
            'options' => ['weight_limit_kg' => 70],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.format_label', 'Village 6s · 2 × 12 min')
            ->assertJsonPath('data.players_per_side', 6)
            ->assertJsonPath('data.options.weight_limit_kg', 70);
    }

    public function test_unknown_player_and_tournament_are_404(): void
    {
        $this->getJson('/api/players/HRNNOPE000/tournaments')->assertNotFound();
        $this->getJson('/api/tournaments/999999')->assertNotFound();
    }
}
