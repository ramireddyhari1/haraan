<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Controllers\Api\MatchesController;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Models\LiveMatch;
use App\Models\User;
use App\Services\MatchEventRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only: a realistic ActionBoard — live, finished and scheduled matches across
 * cricket, football and badminton around Vijayawada — so every tab and card state
 * can be eyeballed on the website and the app.
 *
 *   php artisan db:seed --class=LocalActionBoardSeeder
 *
 * Built through the REAL controllers (create → score-action / events → complete),
 * so scores, overs, the over log and results are what the scorer would produce,
 * not hand-written columns.
 *
 * scorer : scorer@haraan.test / player1234  (creates the live + finished matches)
 * viewer : player@haraan.test / player1234  (owns two scheduled matches → "Mine")
 *
 * Idempotent: removes the matches these two users created before re-seeding.
 */
class LocalActionBoardSeeder extends Seeder
{
    private const AREAS = [
        ['Benz Circle', 16.4997, 80.6560], ['Gunadala', 16.5245, 80.6620], ['Labbipet', 16.5062, 80.6400],
        ['Moghalrajpuram', 16.5105, 80.6455], ['Patamata', 16.4950, 80.6650], ['Governorpet', 16.5150, 80.6290],
    ];

    private MatchesController $api;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalActionBoardSeeder only runs locally.');

            return;
        }

        $this->api = app(MatchesController::class);
        $scorer = $this->user('scorer@haraan.test', 'Ravi Kumar');
        $viewer = $this->user('player@haraan.test', 'Sai Kiran');

        LiveMatch::query()->whereIn('user_id', [$scorer->id, $viewer->id])->get()->each->delete();

        // ── LIVE ──────────────────────────────────────────────────────────────
        $this->cricket($scorer, 'Benz Circle Strikers', 'Gunadala Warriors', 0, 8, balls: 25, finish: false);
        $this->cricket($scorer, 'Patamata Panthers', 'Labbipet Lions', 2, 10, balls: 39, finish: false);
        $this->football($scorer, 'Moghalrajpuram FC', 'Governorpet United', 3, [['home', 12], ['away', 27], ['home', 41]], finish: false);
        $this->badminton($scorer, 'Kiran & Teja', 'Arjun & Vamsi', 4, 17, 14, finish: false);

        // ── FINISHED ──────────────────────────────────────────────────────────
        $this->cricket($scorer, 'Gunadala Warriors', 'Patamata Panthers', 1, 6, balls: 36, finish: true);
        $this->football($scorer, 'Labbipet Lions FC', 'Benz Circle FC', 2, [['away', 8], ['home', 33], ['home', 58], ['away', 71]], finish: true);
        $this->badminton($scorer, 'Sravya', 'Meghana', 5, 21, 17, finish: true);

        // ── SCHEDULED ─────────────────────────────────────────────────────────
        // Mine (the viewer's own) — the website's "Next up" line reads these.
        $this->scheduled($viewer, 'cricket', 'Sunday Smashers', 'Office XI', 0, now()->addHours(5), open: false);
        $this->scheduled($viewer, 'football', 'Morning Kickers', 'Riverside FC', 4, now()->addDay()->setTime(6, 30), open: false);
        // Open near me — someone else's, looking for players.
        $this->scheduled($scorer, 'cricket', 'Gunadala Gully XI', 'Need 3 players', 1, now()->addHours(26), open: true);
        $this->scheduled($scorer, 'badminton', 'Evening Doubles', 'Open pair', 2, now()->addHours(9), open: true);

        $this->command?->info('ActionBoard: ' . LiveMatch::query()->whereIn('user_id', [$scorer->id, $viewer->id])
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->toJson());
    }

    private function user(string $email, string $name): User
    {
        $u = User::firstOrNew(['email' => $email]);
        $u->forceFill([
            'name' => $name, 'status' => 'active', 'password' => Hash::make('player1234'),
            'district' => 'NTR', 'state' => 'Andhra Pradesh',
        ])->save();

        return $u->fresh();
    }

    /** A request as the app sends it, with the signed-in user attached. */
    private function req(User $as, array $data = []): Request
    {
        $r = Request::create('/api/matches', 'POST', $data);
        $r->headers->set('Accept', 'application/json');
        $r->attributes->set('auth_user', $as);

        return $r;
    }

    private function create(User $as, string $sport, string $teamA, string $teamB, int $area, array $extra = []): LiveMatch
    {
        [$locality, $lat, $lng] = self::AREAS[$area % count(self::AREAS)];
        $squad = fn (string $p) => array_map(fn ($i) => ['id' => $p . $i, 'name' => $this->name($p, $i)], range(1, $sport === 'badminton' ? 2 : 11));
        $data = [
            'sport' => $sport, 'matchType' => 'casual', 'isPrivate' => false,
            'playersPerSide' => $sport === 'badminton' ? 2 : 11,
            'teamA' => $teamA, 'teamB' => $teamB,
            'teamAEmblem' => 'action' . (1 + $area % 4), 'teamBEmblem' => 'action' . (1 + ($area + 2) % 4),
            'squadA' => $squad('A'), 'squadB' => $squad('B'),
            'venue' => $locality . ' Ground', 'locality' => $locality, 'district' => 'NTR',
            'latitude' => $lat, 'longitude' => $lng,
        ] + $extra + match ($sport) {
            'cricket' => ['overs' => 10, 'format' => ['kind' => 'cricket', 'overs' => 10, 'ball' => 'tennis']],
            'football' => ['format' => ['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 40]],
            'badminton' => ['format' => ['kind' => 'badminton', 'bestOf' => 3, 'pointsTo' => 21, 'doubles' => true]],
        };

        $form = StoreMatchRequest::createFrom($this->req($as, $data));
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();
        $res = $this->api->store($form)->getData(true);
        $id = $res['data']['id'] ?? $res['id'] ?? null;
        if (! $id) {
            throw new \RuntimeException('Match create failed: ' . json_encode($res));
        }

        return LiveMatch::query()->findOrFail($id);
    }

    private function score(User $as, LiveMatch $m, string $type, array $payload = []): void
    {
        $res = $this->api->scoreAction($this->req($as, ['type' => $type, 'payload' => $payload] + $payload), (string) $m->id);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException("score-action {$type} failed: " . json_encode($res->getData(true)));
        }
    }

    /**
     * Cricket on the real scorer. Live: the first innings, $balls legal deliveries in.
     * Finished: a full first innings, then a chase bowled until the engine itself writes
     * the result ("… won by 4 wickets" / "… won by 12 runs").
     */
    private function cricket(User $as, string $a, string $b, int $area, int $seed, int $balls, bool $finish): void
    {
        $m = $this->create($as, 'cricket', $a, $b, $area);
        mt_srand($seed);
        $this->score($as, $m, 'start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B11', 'decision' => $a . ' • Bat']);
        $this->innings($as, $m, 'A', 'B', $finish ? 60 : $balls);
        if (! $finish) {
            return;
        }
        $this->score($as, $m, 'start', ['batting_team' => 2, 'striker_id' => 'B1', 'non_striker_id' => 'B2', 'bowler_id' => 'A11']);
        $this->innings($as, $m, 'B', 'A', 60);
        $this->api->complete($this->req($as), (string) $m->id);
    }

    /** Bowl up to $balls legal deliveries; stops early when the engine ends the match. */
    private function innings(User $as, LiveMatch $m, string $bat, string $bowl, int $balls): void
    {
        $next = 3;
        $bowlers = [$bowl . '11', $bowl . '10', $bowl . '9', $bowl . '8'];
        $legal = 0;
        $over = 0;
        while ($legal < $balls && strtolower((string) $m->fresh()->status) === 'live') {
            $r = mt_rand(1, 100);
            if ($r <= 5 && $next <= 11) {
                $this->score($as, $m, 'wicket', ['dismissal' => ['bowled', 'caught', 'lbw'][mt_rand(0, 2)], 'new_batsman_id' => $bat . $next++]);
                $legal++;
            } elseif ($r <= 9) {
                $this->score($as, $m, 'wide', ['value' => 1]);
            } else {
                $this->score($as, $m, 'runs', ['value' => [0, 0, 0, 1, 1, 1, 1, 2, 2, 3, 4, 4, 6][mt_rand(0, 12)]]);
                $legal++;
            }
            // New over, new bowler — once per over, however many extras follow.
            if ($legal % 6 === 0 && intdiv($legal, 6) !== $over && $legal < $balls) {
                $over = intdiv($legal, 6);
                if (strtolower((string) $m->fresh()->status) === 'live') {
                    $this->score($as, $m, 'change_bowler', ['bowler_id' => $bowlers[$over % count($bowlers)]]);
                }
            }
        }
    }

    private function event(User $as, LiveMatch $m, array $e): void
    {
        $res = $this->api->recordEvent($this->req($as, $e), (string) $m->id, app(MatchEventRecorder::class));
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('event failed: ' . json_encode($res->getData(true)));
        }
    }

    /** @param list<array{0: string, 1: int}> $goals side + minute */
    private function football(User $as, string $a, string $b, int $area, array $goals, bool $finish): void
    {
        $m = $this->create($as, 'football', $a, $b, $area);
        foreach ($goals as $i => [$side, $min]) {
            $this->event($as, $m, ['kind' => 'goal', 'side' => $side, 'minute' => $min, 'player_name' => $this->name($side === 'home' ? 'A' : 'B', 7 + $i % 4)]);
            if ($i === 1) {
                $this->event($as, $m, ['kind' => 'yellow', 'side' => $side === 'home' ? 'away' : 'home', 'minute' => $min + 3, 'player_name' => $this->name('B', 4)]);
            }
        }
        if ($finish) {
            $this->api->complete($this->req($as), (string) $m->id);
        }
    }

    /** First game played to the given score; a finished match also takes game two. */
    private function badminton(User $as, string $a, string $b, int $area, int $home, int $away, bool $finish): void
    {
        $m = $this->create($as, 'badminton', $a, $b, $area);
        $games = $finish ? [[21, 15], [$home, $away]] : [[21, 18], [$home, $away]];
        foreach ($games as [$h, $w]) {
            // Interleave so the rally log reads like a game. The leader's final point is held
            // back and played last, so a finished game ends exactly on its winning rally.
            $lead = $h >= $w ? 'home' : 'away';
            $seq = array_merge(
                array_fill(0, $lead === 'home' ? $h - 1 : $h, 'home'),
                array_fill(0, $lead === 'away' ? $w - 1 : $w, 'away'),
            );
            mt_srand($h * 31 + $w);
            shuffle($seq);
            $seq[] = $lead;
            foreach ($seq as $side) {
                $this->event($as, $m, ['kind' => 'point', 'side' => $side]);
            }
        }
        if ($finish) {
            $this->api->complete($this->req($as), (string) $m->id);
        }
    }

    private function scheduled(User $as, string $sport, string $a, string $b, int $area, \DateTimeInterface $at, bool $open): void
    {
        $this->create($as, $sport, $a, $b, $area, array_filter([
            'scheduledAt' => $at->format(DATE_ATOM),
            'openToJoin' => $open ?: null,
            'slotsNeeded' => $open ? 3 : null,
        ]));
    }

    private function name(string $side, int $i): string
    {
        $a = ['Ravi', 'Teja', 'Sai', 'Kiran', 'Vamsi', 'Arjun', 'Naveen', 'Pavan', 'Charan', 'Manoj', 'Harsha'];
        $b = ['Rahul', 'Ajay', 'Suresh', 'Gopi', 'Rakesh', 'Venkat', 'Srinu', 'Bhanu', 'Murali', 'Chaitanya', 'Kalyan'];

        return ($side === 'A' ? $a : $b)[($i - 1) % 11];
    }
}
