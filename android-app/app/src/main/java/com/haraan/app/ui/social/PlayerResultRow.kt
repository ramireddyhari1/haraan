package com.haraan.app.ui.social

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.DiscoveredPlayer
import com.haraan.app.ui.theme.HaraanColors

private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Accent = HaraanColors.EventsBlue
private val Hairline = HaraanColors.Field

/**
 * One player in the directory.
 *
 * The handle leads the second line rather than the district: people search by
 * @handle, so the thing they typed should be the thing they can confirm at a glance.
 */
@Composable
fun PlayerResultRow(
  player: DiscoveredPlayer,
  isFollowing: Boolean,
  isPending: Boolean,
  canFollow: Boolean,
  onToggleFollow: () -> Unit,
  onOpen: () -> Unit,
) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .clickable(onClick = onOpen)
      .padding(horizontal = 20.dp, vertical = 12.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    PlayerAvatar(player)

    Spacer(Modifier.width(12.dp))

    Column(Modifier.weight(1f)) {
      Text(
        text = player.name,
        fontSize = 15.sp,
        fontWeight = FontWeight.SemiBold,
        color = Ink,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis,
      )
      Spacer(Modifier.height(2.dp))
      Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
          text = player.handleOrId,
          fontSize = 13.sp,
          fontWeight = if (player.username != null) FontWeight.Medium else FontWeight.Normal,
          color = if (player.username != null) Accent else Muted,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis,
          modifier = Modifier.weight(1f, fill = false),
        )
        if (player.subtitle.isNotBlank()) {
          Text(
            text = "  ·  " + player.subtitle,
            fontSize = 12.5.sp,
            color = Muted,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
          )
        }
      }
    }

    Spacer(Modifier.width(12.dp))

    FollowPill(
      isFollowing = isFollowing,
      isPending = isPending,
      enabled = canFollow,
      onClick = onToggleFollow,
    )
  }
}

@Composable
private fun PlayerAvatar(player: DiscoveredPlayer) {
  val url = ApiConfig.mediaUrl(player.avatar)

  Box(
    modifier = Modifier
      .size(44.dp)
      .clip(CircleShape)
      .background(Hairline),
    contentAlignment = Alignment.Center,
  ) {
    if (url != null) {
      AsyncImage(
        model = url,
        contentDescription = null,
        modifier = Modifier.size(44.dp).clip(CircleShape),
        contentScale = ContentScale.Crop,
      )
    } else {
      // Initials beat a generic silhouette — the list stays scannable when most
      // players have no photo, which is the normal case.
      Text(
        text = player.name.trim().take(1).uppercase().ifBlank { "?" },
        fontSize = 17.sp,
        fontWeight = FontWeight.Bold,
        color = Muted,
      )
    }
  }
}

/**
 * Follow / Following, as a pill.
 *
 * Following is the low-emphasis state on purpose: once you follow someone the
 * button's job is done, and a screen of filled blue pills is noise.
 */
@Composable
private fun FollowPill(
  isFollowing: Boolean,
  isPending: Boolean,
  enabled: Boolean,
  onClick: () -> Unit,
) {
  val background = when {
    !enabled -> Hairline
    isFollowing -> Color.White
    else -> Accent
  }
  val label = when {
    isFollowing -> "Following"
    else -> "Follow"
  }
  val labelColor = when {
    !enabled -> Muted
    isFollowing -> Muted
    else -> Color.White
  }

  Box(
    modifier = Modifier
      .height(34.dp)
      .width(if (isFollowing) 96.dp else 84.dp)
      .clip(RoundedCornerShape(17.dp))
      .background(background)
      // Followed reads as an outline, not a fill — the loud state is the one you
      // haven't done yet.
      .then(
        if (isFollowing && enabled) {
          Modifier.border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(17.dp))
        } else Modifier
      )
      .clickable(enabled = enabled && !isPending, onClick = onClick),
    contentAlignment = Alignment.Center,
  ) {
    if (isPending) {
      CircularProgressIndicator(
        modifier = Modifier.size(15.dp),
        strokeWidth = 2.dp,
        color = if (isFollowing) Muted else Color.White,
      )
    } else {
      Text(
        text = label,
        fontSize = 13.sp,
        fontWeight = FontWeight.SemiBold,
        color = labelColor,
      )
    }
  }
}
