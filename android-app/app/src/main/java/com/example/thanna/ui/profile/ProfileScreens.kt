package com.example.thanna.ui.profile

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
import com.example.thanna.data.PlayerProfile
import com.example.thanna.data.RecentMatch

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

// ─── Mock gamification — TODO: wire to backend (no fields on PlayerProfile yet) ──
private data class PlayerExtras(
    val level: Int,
    val tier: String,
    val profilePct: Int,
    val profileSteps: List<String>,
    val role: String,
    val streak: String,
    val rankUpThisMonth: Int,
    val chips: List<RepChip>,
    val achievements: List<Achievement>,
)

private enum class BadgeTier(val label: String, val color: Color) {
    BRONZE("Bronze", Bronze), SILVER("Silver", Silver), GOLD("Gold", Gold)
}

private data class RepChip(val emoji: String, val label: String, val green: Boolean)
private data class Achievement(val emoji: String, val label: String, val tier: BadgeTier, val unlocked: Boolean)

private val mockPlayerExtras = PlayerExtras(
    level = 12,
    tier = "Rising Player",
    profilePct = 85,
    profileSteps = listOf("Add player photo", "Play first match"),
    role = "All Rounder",
    streak = "7 Match Streak",
    rankUpThisMonth = 14,
    chips = listOf(
        RepChip("🏏", "All Rounder", green = true),
        RepChip("🔥", "7 Match Streak", green = false),
        RepChip("⭐", "Top 10 Kadapa", green = true),
        RepChip("🛡", "Verified", green = false),
    ),
    achievements = listOf(
        Achievement("🏆", "First Century", BadgeTier.GOLD, unlocked = true),
        Achievement("🔥", "5 Match Streak", BadgeTier.SILVER, unlocked = true),
        Achievement("⭐", "District Top 100", BadgeTier.BRONZE, unlocked = true),
        Achievement("🎯", "First Tournament", BadgeTier.BRONZE, unlocked = false),
        Achievement("🥇", "MVP x10", BadgeTier.SILVER, unlocked = false),
        Achievement("🏏", "50 Wickets", BadgeTier.GOLD, unlocked = false),
    ),
)

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
            is ProfileState.Loaded -> ProfileContent(s.profile, mockPlayerExtras)
        }
    }
}

@Composable
private fun ProfileContent(p: PlayerProfile, e: PlayerExtras) {
    val clipboard = LocalClipboardManager.current
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
    ) {
        item { HeroCard(p, e, onCopyId = { clipboard.setText(AnnotatedString(p.playerId)) }) }
        aboutRows(p).takeIf { it.isNotEmpty() }?.let { rows ->
            item { Spacer(Modifier.height(14.dp)); SectionTitle("About ${p.name.ifBlank { "player" }}") }
            item { Spacer(Modifier.height(12.dp)); AboutCard(rows) }
        }
        item { Spacer(Modifier.height(14.dp)); PlayerSnapshot(p, e) }
        item { Spacer(Modifier.height(14.dp)); ReputationChips(e.chips) }
        item { Spacer(Modifier.height(14.dp)); StatRow(p) }
        item { Spacer(Modifier.height(14.dp)); DistrictRankCard(p, e) }
        item { Spacer(Modifier.height(14.dp)); XpCard(p) }

        item { Spacer(Modifier.height(24.dp)); SectionTitle("Achievements", "${e.achievements.count { it.unlocked }}/${e.achievements.size} unlocked") }
        item { Spacer(Modifier.height(12.dp)); Achievements(e.achievements) }

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
private fun HeroCard(p: PlayerProfile, e: PlayerExtras, onCopyId: () -> Unit) {
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
                Box(
                    Modifier.size(62.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.16f)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        p.name.take(1).uppercase().ifBlank { "?" },
                        color = Color.White, fontSize = 26.sp, fontWeight = FontWeight.Bold,
                    )
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
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
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

// ─────────────────────────────────────────────────────── Player snapshot ────────
@Composable
private fun PlayerSnapshot(p: PlayerProfile, e: PlayerExtras) {
    val tiles = listOf(
        Triple("🏏", "Role", e.role),
        Triple("📍", "Location", p.district ?: p.state ?: "—"),
        Triple("⭐", "Tier", e.tier),
        Triple("🔥", "Streak", e.streak.removeSuffix(" Match Streak").let { "$it matches" }),
    )
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        tiles.forEach { (emoji, label, value) ->
            Column(
                Modifier.weight(1f).clip(RoundedCornerShape(14.dp)).background(Surface)
                    .border(1.dp, Stroke, RoundedCornerShape(14.dp)).padding(vertical = 12.dp, horizontal = 6.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(emoji, fontSize = 18.sp)
                Spacer(Modifier.height(5.dp))
                Text(value, color = Text1, fontSize = 12.sp, fontWeight = FontWeight.Bold, maxLines = 1, textAlign = TextAlign.Center)
                Text(label, color = Text3, fontSize = 10.sp)
            }
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
                Text(c.emoji, fontSize = 13.sp)
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
        StatCell("${p.careerMatches}", "Matches", Green)
        VDivider()
        StatCell("${p.careerRuns}", "Runs", Green)
        VDivider()
        StatCell("${p.careerWickets}", "Wickets", Green)
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
private fun DistrictRankCard(p: PlayerProfile, e: PlayerExtras) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Brush.linearGradient(listOf(BlueBright, Green)))
            .padding(18.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("DISTRICT RANKING", color = Color.White.copy(alpha = 0.85f), fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(p.rankDistrict?.let { "#$it" } ?: "Unranked", color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold)
            Text("${p.district ?: "Your"} District", color = Color.White.copy(alpha = 0.9f), fontSize = 13.sp)
        }
        if (p.rankDistrict != null) {
            Row(
                Modifier.clip(RoundedCornerShape(10.dp)).background(Color.White.copy(alpha = 0.18f))
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Default.TrendingUp, null, tint = Color.White, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(5.dp))
                Text("↑ ${e.rankUpThisMonth} this month", color = Color.White, fontSize = 12.5.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

// ─────────────────────────────────────────────────────────── XP card (REAL) ─────
@Composable
private fun XpCard(p: PlayerProfile) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
            .padding(18.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("RANKED XP", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Text("${p.rankedXp}", color = BlueBright, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold)
            Text("Casual XP: ${p.casualXp}", color = Text2, fontSize = 12.5.sp)
        }
        Column(horizontalAlignment = Alignment.End) {
            StatMini("This month", "${p.monthRankedXp}")
            Spacer(Modifier.height(8.dp))
            StatMini("Casual XP", "${p.casualXp}")
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
                Text(item.emoji, fontSize = 27.sp)
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(item.tier.label, color = if (locked) Text3 else item.tier.color, fontSize = 9.5.sp, fontWeight = FontWeight.ExtraBold)
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
