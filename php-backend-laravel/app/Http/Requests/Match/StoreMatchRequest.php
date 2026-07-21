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
            'sport'          => ['nullable', 'string', 'in:cricket,football,badminton,basketball'],
            'matchType'      => ['required', 'string', 'in:casual,league,tournament'],
            // Private = pure scoreboard: no XP, no rank, hidden from feeds, share-code access.
            'isPrivate'      => ['nullable', 'boolean'],
            'overs'          => ['required', 'integer', 'min:1', 'max:50'],
            // Must stay in sync with the app's BallType enum (CreateMatchWizard.kt).
            'ball'           => ['nullable', 'string', 'in:tennis,tape,rubber,cork,synthetic,leather,season'],
            'playersPerSide' => ['required', 'integer', 'min:2', 'max:15'],
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
