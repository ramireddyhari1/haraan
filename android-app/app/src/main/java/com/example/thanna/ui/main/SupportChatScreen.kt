package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Chat
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.BusinessCenter
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
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
        // Extra bottom padding is the green the content below straddles.
        .padding(start = 8.dp, end = 8.dp, top = 10.dp, bottom = 26.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      IconButton(onClick = onClose) {
        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Color.White)
      }
      Column(modifier = Modifier.weight(1f).padding(start = 4.dp)) {
        Text("Haraan Support", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 17.sp)
        Spacer(Modifier.height(2.dp))
        // Cheapest trust signal in support UI: say someone is actually there.
        Row(verticalAlignment = Alignment.CenterVertically) {
          Box(
            modifier = Modifier
              .size(6.dp)
              .clip(RoundedCornerShape(3.dp))
              .background(HaraanColors.GameHubGreen)
          )
          Spacer(Modifier.width(6.dp))
          Text(
            "Team online · replies in a few hours",
            color = Color.White.copy(alpha = 0.85f),
            fontSize = 12.sp
          )
        }
      }
    }

    // Straddle the seam so the header reads as a band the sheet sits on, not a
    // flat slab the content butts against.
    Box(
      modifier = Modifier
        .weight(1f)
        .fillMaxWidth()
        .offset(y = (-16).dp)
        .clip(RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp))
        .background(HaraanColors.Background)
    ) {
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
          val (chipIcon, chipAccent) = topicIconFor(chosen.iconKey)
          Row(
            modifier = Modifier
              .clip(RoundedCornerShape(14.dp))
              .background(chipAccent.copy(alpha = 0.10f))
              .padding(horizontal = 10.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Icon(chipIcon, contentDescription = null, tint = chipAccent, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(6.dp))
            Text(chosen.label, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = chipAccent)
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
 * Vector + accent tint for an admin-chosen icon key. Unknown keys (a topic added
 * after this build shipped) degrade to a neutral chat bubble instead of a gap.
 */
private fun topicIconFor(key: String): Pair<ImageVector, Color> = when (key) {
  "ticket"  -> Icons.Filled.ConfirmationNumber to Color(0xFF2563EB)
  "card"    -> Icons.Filled.CreditCard to Color(0xFF0F766E)
  "cricket" -> Icons.Filled.SportsCricket to Color(0xFFB45309)
  "venue"   -> Icons.Filled.LocationOn to Color(0xFFC2410C)
  "account" -> Icons.Filled.Person to Color(0xFF6D28D9)
  "partner" -> Icons.Filled.BusinessCenter to Color(0xFFBE185D)
  "event"   -> Icons.Filled.CalendarMonth to Color(0xFF0891B2)
  else      -> Icons.AutoMirrored.Filled.Chat to HaraanColors.TextSecondary
}

/**
 * The pre-chat topic picker. A single-column list, not a grid: the row format
 * earns its space by carrying a subtitle, and the subtitle is what stops users
 * picking the wrong topic. "Something else" is a real row, not a bare link — a
 * user whose issue we didn't anticipate must never feel stuck.
 */
@Composable
private fun TopicPicker(
  categories: List<SupportCategoryItem>,
  onPick: (SupportCategoryItem) -> Unit,
  onSkip: () -> Unit,
) {
  LazyColumn(
    modifier = Modifier.fillMaxSize(),
    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 20.dp, bottom = 24.dp)
  ) {
    item {
      Column(modifier = Modifier.padding(bottom = 14.dp)) {
        Text(
          "What do you need help with?",
          fontWeight = FontWeight.Bold,
          fontSize = 20.sp,
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

    // One card, hairline-divided rows — a stack of six separate cards at this
    // size reads as clutter, not as premium.
    item {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(18.dp))
          .background(HaraanColors.Surface)
      ) {
        categories.forEachIndexed { index, category ->
          TopicRow(category = category, onClick = { onPick(category) })
          if (index != categories.lastIndex) {
            HorizontalDivider(
              color = HaraanColors.BorderLight,
              thickness = 0.7.dp,
              modifier = Modifier.padding(start = 68.dp)
            )
          }
        }
      }
    }

    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(top = 16.dp)
          .clip(RoundedCornerShape(16.dp))
          .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(16.dp))
          .clickable { onSkip() }
          .padding(vertical = 15.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Icon(
          Icons.AutoMirrored.Filled.Chat,
          contentDescription = null,
          tint = HaraanColors.TextSecondary,
          modifier = Modifier.size(18.dp)
        )
        Spacer(Modifier.width(8.dp))
        Text(
          "Something else",
          fontSize = 14.sp,
          fontWeight = FontWeight.SemiBold,
          color = HaraanColors.TextPrimary
        )
      }
    }

    item {
      Text(
        "Most issues are resolved the same day",
        fontSize = 11.sp,
        color = HaraanColors.TextMuted,
        textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        modifier = Modifier.fillMaxWidth().padding(top = 16.dp)
      )
    }
  }
}

@Composable
private fun TopicRow(category: SupportCategoryItem, onClick: () -> Unit) {
  val (icon, accent) = topicIconFor(category.iconKey)
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .clickable { onClick() }
      .padding(horizontal = 14.dp, vertical = 13.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    // Tinted container: one stroke weight, one colour logic, brand-owned.
    Box(
      modifier = Modifier
        .size(40.dp)
        .clip(RoundedCornerShape(11.dp))
        .background(accent.copy(alpha = 0.10f)),
      contentAlignment = Alignment.Center
    ) {
      Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(21.dp))
    }
    Spacer(Modifier.width(14.dp))
    Column(modifier = Modifier.weight(1f)) {
      Text(
        category.label,
        fontSize = 15.sp,
        fontWeight = FontWeight.SemiBold,
        color = HaraanColors.TextPrimary
      )
      category.subtitle?.let {
        Spacer(Modifier.height(1.dp))
        Text(it, fontSize = 12.sp, color = HaraanColors.TextSecondary, lineHeight = 16.sp)
      }
    }
    Spacer(Modifier.width(8.dp))
    Icon(
      Icons.AutoMirrored.Filled.KeyboardArrowRight,
      contentDescription = null,
      tint = HaraanColors.TextMuted,
      modifier = Modifier.size(20.dp)
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
