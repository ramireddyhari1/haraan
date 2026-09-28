package com.haraan.app.ui.matches

import com.haraan.app.data.DeliveryReview
import com.haraan.app.data.ReviewFactor
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * The rules that decide whether the word OUT goes on a screen people will believe.
 */
class ReviewRevealTest {

    private fun review(vararg pairs: Pair<String, String>, certain: Boolean = true) = DeliveryReview(
        factors = pairs.map { ReviewFactor(it.first, it.second, certain) },
        visibility = "good",
        notes = null,
    )

    private val plumb = arrayOf(
        "pitching" to "in_line",
        "impact" to "in_line",
        "bat_involved" to "no_bat",
        "height" to "below_stumps",
        "line" to "would_hit",
    )

    private fun readOf(r: DeliveryReview) = cameraReadOf(revealChipsOf(r))

    @Test
    fun `all five certain and out-leaning reads OUT`() {
        assertEquals(CameraRead.OUT, readOf(review(*plumb)))
    }

    @Test
    fun `the same five without certainty is only the umpire's call`() {
        assertEquals(CameraRead.UMPIRES_CALL, readOf(review(*plumb, certain = false)))
    }

    @Test
    fun `one certain not-out reason is enough for NOT OUT`() {
        val r = review(*plumb.map { if (it.first == "pitching") "pitching" to "outside_leg" else it }.toTypedArray())
        assertEquals(CameraRead.NOT_OUT, readOf(r))
    }

    @Test
    fun `pitching outside off still counts towards out`() {
        val r = review(*plumb.map { if (it.first == "pitching") "pitching" to "outside_off" else it }.toTypedArray())
        assertEquals(CameraRead.OUT, readOf(r))
    }

    @Test
    fun `impact outside off depends on a shot nobody can see`() {
        val r = review(*plumb.map { if (it.first == "impact") "impact" to "outside_off" else it }.toTypedArray())
        assertEquals(CameraRead.UMPIRES_CALL, readOf(r))
    }

    @Test
    fun `two unanswered questions is too little to say anything`() {
        val r = review(
            "pitching" to "in_line",
            "impact" to "cannot_tell",
            "bat_involved" to "no_bat",
            "height" to "cannot_tell",
            "line" to "would_hit",
        )
        assertEquals(CameraRead.CANT_SAY, readOf(r))
    }

    @Test
    fun `a missing factor can never make OUT`() {
        val r = review(*plumb.filter { it.first != "height" }.toTypedArray())
        assertEquals(CameraRead.UMPIRES_CALL, readOf(r))
    }

    @Test
    fun `chips come out in cricket's order whatever the server sent`() {
        val r = review(*plumb.reversedArray())
        assertEquals(listOf("PITCHING", "IMPACT", "BAT", "HEIGHT", "WICKETS"), revealChipsOf(r).map { it.label })
    }

    private fun cam(verdict: String) = com.haraan.app.data.CameraWickets(verdict, 4.0, 3.0, "note")

    @Test
    fun `the camera's wickets answer is there while the model is still reading`() {
        val slots = revealSlotsOf(review = null, wickets = cam("HITTING"), reading = true)
        val wickets = slots.last()
        assertEquals("Hitting", wickets.chip.value)
        assertEquals(false, wickets.pending)
        assertEquals(true, wickets.fromCamera)
        assertEquals(true, slots.dropLast(1).all { it.pending })
    }

    @Test
    fun `the camera's measurement outranks the model on wickets`() {
        val r = review(*plumb.map { if (it.first == "line") "line" to "would_miss" else it }.toTypedArray())
        val slots = revealSlotsOf(r, cam("HITTING"), reading = false)
        assertEquals("Hitting", slots.last().chip.value)
        assertEquals(CameraRead.OUT, cameraReadOf(slots.map { it.chip }))
    }

    @Test
    fun `when the camera could not judge, the model's wickets answer is used`() {
        val slots = revealSlotsOf(review(*plumb), cam("UNAVAILABLE"), reading = false)
        assertEquals("Hitting", slots.last().chip.value)
        assertEquals(false, slots.last().fromCamera)
    }

    @Test
    fun `a camera that could not judge and no model read says not judged`() {
        val slots = revealSlotsOf(null, cam("UNAVAILABLE"), reading = false)
        assertEquals("Not judged", slots.last().chip.value)
        assertEquals(CameraRead.CANT_SAY, cameraReadOf(slots.map { it.chip }))
    }

    @Test
    fun `camera missing is a certain not out`() {
        val slots = revealSlotsOf(review(*plumb), cam("MISSING"), reading = false)
        assertEquals(CameraRead.NOT_OUT, cameraReadOf(slots.map { it.chip }))
    }

    @Test
    fun `umpire's call from the camera never makes OUT`() {
        val slots = revealSlotsOf(review(*plumb), cam("UMPIRES_CALL"), reading = false)
        assertEquals(CameraRead.UMPIRES_CALL, cameraReadOf(slots.map { it.chip }))
    }
}
