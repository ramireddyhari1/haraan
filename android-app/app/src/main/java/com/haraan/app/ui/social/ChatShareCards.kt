package com.haraan.app.ui.social

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.SharedCard
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.delay

/**
 * A shared match, venue or player, drawn inside a chat bubble.
 *
 * A white card on either bubble colour, the way a link preview sits in a messenger, so it
 * reads as "a thing someone passed you" rather than more of their words. A LIVE match re-reads
 * itself every 15 seconds while it is on screen — the score in the chat moves with the game.
 *
 * [onOpen] null makes it inert (the pending bubble, where a tap means "retry").
 */
@Composable
internal fun SharedCardView(
    card: SharedCard,
    onOpen: ((SharedCard) -> Unit)?,
    refresh: (suspend (SharedCard) -> SharedCard?)? = null,
) {
    var shown by remember(card) { mutableStateOf(card) }
    if (shown.live && refresh != null) {
        LaunchedEffect(shown.type, shown.id) {
            while (true) {
                delay(15_000)
                refresh(shown)?.let { shown = it }
                if (!shown.live) break
            }
        }
    }

    val shape = RoundedCornerShape(12.dp)
    Column(
        Modifier
            .width(232.dp)
            .clip(shape)
            .background(Color.White)
            .border(1.dp, HaraanColors.BorderLight, shape)
            .then(
                if (onOpen != null && shown.available) {
                    Modifier.pressable(haptic = Feel.SELECT) { onOpen(shown) }
                } else {
                    Modifier
                }
            ),
    ) {
        if (!shown.available) {
            UnavailableCard(shown)
            return@Column
        }
        when (shown.type) {
            "venue" -> VenueBody(shown)
            "player" -> PlayerBody(shown)
            else -> MatchBody(shown)
        }
        Row(
            Modifier
                .fillMaxWidth()
                .background(HaraanColors.Field.copy(alpha = 0.6f))
                .padding(horizontal = 12.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                when (shown.type) {
                    "venue" -> "View venue"
                    "player" -> "View profile"
                    else -> if (shown.live) "Watch live" else "View match"
                },
                color = HaraanColors.EventsBlue,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.weight(1f),
            )
            Icon(
                Icons.AutoMirrored.Filled.KeyboardArrowRight,
                null,
                tint = HaraanColors.EventsBlue,
                modifier = Modifier.size(18.dp),
            )
        }
    }
}

@Composable
private fun MatchBody(card: SharedCard) {
    Column(Modifier.padding(start = 12.dp, end = 12.dp, top = 11.dp, bottom = 12.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                card.sport.ifBlank { "match" }.uppercase(),
                color = HaraanColors.TextMuted,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 0.6.sp,
                modifier = Modifier.weight(1f),
            )
            card.badge?.let { MatchBadge(it) }
        }
        Spacer(Modifier.height(6.dp))
        Text(
            card.title,
            color = HaraanColors.TextPrimary,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            lineHeight = 19.sp,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        if (card.line.isNotBlank()) {
            Spacer(Modifier.height(4.dp))
            Text(
                card.line,
                color = HaraanColors.TextPrimary,
                fontSize = 20.sp,
                fontWeight = FontWeight.ExtraBold,
                maxLines = 1,
            )
        }
        // A finished match leads with its result; otherwise where and when.
        val foot = card.note?.takeIf { it.isNotBlank() } ?: card.subtitle
        if (foot.isNotBlank()) {
            Spacer(Modifier.height(4.dp))
            Text(
                foot,
                color = if (card.note != null) HaraanColors.TextSecondary else HaraanColors.TextMuted,
                fontSize = 12.5.sp,
                fontWeight = if (card.note != null) FontWeight.SemiBold else FontWeight.Normal,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

/** LIVE carries the red dot every scoreboard uses; the others stay quiet. */
@Composable
private fun MatchBadge(badge: String) {
    val live = badge == "LIVE"
    Row(
        Modifier
            .clip(RoundedCornerShape(50))
            .background(
                when (badge) {
                    "LIVE" -> HaraanColors.DangerTint
                    "UPCOMING" -> HaraanColors.EventsBlue.copy(alpha = 0.08f)
                    else -> HaraanColors.Field
                }
            )
            .padding(horizontal = 7.dp, vertical = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (live) {
            Box(Modifier.size(6.dp).clip(CircleShape).background(HaraanColors.Danger))
            Spacer(Modifier.width(4.dp))
        }
        Text(
            badge,
            color = when (badge) {
                "LIVE" -> HaraanColors.Danger
                "UPCOMING" -> HaraanColors.EventsBlue
                else -> HaraanColors.TextSecondary
            },
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
        )
    }
}

@Composable
private fun VenueBody(card: SharedCard) {
    if (card.image != null) {
        AsyncImage(
            model = card.image,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxWidth().height(112.dp).background(HaraanColors.Field),
        )
    } else {
        Box(
            Modifier.fillMaxWidth().height(72.dp).background(HaraanColors.Field),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.Place, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(24.dp))
        }
    }
    Column(Modifier.padding(start = 12.dp, end = 12.dp, top = 10.dp, bottom = 12.dp)) {
        Text(
            card.title,
            color = HaraanColors.TextPrimary,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        if (card.subtitle.isNotBlank()) {
            Text(
                card.subtitle,
                color = HaraanColors.TextMuted,
                fontSize = 12.5.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (card.line.isNotBlank() || card.badge != null) {
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    card.line,
                    color = HaraanColors.TextPrimary,
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                card.badge?.let {
                    Text(it, color = HaraanColors.TextSecondary, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun PlayerBody(card: SharedCard) {
    Row(
        Modifier.padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        CardAvatar(card.image, card.title, 46.dp)
        Spacer(Modifier.width(11.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    card.title,
                    color = HaraanColors.TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (card.badge == "VERIFIED") {
                    Spacer(Modifier.width(4.dp))
                    Icon(Icons.Filled.Verified, "Verified", tint = HaraanColors.EventsBlue, modifier = Modifier.size(15.dp))
                }
            }
            if (card.subtitle.isNotBlank()) {
                Text(
                    card.subtitle,
                    color = HaraanColors.TextMuted,
                    fontSize = 12.5.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

/**
 * Something the reader may not see — a private match they're not in, a venue taken down.
 * Only the neutral title the server sent, and why, so the message keeps its place without
 * leaking what it pointed at.
 */
@Composable
private fun UnavailableCard(card: SharedCard) {
    Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(34.dp).clip(CircleShape).background(HaraanColors.Field),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.Lock, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(16.dp))
        }
        Spacer(Modifier.width(10.dp))
        Column {
            Text(card.title, color = HaraanColors.TextSecondary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
            Text(
                when (card.type) {
                    "match" -> if (card.title == "Private match") "Only its players can see it." else "It's no longer on Haraan."
                    "venue" -> "It's not taking bookings on Haraan."
                    else -> "This profile isn't available."
                },
                color = HaraanColors.TextMuted,
                fontSize = 12.sp,
            )
        }
    }
}

@Composable
private fun CardAvatar(url: String?, name: String, size: androidx.compose.ui.unit.Dp) {
    Box(
        Modifier.size(size).clip(CircleShape).background(HaraanColors.EventsBlue.copy(alpha = 0.12f)),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            name.trim().take(1).uppercase().ifBlank { "?" },
            color = HaraanColors.EventsBlue,
            fontSize = (size.value * 0.4f).sp,
            fontWeight = FontWeight.Bold,
        )
        if (url != null) {
            AsyncImage(
                model = url,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.size(size).clip(CircleShape),
            )
        }
    }
}

/**
 * The + sheet: pick a match, venue or player to drop into the conversation.
 *
 * Every row is the same card the server will send, built by the same code — so what you
 * pick is exactly what the other person sees, and the picker can't offer something they
 * would then be refused (a private match you're not in never appears).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun SharePicker(
    search: suspend (type: String, q: String) -> List<SharedCard>,
    onPick: (SharedCard) -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var type by remember { mutableStateOf("match") }
    var query by remember { mutableStateOf("") }
    var results by remember { mutableStateOf<List<SharedCard>?>(null) }

    LaunchedEffect(type, query) {
        // Typing waits for a pause; switching tabs answers at once.
        if (query.isNotBlank()) delay(300)
        results = null
        results = search(type, query)
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().padding(bottom = 16.dp)) {
            Text(
                "Share in chat",
                color = HaraanColors.TextPrimary,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(horizontal = 20.dp, vertical = 4.dp),
            )
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.padding(horizontal = 20.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                listOf("match" to "Matches", "venue" to "Venues", "player" to "Players").forEach { (key, label) ->
                    val selected = type == key
                    Box(
                        Modifier
                            .clip(RoundedCornerShape(50))
                            .background(if (selected) HaraanColors.EventsBlue else HaraanColors.Field)
                            .pressable { if (type != key) { type = key; query = "" } }
                            .padding(horizontal = 14.dp, vertical = 7.dp),
                    ) {
                        Text(
                            label,
                            color = if (selected) Color.White else HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
            }
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier
                    .padding(horizontal = 20.dp)
                    .fillMaxWidth()
                    .height(44.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(HaraanColors.Field)
                    .padding(horizontal = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Search, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(19.dp))
                Spacer(Modifier.width(8.dp))
                Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
                    if (query.isEmpty()) {
                        Text(
                            when (type) {
                                "venue" -> "Search venues or areas"
                                "player" -> "Search players"
                                else -> "Search teams"
                            },
                            color = HaraanColors.TextMuted,
                            fontSize = 14.5.sp,
                        )
                    }
                    BasicTextField(
                        value = query,
                        onValueChange = { query = it.take(60) },
                        singleLine = true,
                        textStyle = androidx.compose.ui.text.TextStyle(color = HaraanColors.TextPrimary, fontSize = 14.5.sp),
                        cursorBrush = SolidColor(HaraanColors.EventsBlue),
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
            Spacer(Modifier.height(6.dp))

            val list = results
            Box(Modifier.fillMaxWidth().heightIn(min = 220.dp, max = 520.dp)) {
                when {
                    list == null -> Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = HaraanColors.EventsBlue, strokeWidth = 2.5.dp, modifier = Modifier.size(26.dp))
                    }
                    list.isEmpty() -> Text(
                        when {
                            query.isNotBlank() -> "Nothing found for \"${query.trim()}\"."
                            type == "match" -> "Matches you create or play in show here. Search to find other public matches."
                            type == "player" -> "Players who follow you back show here. Search to find anyone else."
                            else -> "No venues to show."
                        },
                        color = HaraanColors.TextSecondary,
                        fontSize = 13.5.sp,
                        lineHeight = 19.sp,
                        modifier = Modifier.padding(horizontal = 20.dp, vertical = 20.dp),
                    )
                    else -> LazyColumn {
                        items(list, key = { it.type + ":" + it.id }) { card ->
                            PickerRow(card) { onPick(card) }
                        }
                    }
                }
            }
        }
    }
}

/** One row in the picker: the card condensed to a line, with a Send arrow. */
@Composable
private fun PickerRow(card: SharedCard, onPick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .pressable(haptic = Feel.COMMIT, onClick = onPick)
            .padding(horizontal = 20.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        when (card.type) {
            "venue" -> Box(
                Modifier.size(44.dp).clip(RoundedCornerShape(10.dp)).background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Place, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(18.dp))
                if (card.image != null) {
                    AsyncImage(
                        model = card.image,
                        contentDescription = null,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.size(44.dp).clip(RoundedCornerShape(10.dp)),
                    )
                }
            }
            "player" -> CardAvatar(card.image, card.title, 44.dp)
            else -> Box(
                Modifier.size(44.dp).clip(RoundedCornerShape(10.dp)).background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    card.sport.take(3).uppercase().ifBlank { "VS" },
                    color = HaraanColors.TextSecondary,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.ExtraBold,
                )
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                card.title,
                color = HaraanColors.TextPrimary,
                fontSize = 14.5.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            val second = listOf(card.line, card.subtitle).filter { it.isNotBlank() }.joinToString(" · ")
            if (second.isNotBlank()) {
                Text(
                    second,
                    color = HaraanColors.TextMuted,
                    fontSize = 12.5.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        if (card.type == "match" && card.badge != null) {
            Spacer(Modifier.width(8.dp))
            MatchBadge(card.badge)
        }
    }
}
