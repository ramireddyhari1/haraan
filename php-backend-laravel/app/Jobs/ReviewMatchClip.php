<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DeliveryReview;
use App\Services\ReviewVideoPreparer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends one clip to Vertex and records what came back.
 *
 * This exists because the review used to run inside the HTTP request. A video model
 * handed several seconds of footage takes 8 seconds on a good day and the client had a
 * 90-second timeout to sit through — which holds a PHP worker for the whole call, and
 * behind nginx at a ground's connection would simply time out with nothing to show.
 *
 * Queued, so the request returns immediately and the phone polls. With
 * QUEUE_CONNECTION=sync it still runs inline exactly as before, which is the correct
 * behaviour for a dev machine with no worker: the review completes, just synchronously.
 * Production needs `queue:work` for this to be worth anything.
 *
 * The clip is prepared before it is sent: a Full-HD recording is usually larger than
 * Vertex will accept inline, so ReviewVideoPreparer builds a temporary derivative and this
 * job deletes it afterwards. The original is never modified and never deleted, whatever
 * happens to the review.
 *
 * One attempt, on purpose. A retry means a second video upload to Vertex and a second
 * bill for footage that was already judged unreadable, and the two failures that matter
 * here — footage the model cannot read, and a clip too large to send — will both fail
 * again identically. A transient network error is the scorer's tap away from a retry.
 */
final class ReviewMatchClip implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Comfortably past the service's own 90s HTTP timeout, so the job never dies first. */
    public int $timeout = 150;

    public function __construct(
        private readonly int $clipId,
        private readonly string $path,
        private readonly string $kind,
    ) {}

    public function handle(DeliveryReview $service, ReviewVideoPreparer $preparer): void
    {
        // A second request for the same clip while this one is in flight would be a
        // second Vertex call for one answer. Claiming the row means the loser of that
        // race does nothing.
        $claimed = DB::table('match_device_clips')
            ->where('id', $this->clipId)
            ->where('review_status', DeliveryReview::STATUS_PENDING)
            ->update([
                'review_status' => DeliveryReview::STATUS_PROCESSING,
                'updated_at' => now(),
            ]);
        if ($claimed === 0) {
            return;
        }

        $startedAt = microtime(true);

        // PREPARE, then analyse. The original is never the thing that gets sent unless it
        // already fits, and it is never the thing that gets deleted.
        $prepared = $preparer->prepare($this->path);
        if ($prepared === null) {
            $this->fail(
                $preparer->lastFailure(),
                (int) round((microtime(true) - $startedAt) * 1000),
            );

            return;
        }

        try {
            $vertexStartedAt = microtime(true);
            $review = $service->reviewFile($prepared->path, $this->kind);
            $vertexMs = (int) round((microtime(true) - $vertexStartedAt) * 1000);
        } finally {
            // Deleted on every path, including an exception on the way out of Vertex.
            // A worker that dies mid-encode is the one case this cannot cover, which is
            // why derivatives live in the system temp directory and not beside the
            // originals — the OS clears up after us.
            $prepared->discard();
        }

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($review === null) {
            $this->fail($service->lastFailure(), $elapsedMs);

            return;
        }

        // Timing lives inside the analysis JSON rather than in new columns: review_ms
        // already carries the total, and splitting it is diagnostic detail, not something
        // the app queries on.
        $review['timing'] = [
            'preparationMs' => $prepared->preparationMs,
            'vertexMs' => $vertexMs,
            'strategy' => $prepared->strategy,
            'sentBytes' => $prepared->bytes,
        ];

        DB::table('match_device_clips')->where('id', $this->clipId)->update([
            'analysis' => json_encode($review),
            'analysed_at' => now(),
            'review_status' => DeliveryReview::STATUS_COMPLETED,
            'review_error' => null,
            'review_ms' => $elapsedMs,
            'updated_at' => now(),
        ]);
    }

    /** The queue gave up — record it as a failure rather than leaving the row processing. */
    public function failed(?\Throwable $e): void
    {
        Log::warning('review-clip job failed', [
            'clip' => $this->clipId,
            'error' => $e?->getMessage(),
        ]);
        $this->fail('The review did not finish. Try again.', null);
    }

    private function fail(string $reason, ?int $elapsedMs): void
    {
        DB::table('match_device_clips')->where('id', $this->clipId)->update([
            'review_status' => DeliveryReview::STATUS_FAILED,
            'review_error' => mb_substr($reason, 0, 160),
            'review_ms' => $elapsedMs,
            'updated_at' => now(),
        ]);
    }
}
