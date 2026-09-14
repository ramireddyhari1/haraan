<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Player-hosted tournaments in every sport the app scores: create one from the app's
 * tournament wizard, read it back on its detail page, and list a player's own on their profile.
 */
final class TournamentsController extends Controller
{
    /** A host making more than this many in a day is spamming the directory, not organising. */
    private const DAILY_CREATE_LIMIT = 10;

    /**
     * POST /api/tournaments — multipart, so the banner and logo travel with the details and
     * a tournament is never saved half-dressed. Arrays arrive as `format_rules[best_of]`,
     * `events[]` and `options[shuttle]`.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! ReputationService::canCreateRankedTournament($user)) {
            return response()->json([
                'error' => 'Your trust score is too low to host a tournament.',
            ], 403);
        }

        // The sport decides every other rule, so it is settled first.
        $sport = (string) $request->validate([
            'sport' => ['required', Rule::in(Tournament::sportKeys())],
        ])['sport'];
        $isTeam = Tournament::isTeamSport($sport);
        $spec = Tournament::SPORTS[$sport] ?? null;

        $today = Carbon::today()->toDateString();

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Tournament::CATEGORIES))],
            'category_other' => ['nullable', 'required_if:category,other', 'string', 'min:2', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'], // 6 MB
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],   // 2 MB
            'age_group' => ['required', Rule::in(array_keys(Tournament::AGE_GROUPS))],
            // Racquet sports say who plays through their events, never a single gender.
            'gender' => $isTeam
                ? ['required', Rule::in(array_keys(Tournament::GENDERS))]
                : ['prohibited'],

            'city' => ['required', 'string', 'min:2', 'max:100'],
            'venue' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],

            'structure' => ['required', Rule::in(array_keys(Tournament::STRUCTURES))],
            'teams_count' => ['nullable', 'integer', 'between:2,128'],
            'entry_fee' => ['nullable', 'integer', 'between:0,1000000'],
            'prize_pool' => ['nullable', 'string', 'max:120'],

            'organizer_name' => ['required', 'string', 'min:2', 'max:100'],
            'organizer_phone' => ['required', 'string', 'max:20'],
        ];

        if ($sport === 'cricket') {
            $rules += [
                'match_format' => ['required', Rule::in(array_keys(Tournament::FORMATS))],
                'format_name' => ['nullable', 'required_if:match_format,custom', 'string', 'min:2', 'max:60'],
                'overs_per_innings' => ['nullable', 'required_if:match_format,box,custom', 'integer', 'between:1,90'],
                'match_days' => ['nullable', 'integer', 'between:1,5'],
                'players_per_side' => ['required', 'integer', 'between:4,15'],
                'ball_type' => ['required', Rule::in(Tournament::BALL_TYPES)],
                'surface' => ['required', Rule::in(array_keys(Tournament::PITCH_TYPES))],
            ];
        } else {
            $isCustom = $request->input('match_format') === 'custom';
            $rules += [
                'match_format' => ['required', Rule::in(array_keys($spec['formats']))],
                'format_name' => ['nullable', 'required_if:match_format,custom', 'string', 'min:2', 'max:60'],
                'surface' => isset($spec['surfaces'])
                    ? ['required', Rule::in(array_keys($spec['surfaces']))]
                    : ['prohibited'],
                'options' => ['nullable', 'array'],
            ];
            if ($isCustom) {
                if ($isTeam) {
                    $rules['players_per_side'] = [
                        'required', 'integer', 'between:' . $spec['players'][0] . ',' . $spec['players'][1],
                    ];
                }
                $rules['format_rules'] = ['required', 'array'];
                foreach ($spec['rules'] as $key => $rule) {
                    $rules['format_rules.' . $key] = match (true) {
                        isset($rule['values']) => ['required', Rule::in(array_keys($rule['values']))],
                        isset($rule['in']) => ['required', 'integer', Rule::in($rule['in'])],
                        default => ['required', 'integer', 'between:' . $rule['min'] . ',' . $rule['max']],
                    };
                }
            }
            if (isset($spec['events'])) {
                $rules['events'] = ['required', 'array', 'min:1'];
                $rules['events.*'] = ['distinct', Rule::in(array_keys($spec['events']))];
            }
            foreach ($spec['options'] ?? [] as $key => $option) {
                $rules['options.' . $key] = isset($option['values'])
                    ? ['nullable', Rule::in(array_keys($option['values']))]
                    : ['nullable', 'integer', 'between:' . $option['min'] . ',' . $option['max']];
            }
        }

        $v = $request->validate($rules);

        $phone = self::normalizeIndianMobile($v['organizer_phone']);
        if ($phone === null) {
            throw ValidationException::withMessages([
                'organizer_phone' => 'Enter a valid 10-digit mobile number.',
            ]);
        }

        if (Carbon::parse($v['start_date'])->diffInDays(Carbon::parse($v['end_date'])) > 180) {
            throw ValidationException::withMessages([
                'end_date' => 'A tournament can run for at most 180 days.',
            ]);
        }

        $createdToday = Tournament::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($createdToday >= self::DAILY_CREATE_LIMIT) {
            return response()->json([
                'error' => 'You have created a lot of tournaments today. Try again tomorrow.',
            ], 429);
        }

        $format = $v['match_format'];
        $formatColumns = $sport === 'cricket'
            ? self::cricketFormat($format, $v)
            : self::sportFormat($spec, $format, $v, $isTeam);

        $tournament = Tournament::query()->create($formatColumns + [
            'user_id' => $user->id,
            'sport' => $sport,
            'name' => trim($v['name']),
            'category' => $v['category'],
            'category_other' => $v['category'] === 'other' ? trim((string) $v['category_other']) : null,
            'description' => isset($v['description']) ? (trim($v['description']) ?: null) : null,
            'banner_path' => $request->hasFile('banner')
                ? '/storage/' . $request->file('banner')->store('tournaments/banners', 'public')
                : null,
            'logo_path' => $request->hasFile('logo')
                ? '/storage/' . $request->file('logo')->store('tournaments/logos', 'public')
                : null,
            'gender' => $isTeam ? $v['gender'] : null,
            'age_group' => $v['age_group'],
            'city' => trim($v['city']),
            'venue' => isset($v['venue']) ? (trim($v['venue']) ?: null) : null,
            'district' => $v['district'] ?? $user->district,
            'state' => $v['state'] ?? $user->state,
            'latitude' => $v['latitude'] ?? null,
            'longitude' => $v['longitude'] ?? null,
            'start_date' => $v['start_date'],
            'end_date' => $v['end_date'],
            'match_format' => $format,
            'format_name' => $format === 'custom' ? trim((string) $v['format_name']) : null,
            'surface' => $v['surface'] ?? null,
            'structure' => $v['structure'],
            'teams_count' => $v['teams_count'] ?? null,
            'entry_fee' => $v['entry_fee'] ?? null,
            'prize_pool' => isset($v['prize_pool']) ? (trim($v['prize_pool']) ?: null) : null,
            'organizer_name' => trim($v['organizer_name']),
            'organizer_phone' => $phone,
        ]);

        return response()->json([
            'message' => 'Tournament created',
            'data' => self::payload($tournament->load('user'), $user),
        ], 201);
    }

    /**
     * Cricket's format columns. Fixed formats carry their own overs; box and custom take the
     * organiser's (required by validation).
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private static function cricketFormat(string $format, array $v): array
    {
        $spec = Tournament::FORMATS[$format];

        return [
            'overs_per_innings' => in_array($format, Tournament::OVERS_CHOSEN_BY_ORGANISER, true)
                ? (int) $v['overs_per_innings']
                : $spec['overs'],
            'balls_per_innings' => $spec['balls'],
            'innings_per_side' => $spec['innings'],
            'match_days' => $format === 'test' ? (int) ($v['match_days'] ?? 2) : null,
            'players_per_side' => (int) $v['players_per_side'],
            'ball_type' => $v['ball_type'],
        ];
    }

    /**
     * Any other sport: a preset's rules and players come from the table (whatever the client
     * sent); a custom format's from the validated input. Options fall back to their defaults.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private static function sportFormat(array $spec, string $format, array $v, bool $isTeam): array
    {
        if ($format === 'custom') {
            $formatRules = [];
            foreach ($spec['rules'] as $key => $rule) {
                $value = $v['format_rules'][$key];
                $formatRules[$key] = isset($rule['values']) ? (string) $value : (int) $value;
            }
            $players = $isTeam ? (int) $v['players_per_side'] : null;
        } else {
            $formatRules = $spec['formats'][$format]['rules'];
            $players = $isTeam ? $spec['formats'][$format]['players'] : null;
        }

        $options = [];
        foreach ($spec['options'] ?? [] as $key => $option) {
            $value = $v['options'][$key] ?? $option['default'];
            $options[$key] = $value === null ? null : (isset($option['values']) ? (string) $value : (int) $value);
        }

        return [
            'format_rules' => $formatRules,
            'events' => isset($spec['events']) ? array_values(array_unique($v['events'])) : null,
            'options' => $options === [] ? null : $options,
            'players_per_side' => $players,
        ];
    }

    /** GET /api/tournaments/{id} — public; `mine` is set for the host. */
    public function show(Request $request, string $id): JsonResponse
    {
        $tournament = Tournament::query()->with('user')->find($id);
        if ($tournament === null) {
            return response()->json(['error' => 'Tournament not found'], 404);
        }

        $viewer = $request->attributes->get('auth_user');

        return response()->json([
            'data' => self::payload($tournament, $viewer instanceof User ? $viewer : null),
        ]);
    }

    /**
     * The tournaments one player hosts, for their profile tab: running ones first, then
     * upcoming soonest-first, then finished most-recent-first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForHost(User $host, ?User $viewer): array
    {
        $today = Carbon::today()->toDateString();

        return Tournament::query()
            ->with('user')
            ->where('user_id', $host->id)
            ->orderByRaw(
                'CASE WHEN start_date <= ? AND end_date >= ? THEN 0 WHEN start_date > ? THEN 1 ELSE 2 END',
                [$today, $today, $today],
            )
            ->orderByRaw('CASE WHEN end_date >= ? THEN start_date END ASC', [$today])
            ->orderByDesc('end_date')
            ->limit(100)
            ->get()
            ->map(fn (Tournament $t) => self::payload($t, $viewer))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function payload(Tournament $t, ?User $viewer): array
    {
        $host = $t->user;

        return [
            'id' => (string) $t->id,
            'sport' => $t->sport,
            'sport_label' => Tournament::sportLabel($t->sport),
            'name' => $t->name,
            'category' => $t->category,
            'category_label' => $t->categoryLabel(),
            'description' => $t->description,
            'banner' => $t->banner_path,
            'logo' => $t->logo_path,
            'gender' => $t->gender,
            'age_group' => $t->age_group,
            'eligibility_label' => $t->eligibilityLabel(),
            'city' => $t->city,
            'venue' => $t->venue,
            'district' => $t->district,
            'state' => $t->state,
            'latitude' => $t->latitude,
            'longitude' => $t->longitude,
            'start_date' => $t->start_date->toDateString(),
            'end_date' => $t->end_date->toDateString(),
            'phase' => $t->phase(),
            'match_format' => $t->match_format,
            'format_name' => $t->format_name,
            'format_short' => $t->formatName(),
            'format_label' => $t->formatLabel(),
            'format_rules' => $t->format_rules,
            'events' => $t->events ?? [],
            'event_labels' => $t->eventLabels(),
            'options' => $t->options,
            'overs_per_innings' => $t->overs_per_innings,
            'balls_per_innings' => $t->balls_per_innings,
            'innings_per_side' => $t->innings_per_side,
            'match_days' => $t->match_days,
            'players_per_side' => $t->players_per_side,
            'ball_type' => $t->ball_type,
            'surface' => $t->surface,
            'surface_label' => $t->surfaceLabel(),
            // The tournament page's format section, already worded for the sport.
            'format_details' => $t->formatDetails(),
            // "team" or "entry" — what the entry fee and the count are per.
            'entry_noun' => $t->entryNoun(),
            'structure' => $t->structure,
            'structure_label' => Tournament::STRUCTURES[$t->structure] ?? $t->structure,
            'teams_count' => $t->teams_count,
            'entry_fee' => $t->entry_fee,
            'prize_pool' => $t->prize_pool,
            'organizer_name' => $t->organizer_name,
            'organizer_phone' => $t->organizer_phone,
            'host' => $host === null ? null : [
                'player_id' => $host->player_id,
                'name' => $host->name,
                'username' => $host->username,
            ],
            'mine' => $viewer !== null && (int) $viewer->id === (int) $t->user_id,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    /** "+91 98765 43210" / "098765-43210" / "9876543210" → "9876543210"; anything else → null. */
    private static function normalizeIndianMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? $digits : null;
    }
}
