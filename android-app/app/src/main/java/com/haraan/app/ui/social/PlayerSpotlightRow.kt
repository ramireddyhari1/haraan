package com.haraan.app.ui.social

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
    // The initial sits UNDER the photo: a failed or missing image draws nothing, so the
    // card falls back to the letter instead of an empty grey block.
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(168.dp)
        .clip(PhotoShape)
        .background(HaraanColors.Field),
      contentAlignment = Alignment.Center,
    ) {
      Text(
        text = player.name.take(1).uppercase(),
        color = HaraanColors.TextMuted,
        fontSize = 44.sp,
        fontWeight = FontWeight.Bold,
      )
      ApiConfig.mediaUrl(player.avatar)?.let { url ->
        AsyncImage(
          model = url,
          contentDescription = player.name,
          contentScale = ContentScale.Crop,
          // Faces sit high in a portrait; keep the head, crop the legs.
          alignment = Alignment.TopCenter,
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
    // Runs and wickets are cricket's numbers; any other sport's player gets their match count.
    val sport = sportKey(player.primarySport)
    if (sport == null || sport == "cricket") {
      Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        StatText("R", card.runs)
        StatText("W", card.wickets)
      }
    } else {
      Text(
        text = "${player.matches} ${if (player.matches == 1) "match" else "matches"}",
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
      .clip(CircleShape)
      .background(Color.White.copy(alpha = 0.94f))
      .padding(start = 6.dp, end = 9.dp, top = 4.dp, bottom = 4.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(4.dp),
  ) {
    SportGlyph(sport = sport, tint = HaraanColors.EventsBlue, size = 14.dp)
    Text(
      text = sport.replace('_', ' ').uppercase(),
      color = HaraanColors.TextPrimary,
      fontSize = 10.sp,
      fontWeight = FontWeight.Bold,
      letterSpacing = 0.4.sp,
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
