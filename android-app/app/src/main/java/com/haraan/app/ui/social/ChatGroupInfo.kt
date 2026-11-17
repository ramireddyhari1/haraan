package com.haraan.app.ui.social

import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Group
import androidx.compose.material.icons.filled.NotificationsOff
import androidx.compose.material.icons.filled.PersonAdd
import androidx.compose.material.icons.filled.PersonRemove
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ChatCandidate
import com.haraan.app.data.ChatInfo
import com.haraan.app.data.ChatMember
import com.haraan.app.data.DirectMessageRepository.Outcome
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch

/**
 * A group's info screen: its photo and name, everyone in it (admin first), and what you can do
 * about it — add people, mute, leave; the admin can also remove people.
 *
 * Every change is the server's decision: the screen asks, then re-reads, so what it shows is
 * always what the group actually is (and the "Ravi added Kiran" line lands in the thread).
 */
@Composable
fun ChatGroupInfoScreen(
    load: suspend () -> ChatInfo?,
    /** Mutual follows — who this viewer is allowed to add. */
    loadCandidates: suspend () -> List<ChatCandidate>,
    rename: suspend (String) -> Outcome,
    addMembers: suspend (List<String>) -> Outcome,
    removeMember: suspend (String) -> Outcome,
    setPhoto: suspend (ByteArray) -> String?,
    mute: suspend (hours: Int) -> Boolean?,
    leave: suspend () -> Boolean,
    onOpenProfile: (playerId: String) -> Unit,
    /** Something changed — the caller refreshes the thread header and the list. */
    onChanged: (ChatInfo) -> Unit,
    /** Left the group: the caller closes the thread too. */
    onLeft: () -> Unit,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var info by remember { mutableStateOf<ChatInfo?>(null) }
    var failed by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var renaming by remember { mutableStateOf(false) }
    var adding by remember { mutableStateOf(false) }
    var removing by remember { mutableStateOf<ChatMember?>(null) }
    var confirmLeave by remember { mutableStateOf(false) }
    var showMutePicker by remember { mutableStateOf(false) }

    suspend fun reload() {
        val fresh = load()
        if (fresh != null) {
            info = fresh
            failed = false
            onChanged(fresh)
        } else if (info == null) {
            failed = true
        }
    }

    fun toast(text: String) = android.widget.Toast.makeText(context, text, android.widget.Toast.LENGTH_SHORT).show()

    fun said(outcome: Outcome, ok: String?) {
        when (outcome) {
            Outcome.Ok -> ok?.let(::toast)
            is Outcome.Refused -> toast(outcome.message)
            Outcome.Failed -> toast("Couldn't reach Haraan. Try again.")
        }
    }

    LaunchedEffect(Unit) { reload() }
    BackHandler { if (adding) adding = false else onClose() }

    val pickPhoto = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) {
            busy = true
            scope.launch {
                val prepared = prepareChatPhoto(context, uri, maxEdge = 800)
                val url = prepared?.let { setPhoto(it.jpeg) }
                busy = false
                if (url == null) toast("Couldn't change the photo.") else reload()
            }
        }
    }

    Column(modifier.fillMaxSize().background(Color.White).statusBarsPadding()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(38.dp).clip(CircleShape).background(HaraanColors.Field).pressable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Text("Group info", color = HaraanColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.weight(1f))
            if (busy) CircularProgressIndicator(color = HaraanColors.EventsBlue, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
        }

        val current = info
        when {
            current == null && failed -> Column(
                Modifier.fillMaxSize().padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Text("Couldn't load this group.", color = HaraanColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(10.dp))
                Text(
                    "Try again",
                    color = HaraanColors.EventsBlue,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.clip(RoundedCornerShape(8.dp)).pressable { scope.launch { reload() } }.padding(10.dp),
                )
            }
            current == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = HaraanColors.EventsBlue)
            }
            else -> LazyColumn(Modifier.fillMaxSize(), contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 32.dp)) {
                item(key = "head") {
                    Column(Modifier.fillMaxWidth().padding(top = 8.dp, bottom = 18.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        // The photo: tap to change it. Any member may, as in every group chat.
                        Box(
                            Modifier.size(96.dp).pressable(enabled = !busy) {
                                pickPhoto.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
                            },
                        ) {
                            Box(
                                Modifier.fillMaxSize().clip(CircleShape).background(HaraanColors.Field),
                                contentAlignment = Alignment.Center,
                            ) {
                                if (current.avatar != null) {
                                    AsyncImage(current.avatar, null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize().clip(CircleShape))
                                } else {
                                    Icon(Icons.Filled.Group, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(40.dp))
                                }
                            }
                            Box(
                                Modifier.align(Alignment.BottomEnd).offset(x = 2.dp, y = 2.dp).size(30.dp)
                                    .clip(CircleShape).background(HaraanColors.EventsBlue),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(Icons.Filled.CameraAlt, "Change group photo", tint = Color.White, modifier = Modifier.size(15.dp))
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                        Row(
                            Modifier.clip(RoundedCornerShape(10.dp)).pressable { renaming = true }.padding(horizontal = 10.dp, vertical = 4.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(
                                current.title,
                                color = HaraanColors.TextPrimary,
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                maxLines = 2,
                                textAlign = TextAlign.Center,
                            )
                            Spacer(Modifier.width(6.dp))
                            Icon(Icons.Filled.Edit, "Rename group", tint = HaraanColors.TextMuted, modifier = Modifier.size(16.dp))
                        }
                        Text(
                            "Group · ${current.members.size} ${if (current.members.size == 1) "member" else "members"}",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                        )
                    }
                }

                item(key = "mute") {
                    InfoRow(
                        icon = Icons.Filled.NotificationsOff,
                        label = "Mute notifications",
                        detail = when {
                            !current.muted -> null
                            current.mutedUntil != null -> "Muted until ${mutedUntilLabel(current.mutedUntil)}"
                            else -> "Muted until you turn it back on"
                        },
                        trailing = {
                            Switch(
                                checked = current.muted,
                                onCheckedChange = { on ->
                                    if (on) {
                                        showMutePicker = true
                                    } else {
                                        scope.launch {
                                            if (mute(0) == null) toast("Couldn't unmute. Try again.") else reload()
                                        }
                                    }
                                },
                                colors = SwitchDefaults.colors(checkedTrackColor = HaraanColors.EventsBlue),
                            )
                        },
                    )
                }

                item(key = "section") {
                    Text(
                        "${current.members.size} ${if (current.members.size == 1) "MEMBER" else "MEMBERS"}",
                        color = HaraanColors.TextMuted,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(start = 16.dp, top = 20.dp, bottom = 6.dp),
                    )
                }

                // Adding is open to every member — but only people who follow THEM back, the same
                // rule that keeps every chat on Haraan from being an unwanted one.
                if (current.members.size < current.maxMembers) {
                    item(key = "add") {
                        InfoRow(icon = Icons.Filled.PersonAdd, label = "Add people", tint = HaraanColors.EventsBlue, onClick = { adding = true })
                    }
                }

                items(current.members, key = { it.playerId }) { member ->
                    MemberRow(
                        member = member,
                        canRemove = current.isAdmin && !member.isMe,
                        onOpen = { if (!member.isMe) onOpenProfile(member.playerId) },
                        onRemove = { removing = member },
                    )
                }

                item(key = "leave") {
                    Spacer(Modifier.height(18.dp))
                    InfoRow(
                        icon = Icons.AutoMirrored.Filled.Logout,
                        label = "Leave group",
                        tint = HaraanColors.Danger,
                        onClick = { confirmLeave = true },
                    )
                }
            }
        }
    }

    // ── Overlays ────────────────────────────────────────────────────────────────────
    val current = info
    if (renaming && current != null) {
        var name by remember { mutableStateOf(current.title) }
        AlertDialog(
            onDismissRequest = { renaming = false },
            title = { Text("Group name", fontWeight = FontWeight.Bold) },
            text = {
                Box(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(HaraanColors.Field)
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                ) {
                    BasicTextField(
                        value = name,
                        onValueChange = { name = it.take(80) },
                        singleLine = true,
                        textStyle = TextStyle(color = HaraanColors.TextPrimary, fontSize = 15.sp),
                        cursorBrush = SolidColor(HaraanColors.EventsBlue),
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            },
            confirmButton = {
                TextButton(enabled = name.isNotBlank(), onClick = {
                    renaming = false
                    scope.launch {
                        busy = true
                        val r = rename(name.trim())
                        busy = false
                        said(r, null)
                        if (r == Outcome.Ok) reload()
                    }
                }) { Text("Save", color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(onClick = { renaming = false }) { Text("Cancel", color = HaraanColors.TextSecondary) } },
            containerColor = Color.White,
        )
    }

    removing?.let { member ->
        AlertDialog(
            onDismissRequest = { removing = null },
            title = { Text("Remove ${member.name}?", fontWeight = FontWeight.Bold) },
            text = { Text("They'll stop getting this group's messages. Everyone sees that you removed them.") },
            confirmButton = {
                TextButton(onClick = {
                    removing = null
                    scope.launch {
                        busy = true
                        val r = removeMember(member.playerId)
                        busy = false
                        said(r, "${member.name} was removed.")
                        if (r == Outcome.Ok) reload()
                    }
                }) { Text("Remove", color = HaraanColors.Danger, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(onClick = { removing = null }) { Text("Cancel", color = HaraanColors.TextSecondary) } },
            containerColor = Color.White,
        )
    }

    if (confirmLeave && current != null) {
        AlertDialog(
            onDismissRequest = { confirmLeave = false },
            title = { Text("Leave ${current.title}?", fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    if (current.isAdmin && current.members.size > 1) {
                        "You'll stop getting its messages. The member who's been here longest becomes the admin."
                    } else {
                        "You'll stop getting its messages. A member can add you back."
                    },
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    confirmLeave = false
                    scope.launch {
                        busy = true
                        val ok = leave()
                        busy = false
                        if (ok) onLeft() else toast("Couldn't leave. Try again.")
                    }
                }) { Text("Leave", color = HaraanColors.Danger, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(onClick = { confirmLeave = false }) { Text("Cancel", color = HaraanColors.TextSecondary) } },
            containerColor = Color.White,
        )
    }

    if (showMutePicker) {
        MutePicker(
            onPick = { hours ->
                showMutePicker = false
                scope.launch { if (mute(hours) == null) toast("Couldn't mute. Try again.") else reload() }
            },
            onDismiss = { showMutePicker = false },
        )
    }

    if (adding && current != null) {
        AddMembersScreen(
            existing = current.members.map { it.playerId }.toSet(),
            room = (current.maxMembers - current.members.size).coerceAtLeast(0),
            loadCandidates = loadCandidates,
            onAdd = { ids ->
                scope.launch {
                    busy = true
                    val r = addMembers(ids)
                    busy = false
                    said(r, if (ids.size == 1) "Added." else "Added ${ids.size} people.")
                    if (r == Outcome.Ok) {
                        adding = false
                        reload()
                    }
                }
            },
            onClose = { adding = false },
        )
    }
}

/** The mutual follows not already in the group; pick several, add them in one go. */
@Composable
private fun AddMembersScreen(
    existing: Set<String>,
    room: Int,
    loadCandidates: suspend () -> List<ChatCandidate>,
    onAdd: (List<String>) -> Unit,
    onClose: () -> Unit,
) {
    var candidates by remember { mutableStateOf<List<ChatCandidate>?>(null) }
    var picked by remember { mutableStateOf<Set<String>>(emptySet()) }
    LaunchedEffect(Unit) { candidates = loadCandidates().filterNot { it.playerId in existing } }

    Column(Modifier.fillMaxSize().background(Color.White).statusBarsPadding().navigationBarsPadding()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(38.dp).clip(CircleShape).background(HaraanColors.Field).pressable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Close, "Close", tint = HaraanColors.TextPrimary, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Add people", color = HaraanColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                Text(
                    if (picked.isEmpty()) "Players who follow you back" else "${picked.size} selected",
                    color = HaraanColors.TextMuted,
                    fontSize = 12.sp,
                )
            }
            Text(
                "Add",
                color = if (picked.isEmpty()) HaraanColors.TextMuted else Color.White,
                fontWeight = FontWeight.Bold,
                fontSize = 14.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(if (picked.isEmpty()) HaraanColors.Field else HaraanColors.EventsBlue)
                    .pressable(enabled = picked.isNotEmpty(), haptic = Feel.COMMIT) { onAdd(picked.toList()) }
                    .padding(horizontal = 18.dp, vertical = 9.dp),
            )
        }

        when (val list = candidates) {
            null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = HaraanColors.EventsBlue)
            }
            else -> if (list.isEmpty()) {
                Text(
                    "Everyone who follows you back is already here. Follow more players to add them.",
                    color = HaraanColors.TextSecondary,
                    fontSize = 14.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth().padding(32.dp),
                )
            } else {
                LazyColumn(Modifier.fillMaxSize()) {
                    items(list, key = { it.playerId }) { cand ->
                        val selected = cand.playerId in picked
                        CandidateRow(cand, selected) {
                            picked = when {
                                selected -> picked - cand.playerId
                                picked.size >= room -> picked // the /control size cap
                                else -> picked + cand.playerId
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun MemberRow(member: ChatMember, canRemove: Boolean, onOpen: () -> Unit, onRemove: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().pressable(onClick = onOpen).padding(horizontal = 16.dp, vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Avatar(member.avatar, member.name, 44.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                if (member.isMe) "You" else member.name,
                color = HaraanColors.TextPrimary,
                fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            member.username?.let {
                Text("@$it", color = HaraanColors.TextMuted, fontSize = 12.5.sp, maxLines = 1)
            }
        }
        if (member.isAdmin) {
            Text(
                "Admin",
                color = HaraanColors.EventsBlue,
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(HaraanColors.EventsBlue.copy(alpha = 0.1f))
                    .padding(horizontal = 9.dp, vertical = 3.dp),
            )
        }
        if (canRemove) {
            Spacer(Modifier.width(4.dp))
            Box(
                Modifier.size(36.dp).clip(CircleShape).pressable(haptic = Feel.REMOVE, onClick = onRemove),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.PersonRemove, "Remove ${member.name}", tint = HaraanColors.TextMuted, modifier = Modifier.size(19.dp))
            }
        }
    }
}

@Composable
private fun InfoRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    detail: String? = null,
    tint: Color = HaraanColors.TextSecondary,
    onClick: (() -> Unit)? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .then(if (onClick != null) Modifier.pressable(onClick = onClick) else Modifier)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(44.dp).clip(CircleShape).background(HaraanColors.Field), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = tint, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                label,
                color = if (tint == HaraanColors.TextSecondary) HaraanColors.TextPrimary else tint,
                fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold,
            )
            detail?.let { Text(it, color = HaraanColors.TextMuted, fontSize = 12.5.sp) }
        }
        trailing?.invoke()
    }
}

/** "9:30 PM" today, "Mon 9:30 PM" within the week, else "12 Nov". */
private fun mutedUntilLabel(iso: String): String = try {
    val until = java.time.OffsetDateTime.parse(iso).atZoneSameInstant(java.time.ZoneId.systemDefault())
    val now = java.time.ZonedDateTime.now()
    val days = java.time.Duration.between(now, until).toDays()
    val pattern = when {
        until.toLocalDate() == now.toLocalDate() -> "h:mm a"
        days < 7 -> "EEE h:mm a"
        else -> "d MMM"
    }
    until.format(java.time.format.DateTimeFormatter.ofPattern(pattern))
} catch (_: Exception) {
    "later"
}
