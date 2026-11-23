package com.haraan.app.ui.main

import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.vector.PathParser

/**
 * The mark for one "Good to know" line, drawn in a 24×24 space — the same paths as the web
 * venue page (gamehub-detail `$mvKnowIcon` / `$mvPicto`), so keep the two in step.
 * A prohibition is the real sign: the thing itself in ink (cigarette, bottle and glass,
 * fork and knife, paw, shoe) under a red ring and slash. A must is an amber alert, the
 * cancellation policy a clock, booking advice a calendar, anything else plain info.
 */
@Composable
fun RuleSign(rule: String, cancellation: String, modifier: Modifier = Modifier) {
  val sign = remember(rule, cancellation) { ruleSign(rule, cancellation) }
  Canvas(modifier) {
    val k = size.width / 24f
    scale(k, k, pivot = Offset.Zero) { drawSign(sign) }
  }
}

private enum class Sign { NO_SMOKE, NO_FOOD, NO_ALCOHOL, NO_PET, NO_SHOE, NO_OTHER, CANCEL, MUST, BOOK, INFO }

private fun ruleSign(rule: String, cancellation: String): Sign {
  val r = rule.trim().lowercase()
  fun has(vararg n: String) = n.any { it in r }
  val cancel = rule == cancellation || has("cancel", "refund")
  if (!cancel && (r.startsWith("no ") || has("not allowed", "prohibited", "banned", "don't", "do not"))) {
    return when {
      has("smok", "cigar", "tobacco", "gutka", "gutkha", "pan masala", "vap", "beedi", "bidi") -> Sign.NO_SMOKE
      has("food", "eat", "snack", "meal", "chew") -> Sign.NO_FOOD
      has("alcohol", "liquor", "beer", "wine", "drink", "booze", "drunk") -> Sign.NO_ALCOHOL
      has("pet", "dog", "animal") -> Sign.NO_PET
      has("shoe", "footwear", "spike", "stud", "boot", "chappal", "slipper", "heel") -> Sign.NO_SHOE
      else -> Sign.NO_OTHER
    }
  }
  return when {
    cancel -> Sign.CANCEL
    has("mandatory", "required", "must", "compulsory") -> Sign.MUST
    has("book", "reserv") -> Sign.BOOK
    else -> Sign.INFO
  }
}

private val Ink = Color(0xFF0F172A)
private val Red = Color(0xFFDC2626)
private val Blue = Color(0xFF2563EB)
private val Amber = Color(0xFFB45309)

private fun path(d: String) = PathParser().parsePathString(d).toPath()
private fun DrawScope.fill(d: String) = drawPath(path(d), Ink)
private fun DrawScope.ink(d: String, w: Float) = drawPath(path(d), Ink, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round))
private fun DrawScope.glyph(d: String, c: Color) = drawPath(path(d), c, style = Stroke(2f, cap = StrokeCap.Round, join = StrokeJoin.Round))
private fun DrawScope.dot(cx: Float, cy: Float) = drawCircle(Ink, 1.35f, Offset(cx, cy))

private fun DrawScope.drawSign(sign: Sign) {
  when (sign) {
    Sign.NO_SMOKE -> {
      fill("M4.5 13.2h10.3v2.6H4.5z"); fill("M15.5 13.2h1.3v2.6h-1.3z"); fill("M17.5 13.2h1.1v2.6h-1.1z")
      ink("M16.4 11.8c0-1.4 1.6-1.4 1.6-2.8s-1.6-1.4-1.6-2.8", 1.3f)
    }
    Sign.NO_ALCOHOL -> {
      fill("M8.3 5.5h1.8v2.7c.9.5 1.4 1.3 1.4 2.3v7.7H6.9v-7.7c0-1 .5-1.8 1.4-2.3z")
      fill("M13.4 10h4.6l-.4 2.6a1.9 1.9 0 0 1-3.8 0z")
      ink("M15.7 14.5v3.4M14.3 18h2.8", 1.2f)
    }
    Sign.NO_FOOD -> {
      ink("M8 6v3.6a1.6 1.6 0 0 0 3.2 0V6M9.6 6v12.5", 1.4f)
      fill("M16.2 6c-1.6.6-2.6 2.6-2.6 5.6h1.6v6.9h1.4V6z")
    }
    Sign.NO_PET -> {
      fill("M12 11.6c-2.2 0-4 2.6-4 4.3 0 1.3 1 1.9 2.1 1.9.8 0 1.2-.4 1.9-.4s1.1.4 1.9.4c1.1 0 2.1-.6 2.1-1.9 0-1.7-1.8-4.3-4-4.3z")
      dot(8.35f, 10.6f); dot(10.6f, 8f); dot(13.4f, 8f); dot(15.65f, 10.6f)
    }
    Sign.NO_SHOE -> {
      fill("M5.2 15.6V9.8h2.9l1.3 1.9c1.7.4 4.6.8 6.6 1.4 1.4.4 2.1 1.2 2.1 2.2v.3z")
      ink("M5.2 17.3h13", 1.3f)
    }
    Sign.NO_OTHER -> Unit
    Sign.CANCEL -> {
      drawCircle(Blue, 8f, Offset(12f, 13f), style = Stroke(2f))
      glyph("M12 9v4l2.5 2M9.5 2.5h5", Blue)
    }
    Sign.MUST -> glyph("M12 3.5 21.5 20h-19zM12 10v4.2M12 17.2v.01", Amber)
    Sign.BOOK -> glyph("M6.5 5.5h11A1.5 1.5 0 0 1 19 7v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 18V7a1.5 1.5 0 0 1 1.5-1.5zM5 10h14M9 3.5v4M15 3.5v4", Blue)
    Sign.INFO -> {
      drawCircle(Blue, 9f, Offset(12f, 12f), style = Stroke(2f))
      glyph("M12 11v5.5M12 7.8v.01", Blue)
    }
  }
  if (sign.name.startsWith("NO_")) {
    // A white gap either side of the slash keeps the thing under it readable.
    drawLine(Color.White, Offset(4.9f, 4.9f), Offset(19.1f, 19.1f), 3.6f)
    drawCircle(Red, 10f, Offset(12f, 12f), style = Stroke(2.2f))
    drawLine(Red, Offset(4.9f, 4.9f), Offset(19.1f, 19.1f), 2.2f)
  }
}
