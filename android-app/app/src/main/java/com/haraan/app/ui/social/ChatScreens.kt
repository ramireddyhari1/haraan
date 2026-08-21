@file:OptIn(
    androidx.compose.foundation.ExperimentalFoundationApi::class,
    androidx.compose.material3.ExperimentalMaterial3Api::class,
)

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
import androidx.compose.runtime.DisposableEffect
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
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.wrapContentWidth
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.graphicsLayer
import com.haraan.app.ui.components.AutoRefresh
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.material.icons.filled.Block
import androidx.compose.foundation.layout.offset
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Close
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
    /**
     * Requests from players asking to join one of your matches.
     *
     * These live here rather than under Scheduled because they are someone reaching OUT to
     * you and waiting on an answer — the same thing every other row on this screen is. Buried
     * three taps deep in a Scheduled sub-tab, they were easy to leave unanswered.
     */
    joinRequests: List<com.haraan.app.data.IncomingJoinRequest> = emptyList(),
    onOpenRequests: () -> Unit = {},
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

        // A single summary row, not the cards themselves: the inbox is a list of
        // conversations, and stacking accept/decline cards on top of it would bury the
        // messages under a queue of chores.
        if (joinRequests.isNotEmpty()) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .padding(bottom = 12.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(HaraanColors.EventsBlue.copy(alpha = 0.08f))
                    .pressable(onClick = onOpenRequests)
                    .padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier.size(38.dp).clip(CircleShape).background(HaraanColors.EventsBlue),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Filled.GroupAdd,
                        null,
                        tint = Color.White,
                        modifier = Modifier.size(19.dp),
                    )
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        "Join requests",
                        color = HaraanColors.TextPrimary,
                        fontSize = 14.5.sp,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        if (joinRequests.size == 1) {
                            "${joinRequests.first().playerName} wants to join your match"
                        } else {
                            "${joinRequests.size} players want to join your matches"
                        },
                        color = HaraanColors.TextSecondary,
                        fontSize = 12.5.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                Spacer(Modifier.width(10.dp))
                Box(
                    Modifier
                        .clip(RoundedCornerShape(50))
                        .background(HaraanColors.EventsBlue)
                        .padding(horizontal = 9.dp, vertical = 3.dp),
                ) {
                    Text(
                        "${joinRequests.size}",
                        color = Color.White,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
        }

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
                // An empty inbox used to be two grey lines floating in the vertical centre
                // of a blank screen, and the copy pointed only at profiles — while a New
                // group button sat unmentioned in the corner. Now it has something to look
                // at and names BOTH ways to start a conversation.
                Column(
                    Modifier.fillMaxSize().padding(horizontal = 32.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(
                        Modifier.size(66.dp).clip(CircleShape).background(HaraanColors.Field),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            Icons.AutoMirrored.Filled.Send,
                            null,
                            tint = HaraanColors.EventsBlue.copy(alpha = 0.75f),
                            modifier = Modifier.size(26.dp),
                        )
                    }
                    Spacer(Modifier.height(16.dp))
                    Text("No messages yet", color = HaraanColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(7.dp))
                    Text(
                        "You can message players who follow you back — open their profile to start one.",
                        color = HaraanColors.TextSecondary,
                        fontSize = 13.5.sp,
                        lineHeight = 19.sp,
                        textAlign = TextAlign.Center,
                    )
                    Spacer(Modifier.height(18.dp))
                    Row(
                        Modifier
                            .clip(RoundedCornerShape(50))
                            .background(HaraanColors.EventsBlue.copy(alpha = 0.10f))
                            .pressable(onClick = onNewGroup)
                            .padding(horizontal = 16.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Filled.GroupAdd, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(17.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Start a group", color = HaraanColors.EventsBlue, fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
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
    /** Null loads the whole thread; an id loads only what arrived after it. */
    load: suspend (sinceId: Long?) -> List<ChatMessage>,
    send: suspend (body: String, replyToId: Long?) -> ChatMessage?,
    onClose: () -> Unit,
    isGroup: Boolean = false,
    /**
     * How many messages were unread when this thread was opened, so the reader can be
     * shown where they left off. Opening the thread marks it read server-side, so this
     * value is only ever true at the moment of opening — it is captured, not polled.
     */
    unreadCount: Int = 0,
    /** The conversation's id — what the realtime channel is named after. 0 disables it. */
    conversationId: Long = 0L,
    /** How far the other side has got, refreshed with every fetch. Null in a group. */
    theirDeliveredAt: String? = null,
    theirReadAt: String? = null,
    /** Unsend one of your own. Returns false when the server refused. Null hides the action. */
    onUnsend: (suspend (Long) -> Boolean)? = null,
    /** React to a message. Sending the same emoji again clears it, server-side. */
    onReact: (suspend (Long, String) -> Boolean)? = null,
    /** Forward a message into another conversation. Null hides the action. */
    onForward: (suspend (messageId: Long, toConversationId: Long) -> Boolean)? = null,
    /** The threads a message can be forwarded INTO — this one excluded by the caller. */
    forwardTargets: List<com.haraan.app.data.ChatThread> = emptyList(),
    /** e.g. "5 members" — shown under a group's title. */
    subtitle: String? = null,
    /** Present only for groups: returns true if the leave took, so we can close then. */
    onLeave: (suspend () -> Boolean)? = null,
    modifier: Modifier = Modifier,
) {
    var messages by remember { mutableStateOf<List<ChatMessage>?>(null) }
    var draft by remember { mutableStateOf("") }
    var confirmLeave by remember { mutableStateOf(false) }
    var leaving by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val listState = rememberLazyListState()

    // Messages this viewer has sent that the server hasn't confirmed yet.
    //
    // Before this, tapping Send cleared the box and NOTHING appeared until the round trip
    // came back — on a slow connection your own message simply vanished for a second, which
    // is the single worst thing a chat can do. Now the bubble appears instantly, dimmed,
    // and either settles into the thread or turns into a tappable "Tap to retry".
    var outbox by remember { mutableStateOf<List<PendingMessage>>(emptyList()) }

    // The index of the first message the reader hadn't seen, worked out from the unread
    // count the LIST row carried. Captured on first load and then left alone: opening the
    // thread marks it read, so recomputing on every poll would erase the marker while the
    // reader is still looking at it.
    var unreadStart by remember { mutableStateOf<Int?>(null) }

    // The message a long press opened the sheet on, and whatever the last action had to say.
    var sheetFor by remember { mutableStateOf<ChatMessage?>(null) }
    var actionNote by remember { mutableStateOf("") }
    // Set when the overlay hands off to the fuller "Message info" sheet.
    var showInfoFor by remember { mutableStateOf<ChatMessage?>(null) }
    // The message the composer is currently answering, and the one waiting for a target.
    var replyingTo by remember { mutableStateOf<ChatMessage?>(null) }
    var forwarding by remember { mutableStateOf<ChatMessage?>(null) }
    val clipboard = androidx.compose.ui.platform.LocalClipboardManager.current
    LaunchedEffect(messages != null) {
        val loaded = messages
        if (unreadStart == null && loaded != null && unreadCount > 0 && unreadCount < loaded.size) {
            val candidate = loaded.size - unreadCount
            // Only when it really points at incoming messages — a count that has drifted
            // must not draw a line above something this viewer wrote themselves.
            if (loaded.getOrNull(candidate)?.mine == false) unreadStart = candidate
        }
    }

    BackHandler(enabled = true) { onClose() }
    LaunchedEffect(Unit) { messages = load(null) }

    // ── Realtime ──────────────────────────────────────────────────────────────────
    //
    // The server pushes "conversation {id} moved" over the same Reverb socket the live
    // match boards already use, and we pull the new messages the moment it lands. That is
    // the difference between a message appearing when it is sent and appearing up to five
    // seconds later, which is most of what makes a chat feel alive.
    //
    // The frame carries no body: the channel is public, so the fetch below — authenticated,
    // membership-checked — is what actually delivers the words.
    if (conversationId > 0L) {
        DisposableEffect(conversationId) {
            com.haraan.app.data.RealtimeClient.subscribe("conversation.$conversationId")
            onDispose { com.haraan.app.data.RealtimeClient.unsubscribe("conversation.$conversationId") }
        }
        LaunchedEffect(conversationId) {
            com.haraan.app.data.ChatRealtimeBus.updates.collect { id ->
                if (id == conversationId.toString()) {
                    val current = messages
                    val fresh = load(current?.lastOrNull()?.id)
                    if (fresh.isNotEmpty()) {
                        val known = current?.map { it.id }?.toSet() ?: emptySet()
                        messages = (current ?: emptyList()) + fresh.filterNot { it.id in known }
                    }
                }
            }
        }
    }

    // The socket is the fast path; this is the safety net for when it is down, the phone
    // has just woken, or a frame was missed. It used to run every 5s as the ONLY delivery
    // mechanism — at that rate on every open thread it was also the app's chattiest poll.
    AutoRefresh(20_000L, true) {
        val current = messages
        if (current == null) {
            messages = load(null)
        } else {
            val fresh = load(current.lastOrNull()?.id)
            if (fresh.isNotEmpty()) {
                // De-duplicate on id: a message this device just sent can arrive back from
                // the server too, and two copies of your own line is worse than a slow one.
                val known = current.map { it.id }.toSet()
                messages = current + fresh.filterNot { it.id in known }
            }
        }
    }

    // Keep the newest message in view. The FIRST load jumps — animating a long scroll
    // through a year of history on open looks like a bug — and everything after animates.
    var settledOnce by remember { mutableStateOf(false) }
    LaunchedEffect(messages?.size, outbox.size) {
        val n = (messages?.size ?: 0) + outbox.size
        if (n > 0) {
            if (settledOnce) listState.animateScrollToItem(n - 1)
            else { listState.scrollToItem(n - 1); settledOnce = true }
        }
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
                // A thread with nothing in it used to be a blank white void between the
                // header and the composer — the screen looked broken at the exact moment a
                // new conversation begins. Now it says whose thread this is.
                else -> if (list.isEmpty() && outbox.isEmpty()) Column(
                    Modifier.fillMaxSize().padding(horizontal = 40.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    if (isGroup) {
                        Box(
                            Modifier.size(72.dp).clip(CircleShape).background(HaraanColors.Field),
                            contentAlignment = Alignment.Center,
                        ) { Icon(Icons.Filled.Group, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(30.dp)) }
                    } else {
                        Avatar(avatar, title, 72.dp)
                    }
                    Spacer(Modifier.height(14.dp))
                    Text(
                        if (isGroup) title else "Say hello to $title",
                        color = HaraanColors.TextPrimary,
                        fontSize = 16.5.sp,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center,
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        if (isGroup) "No messages yet — start the conversation."
                        else "This is the beginning of your conversation.",
                        color = HaraanColors.TextSecondary,
                        fontSize = 13.5.sp,
                        textAlign = TextAlign.Center,
                    )
                } else LazyColumn(
                    state = listState,
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(3.dp),
                ) {
                    // In a group, an incoming bubble shows WHO sent it (grouped: only the
                    // first of a run from the same sender carries the name).
                    itemsIndexed(list, key = { _, m -> m.id }) { i, m ->
                        val prev = list.getOrNull(i - 1)
                        val next = list.getOrNull(i + 1)
                        val showSender = isGroup && !m.mine &&
                            (prev == null || prev.mine || prev.senderName != m.senderName)
                        // A date rule whenever the day changes — without it a thread reads
                        // as one endless conversation and "9:41" could be from any week.
                        val day = dayLabel(m.sentAt)
                        if (day != null && day != dayLabel(prev?.sentAt)) DaySeparator(day)
                        // Where the reader left off. Drawn once, at the first message they
                        // hadn't seen — the single most useful line in any messenger, and
                        // the data (unread_count) was already on the thread row.
                        if (unreadStart != null && i == unreadStart) UnreadDivider(unreadCount)
                        // Consecutive bubbles from the same side hug; a change of speaker
                        // opens a gap. That rhythm is what makes a thread scannable.
                        val tight = prev != null && prev.mine == m.mine && day == dayLabel(prev.sentAt)
                        Spacer(Modifier.height(if (tight) 3.dp else 10.dp))
                        // One timestamp per RUN, on its last bubble. Stamping every bubble
                        // meant four lines sent in one breath carried four near-identical
                        // times — noise that made the thread look like a log file.
                        val endsRun = next == null || next.mine != m.mine ||
                            dayLabel(next.sentAt) != day
                        Bubble(
                            m,
                            showSenderName = showSender,
                            showTime = endsRun,
                            // Ticks only on your OWN messages, and only where the answer is
                            // real: a group has many recipients, so the server sends null
                            // and nothing is drawn rather than a tick that means "someone".
                            status = if (m.mine && !m.deleted) {
                                receiptFor(m.sentAt, theirDeliveredAt, theirReadAt)
                            } else {
                                null
                            },
                            onLongPress = { sheetFor = m },
                        )
                    }
                    // In flight, below everything that has landed.
                    items(outbox, key = { it.tempId }) { p ->
                        Spacer(Modifier.height(3.dp))
                        PendingBubble(p) {
                            // Retry: drop it from the outbox and send it again.
                            outbox = outbox.filterNot { it.tempId == p.tempId }
                            scope.launch {
                                sendWithOutbox(p.body, p.replyToId, { outbox }, { outbox = it }, send) {
                                    messages = it(messages)
                                }
                            }
                        }
                    }
                }
            }
        }

        // Long press: the thread falls back and the message stays lit, with a reaction row
        // above it and its actions below — the gesture people already know. "Message info"
        // opens the fuller sheet underneath, which is where the receipt timeline lives.
        sheetFor?.let { target ->
            if (showInfoFor?.id == target.id) {
                MessageSheet(
                    message = target,
                    deliveredAt = theirDeliveredAt,
                    readAt = theirReadAt,
                    note = actionNote,
                    canUnsend = target.mine && !target.deleted && onUnsend != null,
                    onCopy = {
                        clipboard.setText(androidx.compose.ui.text.AnnotatedString(target.body))
                        sheetFor = null; showInfoFor = null; actionNote = ""
                    },
                    onUnsend = {
                        scope.launch {
                            val ok = onUnsend?.invoke(target.id) ?: false
                            if (ok) {
                                messages = messages?.map {
                                    if (it.id == target.id) it.copy(body = "", deleted = true) else it
                                }
                                sheetFor = null; showInfoFor = null; actionNote = ""
                            } else {
                                actionNote = "Couldn't unsend that message."
                            }
                        }
                    },
                    onDismiss = { sheetFor = null; showInfoFor = null; actionNote = "" },
                )
            } else {
                MessageActionOverlay(
                    message = target,
                    receiptLine = receiptDetail(target, theirDeliveredAt, theirReadAt),
                    canUnsend = target.mine && !target.deleted && onUnsend != null,
                    onReact = { emoji ->
                        scope.launch {
                            onReact?.invoke(target.id, emoji)
                            // Re-read so the count is the server's, not a local guess.
                            val fresh = load(null)
                            if (fresh.isNotEmpty()) messages = fresh
                            sheetFor = null
                        }
                    },
                    onCopy = {
                        clipboard.setText(androidx.compose.ui.text.AnnotatedString(target.body))
                        sheetFor = null
                    },
                    onUnsend = {
                        scope.launch {
                            val ok = onUnsend?.invoke(target.id) ?: false
                            if (ok) {
                                messages = messages?.map {
                                    if (it.id == target.id) it.copy(body = "", deleted = true) else it
                                }
                                sheetFor = null
                            } else {
                                actionNote = "Couldn't unsend that message."
                            }
                        }
                    },
                    onReply = { replyingTo = target; sheetFor = null },
                    onForward = if (onForward != null && forwardTargets.isNotEmpty()) {
                        { forwarding = target; sheetFor = null }
                    } else {
                        null
                    },
                    onInfo = { showInfoFor = target },
                    onDismiss = { sheetFor = null; actionNote = "" },
                )
            }
        }

        forwarding?.let { target ->
            ForwardPicker(
                threads = forwardTargets,
                onPick = { thread ->
                    scope.launch {
                        val ok = onForward?.invoke(target.id, thread.id) ?: false
                        forwarding = null
                        actionNote = if (ok) "" else "Couldn't forward that message."
                    }
                },
                onDismiss = { forwarding = null },
            )
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

        // ── Composer ──────────────────────────────────────────────────────────────
        //
        // Was a Material OutlinedTextField: a boxed form field with a floating border, in
        // an app whose every other input is a filled pill (the search field twenty lines
        // up in this same file). It read as a settings screen, not a conversation.
        // What you are answering, above the input — so the quote is visible while you type
        // rather than a mode you have to remember you are in.
        replyingTo?.let { q ->
            Row(
                Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(HaraanColors.Field)
                    .padding(start = 12.dp, end = 8.dp, top = 8.dp, bottom = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier
                        .width(3.dp)
                        .heightIn(min = 28.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(HaraanColors.EventsBlue),
                )
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        if (q.mine) "Replying to yourself" else "Replying to ${q.senderName ?: title}",
                        color = HaraanColors.EventsBlue,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                    )
                    Text(
                        q.body,
                        color = HaraanColors.TextSecondary,
                        fontSize = 12.5.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                Box(
                    Modifier.size(30.dp).clip(CircleShape).pressable { replyingTo = null },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Close, "Cancel reply", tint = HaraanColors.TextMuted, modifier = Modifier.size(16.dp))
                }
            }
            Spacer(Modifier.height(8.dp))
        }

        Row(
            Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.Bottom,
        ) {
            Box(
                Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(22.dp))
                    .background(HaraanColors.Field)
                    .padding(horizontal = 16.dp, vertical = 11.dp),
            ) {
                if (draft.isEmpty()) {
                    Text("Message", color = HaraanColors.TextMuted, fontSize = 14.5.sp)
                }
                BasicTextField(
                    value = draft,
                    onValueChange = { draft = it },
                    textStyle = androidx.compose.ui.text.TextStyle(
                        color = HaraanColors.TextPrimary,
                        fontSize = 14.5.sp,
                        lineHeight = 20.sp,
                    ),
                    cursorBrush = SolidColor(HaraanColors.EventsBlue),
                    maxLines = 5,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            Spacer(Modifier.width(9.dp))
            val canSend = draft.trim().isNotEmpty()
            // Grows into place when there is something to send, so the control answers the
            // typing rather than sitting there greyed out.
            val sendScale by animateFloatAsState(
                targetValue = if (canSend) 1f else 0.88f,
                animationSpec = spring(dampingRatio = 0.6f, stiffness = 420f),
                label = "sendScale",
            )
            Box(
                Modifier
                    .size(46.dp)
                    .graphicsLayer { scaleX = sendScale; scaleY = sendScale }
                    .pressable(enabled = canSend, haptic = Feel.COMMIT) {
                        val text = draft.trim()
                        draft = ""
                        val quoted = replyingTo?.id
                        replyingTo = null
                        scope.launch {
                            sendWithOutbox(text, quoted, { outbox }, { outbox = it }, send) { update ->
                                messages = update(messages)
                            }
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
                    modifier = Modifier.size(19.dp),
                )
            }
        }
    }
}

/** A message this viewer sent that the server has not confirmed yet. */
data class PendingMessage(
    val tempId: Long,
    val body: String,
    val failed: Boolean = false,
    val replyToId: Long? = null,
)

/**
 * Send one message through the outbox: show it immediately, then either fold it into the
 * thread or mark it failed. Pulled out of the composable so the retry tap and the send tap
 * are literally the same code path — two copies of this logic is how a retry ends up
 * behaving subtly differently from a first attempt.
 */
private suspend fun sendWithOutbox(
    text: String,
    replyToId: Long?,
    outbox: () -> List<PendingMessage>,
    setOutbox: (List<PendingMessage>) -> Unit,
    send: suspend (String, Long?) -> ChatMessage?,
    updateMessages: ((List<ChatMessage>?) -> List<ChatMessage>) -> Unit,
) {
    if (text.isBlank()) return
    val tempId = -System.currentTimeMillis()
    // The quote rides with the pending row, so a retry answers the same message rather than
    // quietly becoming a stray line.
    setOutbox(outbox() + PendingMessage(tempId, text, replyToId = replyToId))
    val sent = send(text, replyToId)
    if (sent == null) {
        setOutbox(outbox().map { if (it.tempId == tempId) it.copy(failed = true) else it })
    } else {
        setOutbox(outbox().filterNot { it.tempId == tempId })
        updateMessages { current -> (current ?: emptyList()) + sent }
    }
}

/** Where one of your own messages has got to. Null when the answer isn't knowable. */
enum class Receipt { SENT, DELIVERED, READ }

@Composable
private fun Bubble(
    m: ChatMessage,
    showSenderName: Boolean = false,
    showTime: Boolean = true,
    status: Receipt? = null,
    onLongPress: (() -> Unit)? = null,
) {
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
        // An unsent message keeps its place but says plainly what happened — a message that
        // simply disappeared leaves the other person wondering what they missed.
        if (m.deleted) {
            BubbleShell(mine = m.mine, ghost = true) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.Block,
                        null,
                        tint = HaraanColors.TextMuted,
                        modifier = Modifier.size(13.dp),
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        if (m.mine) "You unsent this message" else "This message was unsent",
                        color = HaraanColors.TextMuted,
                        fontSize = 13.sp,
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic,
                    )
                }
            }
            return@Column
        }
        BubbleShell(mine = m.mine, onLongPress = onLongPress) {
            // "Forwarded" says these aren't the sender's words — the honest half of the
            // label. It deliberately does NOT link back: following a pointer into a thread
            // the reader may not belong to is exactly the leak this app shouldn't have.
            if (m.forwarded) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.AutoMirrored.Filled.Send,
                        null,
                        tint = if (m.mine) Color.White.copy(alpha = 0.7f) else HaraanColors.TextMuted,
                        modifier = Modifier.size(11.dp),
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        "Forwarded",
                        color = if (m.mine) Color.White.copy(alpha = 0.7f) else HaraanColors.TextMuted,
                        fontSize = 10.5.sp,
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic,
                    )
                }
                Spacer(Modifier.height(5.dp))
            }
            // The message being answered, inside the reply's own bubble — so the thread
            // reads top to bottom without hunting upwards for what "yes, that one" meant.
            m.replyTo?.let { q ->
                Row(
                    Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(
                            if (m.mine) Color.White.copy(alpha = 0.18f) else Color.Black.copy(alpha = 0.05f)
                        )
                        .padding(horizontal = 8.dp, vertical = 6.dp),
                ) {
                    Box(
                        Modifier
                            .width(2.5.dp)
                            .heightIn(min = 24.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(if (m.mine) Color.White.copy(alpha = 0.8f) else HaraanColors.EventsBlue),
                    )
                    Spacer(Modifier.width(8.dp))
                    Column {
                        Text(
                            if (q.mine) "You" else (q.senderName ?: "Them"),
                            color = if (m.mine) Color.White else HaraanColors.EventsBlue,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                        )
                        Text(
                            if (q.deleted) "Unsent message" else q.body,
                            color = if (m.mine) Color.White.copy(alpha = 0.85f) else HaraanColors.TextSecondary,
                            fontSize = 12.sp,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            fontStyle = if (q.deleted) androidx.compose.ui.text.font.FontStyle.Italic
                                else androidx.compose.ui.text.font.FontStyle.Normal,
                        )
                    }
                }
                Spacer(Modifier.height(6.dp))
            }
            Text(
                m.body,
                color = if (m.mine) Color.White else HaraanColors.TextPrimary,
                fontSize = 14.5.sp,
                lineHeight = 20.sp,
            )
            // Time, and — on your own messages — how far it has got. Both live inside the
            // bubble, on one line, so a run of messages doesn't grow a column of metadata.
            val time = timeLabel(m.sentAt).takeIf { showTime }
            if (time != null || status != null) {
                Spacer(Modifier.height(3.dp))
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.align(Alignment.End),
                ) {
                    if (time != null) {
                        Text(
                            time,
                            color = if (m.mine) Color.White.copy(alpha = 0.75f) else HaraanColors.TextMuted,
                            fontSize = 10.5.sp,
                        )
                    }
                    if (status != null) {
                        Spacer(Modifier.width(4.dp))
                        ReceiptTicks(status)
                    }
                }
            }
        }
        // Reactions hang under the bubble they belong to, overlapping it slightly — the
        // convention that says "this is ON the message" rather than a reply beneath it.
        if (m.reactions.isNotEmpty()) {
            Row(
                Modifier.offset(y = (-6).dp).padding(horizontal = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                m.reactions.forEach { r ->
                    Row(
                        Modifier
                            .clip(RoundedCornerShape(50))
                            .background(Color.White)
                            .border(
                                1.dp,
                                if (r.mine) HaraanColors.EventsBlue.copy(alpha = 0.5f) else HaraanColors.BorderLight,
                                RoundedCornerShape(50),
                            )
                            .padding(horizontal = 7.dp, vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(r.emoji, fontSize = 12.sp)
                        // The count only earns its place once more than one person reacted.
                        if (r.count > 1) {
                            Spacer(Modifier.width(3.dp))
                            Text(
                                "${r.count}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = HaraanColors.TextSecondary,
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * One tick sent, two delivered, two blue read — the convention everybody already knows, so
 * it needs no legend. Drawn as two overlapping checks rather than a "✓✓" string, which
 * renders differently on every device font.
 */
@Composable
private fun ReceiptTicks(status: Receipt) {
    val tint = when (status) {
        Receipt.READ -> Color(0xFF7DD3FC)          // the blue that reads on a blue bubble
        else -> Color.White.copy(alpha = 0.75f)
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Filled.Check, null, tint = tint, modifier = Modifier.size(12.dp))
        if (status != Receipt.SENT) {
            Icon(
                Icons.Filled.Check,
                null,
                tint = tint,
                modifier = Modifier.size(12.dp).offset(x = (-6).dp),
            )
        }
    }
}

/**
 * Which tick a message has earned, by comparing when it was sent against how far the other
 * side has got. Anything unknowable — no timestamps, a group, an unparseable date — returns
 * SENT rather than inventing a stronger claim.
 */
private fun receiptFor(sentAt: String?, deliveredAt: String?, readAt: String?): Receipt {
    val sent = parseIso(sentAt)?.time ?: return Receipt.SENT
    val read = parseIso(readAt)?.time
    if (read != null && read >= sent) return Receipt.READ
    val delivered = parseIso(deliveredAt)?.time
    if (delivered != null && delivered >= sent) return Receipt.DELIVERED
    return Receipt.SENT
}

/** The same fact in words, for the long-press sheet: "Sent 3:14 PM · Read 3:15 PM". */
private fun receiptDetail(m: ChatMessage, deliveredAt: String?, readAt: String?): String {
    val parts = mutableListOf<String>()
    timeLabel(m.sentAt)?.let { parts += "Sent $it" }
    if (m.mine && !m.deleted) {
        when (receiptFor(m.sentAt, deliveredAt, readAt)) {
            Receipt.READ -> timeLabel(readAt)?.let { parts += "Read $it" }
            Receipt.DELIVERED -> timeLabel(deliveredAt)?.let { parts += "Delivered $it" }
            Receipt.SENT -> parts += "Not delivered yet"
        }
    }
    return parts.joinToString("  ·  ").ifBlank { "No timestamp" }
}

/**
 * The long-press sheet: what this message is, how far it got, and what you can do about it.
 *
 * Rises from the bottom with the platform's own spring, carries a drag handle, and puts the
 * destructive action last behind a divider — the shape of the menus people already use every
 * day, rather than a dialog box that could belong to any app.
 */
@Composable
private fun MessageSheet(
    message: ChatMessage,
    deliveredAt: String?,
    readAt: String?,
    note: String,
    canUnsend: Boolean,
    onCopy: () -> Unit,
    onUnsend: () -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = androidx.compose.material3.rememberModalBottomSheetState()
    androidx.compose.material3.ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        dragHandle = { androidx.compose.material3.BottomSheetDefaults.DragHandle() },
    ) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 26.dp)) {
            if (!message.deleted) {
                // The message itself, quoted the way a reply would quote it — so there is
                // never a doubt about which bubble the actions apply to.
                Row(Modifier.fillMaxWidth()) {
                    Box(
                        Modifier
                            .width(3.dp)
                            .heightIn(min = 20.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(HaraanColors.EventsBlue.copy(alpha = 0.5f)),
                    )
                    Spacer(Modifier.width(10.dp))
                    Text(
                        message.body,
                        color = HaraanColors.TextPrimary,
                        fontSize = 14.sp,
                        lineHeight = 20.sp,
                        maxLines = 3,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                }
                Spacer(Modifier.height(18.dp))
            }

            // Receipt timeline. Three fixed rows, so the message's progress reads as a
            // journey with a next step rather than a single word that changes meaning.
            if (message.mine && !message.deleted) {
                val state = receiptFor(message.sentAt, deliveredAt, readAt)
                ReceiptRow(
                    "Sent", timeLabel(message.sentAt), reached = true, ticks = 1,
                )
                ReceiptRow(
                    "Delivered",
                    timeLabel(deliveredAt),
                    reached = state != Receipt.SENT,
                    ticks = 2,
                )
                ReceiptRow(
                    "Read",
                    timeLabel(readAt),
                    reached = state == Receipt.READ,
                    ticks = 2,
                    blue = true,
                )
            } else {
                ReceiptRow("Sent", timeLabel(message.sentAt), reached = true, ticks = 0)
            }

            Spacer(Modifier.height(14.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
            Spacer(Modifier.height(6.dp))

            if (!message.deleted) {
                SheetAction(Icons.Filled.ContentCopy, "Copy text", onClick = onCopy)
            }
            if (canUnsend) {
                SheetAction(
                    Icons.Filled.Block,
                    "Unsend for everyone",
                    tint = HaraanColors.Danger,
                    onClick = onUnsend,
                )
            }

            if (note.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(note, color = HaraanColors.Danger, fontSize = 12.5.sp)
            }
        }
    }
}

/** One step of the receipt timeline. Unreached steps are present but visibly not yet true. */
@Composable
private fun ReceiptRow(
    label: String,
    time: String?,
    reached: Boolean,
    ticks: Int,
    blue: Boolean = false,
) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        val tint = when {
            !reached -> HaraanColors.BorderLight
            blue -> HaraanColors.EventsBlue
            else -> HaraanColors.TextSecondary
        }
        Box(Modifier.width(26.dp), contentAlignment = Alignment.CenterStart) {
            if (ticks > 0) {
                Row {
                    Icon(Icons.Filled.Check, null, tint = tint, modifier = Modifier.size(14.dp))
                    if (ticks > 1) {
                        Icon(
                            Icons.Filled.Check,
                            null,
                            tint = tint,
                            modifier = Modifier.size(14.dp).offset(x = (-7).dp),
                        )
                    }
                }
            }
        }
        Spacer(Modifier.width(8.dp))
        Text(
            label,
            color = if (reached) HaraanColors.TextPrimary else HaraanColors.TextMuted,
            fontSize = 13.5.sp,
            fontWeight = if (reached) FontWeight.SemiBold else FontWeight.Normal,
            modifier = Modifier.weight(1f),
        )
        Text(
            // A step that hasn't happened says so, instead of showing a blank where a
            // time should be.
            if (reached) (time ?: "—") else "Not yet",
            color = if (reached) HaraanColors.TextSecondary else HaraanColors.TextMuted,
            fontSize = 12.5.sp,
        )
    }
}

/** One tappable row in the sheet: icon, label, full-width press target. */
@Composable
private fun SheetAction(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    tint: Color = HaraanColors.TextPrimary,
    onClick: () -> Unit,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .pressable(onClick = onClick)
            .padding(vertical = 14.dp, horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = tint, modifier = Modifier.size(19.dp))
        Spacer(Modifier.width(14.dp))
        Text(label, color = tint, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * Where to forward a message: your other conversations, newest first.
 *
 * The list comes from the threads already loaded for the inbox, minus this one — so the
 * picker can never offer a thread you are not in, and the server checks it again anyway.
 */
@Composable
private fun ForwardPicker(
    threads: List<com.haraan.app.data.ChatThread>,
    onPick: (com.haraan.app.data.ChatThread) -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = androidx.compose.material3.rememberModalBottomSheetState()
    androidx.compose.material3.ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
    ) {
        Column(Modifier.fillMaxWidth().padding(bottom = 24.dp)) {
            Text(
                "Forward to",
                color = HaraanColors.TextPrimary,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(horizontal = 20.dp, vertical = 8.dp),
            )
            if (threads.isEmpty()) {
                Text(
                    "You have no other conversations to forward this to.",
                    color = HaraanColors.TextSecondary,
                    fontSize = 13.5.sp,
                    modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp),
                )
            }
            threads.take(20).forEach { thread ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .pressable(haptic = Feel.COMMIT) { onPick(thread) }
                        .padding(horizontal = 20.dp, vertical = 11.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (thread.isGroup) GroupAvatar(thread, 40.dp) else Avatar(thread.avatar, thread.name, 40.dp)
                    Spacer(Modifier.width(12.dp))
                    Text(
                        thread.name,
                        color = HaraanColors.TextPrimary,
                        fontSize = 14.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f),
                    )
                    Icon(
                        Icons.AutoMirrored.Filled.Send,
                        null,
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(17.dp),
                    )
                }
            }
        }
    }
}

/** A message in flight, or one that failed and can be tapped to retry. */
@Composable
private fun PendingBubble(p: PendingMessage, onRetry: () -> Unit) {
    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.End) {
        BubbleShell(mine = true, dimmed = !p.failed, failed = p.failed, onClick = if (p.failed) onRetry else null) {
            Text(
                p.body,
                color = Color.White,
                fontSize = 14.5.sp,
                lineHeight = 20.sp,
            )
            Spacer(Modifier.height(3.dp))
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.align(Alignment.End)) {
                Icon(
                    if (p.failed) Icons.Filled.ErrorOutline else Icons.Filled.Schedule,
                    null,
                    tint = Color.White.copy(alpha = 0.85f),
                    modifier = Modifier.size(11.dp),
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    if (p.failed) "Tap to retry" else "Sending",
                    color = Color.White.copy(alpha = 0.85f),
                    fontSize = 10.5.sp,
                    fontWeight = if (p.failed) FontWeight.Bold else FontWeight.Normal,
                )
            }
        }
    }
}

/**
 * The bubble itself.
 *
 * Width is a FRACTION of the screen, not a fixed 280dp: on a 720px-wide phone that constant
 * was almost the entire width, so a two-word reply and a paragraph looked the same size and
 * the thread lost its shape.
 */
@Composable
private fun BubbleShell(
    mine: Boolean,
    dimmed: Boolean = false,
    failed: Boolean = false,
    /** An unsent message: no fill, just an outline, so it reads as an absence. */
    ghost: Boolean = false,
    onClick: (() -> Unit)? = null,
    onLongPress: (() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val shape = RoundedCornerShape(
        topStart = 16.dp,
        topEnd = 16.dp,
        // The squared corner points at its sender, so who said what is readable
        // without colour alone carrying it.
        bottomStart = if (mine) 16.dp else 4.dp,
        bottomEnd = if (mine) 4.dp else 16.dp,
    )
    val fill = when {
        ghost -> Color.Transparent
        failed -> HaraanColors.Danger
        mine && dimmed -> HaraanColors.EventsBlue.copy(alpha = 0.55f)
        mine -> HaraanColors.EventsBlue
        else -> HaraanColors.Field
    }
    // widthIn(max), NOT fillMaxWidth(fraction): a fraction makes every bubble that width,
    // so a two-word reply blew out to the same slab as a paragraph and the thread lost its
    // shape entirely. A max lets short messages hug their text and long ones wrap.
    val maxBubble = (LocalConfiguration.current.screenWidthDp * 0.78f).dp
    val view = androidx.compose.ui.platform.LocalView.current
    Column(
        Modifier
            .widthIn(max = maxBubble)
            .clip(shape)
            .background(fill)
            .then(
                if (!mine || ghost) Modifier.border(1.dp, HaraanColors.BorderLight, shape) else Modifier
            )
            .then(if (onClick != null) Modifier.pressable(onClick = onClick) else Modifier)
            .then(
                if (onLongPress != null) {
                    Modifier.combinedClickable(
                        onClick = {},
                        onLongClick = { view.performHapticFeedback(Feel.SELECT); onLongPress() },
                    )
                } else {
                    Modifier
                }
            )
            .padding(horizontal = 14.dp, vertical = 10.dp),
        content = content,
    )
}

/** "3 new messages" — where the reader left off, in the app's own blue. */
@Composable
private fun UnreadDivider(count: Int) {
    Row(
        Modifier.fillMaxWidth().padding(top = 14.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.weight(1f).height(1.dp).background(HaraanColors.EventsBlue.copy(alpha = 0.35f)))
        Text(
            if (count == 1) "1 new message" else "$count new messages",
            color = HaraanColors.EventsBlue,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 10.dp),
        )
        Box(Modifier.weight(1f).height(1.dp).background(HaraanColors.EventsBlue.copy(alpha = 0.35f)))
    }
}

/** The date rule between days — a centred, quiet marker, not a heading. */
@Composable
private fun DaySeparator(label: String) {
    Box(Modifier.fillMaxWidth().padding(vertical = 12.dp), contentAlignment = Alignment.Center) {
        Box(
            Modifier
                .clip(RoundedCornerShape(50))
                .background(HaraanColors.Field)
                .padding(horizontal = 12.dp, vertical = 5.dp),
        ) {
            Text(label, color = HaraanColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

/** "9:41 pm" for one message. Null when the server sent no timestamp — never a guess. */
private fun timeLabel(iso: String?): String? {
    val at = parseIso(iso) ?: return null
    return java.text.SimpleDateFormat("h:mm a", java.util.Locale.getDefault()).format(at)
}

/** "Today" / "Yesterday" / "12 Aug 2026" — what the date rule shows. */
private fun dayLabel(iso: String?): String? {
    val at = parseIso(iso) ?: return null
    val cal = java.util.Calendar.getInstance().apply { time = at }
    val now = java.util.Calendar.getInstance()
    fun sameDay(a: java.util.Calendar, b: java.util.Calendar) =
        a.get(java.util.Calendar.YEAR) == b.get(java.util.Calendar.YEAR) &&
            a.get(java.util.Calendar.DAY_OF_YEAR) == b.get(java.util.Calendar.DAY_OF_YEAR)
    if (sameDay(cal, now)) return "Today"
    val yesterday = java.util.Calendar.getInstance().apply { add(java.util.Calendar.DAY_OF_YEAR, -1) }
    if (sameDay(cal, yesterday)) return "Yesterday"
    val sameYear = cal.get(java.util.Calendar.YEAR) == now.get(java.util.Calendar.YEAR)
    val pattern = if (sameYear) "d MMM" else "d MMM yyyy"
    return java.text.SimpleDateFormat(pattern, java.util.Locale.getDefault()).format(at)
}

/** The server sends ISO-8601; a value it can't parse yields null rather than a wrong date. */
private fun parseIso(iso: String?): java.util.Date? {
    if (iso.isNullOrBlank()) return null
    val patterns = listOf(
        "yyyy-MM-dd'T'HH:mm:ss.SSSXXX",
        "yyyy-MM-dd'T'HH:mm:ssXXX",
        "yyyy-MM-dd HH:mm:ss",
    )
    for (p in patterns) {
        runCatching {
            return java.text.SimpleDateFormat(p, java.util.Locale.US).parse(iso)
        }
    }
    return null
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
