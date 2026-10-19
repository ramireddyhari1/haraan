package com.haraan.partner.academy

import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathMeasure
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextMeasurer
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.drawText
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.AcademyPage
import com.haraan.partner.BatchRow
import com.haraan.partner.PartnerApi
import com.haraan.partner.RosterPage
import com.haraan.partner.StaffPlace
import com.haraan.partner.StudentDetail
import com.haraan.partner.StudentRow
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.ProblemState
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.components.rememberMoneyMotion
import com.haraan.partner.ui.desk.AddPill
import com.haraan.partner.ui.desk.DAmber
import com.haraan.partner.ui.desk.DBlue
import com.haraan.partner.ui.desk.DBlueTint
import com.haraan.partner.ui.desk.DFaint
import com.haraan.partner.ui.desk.DGreen
import com.haraan.partner.ui.desk.DHair
import com.haraan.partner.ui.desk.DInk
import com.haraan.partner.ui.desk.DMuted
import com.haraan.partner.ui.desk.DNavy
import com.haraan.partner.ui.desk.DPage
import com.haraan.partner.ui.desk.DRed
import com.haraan.partner.ui.desk.DeskChip
import com.haraan.partner.ui.desk.DeskField
import com.haraan.partner.ui.desk.DeskLoad
import com.haraan.partner.ui.desk.DeskMethod
import com.haraan.partner.ui.desk.DeskSection
import com.haraan.partner.ui.desk.DeskSkeleton
import com.haraan.partner.ui.desk.DeskTopBar
import com.haraan.partner.ui.desk.MethodPicker
import com.haraan.partner.ui.desk.deskLoad
import com.haraan.partner.ui.desk.dial
import com.haraan.partner.ui.desk.initialsOf
import com.haraan.partner.ui.desk.methodLabel
import com.haraan.partner.ui.desk.openWhatsApp
import com.haraan.partner.ui.desk.readable
import com.haraan.partner.ui.desk.shiftIso
import com.haraan.partner.ui.desk.shortDate
import com.haraan.partner.ui.desk.todayIso
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import kotlin.math.roundToLong

private val WEEK = listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun")
private val SPORTS = listOf("Badminton", "Cricket", "Football", "Tennis", "Pickleball", "Swimming", "Basketball", "Skating")

/** Each sport keeps one colour everywhere it is drawn. */
private fun sportColor(sport: String?): Color = when (sport?.lowercase()) {
    "badminton" -> Color(0xFF2563EB)
    "cricket" -> Color(0xFF0D9488)
    "football" -> Color(0xFF16A34A)
    "tennis" -> Color(0xFFCA8A04)
    "pickleball" -> Color(0xFF7C3AED)
    "swimming" -> Color(0xFF0284C7)
    "basketball" -> Color(0xFFEA580C)
    "skating" -> Color(0xFFDB2777)
    else -> Color(0xFF475569)
}

/** "06:00" → minutes since midnight, or null. */
private fun minutesOf(t: String?): Int? {
    val m = Regex("^(\\d{1,2}):(\\d{2})").find(t ?: return null) ?: return null
    val h = m.groupValues[1].toInt()
    val mm = m.groupValues[2].toInt()
    return if (h in 0..23 && mm in 0..59) h * 60 + mm else null
}

private fun clock(t: String?): String? {
    val m = minutesOf(t) ?: return null
    val h = m / 60
    val suffix = if (h < 12) "AM" else "PM"
    val h12 = if (h % 12 == 0) 12 else h % 12
    return if (m % 60 == 0) "$h12 $suffix" else "$h12:${(m % 60).toString().padStart(2, '0')} $suffix"
}

private fun timeRange(b: BatchRow): String? {
    val s = clock(b.startTime) ?: return null
    val e = clock(b.endTime)
    return if (e == null) s else "$s – $e"
}

private fun feeLine(s: StudentRow): Pair<String, Color> {
    val d = s.daysOverdue
    return when {
        s.paidUntil == null -> "No fees on record" to DMuted
        s.overdue && d != null -> (if (d == 1) "1 day overdue" else "$d days overdue") to DRed
        s.dueSoon && d != null -> (if (d == 0) "Due today" else "Due in ${-d} days") to DAmber
        else -> "Paid till ${shortDate(s.paidUntil)}" to DGreen
    }
}

private fun dayLabel(iso: String): String {
    if (iso == todayIso()) return "Today"
    if (iso == shiftIso(todayIso(), -1)) return "Yesterday"
    return runCatching {
        val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso)!!
        SimpleDateFormat("EEE, d MMM", Locale.ENGLISH).format(d)
    }.getOrDefault(iso)
}

// ---- Entry -----------------------------------------------------------------

/**
 * The academy: coaching batches, who came today, and who owes fees.
 *
 * [canDefine] (pricing) creates and edits batches; [canDesk] (bookings) runs the
 * day: marks attendance, enrols students, collects fees.
 */
@Composable
fun AcademyScreen(api: PartnerApi, token: String, canDefine: Boolean, canDesk: Boolean, onBack: () -> Unit) {
    var reload by remember { mutableIntStateOf(0) }
    var load by remember { mutableStateOf<DeskLoad<AcademyPage>>(DeskLoad.Loading) }
    var openId by remember { mutableStateOf<Long?>(null) }
    var editing by remember { mutableStateOf<BatchRow?>(null) }
    var adding by remember { mutableStateOf(false) }
    var filter by remember { mutableStateOf<String?>(null) }
    val snack = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val view = LocalView.current

    LaunchedEffect(reload) {
        val next = deskLoad { api.academy(token) }
        if (next is DeskLoad.Ready || load !is DeskLoad.Ready) load = next
    }

    val page = (load as? DeskLoad.Ready)?.value
    val open = page?.batches?.firstOrNull { it.id == openId }

    if (open != null) {
        BackHandler { openId = null; reload++ }
        BatchScreen(
            api = api, token = token, batch = open, canDefine = canDefine, canDesk = canDesk,
            onBack = { openId = null; reload++ },
            onEdit = { editing = open },
        )
    } else {
        Scaffold(
            containerColor = DPage,
            snackbarHost = { SnackbarHost(snack) },
            topBar = {
                DeskTopBar("Academy", onBack) {
                    if (canDefine && page != null && page.batches.isNotEmpty()) AddPill("New batch") { adding = true }
                }
            },
        ) { padding ->
            Box(Modifier.fillMaxSize().padding(padding)) {
                when (val l = load) {
                    DeskLoad.Loading -> DeskSkeleton()
                    is DeskLoad.Failed -> ProblemState(l.problem, onRetry = { reload++ }, detail = l.message)
                    is DeskLoad.Ready -> if (l.value.batches.isEmpty()) {
                        EmptyAcademy(canDefine) { adding = true }
                    } else {
                        val shown = when (filter) {
                            "overdue" -> l.value.batches.filter { it.overdue > 0 }
                            "soon" -> l.value.batches.filter { it.dueSoon > 0 }
                            else -> l.value.batches
                        }
                        LazyColumn(
                            Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            item(key = "hero") {
                                TodayBoard(l.value, filter) { f -> Haptics.tick(view); filter = if (filter == f) null else f }
                            }
                            val live = shown.filter { it.isActive }
                            val paused = shown.filterNot { it.isActive }
                            val today = live.filter { it.runsToday }
                            val other = live.filterNot { it.runsToday }
                            if (today.isNotEmpty()) {
                                item(key = "h-today") { DeskSection("On today") }
                                items(today, key = { "b${it.id}" }) { BatchCard(it) { openId = it.id } }
                            }
                            if (other.isNotEmpty()) {
                                item(key = "h-other") { DeskSection(if (today.isEmpty()) "Batches" else "Other days") }
                                items(other, key = { "b${it.id}" }) { BatchCard(it) { openId = it.id } }
                            }
                            if (paused.isNotEmpty()) {
                                item(key = "h-paused") { DeskSection("Paused") }
                                items(paused, key = { "b${it.id}" }) { BatchCard(it) { openId = it.id } }
                            }
                            if (shown.isEmpty()) {
                                item(key = "none") {
                                    Text("No batch matches that filter.", fontSize = 14.sp, color = DMuted, modifier = Modifier.padding(8.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ((adding || editing != null) && page != null) {
        val target = editing
        BatchEditorSheet(
            existing = target,
            venues = page.venues,
            onDismiss = { adding = false; editing = null },
            onSave = { form, done ->
                scope.launch {
                    try {
                        api.saveBatch(
                            token, target?.id, form.name, form.coach, form.sport, form.days, form.start, form.end,
                            form.fee, form.capacity, form.venueId, form.active,
                        )
                        Haptics.confirm(view)
                        done(null)
                        adding = false; editing = null
                        reload++
                        snack.showSnackbar(if (target == null) "${form.name} added" else "Saved")
                    } catch (e: Exception) {
                        Haptics.reject(view)
                        done(e.readable())
                    }
                }
            },
        )
    }
}

// ---- Today board (hero) -------------------------------------------------------

/**
 * Today's classes as training cones on a time track from 6 AM to 10 PM. Each
 * cone fills from the base as students are marked in; the class running now
 * has a ball bouncing beside it. Under it, the facts an owner opens this for.
 */
@Composable
private fun TodayBoard(page: AcademyPage, filter: String?, onFilter: (String) -> Unit) {
    val s = page.summary
    val today = page.batches.filter { it.isActive && it.runsToday }
    val rise = remember { Animatable(0f) }
    LaunchedEffect(Unit) { rise.animateTo(1f, spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessLow)) }
    val bounce by rememberInfiniteTransition(label = "ball").animateFloat(
        0f, 1f, infiniteRepeatable(tween(900, easing = LinearEasing)), label = "b",
    )
    val nowMin = remember { Calendar.getInstance().let { it.get(Calendar.HOUR_OF_DAY) * 60 + it.get(Calendar.MINUTE) } }
    val money = rememberMoneyMotion(s.collectedMonth.toDouble(), "academy.month")
    val measurer = rememberTextMeasurer()

    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).border(1.dp, DHair, RoundedCornerShape(24.dp)),
    ) {
        Canvas(
            Modifier.fillMaxWidth().height(168.dp).semantics {
                contentDescription = "${today.size} classes today, ${s.presentToday} students marked in"
            },
        ) {
            drawTrack(today, nowMin, rise.value, bounce, measurer)
        }
        Row(Modifier.fillMaxWidth().padding(start = 18.dp, end = 18.dp, top = 12.dp), verticalAlignment = Alignment.Bottom) {
            Column(Modifier.weight(1f)) {
                Text(
                    when (today.size) { 0 -> "No classes today"; 1 -> "1 class today"; else -> "${today.size} classes today" },
                    fontSize = 16.sp, fontWeight = FontWeight.Bold, color = DInk,
                )
                val expected = today.sumOf { it.students }
                Text(
                    if (expected == 0) "${s.students} students across all batches" else "${s.presentToday} of $expected marked in",
                    fontSize = 12.5.sp, color = DMuted,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(money.shown.roundToLong().toDouble()), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = DInk)
                Text("fees this month", fontSize = 12.sp, color = DMuted)
            }
        }
        if (s.overdue > 0 || s.dueSoon > 0) {
            Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (s.overdue > 0) FeeFilterChip(if (s.overdue == 1) "1 fee overdue" else "${s.overdue} fees overdue", DRed, filter == "overdue") { onFilter("overdue") }
                if (s.dueSoon > 0) FeeFilterChip("${s.dueSoon} due this week", DAmber, filter == "soon") { onFilter("soon") }
            }
        } else {
            Text(
                "Every fee is paid up.", fontSize = 12.5.sp, color = DGreen,
                modifier = Modifier.padding(horizontal = 18.dp, vertical = 12.dp),
            )
        }
    }
}

@Composable
private fun FeeFilterChip(label: String, tone: Color, selected: Boolean, onClick: () -> Unit) {
    Row(
        Modifier
            .pressableTile(999.dp, pressedScale = 0.95f, onClick = onClick)
            .clip(RoundedCornerShape(999.dp))
            .background(if (selected) tone.copy(alpha = 0.14f) else tone.copy(alpha = 0.07f))
            .border(if (selected) 1.5.dp else 1.dp, tone.copy(alpha = if (selected) 0.8f else 0.25f), RoundedCornerShape(999.dp))
            .heightIn(min = 36.dp)
            .padding(horizontal = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(7.dp).clip(CircleShape).background(tone))
        Spacer(Modifier.width(7.dp))
        Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = tone)
    }
}

private fun DrawScope.drawTrack(today: List<BatchRow>, nowMin: Int, rise: Float, bounce: Float, measurer: TextMeasurer) {
    val w = size.width
    val h = size.height
    val u = h / 168f
    val left = 22 * u
    val right = w - 22 * u
    val ground = h - 40 * u
    val startMin = 6 * 60f
    val endMin = 22 * 60f
    fun xOf(min: Float) = left + (right - left) * ((min - startMin) / (endMin - startMin)).coerceIn(0f, 1f)

    // Turf strip with mown bands, and the time ticks under it.
    drawRect(Color(0xFFF1F7F2), Offset(0f, ground - 6 * u), Size(w, h - ground + 6 * u))
    for (i in 0 until 8) {
        if (i % 2 == 0) drawRect(Color(0xFFE5F1E8), Offset(w * i / 8f, ground - 6 * u), Size(w / 8f, h - ground + 6 * u))
    }
    drawLine(Color(0xFFCBD5E1), Offset(left, ground), Offset(right, ground), 1.4f * u)
    for (hr in 6..22 step 2) {
        val x = xOf(hr * 60f)
        drawLine(Color(0xFFCBD5E1), Offset(x, ground), Offset(x, ground + 4 * u), 1.2f * u)
        if (hr % 4 == 2) {
            val label = if (hr == 12) "12 PM" else if (hr < 12) "$hr AM" else "${hr - 12} PM"
            val t = measurer.measure(label, TextStyle(fontSize = 10.sp, color = DFaint))
            drawText(t, topLeft = Offset((x - t.size.width / 2f).coerceIn(2f, w - t.size.width - 2f), ground + 8 * u))
        }
    }

    // Now marker.
    if (nowMin in (6 * 60)..(22 * 60)) {
        val x = xOf(nowMin.toFloat())
        drawLine(DBlue.copy(alpha = 0.5f), Offset(x, 14 * u), Offset(x, ground), 1.4f * u,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(4 * u, 4 * u)))
        drawCircle(DBlue, 3.5f * u, Offset(x, 14 * u))
    }

    if (today.isEmpty()) {
        // Cones stacked away for the day.
        for (i in 0..2) cone(Offset(w / 2 - 18 * u + i * 18 * u, ground), 26 * u, DFaint.copy(alpha = 0.5f), 0f, u, lying = i == 1)
        return
    }

    today.forEachIndexed { i, b ->
        val start = minutesOf(b.startTime)?.toFloat() ?: (7 * 60f + i * 90f)
        val end = minutesOf(b.endTime)?.toFloat() ?: (start + 60f)
        val x = xOf((start + end) / 2f)
        val fill = if (b.students == 0) 0f else (b.presentToday.toFloat() / b.students).coerceIn(0f, 1f)
        val c = sportColor(b.sport)
        val coneH = 58 * u
        val lift = (1f - rise) * 40 * u
        cone(Offset(x, ground + lift), coneH, c, fill * rise, u, lying = false)
        // Name and head-count over the cone, so the track reads without a legend.
        val name = b.name.split(' ').first().take(10)
        val t1 = measurer.measure(name, TextStyle(fontSize = 11.sp, color = DInk, fontWeight = FontWeight.SemiBold))
        val t2 = measurer.measure("${b.presentToday}/${b.students}", TextStyle(fontSize = 10.sp, color = c, fontWeight = FontWeight.Bold))
        val top = ground + lift - coneH - 8 * u - t1.size.height - t2.size.height
        drawText(t1, topLeft = Offset((x - t1.size.width / 2f).coerceIn(2f, w - t1.size.width - 2f), top))
        drawText(t2, topLeft = Offset((x - t2.size.width / 2f).coerceIn(2f, w - t2.size.width - 2f), top + t1.size.height))
        // Running now: a ball bounces next to the cone.
        if (nowMin >= start && nowMin <= end) {
            val by = ground - 6 * u - (1f - (2 * bounce - 1) * (2 * bounce - 1)) * 30 * u
            drawCircle(Color(0x220F172A), 5 * u * (0.6f + 0.4f * (by / ground)), Offset(x + 22 * u, ground + 1 * u))
            drawCircle(Color(0xFFFACC15), 6 * u, Offset(x + 22 * u, by))
            drawArc(Color(0xFFCA8A04), 20f, 140f, false, Offset(x + 16 * u, by - 6 * u), Size(12 * u, 12 * u), style = Stroke(1.2f * u))
        }
    }
}

/** A training cone. [fill] 0..1 pours the sport colour up from the base. */
private fun DrawScope.cone(base: Offset, height: Float, color: Color, fill: Float, u: Float, lying: Boolean) {
    val half = height * 0.38f
    val path = Path().apply {
        if (lying) {
            moveTo(base.x - height * 0.5f, base.y - half * 0.9f)
            lineTo(base.x + height * 0.5f, base.y - 3 * u)
            lineTo(base.x - height * 0.5f, base.y)
            close()
        } else {
            moveTo(base.x - half, base.y - 3 * u)
            lineTo(base.x - 3 * u, base.y - height)
            lineTo(base.x + 3 * u, base.y - height)
            lineTo(base.x + half, base.y - 3 * u)
            close()
        }
    }
    drawPath(path, Color.White)
    if (!lying && fill > 0f) {
        clipPath(path) {
            val top = base.y - 3 * u - (height - 3 * u) * fill
            drawRect(color, Offset(base.x - half, top), Size(half * 2, base.y - top))
        }
    }
    drawPath(path, color, style = Stroke(2f * u))
    if (!lying) {
        // Reflective band across the middle, and the square foot.
        drawLine(color.copy(alpha = 0.45f), Offset(base.x - half * 0.55f, base.y - height * 0.45f), Offset(base.x + half * 0.55f, base.y - height * 0.45f), 2.4f * u)
        drawRoundRect(color, Offset(base.x - half - 4 * u, base.y - 4 * u), Size(half * 2 + 8 * u, 5 * u), CornerRadius(2 * u))
    }
}

// ---- Batch card ----------------------------------------------------------------

@Composable
private fun BatchCard(b: BatchRow, onClick: () -> Unit) {
    val c = if (b.isActive) sportColor(b.sport) else DFaint
    Row(
        Modifier
            .fillMaxWidth()
            .pressableTile(20.dp, pressedScale = 0.97f, onClick = onClick)
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, DHair, RoundedCornerShape(20.dp))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        SeatCourt(b, c, Modifier.size(width = 70.dp, height = 84.dp))
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(b.name, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = if (b.isActive) DInk else DMuted,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (!b.isActive) {
                    Spacer(Modifier.width(8.dp))
                    Tag("Paused", DMuted)
                }
            }
            val sub = listOfNotNull(b.coach?.let { "Coach $it" }, timeRange(b)).joinToString(" · ")
            if (sub.isNotEmpty()) Text(sub, fontSize = 12.5.sp, color = DMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(6.dp))
            DayLetters(b.days, c)
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("₹" + formatInr(b.monthlyFee.toDouble()), fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = DInk)
                Text(" / month", fontSize = 12.sp, color = DFaint)
                Spacer(Modifier.weight(1f))
                when {
                    b.overdue > 0 -> Tag("${b.overdue} overdue", DRed)
                    b.dueSoon > 0 -> Tag("${b.dueSoon} due soon", DAmber)
                }
            }
        }
    }
}

@Composable
private fun Tag(text: String, color: Color) {
    Text(
        text, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = color,
        modifier = Modifier.clip(RoundedCornerShape(6.dp)).background(color.copy(alpha = 0.09f)).padding(horizontal = 7.dp, vertical = 2.dp),
    )
}

/** M T W T F S S, with this batch's days lit. */
@Composable
private fun DayLetters(days: List<String>, color: Color) {
    val every = days.isEmpty()
    Row(horizontalArrangement = Arrangement.spacedBy(3.dp)) {
        WEEK.forEach { d ->
            val on = every || d in days
            Box(
                Modifier.size(18.dp).clip(CircleShape).background(if (on) color.copy(alpha = 0.14f) else Color.Transparent),
                contentAlignment = Alignment.Center,
            ) {
                Text(d.take(1), fontSize = 10.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Normal, color = if (on) color else Color(0xFFCBD5E1))
            }
        }
    }
}

/**
 * The batch as a small court seen from above, its seats as dots: solid = here
 * today, ring = enrolled, dashed = a free seat. A full batch reads at a glance.
 */
@Composable
private fun SeatCourt(b: BatchRow, c: Color, modifier: Modifier) {
    val seats = (b.capacity ?: (b.students + 2)).coerceIn(1, 24)
    val filled by animateFloatAsState(1f, tween(600), label = "seat")
    Canvas(modifier) {
        val u = size.width / 70f
        drawRoundRect(c.copy(alpha = 0.10f), Offset.Zero, size, CornerRadius(14 * u))
        // Court lines.
        val inset = 7 * u
        drawRoundRect(c.copy(alpha = 0.35f), Offset(inset, inset), Size(size.width - inset * 2, size.height - inset * 2), CornerRadius(3 * u), style = Stroke(1.2f * u))
        drawLine(c.copy(alpha = 0.35f), Offset(inset, size.height / 2), Offset(size.width - inset, size.height / 2), 1.2f * u)
        val cols = if (seats <= 6) 2 else if (seats <= 12) 3 else 4
        val rows = (seats + cols - 1) / cols
        val gx = (size.width - inset * 2) / cols
        val gy = (size.height - inset * 2) / rows
        val r = (minOf(gx, gy) * 0.30f).coerceAtMost(7 * u)
        for (i in 0 until seats) {
            val cx = inset + gx * (i % cols) + gx / 2
            val cy = inset + gy * (i / cols) + gy / 2
            val p = Offset(cx, cy)
            when {
                i < b.presentToday && b.runsToday -> drawCircle(c, r * filled, p)
                i < b.students -> {
                    drawCircle(Color.White, r, p)
                    drawCircle(c, r, p, style = Stroke(1.6f * u))
                }
                else -> drawCircle(c.copy(alpha = 0.35f), r, p, style = Stroke(1.1f * u, pathEffect = PathEffect.dashPathEffect(floatArrayOf(2 * u, 2 * u))))
            }
        }
    }
}

// ---- Empty --------------------------------------------------------------------------

@Composable
private fun EmptyAcademy(canDefine: Boolean, onAdd: () -> Unit) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(36.dp))
        val drop = remember { Animatable(0f) }
        LaunchedEffect(Unit) { drop.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = 120f)) }
        Canvas(Modifier.fillMaxWidth().height(150.dp)) {
            val u = size.height / 150f
            val g = 118 * u
            drawLine(Color(0xFFCBD5E1), Offset(size.width * 0.15f, g), Offset(size.width * 0.85f, g), 1.4f * u)
            val colors = listOf(DBlue, Color(0xFF0D9488), Color(0xFFEA580C))
            colors.forEachIndexed { i, c ->
                val x = size.width / 2 + (i - 1) * 56 * u
                cone(Offset(x, g - (1f - drop.value) * (60 + i * 20) * u), 52 * u, c, 0f, u, lying = false)
            }
        }
        Spacer(Modifier.height(16.dp))
        Text("No batches yet", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = DInk)
        Spacer(Modifier.height(6.dp))
        Text(
            "Set up a coaching batch with its days, time and monthly fee. Then mark attendance and collect fees right from the desk.",
            fontSize = 14.sp, color = DMuted, lineHeight = 20.sp, textAlign = TextAlign.Center,
        )
        if (canDefine) {
            Spacer(Modifier.height(22.dp))
            Button(
                onClick = onAdd, colors = ButtonDefaults.buttonColors(containerColor = DBlue), shape = RoundedCornerShape(14.dp),
                modifier = Modifier.heightIn(min = 52.dp).fillMaxWidth(),
            ) { Text("Create your first batch", fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
        }
        Spacer(Modifier.height(40.dp))
    }
}

// ---- One batch: the register ---------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BatchScreen(
    api: PartnerApi,
    token: String,
    batch: BatchRow,
    canDefine: Boolean,
    canDesk: Boolean,
    onBack: () -> Unit,
    onEdit: () -> Unit,
) {
    var date by remember { mutableStateOf(todayIso()) }
    var reload by remember { mutableIntStateOf(0) }
    var load by remember { mutableStateOf<DeskLoad<RosterPage>>(DeskLoad.Loading) }
    var openStudent by remember { mutableStateOf<StudentRow?>(null) }
    var enrolling by remember { mutableStateOf(false) }
    var busyAll by remember { mutableStateOf(false) }
    val local = remember { mutableStateListOf<Pair<Long, Boolean>>() }
    val snack = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val view = LocalView.current

    LaunchedEffect(date, reload) {
        val next = deskLoad { api.batchRoster(token, batch.id, date) }
        if (next is DeskLoad.Ready) local.clear()
        if (next is DeskLoad.Ready || load !is DeskLoad.Ready) load = next
    }

    fun presentOf(s: StudentRow) = local.lastOrNull { it.first == s.id }?.second ?: s.present

    Scaffold(
        containerColor = DPage,
        snackbarHost = { SnackbarHost(snack) },
        topBar = {
            DeskTopBar(batch.name, onBack) {
                if (canDefine) TextButton(onClick = { Haptics.tick(view); onEdit() }) { Text("Edit", color = DBlue, fontWeight = FontWeight.SemiBold) }
            }
        },
        bottomBar = {
            if (canDesk && batch.isActive) {
                Box(Modifier.fillMaxWidth().background(DPage).navigationBarsPadding().padding(horizontal = 16.dp, vertical = 10.dp)) {
                    Button(
                        onClick = { Haptics.tick(view); enrolling = true },
                        colors = ButtonDefaults.buttonColors(containerColor = DBlue), shape = RoundedCornerShape(14.dp),
                        modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp),
                    ) { Text("Enrol a student", fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
                }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val l = load) {
                DeskLoad.Loading -> DeskSkeleton(heroHeight = 150, rows = 4)
                is DeskLoad.Failed -> ProblemState(l.problem, onRetry = { reload++ }, detail = l.message)
                is DeskLoad.Ready -> {
                    val roster = l.value
                    val here = roster.students.count { presentOf(it) }
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 24.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        item(key = "head") {
                            RegisterHead(
                                roster = roster, date = date, here = here,
                                onDay = { delta -> Haptics.tick(view); date = shiftIso(date, delta) },
                                canMark = canDesk && roster.students.isNotEmpty(),
                                busy = busyAll,
                                onAll = { allIn ->
                                    busyAll = true
                                    scope.launch {
                                        try {
                                            api.markAllAttendance(token, batch.id, date, allIn)
                                            if (allIn) Haptics.confirm(view) else Haptics.tick(view)
                                            reload++
                                        } catch (e: Exception) {
                                            Haptics.reject(view); snack.showSnackbar(e.readable())
                                        } finally { busyAll = false }
                                    }
                                },
                            )
                        }
                        if (roster.students.isEmpty()) {
                            item(key = "empty") {
                                Text(
                                    "Nobody on this batch yet." + if (canDesk) " Enrol the first student below." else "",
                                    fontSize = 14.sp, color = DMuted, modifier = Modifier.padding(12.dp),
                                )
                            }
                        }
                        items(roster.students, key = { "s${it.id}" }) { s ->
                            val now = presentOf(s)
                            StudentLine(
                                s = if (roster.runsToday && s.recent.isNotEmpty()) s.copy(recent = s.recent.dropLast(1) + now) else s,
                                present = now,
                                canMark = canDesk,
                                onToggle = { now ->
                                    local.add(s.id to now)
                                    if (now) Haptics.confirm(view) else Haptics.tick(view)
                                    scope.launch {
                                        try { api.markAttendance(token, s.id, date, now) } catch (e: Exception) {
                                            local.add(s.id to !now)
                                            Haptics.reject(view); snack.showSnackbar(e.readable())
                                        }
                                    }
                                },
                                onOpen = { openStudent = s },
                            )
                        }
                    }
                }
            }
        }
    }

    openStudent?.let { s ->
        StudentSheet(
            api = api, token = token, studentId = s.id, batchName = batch.name, canDesk = canDesk,
            onDismiss = { openStudent = null; reload++ },
            onRemoved = { openStudent = null; reload++; scope.launch { snack.showSnackbar("${s.name} removed from ${batch.name}") } },
        )
    }

    if (enrolling) {
        EnrolSheet(
            batch = batch,
            onDismiss = { enrolling = false },
            onEnrol = { name, phone, months, method, done ->
                scope.launch {
                    try {
                        api.enrollStudent(token, batch.id, name, phone, months, method.api)
                        Haptics.money(view)
                        done(null)
                        enrolling = false
                        reload++
                        snack.showSnackbar("$name enrolled · ₹${formatInr((batch.monthlyFee * months).toDouble())} collected")
                    } catch (e: Exception) {
                        Haptics.reject(view)
                        done(e.readable())
                    }
                }
            },
        )
    }
}

@Composable
private fun RegisterHead(
    roster: RosterPage,
    date: String,
    here: Int,
    onDay: (Int) -> Unit,
    canMark: Boolean,
    busy: Boolean,
    onAll: (Boolean) -> Unit,
) {
    val b = roster.batch
    val total = roster.students.size
    val c = sportColor(b.sport)
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(22.dp)).background(Color.White).border(1.dp, DHair, RoundedCornerShape(22.dp)).padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            DayStep("‹", date > shiftIso(todayIso(), -60)) { onDay(-1) }
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(dayLabel(date), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = DInk)
                Text(
                    listOfNotNull(timeRange(b), b.coach?.let { "Coach $it" }).joinToString(" · ").ifEmpty { "Register" },
                    fontSize = 12.sp, color = DMuted,
                )
            }
            DayStep("›", date < todayIso()) { onDay(1) }
        }
        Spacer(Modifier.height(14.dp))
        // The register as a row of seats filling up.
        Canvas(Modifier.fillMaxWidth().height(22.dp)) {
            val n = total.coerceAtLeast(1)
            val gap = 4.dp.toPx()
            val w = ((size.width - gap * (n - 1)) / n).coerceAtLeast(2f)
            for (i in 0 until n) {
                val x = i * (w + gap)
                val on = i < here
                drawRoundRect(if (on) c else c.copy(alpha = 0.12f), Offset(x, 0f), Size(w, size.height), CornerRadius(minOf(w, size.height) / 2.6f))
            }
        }
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                if (total == 0) "No students" else "$here of $total here",
                fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk, modifier = Modifier.weight(1f),
            )
            if (canMark) {
                val allIn = here == total
                TextButton(onClick = { onAll(!allIn) }, enabled = !busy) {
                    Text(if (busy) "Marking…" else if (allIn) "Clear all" else "Mark everyone here", color = DBlue, fontWeight = FontWeight.SemiBold)
                }
            }
        }
        if (!roster.runsToday) {
            Text(
                "This batch doesn't usually run on ${dayLabel(date).let { if (it == "Today" || it == "Yesterday") "this day" else it.substringBefore(',') + "s" }}. You can still mark it.",
                fontSize = 12.sp, color = DAmber, modifier = Modifier.padding(top = 4.dp),
            )
        }
    }
}

@Composable
private fun DayStep(glyph: String, enabled: Boolean, onClick: () -> Unit) {
    Box(
        Modifier.size(44.dp).clip(CircleShape)
            .pressableTile(22.dp, enabled = enabled, onClick = onClick)
            .background(if (enabled) DBlueTint else Color.Transparent),
        contentAlignment = Alignment.Center,
    ) { Text(glyph, fontSize = 22.sp, color = if (enabled) DBlue else Color(0xFFCBD5E1), fontWeight = FontWeight.Bold) }
}

/**
 * One line of the register. The box on the left is a real register box: tap it
 * and a tick is inked in with one stroke; tap again and it lifts off.
 */
@Composable
private fun StudentLine(s: StudentRow, present: Boolean, canMark: Boolean, onToggle: (Boolean) -> Unit, onOpen: () -> Unit) {
    val ink by animateFloatAsState(if (present) 1f else 0f, spring(dampingRatio = 0.7f, stiffness = 260f), label = "ink")
    val (fee, tone) = feeLine(s)
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, if (present) DGreen.copy(alpha = 0.35f) else DHair, RoundedCornerShape(18.dp))
            .heightIn(min = 72.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .padding(start = 10.dp)
                .size(52.dp)
                .pressableTile(14.dp, enabled = canMark, pressedScale = 0.88f) { onToggle(!present) }
                .semantics { contentDescription = if (present) "${s.name} is here" else "Mark ${s.name} here" },
            contentAlignment = Alignment.Center,
        ) {
            Canvas(Modifier.size(34.dp)) {
                val r = 8.dp.toPx()
                drawRoundRect(if (present) DGreen.copy(alpha = 0.10f) else Color.White, cornerRadius = CornerRadius(r))
                drawRoundRect(if (present) DGreen else Color(0xFFCBD5E1), cornerRadius = CornerRadius(r), style = Stroke(1.8.dp.toPx()))
                if (ink > 0.01f) {
                    val tick = Path().apply {
                        moveTo(size.width * 0.22f, size.height * 0.54f)
                        quadraticBezierTo(size.width * 0.34f, size.height * 0.62f, size.width * 0.42f, size.height * 0.76f)
                        quadraticBezierTo(size.width * 0.58f, size.height * 0.42f, size.width * 0.84f, size.height * 0.20f)
                    }
                    val m = PathMeasure().apply { setPath(tick, false) }
                    val part = Path()
                    m.getSegment(0f, m.length * ink.coerceIn(0f, 1f), part, true)
                    drawPath(part, DGreen, style = Stroke(3.2.dp.toPx(), cap = StrokeCap.Round))
                }
            }
        }
        Column(
            Modifier.weight(1f).pressableTile(14.dp, pressedScale = 0.98f, onClick = onOpen).padding(start = 8.dp, end = 12.dp, top = 10.dp, bottom = 10.dp),
        ) {
            Text(s.name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(fee, fontSize = 12.5.sp, color = tone, maxLines = 1)
            if (s.recent.isNotEmpty()) {
                Spacer(Modifier.height(5.dp))
                RecentDots(s.recent)
            }
        }
    }
}

/** The batch's last sessions for this student: filled = came, ring = missed. */
@Composable
private fun RecentDots(recent: List<Boolean>, big: Boolean = false) {
    val d = if (big) 12.dp else 8.dp
    Row(horizontalArrangement = Arrangement.spacedBy(if (big) 6.dp else 4.dp), verticalAlignment = Alignment.CenterVertically) {
        recent.forEach { came ->
            Canvas(Modifier.size(d)) {
                if (came) drawCircle(DGreen) else drawCircle(Color(0xFFCBD5E1), style = Stroke(1.3.dp.toPx()))
            }
        }
    }
}

// ---- Student sheet -----------------------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun StudentSheet(
    api: PartnerApi,
    token: String,
    studentId: Long,
    batchName: String,
    canDesk: Boolean,
    onDismiss: () -> Unit,
    onRemoved: () -> Unit,
) {
    var reload by remember { mutableIntStateOf(0) }
    var load by remember { mutableStateOf<DeskLoad<StudentDetail>>(DeskLoad.Loading) }
    var months by remember { mutableIntStateOf(1) }
    var method by remember { mutableStateOf(DeskMethod.CASH) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var confirmRemove by remember { mutableStateOf(false) }
    var justPaid by remember { mutableIntStateOf(0) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val context = LocalContext.current

    LaunchedEffect(reload) {
        val next = deskLoad { api.studentDetail(token, studentId) }
        if (next is DeskLoad.Ready || load !is DeskLoad.Ready) load = next
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        when (val l = load) {
            DeskLoad.Loading -> Box(Modifier.fillMaxWidth().height(320.dp))
            is DeskLoad.Failed -> Text(l.message, color = DRed, modifier = Modifier.padding(24.dp))
            is DeskLoad.Ready -> {
                val d = l.value
                val s = d.student
                val (fee, tone) = feeLine(s)
                Column(Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).navigationBarsPadding()) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(52.dp).clip(CircleShape).background(DBlueTint), contentAlignment = Alignment.Center) {
                            Text(initialsOf(s.name), fontSize = 18.sp, fontWeight = FontWeight.Bold, color = DBlue)
                        }
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(s.name, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = DInk)
                            Text("$batchName · +91 ${s.phone}", fontSize = 13.sp, color = DMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        }
                    }

                    Spacer(Modifier.height(18.dp))
                    Text(fee, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = tone)
                    Spacer(Modifier.height(8.dp))
                    FeeStrip(paidUntil = s.paidUntil, pulse = justPaid)

                    if (canDesk) {
                        Spacer(Modifier.height(20.dp))
                        Text("Collect fees", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                        Spacer(Modifier.height(8.dp))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            listOf(1, 2, 3, 6).forEach { m ->
                                DeskChip(if (m == 1) "1 month" else "$m months", months == m, Modifier.weight(1f)) { Haptics.tick(view); months = m }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                        MethodPicker(method) { Haptics.tick(view); method = it }
                        error?.let { Text(it, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(top = 8.dp)) }
                        Spacer(Modifier.height(10.dp))
                        Button(
                            onClick = {
                                busy = true; error = null
                                scope.launch {
                                    try {
                                        api.collectBatchFee(token, studentId, months, method.api)
                                        Haptics.money(view)
                                        justPaid++
                                        reload++
                                    } catch (e: Exception) {
                                        Haptics.reject(view); error = e.readable()
                                    } finally { busy = false }
                                }
                            },
                            enabled = !busy,
                            colors = ButtonDefaults.buttonColors(containerColor = DBlue),
                            shape = RoundedCornerShape(14.dp),
                            modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp),
                        ) {
                            Text(
                                if (busy) "Collecting…" else "Collect ₹" + formatInr((d.monthlyFee * months).toDouble()) + " · " + method.label,
                                fontSize = 15.sp, fontWeight = FontWeight.SemiBold,
                            )
                        }
                    }

                    if (d.attendance.isNotEmpty()) {
                        Spacer(Modifier.height(22.dp))
                        val came = d.attendance.count { it.second }
                        Text("Came to $came of the last ${d.attendance.size} classes", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                        Spacer(Modifier.height(8.dp))
                        RecentDots(d.attendance.map { it.second }, big = true)
                    }

                    Spacer(Modifier.height(22.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        DeskChip("WhatsApp", false, Modifier.weight(1f)) {
                            Haptics.tick(view)
                            val msg = if (s.overdue || s.dueSoon) {
                                "Hi, a reminder that ${s.name}'s $batchName fees " +
                                    (if (s.overdue) "were due on ${shortDate(s.paidUntil)}" else "are due on ${shortDate(s.paidUntil)}") +
                                    ". ₹${formatInr(d.monthlyFee.toDouble())} a month. Thank you!"
                            } else "Hi, this is about ${s.name}'s $batchName classes."
                            openWhatsApp(context, s.phone, msg)
                        }
                        DeskChip("Call", false, Modifier.weight(1f)) { Haptics.tick(view); dial(context, s.phone) }
                    }

                    if (d.payments.isNotEmpty()) {
                        Spacer(Modifier.height(22.dp))
                        Text("Fees paid", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                        d.payments.forEach { p ->
                            Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(
                                        if (p.method == "opening") "Earlier payments" else (if (p.months == 1) "1 month" else "${p.months} months") + " · " + methodLabel(p.method),
                                        fontSize = 14.sp, color = DInk,
                                    )
                                    Text(
                                        listOfNotNull(shortDate(p.collectedAt), p.collectedBy?.let { "by $it" }, p.paidUntil?.let { "covers till ${shortDate(it)}" })
                                            .joinToString(" · "),
                                        fontSize = 12.sp, color = DFaint,
                                    )
                                }
                                Text("₹" + formatInr(p.amount.toDouble()), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk)
                            }
                            Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFF1F5F9)))
                        }
                    }

                    if (canDesk) {
                        Spacer(Modifier.height(10.dp))
                        TextButton(onClick = { Haptics.tick(view); confirmRemove = true }, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp)) {
                            Text("Remove from $batchName", color = DRed, fontWeight = FontWeight.SemiBold)
                        }
                    }
                    Spacer(Modifier.height(20.dp))

                    if (confirmRemove) {
                        AlertDialog(
                            onDismissRequest = { confirmRemove = false },
                            containerColor = Color.White,
                            title = { Text("Remove ${s.name}?") },
                            text = { Text("Their seat frees up. Fees and attendance stay on record, and enrolling the same number again brings them back.") },
                            confirmButton = {
                                TextButton(onClick = {
                                    confirmRemove = false
                                    scope.launch {
                                        try { api.removeStudent(token, studentId); Haptics.warn(view); onRemoved() } catch (e: Exception) {
                                            Haptics.reject(view); error = e.readable()
                                        }
                                    }
                                }) { Text("Remove", color = DRed, fontWeight = FontWeight.SemiBold) }
                            },
                            dismissButton = { TextButton(onClick = { confirmRemove = false }) { Text("Keep", color = DMuted) } },
                        )
                    }
                }
            }
        }
    }
}

/**
 * Six months as tiles, two back to three ahead. Covered months are green, the
 * months a student is behind on are hatched red, this month is outlined. A new
 * payment makes the newly covered tiles pop in.
 */
@Composable
private fun FeeStrip(paidUntil: String?, pulse: Int) {
    val until = remember(paidUntil) {
        paidUntil?.let { runCatching { SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(it) }.getOrNull() }
    }
    val pop = remember { Animatable(1f) }
    LaunchedEffect(pulse) {
        if (pulse > 0) { pop.snapTo(0.6f); pop.animateTo(1f, spring(dampingRatio = 0.4f, stiffness = 300f)) }
    }
    val months = remember {
        (-2..3).map { off ->
            Calendar.getInstance().apply { set(Calendar.DAY_OF_MONTH, 1); add(Calendar.MONTH, off) }
        }
    }
    val now = Calendar.getInstance()
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        months.forEach { m ->
            val monthEnd = (m.clone() as Calendar).apply { add(Calendar.MONTH, 1); add(Calendar.DAY_OF_YEAR, -1) }
            val covered = until != null && !until.before(monthEnd.time)
            val partly = until != null && !covered && !until.before(m.time)
            val isNow = m.get(Calendar.YEAR) == now.get(Calendar.YEAR) && m.get(Calendar.MONTH) == now.get(Calendar.MONTH)
            val behind = !covered && !partly && !m.after(now) && until != null
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Canvas(Modifier.fillMaxWidth().height(38.dp)) {
                    val r = CornerRadius(8.dp.toPx())
                    val sc = if (covered || partly) pop.value else 1f
                    val inset = (1f - sc) * size.width / 2
                    when {
                        covered -> drawRoundRect(DGreen, Offset(inset, inset), Size(size.width - inset * 2, size.height - inset * 2), r)
                        partly -> {
                            val lapsed = until != null && until.before(now.time)
                            drawRoundRect(if (lapsed) DRed.copy(alpha = 0.08f) else DGreen.copy(alpha = 0.12f), cornerRadius = r)
                            if (lapsed) clipRect(left = size.width * 0.5f) {
                                var x = -size.height
                                while (x < size.width) {
                                    drawLine(DRed.copy(alpha = 0.35f), Offset(x, size.height), Offset(x + size.height, 0f), 1.6.dp.toPx())
                                    x += 7.dp.toPx()
                                }
                            }
                            clipRect(right = size.width * 0.5f) { drawRoundRect(DGreen, cornerRadius = r) }
                        }
                        behind -> {
                            drawRoundRect(DRed.copy(alpha = 0.08f), cornerRadius = r)
                            clipRect {
                                var x = -size.height
                                while (x < size.width) {
                                    drawLine(DRed.copy(alpha = 0.35f), Offset(x, size.height), Offset(x + size.height, 0f), 1.6.dp.toPx())
                                    x += 7.dp.toPx()
                                }
                            }
                        }
                        else -> drawRoundRect(Color(0xFFF1F5F9), cornerRadius = r)
                    }
                    if (isNow) drawRoundRect(DInk, cornerRadius = r, style = Stroke(1.8.dp.toPx()))
                }
                Spacer(Modifier.height(4.dp))
                Text(
                    SimpleDateFormat("MMM", Locale.ENGLISH).format(m.time),
                    fontSize = 11.sp, color = if (isNow) DInk else DFaint, fontWeight = if (isNow) FontWeight.Bold else FontWeight.Normal,
                )
            }
        }
    }
}

// ---- Enrol sheet ---------------------------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EnrolSheet(
    batch: BatchRow,
    onDismiss: () -> Unit,
    onEnrol: (name: String, phone: String, months: Int, method: DeskMethod, done: (String?) -> Unit) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var months by remember { mutableIntStateOf(1) }
    var method by remember { mutableStateOf(DeskMethod.CASH) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val view = LocalView.current
    val ok = name.isNotBlank() && phone.length == 10 && !busy
    val full = batch.capacity != null && batch.students >= batch.capacity

    ModalBottomSheet(
        onDismissRequest = { if (!busy) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().imePadding().navigationBarsPadding().padding(horizontal = 20.dp)) {
            Text("Enrol in ${batch.name}", fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk)
            Text(
                "₹${formatInr(batch.monthlyFee.toDouble())} a month" + (batch.capacity?.let { " · ${batch.students} of $it seats taken" } ?: ""),
                fontSize = 13.sp, color = if (full) DRed else DMuted,
            )
            Spacer(Modifier.height(16.dp))
            DeskField(name, { name = it; error = null }, "Student name")
            Spacer(Modifier.height(10.dp))
            DeskField(phone, { phone = it.filter(Char::isDigit).take(10); error = null }, "Parent's or student's mobile", KeyboardType.Phone, prefix = "+91 ")
            Text(
                "Already on this batch? Their fees are extended instead of adding them twice.",
                fontSize = 12.sp, color = DFaint, modifier = Modifier.padding(top = 6.dp),
            )
            Spacer(Modifier.height(16.dp))
            Text("Paying for", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
            Spacer(Modifier.height(8.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(1, 2, 3, 6).forEach { m ->
                    DeskChip(if (m == 1) "1 month" else "$m months", months == m, Modifier.weight(1f)) { Haptics.tick(view); months = m }
                }
            }
            Spacer(Modifier.height(8.dp))
            MethodPicker(method) { Haptics.tick(view); method = it }
            error?.let { Text(it, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(top = 10.dp)) }
            Spacer(Modifier.height(14.dp))
            Button(
                onClick = {
                    busy = true; error = null
                    onEnrol(name.trim(), phone, months, method) { err -> busy = false; error = err }
                },
                enabled = ok,
                colors = ButtonDefaults.buttonColors(containerColor = DBlue, disabledContainerColor = DBlue.copy(alpha = 0.35f), disabledContentColor = Color.White),
                shape = RoundedCornerShape(14.dp),
                modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
            ) {
                Text(
                    if (busy) "Enrolling…" else "Enrol and collect ₹" + formatInr((batch.monthlyFee * months).toDouble()),
                    fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold,
                )
            }
            Spacer(Modifier.height(16.dp))
        }
    }
}

// ---- Batch editor ------------------------------------------------------------------------------

private data class BatchForm(
    val name: String,
    val coach: String?,
    val sport: String?,
    val days: List<String>,
    val start: String?,
    val end: String?,
    val fee: Int,
    val capacity: Int?,
    val venueId: Long?,
    val active: Boolean,
)

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun BatchEditorSheet(
    existing: BatchRow?,
    venues: List<StaffPlace>,
    onDismiss: () -> Unit,
    onSave: (BatchForm, (String?) -> Unit) -> Unit,
) {
    val view = LocalView.current
    var name by remember { mutableStateOf(existing?.name ?: "") }
    var coach by remember { mutableStateOf(existing?.coach ?: "") }
    var sport by remember { mutableStateOf(existing?.sport) }
    val days = remember { mutableStateListOf<String>().apply { addAll(existing?.days ?: emptyList()) } }
    var start by remember { mutableStateOf(existing?.startTime ?: "") }
    var end by remember { mutableStateOf(existing?.endTime ?: "") }
    var fee by remember { mutableStateOf(existing?.monthlyFee?.takeIf { it > 0 }?.toString() ?: "") }
    var cap by remember { mutableStateOf(existing?.capacity?.toString() ?: "") }
    var venueId by remember { mutableStateOf(existing?.venueId ?: venues.singleOrNull()?.id) }
    var active by remember { mutableStateOf(existing?.isActive ?: true) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val startOk = start.isBlank() || minutesOf(start) != null
    val endOk = end.isBlank() || (minutesOf(end) != null && (minutesOf(start) == null || minutesOf(end)!! > minutesOf(start)!!))
    val canSave = name.isNotBlank() && fee.isNotBlank() && startOk && endOk && !busy

    ModalBottomSheet(
        onDismissRequest = { if (!busy) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().imePadding().navigationBarsPadding()) {
            Column(Modifier.weight(1f, fill = false).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp)) {
                Text(if (existing == null) "New batch" else "Edit ${existing.name}", fontSize = 21.sp, fontWeight = FontWeight.Bold, color = DInk)
                Spacer(Modifier.height(16.dp))
                DeskField(name, { name = it }, "Batch name, e.g. Juniors evening")
                Spacer(Modifier.height(10.dp))
                DeskField(coach, { coach = it }, "Coach")

                Spacer(Modifier.height(18.dp))
                Text("Sport", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                Spacer(Modifier.height(8.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    SPORTS.forEach { sp ->
                        DeskChip(sp, sport.equals(sp, ignoreCase = true)) { Haptics.tick(view); sport = if (sport.equals(sp, true)) null else sp }
                    }
                }

                Spacer(Modifier.height(18.dp))
                Text("Days", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    WEEK.forEach { d ->
                        val on = d in days
                        Box(
                            Modifier.size(42.dp)
                                .pressableTile(21.dp, pressedScale = 0.9f) { if (on) days.remove(d) else days.add(d) }
                                .clip(CircleShape)
                                .background(if (on) DBlueTint else Color.White)
                                .border(if (on) 1.5.dp else 1.dp, if (on) DBlue else DHair, CircleShape),
                            contentAlignment = Alignment.Center,
                        ) { Text(d.take(2), fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Normal, color = if (on) DBlue else DInk) }
                    }
                }
                Text(
                    if (days.isEmpty()) "No days picked: the batch runs every day." else days.sortedBy { WEEK.indexOf(it) }.joinToString(", "),
                    fontSize = 12.sp, color = DFaint, modifier = Modifier.padding(top = 6.dp),
                )

                Spacer(Modifier.height(18.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    DeskField(start, { start = it.filter { c -> c.isDigit() || c == ':' }.take(5) }, "Starts (06:30)", KeyboardType.Number, Modifier.weight(1f))
                    DeskField(end, { end = it.filter { c -> c.isDigit() || c == ':' }.take(5) }, "Ends (07:30)", KeyboardType.Number, Modifier.weight(1f))
                }
                if (!startOk || !endOk) Text("Use 24-hour time like 06:30, and end after it starts.", fontSize = 12.sp, color = DRed, modifier = Modifier.padding(top = 6.dp))

                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    DeskField(fee, { fee = it.filter(Char::isDigit).take(6) }, "Fee a month", KeyboardType.Number, Modifier.weight(1f), prefix = "₹ ")
                    DeskField(cap, { cap = it.filter(Char::isDigit).take(3) }, "Seats (optional)", KeyboardType.Number, Modifier.weight(1f))
                }

                if (venues.size > 1) {
                    Spacer(Modifier.height(18.dp))
                    Text("Where it runs", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = DMuted)
                    Spacer(Modifier.height(8.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        venues.forEach { v -> DeskChip(v.name, venueId == v.id) { Haptics.tick(view); venueId = v.id } }
                    }
                    Text("Fees taken for this batch go into that venue's cash drawer.", fontSize = 12.sp, color = DFaint, modifier = Modifier.padding(top = 6.dp))
                }

                if (existing != null) {
                    Spacer(Modifier.height(18.dp))
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("Taking classes", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = DInk)
                            Text(if (active) "Shows on the desk every day it runs." else "Paused: hidden from today's board.", fontSize = 12.5.sp, color = DMuted)
                        }
                        Switch(
                            checked = active, onCheckedChange = { Haptics.tick(view); active = it },
                            colors = SwitchDefaults.colors(checkedTrackColor = DBlue, checkedThumbColor = Color.White),
                        )
                    }
                }
                Spacer(Modifier.height(16.dp))
            }
            Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 12.dp)) {
                error?.let { Text(it, fontSize = 13.sp, color = DRed, modifier = Modifier.padding(bottom = 8.dp)) }
                Button(
                    onClick = {
                        busy = true; error = null
                        onSave(
                            BatchForm(
                                name.trim(), coach.trim().ifEmpty { null }, sport, days.sortedBy { WEEK.indexOf(it) },
                                start.trim().ifEmpty { null }, end.trim().ifEmpty { null }, fee.toIntOrNull() ?: 0,
                                cap.toIntOrNull(), venueId, active,
                            ),
                        ) { err -> busy = false; error = err }
                    },
                    enabled = canSave,
                    colors = ButtonDefaults.buttonColors(containerColor = DBlue, disabledContainerColor = DBlue.copy(alpha = 0.35f), disabledContentColor = Color.White),
                    shape = RoundedCornerShape(14.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
                ) { Text(if (busy) "Saving…" else if (existing == null) "Create batch" else "Save changes", fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold) }
            }
        }
    }
}
