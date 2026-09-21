package com.haraan.app.vision.replay

import android.content.Context
import android.media.MediaCodec
import android.media.MediaCodecInfo
import android.media.MediaExtractor
import android.media.MediaFormat
import android.media.MediaMetadataRetriever
import android.net.Uri
import android.os.Build
import android.provider.OpenableColumns
import android.util.Log
import java.nio.ByteBuffer

/** One turn of the decoder's handle. */
sealed interface DecodeStep {

    /** A real frame, already adapted to the engine's layout. */
    data class Frame(val luma: LumaFrame, val presentationTimeUs: Long) : DecodeStep

    /**
     * The decoder produced an output buffer that could not be read as a luma plane.
     *
     * Reported rather than swallowed: a clip that yields a hundred of these is telling you
     * the decoder is handing back a colour format this harness does not understand, which
     * is a completely different problem from a detector that finds nothing.
     */
    data object Malformed : DecodeStep

    data object EndOfStream : DecodeStep
}

/**
 * An MP4 on disk, decoded one frame at a time.
 *
 * PULL, NOT PUSH. [nextFrame] hands back a single frame per call and returns between
 * frames, which is what makes pause, seek and cancellation honest: the controller stops by
 * simply not asking again, so there is never a decode callback running inside a codec that
 * somebody else has already released.
 *
 * NO SURFACE. The decoder is configured with a null surface and a flexible YUV colour
 * format so that [MediaCodec.getOutputImage] returns real planes. Decoding to a Surface
 * would be faster and would render beautifully, and would also make the pixels
 * unreachable — which for a harness whose entire purpose is to hand pixels to a detector
 * is the wrong trade in every direction.
 *
 * ONE FRAME IN MEMORY. The container is never read whole; the extractor streams samples
 * and each output buffer is released back to the codec before the next is asked for. A
 * four-minute clip costs the same memory as a four-second one.
 */
class VideoReplaySource private constructor(
    private val extractor: MediaExtractor,
    private var codec: MediaCodec,
    /** Kept so a seek can build a second decoder exactly like the first. */
    private val configuredFormat: MediaFormat,
    override val metadata: VideoMetadata,
    /**
     * The presentation timestamp of the track's first sample, read before any decoding.
     *
     * Fixed at open so that a given frame of a given file always carries the same engine
     * timestamp, no matter what order the harness seeks around in. Deriving it from
     * whichever frame happened to be decoded first would make the same clip produce
     * different gaps after a seek, and the gaps are the whole contract.
     */
    private val firstPresentationTimeUs: Long,
) : FrameSource {

    private var inputDone = false
    private var outputDone = false
    private var released = false
    private var scratch: ByteArray? = null
    private var copyBuffer: ByteArray? = null

    /**
     * Decode until one frame comes out, the clip ends, or the decoder misbehaves.
     *
     * @throws ReplayException when the codec throws, or stalls long enough that waiting
     *   further would hang the screen rather than fail it.
     */
    override fun nextFrame(): DecodeStep {
        check(!released) { "nextFrame() after release()" }
        if (outputDone) return DecodeStep.EndOfStream

        var idleTurns = 0
        val info = MediaCodec.BufferInfo()

        while (true) {
            try {
                if (!inputDone) feedInput()

                val index = codec.dequeueOutputBuffer(info, DEQUEUE_TIMEOUT_US)
                when {
                    index >= 0 -> {
                        val endOfStream = info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM != 0
                        // A codec-config buffer carries parameter sets, not a picture.
                        val isConfig = info.flags and MediaCodec.BUFFER_FLAG_CODEC_CONFIG != 0
                        val step = if (info.size > 0 && !isConfig) {
                            readFrame(index, info.presentationTimeUs)
                        } else {
                            null
                        }
                        codec.releaseOutputBuffer(index, false)
                        if (endOfStream) {
                            outputDone = true
                            return step ?: DecodeStep.EndOfStream
                        }
                        if (step != null) return step
                        idleTurns = 0
                    }

                    index == MediaCodec.INFO_TRY_AGAIN_LATER -> {
                        // Normal while the decoder fills its pipeline. Only a very long
                        // run of these means it has stopped making progress.
                        if (++idleTurns > MAX_IDLE_TURNS) {
                            val seconds = MAX_IDLE_TURNS * DEQUEUE_TIMEOUT_US / 1_000_000
                            throw ReplayException(
                                ReplayError.DecodeFailed("decoder produced no output for ${seconds}s"),
                            )
                        }
                    }

                    else -> idleTurns = 0
                }
            } catch (e: ReplayException) {
                throw e
            } catch (e: MediaCodec.CodecException) {
                throw ReplayException(ReplayError.DecodeFailed(e.diagnosticInfo), e)
            } catch (e: IllegalStateException) {
                throw ReplayException(ReplayError.DecodeFailed(e.message ?: "codec in bad state"), e)
            }
        }
    }

    private fun feedInput() {
        val index = codec.dequeueInputBuffer(DEQUEUE_TIMEOUT_US)
        if (index < 0) return
        val buffer: ByteBuffer = codec.getInputBuffer(index) ?: return

        val size = extractor.readSampleData(buffer, 0)
        if (size < 0) {
            codec.queueInputBuffer(index, 0, 0, 0L, MediaCodec.BUFFER_FLAG_END_OF_STREAM)
            inputDone = true
        } else {
            codec.queueInputBuffer(index, 0, size, extractor.sampleTime, 0)
            extractor.advance()
        }
    }

    /**
     * An output buffer into the engine's layout, or [DecodeStep.Malformed].
     *
     * The image is read and the caller releases the buffer immediately afterwards, so no
     * decoder-owned memory outlives this call. That matters more than it looks: holding an
     * Image past its releaseOutputBuffer starves the codec of buffers and deadlocks the
     * loop a few frames later, somewhere that looks nothing like the cause.
     */
    private fun readFrame(index: Int, presentationTimeUs: Long): DecodeStep {
        val image = runCatching { codec.getOutputImage(index) }.getOrNull()
            ?: return DecodeStep.Malformed
        try {
            val plane = image.planes.getOrNull(0) ?: return DecodeStep.Malformed
            val crop = image.cropRect
            val width = if (crop.width() > 0) crop.width() else image.width
            val height = if (crop.height() > 0) crop.height() else image.height

            val buffer = plane.buffer
            val length = buffer.remaining()
            if (length <= 0) return DecodeStep.Malformed

            // Reused between frames. A 1080p luma plane is two megabytes, and a fresh one
            // per frame turns a thirty-second clip into a gigabyte of garbage.
            val bytes = copyBuffer?.takeIf { it.size >= length }
                ?: ByteArray(length).also { copyBuffer = it }
            buffer.get(bytes, 0, length)

            val luma = LumaPlane.adapt(
                source = bytes,
                sourceLength = length,
                cropLeft = crop.left.coerceAtLeast(0),
                cropTop = crop.top.coerceAtLeast(0),
                width = width,
                height = height,
                rowStride = plane.rowStride,
                pixelStride = plane.pixelStride.coerceAtLeast(1),
                scratch = scratch,
            ) ?: return DecodeStep.Malformed

            // Hold on to the packed destination so the next frame reuses it. Only when it
            // is actually a separate array: on the pass-through path it IS copyBuffer.
            if (luma.bytes !== bytes) scratch = luma.bytes
            return DecodeStep.Frame(luma, presentationTimeUs)
        } catch (t: Throwable) {
            Log.w(TAG, "output image unreadable", t)
            return DecodeStep.Malformed
        } finally {
            runCatching { image.close() }
        }
    }

    /** Engine timestamp for a presentation timestamp from this clip. */
    override fun engineTimestampMs(presentationTimeUs: Long): Long =
        LumaPlane.engineTimestampMs(presentationTimeUs, firstPresentationTimeUs)

    /**
     * Jump to the sync sample at or before [positionUs] and start decoding again.
     *
     * SYNC SAMPLE, not the exact frame: a decoder cannot produce a P-frame without the
     * keyframe it refers to, so asking for an exact position would mean silently decoding
     * a run of frames and throwing them away — which would feed the detector a burst of
     * hidden frames and quietly change what the replay reports.
     */
    @Throws(ReplayException::class)
    override fun seekTo(positionUs: Long) {
        check(!released) { "seekTo() after release()" }

        extractor.seekTo(positionUs.coerceAtLeast(0L), MediaExtractor.SEEK_TO_PREVIOUS_SYNC)

        // A FRESH DECODER, rather than flush() on this one.
        //
        // flush() is the documented way to do this and it does not work reliably here:
        // on the emulator's software AVC decoder a flushed codec went on returning
        // INFO_TRY_AGAIN_LATER for every dequeue until the stall watchdog fired, with and
        // without the start() that the state diagram says follows a flush. Rather than
        // ship a guess about which sub-state it was really in, the decoder is rebuilt from
        // the format it was configured with — which has exactly one meaning on every
        // device, costs a few milliseconds, and happens only when a human drags the scrub
        // bar. Decoding itself never takes this path.
        runCatching { codec.stop() }
        runCatching { codec.release() }

        codec = runCatching {
            MediaCodec.createDecoderByType(metadata.mimeType).also {
                it.configure(configuredFormat, null, null, 0)
                it.start()
            }
        }.getOrElse {
            released = true
            throw ReplayException(
                ReplayError.DecoderInitFailed(it.message ?: it::class.java.simpleName),
                it,
            )
        }

        scratch = null
        copyBuffer = null
        inputDone = false
        outputDone = false
    }

    /** Back to the first sync sample. */
    override fun restart() = seekTo(0L)

    /**
     * Stop and hand everything back. Safe to call twice; the source is dead afterwards.
     *
     * stop() before release() on purpose: releasing a running codec is legal but leaves
     * the driver tidying up asynchronously, and on emulator software decoders that is
     * where the "buffer owned by client" warnings come from.
     */
    override fun release() {
        if (released) return
        released = true
        runCatching { codec.stop() }
        runCatching { codec.release() }
        runCatching { extractor.release() }
        scratch = null
        copyBuffer = null
    }

    companion object {
        private const val TAG = "VisionReplay"
        private const val DEQUEUE_TIMEOUT_US = 10_000L

        /** ~10s of nothing. Long enough for a slow emulator decoder, short enough to fail. */
        private const val MAX_IDLE_TURNS = 1_000

        /**
         * Open [uri] and get a decoder running, or fail with a reason worth reading.
         *
         * Every failure mode of this sequence is separated deliberately: "no decoder for
         * this MIME" and "the decoder would not start" are one exception class away from
         * each other and a fortnight apart in what you do about them.
         */
        @Throws(ReplayException::class)
        fun open(context: Context, uri: Uri): VideoReplaySource {
            val extractor = MediaExtractor()
            try {
                val setSourceResult = if (uri.scheme == "file" && uri.path != null) {
                    runCatching { extractor.setDataSource(uri.path!!) }
                        .recoverCatching { extractor.setDataSource(context, uri, null) }
                } else {
                    runCatching { extractor.setDataSource(context, uri, null) }
                }
                setSourceResult.getOrElse { throw ReplayException(ReplayError.InvalidUri, it) }

                var track = -1
                var mime: String? = null
                for (i in 0 until extractor.trackCount) {
                    val candidate = extractor.getTrackFormat(i).getString(MediaFormat.KEY_MIME).orEmpty()
                    if (candidate.startsWith("video/")) {
                        track = i
                        mime = candidate
                        break
                    }
                }
                if (track < 0 || mime == null) throw ReplayException(ReplayError.NoVideoTrack)

                extractor.selectTrack(track)
                val format = extractor.getTrackFormat(track)
                val width = format.optionalInt(MediaFormat.KEY_WIDTH) ?: 0
                val height = format.optionalInt(MediaFormat.KEY_HEIGHT) ?: 0
                if (width <= 0 || height <= 0) throw ReplayException(ReplayError.EmptyVideo)

                // Read before a single sample is consumed, so it is a property of the file
                // rather than of however this run happened to move through it.
                val firstPts = extractor.sampleTime.coerceAtLeast(0L)

                val durationMs = (format.optionalLong(MediaFormat.KEY_DURATION) ?: 0L) / 1000L
                val rotation = rotationOf(context, uri, format)

                format.setInteger(
                    MediaFormat.KEY_COLOR_FORMAT,
                    MediaCodecInfo.CodecCapabilities.COLOR_FormatYUV420Flexible,
                )

                val codec = runCatching { MediaCodec.createDecoderByType(mime) }
                    .getOrElse { throw ReplayException(ReplayError.UnsupportedCodec(mime), it) }

                runCatching {
                    // Null surface: the pixels have to come back to us, not go to a screen.
                    codec.configure(format, null, null, 0)
                    codec.start()
                }.getOrElse {
                    runCatching { codec.release() }
                    throw ReplayException(
                        ReplayError.DecoderInitFailed(it.message ?: it::class.java.simpleName),
                        it,
                    )
                }

                return VideoReplaySource(
                    extractor = extractor,
                    codec = codec,
                    configuredFormat = format,
                    metadata = VideoMetadata(
                        mimeType = mime,
                        width = width,
                        height = height,
                        rotationDegrees = rotation,
                        durationMs = durationMs,
                        displayName = displayNameOf(context, uri),
                    ),
                    firstPresentationTimeUs = firstPts,
                )
            } catch (e: ReplayException) {
                runCatching { extractor.release() }
                throw e
            } catch (t: Throwable) {
                runCatching { extractor.release() }
                throw ReplayException(ReplayError.DecodeFailed(t.message ?: "open failed"), t)
            }
        }

        /**
         * How far the buffer must be turned to look upright.
         *
         * This is passed to the engine exactly as CameraX's rotationDegrees is, because it
         * means the same thing: decoding to a ByteBuffer never applies the container's
         * rotation, so a clip filmed on a phone held upright arrives sideways in precisely
         * the way a sensor frame does. The engine turns it; nothing here does.
         */
        private fun rotationOf(context: Context, uri: Uri, format: MediaFormat): Int {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                format.optionalInt(MediaFormat.KEY_ROTATION)?.let { return normalise(it) }
            }
            val retriever = MediaMetadataRetriever()
            return try {
                if (uri.scheme == "file" && uri.path != null) {
                    runCatching { retriever.setDataSource(uri.path!!) }
                        .getOrElse { retriever.setDataSource(context, uri) }
                } else {
                    retriever.setDataSource(context, uri)
                }
                normalise(
                    retriever
                        .extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION)
                        ?.toIntOrNull() ?: 0,
                )
            } catch (_: Throwable) {
                0
            } finally {
                runCatching { retriever.release() }
            }
        }

        private fun normalise(degrees: Int) = ((degrees % 360) + 360) % 360

        /** The filename, when the provider will tell us. Never a filesystem path. */
        private fun displayNameOf(context: Context, uri: Uri): String {
            runCatching {
                context.contentResolver
                    .query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)
                    ?.use { cursor ->
                        if (cursor.moveToFirst() && cursor.columnCount > 0) {
                            cursor.getString(0)?.let { return it }
                        }
                    }
            }
            return uri.lastPathSegment ?: "clip"
        }
    }
}

/** Absent keys throw rather than return a default, which makes every read a try/catch. */
private fun MediaFormat.optionalInt(key: String): Int? =
    if (containsKey(key)) runCatching { getInteger(key) }.getOrNull() else null

private fun MediaFormat.optionalLong(key: String): Long? =
    if (containsKey(key)) runCatching { getLong(key) }.getOrNull() else null
