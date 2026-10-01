package com.haraan.partner.ui.payouts

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.rotate

/*
 * The Payouts screen's illustrations, drawn in code rather than shipped as
 * images: they stay sharp at any density, cost nothing in APK size, and take
 * their colours from the caller so they sit in either the navy hero or a
 * white card. Each one draws inside a square-ish box; size it from outside.
 */

/**
 * A wallet with a rupee coin tucked into its mouth: the balance hero's
 * watermark. Drawn in [tint] at whatever alpha the caller gives it, so it
 * reads as texture behind the figure, not a picture competing with it.
 */
@Composable
fun WalletArt(modifier: Modifier, tint: Color) {
    val t = rememberInfiniteTransition(label = "wallet")
    // The coin bobs a few pixels in and out of the wallet, slowly enough to
    // notice only on a second look.
    val bob by t.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(2400, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "bob",
    )
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val stroke = w * 0.035f

        // Coin, behind the wallet's front so it looks tucked in.
        val coinR = w * 0.17f
        val coinC = Offset(w * 0.62f, h * 0.30f - bob * h * 0.05f)
        drawCircle(tint, coinR, coinC)
        drawCircle(Color.Black.copy(alpha = 0.18f), coinR * 0.78f, coinC, style = Stroke(stroke * 0.6f))
        drawRupee(coinC, coinR * 0.95f, Color.Black.copy(alpha = 0.28f), stroke * 0.7f)

        // Wallet body.
        val bodyTop = h * 0.36f
        val body = Size(w * 0.86f, h * 0.56f)
        val bodyTl = Offset(w * 0.07f, bodyTop)
        drawRoundRect(tint, bodyTl, body, CornerRadius(w * 0.09f))
        // Back flap peeking above the body.
        drawRoundRect(
            tint.copy(alpha = tint.alpha * 0.55f),
            Offset(w * 0.12f, bodyTop - h * 0.08f),
            Size(w * 0.70f, h * 0.16f),
            CornerRadius(w * 0.06f),
        )
        // Clasp tab on the right edge.
        val tabH = h * 0.18f
        val tabTl = Offset(w * 0.62f, bodyTop + body.height / 2f - tabH / 2f)
        drawRoundRect(Color.Black.copy(alpha = 0.16f), tabTl, Size(w * 0.31f, tabH), CornerRadius(tabH / 2f))
        drawCircle(tint, tabH * 0.22f, Offset(tabTl.x + tabH * 0.55f, tabTl.y + tabH / 2f))
        // Stitch line along the top of the body.
        drawLine(
            Color.Black.copy(alpha = 0.14f),
            Offset(bodyTl.x + w * 0.08f, bodyTop + h * 0.07f),
            Offset(bodyTl.x + body.width - w * 0.08f, bodyTop + h * 0.07f),
            strokeWidth = stroke * 0.5f,
            cap = StrokeCap.Round,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(stroke * 1.4f, stroke * 1.2f)),
        )
    }
}

/**
 * A small bank building with a UPI-style arrow badge: the "add a settlement
 * account" prompt. [accent] fills the building, [soft] is the plinth behind it.
 */
@Composable
fun BankArt(modifier: Modifier, accent: Color, soft: Color) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        drawCircle(soft, w * 0.48f, Offset(w * 0.5f, h * 0.52f))

        // Roof.
        val roof = Path().apply {
            moveTo(w * 0.18f, h * 0.40f)
            lineTo(w * 0.50f, h * 0.18f)
            lineTo(w * 0.82f, h * 0.40f)
            close()
        }
        drawPath(roof, accent)
        drawCircle(Color.White, w * 0.045f, Offset(w * 0.5f, h * 0.32f))
        // Columns.
        val colW = w * 0.075f
        listOf(0.27f, 0.42f, 0.58f, 0.73f).forEach { cx ->
            drawRoundRect(
                accent.copy(alpha = 0.85f),
                Offset(w * cx - colW / 2f, h * 0.44f),
                Size(colW, h * 0.26f),
                CornerRadius(colW / 3f),
            )
        }
        // Base steps.
        drawRoundRect(accent, Offset(w * 0.18f, h * 0.71f), Size(w * 0.64f, h * 0.07f), CornerRadius(w * 0.02f))
        drawRoundRect(accent.copy(alpha = 0.7f), Offset(w * 0.13f, h * 0.79f), Size(w * 0.74f, h * 0.06f), CornerRadius(w * 0.02f))

        // UPI-ish badge: two forward chevrons on a white disc, bottom right.
        val bc = Offset(w * 0.80f, h * 0.78f)
        val br = w * 0.15f
        drawCircle(Color.White, br, bc)
        drawCircle(accent.copy(alpha = 0.18f), br, bc, style = Stroke(w * 0.02f))
        val s = br * 0.42f
        listOf(-0.32f, 0.28f).forEachIndexed { i, dx ->
            val c = if (i == 0) Color(0xFF16A34A) else Color(0xFFF59E0B)
            val p = Path().apply {
                moveTo(bc.x + br * dx - s * 0.35f, bc.y - s)
                lineTo(bc.x + br * dx + s * 0.55f, bc.y)
                lineTo(bc.x + br * dx - s * 0.35f, bc.y + s)
                close()
            }
            drawPath(p, c)
        }
    }
}

/**
 * The dotted road a payout travels along, with a coin moving down it: the
 * empty-history illustration. Draw it full width behind three evenly spaced
 * step badges; [stops] tells it where their centres sit (0..1 across the
 * width) so the road meets them exactly.
 */
@Composable
fun PayoutRoadArt(modifier: Modifier, road: Color, coin: Color, stops: List<Float>) {
    val t = rememberInfiniteTransition(label = "road")
    val p by t.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(3200, easing = LinearEasing)),
        label = "coin",
    )
    Canvas(modifier) {
        if (stops.size < 2) return@Canvas
        val y = size.height / 2f
        val x0 = size.width * stops.first()
        val x1 = size.width * stops.last()
        val dash = size.height * 0.10f
        drawLine(
            road,
            Offset(x0, y),
            Offset(x1, y),
            strokeWidth = size.height * 0.045f,
            cap = StrokeCap.Round,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(dash, dash * 0.9f)),
        )
        // The coin eases in at the start and fades out on arrival so the loop
        // has no visible jump back to the beginning.
        val x = x0 + (x1 - x0) * p
        val fade = when {
            p < 0.12f -> p / 0.12f
            p > 0.88f -> (1f - p) / 0.12f
            else -> 1f
        }
        val r = size.height * 0.11f
        drawCircle(coin.copy(alpha = 0.25f * fade), r * 1.8f, Offset(x, y))
        drawCircle(coin.copy(alpha = fade), r, Offset(x, y))
        drawRupee(Offset(x, y), r * 0.95f, Color.White.copy(alpha = fade), r * 0.22f)
    }
}

/**
 * The card-face texture for the balance hero: fine diagonal pinstripes and a
 * soft light band that sweeps across every few seconds, like a premium card
 * catching the light. Sits between the gradient and the content.
 */
@Composable
fun CardSheenArt(modifier: Modifier) {
    val t = rememberInfiniteTransition(label = "sheen")
    val sweep by t.animateFloat(
        initialValue = -0.6f,
        targetValue = 2.2f,
        // Most of the cycle is spent off the card, so the sweep is an occasional
        // glint rather than a constant shimmer.
        animationSpec = infiniteRepeatable(tween(5200, easing = FastOutSlowInEasing)),
        label = "sweep",
    )
    Canvas(modifier) {
        val gap = 14f
        val line = Color.White.copy(alpha = 0.035f)
        clipRect {
            var x = -size.height
            while (x < size.width) {
                drawLine(line, Offset(x, size.height), Offset(x + size.height, 0f), strokeWidth = 1.2f)
                x += gap
            }
        }
        val cx = size.width * sweep
        rotate(20f, pivot = Offset(cx, size.height / 2f)) {
            drawRect(
                Brush.horizontalGradient(
                    listOf(Color.Transparent, Color.White.copy(alpha = 0.10f), Color.Transparent),
                    startX = cx - size.width * 0.18f,
                    endX = cx + size.width * 0.18f,
                ),
                topLeft = Offset(cx - size.width * 0.18f, -size.height),
                size = Size(size.width * 0.36f, size.height * 3f),
            )
        }
    }
}

/** A plain ₹ glyph built from strokes, so the art needs no font. */
private fun DrawScope.drawRupee(c: Offset, r: Float, color: Color, stroke: Float) {
    val l = c.x - r * 0.38f
    val rt = c.x + r * 0.38f
    val top = c.y - r * 0.50f
    val mid = c.y - r * 0.18f
    // Top bar and middle bar.
    drawLine(color, Offset(l, top), Offset(rt, top), stroke, StrokeCap.Round)
    drawLine(color, Offset(l, mid), Offset(rt, mid), stroke, StrokeCap.Round)
    // The bowl and the leg.
    val bowl = Path().apply {
        moveTo(l, top)
        cubicTo(rt + r * 0.05f, top, rt + r * 0.05f, c.y + r * 0.12f, l, c.y + r * 0.12f)
        lineTo(rt * 0.98f + l * 0.02f, c.y + r * 0.55f)
    }
    drawPath(bowl, color, style = Stroke(stroke, cap = StrokeCap.Round))
}
