package com.haraan.app.ui.social

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ChatMessage
import com.haraan.app.data.ChatThread
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch

/**
 * The Chat destination: every conversation this player is in.
 *
 * Messaging is gated on a MUTUAL follow, which is why there is no "new message"
 * button here — you start a conversation from the person's profile, where the
 * relationship that permits it is visible. A compose button on this screen would open
 * a picker whose only honest contents are the people you already have threads with.
 */
@Composable
fun ChatListScreen(
    load: suspend () -> com.haraan.app.data.DirectMessageRepository.ThreadsResult,
    onOpenThread: (ChatThread) -> Unit,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var result by remember {
        mutableStateOf<com.haraan.app.data.DirectMessageRepository.ThreadsResult?>(null)
    }

    BackHandler(enabled = true) { onClose() }
    LaunchedEffect(Unit) { result = load() }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White)
            .statusBarsPadding(),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(38.dp).pressable(onClick = onClose).clip(CircleShape)
                    .background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Text("Messages", color = HaraanColors.TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
        }

        when (val r = result) {
            null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = HaraanColors.EventsBlue)
            }
            // A failed call must never be dressed up as "you have no messages".
            is com.haraan.app.data.DirectMessageRepository.ThreadsResult.Failed ->
                Box(Modifier.fillMaxSize().padding(horizontal = 40.dp), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("Couldn't load messages", color = HaraanColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Check your connection and try again.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.5.sp,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            is com.haraan.app.data.DirectMessageRepository.ThreadsResult.Ready -> if (r.threads.isEmpty()) {
                Box(Modifier.fillMaxSize().padding(horizontal = 40.dp), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No messages yet", color = HaraanColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "You can message players who follow you back. Open someone's profile to start.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.5.sp,
                            lineHeight = 19.sp,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            } else {
                LazyColumn(Modifier.fillMaxSize()) {
                    items(r.threads, key = { it.id }) { t -> ThreadRow(t) { onOpenThread(t) } }
                }
            }
        }
    }
}

@Composable
private fun ThreadRow(thread: ChatThread, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .pressable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Avatar(thread.avatar, thread.name, 48.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                thread.name,
                color = HaraanColors.TextPrimary,
                fontSize = 15.sp,
                // Unread threads sit heavier — the list is scanned, not read.
                fontWeight = if (thread.unreadCount > 0) FontWeight.Bold else FontWeight.Medium,
                maxLines = 1,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                thread.lastMessage ?: "Say hello",
                color = if (thread.unreadCount > 0) HaraanColors.TextPrimary else HaraanColors.TextMuted,
                fontSize = 13.sp,
                maxLines = 1,
            )
        }
        if (thread.unreadCount > 0) {
            Spacer(Modifier.width(10.dp))
            Box(Modifier.size(10.dp).clip(CircleShape).background(HaraanColors.EventsBlue))
        }
    }
}

@Composable
private fun Avatar(url: String?, name: String, size: androidx.compose.ui.unit.Dp) {
    Box(
        Modifier.size(size).clip(CircleShape).background(HaraanColors.Field),
        contentAlignment = Alignment.Center,
    ) {
        if (!url.isNullOrBlank()) {
            AsyncImage(
                model = url,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        } else {
            Text(
                name.trim().take(1).uppercase().ifBlank { "?" },
                color = HaraanColors.EventsBlue,
                fontSize = (size.value / 2.6f).sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

/**
 * One conversation.
 *
 * Sending is optimistic — the bubble appears immediately — but a failed send REMOVES
 * it again rather than leaving a message on screen that no one received. A chat that
 * lies about delivery is worse than one that is slow.
 */
@Composable
fun ChatThreadScreen(
    title: String,
    avatar: String?,
    load: suspend () -> List<ChatMessage>,
    send: suspend (String) -> ChatMessage?,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var messages by remember { mutableStateOf<List<ChatMessage>?>(null) }
    var draft by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    var failed by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val listState = rememberLazyListState()

    BackHandler(enabled = true) { onClose() }
    LaunchedEffect(Unit) { messages = load() }

    // Keep the newest message in view as the thread grows.
    LaunchedEffect(messages?.size) {
        val n = messages?.size ?: 0
        if (n > 0) listState.animateScrollToItem(n - 1)
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White)
            .statusBarsPadding()
            .imePadding(),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(38.dp).pressable(onClick = onClose).clip(CircleShape)
                    .background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Avatar(avatar, title, 34.dp)
            Spacer(Modifier.width(10.dp))
            Text(title, color = HaraanColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        }

        Box(Modifier.weight(1f).fillMaxWidth()) {
            when (val list = messages) {
                null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = HaraanColors.EventsBlue)
                }
                else -> LazyColumn(
                    state = listState,
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(list, key = { it.id }) { m -> Bubble(m) }
                }
            }
        }

        if (failed) {
            Text(
                "Message not sent. Check your connection.",
                color = HaraanColors.Danger,
                fontSize = 12.5.sp,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp),
                textAlign = TextAlign.Center,
            )
        }

        Row(
            Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            OutlinedTextField(
                value = draft,
                onValueChange = { draft = it; failed = false },
                placeholder = { Text("Message", color = HaraanColors.TextMuted, fontSize = 14.sp) },
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(22.dp),
                maxLines = 4,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = HaraanColors.EventsBlue,
                    unfocusedBorderColor = HaraanColors.BorderLight,
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White,
                ),
            )
            Spacer(Modifier.width(8.dp))
            val canSend = draft.trim().isNotEmpty() && !sending
            Box(
                Modifier
                    .size(46.dp)
                    .pressable(enabled = canSend, haptic = Feel.COMMIT) {
                        val text = draft.trim()
                        draft = ""
                        sending = true
                        scope.launch {
                            val sent = send(text)
                            if (sent == null) {
                                // Put the text back so nothing is silently lost.
                                draft = text
                                failed = true
                            } else {
                                messages = (messages ?: emptyList()) + sent
                            }
                            sending = false
                        }
                    }
                    .clip(CircleShape)
                    .background(if (canSend) HaraanColors.EventsBlue else HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.AutoMirrored.Filled.Send,
                    "Send",
                    tint = if (canSend) Color.White else HaraanColors.TextMuted,
                    modifier = Modifier.size(18.dp),
                )
            }
        }
    }
}

@Composable
private fun Bubble(m: ChatMessage) {
    Row(
        Modifier.fillMaxWidth(),
        horizontalArrangement = if (m.mine) Arrangement.End else Arrangement.Start,
    ) {
        Box(
            Modifier
                .widthIn(max = 280.dp)
                .clip(
                    RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 16.dp,
                        // The squared corner points at its sender, so who said what is
                        // readable without colour alone carrying it.
                        bottomStart = if (m.mine) 16.dp else 4.dp,
                        bottomEnd = if (m.mine) 4.dp else 16.dp,
                    )
                )
                .background(if (m.mine) HaraanColors.EventsBlue else HaraanColors.Field)
                .then(
                    if (m.mine) Modifier
                    else Modifier.border(
                        1.dp,
                        HaraanColors.BorderLight,
                        RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp, bottomStart = 4.dp, bottomEnd = 16.dp),
                    )
                )
                .padding(horizontal = 14.dp, vertical = 10.dp),
        ) {
            Text(
                m.body,
                color = if (m.mine) Color.White else HaraanColors.TextPrimary,
                fontSize = 14.5.sp,
                lineHeight = 20.sp,
            )
        }
    }
}
