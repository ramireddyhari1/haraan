package com.example.thanna.ui.matches.create

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
import androidx.compose.animation.core.tween
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
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
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
import com.example.thanna.data.PlayerLite
import com.example.thanna.data.SquadMember
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

// ─────────────────────────────────────────────────────────────────────────────
// Palette — mirrors the CREX light theme tokens in MainScreen (which are private)
// ─────────────────────────────────────────────────────────────────────────────
private val Bg = Color(0xFFEBEBF0)
private val Surface = Color(0xFFFFFFFF)
private val Blue = Color(0xFF2563EB)
private val Text1 = Color(0xFF111827)
private val Text2 = Color(0xFF5A5A6A)
private val Text3 = Color(0xFF9A9AA8)
private val Stroke = Color(0xFFE2E8F0)
private val BlueTint = Color(0xFFEFF4FF)
private val Green = Color(0xFF16A34A)
private val GreenTint = Color(0xFFE9F7EF)

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
    TeamEmblem("action1", com.example.thanna.R.drawable.match_emblem_1),
    TeamEmblem("action2", com.example.thanna.R.drawable.match_emblem_2),
    TeamEmblem("action3", com.example.thanna.R.drawable.match_emblem_3),
    TeamEmblem("action4", com.example.thanna.R.drawable.match_emblem_4),
)

/** Maps a persisted emblem [key] back to its bundled drawable, so match screens can re-render it. */
@androidx.annotation.DrawableRes
fun emblemDrawableFor(key: String): Int? =
    teamEmblems.firstOrNull { it.key == key }?.resId

class CreateMatchDraft {
    // Public = ranked/feed-visible (earns XP after verification). Private = a closed
    // scoreboard reachable only by share code: no XP, never ranked, hidden from feeds.
    var isPrivate by mutableStateOf(false)
    var type by mutableStateOf(MatchType.CASUAL)
    var overs by mutableStateOf(20)
    var ball by mutableStateOf(BallType.TENNIS)
    var playersPerSide by mutableStateOf(11)
    var venue by mutableStateOf("")
    // Village / town / area — finer than the profile district; optional, typed or
    // auto-filled from GPS. Shown with the venue on the live card.
    var locality by mutableStateOf("")
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
}

/**
 * Sprint 1 — Create Match Wizard. Four steps: Type → Rules → Teams → Review.
 * Self-contained UI + local form state. [onCreate] hands the assembled draft to
 * the caller (backend persistence + XP ledger arrive in later sprints).
 */
@Composable
fun CreateMatchWizard(
    onDismiss: () -> Unit,
    onCreate: (CreateMatchDraft) -> Unit,
    lookupPlayer: suspend (playerId: String) -> PlayerLite?,
    loadBookings: suspend () -> List<com.example.thanna.data.BookingLite> = { emptyList() },
    modifier: Modifier = Modifier,
) {
    val draft = remember { CreateMatchDraft() }
    var step by remember { mutableStateOf(0) }
    val lastStep = 3

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        WizardTopBar(
            step = step,
            total = lastStep + 1,
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
                0 -> StepType(draft)
                1 -> StepRules(draft, loadBookings)
                2 -> StepTeams(draft, lookupPlayer)
                else -> StepReview(draft)
            }
        }

        WizardFooter(
            step = step,
            lastStep = lastStep,
            canContinue = canAdvance(draft, step),
            onContinue = {
                if (step == lastStep) onCreate(draft) else step++
            },
        )
    }
}

private fun canAdvance(d: CreateMatchDraft, step: Int): Boolean = when (step) {
    // Area/Village is mandatory for public matches (they feed a local-first list).
    // Private matches are hidden from feeds, so it's optional there.
    1 -> d.overs > 0 && d.playersPerSide > 0 && (d.isPrivate || d.locality.trim().length >= 2)
    2 -> d.teamA.isNotBlank() && d.teamB.isNotBlank()
    else -> true
}

// ─────────────────────────────────────────────────────────────── Top bar ──────
@Composable
private fun WizardTopBar(step: Int, total: Int, onBack: () -> Unit, onClose: () -> Unit) {
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
                Text("Step ${step + 1} of $total", color = Text3, fontSize = 12.sp)
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
            .clip(CircleShape)
            .background(Color(0xFFF1F5F9))
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, cd, tint = Text1, modifier = Modifier.size(18.dp))
    }
}

// ─────────────────────────────────────────────────────────────── Footer ────────
@Composable
private fun WizardFooter(step: Int, lastStep: Int, canContinue: Boolean, onContinue: () -> Unit) {
    Box(
        Modifier
            .fillMaxWidth()
            .background(Surface)
            // Keep the white footer flush to the screen edge, but lift the button above the
            // system nav bar (and the keyboard on text-entry steps) so it isn't overlapped.
            .navigationBarsPadding()
            .imePadding()
            .padding(16.dp)
    ) {
        // Blue carries the user forward through the steps; green commits the match at
        // the end — a deliberate blue→green hand-off so the final action reads as "go".
        val isCommit = step == lastStep
        val accent = if (isCommit) Green else Blue
        Button(
            onClick = onContinue,
            enabled = canContinue,
            modifier = Modifier
                .fillMaxWidth()
                .height(52.dp),
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = accent,
                contentColor = Color.White,
                disabledContainerColor = accent.copy(alpha = 0.35f),
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
            "This sets how much it's worth. Tournament games rank higher than gully games.",
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
            .clip(RoundedCornerShape(11.dp))
            .background(if (selected) Blue else Color.Transparent)
            .clickable(onClick = onClick)
            .padding(vertical = 12.dp, horizontal = 12.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, color = if (selected) Color.White else Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Spacer(Modifier.height(2.dp))
        Text(sub, color = if (selected) Color.White.copy(alpha = 0.85f) else Text3, fontSize = 12.sp, maxLines = 1)
    }
}

@Composable
private fun MatchTypeCard(type: MatchType, selected: Boolean, onClick: () -> Unit, locked: Boolean = false) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (selected) BlueTint else Surface)
            .border(
                BorderStroke(if (selected) 1.5.dp else 1.dp, if (selected) Blue else Stroke),
                RoundedCornerShape(16.dp),
            )
            .clickable(onClick = onClick)
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
            Icon(type.icon, null, tint = if (selected) Blue else Text2, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f).alpha(if (locked) 0.55f else 1f)) {
            Text(type.label, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(type.tagline, color = Text2, fontSize = 13.sp, maxLines = 1)
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
private fun StepRules(draft: CreateMatchDraft, loadBookings: suspend () -> List<com.example.thanna.data.BookingLite>) {
    StepScaffold(
        title = "Format & rules",
        subtitle = "How long, what ball, how many a side.",
    ) {
        FieldLabel("Overs per side")
        Stepper(
            value = draft.overs,
            onChange = { draft.overs = it.coerceIn(1, 50) },
            min = 1, max = 50,
            suffix = "ov",
        )
        Spacer(Modifier.height(20.dp))

        FieldLabel("Ball type")
        ChipRow(
            options = BallType.entries.toList(),
            selected = draft.ball,
            label = { it.label },
            onSelect = { draft.ball = it },
        )
        Spacer(Modifier.height(20.dp))

        FieldLabel("Players per side")
        Stepper(
            value = draft.playersPerSide,
            onChange = { draft.playersPerSide = it.coerceIn(2, 15) },
            min = 2, max = 15,
        )
        Spacer(Modifier.height(20.dp))

        // One combined place field — venue/ground OR village/area. Type it manually or
        // share your location. Drives both the venue label and the district-find locality.
        FieldLabel("Venue / Area / Village *")
        var placeTouched by remember { mutableStateOf(false) }
        WizardTextField(
            value = draft.locality,
            onChange = { draft.locality = it; draft.venue = it },
            placeholder = "Ground, village or area",
            modifier = Modifier.onFocusChanged { if (it.isFocused) placeTouched = true },
        )
        Spacer(Modifier.height(6.dp))
        val localityInvalid = placeTouched && draft.locality.trim().length < 2
        Text(
            text = if (localityInvalid)
                "Required — so locals can find this match in their district."
            else
                "Type a ground/area, or share your location so locals can find it.",
            color = if (localityInvalid) Color(0xFFDC2626) else Text3,
            fontSize = 12.sp,
            lineHeight = 16.sp,
        )
        Spacer(Modifier.height(8.dp))
        UseCurrentLocationButton(
            label = "Share my location",
            resolver = ::resolveCurrentLocality,
            onResolved = { draft.locality = it; draft.venue = it },
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

// Lists the creator's recent CONFIRMED turf bookings so they can attach the one this
// match was played on. The chosen booking id rides along on create; the backend
// validates it and auto-verifies the result against it.
@Composable
private fun TurfBookingPicker(
    loadBookings: suspend () -> List<com.example.thanna.data.BookingLite>,
    selectedId: Long?,
    onSelect: (Long?) -> Unit,
) {
    var loading by remember { mutableStateOf(true) }
    var bookings by remember { mutableStateOf<List<com.example.thanna.data.BookingLite>>(emptyList()) }

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
                        .clip(RoundedCornerShape(12.dp))
                        .background(Surface)
                        .border(1.5.dp, if (sel) Green else Stroke, RoundedCornerShape(12.dp))
                        .clickable { onSelect(if (sel) null else b.id) }
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
private fun StepTeams(draft: CreateMatchDraft, lookupPlayer: suspend (String) -> PlayerLite?) {
    StepScaffold(
        title = "Teams & squads",
        subtitle = "Name both sides. Add players by their Player ID.",
    ) {
        TeamBlock(
            heading = "Team A",
            name = draft.teamA,
            onName = { draft.teamA = it },
            emblemIndex = draft.teamAEmblem,
            onEmblem = { draft.teamAEmblem = it },
            photoUri = draft.teamAPhoto,
            onPhoto = { draft.teamAPhoto = it },
            squad = draft.squadA,
            limit = draft.playersPerSide,
            lookupPlayer = lookupPlayer,
        )
        Spacer(Modifier.height(20.dp))
        TeamBlock(
            heading = "Team B",
            name = draft.teamB,
            onName = { draft.teamB = it },
            emblemIndex = draft.teamBEmblem,
            onEmblem = { draft.teamBEmblem = it },
            photoUri = draft.teamBPhoto,
            onPhoto = { draft.teamBPhoto = it },
            squad = draft.squadB,
            limit = draft.playersPerSide,
            lookupPlayer = lookupPlayer,
        )
        Spacer(Modifier.height(16.dp))
        ImpactNote(
            "Registered players earn XP",
            "Add players by their Haraan Player ID. A match only counts for Ranked XP " +
                "with enough distinct registered players on each side.",
        )
    }
}

/** Lookup state for the add-player field. */
private sealed interface LookupState {
    data object Idle : LookupState
    data object Searching : LookupState
    data class Found(val player: PlayerLite) : LookupState
    data object NotFound : LookupState
    data object AlreadyAdded : LookupState
}

@Composable
private fun TeamBlock(
    heading: String,
    name: String,
    onName: (String) -> Unit,
    emblemIndex: Int,
    onEmblem: (Int) -> Unit,
    photoUri: android.net.Uri?,
    onPhoto: (android.net.Uri?) -> Unit,
    squad: androidx.compose.runtime.snapshots.SnapshotStateList<SquadMember>,
    limit: Int,
    lookupPlayer: suspend (String) -> PlayerLite?,
) {
    var entry by remember { mutableStateOf("") }
    var status by remember { mutableStateOf<LookupState>(LookupState.Idle) }
    var guestName by remember { mutableStateOf("") }
    var showGuest by remember { mutableStateOf(false) }

    // Debounced live lookup as the user types a Player ID.
    LaunchedEffect(entry) {
        val id = entry.trim()
        if (id.isEmpty()) {
            status = LookupState.Idle
            return@LaunchedEffect
        }
        status = LookupState.Searching
        delay(400)
        val player = lookupPlayer(id)
        status = when {
            player == null -> LookupState.NotFound
            squad.any { it.id == player.playerId } -> LookupState.AlreadyAdded
            else -> LookupState.Found(player)
        }
    }

    val found = status as? LookupState.Found
    val canAdd = found != null && squad.size < limit

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
        WizardTextField(value = name, onChange = onName, placeholder = "Team name")
        Spacer(Modifier.height(12.dp))

        TeamIconPicker(
            emblemIndex = emblemIndex,
            photoUri = photoUri,
            onEmblem = onEmblem,
            onPhoto = onPhoto,
        )
        Spacer(Modifier.height(12.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.weight(1f)) {
                WizardTextField(
                    value = entry,
                    onChange = { entry = it },
                    placeholder = "Enter Player ID (${squad.size}/$limit)",
                )
            }
            Spacer(Modifier.width(8.dp))
            Box(
                Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (canAdd) Green else Stroke)
                    .clickable(enabled = canAdd) {
                        found?.let { squad.add(SquadMember(it.player.playerId, it.player.name)) }
                        entry = ""
                    },
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Default.Add, "Add player", tint = Color.White, modifier = Modifier.size(20.dp))
            }
        }

        LookupStatusRow(status, atLimit = squad.size >= limit)

        // Guest add — casual players without a Haraan account (no XP, just fill the side).
        val canAddGuest = guestName.trim().length >= 2 && squad.size < limit
        if (!showGuest) {
            Spacer(Modifier.height(10.dp))
            Text(
                "+ Add guest",
                color = Blue,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.clickable { showGuest = true },
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
                        .clip(RoundedCornerShape(12.dp))
                        .background(if (canAddGuest) Green else Stroke)
                        .clickable(enabled = canAddGuest) {
                            squad.add(SquadMember(id = "", name = guestName.trim(), isGuest = true))
                            guestName = ""
                            showGuest = false
                        },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Default.Add, "Add guest", tint = Color.White, modifier = Modifier.size(20.dp))
                }
            }
            Spacer(Modifier.height(4.dp))
            Text("Guests don't earn XP — they just fill the side.", color = Text3, fontSize = 11.sp)
        }

        squad.forEachIndexed { i, player ->
            Spacer(Modifier.height(8.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(10.dp))
                    .background(Bg)
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("${i + 1}", color = Text3, fontSize = 13.sp, modifier = Modifier.width(22.dp))
                Column(Modifier.weight(1f)) {
                    Text(player.name, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    Text(if (player.isGuest) "Guest player" else player.id, color = Text3, fontSize = 11.sp)
                }
                if (player.isGuest) {
                    Box(
                        Modifier.clip(RoundedCornerShape(6.dp)).background(Stroke).padding(horizontal = 7.dp, vertical = 2.dp),
                    ) {
                        Text("GUEST", color = Text2, fontSize = 8.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                    }
                    Spacer(Modifier.width(10.dp))
                }
                Icon(
                    Icons.Default.Close, "Remove",
                    tint = Text3,
                    modifier = Modifier
                        .size(18.dp)
                        .clickable { squad.removeAt(i) },
                )
            }
        }
    }
}

@Composable
private fun LookupStatusRow(status: LookupState, atLimit: Boolean) {
    val content: (@Composable () -> Unit)? = when (status) {
        is LookupState.Idle -> null
        is LookupState.Searching -> ({
            Row(verticalAlignment = Alignment.CenterVertically) {
                CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = Text3)
                Spacer(Modifier.width(8.dp))
                Text("Checking…", color = Text3, fontSize = 13.sp)
            }
        })
        is LookupState.Found -> ({
            val p = status.player
            val sub = if (p.district.isNullOrBlank()) p.name else "${p.name} · ${p.district}"
            Text(
                if (atLimit) "Squad full — remove a player first" else "✓ $sub",
                color = if (atLimit) Text3 else Green,
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
            )
        })
        is LookupState.NotFound -> ({
            Text("No player with that ID", color = Text2, fontSize = 13.sp)
        })
        is LookupState.AlreadyAdded -> ({
            Text("Already added to this team", color = Text3, fontSize = 13.sp)
        })
    }
    if (content != null) {
        Spacer(Modifier.height(8.dp))
        content()
    }
}

// Team icon chooser — a live preview, a scrollable row of default emblems, and an
// "upload" tile that opens the system photo picker for a custom image. A chosen photo
// wins over the emblem; tapping an emblem clears the photo.
@Composable
private fun TeamIconPicker(
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
            Text("Team icon", color = Text1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
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
                    .clip(CircleShape)
                    .border(BorderStroke(if (selected) 2.5.dp else 0.dp, Blue), CircleShape)
                    .clickable { onPhoto(null); onEmblem(i) },
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

// ────────────────────────────────────────────────────── Step 4 · Review ────────
@Composable
private fun StepReview(draft: CreateMatchDraft) {
    StepScaffold(
        title = "Review & create",
        subtitle = "One last look before it goes live.",
    ) {
        SummaryCard(draft)
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
                "${draft.teamA.ifBlank { "Team A" }}  vs  ${draft.teamB.ifBlank { "Team B" }}",
                color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold,
                modifier = Modifier.weight(1f),
            )
            TeamIconPreview(draft.teamBEmblem, draft.teamBPhoto, size = 28.dp)
            Spacer(Modifier.width(8.dp))
            if (!draft.isPrivate) XpBadge(draft.type.baseXp)
        }
        Spacer(Modifier.height(14.dp))
        SummaryRow("Mode", if (draft.isPrivate) "Private" else "Public")
        SummaryRow("Type", draft.type.label)
        SummaryRow("Format", "${draft.overs} overs · ${draft.ball.label} ball")
        SummaryRow("Per side", "${draft.playersPerSide} players")
        SummaryRow("Venue", draft.venue.ifBlank { "—" } + if (draft.onHaraanTurf) "  · Haraan turf" else "")
        SummaryRow("Squads", "${draft.squadA.size} + ${draft.squadB.size} added")
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
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (isSel) Blue else Surface)
                    .border(1.dp, if (isSel) Blue else Stroke, RoundedCornerShape(12.dp))
                    .clickable { onSelect(opt) }
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
private fun Stepper(value: Int, onChange: (Int) -> Unit, min: Int, max: Int, suffix: String = "") {
    Row(
        Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(12.dp)),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StepperZone("−", enabled = value > min) { onChange(value - 1) }
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
        StepperZone("+", enabled = value < max) { onChange(value + 1) }
    }
}

@Composable
private fun StepperZone(symbol: String, enabled: Boolean, onClick: () -> Unit) {
    Box(
        Modifier
            .size(46.dp)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            symbol,
            color = if (enabled) Text1 else Text3.copy(alpha = 0.4f),
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
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, if (checked) Green else Stroke, RoundedCornerShape(14.dp))
            .clickable { onToggle(!checked) }
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
