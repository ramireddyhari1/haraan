package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
  var input by remember { mutableStateOf("") }
  var loading by remember { mutableStateOf(true) }
  var sending by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }

  // Initial load, then quiet polling while the screen is on-screen.
  LaunchedEffect(token) {
    runCatching { repo.getThread(token) }
      .onSuccess { messages = it.messages; error = null }
      .onFailure { error = it.message }
    loading = false
    while (true) {
      delay(4000)
      runCatching { repo.getThread(token) }.getOrNull()?.let { messages = it.messages }
    }
  }

  // Keep the newest message in view.
  LaunchedEffect(messages.size) {
    if (messages.isNotEmpty()) listState.animateScrollToItem(messages.size - 1)
  }

  fun send() {
    val text = input.trim()
    if (text.isEmpty() || sending) return
    sending = true
    scope.launch {
      runCatching { repo.sendMessage(token, text) }
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
            "Send us a message about bookings, matches or your account and the team will get back to you.",
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

    // Composer
    Row(
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
    }
  }
}
