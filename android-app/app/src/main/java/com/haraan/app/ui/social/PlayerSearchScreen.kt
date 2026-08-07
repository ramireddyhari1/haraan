package com.haraan.app.ui.social

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel

private val Ink = Color(0xFF111827)
private val Muted = Color(0xFF6B7280)
private val Faint = Color(0xFF94A3B8)
private val Accent = Color(0xFF2563EB)
private val Field = Color(0xFFF1F5F9)
private val Hairline = Color(0xFFF1F5F9)

/**
 * Find a player by @handle and follow them.
 *
 * Full-screen rather than a bottom sheet: search is a mode, not a peek — the
 * keyboard is up, the list is long, and half a screen of results behind a scrim
 * is the thing that makes in-app search feel like an afterthought.
 */
@Composable
fun PlayerSearchScreen(
  onClose: () -> Unit,
  onOpenPlayer: (String) -> Unit = {},
  viewModel: PlayerDiscoveryViewModel = viewModel(),
) {
  val state by viewModel.state.collectAsStateWithLifecycle()
  val focus = remember { FocusRequester() }

  BackHandler(onBack = onClose)

  // Opening search should put the cursor in the field. Anything else means the
  // user taps twice to do the one thing this screen is for.
  LaunchedEffect(Unit) { focus.requestFocus() }

  Column(
    modifier = Modifier
      .fillMaxSize()
      .background(Color.White)
      .statusBarsPadding(),
  ) {
    SearchBarRow(
      query = state.query,
      onQueryChange = viewModel::onQueryChange,
      onClear = viewModel::clear,
      onClose = onClose,
      focusRequester = focus,
    )

    Box(Modifier.fillMaxSize()) {
      when {
        // Order matters: an auth problem must win over "no results", or we send
        // someone hunting for a typo that was never there.
        !state.isSignedIn -> Notice(
          title = "Sign in to find players",
          body = "The player directory is only available to signed-in accounts. Sign in to search by @username and follow your teammates.",
        )
        state.sessionExpired -> Notice(
          title = "Your session has expired",
          body = "Sign in again to search the player directory.",
        )
        state.failed -> Notice(
          title = "Couldn't reach Haraan",
          body = "Check your connection and try again.",
        )
        state.isTooShort -> SearchPrompt()
        state.isLoading && state.results.isEmpty() -> LoadingRow()
        state.hasSearched && state.results.isEmpty() -> NoResults(state.query)
        else -> LazyColumn(Modifier.fillMaxSize()) {
          items(state.results, key = { it.playerId }) { player ->
            PlayerResultRow(
              player = player,
              isFollowing = state.isFollowing(player),
              isPending = state.pending.contains(player.playerId),
              canFollow = state.isSignedIn,
              onToggleFollow = { viewModel.toggleFollow(player) },
              onOpen = { onOpenPlayer(player.playerId) },
            )
          }
        }
      }
    }
  }
}

@Composable
private fun SearchBarRow(
  query: String,
  onQueryChange: (String) -> Unit,
  onClear: () -> Unit,
  onClose: () -> Unit,
  focusRequester: FocusRequester,
) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(horizontal = 12.dp, vertical = 10.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Box(
      modifier = Modifier
        .size(40.dp)
        .clip(RoundedCornerShape(12.dp))
        .clickable(onClick = onClose),
      contentAlignment = Alignment.Center,
    ) {
      Icon(Icons.Default.ArrowBack, contentDescription = "Close search", tint = Ink, modifier = Modifier.size(20.dp))
    }

    Spacer(Modifier.width(6.dp))

    Row(
      modifier = Modifier
        .weight(1f)
        .height(44.dp)
        .clip(RoundedCornerShape(14.dp))
        .background(Field)
        .padding(horizontal = 14.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Icon(Icons.Default.Search, contentDescription = null, tint = Faint, modifier = Modifier.size(18.dp))
      Spacer(Modifier.width(10.dp))

      Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
        if (query.isEmpty()) {
          Text("Search players by @username", fontSize = 14.5.sp, color = Faint)
        }
        BasicTextField(
          value = query,
          onValueChange = onQueryChange,
          singleLine = true,
          textStyle = TextStyle(fontSize = 14.5.sp, color = Ink, fontWeight = FontWeight.Medium),
          cursorBrush = SolidColor(Accent),
          // Handles are lowercase, so an auto-capitalised first letter is friction.
          keyboardOptions = KeyboardOptions(
            capitalization = KeyboardCapitalization.None,
            imeAction = ImeAction.Search,
          ),
          modifier = Modifier
            .fillMaxWidth()
            .focusRequester(focusRequester),
        )
      }

      if (query.isNotEmpty()) {
        Box(
          modifier = Modifier.size(20.dp).clickable(onClick = onClear),
          contentAlignment = Alignment.Center,
        ) {
          Icon(Icons.Default.Close, contentDescription = "Clear", tint = Muted, modifier = Modifier.size(16.dp))
        }
      }
    }
  }
}

/** A blocking state that is NOT "we searched and found nothing". */
@Composable
private fun Notice(title: String, body: String) {
  Column(
    modifier = Modifier.fillMaxWidth().padding(horizontal = 32.dp, vertical = 56.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Text(title, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = Ink)
    Spacer(Modifier.height(6.dp))
    Text(body, fontSize = 13.5.sp, color = Muted)
  }
}

/** Before two characters — say what this searches, don't show a blank page. */
@Composable
private fun SearchPrompt() {
  Column(
    modifier = Modifier.fillMaxWidth().padding(horizontal = 32.dp, vertical = 56.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Icon(Icons.Default.Search, contentDescription = null, tint = Color(0xFFCBD5E1), modifier = Modifier.size(40.dp))
    Spacer(Modifier.height(14.dp))
    Text("Find your teammates", fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = Ink)
    Spacer(Modifier.height(6.dp))
    Text(
      text = "Search by @username, name, or Player ID. Follow someone to keep their matches close.",
      fontSize = 13.5.sp,
      color = Muted,
    )
  }
}

@Composable
private fun LoadingRow() {
  Box(Modifier.fillMaxWidth().padding(vertical = 48.dp), contentAlignment = Alignment.Center) {
    CircularProgressIndicator(modifier = Modifier.size(22.dp), strokeWidth = 2.dp, color = Accent)
  }
}

@Composable
private fun NoResults(query: String) {
  Column(
    modifier = Modifier.fillMaxWidth().padding(horizontal = 32.dp, vertical = 56.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Text("No players for “$query”", fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, color = Ink)
    Spacer(Modifier.height(6.dp))
    Text(
      text = "Check the spelling, or try their Player ID. Players who turned off discovery in their privacy settings will not appear.",
      fontSize = 13.sp,
      color = Muted,
    )
  }
}
