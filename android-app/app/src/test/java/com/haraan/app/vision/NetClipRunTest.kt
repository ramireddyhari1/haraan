package com.haraan.app.vision

import org.junit.Assume
import org.junit.Test
import java.io.File
import java.io.RandomAccessFile

/**
 * Scratch: a whole net clip through NetReplayActivity's chain, from a raw sensor stream
 * (NET_RAW: Y 1280x720 then Cb 640x360 per frame, every NET_STRIDE-th clip frame). Writes
 * one line per event to NET_OUT for drawing over the clip. Not committed.
 */
class NetClipRunTest {
    @Test
    fun run() {
        val raw = File(System.getenv("NET_RAW") ?: "")
        Assume.assumeTrue(raw.isFile)
        OpenCvJvm.require()
        val stride = (System.getenv("NET_STRIDE") ?: "4").toInt()
        val fps = (System.getenv("NET_FPS") ?: "119.946").toDouble()
        val out = File(System.getenv("NET_OUT") ?: "net_out.txt").printWriter()
        val sw = 1280; val sh = 720
        val ySize = sw * sh; val uSize = (sw / 2) * (sh / 2)
        val n = (raw.length() / (ySize + uSize)).toInt()
        val f = RandomAccessFile(raw, "r")
        val y = ByteArray(ySize); val u = ByteArray(uSize)
        val stumps = ColourStumpDetector(); val wickets = WicketTracker(); val ball = OpenCvBallTracker()
        val camera = CameraIntrinsics(1.4, "assumed")
        val aspect = 720f / 1280f
        val still = FrameMotion(0f, 0f, 1f, 0f, 30, 32)
        val dbg = System.getenv("NET_DEBUG")?.split("-")?.map { it.toInt() }
        var curFrame = 0
        if (dbg != null) ball.debug = { line -> if (curFrame in dbg[0]..dbg[1]) out.println("DBG f$curFrame $line") }
        var lock: WicketLock? = null
        var bowlersEnd: BowlersEnd? = null
        val nearEnd = BowlersEndFilter()
        var corridor: FloatArray? = null
        val steadyW = SteadyWicket()
        fun read(k: Int) { f.seek(k.toLong() * (ySize + uSize)); f.readFully(y); f.readFully(u) }
        val idxFile = File(raw.path + ".idx")
        val clipIdx: List<Int> = if (idxFile.isFile) idxFile.readLines().filter { it.isNotBlank() }.map { it.trim().toInt() } else List(n) { it * stride }
        fun tsOf(k: Int) = (clipIdx[k] * 1000.0 / fps).toLong()
        val warm = System.getenv("NET_WARM")?.let { File(it) }?.takeIf { it.isFile }
        if (warm != null) {
            val wf = RandomAccessFile(warm, "r")
            val wn = minOf(220, (warm.length() / (ySize + uSize)).toInt())
            for (k in 0 until wn) {
                wf.seek(k.toLong() * (ySize + uSize)); wf.readFully(y); wf.readFully(u)
                val seen = stumps.detectCandidates(y, sw, sh, sw, 90, u, sw / 2, 1, focus = wickets.focus())
                com.haraan.app.camera.bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
                lock = wickets.onFrame(com.haraan.app.camera.battersEndOnly(seen, camera, bowlersEnd), still, aspect, (k * 4000.0 / fps).toLong())
            }
            wf.close()
        }
        for (k in 0 until if (warm != null) 0 else minOf(90, n)) {
            read(k)
            val seen = stumps.detectCandidates(y, sw, sh, sw, 90, u, sw / 2, 1, focus = wickets.focus())
            com.haraan.app.camera.bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
            lock = wickets.onFrame(com.haraan.app.camera.battersEndOnly(seen, camera, bowlersEnd), still, aspect, tsOf(k))
        }
        out.println("WARM lock=${lock?.state} base=${lock?.anchor?.base?.x},${lock?.anchor?.base?.y} L=${lock?.anchor?.baseLeft} R=${lock?.anchor?.baseRight} near=$bowlersEnd")
        var deliveries = 0
        fun call(track: List<BallSighting>, l: WicketLock, at: Int) {
            val pts = track.joinToString(";") { "%d,%.4f,%.4f".format((it.timestampMs * fps / 1000.0).toInt(), it.x, it.y) }
            out.println("CALL f$at pts=${track.size} track=$pts")
            if (track.size < FlightEnd.MIN_FLIGHT_POINTS) return
            val g = PitchGround.best(l, camera, 720, 1280, tilt = null, bowlersEnd = bowlersEnd)
            val pc = g?.pitchCamera(720, 1280)
            val m = FlightMetrics.of(track, aspect, quad = null, wicket = l, ground = pc)
            val f3 = m.flight3d
            val refused = pc?.let { (QuadSpeed.fit(FlightTrim.receding(track, aspect, l.anchor.base), it, null, aspect) as? QuadSpeed.FitResult.Refused)?.reason }
            if (pc != null) for (drop in 1..4) {
                val t2 = track.dropLast(drop)
                val r = QuadSpeed.fit(t2, pc, null, aspect)
                out.println("  DROP$drop " + when (r) {
                    is QuadSpeed.FitResult.Ok -> "speed=%.1f bounce=%.2f,%.2f out=${r.flight.hasOutLeg} hits=${r.flight.hitsStumps}".format(r.flight.speedKmh, r.flight.bounceX, r.flight.bounceY)
                    is QuadSpeed.FitResult.Refused -> "refused: ${r.reason}"
                })
            }
            // Geometry-free check: release to the stumps, ~18.5 m (pitch less the bowler's stride past the crease).
            run {
                val base = l.anchor.base
                val arrive = track.firstOrNull { kotlin.math.abs(it.x - base.x) < 0.06 && it.y >= base.y - 0.012 }
                if (arrive != null) {
                    val sec = (arrive.timestampMs - track.first().timestampMs) / 1000.0
                    out.println("  TIMING release->stumps %.3f s  avg %.1f km/h".format(sec, 18.5 / sec * 3.6))
                }
            }
            out.println("  TRIM kept=${FlightTrim.receding(track, aspect, l.anchor.base).size}/${track.size} out=${f3?.hasOutLeg} hits=${f3?.hitsStumps}")
            out.println("  METRICS far=${"%.4f,%.4f,%.5f".format(l.anchor.base.x, l.anchor.base.y, l.anchor.span(aspect))} near=${bowlersEnd?.let { "%.3f,%.3f,%.4f".format(it.base.x, it.base.y, it.spanFw) }} ground=${g != null} h=${g?.heightM} dist=${g?.stumpsDistanceM} speed3d=${f3?.speedKmh} bounce=${f3?.let { "%.2f,%.2f".format(it.bounceX, it.bounceY) }} refused=$refused")
            if (f3 != null && pc != null) {
                val p = ArPath.fromFlight(f3, pc)
                if (p != null) {
                    deliveries++
                    out.println("  PATH bounce=${p.bounce} hitting=${p.hitting} pts=" + p.points.joinToString(";") { "%.4f,%.4f".format(it.x, it.y) })
                    out.println("  STUMPS " + p.stumps.joinToString(";") { "%.4f,%.4f,%.4f,%.4f".format(it.first.x, it.first.y, it.second.x, it.second.y) })
                }
            }
        }
        fun measureLock() = if (System.getenv("NET_STEADY") != null) (steadyW.value() ?: lock) else lock
        var lastStumpsMs = Long.MIN_VALUE / 2
        var lastCorridorMs = Long.MIN_VALUE / 2
        for (k in 0 until n) {
            read(k)
            val ts = tsOf(k)
            val clipFrame = clipIdx[k]
            curFrame = clipFrame
            if (k > 0 && clipIdx[k] - clipIdx[k - 1] > stride) {
                // A cut between test windows: a flight still running is called as it stands.
                measureLock()?.let { l -> ball.track().takeIf { it.size >= FlightEnd.MIN_FLIGHT_POINTS }?.let { call(it, l, clipIdx[k - 1]) } }
                out.println("SEG f$clipFrame")
                ball.reset()
            }
            val confirmed = lock?.state == WicketTrackState.CONFIRMED
            // Ten a second once confirmed, as the camera screen's one in three at 30 fps.
            if (!confirmed || ts - lastStumpsMs >= 100L) {
                lastStumpsMs = ts
                val seen = stumps.detectCandidates(y, sw, sh, sw, 90, u, sw / 2, 1, focus = wickets.focus())
                com.haraan.app.camera.bowlersEndOf(seen, camera)?.let { bowlersEnd = nearEnd.add(it) ?: bowlersEnd }
                lock = wickets.onFrame(com.haraan.app.camera.battersEndOnly(seen, camera, bowlersEnd), still, aspect, ts)
                steadyW.add(lock, ts)
            }
            if (corridor == null || ts - lastCorridorMs >= 1_000L) {
                lastCorridorMs = ts
                val steady = steadyW.value()
                val c = com.haraan.app.camera.deliveryCorridor(steady ?: lock, camera, 720, aspect)
                if (c != null) {
                    if (corridor == null || !c.contentEquals(corridor)) out.println("CORR f$clipFrame ${c.joinToString(",")}")
                    corridor = c
                    ball.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
                } else if (lock == null && steady == null) {
                    corridor = null
                    ball.setCorridor(null, null, null, null, null)
                }
            }
            val s = ball.onFrame(y, sw, sh, sw, 90, ts)
            if (s != null) out.println("BALL f$clipFrame %.4f %.4f a=${s.areaPx} c=%.2f tr=${ball.track().size}".format(s.x, s.y, s.trackingConfidence))
            if (k % 30 == 0) out.println("LOCK f$clipFrame ${lock?.state} ${lock?.anchor?.base?.x},${lock?.anchor?.base?.y} span=${lock?.anchor?.span(aspect)} held=${lock?.heldFrames} steady=${steadyW.value()?.anchor?.span(aspect)}")
            val l = measureLock()
            val replaced = ball.takeFinished()?.takeIf { fin ->
                l != null && FlightEnd.check(fin, fin.last().timestampMs + FlightEnd.QUIET_MS, aspect, l) != null
            }
            val track = replaced ?: ball.track()
            if (l != null && (replaced != null || FlightEnd.check(track, ts, aspect, l) != null)) {
                call(track, l, clipFrame)
                if (replaced == null) ball.reset()
            }
        }
        measureLock()?.let { l -> ball.track().takeIf { it.size >= FlightEnd.MIN_FLIGHT_POINTS }?.let { call(it, l, clipIdx[n - 1]) } }
        val d = ball.diagnostics()
        out.println("END deliveries=$deliveries rej motion=${d.rejectedGlobalMotion} size=${d.rejectedSize} shape=${d.rejectedShape} traj=${d.rejectedTrajectory} still=${d.rejectedStationary} cluster=${d.rejectedCluster}")
        out.close()
        f.close()
    }
}
