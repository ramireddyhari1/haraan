<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A player-hosted tournament in any sport the app scores.
 *
 * The tables below are the single source of truth for what each sport's formats mean. The
 * API resolves a preset's numbers from here and validates a custom format against the rule
 * ranges, so a client can never file a "T20" with 35 overs or a "5-a-side" with eleven.
 * The app mirrors them for its UI (ui/matches/create/TournamentSports.kt).
 */
class Tournament extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'format_rules' => 'array',
        'events' => 'array',
        'options' => 'array',
        'overs_per_innings' => 'integer',
        'balls_per_innings' => 'integer',
        'innings_per_side' => 'integer',
        'match_days' => 'integer',
        'players_per_side' => 'integer',
        'teams_count' => 'integer',
        'entry_fee' => 'integer',
    ];

    public const CATEGORIES = [
        'open' => 'Open',
        'corporate' => 'Corporate',
        'community' => 'Community',
        'school' => 'School',
        'college' => 'College',
        'university' => 'University',
        'series' => 'Series',
        'other' => 'Other',
    ];

    public const GENDERS = [
        'men' => 'Men',
        'women' => 'Women',
        'mixed' => 'Mixed',
    ];

    public const AGE_GROUPS = [
        'open' => 'Open age',
        'u12' => 'Under 12',
        'u14' => 'Under 14',
        'u16' => 'Under 16',
        'u19' => 'Under 19',
        'veterans' => 'Veterans (35+)',
    ];

    public const STRUCTURES = [
        'knockout' => 'Knockout',
        'league' => 'League',
        'league_knockout' => 'League + Knockouts',
        'groups_knockout' => 'Groups + Knockouts',
    ];

    // ── Cricket ──────────────────────────────────────────────────────────────

    /**
     * label · fixed overs (null = organiser sets them, or not over-limited) · fixed balls ·
     * innings per side · default players a side.
     */
    public const FORMATS = [
        't20' => ['label' => 'T20', 'overs' => 20, 'balls' => null, 'innings' => 1, 'players' => 11],
        't10' => ['label' => 'T10', 'overs' => 10, 'balls' => null, 'innings' => 1, 'players' => 11],
        'odi' => ['label' => 'One Day', 'overs' => 50, 'balls' => null, 'innings' => 1, 'players' => 11],
        'hundred' => ['label' => 'The Hundred', 'overs' => null, 'balls' => 100, 'innings' => 1, 'players' => 11],
        'test' => ['label' => 'Test / Multi-day', 'overs' => null, 'balls' => null, 'innings' => 2, 'players' => 11],
        'box' => ['label' => 'Box Cricket', 'overs' => null, 'balls' => null, 'innings' => 1, 'players' => 8],
        'sixes' => ['label' => 'Sixes', 'overs' => 5, 'balls' => null, 'innings' => 1, 'players' => 6],
        'custom' => ['label' => 'Custom', 'overs' => null, 'balls' => null, 'innings' => 1, 'players' => 11],
    ];

    /** Cricket formats whose overs the organiser chooses. */
    public const OVERS_CHOSEN_BY_ORGANISER = ['box', 'custom'];

    public const BALL_TYPES = ['tennis', 'tape', 'rubber', 'cork', 'synthetic', 'leather', 'season'];

    public const PITCH_TYPES = [
        'turf' => 'Natural turf',
        'matting' => 'Matting',
        'cement' => 'Cement',
        'astro' => 'Astro turf',
        'mud' => 'Mud',
        'box' => 'Box / indoor',
    ];

    // ── Every other sport ────────────────────────────────────────────────────

    private const RACQUET_EVENTS = [
        'mens_singles' => "Men's singles",
        'womens_singles' => "Women's singles",
        'mens_doubles' => "Men's doubles",
        'womens_doubles' => "Women's doubles",
        'mixed_doubles' => 'Mixed doubles',
    ];

    /**
     * Per sport:
     *  - team: played by sides (players_per_side + gender) or by entries in events (racquet).
     *  - players: allowed range a side, for a custom format.
     *  - rules: the numbers a format is made of. `min`/`max` for ranges, `in` for a fixed set
     *    of numbers, `values` for named choices.
     *  - formats: presets with their fixed rules (and players a side); `custom` takes the
     *    organiser's own, validated against `rules`.
     *  - events / surfaces / options: what else the organiser picks. Options carry a default.
     */
    public const SPORTS = [
        'football' => [
            'label' => 'Football',
            'team' => true,
            'players' => [3, 11],
            'rules' => [
                'halves' => ['label' => 'Halves', 'min' => 1, 'max' => 2],
                'half_minutes' => ['label' => 'Minutes per half', 'min' => 5, 'max' => 60],
            ],
            'formats' => [
                'eleven' => ['label' => '11-a-side', 'players' => 11, 'rules' => ['halves' => 2, 'half_minutes' => 45]],
                'nine' => ['label' => '9-a-side', 'players' => 9, 'rules' => ['halves' => 2, 'half_minutes' => 30]],
                'seven' => ['label' => '7-a-side', 'players' => 7, 'rules' => ['halves' => 2, 'half_minutes' => 25]],
                'five' => ['label' => '5-a-side', 'players' => 5, 'rules' => ['halves' => 2, 'half_minutes' => 20]],
                'futsal' => ['label' => 'Futsal', 'players' => 5, 'rules' => ['halves' => 2, 'half_minutes' => 20]],
                'custom' => ['label' => 'Custom'],
            ],
            'surfaces' => [
                'grass' => 'Natural grass',
                'artificial' => 'Artificial turf',
                'indoor' => 'Indoor court',
                'sand' => 'Sand',
                'mud' => 'Mud ground',
            ],
            'options' => [
                'knockout_tiebreak' => [
                    'label' => 'Knockout draws decided by',
                    'values' => ['penalties' => 'Penalty shoot-out', 'extra_time_penalties' => 'Extra time, then penalties'],
                    'default' => 'penalties',
                ],
            ],
        ],
        'badminton' => [
            'label' => 'Badminton',
            'team' => false,
            'rules' => [
                'best_of' => ['label' => 'Games', 'in' => [1, 3, 5]],
                'points_to' => ['label' => 'Points per game', 'min' => 11, 'max' => 30],
            ],
            'formats' => [
                'standard' => ['label' => 'Standard', 'rules' => ['best_of' => 3, 'points_to' => 21]],
                'short' => ['label' => 'Short games', 'rules' => ['best_of' => 3, 'points_to' => 15]],
                'one_game' => ['label' => 'One game', 'rules' => ['best_of' => 1, 'points_to' => 21]],
                'fast11' => ['label' => 'Fast 11s', 'rules' => ['best_of' => 5, 'points_to' => 11]],
                'custom' => ['label' => 'Custom'],
            ],
            'events' => self::RACQUET_EVENTS,
            'surfaces' => [
                'wooden' => 'Wooden court',
                'synthetic' => 'Synthetic mat',
                'cement' => 'Outdoor cement',
            ],
            'options' => [
                'shuttle' => [
                    'label' => 'Shuttle',
                    'values' => ['feather' => 'Feather', 'nylon' => 'Nylon'],
                    'default' => 'feather',
                ],
            ],
        ],
        'volleyball' => [
            'label' => 'Volleyball',
            'team' => true,
            'players' => [2, 9],
            'rules' => [
                'best_of' => ['label' => 'Sets', 'in' => [1, 3, 5]],
                'points_to' => ['label' => 'Points per set', 'min' => 15, 'max' => 30],
                'decider_to' => ['label' => 'Deciding set to', 'min' => 15, 'max' => 30],
            ],
            'formats' => [
                'indoor' => ['label' => 'Indoor 6s', 'players' => 6, 'rules' => ['best_of' => 5, 'points_to' => 25, 'decider_to' => 15]],
                'indoor_bo3' => ['label' => 'Indoor, best of 3', 'players' => 6, 'rules' => ['best_of' => 3, 'points_to' => 25, 'decider_to' => 15]],
                'beach' => ['label' => 'Beach 2s', 'players' => 2, 'rules' => ['best_of' => 3, 'points_to' => 21, 'decider_to' => 15]],
                'one_set' => ['label' => 'One set', 'players' => 6, 'rules' => ['best_of' => 1, 'points_to' => 25, 'decider_to' => 25]],
                'custom' => ['label' => 'Custom'],
            ],
            'surfaces' => [
                'indoor' => 'Indoor court',
                'sand' => 'Sand',
                'grass' => 'Grass',
                'outdoor' => 'Outdoor hard court',
            ],
        ],
        'basketball' => [
            'label' => 'Basketball',
            'team' => true,
            'players' => [3, 5],
            'rules' => [
                'periods' => ['label' => 'Periods', 'in' => [1, 2, 4]],
                'period_minutes' => ['label' => 'Minutes per period', 'min' => 5, 'max' => 20],
            ],
            'formats' => [
                'fiba' => ['label' => '5x5 FIBA', 'players' => 5, 'rules' => ['periods' => 4, 'period_minutes' => 10]],
                'long' => ['label' => '12-minute quarters', 'players' => 5, 'rules' => ['periods' => 4, 'period_minutes' => 12]],
                'halves' => ['label' => 'Two halves', 'players' => 5, 'rules' => ['periods' => 2, 'period_minutes' => 20]],
                'three' => ['label' => '3x3', 'players' => 3, 'rules' => ['periods' => 1, 'period_minutes' => 10]],
                'custom' => ['label' => 'Custom'],
            ],
            'surfaces' => [
                'indoor' => 'Indoor wooden',
                'outdoor' => 'Outdoor concrete',
                'synthetic' => 'Synthetic tiles',
            ],
        ],
        'kabaddi' => [
            'label' => 'Kabaddi',
            'team' => true,
            'players' => [4, 8],
            'rules' => [
                'halves' => ['label' => 'Halves', 'min' => 1, 'max' => 2],
                'half_minutes' => ['label' => 'Minutes per half', 'min' => 5, 'max' => 25],
            ],
            'formats' => [
                'standard' => ['label' => 'Standard', 'players' => 7, 'rules' => ['halves' => 2, 'half_minutes' => 20]],
                'short' => ['label' => 'Short halves', 'players' => 7, 'rules' => ['halves' => 2, 'half_minutes' => 15]],
                'beach' => ['label' => 'Beach kabaddi', 'players' => 4, 'rules' => ['halves' => 2, 'half_minutes' => 15]],
                'circle' => ['label' => 'Circle style', 'players' => 8, 'rules' => ['halves' => 2, 'half_minutes' => 20]],
                'custom' => ['label' => 'Custom'],
            ],
            'surfaces' => [
                'mat' => 'Synthetic mat',
                'mud' => 'Mud ground',
                'sand' => 'Sand',
            ],
            'options' => [
                'weight_limit_kg' => [
                    'label' => 'Weight limit',
                    'min' => 30,
                    'max' => 120,
                    'suffix' => ' kg',
                    'default' => null,
                ],
            ],
        ],
        'tennis' => [
            'label' => 'Tennis',
            'team' => false,
            'rules' => [
                'best_of' => ['label' => 'Sets', 'in' => [1, 3, 5]],
                'games_to' => ['label' => 'Games per set', 'min' => 4, 'max' => 9],
                'final_set' => [
                    'label' => 'Deciding set',
                    'values' => ['full' => 'Full set', 'super_tiebreak' => 'Super tiebreak to 10'],
                ],
            ],
            'formats' => [
                'best3' => ['label' => 'Best of 3', 'rules' => ['best_of' => 3, 'games_to' => 6, 'final_set' => 'full']],
                'super_tiebreak' => ['label' => 'Super tiebreak', 'rules' => ['best_of' => 3, 'games_to' => 6, 'final_set' => 'super_tiebreak']],
                'pro_set' => ['label' => 'Pro set', 'rules' => ['best_of' => 1, 'games_to' => 8, 'final_set' => 'full']],
                'fast4' => ['label' => 'Fast4', 'rules' => ['best_of' => 3, 'games_to' => 4, 'final_set' => 'full']],
                'best5' => ['label' => 'Best of 5', 'rules' => ['best_of' => 5, 'games_to' => 6, 'final_set' => 'full']],
                'custom' => ['label' => 'Custom'],
            ],
            'events' => self::RACQUET_EVENTS,
            'surfaces' => [
                'hard' => 'Hard court',
                'clay' => 'Clay',
                'grass' => 'Grass',
                'synthetic' => 'Artificial grass',
            ],
        ],
        'table_tennis' => [
            'label' => 'Table Tennis',
            'team' => false,
            'rules' => [
                'best_of' => ['label' => 'Games', 'in' => [1, 3, 5, 7]],
                'points_to' => ['label' => 'Points per game', 'in' => [11, 21]],
            ],
            'formats' => [
                'best5' => ['label' => 'Best of 5', 'rules' => ['best_of' => 5, 'points_to' => 11]],
                'best7' => ['label' => 'Best of 7', 'rules' => ['best_of' => 7, 'points_to' => 11]],
                'best3' => ['label' => 'Best of 3', 'rules' => ['best_of' => 3, 'points_to' => 11]],
                'custom' => ['label' => 'Custom'],
            ],
            'events' => self::RACQUET_EVENTS,
        ],
    ];

    /** Every sport a tournament can be hosted in. */
    public static function sportKeys(): array
    {
        return array_merge(['cricket'], array_keys(self::SPORTS));
    }

    public static function sportLabel(string $sport): string
    {
        return $sport === 'cricket' ? 'Cricket' : (self::SPORTS[$sport]['label'] ?? ucfirst($sport));
    }

    /** True when the sport is played by sides (and so has players a side and a gender). */
    public static function isTeamSport(string $sport): bool
    {
        return $sport === 'cricket' || (self::SPORTS[$sport]['team'] ?? false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** upcoming · ongoing · completed — derived from the dates, so it can never go stale. */
    public function phase(?Carbon $today = null): string
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        if ($this->start_date->copy()->startOfDay()->gt($today)) {
            return 'upcoming';
        }

        return $this->end_date->copy()->startOfDay()->lt($today) ? 'completed' : 'ongoing';
    }

    /** The format's own name: the preset's label, or the organiser's name for a custom one. */
    public function formatName(): string
    {
        if ($this->match_format === 'custom' && $this->format_name) {
            return $this->format_name;
        }

        return $this->sport === 'cricket'
            ? (self::FORMATS[$this->match_format]['label'] ?? 'Custom')
            : (self::SPORTS[$this->sport]['formats'][$this->match_format]['label'] ?? 'Custom');
    }

    /**
     * One line for cards and shares: "T20 · 20 overs", "7-a-side · 2 × 25 min",
     * "Standard · best of 3 to 21", "Super tiebreak · best of 3 sets, super tiebreak".
     */
    public function formatLabel(): string
    {
        $detail = $this->sport === 'cricket' ? $this->cricketDetail() : $this->rulesSummary();

        return $detail === '' ? $this->formatName() : $this->formatName() . ' · ' . $detail;
    }

    private function cricketDetail(): string
    {
        return match (true) {
            $this->balls_per_innings !== null => $this->balls_per_innings . ' balls',
            $this->match_format === 'test' => ($this->match_days ?? 1) . ' ' . (($this->match_days ?? 1) === 1 ? 'day' : 'days'),
            $this->overs_per_innings !== null => $this->overs_per_innings . ' ' . ($this->overs_per_innings === 1 ? 'over' : 'overs'),
            default => '',
        };
    }

    /** The numbers of a non-cricket format, in that sport's own words. */
    public function rulesSummary(): string
    {
        $r = $this->format_rules ?? [];

        return match ($this->sport) {
            'football', 'kabaddi' => ((int) ($r['halves'] ?? 2)) === 1
                ? ($r['half_minutes'] ?? 0) . ' min, one half'
                : ($r['halves'] ?? 2) . ' × ' . ($r['half_minutes'] ?? 0) . ' min',
            'basketball' => ((int) ($r['periods'] ?? 4)) === 1
                ? ($r['period_minutes'] ?? 0) . ' min, one period'
                : ($r['periods'] ?? 4) . ' × ' . ($r['period_minutes'] ?? 0) . ' min',
            'badminton', 'table_tennis' => ((int) ($r['best_of'] ?? 3)) === 1
                ? 'one game to ' . ($r['points_to'] ?? 21)
                : 'best of ' . ($r['best_of'] ?? 3) . ' to ' . ($r['points_to'] ?? 21),
            'volleyball' => ((int) ($r['best_of'] ?? 3)) === 1
                ? 'one set to ' . ($r['points_to'] ?? 25)
                : 'best of ' . ($r['best_of'] ?? 3) . ' to ' . ($r['points_to'] ?? 25),
            'tennis' => $this->tennisSummary($r),
            default => '',
        };
    }

    private function tennisSummary(array $r): string
    {
        $bestOf = (int) ($r['best_of'] ?? 3);
        $games = (int) ($r['games_to'] ?? 6);
        $line = $bestOf === 1 ? 'one set to ' . $games : 'best of ' . $bestOf . ' sets';
        if ($bestOf > 1 && $games !== 6) {
            $line .= ' to ' . $games . ' games';
        }
        if ($bestOf > 1 && ($r['final_set'] ?? 'full') === 'super_tiebreak') {
            $line .= ', super tiebreak';
        }

        return $line;
    }

    public function categoryLabel(): string
    {
        if ($this->category === 'other' && $this->category_other) {
            return $this->category_other;
        }

        return self::CATEGORIES[$this->category] ?? 'Other';
    }

    /** "Men · Under 19", "Open age", "Mixed". */
    public function eligibilityLabel(): string
    {
        $parts = [];
        if ($this->gender !== null && isset(self::GENDERS[$this->gender])) {
            $parts[] = self::GENDERS[$this->gender];
        }
        $parts[] = self::AGE_GROUPS[$this->age_group] ?? 'Open age';

        return implode(' · ', $parts);
    }

    public function surfaceLabel(): ?string
    {
        if ($this->surface === null) {
            return null;
        }

        $surfaces = $this->sport === 'cricket' ? self::PITCH_TYPES : (self::SPORTS[$this->sport]['surfaces'] ?? []);

        return $surfaces[$this->surface] ?? ucfirst($this->surface);
    }

    /** @return array<int, string> */
    public function eventLabels(): array
    {
        $names = self::SPORTS[$this->sport]['events'] ?? [];

        return array_values(array_map(fn (string $e) => $names[$e] ?? $e, $this->events ?? []));
    }

    /** "Entries" for racquet sports, where people enter events; "Teams" everywhere else. */
    public function entryNoun(): string
    {
        return self::isTeamSport($this->sport) ? 'team' : 'entry';
    }

    /**
     * The format section of the tournament page, as label/value rows, so the app can draw
     * every sport with one composable and never needs a release to name a new rule.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function formatDetails(): array
    {
        $rows = [];
        $add = function (string $label, ?string $value) use (&$rows): void {
            if ($value !== null && $value !== '') {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        };

        if ($this->sport === 'cricket') {
            $add('Match format', $this->formatLabel());
            $add('Per side', $this->players_per_side ? $this->players_per_side . ' players' : null);
            $add('Ball', $this->ball_type ? ucfirst($this->ball_type) : null);
            $add('Pitch', $this->surfaceLabel());
            if (($this->innings_per_side ?? 1) > 1) {
                $add('Innings', $this->innings_per_side . ' per side');
            }
        } else {
            $spec = self::SPORTS[$this->sport] ?? [];
            $add(self::isTeamSport($this->sport) ? 'Game' : 'Scoring', $this->formatName() . ' · ' . $this->rulesSummary());
            if (($this->format_rules['decider_to'] ?? null) !== null && ($this->format_rules['best_of'] ?? 1) > 1) {
                $add('Deciding set', 'to ' . $this->format_rules['decider_to']);
            }
            if (! empty($this->events)) {
                $add('Events', implode(', ', $this->eventLabels()));
            }
            $add($this->sport === 'kabaddi' ? 'On the mat' : 'Per side', $this->players_per_side ? $this->players_per_side . ' players' : null);
            $add(in_array($this->sport, ['badminton', 'tennis', 'volleyball', 'basketball'], true) ? 'Court' : 'Surface', $this->surfaceLabel());
            foreach ($spec['options'] ?? [] as $key => $option) {
                $value = $this->options[$key] ?? null;
                if ($value === null) {
                    continue;
                }
                $add($option['label'], isset($option['values'])
                    ? ($option['values'][$value] ?? (string) $value)
                    : $value . ($option['suffix'] ?? ''));
            }
        }

        $add('Who can play', $this->eligibilityLabel());

        return $rows;
    }
}
