<?php

declare(strict_types=1);

namespace App\Http\Requests\Match;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Create a new ActionBoard match from the Create Match wizard.
 */
final class StoreMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Which sport this match is. Defaults to cricket (the only sport with a full
            // create/toss/scorer flow) when omitted, so older clients keep working.
            'sport'          => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\SportRules::SUPPORTED)],
            'matchType'      => ['required', 'string', 'in:casual,league,tournament'],
            // Private = pure scoreboard: no XP, no rank, hidden from feeds, share-code access.
            'isPrivate'      => ['nullable', 'boolean'],
            // Overs are cricket's. Football and badminton used to be forced to send a
            // number here, so every non-cricket match carried "20 overs" as junk and
            // titled itself "20 Over Match".
            'overs'          => ['required_if:sport,cricket', 'nullable', 'integer', 'min:1', 'max:50'],
            // Must stay in sync with the app's BallType enum (CreateMatchWizard.kt).
            'ball'           => ['nullable', 'string', 'in:tennis,tape,rubber,cork,synthetic,leather,season'],
            // Floor is 1, not 2: badminton singles is one a side, and the old floor
            // meant a singles match literally could not be created.
            'playersPerSide' => ['required', 'integer', 'min:1', 'max:15'],

            // What ends the match, in that sport's own terms. Shape depends on `kind`;
            // stored verbatim under `sport_state.format`.
            'format'                => ['nullable', 'array'],
            'format.kind'           => ['required_with:format', 'string', \Illuminate\Validation\Rule::in(\App\Support\SportRules::SUPPORTED)],
            'format.overs'          => ['nullable', 'integer', 'min:1', 'max:50'],
            'format.ball'           => ['nullable', 'string', 'max:20'],
            // What the match is played ON — the create wizard's Ground step. Kept to a
            // closed set so the surface can be counted and filtered later; anything
            // unlisted is a typo, not a new kind of pitch.
            'format.ground'         => ['nullable', 'string', 'in:turf,matting,cement,astro,mud,box'],
            'format.halves'         => ['nullable', 'integer', 'min:1', 'max:2'],
            'format.halfLengthMin'  => ['nullable', 'integer', 'min:5', 'max:45'],
            'format.bestOf'         => ['nullable', 'integer', 'in:1,3,5'],
            // Rally sports run to 11 (table tennis), 15 (a volleyball decider), 21 (badminton)
            // or 25 (volleyball). Tennis sends gamesTo instead.
            'format.pointsTo'       => ['nullable', 'integer', 'in:11,15,21,25'],
            'format.gamesTo'        => ['nullable', 'integer', 'in:4,6'],
            'format.periods'        => ['nullable', 'integer', 'min:1', 'max:4'],
            'format.periodLengthMin' => ['nullable', 'integer', 'min:3', 'max:45'],
            'format.doubles'        => ['nullable', 'boolean'],
            'venue'          => ['nullable', 'string', 'max:255'],
            // Area/Village is mandatory for public matches (they appear in the district
            // feed). Private matches are hidden from feeds, so it's optional there.
            'locality'       => [Rule::requiredIf(fn (): bool => !$this->boolean('isPrivate')), 'nullable', 'string', 'max:120'],
            // A real GPS fix is mandatory for public matches — it's what powers the
            // "near me" feed; a typed place name alone can't be sorted by distance.
            // Private matches never appear in a feed, so they don't need one.
            'latitude'       => [Rule::requiredIf(fn (): bool => !$this->boolean('isPrivate')), 'nullable', 'numeric', 'between:-90,90'],
            'longitude'      => [Rule::requiredIf(fn (): bool => !$this->boolean('isPrivate')), 'nullable', 'numeric', 'between:-180,180'],
            // District resolved from that same fix. Preferred over the creator's
            // profile district, which is often stale or simply where they signed up.
            'district'       => ['nullable', 'string', 'max:120'],
            'onHaraanTurf'   => ['nullable', 'boolean'],
            'venueBookingId' => ['nullable', 'integer'],

            // Future kick-off (ISO-8601). Omitted / null = "play now" (started right
            // after creation via the toss). Must be in the future when present, so a
            // scheduled match never lands already overdue.
            'scheduledAt'    => ['nullable', 'date', 'after:now'],

            // "Looking for players": open the match so nearby players can request to
            // join, and how many more are wanted.
            'openToJoin'     => ['nullable', 'boolean'],
            'slotsNeeded'    => ['nullable', 'integer', 'min:0', 'max:30'],

            'teamA'          => ['required', 'string', 'max:255'],
            'teamB'          => ['required', 'string', 'max:255'],

            // Default team emblems (emoji). Custom uploaded logos arrive separately
            // via POST /matches/{id}/team-logo once the match exists.
            'teamAEmblem'    => ['nullable', 'string', 'max:16'],
            'teamBEmblem'    => ['nullable', 'string', 'max:16'],

            // Squads: list of names, or {id, name} objects for registered players.
            'squadA'         => ['nullable', 'array'],
            'squadA.*'       => ['nullable'],
            'squadB'         => ['nullable', 'array'],
            'squadB.*'       => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locality.required'  => 'Please add the area or village where the match is played.',
            'locality.min'       => 'The area/village name is too short.',
            'latitude.required'  => 'Turn on location so players nearby can find this match.',
            'longitude.required' => 'Turn on location so players nearby can find this match.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
