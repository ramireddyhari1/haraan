package com.haraan.partner.whatsapp.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.haraan.partner.ui.components.HaraanHoldSlotTimerCard
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.whatsapp.model.BookingSuggestion

/**
 * BookingSuggestionCard refactored to use the centralized HaraanHoldSlotTimerCard
 * with configurable 5-minute hold, breathing pulse, and non-jittering tabular numerals.
 */
@Composable
fun BookingSuggestionCard(
    suggestion: BookingSuggestion,
    holdSecondsLeft: Int,
    isLoading: Boolean,
    onHoldAndSendLink: () -> Unit,
    onSendPaymentLink: () -> Unit,
    onMarkPaidCash: () -> Unit,
    onReleaseHold: () -> Unit,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier
) {
    HaraanHoldSlotTimerCard(
        courtName = suggestion.courtName,
        date = suggestion.date,
        timeSlot = "${suggestion.startTime.take(5)} - ${suggestion.endTime.take(5)}",
        price = suggestion.price,
        holdSecondsLeft = holdSecondsLeft,
        isLoading = isLoading,
        onHoldAndSendLink = onHoldAndSendLink,
        onSendPaymentLink = onSendPaymentLink,
        onMarkPaidCash = onMarkPaidCash,
        onReleaseHold = onReleaseHold,
        onDismiss = onDismiss,
        modifier = modifier,
        holdDurationMinutes = HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES,
        sportName = suggestion.sport ?: "Turf"
    )
}