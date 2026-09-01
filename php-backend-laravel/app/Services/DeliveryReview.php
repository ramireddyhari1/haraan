<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A read of one delivery, from the clip the LBW camera sent.
 *
 * WHAT THIS IS NOT, and the whole design follows from it: this is not an umpire. A single
 * uncalibrated phone at an unknown distance, at whatever frame rate the handset felt like,
 * cannot adjudicate an LBW. Real DRS uses several synchronised high-speed cameras, a
 * surveyed pitch and a ball-tracking model, and it still leaves an umpire's call. Anything
 * here that printed "OUT" would be inventing certainty it does not have — and on a maidan,
 * where these decisions are already argued over, a confident wrong answer from a phone is
 * worse than no answer at all.
 *
 * What it IS: a review. Gully cricket has no replay whatsoever, so simply seeing the ball
 * again is most of the value. On top of that, the model reports the things a camera can
 * genuinely see — where the ball pitched, what it struck first, whether the bat was
 * involved — and is required to say, per factor, when the footage cannot tell. The
 * decision stays with the players, which is also where the responsibility stays.
 *
 * Every field comes back through a response schema, so what reaches the screen is a set of
 * enums the app renders as designed components. The model never writes a verdict, never
 * writes markup, and never gets to phrase a number.
 */
class DeliveryReview
{
    private const TIMEOUT_SECONDS = 90;
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /** Where a clip's review has got to. Mirrored in the app's ReviewStatus. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * THE REVIEW CONTRACT — what the product accepts, stores and plays back.
     *
     * Full HD at ten seconds is worth carrying whether or not a model ever reads it: a
     * ground with no replay at all gains most of the value from a scorer simply watching
     * the delivery again, in a resolution where the ball is visible.
     */
    public const MAX_REVIEW_BYTES = 50 * 1024 * 1024;

    public const MAX_REVIEW_MB = 50;

    public const MAX_REVIEW_SECONDS = 10;

    /**
     * THE VERTEX CEILING — a different number, for a different reason, and the two must
     * never be conflated again.
     *
     * Video reaches Gemini inline, base64 in the request body, and base64 inflates by
     * four thirds. Vertex caps a generateContent request in the low tens of megabytes, so
     * the largest raw clip that fits with room for the prompt and the schema is around
     * fourteen. Fifty megabytes of mp4 would be sixty-seven on the wire and would be
     * refused by the API — raising this constant does not raise that ceiling, it only
     * moves the failure somewhere harder to explain.
     *
     * Lifting this for real needs one of two things, neither of which exists here yet:
     * a transcode step that hands Vertex a smaller derivative while the original stays
     * for playback, or a GCS bucket so the clip goes by fileData reference instead of
     * inline. Until then a clip can be perfectly playable and still be too big to read,
     * and the app says exactly that rather than failing quietly.
     */
    public const MAX_VERTEX_INLINE_BYTES = 14 * 1024 * 1024;

    public const MAX_VERTEX_INLINE_MB = 14;

    /**
     * Kept as an alias so nothing that referenced the old name breaks.
     *
     * @deprecated Use MAX_REVIEW_BYTES for upload, MAX_VERTEX_INLINE_BYTES for analysis.
     */
    public const MAX_BYTES = self::MAX_VERTEX_INLINE_BYTES;

    /** @deprecated see MAX_BYTES */
    public const MAX_MB = self::MAX_VERTEX_INLINE_MB;

    /**
     * Why the last review returned null, in words safe to show a user.
     *
     * Never an exception message and never an upstream body: those go to the log. This
     * is read straight out to a phone, so it says what the person can do about it.
     */
    private string $failure = 'That footage could not be reviewed.';

    public function lastFailure(): string
    {
        return $this->failure;
    }

    /** Factors an umpire weighs, in the order they are weighed. */
    public const FACTORS = ['pitching', 'impact', 'bat_involved', 'height', 'line'];

    /**
     * WHERE A NUMBER CAME FROM.
     *
     * Every measured value in a delivery carries one of these, and the code that produced
     * the value assigns it — never the model. A language model asked to label its own
     * output "computer_vision" would happily do so, and the whole point of the field is to
     * tell a measured quantity apart from an inferred one. Anything arriving from Vertex
     * is stamped SOURCE_VERTEX here, unconditionally, whatever the JSON claims.
     */
    public const SOURCE_VERTEX = 'vertex';

    /** Measured by deterministic computer vision. Nothing sets this yet — see below. */
    public const SOURCE_CV = 'computer_vision';

    /** Filled in between two real observations, for drawing only. Never an observation. */
    public const SOURCE_INTERPOLATED = 'interpolated';

    public const SOURCE_UNKNOWN = 'unknown';

    public function __construct(private readonly GeminiText $gemini) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * @param  string  $path  the clip's path on the public disk
     * @param  string  $kind  'lbw' or 'bowler'
     * @return array|null null whenever the read cannot be trusted — never a guess
     */
    public function review(string $path, string $kind = 'lbw'): ?array
    {
        $this->failure = 'That footage could not be reviewed.';

        if (! Storage::disk('public')->exists($path)) {
            Log::warning('delivery-review: clip missing', ['path' => $path]);
            $this->failure = 'That clip is no longer on the server.';

            return null;
        }

        return $this->analyse(Storage::disk('public')->path($path), $kind);
    }

    /**
     * Review a file that has already been prepared for sending.
     *
     * The queued job hands over a derivative built by ReviewVideoPreparer, which may be a
     * temporary encode or the original when it was already small enough. Either way the
     * size has been settled by then, so this trusts the caller about the path and still
     * checks the bytes — the ceiling is the transport's, and nothing gets to skip it.
     */
    public function reviewFile(string $absolutePath, string $kind = 'lbw'): ?array
    {
        $this->failure = 'That footage could not be reviewed.';

        if (! is_readable($absolutePath)) {
            $this->failure = 'That clip is no longer on the server.';

            return null;
        }

        return $this->analyse($absolutePath, $kind);
    }

    /** The actual call. Everything above it is about deciding which bytes to send. */
    private function analyse(string $absolutePath, string $kind): ?array
    {
        $bytes = (int) filesize($absolutePath);
        if ($bytes <= 0 || $bytes > self::MAX_VERTEX_INLINE_BYTES) {
            Log::warning('delivery-review: clip too large for inline Vertex', ['bytes' => $bytes]);
            // Says which limit was hit. The clip is still fine to keep and to watch — it
            // is only too big to send to the model — and the wording has to carry that
            // difference or a scorer reads it as "your footage was rejected".
            $this->failure = 'This clip plays fine but is too large to analyse (limit '
                . self::MAX_VERTEX_INLINE_MB . 'MB). Record a shorter delivery to review it.';

            return null;
        }

        [$url, $headers] = $this->endpoint();
        if ($url === null) {
            $this->failure = 'Review is not configured on this server.';

            return null;
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt($kind)]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    $this->videoPart($absolutePath),
                    ['text' => $this->askPrompt($kind)],
                ],
            ]],
            'generationConfig' => [
                // Low, not zero: the model still has to describe what it sees, but this is
                // a measurement task and creative variation is a defect here.
                'temperature' => 0.1,
                // Sized for the answer, not for the deliberation.
                //
                // Thinking tokens count against this budget on Gemini 3.x, and with the
                // delivery block added the model spent 860 of 900 on thought and 22 on
                // the reply — returning 93 characters of truncated JSON that parsed as
                // nothing. GeminiText already carried this scar; this class did not.
                //
                // Thinking is switched off and the budget raised: the response can now
                // carry a tracked ball path, which is many times longer than the five
                // enum factors this number was originally chosen for.
                'maxOutputTokens' => 2500,
                'thinkingConfig' => ['thinkingBudget' => 0],
                'candidateCount' => 1,
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->schema($kind),
            ],
        ];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders($headers)->post($url, $body);
            if (! $response->successful()) {
                Log::warning('delivery-review: HTTP ' . $response->status(), [
                    'body' => mb_substr((string) $response->body(), 0, 400),
                ]);
                // The status is the most a user should ever see of an upstream failure.
                $this->failure = 'The review service did not answer (' . $response->status() . ').';

                return null;
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $parsed = json_decode($text, true);
            if (! is_array($parsed)) {
                $this->failure = 'The review came back unreadable.';

                return null;
            }
            $clean = $this->sanitise($parsed, $kind);
            if ($clean === null) {
                $this->failure = 'Nothing usable could be read from that footage.';
            }

            return $clean;
        } catch (\Throwable $e) {
            Log::warning('delivery-review failed: ' . $e->getMessage());
            $this->failure = 'The review could not be completed. Try again.';

            return null;
        }
    }

    /**
     * Keep only fields we asked for, and only values from the enums we defined.
     *
     * Controlled generation makes a malformed response unlikely, not impossible, and this
     * output drives which components the app draws. An unrecognised enum value would
     * render as a blank row, so it is dropped here rather than shipped to a screen.
     */
    private function sanitise(array $raw, string $kind): ?array
    {
        if ($kind === 'bowler') {
            $out = [
                'armAction' => $this->pick($raw['armAction'] ?? null, ['side_on', 'front_on', 'midway', 'unclear']),
                'frontFoot' => $this->pick($raw['frontFoot'] ?? null, ['behind_line', 'on_line', 'over_line', 'not_visible']),
                'notes' => $this->line($raw['notes'] ?? null),
                'visibility' => $this->pick($raw['visibility'] ?? null, ['good', 'partial', 'poor']),
            ];

            return $out['visibility'] === null ? null : $out;
        }

        $factors = [];
        foreach (self::FACTORS as $factor) {
            $value = $this->pick(
                data_get($raw, "factors.$factor.reading"),
                $this->readingsFor($factor),
            );
            if ($value === null) {
                continue;
            }
            $factors[$factor] = [
                'reading' => $value,
                // "cannot tell" is a first-class answer, so a factor is allowed to be
                // present and unresolved. That is the honest shape of a phone camera.
                //
                // Strictly identical to true, never a cast: (bool) "unsure" is TRUE in
                // PHP, and that string would have lit the certainty dot on a review the
                // model was telling us it was not sure about. Only a real boolean true
                // counts as certain; anything else is treated as doubt.
                'certain' => data_get($raw, "factors.$factor.certain") === true,
            ];
        }

        if ($factors === []) {
            return null;
        }

        return [
            'factors' => $factors,
            'visibility' => $this->pick($raw['visibility'] ?? null, ['good', 'partial', 'poor']) ?? 'poor',
            'notes' => $this->line($raw['notes'] ?? null),
            'delivery' => $this->delivery($raw['delivery'] ?? null),
        ];
    }

    /**
     * The coordinate evidence, cleaned hard enough to draw from.
     *
     * This exists so a future renderer has something real to plot. Everything about it is
     * defensive, because a coordinate is more dangerous than a label: a wrong word reads
     * as a wrong opinion, whereas a wrong point renders as a confident dot on a pitch map
     * and looks like a measurement.
     *
     * The rules, in order of how much they matter:
     *
     *  - detected is DERIVED, never trusted. A model claiming it saw the pitching point
     *    while giving coordinates outside the frame did not see it, whatever it said.
     *  - Coordinates are normalised 0..1 IMAGE coordinates and are labelled as such.
     *    Nothing here is pitch-relative, because nothing here is calibrated: no stump
     *    height, no crease reference, no camera pose. A field called pitch_x would be a
     *    lie told in a variable name.
     *  - projected is kept apart from detected all the way through, because a predicted
     *    path drawn like an observed one is the most misleading thing this could ship.
     *  - Confidence is the model own estimate. It is stored because it is a real value
     *    the pipeline returned, and named so nobody mistakes it for measured accuracy:
     *    no calibration has ever been done against annotated footage.
     */
    private function delivery(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $ballPoints = $this->points(data_get($raw, 'ball.points'));
        $ball = [
            // Derived: no usable points means nothing was tracked, whatever was claimed.
            'detected' => $ballPoints !== [],
            'points' => $ballPoints,
            'modelConfidence' => $this->confidence(data_get($raw, 'ball.confidence')),
            // Assigned here, never read from the response.
            'source' => $ballPoints === [] ? self::SOURCE_UNKNOWN : self::SOURCE_VERTEX,
        ];

        $projection = null;
        if (is_array($raw['wicketProjection'] ?? null)) {
            $p = $raw['wicketProjection'];
            $stumps = $this->pick($p['stumpsHit'] ?? null, ['would_hit', 'would_miss', 'cannot_tell'])
                ?? 'cannot_tell';
            $projection = [
                // A projection only counts as made when it says something. A
                // "predicted: true" carrying cannot_tell is not a prediction.
                'predicted' => $stumps !== 'cannot_tell',
                'stumpsHit' => $stumps,
                'x' => $this->coordinate($p['x'] ?? null),
                'y' => $this->coordinate($p['y'] ?? null),
                'modelConfidence' => $this->confidence($p['confidence'] ?? null),
                // Says out loud what this is, so a renderer cannot draw it as observed.
                'kind' => 'projected',
                'source' => self::SOURCE_VERTEX,
            ];
        }

        $pitching = $this->markedPoint($raw['pitching'] ?? null);
        $impact = $this->markedPoint($raw['impact'] ?? null);

        return [
            // Never "pitch". The model does not get to promote its own output to
            // real-world coordinates by returning a different string.
            'coordinateSpace' => 'image_normalised',

            /*
             * CALIBRATION — the gate on every real-world measurement.
             *
             * Hard-coded unavailable, and that is the honest answer rather than a stub.
             * Nothing in this pipeline has ever located a crease, a stump or a pitch edge,
             * so there is no mapping from pixels to metres. Until something establishes
             * that scale, distance and speed cannot be computed at all — and a speed
             * derived from pixels without it would be a number with no unit pretending to
             * be km/h.
             *
             * The CV stage sets this to "image" or "pitch" when it can find the creases.
             */
            'calibration' => [
                'status' => 'unavailable',
                'source' => self::SOURCE_UNKNOWN,
            ],

            /*
             * TIMING — when the three events happened inside the clip.
             *
             * Taken from the points the model marked, not asked for separately: a
             * timestamp the model volunteers without a position behind it is a guess with
             * no evidence attached to check it against.
             */
            'timing' => [
                'releaseMs' => $ballPoints === [] ? null : $ballPoints[0]['timestampMs'],
                'bounceMs' => $pitching['timestampMs'] ?? null,
                'impactMs' => $impact['timestampMs'] ?? null,
                // Sourced on what actually survived, not on whether the model replied.
                // A timing block of three nulls did not come from Vertex in any useful
                // sense, and labelling it so implies evidence that is not there.
                'source' => ($ballPoints === []
                    && ($pitching['timestampMs'] ?? null) === null
                    && ($impact['timestampMs'] ?? null) === null)
                    ? self::SOURCE_UNKNOWN
                    : self::SOURCE_VERTEX,
            ],

            'ball' => $ball,
            'pitching' => $pitching,
            'impact' => $impact,

            /*
             * TRAJECTORY — observed only.
             *
             * The same points as the ball track, restated as the shape a renderer draws,
             * and deliberately carrying NO interpolated or projected points. Filling the
             * gaps where the ball was hidden is a drawing decision, and it belongs in the
             * renderer where it can be dashed, not in the evidence where it would look
             * measured.
             */
            'trajectory' => [
                'points' => $ballPoints,
                'confidence' => $ball['modelConfidence'],
                'source' => $ballPoints === [] ? self::SOURCE_UNKNOWN : self::SOURCE_VERTEX,
            ],

            /*
             * SPEED — null, always, for now.
             *
             * Speed is distance over time, and there is no distance without calibration.
             * A pixel displacement per millisecond is a real number that means nothing in
             * km/h, and printing it would be the single most convincing lie this feature
             * could tell — everyone believes a speed gun. It stays null until calibration
             * is available and a real scale exists.
             */
            'speed' => null,

            'wicketProjection' => $projection,
            'uncertainty' => $this->uncertainty($raw['uncertainty'] ?? null),
            'evidence' => $this->evidence($ball, $pitching, $impact, $projection),
        ];
    }

    /** One observed point: detected only when its coordinates actually survived. */
    private function markedPoint(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $x = $this->coordinate($raw['x'] ?? null);
        $y = $this->coordinate($raw['y'] ?? null);
        $detected = $x !== null && $y !== null;

        return [
            'detected' => $detected,
            'x' => $x,
            'y' => $y,
            // Both gated on detection. A timestamp for an event whose position was
            // rejected is a claim that something happened at a moment, with nothing to
            // check it against — and timing.bounceMs was picking it up and presenting it
            // as a located event.
            'timestampMs' => $detected ? $this->timestamp($raw['timestampMs'] ?? null) : null,
            'modelConfidence' => $detected ? $this->confidence($raw['confidence'] ?? null) : null,
            'kind' => 'detected',
            'source' => $detected ? self::SOURCE_VERTEX : self::SOURCE_UNKNOWN,
        ];
    }

    /**
     * The tracked path.
     *
     * Capped, sorted and de-duplicated by time: a renderer scrubbing a timeline needs
     * points in the order they happened, and a model that repeats a frame would draw a
     * stutter. Any point that is not fully valid is dropped rather than repaired, because
     * interpolating a missing coordinate would be inventing a position.
     *
     * @return list<array{timestampMs:int, x:float, y:float}>
     */
    private function points(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $point) {
            if (! is_array($point)) {
                continue;
            }
            $x = $this->coordinate($point['x'] ?? null);
            $y = $this->coordinate($point['y'] ?? null);
            $t = $this->timestamp($point['timestampMs'] ?? null);
            if ($x === null || $y === null || $t === null) {
                continue;
            }
            $out[$t] = ['timestampMs' => $t, 'x' => $x, 'y' => $y];
            if (count($out) >= 120) {
                break;
            }
        }
        ksort($out);

        return array_values($out);
    }

    /** Normalised 0..1, or null. Anything outside the frame was not seen in the frame. */
    private function coordinate(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if (is_nan($n) || is_infinite($n) || $n < 0.0 || $n > 1.0) {
            return null;
        }

        return round($n, 4);
    }

    /** Milliseconds inside a clip that can never exceed the review ceiling. */
    private function timestamp(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $ms = (int) $value;

        return ($ms < 0 || $ms > self::MAX_REVIEW_SECONDS * 1000) ? null : $ms;
    }

    /** The model own 0..1 estimate. Never calibrated, never presented as accuracy. */
    private function confidence(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if (is_nan($n) || is_infinite($n)) {
            return null;
        }
        // Some models answer 0-100 for a field described as a probability. A value above
        // one is read as a percentage rather than discarded; beyond that it is not a
        // confidence at all.
        if ($n > 1.0 && $n <= 100.0) {
            $n /= 100.0;
        }

        return ($n < 0.0 || $n > 1.0) ? null : round($n, 3);
    }

    /**
     * How much this review is actually standing on, per component.
     *
     * The point of grading evidence separately is that a review is not one claim but
     * several, and they fail independently: the camera can track the ball perfectly and
     * still have no idea whether it would have hit the stumps. Collapsing that into a
     * single "confidence: 82%" hides exactly the distinction a player needs.
     *
     * HIGH is deliberately hard to reach and, for wicket projection, unreachable. A
     * projected path from one uncalibrated camera cannot be high-confidence evidence no
     * matter how sure the model sounds, so it is capped at medium by construction rather
     * than by a threshold somebody could tune upward later.
     *
     * These grades are what a hybrid pipeline routes on: HIGH means take the deterministic
     * answer, MEDIUM means ask the model, LOW means say CANNOT TELL and stop.
     *
     * @return array<string,string>
     */
    private function evidence(array $ball, ?array $pitching, ?array $impact, ?array $projection): array
    {
        $grade = static function (bool $detected, ?float $confidence, bool $capAtMedium = false): string {
            if (! $detected) {
                return 'low';
            }
            if ($capAtMedium) {
                return 'medium';
            }
            if ($confidence === null) {
                // Detected but unquantified is real evidence with no weight behind it.
                return 'medium';
            }

            return $confidence >= 0.8 ? 'high' : ($confidence >= 0.5 ? 'medium' : 'low');
        };

        return [
            // A single point is a sighting, not a track. Three or more is a path.
            'ballTracking' => count($ball['points']) >= 3
                ? $grade(true, $ball['modelConfidence'])
                : ($ball['points'] === [] ? 'low' : 'medium'),
            'pitching' => $grade(
                (bool) ($pitching['detected'] ?? false),
                $pitching['modelConfidence'] ?? null,
            ),
            'impact' => $grade(
                (bool) ($impact['detected'] ?? false),
                $impact['modelConfidence'] ?? null,
            ),
            // Capped: see the note above. One camera, no calibration, no projection.
            'wicketProjection' => $grade(
                (bool) ($projection['predicted'] ?? false),
                $projection['modelConfidence'] ?? null,
                capAtMedium: true,
            ),
            // Nothing has ever been calibrated, so this is the one grade that is a
            // constant. It becomes real the day the CV stage finds a crease.
            'calibration' => 'low',
        ];
    }

    /** @return array<string,string>|null */
    private function uncertainty(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach (['ballTracking', 'pitching', 'impact', 'trajectory'] as $key) {
            $value = $this->pick($raw[$key] ?? null, ['low', 'medium', 'high']);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out === [] ? null : $out;
    }

    /** @return list<string> */
    private function readingsFor(string $factor): array
    {
        return match ($factor) {
            'pitching' => ['in_line', 'outside_off', 'outside_leg', 'cannot_tell'],
            'impact' => ['in_line', 'outside_off', 'outside_leg', 'cannot_tell'],
            'bat_involved' => ['bat_first', 'pad_first', 'no_bat', 'cannot_tell'],
            'height' => ['below_stumps', 'above_stumps', 'cannot_tell'],
            'line' => ['would_hit', 'would_miss', 'cannot_tell'],
            default => ['cannot_tell'],
        };
    }

    /** @param list<string> $allowed */
    private function pick(mixed $value, array $allowed): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : null;

        return ($value !== null && in_array($value, $allowed, true)) ? $value : null;
    }

    /** One short sentence, or nothing. Never a paragraph on a review screen. */
    private function line(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return ($text === '' || mb_strlen($text) > 160) ? null : $text;
    }

    private function schema(string $kind): array
    {
        if ($kind === 'bowler') {
            return [
                'type' => 'OBJECT',
                'properties' => [
                    'armAction' => ['type' => 'STRING', 'enum' => ['side_on', 'front_on', 'midway', 'unclear']],
                    'frontFoot' => ['type' => 'STRING', 'enum' => ['behind_line', 'on_line', 'over_line', 'not_visible']],
                    'visibility' => ['type' => 'STRING', 'enum' => ['good', 'partial', 'poor']],
                    'notes' => ['type' => 'STRING'],
                ],
                'required' => ['armAction', 'frontFoot', 'visibility'],
            ];
        }

        $factor = fn (array $enum): array => [
            'type' => 'OBJECT',
            'properties' => [
                'reading' => ['type' => 'STRING', 'enum' => $enum],
                'certain' => ['type' => 'BOOLEAN'],
            ],
            'required' => ['reading', 'certain'],
        ];

        $point = [
            'type' => 'OBJECT',
            'properties' => [
                'detected' => ['type' => 'BOOLEAN'],
                'x' => ['type' => 'NUMBER'],
                'y' => ['type' => 'NUMBER'],
                'timestampMs' => ['type' => 'INTEGER'],
                'confidence' => ['type' => 'NUMBER'],
            ],
            'required' => ['detected'],
        ];

        return [
            'type' => 'OBJECT',
            'properties' => [
                // THE EVIDENCE, as coordinates, for a future 2D/3D renderer.
                //
                // Asked for separately from the five verdict factors and never used to
                // derive them: a point on a frame is a different kind of claim from
                // "pitched in line", and mixing them would let a coordinate the model
                // guessed quietly change a reading a person will act on.
                'delivery' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'ball' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'detected' => ['type' => 'BOOLEAN'],
                                'confidence' => ['type' => 'NUMBER'],
                                'points' => [
                                    'type' => 'ARRAY',
                                    'items' => [
                                        'type' => 'OBJECT',
                                        'properties' => [
                                            'timestampMs' => ['type' => 'INTEGER'],
                                            'x' => ['type' => 'NUMBER'],
                                            'y' => ['type' => 'NUMBER'],
                                        ],
                                        'required' => ['timestampMs', 'x', 'y'],
                                    ],
                                ],
                            ],
                            'required' => ['detected'],
                        ],
                        'pitching' => $point,
                        'impact' => $point,
                        'wicketProjection' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'predicted' => ['type' => 'BOOLEAN'],
                                'stumpsHit' => [
                                    'type' => 'STRING',
                                    'enum' => ['would_hit', 'would_miss', 'cannot_tell'],
                                ],
                                'x' => ['type' => 'NUMBER'],
                                'y' => ['type' => 'NUMBER'],
                                'confidence' => ['type' => 'NUMBER'],
                            ],
                            'required' => ['predicted', 'stumpsHit'],
                        ],
                        'uncertainty' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'ballTracking' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high']],
                                'pitching' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high']],
                                'impact' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high']],
                                'trajectory' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high']],
                            ],
                        ],
                    ],
                ],
                'factors' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'pitching' => $factor(['in_line', 'outside_off', 'outside_leg', 'cannot_tell']),
                        'impact' => $factor(['in_line', 'outside_off', 'outside_leg', 'cannot_tell']),
                        'bat_involved' => $factor(['bat_first', 'pad_first', 'no_bat', 'cannot_tell']),
                        'height' => $factor(['below_stumps', 'above_stumps', 'cannot_tell']),
                        'line' => $factor(['would_hit', 'would_miss', 'cannot_tell']),
                    ],
                    'required' => ['pitching', 'impact', 'bat_involved', 'height', 'line'],
                ],
                'visibility' => ['type' => 'STRING', 'enum' => ['good', 'partial', 'poor']],
                'notes' => ['type' => 'STRING'],
            ],
            'required' => ['factors', 'visibility'],
        ];
    }

    private function systemPrompt(string $kind): string
    {
        if ($kind === 'bowler') {
            return <<<'TXT'
            You are analysing a short video of a cricket bowler's delivery, filmed on a
            phone at a local ground.

            Report only what is visible in the footage. If the crease is out of frame, the
            front foot is "not_visible" — do not infer it from the bowler's momentum. If
            the action is obscured or too far away, say the visibility is poor.

            You are not making a decision. You are describing what the camera caught.
            TXT;
        }

        return <<<'TXT'
        You are reviewing a short video of one cricket delivery, filmed on a phone at a
        local ground, where the fielding side has appealed for LBW.

        YOU ARE NOT THE UMPIRE. You never state whether the batter is out. You report, for
        each factor an umpire weighs, only what this footage actually shows.

        "cannot_tell" is the correct and expected answer whenever the camera does not
        settle the question, and most phone footage will not settle most of these. A single
        camera at an unknown angle cannot usually judge whether a ball would have gone on
        to hit the stumps — for that factor, "cannot_tell" is almost always right, and
        claiming otherwise is a serious error.

        Set "certain" to true only when the footage is unambiguous on that factor: the
        frame clearly shows it, the angle is suitable, and the ball and the batter are both
        visible at the moment in question. When in any doubt, "certain" is false.

        Judge the factors in cricket's terms:
        - pitching: where the ball landed, relative to the line of leg and off stump.
        - impact: where the ball first struck the batter, relative to the same lines.
        - bat_involved: whether the ball touched the bat before the pad.
        - height: whether impact was below or above stump height.
        - line: whether the ball was heading on to hit the stumps.

        In "notes", one short sentence about the footage itself - the angle, what is out of
        frame, anything that limits the review. Not an opinion on the appeal.

        THE "delivery" BLOCK - coordinates, kept separate from the factors above.

        Report where things happened IN THE IMAGE, as normalised coordinates: x from 0 at
        the left edge to 1 at the right, y from 0 at the top to 1 at the bottom. These are
        positions in the frame. They are NOT positions on the pitch, and you must not try
        to convert them into real-world measurements - the camera is uncalibrated and you
        do not know where it was standing.

        - ball.points: where the ball is in frames where you can actually see it, each with
          the time in milliseconds from the start of the clip. Omit frames where the ball
          is hidden, blurred past recognition, or you are guessing. A short honest track is
          worth more than a long invented one. An empty list is a perfectly good answer.
        - pitching: where the ball bounced, if you saw it bounce.
        - impact: where the ball first struck the batter, if you saw it strike.
        - wicketProjection: ONLY if the footage itself shows the ball continuing to the
          stumps. You cannot compute a trajectory from this video, so if the ball is
          blocked by the batter after impact the answer is stumpsHit "cannot_tell" and
          predicted false. Guessing here is the worst mistake you can make.
        - uncertainty: how much doubt there is in each of ball tracking, pitching, impact
          and trajectory - low, medium or high.

        Set "detected" false and leave coordinates out whenever you did not clearly see the
        thing. Missing evidence is expected and useful. Invented evidence is not.
        TXT;
    }

    private function askPrompt(string $kind): string
    {
        return $kind === 'bowler'
            ? 'Describe this bowling action from the footage.'
            : 'Review this appealed delivery factor by factor. Report only what the footage shows.';
    }

    /**
     * The video, as Vertex wants to receive it.
     *
     * Inline base64 today, which is why the derivative exists at all. Kept as its own
     * method because the other way to send video is a Cloud Storage reference:

     *     ['fileData' => ['mimeType' => ..., 'fileUri' => 'gs://bucket/clip.mp4']]

     * That path has no size ceiling worth worrying about and would retire the whole
     * ladder, so the day a bucket exists this is the single place that changes. Nothing
     * else in the service knows how the bytes travel.
     *
     * @return array<string, mixed>
     */
    private function videoPart(string $absolutePath): array
    {
        return ['inlineData' => [
            'mimeType' => $this->mimeFor($absolutePath),
            'data' => base64_encode((string) file_get_contents($absolutePath)),
        ]];
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            '3gp' => 'video/3gpp',
            default => 'video/mp4',
        };
    }

    /**
     * Vertex only. The public generativelanguage endpoint is fine for a sentence of text,
     * but this ships a video off a ground's connection and belongs on the project's own
     * service account, where it is billed, quota'd and auditable like everything else.
     *
     * @return array{0: ?string, 1: array<string,string>}
     */
    private function endpoint(): array
    {
        $path = trim((string) config('services.vertex.credentials'));
        if ($path !== '' && ! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }
        if ($path === '' || ! is_readable($path)) {
            Log::info('delivery-review: no Vertex credentials, skipping');

            return [null, []];
        }

        $token = app(GoogleServiceAccountToken::class)->get($path, self::SCOPE);
        if ($token === null) {
            return [null, []];
        }

        $project = (string) (config('services.vertex.project') ?: 'haraan');
        $location = (string) (config('services.vertex.location') ?: 'us-central1');
        $model = (string) (config('services.gemini.model') ?: 'gemini-3.6-flash');
        $host = $location === 'global'
            ? 'aiplatform.googleapis.com'
            : "{$location}-aiplatform.googleapis.com";

        return [
            "https://{$host}/v1/projects/{$project}/locations/{$location}"
                . "/publishers/google/models/{$model}:generateContent",
            ['Authorization' => 'Bearer ' . $token],
        ];
    }
}
