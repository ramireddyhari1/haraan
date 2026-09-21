package com.haraan.app.vision.replay

import org.json.JSONArray
import org.json.JSONObject
import java.io.File

/**
 * What a human says was actually in one frame.
 *
 * THREE STATES, NOT TWO, and this is the whole design. A frame can carry a ball, be
 * confirmed empty, or never have been looked at — and a scoring pass that cannot tell
 * "confirmed empty" from "not reviewed" cannot count a false positive. Every detection in
 * an unreviewed frame would have to be silently forgiven, which flatters precision exactly
 * where a detector is worst: the frames a person skipped because nothing was happening.
 *
 * So absence is recorded explicitly, and only reviewed frames are ever scored.
 */
sealed interface FrameLabel {
    val frameIndex: Int
    val timestampMs: Long

    /** A person pointed at the ball here. Normalised upright coordinates, as sightings are. */
    data class Ball(
        override val frameIndex: Int,
        override val timestampMs: Long,
        val x: Float,
        val y: Float,
    ) : FrameLabel

    /** A person looked and there was no ball to point at. */
    data class Absent(
        override val frameIndex: Int,
        override val timestampMs: Long,
    ) : FrameLabel
}

/**
 * Every reviewed frame of one clip.
 *
 * [frameWidth] and [frameHeight] travel with the labels because a pixel error means
 * nothing without them: the same 0.01 of normalised distance is a different number of
 * pixels on a 720p clip and a 1080p one, and the two axes are not even the same unit.
 */
data class LabelSet(
    val clip: String,
    val frameWidth: Int,
    val frameHeight: Int,
    val labels: List<FrameLabel>,
) {
    val reviewed: Int get() = labels.size
    val withBall: Int get() = labels.count { it is FrameLabel.Ball }

    /** Width over height, for putting the two axes into one unit. */
    val aspect: Float
        get() = if (frameHeight > 0) frameWidth.toFloat() / frameHeight else 1f

    /** Labels are unique per frame; a later one replaces an earlier one. */
    fun with(label: FrameLabel): LabelSet =
        copy(labels = (labels.filterNot { it.frameIndex == label.frameIndex } + label)
            .sortedBy { it.frameIndex })

    fun without(frameIndex: Int): LabelSet =
        copy(labels = labels.filterNot { it.frameIndex == frameIndex })

    fun at(frameIndex: Int): FrameLabel? = labels.firstOrNull { it.frameIndex == frameIndex }
}

/**
 * Labels on disk, as JSON a person can read.
 *
 * JSON rather than anything faster because these files are small, are meant to be pulled
 * off a device with adb and inspected, and will outlive several versions of the detector
 * they were made to judge. A ground truth that can only be read by the program that wrote
 * it is not much of a ground truth.
 */
object LabelStore {

    const val DIRECTORY = "labels"
    private const val VERSION = 1

    fun directory(base: File): File = File(base, DIRECTORY).also { it.mkdirs() }

    /** One file per clip, named after it so the pairing survives a copy off the device. */
    fun fileFor(base: File, clip: String): File =
        File(directory(base), "${clip.replace(Regex("[^A-Za-z0-9._-]"), "_")}.labels.json")

    fun save(base: File, set: LabelSet): File =
        fileFor(base, set.clip).also { it.writeText(toJson(set)) }

    fun load(base: File, clip: String): LabelSet? {
        val file = fileFor(base, clip)
        if (!file.exists()) return null
        return runCatching { fromJson(file.readText()) }.getOrNull()
    }

    fun toJson(set: LabelSet): String {
        val labels = JSONArray()
        set.labels.forEach { label ->
            val item = JSONObject()
                .put("frame", label.frameIndex)
                .put("t", label.timestampMs)
            when (label) {
                is FrameLabel.Ball -> item.put("ball", true)
                    .put("x", label.x.toDouble())
                    .put("y", label.y.toDouble())

                is FrameLabel.Absent -> item.put("ball", false)
            }
            labels.put(item)
        }
        return JSONObject()
            .put("version", VERSION)
            .put("clip", set.clip)
            .put("width", set.frameWidth)
            .put("height", set.frameHeight)
            .put("labels", labels)
            .toString(2)
    }

    fun fromJson(text: String): LabelSet? = runCatching {
        val root = JSONObject(text)
        val array = root.getJSONArray("labels")
        val labels = (0 until array.length()).mapNotNull { i ->
            val item = array.getJSONObject(i)
            val frame = item.getInt("frame")
            val t = item.optLong("t", 0L)
            if (item.optBoolean("ball", false)) {
                FrameLabel.Ball(frame, t, item.getDouble("x").toFloat(), item.getDouble("y").toFloat())
            } else {
                FrameLabel.Absent(frame, t)
            }
        }
        LabelSet(
            clip = root.optString("clip", "clip"),
            frameWidth = root.optInt("width", 0),
            frameHeight = root.optInt("height", 0),
            labels = labels.sortedBy { it.frameIndex },
        )
    }.getOrNull()
}
