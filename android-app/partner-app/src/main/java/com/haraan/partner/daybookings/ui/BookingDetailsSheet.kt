package com.haraan.partner.daybookings.ui

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Chat
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.daybookings.model.DayBookingItem

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val RedColor = Color(0xFFDC2626)
private val CardBorder = Color(0xFFE5E7EB)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingDetailsSheet(
    booking: DayBookingItem,
    onDismiss: () -> Unit,
    onCheckIn: (ticketCode: String) -> Unit,
    onCancel: (bookingId: Long) -> Unit,
) {
    val context = LocalContext.current
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var showCancelConfirmDialog by remember { mutableStateOf(false) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp, vertical = 8.dp),
        ) {
            // Header Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            booking.ticketCode,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = InkDark,
                        )
                        Spacer(Modifier.width(8.dp))
                        Icon(
                            Icons.Filled.ContentCopy,
                            contentDescription = "Copy Ticket Code",
                            tint = PrimaryBlue,
                            modifier = Modifier
                                .size(16.dp)
                                .clickable {
                                    val cm = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                    cm.setPrimaryClip(ClipData.newPlainText("Ticket Code", booking.ticketCode))
                                    Toast.makeText(context, "Ticket code copied", Toast.LENGTH_SHORT).show()
                                },
                        )
                    }
                    Text(
                        "${booking.slotTime} · ${booking.slotDate}",
                        fontSize = 12.sp,
                        color = MutedGray,
                    )
                }

                IconButton(onClick = onDismiss) {
                    Icon(Icons.Filled.Close, contentDescription = "Close", tint = MutedGray)
                }
            }

            Spacer(Modifier.height(16.dp))

            // Customer Identity Card
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(Color(0xFFF8FAFC))
                    .border(1.dp, CardBorder, RoundedCornerShape(14.dp))
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(if (booking.isWalkIn) Color(0xFFE0F2FE) else Color(0xFFDBEAFE)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        booking.customerName.trim().take(1).uppercase(),
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (booking.isWalkIn) Color(0xFF0369A1) else PrimaryBlue,
                    )
                }

                Spacer(Modifier.width(12.dp))

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        booking.customerName,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = InkDark,
                    )
                    Text(
                        if (booking.isWalkIn) "Walk-in Desk Customer" else "Online App Booker",
                        fontSize = 11.5.sp,
                        color = MutedGray,
                    )
                }

                // Phone & WhatsApp Shortcuts
                booking.phone?.let { phone ->
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        IconButton(
                            onClick = {
                                val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))
                                context.startActivity(intent)
                            },
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFEFF6FF)),
                        ) {
                            Icon(Icons.Filled.Phone, contentDescription = "Call", tint = PrimaryBlue, modifier = Modifier.size(18.dp))
                        }

                        IconButton(
                            onClick = {
                                val clean = phone.filter { it.isDigit() }
                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$clean"))
                                context.startActivity(intent)
                            },
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFDCFCE7)),
                        ) {
                            Icon(Icons.AutoMirrored.Filled.Chat, contentDescription = "WhatsApp", tint = GreenColor, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // Financial Ledger Breakdown Card
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(Color(0xFFF8FAFC))
                    .border(1.dp, CardBorder, RoundedCornerShape(14.dp))
                    .padding(14.dp),
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text("Total Booking Price", fontSize = 13.sp, color = MutedGray)
                    Text("₹" + booking.totalAmount.toInt(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = InkDark)
                }
                Spacer(Modifier.height(6.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text("Amount Paid", fontSize = 13.sp, color = MutedGray)
                    Text("₹" + booking.amountPaid.toInt(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = GreenColor)
                }
                if (booking.balanceDue > 0) {
                    Spacer(Modifier.height(6.dp))
                    HorizontalDivider(color = Color(0xFFE2E8F0))
                    Spacer(Modifier.height(6.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                    ) {
                        Text("Outstanding Balance", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = RedColor)
                        Text("₹" + booking.balanceDue.toInt(), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, color = RedColor)
                    }
                }
                Spacer(Modifier.height(8.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("Payment Method", fontSize = 12.sp, color = MutedGray)
                    Text(
                        booking.paymentMethod?.uppercase() ?: "ONLINE",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryBlue,
                    )
                }
            }

            Spacer(Modifier.height(20.dp))

            // Action Buttons
            if (!booking.isCancelled) {
                if (!booking.isCheckedIn) {
                    Button(
                        onClick = { onCheckIn(booking.ticketCode) },
                        colors = ButtonDefaults.buttonColors(containerColor = GreenColor),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp),
                    ) {
                        Icon(Icons.Filled.Check, contentDescription = null, tint = Color.White)
                        Spacer(Modifier.width(8.dp))
                        Text("Check-In Attendee", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    }
                    Spacer(Modifier.height(10.dp))
                }

                OutlinedButton(
                    onClick = { showCancelConfirmDialog = true },
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = RedColor),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFFCA5A5)),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(46.dp),
                ) {
                    Icon(Icons.Filled.Delete, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("Cancel Booking", fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
                }
            } else {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(Color(0x14DC2626))
                        .padding(12.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("This booking was cancelled", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = RedColor)
                }
            }

            Spacer(Modifier.height(12.dp))
        }
    }

    if (showCancelConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showCancelConfirmDialog = false },
            title = { Text("Cancel Booking?") },
            text = { Text("Are you sure you want to cancel booking ${booking.ticketCode}? This will free up the court/slot immediately.") },
            confirmButton = {
                Button(
                    onClick = {
                        showCancelConfirmDialog = false
                        onCancel(booking.id)
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = RedColor)
                ) {
                    Text("Yes, Cancel")
                }
            },
            dismissButton = {
                TextButton(onClick = { showCancelConfirmDialog = false }) {
                    Text("Keep Booking")
                }
            }
        )
    }
}
