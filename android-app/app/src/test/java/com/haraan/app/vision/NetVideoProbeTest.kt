package com.haraan.app.vision

import org.junit.Assume
import org.junit.Test
import java.io.File

/** Scratch probe on a real net video (frames via NET_FRAMES); not committed. */
class NetVideoProbeTest {
    private fun frames(): List<Pair<Int, HyuvFrame>> {
        val dir = File(System.getenv("NET_FRAMES") ?: "")
        Assume.assumeTrue(dir.isDirectory)
        return dir.listFiles { f -> f.name.endsWith(".hyuv") }!!.sortedBy { it.name }
            .map { it.name.substring(1, 6).toInt() to HyuvFrame.read(it) }
    }

    @Test
    fun stumps() {
        OpenCvJvm.require()
        val det = ColourStumpDetector()
        val tracker = WicketTracker()
        val cam = CameraIntrinsics(1.4, "assumed")
        for ((i, f) in frames().filter { it.first < 120 }) {
            val raw = det.detectCandidates(
                f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride,
                focus = tracker.focus(),
            )
            val kept = com.haraan.app.camera.battersEndOnly(raw, cam)
            val lock = tracker.onFrame(kept, FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, i * 33L)
            println(
                "f$i raw=" + raw.joinToString { s ->
                    val st = (s as? WicketSighting.Stumps)?.set
                    if (st == null) "stone" else "base(%.3f,%.3f) span=%.4f d=%.1fm score=%.2f".format(
                        st.baseMiddle.x, st.baseMiddle.y, st.spanX, 1.4 * 0.2286 / st.spanX, st.score,
                    )
                } + " kept=${kept.size} lock=${lock?.let { "%s (%.3f,%.3f)".format(it.state, it.anchor.base.x, it.anchor.base.y) }}",
            )
        }
    }

    @Test
    fun ball() {
        OpenCvJvm.require()
        for (detail in listOf(1.0, 1.5)) {
            val t = OpenCvBallTracker().also { it.setDetail(detail) }
            var hits = 0
            for ((i, f) in frames().filter { it.first >= 1300 }) {
                val s = t.onFrame(f.y, f.width, f.height, f.yRowStride, f.rotation, i * 1000L / 120)
                if (s != null) {
                    hits++
                    println("detail=$detail f$i ball (%.3f, %.3f) area=${s.areaPx} conf=%.2f track=${t.track().size}".format(s.x, s.y, s.trackingConfidence))
                }
            }
            val d = t.diagnostics()
            println("detail=$detail hits=$hits track=${t.track().size} rej motion=${d.rejectedGlobalMotion} size=${d.rejectedSize} shape=${d.rejectedShape} traj=${d.rejectedTrajectory} still=${d.rejectedStationary} cluster=${d.rejectedCluster}")
        }
    }

    @Test
    fun corridor() {
        OpenCvJvm.require()
        val truth = mapOf(1352 to (0.398 to 0.311), 1356 to (0.421 to 0.339), 1364 to (0.451 to 0.379), 1368 to (0.460 to 0.396), 1372 to (0.468 to 0.409), 1380 to (0.476 to 0.437), 1396 to (0.489 to 0.475))
        val target = Point2(0.606, 0.50)
        val t = OpenCvBallTracker()
        t.debug = { line -> if (line.contains("t=11") || line.contains("t=112") || line.contains("t=113") || line.contains("t=114") || line.contains("t=115")) println("dbg $line") }
        t.setCorridor((target.x - 0.36).toFloat(), (target.y - 0.36).toFloat(), (target.x + 0.36).toFloat(), (target.y + 0.12).toFloat(), target)
        var ms = 0L
        for ((i, f) in frames().filter { it.first >= 1300 }) {
            val t0 = System.nanoTime()
            val s = t.onFrame(f.y, f.width, f.height, f.yRowStride, f.rotation, i * 1000L / 120)
            ms += (System.nanoTime() - t0) / 1_000_000
            val tr = truth[i]
            if (s != null || tr != null) {
                val err = if (s != null && tr != null) " err=%.4f".format(kotlin.math.hypot(s.x - tr.first, (s.y - tr.second) * 16.0 / 9.0)) else ""
                println("corr f$i got=${s?.let { "(%.3f, %.3f) a=${it.areaPx}".format(it.x, it.y) }} truth=$tr$err track=${t.track().size}")
            }
        }
        val d = t.diagnostics()
        println("corr track=${t.track().size} pts=${t.track().map { "%.3f,%.3f".format(it.x, it.y) }} msPerFrame=${ms / 55} rej size=${d.rejectedSize} shape=${d.rejectedShape} traj=${d.rejectedTrajectory} still=${d.rejectedStationary}")
    }

    @Test
    fun endToEnd() {
        OpenCvJvm.require()
        val dir = File(System.getenv("NET_FRAMES2") ?: "")
        Assume.assumeTrue(dir.isDirectory)
        val all = dir.listFiles { f -> f.name.endsWith(".hyuv") }!!.sortedBy { it.name }.map { it.name.substring(1, 6).toInt() to HyuvFrame.read(it) }
        val det = ColourStumpDetector()
        val wt = WicketTracker()
        val cam = CameraIntrinsics(1.4, "assumed")
        var lock: WicketLock? = null
        for ((i, f) in all.filter { it.first < 40 }) {
            val raw = det.detectCandidates(f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride, focus = wt.focus())
            lock = wt.onFrame(com.haraan.app.camera.battersEndOnly(raw, cam), FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, i * 33L)
        }
        val c = com.haraan.app.camera.deliveryCorridor(lock, cam, 720, 720f / 1280f)
        println("e2e lock=${lock?.state} base=${lock?.anchor?.base} corridor=${c?.joinToString()}")
        val t = OpenCvBallTracker()
        if (c != null) t.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
        val truth = mapOf(3676 to (0.332 to 0.347), 3680 to (0.378 to 0.398), 3692 to (0.458 to 0.496), 3696 to (0.471 to 0.518), 3700 to (0.484 to 0.539))
        for ((i, f) in all.filter { it.first >= 3600 }) {
            val s = t.onFrame(f.y, f.width, f.height, f.yRowStride, f.rotation, i * 1000L / 120)
            val tr = truth[i]
            if (s != null || tr != null) {
                val err = if (s != null && tr != null) " err=%.4f".format(kotlin.math.hypot(s.x - tr.first, (s.y - tr.second) * 16.0 / 9.0)) else ""
                println("e2e f$i got=${s?.let { "(%.3f, %.3f)".format(it.x, it.y) }} truth=$tr$err track=${t.track().size}")
            }
        }
        println("e2e track=${t.track().map { "%.3f,%.3f".format(it.x, it.y) }}")
    }

    @Test
    fun twoWickets() {
        OpenCvJvm.require()
        val det = ColourStumpDetector()
        val wt = WicketTracker()
        val cam = CameraIntrinsics(1.4, "assumed")
        var lock: WicketLock? = null
        var near: BowlersEnd? = null
        for ((i, f) in frames().filter { it.first < 120 }) {
            val raw = det.detectCandidates(f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride, focus = wt.focus())
            com.haraan.app.camera.bowlersEndOf(raw, cam)?.let { near = it }
            lock = wt.onFrame(com.haraan.app.camera.battersEndOnly(raw, cam), FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, i * 33L)
        }
        val l = lock!!
        println("two far=${l.anchor.base} span=${l.anchor.span(720f / 1280f)} near=$near")
        val g1 = PitchGround.fromWicket(l, cam, 720, 1280)!!
        println("two assumed: h=${g1.heightM} dist=${g1.stumpsDistanceM} 14m y=${g1.project(14.0, 0.0)}")
        near?.let { n ->
            val a = g1.aimedAt(n.base.x * 720 to n.base.y * 1280)
            println("two aimed(1.4): 14m=${a?.project(14.0, 0.0)} 20m=${a?.project(20.12, 0.0)}")
            val g2 = PitchGround.fromTwoWickets(l, cam, 720, 1280, n)
            println("two solved: h=${g2?.heightM} dist=${g2?.stumpsDistanceM} 14m=${g2?.project(14.0, 0.0)} 20m=${g2?.project(20.12, 0.0)} near=$n")
            for (hh in listOf(0.2, 0.4, 0.8, 1.4, 2.5, 4.0)) {
                val g = PitchGround.fromWicket(l, cam, 720, 1280, hh)
                val ag = g?.aimedAt(n.base.x * 720 to n.base.y * 1280)
                println("two h=$hh dist=${g?.stumpsDistanceM} aimedNull=${ag == null} 20m=${ag?.project(20.12, 0.0)}")
            }
        }
    }

    /** Both deliveries: track in the corridor, then the 3D flight through the two-wicket ground. */
    @Test
    fun flight3d() {
        OpenCvJvm.require()
        for ((label, env) in listOf("d1" to "NET_FRAMES", "d2" to "NET_FRAMES2")) {
            val dir = File(System.getenv(env) ?: "")
            if (!dir.isDirectory) continue
            val all = dir.listFiles { f -> f.name.endsWith(".hyuv") }!!.sortedBy { it.name }.map { it.name.substring(1, 6).toInt() to HyuvFrame.read(it) }
            val det = ColourStumpDetector()
            val wt = WicketTracker()
            val cam = CameraIntrinsics(1.4, "assumed")
            var lock: WicketLock? = null
            var near: BowlersEnd? = null
            for ((i, f) in all.filter { it.first < 120 }) {
                val raw = det.detectCandidates(f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride, focus = wt.focus())
                com.haraan.app.camera.bowlersEndOf(raw, cam)?.let { near = it }
                lock = wt.onFrame(com.haraan.app.camera.battersEndOnly(raw, cam), FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, i * 33L)
            }
            val c = com.haraan.app.camera.deliveryCorridor(lock, cam, 720, 720f / 1280f) ?: continue
            val t = OpenCvBallTracker()
            t.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
            for ((i, f) in all.filter { it.first >= 1300 }) t.onFrame(f.y, f.width, f.height, f.yRowStride, f.rotation, i * 1000L / 120)
            val track = t.track()
            val g = PitchGround.best(lock!!, cam, 720, 1280, tilt = null, bowlersEnd = near)!!
            val pc = g.pitchCamera(720, 1280)
            val m = FlightMetrics.of(track, 720f / 1280f, quad = null, wicket = lock, ground = pc)
            val f3 = m.flight3d
            println("$label pts=${track.size} h=%.2f measured=${g.heightMeasured} speed=${m.groundSpeed} 3d=${f3?.let { "speed %.1f bounce (%.2f, %.2f)".format(it.speedKmh, it.bounceX, it.bounceY) }}".format(g.heightM))
            if (f3 != null) println("$label arPath=${ArPath.fromFlight(f3, pc)?.points?.size}")
        }
    }

    private val dbgLines = ArrayList<String>()

    /** The 30 fps re-encode, end to end as the replay screen runs it. */
    @Test
    fun thirty() {
        OpenCvJvm.require()
        val dir = File(System.getenv("NET30") ?: "")
        Assume.assumeTrue(dir.isDirectory)
        val all = dir.listFiles { f -> f.name.endsWith(".hyuv") }!!.sortedBy { it.name }.map { it.name.substring(1, 6).toInt() to HyuvFrame.read(it) }
        val det = ColourStumpDetector()
        val wt = WicketTracker()
        val cam = CameraIntrinsics(1.4, "assumed")
        var lock: WicketLock? = null
        var near: BowlersEnd? = null
        val t = OpenCvBallTracker()
        t.debug = { line -> if (line.contains("confirmed=")) dbgLines += line }
        var corridor: FloatArray? = null
        for ((i, f) in all.take(90)) {
            val raw = det.detectCandidates(f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride, focus = wt.focus())
            com.haraan.app.camera.bowlersEndOf(raw, cam)?.let { near = it }
            lock = wt.onFrame(com.haraan.app.camera.battersEndOnly(raw, cam, near), FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, i * 1000L / 30)
        }
        for ((i, f) in all) {
            dbgLines.clear()
            val ts = i * 1000L / 30
            val raw = det.detectCandidates(f.y, f.width, f.height, f.yRowStride, f.rotation, f.u, f.uvRowStride, f.uvPixelStride, focus = wt.focus())
            com.haraan.app.camera.bowlersEndOf(raw, cam)?.let { near = it }
            lock = wt.onFrame(com.haraan.app.camera.battersEndOnly(raw, cam, near), FrameMotion(0f, 0f, 1f, 0f, 30, 32), 720f / 1280f, ts)
            if (corridor == null || i % 30 == 0) {
                com.haraan.app.camera.deliveryCorridor(lock, cam, 720, 720f / 1280f)?.let { c ->
                    corridor = c
                    t.setCorridor(c[0], c[1], c[2], c[3], Point2(c[4].toDouble(), c[5].toDouble()))
                }
            }
            val s = t.onFrame(f.y, f.width, f.height, f.yRowStride, f.rotation, ts)
            if (s != null) println("thirty f$i ball (%.3f, %.3f) track=${t.track().size}".format(s.x, s.y))
            if (i in 50..62) println("thirty dbg f$i lock=${lock?.state} corr=${t.corridorActive()} ${dbgLines.joinToString().take(500)}")
            val replaced = t.takeFinished()?.takeIf { fin -> lock != null && FlightEnd.check(fin, fin.last().timestampMs + FlightEnd.QUIET_MS, 720f / 1280f, lock) != null }
            val flight = replaced ?: t.track()
            val end = if (replaced != null) FlightEnd.Reason.BALL_GONE else lock?.let { FlightEnd.check(flight, ts, 720f / 1280f, it) }
            if (end != null) {
                val g = PitchGround.best(lock!!, cam, 720, 1280, tilt = null, bowlersEnd = near)
                val m = FlightMetrics.of(flight, 720f / 1280f, quad = null, wicket = lock, ground = g?.pitchCamera(720, 1280))
                val fit = g?.let { QuadSpeed.fit(flight, it.pitchCamera(720, 1280), null, 720f / 1280f) }
                println("thirty CALLED f$i replaced=${replaced != null} pts=${flight.size} 3d=${m.flight3d?.speedKmh} bounce=${m.flight3d?.let { "%.1f".format(it.bounceY) }} fit=${(fit as? QuadSpeed.FitResult.Refused)?.reason}")
                if (replaced == null) t.reset()
            }
        }
        println("thirty lock=${lock?.state} corridor=${corridor?.joinToString()} near=$near")
    }
}
