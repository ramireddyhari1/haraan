package com.haraan.app.ui.social

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Poll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ChatPoll
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

/**
 * A poll inside a bubble. Tap an answer to vote (tap it again to take it back); a
 * multiple-choice poll toggles each one. Every answer shows its bar and count once anyone
 * has voted, and in a group the names under each answer — "who's in" is the point.
 */
@Composable
internal fun PollCard(poll: ChatPoll, mine: Boolean, onVote: ((List<Long>) -> Unit)?) {
    val fg = if (mine) Color.White else HaraanColors.TextPrimary
    val sub = if (mine) Color.White.copy(alpha = 0.8f) else HaraanColors.TextSecondary
    val accent = if (mine) Color.White else HaraanColors.EventsBlue
    val total = poll.options.sumOf { it.votes }.coerceAtLeast(1)
    val myVotes = poll.options.filter { it.mine }.map { it.id }

    Column(Modifier.widthIn(min = 220.dp, max = 260.dp).animateContentSize()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Poll, null, tint = accent, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(
                if (poll.multiple) "POLL · PICK ANY" else "POLL",
                color = sub, fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp,
            )
        }
        Spacer(Modifier.height(5.dp))
        Text(poll.question, color = fg, fontSize = 15.sp, fontWeight = FontWeight.Bold, lineHeight = 20.sp)
        Spacer(Modifier.height(8.dp))
        poll.options.forEach { o ->
            val share by animateFloatAsState(o.votes.toFloat() / total, label = "pollShare")
            Column(
                Modifier
                    .fillMaxWidth()
                    .padding(vertical = 3.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .then(
                        if (onVote != null) {
                            Modifier.pressable(haptic = Feel.SELECT) {
                                val next = when {
                                    o.mine -> myVotes - o.id
                                    poll.multiple -> myVotes + o.id
                                    else -> listOf(o.id)
                                }
                                onVote(next)
                            }
                        } else Modifier
                    ),
            ) {
                Box(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .border(
                            1.dp,
                            if (o.mine) accent else (if (mine) Color.White.copy(alpha = 0.35f) else HaraanColors.BorderLight),
                            RoundedCornerShape(10.dp),
                        ),
                ) {
                    // The share of votes, as a fill behind the label.
                    Box(Modifier.matchParentSize()) {
                        Box(
                            Modifier
                                .fillMaxHeight()
                                .fillMaxWidth(share)
                                .background(accent.copy(alpha = if (mine) 0.22f else 0.10f)),
                        )
                    }
                    Row(
                        Modifier.fillMaxWidth().padding(horizontal = 10.dp, vertical = 9.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(
                            Modifier
                                .size(18.dp)
                                .clip(if (poll.multiple) RoundedCornerShape(4.dp) else CircleShape)
                                .background(if (o.mine) accent else Color.Transparent)
                                .border(1.5.dp, accent.copy(alpha = if (o.mine) 1f else 0.6f), if (poll.multiple) RoundedCornerShape(4.dp) else CircleShape),
                            contentAlignment = Alignment.Center,
                        ) {
                            if (o.mine) Icon(Icons.Filled.Check, null, tint = if (mine) HaraanColors.EventsBlue else Color.White, modifier = Modifier.size(12.dp))
                        }
                        Spacer(Modifier.width(9.dp))
                        Text(o.label, color = fg, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f), maxLines = 2, overflow = TextOverflow.Ellipsis)
                        if (o.votes > 0) {
                            Spacer(Modifier.width(8.dp))
                            Text("${o.votes}", color = fg, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
                if (o.voterNames.isNotEmpty()) {
                    Text(
                        o.voterNames.joinToString(", "),
                        color = sub,
                        fontSize = 11.sp,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.padding(start = 6.dp, top = 2.dp),
                    )
                }
            }
        }
        Spacer(Modifier.height(4.dp))
        Text(
            when (poll.voters) {
                0 -> "No votes yet"
                1 -> "1 person voted"
                else -> "${poll.voters} people voted"
            },
            color = sub,
            fontSize = 11.5.sp,
        )
    }
}

/** Make a poll: a question, 2–6 answers, and whether people may pick more than one. */
@Composable
internal fun PollComposer(onCreate: (question: String, options: List<String>, multiple: Boolean) -> Unit, onDismiss: () -> Unit) {
    var question by remember { mutableStateOf("") }
    val options = remember { mutableStateListOf("", "") }
    var multiple by remember { mutableStateOf(false) }
    val filled = options.map { it.trim() }.filter { it.isNotEmpty() }
    val canCreate = question.isNotBlank() && filled.distinct().size >= 2

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("New poll", fontWeight = FontWeight.Bold) },
        text = {
            Column {
                PollField(question, "Ask a question — e.g. Who's in Saturday 6am?", 200) { question = it }
                Spacer(Modifier.height(12.dp))
                Text("ANSWERS", color = HaraanColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
                Spacer(Modifier.height(6.dp))
                options.forEachIndexed { i, value ->
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 3.dp)) {
                        Box(Modifier.weight(1f)) { PollField(value, "Answer ${i + 1}", 80) { options[i] = it } }
                        if (options.size > 2) {
                            Box(
                                Modifier.size(34.dp).clip(CircleShape).pressable { options.removeAt(i) },
                                contentAlignment = Alignment.Center,
                            ) { Icon(Icons.Filled.Close, "Remove answer", tint = HaraanColors.TextMuted, modifier = Modifier.size(16.dp)) }
                        }
                    }
                }
                if (options.size < 6) {
                    Row(
                        Modifier.clip(RoundedCornerShape(8.dp)).pressable { options.add("") }.padding(vertical = 8.dp, horizontal = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Filled.Add, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("Add an answer", color = HaraanColors.EventsBlue, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("People can pick more than one", color = HaraanColors.TextPrimary, fontSize = 14.sp, modifier = Modifier.weight(1f))
                    Switch(
                        checked = multiple,
                        onCheckedChange = { multiple = it },
                        colors = SwitchDefaults.colors(checkedTrackColor = HaraanColors.EventsBlue),
                    )
                }
            }
        },
        confirmButton = {
            TextButton(enabled = canCreate, onClick = { onCreate(question.trim(), filled.distinct(), multiple) }) {
                Text("Send poll", color = if (canCreate) HaraanColors.EventsBlue else HaraanColors.TextMuted, fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel", color = HaraanColors.TextSecondary) } },
        containerColor = Color.White,
    )
}

@Composable
private fun PollField(value: String, hint: String, max: Int, onChange: (String) -> Unit) {
    Box(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).background(HaraanColors.Field).padding(horizontal = 12.dp, vertical = 11.dp),
    ) {
        if (value.isEmpty()) Text(hint, color = HaraanColors.TextMuted, fontSize = 14.sp)
        BasicTextField(
            value = value,
            onValueChange = { onChange(it.take(max)) },
            textStyle = TextStyle(color = HaraanColors.TextPrimary, fontSize = 14.sp),
            cursorBrush = SolidColor(HaraanColors.EventsBlue),
            maxLines = 3,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
