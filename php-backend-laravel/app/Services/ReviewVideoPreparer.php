<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Turns a Full-HD recording into something Vertex can actually be handed.
 *
 * The product records at 1080p and keeps up to fifty megabytes, because a review is only
 * worth having if the ball is visible in it and the original is what a player watches.
 * Vertex takes video inline as base64 inside the request body, which inflates by four
 * thirds against a request cap in the low tens of megabytes — so a twenty-megabyte clip
 * that plays perfectly cannot be sent at all.
 *
 * Record once in high quality, analyse from a derivative, keep the original. This class is
 * the middle clause. It never touches the original: it writes a temporary file, hands back
 * its path, and the caller deletes it whether the review succeeded or not.
 *
 * WHAT IT DOES NOT DO. It does not improve accuracy, and nothing here should ever be
 * described as if it did. Compression can only lose information; the most this achieves is
 * making analysis possible at all for clips that previously could not be sent. Whether the
 * derivative reads as well as the original is a question for annotated footage, and that
 * comparison has not been run.
 */
class ReviewVideoPreparer
{
    /**
     * Aim comfortably under the ceiling rather than at it.
     *
     * x264 hits a target size approximately, and container overhead varies with the
     * source. Encoding to exactly the limit produces a file that is occasionally over it,
     * and discovering that at the API is worse than leaving headroom here.
     */
    private const TARGET_BYTES = 12 * 1024 * 1024 + 512 * 1024; // 12.5MB

    /** A ten-second clip should never take this long; a hung encoder must not hold a worker. */
    private const ENCODE_TIMEOUT_SECONDS = 120;

    /**
     * The ladder, tried in order, stopping at the first rung that fits.
     *
     * Resolution is given up last and reluctantly. LBW analysis is looking for a small
     * fast object against grass, and pixels are exactly what makes that possible — so the
     * first rungs keep 1080p and spend quality on compression instead. Frame rate is never
     * on this ladder: release, bounce and impact are the whole subject, and dropping
     * frames throws away the evidence rather than the file size.
     *
     * @var list<array{height:int|null, crf:int, label:string}>
     */
    private const LADDER = [
        ['height' => null, 'crf' => 28, 'label' => '1080p crf28'],
        ['height' => null, 'crf' => 32, 'label' => '1080p crf32'],
        ['height' => 720,  'crf' => 30, 'label' => '720p crf30'],
        ['height' => 720,  'crf' => 34, 'label' => '720p crf34'],
        ['height' => 540,  'crf' => 34, 'label' => '540p crf34'],
    ];

    /** Why the last prepare() returned null, in words safe to show a user. */
    private string $failure = 'We could not prepare this video for AI review. Please try again.';

    public function lastFailure(): string
    {
        return $this->failure;
    }

    public function ffmpegAvailable(): bool
    {
        return $this->ffmpegPath() !== null;
    }

    /**
     * @param  string  $publicPath  the original's path on the public disk — never modified
     * @return PreparedVideo|null null when no safe derivative could be produced
     */
    public function prepare(string $publicPath): ?PreparedVideo
    {
        $startedAt = microtime(true);

        if (! Storage::disk('public')->exists($publicPath)) {
            $this->failure = 'That clip is no longer on the server.';

            return null;
        }

        $original = Storage::disk('public')->path($publicPath);
        $originalBytes = (int) filesize($original);

        // ALREADY SMALL ENOUGH — send it untouched.
        //
        // Re-encoding a clip that already fits would spend CPU to make the evidence worse.
        // A five-megabyte recording goes to Vertex exactly as the camera wrote it.
        if ($originalBytes > 0 && $originalBytes <= DeliveryReview::MAX_VERTEX_INLINE_BYTES) {
            return new PreparedVideo(
                path: $original,
                bytes: $originalBytes,
                temporary: false,
                strategy: 'original',
                preparationMs: (int) round((microtime(true) - $startedAt) * 1000),
            );
        }

        $ffmpeg = $this->ffmpegPath();
        if ($ffmpeg === null) {
            // Honest, and specific about which half failed: the upload was fine and the
            // clip still plays. Only the analysis cannot happen.
            Log::warning('review-preparer: ffmpeg unavailable, cannot shrink clip', [
                'bytes' => $originalBytes,
            ]);
            $this->failure = 'This video is too large for AI analysis. Please record a shorter clip.';

            return null;
        }

        foreach (self::LADDER as $rung) {
            $derivative = $this->encode($ffmpeg, $original, $rung);
            if ($derivative === null) {
                continue;
            }

            $bytes = (int) filesize($derivative);
            if ($bytes > 0 && $bytes <= self::TARGET_BYTES) {
                return new PreparedVideo(
                    path: $derivative,
                    bytes: $bytes,
                    temporary: true,
                    strategy: $rung['label'],
                    preparationMs: (int) round((microtime(true) - $startedAt) * 1000),
                );
            }

            // Too big still: drop it now rather than carrying failed attempts on disk
            // while the ladder continues.
            @unlink($derivative);
        }

        Log::warning('review-preparer: every rung exceeded the target', [
            'originalBytes' => $originalBytes,
        ]);
        $this->failure = 'This video is too large for AI analysis. Please record a shorter clip.';

        return null;
    }

    /**
     * One rung. Returns the temporary file, or null if ffmpeg refused it.
     *
     * @param  array{height:int|null, crf:int, label:string}  $rung
     */
    private function encode(string $ffmpeg, string $original, array $rung): ?string
    {
        $target = tempnam(sys_get_temp_dir(), 'haraan-ai-') . '.mp4';

        $args = [
            $ffmpeg,
            '-y',
            '-i', $original,
            // NO AUDIO. The prompt asks the model to look, never to listen, and a review
            // that turned on a snick would need a calibrated microphone rather than
            // whatever a phone caught across a maidan. Dropped from the derivative only —
            // the original keeps its sound.
            '-an',
            '-c:v', 'libx264',
            '-crf', (string) $rung['crf'],
            // Fast enough that a queue worker is not held for minutes, slow enough that
            // the compression is worth having.
            '-preset', 'veryfast',
            // Frame rate is deliberately not set: whatever the phone shot, the derivative
            // keeps, because the events being judged last a frame or two.
            '-movflags', '+faststart',
        ];

        if ($rung['height'] !== null) {
            // Height-bound, width auto to an even number — odd widths are invalid for
            // yuv420p and ffmpeg will refuse the encode outright.
            $args[] = '-vf';
            $args[] = 'scale=-2:' . $rung['height'];
        }

        $args[] = $target;

        try {
            $process = new Process($args);
            $process->setTimeout(self::ENCODE_TIMEOUT_SECONDS);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
                // ffmpeg's stderr goes to the log and never to a user: it carries file
                // paths and build details that mean nothing to a scorer.
                Log::warning('review-preparer: encode failed', [
                    'rung' => $rung['label'],
                    'stderr' => mb_substr($process->getErrorOutput(), 0, 400),
                ]);
                @unlink($target);

                return null;
            }

            return $target;
        } catch (ProcessFailedException|\Throwable $e) {
            Log::warning('review-preparer: encode threw', [
                'rung' => $rung['label'],
                'error' => $e->getMessage(),
            ]);
            @unlink($target);

            return null;
        }
    }

    /**
     * The ffmpeg binary, or null when there is not one.
     *
     * Absent on the Windows dev machine and normally present on a Linux server, so this
     * has to be a question asked at runtime rather than an assumption baked into the
     * pipeline. FFMPEG_PATH overrides for an unusual install.
     */
    private function ffmpegPath(): ?string
    {
        $configured = trim((string) config('services.ffmpeg.path', ''));
        if ($configured !== '' && is_executable($configured)) {
            return $configured;
        }

        try {
            $probe = new Process([$configured !== '' ? $configured : 'ffmpeg', '-version']);
            $probe->setTimeout(10);
            $probe->run();

            return $probe->isSuccessful() ? ($configured !== '' ? $configured : 'ffmpeg') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

/**
 * What came out of preparation.
 *
 * [temporary] is the instruction to the caller: true means this file exists only for the
 * Vertex call and must be deleted afterwards, false means it IS the original and deleting
 * it would destroy the recording. Getting that backwards loses a player's footage, so it
 * is a named flag rather than something inferred from a path.
 */
final class PreparedVideo
{
    public function __construct(
        public readonly string $path,
        public readonly int $bytes,
        public readonly bool $temporary,
        /** Which rung produced this, e.g. "original" or "720p crf30". For the log. */
        public readonly string $strategy,
        public readonly int $preparationMs,
    ) {}

    public function discard(): void
    {
        if ($this->temporary && is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
