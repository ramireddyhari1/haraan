package com.haraan.app.ui.main

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.provider.CalendarContract
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone
import androidx.compose.animation.core.*
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
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Navigation
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
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
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.QrImage
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanRadius
import com.haraan.app.ui.theme.HaraanSpacing
import com.haraan.app.ui.theme.HaraanTypography

/** The pass's header band — the one dark surface identity sits on. */
private val PassNavy = HaraanColors.GameHubDeep
private val HaraanBlue = HaraanColors.EventsBlue

/**
 * Ultra-Polished Haraan Booking Confirmation Experience.
 *
 * Staged after successful Haraan Pay authorization:
 * - Dynamic celebration header with payment reference & Haraan Pay authentication badge.
 * - Interactive digital entry pass with notched perforation, holographic security shimmer band.
 * - High-definition gate QR code with automatic screen brightness boosting.
 * - Multi-pass carousel navigation if order holds multiple tickets.
 * - Quick post-booking utilities:
 *     1. Google Maps Navigation ("Get Directions")
 *     2. Add to Google Calendar / Outlook
 *     3. Share pass via Android Share Sheet (WhatsApp, Telegram, etc.)
 */
@Composable
fun HaraanBookingConfirmationScreen(
    booking: BookingLite,
    paymentId: String = "",
    passCount: Int = 1,
    onDone: () -> Unit,
) {
    val ctx = LocalContext.current

    // Automatically boost screen brightness for reliable turnstile QR scanning
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

    val code = booking.ticketCode?.takeIf { it.isNotBlank() }
    val prettyCode = code?.chunked(4)?.take(3)?.joinToString(" · ") ?: "—"

    // Infinite holographic shimmer on security band
    // The success moment: the tick settles with a little overshoot, then the pass rises under
    // it a beat later. Two springs, no easing curves invented for the occasion.
    var entered by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { entered = true }
    val markScale by animateFloatAsState(
        targetValue = if (entered) 1f else 0.4f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow),
        label = "successMarkScale",
    )
    val cardScale by animateFloatAsState(
        targetValue = if (entered) 1f else 0.96f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessMediumLow),
        label = "passCardScale",
    )

    val shimmerTransition = rememberInfiniteTransition(label = "hologramShimmer")
    val shimmerOffset by shimmerTransition.animateFloat(
        initialValue = -1f,
        targetValue = 2f,
        animationSpec = infiniteRepeatable(tween(2400, easing = LinearEasing), RepeatMode.Restart),
        label = "shimmerOffset",
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HaraanColors.Background),
        contentAlignment = Alignment.TopCenter,
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp, vertical = 14.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            // ── Top Navigation Row ───────────────────────────────────────────
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "You're going",
                    color = HaraanColors.TextPrimary,
                    fontSize = 19.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(HaraanColors.Field)
                        .clickable(onClick = onDone),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Default.Close,
                        contentDescription = "Done",
                        tint = HaraanColors.TextSecondary,
                        modifier = Modifier.size(18.dp),
                    )
                }
            }

            Spacer(Modifier.height(14.dp))

            // ── Celebration Confirmation Banner ──────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 380.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(HaraanColors.SuccessTint)
                    .border(1.dp, HaraanColors.Success.copy(alpha = 0.22f), RoundedCornerShape(16.dp))
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    modifier = Modifier
                        .size(38.dp)
                        .clip(CircleShape)
                        .background(HaraanColors.Success)
                        .scale(markScale),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Default.Check, contentDescription = null, tint = Color.White, modifier = Modifier.size(22.dp))
                }
                Spacer(Modifier.width(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = if (passCount > 1) "$passCount passes are yours" else "Your pass is ready",
                        color = HaraanColors.TextPrimary,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = if (paymentId.isNotBlank()) "Payment $paymentId" else "Payment confirmed",
                        color = HaraanColors.TextSecondary,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Medium,
                        modifier = Modifier.padding(top = 1.dp),
                    )
                }
            }

            Spacer(Modifier.height(16.dp))

            // ── The Ticket Pass Card ─────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 380.dp)
                    .scale(cardScale)
                    .clip(RoundedCornerShape(22.dp))
                    .background(HaraanColors.Surface),
            ) {
                // Navy Brand Header Band
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(PassNavy)
                        .padding(horizontal = 18.dp, vertical = 13.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Image(
                        painter = painterResource(id = R.drawable.haraan_logo),
                        contentDescription = "Haraan",
                        contentScale = ContentScale.Fit,
                        colorFilter = ColorFilter.tint(Color.White),
                        modifier = Modifier.height(17.dp),
                    )
                    Spacer(Modifier.weight(1f))
                    Text(
                        text = if (passCount > 1) "ENTRY PASS (1 OF $passCount)" else "OFFICIAL ENTRY PASS",
                        color = Color.White.copy(alpha = 0.85f),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.2.sp,
                    )
                }

                // Event & Venue Summary
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
                            .background(HaraanColors.Field),
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
                            text = booking.eventTitle,
                            color = HaraanColors.TextPrimary,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            lineHeight = 21.sp,
                        )
                        booking.eventVenue?.takeIf { it.isNotBlank() }?.let { venueText ->
                            Text(
                                text = venueText,
                                color = HaraanColors.TextSecondary,
                                fontSize = 12.sp,
                                maxLines = 2,
                                overflow = TextOverflow.Ellipsis,
                                modifier = Modifier.padding(top = 3.dp),
                            )
                        }
                    }
                }

                // Pass Details Grid
                val cells = buildList {
                    booking.eventDate?.take(10)?.takeIf { it.isNotBlank() }?.let { add("DATE" to it) }
                    booking.tierName?.takeIf { it.isNotBlank() }?.let { add("TIER" to it) }
                    booking.slotLabel?.takeIf { it.isNotBlank() }?.let { add("TIME" to it) }
                    add("PASSES" to "${booking.quantity} Attendee${if (booking.quantity == 1) "" else "s"}")
                }
                if (cells.isNotEmpty()) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(start = 18.dp, end = 18.dp, top = 16.dp, bottom = 4.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        cells.chunked(2).forEach { row ->
                            Row(modifier = Modifier.fillMaxWidth()) {
                                row.forEach { (label, value) ->
                                    Column(modifier = Modifier.weight(1f)) {
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
                                if (row.size == 1) Spacer(Modifier.weight(1f))
                            }
                        }
                    }
                }

                Spacer(Modifier.height(14.dp))

                // ── Perforation Cut Line ─────────────────────────────────────
                TicketPerforationBar()

                // ── QR Code & Holographic Security Section ───────────────────
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp)
                        .padding(top = 16.dp, bottom = 20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    // Holographic Security Pill
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(20.dp))
                            .background(
                                Brush.linearGradient(
                                    colors = listOf(
                                        HaraanColors.GameHubDeep,
                                        HaraanColors.EventsBlue,
                                        HaraanColors.GameHubDeep,
                                    ),
                                    start = androidx.compose.ui.geometry.Offset(shimmerOffset * 300f, 0f),
                                    end = androidx.compose.ui.geometry.Offset((shimmerOffset + 1f) * 300f, 0f),
                                )
                            )
                            .padding(horizontal = 12.dp, vertical = 4.dp),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            Icon(Icons.Outlined.Shield, contentDescription = null, tint = Color.White, modifier = Modifier.size(12.dp))
                            Text(
                                text = "VERIFIED PASS",
                                color = Color.White,
                                fontSize = 9.5.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 0.8.sp,
                            )
                        }
                    }

                    Spacer(Modifier.height(14.dp))

                    // QR Card with Center Monogram Knockout
                    if (code != null) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(14.dp))
                                .background(HaraanColors.Background)
                                .border(1.dp, HaraanColors.Hairline, RoundedCornerShape(14.dp))
                                .padding(16.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            QrImage(
                                content = "haraan:ticket:$code",
                                sizePx = 720,
                                modifier = Modifier.size(190.dp),
                            )
                            Box(
                                modifier = Modifier
                                    .size(42.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(HaraanColors.Surface)
                                    .padding(6.dp),
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

                        Spacer(Modifier.height(10.dp))

                        Text(
                            text = prettyCode,
                            color = HaraanColors.TextMuted,
                            fontSize = 11.5.sp,
                            fontFamily = FontFamily.Monospace,
                            letterSpacing = 1.4.sp,
                            fontWeight = FontWeight.Medium,
                        )
                    }

                    Spacer(Modifier.height(8.dp))

                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp),
                    ) {
                        Icon(Icons.Outlined.PhoneAndroid, contentDescription = null, tint = HaraanColors.TextMuted, modifier = Modifier.size(12.dp))
                        Text("Screen brightness boosted for gate scanner", color = HaraanColors.TextMuted, fontSize = 11.sp)
                    }
                }

                // ── Action Bar: Calendar, Directions, Share ──────────────────
                Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 8.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceEvenly,
                ) {
                    // 1. Add to Calendar
                    Row(
                        modifier = Modifier
                            .pressable(haptic = Feel.SELECT) {
                                addToCalendar(
                                    ctx,
                                    booking.eventTitle,
                                    booking.eventVenue.orEmpty(),
                                    booking.eventDate,
                                    booking.slotLabel,
                                )
                            }
                            .padding(horizontal = 10.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Outlined.CalendarMonth, contentDescription = null, tint = HaraanBlue, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(5.dp))
                        Text("Calendar", color = HaraanBlue, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
                    }

                    // 2. Directions (Maps)
                    val mapLink = booking.mapLink?.takeIf { it.isNotBlank() }
                    Row(
                        modifier = Modifier
                            .pressable(haptic = Feel.SELECT) {
                                val url = mapLink ?: "geo:0,0?q=${Uri.encode(booking.eventVenue ?: booking.eventTitle)}"
                                openDirections(ctx, url)
                            }
                            .padding(horizontal = 10.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Outlined.Navigation, contentDescription = null, tint = HaraanBlue, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(5.dp))
                        Text("Directions", color = HaraanBlue, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
                    }

                    // 3. Share Ticket
                    Row(
                        modifier = Modifier
                            .pressable(haptic = Feel.SELECT) {
                                shareTicket(ctx, booking.eventTitle, booking.eventDate.orEmpty(), booking.eventVenue.orEmpty(), prettyCode)
                            }
                            .padding(horizontal = 10.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Outlined.Share, contentDescription = null, tint = HaraanBlue, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(5.dp))
                        Text("Share", color = HaraanBlue, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }

            Spacer(Modifier.height(20.dp))

            // ── Primary Done CTA ─────────────────────────────────────────────
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 380.dp)
                    .height(52.dp)
                    .pressable(haptic = Feel.COMMIT, onClick = onDone),
                shape = RoundedCornerShape(16.dp),
                color = HaraanColors.EventsBlue,
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = "View in My Schedule",
                            color = Color.White,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                        )
                        Spacer(Modifier.width(6.dp))
                        Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                    }
                }
            }

            Spacer(Modifier.height(14.dp))
        }
    }
}

@Composable
private fun TicketPerforationBar() {
    Box(modifier = Modifier.fillMaxWidth().height(22.dp)) {
        Box(
            modifier = Modifier
                .align(Alignment.CenterStart)
                .offset(x = (-11).dp)
                .size(22.dp)
                .clip(CircleShape)
                .background(HaraanColors.Background),
        )
        Box(
            modifier = Modifier
                .align(Alignment.CenterEnd)
                .offset(x = 11.dp)
                .size(22.dp)
                .clip(CircleShape)
                .background(HaraanColors.Background),
        )
        Row(
            modifier = Modifier
                .align(Alignment.Center)
                .fillMaxWidth()
                .padding(horizontal = 18.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            repeat(26) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(2.dp)
                        .clip(RoundedCornerShape(1.dp))
                        .background(HaraanColors.BorderLight)
                )
            }
        }
    }
}

private fun openDirections(ctx: Context, uriString: String) {
    runCatching {
        ctx.startActivity(
            Intent(Intent.ACTION_VIEW, Uri.parse(uriString))
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        )
    }
}

/**
 * Hand the booking to the device calendar.
 *
 * The start time matters: without [CalendarContract.EXTRA_EVENT_BEGIN_TIME] the calendar app opens
 * a draft at *now*, which is the one thing the buyer will not notice until they miss the event.
 * [dateStr] arrives as the API serialises it — an ISO timestamp, or a bare `yyyy-MM-dd` for
 * all-day bookings. Anything we cannot parse gets a dateless draft rather than a confidently wrong one.
 */
private fun addToCalendar(
    ctx: Context,
    title: String,
    venue: String,
    dateStr: String?,
    slotLabel: String?,
) {
    runCatching {
        val start = parseBookingStart(dateStr)
        val intent = Intent(Intent.ACTION_INSERT).apply {
            data = CalendarContract.Events.CONTENT_URI
            putExtra(CalendarContract.Events.TITLE, title)
            putExtra(CalendarContract.Events.EVENT_LOCATION, venue)
            putExtra(
                CalendarContract.Events.DESCRIPTION,
                buildString {
                    append("Haraan booking")
                    if (!slotLabel.isNullOrBlank()) append(" · ").append(slotLabel)
                },
            )
            if (start != null) {
                val (beginMillis, allDay) = start
                putExtra(CalendarContract.EXTRA_EVENT_BEGIN_TIME, beginMillis)
                putExtra(CalendarContract.EXTRA_EVENT_END_TIME, beginMillis + 2 * 60 * 60 * 1000L)
                putExtra(CalendarContract.EXTRA_EVENT_ALL_DAY, allDay)
            }
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        ctx.startActivity(intent)
    }
}

private fun shareTicket(ctx: Context, title: String, date: String, venue: String, code: String) {
    runCatching {
        val text = buildString {
            append("I'm going to ").append(title)
            if (date.isNotBlank()) appendLine().append(date)
            if (venue.isNotBlank()) appendLine().append(venue)
            if (code.isNotBlank() && code != "—") appendLine().append("Pass ").append(code)
            appendLine().appendLine().append("Booked on Haraan")
        }
        val sendIntent = Intent().apply {
            action = Intent.ACTION_SEND
            putExtra(Intent.EXTRA_TEXT, text)
            type = "text/plain"
        }
        val shareIntent = Intent.createChooser(sendIntent, "Share your pass")
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        ctx.startActivity(shareIntent)
    }
}

/**
 * Parse whatever the API put in `eventDate` into (startMillis, isAllDay).
 *
 * Bookings come back in a few shapes depending on the endpoint — a full ISO-8601 instant, a
 * `yyyy-MM-dd HH:mm:ss` from Laravel's default datetime cast, or a bare date for an all-day
 * event. Returns null when none of them fit, so the caller can leave the calendar draft undated
 * instead of guessing.
 */
private fun parseBookingStart(dateStr: String?): Pair<Long, Boolean>? {
    val raw = dateStr?.trim()?.takeIf { it.isNotBlank() } ?: return null

    // Timed forms first: a bare-date parser would happily swallow the date half of a timestamp
    // and silently drop the time.
    val timed = listOf(
        "yyyy-MM-dd'T'HH:mm:ss.SSSXXX" to false,
        "yyyy-MM-dd'T'HH:mm:ssXXX" to false,
        "yyyy-MM-dd'T'HH:mm:ss" to true,
        "yyyy-MM-dd HH:mm:ss" to true,
        "yyyy-MM-dd HH:mm" to true,
    )
    for ((pattern, isLocal) in timed) {
        val parsed = runCatching {
            SimpleDateFormat(pattern, Locale.US).apply {
                isLenient = false
                if (!isLocal) timeZone = TimeZone.getTimeZone("UTC")
            }.parse(raw)
        }.getOrNull()
        if (parsed != null) return parsed.time to false
    }

    // All-day: anchor to local midnight so the event lands on the right calendar day.
    val dayOnly = runCatching {
        SimpleDateFormat("yyyy-MM-dd", Locale.US).apply { isLenient = false }.parse(raw.take(10))
    }.getOrNull()
    return dayOnly?.let { it.time to true }
}
