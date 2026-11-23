package com.haraan.app.ui.main

import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.vector.PathParser

/**
 * A sport's own playing surface, drawn from above — the same art as the website's sport
 * cards (gamehub-detail `$vdCourtArt`), in the same 160×100 space so the two stay in step.
 * Cricket gets its mown stripes and pitch strip, nets get three netted lanes, football its
 * markings, and so on. Draw it at 16:10.
 */
@Composable
fun SportCourtArt(sport: String, modifier: Modifier = Modifier) {
  val kind = remember(sport) { courtKind(sport) }
  Canvas(modifier) {
    val k = size.width / 160f
    scale(k, k, pivot = Offset.Zero) { drawCourt(kind) }
  }
}

private enum class CourtKind { NETS, CRICKET, FOOTBALL, BASKETBALL, VOLLEYBALL, SWIM, PICKLEBALL, TENNIS, TABLE, BADMINTON, OTHER }

private fun courtKind(sport: String): CourtKind {
  val s = sport.lowercase()
  return when {
    "net" in s && "cricket" in s -> CourtKind.NETS
    "cricket" in s -> CourtKind.CRICKET
    "football" in s || "soccer" in s || "futsal" in s -> CourtKind.FOOTBALL
    "basket" in s -> CourtKind.BASKETBALL
    "volley" in s -> CourtKind.VOLLEYBALL
    "swim" in s || "pool" in s -> CourtKind.SWIM
    "pickle" in s -> CourtKind.PICKLEBALL
    "tennis" in s && "table" !in s -> CourtKind.TENNIS
    "table" in s -> CourtKind.TABLE
    "badminton" in s || "squash" in s || "padel" in s -> CourtKind.BADMINTON
    else -> CourtKind.OTHER
  }
}

private fun hex(v: Long) = Color(v or 0xFF000000)
private val White = Color.White
private val Ink = hex(0x0F172A)
private val Grass = hex(0x2F8F55)
private val Stripe = hex(0x36A060)
private val Lane = hex(0x3FA466)
private val Clay = hex(0xD8BF8A)
private val Chalk = hex(0xE2E8F0)

private fun DrawScope.box(x: Float, y: Float, w: Float, h: Float, c: Color, r: Float = 0f) =
  drawRoundRect(c, Offset(x, y), Size(w, h), CornerRadius(r, r))

private fun DrawScope.outline(x: Float, y: Float, w: Float, h: Float, c: Color = White, width: Float = 1.6f, r: Float = 0f, dash: FloatArray? = null, alpha: Float = 1f) =
  drawRoundRect(c, Offset(x, y), Size(w, h), CornerRadius(r, r), alpha = alpha,
    style = Stroke(width, pathEffect = dash?.let { PathEffect.dashPathEffect(it) }))

private fun DrawScope.line(d: String, c: Color = White, width: Float = 1.6f, alpha: Float = 1f, dash: FloatArray? = null, round: Boolean = false) =
  drawPath(PathParser().parsePathString(d).toPath(), c, alpha = alpha,
    style = Stroke(width, cap = if (round) StrokeCap.Round else StrokeCap.Butt, pathEffect = dash?.let { PathEffect.dashPathEffect(it) }))

private fun DrawScope.fillPath(d: String, c: Color, alpha: Float = 1f) =
  drawPath(PathParser().parsePathString(d).toPath(), c, alpha = alpha)

/** Outer apron + inner playing surface, the web's `$bg(outer, inner)`. */
private fun DrawScope.ground(outer: Color, inner: Color) {
  box(0f, 0f, 160f, 100f, outer, 10f)
  box(14f, 12f, 132f, 76f, inner, 2f)
}

/** Net across the middle with its two posts, the web's `$net`. */
private fun DrawScope.centreNet() {
  line("M80 6v88", Ink, 2f, alpha = .55f)
  drawCircle(Ink, 2.2f, Offset(80f, 6f), alpha = .6f)
  drawCircle(Ink, 2.2f, Offset(80f, 94f), alpha = .6f)
}

private fun DrawScope.drawCourt(kind: CourtKind) {
  when (kind) {
    CourtKind.NETS -> {
      box(0f, 0f, 160f, 100f, Grass, 10f)
      listOf(8f, 39f, 70f).forEach { y ->
        box(10f, y, 140f, 22f, Lane, 2f)
        box(22f, y + 7f, 116f, 8f, Clay, 1f)
        line("M30 ${y + 5}v12M130 ${y + 5}v12", width = 1.4f)
        line("M10 ${y}h140M10 ${y + 22}h140", Chalk, 1f, alpha = .7f, dash = floatArrayOf(2f, 2f))
      }
    }
    CourtKind.CRICKET -> {
      box(0f, 0f, 160f, 100f, Grass, 10f)
      listOf(8f, 32f, 56f, 80f, 104f, 128f).forEach { x -> box(x, 8f, 12f, 84f, Stripe) }
      outline(8f, 8f, 144f, 84f, Chalk, 1f, 4f, floatArrayOf(3f, 2.5f), .8f)
      box(44f, 42f, 72f, 16f, Clay, 1.5f)
      line("M52 40v20M108 40v20")
      line("M47 46v8M113 46v8", hex(0x7C2D12), 2f, round = true)
    }
    CourtKind.FOOTBALL -> {
      box(0f, 0f, 160f, 100f, Grass, 10f)
      listOf(12f, 34f, 56f, 78f, 100f, 122f, 133f).forEach { x -> box(x, 10f, 11f, 80f, Stripe) }
      outline(12f, 10f, 136f, 80f, r = 1f)
      line("M80 10v80")
      drawCircle(White, 12f, Offset(80f, 50f), style = Stroke(1.6f))
      line("M12 30h20v40H12M148 30h-20v40h20M12 40h8v20h-8M148 40h-8v20h8")
      line("M12 44H8v12h4M148 44h4v12h-4")
    }
    CourtKind.BASKETBALL -> {
      ground(hex(0xC9874F), hex(0xE7B07A))
      outline(14f, 12f, 132f, 76f)
      line("M80 12v76")
      drawCircle(White, 10f, Offset(80f, 50f), style = Stroke(1.6f))
      fillPath("M14 38h26v24H14zM146 38h-26v24h26z", hex(0xC2410C), .25f)
      line("M14 38h26v24H14M146 38h-26v24h26")
      line("M14 20a36 36 0 0 1 0 60M146 20a36 36 0 0 0 0 60")
      drawCircle(hex(0xEA580C), 2.5f, Offset(20f, 50f), style = Stroke(1.6f))
      drawCircle(hex(0xEA580C), 2.5f, Offset(140f, 50f), style = Stroke(1.6f))
    }
    CourtKind.VOLLEYBALL -> {
      ground(hex(0x3B82C4), hex(0xE8834A))
      outline(14f, 12f, 132f, 76f)
      line("M58 12v76M102 12v76")
      centreNet()
    }
    CourtKind.SWIM -> {
      box(0f, 0f, 160f, 100f, hex(0xCBD5E1), 10f)
      box(10f, 10f, 140f, 80f, hex(0x38A3D6), 4f)
      listOf(26f, 42f, 58f, 74f).forEach { y -> line("M14 ${y}h132", width = 1.3f, dash = floatArrayOf(3f, 3f)) }
      line("M22 18c6 3 10 3 16 0s10-3 16 0", width = 1f, alpha = .5f)
    }
    CourtKind.PICKLEBALL -> {
      ground(hex(0x7FA0C8), hex(0x2F6FD0))
      outline(14f, 12f, 132f, 76f)
      box(66f, 12f, 28f, 76f, hex(0x1E5BB8))
      line("M66 12v76M94 12v76M14 50h52M94 50h52")
      centreNet()
    }
    CourtKind.TENNIS -> {
      ground(hex(0x4E9A6B), hex(0x3E6FB5))
      outline(14f, 12f, 132f, 76f)
      line("M14 22h132M14 78h132M46 22v56M114 22v56M46 50h68")
      centreNet()
    }
    CourtKind.TABLE -> {
      box(0f, 0f, 160f, 100f, hex(0xE2E8F0), 10f)
      box(22f, 20f, 116f, 60f, hex(0x1E4E8C), 2f)
      outline(22f, 20f, 116f, 60f, r = 2f)
      line("M22 50h116", width = .8f)
      line("M80 14v72", Ink, 2f, alpha = .55f)
    }
    CourtKind.BADMINTON -> {
      ground(hex(0x1F6E4E), hex(0x2E8B57))
      outline(14f, 12f, 132f, 76f)
      line("M14 18h132M14 82h132M20 12v76M140 12v76M64 12v76M96 12v76M14 50h50M96 50h50")
      centreNet()
    }
    CourtKind.OTHER -> {
      ground(hex(0x94A3B8), hex(0x64748B))
      outline(14f, 12f, 132f, 76f)
      line("M80 12v76")
      drawCircle(White, 12f, Offset(80f, 50f), style = Stroke(1.6f))
    }
  }
}
