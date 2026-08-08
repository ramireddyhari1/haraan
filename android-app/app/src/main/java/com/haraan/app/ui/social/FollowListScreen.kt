package com.haraan.app.ui.social

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.DiscoveredPlayer
import com.haraan.app.data.DiscoveryOutcome
import com.haraan.app.ui.pressable
import kotlinx.coroutines.launch
import com.haraan.app.ui.theme.HaraanColors

// Mirrors PlayerSearchScreen's palette. Repeated rather than shared because those are
// file-private there; the two screens are siblings and must not drift apart in colour.
private val Ink = HaraanColors.TextPrimary
private val Muted = HaraanColors.TextSecondary
private val Faint = HaraanColors.TextMuted
private val Accent = HaraanColors.EventsBlue
private val Field = HaraanColors.Field

/** Which side of the graph this screen is showing. */
enum class FollowRelation(val slug: String, val title: String) {
    FOLLOWERS("followers", "Followers"),
    FOLLOWING("following", "Following"),
}

/**
 * Who follows a player, or who they follow.
 *
 * The endpoints for this shipped with the original follow work and nothing ever
 * opened them — the counts on a profile were a dead end. Rows are the SAME
 * [PlayerResultRow] the search screen uses, because the server returns the same player
 * card for both, and two visually different "a player in a list" rows would be a
 * difference with no meaning behind it.
 */
@Composable
fun FollowListScreen(
    playerId: String,
    relation: FollowRelation,
    /** Shown in the header so it reads "Followers of REDDY" rather than a bare title. */
    playerName: String,
    token: String?,
    load: suspend (playerId: String, relation: String) -> DiscoveryOutcome,
    onToggleFollow: suspend (playerId: String, follow: Boolean) -> Boolean?,
    onOpenPlayer: (String) -> Unit,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var outcome by remember(playerId, relation) { mutableStateOf<DiscoveryOutcome?>(null) }
    // Follow state is per-row and optimistic, keyed by player id so a row settling does
    // not disturb its neighbours.
    val overrides = remember(playerId, relation) { mutableStateMapOf<String, Boolean>() }
    val pending = remember(playerId, relation) { mutableStateMapOf<String, Boolean>() }
    val scope = rememberCoroutineScope()

    BackHandler(enabled = true) { onClose() }

    LaunchedEffect(playerId, relation) {
        outcome = null
        outcome = load(playerId, relation.slug)
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White)
            .statusBarsPadding(),
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier
                    .size(38.dp)
                    .pressable(onClick = onClose)
                    .clip(CircleShape)
                    .background(Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Ink, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column {
                Text(relation.title, color = Ink, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                if (playerName.isNotBlank()) {
                    Text(playerName, color = Faint, fontSize = 12.sp, maxLines = 1)
                }
            }
        }

        when (val o = outcome) {
            null -> CenterMessage { CircularProgressIndicator(color = Accent) }

            is DiscoveryOutcome.Unauthorized -> CenterMessage {
                Message("Sign in to see this", "Follower lists are only visible to signed-in players.")
            }

            is DiscoveryOutcome.Failed -> CenterMessage {
                Message("Couldn't load this list", "Check your connection and try again.")
            }

            is DiscoveryOutcome.Success -> {
                if (o.players.isEmpty()) {
                    CenterMessage {
                        Message(
                            if (relation == FollowRelation.FOLLOWERS) "No followers yet" else "Not following anyone yet",
                            if (relation == FollowRelation.FOLLOWERS) {
                                "When someone follows this player they'll show up here."
                            } else {
                                "Players they follow will show up here."
                            },
                        )
                    }
                } else {
                    LazyColumn(Modifier.fillMaxSize()) {
                        items(o.players, key = { it.playerId }) { player ->
                            val following = overrides[player.playerId] ?: (player.isFollowing == true)
                            PlayerResultRow(
                                player = player,
                                isFollowing = following,
                                isPending = pending[player.playerId] == true,
                                canFollow = player.isFollowing != null,
                                onToggleFollow = {
                                    val next = !following
                                    overrides[player.playerId] = next
                                    pending[player.playerId] = true
                                    scope.launch {
                                        val settled = onToggleFollow(player.playerId, next)
                                        // Roll back a request that never landed rather
                                        // than leaving a filled button that lies.
                                        overrides[player.playerId] = settled ?: !next
                                        pending[player.playerId] = false
                                    }
                                },
                                onOpen = { onOpenPlayer(player.playerId) },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CenterMessage(content: @Composable () -> Unit) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { content() }
}

@Composable
private fun Message(title: String, body: String) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
        modifier = Modifier.padding(horizontal = 40.dp),
    ) {
        Text(title, color = Ink, fontSize = 16.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
        Spacer(Modifier.height(6.dp))
        Text(body, color = Muted, fontSize = 13.5.sp, lineHeight = 19.sp, textAlign = TextAlign.Center)
    }
}
