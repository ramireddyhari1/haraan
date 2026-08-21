package com.haraan.app.ui.main

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.graphics.Brush
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.layout
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.R
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.QrImage
import com.haraan.app.ui.pressable
import kotlin.math.roundToInt

/**
 * The booking confirmation, printed.
 *
 * A confirmation screen is the most reflexively generic surface in any app: a green
 * tick, a headline, a button. This one is a thermal slip fed out of a machine, because
 * that is what a venue booking physically IS at the desk — and because the moment is
 * worth staging.
 *
 * What sells it is the motion, not the paper texture:
 *  - the feed is **stepped**, 14 discrete jumps, never a smooth slide. Real print heads
 *    advance in increments; a smooth reveal reads as a CSS transition;
 *  - a haptic **tick fires on every step**, so the printer chatters in the hand, then one
 *    heavier confirm when the paper is out;
 *  - the slip is revealed by *layout height*, not by translation, so the content stays
 *    pinned to the head and emerges rather than sliding past a window;
 *  - the torn bottom edge is a real zigzag path, so the paper ends where a tear would.
 *
 * The QR is genuine: `haraan:ticket:<code>` against the booking's own `ticket_code`, the
 * same payload the check-in scanner resolves. When the backend hasn't returned a code we
 * print the slip WITHOUT a QR rather than encode a placeholder — a QR that scans to
 * nothing at the gate is worse than no QR at all.
 */
@Composable
fun PrintedBookingSlip(
    venueName: String,
    sport: String,
    dateLabel: String,
    timeLabel: String,
    courtName: String?,
    durationHours: Int,
    totalLabel: String,
    ticketCode: String?,
    payAtVenue: Boolean,
    onDone: () -> Unit,
) {
    val view = LocalView.current
    val feed = remember { Animatable(0f) }
    var printed by remember { mutableStateOf(false) }
    // Drives the machine's buzz and status LED. Declared unconditionally (a transition
    // can't be made inside an `if`) and only read while the head is running.
    val printerRun = rememberInfiniteTransition(label = "printerRun")
    // The QR "develops" once the paper is clear of the head, so the eye lands on it last.
    val qrReveal = remember { Animatable(0f) }

    LaunchedEffect(Unit) {
        val steps = 14
        repeat(steps) { i ->
            feed.animateTo((i + 1) / steps.toFloat(), tween(70, easing = LinearEasing))
            view.performHapticFeedback(Feel.TICK)
        }
        printed = true
        view.performHapticFeedback(Feel.COMMIT)
        qrReveal.animateTo(1f, spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessLow))
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        // ── The billing machine ──────────────────────────────────────────────────
        // A bare slit wasn't enough: without a machine you can recognise, a growing
        // rectangle is just a growing rectangle. This is a counter-top thermal printer —
        // moulded body, the seam of the paper-roll lid, a status LED, and a recessed feed
        // slot with a tear bar. It BUZZES while it prints (a sub-pixel jitter) and the LED
        // pulses, so the machine is visibly working rather than just present.
        val machineBuzz by printerRun.animateFloat(
            initialValue = -0.6f,
            targetValue = 0.6f,
            animationSpec = infiniteRepeatable(tween(70, easing = LinearEasing), RepeatMode.Reverse),
            label = "printerBuzz",
        )
        val ledPulse by printerRun.animateFloat(
            initialValue = 0.35f,
            targetValue = 1f,
            animationSpec = infiniteRepeatable(tween(420, easing = LinearEasing), RepeatMode.Reverse),
            label = "printerLed",
        )
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(66.dp)
                .graphicsLayer {
                    // Only while the head is actually running.
                    translationY = if (printed) 0f else machineBuzz * density
                }
                .clip(RoundedCornerShape(topStart = 18.dp, topEnd = 18.dp, bottomStart = 7.dp, bottomEnd = 7.dp))
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFF39434F), Color(0xFF232B35), Color(0xFF12171D)),
                    ),
                ),
        ) {
            // Lid seam — the line the paper-roll cover closes along.
            Box(
                Modifier
                    .align(Alignment.TopCenter)
                    .padding(top = 15.dp)
                    .fillMaxWidth(0.62f)
                    .height(2.dp)
                    .clip(RoundedCornerShape(50))
                    .background(Color(0xFF4C586A)),
            )
            // Status light: green and breathing while printing, steady once done.
            Box(
                Modifier
                    .align(Alignment.TopEnd)
                    .padding(top = 12.dp, end = 16.dp)
                    .size(7.dp)
                    .clip(CircleShape)
                    .background(
                        Color(0xFF34D399).copy(alpha = if (printed) 1f else ledPulse),
                    ),
            )
            // Feed slot — recessed, with the tear bar's teeth along its lip.
            Column(
                modifier = Modifier.align(Alignment.BottomCenter),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Box(
                    Modifier
                        .fillMaxWidth(0.94f)
                        .height(8.dp)
                        .clip(RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp))
                        .background(Color(0xFF05070A)),
                )
                Canvas(
                    modifier = Modifier
                        .fillMaxWidth(0.94f)
                        .height(4.dp),
                ) {
                    val tooth = 9f
                    val path = Path().apply {
                        moveTo(0f, 0f)
                        var x = 0f
                        while (x < size.width) {
                            lineTo(x + tooth / 2f, size.height)
                            lineTo(x + tooth, 0f)
                            x += tooth
                        }
                        lineTo(size.width, 0f)
                        close()
                    }
                    drawPath(path, color = Color(0xFF6B7686))
                }
            }
        }

        // ── The paper ────────────────────────────────────────────────────────────
        // Narrower than the machine, because a roll sits inside its housing — matching
        // widths is the giveaway that this is two rectangles rather than a device.
        Column(
            modifier = Modifier
                .fillMaxWidth(0.92f)
                .clipToBounds()
                // Reveal by LAYOUT height: measure the slip at full size, then report a
                // fraction of it. The content keeps its position under the head and the
                // paper grows downward — the same thing a printer does.
                .layout { measurable, constraints ->
                    val placeable = measurable.measure(constraints)
                    val revealed = (placeable.height * feed.value).roundToInt().coerceAtLeast(0)
                    layout(placeable.width, revealed) { placeable.place(0, 0) }
                }
                .background(Color.White),
        ) {
            Spacer(Modifier.height(20.dp))

            // Brand, printed. The white wordmark asset tinted to ink — same asset the
            // splash uses, so the mark is identical everywhere.
            Image(
                painter = painterResource(id = R.drawable.haraan_logo_white),
                contentDescription = "Haraan",
                contentScale = ContentScale.Fit,
                colorFilter = ColorFilter.tint(Color(0xFF10151C)),
                modifier = Modifier
                    .align(Alignment.CenterHorizontally)
                    .width(104.dp),
            )
            Spacer(Modifier.height(6.dp))
            Text(
                "BOOKING RECEIPT",
                color = Color(0xFF8B95A3),
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 2.2.sp,
                fontFamily = FontFamily.Monospace,
                modifier = Modifier.align(Alignment.CenterHorizontally),
            )

            Spacer(Modifier.height(14.dp))
            DashedRule()
            Spacer(Modifier.height(14.dp))

            Text(
                venueName,
                color = Color(0xFF10151C),
                fontSize = 16.sp,
                fontWeight = FontWeight.ExtraBold,
                modifier = Modifier.padding(horizontal = 18.dp),
            )
            Spacer(Modifier.height(12.dp))

            SlipRow("SPORT", sport)
            SlipRow("DATE", dateLabel)
            SlipRow("TIME", timeLabel)
            if (!courtName.isNullOrBlank()) SlipRow("COURT", courtName)
            SlipRow("DURATION", if (durationHours == 1) "1 hour" else "$durationHours hours")

            Spacer(Modifier.height(12.dp))
            DashedRule()
            Spacer(Modifier.height(12.dp))

            Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 18.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(
                    "TOTAL",
                    color = Color(0xFF10151C),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.2.sp,
                    fontFamily = FontFamily.Monospace,
                )
                Text(
                    totalLabel,
                    color = Color(0xFF10151C),
                    fontSize = 18.sp,
                    fontWeight = FontWeight.ExtraBold,
                    fontFamily = FontFamily.Monospace,
                )
            }
            if (payAtVenue) {
                Spacer(Modifier.height(3.dp))
                Text(
                    // Said plainly, because it is what happens: nothing was charged here.
                    "Pay at the venue",
                    color = Color(0xFF8B95A3),
                    fontSize = 10.5.sp,
                    fontFamily = FontFamily.Monospace,
                    modifier = Modifier.padding(horizontal = 18.dp),
                )
            }

            Spacer(Modifier.height(16.dp))

            if (!ticketCode.isNullOrBlank()) {
                DashedRule()
                Spacer(Modifier.height(16.dp))
                Box(
                    modifier = Modifier
                        .align(Alignment.CenterHorizontally)
                        .graphicsLayer {
                            alpha = qrReveal.value
                            val s = 0.88f + 0.12f * qrReveal.value
                            scaleX = s
                            scaleY = s
                        },
                ) {
                    QrImage(
                        content = "haraan:ticket:$ticketCode",
                        modifier = Modifier.size(132.dp),
                    )
                }
                Spacer(Modifier.height(8.dp))
                Text(
                    ticketCode.uppercase(),
                    color = Color(0xFF10151C),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 3.sp,
                    fontFamily = FontFamily.Monospace,
                    modifier = Modifier.align(Alignment.CenterHorizontally),
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    "SHOW THIS AT THE DESK",
                    color = Color(0xFF8B95A3),
                    fontSize = 8.5.sp,
                    letterSpacing = 1.6.sp,
                    fontFamily = FontFamily.Monospace,
                    modifier = Modifier.align(Alignment.CenterHorizontally),
                )
                Spacer(Modifier.height(18.dp))
            }

            // The tear. Drawn as the paper's own bottom boundary so the slip ends in
            // points rather than a ruled line.
            TornEdge()
        }

        Spacer(Modifier.height(22.dp))

        // Only offered once the paper is out — a button competing with the print turns a
        // staged moment back into a dialog. Never auto-dismisses: the code is the point.
        if (printed) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .pressable(haptic = Feel.SELECT, onClick = onDone)
                    .clip(RoundedCornerShape(50))
                    .background(Color.White.copy(alpha = 0.16f))
                    .padding(vertical = 15.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text("Done", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp)
            }
        }
    }
}

/** A printed dashed rule, drawn rather than typed so the dashes stay even at any width. */
@Composable
private fun DashedRule() {
    Canvas(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 18.dp)
            .height(1.dp),
    ) {
        val dash = 5f
        val gap = 4f
        var x = 0f
        while (x < size.width) {
            drawLine(
                color = Color(0xFFD3DAE3),
                start = androidx.compose.ui.geometry.Offset(x, 0f),
                end = androidx.compose.ui.geometry.Offset(minOf(x + dash, size.width), 0f),
                strokeWidth = size.height,
            )
            x += dash + gap
        }
    }
}

/** `LABEL ........ value` — the receipt idiom, with the leader drawn not typed. */
@Composable
private fun SlipRow(label: String, value: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 18.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            label,
            color = Color(0xFF8B95A3),
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.1.sp,
            fontFamily = FontFamily.Monospace,
        )
        Spacer(Modifier.width(8.dp))
        Box(Modifier.weight(1f)) { DottedLeader() }
        Spacer(Modifier.width(8.dp))
        Text(
            value,
            color = Color(0xFF10151C),
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            fontFamily = FontFamily.Monospace,
        )
    }
}

@Composable
private fun DottedLeader() {
    Canvas(modifier = Modifier.fillMaxWidth().height(1.dp)) {
        var x = 0f
        while (x < size.width) {
            drawLine(
                color = Color(0xFFE1E6EC),
                start = androidx.compose.ui.geometry.Offset(x, 0f),
                end = androidx.compose.ui.geometry.Offset(x + 1.5f, 0f),
                strokeWidth = size.height,
            )
            x += 5f
        }
    }
}

/** The zigzag the paper tears along. */
@Composable
private fun TornEdge() {
    Canvas(
        modifier = Modifier
            .fillMaxWidth()
            .height(9.dp),
    ) {
        val tooth = 14f
        val path = Path().apply {
            moveTo(0f, 0f)
            var x = 0f
            while (x < size.width) {
                lineTo(x + tooth / 2f, size.height)
                lineTo(x + tooth, 0f)
                x += tooth
            }
            lineTo(size.width, 0f)
            close()
        }
        drawPath(path, color = Color.White)
    }
}
