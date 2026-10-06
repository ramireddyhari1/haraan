package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * A mirrored replay camera would put every outswinger on the wrong side and nobody would
 * notice from the picture, so the handedness of each view is pinned here.
 */
class ReplayCameraTest {

    private val w = 1080.0
    private val h = 1920.0

    @Test
    fun `the target lands in the middle of the screen`() {
        val cam = ReplayView.AUTO.camera
        val p = cam.project(cam.target, w, h)!!
        assertEquals(w / 2, p.x, 1e-6)
        assertEquals(h / 2, p.y, 1e-6)
    }

    @Test
    fun `from behind the bowler, the right of the pitch is the right of the screen`() {
        val cam = ReplayView.AUTO.camera
        val right = cam.project(Point3(1.0, 5.0, 0.0), w, h)!!
        val left = cam.project(Point3(-1.0, 5.0, 0.0), w, h)!!
        assertTrue(right.x > left.x)
    }

    @Test
    fun `from behind the bowler, the striker's end is further up the screen`() {
        val cam = ReplayView.AUTO.camera
        val striker = cam.project(Point3(0.0, 0.0, 0.0), w, h)!!
        val bowler = cam.project(Point3(0.0, 18.0, 0.0), w, h)!!
        assertTrue(striker.y < bowler.y)
    }

    @Test
    fun `the top view reads like a pitch map, striker at the top, off and leg unmirrored`() {
        val cam = ReplayView.TOP.camera
        val striker = cam.project(Point3(0.0, 0.0, 0.0), w, h)!!
        val bowler = cam.project(Point3(0.0, 20.0, 0.0), w, h)!!
        assertTrue(striker.y < bowler.y)
        val right = cam.project(Point3(1.0, 10.0, 0.0), w, h)!!
        assertTrue(right.x > w / 2)
    }

    @Test
    fun `a point behind the camera is not drawn`() {
        assertNull(ReplayView.AUTO.camera.project(Point3(0.0, 60.0, 1.0), w, h))
    }

    @Test
    fun `a polygon running behind the camera is cut, not flipped`() {
        val cam = ReplayView.AUTO.camera
        val ground = listOf(Point3(-5.0, 0.0, 0.0), Point3(5.0, 0.0, 0.0), Point3(5.0, 40.0, 0.0), Point3(-5.0, 40.0, 0.0))
        val screen = cam.polygon(ground, w, h)
        assertTrue(screen.size >= 3)
        // Everything kept is in front of the lens, so nothing is above the horizon-flip.
        screen.forEach { assertTrue(it.y > -h) }
    }

    @Test
    fun `the stump camera has the striker's stumps in shot, the right way round`() {
        val cam = ReplayView.STUMPS.camera
        val middle = cam.project(Point3(0.0, 0.0, 0.4), w, h)!!
        assertTrue(middle.x in 0.0..w && middle.y in 0.0..h)
        val off = cam.project(Point3(0.1, 0.0, 0.4), w, h)!!
        assertTrue("+x must be screen-right from the bowling side", off.x > middle.x)
    }

    @Test
    fun `from behind the stumps the bowler's end is in front of the camera`() {
        val cam = ReplayView.KEEPER.camera
        assertNotNull(cam.project(Point3(0.0, 18.0, 0.5), w, h))
        assertNull(cam.project(Point3(0.0, -10.0, 0.5), w, h))
    }

    @Test
    fun `the director's cut ends on the stumps`() {
        val end = ReplayView.AUTO.cameraAt(1.0)
        val stumps = end.project(Point3(0.0, 0.0, 0.4), w, h)!!
        assertTrue(stumps.x in 0.0..w && stumps.y in 0.0..h)
        assertTrue(end.toCamera(Point3(0.0, 0.0, 0.4)).z < 6.0)
    }

    @Test
    fun `gliding between views passes through finite cameras`() {
        val mid = ReplayView.AUTO.camera.lerp(ReplayView.TOP.camera, 0.5)
        assertNotNull(mid.project(Point3(0.0, 9.0, 0.0), w, h))
    }
}
