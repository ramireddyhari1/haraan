package com.haraan.app.ui.main

import android.app.Activity
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.outlined.Navigation
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.R
import com.haraan.app.data.BookingLite
import com.haraan.app.ui.components.QrImage
import com.haraan.app.ui.theme.HaraanColors

private val HaraanBlue = Color(0xFF2E97F7)
private val PassNavy = Color(0xFF14324A)

/**
 * Full-screen entry pass shown when a user taps one of their booked events/venues in "My Schedule".
 * The QR encodes `haraan:ticket:<ticketCode>` — the exact payload the partner check-in scanner
 * resolves — with the Haraan H monogram knocked into the centre (safe: [QrImage] uses ECC level H).
 * Screen brightness is pushed to max while open so the QR scans reliably at the gate.
 */
@Composable
fun TicketPassScreen(booking: BookingLite, onClose: () -> Unit) {
    val ctx = LocalContext.current

    // Boost brightness to max while the pass is on screen; restore on dismiss.
    DisposableEffect(Unit) {
        val window = (ctx as? Activity)?.window
        val previous = window?.attributes?.screenBrightness
        window?.let {
            val lp = it.attributes
            lp.screenBrightness = 1f
            it.attributes = lp
        }
        onDispose {
            window?.let {
                val lp = it.attributes
                lp.screenBrightness = previous ?: -1f
                it.attributes = lp
            }
        }
    }

    val isVenue = booking.type == "venue"
    val code = booking.ticketCode?.takeIf { it.isNotBlank() }
    // Only the leading groups: enough for a human to read out at the gate, and
    // the full code is in the QR anyway. Six groups read as a serial dump.
    val prettyCode = code?.chunked(4)?.take(3)?.joinToString(" · ") ?: "—"

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xF20B1622)) // deep translucent scrim
            .clickable(enabled = false) {},
        contentAlignment = Alignment.TopCenter,
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .padding(horizontal = 20.dp, vertical = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            // Close row
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Your pass",
                    color = Color.White,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.12f))
                        .clickable(onClick = onClose),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Default.Close, contentDescription = "Close", tint = Color.White, modifier = Modifier.size(20.dp))
                }
            }

            Spacer(Modifier.height(20.dp))

            // ── The pass card ────────────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 360.dp)
                    .clip(RoundedCornerShape(22.dp))
                    .background(Color.White),
            ) {
                // Brand band — anchors the card and gives "ENTRY PASS" somewhere to
                // live other than as grey afterthought text on white.
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(PassNavy)
                        .padding(horizontal = 18.dp, vertical = 14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // The wordmark is dark navy ink on transparency — the same navy as
                    // this band. It's alpha art, so tint it white rather than shipping
                    // a second asset.
                    Image(
                        painter = painterResource(id = R.drawable.haraan_logo),
                        contentDescription = "Haraan",
                        contentScale = ContentScale.Fit,
                        colorFilter = ColorFilter.tint(Color.White),
                        modifier = Modifier.height(18.dp),
                    )
                    Spacer(Modifier.weight(1f))
                    Text(
                        "ENTRY PASS",
                        color = Color.White.copy(alpha = 0.75f),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.2.sp,
                    )
                }

                // Event / venue summary
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(start = 18.dp, end = 18.dp, top = 16.dp),
                    verticalAlignment = Alignment.Top,
                ) {
                    Box(
                        modifier = Modifier
                            .size(56.dp)
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color(0xFFEFF3F8)),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (!booking.imageUrl.isNullOrBlank()) {
                            AsyncImage(
                                model = booking.imageUrl,
                                contentDescription = booking.eventTitle,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(14.dp)),
                            )
                        } else {
                            Image(
                                painter = painterResource(id = R.drawable.haraan_copy),
                                contentDescription = null,
                                contentScale = ContentScale.Fit,
                                modifier = Modifier.size(26.dp),
                            )
                        }
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            booking.eventTitle,
                            color = HaraanColors.TextPrimary,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            lineHeight = 21.sp,
                        )
                        booking.eventVenue?.takeIf { it.isNotBlank() }?.let {
                            Text(it, color = HaraanColors.TextSecondary, fontSize = 12.sp, maxLines = 2, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 3.dp))
                        }
                    }
                }

                // The same facts as the old run-on meta line, given structure.
                // Cells with nothing to say are dropped rather than shown empty.
                val cells = buildList {
                    booking.eventDate?.take(10)?.takeIf { it.isNotBlank() }?.let { add("DATE" to it) }
                    if (isVenue) {
                        booking.slotLabel?.takeIf { it.isNotBlank() }?.let { add("SLOT" to it) }
                    } else {
                        booking.tierName?.takeIf { it.isNotBlank() }?.let { add("TIER" to it) }
                        add("GUESTS" to "${booking.quantity} ticket${if (booking.quantity == 1) "" else "s"}")
                    }
                }
                if (cells.isNotEmpty()) {
                    Column(
                        modifier = Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 2.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        cells.chunked(2).forEach { row ->
                            Row(modifier = Modifier.fillMaxWidth()) {
                                row.forEach { (label, value) ->
                                    PassDetail(label, value, Modifier.weight(1f))
                                }
                                if (row.size == 1) Spacer(Modifier.weight(1f))
                            }
                        }
                    }
                }

                Spacer(Modifier.height(14.dp))

                // Perforation
                TicketPerforation()

                // QR block
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp)
                        .padding(top = 18.dp, bottom = 22.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    if (code != null) {
                        // No border: it would only separate white from white. A barely
                        // tinted plate gives the code contrast without drawing a box.
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(14.dp))
                                .background(Color(0xFFF8FAFC))
                                .padding(16.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            QrImage(
                                content = "haraan:ticket:$code",
                                sizePx = 720,
                                modifier = Modifier.size(210.dp),
                            )
                            // Brand monogram knocked into the centre, on a chip matching
                            // the QR's own white so the knockout reads as clean.
                            Box(
                                modifier = Modifier
                                    .size(46.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(Color.White)
                                    .padding(7.dp),
                                contentAlignment = Alignment.Center,
                            ) {
                                Image(
                                    painter = painterResource(id = R.drawable.haraan_copy),
                                    contentDescription = null,
                                    contentScale = ContentScale.Fit,
                                    modifier = Modifier.fillMaxSize(),
                                )
                            }
                        }
                        Spacer(Modifier.height(13.dp))
                        // A quiet fallback for when a scanner fails — not the loudest
                        // thing on the card. The QR carries the full code.
                        Text(
                            prettyCode,
                            color = HaraanColors.TextMuted,
                            fontSize = 11.sp,
                            fontFamily = FontFamily.Monospace,
                            letterSpacing = 1.4.sp,
                        )
                    } else {
                        // Extremely rare: a legacy booking with no code. Honest, not a broken QR.
                        Text(
                            "This booking has no scannable code yet. Show the details below at the gate.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(vertical = 24.dp, horizontal = 12.dp),
                        )
                    }

                    Spacer(Modifier.height(8.dp))
                    StatusChip(booking.status)

                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                        Icon(Icons.Outlined.PhoneAndroid, contentDescription = null, tint = HaraanColors.TextMuted, modifier = Modifier.size(13.dp))
                        // Surfaces the brightness boost above, and answers the "will this
                        // work with no signal at the gate?" worry before it's asked.
                        Text("Brightness boosted · works offline", color = HaraanColors.TextMuted, fontSize = 11.sp)
                    }
                }

                // Actions live on the stub, below the QR: the pass is the point,
                // these are what you do around it.
                val mapLink = booking.mapLink?.takeIf { it.isNotBlank() }
                if (mapLink != null) {
                    HorizontalHairline()
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { openDirections(ctx, mapLink) }
                            .padding(vertical = 14.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Outlined.Navigation, contentDescription = null, tint = HaraanBlue, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(7.dp))
                        Text("Get directions", color = HaraanBlue, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

/**
 * Hand the admin-set map link to whatever app the user has. A device with no
 * browser or maps app is vanishingly rare, but it must not crash the pass —
 * the ticket itself is the thing that matters here.
 */
private fun openDirections(ctx: android.content.Context, mapLink: String) {
    runCatching {
        ctx.startActivity(
            android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(mapLink))
                .addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
        )
    }
}

/** One small-caps label above its value, in the pass's detail grid. */
@Composable
private fun PassDetail(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier) {
        Text(label, color = HaraanColors.TextMuted, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Text(
            value,
            color = HaraanColors.TextPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = 2.dp),
        )
    }
}

@Composable
private fun HorizontalHairline() {
    Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
}

/** The ticket-stub cut: a dashed line with a notch punched into each edge. */
@Composable
private fun TicketPerforation() {
    Box(modifier = Modifier.fillMaxWidth().height(22.dp)) {
        // Notches (match the scrim so they read as punched-out holes).
        Box(
            modifier = Modifier
                .align(Alignment.CenterStart)
                .offset(x = (-11).dp)
                .size(22.dp)
                .clip(CircleShape)
                .background(Color(0xFF0B1622)),
        )
        Box(
            modifier = Modifier
                .align(Alignment.CenterEnd)
                .offset(x = 11.dp)
                .size(22.dp)
                .clip(CircleShape)
                .background(Color(0xFF0B1622)),
        )
        // Dashed line between the notches.
        Row(
            modifier = Modifier
                .align(Alignment.Center)
                .fillMaxWidth()
                .padding(horizontal = 18.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            repeat(26) {
                Box(modifier = Modifier.weight(1f).height(2.dp).clip(RoundedCornerShape(1.dp)).background(HaraanColors.BorderLight))
            }
        }
    }
}

/**
 * "Confirmed" is true for almost every pass ever opened, so it gets a quiet dot.
 * A filled chip is saved for the states you actually want to alarm about.
 */
@Composable
private fun StatusChip(status: String) {
    val s = status.uppercase()
    val pretty = s.lowercase().replaceFirstChar { it.uppercase() }
    val alarming = s in setOf("CANCELLED", "REFUNDED", "FAILED")

    if (s == "CONFIRMED") {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            Box(modifier = Modifier.size(5.dp).clip(CircleShape).background(Color(0xFF16A34A)))
            Text(pretty, color = Color(0xFF16A34A), fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold)
        }
        return
    }

    val (fg, bg) = if (alarming) HaraanColors.LiveRed to Color(0xFFFDECEC) else HaraanBlue to Color(0xFFE9F3FE)
    Box(
        modifier = Modifier.clip(RoundedCornerShape(20.dp)).background(bg).padding(horizontal = 10.dp, vertical = 4.dp),
    ) {
        Text(s, color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.4.sp)
    }
}
