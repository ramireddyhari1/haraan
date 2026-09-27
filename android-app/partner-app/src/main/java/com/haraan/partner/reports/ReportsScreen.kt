package com.haraan.partner.reports

import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.pdf.PdfRenderer
import android.os.ParcelFileDescriptor
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Download
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.TableChart
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DateRangePicker
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDateRangePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import com.haraan.partner.PartnerApi
import com.haraan.partner.ReportData
import com.haraan.partner.ReportRow
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import java.util.TimeZone

private val Blue = Color(0xFF1D4ED8)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFF9AA3B2)
private val Fill = Color(0xFFF4F6FA)
private val Page = Color(0xFFF6F7FA)
private val Hairline = Color(0xFFE6EAF0)
private val Green = Color(0xFF16A34A)
private val Red = Color(0xFFDC2626)
private val Amber = Color(0xFFD97706)

/** The ranges a desk actually asks for, in the order they're asked. */
private enum class Preset(val label: String) {
    CUSTOM("Custom"), TODAY("Today"), YESTERDAY("Yesterday"), WEEK("Last 7 days"), MONTH("This month"), LAST_MONTH("Last month")
}

private data class Range(val from: String, val to: String)

private val iso = SimpleDateFormat("yyyy-MM-dd", Locale.US)

private fun Calendar.isoDate(): String = iso.format(time)

private fun rangeFor(p: Preset, custom: Range?): Range {
    val c = Calendar.getInstance()
    val today = c.isoDate()
    return when (p) {
        Preset.TODAY -> Range(today, today)
        Preset.YESTERDAY -> { c.add(Calendar.DAY_OF_YEAR, -1); val y = c.isoDate(); Range(y, y) }
        Preset.WEEK -> { c.add(Calendar.DAY_OF_YEAR, -6); Range(c.isoDate(), today) }
        Preset.MONTH -> { c.set(Calendar.DAY_OF_MONTH, 1); Range(c.isoDate(), today) }
        Preset.LAST_MONTH -> {
            c.set(Calendar.DAY_OF_MONTH, 1); c.add(Calendar.DAY_OF_YEAR, -1); val end = c.isoDate()
            c.set(Calendar.DAY_OF_MONTH, 1); Range(c.isoDate(), end)
        }
        Preset.CUSTOM -> custom ?: Range(today, today)
    }
}

/** "1 – 27 Sep 2026" / "28 Aug – 27 Sep 2026" / "Sun, 27 Sep 2026". */
private fun prettyRange(r: Range): String = runCatching {
    val a = iso.parse(r.from)!!
    val b = iso.parse(r.to)!!
    val ca = Calendar.getInstance().apply { time = a }
    val cb = Calendar.getInstance().apply { time = b }
    val en = Locale.ENGLISH
    when {
        r.from == r.to -> SimpleDateFormat("EEE, d MMM yyyy", en).format(a)
        ca.get(Calendar.YEAR) != cb.get(Calendar.YEAR) ->
            SimpleDateFormat("d MMM yyyy", en).format(a) + " – " + SimpleDateFormat("d MMM yyyy", en).format(b)
        ca.get(Calendar.MONTH) == cb.get(Calendar.MONTH) ->
            SimpleDateFormat("d", en).format(a) + " – " + SimpleDateFormat("d MMM yyyy", en).format(b)
        else -> SimpleDateFormat("d MMM", en).format(a) + " – " + SimpleDateFormat("d MMM yyyy", en).format(b)
    }
}.getOrDefault("${r.from} – ${r.to}")

private fun dayCount(r: Range): Int = runCatching {
    (((iso.parse(r.to)!!.time - iso.parse(r.from)!!.time) / 86_400_000L) + 1).toInt()
}.getOrDefault(1)

private sealed interface Load {
    data object Loading : Load
    data class Ready(val data: ReportData) : Load
    data class Failed(val message: String) : Load
}

/**
 * Reports: pick a period with one tap, see what it came to straight away, and take it
 * away as a PDF to read, save or send — or a CSV for a spreadsheet.
 *
 * The old screen was two date rows and a "Download CSV" button: nothing to look at
 * before exporting, and a CSV most owners can't open on a phone. Now the numbers are
 * the screen, and the PDF is a document you'd hand to a partner or an accountant.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ReportsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()

    // Opens on Custom, starting on this month so far — the partner sets their own From–To.
    var preset by remember { mutableStateOf(Preset.CUSTOM) }
    var custom by remember { mutableStateOf<Range?>(rangeFor(Preset.MONTH, null)) }
    var byPlayed by remember { mutableStateOf(false) }
    var picking by remember { mutableStateOf(false) }
    var reload by remember { mutableIntStateOf(0) }
    var pdf by remember { mutableStateOf<File?>(null) }
    var building by remember { mutableStateOf<String?>(null) } // "pdf" | "csv"
    var toast by remember { mutableStateOf<String?>(null) }

    val range = rangeFor(preset, custom)
    val by = if (byPlayed) "played" else "booked"

    val load by produceState<Load>(Load.Loading, range, by, reload) {
        value = Load.Loading
        delay(150) // a quick chip-to-chip sweep doesn't fire a request per chip
        value = runCatching { api.reportRows(token, range.from, range.to, by) }
            .fold({ Load.Ready(it) }, { Load.Failed(it.message ?: "Couldn't load the report") })
    }

    LaunchedEffect(toast) { if (toast != null) { delay(2600); toast = null } }

    val basisLabel = if (byPlayed) "By play date" else "By booking date"

    fun openPdf() {
        val data = (load as? Load.Ready)?.data ?: return
        building = "pdf"
        scope.launch {
            runCatching {
                withContext(Dispatchers.Default) { ReportPdf.build(context, data, prettyRange(range), basisLabel) }
            }.onSuccess { Haptics.confirm(view); pdf = it }
                .onFailure { Haptics.reject(view); toast = "Couldn't make the PDF" }
            building = null
        }
    }

    fun shareCsv() {
        building = "csv"
        scope.launch {
            runCatching {
                val csv = api.reportCsv(token, range.from, range.to, by)
                withContext(Dispatchers.IO) {
                    File(File(context.cacheDir, "reports").apply { mkdirs() }, "haraan_bookings_${range.from}_to_${range.to}.csv")
                        .apply { writeText(csv) }
                }
            }.onSuccess { share(context, it, "text/csv"); Haptics.confirm(view) }
                .onFailure { Haptics.reject(view); toast = it.message ?: "Couldn't make the CSV" }
            building = null
        }
    }

    // The PDF viewer takes the whole screen; Back returns to the report.
    pdf?.let { file ->
        PdfViewer(file = file, title = prettyRange(range), onClose = { pdf = null })
        return
    }

    Box(Modifier.fillMaxSize().background(Page)) {
        Column(
            Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .verticalScroll(rememberScrollState())
                .padding(bottom = 120.dp),
        ) {
            // Header
            Row(Modifier.fillMaxWidth().padding(start = 4.dp, end = 16.dp, top = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                Text("Reports", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
            }

            // Period
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Preset.entries.forEach { p ->
                    PeriodChip(
                        label = p.label,
                        icon = if (p == Preset.CUSTOM) Icons.Outlined.CalendarMonth else null,
                        selected = p == preset,
                    ) {
                        Haptics.tick(view)
                        if (p == Preset.CUSTOM) picking = true else preset = p
                    }
                }
            }

            Row(
                Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                // The date line is the Custom control too: tap it to pick From–To.
                Column(
                    Modifier.weight(1f).clip(RoundedCornerShape(12.dp))
                        .clickable { Haptics.tick(view); picking = true }
                        .padding(vertical = 4.dp),
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        AnimatedContent(prettyRange(range), transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(100)) }, label = "range") {
                            Text(it, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
                        }
                        Spacer(Modifier.width(6.dp))
                        Icon(Icons.Outlined.CalendarMonth, contentDescription = "Change dates", tint = Blue, modifier = Modifier.size(16.dp))
                    }
                    Text("${dayCount(range)} day${if (dayCount(range) == 1) "" else "s"} · $basisLabel", fontSize = 12.sp, color = Muted)
                }
                BasisToggle(byPlayed) { Haptics.tick(view); byPlayed = it }
            }

            Spacer(Modifier.height(14.dp))

            when (val l = load) {
                Load.Loading -> SummarySkeleton()
                is Load.Failed -> FailedCard(l.message) { Haptics.tick(view); reload++ }
                is Load.Ready -> {
                    val s = summarize(l.data.rows)
                    SummaryCard(s)
                    Spacer(Modifier.height(18.dp))
                    BookingsPeek(l.data.rows)
                }
            }
        }

        // Actions, pinned where the thumb is.
        val ready = load is Load.Ready
        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .background(Color.White)
                .border(1.dp, Hairline)
                .navigationBarsPadding()
                .padding(horizontal = 16.dp, vertical = 12.dp),
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                ActionButton(
                    label = "View PDF",
                    icon = Icons.Outlined.PictureAsPdf,
                    primary = true,
                    enabled = ready && building == null,
                    loading = building == "pdf",
                    modifier = Modifier.weight(1.6f),
                    onClick = ::openPdf,
                )
                ActionButton(
                    label = "CSV",
                    icon = Icons.Outlined.TableChart,
                    primary = false,
                    enabled = ready && building == null,
                    loading = building == "csv",
                    modifier = Modifier.weight(1f),
                    onClick = ::shareCsv,
                )
            }
        }

        toast?.let {
            Text(
                it,
                color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 96.dp)
                    .clip(RoundedCornerShape(12.dp)).background(Ink).padding(horizontal = 14.dp, vertical = 10.dp),
            )
        }
    }

    if (picking) {
        val utc = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).apply { timeZone = TimeZone.getTimeZone("UTC") } }
        val state = rememberDateRangePickerState(
            initialSelectedStartDateMillis = runCatching { utc.parse(range.from)!!.time }.getOrNull(),
            initialSelectedEndDateMillis = runCatching { utc.parse(range.to)!!.time }.getOrNull(),
        )
        DatePickerDialog(
            onDismissRequest = { picking = false },
            confirmButton = {
                TextButton(
                    enabled = state.selectedStartDateMillis != null,
                    onClick = {
                        val start = state.selectedStartDateMillis
                        if (start != null) {
                            val end = state.selectedEndDateMillis ?: start
                            custom = Range(utc.format(start), utc.format(end))
                            preset = Preset.CUSTOM
                            Haptics.tick(view)
                        }
                        picking = false
                    },
                ) { Text("Apply", fontWeight = FontWeight.Bold, color = Blue) }
            },
            dismissButton = { TextButton(onClick = { picking = false }) { Text("Cancel", color = Muted) } },
        ) {
            DateRangePicker(state = state, modifier = Modifier.height(520.dp), showModeToggle = false)
        }
    }
}

// ---- Summary -------------------------------------------------------------------

@Composable
private fun SummaryCard(s: ReportSummary) {
    // Money counts up to its figure, so a new period reads as "changed", not "reloaded".
    val gross by animateFloatAsState(s.gross.toFloat(), tween(650), label = "gross")
    val share by animateFloatAsState(if (s.gross > 0) (s.collected / s.gross).toFloat() else 0f, tween(650), label = "share")

    Column(
        Modifier.padding(horizontal = 16.dp).fillMaxWidth()
            .clip(RoundedCornerShape(24.dp)).background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(24.dp)).padding(20.dp),
    ) {
        Text("GROSS SALES", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 1.sp)
        Spacer(Modifier.height(4.dp))
        Text("₹" + formatInr(gross.toDouble()), fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Ink)
        Spacer(Modifier.height(12.dp))
        Box(Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)).background(Fill)) {
            Box(Modifier.fillMaxWidth(share.coerceIn(0f, 1f)).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(Green))
        }
        Spacer(Modifier.height(10.dp))
        Row {
            Text("₹" + formatInr(s.collected) + " collected", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Green)
            Spacer(Modifier.weight(1f))
            Text(
                if (s.due > 0) "₹" + formatInr(s.due) + " due" else "Nothing due",
                fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = if (s.due > 0) Red else Muted,
            )
        }

        Spacer(Modifier.height(18.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        Spacer(Modifier.height(14.dp))

        // Facts as a sentence row, not a grid of boxes.
        Row(horizontalArrangement = Arrangement.spacedBy(18.dp)) {
            Fact("${s.sales}", if (s.sales == 1) "booking" else "bookings")
            Fact("${s.checkedIn}", "checked in")
            if (s.cancelled > 0) Fact("${s.cancelled}", "cancelled", Red)
        }

        if (s.sales > 0) {
            Spacer(Modifier.height(16.dp))
            val onlineShare = s.online.toFloat() / s.sales
            Row(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp))) {
                if (s.online > 0) Box(Modifier.weight(onlineShare.coerceAtLeast(0.001f)).fillMaxHeight().background(Blue))
                if (s.walkIns > 0) Box(Modifier.weight((1f - onlineShare).coerceAtLeast(0.001f)).fillMaxHeight().background(Color(0xFF93C5FD)))
            }
            Spacer(Modifier.height(8.dp))
            Row {
                Legend(Blue, "Online ${s.online}")
                Spacer(Modifier.width(16.dp))
                Legend(Color(0xFF93C5FD), "Walk-in ${s.walkIns}")
            }
        }
    }
}

@Composable
private fun Fact(value: String, label: String, color: Color = Ink) {
    Row(verticalAlignment = Alignment.Bottom) {
        Text(value, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = color)
        Spacer(Modifier.width(5.dp))
        Text(label, fontSize = 12.5.sp, color = Muted, modifier = Modifier.padding(bottom = 2.dp))
    }
}

@Composable
private fun Legend(color: Color, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(8.dp).clip(CircleShape).background(color))
        Spacer(Modifier.width(6.dp))
        Text(text, fontSize = 12.sp, color = Muted, fontWeight = FontWeight.Medium)
    }
}

/** The first bookings of the period, so the report is something you can already read. */
@Composable
private fun BookingsPeek(rows: List<ReportRow>) {
    Column(Modifier.padding(horizontal = 16.dp)) {
        Text("In this report", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.padding(start = 4.dp))
        Spacer(Modifier.height(8.dp))
        if (rows.isEmpty()) {
            Column(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Color.White)
                    .border(1.dp, Hairline, RoundedCornerShape(20.dp)).padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text("No bookings in this period", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink)
                Spacer(Modifier.height(4.dp))
                Text("Try a longer range, or switch between booking and play date.", fontSize = 12.5.sp, color = Muted)
            }
            return
        }
        val latest = rows.sortedByDescending { it.bookedAt }.take(6)
        val withItem = spansItems(rows)
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(20.dp)),
        ) {
            latest.forEachIndexed { i, r ->
                if (i > 0) Box(Modifier.padding(horizontal = 16.dp).fillMaxWidth().height(1.dp).background(Hairline))
                PeekRow(r, withItem)
            }
        }
        if (rows.size > latest.size) {
            Text(
                "and ${rows.size - latest.size} more in the PDF",
                fontSize = 12.5.sp, color = Muted, modifier = Modifier.padding(start = 4.dp, top = 8.dp),
            )
        }
    }
}

@Composable
private fun PeekRow(r: ReportRow, withItem: Boolean) {
    val dead = r.status.lowercase() in setOf("cancelled", "refunded", "failed", "expired")
    val (tag, tone) = when {
        dead -> r.status.lowercase().replaceFirstChar { it.uppercase() } to Red
        r.paymentStatus.equals("paid", true) -> "Paid" to Green
        r.status.equals("pending", true) -> "Pending" to Amber
        else -> "Due" to Amber
    }
    Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(r.customer.ifBlank { "Guest" }, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                listOf(shortDay(r.slotDate.ifBlank { r.bookedAt.take(10) }), bookingLabel(r, withItem), if (r.isWalkIn) "Walk-in" else null)
                    .filterNotNull().filter { it.isNotBlank() }.joinToString(" · "),
                fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(
                "₹" + formatInr(r.amount), fontSize = 14.sp, fontWeight = FontWeight.Bold,
                color = if (dead) Faint else Ink,
            )
            Text(tag, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = tone)
        }
    }
}

private fun shortDay(isoDate: String): String = runCatching {
    SimpleDateFormat("d MMM", Locale.ENGLISH).format(iso.parse(isoDate.take(10))!!)
}.getOrDefault(isoDate)

@Composable
private fun SummarySkeleton() {
    val pulse = rememberInfiniteTransition(label = "skeleton")
    val a by pulse.animateFloat(0.45f, 0.9f, infiniteRepeatable(tween(750), RepeatMode.Reverse), label = "a")
    Column(
        Modifier.padding(horizontal = 16.dp).fillMaxWidth()
            .clip(RoundedCornerShape(24.dp)).background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(24.dp)).padding(20.dp),
    ) {
        Bar(90.dp, 10.dp, a); Spacer(Modifier.height(10.dp))
        Bar(170.dp, 30.dp, a); Spacer(Modifier.height(16.dp))
        Bar(Dp.Unspecified, 8.dp, a); Spacer(Modifier.height(18.dp))
        Bar(220.dp, 14.dp, a)
    }
}

@Composable
private fun Bar(w: Dp, h: Dp, alpha: Float) {
    val m = if (w == Dp.Unspecified) Modifier.fillMaxWidth() else Modifier.width(w)
    Box(m.height(h).clip(RoundedCornerShape(8.dp)).alpha(alpha).background(Fill))
}

@Composable
private fun FailedCard(message: String, onRetry: () -> Unit) {
    Column(
        Modifier.padding(horizontal = 16.dp).fillMaxWidth()
            .clip(RoundedCornerShape(24.dp)).background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(24.dp)).padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("Couldn't load the report", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text(message, fontSize = 12.5.sp, color = Muted)
        Spacer(Modifier.height(14.dp))
        ActionButton("Try again", Icons.Outlined.Refresh, primary = true, enabled = true, loading = false, modifier = Modifier.width(180.dp), onClick = onRetry)
    }
}

// ---- Controls ------------------------------------------------------------------

@Composable
private fun PeriodChip(label: String, icon: ImageVector?, selected: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val bg by animateColorAsState(if (selected) Ink else Color.White, tween(180), label = "chip-bg")
    val fg by animateColorAsState(if (selected) Color.White else Ink, tween(180), label = "chip-fg")
    Row(
        Modifier
            .pressScale(interaction, 0.94f)
            .height(40.dp)
            .clip(RoundedCornerShape(99.dp))
            .background(bg)
            .border(1.dp, if (selected) Ink else Hairline, RoundedCornerShape(99.dp))
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon != null) {
            Icon(icon, contentDescription = null, tint = fg, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
        }
        Text(label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = fg, maxLines = 1)
    }
}

/** Booked / Played — which date the period filters on. A two-way slider, not a switch. */
@Composable
private fun BasisToggle(played: Boolean, onChange: (Boolean) -> Unit) {
    val x by androidx.compose.animation.core.animateDpAsState(if (played) 72.dp else 0.dp, tween(200), label = "basis")
    Box(Modifier.width(148.dp).height(34.dp).clip(RoundedCornerShape(99.dp)).background(Fill).padding(3.dp)) {
        Box(Modifier.offset(x = x).width(70.dp).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(Color.White).border(1.dp, Hairline, RoundedCornerShape(99.dp)))
        Row(Modifier.fillMaxSize()) {
            listOf(false to "Booked", true to "Played").forEach { (value, label) ->
                Box(
                    Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(99.dp)).clickable { if (value != played) onChange(value) },
                    contentAlignment = Alignment.Center,
                ) {
                    Text(label, fontSize = 12.sp, fontWeight = if (value == played) FontWeight.Bold else FontWeight.Medium, color = if (value == played) Ink else Muted)
                }
            }
        }
    }
}

@Composable
private fun ActionButton(
    label: String,
    icon: ImageVector,
    primary: Boolean,
    enabled: Boolean,
    loading: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val bg = if (primary) Blue else Color.White
    val fg = if (primary) Color.White else Blue
    Row(
        modifier
            .pressScale(interaction, 0.97f)
            .height(54.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(if (enabled || loading) bg else bg.copy(alpha = 0.5f))
            .border(if (primary) 0.dp else 1.5.dp, if (primary) Color.Transparent else Hairline, RoundedCornerShape(16.dp))
            .pressShade(interaction, if (primary) 0.12f else 0.05f)
            .clickable(interactionSource = interaction, indication = null, enabled = enabled, onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            CircularProgressIndicator(color = fg, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
        } else {
            Icon(icon, contentDescription = null, tint = fg, modifier = Modifier.size(19.dp))
        }
        Spacer(Modifier.width(8.dp))
        Text(label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = fg)
    }
}

// ---- PDF viewer ----------------------------------------------------------------

/**
 * The report as it will print: every page rendered on the phone, one under another.
 * Save puts the file where the partner chooses (Downloads by default, via the system
 * picker — no storage permission); Share hands it to WhatsApp, mail or print.
 */
@Composable
private fun PdfViewer(file: File, title: String, onClose: () -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    BackHandler(onBack = onClose)
    var saved by remember { mutableStateOf<String?>(null) }

    val save = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/pdf")) { uri ->
        if (uri != null) {
            runCatching {
                context.contentResolver.openOutputStream(uri)?.use { out -> file.inputStream().use { it.copyTo(out) } }
            }.onSuccess { Haptics.confirm(view); saved = "Saved" }
                .onFailure { Haptics.reject(view); saved = "Couldn't save" }
        }
    }
    LaunchedEffect(saved) { if (saved != null) { delay(2200); saved = null } }

    BoxWithConstraints(Modifier.fillMaxSize().background(Color(0xFFE9ECF2))) {
        val widthPx = with(androidx.compose.ui.platform.LocalDensity.current) { (maxWidth - 32.dp).roundToPx() }
        val pages by produceState<List<Bitmap>?>(null, file, widthPx) {
            value = withContext(Dispatchers.IO) { renderPages(file, widthPx) }
        }

        Column(Modifier.fillMaxSize()) {
            Row(
                Modifier.fillMaxWidth().background(Color.White).statusBarsPadding().padding(start = 4.dp, end = 8.dp, top = 4.dp, bottom = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onClose) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                Column(Modifier.weight(1f)) {
                    Text("Booking report", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
                    Text(
                        title + (pages?.let { " · ${it.size} page${if (it.size == 1) "" else "s"}" } ?: ""),
                        fontSize = 12.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
                IconButton(onClick = { Haptics.tick(view); share(context, file, "application/pdf") }) {
                    Icon(Icons.Outlined.Share, contentDescription = "Share PDF", tint = Ink)
                }
            }

            val list = pages
            if (list == null) {
                Box(Modifier.weight(1f).fillMaxWidth(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = Blue, strokeWidth = 2.5.dp, modifier = Modifier.size(28.dp))
                }
            } else {
                LazyColumn(
                    Modifier.weight(1f).fillMaxWidth(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    itemsIndexed(list) { _, bmp ->
                        Image(
                            bmp.asImageBitmap(),
                            contentDescription = "Report page",
                            contentScale = ContentScale.FillWidth,
                            modifier = Modifier.fillMaxWidth().shadow(6.dp, RoundedCornerShape(4.dp)).clip(RoundedCornerShape(4.dp)).background(Color.White),
                        )
                    }
                }
            }

            Row(
                Modifier.fillMaxWidth().background(Color.White).navigationBarsPadding().padding(horizontal = 16.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                ActionButton("Save PDF", Icons.Outlined.Download, primary = true, enabled = true, loading = false, modifier = Modifier.weight(1f)) {
                    Haptics.tick(view)
                    save.launch(file.name)
                }
                ActionButton("Share", Icons.Outlined.Share, primary = false, enabled = true, loading = false, modifier = Modifier.weight(1f)) {
                    Haptics.tick(view)
                    share(context, file, "application/pdf")
                }
            }
        }

        saved?.let {
            Text(
                it, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 100.dp)
                    .clip(RoundedCornerShape(12.dp)).background(Ink).padding(horizontal = 14.dp, vertical = 10.dp),
            )
        }
    }
}

/** Every page of [file] as a bitmap [width] pixels wide, drawn on white. */
private fun renderPages(file: File, width: Int): List<Bitmap> {
    val fd = ParcelFileDescriptor.open(file, ParcelFileDescriptor.MODE_READ_ONLY)
    return PdfRenderer(fd).use { renderer ->
        (0 until renderer.pageCount).map { i ->
            renderer.openPage(i).use { page ->
                val w = width.coerceAtLeast(300)
                val h = (w.toFloat() * page.height / page.width).toInt()
                val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
                bmp.eraseColor(android.graphics.Color.WHITE)
                page.render(bmp, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
                bmp
            }
        }
    }
}

private fun share(context: Context, file: File, mime: String) {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val send = Intent(Intent.ACTION_SEND).apply {
        type = mime
        putExtra(Intent.EXTRA_STREAM, uri)
        putExtra(Intent.EXTRA_SUBJECT, file.nameWithoutExtension.replace('_', ' '))
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(send, "Share report"))
}
