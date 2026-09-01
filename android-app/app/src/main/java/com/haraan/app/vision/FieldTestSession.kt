package com.haraan.app.vision

import org.json.JSONArray
import org.json.JSONObject
import java.io.File

/**
 * A field-test session: the deliveries filmed, what the detector saw in each, and the
 * conditions they were filmed under.
 *
 * Deliberately free of Android and OpenCV so it can be unit-tested on the JVM. That
 * matters more than usual here — the detector itself cannot be, because it needs the
 * native library, so everything that CAN be tested off-device should be.
 *
 * WHAT THIS REFUSES TO DO. It never computes an accuracy figure. A session knows how many
 * deliveries were filmed and how many produced a track, and those are two different
 * questions from "was the thing it tracked the ball". Only a human watching the footage
 * can answer that, so [validationStatus] stays UNANNOTATED until annotations exist, and
 * the summary says so in those words rather than quietly reporting a tracked-count as if
 * it were a hit-rate.
 */
class FieldTestSession(
    val startedAtMs: Long,
    val conditions: TestConditions,
) {
    private val deliveries = mutableListOf<DeliveryRecord>()

    fun record(record: DeliveryRecord) {
        deliveries.add(record)
    }

    fun deliveries(): List<DeliveryRecord> = deliveries.toList()

    fun summary(): SessionSummary {
        val tracked = deliveries.count { it.quality == TrackQuality.RELIABLE }
        val partial = deliveries.count { it.quality == TrackQuality.PARTIAL }
        val none = deliveries.count { it.quality == TrackQuality.UNCERTAIN }

        val processed = deliveries.filter { it.framesSeen > 0 }
        val avgProcessing = if (processed.isEmpty()) 0.0 else {
            processed.sumOf { it.averageProcessingMs * it.framesSeen } /
                processed.sumOf { it.framesSeen }.toDouble()
        }
        val avgFps = if (processed.isEmpty()) 0.0 else processed.map { it.analysisFps }.average()

        return SessionSummary(
            deliveries = deliveries.size,
            tracked = tracked,
            partial = partial,
            notTracked = none,
            averageProcessingMs = avgProcessing,
            averageAnalysisFps = avgFps,
            totalPoints = deliveries.sumOf { it.points.size },
            totalRejected = deliveries.sumOf { it.rejectedTotal() },
            // The whole point: a count of tracks is not a measure of correctness.
            validationStatus = if (deliveries.any { it.annotation != null }) {
                ValidationStatus.PARTIALLY_ANNOTATED
            } else {
                ValidationStatus.UNANNOTATED
            },
        )
    }

    /**
     * Write the session out as a folder a person can copy off the phone and analyse on a
     * laptop, where annotating frame-by-frame is far easier than on a handset.
     *
     * Videos are NOT copied or re-encoded — the record holds the path of the original
     * Full-HD clip, and that file is never touched by anything in this package.
     */
    fun export(into: File): File {
        val cvDir = File(into, "cv").apply { mkdirs() }
        File(into, "annotations").mkdirs()

        deliveries.forEach { record ->
            File(cvDir, "delivery_%03d.json".format(record.index))
                .writeText(record.toJson().toString(2))
        }

        val annotations = JSONArray()
        deliveries.forEach { record ->
            annotations.put(
                JSONObject().apply {
                    put("deliveryId", record.index)
                    put("ballVisible", record.annotation?.ballVisible ?: JSONObject.NULL)
                    put("releaseTimestampMs", record.annotation?.releaseMs ?: JSONObject.NULL)
                    put("bounceTimestampMs", record.annotation?.bounceMs ?: JSONObject.NULL)
                    put("impactTimestampMs", record.annotation?.impactMs ?: JSONObject.NULL)
                    put("annotator", record.annotation?.annotator ?: JSONObject.NULL)
                },
            )
        }
        // Written even when empty, with nulls rather than zeros: an unknown event is not
        // an event at millisecond zero, and a downstream benchmark must be able to tell
        // "nobody looked" from "it happened at the start".
        File(into, "annotations/annotations.json")
            .writeText(JSONObject().put("annotations", annotations).toString(2))

        val summary = summary()
        File(into, "session.json").writeText(
            JSONObject().apply {
                put("startedAtMs", startedAtMs)
                put("conditions", conditions.toJson())
                put("summary", summary.toJson())
                put("schemaVersion", 1)
                put(
                    "note",
                    "CV points are observations from the on-device detector. They are NOT " +
                        "ground truth. Fill annotations/annotations.json by watching the " +
                        "videos before computing any accuracy figure.",
                )
            }.toString(2),
        )
        return into
    }
}

/** One filmed delivery and everything measured while filming it. */
data class DeliveryRecord(
    val index: Int,
    val videoPath: String?,
    val startedAtMs: Long,
    val durationMs: Long,
    val points: List<BallSighting>,
    val quality: TrackQuality,
    val framesSeen: Int,
    val framesWithCandidate: Int,
    val rejectedGlobalMotion: Int,
    val rejectedSize: Int,
    val rejectedShape: Int,
    val rejectedTrajectory: Int,
    val averageProcessingMs: Double,
    val maxProcessingMs: Long,
    val analysisFps: Double,
    /** Null until a human has watched the clip. Never inferred from the CV output. */
    val annotation: HumanAnnotation? = null,
) {
    fun rejectedTotal() =
        rejectedGlobalMotion + rejectedSize + rejectedShape + rejectedTrajectory

    fun toJson(): JSONObject = JSONObject().apply {
        put("delivery", index)
        put("video", videoPath ?: JSONObject.NULL)
        put("startedAtMs", startedAtMs)
        put("durationMs", durationMs)
        put("trackQuality", quality.name)
        put(
            "points",
            JSONArray().also { arr ->
                points.forEach { p ->
                    arr.put(
                        JSONObject().apply {
                            put("timestampMs", p.timestampMs)
                            put("x", p.x)
                            put("y", p.y)
                            put("trackingConfidence", p.trackingConfidence)
                            put("areaPx", p.areaPx)
                            // Stamped here as well as server-side. Anything that reads
                            // this file must be able to tell a measurement from a guess
                            // without consulting anything else.
                            put("source", "computer_vision")
                            put("kind", "detected")
                        },
                    )
                }
            },
        )
        put(
            "diagnostics",
            JSONObject().apply {
                put("framesSeen", framesSeen)
                put("framesWithCandidate", framesWithCandidate)
                put("rejectedGlobalMotion", rejectedGlobalMotion)
                put("rejectedSize", rejectedSize)
                put("rejectedShape", rejectedShape)
                put("rejectedTrajectory", rejectedTrajectory)
                put("averageProcessingMs", averageProcessingMs)
                put("maxProcessingMs", maxProcessingMs)
                put("analysisFps", analysisFps)
            },
        )
        put("coordinateSpace", "image_normalised")
    }
}

/**
 * What a person saw, kept strictly apart from what the detector saw.
 *
 * Null means nobody has judged that event. It must never become 0, which would read as
 * "happened at the first millisecond" and quietly corrupt every timing error computed
 * against it.
 */
data class HumanAnnotation(
    val ballVisible: Boolean?,
    val releaseMs: Long?,
    val bounceMs: Long?,
    val impactMs: Long?,
    val annotator: String?,
)

/**
 * The conditions the session was filmed under.
 *
 * Recorded up front because failures are only explainable with them. "The tracker missed
 * eight deliveries" is not a finding; "it missed eight deliveries filmed square of the
 * wicket in evening light with a red ball" is the beginning of one.
 */
data class TestConditions(
    val cameraPosition: String,
    val lighting: String,
    val ballType: String,
    val cameraStable: Boolean,
    val ballVisible: Boolean,
    val pitchVisible: Boolean,
    val stumpsVisible: Boolean,
    val batterVisible: Boolean,
    val adequateLighting: Boolean,
    val notes: String,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("cameraPosition", cameraPosition)
        put("lighting", lighting)
        put("ballType", ballType)
        put(
            "checklist",
            JSONObject().apply {
                put("cameraStable", cameraStable)
                put("ballVisible", ballVisible)
                put("pitchVisible", pitchVisible)
                put("stumpsVisible", stumpsVisible)
                put("batterVisible", batterVisible)
                put("adequateLighting", adequateLighting)
            },
        )
        put("notes", notes)
    }

    companion object {
        fun blank() = TestConditions(
            cameraPosition = "behind_bowler",
            lighting = "day",
            ballType = "white",
            cameraStable = false,
            ballVisible = false,
            pitchVisible = false,
            stumpsVisible = false,
            batterVisible = false,
            adequateLighting = false,
            notes = "",
        )
    }
}

data class SessionSummary(
    val deliveries: Int,
    val tracked: Int,
    val partial: Int,
    val notTracked: Int,
    val averageProcessingMs: Double,
    val averageAnalysisFps: Double,
    val totalPoints: Int,
    val totalRejected: Int,
    val validationStatus: ValidationStatus,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("deliveries", deliveries)
        put("trackedReliable", tracked)
        put("trackedPartial", partial)
        put("notTracked", notTracked)
        put("averageProcessingMs", averageProcessingMs)
        put("averageAnalysisFps", averageAnalysisFps)
        put("totalPoints", totalPoints)
        put("totalRejected", totalRejected)
        put("validationStatus", validationStatus.name)
        put(
            "accuracyNote",
            "No accuracy figure is produced here. Tracked counts say the detector found " +
                "SOMETHING, not that it found the ball.",
        )
    }
}

/** Whether these recordings can support an accuracy claim at all. */
enum class ValidationStatus {
    /** Filmed, nothing judged by a human. No accuracy figure may be computed. */
    UNANNOTATED,

    /** Some deliveries judged. Metrics may be computed over the annotated subset only. */
    PARTIALLY_ANNOTATED,

    /** Every delivery judged. */
    ANNOTATED,
}
