package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.layout.ContentScale
import com.example.thanna.data.BookingLite
import com.example.thanna.ui.theme.HaraanColors

/**
 * Bell icon → in-app notifications. There is no `notifications` table/route in the backend yet
 * ([[haraan-realtime-updates]] Reverb backbone exists but nothing persists a per-user feed), so
 * this is an honest, polished empty state rather than a faked list. It IS a working screen —
 * it opens, reads intentional, and is the seam we deepen into a real feed once the API exists.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsSheet(onDismiss: () -> Unit) {
    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 32.dp),
        ) {
            Text("Notifications", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = HaraanColors.TextPrimary)
            Spacer(Modifier.height(28.dp))
            Column(
                modifier = Modifier.fillMaxWidth().padding(vertical = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Box(
                    modifier = Modifier
                        .size(72.dp)
                        .clip(CircleShape)
                        .background(Color(0xFFEFF3F8)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Outlined.NotificationsNone,
                        contentDescription = null,
                        tint = HaraanColors.GameHubDeep,
                        modifier = Modifier.size(34.dp),
                    )
                }
                Spacer(Modifier.height(16.dp))
                Text(
                    "You're all caught up",
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    color = HaraanColors.TextPrimary,
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    "We'll let you know here about match invites, booking updates and new events near you.",
                    fontSize = 13.sp,
                    color = HaraanColors.TextSecondary,
                    modifier = Modifier.padding(horizontal = 24.dp),
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                )
            }
        }
    }
}

/**
 * Calendar icon → "My Schedule": the user's OWN booked events and venue slots (from
 * `GET /api/bookings`), day-grouped. Tapping a row opens its scannable entry-pass QR
 * ([TicketPassScreen]) to show at check-in. Not the public events feed — only what you've booked.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ScheduleSheet(
    onDismiss: () -> Unit,
    fetchBookings: suspend () -> List<BookingLite>,
    onBookingClick: (BookingLite) -> Unit,
) {
    // null = still loading; empty list = loaded, nothing booked.
    var bookings by remember { mutableStateOf<List<BookingLite>?>(null) }
    LaunchedEffect(Unit) {
        bookings = runCatching { fetchBookings() }.getOrNull().orEmpty()
    }

    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 200.dp)
                .padding(bottom = 24.dp),
        ) {
            Column(modifier = Modifier.padding(horizontal = 20.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Outlined.CalendarMonth,
                        contentDescription = null,
                        tint = HaraanColors.GameHubDeep,
                        modifier = Modifier.size(22.dp),
                    )
                    Spacer(Modifier.width(8.dp))
                    Text("My Schedule", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = HaraanColors.TextPrimary)
                }
                Spacer(Modifier.height(2.dp))
                Text("Your booked events & venues · tap for pass", fontSize = 13.sp, color = HaraanColors.TextSecondary)
            }
            Spacer(Modifier.height(16.dp))

            val list = bookings
            when {
                list == null -> Box(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 40.dp),
                    contentAlignment = Alignment.Center,
                ) { CircularProgressIndicator(color = HaraanColors.GameHubDeep, strokeWidth = 2.5.dp) }

                list.isEmpty() -> Column(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 32.dp, horizontal = 24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(
                        Icons.Outlined.CalendarMonth,
                        contentDescription = null,
                        tint = HaraanColors.TextSecondary,
                        modifier = Modifier.size(40.dp),
                    )
                    Spacer(Modifier.height(12.dp))
                    Text("Nothing booked yet", fontWeight = FontWeight.SemiBold, fontSize = 15.sp, color = HaraanColors.TextPrimary)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Events and venue slots you book will show up here with an entry pass.",
                        fontSize = 13.sp,
                        color = HaraanColors.TextSecondary,
                        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    )
                }

                else -> {
                    // Group by day (best-effort off the stored date); most-recent booking first.
                    val grouped = list.groupBy { (it.eventDate?.take(10)).orEmpty().ifBlank { "Booked" } }
                    LazyColumn(
                        modifier = Modifier.fillMaxWidth().heightIn(max = 460.dp),
                        contentPadding = PaddingValues(bottom = 8.dp),
                    ) {
                        grouped.forEach { (day, dayBookings) ->
                            item(key = "hdr_$day") {
                                Text(
                                    day,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 13.sp,
                                    color = HaraanColors.GameHubDeep,
                                    modifier = Modifier.padding(start = 20.dp, end = 20.dp, top = 12.dp, bottom = 6.dp),
                                )
                            }
                            items(dayBookings, key = { it.id }) { b ->
                                ScheduleRow(b = b, onClick = { onBookingClick(b) })
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ScheduleRow(b: BookingLite, onClick: () -> Unit) {
    val isVenue = b.type == "venue"
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 20.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // Real event poster / venue image so a row is recognisable at a glance; falls back to a
        // calendar tile when there's no creative.
        Box(
            modifier = Modifier
                .size(52.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFFEFF3F8)),
            contentAlignment = Alignment.Center,
        ) {
            if (!b.imageUrl.isNullOrBlank()) {
                coil.compose.AsyncImage(
                    model = b.imageUrl,
                    contentDescription = b.eventTitle,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(12.dp)),
                )
            } else {
                Icon(
                    Icons.Outlined.CalendarMonth,
                    contentDescription = null,
                    tint = HaraanColors.GameHubDeep,
                    modifier = Modifier.size(22.dp),
                )
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                b.eventTitle,
                fontWeight = FontWeight.SemiBold,
                fontSize = 14.sp,
                color = HaraanColors.TextPrimary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            val sub = listOfNotNull(
                if (isVenue) b.slotLabel else "${b.quantity} ticket${if (b.quantity == 1) "" else "s"}",
                b.eventVenue,
            ).filter { it.isNotBlank() }.joinToString(" • ")
            if (sub.isNotBlank()) {
                Text(sub, fontSize = 12.sp, color = HaraanColors.TextSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        Icon(
            Icons.AutoMirrored.Filled.KeyboardArrowRight,
            contentDescription = null,
            tint = HaraanColors.TextSecondary,
            modifier = Modifier.size(20.dp),
        )
    }
}
