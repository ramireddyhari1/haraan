package com.haraan.app.vision

import kotlin.math.sqrt

/**
 * A field log for checking the stump pipeline against a tape measure.
 *
 * WHY THIS EXISTS. Every distance and startup figure this package produces has been shown
 * on synthetic frames only. The only way to know what it does on a real ground, with a real
 * lens and real grass, is to stand the phone at measured distances from a real wicket and
 * write down what it says. This is the notebook: the operator enters the taped distance,
 * records a few seconds, moves, repeats, and exports a CSV.
 *
 * Pure Kotlin, no clock, no file system: the screen writes [csv] wherever it likes.
 */
class WicketValidation {

    data class Sample(
        val trueDistanceM: Double,
        val timestampMs: Long,
        val state: WicketTrackState?,
        val detected: Boolean,
        val estimatedM: Double?,
        val heightEstimateM: Double?,
        val spanPx: Double?,
        val confidence: Float?,
        val rollDeg: Float?,
        val method: StumpMethod?,
        val timeToReadyMs: Long?,
    )

    data class Row(
        val trueDistanceM: Double,
        val samples: Int,
        /** Share of samples with a READY lock (confirmed, re-acquiring or coasting). */
        val readyRate: Double,
        val meanEstimateM: Double?,
        val sdEstimateM: Double?,
        /** (mean estimate − truth) / truth, percent. */
        val errorPct: Double?,
        val meanSpanPx: Double?,
        val meanConfidence: Double?,
    )

    private val samples = ArrayList<Sample>()

    val size: Int get() = samples.size

    @Synchronized
    fun record(
        trueDistanceM: Double,
        timestampMs: Long,
        lock: WicketLock?,
        camera: CameraIntrinsics?,
        uprightWidthPx: Int,
        rollDeg: Float?,
        timeToReadyMs: Long?,
    ) {
        val ready = lock != null && lock.state != WicketTrackState.TENTATIVE
        samples.add(
            Sample(
                trueDistanceM = trueDistanceM,
                timestampMs = timestampMs,
                state = lock?.state,
                detected = ready,
                estimatedM = if (ready && camera != null) WicketRange.fromSpan(lock!!, camera) else null,
                heightEstimateM = if (ready && camera != null) WicketRange.fromHeight(lock!!, camera) else null,
                spanPx = if (ready) WicketRange.spanPx(lock!!, uprightWidthPx) else null,
                confidence = lock?.confidence,
                rollDeg = rollDeg,
                method = lock?.method,
                timeToReadyMs = timeToReadyMs,
            ),
        )
    }

    @Synchronized
    fun clear() = samples.clear()

    /** One row per taped distance, nearest first. */
    @Synchronized
    fun summary(): List<Row> = samples.groupBy { it.trueDistanceM }.toSortedMap().map { (truth, group) ->
        val est = group.mapNotNull { it.estimatedM }
        val mean = est.takeIf { it.isNotEmpty() }?.average()
        val sd = if (est.size >= 2 && mean != null) {
            sqrt(est.sumOf { (it - mean) * (it - mean) } / (est.size - 1))
        } else {
            null
        }
        Row(
            trueDistanceM = truth,
            samples = group.size,
            readyRate = group.count { it.detected }.toDouble() / group.size,
            meanEstimateM = mean,
            sdEstimateM = sd,
            errorPct = mean?.let { (it - truth) / truth * 100.0 },
            meanSpanPx = group.mapNotNull { it.spanPx }.takeIf { it.isNotEmpty() }?.average(),
            meanConfidence = group.mapNotNull { it.confidence?.toDouble() }.takeIf { it.isNotEmpty() }?.average(),
        )
    }

    /** Every sample, then the summary, as CSV. */
    @Synchronized
    fun csv(cameraSource: String?): String = buildString {
        append("# Haraan stump validation. camera=").append(cameraSource ?: "unknown").append('\n')
        append("true_m,t_ms,state,ready,est_m,height_est_m,span_px,confidence,roll_deg,method,time_to_ready_ms\n")
        for (s in samples) {
            append(s.trueDistanceM).append(',')
            append(s.timestampMs).append(',')
            append(s.state ?: "").append(',')
            append(if (s.detected) 1 else 0).append(',')
            append(s.estimatedM?.let { "%.3f".format(java.util.Locale.ROOT, it) } ?: "").append(',')
            append(s.heightEstimateM?.let { "%.3f".format(java.util.Locale.ROOT, it) } ?: "").append(',')
            append(s.spanPx?.let { "%.2f".format(java.util.Locale.ROOT, it) } ?: "").append(',')
            append(s.confidence?.let { "%.3f".format(java.util.Locale.ROOT, it) } ?: "").append(',')
            append(s.rollDeg?.let { "%.2f".format(java.util.Locale.ROOT, it) } ?: "").append(',')
            append(s.method ?: "").append(',')
            append(s.timeToReadyMs ?: "").append('\n')
        }
        append("\n# summary\ntrue_m,samples,ready_rate,mean_est_m,sd_est_m,error_pct,mean_span_px,mean_confidence\n")
        for (r in summary()) {
            fun f(v: Double?) = v?.let { "%.3f".format(java.util.Locale.ROOT, it) } ?: ""
            append(r.trueDistanceM).append(',').append(r.samples).append(',')
            append(f(r.readyRate)).append(',').append(f(r.meanEstimateM)).append(',')
            append(f(r.sdEstimateM)).append(',').append(f(r.errorPct)).append(',')
            append(f(r.meanSpanPx)).append(',').append(f(r.meanConfidence)).append('\n')
        }
    }
}
