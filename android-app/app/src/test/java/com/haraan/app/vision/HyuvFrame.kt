package com.haraan.app.vision

import java.io.DataInputStream
import java.io.File

/**
 * One real camera frame, as the camera screen's developer "Dump frame" wrote it.
 *
 * Lets a scene filmed on a real phone — real lens, real light, real stumps — be replayed
 * through the detector at a desk, where it can be measured and fixed and then kept as a
 * regression test. Format: "HYUV", width, height, rotation, then for each of Y, U, V:
 * rowStride, pixelStride, byte count, bytes.
 */
class HyuvFrame(
    val width: Int,
    val height: Int,
    val rotation: Int,
    val y: ByteArray,
    val yRowStride: Int,
    val u: ByteArray,
    val v: ByteArray,
    val uvRowStride: Int,
    val uvPixelStride: Int,
) {
    /** U (Cb) at luma resolution, nearest-neighbour, laid out with stride = width. */
    fun uFull(): ByteArray = chromaFull(u)
    fun vFull(): ByteArray = chromaFull(v)

    private fun chromaFull(plane: ByteArray): ByteArray {
        val out = ByteArray(width * height)
        for (yy in 0 until height) for (xx in 0 until width) {
            val i = (yy / 2) * uvRowStride + (xx / 2) * uvPixelStride
            out[yy * width + xx] = if (i < plane.size) plane[i] else 128.toByte()
        }
        return out
    }

    companion object {
        fun read(file: File): HyuvFrame = DataInputStream(file.inputStream().buffered()).use { inp ->
            val magic = ByteArray(4).also { inp.readFully(it) }
            require(String(magic) == "HYUV") { "not a HYUV frame: ${file.name}" }
            val w = inp.readInt()
            val h = inp.readInt()
            val rot = inp.readInt()
            fun plane(): Triple<Int, Int, ByteArray> {
                val rs = inp.readInt()
                val ps = inp.readInt()
                val n = inp.readInt()
                return Triple(rs, ps, ByteArray(n).also { inp.readFully(it) })
            }
            val (yrs, _, yb) = plane()
            val (urs, ups, ub) = plane()
            val (_, _, vb) = plane()
            HyuvFrame(w, h, rot, yb, yrs, ub, vb, urs, ups)
        }

        /** Every frame under the test resources' "frames" folder, oldest first. */
        fun all(): List<Pair<String, HyuvFrame>> {
            val dir = File("src/test/resources/frames")
            return dir.listFiles { f -> f.name.endsWith(".hyuv") }.orEmpty().sortedBy { it.name }
                .map { it.name to read(it) }
        }
    }
}
