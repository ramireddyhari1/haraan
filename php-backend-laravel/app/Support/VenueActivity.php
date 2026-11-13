<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use App\Models\Venue;
use App\Models\VenueInterest;
use Illuminate\Support\Facades\Cache;

/**
 * What players have actually done at a venue on Haraan: matches scored there (and what
 * those matches say about the ground), and players asking to book it.
 *
 * Nobody else has this. Every directory repeats the same name, address and Google photos;
 * this is the part of a venue page that is Haraan's own, and it changes as people play.
 * Only real rows are counted, and a block with nothing in it is not shown at all.
 */
final class VenueActivity
{
    /** Below this many asks the number reads as noise, not demand. */
    public const MIN_ASKS = 3;

    /**
     * @return array{
     *   matches:int, ground:?string, recent:list<array{id:int,home:string,away:string,date:string,status:string}>,
     *   trends:?array{highest:int,best:int,bestBy:?string,battingFirstWins:int,decided:int,avgFirst:int},
     *   asks:int, askTime:?string, faq:?array{question:string,answer:string}
     * }|null
     */
    public static function forVenue(Venue $venue): ?array
    {
        return Cache::remember('venue-activity:v1:' . $venue->id, now()->addMinutes(30), function () use ($venue): ?array {
            $ground = self::ground($venue);

            $matches = 0;
            $recent = [];
            $trends = null;
            if ($ground !== null) {
                $base = LiveMatch::query()
                    ->where('ground_id', $ground->id)
                    ->where('is_private', false)
                    ->whereRaw('lower(coalesce(status, \'\')) != ?', ['abandoned']);
                $matches = (clone $base)->count();
                $recent = (clone $base)->orderByDesc('created_at')->limit(5)->get()
                    ->map(fn (LiveMatch $m) => [
                        'id' => (int) $m->id,
                        'home' => (string) ($m->home_full ?: $m->home),
                        'away' => (string) ($m->away_full ?: $m->away),
                        'date' => (string) $m->created_at?->format('j M Y'),
                        'status' => ucfirst(strtolower((string) ($m->status ?: 'scheduled'))),
                    ])->all();
                // The ground card's own rule: trends only from five completed matches.
                if ($ground->hasTrends()) {
                    $trends = [
                        'highest' => (int) $ground->highest_total,
                        'best' => (int) $ground->best_individual,
                        'bestBy' => $ground->best_individual_by ?: null,
                        'battingFirstWins' => (int) $ground->batting_first_wins,
                        'decided' => (int) $ground->decided_matches,
                        'avgFirst' => (int) $ground->first_innings_avg,
                    ];
                }
            }

            $asks = 0;
            $askTime = null;
            if ($venue->isListed()) {
                $asks = VenueInterest::query()->where('venue_id', $venue->id)->distinct()->count('phone');
                if ($asks >= self::MIN_ASKS) {
                    $top = VenueInterest::query()->where('venue_id', $venue->id)->whereNotNull('preferred_time')
                        ->selectRaw('preferred_time, count(*) as n')->groupBy('preferred_time')->orderByDesc('n')->first();
                    $askTime = $top && (int) $top->n >= 2 ? (string) $top->preferred_time : null;
                }
            }

            if ($matches === 0 && $asks < self::MIN_ASKS) {
                return null;
            }

            $faq = $matches > 0 ? [
                'question' => 'Are cricket matches played at ' . $venue->name . '?',
                'answer' => 'Yes — ' . $matches . ' ' . ($matches === 1 ? 'match has' : 'matches have') . ' been scored at ' . $venue->name . ' on Haraan'
                    . ($trends && $trends['highest'] > 0 ? ', with a highest total of ' . $trends['highest'] : '')
                    . '. Each one has a live scorecard on Haraan ActionBoard.',
            ] : null;

            return [
                'matches' => $matches,
                'ground' => $ground?->name,
                'recent' => $recent,
                'trends' => $trends,
                'asks' => $asks >= self::MIN_ASKS ? $asks : 0,
                'askTime' => $askTime,
                'faq' => $faq,
            ];
        });
    }

    /**
     * The match ground this venue is. Google's place id when both have one; otherwise the
     * same normalised name in the same town, and — when both have pins — within a
     * kilometre, so a "Youth Club" in another town is never counted as this one.
     */
    public static function ground(Venue $venue): ?MatchGround
    {
        if (filled($venue->place_id)) {
            $g = MatchGround::query()->where('place_id', $venue->place_id)->first();
            if ($g !== null) {
                return $g;
            }
        }

        $city = trim((string) $venue->city);
        $key = MatchGround::nameKey((string) $venue->name, $city ?: null, trim((string) $venue->location) ?: null);
        if ($key === '' || mb_strlen($key) < 4) {
            return null;
        }

        $lc = mb_strtolower($city);
        foreach (MatchGround::query()->where('name_key', $key)->limit(10)->get() as $g) {
            $sameTown = $lc !== '' && in_array($lc, [mb_strtolower((string) $g->district), mb_strtolower((string) $g->locality)], true);
            $close = $g->latitude !== null && $venue->latitude !== null && $venue->longitude !== null
                && CityVenueGuide::km((float) $venue->latitude, (float) $venue->longitude, (float) $g->latitude, (float) $g->longitude) <= 1.0;
            $far = $g->latitude !== null && $venue->latitude !== null && ! $close;
            if (($sameTown || $close) && ! $far) {
                return $g;
            }
        }

        return null;
    }
}
