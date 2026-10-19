package com.haraan.partner.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics

/**
 * What went wrong with a load, told in the partner's terms rather than the
 * server's. "Unauthorized" is an HTTP word; what happened is that this phone
 * was signed out, and the only fix is to sign in again — pulling down won't.
 */
enum class Problem { SignedOut, Offline, Server, Other }

/** Who is signed in on this phone, and the way back in. Set by the console. */
data class SessionDoor(
    val name: String?,
    val role: String,
    val signInAgain: () -> Unit,
)

val LocalSessionDoor = staticCompositionLocalOf<SessionDoor?> { null }

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Blue = Color(0xFF2563EB)
private val Strap = Color(0xFF1E3A8A)
private val Metal = Color(0xFF94A3B8)
private val MetalDark = Color(0xFF64748B)
private val Page = Color(0xFFF6F8FC)

@Composable
fun ProblemState(problem: Problem, onRetry: (() -> Unit)?, detail: String? = null) {
    val view = LocalView.current
    val door = LocalSessionDoor.current
    val signedOut = problem == Problem.SignedOut && door != null
    Column(
        Modifier
            .fillMaxSize()
            .background(Page)
            // Scrollable so the screen still sits inside a pull-to-refresh.
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 32.dp, vertical = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Spacer(Modifier.height(40.dp))
        if (signedOut) LoosePass(door!!.name, door.role) else RestingBall()
        Spacer(Modifier.height(if (signedOut) 20.dp else 28.dp))
        val (title, body) = when (problem) {
            Problem.SignedOut -> "You've been signed out" to
                "Your sign-in on this phone has ended. Sign in again to see today's bookings."
            Problem.Offline -> "No internet on this phone" to
                "Check mobile data or Wi-Fi. Your bookings are safe and will show when you're back."
            Problem.Server -> "Haraan didn't answer" to
                "Our server had a problem for a moment. Nothing on your side is lost."
            Problem.Other -> "This didn't load" to
                (detail?.takeIf { it.readsHuman() }
                    ?: "Try again in a moment. If it keeps happening, message Haraan support from the menu.")
        }
        Text(title, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink, textAlign = TextAlign.Center)
        Spacer(Modifier.height(8.dp))
        Text(
            body, fontSize = 14.sp, lineHeight = 20.sp, color = Muted, textAlign = TextAlign.Center,
            modifier = Modifier.widthIn(max = 300.dp),
        )
        Spacer(Modifier.height(24.dp))
        when {
            signedOut -> Button(
                onClick = { Haptics.tick(view); door!!.signInAgain() },
                colors = ButtonDefaults.buttonColors(containerColor = Blue),
                shape = RoundedCornerShape(14.dp),
                modifier = Modifier.heightIn(min = 50.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 28.dp),
            ) { Text("Sign in again", fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
            onRetry != null -> OutlinedButton(
                onClick = { Haptics.tick(view); onRetry() },
                border = BorderStroke(1.dp, Blue.copy(alpha = 0.35f)),
                shape = RoundedCornerShape(14.dp),
                modifier = Modifier.heightIn(min = 48.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 26.dp),
            ) { Text("Try again", color = Blue, fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
        }
        Spacer(Modifier.height(80.dp))
    }
}

/**
 * The partner's own staff pass, come loose. A dual-clip lanyard: the left clip
 * still holds, the right one is open, so the pass has dropped onto one corner and
 * swings there before it settles. The name on it is the signed-in partner's.
 */
@Composable
private fun LoosePass(name: String?, role: String) {
    val swing = remember { Animatable(-6f) }
    LaunchedEffect(Unit) {
        // Let go from near level; a low-damped spring swings it past and back.
        swing.animateTo(17f, spring(dampingRatio = 0.22f, stiffness = 40f))
    }
    val sway by rememberInfiniteTransition(label = "hook").animateFloat(
        -4f, 4f, infiniteRepeatable(tween(1400, easing = LinearEasing), RepeatMode.Reverse), label = "sway",
    )

    val cardW = 112.dp
    val cardH = 166.dp
    val hole = 11.dp
    Box(Modifier.size(width = 240.dp, height = 246.dp)) {
        // Straps behind the pass.
        Canvas(Modifier.fillMaxSize()) {
            val pivot = Offset(78.dp.toPx(), 64.dp.toPx())
            strap(Offset(40.dp.toPx(), -8.dp.toPx()), pivot)
            clip(pivot, closed = true, angle = 0f)
            val loose = Offset(176.dp.toPx(), 58.dp.toPx())
            rotate(sway, pivot = Offset(loose.x + 8.dp.toPx(), -8.dp.toPx())) {
                strap(Offset(184.dp.toPx(), -8.dp.toPx()), loose)
                clip(loose, closed = false, angle = 0f)
            }
        }
        // The pass hangs from its top-left hole, which sits exactly on the clip.
        Box(
            Modifier
                .offset(x = 78.dp - hole, y = 64.dp - hole)
                .size(cardW, cardH)
                .graphicsLayer {
                    rotationZ = swing.value
                    transformOrigin = TransformOrigin(hole / cardW, hole / cardH)
                }
                .shadow(6.dp, RoundedCornerShape(12.dp), ambientColor = Ink.copy(alpha = 0.18f), spotColor = Ink.copy(alpha = 0.22f))
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White),
        ) {
            PassFace(name, role)
            // Punched holes: the held one and the empty one.
            Canvas(Modifier.fillMaxSize()) {
                val h = hole.toPx()
                for (x in listOf(h, size.width - h)) {
                    drawRoundRect(
                        Color(0xFF0B1E5B), topLeft = Offset(x - 5.dp.toPx(), h - 2.5.dp.toPx()),
                        size = Size(10.dp.toPx(), 5.dp.toPx()),
                        cornerRadius = androidx.compose.ui.geometry.CornerRadius(3.dp.toPx()),
                    )
                }
            }
        }
    }
}

@Composable
private fun PassFace(name: String?, role: String) {
    val shown = name?.trim().orEmpty().ifBlank { "Partner" }
    Column(Modifier.fillMaxSize()) {
        Box(
            Modifier.fillMaxWidth().height(40.dp).background(Blue),
            contentAlignment = Alignment.BottomCenter,
        ) {
            Text(
                "HARAAN  PARTNER", color = Color.White.copy(alpha = 0.92f), fontSize = 7.5.sp,
                fontWeight = FontWeight.SemiBold, letterSpacing = 1.6.sp, modifier = Modifier.padding(bottom = 7.dp),
            )
        }
        Spacer(Modifier.height(12.dp))
        Box(
            Modifier.align(Alignment.CenterHorizontally).size(38.dp).clip(CircleShape)
                .background(Color(0xFFE8EFFE)).border(1.dp, Color(0xFFD3E0FD), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Text(shown.take(1).uppercase(), color = Blue, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(8.dp))
        Text(
            shown, color = Ink, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, maxLines = 1,
            overflow = TextOverflow.Ellipsis, textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp),
        )
        Text(role, color = Muted, fontSize = 9.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.weight(1f))
        // The stub at the foot: hatched, with what the pass now says about you.
        Box(Modifier.fillMaxWidth().height(26.dp), contentAlignment = Alignment.Center) {
            Canvas(Modifier.fillMaxSize()) {
                drawLine(
                    Color(0xFFCBD5E1), Offset(0f, 0f), Offset(size.width, 0f), 1.dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx())),
                )
                clipRect {
                    var x = -size.height
                    while (x < size.width) {
                        drawLine(Color(0xFFF1F5F9), Offset(x, size.height), Offset(x + size.height, 0f), 4.dp.toPx())
                        x += 9.dp.toPx()
                    }
                }
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(5.dp).clip(CircleShape).background(Color(0xFF94A3B8)))
                Spacer(Modifier.width(5.dp))
                Text("SIGNED OUT", color = Color(0xFF64748B), fontSize = 7.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.4.sp)
            }
        }
    }
}

private fun DrawScope.strap(from: Offset, to: Offset) {
    val w = 9.dp.toPx()
    drawLine(Strap, from, to, w, StrokeCap.Butt)
    // Woven edge and a centre stitch, so it reads as fabric, not a bar.
    drawLine(Color.White.copy(alpha = 0.28f), from, to, 1.dp.toPx(),
        pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx())))
}

/** A lobster clip: a ring on the strap and a hook that is shut, or sprung open. */
private fun DrawScope.clip(at: Offset, closed: Boolean, angle: Float) {
    val ringTop = at.y - 14.dp.toPx()
    drawRoundRect(
        Metal, topLeft = Offset(at.x - 6.dp.toPx(), ringTop - 4.dp.toPx()), size = Size(12.dp.toPx(), 7.dp.toPx()),
        cornerRadius = androidx.compose.ui.geometry.CornerRadius(2.dp.toPx()),
    )
    val body = Path().apply {
        moveTo(at.x, ringTop + 3.dp.toPx())
        lineTo(at.x, at.y + 2.dp.toPx())
    }
    drawPath(body, MetalDark, style = Stroke(3.dp.toPx(), cap = StrokeCap.Round))
    val r = 6.5.dp.toPx()
    if (closed) {
        drawCircle(Metal, r, Offset(at.x, at.y), style = Stroke(2.2.dp.toPx()))
    } else {
        // Sprung open: the gate swings out, leaving a gap in the hook.
        drawArc(
            Metal, 60f, 250f, false,
            topLeft = Offset(at.x - r, at.y + 2.dp.toPx() - r), size = Size(r * 2, r * 2),
            style = Stroke(2.2.dp.toPx(), cap = StrokeCap.Round),
        )
        drawLine(MetalDark, Offset(at.x + r * 0.5f, at.y + 2.dp.toPx() + r * 0.85f), Offset(at.x + r * 1.6f, at.y + r * 0.2f), 1.8.dp.toPx(), StrokeCap.Round)
    }
}

/** The venue's ball, stopped on the floor line: nothing is moving because nothing came back. */
@Composable
private fun RestingBall() {
    val ball = LocalRefreshBall.current
    Canvas(Modifier.size(width = 160.dp, height = 70.dp)) {
        val r = 22.dp.toPx()
        val floor = size.height - 8.dp.toPx()
        val c = Offset(size.width / 2f, floor - r)
        drawLine(Ink.copy(alpha = 0.10f), Offset(c.x - 70.dp.toPx(), floor), Offset(c.x + 70.dp.toPx(), floor), 1.dp.toPx(), StrokeCap.Round)
        drawOval(Ink.copy(alpha = 0.14f), topLeft = Offset(c.x - r * 1.4f, floor - r * 0.15f), size = Size(r * 2.8f, r * 0.3f))
        drawRefreshBall(ball, c, r, if (ball == RefreshBall.Shuttle) 72f else 18f)
    }
}

/** A server message fit to show a partner: a short sentence, not a stack trace or a route. */
private fun String.readsHuman(): Boolean =
    length in 4..140 && listOf("exception", "sqlstate", "route", "http", "stack", "null", "{", "<")
        .none { it in lowercase() }
