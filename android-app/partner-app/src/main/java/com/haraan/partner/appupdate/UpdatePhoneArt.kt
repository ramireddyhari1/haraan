package com.haraan.partner.appupdate

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.RoundRect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.drawText
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.unit.sp
import kotlin.math.PI
import kotlin.math.sin

/**
 * The update sheet's picture: a phone with the new version arriving in it, driven by the real
 * update state, so it is never decoration running on its own.
 *
 *  - Offer: the version card floats just above the phone, a small arrow pointing in.
 *  - Downloading: the card sinks into the screen and the screen fills with blue to the same
 *    fraction as the bytes. No byte count yet: the water just rolls gently.
 *  - Checking / installing: a light sweeps down the screen.
 *  - Ready / restart: full, the card settled in the middle, a check on it.
 *  - Failed: the badge turns red with a bar instead of an arrow.
 *
 * Drawn in a 220×170 box and scaled to fit.
 * NB: the same file lives in both apps (appupdate/), only the package differs; edit both.
 */
@Composable
internal fun UpdatePhoneArt(s: UpdateState, modifier: Modifier = Modifier) {
    val level = when (s.phase) {
        UpdatePhase.OFFER, UpdatePhase.PERMISSION -> if (s.bytes > 0) 1f else 0f
        UpdatePhase.DOWNLOADING -> s.fraction ?: 0.08f
        UpdatePhase.FAILED -> s.fraction ?: 0f
        else -> 1f
    }
    val fill by animateFloatAsState(level, tween(420, easing = LinearOutSlowInEasing), label = "fill")
    val inside by animateFloatAsState(
        if (s.phase == UpdatePhase.OFFER || s.phase == UpdatePhase.FAILED && level == 0f) 0f else 0.35f + 0.65f * level,
        tween(520, easing = LinearOutSlowInEasing),
        label = "inside",
    )
    val clock = rememberInfiniteTransition(label = "phone")
    val bob by clock.animateFloat(
        0f, 2f * PI.toFloat(),
        infiniteRepeatable(tween(3200, easing = LinearEasing), RepeatMode.Restart),
        label = "bob",
    )
    val sweep by clock.animateFloat(
        0f, 1f,
        infiniteRepeatable(tween(1300, easing = LinearEasing), RepeatMode.Restart),
        label = "sweep",
    )
    val sweeping = s.phase == UpdatePhase.CHECKING || s.phase == UpdatePhase.INSTALLING
    val done = s.phase == UpdatePhase.READY || s.phase == UpdatePhase.RESTART || s.phase == UpdatePhase.INSTALLING
    val failed = s.phase == UpdatePhase.FAILED
    val idle = s.phase == UpdatePhase.OFFER || s.phase == UpdatePhase.PERMISSION

    val measurer = rememberTextMeasurer()
    val label = s.info.latestVersion?.let { "v$it" } ?: "New"
    val labelLayout = measurer.measure(label, TextStyle(fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Ink))
    val monoLayout = measurer.measure("H", TextStyle(fontSize = 13.sp, fontWeight = FontWeight.ExtraBold, color = Color.White))
    val appLayout = measurer.measure(AppUpdater.appLabel, TextStyle(fontSize = 8.5.sp, fontWeight = FontWeight.Medium, color = Muted))

    Canvas(modifier) {
        val k = minOf(size.width / 220f, size.height / 170f)
        val ox = (size.width - 220f * k) / 2f
        val oy = (size.height - 170f * k) / 2f
        fun p(x: Float, y: Float) = Offset(ox + x * k, oy + y * k)

        // Floor shadow.
        drawOval(Ink.copy(alpha = 0.07f), topLeft = p(62f, 158f), size = Size(96f * k, 9f * k))

        // ── Phone: a side edge for thickness, the body, the screen.
        val bodyL = 67f; val bodyT = 26f; val bodyW = 86f; val bodyH = 136f
        drawRoundRect(Color(0xFF1E293B), topLeft = p(bodyL + 3.5f, bodyT + 2.5f), size = Size(bodyW * k, bodyH * k), cornerRadius = CornerRadius(15f * k))
        drawRoundRect(Ink, topLeft = p(bodyL, bodyT), size = Size(bodyW * k, bodyH * k), cornerRadius = CornerRadius(15f * k))
        // Side buttons.
        drawRoundRect(Color(0xFF334155), topLeft = p(bodyL + bodyW + 1.5f, bodyT + 30f), size = Size(2.2f * k, 16f * k), cornerRadius = CornerRadius(1f * k))
        drawRoundRect(Color(0xFF334155), topLeft = p(bodyL + bodyW + 1.5f, bodyT + 52f), size = Size(2.2f * k, 10f * k), cornerRadius = CornerRadius(1f * k))

        val sL = bodyL + 4f; val sT = bodyT + 4f; val sW = bodyW - 8f; val sH = bodyH - 8f
        val screen = Path().apply {
            addRoundRect(RoundRect(p(sL, sT).x, p(sL, sT).y, p(sL + sW, sT + sH).x, p(sL + sW, sT + sH).y, CornerRadius(11.5f * k)))
        }
        drawPath(screen, ScreenBg)
        clipPath(screen) {
            // The water: rises with the download, a gentle roll on its surface.
            if (fill > 0.001f) {
                val top = sT + sH * (1f - fill)
                val amp = if (fill >= 0.999f) 0f else 2.2f
                val water = Path().apply {
                    moveTo(p(sL, sT + sH).x, p(sL, sT + sH).y)
                    var x = sL
                    lineTo(p(x, top).x, p(x, top + amp * sin(bob)).y)
                    while (x <= sL + sW) {
                        val y = top + amp * sin(bob + (x - sL) / sW * 2f * PI.toFloat())
                        lineTo(p(x, y).x, p(x, y).y)
                        x += 2f
                    }
                    lineTo(p(sL + sW, sT + sH).x, p(sL + sW, sT + sH).y)
                    close()
                }
                drawPath(water, (if (failed) Red else Blue).copy(alpha = if (fill >= 0.999f && !failed) 0.18f else 0.14f))
            }
            // Checking / installing: a band of light running down the screen.
            if (sweeping) {
                val y = sT - 16f + (sH + 32f) * sweep
                drawRect(Color.White.copy(alpha = 0.55f), topLeft = p(sL, y), size = Size(sW * k, 8f * k))
            }
            // The app on a home screen: its tile and name, where the new version lands.
            val tile = 26f
            val tx = sL + sW / 2f - tile / 2f
            val ty = sT + sH * 0.64f - tile / 2f
            drawRoundRect(Blue, topLeft = p(tx, ty), size = Size(tile * k, tile * k), cornerRadius = CornerRadius(7.5f * k))
            drawText(monoLayout, topLeft = Offset(p(sL + sW / 2f, 0f).x - monoLayout.size.width / 2f, p(0f, ty + tile / 2f).y - monoLayout.size.height / 2f))
            drawText(appLayout, topLeft = Offset(p(sL + sW / 2f, 0f).x - appLayout.size.width / 2f, p(0f, ty + tile + 3f).y))
        }
        // Camera punch-hole.
        drawCircle(Ink, 2.4f * k, p(sL + sW / 2f, sT + 6f))

        // ── The version card: floats above the phone, sinks into the screen as it arrives.
        val cardW = 92f; val cardH = 34f
        val floatY = 4f + (if (idle) 2.6f * sin(bob) else 0f)
        val restY = sT + sH * 0.30f - cardH / 2f
        val cy = floatY + (restY - floatY) * inside
        val cx = 110f - cardW / 2f
        drawRoundRect(Ink.copy(alpha = 0.10f), topLeft = p(cx + 1.5f, cy + 3f), size = Size(cardW * k, cardH * k), cornerRadius = CornerRadius(10f * k))
        drawRoundRect(Color.White, topLeft = p(cx, cy), size = Size(cardW * k, cardH * k), cornerRadius = CornerRadius(10f * k))
        drawRoundRect(Line, topLeft = p(cx, cy), size = Size(cardW * k, cardH * k), cornerRadius = CornerRadius(10f * k), style = Stroke(1f * k))

        // Badge: arrow while it's coming, check once it's here, a bar if it failed.
        val badge = p(cx + 18f, cy + cardH / 2f)
        drawCircle(if (failed) Red else Blue, 10.5f * k, badge)
        val w = 1.9f * k
        when {
            failed -> drawLine(Color.White, p(cx + 18f, cy + cardH / 2f - 5f), p(cx + 18f, cy + cardH / 2f + 1.5f), w, StrokeCap.Round).also {
                drawCircle(Color.White, 1.2f * k, p(cx + 18f, cy + cardH / 2f + 5f))
            }
            done -> drawPath(
                Path().apply {
                    moveTo(p(cx + 13.5f, cy + cardH / 2f + 0.5f).x, p(cx + 13.5f, cy + cardH / 2f + 0.5f).y)
                    lineTo(p(cx + 17f, cy + cardH / 2f + 4f).x, p(cx + 17f, cy + cardH / 2f + 4f).y)
                    lineTo(p(cx + 23f, cy + cardH / 2f - 3.5f).x, p(cx + 23f, cy + cardH / 2f - 3.5f).y)
                },
                Color.White, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round),
            )
            else -> drawArrow(p(cx + 18f, cy + cardH / 2f), k, w)
        }
        drawText(
            labelLayout,
            topLeft = Offset(p(cx + 34f, 0f).x, badge.y - labelLayout.size.height / 2f),
        )
    }
}

private fun DrawScope.drawArrow(c: Offset, k: Float, w: Float) {
    drawLine(Color.White, c + Offset(0f, -5f * k), c + Offset(0f, 4.5f * k), w, StrokeCap.Round)
    drawPath(
        Path().apply {
            moveTo(c.x - 4f * k, c.y + 0.8f * k)
            lineTo(c.x, c.y + 4.8f * k)
            lineTo(c.x + 4f * k, c.y + 0.8f * k)
        },
        Color.White, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round),
    )
}

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Line = Color(0xFFE2E8F0)
private val Blue = Color(0xFF2563EB)
private val Red = Color(0xFFDC2626)
private val ScreenBg = Color(0xFFF1F5F9)
