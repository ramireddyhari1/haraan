package com.haraan.partner.staff

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ApiException
import com.haraan.partner.PartnerApi
import com.haraan.partner.StaffMember
import com.haraan.partner.StaffPlace
import com.haraan.partner.StaffPreset
import com.haraan.partner.StaffRoster
import com.haraan.partner.STAFF_PERMISSIONS
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.Problem
import com.haraan.partner.ui.components.ProblemState
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.components.rememberMoneyMotion
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.exp
import kotlin.math.min
import kotlin.math.roundToLong
import kotlin.math.sin

// ---- Palette -------------------------------------------------------------

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hair = Color(0xFFE2E8F0)
private val Page = Color(0xFFF6F8FC)
private val Blue = Color(0xFF2563EB)
private val BlueTint = Color(0xFFEFF4FF)
private val Navy = Color(0xFF0B1C46)
private val Green = Color(0xFF16A34A)
private val Amber = Color(0xFFD97706)
private val Red = Color(0xFFDC2626)
private val Wall = Color(0xFFEEF3FB)
private val Wood = Color(0xFFC08A5A)
private val WoodDark = Color(0xFF9A6A40)
private val Skin = Color(0xFFE7B892)
private val Hair2 = Color(0xFF2B2B35)

/** One colour per role, so a pass is recognisable from across the room. */
private fun roleColor(role: String): Color = when (role) {
    "manager" -> Color(0xFF1E3A8A)
    "box_office" -> Blue
    "gate" -> Color(0xFF0D9488)
    "finance" -> Color(0xFF6D28D9)
    else -> Color(0xFF475569)
}

/** What each capability lets a person do, said the way an owner would say it. */
private fun permLine(p: String): String = when (p) {
    "bookings" -> "Take walk-ins and manage bookings"
    "checkin" -> "Check people in at the gate"
    "pricing" -> "Change prices, slots and listings"
    "reports" -> "See money, reports and settlement"
    else -> p
}

private fun presetPitch(key: String): String = when (key) {
    "manager" -> "Runs the place. Everything except the team."
    "box_office" -> "Front desk: walk-ins, bookings and check-in."
    "gate" -> "Scans tickets at the entrance. Nothing else."
    "finance" -> "Sees money and reports. Can't take bookings."
    else -> "Pick exactly what they can do."
}

private fun permsFor(key: String, presets: List<StaffPreset>): List<String>? =
    presets.firstOrNull { it.key == key }?.permissions

/** Map a permission set back onto the preset it matches, like the server does. */
private fun presetKeyOf(perms: Collection<String>, presets: List<StaffPreset>): String =
    presets.firstOrNull { it.permissions.toSet() == perms.toSet() }?.key ?: "custom"

private fun initials(name: String): String =
    name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2)
        .joinToString("") { it.first().uppercase() }.ifEmpty { "?" }

private fun parseIso(iso: String?): Long? {
    if (iso.isNullOrBlank()) return null
    for (p in listOf("yyyy-MM-dd'T'HH:mm:ssXXX", "yyyy-MM-dd'T'HH:mm:ss.SSSSSSXXX", "yyyy-MM-dd'T'HH:mm:ss.SSSXXX")) {
        runCatching { return SimpleDateFormat(p, Locale.US).parse(iso)?.time }
    }
    return null
}

private fun clockTime(iso: String?): String? =
    parseIso(iso)?.let { SimpleDateFormat("h:mm a", Locale.ENGLISH).format(it) }

private fun agoText(iso: String?): String? {
    val t = parseIso(iso) ?: return null
    val mins = ((System.currentTimeMillis() - t) / 60_000).coerceAtLeast(0)
    return when {
        mins < 2 -> "just now"
        mins < 60 -> "$mins min ago"
        mins < 60 * 24 -> "${mins / 60}h ago"
        mins < 60 * 24 * 7 -> "${mins / (60 * 24)}d ago"
        else -> SimpleDateFormat("d MMM", Locale.ENGLISH).format(t)
    }
}

/** The one line under a name that says where this person is right now. */
private fun statusLine(m: StaffMember): Pair<String, Color> = when {
    m.isSuspended -> "Access paused" to Red
    m.isOnShift -> buildString {
        append("On shift")
        m.onShiftVenue?.let { append(" · ").append(it) }
        clockTime(m.onShiftSince)?.let { append(" since ").append(it) }
    } to Green
    m.isPending -> "Invited · hasn't signed in yet" to Amber
    else -> (agoText(m.lastSeenAt)?.let { "Last active $it" } ?: "Off duty") to Muted
}

private fun placesLine(m: StaffMember): String? {
    val names = (m.venues + m.events).map { it.name }
    return when {
        names.isEmpty() -> null
        names.size <= 2 -> "Only " + names.joinToString(" & ")
        else -> "Only ${names.first()} + ${names.size - 1} more"
    }
}

private sealed interface Load {
    data object Loading : Load
    data class Failed(val problem: Problem, val message: String) : Load
    data class Ready(val roster: StaffRoster) : Load
}

private fun Throwable.readable(): String = when (this) {
    is ApiException -> message ?: "Something went wrong"
    is java.io.IOException -> "No connection. Check the internet and try again."
    else -> message ?: "Something went wrong"
}

// ---- Screen ----------------------------------------------------------------

/**
 * The owner's desk team. Who is at the counter right now, what each person took
 * today, and the levers an owner actually reaches for: change a role, limit
 * someone to one venue, pause a login the moment a person leaves, resend an invite.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StaffScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableIntStateOf(0) }
    var load by remember { mutableStateOf<Load>(Load.Loading) }
    var editing by remember { mutableStateOf<StaffMember?>(null) }
    var adding by remember { mutableStateOf(false) }
    var openId by remember { mutableStateOf<Long?>(null) }
    val snack = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    val view = LocalView.current

    LaunchedEffect(reload) {
        if (load !is Load.Ready) load = Load.Loading
        load = try {
            Load.Ready(api.staffRoster(token))
        } catch (e: ApiException) {
            Load.Failed(if (e.code == 401) Problem.SignedOut else if (e.code >= 500) Problem.Server else Problem.Other, e.readable())
        } catch (e: java.io.IOException) {
            Load.Failed(Problem.Offline, e.readable())
        } catch (e: Exception) {
            Load.Failed(Problem.Other, e.readable())
        }
    }

    /** Run a write, buzz the outcome, say it, and refresh. */
    fun act(done: String?, buzz: (android.view.View) -> Unit = Haptics::confirm, block: suspend () -> Unit) {
        scope.launch {
            try {
                block()
                buzz(view)
                reload++
                done?.let { snack.showSnackbar(it) }
            } catch (e: Exception) {
                Haptics.reject(view)
                snack.showSnackbar(e.readable())
            }
        }
    }

    val roster = (load as? Load.Ready)?.roster

    Scaffold(
        containerColor = Page,
        snackbarHost = { SnackbarHost(snack) },
        topBar = {
            Row(
                Modifier.fillMaxWidth().background(Page).statusBarsPadding().padding(start = 4.dp, end = 12.dp, top = 4.dp, bottom = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                Text("Desk team", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.weight(1f))
                if (roster != null && roster.members.isNotEmpty()) {
                    AddPill { Haptics.tick(view); adding = true }
                }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val l = load) {
                Load.Loading -> StaffSkeleton()
                is Load.Failed -> ProblemState(l.problem, onRetry = { reload++ }, detail = l.message)
                is Load.Ready -> if (l.roster.members.isEmpty()) {
                    EmptyDesk(onAdd = { Haptics.tick(view); adding = true })
                } else {
                    TeamList(l.roster, onOpen = { openId = it.id })
                }
            }
        }
    }

    val open = roster?.members?.firstOrNull { it.id == openId }
    if (open != null) {
        StaffDetailSheet(
            m = open,
            onDismiss = { openId = null },
            onEdit = { openId = null; editing = open },
            onInvite = { act("Invite sent to ${open.email}") { api.inviteStaff(token, open.id) } },
            onPause = { pause ->
                act(if (pause) "${open.name.substringBefore(' ')} is paused and signed out" else "${open.name.substringBefore(' ')} can sign in again",
                    buzz = if (pause) Haptics::warn else Haptics::confirm) {
                    api.suspendStaff(token, open.id, pause)
                }
            },
            onRemove = {
                openId = null
                act("${open.name} removed from the team", buzz = Haptics::warn) { api.deleteStaff(token, open.id) }
            },
        )
    }

    if ((adding || editing != null) && roster != null) {
        val existing = editing
        StaffEditorSheet(
            existing = existing,
            roster = roster,
            onDismiss = { adding = false; editing = null },
            onSave = { form, finish ->
                scope.launch {
                    try {
                        if (existing == null) {
                            val invited = api.createStaff(token, form.name, form.email, form.password, form.perms, form.venueIds, form.eventIds)
                            Haptics.confirm(view)
                            finish(null)
                            adding = false
                            reload++
                            snack.showSnackbar(
                                when {
                                    form.password != null -> "${form.name} can sign in now"
                                    invited -> "Invite emailed to ${form.email}"
                                    else -> "${form.name} added, but the invite email didn't send. Resend it from their pass."
                                },
                            )
                        } else {
                            api.updateStaff(token, existing.id, form.name, form.perms, form.venueIds, form.eventIds)
                            Haptics.confirm(view)
                            finish(null)
                            editing = null
                            reload++
                            snack.showSnackbar("Saved")
                        }
                    } catch (e: Exception) {
                        Haptics.reject(view)
                        finish(e.readable())
                    }
                }
            },
        )
    }
}

@Composable
private fun AddPill(onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .pressScale(interaction, 0.94f)
            .clip(RoundedCornerShape(999.dp))
            .background(Blue)
            .pressShade(interaction, 0.12f)
            .clickable(interaction, indication = null, onClick = onClick)
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
        Text("Add person", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun TeamList(roster: StaffRoster, onOpen: (StaffMember) -> Unit) {
    val members = roster.members
    val onShift = members.filter { it.isOnShift }
    val rest = members.filterNot { it.isOnShift }
    LazyColumn(
        Modifier.fillMaxSize(),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item(key = "hero") { DeskHero(members) }
        if (onShift.isNotEmpty()) {
            item(key = "h-on") { SectionLabel("At the desk now") }
            items(onShift, key = { "m${it.id}" }) { StaffRow(it) { onOpen(it) } }
        }
        if (rest.isNotEmpty()) {
            item(key = "h-rest") { SectionLabel(if (onShift.isEmpty()) "Your team" else "Off duty") }
            items(rest, key = { "m${it.id}" }) { StaffRow(it) { onOpen(it) } }
        }
        item(key = "foot") {
            Text(
                "Pausing someone signs them out of every phone straight away. Their bookings and payments stay on record.",
                fontSize = 12.sp, color = Faint, lineHeight = 17.sp,
                modifier = Modifier.padding(horizontal = 6.dp, vertical = 8.dp),
            )
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted,
        modifier = Modifier.padding(start = 4.dp, top = 10.dp, bottom = 2.dp),
    )
}

// ---- Hero: the front desk ---------------------------------------------------

/**
 * The desk as a picture. People on shift stand behind the counter wearing their
 * pass; everyone else's pass hangs on the key board on the back wall. A pending
 * invite hangs still in its plastic sleeve, a paused pass hangs with a red clip.
 * The wall clock shows the real time. Under it, the two facts an owner wants.
 */
@Composable
private fun DeskHero(members: List<StaffMember>) {
    val onShift = members.filter { it.isOnShift }
    val hanging = members.filterNot { it.isOnShift }
    val takenToday = members.sumOf { it.collectedToday }
    val payments = members.sumOf { it.paymentsToday }

    val rise = remember { Animatable(0f) }
    val swing = remember { Animatable(1f) }
    LaunchedEffect(Unit) {
        launch { rise.animateTo(1f, spring(dampingRatio = 0.55f, stiffness = Spring.StiffnessLow)) }
        launch { swing.snapTo(1f); swing.animateTo(0f, tween(2600, easing = LinearEasing)) }
    }
    val breathe by rememberInfiniteTransition(label = "breathe").animateFloat(
        0f, 1f, infiniteRepeatable(tween(3200, easing = LinearEasing)), label = "b",
    )
    var now by remember { mutableStateOf(Calendar.getInstance()) }
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(30_000)
            now = Calendar.getInstance()
        }
    }

    val money = rememberMoneyMotion(takenToday, "staff.desk.today")

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(24.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(24.dp)),
    ) {
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(168.dp)
                .semantics {
                    contentDescription = "${onShift.size} at the desk, ${hanging.size} off duty"
                },
        ) {
            drawDeskScene(onShift, hanging, rise.value, swing.value, breathe, now)
        }
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 18.dp, vertical = 14.dp),
            verticalAlignment = Alignment.Bottom,
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    when (onShift.size) {
                        0 -> "Nobody on shift"
                        1 -> "1 person at the desk"
                        else -> "${onShift.size} people at the desk"
                    },
                    fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink,
                )
                Text(
                    "${members.size} in the team" + (members.count { it.isPending }.takeIf { it > 0 }?.let { " · $it invite pending" } ?: ""),
                    fontSize = 12.5.sp, color = Muted,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "₹" + formatInr(money.shown.roundToLong().toDouble()),
                    fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink,
                )
                Text(
                    if (payments == 0) "taken at the desk today" else "at the desk today · $payments paid",
                    fontSize = 12.sp, color = Muted,
                )
            }
        }
    }
}

private fun DrawScope.drawDeskScene(
    onShift: List<StaffMember>,
    hanging: List<StaffMember>,
    rise: Float,
    swing: Float,
    breathe: Float,
    now: Calendar,
) {
    val w = size.width
    val h = size.height
    val u = h / 168f

    // Back wall and floor line.
    drawRect(Wall, size = Size(w, h))
    drawRect(Color(0xFFE3EAF6), topLeft = Offset(0f, h * 0.70f), size = Size(w, h * 0.30f))

    // Key board on the left: a cork strip with hooks, one per off-duty pass.
    val boardLeft = 16 * u
    val boardTop = 18 * u
    val shown = hanging.take(4)
    val boardW = (shown.size.coerceAtLeast(2) * 30 + 14) * u
    drawRoundRect(Color(0xFFD9C3A5), Offset(boardLeft, boardTop), Size(boardW, 12 * u), CornerRadius(4 * u))
    drawRoundRect(Color(0xFFBFA585), Offset(boardLeft, boardTop + 9 * u), Size(boardW, 3 * u), CornerRadius(2 * u))
    shown.forEachIndexed { i, m ->
        val hx = boardLeft + (14 + i * 30) * u
        val hy = boardTop + 12 * u
        drawCircle(Color(0xFF8B95A7), 2.2f * u, Offset(hx, hy))
        // Each pass swings out once on entrance and settles; staggered so the
        // board doesn't move as one plank.
        val phase = i * 0.9f
        val angle = 14f * swing * sin((1f - swing) * 9f + phase) * exp(-(1f - swing) * 1.5f)
        rotate(angle, pivot = Offset(hx, hy)) {
            hangingPass(m, Offset(hx, hy), u)
        }
    }
    if (hanging.size > 4) {
        drawCircle(Color.White, 9 * u, Offset(boardLeft + boardW + 14 * u, boardTop + 30 * u))
    }

    // Wall clock, right side, showing the real time.
    val cx = w - 40 * u
    val cy = 38 * u
    val r = 18 * u
    drawCircle(Color.White, r, Offset(cx, cy))
    drawCircle(Ink, r, Offset(cx, cy), style = Stroke(2.4f * u))
    for (t in 0 until 12) {
        val a = t / 12f * 2 * PI
        val o = if (t % 3 == 0) 4.5f * u else 2.5f * u
        drawLine(
            Faint,
            Offset(cx + (r - 3 * u) * sin(a).toFloat(), cy - (r - 3 * u) * cos(a).toFloat()),
            Offset(cx + (r - 3 * u - o) * sin(a).toFloat(), cy - (r - 3 * u - o) * cos(a).toFloat()),
            1.2f * u,
        )
    }
    val hr = now.get(Calendar.HOUR) + now.get(Calendar.MINUTE) / 60f
    val mn = now.get(Calendar.MINUTE) / 60f
    val ha = hr / 12f * 2 * PI
    val ma = mn * 2 * PI
    drawLine(Ink, Offset(cx, cy), Offset(cx + r * 0.5f * sin(ha).toFloat(), cy - r * 0.5f * cos(ha).toFloat()), 2.6f * u, StrokeCap.Round)
    drawLine(Ink, Offset(cx, cy), Offset(cx + r * 0.75f * sin(ma).toFloat(), cy - r * 0.75f * cos(ma).toFloat()), 1.8f * u, StrokeCap.Round)
    drawCircle(Blue, 2.2f * u, Offset(cx, cy))

    // People on shift, standing behind the counter, rising up on entrance.
    val counterTop = h * 0.66f
    val people = onShift.take(4)
    if (people.isNotEmpty()) {
        val span = min(w * 0.56f, people.size * 62 * u)
        val startX = w * 0.55f - span / 2 + span / people.size / 2
        people.forEachIndexed { i, m ->
            val px = startX + i * (span / people.size)
            val lift = (1f - rise) * 46 * u
            val bob = sin((breathe + i * 0.3f) * 2 * PI).toFloat() * 0.9f * u
            person(m, Offset(px, counterTop + lift + bob), u)
        }
    } else {
        // An empty counter still has its bell, waiting.
        val bx = w * 0.55f
        drawArc(Color(0xFFCBD5E1), 180f, 180f, true, Offset(bx - 9 * u, counterTop - 9 * u), Size(18 * u, 18 * u))
        drawCircle(Color(0xFF94A3B8), 2 * u, Offset(bx, counterTop - 10 * u))
    }

    // The counter itself: a wood top over a navy front with a slim highlight.
    drawRoundRect(Wood, Offset(w * 0.18f, counterTop - 2 * u), Size(w * 0.74f, 8 * u), CornerRadius(3 * u))
    drawRect(WoodDark, Offset(w * 0.18f, counterTop + 5 * u), Size(w * 0.74f, 2 * u))
    drawRect(
        Brush.verticalGradient(listOf(Color(0xFF14295E), Navy), startY = counterTop + 7 * u, endY = h),
        Offset(w * 0.2f, counterTop + 7 * u), Size(w * 0.70f, h - counterTop - 7 * u),
    )
    drawLine(Color.White.copy(alpha = 0.10f), Offset(w * 0.2f, counterTop + 18 * u), Offset(w * 0.9f, counterTop + 18 * u), 1.5f * u)
    // A small card reader on the counter: this is where money is taken.
    drawRoundRect(Color(0xFF334155), Offset(w * 0.80f, counterTop - 14 * u), Size(16 * u, 12 * u), CornerRadius(2.5f * u))
    drawRoundRect(Color(0xFF7DD3FC), Offset(w * 0.80f + 3 * u, counterTop - 12 * u), Size(10 * u, 4 * u), CornerRadius(1 * u))
}

/** A person from the chest up: head, hair, shoulders in role colour, pass on a lanyard. */
private fun DrawScope.person(m: StaffMember, base: Offset, u: Float) {
    val c = roleColor(m.role)
    // Shoulders (cut off by the counter drawn after).
    drawRoundRect(c, Offset(base.x - 20 * u, base.y - 30 * u), Size(40 * u, 40 * u), CornerRadius(16 * u))
    // Neck + head.
    drawRect(Skin, Offset(base.x - 4 * u, base.y - 36 * u), Size(8 * u, 8 * u))
    drawCircle(Skin, 11 * u, Offset(base.x, base.y - 46 * u))
    drawArc(Hair2, 180f, 180f, true, Offset(base.x - 11.5f * u, base.y - 58 * u), Size(23 * u, 20 * u))
    // Lanyard V and the pass, which pokes just above the counter top.
    drawLine(Color.White.copy(alpha = 0.85f), Offset(base.x - 7 * u, base.y - 30 * u), Offset(base.x, base.y - 14 * u), 1.6f * u)
    drawLine(Color.White.copy(alpha = 0.85f), Offset(base.x + 7 * u, base.y - 30 * u), Offset(base.x, base.y - 14 * u), 1.6f * u)
    drawRoundRect(Color.White, Offset(base.x - 6 * u, base.y - 14 * u), Size(12 * u, 12 * u), CornerRadius(2 * u))
    drawRect(c, Offset(base.x - 6 * u, base.y - 14 * u), Size(12 * u, 3.5f * u))
}

/** A pass hanging from a hook by its strap. */
private fun DrawScope.hangingPass(m: StaffMember, hook: Offset, u: Float) {
    val c = if (m.isSuspended) Faint else roleColor(m.role)
    val top = hook.y + 12 * u
    drawLine(c.copy(alpha = 0.8f), hook, Offset(hook.x - 4 * u, top), 1.6f * u)
    drawLine(c.copy(alpha = 0.8f), hook, Offset(hook.x + 4 * u, top), 1.6f * u)
    val card = Size(20 * u, 27 * u)
    val tl = Offset(hook.x - card.width / 2, top)
    drawRoundRect(Color.White, tl, card, CornerRadius(3 * u))
    drawRoundRect(Color(0x220F172A), tl, card, CornerRadius(3 * u), style = Stroke(0.8f * u))
    drawRoundRect(c, tl, Size(card.width, 6 * u), CornerRadius(3 * u))
    drawRect(c, Offset(tl.x, tl.y + 3 * u), Size(card.width, 3 * u))
    drawCircle(c.copy(alpha = 0.25f), 4 * u, Offset(hook.x, tl.y + 13 * u))
    drawLine(Hair, Offset(tl.x + 4 * u, tl.y + 21 * u), Offset(tl.x + card.width - 4 * u, tl.y + 21 * u), 1.4f * u, StrokeCap.Round)
    when {
        m.isPending -> drawRoundRect(
            Amber, Offset(tl.x - 2 * u, tl.y - 2 * u), Size(card.width + 4 * u, card.height + 4 * u), CornerRadius(4 * u),
            style = Stroke(1.2f * u, pathEffect = PathEffect.dashPathEffect(floatArrayOf(3 * u, 2.5f * u))),
        )
        m.isSuspended -> drawRoundRect(Red, Offset(hook.x - 4 * u, top - 3 * u), Size(8 * u, 5 * u), CornerRadius(1.5f * u))
    }
}

// ---- Rows --------------------------------------------------------------------

@Composable
private fun StaffRow(m: StaffMember, onClick: () -> Unit) {
    val (status, tone) = statusLine(m)
    Row(
        Modifier
            .fillMaxWidth()
            .pressableTile(20.dp, pressedScale = 0.97f, onClick = onClick)
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(20.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        PassBadge(m, Modifier.size(width = 40.dp, height = 52.dp))
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    m.name, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = if (m.isSuspended) Muted else Ink,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false),
                )
                Spacer(Modifier.width(8.dp))
                RoleTag(m.roleLabel, roleColor(m.role))
            }
            Spacer(Modifier.height(3.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (m.isOnShift) {
                    LiveDot()
                    Spacer(Modifier.width(6.dp))
                }
                Text(status, fontSize = 12.5.sp, color = tone, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            placesLine(m)?.let {
                Text(it, fontSize = 12.sp, color = Faint, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        if (m.collectedToday != 0.0) {
            Spacer(Modifier.width(10.dp))
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(m.collectedToday), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
                Text("today", fontSize = 11.sp, color = Faint)
            }
        }
    }
}

@Composable
private fun RoleTag(label: String, color: Color) {
    Text(
        label,
        fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = color,
        modifier = Modifier.clip(RoundedCornerShape(6.dp)).background(color.copy(alpha = 0.09f))
            .padding(horizontal = 7.dp, vertical = 2.dp),
    )
}

@Composable
private fun LiveDot() {
    val t by rememberInfiniteTransition(label = "live").animateFloat(
        0f, 1f, infiniteRepeatable(tween(1600, easing = LinearEasing)), label = "ring",
    )
    Canvas(Modifier.size(10.dp)) {
        drawCircle(Green.copy(alpha = (1f - t) * 0.35f), size.minDimension / 2 * (0.5f + t * 0.5f))
        drawCircle(Green, size.minDimension / 4)
    }
}

/** A small drawn staff pass: strap, role band, initials. Greyed when paused. */
@Composable
private fun PassBadge(m: StaffMember, modifier: Modifier) {
    val c = if (m.isSuspended) Faint else roleColor(m.role)
    val letters = initials(m.name)
    Box(modifier, contentAlignment = Alignment.Center) {
        Canvas(Modifier.fillMaxSize()) {
            val u = size.width / 40f
            drawRoundRect(c.copy(alpha = 0.55f), Offset(15 * u, 0f), Size(10 * u, 9 * u), CornerRadius(2 * u))
            val tl = Offset(2 * u, 7 * u)
            val card = Size(36 * u, 45 * u)
            drawRoundRect(Color.White, tl, card, CornerRadius(6 * u))
            drawRoundRect(c, tl, card, CornerRadius(6 * u), style = Stroke(1.4f * u))
            drawRoundRect(c, tl, Size(card.width, 11 * u), CornerRadius(6 * u))
            drawRect(c, Offset(tl.x, tl.y + 6 * u), Size(card.width, 5 * u))
            drawRoundRect(Color.White.copy(alpha = 0.9f), Offset(15 * u, 10 * u), Size(10 * u, 2.4f * u), CornerRadius(1.2f * u))
            if (m.isPending) {
                drawRoundRect(
                    Amber, Offset(0.5f * u, 5.5f * u), Size(39 * u, 48 * u), CornerRadius(7 * u),
                    style = Stroke(1.2f * u, pathEffect = PathEffect.dashPathEffect(floatArrayOf(4 * u, 3 * u))),
                )
            }
        }
        Text(
            letters, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = c,
            modifier = Modifier.padding(top = 14.dp),
        )
    }
}

// ---- Empty + loading ---------------------------------------------------------

@Composable
private fun EmptyDesk(onAdd: () -> Unit) {
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(28.dp))
        val swing = remember { Animatable(1f) }
        LaunchedEffect(Unit) { swing.animateTo(0f, tween(2800, easing = LinearEasing)) }
        Canvas(Modifier.fillMaxWidth().height(170.dp)) {
            val u = size.height / 170f
            val cx = size.width / 2
            // An empty counter with three bare hooks above it.
            drawRoundRect(Color(0xFFD9C3A5), Offset(cx - 60 * u, 14 * u), Size(120 * u, 11 * u), CornerRadius(4 * u))
            for (i in 0..2) {
                val hx = cx - 34 * u + i * 34 * u
                drawCircle(Color(0xFF8B95A7), 2.4f * u, Offset(hx, 25 * u))
                if (i == 1) {
                    val a = 16f * swing.value * sin((1f - swing.value) * 10f)
                    rotate(a, pivot = Offset(hx, 25 * u)) {
                        val tl = Offset(hx - 12 * u, 38 * u)
                        drawLine(Blue.copy(alpha = 0.6f), Offset(hx, 25 * u), Offset(hx - 5 * u, 38 * u), 1.6f * u)
                        drawLine(Blue.copy(alpha = 0.6f), Offset(hx, 25 * u), Offset(hx + 5 * u, 38 * u), 1.6f * u)
                        drawRoundRect(
                            Blue, tl, Size(24 * u, 32 * u), CornerRadius(4 * u),
                            style = Stroke(1.5f * u, pathEffect = PathEffect.dashPathEffect(floatArrayOf(4 * u, 3 * u))),
                        )
                        drawLine(Blue, Offset(hx - 5 * u, 54 * u), Offset(hx + 5 * u, 54 * u), 2f * u, StrokeCap.Round)
                        drawLine(Blue, Offset(hx, 49 * u), Offset(hx, 59 * u), 2f * u, StrokeCap.Round)
                    }
                }
            }
            val top = 112 * u
            drawRoundRect(Wood, Offset(cx - 120 * u, top), Size(240 * u, 9 * u), CornerRadius(3 * u))
            drawRoundRect(Navy, Offset(cx - 114 * u, top + 9 * u), Size(228 * u, 46 * u), CornerRadius(2 * u))
            drawArc(Color(0xFFCBD5E1), 180f, 180f, true, Offset(cx + 50 * u, top - 10 * u), Size(20 * u, 20 * u))
            drawCircle(Faint, 2.2f * u, Offset(cx + 60 * u, top - 11 * u))
        }
        Spacer(Modifier.height(18.dp))
        Text("Nobody at the desk yet", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(6.dp))
        Text(
            "Give your front-desk people their own login. They take walk-ins and check people in; you choose whether they see money.",
            fontSize = 14.sp, color = Muted, lineHeight = 20.sp,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        )
        Spacer(Modifier.height(22.dp))
        Button(
            onClick = onAdd,
            colors = ButtonDefaults.buttonColors(containerColor = Blue),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier.heightIn(min = 52.dp).fillMaxWidth(),
        ) { Text("Add your first desk person", fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
        Spacer(Modifier.height(40.dp))
    }
}

@Composable
private fun StaffSkeleton() {
    val x by rememberInfiniteTransition(label = "sk").animateFloat(
        -600f, 1400f, infiniteRepeatable(tween(1250, easing = LinearEasing)), label = "sweep",
    )
    val sweep = Brush.linearGradient(
        listOf(Color(0xFFEDF1F7), Color(0xFFF7FAFE), Color(0xFFEDF1F7)),
        start = Offset(x, 0f), end = Offset(x + 600f, 0f),
    )
    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.fillMaxWidth().height(232.dp).clip(RoundedCornerShape(24.dp)).background(sweep))
        repeat(3) { Box(Modifier.fillMaxWidth().height(78.dp).clip(RoundedCornerShape(20.dp)).background(sweep)) }
    }
}

// ---- Detail sheet ------------------------------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun StaffDetailSheet(
    m: StaffMember,
    onDismiss: () -> Unit,
    onEdit: () -> Unit,
    onInvite: () -> Unit,
    onPause: (Boolean) -> Unit,
    onRemove: () -> Unit,
) {
    val view = LocalView.current
    var confirmPause by remember { mutableStateOf(false) }
    var confirmRemove by remember { mutableStateOf(false) }
    val first = m.name.substringBefore(' ')

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).navigationBarsPadding(),
        ) {
            BigPass(m)
            Spacer(Modifier.height(18.dp))

            // Today, in two facts.
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Fact("Taken today", if (m.collectedToday == 0.0) "₹0" else "₹" + formatInr(m.collectedToday),
                    if (m.paymentsToday == 1) "1 payment" else "${m.paymentsToday} payments", Modifier.weight(1f))
                val (status, tone) = statusLine(m)
                Fact(
                    when {
                        m.isOnShift -> "On shift"
                        m.isSuspended || m.isPending -> "Status"
                        else -> "Last active"
                    },
                    if (m.isOnShift) clockTime(m.onShiftSince)?.let { "since $it" } ?: "now" else when {
                        m.isSuspended -> "Paused"
                        m.isPending -> "Invited"
                        else -> agoText(m.lastSeenAt) ?: "Off duty"
                    },
                    if (m.isOnShift) m.onShiftVenue ?: "" else if (m.isPending) "hasn't signed in" else "",
                    Modifier.weight(1f),
                    tone = if (m.isOnShift) Green else tone,
                )
            }

            Spacer(Modifier.height(20.dp))
            Text("What $first can do", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
            Spacer(Modifier.height(6.dp))
            STAFF_PERMISSIONS.forEach { p ->
                val has = p in m.permissions
                Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                    CheckMark(has)
                    Spacer(Modifier.width(12.dp))
                    Text(permLine(p), fontSize = 14.sp, color = if (has) Ink else Faint)
                }
            }
            Spacer(Modifier.height(4.dp))
            Text(
                placesLine(m)?.let { "$it." } ?: "Works at all your venues and events.",
                fontSize = 13.sp, color = Muted, modifier = Modifier.padding(top = 4.dp),
            )

            Spacer(Modifier.height(20.dp))
            SheetAction("Change role or places", Blue) { Haptics.tick(view); onEdit() }
            if (m.isPending && !m.isSuspended) {
                SheetAction("Email the invite again", Blue) { Haptics.tick(view); onInvite() }
            }
            if (m.isSuspended) {
                SheetAction("Restore access", Blue) { Haptics.tick(view); onPause(false) }
            } else {
                SheetAction("Pause access", Amber) { Haptics.tick(view); confirmPause = true }
            }
            SheetAction("Remove from team", Red) { Haptics.tick(view); confirmRemove = true }
            Spacer(Modifier.height(20.dp))
        }
    }

    if (confirmPause) {
        AlertDialog(
            onDismissRequest = { confirmPause = false },
            containerColor = Color.White,
            title = { Text("Pause $first?") },
            text = { Text("$first is signed out of every phone now and can't sign back in until you restore access. Nothing they recorded is lost.") },
            confirmButton = {
                TextButton(onClick = { confirmPause = false; onPause(true) }) { Text("Pause", color = Amber, fontWeight = FontWeight.SemiBold) }
            },
            dismissButton = { TextButton(onClick = { confirmPause = false }) { Text("Cancel", color = Muted) } },
        )
    }
    if (confirmRemove) {
        AlertDialog(
            onDismissRequest = { confirmRemove = false },
            containerColor = Color.White,
            title = { Text("Remove ${m.name}?") },
            text = {
                Text(
                    "Their login is deleted and they're signed out everywhere. Bookings and payments they took stay in your records. " +
                        "If they might come back, pause them instead.",
                )
            },
            confirmButton = {
                TextButton(onClick = { confirmRemove = false; onRemove() }) { Text("Remove", color = Red, fontWeight = FontWeight.SemiBold) }
            },
            dismissButton = { TextButton(onClick = { confirmRemove = false }) { Text("Keep", color = Muted) } },
        )
    }
}

/**
 * The person's pass, large: it hangs from a lanyard and swings into place when the
 * sheet opens; a tap knocks it and it swings again. The whole pass (card, initials,
 * role, stamp) turns as one object about the lanyard's top.
 */
@Composable
private fun BigPass(m: StaffMember) {
    val c = if (m.isSuspended) Faint else roleColor(m.role)
    val swing = remember { Animatable(-9f) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    LaunchedEffect(m.id) { swing.animateTo(0f, spring(dampingRatio = 0.22f, stiffness = 60f)) }
    // A barcode that is the same for the same person, every time.
    val bars = remember(m.id) {
        val r = java.util.Random(m.id * 7919L + 17)
        List(22) { 1 + r.nextInt(3) }
    }
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.TopCenter) {
        Box(
            Modifier
                .size(width = 190.dp, height = 210.dp)
                .graphicsLayer {
                    rotationZ = swing.value
                    transformOrigin = TransformOrigin(0.5f, 0f)
                }
                .clickable(remember { MutableInteractionSource() }, indication = null) {
                    Haptics.tick(view)
                    scope.launch { swing.snapTo(7f); swing.animateTo(0f, spring(dampingRatio = 0.22f, stiffness = 60f)) }
                },
            contentAlignment = Alignment.TopCenter,
        ) {
            Canvas(Modifier.fillMaxSize()) {
                val u = size.width / 190f
                val pivot = Offset(size.width / 2, 0f)
                // Lanyard.
                drawLine(c, pivot, Offset(pivot.x - 22 * u, 52 * u), 5 * u, StrokeCap.Round)
                drawLine(c, pivot, Offset(pivot.x + 22 * u, 52 * u), 5 * u, StrokeCap.Round)
                drawRoundRect(Color(0xFF94A3B8), Offset(pivot.x - 10 * u, 48 * u), Size(20 * u, 14 * u), CornerRadius(3 * u))
                // Card.
                val tl = Offset(pivot.x - 70 * u, 58 * u)
                val card = Size(140 * u, 148 * u)
                drawRoundRect(Color(0x140F172A), tl + Offset(0f, 4 * u), card, CornerRadius(14 * u))
                drawRoundRect(Color.White, tl, card, CornerRadius(14 * u))
                drawRoundRect(c, tl, card, CornerRadius(14 * u), style = Stroke(2 * u))
                drawRoundRect(c, tl, Size(card.width, 34 * u), CornerRadius(14 * u))
                drawRect(c, Offset(tl.x, tl.y + 20 * u), Size(card.width, 14 * u))
                drawRoundRect(Color.White, Offset(pivot.x - 16 * u, tl.y + 8 * u), Size(32 * u, 6 * u), CornerRadius(3 * u))
                drawCircle(c.copy(alpha = 0.12f), 24 * u, Offset(pivot.x, tl.y + 62 * u))
                // Barcode along the foot of the pass.
                val total = bars.sum() + bars.size
                val bw = (card.width - 36 * u) / total
                var x = tl.x + 18 * u
                bars.forEachIndexed { i, b ->
                    if (i % 2 == 0) drawRect(c.copy(alpha = 0.55f), Offset(x, tl.y + 120 * u), Size(b * bw, 14 * u))
                    x += (b + 1) * bw
                }
            }
            Column(
                Modifier.padding(top = 105.dp).width(140.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(initials(m.name), fontSize = 21.sp, fontWeight = FontWeight.Bold, color = c)
                Spacer(Modifier.height(14.dp))
                Text(
                    m.roleLabel.uppercase(), fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
                    letterSpacing = 1.6.sp, color = c, maxLines = 1,
                )
            }
            if (m.isSuspended) {
                // A paused pass carries a rubber stamp across it.
                Text(
                    "PAUSED",
                    fontSize = 17.sp, fontWeight = FontWeight.Black, letterSpacing = 3.sp, color = Red,
                    modifier = Modifier
                        .padding(top = 150.dp)
                        .graphicsLayer { rotationZ = -14f }
                        .border(2.4.dp, Red, RoundedCornerShape(5.dp))
                        .background(Color.White.copy(alpha = 0.85f), RoundedCornerShape(5.dp))
                        .padding(horizontal = 12.dp, vertical = 3.dp),
                )
            }
        }
    }
    Spacer(Modifier.height(6.dp))
    Text(m.name, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink, modifier = Modifier.fillMaxWidth(),
        textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    Text(m.email, fontSize = 13.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
        modifier = Modifier.fillMaxWidth().padding(top = 2.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
}

@Composable
private fun Fact(label: String, value: String, sub: String, modifier: Modifier, tone: Color = Ink) {
    Column(
        modifier.clip(RoundedCornerShape(16.dp)).background(Page).padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(label, fontSize = 12.sp, color = Muted)
        Spacer(Modifier.height(2.dp))
        Text(value, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = tone, maxLines = 1, overflow = TextOverflow.Ellipsis)
        if (sub.isNotBlank()) Text(sub, fontSize = 12.sp, color = Faint, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun CheckMark(on: Boolean) {
    Canvas(Modifier.size(20.dp)) {
        if (on) {
            drawCircle(Green.copy(alpha = 0.12f))
            val p = Path().apply {
                moveTo(size.width * 0.28f, size.height * 0.52f)
                lineTo(size.width * 0.44f, size.height * 0.68f)
                lineTo(size.width * 0.74f, size.height * 0.36f)
            }
            drawPath(p, Green, style = Stroke(2.dp.toPx(), cap = StrokeCap.Round))
        } else {
            drawCircle(Hair, style = Stroke(1.4.dp.toPx()))
            drawLine(Faint, Offset(size.width * 0.34f, size.height / 2), Offset(size.width * 0.66f, size.height / 2), 1.6.dp.toPx(), StrokeCap.Round)
        }
    }
}

@Composable
private fun SheetAction(label: String, tone: Color, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .pressableTile(14.dp, pressedScale = 0.98f, onClick = onClick)
            .heightIn(min = 52.dp)
            .padding(horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = tone, modifier = Modifier.weight(1f))
        Canvas(Modifier.size(14.dp)) {
            val p = Path().apply {
                moveTo(size.width * 0.35f, size.height * 0.2f)
                lineTo(size.width * 0.68f, size.height * 0.5f)
                lineTo(size.width * 0.35f, size.height * 0.8f)
            }
            drawPath(p, Faint, style = Stroke(1.8.dp.toPx(), cap = StrokeCap.Round))
        }
    }
    Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFF1F5F9)))
}

// ---- Add / edit sheet ----------------------------------------------------------

private data class StaffForm(
    val name: String,
    val email: String,
    val password: String?,
    val perms: List<String>,
    val venueIds: List<Long>,
    val eventIds: List<Long>,
)

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun StaffEditorSheet(
    existing: StaffMember?,
    roster: StaffRoster,
    onDismiss: () -> Unit,
    /** Save, then call back with null on success or an error to show. */
    onSave: (StaffForm, (String?) -> Unit) -> Unit,
) {
    val view = LocalView.current
    var name by remember { mutableStateOf(existing?.name ?: "") }
    var email by remember { mutableStateOf(existing?.email ?: "") }
    var invite by remember { mutableStateOf(true) }
    var password by remember { mutableStateOf("") }
    val perms = remember {
        mutableStateListOf<String>().apply {
            addAll(existing?.permissions ?: permsFor("box_office", roster.presets) ?: listOf("bookings", "checkin"))
        }
    }
    var role by remember { mutableStateOf(presetKeyOf(perms, roster.presets)) }
    val venueIds = remember { mutableStateListOf<Long>().apply { addAll(existing?.venues?.map { it.id } ?: emptyList()) } }
    val eventIds = remember { mutableStateListOf<Long>().apply { addAll(existing?.events?.map { it.id } ?: emptyList()) } }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val emailOk = existing != null || Regex("^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$").matches(email.trim())
    val passOk = existing != null || invite || password.length >= 6
    val canSave = name.isNotBlank() && emailOk && passOk && perms.isNotEmpty() && !saving

    ModalBottomSheet(
        onDismissRequest = { if (!saving) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().imePadding().navigationBarsPadding()) {
            Column(
                Modifier.weight(1f, fill = false).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp),
            ) {
                Text(
                    if (existing == null) "Add a desk person" else "Edit ${existing.name.substringBefore(' ')}",
                    fontSize = 21.sp, fontWeight = FontWeight.Bold, color = Ink,
                )
                Spacer(Modifier.height(16.dp))

                Field(name, { name = it; error = null }, "Name", KeyboardType.Text)
                if (existing == null) {
                    Spacer(Modifier.height(10.dp))
                    Field(email, { email = it.trim(); error = null }, "Email they'll sign in with", KeyboardType.Email)
                }

                Spacer(Modifier.height(22.dp))
                Text("Role", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                Spacer(Modifier.height(8.dp))
                val keys = roster.presets.map { it.key } + "custom"
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    keys.forEach { key ->
                        val label = roster.presets.firstOrNull { it.key == key }?.label ?: "Custom"
                        RoleOption(key, label, presetPitch(key), selected = role == key) {
                            Haptics.tick(view)
                            role = key
                            permsFor(key, roster.presets)?.let { perms.clear(); perms.addAll(it) }
                        }
                    }
                }
                AnimatedVisibility(role == "custom", enter = expandVertically() + fadeIn(), exit = shrinkVertically() + fadeOut()) {
                    Column(Modifier.padding(top = 10.dp)) {
                        STAFF_PERMISSIONS.forEach { p ->
                            Row(
                                Modifier.fillMaxWidth().heightIn(min = 48.dp)
                                    .pressableTile(12.dp, pressedScale = 0.98f) { if (p in perms) perms.remove(p) else perms.add(p) }
                                    .padding(horizontal = 4.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text(permLine(p), fontSize = 14.sp, color = Ink, modifier = Modifier.weight(1f))
                                Switch(
                                    checked = p in perms,
                                    onCheckedChange = { on -> Haptics.tick(view); if (on) perms.add(p) else perms.remove(p) },
                                    colors = SwitchDefaults.colors(checkedTrackColor = Blue, checkedThumbColor = Color.White),
                                )
                            }
                        }
                        if (perms.isEmpty()) Text("Pick at least one thing they can do.", fontSize = 12.5.sp, color = Red)
                    }
                }

                val places = roster.venues.map { 'v' to it } + roster.events.map { 'e' to it }
                if (places.isNotEmpty()) {
                    Spacer(Modifier.height(22.dp))
                    Text("Where they work", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                    Spacer(Modifier.height(8.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        PlaceChip("Everywhere", venueIds.isEmpty() && eventIds.isEmpty()) {
                            Haptics.tick(view); venueIds.clear(); eventIds.clear()
                        }
                        places.forEach { (kind, place) ->
                            val list = if (kind == 'v') venueIds else eventIds
                            PlaceChip(place.name, place.id in list) {
                                Haptics.tick(view)
                                if (place.id in list) list.remove(place.id) else list.add(place.id)
                            }
                        }
                    }
                    Text(
                        if (venueIds.isEmpty() && eventIds.isEmpty()) "They'll see every venue and event you run."
                        else "They'll only see the places picked here.",
                        fontSize = 12.5.sp, color = Faint, modifier = Modifier.padding(top = 8.dp),
                    )
                }

                if (existing == null) {
                    Spacer(Modifier.height(22.dp))
                    Text("How they sign in", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Muted)
                    Spacer(Modifier.height(8.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Segment("Email an invite", invite, Modifier.weight(1f)) { Haptics.tick(view); invite = true }
                        Segment("Set a password", !invite, Modifier.weight(1f)) { Haptics.tick(view); invite = false }
                    }
                    AnimatedVisibility(!invite) {
                        Column(Modifier.padding(top = 10.dp)) {
                            Field(password, { password = it; error = null }, "Password (6 or more)", KeyboardType.Password, secret = true)
                        }
                    }
                    Text(
                        if (invite) "They get an email to set their own password. The link works for 60 minutes; you can resend it."
                        else "Tell them the password in person. They can change it later.",
                        fontSize = 12.5.sp, color = Faint, modifier = Modifier.padding(top = 8.dp),
                    )
                }
                Spacer(Modifier.height(16.dp))
            }

            Column(Modifier.fillMaxWidth().background(Color.White).padding(horizontal = 20.dp, vertical = 12.dp)) {
                error?.let {
                    Text(it, fontSize = 13.sp, color = Red, modifier = Modifier.padding(bottom = 8.dp))
                }
                Button(
                    onClick = {
                        saving = true
                        error = null
                        onSave(
                            StaffForm(
                                name = name.trim(),
                                email = email.trim(),
                                password = if (existing == null && !invite) password else null,
                                perms = perms.toList(),
                                venueIds = venueIds.toList(),
                                eventIds = eventIds.toList(),
                            ),
                        ) { err -> saving = false; error = err }
                    },
                    enabled = canSave,
                    colors = ButtonDefaults.buttonColors(containerColor = Blue, disabledContainerColor = Blue.copy(alpha = 0.35f), disabledContentColor = Color.White),
                    shape = RoundedCornerShape(14.dp),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp),
                ) {
                    Text(
                        when {
                            saving -> "Saving…"
                            existing != null -> "Save changes"
                            invite -> "Add and send invite"
                            else -> "Add person"
                        },
                        fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold,
                    )
                }
            }
        }
    }
}

@Composable
private fun Field(value: String, onChange: (String) -> Unit, label: String, type: KeyboardType, secret: Boolean = false) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        label = { Text(label) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = type),
        visualTransformation = if (secret) PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
        shape = RoundedCornerShape(14.dp),
        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Blue, focusedLabelColor = Blue, unfocusedBorderColor = Hair),
        modifier = Modifier.fillMaxWidth(),
    )
}

/** A role, as a row: its drawn glyph, its name, and what it means in one line. */
@Composable
private fun RoleOption(key: String, label: String, pitch: String, selected: Boolean, onClick: () -> Unit) {
    val border by animateColorAsState(if (selected) Blue else Hair, label = "rb")
    val bg by animateColorAsState(if (selected) BlueTint else Color.White, label = "rbg")
    val wiggle = remember { Animatable(0f) }
    LaunchedEffect(selected) {
        if (selected) { wiggle.snapTo(1f); wiggle.animateTo(0f, spring(dampingRatio = 0.3f, stiffness = 300f)) }
    }
    Row(
        Modifier
            .fillMaxWidth()
            .pressableTile(16.dp, pressedScale = 0.98f, onClick = onClick)
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .border(BorderStroke(if (selected) 1.6.dp else 1.dp, border), RoundedCornerShape(16.dp))
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(42.dp).clip(RoundedCornerShape(12.dp)).background(roleColor(key).copy(alpha = 0.10f)),
            contentAlignment = Alignment.Center,
        ) {
            Canvas(Modifier.size(26.dp)) {
                rotate(wiggle.value * 12f) { roleGlyph(key, roleColor(key)) }
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(label, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink)
            Text(pitch, fontSize = 12.5.sp, color = Muted, lineHeight = 16.sp)
        }
        Canvas(Modifier.size(22.dp)) {
            if (selected) {
                drawCircle(Blue)
                val p = Path().apply {
                    moveTo(size.width * 0.28f, size.height * 0.52f)
                    lineTo(size.width * 0.44f, size.height * 0.68f)
                    lineTo(size.width * 0.74f, size.height * 0.36f)
                }
                drawPath(p, Color.White, style = Stroke(2.2.dp.toPx(), cap = StrokeCap.Round))
            } else {
                drawCircle(Hair, style = Stroke(1.6.dp.toPx()))
            }
        }
    }
}

/**
 * One drawn object per role, the thing that person actually holds:
 * manager a key, box office a ticket, gate a scanner frame, finance a coin stack,
 * custom a pair of sliders.
 */
private fun DrawScope.roleGlyph(key: String, c: Color) {
    val s = size.width
    val st = Stroke(s * 0.085f, cap = StrokeCap.Round)
    when (key) {
        "manager" -> {
            drawCircle(c, s * 0.2f, Offset(s * 0.32f, s * 0.36f), style = st)
            drawCircle(c, s * 0.06f, Offset(s * 0.32f, s * 0.36f))
            drawLine(c, Offset(s * 0.46f, s * 0.5f), Offset(s * 0.82f, s * 0.86f), st.width, StrokeCap.Round)
            drawLine(c, Offset(s * 0.66f, s * 0.7f), Offset(s * 0.76f, s * 0.6f), st.width, StrokeCap.Round)
            drawLine(c, Offset(s * 0.75f, s * 0.79f), Offset(s * 0.85f, s * 0.69f), st.width, StrokeCap.Round)
        }
        "box_office" -> {
            val p = Path().apply {
                moveTo(s * 0.1f, s * 0.28f); lineTo(s * 0.9f, s * 0.28f); lineTo(s * 0.9f, s * 0.42f)
                quadraticBezierTo(s * 0.8f, s * 0.5f, s * 0.9f, s * 0.58f)
                lineTo(s * 0.9f, s * 0.72f); lineTo(s * 0.1f, s * 0.72f); lineTo(s * 0.1f, s * 0.58f)
                quadraticBezierTo(s * 0.2f, s * 0.5f, s * 0.1f, s * 0.42f); close()
            }
            drawPath(p, c, style = Stroke(s * 0.08f, cap = StrokeCap.Round))
            drawLine(c, Offset(s * 0.62f, s * 0.32f), Offset(s * 0.62f, s * 0.68f), s * 0.05f,
                pathEffect = PathEffect.dashPathEffect(floatArrayOf(s * 0.06f, s * 0.05f)))
            drawLine(c, Offset(s * 0.24f, s * 0.44f), Offset(s * 0.48f, s * 0.44f), s * 0.07f, StrokeCap.Round)
            drawLine(c, Offset(s * 0.24f, s * 0.56f), Offset(s * 0.4f, s * 0.56f), s * 0.07f, StrokeCap.Round)
        }
        "gate" -> {
            val l = s * 0.22f
            listOf(
                Offset(s * 0.12f, s * 0.12f) to listOf(Offset(l, 0f), Offset(0f, l)),
                Offset(s * 0.88f, s * 0.12f) to listOf(Offset(-l, 0f), Offset(0f, l)),
                Offset(s * 0.12f, s * 0.88f) to listOf(Offset(l, 0f), Offset(0f, -l)),
                Offset(s * 0.88f, s * 0.88f) to listOf(Offset(-l, 0f), Offset(0f, -l)),
            ).forEach { (o, arms) -> arms.forEach { drawLine(c, o, o + it, st.width, StrokeCap.Round) } }
            drawRect(c, Offset(s * 0.32f, s * 0.32f), Size(s * 0.14f, s * 0.14f))
            drawRect(c, Offset(s * 0.54f, s * 0.32f), Size(s * 0.14f, s * 0.14f))
            drawRect(c, Offset(s * 0.32f, s * 0.54f), Size(s * 0.14f, s * 0.14f))
            drawLine(Color(0xFFEF4444), Offset(s * 0.06f, s * 0.5f), Offset(s * 0.94f, s * 0.5f), s * 0.05f)
        }
        "finance" -> {
            for (i in 0..2) {
                val y = s * (0.74f - i * 0.17f)
                drawOval(Color.White, Offset(s * 0.18f, y - s * 0.1f), Size(s * 0.64f, s * 0.2f))
                drawOval(c, Offset(s * 0.18f, y - s * 0.1f), Size(s * 0.64f, s * 0.2f), style = Stroke(s * 0.075f))
            }
            translate(s * 0.5f, s * 0.38f) {
                drawLine(c, Offset(-s * 0.08f, -s * 0.04f), Offset(s * 0.08f, -s * 0.04f), s * 0.05f, StrokeCap.Round)
            }
        }
        else -> {
            listOf(0.3f to 0.34f, 0.7f to 0.66f).forEachIndexed { i, (y, knob) ->
                val yy = s * (if (i == 0) 0.32f else 0.68f)
                drawLine(c.copy(alpha = 0.45f), Offset(s * 0.12f, yy), Offset(s * 0.88f, yy), s * 0.07f, StrokeCap.Round)
                drawCircle(Color.White, s * 0.11f, Offset(s * knob, yy))
                drawCircle(c, s * 0.11f, Offset(s * knob, yy), style = Stroke(s * 0.075f))
            }
        }
    }
}

@Composable
private fun PlaceChip(label: String, selected: Boolean, onClick: () -> Unit) {
    val border by animateColorAsState(if (selected) Blue else Hair, label = "pc")
    val bg by animateColorAsState(if (selected) BlueTint else Color.White, label = "pcb")
    Text(
        label,
        fontSize = 13.5.sp,
        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
        color = if (selected) Blue else Ink,
        maxLines = 1, overflow = TextOverflow.Ellipsis,
        modifier = Modifier
            .pressableTile(999.dp, pressedScale = 0.94f, onClick = onClick)
            .clip(RoundedCornerShape(999.dp))
            .background(bg)
            .border(1.dp, border, RoundedCornerShape(999.dp))
            .heightIn(min = 40.dp)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    )
}

@Composable
private fun Segment(label: String, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val border by animateColorAsState(if (selected) Blue else Hair, label = "sg")
    val bg by animateColorAsState(if (selected) BlueTint else Color.White, label = "sgb")
    Box(
        modifier
            .pressableTile(12.dp, pressedScale = 0.97f, onClick = onClick)
            .clip(RoundedCornerShape(12.dp))
            .background(bg)
            .border(1.dp, border, RoundedCornerShape(12.dp))
            .heightIn(min = 46.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = if (selected) Blue else Muted)
    }
}
