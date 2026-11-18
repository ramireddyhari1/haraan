package com.haraan.app.ui.social

import androidx.compose.foundation.Image
import androidx.compose.ui.res.painterResource
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.layout
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.PlayerRepository
import com.haraan.app.data.PlayerSpotlight
import com.haraan.app.data.SpotlightPlayer
import com.haraan.app.ui.membership.MemberTier
import com.haraan.app.ui.membership.MemberTierChip
import com.haraan.app.ui.membership.MemberTierStyles
import com.haraan.app.ui.membership.SportGlyph
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch

private val CardShape = RoundedCornerShape(26.dp)
private val PhotoShape = RoundedCornerShape(18.dp)

/**
 * "Players to follow" — a sideways row of tall player cards (photo, name, R/W, Follow) on the
 * ActionBoard Live tab and the Home feed. Heading, order, who shows and whether the row shows
 * at all come from /control via [PlayerSpotlight]; an empty row draws nothing.
 *
 * Follow is optimistic and rolls back when the server didn't take it, the same contract as
 * search. A guest's card has no follow state (`isFollowing == null`), so its button opens the
 * profile instead of firing a call that can only 401.
 *
 * [bleed] widens the row past a parent's side padding so cards scroll edge to edge.
 */
@Composable
fun PlayerSpotlightRow(
  spotlight: PlayerSpotlight,
  repository: PlayerRepository,
  tokenProvider: () -> String?,
  onOpenPlayer: (String) -> Unit,
  modifier: Modifier = Modifier,
  bleed: Dp = 0.dp,
) {
  if (spotlight.players.isEmpty()) return
  val scope = rememberCoroutineScope()
  // Settled follow state per player id, layered over what the row was loaded with.
  val following = remember(spotlight) { mutableStateMapOf<String, Boolean>() }
  val busy = remember(spotlight) { mutableStateMapOf<String, Boolean>() }

  Column(modifier = modifier.fillMaxWidth()) {
    Text(
      text = spotlight.title,
      color = HaraanColors.TextPrimary,
      fontSize = 15.sp,
      fontWeight = FontWeight.ExtraBold,
      modifier = Modifier.padding(start = if (bleed > 0.dp) 0.dp else 16.dp, bottom = 10.dp),
    )
    LazyRow(
      modifier = Modifier.bleedHorizontally(bleed),
      contentPadding = PaddingValues(horizontal = if (bleed > 0.dp) bleed else 16.dp),
      horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
      items(spotlight.players, key = { it.player.playerId }) { card ->
        val id = card.player.playerId
        val isFollowing = following[id] ?: card.player.isFollowing
        PlayerSpotlightCard(
          card = card,
          isFollowing = isFollowing,
          busy = busy[id] == true,
          onOpen = { onOpenPlayer(id) },
          onFollow = follow@{
            val token = tokenProvider()
            if (isFollowing == null || token.isNullOrBlank()) {
              onOpenPlayer(id)
              return@follow
            }
            val target = !isFollowing
            following[id] = target
            busy[id] = true
            scope.launch {
              val settled = repository.setFollowing(token, id, target)
              following[id] = settled ?: isFollowing
              busy.remove(id)
            }
          },
        )
      }
    }
  }
}

@Composable
private fun PlayerSpotlightCard(
  card: SpotlightPlayer,
  isFollowing: Boolean?,
  busy: Boolean,
  onOpen: () -> Unit,
  onFollow: () -> Unit,
) {
  val player = card.player
  // Pro / Hero: their mark over the photo and the card edged in the tier's colour.
  val tier = MemberTier.fromBadge(card.memberBadge)
  val tierStyle = MemberTierStyles.of(tier)
  Column(
    modifier = Modifier
      .width(172.dp)
      .clip(CardShape)
      .background(Color.White)
      .border(if (tierStyle != null) 1.5.dp else 1.dp, tierStyle?.rim ?: HaraanColors.BorderLight, CardShape)
      .clickable(onClick = onOpen)
      .padding(start = 10.dp, end = 10.dp, top = 10.dp, bottom = 12.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(168.dp)
        .clip(PhotoShape)
        .background(HaraanColors.Field),
      contentAlignment = Alignment.Center,
    ) {
      // No photo, or one that won't load: the default player, filling the tile like a real
      // photo. It's a rectangular cut from INSIDE the profile's round artwork
      // (ic_default_player_card), because the round original has white corners that showed
      // when stretched over a square tile.
      val url = ApiConfig.mediaUrl(player.avatar)
      var photoFailed by remember(url) { mutableStateOf(false) }
      if (url == null || photoFailed) {
        Image(
          painter = painterResource(com.haraan.app.R.drawable.ic_default_player_card),
          contentDescription = player.name,
          contentScale = ContentScale.Crop,
          alignment = Alignment.TopCenter,
          modifier = Modifier.fillMaxSize(),
        )
      } else {
        AsyncImage(
          model = url,
          contentDescription = player.name,
          contentScale = ContentScale.Crop,
          // Faces sit high in a portrait; keep the head, crop the legs.
          alignment = Alignment.TopCenter,
          onError = { photoFailed = true },
          modifier = Modifier.fillMaxSize(),
        )
      }
      // Sport on the left, Pro / Hero on the right — one row so the two pills share a centre line.
      Row(
        modifier = Modifier.align(Alignment.TopCenter).fillMaxWidth().padding(8.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        sportKey(player.primarySport)?.let { SportBadge(it) }
        Spacer(Modifier.weight(1f))
        if (tier.isMember) MemberTierChip(tier, compact = true)
      }
    }
    Spacer(Modifier.height(12.dp))
    Text(
      text = player.name.uppercase(),
      color = HaraanColors.TextPrimary,
      fontSize = 13.sp,
      fontWeight = FontWeight.Bold,
      letterSpacing = 0.3.sp,
      maxLines = 1,
      overflow = TextOverflow.Ellipsis,
      textAlign = TextAlign.Center,
    )
    Spacer(Modifier.height(3.dp))
    val sport = sportKey(player.primarySport)
    // Two numbers in the player's own sport, zeros included: cricket R/W, football G/A,
    // basketball PTS/REB, kabaddi PTS/TKL, anything else matches Won/Lost. The server picks
    // them; an older server that doesn't send them falls back to the old R/W or match count.
    if (card.stats.isNotEmpty()) {
      Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        card.stats.take(2).forEach { (label, value) -> StatText(label, value) }
      }
    } else if (sport == null || sport == "cricket") {
      Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        StatText("R", card.runs)
        StatText("W", card.wickets)
      }
    } else {
      Text(
        text = if (player.matches <= 0) "New player" else "${player.matches} ${if (player.matches == 1) "match" else "matches"}",
        color = HaraanColors.TextSecondary,
        fontSize = 13.sp,
        fontWeight = FontWeight.Medium,
      )
    }
    Spacer(Modifier.height(12.dp))
    FollowPill(isFollowing = isFollowing == true, busy = busy, onClick = onFollow)
  }
}

/** "Table Tennis" → "table_tennis", the key [SportGlyph] draws; null when the player has none. */
private fun sportKey(raw: String?): String? =
  raw?.trim()?.lowercase()?.replace(' ', '_')?.takeIf { it.isNotEmpty() && it != "null" }

/** The player's sport over the photo's corner: a drawn glyph and the name, on a white pill. */
@Composable
private fun SportBadge(sport: String, modifier: Modifier = Modifier) {
  Row(
    modifier = modifier
      // Same size as the compact PRO / HERO chip beside it (MemberTierChip compact), so the
      // two tags read as a matched pair rather than one shouting over the other.
      .clip(CircleShape)
      .background(Color.White.copy(alpha = 0.94f))
      .padding(start = 4.dp, end = 7.dp, top = 1.dp, bottom = 1.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(4.dp),
  ) {
    SportGlyph(sport = sport, tint = HaraanColors.EventsBlue, size = 13.dp)
    Text(
      text = sport.replace('_', ' ').uppercase(),
      color = HaraanColors.TextPrimary,
      fontSize = 9.sp,
      lineHeight = 12.sp,
      fontWeight = FontWeight.ExtraBold,
      letterSpacing = 0.6.sp,
      maxLines = 1,
    )
  }
}

@Composable
private fun StatText(label: String, value: Int) {
  Text(
    text = "$label:$value",
    color = HaraanColors.TextSecondary,
    fontSize = 13.sp,
    fontWeight = FontWeight.Medium,
  )
}

/** Solid blue "Follow"; once following, a blue-tint "Following" so it no longer reads as the CTA. */
@Composable
private fun FollowPill(isFollowing: Boolean, busy: Boolean, onClick: () -> Unit) {
  val accent = HaraanColors.EventsBlue
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .height(40.dp)
      .clip(CircleShape)
      .background(if (isFollowing) accent.copy(alpha = 0.10f) else accent)
      .clickable(enabled = !busy, onClick = onClick),
    contentAlignment = Alignment.Center,
  ) {
    Text(
      text = if (isFollowing) "Following" else "Follow",
      color = if (isFollowing) accent else Color.White,
      fontSize = 15.sp,
      fontWeight = FontWeight.SemiBold,
    )
  }
}

/** Lets a child run past its parent's horizontal padding by [bleed] on each side. */
private fun Modifier.bleedHorizontally(bleed: Dp): Modifier =
  if (bleed <= 0.dp) this else layout { measurable, constraints ->
    val extra = (bleed * 2).roundToPx()
    val placeable = measurable.measure(
      constraints.copy(
        minWidth = constraints.maxWidth + extra,
        maxWidth = constraints.maxWidth + extra,
      )
    )
    layout(constraints.maxWidth, placeable.height) { placeable.place(-bleed.roundToPx(), 0) }
  }
