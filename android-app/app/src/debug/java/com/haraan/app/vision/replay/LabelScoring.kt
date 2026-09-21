package com.haraan.app.vision.replay

import kotlin.math.sqrt

/**
 * What the detector got right, against what a person said was there.
 *
 * This is the first thing in the whole vision package that can be WRONG in a way the code
 * can detect. Everything before it — sightings, diagnostics, the delivery panel — reports
 * what the detector believed. These are the numbers that say whether believing it was
 * justified, and they exist only because somebody sat and pointed at frames.
 *
 * [precision] and [recall] are nullable on purpose. With nothing detected and nothing
 * labelled there is no ratio to report, and a screen that printed 0% or 100% there would
 * be inventing a verdict out of an empty sample.
 */
data class LabelScore(
    val reviewedFrames: Int,
    val truePositives: Int,
    val falsePositives: Int,
    val falseNegatives: Int,
    val trueNegatives: Int,
    /** Median distance between a matched detection and its label, in frame widths. */
    val medianErrorFw: Double?,
    val worstErrorFw: Double?,
) {
    /** Of what it called a ball, how much was. Null when it never called anything. */
    val precision: Double?
        get() = (truePositives + falsePositives).takeIf { it > 0 }
            ?.let { truePositives.toDouble() / it }

    /** Of the balls that were there, how many it found. Null when none were labelled. */
    val recall: Double?
        get() = (truePositives + falseNegatives).takeIf { it > 0 }
            ?.let { truePositives.toDouble() / it }

    val f1: Double?
        get() {
            val p = precision ?: return null
            val r = recall ?: return null
            return if (p + r <= 0.0) 0.0 else 2 * p * r / (p + r)
        }
}

object LabelScoring {

    /**
     * How close a detection has to be to count as the same ball, in frame widths.
     *
     * Two per cent of the frame — about 38px on a 1920-wide clip, a few ball widths at
     * the distances this films from. Deliberately generous: the question this tool exists
     * to answer first is "did it find the ball or an arm", which is a question about tens
     * of per cent, not pixels. Tighten it once the answer stops being obvious.
     */
    const val DEFAULT_TOLERANCE_FW = 0.02f

    /**
     * Score [detections] against [set].
     *
     * ONLY REVIEWED FRAMES COUNT. A detection in a frame nobody labelled is neither
     * credited nor penalised, because there is no evidence either way — and quietly
     * forgiving those is the easiest way to make a bad detector look good.
     *
     * A detection that fires in the wrong place scores BOTH a false positive and a false
     * negative: it claimed a ball that was not there, and it missed the one that was.
     * Counting it once would let a detector that is confidently wrong score the same as
     * one that honestly says nothing.
     *
     * @param detections the tracker's position per frame index, normalised, as reported.
     */
    fun score(
        set: LabelSet,
        detections: Map<Int, Pair<Float, Float>>,
        toleranceFw: Float = DEFAULT_TOLERANCE_FW,
    ): LabelScore {
        val aspect = set.aspect.takeIf { it > 0f } ?: 1f
        var tp = 0
        var fp = 0
        var fn = 0
        var tn = 0
        val errors = mutableListOf<Double>()

        set.labels.forEach { label ->
            val detected = detections[label.frameIndex]
            when (label) {
                is FrameLabel.Ball -> {
                    if (detected == null) {
                        fn++
                    } else {
                        val error = separationFw(label.x, label.y, detected.first, detected.second, aspect)
                        if (error <= toleranceFw) {
                            tp++
                            errors += error
                        } else {
                            fp++
                            fn++
                        }
                    }
                }

                is FrameLabel.Absent -> if (detected == null) tn++ else fp++
            }
        }

        return LabelScore(
            reviewedFrames = set.labels.size,
            truePositives = tp,
            falsePositives = fp,
            falseNegatives = fn,
            trueNegatives = tn,
            medianErrorFw = errors.median(),
            worstErrorFw = errors.maxOrNull(),
        )
    }

    /**
     * Distance in frame widths.
     *
     * y is divided by the aspect because it is normalised against the HEIGHT: on a 16:9
     * clip a tenth of the height and a tenth of the width are nowhere near the same
     * distance, and a tolerance that ignored that would be lenient vertically and strict
     * horizontally for no reason anybody chose.
     */
    private fun separationFw(ax: Float, ay: Float, bx: Float, by: Float, aspect: Float): Double {
        val dx = (bx - ax).toDouble()
        val dy = (by - ay).toDouble() / aspect
        return sqrt(dx * dx + dy * dy)
    }

    /** Median, not mean: one detection on a fielder's glove should not move the headline. */
    private fun List<Double>.median(): Double? {
        if (isEmpty()) return null
        val sorted = sorted()
        val middle = sorted.size / 2
        return if (sorted.size % 2 == 1) {
            sorted[middle]
        } else {
            (sorted[middle - 1] + sorted[middle]) / 2.0
        }
    }
}
