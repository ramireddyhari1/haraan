package com.haraan.partner

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics

private val RvInk = Color(0xFF0F172A)
private val RvMuted = Color(0xFF64748B)
private val RvBlue = Color(0xFF2563EB)
private val RvAmber = Color(0xFFF59E0B)
private val RvTrack = Color(0xFFEEF2F7)
private val RvPage = Color(0xFFF7F8FB)

/**
 * What players said about this partner's venues (GET /api/partner/reviews). Read-only —
 * partners never edit a customer's words; Haraan hides or removes reviews in /control.
 * Same screen as the partner web app's: a score card whose 5→1 bars are the star
 * filter, then the feed. Reviews come from the member app, the website and the
 * post-booking link; until this screen the partner app couldn't read any of them.
 */
@Composable
internal fun ReviewsScreen(api: PartnerApi, token: String, venueId: Long?, onBack: () -> Unit) {
    val view = LocalView.current
    var stars by remember { mutableIntStateOf(0) }
    var page by remember { mutableStateOf<ReviewsPage?>(null) }
    var failed by remember { mutableStateOf(false) }

    LaunchedEffect(stars, venueId) {
        failed = false
        runCatching { api.reviews(token, venueId, stars) }
            .onSuccess { page = it }
            .onFailure { failed = true }
    }

    Column(Modifier.fillMaxSize().background(RvPage)) {
        Row(
            Modifier.fillMaxWidth().background(Color.White).statusBarsPadding().padding(horizontal = 4.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = RvInk) }
            Text("Reviews", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = RvInk, modifier = Modifier.weight(1f))
            if (stars != 0) {
                Text(
                    "Show all", color = RvBlue, fontSize = 14.sp, fontWeight = FontWeight.Bold,
                    modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { stars = 0 }.padding(horizontal = 12.dp, vertical = 8.dp),
                )
            }
        }

        val p = page
        when {
            failed && p == null -> Quiet("Couldn't load reviews. Check the connection and try again.")
            p == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { Text("Loading…", color = RvMuted) }
            p.count == 0 -> NoReviews()
            else -> LazyColumn(
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 32.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item { ScoreCard(p, stars) { s -> stars = if (stars == s) 0 else s; Haptics.tick(view) } }
                if (p.reviews.isEmpty()) {
                    item { Text("No $stars-star reviews.", color = RvMuted, fontSize = 14.sp, modifier = Modifier.fillMaxWidth().padding(28.dp), textAlign = TextAlign.Center) }
                }
                items(p.reviews, key = { it.id }) { ReviewCard(it) }
            }
        }
    }
}

@Composable
private fun ScoreCard(p: ReviewsPage, picked: Int, onPick: (Int) -> Unit) {
    val max = (p.spread.values.maxOrNull() ?: 0).coerceAtLeast(1)
    Row(
        Modifier
            .fillMaxWidth()
            .shadow(8.dp, RoundedCornerShape(18.dp), ambientColor = RvBlue.copy(alpha = 0.25f), spotColor = RvBlue.copy(alpha = 0.35f))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.5.dp, RvBlue, RoundedCornerShape(18.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.widthIn(min = 96.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(String.format(java.util.Locale.US, "%.1f", p.average ?: 0.0), fontSize = 40.sp, fontWeight = FontWeight.ExtraBold, color = RvInk, letterSpacing = (-1).sp)
            Stars(Math.round(p.average ?: 0.0).toInt(), 14)
            Spacer(Modifier.height(4.dp))
            Text("${p.count} ${if (p.count == 1) "rating" else "ratings"}", fontSize = 12.sp, color = RvMuted)
            if (p.thisMonth > 0) Text("${p.thisMonth} this month", fontSize = 12.sp, color = Color(0xFF15803D), fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.width(16.dp))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            for (s in 5 downTo 1) {
                val n = p.spread[s] ?: 0
                val frac by animateFloatAsState(n / max.toFloat(), tween(400), label = "bar$s")
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .background(if (picked == s) Color(0xFFFFF7E0) else Color.Transparent)
                        .then(if (n > 0) Modifier.clickable { onPick(s) } else Modifier)
                        .padding(horizontal = 6.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("$s★", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (n > 0) RvInk else RvMuted, modifier = Modifier.width(26.dp))
                    Box(Modifier.weight(1f).height(7.dp).clip(CircleShape).background(RvTrack)) {
                        Box(Modifier.fillMaxHeight().fillMaxWidth(frac).clip(CircleShape).background(RvAmber))
                    }
                    Text("$n", fontSize = 12.sp, color = RvMuted, textAlign = TextAlign.End, modifier = Modifier.width(24.dp))
                }
            }
        }
    }
}

@Composable
private fun ReviewCard(r: ReviewRow) {
    Column(
        Modifier
            .fillMaxWidth()
            .shadow(2.dp, RoundedCornerShape(16.dp))
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(38.dp).clip(CircleShape).background(RvBlue.copy(alpha = 0.08f)), contentAlignment = Alignment.Center) {
                Text(r.name.take(1).uppercase(), color = Color(0xFF1E50E6), fontWeight = FontWeight.Bold, fontSize = 15.sp)
            }
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(r.name, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = RvInk, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                    if (r.played) {
                        Spacer(Modifier.width(6.dp))
                        Text(
                            "Played here", fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF15803D),
                            modifier = Modifier.clip(RoundedCornerShape(5.dp)).background(Color(0xFFECFDF3)).padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Text(listOf(r.ago, r.venue).filter { it.isNotBlank() }.joinToString(" · "), fontSize = 12.sp, color = RvMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Stars(r.rating, 14)
        }
        Spacer(Modifier.height(10.dp))
        if (r.text.isNotBlank()) Text(r.text, fontSize = 14.sp, lineHeight = 21.sp, color = RvInk)
        else Text("Rated ${r.rating} ${if (r.rating == 1) "star" else "stars"}, no comment.", fontSize = 13.sp, color = RvMuted)
    }
}

@Composable
private fun Stars(n: Int, size: Int) {
    Row {
        for (i in 1..5) Icon(Icons.Filled.Star, null, tint = if (i <= n) RvAmber else Color(0xFFE2E8F0), modifier = Modifier.size(size.dp))
    }
}

@Composable
private fun NoReviews() {
    Column(Modifier.fillMaxSize().padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
        Row(verticalAlignment = Alignment.Bottom) {
            Icon(Icons.Filled.Star, null, tint = RvAmber.copy(alpha = 0.35f), modifier = Modifier.size(28.dp))
            Icon(Icons.Filled.Star, null, tint = RvAmber.copy(alpha = 0.75f), modifier = Modifier.size(36.dp).padding(bottom = 4.dp))
            Icon(Icons.Filled.Star, null, tint = RvAmber.copy(alpha = 0.35f), modifier = Modifier.size(28.dp))
        }
        Spacer(Modifier.height(12.dp))
        Text("No ratings yet", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = RvInk)
        Spacer(Modifier.height(6.dp))
        Text(
            "Players rate your venue from its page in the Haraan app and website, and from the link after a booking. They land here the moment they're posted.",
            fontSize = 13.sp, lineHeight = 19.sp, color = RvMuted, textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun Quiet(text: String) {
    Box(Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
        Text(text, color = RvMuted, fontSize = 14.sp, textAlign = TextAlign.Center)
    }
}
