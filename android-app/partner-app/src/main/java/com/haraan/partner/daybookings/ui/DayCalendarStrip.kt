package com.haraan.partner.daybookings.ui

import android.app.DatePickerDialog
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val CardBorder = Color(0xFFE5E7EB)

@Composable
fun DayCalendarStrip(
    selectedMillis: Long,
    onSelectDay: (Long) -> Unit,
    onPrevDay: () -> Unit,
    onNextDay: () -> Unit,
    onToday: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val listState = rememberLazyListState()

    // Generate a 21-day window around the selected date (-7 days to +14 days)
    val dayPills = remember(selectedMillis) {
        val list = mutableListOf<DayPill>()
        val cal = Calendar.getInstance().apply {
            timeInMillis = selectedMillis
            add(Calendar.DAY_OF_YEAR, -7)
        }
        for (i in 0..21) {
            val pillCal = Calendar.getInstance().apply { timeInMillis = cal.timeInMillis }
            list.add(
                DayPill(
                    millis = pillCal.timeInMillis,
                    dayOfWeek = SimpleDateFormat("EEE", Locale.US).format(pillCal.time).uppercase(),
                    dayNumber = SimpleDateFormat("dd", Locale.US).format(pillCal.time),
                    isToday = isSameDay(pillCal.timeInMillis, System.currentTimeMillis()),
                    isSelected = isSameDay(pillCal.timeInMillis, selectedMillis),
                )
            )
            cal.add(Calendar.DAY_OF_YEAR, 1)
        }
        list
    }

    LaunchedEffect(selectedMillis) {
        val index = dayPills.indexOfFirst { it.isSelected }
        if (index >= 0) {
            val target = (index - 2).coerceAtLeast(0)
            listState.animateScrollToItem(target)
        }
    }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, CardBorder, RoundedCornerShape(16.dp))
            .padding(vertical = 10.dp)
    ) {
        // Top Row: Previous, Friendly Day Heading, Calendar Picker, Next
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            IconButton(onClick = onPrevDay) {
                Icon(Icons.Filled.ChevronLeft, contentDescription = "Previous Day", tint = PrimaryBlue)
            }

            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clickable {
                    val c = Calendar.getInstance().apply { timeInMillis = selectedMillis }
                    DatePickerDialog(
                        context,
                        { _, year, month, dayOfMonth ->
                            val picked = Calendar.getInstance().apply {
                                set(year, month, dayOfMonth, 12, 0, 0)
                            }
                            onSelectDay(picked.timeInMillis)
                        },
                        c.get(Calendar.YEAR),
                        c.get(Calendar.MONTH),
                        c.get(Calendar.DAY_OF_MONTH)
                    ).show()
                }
            ) {
                Icon(Icons.Filled.CalendarMonth, contentDescription = null, tint = PrimaryBlue, modifier = Modifier.size(17.dp))
                Spacer(Modifier.width(6.dp))
                Text(
                    formatFriendlyHeader(selectedMillis),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = InkDark,
                )
            }

            IconButton(onClick = onNextDay) {
                Icon(Icons.Filled.ChevronRight, contentDescription = "Next Day", tint = PrimaryBlue)
            }
        }

        Spacer(Modifier.height(6.dp))

        // Horizontal scrolling day pills
        LazyRow(
            state = listState,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp)
        ) {
            items(dayPills, key = { it.millis }) { pill ->
                val bg = when {
                    pill.isSelected -> PrimaryBlue
                    pill.isToday -> Color(0xFFEFF6FF)
                    else -> Color(0xFFF8FAFC)
                }
                val border = when {
                    pill.isSelected -> PrimaryBlue
                    pill.isToday -> Color(0xFFBFDBFE)
                    else -> Color(0xFFE2E8F0)
                }
                val textColor = when {
                    pill.isSelected -> Color.White
                    else -> InkDark
                }
                val subTextColor = when {
                    pill.isSelected -> Color(0xFFDBEAFE)
                    else -> MutedGray
                }

                Column(
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(bg)
                        .border(1.dp, border, RoundedCornerShape(12.dp))
                        .clickable { onSelectDay(pill.millis) }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(pill.dayOfWeek, fontSize = 9.5.sp, fontWeight = FontWeight.SemiBold, color = subTextColor)
                    Spacer(Modifier.height(2.dp))
                    Text(pill.dayNumber, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = textColor)
                    if (pill.isToday && !pill.isSelected) {
                        Box(
                            Modifier
                                .size(4.dp)
                                .clip(RoundedCornerShape(99.dp))
                                .background(PrimaryBlue)
                        )
                    }
                }
            }
        }
    }
}

private data class DayPill(
    val millis: Long,
    val dayOfWeek: String,
    val dayNumber: String,
    val isToday: Boolean,
    val isSelected: Boolean,
)

private fun isSameDay(m1: Long, m2: Long): Boolean {
    val c1 = Calendar.getInstance().apply { timeInMillis = m1 }
    val c2 = Calendar.getInstance().apply { timeInMillis = m2 }
    return c1.get(Calendar.YEAR) == c2.get(Calendar.YEAR) &&
        c1.get(Calendar.DAY_OF_YEAR) == c2.get(Calendar.DAY_OF_YEAR)
}

private fun formatFriendlyHeader(millis: Long): String {
    val now = System.currentTimeMillis()
    val calPicked = Calendar.getInstance().apply { timeInMillis = millis }
    val calNow = Calendar.getInstance().apply { timeInMillis = now }

    val diffDays = (calPicked.get(Calendar.DAY_OF_YEAR) - calNow.get(Calendar.DAY_OF_YEAR)) +
        (calPicked.get(Calendar.YEAR) - calNow.get(Calendar.YEAR)) * 365

    val fullDate = SimpleDateFormat("EEE, dd MMM", Locale.getDefault()).format(calPicked.time).uppercase()
    return when (diffDays) {
        0 -> "TODAY · $fullDate"
        1 -> "TOMORROW · $fullDate"
        -1 -> "YESTERDAY · $fullDate"
        else -> fullDate
    }
}
