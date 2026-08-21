package com.haraan.app.ui.matches.create

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Geocoder
import android.location.LocationManager
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.Groups
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.SportsBasketball
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsKabaddi
import androidx.compose.material.icons.filled.SportsVolleyball
import androidx.compose.material.icons.filled.SportsSoccer
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import coil.compose.AsyncImage
import com.haraan.app.data.PlayerLite
import com.haraan.app.data.SquadMember
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import java.util.Locale
import com.haraan.app.ui.theme.HaraanColors

// ─────────────────────────────────────────────────────────────────────────────
// Palette — mirrors the CREX light theme tokens in MainScreen (which are private)
// ─────────────────────────────────────────────────────────────────────────────
private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Blue = HaraanColors.EventsBlue
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val BlueTint = HaraanColors.AccentTint
private val Green = HaraanColors.Success
private val GreenTint = HaraanColors.SuccessTint

// ─────────────────────────────────────────────────────────────────────────────
// Domain — Sprint 1 keeps this local. Match type sets the XP CEILING only;
// trust (and the multiplier that unlocks real XP) is decided AFTER the match,
// so it is intentionally NOT chosen here.
// ─────────────────────────────────────────────────────────────────────────────
enum class MatchType(
    val label: String,
    val tagline: String,
    val baseXp: Int,
    val serverValue: String,
    val icon: ImageVector,
) {
    CASUAL("Casual / Gully", "Friendly, self-scored", 25, "casual", Icons.Filled.SportsCricket),
    LEAGUE("Local League", "Recurring club games", 60, "league", Icons.Filled.Groups),
    TOURNAMENT("Tournament", "Organised, bracketed", 100, "tournament", Icons.Filled.EmojiEvents),
}

enum class BallType(val label: String, val serverValue: String) {
    TENNIS("Tennis", "tennis"),
    TAPE("Tape Ball", "tape"),
    RUBBER("Rubber", "rubber"),
    CORK("Cork", "cork"),
    SYNTHETIC("Synthetic", "synthetic"),
    LEATHER("Leather", "leather"),
    SEASON("Season", "season"),
}

// ─────────────────────────────────────────────────────────────────────────────
// Format — the one question every sport has to answer: what makes the match end?
//
// Cricket answers it with overs, football with halves × length, badminton with
// games × points. Until this existed the wizard only ever asked cricket, and the
// football/badminton scorers opened on hardcoded defaults (45-minute halves,
// best-of-3) that no gully match actually plays.
// ─────────────────────────────────────────────────────────────────────────────
sealed interface MatchFormat {
    /** What the review card and the scorer header read. */
    val summaryLine: String

    /** Serialised into `sport_state.format` on the match — no schema per sport. */
    fun toServerMap(): Map<String, Any>

    data class Cricket(val overs: Int, val ball: BallType) : MatchFormat {
        override val summaryLine: String get() = "$overs overs · ${ball.label} ball"
        override fun toServerMap(): Map<String, Any> =
            mapOf("kind" to "cricket", "overs" to overs, "ball" to ball.serverValue)
    }

    data class Football(val halves: Int, val halfLengthMin: Int) : MatchFormat {
        override val summaryLine: String get() = "$halves × $halfLengthMin min"
        override fun toServerMap(): Map<String, Any> =
            mapOf("kind" to "football", "halves" to halves, "halfLengthMin" to halfLengthMin)
    }

    data class Badminton(val bestOf: Int, val pointsTo: Int, val doubles: Boolean) : MatchFormat {
        override val summaryLine: String get() = buildString {
            append(if (doubles) "Doubles" else "Singles")
            append(" · ")
            append(if (bestOf == 1) "one game" else "best of $bestOf")
            append(" to $pointsTo")
        }
        override fun toServerMap(): Map<String, Any> = mapOf(
            "kind" to "badminton",
            "bestOf" to bestOf,
            "pointsTo" to pointsTo,
            "doubles" to doubles,
        )
    }

    /**
     * Rally sports: volleyball and table tennis (badminton keeps its own class, which came
     * first). One shape, because "best of N sets to P points" is the entire rule set — what
     * differs between them is the numbers, and those come from the presets.
     */
    data class Rally(val sport: String, val bestOf: Int, val pointsTo: Int) : MatchFormat {
        override val summaryLine: String get() =
            (if (bestOf == 1) "one set" else "best of $bestOf") + " to $pointsTo"
        override fun toServerMap(): Map<String, Any> =
            mapOf("kind" to sport, "bestOf" to bestOf, "pointsTo" to pointsTo)
    }

    /** Tennis: sets of GAMES, not sets of points — its own shape because it counts its own way. */
    data class Tennis(val bestOf: Int, val gamesTo: Int) : MatchFormat {
        override val summaryLine: String get() = "best of $bestOf sets to $gamesTo games"
        override fun toServerMap(): Map<String, Any> =
            mapOf("kind" to "tennis", "bestOf" to bestOf, "gamesTo" to gamesTo)
    }

    /** Basketball and kabaddi: periods of a fixed length, with points scored inside them. */
    data class Periods(val sport: String, val periods: Int, val periodLengthMin: Int) : MatchFormat {
        override val summaryLine: String get() = "$periods x $periodLengthMin min"
        override fun toServerMap(): Map<String, Any> = mapOf(
            "kind" to sport,
            "periods" to periods,
            "periodLengthMin" to periodLengthMin,
        )
    }
}

/**
 * A one-tap format. Presets are the primary control on the Rules step — a column of
 * bare steppers is both slower and reads like a form, not a sport. [playersPerSide]
 * is set only where the preset genuinely implies it (a "7-a-side" football game is
 * seven a side by definition); cricket leaves it to the stepper.
 */
data class FormatPreset(
    val label: String,
    val sub: String,
    val format: MatchFormat,
    val playersPerSide: Int? = null,
) {
    /**
     * Whether [draft] currently sits on this preset. Cricket compares overs only —
     * ball type is a separate axis, so changing the ball must not knock "T20" off.
     */
    fun matches(draft: CreateMatchDraft): Boolean {
        val current = draft.format
        val preset = format
        val sameFormat = if (preset is MatchFormat.Cricket && current is MatchFormat.Cricket) {
            current.overs == preset.overs
        } else {
            current == preset
        }
        return sameFormat && (playersPerSide == null || draft.playersPerSide == playersPerSide)
    }

    /** Applies the preset, preserving the ball the creator already chose. */
    fun apply(draft: CreateMatchDraft) {
        val preset = format
        val current = draft.format
        draft.format = if (preset is MatchFormat.Cricket && current is MatchFormat.Cricket) {
            preset.copy(ball = current.ball)
        } else {
            preset
        }
        playersPerSide?.let { draft.playersPerSide = it }
    }
}

/**
 * Everything that differs between sports, in one place. Before this, the wizard's
 * entire sport-awareness was an `if (isCricket)` that *hid* two cricket fields —
 * so football and badminton were literally "cricket, minus overs".
 */
data class SportSpec(
    val key: String,
    val displayName: String,
    val icon: ImageVector,
    /** The casual tier reads differently per sport — "Gully" means nothing in badminton. */
    val casualLabel: String,
    val casualTagline: String,
    /** How the casual tier reads mid-sentence: "…rank higher than gully games". */
    val casualPlural: String,
    val rulesSubtitle: String,
    /** Badminton is played by people, not clubs — the Teams step has to say so. */
    val teamsTitle: String,
    val teamsSubtitle: String,
    val presets: List<FormatPreset>,
    val defaultFormat: MatchFormat,
    val defaultPlayersPerSide: Int,
    val playersRange: IntRange,
    /** Hidden when the presets already fix it (badminton singles/doubles). */
    val showPlayersStepper: Boolean = true,
) {
    companion object {
        val Cricket = SportSpec(
            key = "cricket",
            displayName = "Cricket",
            icon = Icons.Filled.SportsCricket,
            casualLabel = "Casual / Gully",
            casualTagline = "Friendly, self-scored",
            casualPlural = "gully games",
            rulesSubtitle = "How long, what ball, how many a side.",
            teamsTitle = "Teams & squads",
            teamsSubtitle = "Name both sides. Search players by @username or name.",
            presets = listOf(
                FormatPreset("T20", "20 overs", MatchFormat.Cricket(20, BallType.TENNIS)),
                FormatPreset("T10", "10 overs", MatchFormat.Cricket(10, BallType.TENNIS)),
                FormatPreset("Gully", "6 overs", MatchFormat.Cricket(6, BallType.TENNIS)),
            ),
            defaultFormat = MatchFormat.Cricket(20, BallType.TENNIS),
            defaultPlayersPerSide = 11,
            playersRange = 2..15,
        )

        val Football = SportSpec(
            key = "football",
            displayName = "Football",
            icon = Icons.Filled.SportsSoccer,
            casualLabel = "Casual kickabout",
            casualTagline = "Friendly, self-scored",
            casualPlural = "kickabouts",
            rulesSubtitle = "How long the halves run, and how many a side.",
            teamsTitle = "Teams & squads",
            teamsSubtitle = "Name both sides. Search players by @username or name.",
            presets = listOf(
                FormatPreset("5-a-side", "2 × 20 min", MatchFormat.Football(2, 20), playersPerSide = 5),
                FormatPreset("7-a-side", "2 × 25 min", MatchFormat.Football(2, 25), playersPerSide = 7),
                FormatPreset("11-a-side", "2 × 45 min", MatchFormat.Football(2, 45), playersPerSide = 11),
            ),
            defaultFormat = MatchFormat.Football(2, 25),
            defaultPlayersPerSide = 7,
            playersRange = 3..11,
        )

        val Badminton = SportSpec(
            key = "badminton",
            displayName = "Badminton",
            icon = Icons.Filled.SportsTennis,
            casualLabel = "Friendly / Club",
            casualTagline = "Knock-about, self-scored",
            casualPlural = "friendlies",
            rulesSubtitle = "Singles or doubles, and how many games it takes.",
            teamsTitle = "Players",
            teamsSubtitle = "Who's playing? Search by @username or name.",
            presets = listOf(
                FormatPreset("Singles", "Best of 3 to 21", MatchFormat.Badminton(3, 21, doubles = false), playersPerSide = 1),
                FormatPreset("Doubles", "Best of 3 to 21", MatchFormat.Badminton(3, 21, doubles = true), playersPerSide = 2),
                FormatPreset("One game", "Single game to 21", MatchFormat.Badminton(1, 21, doubles = false), playersPerSide = 1),
            ),
            defaultFormat = MatchFormat.Badminton(3, 21, doubles = false),
            defaultPlayersPerSide = 1,
            // Singles is one a side. The old floor of 2 (stepper *and* server) meant a
            // singles match simply could not be expressed.
            playersRange = 1..2,
            showPlayersStepper = false,
        )

        val Volleyball = SportSpec(
            key = "volleyball",
            displayName = "Volleyball",
            icon = Icons.Filled.SportsVolleyball,
            casualLabel = "Casual / Court",
            casualTagline = "Friendly, self-scored",
            casualPlural = "friendlies",
            rulesSubtitle = "How many sets it takes, and how many a side.",
            teamsTitle = "Teams & squads",
            teamsSubtitle = "Name both sides. Search players by @username or name.",
            presets = listOf(
                FormatPreset("Indoor 6s", "Best of 5 to 25", MatchFormat.Rally("volleyball", 5, 25), playersPerSide = 6),
                FormatPreset("Beach", "Best of 3 to 21", MatchFormat.Rally("volleyball", 3, 21), playersPerSide = 2),
                FormatPreset("Quick", "One set to 25", MatchFormat.Rally("volleyball", 1, 25), playersPerSide = 6),
            ),
            defaultFormat = MatchFormat.Rally("volleyball", 5, 25),
            defaultPlayersPerSide = 6,
            playersRange = 2..8,
        )

        val Basketball = SportSpec(
            key = "basketball",
            displayName = "Basketball",
            icon = Icons.Filled.SportsBasketball,
            casualLabel = "Casual / Street",
            casualTagline = "Pick-up, self-scored",
            casualPlural = "pick-up games",
            rulesSubtitle = "How many quarters, how long, and how many a side.",
            teamsTitle = "Teams & squads",
            teamsSubtitle = "Name both sides. Search players by @username or name.",
            presets = listOf(
                FormatPreset("Full court 5s", "4 x 10 min", MatchFormat.Periods("basketball", 4, 10), playersPerSide = 5),
                FormatPreset("3x3 half court", "2 x 10 min", MatchFormat.Periods("basketball", 2, 10), playersPerSide = 3),
                FormatPreset("Quick run", "2 x 8 min", MatchFormat.Periods("basketball", 2, 8), playersPerSide = 5),
            ),
            defaultFormat = MatchFormat.Periods("basketball", 4, 10),
            defaultPlayersPerSide = 5,
            playersRange = 3..6,
        )

        val Kabaddi = SportSpec(
            key = "kabaddi",
            displayName = "Kabaddi",
            icon = Icons.Filled.SportsKabaddi,
            casualLabel = "Casual / Gully",
            casualTagline = "Friendly, self-scored",
            casualPlural = "gully games",
            rulesSubtitle = "How long each half runs, and how many on the mat.",
            teamsTitle = "Teams & squads",
            teamsSubtitle = "Name both sides. Search players by @username or name.",
            presets = listOf(
                FormatPreset("Standard 7s", "2 x 20 min", MatchFormat.Periods("kabaddi", 2, 20), playersPerSide = 7),
                FormatPreset("Short", "2 x 10 min", MatchFormat.Periods("kabaddi", 2, 10), playersPerSide = 7),
                FormatPreset("Small side", "2 x 15 min", MatchFormat.Periods("kabaddi", 2, 15), playersPerSide = 5),
            ),
            defaultFormat = MatchFormat.Periods("kabaddi", 2, 20),
            defaultPlayersPerSide = 7,
            playersRange = 4..7,
        )

        val Tennis = SportSpec(
            key = "tennis",
            displayName = "Tennis",
            icon = Icons.Filled.SportsTennis,
            casualLabel = "Friendly / Club",
            casualTagline = "Knock-about, self-scored",
            casualPlural = "friendlies",
            rulesSubtitle = "Singles or doubles, and how many sets it takes.",
            teamsTitle = "Players",
            teamsSubtitle = "Who is playing? Search by @username or name.",
            presets = listOf(
                FormatPreset("Singles", "Best of 3 sets", MatchFormat.Tennis(3, 6), playersPerSide = 1),
                FormatPreset("Doubles", "Best of 3 sets", MatchFormat.Tennis(3, 6), playersPerSide = 2),
                FormatPreset("Short set", "One set to 4", MatchFormat.Tennis(1, 4), playersPerSide = 1),
            ),
            defaultFormat = MatchFormat.Tennis(3, 6),
            defaultPlayersPerSide = 1,
            playersRange = 1..2,
            showPlayersStepper = false,
        )

        val TableTennis = SportSpec(
            key = "table_tennis",
            displayName = "Table Tennis",
            icon = Icons.Filled.SportsTennis,
            casualLabel = "Friendly / Club",
            casualTagline = "Knock-about, self-scored",
            casualPlural = "friendlies",
            rulesSubtitle = "Singles or doubles, and how many games it takes.",
            teamsTitle = "Players",
            teamsSubtitle = "Who is playing? Search by @username or name.",
            presets = listOf(
                FormatPreset("Singles", "Best of 5 to 11", MatchFormat.Rally("table_tennis", 5, 11), playersPerSide = 1),
                FormatPreset("Doubles", "Best of 5 to 11", MatchFormat.Rally("table_tennis", 5, 11), playersPerSide = 2),
                FormatPreset("Quick", "Best of 3 to 11", MatchFormat.Rally("table_tennis", 3, 11), playersPerSide = 1),
            ),
            defaultFormat = MatchFormat.Rally("table_tennis", 5, 11),
            defaultPlayersPerSide = 1,
            playersRange = 1..2,
            showPlayersStepper = false,
        )

        /**
         * Every sport that can be created AND scored end to end. The sport step offers
         * exactly these, which is why the wizard no longer needs a "coming soon" gate:
         * an unsupported sport can't be chosen in the first place.
         */
        val supported: List<SportSpec> = listOf(
            Cricket, Football, Badminton,
            Volleyball, Basketball, Kabaddi, Tennis, TableTennis,
        )

        fun forKey(sport: String): SportSpec =
            supported.firstOrNull { it.key == sport.lowercase() } ?: Cricket
    }
}

/**
 * Default team icons offered at create time. A creator can pick one of these bundled
 * action images or upload their own photo; [teamEmblems] indices are stored on the draft
 * (see [CreateMatchDraft]).
 *
 * [key] is the stable identifier persisted on the backend (rendered back via
 * [emblemDrawableFor]). [resId] is the bundled drawable shown as a circular crest.
 */
data class TeamEmblem(val key: String, @androidx.annotation.DrawableRes val resId: Int)

val teamEmblems = listOf(
    TeamEmblem("action1", com.haraan.app.R.drawable.match_emblem_1),
    TeamEmblem("action2", com.haraan.app.R.drawable.match_emblem_2),
    TeamEmblem("action3", com.haraan.app.R.drawable.match_emblem_3),
    TeamEmblem("action4", com.haraan.app.R.drawable.match_emblem_4),
)

/** Maps a persisted emblem [key] back to its bundled drawable, so match screens can re-render it. */
@androidx.annotation.DrawableRes
fun emblemDrawableFor(key: String): Int? =
    teamEmblems.firstOrNull { it.key == key }?.resId

class CreateMatchDraft(sport: String = "cricket") {
    /** Everything that differs by sport — presets, ranges, copy, icon. */
    val spec: SportSpec = SportSpec.forKey(sport)

    // Which sport this match is. Persisted so the feed/detail can branch and the match
    // is never mislabelled as cricket.
    var sport by mutableStateOf(spec.key)
    // Public = ranked/feed-visible (earns XP after verification). Private = a closed
    // scoreboard reachable only by share code: no XP, never ranked, hidden from feeds.
    var isPrivate by mutableStateOf(false)
    var type by mutableStateOf(MatchType.CASUAL)
    // What ends the match, in that sport's own terms. Replaces the old always-cricket
    // overs+ball pair, which football and badminton carried as meaningless data.
    var format by mutableStateOf(spec.defaultFormat)
    var playersPerSide by mutableStateOf(spec.defaultPlayersPerSide)

    // Cricket's fields, read off [format]. The server still takes `overs` for cricket,
    // and sends a harmless placeholder for the other sports (see the payload builder).
    val overs: Int get() = (format as? MatchFormat.Cricket)?.overs ?: 0
    val ball: BallType get() = (format as? MatchFormat.Cricket)?.ball ?: BallType.TENNIS
    /** Football's half length — what the scorer clock actually runs on. */
    val halfLengthMin: Int get() = (format as? MatchFormat.Football)?.halfLengthMin ?: 45
    /** Badminton's games-to-win-the-match. */
    val bestOf: Int get() = (format as? MatchFormat.Badminton)?.bestOf ?: 3
    /** "Football · 2 × 25 min" — the header line every scorer shows. */
    val formatLabel: String get() = "${spec.displayName} · ${format.summaryLine}"
    var venue by mutableStateOf("")
    // Village / town / area — finer than the profile district. Auto-filled from the
    // GPS fix below, then editable: the reverse geocoder often returns the nearest
    // *town* ("Pulivendula") when the ground is a village ("keerthipalle"), and only
    // the creator knows the name locals actually use. Shown on the live card.
    var locality by mutableStateOf("")
    // The GPS fix. Mandatory for public matches — it's what makes "matches near me"
    // measurable; the typed label above can't be sorted by distance. Null until the
    // creator grants location and a fix lands.
    var latitude by mutableStateOf<Double?>(null)
    var longitude by mutableStateOf<Double?>(null)
    // District resolved from that same fix — where the match actually is, which beats
    // the creator's profile district for feed scoping.
    var locationDistrict by mutableStateOf("")
    var onHaraanTurf by mutableStateOf(false)
    // When the match was played on a booked Haraan turf, the creator picks which
    // booking it was — the backend validates ownership + CONFIRMED status and
    // auto-verifies the result against it (highest trust, full XP).
    var venueBookingId by mutableStateOf<Long?>(null)
    var teamA by mutableStateOf("")
    var teamB by mutableStateOf("")
    // Team icons — either a default emblem (index into [teamEmblems]) or a custom uploaded
    // image. A non-null photo Uri takes precedence over the emblem. Defaults are seeded
    // distinct so the two sides read apart at a glance.
    var teamAEmblem by mutableStateOf(0)
    var teamBEmblem by mutableStateOf(2)
    var teamAPhoto by mutableStateOf<android.net.Uri?>(null)
    var teamBPhoto by mutableStateOf<android.net.Uri?>(null)
    val squadA = mutableStateListOf<SquadMember>()
    val squadB = mutableStateListOf<SquadMember>()

    // When the match kicks off. null = "Play now" — created and the toss runs straight
    // away (the default). A future epoch-millis value = "Schedule for later": the match
    // is created with that start time, skips the immediate toss, and waits in the
    // Scheduled tab until the creator starts it.
    var scheduledAt by mutableStateOf<Long?>(null)

    // "Looking for players": open the match so nearby players can request to join, and
    // how many more the match wants. Public matches only.
    var openToJoin by mutableStateOf(false)
    var slotsNeeded by mutableStateOf(2)
}

/**
 * What one side is called: "Team A"/"Team B" for cricket and football, "Player 1"/
 * "Player 2" for badminton singles, "Pair 1"/"Pair 2" for doubles. Badminton is
 * played by people, not clubs — calling a singles opponent "Team B" is the tell that
 * a screen was built for one sport and handed to another.
 */
private fun CreateMatchDraft.sideLabel(index: Int): String {
    val f = format
    return when {
        f is MatchFormat.Badminton && f.doubles -> "Pair ${index + 1}"
        f is MatchFormat.Badminton -> "Player ${index + 1}"
        else -> if (index == 0) "Team A" else "Team B"
    }
}

/** The noun for that side's name field and icon: "Team" / "Player" / "Pair". */
private val CreateMatchDraft.sideNoun: String
    get() = sideLabel(0).substringBeforeLast(' ')

/**
 * Sprint 1 — Create Match Wizard. Four steps: Type → Rules → Teams → Review.
 * Self-contained UI + local form state. [onCreate] hands the assembled draft to
 * the caller (backend persistence + XP ledger arrive in later sprints).
 */
@Composable
fun CreateMatchWizard(
    onDismiss: () -> Unit,
    onCreate: (CreateMatchDraft) -> Unit,
    searchPlayers: suspend (query: String) -> List<PlayerLite> = { emptyList() },
    loadBookings: suspend () -> List<com.haraan.app.data.BookingLite> = { emptyList() },
    /**
     * The tab the creator pressed Create from. It PRE-SELECTS the sport step rather
     * than locking it: opening from the Cricket tab and being unable to make anything
     * but a cricket match was the complaint, but making everyone choose from scratch
     * would tax the common case. So the common path is one extra tap, not one extra
     * decision — and the choice is visible and changeable.
     */
    sport: String = "Cricket",
    modifier: Modifier = Modifier,
) {
    // Now wizard state, not a parameter: changing it rebuilds the draft, because
    // format and players-per-side only mean anything within a sport.
    var sportKey by remember { mutableStateOf(SportSpec.forKey(sport).key) }

    val draft = remember(sportKey) { CreateMatchDraft(sportKey) }
    var step by remember { mutableStateOf(0) }
    val lastStep = 4

    // System Back must do exactly what the top bar's ← does. Until this existed the
    // press fell through to the Activity and closed the app mid-wizard, discarding
    // the whole draft (teams, squads, captains) with no warning.
    com.haraan.app.ui.DismissOnBack(enabled = true) {
        if (step == 0) onDismiss() else step--
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        WizardTopBar(
            step = step,
            total = lastStep + 1,
            // Name the sport in the chrome — three sports share this wizard, and the
            // steps alone no longer tell you which one you're creating.
            sport = draft.spec.displayName,
            onBack = { if (step == 0) onDismiss() else step-- },
            onClose = onDismiss,
        )

        AnimatedContent(
            targetState = step,
            transitionSpec = {
                val forward = targetState > initialState
                val dir = if (forward) 1 else -1
                (slideInHorizontally(tween(260)) { it * dir } + fadeIn(tween(260)))
                    .togetherWith(slideOutHorizontally(tween(260)) { -it * dir } + fadeOut(tween(260)))
            },
            modifier = Modifier.weight(1f),
            label = "wizardStep",
        ) { s ->
            when (s) {
                0 -> StepSport(selected = sportKey, onSelect = { sportKey = it })
                1 -> StepType(draft)
                2 -> StepRules(draft, loadBookings)
                3 -> StepTeams(draft, searchPlayers)
                else -> StepReview(draft)
            }
        }

        WizardFooter(
            step = step,
            lastStep = lastStep,
            canContinue = canAdvance(draft, step),
            missing = missingOn(draft, step),
            onContinue = {
                if (step == lastStep) onCreate(draft) else step++
            },
        )
    }
}

// ─────────────────────────────────────────────────────── Step 1 · Sport ────────
/**
 * Which sport this is. Replaces the old "coming soon" gate outright: the picker only
 * offers sports that can be created AND scored end to end, so an unsupported one can
 * no longer be reached, let alone silently persisted as cricket.
 *
 * Arrives pre-selected from the tab you pressed Create on, so the usual path is a
 * single Continue. Changing it here rebuilds the draft with that sport's own defaults
 * — nothing has been entered yet at this point, so nothing is lost.
 */
@Composable
private fun StepSport(selected: String, onSelect: (String) -> Unit) {
    StepScaffold(
        title = "What are you playing?",
        subtitle = "Everything after this — the format, the sides, the scorer — follows from this.",
    ) {
        SportSpec.supported.forEachIndexed { i, spec ->
            if (i > 0) Spacer(Modifier.height(12.dp))
            SportCard(spec = spec, selected = spec.key == selected, onClick = { onSelect(spec.key) })
        }
    }
}

@Composable
private fun SportCard(spec: SportSpec, selected: Boolean, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(16.dp))
            .background(if (selected) BlueTint else Surface)
            .border(
                BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke),
                RoundedCornerShape(16.dp),
            )
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(44.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(if (selected) Blue.copy(alpha = 0.12f) else Bg),
            contentAlignment = Alignment.Center,
        ) {
            Icon(spec.icon, null, tint = if (selected) Blue else Text2, modifier = Modifier.size(22.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(spec.displayName, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            // The formats it offers — concrete, so the choice is about the game rather
            // than the word.
            Text(
                spec.presets.joinToString(" · ") { it.label },
                color = Text2,
                fontSize = 13.sp,
                maxLines = 1,
            )
        }
        Spacer(Modifier.width(10.dp))
        SelectDot(selected)
    }
}

// Step indices: 0 sport · 1 type · 2 rules · 3 teams · 4 review.
private fun canAdvance(d: CreateMatchDraft, step: Int): Boolean = when (step) {
    // A public match needs BOTH a GPS fix (so it can be found by distance) and a
    // readable place name (so the card means something). Private matches never
    // reach a feed, so neither is required there.
    2 -> formatComplete(d.format) && d.playersPerSide > 0 &&
        (d.isPrivate || (d.latitude != null && d.longitude != null && d.locality.trim().length >= 2))
    3 -> d.teamA.isNotBlank() && d.teamB.isNotBlank()
    else -> true
}

/**
 * The first thing still missing on [step], phrased for the footer. Mirrors
 * [canAdvance] exactly — a greyed-out Continue with no explanation is the same
 * dead end the player-profile form had.
 */
/** A format is only usable if every number in it is positive — the steppers clamp, so this is a floor, not a UI guard. */
private fun formatComplete(f: MatchFormat): Boolean = when (f) {
    is MatchFormat.Cricket -> f.overs > 0
    is MatchFormat.Football -> f.halves > 0 && f.halfLengthMin > 0
    is MatchFormat.Badminton -> f.bestOf > 0 && f.pointsTo > 0
    is MatchFormat.Rally -> f.bestOf > 0 && f.pointsTo > 0
    is MatchFormat.Tennis -> f.bestOf > 0 && f.gamesTo > 0
    is MatchFormat.Periods -> f.periods > 0 && f.periodLengthMin > 0
}

/** What the footer names as missing when [formatComplete] fails, in that sport's words. */
private fun formatMissingLabel(f: MatchFormat): String = when (f) {
    is MatchFormat.Cricket -> "the number of overs"
    is MatchFormat.Football -> "the half length"
    is MatchFormat.Badminton -> "the number of games"
    // Each sport is named in its own words — "the number of sets" means nothing to
    // someone setting up a basketball game.
    is MatchFormat.Rally -> if (f.sport == "table_tennis") "the number of games" else "the number of sets"
    is MatchFormat.Tennis -> "the number of sets"
    is MatchFormat.Periods -> if (f.sport == "basketball") "the quarter length" else "the half length"
}

private fun missingOn(d: CreateMatchDraft, step: Int): String? = when (step) {
    2 -> when {
        !formatComplete(d.format) -> formatMissingLabel(d.format)
        d.playersPerSide <= 0 -> "players per side"
        !d.isPrivate && (d.latitude == null || d.longitude == null) -> "your match location"
        !d.isPrivate && d.locality.trim().length < 2 -> "the area or village"
        else -> null
    }
    3 -> when {
        d.teamA.isBlank() -> "a name for ${d.sideLabel(0)}"
        d.teamB.isBlank() -> "a name for ${d.sideLabel(1)}"
        else -> null
    }
    else -> null
}

// ─────────────────────────────────────────────────────────────── Top bar ──────
@Composable
private fun WizardTopBar(
    step: Int,
    total: Int,
    onBack: () -> Unit,
    onClose: () -> Unit,
    sport: String = "",
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(Surface)
            .padding(horizontal = 16.dp, vertical = 12.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconCircle(Icons.AutoMirrored.Filled.ArrowBack, "Back", onBack)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Create Match", color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Text(
                    if (sport.isBlank()) "Step ${step + 1} of $total" else "$sport · Step ${step + 1} of $total",
                    color = Text3,
                    fontSize = 12.sp,
                )
            }
            IconCircle(Icons.Default.Close, "Close", onClose)
        }
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            repeat(total) { i ->
                Box(
                    Modifier
                        .weight(1f)
                        .height(4.dp)
                        .background(if (i <= step) Blue else Stroke, RoundedCornerShape(2.dp))
                )
            }
        }
    }
}

@Composable
private fun IconCircle(icon: androidx.compose.ui.graphics.vector.ImageVector, cd: String, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .size(36.dp)
            .pressable(onClick = onClick)
            .clip(CircleShape)
            .background(Color(0xFFF1F5F9)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, cd, tint = Text1, modifier = Modifier.size(18.dp))
    }
}

// ─────────────────────────────────────────────────────────────── Footer ────────
@Composable
private fun WizardFooter(
    step: Int,
    lastStep: Int,
    canContinue: Boolean,
    onContinue: () -> Unit,
    missing: String? = null,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .background(Surface)
            // Keep the white footer flush to the screen edge, but lift the button above the
            // system nav bar (and the keyboard on text-entry steps) so it isn't overlapped.
            .navigationBarsPadding()
            .imePadding()
            .padding(16.dp)
    ) {
        // Why the button is inert, named, before the user pokes at it. Slides away
        // rather than vanishing — the line disappearing is how you notice the form
        // just became valid.
        AnimatedVisibility(
            visible = missing != null,
            enter = fadeIn(tween(180)) + expandVertically(tween(180)),
            exit = fadeOut(tween(140)) + shrinkVertically(tween(140)),
        ) {
            Text(
                text = "Add ${missing.orEmpty()} to continue",
                color = Text2,
                fontSize = 13.sp,
                modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp),
                textAlign = TextAlign.Center,
            )
        }
        // Blue carries the user forward through the steps; green commits the match at
        // the end — a deliberate blue→green hand-off so the final action reads as "go".
        val isCommit = step == lastStep
        val accent = if (isCommit) Green else Blue

        // The moment a step becomes completable is the most satisfying beat in a
        // wizard, and it used to pass unmarked: the fill snapped from 35% to solid.
        // Now it eases in, and the hand is told.
        val view = LocalView.current
        val container by animateColorAsState(
            targetValue = if (canContinue) accent else accent.copy(alpha = 0.35f),
            animationSpec = tween(220),
            label = "footerFill",
        )
        var wasReady by remember(step) { mutableStateOf(canContinue) }
        LaunchedEffect(canContinue) {
            if (canContinue && !wasReady) view.performHapticFeedback(Feel.TICK)
            wasReady = canContinue
        }

        Button(
            onClick = {
                // Creating the match is the one irreversible action in the flow, so it
                // gets its own weight — distinct from every Continue that preceded it.
                view.performHapticFeedback(if (isCommit) Feel.COMMIT else Feel.SELECT)
                onContinue()
            },
            enabled = canContinue,
            modifier = Modifier
                .fillMaxWidth()
                .height(52.dp),
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = container,
                contentColor = Color.White,
                disabledContainerColor = container,
                disabledContentColor = Color.White.copy(alpha = 0.7f),
            ),
        ) {
            Text(
                if (isCommit) "Create Match" else "Continue",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

// ─────────────────────────────────────────────────────── Step 1 · Type ─────────
@Composable
private fun StepType(draft: CreateMatchDraft) {
    StepScaffold(
        title = "What kind of match?",
        subtitle = if (draft.isPrivate)
            "Private games are just a scoreboard for your group — no XP, no ranking."
        else
            // "gully" is cricket's word — a football screen shouldn't use it.
            "This sets how much it's worth. Tournament games rank higher than ${draft.spec.casualPlural}.",
    ) {
        // Public vs Private — the top-level choice. It decides whether this match
        // participates in XP/ranking at all.
        VisibilityChoice(
            isPrivate = draft.isPrivate,
            onChange = { draft.isPrivate = it },
        )
        Spacer(Modifier.height(20.dp))

        if (!draft.isPrivate) {
            val ctx = androidx.compose.ui.platform.LocalContext.current
            MatchType.entries.forEach { type ->
                // Only Casual/Gully is open for now; League & Tournament are concierge-only.
                val locked = type != MatchType.CASUAL
                MatchTypeCard(
                    type = type,
                    spec = draft.spec,
                    selected = draft.type == type && !locked,
                    locked = locked,
                    onClick = {
                        if (locked) {
                            android.widget.Toast.makeText(
                                ctx, "Contact Haraan to host ${type.label} matches.",
                                android.widget.Toast.LENGTH_SHORT
                            ).show()
                        } else {
                            draft.type = type
                        }
                    },
                )
                Spacer(Modifier.height(12.dp))
            }
            ImpactNote(
                "+${draft.type.baseXp} XP ceiling",
                "Actual XP unlocks after the result is verified (both captains confirm). " +
                    "Until then it settles at Low trust.",
            )
        } else {
            ImpactNote(
                "No XP · no ranking",
                "A private match won't appear in any feed or leaderboard. You'll get a share " +
                    "code so your group can follow the score — that's the only way in.",
            )
        }
    }
}

// Segmented Public / Private selector. Public is the default (ranked) lane.
@Composable
private fun VisibilityChoice(isPrivate: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        VisibilityTab(
            title = "Public",
            sub = "Ranked · XP",
            selected = !isPrivate,
            onClick = { onChange(false) },
            modifier = Modifier.weight(1f),
        )
        VisibilityTab(
            title = "Private",
            sub = "Scoreboard",
            selected = isPrivate,
            onClick = { onChange(true) },
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun VisibilityTab(
    title: String,
    sub: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(11.dp))
            .background(if (selected) Blue else Color.Transparent)
            .padding(vertical = 12.dp, horizontal = 12.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, color = if (selected) Color.White else Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Spacer(Modifier.height(2.dp))
        Text(sub, color = if (selected) Color.White.copy(alpha = 0.85f) else Text3, fontSize = 12.sp, maxLines = 1)
    }
}

@Composable
private fun MatchTypeCard(
    type: MatchType,
    spec: SportSpec,
    selected: Boolean,
    onClick: () -> Unit,
    locked: Boolean = false,
) {
    // The casual tier is the one that speaks the sport's own language — "Gully" in
    // cricket, "kickabout" in football. League and Tournament read the same everywhere.
    val isCasual = type == MatchType.CASUAL
    val label = if (isCasual) spec.casualLabel else type.label
    val tagline = if (isCasual) spec.casualTagline else type.tagline
    val icon = if (isCasual) spec.icon else type.icon
    Row(
        modifier = Modifier
            .fillMaxWidth()
            // A locked tier still presses — the toast explaining why is the response,
            // and a card that ignores the finger entirely reads as broken, not gated.
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(16.dp))
            .background(if (selected) BlueTint else Surface)
            .border(
                BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke),
                RoundedCornerShape(16.dp),
            )
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(11.dp))
                .background(if (selected) Blue.copy(alpha = 0.12f) else Bg)
                .alpha(if (locked) 0.55f else 1f),
            contentAlignment = Alignment.Center,
        ) {
            Icon(icon, null, tint = if (selected) Blue else Text2, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f).alpha(if (locked) 0.55f else 1f)) {
            Text(label, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(tagline, color = Text2, fontSize = 13.sp, maxLines = 1)
        }
        Spacer(Modifier.width(10.dp))
        if (locked) {
            // Concierge-only tier — a pill that signals it's enabled by Haraan, not self-serve.
            Box(
                Modifier.clip(RoundedCornerShape(8.dp)).background(Bg).border(1.dp, Stroke, RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 5.dp)
            ) {
                Text("Contact Haraan", color = Text2, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        } else {
            XpBadge(type.baseXp)
            Spacer(Modifier.width(10.dp))
            SelectDot(selected)
        }
    }
}

@Composable
private fun XpBadge(xp: Int) {
    Box(
        Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(GreenTint)
            .padding(horizontal = 10.dp, vertical = 5.dp)
    ) {
        Text("+$xp XP", color = Green, fontSize = 13.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun SelectDot(selected: Boolean) {
    Box(
        Modifier
            .size(22.dp)
            .clip(CircleShape)
            .background(if (selected) Blue else Color.Transparent)
            .border(BorderStroke(if (selected) 0.dp else 1.5.dp, Text3), CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        if (selected) Icon(Icons.Default.Check, null, tint = Color.White, modifier = Modifier.size(14.dp))
    }
}

// ─────────────────────────────────────────────────────── Step 2 · Rules ────────
@Composable
private fun StepRules(
    draft: CreateMatchDraft,
    loadBookings: suspend () -> List<com.haraan.app.data.BookingLite>,
) {
    val spec = draft.spec
    StepScaffold(
        title = "Format & rules",
        subtitle = spec.rulesSubtitle,
    ) {
        // The format block — the one question that defines the sport. Presets first:
        // one tap gets a real match, and "Custom" opens the numbers for anyone whose
        // game doesn't fit a preset.
        FieldLabel("Match format")
        FormatPicker(draft)
        Spacer(Modifier.height(20.dp))

        // Badminton fixes this with singles/doubles, so the stepper would only be a
        // way to create something the sport doesn't have.
        if (spec.showPlayersStepper) {
            FieldLabel("Players per side")
            Stepper(
                value = draft.playersPerSide,
                onChange = { draft.playersPerSide = it.coerceIn(spec.playersRange) },
                min = spec.playersRange.first,
                max = spec.playersRange.last,
            )
            Spacer(Modifier.height(20.dp))
        }

        // Location is captured from GPS, not typed. The fix is what makes this match
        // findable by distance; the name below is only how it reads on the card. A
        // private match skips all of it (it never reaches a feed).
        if (!draft.isPrivate) {
            FieldLabel("Match location *")
            MatchLocationField(
                latitude = draft.latitude,
                longitude = draft.longitude,
                onResolved = { lat, lng, area, district ->
                    draft.latitude = lat
                    draft.longitude = lng
                    draft.locationDistrict = district
                    // Seed the name from the fix, but never overwrite a name the
                    // creator already corrected by hand.
                    if (draft.locality.isBlank() && area.isNotBlank()) {
                        draft.locality = area
                        draft.venue = area
                    }
                },
            )
            Spacer(Modifier.height(16.dp))
        }

        // The readable place name. Auto-filled from the fix above and editable —
        // the geocoder frequently names the nearest town, not the actual ground.
        FieldLabel(if (draft.isPrivate) "Venue / Area / Village" else "Ground or village name *")
        var placeTouched by remember { mutableStateOf(false) }
        WizardTextField(
            value = draft.locality,
            onChange = { draft.locality = it; draft.venue = it },
            placeholder = "Ground, village or area",
            modifier = Modifier.onFocusChanged { if (it.isFocused) placeTouched = true },
        )
        Spacer(Modifier.height(6.dp))
        val localityInvalid = !draft.isPrivate && placeTouched && draft.locality.trim().length < 2
        Text(
            text = when {
                localityInvalid -> "Required — so locals recognise the ground."
                draft.isPrivate -> "Optional for a private match."
                else -> "Correct this if the detected name isn't what locals call it."
            },
            color = if (localityInvalid) Color(0xFFDC2626) else Text3,
            fontSize = 12.sp,
            lineHeight = 16.sp,
        )
        Spacer(Modifier.height(12.dp))
        ToggleRow(
            label = "Played on a Haraan turf",
            sub = "Booked turfs auto-verify the result → highest trust, full XP.",
            checked = draft.onHaraanTurf,
            onToggle = {
                draft.onHaraanTurf = it
                if (!it) draft.venueBookingId = null   // clearing the toggle drops the linked booking
            },
        )
        if (draft.onHaraanTurf) {
            Spacer(Modifier.height(12.dp))
            TurfBookingPicker(
                loadBookings = loadBookings,
                selectedId = draft.venueBookingId,
                onSelect = { draft.venueBookingId = it },
            )
        }
    }
}

/**
 * The format block. Presets carry the common cases; "Custom" reveals that sport's own
 * numbers. Ball type sits outside the preset set for cricket because it varies
 * independently of length — a 20-over game is played on tennis, tape or leather.
 */
@Composable
private fun FormatPicker(draft: CreateMatchDraft) {
    val spec = draft.spec
    val presets = spec.presets
    // Custom opens on demand, and also whenever the current format doesn't correspond
    // to any preset (e.g. re-entering after editing the numbers).
    var custom by remember { mutableStateOf(presets.none { it.matches(draft) }) }

    FormatPresetRow(
        presets = presets,
        customSelected = custom,
        isSelected = { !custom && it.matches(draft) },
        onSelect = { preset -> custom = false; preset.apply(draft) },
        onCustom = { custom = true },
    )

    if (custom) {
        // Only in Custom: a selected preset card already states its own format, so
        // echoing it there would just repeat the line directly above it.
        Spacer(Modifier.height(10.dp))
        Text(draft.format.summaryLine, color = Text2, fontSize = 13.sp, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(16.dp))
        when (val f = draft.format) {
            is MatchFormat.Cricket -> {
                FieldLabel("Overs per side")
                Stepper(
                    value = f.overs,
                    onChange = { draft.format = f.copy(overs = it.coerceIn(1, 50)) },
                    min = 1, max = 50,
                    suffix = "ov",
                )
            }
            is MatchFormat.Football -> {
                FieldLabel("Halves")
                Stepper(
                    value = f.halves,
                    onChange = { draft.format = f.copy(halves = it.coerceIn(1, 2)) },
                    min = 1, max = 2,
                )
                Spacer(Modifier.height(16.dp))
                FieldLabel("Half length")
                // Fives, because nobody plays a 23-minute half and the stepper
                // shouldn't make you tap twenty times to reach one that's real.
                Stepper(
                    value = f.halfLengthMin,
                    onChange = { draft.format = f.copy(halfLengthMin = it.coerceIn(5, 45)) },
                    min = 5, max = 45, step = 5,
                    suffix = "min",
                )
            }
            is MatchFormat.Badminton -> {
                FieldLabel("Singles or doubles")
                ChipRow(
                    options = listOf(false, true),
                    selected = f.doubles,
                    label = { if (it) "Doubles" else "Singles" },
                    onSelect = { doubles ->
                        draft.format = f.copy(doubles = doubles)
                        draft.playersPerSide = if (doubles) 2 else 1
                    },
                )
                Spacer(Modifier.height(16.dp))
                FieldLabel("Games")
                ChipRow(
                    options = listOf(1, 3, 5),
                    selected = f.bestOf,
                    label = { if (it == 1) "One game" else "Best of $it" },
                    onSelect = { draft.format = f.copy(bestOf = it) },
                )
                Spacer(Modifier.height(16.dp))
                FieldLabel("Points per game")
                ChipRow(
                    options = listOf(11, 15, 21),
                    selected = f.pointsTo,
                    label = { "To $it" },
                    onSelect = { draft.format = f.copy(pointsTo = it) },
                )
            }
            is MatchFormat.Rally -> {
                FieldLabel(if (f.sport == "table_tennis") "Games to win the match" else "Sets to win the match")
                Stepper(
                    value = f.bestOf,
                    // Sets are played in odd numbers so a match cannot end level.
                    onChange = { draft.format = f.copy(bestOf = it.coerceIn(1, 7).let { n -> if (n % 2 == 0) n - 1 else n }) },
                    min = 1, max = 7,
                    suffix = "",
                )
                Spacer(Modifier.height(14.dp))
                FieldLabel("Points per set")
                Stepper(
                    value = f.pointsTo,
                    onChange = { draft.format = f.copy(pointsTo = it.coerceIn(5, 25)) },
                    min = 5, max = 25,
                    suffix = "pts",
                )
            }
            is MatchFormat.Tennis -> {
                FieldLabel("Sets to win the match")
                Stepper(
                    value = f.bestOf,
                    onChange = { draft.format = f.copy(bestOf = it.coerceIn(1, 5).let { n -> if (n % 2 == 0) n - 1 else n }) },
                    min = 1, max = 5,
                    suffix = "",
                )
                Spacer(Modifier.height(14.dp))
                FieldLabel("Games per set")
                Stepper(
                    value = f.gamesTo,
                    onChange = { draft.format = f.copy(gamesTo = it.coerceIn(4, 6)) },
                    min = 4, max = 6,
                    suffix = "gm",
                )
            }
            is MatchFormat.Periods -> {
                FieldLabel(if (f.sport == "basketball") "Quarters" else "Halves")
                Stepper(
                    value = f.periods,
                    onChange = { draft.format = f.copy(periods = it.coerceIn(1, 4)) },
                    min = 1, max = 4,
                    suffix = "",
                )
                Spacer(Modifier.height(14.dp))
                FieldLabel(if (f.sport == "basketball") "Minutes per quarter" else "Minutes per half")
                Stepper(
                    value = f.periodLengthMin,
                    onChange = { draft.format = f.copy(periodLengthMin = it.coerceIn(3, 45)) },
                    min = 3, max = 45,
                    suffix = "min",
                )
            }
        }
    }

    // Ball type is cricket's alone, and independent of length — kept out of the
    // presets so picking "T20" never silently changes the ball you're playing with.
    if (draft.format is MatchFormat.Cricket) {
        Spacer(Modifier.height(20.dp))
        FieldLabel("Ball type")
        ChipRow(
            options = BallType.entries.toList(),
            selected = draft.ball,
            label = { it.label },
            onSelect = { ball ->
                (draft.format as? MatchFormat.Cricket)?.let { draft.format = it.copy(ball = ball) }
            },
        )
    }
}

/** The preset cards, plus the Custom escape hatch, as one wrapping row. */
@Composable
@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
private fun FormatPresetRow(
    presets: List<FormatPreset>,
    customSelected: Boolean,
    isSelected: (FormatPreset) -> Boolean,
    onSelect: (FormatPreset) -> Unit,
    onCustom: () -> Unit,
) {
    // Two per row, equal width — three presets plus Custom make a clean 2×2 block.
    // Left to wrap naturally, the fourth card stranded itself on its own line and
    // read like a layout accident rather than a choice.
    androidx.compose.foundation.layout.FlowRow(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
        maxItemsInEachRow = 2,
    ) {
        presets.forEach { preset ->
            FormatPresetCard(
                label = preset.label,
                sub = preset.sub,
                selected = isSelected(preset),
                onClick = { onSelect(preset) },
                modifier = Modifier.weight(1f),
            )
        }
        FormatPresetCard(
            label = "Custom",
            sub = "Set it yourself",
            selected = customSelected,
            onClick = onCustom,
            modifier = Modifier.weight(1f),
        )
        // An odd preset count would leave the last card double-width; a spacer keeps
        // every card on the same grid.
        if (presets.size % 2 == 0) Spacer(Modifier.weight(1f))
    }
}

@Composable
private fun FormatPresetCard(
    label: String,
    sub: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier
            .pressable(onClick = onClick)
            .clip(RoundedCornerShape(14.dp))
            .background(if (selected) BlueTint else Surface)
            .border(
                BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke),
                RoundedCornerShape(14.dp),
            )
            .padding(horizontal = 14.dp, vertical = 11.dp),
    ) {
        Text(
            label,
            color = if (selected) Blue else Text1,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
        )
        Spacer(Modifier.height(2.dp))
        Text(sub, color = if (selected) Blue.copy(alpha = 0.75f) else Text3, fontSize = 12.sp, maxLines = 1)
    }
}

// Lists the creator's recent CONFIRMED turf bookings so they can attach the one this
// match was played on. The chosen booking id rides along on create; the backend
// validates it and auto-verifies the result against it.
@Composable
private fun TurfBookingPicker(
    loadBookings: suspend () -> List<com.haraan.app.data.BookingLite>,
    selectedId: Long?,
    onSelect: (Long?) -> Unit,
) {
    var loading by remember { mutableStateOf(true) }
    var bookings by remember { mutableStateOf<List<com.haraan.app.data.BookingLite>>(emptyList()) }

    LaunchedEffect(Unit) {
        loading = true
        bookings = runCatching { loadBookings() }
            .getOrDefault(emptyList())
            .filter { it.status.equals("CONFIRMED", ignoreCase = true) }
        loading = false
    }

    FieldLabel("Verify against a booking")
    when {
        loading -> Row(verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = Blue)
            Spacer(Modifier.width(8.dp))
            Text("Loading your bookings…", color = Text3, fontSize = 13.sp)
        }
        bookings.isEmpty() -> Text(
            "No confirmed turf bookings found. The match will use captain verification instead.",
            color = Text3, fontSize = 13.sp,
        )
        else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            bookings.forEach { b ->
                val sel = b.id == selectedId
                Row(
                    Modifier
                        .fillMaxWidth()
                        .pressable { onSelect(if (sel) null else b.id) }
                        .clip(RoundedCornerShape(12.dp))
                        .background(Surface)
                        .border(1.5.dp, if (sel) Green else Stroke, RoundedCornerShape(12.dp))
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(b.eventTitle, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        val meta = listOfNotNull(b.eventVenue, b.eventDate).joinToString(" · ")
                        if (meta.isNotBlank()) {
                            Spacer(Modifier.height(2.dp))
                            Text(meta, color = Text3, fontSize = 12.sp)
                        }
                    }
                    if (sel) {
                        Icon(Icons.Filled.Check, contentDescription = "Selected", tint = Green, modifier = Modifier.size(20.dp))
                    }
                }
            }
        }
    }
}

/**
 * Mandatory GPS capture for a public match. The fix — not the typed name — is what
 * makes a match findable by distance, so this gates Continue.
 *
 * Every failure path stays actionable: a denied permission offers the system
 * settings, a missing fix offers a retry. The one thing this must never do is leave
 * the creator staring at a disabled button with no way forward.
 */
@Composable
private fun MatchLocationField(
    latitude: Double?,
    longitude: Double?,
    onResolved: (Double, Double, String, String) -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { com.haraan.app.data.LocationRepository(context) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var denied by remember { mutableStateOf(false) }
    var detectedLabel by remember { mutableStateOf("") }

    fun detect() {
        loading = true
        error = null
        scope.launch {
            when (val state = repo.detectCurrent()) {
                is com.haraan.app.data.LocationState.Resolved -> {
                    val lat = state.latitude
                    val lng = state.longitude
                    if (lat == null || lng == null) {
                        error = "Couldn't pin your position. Try again in the open."
                    } else {
                        denied = false
                        // Prefer the finest label available: area → city.
                        detectedLabel = listOf(state.area, state.city)
                            .firstOrNull { it.isNotBlank() && !it.equals("Unknown", true) }
                            .orEmpty()
                        onResolved(lat, lng, detectedLabel, state.district)
                    }
                }
                com.haraan.app.data.LocationState.Denied -> {
                    denied = true
                    error = "Location permission is off."
                }
                com.haraan.app.data.LocationState.ServicesOff ->
                    error = "Location is switched off on this device. Turn it on in Settings, then try again."
                com.haraan.app.data.LocationState.Unavailable ->
                    error = "No GPS signal yet. Step outside or wait a moment, then try again."
                else -> error = "Couldn't read your location. Try again."
            }
            loading = false
        }
    }

    val permLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            denied = false
            detect()
        } else {
            denied = true
            error = "Location permission is off."
        }
    }

    fun request() {
        if (repo.hasPermission()) detect() else permLauncher.launch(Manifest.permission.ACCESS_FINE_LOCATION)
    }

    val hasFix = latitude != null && longitude != null

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(if (hasFix) Color(0xFFF0FDF4) else Color(0xFFF8FAFC))
            .border(
                1.dp,
                if (hasFix) Color(0xFFBBF7D0) else Color(0xFFE2E8F0),
                RoundedCornerShape(12.dp),
            )
            .padding(14.dp),
    ) {
        if (hasFix) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Icon(Icons.Filled.Check, contentDescription = null, tint = Color(0xFF16A34A), modifier = Modifier.size(18.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Location captured",
                        color = Color(0xFF15803D),
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = detectedLabel.ifBlank { "Nearby players can find this match" },
                        color = Text3,
                        fontSize = 12.sp,
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(
                text = if (loading) "Updating…" else "Update location",
                color = Blue,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(RoundedCornerShape(6.dp))
                    .clickable(enabled = !loading) { request() }
                    .padding(vertical = 4.dp, horizontal = 2.dp),
            )
        } else {
            Text(
                text = "Allow location to create this match",
                color = Text1,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = "We stamp where the match is played so players nearby can find it. Shown only as the area name — never your exact address.",
                color = Text3,
                fontSize = 12.sp,
                lineHeight = 16.sp,
            )
            Spacer(Modifier.height(12.dp))
            Button(
                onClick = {
                    // Once denied, the OS won't re-prompt — send them to settings.
                    if (denied && !repo.hasPermission()) {
                        runCatching {
                            context.startActivity(
                                android.content.Intent(
                                    android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                                    android.net.Uri.fromParts("package", context.packageName, null),
                                ).addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
                            )
                        }
                    } else {
                        request()
                    }
                },
                enabled = !loading,
                colors = ButtonDefaults.buttonColors(containerColor = Blue, contentColor = Color.White),
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth().height(44.dp),
            ) {
                if (loading) {
                    CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = Color.White)
                    Spacer(Modifier.width(8.dp))
                    Text("Getting location…", fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                } else {
                    Icon(Icons.Filled.MyLocation, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        text = if (denied) "Open settings" else "Allow location",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }
        }

        error?.let {
            Spacer(Modifier.height(8.dp))
            Text(text = it, color = Color(0xFFDC2626), fontSize = 12.sp, lineHeight = 16.sp)
        }
    }
}

// "Use current location" — fills the venue from the creator's device location (reverse
// geocoded to an area label). Requests location permission on first use; on any failure the
// creator just types the venue as before.
@Composable
private fun UseCurrentLocationButton(
    label: String = "Use current location",
    resolver: (Context) -> String? = ::resolveCurrentVenue,
    onResolved: (String) -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var loading by remember { mutableStateOf(false) }

    fun resolve() {
        loading = true
        scope.launch {
            val value = withContext(Dispatchers.IO) { resolver(context) }
            loading = false
            if (value != null) {
                onResolved(value)
            } else {
                Toast.makeText(context, "Couldn't read your location. Type it instead.", Toast.LENGTH_SHORT).show()
            }
        }
    }

    val permLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) resolve()
        else Toast.makeText(context, "Location permission is needed to auto-fill this.", Toast.LENGTH_SHORT).show()
    }

    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .clickable(enabled = !loading) {
                val granted = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
                    ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
                if (granted) resolve() else permLauncher.launch(Manifest.permission.ACCESS_FINE_LOCATION)
            }
            .padding(vertical = 6.dp, horizontal = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        if (loading) {
            CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Blue)
        } else {
            Icon(Icons.Filled.MyLocation, contentDescription = null, tint = Blue, modifier = Modifier.size(16.dp))
        }
        Text(
            text = if (loading) "Getting location…" else label,
            color = Blue,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
        )
    }
}

// Blocking — call off the main thread. Returns a readable area label ("Andheri, Mumbai") from
// the most recent device fix, or null if location/geocoding is unavailable.
private fun resolveCurrentVenue(context: Context): String? = try {
    val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
    val loc = lm?.let {
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER, LocationManager.PASSIVE_PROVIDER)
            .mapNotNull { p -> runCatching { if (it.isProviderEnabled(p)) it.getLastKnownLocation(p) else null }.getOrNull() }
            .maxByOrNull { l -> l.time }
    }
    if (loc == null) {
        null
    } else {
        @Suppress("DEPRECATION")
        val addr = Geocoder(context, Locale.getDefault()).getFromLocation(loc.latitude, loc.longitude, 1)?.firstOrNull()
        val parts = listOfNotNull(addr?.subLocality, addr?.locality, addr?.adminArea)
            .filter { it.isNotBlank() }
            .distinct()
        (parts.take(2).joinToString(", ").ifBlank { addr?.getAddressLine(0).orEmpty() }).takeIf { it.isNotBlank() }
    }
} catch (_: SecurityException) {
    null
} catch (_: Exception) {
    null
}

// Blocking — call off the main thread. Returns just the village/town/area from the
// most recent device fix (locality → subLocality → subAdminArea), or null.
private fun resolveCurrentLocality(context: Context): String? = try {
    val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager
    val loc = lm?.let {
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER, LocationManager.PASSIVE_PROVIDER)
            .mapNotNull { p -> runCatching { if (it.isProviderEnabled(p)) it.getLastKnownLocation(p) else null }.getOrNull() }
            .maxByOrNull { l -> l.time }
    }
    if (loc == null) {
        null
    } else {
        @Suppress("DEPRECATION")
        val addr = Geocoder(context, Locale.getDefault()).getFromLocation(loc.latitude, loc.longitude, 1)?.firstOrNull()
        listOfNotNull(addr?.locality, addr?.subLocality, addr?.subAdminArea)
            .firstOrNull { it.isNotBlank() }
    }
} catch (_: SecurityException) {
    null
} catch (_: Exception) {
    null
}

// ─────────────────────────────────────────────────────── Step 3 · Teams ────────
@Composable
private fun StepTeams(draft: CreateMatchDraft, searchPlayers: suspend (String) -> List<PlayerLite>) {
    StepScaffold(
        title = draft.spec.teamsTitle,
        subtitle = draft.spec.teamsSubtitle,
    ) {
        TeamBlock(
            heading = draft.sideLabel(0),
            noun = draft.sideNoun,
            name = draft.teamA,
            onName = { draft.teamA = it },
            emblemIndex = draft.teamAEmblem,
            onEmblem = { draft.teamAEmblem = it },
            photoUri = draft.teamAPhoto,
            onPhoto = { draft.teamAPhoto = it },
            squad = draft.squadA,
            limit = draft.playersPerSide,
            searchPlayers = searchPlayers,
        )
        Spacer(Modifier.height(20.dp))
        TeamBlock(
            heading = draft.sideLabel(1),
            noun = draft.sideNoun,
            name = draft.teamB,
            onName = { draft.teamB = it },
            emblemIndex = draft.teamBEmblem,
            onEmblem = { draft.teamBEmblem = it },
            photoUri = draft.teamBPhoto,
            onPhoto = { draft.teamBPhoto = it },
            squad = draft.squadB,
            limit = draft.playersPerSide,
            searchPlayers = searchPlayers,
        )
        Spacer(Modifier.height(16.dp))
        ImpactNote(
            "Registered players earn XP",
            "Search a teammate by their @username. A match only counts for Ranked XP " +
                "with enough distinct registered players on each side.",
        )
    }
}

/**
 * One search hit. Shows the @handle prominently — that's the thing a player shares with
 * a teammate, and the thing that makes two people with the same name distinguishable.
 * Accounts created before usernames existed fall back to their district.
 */
@Composable
private fun PlayerResultRow(
    player: PlayerLite,
    alreadyAdded: Boolean,
    onAdd: () -> Unit,
) {
    val subtitle = player.username?.let { "@$it" }
        ?: player.district?.takeIf { it.isNotBlank() }
        ?: player.playerId

    Row(
        Modifier
            .fillMaxWidth()
            .pressable(enabled = !alreadyAdded, onClick = onAdd)
            .clip(RoundedCornerShape(12.dp))
            .background(if (alreadyAdded) Color(0xFFF3F5F8) else Color(0xFFF7F9FC))
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(34.dp).clip(CircleShape).background(Color(0xFFE3EAF6)),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                player.name.trim().take(1).uppercase().ifBlank { "?" },
                color = Blue, fontSize = 14.sp, fontWeight = FontWeight.Bold,
            )
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                player.name.ifBlank { player.playerId },
                color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, maxLines = 1,
            )
            Text(subtitle, color = Text3, fontSize = 12.sp, maxLines = 1)
        }
        if (alreadyAdded) {
            Text("Added", color = Text3, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        } else {
            Box(
                Modifier.size(28.dp).clip(CircleShape).background(Green),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Default.Add, "Add ${player.name}", tint = Color.White, modifier = Modifier.size(16.dp))
            }
        }
    }
}

@Composable
private fun TeamBlock(
    heading: String,
    /** "Team" / "Player" / "Pair" — labels the name field and the icon picker. */
    noun: String,
    name: String,
    onName: (String) -> Unit,
    emblemIndex: Int,
    onEmblem: (Int) -> Unit,
    photoUri: android.net.Uri?,
    onPhoto: (android.net.Uri?) -> Unit,
    squad: androidx.compose.runtime.snapshots.SnapshotStateList<SquadMember>,
    limit: Int,
    searchPlayers: suspend (String) -> List<PlayerLite>,
) {
    var entry by remember { mutableStateOf("") }
    var results by remember { mutableStateOf<List<PlayerLite>>(emptyList()) }
    var searching by remember { mutableStateOf(false) }
    var searched by remember { mutableStateOf(false) }
    var guestName by remember { mutableStateOf("") }
    var showGuest by remember { mutableStateOf(false) }

    // Debounced search by @username or name. Was an exact-match lookup on a Player ID
    // (HRN-000123), which nobody knows by heart — in practice you could only build a
    // squad with people standing next to you. An exact Player ID still resolves, because
    // the search endpoint matches on it too.
    LaunchedEffect(entry) {
        val q = entry.trim()
        if (q.length < 2) {
            results = emptyList(); searching = false; searched = false
            return@LaunchedEffect
        }
        searching = true
        delay(350)
        results = searchPlayers(q)
        searching = false
        searched = true
    }

    val atLimit = squad.size >= limit

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Text(heading, color = Text3, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(8.dp))
        WizardTextField(value = name, onChange = onName, placeholder = "$noun name")
        Spacer(Modifier.height(12.dp))

        TeamIconPicker(
            noun = noun,
            emblemIndex = emblemIndex,
            photoUri = photoUri,
            onEmblem = onEmblem,
            onPhoto = onPhoto,
        )
        Spacer(Modifier.height(12.dp))

        WizardTextField(
            value = entry,
            onChange = { entry = it },
            placeholder = "Search @username or name (${squad.size}/$limit)",
        )

        // Results are the control surface: tap a row to add. No separate "+" button to
        // aim at, and no guessing whether the thing you typed resolved to the right person.
        when {
            searching -> {
                Spacer(Modifier.height(10.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Text3)
                    Spacer(Modifier.width(8.dp))
                    Text("Searching…", color = Text3, fontSize = 13.sp)
                }
            }
            atLimit && entry.isNotBlank() -> {
                Spacer(Modifier.height(10.dp))
                Text("Squad full — remove a player first", color = Text3, fontSize = 13.sp)
            }
            searched && results.isEmpty() -> {
                Spacer(Modifier.height(10.dp))
                Text("No players found for \"${entry.trim()}\"", color = Text2, fontSize = 13.sp)
            }
            results.isNotEmpty() -> {
                Spacer(Modifier.height(10.dp))
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    results.take(6).forEach { player ->
                        val already = squad.any { it.id == player.playerId }
                        PlayerResultRow(
                            player = player,
                            alreadyAdded = already,
                            onAdd = {
                                squad.add(SquadMember(player.playerId, player.name))
                                entry = ""
                                results = emptyList()
                                searched = false
                            },
                        )
                    }
                }
            }
        }

        // Guest add — casual players without a Haraan account (no XP, just fill the side).
        val canAddGuest = guestName.trim().length >= 2 && squad.size < limit
        if (!showGuest) {
            Spacer(Modifier.height(10.dp))
            Text(
                "+ Add guest",
                color = Blue,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.pressable { showGuest = true },
            )
        } else {
            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.weight(1f)) {
                    WizardTextField(
                        value = guestName,
                        onChange = { guestName = it },
                        placeholder = "Guest name (no account)",
                    )
                }
                Spacer(Modifier.width(8.dp))
                Box(
                    Modifier
                        .size(48.dp)
                        .pressable(enabled = canAddGuest) {
                            squad.add(SquadMember(id = "", name = guestName.trim(), isGuest = true))
                            guestName = ""
                            showGuest = false
                        }
                        .clip(RoundedCornerShape(12.dp))
                        .background(if (canAddGuest) Green else Stroke),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Default.Add, "Add guest", tint = Color.White, modifier = Modifier.size(20.dp))
                }
            }
            Spacer(Modifier.height(4.dp))
            Text("Guests don't earn XP — they just fill the side.", color = Text3, fontSize = 11.sp)
        }

        // Once a side has more than five players, let the creator mark a captain and
        // vice-captain. Only one of each across the squad; a player can't be both.
        val showLeaderPicks = squad.size > 5
        fun setCaptain(index: Int) {
            val turningOn = !squad[index].isCaptain
            for (j in squad.indices) {
                squad[j] = squad[j].copy(
                    isCaptain = j == index && turningOn,
                    isViceCaptain = squad[j].isViceCaptain && !(j == index && turningOn),
                )
            }
        }
        fun setViceCaptain(index: Int) {
            val turningOn = !squad[index].isViceCaptain
            for (j in squad.indices) {
                squad[j] = squad[j].copy(
                    isViceCaptain = j == index && turningOn,
                    isCaptain = squad[j].isCaptain && !(j == index && turningOn),
                )
            }
        }

        squad.forEachIndexed { i, player ->
            Spacer(Modifier.height(8.dp))
            // A captain/vice-captain gets a tinted, accent-bordered row so the pick reads
            // at a glance — not just the pill.
            val roleColor = when {
                player.isCaptain -> Blue
                player.isViceCaptain -> Green
                else -> null
            }
            val rowBg = when {
                player.isCaptain -> BlueTint
                player.isViceCaptain -> GreenTint
                else -> Bg
            }
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(10.dp))
                    .background(rowBg)
                    .then(
                        if (roleColor != null) Modifier.border(1.dp, roleColor, RoundedCornerShape(10.dp))
                        else Modifier,
                    )
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("${i + 1}", color = Text3, fontSize = 13.sp, modifier = Modifier.width(22.dp))
                Column(Modifier.weight(1f)) {
                    Text(player.name, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    val subtitle = when {
                        player.isCaptain -> "Captain"
                        player.isViceCaptain -> "Vice-captain"
                        player.isGuest -> "Guest player"
                        else -> player.id
                    }
                    Text(
                        subtitle,
                        color = roleColor ?: Text3,
                        fontSize = 11.sp,
                        fontWeight = if (roleColor != null) FontWeight.SemiBold else FontWeight.Normal,
                    )
                }
                if (player.isGuest) {
                    Box(
                        Modifier.clip(RoundedCornerShape(6.dp)).background(Stroke).padding(horizontal = 7.dp, vertical = 2.dp),
                    ) {
                        Text("GUEST", color = Text2, fontSize = 8.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                    }
                    Spacer(Modifier.width(10.dp))
                }
                if (showLeaderPicks) {
                    LeaderBadge("C", active = player.isCaptain, activeColor = Blue) { setCaptain(i) }
                    Spacer(Modifier.width(6.dp))
                    LeaderBadge("VC", active = player.isViceCaptain, activeColor = Green) { setViceCaptain(i) }
                    Spacer(Modifier.width(10.dp))
                }
                Icon(
                    Icons.Default.Close, "Remove",
                    tint = Text3,
                    modifier = Modifier
                        // Removing someone is destructive — a firmer, distinctly
                        // different response from the tick that added them.
                        .pressable(haptic = Feel.REMOVE) { squad.removeAt(i) }
                        .size(18.dp),
                )
            }
        }
    }
}

// A small tappable "C" / "VC" pill for picking a captain / vice-captain. Filled with its
// accent when active, a quiet outline when not.
@Composable
private fun LeaderBadge(label: String, active: Boolean, activeColor: Color, onClick: () -> Unit) {
    Box(
        Modifier
            .pressable { onClick() }
            .clip(RoundedCornerShape(6.dp))
            .background(if (active) activeColor else Color.Transparent)
            .then(if (active) Modifier else Modifier.border(1.dp, Stroke, RoundedCornerShape(6.dp)))
            .padding(horizontal = 8.dp, vertical = 4.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            color = if (active) Color.White else Text3,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.5.sp,
        )
    }
}


// Team icon chooser — a live preview, a scrollable row of default emblems, and an
// "upload" tile that opens the system photo picker for a custom image. A chosen photo
// wins over the emblem; tapping an emblem clears the photo.
@Composable
private fun TeamIconPicker(
    noun: String,
    emblemIndex: Int,
    photoUri: android.net.Uri?,
    onEmblem: (Int) -> Unit,
    onPhoto: (android.net.Uri?) -> Unit,
) {
    val photoLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia()
    ) { uri -> if (uri != null) onPhoto(uri) }

    Row(verticalAlignment = Alignment.CenterVertically) {
        TeamIconPreview(emblemIndex, photoUri, size = 52.dp)
        Spacer(Modifier.width(12.dp))
        Column {
            Text("$noun icon", color = Text1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            Text(
                if (photoUri != null) "Custom image" else "Pick a default or upload your own",
                color = Text3, fontSize = 12.sp,
            )
        }
    }
    Spacer(Modifier.height(10.dp))
    Row(
        modifier = Modifier.horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        teamEmblems.forEachIndexed { i, emblem ->
            val selected = photoUri == null && i == emblemIndex
            Box(
                Modifier
                    .size(44.dp)
                    .pressable { onPhoto(null); onEmblem(i) }
                    .clip(CircleShape)
                    .border(BorderStroke(if (selected) 2.5.dp else 0.dp, Blue), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                androidx.compose.foundation.Image(
                    painter = androidx.compose.ui.res.painterResource(emblem.resId),
                    contentDescription = emblem.key,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(CircleShape),
                )
            }
        }
        // Upload tile — shows the picked thumbnail once chosen, otherwise a "+".
        Box(
            Modifier
                .size(44.dp)
                .clip(CircleShape)
                .background(BlueTint)
                .border(
                    BorderStroke(if (photoUri != null) 2.5.dp else 1.dp, if (photoUri != null) Blue else Stroke),
                    CircleShape,
                )
                .clickable {
                    photoLauncher.launch(
                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
                    )
                },
            contentAlignment = Alignment.Center,
        ) {
            if (photoUri != null) {
                AsyncImage(
                    model = photoUri,
                    contentDescription = "Team icon",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(CircleShape),
                )
            } else {
                Icon(Icons.Default.Add, "Upload team icon", tint = Blue, modifier = Modifier.size(20.dp))
            }
        }
    }
}

@Composable
private fun TeamIconPreview(emblemIndex: Int, photoUri: android.net.Uri?, size: androidx.compose.ui.unit.Dp) {
    if (photoUri != null) {
        AsyncImage(
            model = photoUri,
            contentDescription = "Team icon",
            contentScale = ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape),
        )
    } else {
        val emblem = teamEmblems[emblemIndex.coerceIn(0, teamEmblems.size - 1)]
        androidx.compose.foundation.Image(
            painter = androidx.compose.ui.res.painterResource(emblem.resId),
            contentDescription = emblem.key,
            contentScale = ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape),
        )
    }
}

/**
 * "Looking for players?" — opens the match for nearby players to request to join, and
 * how many the match wants. The discovery + request/approve flow lives in the Scheduled
 * tab's "Open near me".
 */
@Composable
private fun LookingForPlayersCard(draft: CreateMatchDraft) {
    var open by remember { mutableStateOf(draft.openToJoin) }
    var slots by remember { mutableStateOf(draft.slotsNeeded) }
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("Looking for players?", color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(2.dp))
                Text("Let nearby players find your match and ask to join.", color = Text3, fontSize = 12.sp)
            }
            Switch(
                checked = open,
                onCheckedChange = { open = it; draft.openToJoin = it },
                colors = SwitchDefaults.colors(
                    checkedThumbColor = Color.White,
                    checkedTrackColor = Blue,
                    uncheckedThumbColor = Color.White,
                    uncheckedTrackColor = Text3.copy(alpha = 0.4f),
                    uncheckedBorderColor = Color.Transparent,
                ),
            )
        }
        if (open) {
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Players needed", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                SlotStepper(slots) { v -> slots = v; draft.slotsNeeded = v }
            }
        }
    }
}

/** A compact `[−] N [+]` stepper for the players-needed count (1..20). */
@Composable
private fun SlotStepper(count: Int, onChange: (Int) -> Unit) {
    Row(
        Modifier.clip(RoundedCornerShape(10.dp)).border(1.dp, Stroke, RoundedCornerShape(10.dp)),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(34.dp).clickable { if (count > 1) onChange(count - 1) }, contentAlignment = Alignment.Center) {
            Text("−", fontSize = 19.sp, fontWeight = FontWeight.Bold, color = if (count > 1) Text2 else Text3)
        }
        Text("$count", Modifier.width(28.dp), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Text1, textAlign = TextAlign.Center)
        Box(Modifier.size(34.dp).clip(RoundedCornerShape(9.dp)).background(Blue).clickable { if (count < 20) onChange(count + 1) }, contentAlignment = Alignment.Center) {
            Text("+", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = Color.White)
        }
    }
}

// ────────────────────────────────────────────────────── Step 4 · Review ────────
@Composable
private fun StepReview(draft: CreateMatchDraft) {
    StepScaffold(
        title = "Review & create",
        subtitle = "One last look before it goes live.",
    ) {
        SummaryCard(draft)
        Spacer(Modifier.height(16.dp))
        WhenCard(draft)
        // "Looking for players" only makes sense for public matches (a private one is
        // closed by definition and never appears in discovery).
        if (!draft.isPrivate) {
            Spacer(Modifier.height(16.dp))
            LookingForPlayersCard(draft)
        }
        Spacer(Modifier.height(16.dp))
        if (draft.isPrivate) {
            ImpactNote(
                "Private · no XP or ranking",
                "Created as a private scoreboard. You'll get a share code so your group can " +
                    "follow the score. It won't appear in any feed or leaderboard.",
            )
        } else {
            ImpactNote(
                "Starts at Low trust · ${draft.type.baseXp} XP ceiling",
                "This match is created as Scheduled. After it's Completed both captains confirm the " +
                    "result within 72h to settle Ranked XP — otherwise it expires to Low trust.",
            )
        }
    }
}

/**
 * When the match kicks off: "Play now" (the default — runs the toss immediately) or
 * "Schedule for later" (pick a date + time; the match waits in the Scheduled tab until
 * the creator starts it). Uses the platform date/time pickers so it feels native and
 * needs no extra Compose Material dependency.
 */
@Composable
private fun WhenCard(draft: CreateMatchDraft) {
    val isScheduled = draft.scheduledAt != null
    // Drives the in-app schedule sheet. The platform DatePickerDialog was replaced with
    // a custom, on-brand picker — the stock dialog ships a teal Holo theme that clashes
    // hard with the app's blue design language.
    var showSheet by remember { mutableStateOf(false) }
    if (showSheet) {
        ScheduleDialog(
            initialMillis = draft.scheduledAt,
            onDismiss = { showSheet = false },
            onConfirm = { draft.scheduledAt = it; showSheet = false },
        )
    }

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Text("When", color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(12.dp))
        // A segmented mode toggle — selection is a *progress* choice, so it reads blue
        // (per the app's CTA rules: blue = progress, green = commit). Green stays
        // reserved for the single "Create Match" commit button below, so the two don't
        // compete. The selected side is a soft blue tint, not a solid fill.
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            WhenChoice(
                label = "Play now",
                icon = Icons.Filled.PlayArrow,
                selected = !isScheduled,
                modifier = Modifier.weight(1f),
            ) { draft.scheduledAt = null }
            WhenChoice(
                label = "Schedule",
                icon = Icons.Filled.Schedule,
                selected = isScheduled,
                modifier = Modifier.weight(1f),
            ) { showSheet = true }
        }
        if (isScheduled) {
            Spacer(Modifier.height(12.dp))
            val fmt = remember { java.text.SimpleDateFormat("EEE, d MMM · h:mm a", java.util.Locale.getDefault()) }
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(BlueTint)
                    .clickable { showSheet = true }
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Schedule, null, tint = Blue, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(10.dp))
                Text(
                    fmt.format(java.util.Date(draft.scheduledAt!!)),
                    color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                Text("Change", color = Blue, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            }
        } else {
            Spacer(Modifier.height(8.dp))
            Text(
                "The toss runs right away and the match goes live.",
                color = Text3, fontSize = 12.sp,
            )
        }
    }
}

@Composable
private fun WhenChoice(
    label: String,
    icon: ImageVector,
    selected: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Column(
        modifier
            .clip(RoundedCornerShape(14.dp))
            // Selected = soft blue tint + blue border (not a solid fill, so it never
            // reads as a primary button). Unselected = plain surface.
            .background(if (selected) BlueTint else Surface)
            .border(
                BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke),
                RoundedCornerShape(14.dp),
            )
            .clickable(onClick = onClick)
            .padding(vertical = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(icon, null, tint = if (selected) Blue else Text2, modifier = Modifier.size(20.dp))
        Spacer(Modifier.height(6.dp))
        Text(
            label,
            color = if (selected) Blue else Text2,
            fontSize = 14.sp, fontWeight = if (selected) FontWeight.Bold else FontWeight.SemiBold,
        )
    }
}

/**
 * In-app match scheduler — replaces the stock DatePickerDialog (whose teal Holo theme
 * clashed with the app). A premium, on-brand sheet: a horizontal rail of upcoming days
 * and one of 30-minute time slots, both tactile blue-selectable pills, with a live
 * summary and a blue confirm. Past slots grey out when "Today" is selected, so a
 * scheduled match can never land in the past.
 */
@Composable
private fun ScheduleDialog(
    initialMillis: Long?,
    onDismiss: () -> Unit,
    onConfirm: (Long) -> Unit,
) {
    // Midnight today, our day-0 anchor.
    val today0 = remember {
        java.util.Calendar.getInstance().apply {
            set(java.util.Calendar.HOUR_OF_DAY, 0); set(java.util.Calendar.MINUTE, 0)
            set(java.util.Calendar.SECOND, 0); set(java.util.Calendar.MILLISECOND, 0)
        }.timeInMillis
    }
    val dayMs = 24L * 60 * 60 * 1000
    // 30-minute slots, 6:00 AM → 11:30 PM — the realistic window for a gully match.
    val slots = remember { (6 * 60..23 * 60 + 30 step 30).toList() }

    // Seed selection from the existing pick, else ~1 hour out, snapped to a slot.
    val seed = remember {
        val base = initialMillis ?: (System.currentTimeMillis() + 60 * 60 * 1000)
        val c = java.util.Calendar.getInstance().apply { timeInMillis = base }
        val dayIdx = (((c.apply {
            set(java.util.Calendar.HOUR_OF_DAY, 0); set(java.util.Calendar.MINUTE, 0)
            set(java.util.Calendar.SECOND, 0); set(java.util.Calendar.MILLISECOND, 0)
        }.timeInMillis) - today0) / dayMs).toInt().coerceIn(0, 20)
        val cc = java.util.Calendar.getInstance().apply { timeInMillis = base }
        val minute = cc.get(java.util.Calendar.HOUR_OF_DAY) * 60 + cc.get(java.util.Calendar.MINUTE)
        val nearest = slots.minByOrNull { kotlin.math.abs(it - minute) } ?: (18 * 60)
        dayIdx to nearest
    }
    var selDay by remember { mutableStateOf(seed.first) }
    var selMinute by remember { mutableStateOf(seed.second) }

    fun absMillis(dayIdx: Int, minute: Int): Long =
        today0 + dayIdx * dayMs + minute.toLong() * 60_000

    // On "Today", disable slots already past (with a small buffer); bump the selection
    // forward if it fell into a now-disabled slot.
    val nowBuf = System.currentTimeMillis() + 60_000
    val firstEnabledToday = slots.firstOrNull { absMillis(0, it) >= nowBuf }
    if (selDay == 0 && (firstEnabledToday == null || selMinute < firstEnabledToday)) {
        // No enabled slot left today → jump to tomorrow; else snap to the first open slot.
        if (firstEnabledToday == null) selDay = 1 else selMinute = firstEnabledToday
    }

    val dayFmt = remember { java.text.SimpleDateFormat("EEE", java.util.Locale.getDefault()) }
    val dateFmt = remember { java.text.SimpleDateFormat("d MMM", java.util.Locale.getDefault()) }
    val timeFmt = remember { java.text.SimpleDateFormat("h:mm a", java.util.Locale.getDefault()) }
    fun timeLabel(minute: Int): String = timeFmt.format(java.util.Date(absMillis(0, minute)))
    val summaryFmt = remember { java.text.SimpleDateFormat("EEE, d MMM · h:mm a", java.util.Locale.getDefault()) }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .fillMaxWidth()
                .padding(20.dp)
                .clip(RoundedCornerShape(22.dp))
                .background(Surface)
                .padding(20.dp)
        ) {
            Text("Schedule match", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(3.dp))
            Text("Pick when it kicks off.", color = Text3, fontSize = 13.sp)
            Spacer(Modifier.height(18.dp))

            // ── Date rail ──
            Text("DATE", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp)
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                for (i in 0..20) {
                    val ms = today0 + i * dayMs
                    val top = when (i) { 0 -> "Today"; 1 -> "Tomorrow"; else -> dayFmt.format(java.util.Date(ms)) }
                    DayPill(
                        top = top,
                        day = dateFmt.format(java.util.Date(ms)),
                        selected = selDay == i,
                    ) { selDay = i }
                }
            }
            Spacer(Modifier.height(20.dp))

            // ── Time rail ──
            Text("TIME", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp)
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                slots.forEach { minute ->
                    val enabled = selDay != 0 || absMillis(0, minute) >= nowBuf
                    TimePill(
                        label = timeLabel(minute),
                        selected = selMinute == minute && enabled,
                        enabled = enabled,
                    ) { selMinute = minute }
                }
            }
            Spacer(Modifier.height(20.dp))

            // Live summary of the chosen instant.
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(BlueTint)
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Schedule, null, tint = Blue, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(10.dp))
                Text(
                    summaryFmt.format(java.util.Date(absMillis(selDay, selMinute))),
                    color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Bold,
                )
            }
            Spacer(Modifier.height(18.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Box(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(14.dp))
                        .border(1.dp, Stroke, RoundedCornerShape(14.dp))
                        .clickable(onClick = onDismiss)
                        .padding(vertical = 14.dp),
                    contentAlignment = Alignment.Center,
                ) { Text("Cancel", color = Text2, fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
                Box(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(14.dp))
                        .background(Blue)
                        .clickable {
                            // Final guard: never return a past instant (server rejects it too).
                            onConfirm(maxOf(absMillis(selDay, selMinute), System.currentTimeMillis() + 60_000))
                        }
                        .padding(vertical = 14.dp),
                    contentAlignment = Alignment.Center,
                ) { Text("Set time", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold) }
            }
        }
    }
}

/** A tactile day pill for the scheduler: weekday/label on top, date below; blue when picked. */
@Composable
private fun DayPill(top: String, day: String, selected: Boolean, onClick: () -> Unit) {
    Column(
        Modifier
            .clip(RoundedCornerShape(14.dp))
            .background(if (selected) Blue else Bg)
            .border(BorderStroke(if (selected) 0.dp else 1.dp, Stroke), RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(top, color = if (selected) Color.White.copy(alpha = 0.85f) else Text3, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(3.dp))
        Text(day, color = if (selected) Color.White else Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold, maxLines = 1)
    }
}

/** A tactile time-slot pill; blue when picked, greyed when it's already past (today). */
@Composable
private fun TimePill(label: String, selected: Boolean, enabled: Boolean, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (selected) Blue else Bg)
            .border(BorderStroke(if (selected) 0.dp else 1.dp, Stroke), RoundedCornerShape(12.dp))
            .clickable(enabled = enabled, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            color = when {
                selected -> Color.White
                !enabled -> Text3.copy(alpha = 0.4f)
                else -> Text1
            },
            fontSize = 14.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            maxLines = 1,
        )
    }
}

@Composable
private fun SummaryCard(draft: CreateMatchDraft) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            TeamIconPreview(draft.teamAEmblem, draft.teamAPhoto, size = 28.dp)
            Spacer(Modifier.width(6.dp))
            Text(
                "${draft.teamA.ifBlank { draft.sideLabel(0) }}  vs  ${draft.teamB.ifBlank { draft.sideLabel(1) }}",
                color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold,
                modifier = Modifier.weight(1f),
            )
            TeamIconPreview(draft.teamBEmblem, draft.teamBPhoto, size = 28.dp)
            Spacer(Modifier.width(8.dp))
            if (!draft.isPrivate) XpBadge(draft.type.baseXp)
        }
        Spacer(Modifier.height(14.dp))
        SummaryRow("Mode", if (draft.isPrivate) "Private" else "Public")
        SummaryRow("Sport", draft.spec.displayName)
        SummaryRow("Type", if (draft.type == MatchType.CASUAL) draft.spec.casualLabel else draft.type.label)
        // Reads in the sport's own terms — "2 × 25 min", "Doubles · best of 3 to 21" —
        // instead of the old bare "Sport: Football", which confirmed nothing.
        SummaryRow("Format", draft.format.summaryLine)
        if (draft.spec.showPlayersStepper) {
            SummaryRow("Per side", "${draft.playersPerSide} players")
        }
        SummaryRow("Venue", draft.venue.ifBlank { "—" } + if (draft.onHaraanTurf) "  · Haraan turf" else "")
        SummaryRow(
            if (draft.format is MatchFormat.Badminton) "Players" else "Squads",
            "${draft.squadA.size} + ${draft.squadB.size} added",
        )
    }
}

@Composable
private fun SummaryRow(label: String, value: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, color = Text3, fontSize = 14.sp, modifier = Modifier.width(96.dp))
        Text(value, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
    }
}

// ─────────────────────────────────────────────────────── Shared pieces ─────────
@Composable
private fun StepScaffold(title: String, subtitle: String, content: @Composable () -> Unit) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
    ) {
        item {
            Text(title, color = Text1, fontSize = 20.sp, fontWeight = FontWeight.Bold, lineHeight = 26.sp)
            Spacer(Modifier.height(6.dp))
            Text(subtitle, color = Text2, fontSize = 14.sp, lineHeight = 20.sp)
            Spacer(Modifier.height(24.dp))
        }
        item { content() }
    }
}

@Composable
private fun FieldLabel(text: String) {
    Text(text, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
    Spacer(Modifier.height(10.dp))
}

@Composable
@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
private fun <T> ChipRow(options: List<T>, selected: T, label: (T) -> String, onSelect: (T) -> Unit) {
    androidx.compose.foundation.layout.FlowRow(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        options.forEach { opt ->
            val isSel = opt == selected
            Box(
                Modifier
                    .pressable { onSelect(opt) }
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isSel) Blue else Surface)
                    .border(1.dp, if (isSel) Blue else Stroke, RoundedCornerShape(12.dp))
                    .padding(horizontal = 18.dp, vertical = 12.dp),
            ) {
                Text(
                    label(opt),
                    color = if (isSel) Color.White else Text1,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                )
            }
        }
    }
}

// One cohesive pill — [ − | value | + ] — rather than three floating tiles. The ∓ zones
// dim and stop responding at the bounds so the limits read visually, not just by clamping.
@Composable
private fun Stepper(
    value: Int,
    onChange: (Int) -> Unit,
    min: Int,
    max: Int,
    suffix: String = "",
    // Football's half length moves in fives — a 23-minute half isn't a thing, and a
    // step of 1 would mean twenty taps to get from 25 to 45.
    step: Int = 1,
) {
    Row(
        Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(12.dp)),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StepperZone("−", enabled = value > min) { onChange((value - step).coerceAtLeast(min)) }
        StepperDivider()

        Box(
            Modifier
                .widthIn(min = if (suffix.isBlank()) 56.dp else 76.dp)
                .padding(horizontal = 8.dp),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                if (suffix.isBlank()) "$value" else "$value $suffix",
                color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center,
            )
        }
        StepperDivider()
        StepperZone("+", enabled = value < max) { onChange((value + step).coerceAtMost(max)) }
    }
}

/**
 * One −/+ zone. Fires once on tap, then **repeats while held**, accelerating — going
 * from 11 a side down to 5 was six separate taps with nothing under the finger.
 * Each increment ticks, so the count can be felt without watching the number.
 */
@Composable
private fun StepperZone(symbol: String, enabled: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val view = LocalView.current

    // Held past the threshold, repeat and speed up. `enabled` is a key, so reaching a
    // bound cancels the loop rather than spinning against a clamped value.
    LaunchedEffect(pressed, enabled) {
        if (!pressed || !enabled) return@LaunchedEffect
        delay(450)
        var gap = 130L
        while (true) {
            view.performHapticFeedback(Feel.TICK)
            onClick()
            delay(gap)
            gap = (gap * 82 / 100).coerceAtLeast(45L)
        }
    }

    Box(
        Modifier
            .size(46.dp)
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
            ) {
                view.performHapticFeedback(Feel.TICK)
                onClick()
            },
        contentAlignment = Alignment.Center,
    ) {
        Text(
            symbol,
            color = when {
                !enabled -> Text3.copy(alpha = 0.4f)
                pressed -> Blue
                else -> Text1
            },
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
        )
    }
}

@Composable
private fun StepperDivider() {
    Box(Modifier.width(1.dp).height(24.dp).background(Stroke))
}

@Composable
private fun ToggleRow(label: String, sub: String, checked: Boolean, onToggle: (Boolean) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .pressable { onToggle(!checked) }
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, if (checked) Green else Stroke, RoundedCornerShape(14.dp))
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(label, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(2.dp))
            Text(sub, color = Text2, fontSize = 12.5.sp, lineHeight = 17.sp)
        }
        Spacer(Modifier.width(12.dp))
        Box(
            Modifier
                .size(24.dp)
                .clip(RoundedCornerShape(7.dp))
                .background(if (checked) Green else Color.Transparent)
                .border(BorderStroke(if (checked) 0.dp else 1.5.dp, Text3), RoundedCornerShape(7.dp)),
            contentAlignment = Alignment.Center,
        ) {
            if (checked) Icon(Icons.Default.Check, null, tint = Color.White, modifier = Modifier.size(16.dp))
        }
    }
}

@Composable
private fun WizardTextField(
    value: String,
    onChange: (String) -> Unit,
    placeholder: String,
    modifier: Modifier = Modifier,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        placeholder = { Text(placeholder, color = Text3, fontSize = 14.sp) },
        singleLine = true,
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = Blue,
            unfocusedBorderColor = Stroke,
            focusedContainerColor = Surface,
            unfocusedContainerColor = Surface,
            focusedTextColor = Text1,
            unfocusedTextColor = Text1,
            cursorColor = Blue,
        ),
    )
}

@Composable
private fun ImpactNote(title: String, body: String) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(BlueTint)
            .border(1.dp, Blue.copy(alpha = 0.3f), RoundedCornerShape(14.dp))
            .padding(14.dp)
    ) {
        Text(title, color = Blue, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(4.dp))
        Text(body, color = Text2, fontSize = 13.sp, lineHeight = 18.sp)
    }
}
