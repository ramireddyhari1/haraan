package com.haraan.app.ui.main

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
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
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.RoundRect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.Outline
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.layout
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.R
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.QrImage
import com.haraan.app.ui.pressable
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.ceil
import kotlin.math.roundToInt

private val Ink = Color(0xFF10151C)
private val InkSoft = Color(0xFF8B95A3)
private val Paper = Color(0xFFFDFCF8)
private val BrandBlue = Color(0xFF2563EB)

/**
 * The booking confirmation, printed.
 *
 * A confirmation screen is the most reflexively generic surface in any app: a green
 * tick, a headline, a button. This one is a thermal slip fed out of a machine, because
 * that is what a venue booking physically IS at the desk — and because the moment is
 * worth staging.
 *
 * What sells it is physics, not paper texture:
 *  - the PAPER MOVES. The slip slides out of the slot with its text riding on it, leading
 *    edge first (which is the previous customer's tear, so it's already zigzagged). A
 *    growing mask over static text reads as a wipe, not a print;
 *  - the feed is **stepped**, 18 motor jerks with uneven rests, never a smooth slide, and
 *    every jerk kicks the hanging paper so it flutters on its hinge at the slot;
 *  - a haptic **tick fires on every step**, so the printer chatters in the hand;
 *  - the roll visibly turns behind the smoked lid window as paper is pulled off it;
 *  - ink is fresh at the head: the band nearest the slot prints faint and darkens as it
 *    feeds away, and the slot throws a shadow onto the paper;
 *  - it ends in a TEAR: one heavy haptic, the printer recoils, the slip drops free with a
 *    spring and a slight swing, gains a shadow, and its top edge is now zigzagged too.
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
    venueAddress: String? = null,
    /** Opens the venue in Google Maps; null hides the Directions button. */
    onOpenMap: (() -> Unit)? = null,
) {
    val view = LocalView.current
    val feed = remember { Animatable(0f) }
    // Paper hinge at the slot (rotationX, degrees): kicked by each motor step.
    val lean = remember { Animatable(0f) }
    // After the tear: how far the slip falls free (dp), its swing, its lift off the stage.
    val drop = remember { Animatable(0f) }
    val swing = remember { Animatable(0f) }
    val lift = remember { Animatable(0f) }
    // Printer recoil when the paper is torn against its bar (dp).
    val recoil = remember { Animatable(0f) }
    // Head effects (fresh ink, slot shadow, heat) — 1 while printing, fades on the tear.
    val head = remember { Animatable(1f) }
    val doneIn = remember { Animatable(0f) }
    var printing by remember { mutableStateOf(true) }
    var torn by remember { mutableStateOf(false) }
    var slipHeight by remember { mutableIntStateOf(0) }

    val printerRun = rememberInfiniteTransition(label = "printerRun")
    val buzz by printerRun.animateFloat(
        initialValue = -0.5f,
        targetValue = 0.5f,
        animationSpec = infiniteRepeatable(tween(60, easing = LinearEasing), RepeatMode.Reverse),
        label = "printerBuzz",
    )
    val led by printerRun.animateFloat(
        initialValue = 0.35f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(380, easing = LinearEasing), RepeatMode.Reverse),
        label = "printerLed",
    )

    LaunchedEffect(Unit) {
        delay(220) // the machine wakes before it feeds
        val steps = 18
        repeat(steps) { i ->
            launch {
                lean.animateTo(2.4f, tween(40))
                lean.animateTo(0f, spring(dampingRatio = 0.3f, stiffness = Spring.StiffnessMediumLow))
            }
            feed.animateTo((i + 1) / steps.toFloat(), tween(46, easing = FastOutSlowInEasing))
            view.performHapticFeedback(Feel.TICK)
            // Uneven rests: a stepper under load never keeps perfect time.
            delay(longArrayOf(18, 30, 22)[i % 3])
        }
        printing = false
        delay(280) // the beat where a hand reaches for the paper

        torn = true
        view.performHapticFeedback(Feel.COMMIT)
        launch {
            recoil.snapTo(-3.5f)
            recoil.animateTo(0f, spring(dampingRatio = 0.3f, stiffness = Spring.StiffnessMedium))
        }
        launch { drop.animateTo(18f, spring(dampingRatio = 0.42f, stiffness = Spring.StiffnessLow)) }
        launch {
            swing.animateTo(-2.2f, tween(120))
            swing.animateTo(-0.7f, spring(dampingRatio = 0.25f, stiffness = Spring.StiffnessVeryLow))
        }
        launch { lift.animateTo(1f, tween(320)) }
        launch { head.animateTo(0f, tween(420)) }
        delay(260)
        doneIn.animateTo(1f, spring(dampingRatio = 0.8f, stiffness = Spring.StiffnessLow))
    }

    val paperShape = remember(torn) { TornPaperShape(tornTop = torn) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        ThermalPrinter(
            feed = feed.value,
            printing = printing,
            buzz = buzz,
            led = if (printing) led else 1f,
            recoil = recoil.value,
        )

        // The stage centres this whole column, so a box that grew with the paper would
        // walk the printer up the screen as it printed. Reserve the finished slip's height
        // (read back from the paper's own measure) so the machine stays put, as machines do.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = with(LocalDensity.current) { slipHeight.toDp() } + 18.dp),
            contentAlignment = Alignment.TopCenter,
        ) {
            // ── The paper ────────────────────────────────────────────────────────
            // Narrower than the slot, because a roll sits inside its housing.
            Column(
                modifier = Modifier
                    .fillMaxWidth(0.82f)
                    .graphicsLayer {
                        transformOrigin = TransformOrigin(0.5f, 0f)
                        cameraDistance = 14f * density
                        rotationX = lean.value
                        rotationZ = swing.value
                        translationY = drop.value * density
                        shape = paperShape
                        clip = true
                        shadowElevation = lift.value * 14f * density
                    }
                    .drawWithContent {
                        // Paper, with the faint curl a roll leaves at its edges.
                        drawRect(
                            Brush.horizontalGradient(
                                0f to Color(0xFFEDEBE4),
                                0.07f to Paper,
                                0.93f to Paper,
                                1f to Color(0xFFEDEBE4),
                            ),
                        )
                        drawContent()
                        val fx = head.value
                        if (fx > 0f) {
                            val px = 1.dp.toPx()
                            // Fresh ink: faint at the head, full strength a few lines down.
                            drawRect(
                                Brush.verticalGradient(
                                    listOf(Paper.copy(alpha = 0.75f * fx), Paper.copy(alpha = 0f)),
                                    startY = 0f,
                                    endY = 40 * px,
                                ),
                                size = Size(size.width, 40 * px),
                            )
                            // The slot's shadow on the paper it is letting go of.
                            drawRect(
                                Brush.verticalGradient(
                                    listOf(Color.Black.copy(alpha = 0.22f * fx), Color.Transparent),
                                    startY = 0f,
                                    endY = 16 * px,
                                ),
                                size = Size(size.width, 16 * px),
                            )
                            // Heat off the print head, breathing with the LED.
                            if (printing) {
                                drawRect(
                                    Brush.verticalGradient(
                                        listOf(Color(0xFFFF9B54).copy(alpha = 0.22f * led), Color.Transparent),
                                        startY = 3 * px,
                                        endY = 11 * px,
                                    ),
                                    topLeft = Offset(0f, 3 * px),
                                    size = Size(size.width, 8 * px),
                                )
                            }
                        }
                    }
                    // Reveal by LAYOUT height, with the content placed so its BOTTOM sits at
                    // the paper's leading edge: as the reported height grows the slip slides
                    // out of the slot carrying its print, which is what paper does.
                    .layout { measurable, constraints ->
                        val placeable = measurable.measure(constraints)
                        slipHeight = placeable.height
                        val revealed = (placeable.height * feed.value).roundToInt().coerceAtLeast(0)
                        layout(placeable.width, revealed) { placeable.place(0, revealed - placeable.height) }
                    },
            ) {
                SlipContent(
                    venueName = venueName,
                    sport = sport,
                    dateLabel = dateLabel,
                    timeLabel = timeLabel,
                    courtName = courtName,
                    durationHours = durationHours,
                    totalLabel = totalLabel,
                    ticketCode = ticketCode,
                    payAtVenue = payAtVenue,
                    venueAddress = venueAddress,
                    onOpenMap = onOpenMap,
                )
            }

            // Tear bar, drawn OVER the paper so the slip emerges from under its teeth.
            TearBar(
                Modifier
                    .offset(y = (-4).dp)
                    .graphicsLayer { translationY = recoil.value * density }
                    .fillMaxWidth(0.88f)
                    .height(8.dp),
            )
        }

        Spacer(Modifier.height(22.dp))

        // Always laid out (so the slip never jumps when it appears) but only live once the
        // paper is torn — a button competing with the print turns the moment into a dialog.
        // Never auto-dismisses: the code is the point.
        val ready = doneIn.value > 0.5f
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .graphicsLayer {
                    alpha = doneIn.value
                    translationY = (1f - doneIn.value) * 14f * density
                }
                .then(if (ready) Modifier.pressable(haptic = Feel.SELECT, onClick = onDone) else Modifier)
                .clip(RoundedCornerShape(50))
                .background(Color.White.copy(alpha = 0.16f))
                .padding(vertical = 15.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text("Done", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp)
        }
    }
}

@Composable
private fun ColumnScope.SlipContent(
    venueName: String,
    sport: String,
    dateLabel: String,
    timeLabel: String,
    courtName: String?,
    durationHours: Int,
    totalLabel: String,
    ticketCode: String?,
    payAtVenue: Boolean,
    venueAddress: String?,
    onOpenMap: (() -> Unit)?,
) {
    Spacer(Modifier.height(24.dp))

    // Brand, printed. The white wordmark asset tinted to ink — same asset the splash
    // uses, so the mark is identical everywhere.
    Image(
        painter = painterResource(id = R.drawable.haraan_logo_white),
        contentDescription = "Haraan",
        contentScale = ContentScale.Fit,
        colorFilter = ColorFilter.tint(Ink),
        modifier = Modifier
            .align(Alignment.CenterHorizontally)
            .width(100.dp),
    )
    Spacer(Modifier.height(6.dp))
    Text(
        "BOOKING RECEIPT",
        color = InkSoft,
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
        color = Ink,
        fontSize = 16.sp,
        fontWeight = FontWeight.ExtraBold,
        modifier = Modifier.padding(horizontal = 18.dp),
    )
    if (!venueAddress.isNullOrBlank()) {
        Spacer(Modifier.height(4.dp))
        Text(
            venueAddress,
            color = InkSoft,
            fontSize = 10.5.sp,
            lineHeight = 14.sp,
            fontFamily = FontFamily.Monospace,
            maxLines = 3,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = 18.dp),
        )
    }
    if (onOpenMap != null) {
        Spacer(Modifier.height(10.dp))
        // The one thing on the slip you can press — getting there is the next job.
        Row(
            modifier = Modifier
                .padding(horizontal = 18.dp)
                .pressable(haptic = Feel.SELECT, onClick = onOpenMap)
                .clip(RoundedCornerShape(50))
                .background(BrandBlue)
                .padding(start = 10.dp, end = 14.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            MapsGlyph(Modifier.size(18.dp))
            Spacer(Modifier.width(7.dp))
            Text(
                "Directions · Google Maps",
                color = Color.White,
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
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
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            "TOTAL",
            color = Ink,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.2.sp,
            fontFamily = FontFamily.Monospace,
        )
        Text(
            totalLabel,
            color = Ink,
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
            color = InkSoft,
            fontSize = 10.5.sp,
            fontFamily = FontFamily.Monospace,
            modifier = Modifier.padding(horizontal = 18.dp),
        )
    }

    Spacer(Modifier.height(16.dp))

    if (!ticketCode.isNullOrBlank()) {
        DashedRule()
        Spacer(Modifier.height(16.dp))
        QrImage(
            content = "haraan:ticket:$ticketCode",
            modifier = Modifier
                .align(Alignment.CenterHorizontally)
                .size(132.dp),
        )
        Spacer(Modifier.height(8.dp))
        Text(
            ticketCode.uppercase(),
            color = Ink,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 3.sp,
            fontFamily = FontFamily.Monospace,
            modifier = Modifier.align(Alignment.CenterHorizontally),
        )
        Spacer(Modifier.height(4.dp))
        Text(
            "SHOW THIS AT THE DESK",
            color = InkSoft,
            fontSize = 8.5.sp,
            letterSpacing = 1.6.sp,
            fontFamily = FontFamily.Monospace,
            modifier = Modifier.align(Alignment.CenterHorizontally),
        )
        Spacer(Modifier.height(4.dp))
    }

    // Room for the zigzag the shape cuts out of the bottom.
    Spacer(Modifier.height(18.dp))
}

/**
 * A counter-top thermal printer, drawn: a lid with a smoked window onto the paper roll
 * (which turns as paper is pulled off it), a moulded body with rounded flanks, a speaker
 * grille, a FEED key, a status LED that throws light, and the recessed feed slot. It
 * buzzes while the head runs and recoils when the paper is torn against its bar.
 */
@Composable
private fun ThermalPrinter(feed: Float, printing: Boolean, buzz: Float, led: Float, recoil: Float) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(104.dp)
            .graphicsLayer {
                translationY = ((if (printing) buzz else 0f) + recoil) * density
            },
    ) {
        Canvas(Modifier.fillMaxSize()) {
            val u = 1.dp.toPx()
            val w = size.width
            val h = size.height

            // ── Lid (the top face, set back) ──
            val lidX = w * 0.04f
            val lidW = w * 0.92f
            drawRoundRect(
                brush = Brush.verticalGradient(
                    listOf(Color(0xFF5A6677), Color(0xFF3D4754)),
                    startY = 0f,
                    endY = 44 * u,
                ),
                topLeft = Offset(lidX, 0f),
                size = Size(lidW, 52 * u),
                cornerRadius = CornerRadius(16 * u),
            )
            // Specular along the lid's front-top edge.
            drawRoundRect(
                color = Color.White.copy(alpha = 0.14f),
                topLeft = Offset(lidX + 14 * u, 1.5f * u),
                size = Size(lidW - 28 * u, 1.2f * u),
                cornerRadius = CornerRadius(u),
            )

            // ── Roll window ──
            val winW = w * 0.46f
            val winX = (w - winW) / 2f
            val winY = 8 * u
            val winH = 22 * u
            val win = Path().apply {
                addRoundRect(RoundRect(winX, winY, winX + winW, winY + winH, CornerRadius(7 * u)))
            }
            drawPath(win, Color(0xFF0B0F14))
            clipPath(win) {
                val inset = 3 * u
                // The roll: a lit cylinder.
                drawRect(
                    Brush.verticalGradient(
                        0f to Color(0xFF7F8893),
                        0.42f to Color(0xFFF3F4F6),
                        0.62f to Color(0xFFE2E5E9),
                        1f to Color(0xFF6E7782),
                        startY = winY + inset,
                        endY = winY + winH - inset,
                    ),
                    topLeft = Offset(winX + inset * 2, winY + inset),
                    size = Size(winW - inset * 4, winH - inset * 2),
                )
                // Winding lines that roll forward as paper is drawn off.
                val gap = 4.5f * u
                val shift = (feed * 300 * u) % gap
                var y = winY - gap + shift
                while (y < winY + winH) {
                    drawLine(
                        Color.Black.copy(alpha = 0.09f),
                        Offset(winX + inset * 2, y),
                        Offset(winX + winW - inset * 2, y),
                        strokeWidth = 0.8f * u,
                    )
                    y += gap
                }
                // Smoked glass and a diagonal glare.
                drawRect(Color(0xFF0B0F14).copy(alpha = 0.38f), topLeft = Offset(winX, winY), size = Size(winW, winH))
                val glare = Path().apply {
                    moveTo(winX + winW * 0.18f, winY)
                    lineTo(winX + winW * 0.30f, winY)
                    lineTo(winX + winW * 0.20f, winY + winH)
                    lineTo(winX + winW * 0.08f, winY + winH)
                    close()
                }
                drawPath(glare, Color.White.copy(alpha = 0.13f))
            }

            // ── Body (the front face) ──
            val bodyTop = 36 * u
            val body = Path().apply {
                addRoundRect(
                    RoundRect(
                        left = 0f, top = bodyTop, right = w, bottom = h,
                        topLeftCornerRadius = CornerRadius(12 * u),
                        topRightCornerRadius = CornerRadius(12 * u),
                        bottomLeftCornerRadius = CornerRadius(10 * u),
                        bottomRightCornerRadius = CornerRadius(10 * u),
                    ),
                )
            }
            drawPath(
                body,
                Brush.verticalGradient(
                    listOf(Color(0xFF353F4B), Color(0xFF1F262F), Color(0xFF141920)),
                    startY = bodyTop,
                    endY = h,
                ),
            )
            // Rounded flanks: darker at both ends, so it's a moulding, not a slab.
            drawPath(
                body,
                Brush.horizontalGradient(
                    0f to Color.Black.copy(alpha = 0.34f),
                    0.12f to Color.Transparent,
                    0.88f to Color.Transparent,
                    1f to Color.Black.copy(alpha = 0.38f),
                ),
            )
            // The lid's shadow on the body, then the body's own lit lip.
            drawRect(
                Brush.verticalGradient(
                    listOf(Color.Black.copy(alpha = 0.35f), Color.Transparent),
                    startY = bodyTop,
                    endY = bodyTop + 6 * u,
                ),
                topLeft = Offset(10 * u, bodyTop),
                size = Size(w - 20 * u, 6 * u),
            )
            drawLine(
                Color.White.copy(alpha = 0.13f),
                Offset(12 * u, bodyTop + 0.6f * u),
                Offset(w - 12 * u, bodyTop + 0.6f * u),
                strokeWidth = u,
            )

            // ── Speaker grille ──
            val gx = 20 * u
            val gy = bodyTop + 14 * u
            for (r in 0 until 3) for (c in 0 until 5) {
                val ctr = Offset(gx + c * 5 * u, gy + r * 5 * u)
                drawCircle(Color(0xFF080B0F), radius = 1.3f * u, center = ctr)
                drawCircle(Color.White.copy(alpha = 0.07f), radius = 1.3f * u, center = ctr + Offset(0f, 0.7f * u))
                drawCircle(Color(0xFF080B0F), radius = 1.3f * u, center = ctr)
            }

            // ── FEED key ──
            val keyW = 24 * u
            val keyH = 11 * u
            val keyX = w - 62 * u
            val keyY = bodyTop + 14 * u
            drawRoundRect(
                Color.Black.copy(alpha = 0.45f),
                topLeft = Offset(keyX, keyY + 1.2f * u),
                size = Size(keyW, keyH),
                cornerRadius = CornerRadius(keyH / 2),
            )
            drawRoundRect(
                Brush.verticalGradient(listOf(Color(0xFF48535F), Color(0xFF2A323C)), startY = keyY, endY = keyY + keyH),
                topLeft = Offset(keyX, keyY),
                size = Size(keyW, keyH),
                cornerRadius = CornerRadius(keyH / 2),
            )
            drawLine(
                Color.White.copy(alpha = 0.16f),
                Offset(keyX + keyH / 2, keyY + 0.8f * u),
                Offset(keyX + keyW - keyH / 2, keyY + 0.8f * u),
                strokeWidth = 0.8f * u,
            )

            // ── Status LED, with the light it throws ──
            val ledC = Offset(w - 24 * u, keyY + keyH / 2)
            val green = Color(0xFF34D399)
            drawCircle(
                Brush.radialGradient(
                    listOf(green.copy(alpha = 0.5f * led), Color.Transparent),
                    center = ledC,
                    radius = 11 * u,
                ),
                radius = 11 * u,
                center = ledC,
            )
            drawCircle(Color(0xFF0A0D11), radius = 4.4f * u, center = ledC)
            drawCircle(green.copy(alpha = 0.35f + 0.65f * led), radius = 3.2f * u, center = ledC)
            drawCircle(Color.White.copy(alpha = 0.7f * led), radius = 1f * u, center = ledC + Offset(-1f * u, -1f * u))

            // ── Feed slot ──
            val slotX = w * 0.07f
            val slotW = w * 0.86f
            val slotY = h - 13 * u
            drawRoundRect(
                Brush.verticalGradient(listOf(Color(0xFF000000), Color(0xFF161B22)), startY = slotY, endY = h - 2 * u),
                topLeft = Offset(slotX, slotY),
                size = Size(slotW, 11 * u),
                cornerRadius = CornerRadius(3 * u),
            )
            drawLine(
                Color.White.copy(alpha = 0.10f),
                Offset(slotX + 4 * u, slotY - 0.6f * u),
                Offset(slotX + slotW - 4 * u, slotY - 0.6f * u),
                strokeWidth = 0.8f * u,
            )
        }
        // Embossed maker's mark — barely there, as moulded lettering is.
        Text(
            "HARAAN",
            color = Color.White.copy(alpha = 0.2f),
            fontSize = 8.5.sp,
            fontWeight = FontWeight.Black,
            letterSpacing = 3.sp,
            modifier = Modifier
                .align(Alignment.TopCenter)
                .padding(top = 51.dp),
        )
    }
}

/**
 * A maps icon, drawn: a folded paper map (three panels, the middle one in shade so the
 * folds read) with a red pin standing on it.
 */
@Composable
private fun MapsGlyph(modifier: Modifier) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val tops = floatArrayOf(0.34f, 0.24f, 0.34f, 0.24f)
        val bottoms = floatArrayOf(0.96f, 0.86f, 0.96f, 0.86f)
        for (i in 0 until 3) {
            val x0 = w * i / 3f
            val x1 = w * (i + 1) / 3f
            val panel = Path().apply {
                moveTo(x0, h * tops[i])
                lineTo(x1, h * tops[i + 1])
                lineTo(x1, h * bottoms[i + 1])
                lineTo(x0, h * bottoms[i])
                close()
            }
            drawPath(panel, if (i == 1) Color(0xFFD6E2FB) else Color.White)
        }
        // The pin: a head and a point, with a hole through the head.
        val c = Offset(w * 0.6f, h * 0.27f)
        val r = w * 0.22f
        val pin = Path().apply {
            moveTo(c.x - r * 0.86f, c.y + r * 0.5f)
            lineTo(c.x, h * 0.74f)
            lineTo(c.x + r * 0.86f, c.y + r * 0.5f)
            close()
        }
        val red = Color(0xFFEA4335)
        drawPath(pin, red)
        drawCircle(red, radius = r, center = c)
        drawCircle(Color.White, radius = r * 0.42f, center = c)
    }
}

/** The serrated metal bar the slip is torn against. */
@Composable
private fun TearBar(modifier: Modifier) {
    Canvas(modifier) {
        val u = 1.dp.toPx()
        val barH = 3.5f * u
        drawRoundRect(
            Brush.verticalGradient(listOf(Color(0xFFB4BDC9), Color(0xFF5A6472)), startY = 0f, endY = barH),
            size = Size(size.width, barH),
            cornerRadius = CornerRadius(1.5f * u),
        )
        val n = ceil(size.width / (5 * u)).toInt().coerceAtLeast(1)
        val t = size.width / n
        val teeth = Path().apply {
            moveTo(0f, barH)
            for (i in 0 until n) {
                lineTo(i * t + t / 2f, size.height)
                lineTo((i + 1) * t, barH)
            }
            close()
        }
        drawPath(
            teeth,
            Brush.verticalGradient(listOf(Color(0xFF8A94A2), Color(0xFF444C58)), startY = barH, endY = size.height),
        )
        drawLine(Color.White.copy(alpha = 0.45f), Offset(0f, 0.6f * u), Offset(size.width, 0.6f * u), strokeWidth = 0.8f * u)
    }
}

/**
 * The slip's outline: the bottom is always a zigzag (the leading edge is the last tear),
 * the top turns into one the moment it's torn off the bar.
 */
private class TornPaperShape(private val tornTop: Boolean) : Shape {
    override fun createOutline(size: Size, layoutDirection: LayoutDirection, density: Density): Outline {
        val depth = with(density) { 7.dp.toPx() }
        val n = ceil(size.width / with(density) { 12.dp.toPx() }).toInt().coerceAtLeast(1)
        val t = size.width / n
        val path = Path().apply {
            if (tornTop) {
                moveTo(0f, depth)
                for (i in 0 until n) {
                    lineTo(i * t + t / 2f, 0f)
                    lineTo((i + 1) * t, depth)
                }
            } else {
                moveTo(0f, 0f)
                lineTo(size.width, 0f)
            }
            lineTo(size.width, size.height - depth)
            for (i in n downTo 1) {
                lineTo(i * t - t / 2f, size.height)
                lineTo((i - 1) * t, size.height - depth)
            }
            close()
        }
        return Outline.Generic(path)
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
                start = Offset(x, 0f),
                end = Offset(minOf(x + dash, size.width), 0f),
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
            color = InkSoft,
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
            color = Ink,
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
                start = Offset(x, 0f),
                end = Offset(x + 1.5f, 0f),
                strokeWidth = size.height,
            )
            x += 5f
        }
    }
}
