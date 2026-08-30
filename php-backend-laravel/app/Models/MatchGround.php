<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cricket ground, as a thing rather than as a typed string. See the migration.
 */
class MatchGround extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stats_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * How much the card is allowed to claim.
     *
     * The bands are the whole honesty mechanism. Gully grounds accumulate matches
     * slowly, and a "batting friendly" verdict drawn from two games is noise wearing a
     * confident label — so below five, the card shows the ground and says the data is
     * still building, and it never gets to describe the pitch.
     */
    public const BAND_BUILDING = 'building';   // 0-4
    public const BAND_EMERGING = 'emerging';   // 5-9
    public const BAND_ESTABLISHED = 'established'; // 10-14
    public const BAND_STRONG = 'strong';       // 15+

    public function confidence(): string
    {
        return match (true) {
            $this->matches_played >= 15 => self::BAND_STRONG,
            $this->matches_played >= 10 => self::BAND_ESTABLISHED,
            $this->matches_played >= 5 => self::BAND_EMERGING,
            default => self::BAND_BUILDING,
        };
    }

    /** Below this, the card carries identity and conditions but no trends. */
    public function hasTrends(): bool
    {
        return $this->matches_played >= 5;
    }

    /**
     * Normalise a typed venue into an identity key.
     *
     * Lower-cased, accents and punctuation dropped, and the trailing district or city
     * removed — which is what merges "Saipeta Ground, Kadapa" with "Saipeta Ground".
     * Deliberately conservative: it only ever strips a suffix that matches the district
     * we already know, so "Youth Club, Proddatur" and "Youth Club, Badvel" stay two
     * different grounds, because they are.
     */
    public static function nameKey(string $venue, ?string $district = null, ?string $locality = null): string
    {
        $key = mb_strtolower(trim($venue));
        foreach (array_filter([$district, $locality]) as $suffix) {
            $s = mb_strtolower(trim((string) $suffix));
            if ($s === '') {
                continue;
            }
            // Only as a trailing ", suffix" — never mid-string, where it is part of the name.
            $key = (string) preg_replace('/[,\s]+' . preg_quote($s, '/') . '$/u', '', $key);
        }
        $key = (string) preg_replace('/[^a-z0-9]+/u', ' ', $key);

        return trim((string) preg_replace('/\s+/', ' ', $key));
    }
}
