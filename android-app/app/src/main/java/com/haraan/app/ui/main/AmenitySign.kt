package com.haraan.app.ui.main

import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathFillType
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.vector.PathParser

/**
 * An amenity drawn as the sign people see at a ground: the blue P, the man and woman, a
 * CCTV camera, a stadium chair, a floodlight tower, a glass of water. Solid shapes in ink,
 * with the real sign's colour where it has one. Same keyword buckets and the same paths as
 * the web venue page (gamehub-detail `$mvAmenityIcon`), so keep the two in step. 24×24 space.
 */
@Composable
fun AmenitySign(amenity: String, modifier: Modifier = Modifier) {
  val kind = remember(amenity) { amenityKind(amenity) }
  Canvas(modifier) {
    val k = size.width / 24f
    scale(k, k, pivot = Offset.Zero) { drawAmenity(kind) }
  }
}

private enum class Amenity { WIFI, PARKING, WASHROOM, SHOWER, CHANGING, CAFE, FOOD, WATER, LIGHTS, SEATING, AC, FIRST_AID, CCTV, EQUIPMENT, OTHER }

private fun amenityKind(amenity: String): Amenity {
  val a = amenity.trim().lowercase()
  fun has(vararg n: String) = n.any { it in a }
  return when {
    has("wifi", "wi-fi", "internet") -> Amenity.WIFI
    has("park") -> Amenity.PARKING
    has("wash", "toilet", "restroom", "rest room") -> Amenity.WASHROOM
    has("shower") -> Amenity.SHOWER
    has("chang", "locker") -> Amenity.CHANGING
    has("cafe", "coffee", "canteen") -> Amenity.CAFE
    has("food", "restaurant", "kitchen") -> Amenity.FOOD
    has("water", "drink") -> Amenity.WATER
    has("light", "flood") -> Amenity.LIGHTS
    has("seat", "gallery", "chair", "bench", "stand") -> Amenity.SEATING
    a == "ac" || has("a/c", "air con", "aircon", "air-con", "air condition", "cool") -> Amenity.AC
    has("first aid", "first-aid", "medical", "medic") -> Amenity.FIRST_AID
    has("cctv", "secur", "guard", "safe") -> Amenity.CCTV
    has("equip", "gear", "gym", "kit") -> Amenity.EQUIPMENT
    else -> Amenity.OTHER
  }
}

private val Ink = Color(0xFF1E3A8A)
private val Blue = Color(0xFF2563EB)
private val Red = Color(0xFFDC2626)

private fun DrawScope.f(d: String, c: Color = Ink, evenOdd: Boolean = false) {
  val p = PathParser().parsePathString(d).toPath()
  if (evenOdd) p.fillType = PathFillType.EvenOdd
  drawPath(p, c)
}
private fun DrawScope.s(d: String, w: Float, c: Color = Ink) =
  drawPath(PathParser().parsePathString(d).toPath(), c, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round))
private fun DrawScope.c(cx: Float, cy: Float, r: Float, col: Color = Ink) = drawCircle(col, r, Offset(cx, cy))

private const val ROUND_SQUARE = "M6 3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3z"

private fun DrawScope.drawAmenity(kind: Amenity) {
  when (kind) {
    Amenity.WIFI -> { s("M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a14 14 0 0 1 20 0", 2f); c(12f, 19.5f, 1.6f) }
    Amenity.PARKING -> {
      f(ROUND_SQUARE, Blue)
      f("M9 6.5h4.2a3.7 3.7 0 0 1 0 7.4h-1.7v3.6H9zM11.5 8.8v2.8h1.6a1.4 1.4 0 0 0 0-2.8z", Color.White, evenOdd = true)
    }
    Amenity.WASHROOM -> {
      c(6.8f, 4.4f, 1.9f)
      f("M4.8 7.8h4a1.1 1.1 0 0 1 1.1 1.1v5.4H8.8v6.2H7.4v-5H6.2v5H4.8v-6.2H3.7V8.9a1.1 1.1 0 0 1 1.1-1.1z")
      c(17.2f, 4.4f, 1.9f)
      f("M15.8 7.8h2.8l2.5 7.2h-2.1v5.5h-1.4v-5.5h-.8v5.5h-1.4v-5.5h-2.1z")
      s("M12 3v18", 1.2f)
    }
    Amenity.SHOWER -> {
      s("M5 21V7a3 3 0 0 1 6 0v.5", 1.8f); f("M8 9.5a5 5 0 0 1 10 0z")
      c(10f, 13f, 1f, Blue); c(13f, 14f, 1f, Blue); c(16f, 13f, 1f, Blue); c(11.5f, 17f, 1f, Blue); c(14.5f, 17f, 1f, Blue)
    }
    Amenity.CHANGING -> s("M12 6.8a1.8 1.8 0 1 0-1.8-1.8M12 6.8v1.4L3.4 14.6c-.9.7-.4 2.1.7 2.1h15.8c1.1 0 1.6-1.4.7-2.1L12 8.2", 1.8f)
    Amenity.CAFE -> {
      f("M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z")
      s("M16 10.5h1.5a2.5 2.5 0 0 1 0 5H16M7 3.5c-.8 1 .8 1.8 0 3M11 3.5c-.8 1 .8 1.8 0 3", 1.6f)
      f("M3 20.5h14v1.2H3z")
    }
    Amenity.FOOD -> { s("M7 3v5a2 2 0 0 0 4 0V3M9 3v18", 1.8f); f("M17.5 3c-2 .8-3.3 3.3-3.3 7.2h2v10.8h1.8V3z") }
    Amenity.WATER -> {
      f("M12 2.2s2.7 3 2.7 4.8a2.7 2.7 0 0 1-5.4 0c0-1.8 2.7-4.8 2.7-4.8z", Blue)
      f("M7.7 14h8.6l-.6 6.1H8.3z", Blue)
      s("M6.5 10h11l-1.2 10.6a1 1 0 0 1-1 .9H8.7a1 1 0 0 1-1-.9z", 1.6f)
    }
    Amenity.LIGHTS -> {
      f("M5 2.5h14a1 1 0 0 1 1 1V8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1z")
      val lamp = Color(0xFFFDE68A)
      c(8f, 5.75f, 1.4f, lamp); c(12f, 5.75f, 1.4f, lamp); c(16f, 5.75f, 1.4f, lamp)
      s("M12 9v12M8.5 21h7", 1.8f)
      s("M7.5 11 5.5 14M16.5 11l2 3", 1.4f, Color(0xFFF59E0B))
    }
    Amenity.SEATING -> {
      f("M7 3h10a1 1 0 0 1 1 1v8H6V4a1 1 0 0 1 1-1z")
      f("M4.5 13h15a1 1 0 0 1 1 1v1.6h-17V14a1 1 0 0 1 1-1z")
      s("M6.5 15.6V21M17.5 15.6V21", 1.8f)
    }
    Amenity.AC -> s("M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9M9.5 4.5 12 7l2.5-2.5M9.5 19.5 12 17l2.5 2.5", 1.7f, Blue)
    Amenity.FIRST_AID -> {
      f(ROUND_SQUARE, Color.White)
      s("M6 3.8h12A2.2 2.2 0 0 1 20.2 6v12a2.2 2.2 0 0 1-2.2 2.2H6A2.2 2.2 0 0 1 3.8 18V6A2.2 2.2 0 0 1 6 3.8z", 1.2f, Red)
      f("M10 6.5h4v3.5h3.5v4H14v3.5h-4V14H6.5v-4H10z", Red)
    }
    Amenity.CCTV -> {
      f("M3 7.6 15.6 4.2l1.4 5.2L4.4 12.8z"); f("M17.4 5.6l2.8-.8 1 3.6-2.8.8z")
      c(6.3f, 9.2f, .9f, Color(0xFFEF4444))
      s("M9 11.6l1.1 4H5.5V21", 1.8f)
    }
    Amenity.EQUIPMENT -> f("M2 10h2v4H2zM4.8 7h3v10h-3zM16.2 7h3v10h-3zM20 10h2v4h-2zM7.8 11h8.4v2H7.8z")
    Amenity.OTHER -> s("M20 6 9 17l-5-5", 2f)
  }
}
