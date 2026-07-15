package com.haraan.app.ui.profile

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
import androidx.compose.material.icons.filled.Close
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
import androidx.compose.ui.platform.LocalClipboardManager
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

// ─── Palette: blue + green (energetic / gamified player area) ────────────────────
private val Bg        = Color(0xFFF6F8FB)
private val Surface   = Color(0xFFFFFFFF)
private val Navy      = Color(0xFF0F172A)
private val Blue      = Color(0xFF1E3A8A)
private val BlueBright= Color(0xFF2563EB)
private val Green     = Color(0xFF00B140)
private val GreenTint = Color(0xFFE7F7EE)
private val BlueTint  = Color(0xFFEAF1FE)
private val Text1     = Color(0xFF111827)
private val Text2     = Color(0xFF5A6473)
private val Text3     = Color(0xFF9AA3B2)
private val Stroke    = Color(0xFFE5E9F0)
private val Bronze    = Color(0xFFCD7F32)
private val Silver    = Color(0xFF8E99A8)
private val Gold      = Color(0xFFB8860B)
private val GoldTint  = Color(0xFFFAF3E0)

private val HeroGradient = Brush.linearGradient(listOf(Navy, Blue, Green))

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
    val fields = listOf(p.avatar, p.district, p.state, p.playerRole, p.battingStyle, p.bowlingStyle, p.gender, p.dateOfBirth)
    val filled = fields.count { !it.isNullOrBlank() }
    val profilePct = filled * 100 / fields.size
    val steps = buildList {
        if (p.avatar.isNullOrBlank()) add("Add photo")
        if (p.playerRole.isNullOrBlank()) add("Set role")
        if (p.battingStyle.isNullOrBlank()) add("Batting style")
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
) {
    var state by remember { mutableStateOf<ProfileState>(ProfileState.Loading) }
    var reloadKey by remember { mutableStateOf(0) }

    LaunchedEffect(reloadKey) {
        state = ProfileState.Loading
        state = try {
            ProfileState.Loaded(fetchProfile())
        } catch (e: Exception) {
            ProfileState.Error(e.message ?: "Unable to load profile.")
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        TopBar(title = "Player Profile", leadingIcon = Icons.AutoMirrored.Filled.ArrowBack, onLeading = onBack)

        when (val s = state) {
            is ProfileState.Loading -> CenterBox { CircularProgressIndicator(color = BlueBright) }
            is ProfileState.Error -> CenterBox {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(s.message, color = Text2, fontSize = 14.sp)
                    Spacer(Modifier.height(12.dp))
                    Box(
                        Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(BlueBright)
                            .clickable { reloadKey++ }
                            .padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
                }
            }
            is ProfileState.Loaded -> ProfileContent(s.profile, deriveExtras(s.profile))
        }
    }
}

@Composable
private fun ProfileContent(p: PlayerProfile, e: PlayerExtras) {
    val clipboard = LocalClipboardManager.current
    var showShare by remember { mutableStateOf(false) }
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
    ) {
        // Identity — photo, tier, level, trust+ID (each fact appears once, only here).
        item { HeroCard(p, e, onCopyId = { clipboard.setText(AnnotatedString(p.playerId)) }, onShare = { showShare = true }) }

        // Signature — the district rank as the screen's dominant number.
        item { Spacer(Modifier.height(14.dp)); DistrictRankCard(p) }

        // Career stats (real) — matches / runs / wickets / rank, with count-up.
        item { Spacer(Modifier.height(14.dp)); SectionTitle("Career"); Spacer(Modifier.height(12.dp)); StatRow(p) }

        // Recent form (real) — last five results as a W/L guide.
        if (p.recentMatches.isNotEmpty()) {
            item { Spacer(Modifier.height(20.dp)); SectionTitle("Recent form"); Spacer(Modifier.height(12.dp)); RecentForm(p.recentMatches) }
        }

        // XP (real).
        item { Spacer(Modifier.height(20.dp)); SectionTitle("Experience"); Spacer(Modifier.height(12.dp)); XpCard(p) }

        // Recognition (real chips) — only when the player has earned any.
        if (e.chips.isNotEmpty()) {
            item { Spacer(Modifier.height(20.dp)); SectionTitle("Recognition"); Spacer(Modifier.height(12.dp)); ReputationChips(e.chips) }
        }

        // About (real, de-duped — the single home for role / batting / bowling / bio).
        aboutRows(p).takeIf { it.isNotEmpty() }?.let { rows ->
            item { Spacer(Modifier.height(20.dp)); SectionTitle("About ${p.name.ifBlank { "player" }}") }
            item { Spacer(Modifier.height(12.dp)); AboutCard(rows) }
        }

        if (e.achievements.isNotEmpty()) {
            item { Spacer(Modifier.height(24.dp)); SectionTitle("Achievements", "${e.achievements.count { it.unlocked }}/${e.achievements.size} unlocked") }
            item { Spacer(Modifier.height(12.dp)); Achievements(e.achievements) }
        }

        item { Spacer(Modifier.height(24.dp)); SectionTitle("Recent matches") }
        item { Spacer(Modifier.height(12.dp)) }
        if (p.recentMatches.isEmpty()) {
            item { Text("No settled matches yet.", color = Text3, fontSize = 14.sp) }
        } else {
            items(p.recentMatches.size) { i ->
                RecentMatchRow(p.recentMatches[i])
                Spacer(Modifier.height(8.dp))
            }
        }
        item { Spacer(Modifier.height(16.dp)) }
    }

    if (showShare) ShareCardSheet(p, e, onDismiss = { showShare = false })
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
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
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
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
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
private fun HeroCard(p: PlayerProfile, e: PlayerExtras, onCopyId: () -> Unit, onShare: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(HeroGradient)
            .padding(20.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            // Avatar with profile-completion ring
            Box(contentAlignment = Alignment.Center) {
                Canvas(Modifier.size(78.dp)) {
                    val sw = 5.dp.toPx()
                    drawArc(
                        color = Color.White.copy(alpha = 0.22f),
                        startAngle = -90f, sweepAngle = 360f, useCenter = false,
                        style = DrawStroke(width = sw, cap = StrokeCap.Round),
                    )
                    drawArc(
                        color = Green,
                        startAngle = -90f, sweepAngle = 360f * (e.profilePct / 100f), useCenter = false,
                        style = DrawStroke(width = sw, cap = StrokeCap.Round),
                    )
                }
                val photo = avatarModel(p.avatar)
                Box(
                    Modifier.size(62.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.16f)),
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
                        Text(
                            p.name.take(1).uppercase().ifBlank { "?" },
                            color = Color.White, fontSize = 26.sp, fontWeight = FontWeight.Bold,
                        )
                    }
                }
            }
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(p.name.ifBlank { "Player" }, color = Color.White, fontSize = 20.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                    if (p.isOrganizer) {
                        Spacer(Modifier.width(8.dp))
                        Pill("ORGANIZER", Gold, GoldTint)
                    }
                }
                Spacer(Modifier.height(5.dp))
                Box(
                    Modifier.clip(RoundedCornerShape(7.dp)).background(Green.copy(alpha = 0.9f))
                        .padding(horizontal = 9.dp, vertical = 4.dp),
                ) {
                    Text(e.tier.uppercase(), color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold)
                }
                val loc = listOfNotNull(p.district, p.state).joinToString(" · ")
                if (loc.isNotBlank()) {
                    Spacer(Modifier.height(5.dp))
                    Text(loc, color = Color.White.copy(alpha = 0.85f), fontSize = 12.5.sp)
                }
            }
            Column(horizontalAlignment = Alignment.End) {
                Box(
                    Modifier.size(32.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.16f))
                        .clickable(onClick = onShare),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Default.Share, "Share card", tint = Color.White, modifier = Modifier.size(16.dp)) }
                Spacer(Modifier.height(8.dp))
                Box(
                    Modifier.clip(RoundedCornerShape(10.dp)).background(Color.White.copy(alpha = 0.16f))
                        .padding(horizontal = 11.dp, vertical = 6.dp),
                ) {
                    Text("LVL ${e.level}", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
                }
                Spacer(Modifier.height(5.dp))
                Text("${e.profilePct}% complete", color = Color.White.copy(alpha = 0.85f), fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold)
            }
        }
        // Trust + ID row
        Spacer(Modifier.height(16.dp))
        Row(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White.copy(alpha = 0.1f))
                .clickable(onClick = onCopyId)
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Default.Shield, null, tint = Color.White, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text("Trust ${p.trustScore}", color = Color.White, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.width(14.dp))
            Box(Modifier.width(1.dp).height(16.dp).background(Color.White.copy(alpha = 0.3f)))
            Spacer(Modifier.width(14.dp))
            Text("ID ${p.playerId}", color = Color.White.copy(alpha = 0.9f), fontSize = 12.5.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
            Icon(Icons.Default.ContentCopy, "Copy", tint = Color.White.copy(alpha = 0.8f), modifier = Modifier.size(15.dp))
        }
        if (e.profilePct < 100 && e.profileSteps.isNotEmpty()) {
            Spacer(Modifier.height(10.dp))
            Text(
                "Complete your profile:  ${e.profileSteps.joinToString("  ·  ")}",
                color = Color.White.copy(alpha = 0.8f), fontSize = 11.5.sp,
            )
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
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
            .padding(vertical = 16.dp),
        horizontalArrangement = Arrangement.SpaceEvenly,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StatCell("${AnimatedInt(p.careerMatches)}", "Matches", Green)
        VDivider()
        StatCell("${AnimatedInt(p.careerRuns)}", "Runs", Green)
        VDivider()
        StatCell("${AnimatedInt(p.careerWickets)}", "Wickets", Green)
        VDivider()
        StatCell(p.rankDistrict?.let { "#$it" } ?: "—", "Rank", BlueBright)
    }
}

@Composable
private fun StatCell(value: String, label: String, color: Color) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = color, fontSize = 21.sp, fontWeight = FontWeight.ExtraBold)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Text3, fontSize = 11.5.sp)
    }
}

@Composable
private fun VDivider() {
    Box(Modifier.width(1.dp).height(34.dp).background(Stroke))
}

// ─────────────────────────────────────────────── District ranking (REAL) ────────
@Composable
private fun DistrictRankCard(p: PlayerProfile) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Brush.linearGradient(listOf(BlueBright, Green)))
            .padding(18.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("DISTRICT RANKING", color = Color.White.copy(alpha = 0.85f), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp)
            Spacer(Modifier.height(4.dp))
            Text(p.rankDistrict?.let { "#${AnimatedInt(it)}" } ?: "Unranked", color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold)
            Text("${p.district ?: "Your"} District", color = Color.White.copy(alpha = 0.9f), fontSize = 13.sp)
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
        Modifier.clip(RoundedCornerShape(10.dp)).background(Color.White.copy(alpha = 0.18f))
            .padding(horizontal = 12.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, color = Color.White.copy(alpha = 0.85f), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.width(6.dp))
        Text(value, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
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
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
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
                    .background(Brush.linearGradient(listOf(BlueBright, Green))),
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
