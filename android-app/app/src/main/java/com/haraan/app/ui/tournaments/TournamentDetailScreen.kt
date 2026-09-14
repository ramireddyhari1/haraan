package com.haraan.app.ui.tournaments

import android.content.Intent
import android.net.Uri
import android.widget.Toast
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.TokenStore
import com.haraan.app.data.Tournament
import com.haraan.app.data.TournamentRepository
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.HaraanImage
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import java.text.SimpleDateFormat
import java.util.Locale

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Blue = HaraanColors.EventsBlue
private val BlueTint = HaraanColors.AccentTint
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val Green = HaraanColors.Success
private val GreenTint = HaraanColors.SuccessTint
private val Danger = HaraanColors.Danger
private val BannerFallback = Brush.linearGradient(listOf(Color(0xFF0F2A5C), Color(0xFF1D4ED8)))

/** "20 Sep – 28 Sep 2026", or one date for a one-day event. Plain string maths: minSdk 24 has no java.time. */
fun tournamentDateRange(start: String, end: String): String {
    val parse = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    val short = SimpleDateFormat("d MMM", Locale.getDefault())
    val long = SimpleDateFormat("d MMM yyyy", Locale.getDefault())
    return runCatching {
        val s = parse.parse(start)!!
        val e = parse.parse(end)!!
        if (start == end) long.format(s) else "${short.format(s)} – ${long.format(e)}"
    }.getOrDefault("$start – $end")
}

/** The phase pill: running now, coming up, or over. */
@Composable
fun TournamentPhasePill(phase: String) {
    val (label, fg, bg) = when (phase) {
        "ongoing" -> Triple("LIVE NOW", Danger, Danger.copy(alpha = 0.1f))
        "completed" -> Triple("COMPLETED", Text2, Bg)
        else -> Triple("UPCOMING", Blue, BlueTint)
    }
    Box(Modifier.clip(RoundedCornerShape(999.dp)).background(bg).padding(horizontal = 9.dp, vertical = 4.dp)) {
        Text(label, color = fg, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
    }
}

private sealed interface DetailState {
    data object Loading : DetailState
    data object Failed : DetailState
    data class Loaded(val t: Tournament) : DetailState
}

@Composable
fun TournamentDetailScreen(tournamentId: String, onBack: () -> Unit) {
    val context = LocalContext.current
    var state by remember { mutableStateOf<DetailState>(DetailState.Loading) }
    var reload by remember { mutableStateOf(0) }

    LaunchedEffect(tournamentId, reload) {
        state = DetailState.Loading
        val t = TournamentRepository().fetch(TokenStore.getToken(context), tournamentId)
        state = if (t == null) DetailState.Failed else DetailState.Loaded(t)
    }

    Box(Modifier.fillMaxSize().background(Bg)) {
        when (val s = state) {
            DetailState.Loading -> Box(Modifier.fillMaxSize(), Alignment.Center) {
                CircularProgressIndicator(color = Blue, modifier = Modifier.size(28.dp))
            }
            DetailState.Failed -> Column(
                Modifier.fillMaxSize().padding(32.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text("Couldn't load this tournament", color = Text1, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(6.dp))
                Text("Check your connection and try again.", color = Text2, fontSize = 14.sp)
                Spacer(Modifier.height(18.dp))
                Box(
                    Modifier.clip(RoundedCornerShape(12.dp)).background(Blue)
                        .pressable { reload++ }.padding(horizontal = 22.dp, vertical = 11.dp),
                ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
            }
            is DetailState.Loaded -> TournamentContent(s.t)
        }

        // Back floats over the banner, so it stays reachable on every state.
        Box(
            Modifier
                .statusBarsPadding()
                .padding(12.dp)
                .size(40.dp)
                .clip(CircleShape)
                .background(Color.Black.copy(alpha = 0.45f))
                .pressable(onClick = onBack),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Color.White, modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun TournamentContent(t: Tournament) {
    val context = LocalContext.current

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).navigationBarsPadding()) {
        Box(Modifier.fillMaxWidth()) {
            Box(Modifier.fillMaxWidth().aspectRatio(16f / 9f).background(BannerFallback)) {
                if (t.banner != null) {
                    HaraanImage(
                        model = ApiConfig.mediaUrl(t.banner),
                        contentDescription = "${t.name} banner",
                        modifier = Modifier.fillMaxSize(),
                    )
                }
            }
            Box(
                Modifier
                    .align(Alignment.BottomStart)
                    .offset(x = 16.dp, y = 40.dp)
                    .size(84.dp)
                    .clip(CircleShape)
                    .background(Surface)
                    .padding(3.dp)
                    .clip(CircleShape)
                    .background(BlueTint),
                contentAlignment = Alignment.Center,
            ) {
                if (t.logo != null) {
                    HaraanImage(
                        model = ApiConfig.mediaUrl(t.logo),
                        contentDescription = "${t.name} logo",
                        modifier = Modifier.fillMaxSize(),
                    )
                } else {
                    Icon(Icons.Filled.EmojiEvents, null, tint = Blue, modifier = Modifier.size(34.dp))
                }
            }
        }

        Row(
            Modifier.fillMaxWidth().padding(start = 112.dp, end = 16.dp).height(48.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TournamentPhasePill(t.phase)
            Spacer(Modifier.width(6.dp))
            Box(Modifier.clip(RoundedCornerShape(999.dp)).background(BlueTint).padding(horizontal = 9.dp, vertical = 4.dp)) {
                Text(t.sportLabel.uppercase(), color = Blue, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
            }
            Spacer(Modifier.width(6.dp))
            Box(Modifier.clip(RoundedCornerShape(999.dp)).background(Bg).padding(horizontal = 9.dp, vertical = 4.dp)) {
                Text(
                    t.categoryLabel.uppercase(), color = Text2, fontSize = 10.sp, fontWeight = FontWeight.Bold,
                    letterSpacing = 0.6.sp, maxLines = 1,
                )
            }
        }

        Column(Modifier.padding(horizontal = 16.dp)) {
            Spacer(Modifier.height(8.dp))
            Text(t.name, color = Text1, fontSize = 23.sp, fontWeight = FontWeight.Bold, lineHeight = 29.sp, letterSpacing = (-0.3).sp)
            t.hostUsername?.let {
                Spacer(Modifier.height(4.dp))
                Text("Hosted by @$it", color = Text3, fontSize = 13.sp)
            }

            Spacer(Modifier.height(16.dp))
            FactLine(Icons.Filled.CalendarMonth, tournamentDateRange(t.startDate, t.endDate))
            Spacer(Modifier.height(8.dp))
            FactLine(
                Icons.Filled.LocationOn,
                listOfNotNull(t.venue, t.city, t.district?.takeIf { !it.equals(t.city, true) }).joinToString(" · "),
            )

            Spacer(Modifier.height(22.dp))
            // Worded per sport by the server — overs and ball for cricket, halves for
            // football, events and shuttle for badminton — so one card draws them all.
            SectionCard("Format") {
                t.formatDetails.forEach { (label, value) -> InfoRow(label, value) }
            }

            Spacer(Modifier.height(14.dp))
            val isTeam = t.entryNoun == "team"
            SectionCard(if (isTeam) "Teams and draw" else "Entries and draw") {
                InfoRow("Structure", t.structureLabel)
                t.teamsCount?.let { InfoRow(if (isTeam) "Teams" else "Entries", "$it") }
                InfoRow("Entry fee", t.entryFee?.takeIf { it > 0 }?.let { "₹${formatRupees(it)} per ${t.entryNoun}" } ?: "Free")
                t.prizePool?.let { InfoRow("Prizes", it) }
            }

            t.description?.let { about ->
                Spacer(Modifier.height(14.dp))
                SectionCard("About") {
                    Text(about, color = Text1, fontSize = 14.sp, lineHeight = 21.sp)
                }
            }

            Spacer(Modifier.height(14.dp))
            OrganiserCard(t)

            Spacer(Modifier.height(12.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .pressable(haptic = Feel.SELECT) { shareTournament(context, t) }
                    .clip(RoundedCornerShape(14.dp))
                    .border(1.dp, Stroke, RoundedCornerShape(14.dp))
                    .padding(vertical = 13.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Share, null, tint = Text1, modifier = Modifier.size(17.dp))
                Spacer(Modifier.width(8.dp))
                Text("Share tournament", color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun FactLine(icon: ImageVector, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = Text3, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(8.dp))
        Text(text, color = Text1, fontSize = 14.5.sp, fontWeight = FontWeight.Medium)
    }
}

@Composable
private fun SectionCard(title: String, content: @Composable () -> Unit) {
    Column(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp)).padding(16.dp),
    ) {
        Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(8.dp))
        content()
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Text(label, color = Text3, fontSize = 14.sp, modifier = Modifier.width(110.dp))
        Text(value, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun OrganiserCard(t: Tournament) {
    val context = LocalContext.current
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp)).padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("ORGANISER", color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.8.sp)
            Spacer(Modifier.height(4.dp))
            Text(t.organizerName, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Text("+91 ${t.organizerPhone.chunked(5).joinToString(" ")}", color = Text2, fontSize = 13.5.sp)
        }
        // Green: a call is the thing landing, not a step forward in a flow.
        Row(
            Modifier
                .pressable(haptic = Feel.SELECT) {
                    runCatching {
                        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:+91${t.organizerPhone}")))
                    }.onFailure {
                        Toast.makeText(context, "No phone app to place the call.", Toast.LENGTH_SHORT).show()
                    }
                }
                .clip(RoundedCornerShape(12.dp))
                .background(GreenTint)
                .border(BorderStroke(1.dp, Green.copy(alpha = 0.35f)), RoundedCornerShape(12.dp))
                .padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Call, null, tint = Green, modifier = Modifier.size(17.dp))
            Spacer(Modifier.width(6.dp))
            Text("Call", color = Green, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        }
    }
}

/** Indian grouping: 100000 → "1,00,000". */
private fun formatRupees(amount: Int): String {
    val s = amount.toString()
    if (s.length <= 3) return s
    val last3 = s.takeLast(3)
    val rest = s.dropLast(3).reversed().chunked(2).joinToString(",").reversed()
    return "$rest,$last3"
}

private fun shareTournament(context: android.content.Context, t: Tournament) {
    val text = buildString {
        append(t.name).append(" · ").append(t.sportLabel).append('\n')
        append(tournamentDateRange(t.startDate, t.endDate)).append(" · ").append(t.city).append('\n')
        append(t.formatLabel).append(" · ").append(t.structureLabel).append('\n')
        t.entryFee?.takeIf { it > 0 }?.let { append("Entry ₹").append(formatRupees(it)).append(" per team\n") }
        t.prizePool?.let { append("Prizes: ").append(it).append('\n') }
        append("Enter: ").append(t.organizerName).append(" +91 ").append(t.organizerPhone)
    }
    runCatching {
        context.startActivity(
            Intent.createChooser(
                Intent(Intent.ACTION_SEND).setType("text/plain").putExtra(Intent.EXTRA_TEXT, text),
                "Share tournament",
            ),
        )
    }
}
