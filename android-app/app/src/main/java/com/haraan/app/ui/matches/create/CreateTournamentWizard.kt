package com.haraan.app.ui.matches.create

import android.Manifest
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AddAPhoto
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DateRangePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.rememberDateRangePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import coil.compose.AsyncImage
import com.haraan.app.data.LocationRepository
import com.haraan.app.data.LocationState
import com.haraan.app.data.NewTournament
import com.haraan.app.ui.DismissOnBack
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Blue = HaraanColors.EventsBlue
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val BlueTint = HaraanColors.AccentTint
private val Danger = HaraanColors.Danger

// ─────────────────────────────────────────────────────────────────────────────
// Domain. Server values mirror App\Models\Tournament, which owns what each one means.
// ─────────────────────────────────────────────────────────────────────────────

enum class TournamentCategory(val label: String, val serverValue: String) {
    OPEN("Open", "open"),
    CORPORATE("Corporate", "corporate"),
    COMMUNITY("Community", "community"),
    SCHOOL("School", "school"),
    COLLEGE("College", "college"),
    UNIVERSITY("University", "university"),
    SERIES("Series", "series"),
    OTHER("Other", "other"),
}

/**
 * The format every fixture in the tournament follows. [fixedOvers] is the rulebook's
 * number; formats with [oversEditable] take the organiser's instead, starting at
 * [defaultOvers]. The server re-derives all of this, so these are for the UI only.
 */
enum class TournamentFormat(
    val label: String,
    val sub: String,
    val serverValue: String,
    val fixedOvers: Int? = null,
    val oversEditable: Boolean = false,
    val defaultOvers: Int = 20,
    val defaultPlayers: Int = 11,
) {
    T20("T20", "20 overs a side", "t20", fixedOvers = 20),
    T10("T10", "10 overs a side", "t10", fixedOvers = 10),
    ODI("One Day", "50 overs a side", "odi", fixedOvers = 50),
    HUNDRED("The Hundred", "100 balls a side", "hundred"),
    TEST("Test / Multi-day", "Two innings each", "test"),
    BOX("Box Cricket", "Netted arena, short game", "box", oversEditable = true, defaultOvers = 6, defaultPlayers = 8),
    SIXES("Sixes", "6 a side, 5 overs", "sixes", fixedOvers = 5, defaultPlayers = 6),
    CUSTOM("Custom", "Name it, set the overs", "custom", oversEditable = true, defaultOvers = 12),
}

enum class TournamentStructure(val label: String, val sub: String, val serverValue: String) {
    KNOCKOUT("Knockout", "Lose once and you're out", "knockout"),
    LEAGUE("League", "Every team plays every team", "league"),
    LEAGUE_KNOCKOUT("League + Knockouts", "Top of the table go to semis and final", "league_knockout"),
    GROUPS_KNOCKOUT("Groups + Knockouts", "Pools first, then knockouts", "groups_knockout"),
}

enum class TournamentGender(val label: String, val serverValue: String) {
    MEN("Men", "men"),
    WOMEN("Women", "women"),
    MIXED("Mixed", "mixed"),
}

enum class TournamentAgeGroup(val label: String, val serverValue: String) {
    OPEN("Open age", "open"),
    U12("Under 12", "u12"),
    U14("Under 14", "u14"),
    U16("Under 16", "u16"),
    U19("Under 19", "u19"),
    VETERANS("Veterans 35+", "veterans"),
}

/**
 * Everything the wizard collects for one sport. Cricket uses its own format fields; every
 * other sport's format is a [TournamentSportSpec] preset or a custom set of its rules.
 */
class TournamentDraft(val sport: String = "cricket") {
    /** Null for cricket. */
    val sportSpec: TournamentSportSpec? = TournamentSportSpec.forKey(sport)
    val isCricket: Boolean get() = sportSpec == null
    /** Played by sides (gender + players a side), not by entries in events. */
    val isTeam: Boolean get() = sportSpec?.team ?: true
    val sportLabel: String get() = sportSpec?.label ?: "Cricket"

    var banner by mutableStateOf<Uri?>(null)
    var logo by mutableStateOf<Uri?>(null)
    var name by mutableStateOf("")
    var category by mutableStateOf(TournamentCategory.OPEN)
    var categoryOther by mutableStateOf("")
    var gender by mutableStateOf<TournamentGender?>(null)
    var ageGroup by mutableStateOf(TournamentAgeGroup.OPEN)

    var city by mutableStateOf("")
    var venue by mutableStateOf("")
    var district by mutableStateOf("")
    var latitude by mutableStateOf<Double?>(null)
    var longitude by mutableStateOf<Double?>(null)
    /** UTC-midnight millis, as the Material date picker reports them. */
    var startDateUtc by mutableStateOf<Long?>(null)
    var endDateUtc by mutableStateOf<Long?>(null)

    // Cricket
    var format by mutableStateOf(TournamentFormat.T20)
    var formatName by mutableStateOf("")
    var overs by mutableStateOf(TournamentFormat.T20.defaultOvers)
    var matchDays by mutableStateOf(2)
    var playersPerSide by mutableStateOf(sportSpec?.presets?.first()?.players ?: 11)
    var ball by mutableStateOf(BallType.TENNIS)
    var pitch by mutableStateOf(GroundType.TURF)

    // Every other sport. `null` preset = custom.
    var presetKey by mutableStateOf<String?>(sportSpec?.presets?.first()?.key)
    val customRules = mutableStateMapOf<String, Any>().apply { sportSpec?.customDefaults?.let { putAll(it) } }
    val events = mutableStateListOf<String>()
    var surface by mutableStateOf(sportSpec?.surfaces?.firstOrNull()?.first)
    val namedOptions = mutableStateMapOf<String, String>().apply {
        sportSpec?.options?.filterIsInstance<TournamentOption.Named>()?.forEach { put(it.key, it.default) }
    }
    /** Typed numbers for optional numeric options (weight limit); blank = not set. */
    val numberOptions = mutableStateMapOf<String, String>()

    var structure by mutableStateOf(TournamentStructure.KNOCKOUT)
    var teamsCount by mutableStateOf(if (sportSpec?.team == false) 16 else 8)
    var entryFee by mutableStateOf("")
    var prizePool by mutableStateOf("")

    var organizerName by mutableStateOf("")
    var organizerPhone by mutableStateOf("")
    var description by mutableStateOf("")

    fun selectFormat(f: TournamentFormat) {
        format = f
        overs = f.fixedOvers ?: f.defaultOvers
        playersPerSide = f.defaultPlayers
        // A box arena is a box: pick the surface for them, they can still change it.
        if (f == TournamentFormat.BOX) pitch = GroundType.BOX
    }

    /** Pick a non-cricket preset (or null for custom). A preset fixes the players a side. */
    fun selectPreset(key: String?) {
        presetKey = key
        val spec = sportSpec ?: return
        playersPerSide = spec.presets.firstOrNull { it.key == key }?.players ?: spec.customPlayers
    }

    val isCustom: Boolean
        get() = if (isCricket) format == TournamentFormat.CUSTOM else presetKey == null

    /** The rules in force for a non-cricket sport: the preset's, or the custom ones. */
    val activeRules: Map<String, Any>
        get() = sportSpec?.presets?.firstOrNull { it.key == presetKey }?.rules ?: customRules.toMap()

    /** "T20 · 20 overs", "7-a-side · 2 × 25 min", "Standard · best of 3 to 21". */
    val formatSummary: String
        get() {
            if (!isCricket) {
                val spec = sportSpec!!
                val preset = spec.presets.firstOrNull { it.key == presetKey }
                val name = preset?.label ?: formatName.trim().ifEmpty { "Custom" }
                return "$name · ${tournamentRulesSummary(spec.key, activeRules)}"
            }
            val name = if (format == TournamentFormat.CUSTOM && formatName.isNotBlank()) formatName.trim() else format.label
            val detail = when (format) {
                TournamentFormat.HUNDRED -> "100 balls"
                TournamentFormat.TEST -> "$matchDays ${if (matchDays == 1) "day" else "days"}"
                else -> "$overs ${if (overs == 1) "over" else "overs"}"
            }
            return "$name · $detail"
        }

    fun toNewTournament(): NewTournament {
        val spec = sportSpec
        val base = NewTournament(
            sport = sport,
            name = name.trim(),
            category = category.serverValue,
            categoryOther = categoryOther.trim().takeIf { category == TournamentCategory.OTHER && it.isNotEmpty() },
            description = description.trim().ifEmpty { null },
            banner = banner,
            logo = logo,
            gender = if (isTeam) gender?.serverValue else null,
            ageGroup = ageGroup.serverValue,
            city = city.trim(),
            venue = venue.trim().ifEmpty { null },
            district = district.trim().ifEmpty { null },
            state = null,
            latitude = latitude,
            longitude = longitude,
            startDate = isoDate(startDateUtc!!),
            endDate = isoDate(endDateUtc!!),
            matchFormat = format.serverValue,
            formatName = null,
            playersPerSide = null,
            surface = null,
            structure = structure.serverValue,
            teamsCount = teamsCount,
            entryFee = entryFee.toIntOrNull(),
            prizePool = prizePool.trim().ifEmpty { null },
            organizerName = organizerName.trim(),
            organizerPhone = organizerPhone,
        )
        if (spec == null) {
            return base.copy(
                formatName = formatName.trim().takeIf { format == TournamentFormat.CUSTOM && it.isNotEmpty() },
                oversPerInnings = if (format.oversEditable) overs else null,
                matchDays = if (format == TournamentFormat.TEST) matchDays else null,
                ballType = ball.serverValue,
                playersPerSide = playersPerSide,
                surface = pitch.serverValue,
            )
        }
        val custom = presetKey == null
        return base.copy(
            matchFormat = presetKey ?: "custom",
            formatName = if (custom) formatName.trim() else null,
            playersPerSide = if (spec.team) playersPerSide else null,
            surface = surface,
            formatRules = if (custom) customRules.mapValues { it.value.toString() } else emptyMap(),
            events = if (spec.hasEvents) spec.events.map { it.first }.filter { it in events } else emptyList(),
            options = namedOptions.toMap() + numberOptions.filterValues { it.isNotBlank() },
        )
    }
}

/** Indian mobile: ten digits, first one 6–9. The server applies the same rule. */
private fun validPhone(p: String): Boolean = Regex("^[6-9]\\d{9}$").matches(p)

private val utc: TimeZone = TimeZone.getTimeZone("UTC")

private fun isoDate(utcMillis: Long): String =
    SimpleDateFormat("yyyy-MM-dd", Locale.US).apply { timeZone = utc }.format(Date(utcMillis))

private fun prettyDate(utcMillis: Long): String =
    SimpleDateFormat("EEE, d MMM yyyy", Locale.getDefault()).apply { timeZone = utc }.format(Date(utcMillis))

private fun shortDate(utcMillis: Long): String =
    SimpleDateFormat("d MMM", Locale.getDefault()).apply { timeZone = utc }.format(Date(utcMillis))

/** Today's date as the date picker represents it: that calendar day, at UTC midnight. */
private fun todayUtcMillis(): Long {
    val local = Calendar.getInstance()
    return Calendar.getInstance(utc).apply {
        clear()
        set(local.get(Calendar.YEAR), local.get(Calendar.MONTH), local.get(Calendar.DAY_OF_MONTH))
    }.timeInMillis
}

private const val DAY_MS = 24L * 60 * 60 * 1000

private enum class TournamentStep { SPORT, IDENTITY, WHERE_WHEN, FORMAT, DRAW, ORGANISER, REVIEW }

private fun missingOn(d: TournamentDraft, step: TournamentStep): String? = when (step) {
    TournamentStep.SPORT -> null
    TournamentStep.IDENTITY -> when {
        d.name.trim().length < 3 -> "a tournament name"
        d.category == TournamentCategory.OTHER && d.categoryOther.trim().length < 2 -> "what kind of tournament it is"
        d.isTeam && d.gender == null -> "who can play"
        else -> null
    }
    TournamentStep.WHERE_WHEN -> when {
        d.city.trim().length < 2 -> "the city"
        d.startDateUtc == null || d.endDateUtc == null -> "the start and end dates"
        (d.endDateUtc!! - d.startDateUtc!!) / DAY_MS > 180 -> "dates no more than 180 days apart"
        else -> null
    }
    TournamentStep.FORMAT -> {
        val spec = d.sportSpec
        when {
            d.isCustom && d.formatName.trim().length < 2 -> "a name for your format"
            spec != null && spec.hasEvents && d.events.isEmpty() -> "at least one event"
            spec != null && spec.surfaces.isNotEmpty() && d.surface == null -> "the ${spec.surfaceTitle.lowercase()}"
            spec != null && spec.options.filterIsInstance<TournamentOption.OptionalNumber>().any { o ->
                val typed = d.numberOptions[o.key].orEmpty()
                typed.isNotBlank() && (typed.toIntOrNull() ?: -1) !in o.min..o.max
            } -> spec.options.filterIsInstance<TournamentOption.OptionalNumber>()
                .first().let { "a ${it.label.substringBefore(" (").lowercase()} between ${it.min} and ${it.max}" }
            else -> null
        }
    }
    TournamentStep.ORGANISER -> when {
        d.organizerName.trim().length < 2 -> "the organiser's name"
        !validPhone(d.organizerPhone) -> "a valid 10-digit mobile number"
        else -> null
    }
    else -> null
}

/**
 * Host a tournament in any sport the app scores: its identity (banner, logo, name, category,
 * who can play), where and when, the format every fixture follows, the draw, and who teams
 * call to enter.
 *
 * [sport] pre-selects the sport (from the match wizard's sport step); null asks for it first.
 * [onCreate] does the network call; [creating] keeps the commit button busy meanwhile.
 * [loadOrganizer] prefills the organiser from the signed-in account (name, phone).
 */
@Composable
fun CreateTournamentWizard(
    onDismiss: () -> Unit,
    onCreate: (TournamentDraft) -> Unit,
    creating: Boolean,
    sport: String? = null,
    loadOrganizer: suspend () -> Pair<String, String?>? = { null },
    modifier: Modifier = Modifier,
) {
    val askSport = sport == null
    var sportKey by remember { mutableStateOf(sport ?: SportSpec.Cricket.key) }
    // Changing sport starts a fresh draft: formats and events only mean anything within one.
    val draft = remember(sportKey) { TournamentDraft(sportKey) }
    val steps = remember(askSport) {
        listOfNotNull(if (askSport) TournamentStep.SPORT else null) + listOf(
            TournamentStep.IDENTITY, TournamentStep.WHERE_WHEN, TournamentStep.FORMAT,
            TournamentStep.DRAW, TournamentStep.ORGANISER, TournamentStep.REVIEW,
        )
    }
    var step by remember { mutableStateOf(0) }
    val lastStep = steps.lastIndex
    val current = steps[step]

    LaunchedEffect(draft) {
        val (name, phone) = loadOrganizer() ?: return@LaunchedEffect
        if (draft.organizerName.isBlank()) draft.organizerName = name
        if (draft.organizerPhone.isBlank()) {
            draft.organizerPhone = phone.orEmpty().filter(Char::isDigit).takeLast(10)
        }
    }

    DismissOnBack(enabled = !creating) {
        if (step == 0) onDismiss() else step--
    }

    Column(modifier.fillMaxSize().background(Bg)) {
        WizardTopBar(
            step = step,
            total = steps.size,
            title = "Host a Tournament",
            sport = if (current == TournamentStep.SPORT) "" else draft.sportLabel,
            onBack = { if (!creating) { if (step == 0) onDismiss() else step-- } },
            onClose = { if (!creating) onDismiss() },
        )

        AnimatedContent(
            targetState = step,
            transitionSpec = {
                val dir = if (targetState > initialState) 1 else -1
                (slideInHorizontally(tween(260)) { it * dir } + fadeIn(tween(260)))
                    .togetherWith(slideOutHorizontally(tween(260)) { -it * dir } + fadeOut(tween(260)))
            },
            modifier = Modifier.weight(1f),
            label = "tournamentStep",
        ) { index ->
            when (steps[index]) {
                TournamentStep.SPORT -> StepTournamentSport(sportKey) { sportKey = it }
                TournamentStep.IDENTITY -> StepIdentity(draft)
                TournamentStep.WHERE_WHEN -> StepWhereWhen(draft)
                TournamentStep.FORMAT -> if (draft.isCricket) StepFormat(draft) else StepSportFormat(draft, draft.sportSpec!!)
                TournamentStep.DRAW -> StepDraw(draft)
                TournamentStep.ORGANISER -> StepOrganiser(draft)
                TournamentStep.REVIEW -> StepReviewTournament(draft)
            }
        }

        val missing = missingOn(draft, current)
        WizardFooter(
            step = step,
            lastStep = lastStep,
            canContinue = missing == null,
            missing = missing,
            commitLabel = if (creating) "Creating…" else "Create Tournament",
            busy = creating,
            onContinue = { if (step == lastStep) onCreate(draft) else step++ },
        )
    }
}

// ────────────────────────────────────────────────────── Step 0 · Sport ────────
/** The tournament formats a sport offers, for its card: "T20 · One Day · Box Cricket". */
private fun tournamentFormatsLine(sport: String): String {
    val labels = TournamentSportSpec.forKey(sport)?.presets?.map { it.label }
        ?: listOf(TournamentFormat.T20, TournamentFormat.ODI, TournamentFormat.BOX).map { it.label }
    // Three short names fit one line; a long one ("12-minute quarters") would push the third
    // behind an ellipsis, so it is skipped here — the format step still lists every preset.
    val short = labels.filter { it.length <= 16 }
    return (if (short.size >= 3) short else labels).take(3).joinToString(" · ")
}

@Composable
private fun StepTournamentSport(selected: String, onSelect: (String) -> Unit) {
    StepScaffold(
        title = "Which sport?",
        subtitle = "The formats, rules and who enters all follow from the sport.",
    ) {
        SportSpec.supported.forEachIndexed { i, spec ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            SportCard(
                spec = spec,
                selected = spec.key == selected,
                onClick = { onSelect(spec.key) },
                subtitle = tournamentFormatsLine(spec.key),
            )
        }
    }
}

// ─────────────────────────────────────────────────── Step 1 · Identity ────────
@Composable
private fun StepIdentity(d: TournamentDraft) {
    StepScaffold(
        title = "Name your tournament",
        subtitle = "The banner and logo are what teams see first — on your profile and on the tournament page.",
    ) {
        BannerAndLogoPicker(d)
        Spacer(Modifier.height(22.dp))

        FieldLabel("Tournament name")
        WizardTextField(d.name, { d.name = it.take(120) }, "e.g. Kadapa Premier League 2026")
        Spacer(Modifier.height(20.dp))

        FieldLabel("Category")
        ChipRow(TournamentCategory.entries, d.category, { it.label }) { d.category = it }
        AnimatedVisibility(d.category == TournamentCategory.OTHER) {
            Column {
                Spacer(Modifier.height(12.dp))
                WizardTextField(d.categoryOther, { d.categoryOther = it.take(60) }, "What kind? e.g. Police Cup, Village festival")
            }
        }

        Spacer(Modifier.height(20.dp))
        // Racquet sports say who plays through their events (men's singles, mixed doubles),
        // chosen with the format — so only team sports ask for a gender here.
        if (d.isTeam) {
            FieldLabel("Who can play")
            OptionalChipRow(TournamentGender.entries, d.gender, { it.label }) { d.gender = it }
            Spacer(Modifier.height(14.dp))
        }
        FieldLabel("Age group")
        ChipRow(TournamentAgeGroup.entries, d.ageGroup, { it.label }) { d.ageGroup = it }
    }
}

/**
 * A 16:9 banner with the logo sitting over its lower edge — the same composition the
 * tournament page draws, so what is picked here is exactly what gets shown.
 */
@Composable
private fun BannerAndLogoPicker(d: TournamentDraft) {
    val bannerPicker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) d.banner = uri
    }
    val logoPicker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) d.logo = uri
    }
    val imageOnly = PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)

    Box(Modifier.fillMaxWidth()) {
        Box(
            Modifier
                .fillMaxWidth()
                .aspectRatio(16f / 9f)
                .clip(RoundedCornerShape(18.dp))
                .background(Brush.linearGradient(listOf(Color(0xFF0F2A5C), Color(0xFF1D4ED8))))
                .pressable { bannerPicker.launch(imageOnly) },
            contentAlignment = Alignment.Center,
        ) {
            val banner = d.banner
            if (banner != null) {
                AsyncImage(
                    model = banner,
                    contentDescription = "Tournament banner",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
                Row(
                    Modifier
                        .align(Alignment.TopEnd)
                        .padding(10.dp)
                        .clip(RoundedCornerShape(999.dp))
                        .background(Color.Black.copy(alpha = 0.55f))
                        .padding(horizontal = 12.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Filled.AddAPhoto, null, tint = Color.White, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("Change", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            } else {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.AddAPhoto, null, tint = Color.White.copy(alpha = 0.9f), modifier = Modifier.size(26.dp))
                    Spacer(Modifier.height(8.dp))
                    Text("Add banner", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(2.dp))
                    Text("Wide image, 16:9 works best", color = Color.White.copy(alpha = 0.7f), fontSize = 12.sp)
                }
            }
        }

        // Logo, over the banner's bottom-left edge.
        Box(
            Modifier
                .align(Alignment.BottomStart)
                .offset(x = 16.dp, y = 40.dp)
                .size(84.dp)
                .clip(CircleShape)
                .background(Surface)
                .border(BorderStroke(3.dp, Surface), CircleShape)
                .pressable { logoPicker.launch(imageOnly) },
            contentAlignment = Alignment.Center,
        ) {
            val logo = d.logo
            if (logo != null) {
                AsyncImage(
                    model = logo,
                    contentDescription = "Tournament logo",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().padding(3.dp).clip(CircleShape),
                )
            } else {
                Box(
                    Modifier.fillMaxSize().padding(3.dp).clip(CircleShape).background(BlueTint),
                    contentAlignment = Alignment.Center,
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Filled.Add, null, tint = Blue, modifier = Modifier.size(20.dp))
                        Text("Logo", color = Blue, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
    // Room for the logo that hangs below the banner, with the hint beside it.
    Row(Modifier.fillMaxWidth().height(48.dp).padding(start = 112.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(
            if (d.banner == null && d.logo == null) "Banner and logo are optional — tap to add" else "Tap either one to change it",
            color = Text3, fontSize = 12.sp,
        )
    }
}

// ─────────────────────────────────────────────── Step 2 · Where & when ────────
@Composable
private fun StepWhereWhen(d: TournamentDraft) {
    var pickingDates by remember { mutableStateOf(false) }

    StepScaffold(
        title = "Where and when",
        subtitle = "The city puts it in front of teams nearby. Dates cover the first ball to the final.",
    ) {
        FieldLabel("City")
        CityField(d)
        Spacer(Modifier.height(20.dp))

        FieldLabel("Main ground (optional)")
        WizardTextField(d.venue, { d.venue = it.take(150) }, "e.g. YSR Stadium, or leave blank for multiple grounds")
        Spacer(Modifier.height(20.dp))

        FieldLabel("Tournament dates")
        DatesCard(d) { pickingDates = true }
    }

    if (pickingDates) {
        DateRangeDialog(
            startUtc = d.startDateUtc,
            endUtc = d.endDateUtc,
            onDismiss = { pickingDates = false },
            onConfirm = { start, end ->
                d.startDateUtc = start
                d.endDateUtc = end
                pickingDates = false
            },
        )
    }
}

/** Typed city, with suggestions from the city catalogue and a one-tap "use my location". */
@Composable
private fun CityField(d: TournamentDraft) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { LocationRepository(context) }
    val catalogue = remember { runCatching { repo.allCities() }.getOrDefault(emptyList()) }
    var locating by remember { mutableStateOf(false) }
    var locError by remember { mutableStateOf<String?>(null) }
    var justPicked by remember { mutableStateOf(false) }

    fun detect() {
        locating = true
        locError = null
        scope.launch {
            when (val s = repo.detectCurrent()) {
                is LocationState.Resolved -> {
                    val city = listOf(s.city, s.area).firstOrNull { it.isNotBlank() && !it.equals("Unknown", true) }
                    if (city == null) {
                        locError = "Couldn't name your city. Type it instead."
                    } else {
                        d.city = city
                        d.district = s.district
                        d.latitude = s.latitude
                        d.longitude = s.longitude
                        justPicked = true
                    }
                }
                LocationState.Denied -> locError = "Location permission is off. Type the city instead."
                LocationState.ServicesOff -> locError = "Location is switched off on this device."
                else -> locError = "Couldn't read your location. Type the city instead."
            }
            locating = false
        }
    }

    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) detect() else locError = "Location permission is off. Type the city instead."
    }

    WizardTextField(
        value = d.city,
        onChange = {
            d.city = it.take(100)
            // A typed city no longer matches the GPS fix it replaced.
            d.latitude = null
            d.longitude = null
            d.district = ""
            justPicked = false
        },
        placeholder = "e.g. Kadapa",
    )

    val query = d.city.trim()
    val suggestions = if (query.length < 2 || justPicked) emptyList() else catalogue
        .filter { it.name.startsWith(query, ignoreCase = true) && !it.name.equals(query, ignoreCase = true) }
        .take(4)
    if (suggestions.isNotEmpty()) {
        Spacer(Modifier.height(6.dp))
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Surface)
                .border(1.dp, Stroke, RoundedCornerShape(12.dp)),
        ) {
            suggestions.forEachIndexed { i, option ->
                if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(Stroke))
                Row(
                    Modifier.fillMaxWidth()
                        .pressable {
                            d.city = option.name
                            d.district = option.district
                            justPicked = true
                        }
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Filled.LocationOn, null, tint = Text3, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(10.dp))
                    Text(option.name, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                    if (option.district.isNotBlank() && !option.district.equals(option.name, true)) {
                        Text("  ·  ${option.district}", color = Text3, fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }

    Spacer(Modifier.height(8.dp))
    Row(
        Modifier
            .clip(RoundedCornerShape(8.dp))
            .pressable(enabled = !locating) {
                if (repo.hasPermission()) detect() else permission.launch(Manifest.permission.ACCESS_FINE_LOCATION)
            }
            .padding(vertical = 6.dp, horizontal = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (locating) {
            CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Blue)
        } else {
            Icon(Icons.Filled.MyLocation, null, tint = Blue, modifier = Modifier.size(16.dp))
        }
        Spacer(Modifier.width(6.dp))
        Text(
            when {
                locating -> "Getting location…"
                d.latitude != null -> "Location pinned · update"
                else -> "Use my current location"
            },
            color = Blue, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
        )
    }
    locError?.let {
        Text(it, color = Danger, fontSize = 12.sp, lineHeight = 16.sp)
    }
}

@Composable
private fun DatesCard(d: TournamentDraft, onClick: () -> Unit) {
    val start = d.startDateUtc
    val end = d.endDateUtc
    Row(
        Modifier
            .fillMaxWidth()
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, if (start != null) Blue.copy(alpha = 0.5f) else Stroke, RoundedCornerShape(14.dp))
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(40.dp).clip(RoundedCornerShape(11.dp)).background(BlueTint),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.CalendarMonth, null, tint = Blue, modifier = Modifier.size(20.dp)) }
        Spacer(Modifier.width(12.dp))
        if (start == null || end == null) {
            Column(Modifier.weight(1f)) {
                Text("Pick start and end dates", color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                Text("A one-day event? Tap the same date twice.", color = Text3, fontSize = 12.5.sp)
            }
        } else {
            val days = ((end - start) / DAY_MS + 1).toInt()
            Column(Modifier.weight(1f)) {
                Text(
                    if (start == end) prettyDate(start) else "${shortDate(start)}  →  ${prettyDate(end)}",
                    color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold,
                )
                Text(if (days == 1) "One day" else "$days days", color = Text2, fontSize = 12.5.sp)
            }
            Text("Change", color = Blue, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateRangeDialog(
    startUtc: Long?,
    endUtc: Long?,
    onDismiss: () -> Unit,
    onConfirm: (Long, Long) -> Unit,
) {
    val today = remember { todayUtcMillis() }
    val state = rememberDateRangePickerState(
        initialSelectedStartDateMillis = startUtc,
        initialSelectedEndDateMillis = endUtc,
        selectableDates = object : SelectableDates {
            override fun isSelectableDate(utcTimeMillis: Long): Boolean = utcTimeMillis >= today
        },
    )
    val start = state.selectedStartDateMillis
    // One tap = a one-day tournament; the second tap sets the end.
    val end = state.selectedEndDateMillis ?: start

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(Modifier.fillMaxSize().background(Surface).statusBarsPadding().navigationBarsPadding()) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier.size(44.dp).clip(CircleShape).pressable(onClick = onDismiss),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Close, "Close", tint = Text1) }
                Text(
                    "Tournament dates",
                    color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f).padding(start = 4.dp),
                )
                Box(
                    Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(if (start != null) Blue else Stroke)
                        .pressable(enabled = start != null) { if (start != null && end != null) onConfirm(start, end) }
                        .padding(horizontal = 18.dp, vertical = 10.dp),
                ) { Text("Save", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold) }
            }
            DateRangePicker(
                state = state,
                modifier = Modifier.weight(1f),
                showModeToggle = false,
                title = null,
                headline = {
                    Text(
                        when {
                            start == null -> "Tap the first day"
                            state.selectedEndDateMillis == null -> "${prettyDate(start)} · tap the last day"
                            else -> "${shortDate(start)} – ${prettyDate(end!!)}"
                        },
                        color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(start = 20.dp, bottom = 10.dp),
                    )
                },
                colors = DatePickerDefaults.colors(
                    containerColor = Surface,
                    selectedDayContainerColor = Blue,
                    dayInSelectionRangeContainerColor = BlueTint,
                    todayDateBorderColor = Blue,
                    todayContentColor = Blue,
                ),
            )
        }
    }
}

// ───────────────────────────────────────────────────── Step 3 · Format ────────
@Composable
private fun StepFormat(d: TournamentDraft) {
    StepScaffold(
        title = "Playing format",
        subtitle = "Every fixture in the tournament follows these rules.",
    ) {
        FieldLabel("Match format")
        TournamentFormat.entries.chunked(2).forEachIndexed { r, row ->
            if (r > 0) Spacer(Modifier.height(10.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                row.forEach { f ->
                    OptionCard(
                        title = f.label,
                        sub = f.sub,
                        selected = d.format == f,
                        modifier = Modifier.weight(1f),
                    ) { d.selectFormat(f) }
                }
                if (row.size == 1) Spacer(Modifier.weight(1f))
            }
        }

        if (d.format == TournamentFormat.CUSTOM) {
            Spacer(Modifier.height(18.dp))
            FieldLabel("Format name")
            WizardTextField(d.formatName, { d.formatName = it.take(60) }, "e.g. Super 8, 12-over bash")
        }

        if (d.format.oversEditable) {
            Spacer(Modifier.height(18.dp))
            LabelledStepper("Overs per innings", d.overs, { d.overs = it }, 1, 90)
        }
        if (d.format == TournamentFormat.TEST) {
            Spacer(Modifier.height(18.dp))
            LabelledStepper("Days per match", d.matchDays, { d.matchDays = it }, 1, 5)
        }

        Spacer(Modifier.height(18.dp))
        LabelledStepper("Players per side", d.playersPerSide, { d.playersPerSide = it }, 4, 15)

        Spacer(Modifier.height(22.dp))
        FieldLabel("Ball type")
        ChipRow(BallType.entries, d.ball, { it.label }) { d.ball = it }

        Spacer(Modifier.height(22.dp))
        FieldLabel("Pitch")
        GroundPicker(selected = d.pitch, onSelect = { d.pitch = it })
    }
}

@Composable
private fun LabelledStepper(
    label: String,
    value: Int,
    onChange: (Int) -> Unit,
    min: Int,
    max: Int,
    suffix: String = "",
    step: Int = 1,
) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(label, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        Stepper(value = value, onChange = onChange, min = min, max = max, suffix = suffix, step = step)
    }
}

@Composable
private fun OptionCard(
    title: String,
    sub: String,
    selected: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Column(
        modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(14.dp))
            .background(if (selected) BlueTint else Surface)
            .border(BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke), RoundedCornerShape(14.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                title,
                color = if (selected) Blue else Text1,
                fontSize = 15.sp, fontWeight = FontWeight.Bold,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            if (selected) Icon(Icons.Filled.Check, null, tint = Blue, modifier = Modifier.size(16.dp))
        }
        Spacer(Modifier.height(2.dp))
        Text(sub, color = Text2, fontSize = 12.sp, maxLines = 2, lineHeight = 16.sp)
    }
}

// ──────────────────────────────────────────────── Step 4 · Teams & draw ────────
@Composable
private fun StepDraw(d: TournamentDraft) {
    StepScaffold(
        title = if (d.isTeam) "Teams and draw" else "Entries and draw",
        subtitle = if (d.isTeam) "How teams progress, how many can enter, and what they're playing for."
        else "How players progress, how many entries each event takes, and what they're playing for.",
    ) {
        FieldLabel("Tournament structure")
        TournamentStructure.entries.forEachIndexed { i, s ->
            if (i > 0) Spacer(Modifier.height(10.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .pressable { d.structure = s }
                    .clip(RoundedCornerShape(14.dp))
                    .background(if (d.structure == s) BlueTint else Surface)
                    .border(
                        BorderStroke(if (d.structure == s) 1.5.dp else 1.dp, if (d.structure == s) Blue else Stroke),
                        RoundedCornerShape(14.dp),
                    )
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(s.label, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    Text(s.sub, color = Text2, fontSize = 12.5.sp)
                }
                SelectDot(d.structure == s)
            }
        }

        Spacer(Modifier.height(22.dp))
        LabelledStepper(if (d.isTeam) "Number of teams" else "Entries per event", d.teamsCount, { d.teamsCount = it }, 2, 128)

        Spacer(Modifier.height(22.dp))
        FieldLabel(if (d.isTeam) "Entry fee per team (optional)" else "Entry fee per entry (optional)")
        WizardTextField(
            value = d.entryFee,
            onChange = { d.entryFee = it.filter(Char::isDigit).take(7).trimStart('0') },
            placeholder = "₹ amount — leave blank if free",
            keyboardType = KeyboardType.Number,
        )

        Spacer(Modifier.height(18.dp))
        FieldLabel("Prizes (optional)")
        WizardTextField(d.prizePool, { d.prizePool = it.take(120) }, "e.g. ₹50,000 + trophy for the winner")
    }
}

// ─────────────────────────────────────────────────── Step 5 · Organiser ────────
@Composable
private fun StepOrganiser(d: TournamentDraft) {
    StepScaffold(
        title = "Organiser",
        subtitle = if (d.isTeam) "Teams call this number to enter. It is shown on the tournament page."
        else "Players call this number to enter. It is shown on the tournament page.",
    ) {
        FieldLabel("Organiser name")
        WizardTextField(d.organizerName, { d.organizerName = it.take(100) }, "Person or club running it")
        Spacer(Modifier.height(18.dp))

        FieldLabel("Mobile number")
        WizardTextField(
            value = d.organizerPhone,
            onChange = { d.organizerPhone = it.filter(Char::isDigit).take(10) },
            placeholder = "10-digit mobile number",
            keyboardType = KeyboardType.Phone,
        )
        if (d.organizerPhone.length == 10 && !validPhone(d.organizerPhone)) {
            Spacer(Modifier.height(6.dp))
            Text("Indian mobile numbers start with 6, 7, 8 or 9.", color = Danger, fontSize = 12.sp)
        }
        Spacer(Modifier.height(18.dp))

        FieldLabel("About the tournament (optional)")
        WizardTextField(
            value = d.description,
            onChange = { d.description = it.take(2000) },
            placeholder = "Rules, registration deadline, what's included…",
            singleLine = false,
            minLines = 4,
        )
    }
}

// ────────────────────────────────────────────────────── Step 6 · Review ────────
@Composable
private fun StepReviewTournament(d: TournamentDraft) {
    StepScaffold(
        title = "Ready to host?",
        subtitle = "Check the details. Once it's created it shows on your profile under Tournaments.",
    ) {
        TournamentPreview(d)
        Spacer(Modifier.height(16.dp))
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Surface)
                .border(1.dp, Stroke, RoundedCornerShape(16.dp)).padding(16.dp),
        ) {
            val spec = d.sportSpec
            SummaryRow("Sport", d.sportLabel)
            SummaryRow("Category", if (d.category == TournamentCategory.OTHER) d.categoryOther.trim() else d.category.label)
            SummaryRow(
                "Who can play",
                listOfNotNull(if (d.isTeam) d.gender?.label else null, d.ageGroup.label).joinToString(" · "),
            )
            SummaryRow("City", d.city.trim() + (d.venue.trim().takeIf { it.isNotEmpty() }?.let { " · $it" } ?: ""))
            SummaryRow("Format", d.formatSummary)
            if (spec == null) {
                SummaryRow("Per side", "${d.playersPerSide} players")
                SummaryRow("Ball", d.ball.label)
                SummaryRow("Pitch", d.pitch.label)
            } else {
                if (spec.hasEvents) {
                    SummaryRow("Events", spec.events.filter { it.first in d.events }.joinToString(", ") { it.second })
                }
                if (spec.team) SummaryRow(spec.playersLabel.removePrefix("Players ").replaceFirstChar { it.uppercase() }, "${d.playersPerSide} players")
                spec.surfaces.firstOrNull { it.first == d.surface }?.let { SummaryRow(spec.surfaceTitle, it.second) }
                spec.options.forEach { option ->
                    when (option) {
                        is TournamentOption.Named -> option.options.firstOrNull { it.first == d.namedOptions[option.key] }
                            ?.let { SummaryRow(option.label.substringBefore(" decided"), it.second) }
                        is TournamentOption.OptionalNumber -> d.numberOptions[option.key]?.takeIf { it.isNotBlank() }
                            ?.let { SummaryRow(option.label.substringBefore(" ("), "$it ${option.suffix}") }
                    }
                }
            }
            SummaryRow("Structure", d.structure.label)
            SummaryRow(if (d.isTeam) "Teams" else "Entries", "${d.teamsCount}")
            SummaryRow("Entry fee", d.entryFee.toIntOrNull()?.let { "₹$it per ${if (d.isTeam) "team" else "entry"}" } ?: "Free")
            if (d.prizePool.isNotBlank()) SummaryRow("Prizes", d.prizePool.trim())
            SummaryRow("Organiser", "${d.organizerName.trim()} · ${d.organizerPhone}")
        }
    }
}

@Composable
private fun TournamentPreview(d: TournamentDraft) {
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp)),
    ) {
        Box(
            Modifier.fillMaxWidth().aspectRatio(16f / 9f)
                .background(Brush.linearGradient(listOf(Color(0xFF0F2A5C), Color(0xFF1D4ED8)))),
        ) {
            d.banner?.let {
                AsyncImage(it, "Tournament banner", contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
            }
        }
        Row(Modifier.padding(horizontal = 14.dp), verticalAlignment = Alignment.Bottom) {
            Box(
                Modifier.offset(y = (-26).dp).size(64.dp).clip(CircleShape).background(Surface).padding(3.dp)
                    .clip(CircleShape).background(BlueTint),
                contentAlignment = Alignment.Center,
            ) {
                val logo = d.logo
                if (logo != null) {
                    AsyncImage(logo, "Tournament logo", contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                } else {
                    Icon(Icons.Filled.EmojiEvents, null, tint = Blue, modifier = Modifier.size(28.dp))
                }
            }
        }
        Column(Modifier.padding(start = 16.dp, end = 16.dp, bottom = 16.dp).offset(y = (-16).dp)) {
            Text(d.name.trim(), color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold, lineHeight = 23.sp)
            Spacer(Modifier.height(4.dp))
            val start = d.startDateUtc
            val end = d.endDateUtc
            val dates = if (start != null && end != null) {
                if (start == end) prettyDate(start) else "${shortDate(start)} – ${prettyDate(end)}"
            } else ""
            Text(
                listOf(dates, d.city.trim()).filter { it.isNotEmpty() }.joinToString("  ·  "),
                color = Text2, fontSize = 13.sp,
            )
        }
    }
}

// ─────────────────────────────────────────── Step 3 · Format (other sports) ────────
/**
 * The format step for every sport but cricket, drawn from [TournamentSportSpec]: pick a
 * preset or build a custom format from the sport's own rules, then the events (racquet
 * sports), the surface and any sport-specific extras.
 */
@Composable
private fun StepSportFormat(d: TournamentDraft, spec: TournamentSportSpec) {
    StepScaffold(
        title = "Playing format",
        subtitle = if (spec.hasEvents) "The scoring every match follows, and which events you're running."
        else "Every fixture in the tournament follows these rules.",
    ) {
        FieldLabel(if (spec.team) "Match format" else "Scoring")
        val cards = spec.presets.map { Triple(it.key as String?, it.label, it.sub) } +
            Triple(null, "Custom", "Name it, set the rules")
        cards.chunked(2).forEachIndexed { r, row ->
            if (r > 0) Spacer(Modifier.height(10.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                row.forEach { (key, label, sub) ->
                    OptionCard(
                        title = label,
                        sub = sub,
                        selected = d.presetKey == key,
                        modifier = Modifier.weight(1f),
                    ) { d.selectPreset(key) }
                }
                if (row.size == 1) Spacer(Modifier.weight(1f))
            }
        }

        AnimatedVisibility(d.presetKey == null) {
            Column {
                Spacer(Modifier.height(18.dp))
                FieldLabel("Format name")
                WizardTextField(d.formatName, { d.formatName = it.take(60) }, "e.g. Monsoon Cup rules")
                if (spec.team) {
                    Spacer(Modifier.height(18.dp))
                    LabelledStepper(spec.playersLabel, d.playersPerSide, { d.playersPerSide = it }, spec.playersRange.first, spec.playersRange.last)
                }
                spec.rules.forEach { rule ->
                    Spacer(Modifier.height(18.dp))
                    when (rule) {
                        is TournamentRule.Range -> LabelledStepper(
                            label = rule.label,
                            value = (d.customRules[rule.key] as? Int) ?: rule.min,
                            onChange = { d.customRules[rule.key] = it },
                            min = rule.min,
                            max = rule.max,
                            suffix = rule.suffix,
                            step = rule.step,
                        )
                        is TournamentRule.Choice -> {
                            FieldLabel(rule.label)
                            ChipRow(rule.options, (d.customRules[rule.key] as? Int) ?: rule.options.first(), rule.optionLabel) {
                                d.customRules[rule.key] = it
                            }
                        }
                        is TournamentRule.Named -> {
                            FieldLabel(rule.label)
                            val selected = rule.options.firstOrNull { it.first == d.customRules[rule.key] } ?: rule.options.first()
                            ChipRow(rule.options, selected, { it.second }) { d.customRules[rule.key] = it.first }
                        }
                    }
                }
            }
        }

        if (spec.team && d.presetKey != null) {
            Spacer(Modifier.height(12.dp))
            Text(
                "${d.playersPerSide} ${spec.playersLabel.removePrefix("Players ").lowercase()} · ${tournamentRulesSummary(spec.key, d.activeRules)}",
                color = Text2, fontSize = 13.sp,
            )
        }

        if (spec.hasEvents) {
            Spacer(Modifier.height(22.dp))
            FieldLabel("Events")
            MultiChipRow(
                options = spec.events,
                selected = d.events,
                onToggle = { key -> if (key in d.events) d.events.remove(key) else d.events.add(key) },
            )
        }

        if (spec.surfaces.isNotEmpty()) {
            Spacer(Modifier.height(22.dp))
            FieldLabel(spec.surfaceTitle)
            val selected = spec.surfaces.firstOrNull { it.first == d.surface } ?: spec.surfaces.first()
            ChipRow(spec.surfaces, selected, { it.second }) { d.surface = it.first }
        }

        spec.options.forEach { option ->
            Spacer(Modifier.height(22.dp))
            FieldLabel(option.label)
            when (option) {
                is TournamentOption.Named -> {
                    val selected = option.options.firstOrNull { it.first == d.namedOptions[option.key] } ?: option.options.first()
                    ChipRow(option.options, selected, { it.second }) { d.namedOptions[option.key] = it.first }
                }
                is TournamentOption.OptionalNumber -> WizardTextField(
                    value = d.numberOptions[option.key].orEmpty(),
                    onChange = { d.numberOptions[option.key] = it.filter(Char::isDigit).take(3) },
                    placeholder = "${option.placeholder} — or ${option.min}–${option.max} ${option.suffix}",
                    keyboardType = KeyboardType.Number,
                )
            }
        }
    }
}

/** A single-choice chip row that starts with nothing picked, so the choice is deliberate. */
@Composable
@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
private fun <T> OptionalChipRow(options: List<T>, selected: T?, label: (T) -> String, onSelect: (T) -> Unit) {
    androidx.compose.foundation.layout.FlowRow(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        options.forEach { opt -> SelectableChip(label(opt), opt == selected) { onSelect(opt) } }
    }
}

/** Several can be on at once — a badminton tournament runs singles and doubles side by side. */
@Composable
@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
private fun MultiChipRow(options: List<Pair<String, String>>, selected: List<String>, onToggle: (String) -> Unit) {
    androidx.compose.foundation.layout.FlowRow(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        options.forEach { (key, label) ->
            val on = key in selected
            SelectableChip(label, on, leading = if (on) Icons.Filled.Check else Icons.Filled.Add) { onToggle(key) }
        }
    }
}

@Composable
private fun SelectableChip(
    label: String,
    selected: Boolean,
    leading: androidx.compose.ui.graphics.vector.ImageVector? = null,
    onClick: () -> Unit,
) {
    Row(
        Modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(12.dp))
            .background(if (selected) Blue else Surface)
            .border(1.dp, if (selected) Blue else Stroke, RoundedCornerShape(12.dp))
            .padding(start = if (leading != null) 12.dp else 18.dp, end = 18.dp, top = 12.dp, bottom = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (leading != null) {
            Icon(leading, null, tint = if (selected) Color.White else Text2, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
        }
        Text(label, color = if (selected) Color.White else Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
    }
}
