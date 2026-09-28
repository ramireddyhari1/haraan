<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The camera's own ball track, cleaned hard enough to draw from.
 *
 * It arrives from a phone with no account, in the same multipart body as the video, so
 * nothing about it is trusted: every point is re-read as numbers, anything outside the
 * frame is dropped, the list is capped, and a track that does not survive that is stored
 * as null rather than half-kept.
 *
 * Shape, in and out:
 *   { "v": 1, "aspect": 1.7778, "bounce": 12|null,
 *     "points": [[tMs, x, y, score], ...],
 *     "wickets": { "verdict": "HITTING", "offsetCm": 4.2, "uncertaintyCm": 3.0, "note": "…" } }
 *
 * "wickets" is optional and comes only from the camera behind the bowler's arm.
 *
 * x and y are 0..1 in the UPRIGHT analysis frame; aspect is that frame's width/height, so
 * a renderer can draw distances across and down at the same scale. tMs is the camera
 * sensor's clock relative to the first point. score is the tracker's own ranking of how
 * ball-like the sighting was — NOT a probability, and never shown as one.
 */
final class ClipTrack
{
    public const MAX_POINTS = 150;

    /** Fewer than this is not a path, and a single dot is not worth storing. */
    public const MIN_POINTS = 3;

    public static function sanitise(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '' || strlen($raw) > 32_000) {
            return null;
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ! is_array($data['points'] ?? null)) {
            return null;
        }

        $aspect = is_numeric($data['aspect'] ?? null) ? (float) $data['aspect'] : 0.0;
        if ($aspect < 0.2 || $aspect > 5.0) {
            return null;
        }

        $points = [];
        foreach (array_slice($data['points'], 0, self::MAX_POINTS) as $p) {
            if (! is_array($p) || count($p) < 3) {
                continue;
            }
            [$t, $x, $y] = [$p[0], $p[1], $p[2]];
            if (! is_numeric($t) || ! is_numeric($x) || ! is_numeric($y)) {
                continue;
            }
            $x = (float) $x;
            $y = (float) $y;
            if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
                continue;
            }
            $score = is_numeric($p[3] ?? null) ? max(0.0, min(1.0, (float) $p[3])) : 0.0;
            $points[] = [max(0, min(60_000, (int) $t)), round($x, 4), round($y, 4), round($score, 2)];
        }
        if (count($points) < self::MIN_POINTS) {
            return null;
        }

        $bounce = $data['bounce'] ?? null;
        $bounce = is_int($bounce) && $bounce >= 0 && $bounce < count($points) ? $bounce : null;

        $out = [
            'v' => 1,
            'aspect' => round($aspect, 4),
            'bounce' => $bounce,
            'points' => $points,
        ];
        $wickets = self::wickets($data['wickets'] ?? null);
        if ($wickets !== null) {
            $out['wickets'] = $wickets;
        }

        return json_encode($out);
    }

    /**
     * The camera's own "would it have hit the stumps", when it sent one.
     *
     * Kept only in a shape the app can print: a known verdict, centimetres inside sane
     * bounds, and a short plain-text note. Anything else is dropped rather than repaired —
     * a verdict that has to be guessed at is not one to put on the scorer's screen.
     */
    private static function wickets(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $verdict = $raw['verdict'] ?? null;
        if (! in_array($verdict, ['HITTING', 'MISSING', 'UMPIRES_CALL', 'UNAVAILABLE'], true)) {
            return null;
        }
        $cm = static fn (mixed $v, float $lo, float $hi): ?float =>
            is_numeric($v) && (float) $v >= $lo && (float) $v <= $hi ? round((float) $v, 1) : null;
        $note = is_string($raw['note'] ?? null) ? trim(strip_tags($raw['note'])) : '';

        return [
            'verdict' => $verdict,
            'offsetCm' => $cm($raw['offsetCm'] ?? null, -300.0, 300.0),
            'uncertaintyCm' => $cm($raw['uncertaintyCm'] ?? null, 0.0, 300.0),
            'note' => mb_substr($note, 0, 140),
        ];
    }

    /** For the API: the stored JSON back as an array, or null. */
    public static function decode(?string $stored): ?array
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $data = json_decode($stored, true);

        return is_array($data) ? $data : null;
    }
}
