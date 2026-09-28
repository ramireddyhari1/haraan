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
}
