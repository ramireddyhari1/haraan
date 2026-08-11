package com.haraan.app.ui.profile

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material.icons.filled.AddAPhoto
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.GridOn
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedTextField
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.material3.Icon
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke as DrawStroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.material.icons.filled.Check
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import android.widget.Toast
import androidx.compose.runtime.rememberCoroutineScope
import com.haraan.app.data.AccountStore
import com.haraan.app.data.PlayerPost
import com.haraan.app.data.PlayerRepository
import com.haraan.app.data.ProfileRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.HaraanImage
import com.haraan.app.ui.pressable
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.tween
import androidx.compose.material.icons.filled.MilitaryTech
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.filled.Whatshot
import androidx.compose.material.icons.filled.WorkspacePremium
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.graphics.layer.GraphicsLayer
import androidx.compose.ui.graphics.layer.drawLayer
import androidx.compose.ui.graphics.rememberGraphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.FileProvider
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import kotlinx.coroutines.launch
import com.haraan.app.data.PlayerProfile
import com.haraan.app.data.RecentMatch
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow
import androidx.compose.ui.draw.shadow

// ─── Palette ─────────────────────────────────────────────────────────────────
// These are ALIASES onto the design system, not a private palette. This file used to
// define twelve of its own hexes — including a `Green = 0xFF00B140` that directly
// contradicted a decision already recorded in HaraanColors ("green is for actions
// only"; GameHubGreen was deliberately made blue). Local names are kept because they
// are referenced ~200 times here; only their SOURCE changed.
private val Bg        = HaraanColors.Background
private val Surface   = HaraanColors.Surface
private val Blue      = HaraanColors.GameHubDeep
private val BlueBright= HaraanColors.EventsBlue
/** Success — a win, a verification. No longer the ring, the tier pill or a gradient stop. */
private val Green     = HaraanColors.Success
private val GreenTint = HaraanColors.SuccessTint
private val BlueTint  = HaraanColors.AccentTint
private val Text1     = HaraanColors.TextPrimary
private val Text2     = HaraanColors.TextSecondary
private val Text3     = HaraanColors.TextMuted
private val Stroke    = HaraanColors.BorderLight
// Achievement metals stay local: they are medal materials, not brand colours, and
// nothing outside this screen uses them.
private val Bronze    = Color(0xFFCD7F32)
private val Silver    = Color(0xFF8E99A8)
private val Gold      = Color(0xFFB8860B)
private val GoldTint  = Color(0xFFFAF3E0)

/**
 * ONE hue, top to bottom. Was `linearGradient(Navy, Blue, Green)` — a gradient
 * travelling across the colour wheel, which is the most reliable tell of a stock
 * template. Both stops come from the system, so going near-black is a one-value edit
 * in [HaraanColors.HeroSurface] rather than a change here.
 */
private val HeroGradient = Brush.verticalGradient(
    listOf(HaraanColors.HeroSurfaceTop, HaraanColors.HeroSurface),
)

// Level, tier, profile-completion, win streak and recognition chips are all DERIVED from
// real profile data (XP, filled fields, recent results). Only [achievements] remain a
// static list — real milestone tracking is a later batch.
private data class PlayerExtras(
    val level: Int,
    val tier: String,
    val profilePct: Int,
    val profileSteps: List<String>,
    val role: String,
    val streakWins: Int,
    val chips: List<RepChip>,
    val achievements: List<Achievement>,
)

private enum class BadgeTier(val label: String, val color: Color) {
    BRONZE("Bronze", Bronze), SILVER("Silver", Silver), GOLD("Gold", Gold)
}

private data class RepChip(val icon: ImageVector, val label: String, val green: Boolean)
private data class Achievement(val icon: ImageVector, val label: String, val tier: BadgeTier, val unlocked: Boolean, val progress: String? = null)

/** Backend sends a stable icon key + tier string; map them to Compose icons / tiers. */
private fun achievementIcon(key: String): ImageVector = when (key) {
    "SportsCricket" -> Icons.Default.SportsCricket
    "EmojiEvents" -> Icons.Default.EmojiEvents
    "Star" -> Icons.Default.Star
    "WorkspacePremium" -> Icons.Default.WorkspacePremium
    "MilitaryTech" -> Icons.Default.MilitaryTech
    "Whatshot" -> Icons.Default.Whatshot
    "Shield" -> Icons.Default.Shield
    "TrendingUp" -> Icons.Default.TrendingUp
    "Verified" -> Icons.Default.Verified
    else -> Icons.Default.EmojiEvents
}

private fun achievementTier(tier: String): BadgeTier = when (tier.lowercase()) {
    "gold" -> BadgeTier.GOLD
    "silver" -> BadgeTier.SILVER
    else -> BadgeTier.BRONZE
}

/**
 * Wording for a missing-field key. The server names the FACT ("foot"); the sentence
 * lives here, so a label can be reworded without a backend deploy — same split as
 * the per-sport career cells.
 */
private fun completionStepLabel(key: String): String = when (key) {
    "avatar" -> "Add photo"
    "state" -> "Set state"
    "district" -> "Set district"
    // Cricket
    "role" -> "Set role"
    "batting" -> "Batting style"
    "bowling" -> "Bowling style"
    // Football
    "position" -> "Set position"
    "foot" -> "Strong foot"
    // Badminton
    "format" -> "Singles or doubles"
    "hand" -> "Playing hand"
    // A key this build has no wording for is better shown readably than dropped.
    else -> key.replaceFirstChar { it.uppercase() }
}

/** Derive the gamified layer from REAL profile fields — no invented numbers. */
private fun deriveExtras(p: PlayerProfile): PlayerExtras {
    val xp = p.rankedXp
    val level = 1 + xp / 250
    val tier = when {
        xp >= 5000 -> "Elite"
        xp >= 2000 -> "Pro"
        xp >= 750 -> "Rising Player"
        xp >= 200 -> "Prospect"
        else -> "Rookie"
    }
    // Completion comes from the server, which knows what each SPORT requires. This used
    // to be worked out here against cricket's fields — so a footballer was asked for a
    // "Batting style" they cannot set, and capped at 37% however complete they were.
    // The fallback is the old cricket count, for a server too old to send the block.
    val profilePct = p.completion?.pct ?: run {
        val fields = listOf(p.avatar, p.district, p.state, p.playerRole, p.battingStyle, p.bowlingStyle, p.gender, p.dateOfBirth)
        fields.count { !it.isNullOrBlank() } * 100 / fields.size
    }
    val steps = p.completion?.missing?.map(::completionStepLabel)
        ?: buildList {
            if (p.avatar.isNullOrBlank()) add("Add photo")
            if (p.playerRole.isNullOrBlank()) add("Set role")
            if (p.recentMatches.isEmpty()) add("Play a match")
        }
    // Current win streak = leading run of wins in the (newest-first) recent list.
    val streakWins = p.recentMatches.takeWhile { it.won }.count()
    val chips = buildList {
        if (p.trustScore >= 80) add(RepChip(Icons.Default.Verified, "Verified", green = true))
        if (p.isOrganizer) add(RepChip(Icons.Default.WorkspacePremium, "Organizer", green = false))
        p.rankDistrict?.let { r -> if (r <= 100) add(RepChip(Icons.Default.TrendingUp, "Top $r ${p.district ?: "District"}", green = true)) }
        if (streakWins >= 2) add(RepChip(Icons.Default.Whatshot, "$streakWins-win streak", green = false))
    }
    val achievements = p.achievements.map {
        Achievement(achievementIcon(it.icon), it.label, achievementTier(it.tier), it.unlocked, it.progress)
    }
    return PlayerExtras(level, tier, profilePct, steps, p.playerRole ?: "Cricketer", streakWins, chips, achievements)
}

// ─────────────────────────────────────────────────────────── Action menu ───────
private sealed interface MenuHeaderState {
    data object Loading : MenuHeaderState
    data object Error : MenuHeaderState
    data class Loaded(val profile: PlayerProfile) : MenuHeaderState
}

@Composable
fun ActionMenuScreen(
    onClose: () -> Unit,
    onProfile: () -> Unit,
    onLeaderboards: () -> Unit,
    onSettings: () -> Unit,
    onSignOut: () -> Unit,
    fetchProfile: suspend () -> PlayerProfile,
    modifier: Modifier = Modifier,
) {
    var header by remember { mutableStateOf<MenuHeaderState>(MenuHeaderState.Loading) }
    var confirmSignOut by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        header = try {
            MenuHeaderState.Loaded(fetchProfile())
        } catch (_: Exception) {
            MenuHeaderState.Error
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        TopBar(title = "Menu", leadingIcon = Icons.Default.Close, onLeading = onClose)

        Column(
            Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(16.dp)
        ) {
            MenuIdentityCard(header, onProfile)

            Spacer(Modifier.height(20.dp))
            MenuSectionLabel("Play")
            Spacer(Modifier.height(10.dp))
            MenuItem(Icons.Default.EmojiEvents, "Leaderboards", "District · State · India", onLeaderboards)
            Spacer(Modifier.height(12.dp))
            MenuItem(Icons.Default.Person, "My Profile", "ID, XP, trust & stats", onProfile)

            Spacer(Modifier.height(20.dp))
            MenuSectionLabel("App")
            Spacer(Modifier.height(10.dp))
            MenuItem(Icons.Default.Settings, "Settings", "Notifications & preferences", onSettings)

            Spacer(Modifier.height(20.dp))
            MenuItem(Icons.AutoMirrored.Filled.ExitToApp, "Sign out", null, { confirmSignOut = true }, danger = true)

            Spacer(Modifier.height(28.dp))
            MenuFooter()
        }
    }

    if (confirmSignOut) {
        AlertDialog(
            onDismissRequest = { confirmSignOut = false },
            confirmButton = {
                TextButton(onClick = { confirmSignOut = false; onSignOut() }) {
                    Text("Sign out", color = Color(0xFFD23F57), fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmSignOut = false }) {
                    Text("Cancel", color = Text2)
                }
            },
            title = { Text("Sign out?", color = Text1, fontWeight = FontWeight.Bold) },
            text = { Text("You'll need to sign in again to create or verify matches.", color = Text2, fontSize = 14.sp) },
            containerColor = Surface,
        )
    }
}

@Composable
private fun MenuIdentityCard(state: MenuHeaderState, onProfile: () -> Unit) {
    val clipboard = LocalClipboardManager.current
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(HeroGradient)
            .clickable(enabled = state is MenuHeaderState.Loaded, onClick = onProfile)
            .padding(18.dp)
    ) {
        when (state) {
            MenuHeaderState.Loading -> Row(verticalAlignment = Alignment.CenterVertically) {
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(22.dp))
                Spacer(Modifier.width(14.dp))
                Text("Loading your profile…", color = Color.White.copy(alpha = 0.85f), fontSize = 14.sp)
            }
            MenuHeaderState.Error -> Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(52.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.16f)),
                    contentAlignment = Alignment.Center,
                ) { Text("?", color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.Bold) }
                Spacer(Modifier.width(14.dp))
                Column {
                    Text("Player", color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    Text("Tap My Profile to sign in", color = Color.White.copy(alpha = 0.8f), fontSize = 12.5.sp)
                }
            }
            is MenuHeaderState.Loaded -> {
                val p = state.profile
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier.size(56.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.16f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            p.name.take(1).uppercase().ifBlank { "?" },
                            color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.Bold,
                        )
                    }
                    Spacer(Modifier.width(14.dp))
                    Column(Modifier.weight(1f)) {
                        Text(p.name.ifBlank { "Player" }, color = Color.White, fontSize = 19.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.clickable { clipboard.setText(AnnotatedString(p.playerId)) },
                        ) {
                            Text(p.playerId, color = Color.White.copy(alpha = 0.85f), fontSize = 12.5.sp, maxLines = 1)
                            Spacer(Modifier.width(5.dp))
                            Icon(Icons.Default.ContentCopy, "Copy ID", tint = Color.White.copy(alpha = 0.85f), modifier = Modifier.size(13.dp))
                        }
                    }
                    Icon(Icons.Default.KeyboardArrowRight, null, tint = Color.White.copy(alpha = 0.7f), modifier = Modifier.size(22.dp))
                }
                Spacer(Modifier.height(14.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    HeaderStat("XP", p.rankedXp.toString(), Modifier.weight(1f))
                    HeaderStat("Trust", p.trustScore.toString(), Modifier.weight(1f))
                    HeaderStat("District", p.rankDistrict?.let { "#$it" } ?: "—", Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun HeaderStat(label: String, value: String, modifier: Modifier = Modifier) {
    Column(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .background(Color.White.copy(alpha = 0.12f))
            .padding(vertical = 10.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(value, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Text(label, color = Color.White.copy(alpha = 0.75f), fontSize = 11.sp)
    }
}

@Composable
private fun MenuSectionLabel(text: String) {
    Text(
        text.uppercase(),
        color = Text3,
        fontSize = 11.5.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 0.8.sp,
        modifier = Modifier.padding(start = 4.dp),
    )
}

@Composable
private fun MenuFooter() {
    Column(
        Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("ActionBoard", color = Text3, fontSize = 13.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        Spacer(Modifier.height(2.dp))
        Text("Version 1.0", color = Text3, fontSize = 11.sp)
    }
}

@Composable
private fun MenuItem(
    icon: ImageVector,
    title: String,
    sub: String?,
    onClick: () -> Unit,
    danger: Boolean = false,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(11.dp))
                .background(if (danger) Color(0xFFFDECEF) else BlueTint),
            contentAlignment = Alignment.Center,
        ) {
            Icon(icon, null, tint = if (danger) Color(0xFFD23F57) else BlueBright, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(title, color = if (danger) Color(0xFFD23F57) else Text1, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            if (sub != null) {
                Text(sub, color = Text3, fontSize = 12.5.sp)
            }
        }
        if (!danger) {
            Icon(Icons.Default.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(22.dp))
        }
    }
}

// ─────────────────────────────────────────────────────────── Settings ───────────
@Composable
fun SettingsScreen(
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var matchAlerts by remember { mutableStateOf(true) }
    var leaderboardAlerts by remember { mutableStateOf(true) }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        TopBar(title = "Settings", leadingIcon = Icons.AutoMirrored.Filled.ArrowBack, onLeading = onBack)

        Column(
            Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(16.dp)
        ) {
            MenuSectionLabel("Notifications")
            Spacer(Modifier.height(10.dp))
            SettingsToggleRow("Match alerts", "Live score & verification updates", matchAlerts) { matchAlerts = it }
            Spacer(Modifier.height(12.dp))
            SettingsToggleRow("Leaderboard alerts", "When your rank changes", leaderboardAlerts) { leaderboardAlerts = it }

            Spacer(Modifier.height(22.dp))
            MenuSectionLabel("About")
            Spacer(Modifier.height(10.dp))
            SettingsInfoRow("Version", "1.0")
            Spacer(Modifier.height(12.dp))
            SettingsInfoRow("Help & support", "hariharanram56@gmail.com")

            Spacer(Modifier.height(28.dp))
            MenuFooter()
        }
    }
}

@Composable
private fun SettingsToggleRow(title: String, sub: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            Text(sub, color = Text3, fontSize = 12.5.sp)
        }
        Switch(
            checked = checked,
            onCheckedChange = onChange,
            colors = SwitchDefaults.colors(
                checkedThumbColor = Color.White,
                checkedTrackColor = BlueBright,
                uncheckedTrackColor = Stroke,
            ),
        )
    }
}

@Composable
private fun SettingsInfoRow(title: String, value: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        Text(value, color = Text3, fontSize = 13.sp)
    }
}

// ─────────────────────────────────────────────────────────── Profile ────────────
private sealed interface ProfileState {
    data object Loading : ProfileState
    data class Error(val message: String) : ProfileState
    data class Loaded(val profile: PlayerProfile) : ProfileState
}

@Composable
fun PlayerProfileScreen(
    onBack: () -> Unit,
    fetchProfile: suspend () -> PlayerProfile,
    modifier: Modifier = Modifier,
    /**
     * False when the profile is a bottom-bar TAB (the bar carries navigation, so the
     * "Player Profile" app bar and its back arrow are redundant). True for a pushed
     * screen — another player opened from search or a leaderboard — which owns its back.
     */
    showTopBar: Boolean = true,
    /**
     * Opens the create-match flow from the empty state. Null when this is somebody
     * ELSE's profile — which also switches the empty-state copy, since you cannot
     * play another player's first match for them.
     */
    /** True when this is the signed-in player's own profile. */
    isSelf: Boolean = false,
    onCreateMatch: (() -> Unit)? = null,
    /**
     * Follow/unfollow this player, returning the state the SERVER settled on (null =
     * the call failed, so the optimistic toggle rolls back). Null callback means the
     * caller cannot act on the follow graph and the button is not offered.
     */
    onToggleFollow: (suspend (Boolean) -> Boolean?)? = null,
    /** Open the follower / following lists. Null leaves the counts inert. */
    onOpenFollowers: ((playerId: String, name: String) -> Unit)? = null,
    onOpenFollowing: ((playerId: String, name: String) -> Unit)? = null,
    /** Open a DM with this player. Null hides the button entirely. */
    onMessage: ((playerId: String, name: String) -> Unit)? = null,
    /**
     * Opens the account switcher from the "@handle ⌄" chip above your own profile. Null
     * on anyone else's profile — there is no account to switch to from there — which is
     * what keeps the chip off other players' screens.
     */
    onOpenAccounts: (() -> Unit)? = null,
    /**
     * Told the profile that just loaded, so the caller can adopt a pre-existing session
     * into the account roster. Fires on every load; the caller makes it idempotent.
     */
    onProfileLoaded: ((PlayerProfile) -> Unit)? = null,
) {
    var state by remember { mutableStateOf<ProfileState>(ProfileState.Loading) }
    var reloadKey by remember { mutableStateOf(0) }
    var refreshing by remember { mutableStateOf(false) }

    LaunchedEffect(reloadKey) {
        // Only blank to a spinner on the FIRST load. A pull-to-refresh keeps the
        // profile on screen and lets the refresh indicator do the talking —
        // otherwise pulling down throws away the thing you were looking at.
        if (state !is ProfileState.Loaded) state = ProfileState.Loading
        state = try {
            val loaded = fetchProfile()
            onProfileLoaded?.invoke(loaded)
            ProfileState.Loaded(loaded)
        } catch (e: Exception) {
            ProfileState.Error(e.message ?: "Unable to load profile.")
        }
        refreshing = false
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            // Flat white, Instagram-style. Content cards carry their own hairline border,
            // so they still read on white.
            .background(Surface)
    ) {
        if (showTopBar) {
            TopBar(title = "Player Profile", leadingIcon = Icons.AutoMirrored.Filled.ArrowBack, onLeading = onBack)
        }

        when (val s = state) {
            is ProfileState.Loading -> CenterBox { CircularProgressIndicator(color = BlueBright) }
            is ProfileState.Error -> CenterBox {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(s.message, color = Text2, fontSize = 14.sp)
                    Spacer(Modifier.height(12.dp))
                    Box(
                        Modifier
                            .pressable { reloadKey++ }
                            .clip(RoundedCornerShape(10.dp))
                            .background(BlueBright)
                            .padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
                }
            }
            is ProfileState.Loaded -> ProfileContent(
                s.profile,
                deriveExtras(s.profile),
                onOpenAccounts = onOpenAccounts,
                refreshing = refreshing,
                onRefresh = { refreshing = true; reloadKey++ },
                isSelf = isSelf,
                onCreateMatch = onCreateMatch,
                onToggleFollow = onToggleFollow,
                onOpenFollowers = onOpenFollowers,
                onOpenFollowing = onOpenFollowing,
                onMessage = onMessage,
            )
        }
    }
}

@Composable
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class, ExperimentalFoundationApi::class)
private fun ProfileContent(
    p: PlayerProfile,
    e: PlayerExtras,
    // A rank and an XP total change while you are looking at them. Until now the only
    // way to re-fetch was to force an error and hit Retry. Both hoisted to the caller,
    // which owns the fetch and so is the only thing that knows when it finished.
    onOpenAccounts: (() -> Unit)? = null,
    refreshing: Boolean,
    onRefresh: () -> Unit,
    isSelf: Boolean = false,
    onCreateMatch: (() -> Unit)? = null,
    onToggleFollow: (suspend (Boolean) -> Boolean?)? = null,
    onOpenFollowers: ((playerId: String, name: String) -> Unit)? = null,
    onOpenFollowing: ((playerId: String, name: String) -> Unit)? = null,
    onMessage: ((playerId: String, name: String) -> Unit)? = null,
) {
    val clipboard = LocalClipboardManager.current
    var showShare by remember { mutableStateOf(false) }
    var showEditProfile by remember { mutableStateOf(false) }
    // Which content tab is open: 0 = Matches, 1 = Stats, 2 = About, 3 = Posts.
    var selectedTab by remember { mutableStateOf(0) }
    val view = LocalView.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // Drives whether the chip gets a chevron. Keyed on the player so it recomputes after
    // a switch lands, when this composable is showing a different account.
    val accountCount = remember(p.playerId) { AccountStore.accounts(context).size }

    // ── Photo posts ──────────────────────────────────────────────────────────
    // null = not loaded yet (or the fetch failed); an empty list = genuinely no posts.
    // Collapsing the two would turn a network blip into "No posts yet", which invites
    // someone to re-upload a photo that is already there.
    var posts by remember(p.playerId) { mutableStateOf<List<PlayerPost>?>(null) }
    var postsFailed by remember(p.playerId) { mutableStateOf(false) }
    var uploadingPost by remember { mutableStateOf(false) }
    var pendingDelete by remember { mutableStateOf<PlayerPost?>(null) }
    // Picked image(s) awaiting the compose screen (review + caption), so a pick no longer
    // uploads blind.
    var pendingPostUris by remember { mutableStateOf<List<Uri>>(emptyList()) }

    suspend fun loadPosts() {
        // Token is optional — the grid is public — but sending ours is what makes the
        // server mark our own posts `mine`, which is what shows the delete affordance.
        val fetched = PlayerRepository().posts(TokenStore.getToken(context), p.playerId)
        postsFailed = fetched == null
        if (fetched != null) posts = fetched
    }

    LaunchedEffect(p.playerId) { loadPosts() }

    val postPicker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickMultipleVisualMedia(10),
    ) { uris ->
        // Open the compose screen (review + caption) instead of uploading on the spot.
        if (uris.isNotEmpty()) pendingPostUris = uris
    }
    androidx.compose.material3.pulltorefresh.PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = {
            view.performHapticFeedback(Feel.TICK)
            onRefresh()
        },
        modifier = Modifier.fillMaxSize(),
    ) {
    val played = hasAnyHistory(p)
    val about = aboutRows(p)
    // Career/rank/XP/recognition/achievements all live under the Stats tab.
    val hasCareer = careerCells(p).isNotEmpty()
    val statsHasAny = played || hasCareer || e.chips.isNotEmpty() || e.achievements.isNotEmpty()
    // Tabs only earn their place once there's something under them. A brand-new player
    // with no history and no details keeps the single purposeful first-match card.
    //
    // `isSelf` counts as content because Posts is a WRITE surface: without it, a player
    // with no matches yet has no route to their own photo grid and could never make the
    // first post. Their empty Matches tab still leads with the first-match card, so the
    // purposeful onboarding moment survives — it just lives one tab in.
    val showTabs = played || about.isNotEmpty() || e.achievements.isNotEmpty() ||
        e.chips.isNotEmpty() || !posts.isNullOrEmpty() || isSelf

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
    ) {
        // Your own handle, as the tappable account chip. Above the hero rather than
        // inside it: the hero describes the player, this switches WHICH player the app
        // is acting as, and merging the two invites a mis-tap on identity.
        if (onOpenAccounts != null) {
            item {
                AccountChip(
                    // Always show the chevron so it reads as an account switcher you can tap
                    // to add or switch accounts — even when there's only one signed in.
                    label = p.username?.let { "@$it" } ?: p.name,
                    showChevron = true,
                    onClick = onOpenAccounts,
                    modifier = Modifier.padding(bottom = 6.dp),
                )
            }
        }

        // Identity — photo, tier, level, trust+ID, and now the social counts, all in
        // one hero block (each fact appears once, only here).
        item {
            HeroCard(
                p, e,
                matches = matchesPlayed(p),
                followers = p.social?.followersCount ?: 0,
                following = p.social?.followingCount ?: 0,
                onCopyId = { clipboard.setText(AnnotatedString(p.playerId)) },
                onOpenFollowers = onOpenFollowers?.let { cb -> { cb(p.playerId, p.name) } },
                onOpenFollowing = onOpenFollowing?.let { cb -> { cb(p.playerId, p.name) } },
            )
        }

        // The action the identity implies: Follow / Message on someone else, Share on
        // your own. The counts that used to sit here moved up into the hero.
        item {
            Spacer(Modifier.height(12.dp))
            ProfileActions(
                p = p,
                onToggleFollow = onToggleFollow,
                onShare = { showShare = true },
                onMessage = onMessage,
                onEdit = if (isSelf) ({ showEditProfile = true }) else null,
            )
        }

        if (!showTabs) {
            // One purposeful card instead of a rank that says "Unranked", a stat strip
            // of zeros, an XP card at 0 and an empty match list.
            item { Spacer(Modifier.height(14.dp)); FirstMatchCard(p, isSelf, onCreateMatch) }
            item { Spacer(Modifier.height(16.dp)) }
            return@LazyColumn
        }

        // The hero + social block scroll away; the tab switcher pins to the top so the
        // section you're reading is always labelled — the Instagram-style profile.
        item { Spacer(Modifier.height(14.dp)) }
        stickyHeader { ProfileTabs(selectedTab) { selectedTab = it } }

        when (selectedTab) {
            // ── Matches ──────────────────────────────────────────────────────
            0 -> if (p.recentMatches.isEmpty()) {
                item {
                    // Self with nothing played gets the first-match card here rather than a
                    // flat "no matches" line — it is the same purposeful card the tab-less
                    // profile used to lead with, just relocated under its own tab.
                    if (isSelf && !played) {
                        Spacer(Modifier.height(14.dp)); FirstMatchCard(p, true, onCreateMatch)
                    } else {
                        TabEmpty(
                            "No matches yet",
                            if (isSelf) "Your settled matches will show up here." else "This player hasn't played a settled match yet.",
                        )
                    }
                }
            } else {
                item { Spacer(Modifier.height(6.dp)); SectionTitle("Recent form"); Spacer(Modifier.height(12.dp)); RecentForm(p.recentMatches) }
                item { Spacer(Modifier.height(20.dp)); SectionTitle("Match history"); Spacer(Modifier.height(12.dp)) }
                items(p.recentMatches.size) { i ->
                    RecentMatchRow(p.recentMatches[i])
                    Spacer(Modifier.height(8.dp))
                }
            }
            // ── Stats ────────────────────────────────────────────────────────
            1 -> if (!statsHasAny) {
                item { TabEmpty("No stats yet", "Play a match to start building a record.") }
            } else {
                if (played) item { Spacer(Modifier.height(6.dp)); DistrictRankCard(p) }
                if (hasCareer) item { Spacer(Modifier.height(16.dp)); SectionTitle("Career"); Spacer(Modifier.height(12.dp)); StatRow(p) }
                if (played) item { Spacer(Modifier.height(20.dp)); SectionTitle("Experience"); Spacer(Modifier.height(12.dp)); XpCard(p) }
                if (e.chips.isNotEmpty()) item { Spacer(Modifier.height(20.dp)); SectionTitle("Recognition"); Spacer(Modifier.height(12.dp)); ReputationChips(e.chips) }
                if (e.achievements.isNotEmpty()) {
                    item { Spacer(Modifier.height(20.dp)); SectionTitle("Achievements", "${e.achievements.count { it.unlocked }}/${e.achievements.size} unlocked"); Spacer(Modifier.height(12.dp)); Achievements(e.achievements) }
                }
            }
            // ── About ────────────────────────────────────────────────────────
            2 -> if (about.isEmpty()) {
                item {
                    TabEmpty(
                        "No details yet",
                        if (isSelf) "Add your playing role, batting and bowling style from Edit profile." else "This player hasn't added any details.",
                    )
                }
            } else {
                item { Spacer(Modifier.height(6.dp)); AboutCard(about) }
            }
            // ── Posts ────────────────────────────────────────────────────────
            else -> {
                val rows = posts.orEmpty().chunked(3)
                if (isSelf) {
                    item {
                        AddPostRow(uploading = uploadingPost) {
                            postPicker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
                        }
                    }
                }
                when {
                    posts == null && !postsFailed -> item {
                        Box(Modifier.fillMaxWidth().padding(vertical = 44.dp), Alignment.Center) {
                            CircularProgressIndicator(color = BlueBright, modifier = Modifier.size(26.dp))
                        }
                    }
                    // Say the fetch failed rather than showing an empty grid, which would
                    // read as "this player has never posted".
                    posts == null -> item {
                        TabEmpty("Couldn't load posts", "Check your connection and pull down to refresh.")
                    }
                    rows.isEmpty() -> item {
                        TabEmpty(
                            "No posts yet",
                            if (isSelf) "Share a photo from a match, a ground, or your kit." else "This player hasn't posted a photo yet.",
                        )
                    }
                }
                items(rows.size) { r ->
                    PostGridRow(
                        row = rows[r],
                        // Trust the server's answer, not a local id comparison — it is the
                        // same authority that will accept or reject the delete.
                        onDelete = { post -> if (post.mine) pendingDelete = post },
                    )
                    Spacer(Modifier.height(3.dp))
                }
            }
        }

        item { Spacer(Modifier.height(16.dp)) }
    }
    }

    if (showShare) ShareCardSheet(p, e, onDismiss = { showShare = false })
    if (showEditProfile) {
        EditProfileSheet(
            currentName = p.name,
            currentBio = p.bio,
            onDismiss = { showEditProfile = false },
            onSave = { newName, newBio ->
                val token = TokenStore.getToken(context)
                val ok = TokenStore.isSignedIn(token) &&
                    ProfileRepository().updateBasics(token!!, newName, newBio)
                if (ok) {
                    view.performHapticFeedback(Feel.COMMIT)
                    showEditProfile = false
                    onRefresh()
                }
                ok
            },
        )
    }

    // Deleting a photo is not undoable, so it asks first — and only ever offers on a post
    // the server told us is ours.
    pendingDelete?.let { target ->
        AlertDialog(
            onDismissRequest = { pendingDelete = null },
            title = { Text("Delete this post?", color = Text1, fontWeight = FontWeight.Bold) },
            text = { Text("The photo is removed from your profile. This can't be undone.", color = Text2, fontSize = 14.sp) },
            confirmButton = {
                TextButton(onClick = {
                    pendingDelete = null
                    scope.launch {
                        val token = TokenStore.getToken(context)
                        val ok = TokenStore.isSignedIn(token) &&
                            PlayerRepository().deletePost(token!!, target.id)
                        if (ok) {
                            posts = posts?.filterNot { it.id == target.id }
                            view.performHapticFeedback(Feel.TICK)
                        } else {
                            Toast.makeText(context, "Couldn't delete that post.", Toast.LENGTH_SHORT).show()
                        }
                    }
                }) { Text("Delete", color = HaraanColors.Danger, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { pendingDelete = null }) { Text("Cancel", color = Text2) }
            },
            containerColor = Surface,
        )
    }

    // Compose-a-post overlay: review + caption + Share for the picked image(s). Prepends the
    // created post to the grid so it appears instantly.
    if (pendingPostUris.isNotEmpty()) {
        com.haraan.app.ui.DismissOnBack(true) { pendingPostUris = emptyList() }
        com.haraan.app.ui.social.CreatePostScreen(
            imageUris = pendingPostUris,
            onClose = { pendingPostUris = emptyList() },
            onPosted = { created ->
                pendingPostUris = emptyList()
                posts = listOf(created) + (posts ?: emptyList())
                postsFailed = false
                view.performHapticFeedback(Feel.TICK)
            },
        )
    }
}

/**
 * The owner-only "Add photo" affordance above their grid. A bordered, self-width button
 * rather than a full-width block — the grid below is the subject, this is the tool.
 */
@Composable
private fun AddPostRow(uploading: Boolean, onPick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(top = 12.dp, bottom = 10.dp),
        horizontalArrangement = Arrangement.Start,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(
            Modifier
                .clip(RoundedCornerShape(10.dp))
                .then(if (uploading) Modifier else Modifier.pressable(haptic = Feel.SELECT) { onPick() })
                .background(if (uploading) Stroke else BlueBright)
                .padding(horizontal = 14.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (uploading) {
                CircularProgressIndicator(color = Text2, strokeWidth = 2.dp, modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(8.dp))
                Text("Posting…", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            } else {
                Icon(Icons.Filled.AddAPhoto, null, tint = Color.White, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(8.dp))
                Text("Add photo", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

/**
 * One row of the 3-up photo grid.
 *
 * Rows of three rather than a nested LazyVerticalGrid: this lives inside the profile's
 * LazyColumn, and a lazy grid inside a lazy column has no bounded height to measure
 * against. Short final rows are padded with empty weight so cells stay square and
 * left-aligned instead of stretching.
 */
@Composable
private fun PostGridRow(row: List<PlayerPost>, onDelete: (PlayerPost) -> Unit) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
        row.forEach { post ->
            Box(
                Modifier
                    .weight(1f)
                    .aspectRatio(1f)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Stroke)
                    .then(if (post.mine) Modifier.pressable(haptic = Feel.SELECT) { onDelete(post) } else Modifier),
            ) {
                HaraanImage(
                    model = ApiConfig.mediaUrl(post.image),
                    contentDescription = post.caption ?: "Post",
                    modifier = Modifier.fillMaxSize(),
                )
            }
        }
        // Keep the last row's cells the same size as a full row's.
        repeat(3 - row.size) { Spacer(Modifier.weight(1f)) }
    }
}

/**
 * The profile's content switcher — Matches / Stats / About / Posts as Instagram-style icon
 * tabs: an icon over a label, a hairline across the top, and an underline bar under the
 * active one. Opaque (white) so list content scrolls cleanly beneath it when pinned.
 */
@Composable
private fun ProfileTabs(selected: Int, onSelect: (Int) -> Unit) {
    val tabs = listOf(
        "Matches" to Icons.Filled.SportsCricket,
        "Stats" to Icons.Filled.BarChart,
        "About" to Icons.Filled.Person,
        "Posts" to Icons.Filled.GridOn,
    )
    Column(Modifier.fillMaxWidth().background(Surface)) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(Stroke))
        Row(Modifier.fillMaxWidth()) {
            tabs.forEachIndexed { i, (label, icon) ->
                val sel = i == selected
                val tint = if (sel) BlueBright else Text3
                Column(
                    Modifier.weight(1f).pressable(haptic = Feel.SELECT) { onSelect(i) }.padding(top = 10.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(icon, null, tint = tint, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.height(3.dp))
                    Text(label, color = tint, fontSize = 12.sp, fontWeight = if (sel) FontWeight.Bold else FontWeight.Medium)
                    Spacer(Modifier.height(8.dp))
                    Box(
                        Modifier.height(2.5.dp).width(30.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(if (sel) BlueBright else Color.Transparent),
                    )
                }
            }
        }
    }
}

/** Honest per-tab empty state — never a spinner or a lie about having content. */
@Composable
private fun TabEmpty(title: String, sub: String) {
    Column(
        Modifier.fillMaxWidth().padding(top = 44.dp, bottom = 24.dp, start = 24.dp, end = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(6.dp))
        Text(sub, color = Text2, fontSize = 13.5.sp, lineHeight = 19.sp, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }
}

/**
 * The action the identity implies — Instagram's split button row: Follow / Message for
 * another player, Share for your own. The follow relationship is optimistic and rolls
 * back on failure; the follower COUNT lives in the hero and reconciles on next load.
 */
@Composable
private fun ProfileActions(
    p: PlayerProfile,
    onToggleFollow: (suspend (Boolean) -> Boolean?)?,
    onShare: () -> Unit,
    onMessage: ((playerId: String, name: String) -> Unit)? = null,
    onEdit: (() -> Unit)? = null,
) {
    val social = p.social ?: return
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    var following by remember(p.playerId, social.isFollowing) { mutableStateOf(social.isFollowing) }
    var busy by remember(p.playerId) { mutableStateOf(false) }
    // Message shows only when BOTH follow each other — exactly when the server permits a
    // conversation, so the button never exists to be refused.
    val mutual = following && social.followsMe

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        if (social.canFollow && onToggleFollow != null) {
            ActionButton(
                modifier = Modifier.weight(1f),
                label = if (following) "Following" else "Follow",
                filled = !following,
                enabled = !busy,
                haptic = null,
            ) {
                val next = !following
                view.performHapticFeedback(if (next) Feel.COMMIT else Feel.REMOVE)
                following = next
                busy = true
                scope.launch {
                    val settled = onToggleFollow(next)
                    if (settled == null) following = !next
                    else if (settled != next) following = settled
                    busy = false
                }
            }
            if (mutual && onMessage != null) {
                ActionButton(modifier = Modifier.weight(1f), label = "Message", filled = false) {
                    onMessage(p.playerId, p.name)
                }
            }
        } else if (social.isSelf) {
            ActionButton(modifier = Modifier.weight(1f), label = "Share profile", filled = false, onClick = onShare)
            if (onEdit != null) {
                ActionButton(modifier = Modifier.weight(1f), label = "Edit profile", filled = false, onClick = onEdit)
            }
        }
    }
}

/** One outlined/filled action button — the pieces of the Instagram split row. */
@Composable
private fun ActionButton(
    modifier: Modifier = Modifier,
    label: String,
    filled: Boolean,
    enabled: Boolean = true,
    haptic: Int? = Feel.SELECT,
    onClick: () -> Unit,
) {
    Box(
        modifier
            // Subtle lift so the button reads as a physical control, not flat text.
            .shadow(
                elevation = if (filled) 6.dp else 3.dp,
                shape = RoundedCornerShape(10.dp),
                clip = false,
                ambientColor = (if (filled) BlueBright else Color.Black).copy(alpha = 0.10f),
                spotColor = (if (filled) BlueBright else Color.Black).copy(alpha = if (filled) 0.40f else 0.12f),
            )
            .pressable(enabled = enabled, haptic = haptic, onClick = onClick)
            .clip(RoundedCornerShape(10.dp))
            .background(if (filled) BlueBright else Surface)
            .then(if (filled) Modifier else Modifier.border(1.dp, Stroke, RoundedCornerShape(10.dp)))
            .padding(vertical = 11.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, color = if (filled) Color.White else Text1, fontSize = 14.sp, fontWeight = FontWeight.Bold)
    }
}

/**
 * One count. Tappable only when there is a list behind it — a control that looks
 * pressable and does nothing is the hollowness this work has been removing, so
 * Matches (which has no list screen) stays inert.
 */
@Composable
private fun SocialCount(value: Int, label: String, onClick: (() -> Unit)? = null, onHero: Boolean = false) {
    // On the blue hero the counts must read white; on a light card they read dark.
    val valueColor = if (onHero) Color.White else Text1
    val labelColor = if (onHero) Color.White.copy(alpha = 0.82f) else Text3
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = if (onClick != null) {
            Modifier.pressable(onClick = onClick).padding(horizontal = 12.dp, vertical = 4.dp)
        } else {
            Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
        },
    ) {
        Text("${AnimatedInt(value)}", color = valueColor, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
        Spacer(Modifier.height(2.dp))
        Text(label, color = labelColor, fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
    }
}

/**
 * Has this player actually done anything yet?
 *
 * Matters because the profile was designed for someone with a season behind them,
 * and almost nobody is: a new player was shown "Unranked", 0 matches, 0 runs,
 * 0 wickets, — rank, 0 XP and "No settled matches yet" — six separate ways of
 * saying *you have nothing*, with no route out of it.
 */
private fun hasAnyHistory(p: PlayerProfile): Boolean =
    matchesPlayed(p) > 0 || p.recentMatches.isNotEmpty() || p.rankDistrict != null || p.rankedXp > 0

/** Matches in the player's own sport, falling back to cricket's column on an old server. */
private fun matchesPlayed(p: PlayerProfile): Int =
    p.sportCareer?.stats?.firstOrNull { it.label == "Matches" }?.value ?: p.careerMatches

/**
 * Performance cells for the Career strip — the sport's own figures MINUS Matches,
 * which now lives in the social triplet (showing it twice was the same mistake the
 * duplicated rank was).
 *
 * Can legitimately come back EMPTY: badminton records points per side, so it reports
 * matches and nothing else. The section hides rather than printing a bare heading.
 */
private fun careerCells(p: PlayerProfile): List<com.haraan.app.data.CareerStat> =
    (
        p.sportCareer?.stats
            ?: listOf(
                com.haraan.app.data.CareerStat("Matches", p.careerMatches),
                com.haraan.app.data.CareerStat("Runs", p.careerRuns),
                com.haraan.app.data.CareerStat("Wickets", p.careerWickets),
            )
        ).filterNot { it.label == "Matches" }

/**
 * The one card a brand-new player sees in place of the zeros. States plainly what
 * fills the profile in, and — on your own profile — offers the action that does it.
 *
 * [isSelf] drives the copy and [onCreateMatch] the button, and they are deliberately
 * SEPARATE: this screen is opened for your own profile from two different places, and
 * only one of them sits in a scope that can open the create-match flow. Deriving
 * "is this me" from the callback put "hasn't completed a match" on your own profile.
 */
@Composable
private fun FirstMatchCard(p: PlayerProfile, isSelf: Boolean, onCreateMatch: (() -> Unit)?) {
    Column(
        Modifier
            .fillMaxWidth()
            // Soft floating shadow (the app's card language) instead of a flat hairline —
            // this is what gives the card physical lift.
            .premiumCardShadow(radius = 20.dp, ambient = 16.dp, contact = 2.dp)
            .clip(RoundedCornerShape(20.dp))
            .background(Surface)
            .padding(20.dp),
    ) {
        Text(
            if (isSelf) "Play your first match" else "No matches yet",
            color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            if (isSelf) {
                "Your rank, career figures and XP all start the moment a match you played " +
                    "is completed and both captains confirm the result."
            } else {
                "${p.name.ifBlank { "This player" }} hasn't completed a match on Haraan yet, " +
                    "so there's nothing to rank."
            },
            color = Text2, fontSize = 14.sp, lineHeight = 20.sp,
        )

        if (isSelf) {
            // The ladder, so "nothing yet" still shows a next rung rather than a void.
            Spacer(Modifier.height(16.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(BlueTint)
                    .padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Level 1", color = BlueBright, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                Text("250 XP to Level 2", color = BlueBright.copy(alpha = 0.8f), fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
            }

        }

        if (onCreateMatch != null) {
            Spacer(Modifier.height(16.dp))
            // Blue: this moves you forward. Green is reserved for something landing.
            Box(
                Modifier
                    .fillMaxWidth()
                    // Blue-tinted lift on the primary CTA so it reads as a raised button.
                    .shadow(8.dp, RoundedCornerShape(14.dp), clip = false, ambientColor = BlueBright.copy(alpha = 0.35f), spotColor = BlueBright.copy(alpha = 0.5f))
                    .pressable { onCreateMatch() }
                    .clip(RoundedCornerShape(14.dp))
                    .background(BlueBright)
                    .padding(vertical = 14.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text("Create a match", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

/** Count-up number — the big stats animate to their value instead of snapping in. */
@Composable
private fun AnimatedInt(target: Int): Int {
    val v by animateIntAsState(
        targetValue = target,
        animationSpec = tween(650, easing = FastOutSlowInEasing),
        label = "countUp",
    )
    return v
}

/** Absolute avatar URL (backend may hand back a relative /storage path). */
private fun avatarModel(raw: String?): String? {
    val s = raw?.trim().orEmpty()
    if (s.isBlank() || s == "null") return null
    return if (s.startsWith("http")) s else ApiConfig.BASE_URL.trimEnd('/') + "/" + s.trimStart('/')
}

// ─────────────────────────────────────────────────────── Recent form ────────────
@Composable
private fun RecentForm(matches: List<RecentMatch>) {
    val last = matches.take(5)
    val wins = last.count { it.won }
    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .padding(18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Last ${last.size}", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            Text("$wins W · ${last.size - wins} L", color = Text3, fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            last.forEach { m ->
                val won = m.won
                Box(
                    Modifier
                        .size(34.dp)
                        .clip(CircleShape)
                        .background(if (won) Green else Color(0xFFEEF1F5)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(if (won) "W" else "L", color = if (won) Color.White else Text3, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────── About ──────────────
private fun prettyDob(iso: String?): String? {
    if (iso.isNullOrBlank()) return null
    return runCatching {
        val parsed = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(iso)
        java.text.SimpleDateFormat("d MMM yyyy", java.util.Locale.US).format(parsed!!)
    }.getOrDefault(iso)
}

private fun aboutRows(p: PlayerProfile): List<Pair<String, String>> = buildList {
    p.gender?.takeIf { it.isNotBlank() }?.let { add("Gender" to it) }
    prettyDob(p.dateOfBirth)?.let { add("Born" to it) }
    p.birthPlace?.takeIf { it.isNotBlank() }?.let { add("Birth Place" to it) }
    p.height?.takeIf { it.isNotBlank() }?.let { add("Height" to it) }
    p.nationality?.takeIf { it.isNotBlank() }?.let { add("Nationality" to it) }
    p.playerRole?.takeIf { it.isNotBlank() }?.let { add("Role" to it) }
    p.battingStyle?.takeIf { it.isNotBlank() }?.let { add("Bats" to it) }
    p.bowlingStyle?.takeIf { it.isNotBlank() }?.let { add("Bowls" to it) }
}

@Composable
private fun AboutCard(rows: List<Pair<String, String>>) {
    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 16.dp, ambient = 14.dp, contact = 2.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .padding(vertical = 4.dp),
    ) {
        rows.forEachIndexed { i, (label, value) ->
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 13.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(label, color = Text3, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(108.dp))
                Text(value, color = BlueBright, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            }
            if (i != rows.lastIndex) {
                Box(Modifier.fillMaxWidth().padding(horizontal = 16.dp).height(1.dp).background(Stroke))
            }
        }
    }
}

// ─────────────────────────────────────────────────────────── Hero card ──────────
@Composable
private fun HeroCard(
    p: PlayerProfile,
    e: PlayerExtras,
    matches: Int,
    followers: Int,
    following: Int,
    onCopyId: () -> Unit,
    onOpenFollowers: (() -> Unit)? = null,
    onOpenFollowing: (() -> Unit)? = null,
) {
    // Flat, Instagram-style header: avatar left, the three counts on the same row to its
    // right, then name / tier / handle / location as plain text below — no blue card.
    Column(Modifier.fillMaxWidth().padding(horizontal = 4.dp, vertical = 6.dp)) {
        Row(verticalAlignment = Alignment.Top) {
            // Avatar with a blue profile-completion ring on a light track.
            Box(contentAlignment = Alignment.Center) {
                Canvas(Modifier.size(88.dp)) {
                    val sw = 3.5.dp.toPx()
                    drawArc(
                        color = HaraanColors.Field,
                        startAngle = -90f, sweepAngle = 360f, useCenter = false,
                        style = DrawStroke(width = sw, cap = StrokeCap.Round),
                    )
                    drawArc(
                        color = BlueBright,
                        startAngle = -90f, sweepAngle = 360f * (e.profilePct / 100f), useCenter = false,
                        style = DrawStroke(width = sw, cap = StrokeCap.Round),
                    )
                }
                val photo = avatarModel(p.avatar)
                Box(
                    Modifier.size(74.dp).clip(CircleShape).background(HaraanColors.Field),
                    contentAlignment = Alignment.Center,
                ) {
                    if (photo != null) {
                        AsyncImage(
                            model = photo,
                            contentDescription = "Profile photo",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxSize().clip(CircleShape),
                        )
                    } else {
                        Text(p.name.take(1).uppercase().ifBlank { "?" }, color = BlueBright, fontSize = 30.sp, fontWeight = FontWeight.Bold)
                    }
                }
                // Level rides the avatar corner, ringed in the page colour so it reads as
                // a badge on the photo.
                Box(
                    Modifier.align(Alignment.BottomEnd)
                        .clip(RoundedCornerShape(9.dp)).background(BlueBright)
                        .border(2.dp, Surface, RoundedCornerShape(9.dp))
                        .padding(horizontal = 7.dp, vertical = 2.dp),
                ) { Text("LVL ${e.level}", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold) }
            }
            Spacer(Modifier.width(18.dp))
            // Name moved UP beside the avatar, with the three counts directly beneath it.
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(p.name.ifBlank { "Player" }, color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                    Spacer(Modifier.width(8.dp))
                    Box(
                        Modifier.clip(RoundedCornerShape(6.dp)).background(BlueTint).padding(horizontal = 8.dp, vertical = 3.dp),
                    ) { Text(e.tier.uppercase(), color = BlueBright, fontSize = 9.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp) }
                    if (p.isOrganizer) { Spacer(Modifier.width(6.dp)); Pill("ORGANIZER", Gold, GoldTint) }
                }
                Spacer(Modifier.height(12.dp))
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    SocialCount(matches, "Matches")
                    SocialCount(followers, "Followers", onClick = onOpenFollowers)
                    SocialCount(following, "Following", onClick = onOpenFollowing)
                }
            }
        }

        // @handle · location, then the bio — plain text under the header, like Instagram.
        val handle = p.username?.takeIf { it.isNotBlank() }?.let { "@$it" }
        val loc = listOfNotNull(p.district, p.state).joinToString(", ")
        val sub = listOfNotNull(handle, loc.ifBlank { null }).joinToString("  ·  ")
        if (sub.isNotBlank()) {
            Spacer(Modifier.height(10.dp))
            Text(sub, color = Text2, fontSize = 13.sp, fontWeight = FontWeight.Medium)
        }
        if (!p.bio.isNullOrBlank()) {
            Spacer(Modifier.height(if (sub.isNotBlank()) 5.dp else 10.dp))
            Text(p.bio!!, color = Text1, fontSize = 13.5.sp, lineHeight = 18.sp)
        }

        // Trust + ID — copyable, muted, on white. The row answers the tap (haptic + tick).
        Spacer(Modifier.height(8.dp))
        var copied by remember { mutableStateOf(false) }
        LaunchedEffect(copied) { if (copied) { delay(1600); copied = false } }
        Row(
            Modifier.pressable(haptic = Feel.COMMIT) { onCopyId(); copied = true }.padding(vertical = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Default.Shield, null, tint = if (copied) Green else Text3, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(5.dp))
            Text("Trust ${p.trustScore}", color = Text2, fontSize = 12.5.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.width(8.dp))
            Text("·", color = Text3, fontSize = 12.5.sp)
            Spacer(Modifier.width(8.dp))
            Text(
                if (copied) "Copied to clipboard" else "ID ${p.playerId}",
                color = Text2, fontSize = 12.5.sp, fontWeight = FontWeight.Medium, maxLines = 1,
            )
            Spacer(Modifier.width(6.dp))
            Icon(
                if (copied) Icons.Default.Check else Icons.Default.ContentCopy,
                if (copied) "Copied" else "Copy",
                tint = Text3, modifier = Modifier.size(13.dp),
            )
        }
        if (e.profilePct < 100 && e.profileSteps.isNotEmpty()) {
            Spacer(Modifier.height(8.dp))
            Text("Complete your profile:  ${e.profileSteps.joinToString("  ·  ")}", color = Text3, fontSize = 11.5.sp)
        }
    }
}

// ─────────────────────────────────────────────────────── Reputation chips ───────
@Composable
private fun ReputationChips(chips: List<RepChip>) {
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        chips.forEach { c ->
            val (fg, bg) = if (c.green) Green to GreenTint else BlueBright to BlueTint
            Row(
                Modifier.clip(RoundedCornerShape(20.dp)).background(bg)
                    .border(1.dp, fg.copy(alpha = 0.25f), RoundedCornerShape(20.dp))
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(c.icon, null, tint = fg, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(6.dp))
                Text(c.label, color = fg, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

// ─────────────────────────────────────────────────────── Stat row (REAL) ────────
@Composable
private fun StatRow(p: PlayerProfile) {
    // Cells come from the player's own sport. Falling back to cricket only when the
    // server is too old to send the block — this screen used to show every player
    // Runs and Wickets, so a footballer's career read "0 runs, 0 wickets" forever.
    val cells = careerCells(p)

    // No box, no border, no dividers. A bordered card of centred numbers separated by
    // hairlines is the single most generic thing on this screen — and the "Career"
    // heading already provides the grouping the box was doing. Left-aligned so the
    // numbers sit on the same optical margin as every heading down the page.
    //
    // Rank is deliberately NOT here any more: it is the whole point of the card
    // directly above, and showing it twice made neither instance feel like the answer.
    Row(
        Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(32.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        cells.forEach { stat ->
            StatCell("${AnimatedInt(stat.value)}", stat.label)
        }
    }
}

@Composable
private fun StatCell(value: String, label: String) {
    Column(horizontalAlignment = Alignment.Start) {
        // Ink, not green. Green had been doing five unrelated jobs on this screen —
        // completion ring, tier pill, every career number, both gradients — so it had
        // stopped meaning anything. It is reserved now for a good thing landing.
        Text(value, color = Text1, fontSize = 26.sp, fontWeight = FontWeight.ExtraBold)
        Spacer(Modifier.height(2.dp))
        Text(label.uppercase(), color = Text3, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.6.sp)
    }
}

// ─────────────────────────────────────────────── District ranking (REAL) ────────
@Composable
private fun DistrictRankCard(p: PlayerProfile) {
    // Was a second full-width saturated gradient sitting directly under the hero's.
    // Two of them shouted at the same volume, so neither led. On a light surface the
    // rank is MORE prominent, not less: it is now the only saturated number on a white
    // card instead of white-on-green competing with the block above it.
    Row(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .padding(18.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("DISTRICT RANKING", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp)
            Spacer(Modifier.height(4.dp))
            Text(
                p.rankDistrict?.let { "#${AnimatedInt(it)}" } ?: "Unranked",
                color = if (p.rankDistrict != null) BlueBright else Text3,
                fontSize = 34.sp,
                fontWeight = FontWeight.ExtraBold,
            )
            Text("${p.district ?: "Your"} District", color = Text2, fontSize = 13.sp)
        }
        // Real wider-context ranks (state / country) instead of an invented monthly delta.
        Column(horizontalAlignment = Alignment.End) {
            p.rankState?.let { RankChip("State", "#$it") }
            p.rankCountry?.let { Spacer(Modifier.height(8.dp)); RankChip("India", "#$it") }
        }
    }
}

@Composable
private fun RankChip(label: String, value: String) {
    Row(
        Modifier.clip(RoundedCornerShape(10.dp)).background(BlueTint)
            .padding(horizontal = 12.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, color = BlueBright.copy(alpha = 0.75f), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.width(6.dp))
        Text(value, color = BlueBright, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
    }
}

// ─────────────────────────────────────────────────────────── XP card (REAL) ─────
@Composable
private fun XpCard(p: PlayerProfile) {
    // Level curve mirrors deriveExtras: one level per 250 ranked XP.
    val perLevel = 250
    val level = 1 + p.rankedXp / perLevel
    val into = p.rankedXp % perLevel
    val toNext = perLevel - into
    val pct = (into.toFloat() / perLevel).coerceIn(0f, 1f)

    Column(
        Modifier
            .fillMaxWidth()
            .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .padding(18.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("RANKED XP", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Text("${AnimatedInt(p.rankedXp)}", color = BlueBright, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold)
                Text("Casual XP: ${p.casualXp}", color = Text2, fontSize = 12.5.sp)
            }
            StatMini("This month", "${p.monthRankedXp}")
        }

        Spacer(Modifier.height(16.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Level $level", color = Text1, fontSize = 12.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
            Text("$toNext XP to Level ${level + 1}", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Medium)
        }
        Spacer(Modifier.height(8.dp))
        // Progress bar toward the next level.
        Box(Modifier.fillMaxWidth().height(9.dp).clip(RoundedCornerShape(5.dp)).background(Color(0xFFEEF1F5))) {
            Box(
                Modifier
                    .fillMaxWidth(pct)
                    .height(9.dp)
                    .clip(RoundedCornerShape(5.dp))
                    // Same single-hue rule as the hero — this card gets screenshotted
                    // and shared, so it is the most public surface in the app.
                    .background(HeroGradient),
            )
        }
    }
}

// ─────────────────────────────────────────────────────────── Achievements ───────
@Composable
private fun Achievements(items: List<Achievement>) {
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        items.forEach { AchievementBadge(it) }
    }
}

@Composable
private fun AchievementBadge(item: Achievement) {
    val locked = !item.unlocked
    Column(
        Modifier.width(86.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier.size(62.dp).clip(RoundedCornerShape(16.dp))
                .background(if (locked) Bg else item.tier.color.copy(alpha = 0.12f))
                .border(1.5.dp, if (locked) Stroke else item.tier.color.copy(alpha = 0.5f), RoundedCornerShape(16.dp)),
            contentAlignment = Alignment.Center,
        ) {
            if (locked) {
                Icon(Icons.Default.Lock, null, tint = Text3, modifier = Modifier.size(20.dp))
            } else {
                Icon(item.icon, null, tint = item.tier.color, modifier = Modifier.size(26.dp))
            }
        }
        Spacer(Modifier.height(6.dp))
        // Locked badges with a progress hint show it (e.g. "12/50") in place of the tier label.
        val topLabel = if (locked && item.progress != null) item.progress else item.tier.label
        Text(topLabel, color = if (locked) Text3 else item.tier.color, fontSize = 9.5.sp, fontWeight = FontWeight.ExtraBold)
        Text(item.label, color = if (locked) Text3 else Text2, fontSize = 10.5.sp, fontWeight = FontWeight.Medium, maxLines = 2, textAlign = TextAlign.Center)
    }
}

// ─────────────────────────────────────────────────────── Recent match (REAL) ────
@Composable
private fun RecentMatchRow(m: RecentMatch) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(12.dp))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(m.title, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Pill(m.matchType.uppercase(), Text2, Bg)
                Spacer(Modifier.width(6.dp))
                val (tColor, tBg) = trustColors(m.trustLevel)
                Pill(if (m.isRanked) "RANKED" else "CASUAL", tColor, tBg)
                if (m.won) { Spacer(Modifier.width(6.dp)); Pill("WON", Green, GreenTint) }
                if (m.mom) { Spacer(Modifier.width(6.dp)); Pill("MOM", Gold, GoldTint) }
            }
        }
        Text("+${m.xp}", color = BlueBright, fontSize = 17.sp, fontWeight = FontWeight.Bold)
    }
}

// ─────────────────────────────────────────────────────────── Shared bits ────────
@Composable
private fun TopBar(title: String, leadingIcon: ImageVector, onLeading: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .background(Surface)
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(Color(0xFFEFF2F7))
                .clickable(onClick = onLeading),
            contentAlignment = Alignment.Center,
        ) {
            Icon(leadingIcon, "Back", tint = Text1, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(title, color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun CenterBox(content: @Composable () -> Unit) {
    Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) { content() }
}

@Composable
private fun SectionTitle(title: String, trailing: String? = null) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(title, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
        if (trailing != null) {
            Text(trailing, color = Text3, fontSize = 12.5.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun Pill(text: String, color: Color, bg: Color) {
    Box(
        Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(bg)
            .padding(horizontal = 7.dp, vertical = 3.dp),
    ) {
        Text(text, color = color, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun StatMini(label: String, value: String) {
    Column(horizontalAlignment = Alignment.End) {
        Text(value, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        Text(label, color = Text3, fontSize = 11.sp)
    }
}

private fun trustColors(trust: String): Pair<Color, Color> = when (trust) {
    "verified" -> Green to GreenTint
    "high" -> Green to GreenTint
    "medium" -> Gold to GoldTint
    else -> Text3 to Bg
}

// ─────────────────────────────────────────────── Edit name + bio ─────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EditProfileSheet(
    currentName: String,
    currentBio: String?,
    onDismiss: () -> Unit,
    onSave: suspend (name: String, bio: String?) -> Boolean,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val scope = rememberCoroutineScope()
    var name by remember { mutableStateOf(currentName) }
    var bio by remember { mutableStateOf(currentBio ?: "") }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState, containerColor = Surface) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().imePadding().padding(20.dp)) {
            Text("Edit profile", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))

            Text("Name", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            OutlinedTextField(
                value = name,
                onValueChange = { if (it.length <= 40) name = it },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                enabled = !saving,
            )

            Spacer(Modifier.height(14.dp))
            Text("Bio", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            OutlinedTextField(
                value = bio,
                onValueChange = { if (it.length <= 160) bio = it },
                placeholder = { Text("Add a short bio…", color = Text3) },
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
                shape = RoundedCornerShape(12.dp),
                enabled = !saving,
            )
            Text("${bio.length}/160", color = Text3, fontSize = 11.sp, modifier = Modifier.align(Alignment.End).padding(top = 4.dp))

            error?.let { Spacer(Modifier.height(6.dp)); Text(it, color = HaraanColors.Danger, fontSize = 13.sp) }

            Spacer(Modifier.height(18.dp))
            val canSave = name.isNotBlank() && !saving
            Box(
                Modifier
                    .fillMaxWidth()
                    .pressable(enabled = canSave) {
                        saving = true
                        error = null
                        scope.launch {
                            val ok = onSave(name.trim(), bio.trim().ifBlank { null })
                            saving = false
                            if (!ok) error = "Couldn't save. Check your connection and try again."
                        }
                    }
                    .clip(RoundedCornerShape(12.dp))
                    .background(if (canSave) BlueBright else Color(0xFFCBD2DC))
                    .padding(vertical = 14.dp),
                contentAlignment = Alignment.Center,
            ) {
                if (saving) {
                    CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp, color = Color.White)
                } else {
                    Text("Save", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
            Spacer(Modifier.height(8.dp))
        }
    }
}

// ─────────────────────────────────────────────── Shareable player card ───────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ShareCardSheet(p: PlayerProfile, e: PlayerExtras, onDismiss: () -> Unit) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val graphicsLayer = rememberGraphicsLayer()
    var sharing by remember { mutableStateOf(false) }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState, containerColor = Surface) {
        Column(
            Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, bottom = 28.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text("Share your card", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text("A snapshot of your real numbers.", color = Text3, fontSize = 12.5.sp)
            Spacer(Modifier.height(18.dp))

            // Capture exactly what's drawn here into the graphics layer.
            Box(
                Modifier.drawWithContent {
                    graphicsLayer.record { this@drawWithContent.drawContent() }
                    drawLayer(graphicsLayer)
                }
            ) { ShareablePlayerCard(p, e) }

            Spacer(Modifier.height(20.dp))
            Box(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(13.dp))
                    .background(if (sharing) Color(0xFFBFC8D2) else BlueBright)
                    .clickable(enabled = !sharing) {
                        sharing = true
                        scope.launch {
                            runCatching { captureAndShare(ctx, graphicsLayer) }
                            sharing = false
                        }
                    }
                    .padding(vertical = 15.dp),
                contentAlignment = Alignment.Center,
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Share, null, tint = Color.White, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(if (sharing) "Preparing…" else "Share image", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

/** The card that gets rendered to an image — a compact, branded snapshot. */
@Composable
private fun ShareablePlayerCard(p: PlayerProfile, e: PlayerExtras) {
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(22.dp)).background(HeroGradient).padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            val photo = avatarModel(p.avatar)
            Box(
                Modifier.size(58.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.18f)),
                contentAlignment = Alignment.Center,
            ) {
                if (photo != null) {
                    AsyncImage(photo, "Photo", contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize().clip(CircleShape))
                } else {
                    Text(p.name.take(1).uppercase().ifBlank { "?" }, color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.Bold)
                }
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(p.name.ifBlank { "Player" }, color = Color.White, fontSize = 20.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                Text("${e.tier} · Lvl ${e.level}", color = Green, fontSize = 12.5.sp, fontWeight = FontWeight.Bold)
                val loc = listOfNotNull(p.district, p.state).joinToString(" · ")
                if (loc.isNotBlank()) Text(loc, color = Color.White.copy(alpha = 0.85f), fontSize = 11.5.sp)
            }
            Text("HARAAN", color = Color.White.copy(alpha = 0.55f), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.5.sp)
        }

        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.15f)))
        Spacer(Modifier.height(14.dp))

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            ShareStat("RANK", p.rankDistrict?.let { "#$it" } ?: "—")
            ShareStat("RUNS", "${p.careerRuns}")
            ShareStat("WKTS", "${p.careerWickets}")
            ShareStat("XP", "${p.rankedXp}")
        }

        Spacer(Modifier.height(16.dp))
        Text(
            "ActionBoard · ID ${p.playerId}",
            color = Color.White.copy(alpha = 0.7f), fontSize = 10.sp, fontWeight = FontWeight.SemiBold,
            letterSpacing = 0.5.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun ShareStat(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = Color.White, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Color.White.copy(alpha = 0.7f), fontSize = 10.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.5.sp)
    }
}

/** Render the captured layer to a PNG in cache and fire a share-image chooser. */
private suspend fun captureAndShare(context: android.content.Context, graphicsLayer: GraphicsLayer) {
    val bitmap = graphicsLayer.toImageBitmap().asAndroidBitmap()
    val dir = java.io.File(context.cacheDir, "shared").apply { mkdirs() }
    val file = java.io.File(dir, "player_card.png")
    java.io.FileOutputStream(file).use { bitmap.compress(android.graphics.Bitmap.CompressFormat.PNG, 100, it) }
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val send = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
        type = "image/png"
        putExtra(android.content.Intent.EXTRA_STREAM, uri)
        addFlags(android.content.Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(android.content.Intent.createChooser(send, "Share player card"))
}
