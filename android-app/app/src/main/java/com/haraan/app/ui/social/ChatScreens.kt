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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Group
import androidx.compose.material.icons.filled.GroupAdd
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.LocalTextStyle
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
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
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
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
 *
 * The layout is a modern messaging list — search, an All / Unread filter, and rows that
 * surface the last-message time and a numeric unread badge. Everything shown is real:
 * there is no presence ring or "groups" lane because the backend models neither, and a
 * fabricated one would be worse than its absence.
 *
 * [showBack] is false when this renders as a bottom-bar TAB (the bar stays visible and
 * carries navigation, so a back arrow would be redundant) and true when it is a pushed
 * screen that owns its own way back. Hardware back still calls [onClose] either way.
 */
@Composable
fun ChatListScreen(
    load: suspend () -> com.haraan.app.data.DirectMessageRepository.ThreadsResult,
    onOpenThread: (ChatThread) -> Unit,
    onClose: () -> Unit,
    onNewGroup: () -> Unit = {},
    showBack: Boolean = true,
    // Bumped by the caller after creating or leaving a group, to force a reload so the
    // list reflects the change without the user having to leave and come back.
    reloadKey: Int = 0,
    modifier: Modifier = Modifier,
) {
    var result by remember {
        mutableStateOf<com.haraan.app.data.DirectMessageRepository.ThreadsResult?>(null)
    }
    var query by remember { mutableStateOf("") }
    // false = All, true = Unread only. A two-state filter is honest to our data; a
    // "Groups"/"Others" set would be labels for things that don't exist.
    var unreadOnly by remember { mutableStateOf(false) }

    BackHandler(enabled = true) { onClose() }
    LaunchedEffect(reloadKey) { result = load() }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White)
            // As a tab, the Scaffold body already insets for the status bar; only pad
            // ourselves when we are a standalone pushed screen.
            .then(if (showBack) Modifier.statusBarsPadding() else Modifier),
    ) {
        // ── Title row ────────────────────────────────────────────────────────
        Row(
            Modifier.fillMaxWidth().padding(
                start = if (showBack) 12.dp else 16.dp,
                end = 16.dp,
                top = 8.dp,
                bottom = 6.dp,
            ),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (showBack) {
                Box(
                    Modifier.size(38.dp).pressable(onClick = onClose).clip(CircleShape)
                        .background(HaraanColors.Field),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
                }
                Spacer(Modifier.width(10.dp))
            }
            Text("Messages", color = HaraanColors.TextPrimary, fontSize = 25.sp, fontWeight = FontWeight.ExtraBold)
            Spacer(Modifier.weight(1f))
            // New group — the one honest compose action here: 1:1s start from a profile
            // (mutual-follow rule), but a group is something only this screen can begin.
            Box(
                Modifier.size(40.dp).pressable(onClick = onNewGroup).clip(CircleShape)
                    .background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.GroupAdd, "New group", tint = HaraanColors.EventsBlue, modifier = Modifier.size(20.dp))
            }
        }

        // The search + filter chrome only earns its space once there are threads to
        // act on. Empty / loading / failed states render clean, without dead controls.
        val ready = result as? com.haraan.app.data.DirectMessageRepository.ThreadsResult.Ready
        val hasThreads = ready != null && ready.threads.isNotEmpty()

        if (hasThreads) {
            SearchField(
                value = query,
                onValueChange = { query = it },
                modifier = Modifier.padding(horizontal = 16.dp),
            )
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                FilterChip("All", selected = !unreadOnly) { unreadOnly = false }
                val unreadTotal = ready.unreadTotal
                FilterChip(
                    if (unreadTotal > 0) "Unread $unreadTotal" else "Unread",
                    selected = unreadOnly,
                ) { unreadOnly = true }
            }
            Spacer(Modifier.height(6.dp))
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
                val q = query.trim()
                val filtered = r.threads.filter { t ->
                    (!unreadOnly || t.unreadCount > 0) &&
                        (q.isEmpty() || t.name.contains(q, ignoreCase = true) ||
                            (t.lastMessage?.contains(q, ignoreCase = true) == true))
                }
                if (filtered.isEmpty()) {
                    Box(Modifier.fillMaxSize().padding(horizontal = 40.dp), contentAlignment = Alignment.Center) {
                        Text(
                            if (unreadOnly) "You're all caught up." else "No matches for \"$q\".",
                            color = HaraanColors.TextMuted,
                            fontSize = 14.sp,
                            textAlign = TextAlign.Center,
                        )
                    }
                } else {
                    LazyColumn(Modifier.fillMaxSize()) {
                        items(filtered, key = { it.id }) { t ->
                            ThreadRow(t) { onOpenThread(t) }
                            // Hairline inset to the text column — the premium messaging
                            // rhythm, and it keeps avatars from crowding the eye.
                            Box(
                                Modifier
                                    .fillMaxWidth()
                                    .padding(start = 82.dp)
                                    .height(1.dp)
                                    .background(HaraanColors.BorderLight.copy(alpha = 0.6f)),
                            )
                        }
                    }
                }
            }
        }
    }
}

/** Filled, borderless search — the messaging-app pill, not a form field. */
@Composable
private fun SearchField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier
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
            if (value.isEmpty()) {
                Text("Search messages", color = HaraanColors.TextMuted, fontSize = 14.5.sp)
            }
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = true,
                textStyle = LocalTextStyle.current.copy(
                    color = HaraanColors.TextPrimary,
                    fontSize = 14.5.sp,
                ),
                cursorBrush = androidx.compose.ui.graphics.SolidColor(HaraanColors.EventsBlue),
                keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(imeAction = ImeAction.Search),
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun FilterChip(label: String, selected: Boolean, onClick: () -> Unit) {
    Box(
        Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(if (selected) HaraanColors.EventsBlue else HaraanColors.Field)
            .pressable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 8.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            color = if (selected) Color.White else HaraanColors.TextSecondary,
            fontSize = 13.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
        )
    }
}

@Composable
private fun ThreadRow(thread: ChatThread, onClick: () -> Unit) {
    val unread = thread.unreadCount > 0
    Row(
        Modifier
            .fillMaxWidth()
            .pressable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (thread.isGroup) GroupAvatar(thread, 54.dp) else Avatar(thread.avatar, thread.name, 54.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                thread.name,
                color = HaraanColors.TextPrimary,
                fontSize = 15.5.sp,
                // Unread threads sit heavier — the list is scanned, not read.
                fontWeight = if (unread) FontWeight.Bold else FontWeight.SemiBold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                thread.lastMessage ?: "Say hello",
                color = if (unread) HaraanColors.TextPrimary else HaraanColors.TextMuted,
                fontSize = 13.5.sp,
                fontWeight = if (unread) FontWeight.Medium else FontWeight.Normal,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.width(10.dp))
        // Time above, unread badge below — the standard right rail. A fixed-width
        // column keeps names from jittering as timestamps change length.
        Column(horizontalAlignment = Alignment.End, modifier = Modifier.widthIn(min = 40.dp)) {
            val time = relativeTime(thread.lastMessageAt)
            if (time.isNotEmpty()) {
                Text(
                    time,
                    color = if (unread) HaraanColors.EventsBlue else HaraanColors.TextMuted,
                    fontSize = 11.5.sp,
                    fontWeight = if (unread) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                )
            }
            if (unread) {
                Spacer(Modifier.height(6.dp))
                Box(
                    Modifier
                        .heightIn(min = 20.dp)
                        .widthIn(min = 20.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(HaraanColors.EventsBlue)
                        .padding(horizontal = 6.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        if (thread.unreadCount > 99) "99+" else thread.unreadCount.toString(),
                        color = Color.White,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
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
 * A group's row art: two members' avatars overlapped, so a group reads as a group at a
 * glance without a photo of its own. Falls back to a people glyph when we have no member
 * art (e.g. a brand-new group before anyone with a photo is shown).
 */
@Composable
private fun GroupAvatar(thread: ChatThread, size: androidx.compose.ui.unit.Dp) {
    Box(Modifier.size(size), contentAlignment = Alignment.Center) {
        val names = thread.memberNames
        val avatars = thread.memberAvatars
        val shown = maxOf(names.size, avatars.size)
        if (shown == 0) {
            Box(
                Modifier.size(size).clip(CircleShape).background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Group, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(size * 0.5f))
            }
        } else {
            val small = size * 0.68f
            // Back avatar (second member) top-start, front avatar (first) bottom-end, with
            // a white ring on the front so the overlap reads cleanly on any background.
            if (shown > 1) {
                Box(Modifier.align(Alignment.TopStart)) {
                    MiniAvatar(avatars.getOrNull(1), names.getOrNull(1) ?: "", small)
                }
            }
            Box(
                Modifier.align(if (shown > 1) Alignment.BottomEnd else Alignment.Center)
                    .then(if (shown > 1) Modifier.background(Color.White, CircleShape).padding(1.5.dp) else Modifier),
            ) {
                MiniAvatar(avatars.getOrNull(0), names.getOrNull(0) ?: "", small)
            }
        }
    }
}

@Composable
private fun MiniAvatar(url: String?, name: String, size: androidx.compose.ui.unit.Dp) {
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
                fontSize = (size.value / 2.4f).sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

/**
 * ISO-8601 → a short, human "when": "now", "12min", "5h", "Yesterday", "Mon", or a
 * date. SimpleDateFormat keeps parsing safe on minSdk 24 (no java.time desugaring
 * dependency). Returns "" for a missing/unparseable stamp so the caller skips it —
 * matching [com.haraan.app.ui.main.SupportChatScreen]'s formatter philosophy.
 */
private fun relativeTime(iso: String?): String {
    if (iso.isNullOrBlank()) return ""
    return runCatching {
        val parser = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.ENGLISH).apply {
            timeZone = java.util.TimeZone.getTimeZone("UTC")
        }
        val then = parser.parse(iso) ?: return ""
        val now = java.util.Date()
        val diffMs = now.time - then.time
        val minutes = diffMs / 60_000L
        val hours = diffMs / 3_600_000L
        val days = diffMs / 86_400_000L
        when {
            diffMs < 0 -> java.text.SimpleDateFormat("h:mm a", java.util.Locale.getDefault()).format(then)
            minutes < 1 -> "now"
            minutes < 60 -> "${minutes}min"
            hours < 24 -> "${hours}h"
            days < 2 -> "Yesterday"
            days < 7 -> java.text.SimpleDateFormat("EEE", java.util.Locale.getDefault()).format(then)
            else -> java.text.SimpleDateFormat("d MMM", java.util.Locale.getDefault()).format(then)
        }
    }.getOrDefault("")
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
    isGroup: Boolean = false,
    /** e.g. "5 members" — shown under a group's title. */
    subtitle: String? = null,
    /** Present only for groups: returns true if the leave took, so we can close then. */
    onLeave: (suspend () -> Boolean)? = null,
    modifier: Modifier = Modifier,
) {
    var messages by remember { mutableStateOf<List<ChatMessage>?>(null) }
    var draft by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    var failed by remember { mutableStateOf(false) }
    var confirmLeave by remember { mutableStateOf(false) }
    var leaving by remember { mutableStateOf(false) }
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
            if (isGroup) {
                Box(
                    Modifier.size(34.dp).clip(CircleShape).background(HaraanColors.Field),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Group, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(18.dp))
                }
            } else {
                Avatar(avatar, title, 34.dp)
            }
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text(title, color = HaraanColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (!subtitle.isNullOrBlank()) {
                    Text(subtitle, color = HaraanColors.TextMuted, fontSize = 12.sp, maxLines = 1)
                }
            }
            // Leave — only for groups, and deliberately understated (it's destructive).
            if (isGroup && onLeave != null) {
                Box(
                    Modifier.size(38.dp).pressable(haptic = Feel.REMOVE) { confirmLeave = true }
                        .clip(CircleShape).background(HaraanColors.Field),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.AutoMirrored.Filled.Logout, "Leave group", tint = HaraanColors.Danger, modifier = Modifier.size(18.dp))
                }
            }
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
                    // In a group, an incoming bubble shows WHO sent it (grouped: only the
                    // first of a run from the same sender carries the name).
                    itemsIndexed(list, key = { _, m -> m.id }) { i, m ->
                        val prev = list.getOrNull(i - 1)
                        val showSender = isGroup && !m.mine &&
                            (prev == null || prev.mine || prev.senderName != m.senderName)
                        Bubble(m, showSenderName = showSender)
                    }
                }
            }
        }

        if (confirmLeave) {
            androidx.compose.material3.AlertDialog(
                onDismissRequest = { if (!leaving) confirmLeave = false },
                title = { Text("Leave group?", fontWeight = FontWeight.Bold) },
                text = { Text("You'll stop receiving messages from \"$title\". You can be added back by a member.") },
                confirmButton = {
                    androidx.compose.material3.TextButton(
                        enabled = !leaving,
                        onClick = {
                            leaving = true
                            scope.launch {
                                val ok = onLeave?.invoke() ?: false
                                leaving = false
                                confirmLeave = false
                                if (ok) onClose()
                            }
                        },
                    ) { Text("Leave", color = HaraanColors.Danger, fontWeight = FontWeight.Bold) }
                },
                dismissButton = {
                    androidx.compose.material3.TextButton(enabled = !leaving, onClick = { confirmLeave = false }) {
                        Text("Cancel", color = HaraanColors.TextSecondary)
                    }
                },
                containerColor = Color.White,
            )
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
private fun Bubble(m: ChatMessage, showSenderName: Boolean = false) {
    Column(
        Modifier.fillMaxWidth(),
        horizontalAlignment = if (m.mine) Alignment.End else Alignment.Start,
    ) {
        // In a group, name the speaker above the first bubble of their run.
        if (showSenderName && !m.senderName.isNullOrBlank()) {
            Text(
                m.senderName,
                color = HaraanColors.EventsBlue,
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                maxLines = 1,
                modifier = Modifier.padding(start = 4.dp, bottom = 2.dp),
            )
        }
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

/**
 * Create a group: name it, then pick members from your mutual follows.
 *
 * The picker's only contents are people who follow you back — the same rule as a 1:1,
 * applied per member — so nobody can be dropped into a group by someone they haven't
 * connected with. An empty picker says why rather than showing a dead list.
 */
@Composable
fun NewGroupScreen(
    loadCandidates: suspend () -> List<com.haraan.app.data.ChatCandidate>,
    create: suspend (String, List<String>) -> com.haraan.app.data.DirectMessageRepository.GroupResult,
    onCreated: (ChatThread) -> Unit,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var candidates by remember { mutableStateOf<List<com.haraan.app.data.ChatCandidate>?>(null) }
    var name by remember { mutableStateOf("") }
    val selected = remember { mutableStateListOf<String>() }
    var creating by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    BackHandler(enabled = true) { onClose() }
    LaunchedEffect(Unit) { candidates = loadCandidates() }

    val canCreate = name.trim().isNotEmpty() && selected.isNotEmpty() && !creating

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color.White)
            .statusBarsPadding()
            .imePadding(),
    ) {
        // Header with an inline Create action.
        Row(
            Modifier.fillMaxWidth().padding(start = 12.dp, end = 12.dp, top = 8.dp, bottom = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(38.dp).pressable(onClick = onClose).clip(CircleShape).background(HaraanColors.Field),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(10.dp))
            Text("New group", color = HaraanColors.TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
            Spacer(Modifier.weight(1f))
            Box(
                Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(if (canCreate) HaraanColors.EventsBlue else HaraanColors.Field)
                    .pressable(enabled = canCreate, haptic = Feel.COMMIT) {
                        creating = true
                        error = null
                        scope.launch {
                            when (val r = create(name.trim(), selected.toList())) {
                                is com.haraan.app.data.DirectMessageRepository.GroupResult.Ready -> onCreated(r.thread)
                                is com.haraan.app.data.DirectMessageRepository.GroupResult.NotAllowed ->
                                    error = "You can only add players who follow you back."
                                else -> error = "Couldn't create the group. Try again."
                            }
                            creating = false
                        }
                    }
                    .padding(horizontal = 18.dp, vertical = 9.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    "Create",
                    color = if (canCreate) Color.White else HaraanColors.TextMuted,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }

        // Group name.
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp).height(48.dp)
                .clip(RoundedCornerShape(14.dp)).background(HaraanColors.Field).padding(horizontal = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Group, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(19.dp))
            Spacer(Modifier.width(8.dp))
            Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
                if (name.isEmpty()) Text("Group name", color = HaraanColors.TextMuted, fontSize = 15.sp)
                BasicTextField(
                    value = name,
                    onValueChange = { if (it.length <= 80) name = it },
                    singleLine = true,
                    textStyle = LocalTextStyle.current.copy(color = HaraanColors.TextPrimary, fontSize = 15.sp),
                    cursorBrush = androidx.compose.ui.graphics.SolidColor(HaraanColors.EventsBlue),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }

        if (error != null) {
            Text(
                error!!,
                color = HaraanColors.Danger,
                fontSize = 12.5.sp,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp),
                textAlign = TextAlign.Center,
            )
        }

        Text(
            if (selected.isEmpty()) "Add members" else "Add members · ${selected.size} selected",
            color = HaraanColors.TextSecondary,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(start = 16.dp, top = 16.dp, bottom = 6.dp),
        )

        when (val list = candidates) {
            null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = HaraanColors.EventsBlue)
            }
            else -> if (list.isEmpty()) {
                Box(Modifier.fillMaxSize().padding(horizontal = 40.dp), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No one to add yet", color = HaraanColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "You can add players who follow you back. Follow some players and have them follow you to start a group.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.5.sp,
                            lineHeight = 19.sp,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            } else {
                LazyColumn(Modifier.fillMaxSize()) {
                    items(list, key = { it.playerId }) { cand ->
                        val isSel = selected.contains(cand.playerId)
                        CandidateRow(cand, isSel) {
                            if (isSel) selected.remove(cand.playerId) else selected.add(cand.playerId)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CandidateRow(cand: com.haraan.app.data.ChatCandidate, selected: Boolean, onToggle: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().pressable(onClick = onToggle).padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Avatar(cand.avatar, cand.name, 46.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(cand.name, color = HaraanColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            if (!cand.username.isNullOrBlank()) {
                Text("@${cand.username}", color = HaraanColors.TextMuted, fontSize = 12.5.sp, maxLines = 1)
            }
        }
        Spacer(Modifier.width(10.dp))
        // Selection check — filled blue when picked, hollow ring when not.
        Box(
            Modifier.size(24.dp).clip(CircleShape)
                .background(if (selected) HaraanColors.EventsBlue else Color.White)
                .then(if (selected) Modifier else Modifier.border(1.5.dp, HaraanColors.BorderLight, CircleShape)),
            contentAlignment = Alignment.Center,
        ) {
            if (selected) Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(15.dp))
        }
    }
}
