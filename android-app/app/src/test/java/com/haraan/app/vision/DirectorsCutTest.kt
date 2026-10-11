package com.haraan.app.vision

import org.junit.Assert.assertTrue
import org.junit.Test

class DirectorsCutTest {
    @Test
    fun cutsStayInOrderForAnyBounce() {
        var pb = 0.0
        while (pb <= 1.0) {
            val (a, b, c) = directorsCutMarks(pb)
            assertTrue("pb=$pb $a $b $c", a < b && b < c && c < 1.0 && a > 0.12)
            pb += 0.01
        }
    }

    @Test
    fun aYorkerPitchingAtTheEndDoesNotThrow() {
        // The real net clip: the ball pitched in the last few percent of its flight.
        directorsCutMarks(0.95)
        directorsCutMarks(0.99)
        directorsCutMarks(1.0)
    }
}
