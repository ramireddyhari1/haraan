package com.haraan.partner.daybookings.ui

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Chat
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.daybookings.model.DayBookingItem

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val RedColor = Color(0xFFDC2626)
private val AmberColor = Color(0xFFB45309)
private val CardBorder = Color(0xFFE5E7EB)

@Composable
fun DayBookingsList(
    bookings: List<DayBookingItem>,
    onBookingClick: (DayBookingItem) -> Unit,
    onQuickCheckIn: (DayBookingItem) -> Unit,
    modifier: Modifier = Modifier,
) {
    if (bookings.isEmpty()) {
        Box(
            modifier = modifier.fillMaxSize(),
            contentAlignment = Alignment.Center,
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    "No bookings matching criteria",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = InkDark,
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    "Try adjusting your search or filters",
                    fontSize = 12.sp,
                    color = MutedGray,
                )
            }
        }
    } else {
        LazyColumn(
            modifier = modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(top = 4.dp, bottom = 80.dp),
        ) {
            items(bookings, key = { it.id }) { booking ->
                BookingCard(
                    booking = booking,
                    onClick = { onBookingClick(booking) },
                    onCheckIn = { onQuickCheckIn(booking) },
                )
            }
        }
    }
}

@Composable
private fun BookingCard(
    booking: DayBookingItem,
    onClick: () -> Unit,
    onCheckIn: () -> Unit,
) {
    val context = LocalContext.current
    val walkIn = booking.isWalkIn
    val unpaid = booking.isDue

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, CardBorder, RoundedCornerShape(16.dp))
            .clickable(onClick = onClick)
            .padding(14.dp),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            // Customer Initial Icon
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(CircleShape)
                    .background(if (walkIn) Color(0xFFE0F2FE) else Color(0xFFDBEAFE)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    booking.customerName.trim().take(1).uppercase(),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (walkIn) Color(0xFF0369A1) else PrimaryBlue,
                )
            }

            Spacer(Modifier.width(12.dp))

            // Details
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        booking.customerName,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = InkDark,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false),
                    )

                    Spacer(Modifier.width(6.dp))

                    if (booking.isCancelled) {
                        Text(
                            "CANCELLED",
                            fontSize = 8.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = RedColor,
                            modifier = Modifier
                                .clip(RoundedCornerShape(99.dp))
                                .background(Color(0x14DC2626))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    } else if (booking.isCheckedIn) {
                        Text(
                            "✓ CHECKED-IN",
                            fontSize = 8.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = GreenColor,
                            modifier = Modifier
                                .clip(RoundedCornerShape(99.dp))
                                .background(Color(0x1ADC2626))
                                .background(Color(0x1A16A34A))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }

                Spacer(Modifier.height(3.dp))

                Text(
                    listOfNotNull(
                        if (walkIn) "Walk-in" else "Online",
                        booking.slotTime.takeIf { it.isNotBlank() },
                        booking.ticketCode,
                    ).joinToString(" · "),
                    fontSize = 11.5.sp,
                    color = MutedGray,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            Spacer(Modifier.width(8.dp))

            // Price & Payment Status
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "₹" + formatInr(booking.totalAmount),
                    fontSize = 15.5.sp,
                    fontWeight = FontWeight.ExtraBold,
                    color = if (booking.isCancelled) MutedGray else InkDark,
                )
                Spacer(Modifier.height(2.dp))
                if (!booking.isCancelled) {
                    when {
                        unpaid -> {
                            Text(
                                "DUE: ₹${formatInr(booking.balanceDue)}",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = RedColor,
                            )
                        }
                        else -> {
                            Text(
                                booking.paymentMethod?.uppercase() ?: "PAID",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = GreenColor,
                            )
                        }
                    }
                }
            }
        }

        // Quick Actions Row (Phone Call, WhatsApp, Fast Check-In)
        if (!booking.isCancelled) {
            Spacer(Modifier.height(10.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    booking.phone?.let { phone ->
                        Box(
                            modifier = Modifier
                                .size(30.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFF1F5F9))
                                .clickable {
                                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))
                                    context.startActivity(intent)
                                },
                            contentAlignment = Alignment.Center,
                        ) {
                            Icon(Icons.Filled.Phone, contentDescription = "Call", tint = PrimaryBlue, modifier = Modifier.size(14.dp))
                        }

                        Box(
                            modifier = Modifier
                                .size(30.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFDCFCE7))
                                .clickable {
                                    val cleanPhone = phone.filter { it.isDigit() }
                                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$cleanPhone"))
                                    context.startActivity(intent)
                                },
                            contentAlignment = Alignment.Center,
                        ) {
                            Icon(Icons.AutoMirrored.Filled.Chat, contentDescription = "WhatsApp", tint = GreenColor, modifier = Modifier.size(14.dp))
                        }
                    }
                }

                if (!booking.isCheckedIn) {
                    OutlinedButton(
                        onClick = onCheckIn,
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp),
                        modifier = Modifier.height(28.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = GreenColor),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFF86EFAC)),
                    ) {
                        Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(12.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Check-in", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

private fun formatInr(amount: Double): String {
    val rounded = Math.round(amount)
    return java.text.NumberFormat.getNumberInstance(java.util.Locale.forLanguageTag("en-IN")).format(rounded)
}
