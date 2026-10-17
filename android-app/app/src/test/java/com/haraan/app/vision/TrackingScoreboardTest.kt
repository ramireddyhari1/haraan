package com.haraan.app.vision

import com.haraan.app.vision.replay.FrameLabel
import com.haraan.app.vision.replay.LabelScoring
import com.haraan.app.vision.replay.LabelSet
import org.junit.Assume
import org.junit.Test
import java.io.File
import java.io.RandomAccessFile
import kotlin.math.abs
import kotlin.math.roundToInt

/**
 * THE TRACKING SCOREBOARD: the shipped per-frame chain ([DeliveryChain]) run over real clips
 * and scored against ground truth a person checked frame by frame
 * (`src/test/resources/vision-bench/truth.txt`).
 *
 * Every tuning change to the ball tracker, the stumps or the 3D fit should move a number on
 * this board — and a change that cannot be seen here was not shown to help. Each clip runs at
 * its own rate and thinned to 60 and 30 a second, because what a phone can analyse live is
 * not what a clip gives afterwards.
 *
 * The clips are far too big for the repository. Convert them with
 * `scripts/vision-bench/to_bench_raw.py` into one folder and point VISION_BENCH_DIR at it;
 * without it the test is skipped. Gradle caches test results: run with `--rerun`.
 *
 * Writes `build/reports/vision-bench/scoreboard.md` and prints it.
 */
class TrackingScoreboardTest {

    private class TruthDelivery(
        val releaseFrame: Int?,
        val stumpsFrame: Int?,
        val stumpsTol: Int,
        val bounceFrame: Int?,
        val bounceTol: Int,
    )

    private class Clip(
        val id: String,
        val raw: String,
        val from: Int,
        val to: Int,
        val fps: Double?,
        val deliveries: List<TruthDelivery>,
        val cutFrame: Int?,
        val balls: Map<Int, Pair<Float, Float>>,
    )

    private class Row(
        val clip: String,
        val rate: String,
        val found: Boolean,
        val shown: Boolean,
        val points: Int,
        val recall: Double?,
        val medianPx: Double?,
        val truthKmh: Double?,
        val truthTolPct: Double?,
        val groundKmh: Double?,
        val bounceErrFrames: Int?,
        val hitting: Boolean?,
        val falseCalls: Int,
        val falseShown: Int,
    ) {
        val speedErrPct: Double? get() = if (truthKmh != null && groundKmh != null) (groundKmh - truthKmh) / truthKmh * 100 else null
    }

    @Test
    fun scoreboard() {
        val dir = File(System.getenv("VISION_BENCH_DIR") ?: "")
        Assume.assumeTrue("VISION_BENCH_DIR not set to the converted clips", dir.isDirectory)
        OpenCvJvm.require()
        val clips = readTruth().filter { File(dir, it.raw).isFile }
        Assume.assumeTrue("no bench clip found in $dir", clips.isNotEmpty())
        val only = System.getenv("BENCH_ONLY")?.split(',')?.toSet()

        val rows = ArrayList<Row>()
        for (clip in clips) {
            if (only != null && clip.id !in only) continue
            val frames = indexOf(File(dir, clip.raw + ".idx"), clip)
            val nativeFps = 1000.0 / ((frames.last().second - frames.first().second) / (frames.size - 1).coerceAtLeast(1))
            for (rate in listOf(120, 60, 30)) {
                val step = maxOf(1, (nativeFps / rate).roundToInt())
                if (rate != 120 && step == 1) continue
                rows += run(File(dir, clip.raw), clip, frames, step, "${(nativeFps / step).roundToInt()} fps")
            }
        }
        val cold = ArrayList<ColdRow>()
        for (clip in clips) {
            if (only != null && clip.id !in only) continue
            val frames = indexOf(File(dir, clip.raw + ".idx"), clip)
            cold += ColdRow(clip.id, coldStart(File(dir, clip.raw), frames, predictFar = false), coldStart(File(dir, clip.raw), frames, predictFar = true))
        }
        report(rows, cold)
    }

    /** When, from a cold start, the batter's stumps were confirmed and the corridor could be built. */
    private class ColdStart(val confirmedMs: Double?, val readyMs: Double?, val base: Point2?)

    private class ColdRow(val clip: String, val without: ColdStart, val with: ColdStart)

    /*
     * THE LIVE CAMERA FROM COLD. The live screen cannot run on a desk, but its stumps step is
     * the one shared here: thirty frames a second, every one checked while nothing is held.
     * From the clip's first frame, how long until the batter's stumps are confirmed, and
     * until the ball's corridor can be built — with and without [FarWicketSearch].
     */
    private fun coldStart(raw: File, frames: List<Frame>, predictFar: Boolean): ColdStart {
        val y = ByteArray(ClipFrames.SENSOR_W * ClipFrames.SENSOR_H)
        val u = ByteArray(ClipFrames.SENSOR_W / 2 * (ClipFrames.SENSOR_H / 2))
        val f = RandomAccessFile(raw, "r")
        val chain = DeliveryChain(predictFar = predictFar)
        val step = maxOf(1, (frames.size / ((frames.last().second - frames.first().second) / 1000.0) / 30.0).roundToInt())
        var confirmed: Double? = null
        var ready: Double? = null
        try {
            for (k in frames.indices step step) {
                f.seek(frames[k].line * (y.size + u.size).toLong())
                f.readFully(y)
                f.readFully(u)
                chain.warm(y, u, frames[k].second.toLong())
                val since = frames[k].second - frames.first().second
                if (confirmed == null && chain.currentLock?.state == WicketTrackState.CONFIRMED) confirmed = since
                if (ready == null && chain.readyToTrack()) ready = since
                if (ready != null) break
            }
        } finally {
            chain.release()
            f.close()
        }
        return ColdStart(confirmed, ready, chain.currentLock?.base)
    }

    /** One clip through the chain, every [step]-th frame, scored against its truth. */
    private fun run(raw: File, clip: Clip, frames: List<Frame>, step: Int, rate: String): List<Row> {
        val sw = ClipFrames.SENSOR_W
        val sh = ClipFrames.SENSOR_H
        val y = ByteArray(sw * sh)
        val u = ByteArray(sw / 2 * (sh / 2))
        val frameBytes = (y.size + u.size).toLong()
        val f = RandomAccessFile(raw, "r")
        val chain = DeliveryChain(log = if (System.getenv("BENCH_LOG") != null) { line -> println("[${clip.id} $rate] $line") } else { _ -> })
        fun read(at: Int) {
            f.seek(at * frameBytes)
            f.readFully(y)
            f.readFully(u)
        }
        val called = ArrayList<CalledDelivery>()
        val analysed = HashSet<Int>()
        try {
            // Warm-up as the clip screen does: thirty a second, stumps only, until the corridor can be built.
            val warmStep = maxOf(1, (frames.size / ((frames.last().second - frames.first().second) / 1000.0) / 30.0).roundToInt())
            for (k in 0 until minOf(frames.size, WARM_UP_MAX_FRAMES * warmStep) step warmStep) {
                read(frames[k].line)
                chain.warm(y, u, frames[k].second.toLong())
                if (chain.readyToTrack()) break
            }
            for (k in frames.indices step step) {
                read(frames[k].line)
                analysed += frames[k].first
                called += chain.frame(y, u, frames[k].second.toLong())
                if (System.getenv("BENCH_FRAMES") != null) {
                    val l = chain.currentLock
                    val c = chain.currentCorridor
                    val t = chain.ballTrack().lastOrNull()
                    val truth = clip.balls[frames[k].first]
                    println(
                        "[${clip.id} $rate] f${frames[k].first} lock=${l?.state}/${l?.isMeasurable} held=${l?.heldFrames} jit=${l?.let { "%.4f/%.4f sub=${it.subPixel} m=${it.method}".format(it.jitter, WicketTracker.measurableJitter(it.span)) }} steady=${chain.measuringLock?.let { "%s/%s %.4f/%.4f span=%.4f".format(it.state, it.isMeasurable, it.jitter, WicketTracker.measurableJitter(it.span), it.span) }} base=${l?.anchor?.base?.let { "%.3f,%.3f".format(it.x, it.y) }} " +
                            "corr=${c?.joinToString(",") { "%.2f".format(it) }} track=${chain.ballTrack().size} last=${t?.let { "%.3f,%.3f@%d".format(it.x, it.y, it.timestampMs) }} " +
                            "truth=${truth?.let { "%.3f,%.3f".format(it.first, it.second) }}",
                    )
                }
            }
            chain.finish()?.let { called += it }
        } finally {
            chain.release()
            f.close()
        }

        val msOf = frames.associate { it.first to it.second }
        fun frameOf(ms: Long): Int = frames.minByOrNull { abs(it.second - ms) }!!.first
        val used = HashSet<CalledDelivery>()
        val rows = ArrayList<Row>()
        for (truth in clip.deliveries) {
            // The window the ball is in the air: release (or the clip) to a little past the stumps.
            val startMs = truth.releaseFrame?.let { msOf[it]!! - 100 } ?: frames.first().second
            val endMs = truth.stumpsFrame?.let { msOf[it]!! + 300 } ?: (clip.cutFrame?.let { msOf[it] } ?: frames.last().second)
            val match = called.filter { d ->
                d !in used && d.track.first().timestampMs <= endMs && d.track.last().timestampMs >= startMs
            }.maxByOrNull { (if (it.flight != null) 1000 else 0) + it.track.size }
            match?.let { used += it }

            // Ball positions: only labelled frames this rate actually analysed count.
            val labels = clip.balls.filterKeys { it in analysed }.map { (frame, p) ->
                FrameLabel.Ball(frame, msOf[frame]!!.toLong(), p.first, p.second)
            }
            val detections = match?.track?.associate { frameOf(it.timestampMs) to (it.x to it.y) }.orEmpty()
            val score = labels.takeIf { it.isNotEmpty() }?.let {
                LabelScoring.score(LabelSet(clip.id, ClipFrames.UPRIGHT_W, ClipFrames.UPRIGHT_H, it), detections, LABEL_TOLERANCE_FW)
            }

            val truthKmh = if (truth.releaseFrame != null && truth.stumpsFrame != null) {
                val sec = (msOf[truth.stumpsFrame]!! - msOf[truth.releaseFrame]!!) / 1000.0
                RELEASE_TO_STUMPS_M / sec * 3.6
            } else {
                null
            }
            val tolPct = if (truth.releaseFrame != null && truth.stumpsFrame != null) {
                val frameCount = (truth.stumpsFrame - truth.releaseFrame).toDouble()
                (RELEASE_TO_STUMPS_TOL_M / RELEASE_TO_STUMPS_M + (truth.stumpsTol + RELEASE_TOL_FRAMES) / frameCount) * 100
            } else {
                null
            }
            val flight = match?.flight
            rows += Row(
                clip = clip.id,
                rate = rate,
                found = match != null,
                shown = match?.path != null,
                points = match?.track?.size ?: 0,
                recall = score?.recall,
                medianPx = score?.medianErrorFw?.let { it * ClipFrames.UPRIGHT_W },
                truthKmh = truthKmh,
                truthTolPct = tolPct,
                groundKmh = flight?.groundKmh,
                bounceErrFrames = if (truth.bounceFrame != null && flight != null) frameOf(flight.bounceMs.toLong()) - truth.bounceFrame else null,
                hitting = match?.path?.hitting,
                falseCalls = 0,
                falseShown = 0,
            )
        }
        // Everything called that matched no delivery is a false call; only shown ones reach a player.
        val extra = called.filter { it !in used }
        if (rows.isNotEmpty()) {
            val last = rows.removeAt(rows.size - 1)
            rows += Row(
                last.clip, last.rate, last.found, last.shown, last.points, last.recall, last.medianPx, last.truthKmh,
                last.truthTolPct, last.groundKmh, last.bounceErrFrames, last.hitting,
                falseCalls = extra.size, falseShown = extra.count { it.path != null },
            )
        }
        return rows
    }

    private fun report(rows: List<Row>, cold: List<ColdRow> = emptyList()) {
        val sb = StringBuilder()
        sb.appendLine("# Tracking scoreboard")
        sb.appendLine()
        sb.appendLine("Truth: `src/test/resources/vision-bench/truth.txt`, checked by eye. Speed truth is the average release-to-stumps speed (18.5 m), compared with the fit's average (`groundKmh`).")
        sb.appendLine()
        sb.appendLine("| clip | rate | found | shown | pts | ball recall | median err px | truth km/h | fit km/h | err % | bounce err fr | hitting | false calls (shown) |")
        sb.appendLine("|---|---|---|---|---|---|---|---|---|---|---|---|---|")
        fun d(v: Double?, p: Int = 1) = v?.let { "%.${p}f".format(it) } ?: "–"
        for (r in rows) {
            val truth = r.truthKmh?.let { "%.1f ±%.0f%%".format(it, r.truthTolPct) } ?: "–"
            val err = r.speedErrPct?.let { "%+.1f".format(it) + if (abs(it) > (r.truthTolPct ?: 0.0)) " ✗" else "" } ?: "–"
            sb.appendLine(
                "| ${r.clip} | ${r.rate} | ${if (r.found) "yes" else "**no**"} | ${if (r.shown) "yes" else "**no**"} | ${r.points} | " +
                    "${r.recall?.let { "%.0f%%".format(it * 100) } ?: "–"} | ${d(r.medianPx)} | $truth | ${d(r.groundKmh)} | $err | " +
                    "${r.bounceErrFrames?.let { "%+d".format(it) } ?: "–"} | ${r.hitting ?: "–"} | ${r.falseCalls} (${r.falseShown}) |",
            )
        }
        sb.appendLine()
        sb.appendLine("## By rate")
        sb.appendLine()
        sb.appendLine("| rate | deliveries shown | ball recall (mean) | median err px | median abs speed err % | false calls shown |")
        sb.appendLine("|---|---|---|---|---|---|")
        for ((rate, rs) in rows.groupBy { it.rate.split(' ').first().toInt().let { f -> if (f > 90) "120" else if (f > 45) "60" else "30" } }) {
            val recalls = rs.mapNotNull { it.recall }
            val px = rs.mapNotNull { it.medianPx }.sorted()
            val speed = rs.mapNotNull { it.speedErrPct?.let(::abs) }.sorted()
            sb.appendLine(
                "| ~$rate fps | ${rs.count { it.shown }}/${rs.size} | ${if (recalls.isEmpty()) "–" else "%.0f%%".format(recalls.average() * 100)} | " +
                    "${px.getOrNull(px.size / 2)?.let { "%.1f".format(it) } ?: "–"} | ${speed.getOrNull(speed.size / 2)?.let { "%.1f".format(it) } ?: "–"} | ${rs.sumOf { it.falseShown }} |",
            )
        }
        if (cold.isNotEmpty()) {
            sb.appendLine()
            sb.appendLine("## Live camera from cold (30 fps, stumps only)")
            sb.appendLine()
            sb.appendLine("Seconds from the clip's first frame. \"–\" = not within the clip. Corridor = the ball can be searched for.")
            sb.appendLine()
            sb.appendLine("| clip | confirmed, no prediction | corridor, no prediction | confirmed, predicted | corridor, predicted | held at |")
            sb.appendLine("|---|---|---|---|---|---|")
            fun t(v: Double?) = v?.let { "%.2f".format(it / 1000) } ?: "–"
            for (c in cold) {
                sb.appendLine("| ${c.clip} | ${t(c.without.confirmedMs)} | ${t(c.without.readyMs)} | ${t(c.with.confirmedMs)} | ${t(c.with.readyMs)} | ${c.with.base?.let { "%.3f,%.3f".format(it.x, it.y) } ?: "–"} |")
            }
        }
        val out = File("build/reports/vision-bench").apply { mkdirs() }
        File(out, "scoreboard.md").writeText(sb.toString())
        println(sb)
    }

    /** A frame of the clip: its index in the video, its time, and its place in the raw file. */
    private data class Frame(val first: Int, val second: Double, val line: Int)

    /**
     * The clip's frames as (frame index, ms), in file order. The index file holds the frame
     * and, for clips converted with their own times, the presentation time; otherwise the
     * time is the frame over the clip's stated rate. Limited to the clip's from..to.
     */
    private fun indexOf(idx: File, clip: Clip): List<Frame> {
        val all = idx.readLines().filter { it.isNotBlank() }.map { line ->
            val parts = line.trim().split(Regex("\\s+"))
            val frame = parts[0].toInt()
            frame to (parts.getOrNull(1)?.toDouble() ?: (frame * 1000.0 / (clip.fps ?: error("${clip.id}: no times and no fps"))))
        }
        // Positions in the raw file are by line, so keep every line's place and filter after.
        val list = all.withIndex().filter { (_, p) -> p.first in clip.from..clip.to }.map { (k, p) -> Frame(p.first, p.second, k) }
        check(list.isNotEmpty()) { "${clip.id}: no frames in ${clip.from}..${clip.to}" }
        return list
    }

    private fun readTruth(): List<Clip> {
        val text = javaClass.classLoader!!.getResource("vision-bench/truth.txt")!!.readText()
        val clips = ArrayList<Clip>()
        var head: Map<String, String>? = null
        var id = ""
        val deliveries = ArrayList<TruthDelivery>()
        val balls = HashMap<Int, Pair<Float, Float>>()
        var cut: Int? = null
        fun flush() {
            val h = head ?: return
            clips += Clip(
                id, h.getValue("raw"), h["from"]?.toInt() ?: 0, h["to"]?.toInt() ?: Int.MAX_VALUE, h["fps"]?.toDouble(),
                deliveries.toList(), cut, balls.toMap(),
            )
            deliveries.clear(); balls.clear(); cut = null
        }
        fun keys(words: List<String>) = words.filter { '=' in it }.associate { it.substringBefore('=') to it.substringAfter('=') }
        for (line in text.lines()) {
            val words = line.trim().split(Regex("\\s+"))
            when (words.first()) {
                "clip" -> { flush(); id = words[1]; head = keys(words.drop(2).takeWhile { !it.startsWith("source=") }) }
                "delivery" -> {
                    val k = keys(words)
                    deliveries += TruthDelivery(
                        k["release"]?.toInt(), k["stumps"]?.toInt(), k["stumpsTol"]?.toInt() ?: 0,
                        k["bounce"]?.toInt(), k["bounceTol"]?.toInt() ?: 0,
                    )
                }
                "ball" -> balls[words[1].toInt()] = words[2].toFloat() to words[3].toFloat()
                "cut" -> cut = words[1].toInt()
            }
        }
        flush()
        return clips
    }

    private companion object {
        const val WARM_UP_MAX_FRAMES = 150
        /** Release to the batter's stumps: the pitch less the bowler's stride past the crease. */
        const val RELEASE_TO_STUMPS_M = 18.5
        const val RELEASE_TO_STUMPS_TOL_M = 0.5
        /** The release frame is judged to within a frame. */
        const val RELEASE_TOL_FRAMES = 1
        /**
         * A sighting within this of the labelled centre is the ball. Tighter than the
         * label tool's default: these labels are verified centres on 4K frames, and a 4K
         * ball is 10-20 px across at 720 wide.
         */
        const val LABEL_TOLERANCE_FW = 0.012f
    }
}
