package com.haraan.app.vision

import android.content.Context
import android.media.Image
import android.media.MediaCodec
import android.media.MediaCodecInfo
import android.media.MediaCodecList
import android.media.MediaExtractor
import android.media.MediaFormat
import android.net.Uri
import android.os.Build
import java.io.Closeable

/**
 * EVERY FRAME OF ANY VIDEO THE PHONE CAN PLAY, SHRUNK TO THE ANALYSIS SIZE AS IT IS DECODED.
 *
 * Track a clip used to ask [android.media.MediaMetadataRetriever] for whole frames as bitmaps.
 * That is the thumbnail decoder: it hands back full-size frames (16 MB each at 4K) and on many
 * phones refuses outright what the phone's camera records — a 4K 120 fps 10-bit HDR HEVC
 * iPhone clip failed on a Motorola with "this clip couldn't be tracked".
 *
 * This runs the file once through the phone's own video decoder — the one that plays it, built
 * for 4K and for 10-bit HDR — and samples each frame straight from the decoder's YUV planes into
 * the 720x1280 upright picture the tracker reads. No full-size bitmap is ever made.
 *
 * What it takes: any codec the phone decodes (H.264, HEVC, VP9, AV1…), 8-bit or 10-bit, any
 * resolution, any rotation tag, any frame rate, in any container the platform reads (MP4,
 * MOV, 3GP, WebM, MKV). HDR is tone-mapped to ordinary video by the decoder where Android
 * offers it (13+); otherwise the 10-bit samples are read at 8-bit. A Dolby Vision track the
 * phone has no decoder for is decoded through its base layer (HEVC, AVC or AV1).
 *
 * Throws [ClipProblem] when nothing on the phone can decode it; the caller falls back.
 */
internal class VideoFrameStream private constructor(
    private val extractor: MediaExtractor,
    private val format: MediaFormat,
    /** The clockwise turn that stands the stored picture up, from the file. */
    val rotation: Int,
) : Closeable {

    private var codec: MediaCodec? = null
    private val mapper = UprightSampler(ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H)
    private var yBuf = ByteArray(0)
    private var uBuf = ByteArray(0)
    private var vBuf = ByteArray(0)
    private var loggedFormat = false
    /** Nanoseconds spent waiting on the decoder and sampling its frames, for the timing log. */
    var waitNs = 0L
    var sampleNs = 0L

    /**
     * Decodes from the start of the clip and, for every frame index in [from]..[to] that is
     * [step] apart from [from], fills [out] (upright 720x1280 ARGB) and calls [onFrame] with
     * its index and its presentation time in ms from the clip's first frame. Stops after [to].
     */
    fun decode(from: Int, to: Int, step: Int, onFrame: (index: Int, ptsMs: Double, image: Image) -> Unit) =
        decode(from, to, step, null, onFrame)

    /**
     * As above, with [layers] set: frames are numbered by their time at [Layers.fps] rather
     * than counted, so the decode can start at the key frame before [from] instead of the
     * clip's start, and only the clip's bottom temporal layer is decoded until [Layers.fullFrom]
     * — on an iPhone's 120 fps clip a quarter of the frames, the 30 a second nothing else
     * needs. From there every layer is taken up, each at a picture that allows it.
     */
    fun decode(
        from: Int,
        to: Int,
        step: Int,
        layers: Layers?,
        onFrame: (index: Int, ptsMs: Double, image: Image) -> Unit,
    ) {
        val c = codec ?: start().also { codec = it }
        val frameUs = layers?.let { 1e6 / it.fps }
        fun indexAt(ptsUs: Long) = Math.round((ptsUs - originUs) / frameUs!!).toInt()
        extractor.seekTo(if (frameUs != null) originUs + (from * frameUs).toLong() else 0L, MediaExtractor.SEEK_TO_PREVIOUS_SYNC)
        // The highest temporal layer being fed to the decoder, and the frame from which all are.
        var topLayer = if (layers != null) 0 else Int.MAX_VALUE
        val info = MediaCodec.BufferInfo()
        var inputDone = false
        var index = 0
        var firstPtsUs = Long.MIN_VALUE
        var idle = 0
        while (true) {
            if (!inputDone) {
                val i = c.dequeueInputBuffer(TIMEOUT_US)
                if (i >= 0) {
                    val buf = c.getInputBuffer(i) ?: throw ClipProblem("The video decoder gave no input buffer.")
                    var n = extractor.readSampleData(buf, 0)
                    // Pictures above the layers being decoded are passed over: nothing below
                    // them refers to them.
                    if (layers != null) {
                        while (n >= 0) {
                            val at = indexAt(extractor.sampleTime)
                            if (at > to + REORDER_SLACK) { n = -1; break }
                            val (layer, nal) = hevcLayer(buf, n) ?: (0 to -1)
                            // One layer up at a time, each at a picture made for starting it: the
                            // pictures of a layer refer to those of the layer below.
                            if (at >= layers.fullFrom && layer == topLayer + 1 && layer <= layers.maxLayer && nal in SWITCH_UP_NALS) topLayer = layer
                            // A key picture starts every layer afresh.
                            if (at >= layers.fullFrom && nal in IRAP_NALS) topLayer = layers.maxLayer
                            if (layer <= topLayer) break
                            extractor.advance()
                            buf.clear()
                            n = extractor.readSampleData(buf, 0)
                        }
                    }
                    if (n < 0) {
                        c.queueInputBuffer(i, 0, 0, 0, MediaCodec.BUFFER_FLAG_END_OF_STREAM)
                        inputDone = true
                    } else {
                        c.queueInputBuffer(i, 0, n, extractor.sampleTime, 0)
                        extractor.advance()
                    }
                }
            }
            val w0 = System.nanoTime()
            val o = c.dequeueOutputBuffer(info, TIMEOUT_US)
            waitNs += System.nanoTime() - w0
            if (o < 0) {
                // A decoder that stops answering is not waited on forever.
                if (o == MediaCodec.INFO_TRY_AGAIN_LATER && ++idle > MAX_IDLE_POLLS) {
                    throw ClipProblem("The video decoder stopped responding.")
                }
                continue
            }
            idle = 0
            val eos = (info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM) != 0
            val isFrame = info.size > 0 || !eos
            if (isFrame) {
                if (firstPtsUs == Long.MIN_VALUE) firstPtsUs = if (frameUs != null) originUs else info.presentationTimeUs
                if (frameUs != null) index = indexAt(info.presentationTimeUs)
                val wanted = index in from..to && (index - from) % step == 0
                if (wanted) {
                    val image = c.getOutputImage(o) ?: run {
                        c.releaseOutputBuffer(o, false)
                        throw ClipProblem("The video decoder can't hand frames over for analysis.")
                    }
                    try {
                        val s0 = System.nanoTime()
                        onFrame(index, (info.presentationTimeUs - firstPtsUs) / 1000.0, image)
                        sampleNs += System.nanoTime() - s0
                    } finally {
                        image.close()
                        c.releaseOutputBuffer(o, false)
                    }
                } else {
                    c.releaseOutputBuffer(o, false)
                }
                if (frameUs == null) index++
            } else {
                c.releaseOutputBuffer(o, false)
            }
            if (eos || index > to) break
        }
        // Ready for another pass from the start.
        runCatching { c.flush() }
    }

    /** How [decode] takes a layered clip: numbered at [fps], every layer from frame [fullFrom]. */
    class Layers(val fps: Double, val fullFrom: Int, val maxLayer: Int = Int.MAX_VALUE)

    /** The first frame's time: frames are numbered from it. */
    private val originUs: Long = run {
        extractor.seekTo(0, MediaExtractor.SEEK_TO_PREVIOUS_SYNC)
        extractor.sampleTime.coerceAtLeast(0L)
    }

    /**
     * Whether the clip's pictures come in temporal layers — HEVC that an iPhone records at
     * 60 fps and above — so that [decode] can leave the upper ones out. Read from the first
     * couple of hundred pictures' headers.
     */
    val layered: Boolean by lazy {
        val mime = format.getString(MediaFormat.KEY_MIME) ?: ""
        if (!mime.equals(MediaFormat.MIMETYPE_VIDEO_HEVC, true) && !mime.equals("video/dolby-vision", true)) return@lazy false
        val buf = java.nio.ByteBuffer.allocate(maxOf(1 shl 20, if (format.containsKey(MediaFormat.KEY_MAX_INPUT_SIZE)) format.getInteger(MediaFormat.KEY_MAX_INPUT_SIZE) else 0))
        var top = 0
        val times = ArrayList<Long>()
        runCatching {
            extractor.seekTo(0, MediaExtractor.SEEK_TO_PREVIOUS_SYNC)
            repeat(LAYER_PROBE_SAMPLES) {
                buf.clear()
                val n = extractor.readSampleData(buf, 0)
                if (n < 0) return@repeat
                times += extractor.sampleTime
                hevcLayer(buf, n)?.let { top = maxOf(top, it.first) }
                extractor.advance()
            }
        }
        extractor.seekTo(0, MediaExtractor.SEEK_TO_PREVIOUS_SYNC)
        // The rate the frames are recorded at, from the commonest gap between them.
        val gaps = times.sorted().zipWithNext { a, b -> b - a }.filter { it > 0 }.sorted()
        frameRate = if (gaps.isEmpty()) 0.0 else 1e6 / gaps[gaps.size / 2]
        top > 0
    }

    /** Frames a second as recorded, once [layered] has been read; 0 when unknown. */
    var frameRate = 0.0
        private set

    /** The slow, sure way: the decoder's YUV planes copied and sampled upright into [out]. */
    fun upright(image: Image, out: IntArray) {
        val planes = image.planes
        val crop = image.cropRect
        val y = planes[0]
        val u = planes[1]
        val v = planes[2]
        // A 16-bit luma sample (P010 and friends) is the only way the Y plane steps by two.
        val wide = y.pixelStride == 2
        if (!loggedFormat) {
            loggedFormat = true
            runCatching {
                android.util.Log.i("VideoFrameStream", "frame format=${image.format} ${image.width}x${image.height} crop=$crop yStride=${y.rowStride}/${y.pixelStride} uStride=${u.rowStride}/${u.pixelStride} wide=$wide rot=$rotation")
            }
        }
        yBuf = copy(y.buffer, yBuf)
        uBuf = copy(u.buffer, uBuf)
        vBuf = copy(v.buffer, vBuf)
        mapper.prepare(
            srcW = crop.width(), srcH = crop.height(), cropX = crop.left, cropY = crop.top,
            rotation = rotation,
            yRowStride = y.rowStride, yPixelStride = y.pixelStride,
            cRowStride = u.rowStride, cPixelStride = u.pixelStride,
            vRowStride = v.rowStride, vPixelStride = v.pixelStride,
        )
        mapper.fill(yBuf, uBuf, vBuf, wide, out)
    }

    private fun copy(src: java.nio.ByteBuffer, into: ByteArray): ByteArray {
        val b = src.duplicate().apply { rewind() }
        val dst = if (into.size >= b.remaining()) into else ByteArray(b.remaining())
        b.get(dst, 0, b.remaining())
        return dst
    }

    private fun start(): MediaCodec {
        val mime = format.getString(MediaFormat.KEY_MIME) ?: throw ClipProblem("This video has no video track we can read.")
        val list = MediaCodecList(MediaCodecList.REGULAR_CODECS)
        val candidates = buildList {
            add(MediaFormat(format).apply { setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatYUV420Flexible) })
            // Dolby Vision with no Dolby decoder on the phone: its base layer is plain HEVC/AVC/AV1.
            if (mime == MediaFormat.MIMETYPE_VIDEO_DOLBY_VISION) {
                for (base in listOf(MediaFormat.MIMETYPE_VIDEO_HEVC, MediaFormat.MIMETYPE_VIDEO_AVC, MediaFormat.MIMETYPE_VIDEO_AV1)) {
                    add(MediaFormat(format).apply {
                        setString(MediaFormat.KEY_MIME, base)
                        removeKeySafe(MediaFormat.KEY_PROFILE)
                        removeKeySafe(MediaFormat.KEY_LEVEL)
                        setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatYUV420Flexible)
                    })
                }
            }
        }
        var lastError: Throwable? = null
        for (f in candidates) {
            val name = runCatching { list.findDecoderForFormat(f) }.getOrNull()
                ?: runCatching { list.findDecoderForFormat(MediaFormat(f).apply { removeKeySafe(MediaFormat.KEY_FRAME_RATE) }) }.getOrNull()
            val names = listOfNotNull(name) + decodersFor(list, f.getString(MediaFormat.KEY_MIME))
            for (n in names.distinct()) {
                // HDR to ordinary video where the decoder offers it (Android 13+); without it the
                // 10-bit samples are read at 8 bits, which the tracker copes with.
                // HDR decoded as it is stored, 10-bit read at 8: a third of the decoder's time
                // that tone-mapping cost, and the same delivery to within a percent once the
                // far stumps were measured at full size. Tone-mapping only where a decoder
                // will not hand the raw frames over.
                val tries = if (Build.VERSION.SDK_INT >= 33 && debugToneMap) listOf(true, false) else if (Build.VERSION.SDK_INT >= 33) listOf(false, true) else listOf(false)
                for (toneMap in tries) {
                    val c = runCatching { MediaCodec.createByCodecName(n) }.getOrElse { lastError = it; null } ?: continue
                    try {
                        val cf = MediaFormat(f)
                        // As fast as the hardware goes, not at playback speed: this is analysis,
                        // not a video someone is watching.
                        if (Build.VERSION.SDK_INT >= 23) {
                            cf.setInteger(MediaFormat.KEY_PRIORITY, 1)
                            cf.setFloat(MediaFormat.KEY_OPERATING_RATE, Short.MAX_VALUE.toFloat())
                        }
                        if (toneMap && Build.VERSION.SDK_INT >= 33) {
                            cf.setInteger(MediaFormat.KEY_COLOR_TRANSFER_REQUEST, MediaFormat.COLOR_TRANSFER_SDR_VIDEO)
                        }
                        c.configure(cf, null, null, 0)
                        c.start()
                        runCatching {
                            android.util.Log.i("VideoFrameStream", "decoder $n for $mime toneMap=$toneMap out=${c.outputFormat}")
                        }
                        return c
                    } catch (t: Throwable) {
                        lastError = t
                        runCatching { c.release() }
                    }
                }
            }
        }
        throw ClipProblem("This phone can't decode this video (${mime.substringAfter('/')}).", lastError)
    }

    private fun decodersFor(list: MediaCodecList, mime: String?): List<String> =
        if (mime == null) emptyList() else list.codecInfos
            .filter { !it.isEncoder && it.supportedTypes.any { t -> t.equals(mime, ignoreCase = true) } }
            .map { it.name }

    override fun close() {
        runCatching { codec?.stop() }
        runCatching { codec?.release() }
        codec = null
        runCatching { extractor.release() }
    }

    companion object {
        /** Debug only: tone-map HDR to ordinary video first, to compare. */
        @Volatile
        var debugToneMap = false
        private const val TIMEOUT_US = 10_000L
        /** Frames past the last wanted one still read in, for decoders that reorder. */
        private const val REORDER_SLACK = 8
        private const val LAYER_PROBE_SAMPLES = 200
        /** Temporal and step-wise temporal sub-layer access: where a higher layer may start. */
        private val SWITCH_UP_NALS = setOf(2, 3, 4, 5)
        private val IRAP_NALS = 16..23

        /**
         * The temporal layer and NAL type of the first picture slice in a sample, start-coded
         * or length-prefixed, or null when there is none.
         */
        internal fun hevcLayer(buf: java.nio.ByteBuffer, n: Int): Pair<Int, Int>? {
            fun at(i: Int) = buf.get(i).toInt() and 0xFF
            fun header(i: Int): Pair<Int, Int>? {
                if (i + 1 >= n) return null
                val type = (at(i) shr 1) and 0x3F
                return if (type < 32) ((at(i + 1) and 7) - 1).coerceAtLeast(0) to type else null
            }
            val annexB = n >= 4 && at(0) == 0 && at(1) == 0 && (at(2) == 1 || (at(2) == 0 && at(3) == 1))
            if (annexB) {
                var i = 0
                while (i + 3 < n) {
                    if (at(i) == 0 && at(i + 1) == 0 && at(i + 2) == 1) {
                        header(i + 3)?.let { return it }
                        i += 3
                    } else {
                        i++
                    }
                }
                return null
            }
            var i = 0
            while (i + 4 < n) {
                val len = (at(i) shl 24) or (at(i + 1) shl 16) or (at(i + 2) shl 8) or at(i + 3)
                header(i + 4)?.let { return it }
                if (len <= 0) return null
                i += 4 + len
            }
            return null
        }
        /** ~10 s of a decoder saying nothing, with input still going in. */
        private const val MAX_IDLE_POLLS = 1_000

        fun open(context: Context, uri: Uri): VideoFrameStream {
            val ex = MediaExtractor()
            try {
                ex.setDataSource(context, uri, null)
            } catch (t: Throwable) {
                ex.release()
                throw ClipProblem("This video can't be opened. Try a clip from your camera.", t)
            }
            return from(ex)
        }

        fun open(path: String): VideoFrameStream {
            val ex = MediaExtractor()
            try {
                ex.setDataSource(path)
            } catch (t: Throwable) {
                ex.release()
                throw ClipProblem("Can't open $path: ${t.message}", t)
            }
            return from(ex)
        }

        private fun from(ex: MediaExtractor): VideoFrameStream {
            val track = (0 until ex.trackCount).firstOrNull {
                ex.getTrackFormat(it).getString(MediaFormat.KEY_MIME)?.startsWith("video/") == true
            } ?: run {
                ex.release()
                throw ClipProblem("This file has no video in it.")
            }
            ex.selectTrack(track)
            val f = ex.getTrackFormat(track)
            val rotation = if (f.containsKey(MediaFormat.KEY_ROTATION)) f.getInteger(MediaFormat.KEY_ROTATION) else 0
            return VideoFrameStream(ex, f, ((rotation % 360) + 360) % 360)
        }

        private fun MediaFormat.removeKeySafe(key: String) {
            if (Build.VERSION.SDK_INT >= 29 && containsKey(key)) removeKey(key)
        }
    }
}

/**
 * Samples a stored (possibly sideways) YUV 4:2:0 picture into an upright, centre-cropped
 * [outW]x[outH] ARGB picture, averaging a 2x2 block of luma for each output pixel so a small
 * ball survives the shrink. Pure array maths, so it is tested on the JVM.
 *
 * The geometry is the same as [ClipFrames.upright]: turn by [rotation] clockwise, centre-crop to
 * the output's aspect, scale.
 */
internal class UprightSampler(private val outW: Int, private val outH: Int) {
    private var key: List<Int> = emptyList()
    private var yIdx = IntArray(0)
    private var yNext = IntArray(0)
    private var yDown = IntArray(0)
    private var uIdx = IntArray(0)
    private var vIdx = IntArray(0)

    fun prepare(
        srcW: Int, srcH: Int, cropX: Int, cropY: Int, rotation: Int,
        yRowStride: Int, yPixelStride: Int,
        cRowStride: Int, cPixelStride: Int,
        vRowStride: Int, vPixelStride: Int,
    ) {
        val k = listOf(srcW, srcH, cropX, cropY, rotation, yRowStride, yPixelStride, cRowStride, cPixelStride, vRowStride, vPixelStride)
        if (k == key) return
        key = k
        val n = outW * outH
        yIdx = IntArray(n); yNext = IntArray(n); yDown = IntArray(n); uIdx = IntArray(n); vIdx = IntArray(n)
        val sideways = rotation % 180 != 0
        val upW = if (sideways) srcH else srcW
        val upH = if (sideways) srcW else srcH
        // Centre-crop the upright picture to the output's aspect.
        val target = outW.toDouble() / outH
        val aspect = upW.toDouble() / upH
        val cw: Double
        val ch: Double
        if (aspect > target) { ch = upH.toDouble(); cw = upH * target } else { cw = upW.toDouble(); ch = upW / target }
        val x0 = (upW - cw) / 2
        val y0 = (upH - ch) / 2
        val sx = cw / outW
        val sy = ch / outH
        for (oy in 0 until outH) {
            val uy = (y0 + (oy + 0.5) * sy).toInt().coerceIn(0, upH - 1)
            for (ox in 0 until outW) {
                val ux = (x0 + (ox + 0.5) * sx).toInt().coerceIn(0, upW - 1)
                // Upright (ux, uy) back to the stored picture.
                val (px, py) = when (rotation) {
                    90 -> uy to (srcH - 1 - ux)
                    180 -> (srcW - 1 - ux) to (srcH - 1 - uy)
                    270 -> (srcW - 1 - uy) to ux
                    else -> ux to uy
                }
                val x = px + cropX
                val y = py + cropY
                val i = oy * outW + ox
                yIdx[i] = y * yRowStride + x * yPixelStride
                yNext[i] = if (px + 1 < srcW) yPixelStride else 0
                yDown[i] = if (py + 1 < srcH) yRowStride else 0
                uIdx[i] = (y / 2) * cRowStride + (x / 2) * cPixelStride
                vIdx[i] = (y / 2) * vRowStride + (x / 2) * vPixelStride
            }
        }
    }

    /**
     * Fills [out] from the planes. [wide] = 16-bit samples (10-bit video), read by their high
     * byte, which holds the top eight bits. Colour as studio-range BT.709.
     */
    fun fill(yPlane: ByteArray, uPlane: ByteArray, vPlane: ByteArray, wide: Boolean, out: IntArray) {
        val hi = if (wide) 1 else 0
        for (i in out.indices) {
            val a = yIdx[i] + hi
            val yy = ((yPlane.at(a) + yPlane.at(a + yNext[i]) + yPlane.at(a + yDown[i]) + yPlane.at(a + yNext[i] + yDown[i])) shr 2) - 16
            val d = uPlane.at(uIdx[i] + hi) - 128
            val e = vPlane.at(vIdx[i] + hi) - 128
            val c = 298 * yy
            val r = ((c + 459 * e + 128) shr 8).coerceIn(0, 255)
            val g = ((c - 55 * d - 136 * e + 128) shr 8).coerceIn(0, 255)
            val b = ((c + 541 * d + 128) shr 8).coerceIn(0, 255)
            out[i] = (0xFF shl 24) or (r shl 16) or (g shl 8) or b
        }
    }

    private fun ByteArray.at(i: Int): Int = if (i in indices) this[i].toInt() and 0xFF else 128
}
