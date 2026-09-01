<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DeliveryReview;
use App\Services\ReviewVideoPreparer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Record once in high quality, analyse from a derivative, keep the original.
 *
 * The rule these tests exist to defend is the last clause. A player's Full-HD recording is
 * the authoritative asset — it is what they watch, and it is the only copy. Anything in
 * this pipeline that deletes it, shrinks it in place, or hands it back as "the video" is a
 * bug that destroys footage, so several of these assert on the original still being byte
 * for byte what it was.
 *
 * These do not measure whether compression hurts analysis. That needs annotated cricket
 * footage and has not been run.
 */
class ReviewVideoPreparerTest extends TestCase
{
    private string $publicPath = 'test-clips/original.mp4';

    private function writeOriginal(int $bytes): string
    {
        // Not a real video: preparation is size-driven, and every path these tests reach
        // either passes the file through untouched or hands it to ffmpeg, which is
        // skipped when absent.
        //
        // Sized with ftruncate rather than str_repeat. A fifty-megabyte string is a
        // fifty-megabyte allocation, and PHP's default 128MB limit does not survive one
        // per test — the first version of this helper exhausted memory and killed the
        // run. ftruncate asks the filesystem for the length and allocates nothing.
        Storage::disk('public')->put($this->publicPath, '');
        $path = Storage::disk('public')->path($this->publicPath);
        $handle = fopen($path, 'r+');
        ftruncate($handle, $bytes);
        fclose($handle);
        clearstatcache(true, $path);

        return $path;
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->deleteDirectory('test-clips');
        parent::tearDown();
    }

    /**
     * A clip that already fits is sent exactly as the camera wrote it.
     *
     * Re-encoding it would spend CPU to make the evidence worse, and would mark the
     * original as temporary — which is how a recording gets deleted.
     */
    public function test_a_small_clip_is_passed_through_untouched(): void
    {
        $original = $this->writeOriginal(5 * 1024 * 1024);
        $prepared = app(ReviewVideoPreparer::class)->prepare($this->publicPath);

        $this->assertNotNull($prepared);
        $this->assertSame('original', $prepared->strategy);
        $this->assertSame($original, $prepared->path);
        $this->assertFalse($prepared->temporary, 'the original must never be marked temporary');

        // discard() must be a no-op on a non-temporary file.
        $prepared->discard();
        $this->assertFileExists($original);
    }

    public function test_a_clip_at_the_vertex_ceiling_is_still_passed_through(): void
    {
        $this->writeOriginal(DeliveryReview::MAX_VERTEX_INLINE_BYTES);
        $prepared = app(ReviewVideoPreparer::class)->prepare($this->publicPath);

        $this->assertNotNull($prepared);
        $this->assertSame('original', $prepared->strategy);
        $this->assertLessThanOrEqual(DeliveryReview::MAX_VERTEX_INLINE_BYTES, $prepared->bytes);
    }

    /**
     * The whole reason this class exists: 20-30MB is what a Full-HD ten-second delivery
     * actually weighs, and it is over the inline ceiling.
     */
    public function test_a_full_hd_sized_clip_needs_preparation(): void
    {
        $original = $this->writeOriginal(24 * 1024 * 1024);
        $preparer = app(ReviewVideoPreparer::class);
        $prepared = $preparer->prepare($this->publicPath);

        if (! $preparer->ffmpegAvailable()) {
            // No encoder: refused honestly, and the phrasing must not blame the upload,
            // which succeeded.
            $this->assertNull($prepared);
            $this->assertStringContainsString('too large for AI analysis', $preparer->lastFailure());
            $this->assertStringNotContainsString('upload', strtolower($preparer->lastFailure()));
        } else {
            $this->assertNotNull($prepared);
            $this->assertTrue($prepared->temporary);
            $this->assertNotSame($original, $prepared->path);
            $this->assertLessThanOrEqual(DeliveryReview::MAX_VERTEX_INLINE_BYTES, $prepared->bytes);
        }

        // Either way — and this is the point — the original is untouched.
        $this->assertFileExists($original);
        $this->assertSame(24 * 1024 * 1024, filesize($original));
    }

    public function test_a_fifty_megabyte_clip_never_reaches_vertex_whole(): void
    {
        $original = $this->writeOriginal(DeliveryReview::MAX_REVIEW_BYTES);
        $preparer = app(ReviewVideoPreparer::class);
        $prepared = $preparer->prepare($this->publicPath);

        if ($prepared !== null) {
            $this->assertLessThanOrEqual(DeliveryReview::MAX_VERTEX_INLINE_BYTES, $prepared->bytes);
            $prepared->discard();
        } else {
            $this->assertStringContainsString('too large for AI analysis', $preparer->lastFailure());
        }

        $this->assertFileExists($original);
    }

    public function test_a_missing_clip_fails_safely(): void
    {
        $preparer = app(ReviewVideoPreparer::class);

        $this->assertNull($preparer->prepare('test-clips/not-here.mp4'));
        $this->assertSame('That clip is no longer on the server.', $preparer->lastFailure());
    }

    /**
     * Failure messages are read straight out to a phone, so they must never carry a
     * filesystem path, an ffmpeg invocation or an exception. Those go to the log.
     */
    public function test_failure_messages_leak_nothing(): void
    {
        $this->writeOriginal(24 * 1024 * 1024);
        $preparer = app(ReviewVideoPreparer::class);
        $preparer->prepare($this->publicPath);

        $message = $preparer->lastFailure();
        foreach (['ffmpeg', 'libx264', '/', '\\', 'crf', 'Exception', storage_path()] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase(
                $leak,
                $message,
                "failure message leaked: $leak",
            );
        }
    }

    /** Temporary derivatives belong in the system temp dir, never beside the originals. */
    public function test_derivatives_are_not_written_into_public_storage(): void
    {
        $this->writeOriginal(24 * 1024 * 1024);
        $preparer = app(ReviewVideoPreparer::class);
        $prepared = $preparer->prepare($this->publicPath);

        if ($prepared === null || ! $prepared->temporary) {
            $this->markTestSkipped('No derivative produced on this machine (no ffmpeg).');
        }

        $this->assertStringStartsWith(sys_get_temp_dir(), $prepared->path);
        $this->assertStringNotContainsString(
            Storage::disk('public')->path(''),
            $prepared->path,
            'a derivative in public storage would be world-readable',
        );

        $prepared->discard();
        $this->assertFileDoesNotExist($prepared->path);
    }

    public function test_discard_removes_a_temporary_file_and_is_idempotent(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'haraan-test-');
        file_put_contents($temp, 'derivative');

        $prepared = new \App\Services\PreparedVideo(
            path: $temp,
            bytes: (int) filesize($temp),
            temporary: true,
            strategy: '720p crf30',
            preparationMs: 10,
        );

        $prepared->discard();
        $this->assertFileDoesNotExist($temp);

        // Called twice — a failure path may discard after the finally block already did.
        $prepared->discard();
        $this->assertFileDoesNotExist($temp);
    }
}
