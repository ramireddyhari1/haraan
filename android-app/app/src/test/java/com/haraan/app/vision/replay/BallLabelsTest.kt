package com.haraan.app.vision.replay

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder

/**
 * The label file, which has to outlive the detector it was made to judge.
 *
 * A ground truth is only worth gathering if it survives — a reinstall, a refactor, a year.
 * So the round trip is tested rather than assumed, and the format is checked for being
 * the readable thing it claims to be.
 */
class BallLabelsTest {

    @get:Rule
    val folder = TemporaryFolder()

    private fun set(vararg labels: FrameLabel) =
        LabelSet("delivery_003.mp4", 1920, 1080, labels.toList())

    @Test
    fun `labels survive a round trip through json`() {
        val original = set(
            FrameLabel.Ball(4, 132L, 0.3125f, 0.6875f),
            FrameLabel.Absent(5, 165L),
        )

        val restored = LabelStore.fromJson(LabelStore.toJson(original))

        assertEquals(original, restored)
    }

    @Test
    fun `the file is readable by a person, not just by us`() {
        val json = LabelStore.toJson(set(FrameLabel.Ball(2, 66L, 0.5f, 0.25f)))

        // Indented, with the clip and the frame geometry alongside the points — because a
        // pixel error means nothing without knowing the frame it was measured in.
        assertTrue(json.contains("\n"))
        assertTrue(json.contains("delivery_003.mp4"))
        assertTrue(json.contains("1920"))
        assertTrue(json.contains("\"frame\""))
    }

    @Test
    fun `a saved set loads back from disk under the clip's name`() {
        val base = folder.newFolder()
        val original = set(FrameLabel.Ball(1, 33L, 0.2f, 0.4f), FrameLabel.Absent(2, 66L))

        LabelStore.save(base, original)

        assertEquals(original, LabelStore.load(base, "delivery_003.mp4"))
    }

    @Test
    fun `a clip nobody has labelled loads as nothing rather than as empty`() {
        // Null, not an empty set: "never reviewed" and "reviewed and found nothing" are
        // different claims, and the scoring depends on not confusing them.
        assertNull(LabelStore.load(folder.newFolder(), "never-seen.mp4"))
    }

    @Test
    fun `a clip name that is not a filename still gets a file`() {
        val base = folder.newFolder()
        val awkward = set().copy(clip = "content://media/video/42 (copy).mp4")

        val file = LabelStore.save(base, awkward)

        assertTrue(file.exists())
        assertTrue("no path separators may survive", !file.name.contains("/"))
        assertEquals(awkward, LabelStore.load(base, awkward.clip))
    }

    @Test
    fun `corrupt json is refused rather than half-read`() {
        assertNull(LabelStore.fromJson("{ this is not json"))
        assertNull(LabelStore.fromJson(""))
    }

    @Test
    fun `labelling a frame twice replaces the first answer`() {
        val first = set().with(FrameLabel.Ball(3, 99L, 0.1f, 0.1f))
        val second = first.with(FrameLabel.Ball(3, 99L, 0.8f, 0.8f))

        assertEquals(1, second.reviewed)
        assertEquals(0.8f, (second.at(3) as FrameLabel.Ball).x, 1e-6f)
    }

    @Test
    fun `changing your mind from a ball to an empty frame replaces it too`() {
        val labelled = set().with(FrameLabel.Ball(3, 99L, 0.1f, 0.1f))

        val corrected = labelled.with(FrameLabel.Absent(3, 99L))

        assertEquals(1, corrected.reviewed)
        assertEquals(0, corrected.withBall)
        assertTrue(corrected.at(3) is FrameLabel.Absent)
    }

    @Test
    fun `clearing a frame returns it to unreviewed`() {
        val labelled = set().with(FrameLabel.Ball(3, 99L, 0.1f, 0.1f))

        val cleared = labelled.without(3)

        assertEquals(0, cleared.reviewed)
        assertNull(cleared.at(3))
    }

    @Test
    fun `labels stay in frame order however they were gathered`() {
        // A reviewer jumps about. The file should not record the order they wandered in.
        val wandering = set()
            .with(FrameLabel.Ball(9, 297L, 0.5f, 0.5f))
            .with(FrameLabel.Absent(2, 66L))
            .with(FrameLabel.Ball(5, 165L, 0.4f, 0.4f))

        assertEquals(listOf(2, 5, 9), wandering.labels.map { it.frameIndex })
    }

    @Test
    fun `the aspect comes from the frame the labels were taken in`() {
        assertEquals(16f / 9f, set().aspect, 1e-4f)
        assertEquals(1f, LabelSet("c", 0, 0, emptyList()).aspect, 1e-4f)
    }
}
