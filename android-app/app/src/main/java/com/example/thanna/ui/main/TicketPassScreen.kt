package com.example.thanna.ui.main

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
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
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
import com.example.thanna.R
import com.example.thanna.data.BookingLite
import com.example.thanna.ui.components.QrImage
import com.example.thanna.ui.theme.HaraanColors

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
    val prettyCode = code?.chunked(4)?.joinToString("  ") ?: "—"

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
                // Brand bar
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp, vertical = 14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Image(
                        painter = painterResource(id = R.drawable.haraan_logo),
                        contentDescription = "Haraan",
                        contentScale = ContentScale.Fit,
                        modifier = Modifier.height(20.dp),
                    )
                    Spacer(Modifier.weight(1f))
                    Text(
                        "ENTRY PASS",
                        color = HaraanColors.TextSecondary,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp,
                    )
                }
                HorizontalHairline()

                // Event / venue summary
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp, vertical = 16.dp),
                    verticalAlignment = Alignment.CenterVertically,
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
                        )
                        val meta = listOfNotNull(
                            booking.eventDate?.take(10),
                            if (isVenue) booking.slotLabel else null,
                            booking.eventVenue,
                        ).filter { it.isNotBlank() }.joinToString(" · ")
                        if (meta.isNotBlank()) {
                            Text(meta, color = HaraanColors.TextSecondary, fontSize = 12.5.sp, maxLines = 2, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 3.dp))
                        }
                    }
                }

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
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(16.dp))
                                .background(Color.White)
                                .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(16.dp))
                                .padding(14.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            QrImage(
                                content = "haraan:ticket:$code",
                                sizePx = 720,
                                modifier = Modifier.size(210.dp),
                            )
                            // Brand monogram knocked into the centre, on a white chip.
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
                        Spacer(Modifier.height(14.dp))
                        Text(
                            prettyCode,
                            color = HaraanColors.TextPrimary,
                            fontSize = 13.sp,
                            fontFamily = FontFamily.Monospace,
                            letterSpacing = 1.sp,
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

                    Spacer(Modifier.height(10.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        StatusChip(booking.status)
                        if (!isVenue) {
                            Text(
                                "${booking.quantity} ticket${if (booking.quantity == 1) "" else "s"}",
                                color = HaraanColors.TextSecondary,
                                fontSize = 12.5.sp,
                            )
                        }
                    }

                    Spacer(Modifier.height(14.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                        Icon(Icons.Outlined.PhoneAndroid, contentDescription = null, tint = HaraanColors.TextSecondary, modifier = Modifier.size(14.dp))
                        Text("Show this at the gate to check in", color = HaraanColors.TextSecondary, fontSize = 12.sp)
                    }
                }
            }
        }
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

@Composable
private fun StatusChip(status: String) {
    val s = status.uppercase()
    val (fg, bg) = when (s) {
        "CONFIRMED" -> Color(0xFF16A34A) to Color(0xFFE7F6EC)
        "CANCELLED", "REFUNDED", "FAILED" -> HaraanColors.LiveRed to Color(0xFFFDECEC)
        else -> HaraanBlue to Color(0xFFE9F3FE)
    }
    Box(
        modifier = Modifier.clip(RoundedCornerShape(20.dp)).background(bg).padding(horizontal = 10.dp, vertical = 4.dp),
    ) {
        Text(s, color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.4.sp)
    }
}
