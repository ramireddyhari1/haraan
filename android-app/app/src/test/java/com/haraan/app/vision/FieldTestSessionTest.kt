package com.haraan.app.vision

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder

/**
 * The session, serialisation and export layer.
 *
 * These run on the JVM, which is deliberate: the detector itself needs native OpenCV and
 * can only be exercised on a device, so everything that CAN be tested off-device is kept
 * free of Android and OpenCV and tested here.
 *
 * The rules being defended are about honesty rather than correctness of arithmetic:
 * unknown stays null, provenance is stamped, and no accuracy figure is ever produced from
 * footage nobody has watched.
 */
class FieldTestSessionTest {

    @get:Rule
    val folder = TemporaryFolder()

    private fun sighting(t: Long, x: Float = 0.4f, y: Float = 0.5f, c: Float = 0.8f) =
        BallSighting(timestampMs = t, x = x, y = y, trackingConfidence = c, areaPx = 30)

    private fun record(
        index: Int,
        quality: TrackQuality = TrackQuality.RELIABLE,
        points: List<BallSighting> = listOf(sighting(0), sighting(33), sighting(66)),
        annotation: HumanAnnotation? = null,
    ) = DeliveryRecord(
        index = index,
        videoPath = "/storage/vision-test/delivery_%03d.mp4".format(index),
        startedAtMs = 1_000L,
        durationMs = 8_000L,
        points = points,
        quality = quality,
        framesSeen = 240,
        framesWithCandidate = points.size,
        rejectedGlobalMotion = 3,
        rejectedSize = 5,
        rejectedShape = 2,
        rejectedTrajectory = 1,
        averageProcessingMs = 17.5,
        maxProcessingMs = 40,
        analysisFps = 28.0,
        annotation = annotation,
    )

    private fun session() = FieldTestSession(1_000L, TestConditions.blank())

    // ── The rule that matters most ──────────────────────────────────────────────

    /**
     * Filming is not validating. A session where the detector produced beautiful tracks on
     * every delivery still cannot support an accuracy claim, because nobody has confirmed
     * the tracked object was the ball.
     */
    fun `unannotated deliveries never yield a validated session`() = Unit

    @Test
    fun unannotated_session_reports_unannotated() {
        val s = session()
        repeat(30) { s.record(record(it + 1)) }

        val summary = s.summary()
        assertEquals(30, summary.deliveries)
        assertEquals(30, summary.tracked)
        // Thirty perfect tracks, and still not validated.
        assertEquals(ValidationStatus.UNANNOTATED, summary.validationStatus)
    }

    @Test
    fun one_annotation_moves_it_to_partially_annotated() {
        val s = session()
        s.record(record(1))
        s.record(record(2, annotation = HumanAnnotation(true, 1200, 2400, 3100, "hari")))

        assertEquals(ValidationStatus.PARTIALLY_ANNOTATED, s.summary().validationStatus)
    }

    @Test
    fun the_summary_carries_no_accuracy_figure() {
        val s = session()
        repeat(5) { s.record(record(it + 1)) }

        val json = s.summary().toJson().toString().lowercase()
        assertFalse("a summary must not contain an accuracy field", json.contains("\"accuracy\""))
        assertTrue(json.contains("accuracynote"))
        assertTrue(json.contains("validationstatus"))
    }

    // ── Track quality accounting ────────────────────────────────────────────────

    @Test
    fun quality_counts_are_kept_apart() {
        val s = session()
        s.record(record(1, TrackQuality.RELIABLE))
        s.record(record(2, TrackQuality.RELIABLE))
        s.record(record(3, TrackQuality.PARTIAL))
        s.record(record(4, TrackQuality.UNCERTAIN))

        val summary = s.summary()
        assertEquals(2, summary.tracked)
        assertEquals(1, summary.partial)
        assertEquals(1, summary.notTracked)
    }

    @Test
    fun processing_time_is_weighted_by_frames_not_by_delivery() {
        val s = session()
        s.record(record(1).copy(framesSeen = 100, averageProcessingMs = 10.0))
        s.record(record(2).copy(framesSeen = 300, averageProcessingMs = 20.0))

        // A naive mean would say 15ms; weighting by frames gives 17.5, which is what a
        // phone actually spent per frame.
        assertEquals(17.5, s.summary().averageProcessingMs, 0.01)
    }

    @Test
    fun an_empty_session_does_not_divide_by_zero() {
        val summary = session().summary()

        assertEquals(0, summary.deliveries)
        assertEquals(0.0, summary.averageProcessingMs, 0.0)
        assertEquals(ValidationStatus.UNANNOTATED, summary.validationStatus)
    }

    // ── Serialisation and provenance ────────────────────────────────────────────

    @Test
    fun every_point_is_stamped_as_computer_vision_and_detected() {
        val json = record(1).toJson()
        val points = json.getJSONArray("points")

        assertEquals(3, points.length())
        for (i in 0 until points.length()) {
            val p = points.getJSONObject(i)
            assertEquals("computer_vision", p.getString("source"))
            assertEquals("detected", p.getString("kind"))
        }
        assertEquals("image_normalised", json.getString("coordinateSpace"))
    }

    @Test
    fun points_keep_their_order_and_coordinates() {
        val points = listOf(sighting(0, 0.10f, 0.20f), sighting(33, 0.30f, 0.40f), sighting(66, 0.50f, 0.60f))
        val arr = record(1, points = points).toJson().getJSONArray("points")

        assertEquals(listOf(0, 33, 66), (0 until arr.length()).map { arr.getJSONObject(it).getInt("timestampMs") })
        assertEquals(0.10, arr.getJSONObject(0).getDouble("x"), 0.0001)
        assertEquals(0.60, arr.getJSONObject(2).getDouble("y"), 0.0001)
    }

    @Test
    fun a_delivery_with_no_points_serialises_as_an_empty_track() {
        val json = record(1, TrackQuality.UNCERTAIN, points = emptyList()).toJson()

        assertEquals(0, json.getJSONArray("points").length())
        assertEquals("UNCERTAIN", json.getString("trackQuality"))
    }

    @Test
    fun rejection_counters_survive_serialisation() {
        val d = record(1).toJson().getJSONObject("diagnostics")

        assertEquals(3, d.getInt("rejectedGlobalMotion"))
        assertEquals(5, d.getInt("rejectedSize"))
        assertEquals(2, d.getInt("rejectedShape"))
        assertEquals(1, d.getInt("rejectedTrajectory"))
        assertEquals(11, record(1).rejectedTotal())
    }

    // ── Export ──────────────────────────────────────────────────────────────────

    @Test
    fun export_writes_the_expected_structure() {
        val s = session()
        s.record(record(1))
        s.record(record(2))

        val out = s.export(folder.newFolder("session"))

        assertTrue(java.io.File(out, "session.json").exists())
        assertTrue(java.io.File(out, "cv/delivery_001.json").exists())
        assertTrue(java.io.File(out, "cv/delivery_002.json").exists())
        assertTrue(java.io.File(out, "annotations/annotations.json").exists())
    }

    /**
     * Unknown must never be written as zero. A bounce annotated as 0ms would read as
     * "happened at the first frame" and silently poison every timing error computed
     * against it.
     */
    @Test
    fun missing_annotations_export_as_null_never_zero() {
        val s = session()
        s.record(record(1))

        val out = s.export(folder.newFolder("session"))
        val text = java.io.File(out, "annotations/annotations.json").readText()
        val entry = JSONObject(text).getJSONArray("annotations").getJSONObject(0)

        assertTrue(entry.isNull("ballVisible"))
        assertTrue(entry.isNull("releaseTimestampMs"))
        assertTrue(entry.isNull("bounceTimestampMs"))
        assertTrue(entry.isNull("impactTimestampMs"))
        assertTrue(entry.isNull("annotator"))
    }

    @Test
    fun present_annotations_are_exported_intact() {
        val s = session()
        s.record(record(1, annotation = HumanAnnotation(true, 1200, 2400, 3100, "hari")))

        val out = s.export(folder.newFolder("session"))
        val entry = JSONObject(java.io.File(out, "annotations/annotations.json").readText())
            .getJSONArray("annotations").getJSONObject(0)

        assertTrue(entry.getBoolean("ballVisible"))
        assertEquals(1200, entry.getLong("releaseTimestampMs"))
        assertEquals(3100, entry.getLong("impactTimestampMs"))
        assertEquals("hari", entry.getString("annotator"))
    }

    /** The export must state that CV output is not ground truth, in the file itself. */
    @Test
    fun the_session_file_warns_against_treating_cv_as_truth() {
        val s = session()
        s.record(record(1))

        val out = s.export(folder.newFolder("session"))
        val session = JSONObject(java.io.File(out, "session.json").readText())

        assertTrue(session.getString("note").contains("NOT ground truth"))
        assertNotNull(session.getJSONObject("conditions"))
        assertEquals(1, session.getInt("schemaVersion"))
    }

    @Test
    fun conditions_are_exported_with_the_checklist() {
        val conditions = TestConditions(
            cameraPosition = "behind_bowler",
            lighting = "evening",
            ballType = "red",
            cameraStable = true,
            ballVisible = true,
            pitchVisible = true,
            stumpsVisible = false,
            batterVisible = true,
            adequateLighting = false,
            notes = "long shadows across the pitch",
        )
        val s = FieldTestSession(1_000L, conditions)
        s.record(record(1))

        val out = s.export(folder.newFolder("session"))
        val c = JSONObject(java.io.File(out, "session.json").readText()).getJSONObject("conditions")

        assertEquals("red", c.getString("ballType"))
        assertEquals("evening", c.getString("lighting"))
        assertFalse(c.getJSONObject("checklist").getBoolean("stumpsVisible"))
        assertTrue(c.getString("notes").contains("shadows"))
    }

    @Test
    fun exporting_an_empty_session_still_produces_a_readable_package() {
        val out = session().export(folder.newFolder("session"))

        val annotations = JSONObject(java.io.File(out, "annotations/annotations.json").readText())
        assertEquals(0, annotations.getJSONArray("annotations").length())
        assertTrue(java.io.File(out, "session.json").exists())
    }
}
