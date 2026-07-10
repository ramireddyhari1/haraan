package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items as gridItems
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.SupportCategoryItem
import com.example.thanna.data.SupportMessageItem
import com.example.thanna.data.SupportRepository
import com.example.thanna.ui.theme.HaraanColors
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * In-app support chat. The user types here and Haraan admins / assigned workers
 * see and reply from the Filament control panel. Loads the conversation on open
 * and polls it every few seconds so admin replies appear without a manual refresh.
 */
@Composable
fun SupportChatScreen(
  token: String,
  onClose: () -> Unit,
) {
  val repo = remember { SupportRepository() }
  val scope = rememberCoroutineScope()
  val listState = rememberLazyListState()

  var messages by remember { mutableStateOf<List<SupportMessageItem>>(emptyList()) }
  var categories by remember { mutableStateOf<List<SupportCategoryItem>>(emptyList()) }
  // The topic the user tapped. `topicChosen` also covers "Something else", which
  // opens the composer with no topic attached.
  var topic by remember { mutableStateOf<SupportCategoryItem?>(null) }
  var topicChosen by remember { mutableStateOf(false) }
  var input by remember { mutableStateOf("") }
  var loading by remember { mutableStateOf(true) }
  var sending by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }

  // Initial load, then quiet polling while the screen is on-screen.
  LaunchedEffect(token) {
    runCatching { repo.getThread(token) }
      .onSuccess { messages = it.messages; error = null }
      .onFailure { error = it.message }
    // Topics are a nicety — a failure here just means the user types freely.
    categories = runCatching { repo.getCategories(token) }.getOrDefault(emptyList())
    loading = false
    while (true) {
      delay(4000)
      // A successful refresh must also clear any earlier "unable to reach" error,
      // otherwise a transient failure on open leaves the banner stuck forever.
      runCatching { repo.getThread(token) }.getOrNull()?.let { messages = it.messages; error = null }
    }
  }

  // The picker is the empty state: it stands in for the conversation until the
  // user names their issue (or opts out), and never returns once messages exist.
  val showPicker = !loading && messages.isEmpty() && !topicChosen && categories.isNotEmpty()

  // Keep the newest message in view.
  LaunchedEffect(messages.size) {
    if (messages.isNotEmpty()) listState.animateScrollToItem(messages.size - 1)
  }

  fun send() {
    val text = input.trim()
    if (text.isEmpty() || sending) return
    sending = true
    scope.launch {
      runCatching { repo.sendMessage(token, text, topic?.id) }
        .onSuccess { messages = it.messages; input = ""; error = null }
        .onFailure { error = it.message ?: "Couldn't send. Try again." }
      sending = false
    }
  }

  Column(
    modifier = Modifier
      .fillMaxSize()
      .background(HaraanColors.Background)
  ) {
    // Top bar
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .background(HaraanColors.GameHubDeep)
        .statusBarsPadding()
        .padding(horizontal = 8.dp, vertical = 10.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      IconButton(onClick = onClose) {
        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Color.White)
      }
      Column(modifier = Modifier.weight(1f).padding(start = 4.dp)) {
        Text("Haraan Support", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 17.sp)
        Text(
          "We usually reply within a few hours",
          color = Color.White.copy(alpha = 0.8f),
          fontSize = 12.sp
        )
      }
    }

    Box(modifier = Modifier.weight(1f).fillMaxWidth()) {
      when {
        loading -> CircularProgressIndicator(
          color = HaraanColors.GameHubDeep,
          modifier = Modifier.align(Alignment.Center)
        )
        showPicker -> TopicPicker(
          categories = categories,
          onPick = { topic = it; topicChosen = true },
          onSkip = { topic = null; topicChosen = true }
        )
        messages.isEmpty() -> Column(
          modifier = Modifier.align(Alignment.Center).padding(32.dp),
          horizontalAlignment = Alignment.CenterHorizontally
        ) {
          Text("👋", fontSize = 40.sp)
          Spacer(Modifier.height(10.dp))
          Text(
            "How can we help?",
            fontWeight = FontWeight.SemiBold,
            fontSize = 16.sp,
            color = HaraanColors.TextPrimary
          )
          Spacer(Modifier.height(4.dp))
          Text(
            topic?.let { "Tell us what's happening with ${it.label.lowercase()} and the team will get back to you." }
              ?: "Send us a message about bookings, matches or your account and the team will get back to you.",
            fontSize = 13.sp,
            color = HaraanColors.TextSecondary,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center
          )
        }
        else -> LazyColumn(
          state = listState,
          modifier = Modifier.fillMaxSize(),
          contentPadding = PaddingValues(16.dp),
          verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
          items(messages, key = { it.id }) { msg -> MessageBubble(msg) }
        }
      }
    }

    error?.let {
      Text(
        it,
        color = HaraanColors.LiveRed,
        fontSize = 12.sp,
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp)
      )
    }

    // Chosen topic, shown until the first message lands (after that the thread
    // header in the control panel carries it and the chip is just clutter).
    if (!showPicker && messages.isEmpty()) {
      topic?.let { chosen ->
        Row(
          modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Row(
            modifier = Modifier
              .clip(RoundedCornerShape(14.dp))
              .background(HaraanColors.GameHubDeep.copy(alpha = 0.10f))
              .padding(horizontal = 10.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            if (chosen.icon.isNotBlank()) {
              Text(chosen.icon, fontSize = 12.sp)
              Spacer(Modifier.width(6.dp))
            }
            Text(chosen.label, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = HaraanColors.GameHubDeep)
          }
          Spacer(Modifier.weight(1f))
          Text(
            "Change",
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = HaraanColors.EventsBlue,
            modifier = Modifier
              .clip(RoundedCornerShape(8.dp))
              .clickable { topic = null; topicChosen = false }
              .padding(horizontal = 8.dp, vertical = 4.dp)
          )
        }
      }
    }

    // Composer — hidden behind the picker so the user names the issue first.
    if (!showPicker) Row(
      modifier = Modifier
        .fillMaxWidth()
        .background(HaraanColors.Surface)
        .navigationBarsPadding()
        .imePadding()
        .padding(horizontal = 12.dp, vertical = 8.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      OutlinedTextField(
        value = input,
        onValueChange = { input = it },
        modifier = Modifier.weight(1f),
        placeholder = { Text("Type a message…") },
        maxLines = 4,
        shape = RoundedCornerShape(22.dp),
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Send),
        keyboardActions = KeyboardActions(onSend = { send() })
      )
      Spacer(Modifier.width(8.dp))
      Box(
        modifier = Modifier
          .size(46.dp)
          .clip(RoundedCornerShape(23.dp))
          .background(if (input.isBlank()) HaraanColors.BorderLight else HaraanColors.GameHubDeep),
        contentAlignment = Alignment.Center
      ) {
        if (sending) {
          CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
        } else {
          IconButton(onClick = { send() }, enabled = input.isNotBlank()) {
            Icon(
              Icons.AutoMirrored.Filled.Send,
              contentDescription = "Send",
              tint = if (input.isBlank()) HaraanColors.TextMuted else Color.White
            )
          }
        }
      }
    }
  }
}

/**
 * The pre-chat topic picker. Two taps at most: pick a card, or fall through to
 * "Something else" — the escape hatch matters, a user with an issue we didn't
 * anticipate must never be stuck staring at a grid.
 */
@Composable
private fun TopicPicker(
  categories: List<SupportCategoryItem>,
  onPick: (SupportCategoryItem) -> Unit,
  onSkip: () -> Unit,
) {
  LazyVerticalGrid(
    columns = GridCells.Fixed(2),
    modifier = Modifier.fillMaxSize(),
    contentPadding = PaddingValues(16.dp),
    horizontalArrangement = Arrangement.spacedBy(12.dp),
    verticalArrangement = Arrangement.spacedBy(12.dp)
  ) {
    item(span = { GridItemSpan(maxLineSpan) }) {
      Column(modifier = Modifier.padding(top = 8.dp, bottom = 4.dp)) {
        Text(
          "What do you need help with?",
          fontWeight = FontWeight.Bold,
          fontSize = 18.sp,
          color = HaraanColors.TextPrimary
        )
        Spacer(Modifier.height(4.dp))
        Text(
          "Pick a topic so we can get you the right person.",
          fontSize = 13.sp,
          color = HaraanColors.TextSecondary
        )
      }
    }

    gridItems(categories, key = { it.id }) { category ->
      TopicCard(
        icon = category.icon,
        label = category.label,
        onClick = { onPick(category) }
      )
    }

    item(span = { GridItemSpan(maxLineSpan) }) {
      Text(
        "Something else",
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = HaraanColors.EventsBlue,
        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(14.dp))
          .clickable { onSkip() }
          .padding(vertical = 14.dp)
      )
    }
  }
}

@Composable
private fun TopicCard(icon: String, label: String, onClick: () -> Unit) {
  Column(
    modifier = Modifier
      .fillMaxWidth()
      .heightIn(min = 104.dp)
      .clip(RoundedCornerShape(16.dp))
      .background(HaraanColors.Surface)
      .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(16.dp))
      .clickable { onClick() }
      .padding(14.dp),
    verticalArrangement = Arrangement.spacedBy(8.dp)
  ) {
    if (icon.isNotBlank()) Text(icon, fontSize = 24.sp)
    Text(
      label,
      fontSize = 14.sp,
      fontWeight = FontWeight.SemiBold,
      color = HaraanColors.TextPrimary,
      lineHeight = 18.sp
    )
  }
}

@Composable
private fun MessageBubble(msg: SupportMessageItem) {
  val fromAdmin = msg.fromAdmin
  Row(
    modifier = Modifier.fillMaxWidth(),
    horizontalArrangement = if (fromAdmin) Arrangement.Start else Arrangement.End
  ) {
    Column(
      modifier = Modifier
        .widthIn(max = 280.dp)
        .clip(
          RoundedCornerShape(
            topStart = 16.dp,
            topEnd = 16.dp,
            bottomStart = if (fromAdmin) 4.dp else 16.dp,
            bottomEnd = if (fromAdmin) 16.dp else 4.dp
          )
        )
        .background(if (fromAdmin) HaraanColors.Surface else HaraanColors.GameHubDeep)
        .padding(horizontal = 14.dp, vertical = 10.dp)
    ) {
      if (fromAdmin) {
        Text("Haraan Team", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = HaraanColors.GameHubDeep)
        Spacer(Modifier.height(2.dp))
      }
      Text(
        msg.body,
        color = if (fromAdmin) HaraanColors.TextPrimary else Color.White,
        fontSize = 14.sp
      )
      val time = formatBubbleTime(msg.createdAt)
      if (time.isNotEmpty()) {
        Spacer(Modifier.height(3.dp))
        Text(
          time,
          fontSize = 10.sp,
          color = if (fromAdmin) HaraanColors.TextMuted else Color.White.copy(alpha = 0.7f),
          modifier = Modifier.align(Alignment.End)
        )
      }
    }
  }
}

/**
 * Format an ISO-8601 timestamp (e.g. "2026-07-10T15:45:00+00:00") into a short
 * local clock time like "3:45 PM". SimpleDateFormat keeps this safe on minSdk 24.
 * Returns "" when the timestamp is missing or unparseable so the caller can skip it.
 */
private fun formatBubbleTime(iso: String?): String {
  if (iso.isNullOrBlank()) return ""
  return runCatching {
    val parser = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.ENGLISH).apply {
      timeZone = java.util.TimeZone.getTimeZone("UTC")
    }
    val parsed = parser.parse(iso) ?: return ""
    java.text.SimpleDateFormat("h:mm a", java.util.Locale.getDefault()).format(parsed)
  }.getOrDefault("")
}
