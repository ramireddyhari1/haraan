package com.haraan.partner.ui.desk

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ApiException
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.Problem
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import com.haraan.partner.ui.components.pressableTile
import java.text.SimpleDateFormat
import java.util.Locale

/*
 * Shared pieces for the desk tools (Academy, Packages): one palette, one way to
 * load and fail, one payment-method picker. Kept small on purpose; each screen
 * draws its own illustrations.
 */

internal val DInk = Color(0xFF0F172A)
internal val DMuted = Color(0xFF64748B)
internal val DFaint = Color(0xFF94A3B8)
internal val DHair = Color(0xFFE2E8F0)
internal val DPage = Color(0xFFF6F8FC)
internal val DBlue = Color(0xFF2563EB)
internal val DBlueTint = Color(0xFFEFF4FF)
internal val DNavy = Color(0xFF0B1C46)
internal val DGreen = Color(0xFF16A34A)
internal val DAmber = Color(0xFFD97706)
internal val DRed = Color(0xFFDC2626)

internal sealed interface DeskLoad<out T> {
    data object Loading : DeskLoad<Nothing>
    data class Failed(val problem: Problem, val message: String) : DeskLoad<Nothing>
    data class Ready<T>(val value: T) : DeskLoad<T>
}

internal fun Throwable.readable(): String = when (this) {
    is ApiException -> message ?: "Something went wrong"
    is java.io.IOException -> "No connection. Check the internet and try again."
    else -> message ?: "Something went wrong"
}

internal suspend fun <T> deskLoad(block: suspend () -> T): DeskLoad<T> = try {
    DeskLoad.Ready(block())
} catch (e: ApiException) {
    DeskLoad.Failed(if (e.code == 401) Problem.SignedOut else if (e.code >= 500) Problem.Server else Problem.Other, e.readable())
} catch (e: java.io.IOException) {
    DeskLoad.Failed(Problem.Offline, e.readable())
} catch (e: Exception) {
    DeskLoad.Failed(Problem.Other, e.readable())
}

/** A light top bar: back, title, and an optional action on the right. */
@Composable
internal fun DeskTopBar(title: String, onBack: () -> Unit, action: (@Composable () -> Unit)? = null) {
    Row(
        Modifier.fillMaxWidth().background(DPage).statusBarsPadding().padding(start = 4.dp, end = 12.dp, top = 4.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = DInk) }
        Text(title, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = DInk, modifier = Modifier.weight(1f), maxLines = 1)
        action?.invoke()
    }
}

/** The blue pill for the one "add" action a screen has. */
@Composable
internal fun AddPill(label: String, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val view = LocalView.current
    Row(
        Modifier
            .pressScale(interaction, 0.94f)
            .clip(RoundedCornerShape(999.dp))
            .background(DBlue)
            .pressShade(interaction, 0.12f)
            .clickable(interaction, indication = null) { Haptics.tick(view); onClick() }
            .heightIn(min = 40.dp)
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(12.dp)) {
            val w = 2.2.dp.toPx()
            drawLine(Color.White, Offset(size.width / 2, 0f), Offset(size.width / 2, size.height), w, StrokeCap.Round)
            drawLine(Color.White, Offset(0f, size.height / 2), Offset(size.width, size.height / 2), w, StrokeCap.Round)
        }
        Spacer(Modifier.width(8.dp))
        Text(label, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
internal fun DeskSection(text: String, modifier: Modifier = Modifier) {
    Text(text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted, modifier = modifier.padding(start = 4.dp, top = 10.dp, bottom = 2.dp))
}

@Composable
internal fun DeskSkeleton(heroHeight: Int = 220, rows: Int = 3) {
    val x by rememberInfiniteTransition(label = "sk").animateFloat(
        -600f, 1400f, infiniteRepeatable(tween(1250, easing = LinearEasing)), label = "sweep",
    )
    val sweep = Brush.linearGradient(
        listOf(Color(0xFFEDF1F7), Color(0xFFF7FAFE), Color(0xFFEDF1F7)),
        start = Offset(x, 0f), end = Offset(x + 600f, 0f),
    )
    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.fillMaxWidth().height(heroHeight.dp).clip(RoundedCornerShape(24.dp)).background(sweep))
        repeat(rows) { Box(Modifier.fillMaxWidth().height(96.dp).clip(RoundedCornerShape(20.dp)).background(sweep)) }
    }
}

@Composable
internal fun DeskField(
    value: String,
    onChange: (String) -> Unit,
    label: String,
    type: KeyboardType = KeyboardType.Text,
    modifier: Modifier = Modifier.fillMaxWidth(),
    prefix: String? = null,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        label = { Text(label) },
        singleLine = true,
        prefix = prefix?.let { { Text(it, color = DMuted) } },
        keyboardOptions = KeyboardOptions(keyboardType = type),
        shape = RoundedCornerShape(14.dp),
        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = DBlue, focusedLabelColor = DBlue, unfocusedBorderColor = DHair),
        modifier = modifier,
    )
}

/** A selectable chip: blue tint + blue border when on, never a solid fill. */
@Composable
internal fun DeskChip(label: String, selected: Boolean, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val border by animateColorAsState(if (selected) DBlue else DHair, label = "dc")
    val bg by animateColorAsState(if (selected) DBlueTint else Color.White, label = "dcb")
    Box(
        modifier
            .pressableTile(12.dp, pressedScale = 0.95f, onClick = onClick)
            .clip(RoundedCornerShape(12.dp))
            .background(bg)
            .border(if (selected) 1.5.dp else 1.dp, border, RoundedCornerShape(12.dp))
            .heightIn(min = 44.dp)
            .padding(horizontal = 14.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = 14.sp, fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium, color = if (selected) DBlue else DInk, maxLines = 1)
    }
}

internal enum class DeskMethod(val api: String, val label: String) { CASH("cash", "Cash"), UPI("upi", "UPI"), CARD("card", "Card") }

/** How the money was taken. Cash goes in the drawer; UPI and card are matched to statements. */
@Composable
internal fun MethodPicker(selected: DeskMethod, onPick: (DeskMethod) -> Unit) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        DeskMethod.entries.forEach { m ->
            DeskChip(m.label, selected == m, Modifier.weight(1f)) { onPick(m) }
        }
    }
}

internal fun methodLabel(api: String?): String = when (api) {
    "cash" -> "Cash"
    "upi" -> "UPI"
    "card" -> "Card"
    "opening" -> "Before records"
    null, "" -> "—"
    else -> api.replaceFirstChar { it.uppercase() }
}

/** "2026-11-12" → "12 Nov". */
internal fun shortDate(iso: String?): String? {
    if (iso.isNullOrBlank()) return null
    return runCatching {
        // A full timestamp ("…T19:01:00+00:00") is an instant: show it on the
        // phone's own calendar, or a 12:30 AM payment reads as yesterday.
        val d = if (iso.length > 10) {
            listOf("yyyy-MM-dd'T'HH:mm:ssXXX", "yyyy-MM-dd'T'HH:mm:ss.SSSSSSXXX", "yyyy-MM-dd'T'HH:mm:ss.SSSXXX")
                .firstNotNullOfOrNull { p -> runCatching { SimpleDateFormat(p, Locale.US).parse(iso) }.getOrNull() }
        } else null
        val day = d ?: SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso.take(10)) ?: return null
        SimpleDateFormat("d MMM", Locale.ENGLISH).format(day)
    }.getOrNull()
}

/** Today in the server's date format. */
internal fun todayIso(): String = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(java.util.Date())

internal fun shiftIso(iso: String, days: Int): String {
    val f = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    val c = java.util.Calendar.getInstance()
    c.time = f.parse(iso) ?: java.util.Date()
    c.add(java.util.Calendar.DAY_OF_YEAR, days)
    return f.format(c.time)
}

/**
 * Open WhatsApp on the partner's own phone with a message ready to send to this
 * number. Nothing is sent until the partner taps send there.
 */
internal fun openWhatsApp(context: Context, phone: String, message: String) {
    val digits = phone.filter { it.isDigit() }.takeLast(10)
    val uri = Uri.parse("https://wa.me/91$digits?text=" + Uri.encode(message))
    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
}

internal fun dial(context: Context, phone: String) {
    runCatching {
        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:+91" + phone.filter { it.isDigit() }.takeLast(10))).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }
}

internal fun initialsOf(name: String): String =
    name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2)
        .joinToString("") { it.first().uppercase() }.ifEmpty { "?" }
