package com.haraan.partner.whatsapp.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.whatsapp.model.ActiveHold
import com.haraan.partner.whatsapp.model.BookingSuggestion

/**
 * The chat's live hold: what's held, the clock, and the three ways it ends — the
 * customer pays the link, the desk takes the money, or the desk lets it go.
 */
@Composable
fun DeskHoldCard(
    hold: ActiveHold,
    secondsLeft: Int,
    totalSeconds: Int,
    busy: Boolean,
    windowOpen: Boolean,
    onSendLink: () -> Unit,
    onMarkPaid: (method: String) -> Unit,
    onRelease: () -> Unit,
    onHoldAgain: () -> Unit
) {
    val view = LocalView.current
    val lapsed = secondsLeft <= 0
    var payMenu by remember { mutableStateOf(false) }

    DeskCardShell(
        background = if (lapsed) Color.White else WhatsAppDeskColors.AmberHoldBg,
        border = if (lapsed) WhatsAppDeskColors.SlateBorder else WhatsAppDeskColors.AmberHoldBorder
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = when {
                    lapsed -> "HOLD LAPSED"
                    hold.linkSent -> "LINK SENT · WAITING FOR PAYMENT"
                    else -> "HELD FOR THIS CUSTOMER"
                },
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 0.5.sp,
                color = if (lapsed) WhatsAppDeskColors.TextSecondary else WhatsAppDeskColors.AmberHold,
                modifier = Modifier.weight(1f)
            )
            if (!lapsed) {
                Text(
                    text = "%d:%02d".format(secondsLeft / 60, secondsLeft % 60),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    style = LocalTextStyle.current.copy(fontFeatureSettings = "tnum"),
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(WhatsAppDeskColors.AmberHold)
                        .padding(horizontal = 10.dp, vertical = 3.dp)
                )
            }
        }

        if (!lapsed && totalSeconds > 0) {
            Spacer(Modifier.height(8.dp))
            LinearProgressIndicator(
                progress = { (secondsLeft.toFloat() / totalSeconds).coerceIn(0f, 1f) },
                modifier = Modifier.fillMaxWidth().height(3.dp).clip(RoundedCornerShape(50)),
                color = WhatsAppDeskColors.AmberHold,
                trackColor = WhatsAppDeskColors.AmberHoldBorder,
                drawStopIndicator = {}
            )
        }

        Spacer(Modifier.height(10.dp))
        SlotLine(
            court = hold.courtName ?: "Court",
            date = hold.date,
            start = hold.startTime,
            end = hold.endTime,
            price = hold.amount
        )
        Spacer(Modifier.height(12.dp))

        if (lapsed) {
            Text(
                "The court is free again. Hold it again if the customer still wants it.",
                fontSize = 12.sp,
                color = WhatsAppDeskColors.TextSecondary
            )
            Spacer(Modifier.height(10.dp))
            DeskPrimaryButton(text = "Hold again", busy = busy, onClick = onHoldAgain, modifier = Modifier.fillMaxWidth())
            return@DeskCardShell
        }

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            DeskPrimaryButton(
                text = if (hold.linkSent) "Resend link" else "Send payment link",
                busy = busy,
                enabled = windowOpen,
                onClick = onSendLink,
                modifier = Modifier.weight(1f)
            )
            Box {
                OutlinedButton(
                    onClick = { payMenu = true },
                    enabled = !busy,
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, WhatsAppDeskColors.Action),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = WhatsAppDeskColors.Action),
                    modifier = Modifier.height(44.dp)
                ) { Text("Paid here", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }

                DropdownMenu(expanded = payMenu, onDismissRequest = { payMenu = false }) {
                    DropdownMenuItem(text = { Text("Cash at the counter") }, onClick = {
                        payMenu = false
                        Haptics.money(view)
                        onMarkPaid("cash")
                    })
                    DropdownMenuItem(text = { Text("UPI to the venue") }, onClick = {
                        payMenu = false
                        Haptics.money(view)
                        onMarkPaid("upi")
                    })
                }
            }
        }

        TextButton(
            onClick = onRelease,
            enabled = !busy,
            contentPadding = PaddingValues(horizontal = 0.dp),
            colors = ButtonDefaults.textButtonColors(contentColor = WhatsAppDeskColors.Danger)
        ) { Text("Release the court", fontSize = 13.sp) }

        if (!windowOpen) {
            Text(
                "The customer hasn’t messaged in 24 hours, so WhatsApp won’t deliver a link. Call them, or take payment here.",
                fontSize = 11.sp,
                color = WhatsAppDeskColors.TextSecondary
            )
        }
    }
}

/** What the customer asked for, ready to hold in one tap — or to adjust first. */
@Composable
fun DeskSuggestionCard(
    suggestion: BookingSuggestion,
    busy: Boolean,
    onHold: () -> Unit,
    onChange: () -> Unit,
    onDismiss: () -> Unit
) {
    DeskCardShell(background = Color.White, border = WhatsAppDeskColors.ActionBorder) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "THE CUSTOMER ASKED FOR",
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 0.5.sp,
                color = WhatsAppDeskColors.Action,
                modifier = Modifier.weight(1f)
            )
            TextButton(onClick = onDismiss, contentPadding = PaddingValues(horizontal = 6.dp)) {
                Text("Not now", fontSize = 12.sp, color = WhatsAppDeskColors.TextSecondary)
            }
        }
        SlotLine(
            court = suggestion.courtName ?: "Court",
            date = suggestion.date ?: "",
            start = suggestion.startTime ?: "",
            end = suggestion.endTime ?: "",
            price = suggestion.price
        )
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            DeskPrimaryButton(text = "Hold this slot", busy = busy, onClick = onHold, modifier = Modifier.weight(1f))
            OutlinedButton(
                onClick = onChange,
                enabled = !busy,
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp, WhatsAppDeskColors.Action),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = WhatsAppDeskColors.Action),
                modifier = Modifier.height(44.dp)
            ) { Text("Change", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
        }
    }
}

/** No hold and nothing readable in the chat: one quiet way in to the slot picker. */
@Composable
fun HoldSlotPrompt(onPick: () -> Unit) {
    Surface(color = Color.White, shadowElevation = 1.dp) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                "Booking for this customer?",
                fontSize = 13.sp,
                color = WhatsAppDeskColors.TextSecondary,
                modifier = Modifier.weight(1f)
            )
            TextButton(onClick = onPick) {
                Text("Hold a slot", fontWeight = FontWeight.SemiBold, color = WhatsAppDeskColors.Action)
            }
        }
    }
}

@Composable
private fun SlotLine(court: String, date: String, start: String, end: String, price: Double?) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(court, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = WhatsAppDeskColors.TextPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                "${DeskTime.day(date)} · ${DeskTime.slot(start)} – ${DeskTime.slot(end)}",
                fontSize = 12.sp,
                color = WhatsAppDeskColors.TextSecondary
            )
        }
        if (price != null && price > 0) {
            Text(
                DeskTime.rupees(price),
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = WhatsAppDeskColors.TextPrimary,
                style = LocalTextStyle.current.copy(fontFeatureSettings = "tnum")
            )
        }
    }
}

@Composable
private fun DeskCardShell(background: Color, border: Color, content: @Composable ColumnScope.() -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 8.dp),
        shape = RoundedCornerShape(16.dp),
        color = background,
        border = BorderStroke(1.dp, border),
        shadowElevation = 2.dp
    ) {
        Column(Modifier.padding(14.dp), content = content)
    }
}

@Composable
fun DeskPrimaryButton(
    text: String,
    busy: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true
) {
    val view = LocalView.current
    Button(
        onClick = {
            Haptics.tick(view)
            onClick()
        },
        enabled = enabled && !busy,
        shape = RoundedCornerShape(12.dp),
        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppDeskColors.Action),
        contentPadding = PaddingValues(horizontal = 14.dp),
        modifier = modifier.height(44.dp)
    ) {
        if (busy) {
            CircularProgressIndicator(Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
        } else {
            Text(text, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, maxLines = 1)
        }
    }
}
