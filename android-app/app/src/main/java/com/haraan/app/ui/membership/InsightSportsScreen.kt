package com.haraan.app.ui.membership

import android.widget.Toast
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.data.membership.InsightSport
import com.haraan.app.data.membership.InsightSportsStatus
import com.haraan.app.data.membership.MembershipNav
import com.haraan.app.ui.theme.HaraanColors

// Clean white theme with Haraan Blue accent states (no green).
private object Pick {
    val Page = Color.White
    val Tile = Color.White
    val TileSelected = Color(0xFFEFF6FF)
    val Line = Color(0xFFE2E8F0)
    val Well = Color(0xFFF1F5F9)
    val Ink = HaraanColors.TextPrimary
    val Body = Color(0xFF475569)
    val Muted = Color(0xFF64748B)
    val Faint = Color(0xFF94A3B8)
    val Blue = HaraanColors.EventsBlue
    val BlueDeep = HaraanColors.GameHubDeep
    val Amber = Color(0xFFB45309)
}

/**
 * Advanced insights, per sport. Pro chooses up to its number of sports; Hero sees every sport
 * unlocked; Free sees them all locked with a way to the plans. Saving goes to the server,
 * which enforces the limit and the swap cooldown — the tiles only mirror those rules.
 */
@Composable
fun InsightSportsScreen(
    onClose: () -> Unit,
    vm: InsightSportsViewModel = viewModel(),
) {
    val state by vm.state.collectAsState()
    val context = LocalContext.current
    var confirm by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { vm.enter() }

    LaunchedEffect(state.message) {
        state.message?.let {
            Toast.makeText(context, it, Toast.LENGTH_SHORT).show()
            vm.consumeMessage()
        }
    }

    Column(Modifier.fillMaxSize().background(Pick.Page).statusBarsPadding()) {
        TopBar(onClose)

        val status = state.status
        when {
            !state.signedIn -> Message("Sign in to choose your insight sports.", "See plans") { MembershipNav.open() }
            state.loading -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = Pick.Blue) }
            status == null -> Message(state.loadError ?: "Couldn't load your sports.", "Retry", vm::load)
            else -> {
                LazyVerticalGrid(
                    columns = GridCells.Fixed(3),
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 24.dp),
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    item(span = { GridItemSpan(3) }, key = "intro") { Intro(status, state.draft) }
                    items(status.sports, key = { it.key }) { sport ->
                        SportTile(sport = sport, status = status, chosen = sport.key in state.draft, onTap = { vm.tap(sport.key) })
                    }
                    if (status.mode == "choose" && status.cooldownDays > 0) {
                        item(span = { GridItemSpan(3) }, key = "note") {
                            Row(Modifier.padding(top = 8.dp), verticalAlignment = Alignment.Top) {
                                Icon(Icons.Outlined.Schedule, null, tint = Pick.Faint, modifier = Modifier.padding(top = 2.dp).size(14.dp))
                                Spacer(Modifier.width(6.dp))
                                Text(
                                    "A sport stays on your list for ${status.cooldownDays} days after you choose it. Empty slots can be filled any time.",
                                    color = Pick.Muted, fontSize = 12.sp, lineHeight = 17.sp,
                                )
                            }
                        }
                    }
                }

                when (status.mode) {
                    "choose" -> SaveBar(
                        enabled = InsightSportPicker.canSave(status, state.draft),
                        saving = state.saving,
                        label = when {
                            InsightSportPicker.isDirty(status, state.draft) -> "Save sports"
                            status.selected.isEmpty() -> "Choose up to ${status.limit} sports"
                            else -> "Saved"
                        },
                        onSave = {
                            if (status.cooldownDays > 0 && InsightSportPicker.added(status, state.draft).isNotEmpty()) confirm = true
                            else vm.save()
                        },
                    )
                    "none" -> ActionBar("See plans") { MembershipNav.open() }
                    else -> Unit
                }

                if (confirm) {
                    val added = InsightSportPicker.added(status, state.draft)
                        .mapNotNull { key -> status.sports.firstOrNull { it.key == key }?.label }
                    AlertDialog(
                        onDismissRequest = { confirm = false },
                        containerColor = Pick.Page,
                        titleContentColor = Pick.Ink,
                        textContentColor = Pick.Body,
                        title = { Text("Save your sports?", fontWeight = FontWeight.Bold) },
                        text = {
                            Text("${added.joinToString(", ")} will stay on your list for ${status.cooldownDays} days before you can swap ${if (added.size == 1) "it" else "them"} out.")
                        },
                        confirmButton = {
                            TextButton(onClick = { confirm = false; vm.save() }) { Text("Save", color = Pick.Blue, fontWeight = FontWeight.Bold) }
                        },
                        dismissButton = {
                            TextButton(onClick = { confirm = false }) { Text("Keep editing", color = Pick.Muted) }
                        },
                    )
                }
            }
        }
    }
}

@Composable
private fun TopBar(onClose: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // 48dp target around a 38dp visual.
        Box(
            Modifier.size(48.dp).clip(CircleShape).clickable(onClick = onClose),
            contentAlignment = Alignment.Center,
        ) {
            Box(Modifier.size(38.dp).clip(CircleShape).background(Pick.Well), contentAlignment = Alignment.Center) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Pick.Ink, modifier = Modifier.size(18.dp))
            }
        }
        Spacer(Modifier.width(6.dp))
        Text("Insight sports", color = Pick.Ink, fontSize = 17.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.semantics { heading() })
    }
}

@Composable
private fun Intro(status: InsightSportsStatus, draft: Set<String>) {
    Column(Modifier.fillMaxWidth().padding(top = 6.dp, bottom = 12.dp)) {
        status.plan.name?.let { plan ->
            Text(
                "${plan.uppercase()} PLAN",
                color = if (status.mode == "none") Pick.Muted else Pick.BlueDeep,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.1.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(6.dp))
                    .background(if (status.mode == "none") Pick.Well else Pick.TileSelected)
                    .padding(horizontal = 8.dp, vertical = 3.dp),
            )
            Spacer(Modifier.height(10.dp))
        }
        Text(
            when (status.mode) {
                "all" -> "Every sport, unlocked"
                "choose" -> "Choose your ${status.limit} sports"
                else -> "Advanced insights are locked"
            },
            color = Pick.Ink, fontSize = 26.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.5).sp, lineHeight = 31.sp,
            modifier = Modifier.semantics { heading() },
        )
        Spacer(Modifier.height(6.dp))
        Text(
            when (status.mode) {
                "all" -> "Match insights and AI reads for all ${status.sports.size} sports are included in your plan."
                "choose" -> "Match insights and AI reads unlock for the sports you pick."
                else -> "Pro unlocks insights for 3 sports you choose. Hero unlocks all ${status.sports.size}."
            },
            color = Pick.Body, fontSize = 14.sp, lineHeight = 20.sp,
        )

        if (status.mode == "choose") {
            Spacer(Modifier.height(16.dp))
            SelectionCount(status, draft)
            if (status.overLimit) {
                Spacer(Modifier.height(8.dp))
                Text(
                    "Your plan now covers ${status.limit} sports. Remove one to keep your list in step.",
                    color = Pick.Amber, fontSize = 12.5.sp, lineHeight = 17.sp,
                )
            }
        }
    }
}

/** "2 of 3 sports selected", with one pill per slot filling as sports are picked. */
@Composable
private fun SelectionCount(status: InsightSportsStatus, draft: Set<String>) {
    val limit = status.limit ?: 0
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Pick.Well)
            .padding(horizontal = 14.dp, vertical = 11.dp)
            // Announced when it changes, so a screen-reader user hears the count move.
            .semantics { liveRegion = LiveRegionMode.Polite },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        AnimatedContent(
            targetState = draft.size,
            transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(120)) },
            label = "count",
        ) { count ->
            Text(
                InsightSportPicker.countLine(count, limit),
                color = Pick.Ink, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.weight(1f))
        repeat(limit) { i ->
            val filled = i < draft.size
            val width by animateDpAsState(if (filled) 22.dp else 14.dp, spring(stiffness = Spring.StiffnessMediumLow), label = "slot-w")
            val color by animateColorAsState(if (filled) Pick.Blue else Pick.Line, label = "slot-c")
            Box(
                Modifier
                    .padding(start = 5.dp)
                    .width(width)
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp))
                    .background(color),
            )
        }
    }
}

@Composable
private fun SportTile(sport: InsightSport, status: InsightSportsStatus, chosen: Boolean, onTap: () -> Unit) {
    val choosable = status.mode == "choose"
    val highlighted = status.mode == "all" || (choosable && chosen)
    val locked = status.mode == "none"
    val held = choosable && chosen && sport.selected && !status.overLimit && sport.lockedUntil != null
    val heldUntil = if (held) MembershipFormat.date(sport.lockedUntil) else null

    val stateLine = when {
        status.mode == "all" -> "Included"
        locked -> "Locked"
        heldUntil != null -> "Until $heldUntil"
        chosen && sport.selected -> if (sport.unlocked) "Selected" else "Over limit"
        chosen -> "Save to unlock"
        else -> "Tap to add"
    }

    val border by animateColorAsState(if (highlighted) Pick.Blue else Pick.Line, tween(180), label = "tile-border")
    val fill by animateColorAsState(if (highlighted && choosable) Pick.TileSelected else Pick.Tile, tween(180), label = "tile-fill")
    val borderWidth by animateDpAsState(if (highlighted) 2.dp else 1.dp, tween(180), label = "tile-bw")

    // A small press-and-settle on each change of selection — felt, not watched.
    val bounce = remember { Animatable(1f) }
    var first by remember { mutableStateOf(true) }
    LaunchedEffect(chosen) {
        if (first) { first = false; return@LaunchedEffect }
        bounce.snapTo(0.94f)
        bounce.animateTo(1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium))
    }

    val shape = RoundedCornerShape(18.dp)
    Box(
        Modifier
            .fillMaxWidth()
            .heightIn(min = 132.dp)
            .scale(bounce.value)
            .shadow(if (highlighted) 0.dp else 1.dp, shape, ambientColor = Color(0x14000000), spotColor = Color(0x14000000))
            .clip(shape)
            .background(fill)
            .border(borderWidth, border, shape)
            .toggleable(value = chosen, enabled = choosable, role = Role.Checkbox, onValueChange = { onTap() })
            .semantics(mergeDescendants = true) {
                contentDescription = sport.label
                stateDescription = stateLine
            },
    ) {
        Column(
            Modifier.fillMaxWidth().padding(start = 8.dp, end = 8.dp, top = 14.dp, bottom = 12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            SportThumbnail(sport.key, size = 58.dp, dimmed = locked)
            Spacer(Modifier.height(10.dp))
            Text(
                sport.label,
                color = if (locked) Pick.Muted else Pick.Ink,
                fontSize = 13.5.sp,
                fontWeight = FontWeight.SemiBold,
                textAlign = TextAlign.Center,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(2.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (heldUntil != null) {
                    Icon(Icons.Outlined.Schedule, null, tint = Pick.Muted, modifier = Modifier.size(11.dp))
                    Spacer(Modifier.width(3.dp))
                }
                Text(
                    stateLine,
                    color = when {
                        highlighted && (sport.unlocked || status.mode == "all") -> Pick.BlueDeep
                        else -> Pick.Muted
                    },
                    fontSize = 11.sp,
                    fontWeight = if (highlighted) FontWeight.Medium else FontWeight.Normal,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }

        // Corner badge: a Haraan blue check for selected/included, a lock when the plan excludes it.
        Box(Modifier.align(Alignment.TopEnd).padding(8.dp)) {
            AnimatedVisibility(
                visible = highlighted,
                enter = scaleIn(spring(dampingRatio = Spring.DampingRatioMediumBouncy)) + fadeIn(),
                exit = scaleOut(tween(120)) + fadeOut(tween(120)),
            ) {
                Box(
                    Modifier.size(20.dp).clip(CircleShape).background(Pick.Blue),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(13.dp)) }
            }
            if (locked) {
                Box(
                    Modifier.size(20.dp).clip(CircleShape).background(Pick.Well),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Lock, null, tint = Pick.Faint, modifier = Modifier.size(11.dp)) }
            }
        }
    }
}

@Composable
private fun SaveBar(enabled: Boolean, saving: Boolean, label: String, onSave: () -> Unit) {
    BottomBar {
        val bg by animateColorAsState(if (enabled || saving) Pick.Blue else Pick.Well, label = "save-bg")
        Row(
            Modifier
                .fillMaxWidth()
                .heightIn(min = 52.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(bg)
                .clickable(enabled = enabled && !saving, role = Role.Button, onClick = onSave)
                .padding(vertical = 15.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (saving) {
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(10.dp))
            }
            Text(
                if (saving) "Saving…" else label,
                color = if (enabled || saving) Color.White else Pick.Muted,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
private fun ActionBar(label: String, onClick: () -> Unit) {
    BottomBar {
        Box(
            Modifier
                .fillMaxWidth()
                .heightIn(min = 52.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(HaraanColors.EventsBlue)
                .clickable(role = Role.Button, onClick = onClick)
                .padding(vertical = 15.dp),
            contentAlignment = Alignment.Center,
        ) { Text(label, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold) }
    }
}

@Composable
private fun BottomBar(content: @Composable () -> Unit) {
    Column(Modifier.fillMaxWidth().background(Pick.Page)) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(Pick.Line))
        Box(Modifier.fillMaxWidth().navigationBarsPadding().padding(horizontal = 16.dp, vertical = 12.dp)) { content() }
    }
}

@Composable
private fun Message(text: String, action: String, onAction: () -> Unit) {
    Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(text, color = Pick.Body, fontSize = 14.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(14.dp))
            Box(
                Modifier
                    .heightIn(min = 48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(HaraanColors.EventsBlue)
                    .clickable(role = Role.Button, onClick = onAction)
                    .padding(horizontal = 22.dp, vertical = 13.dp),
                contentAlignment = Alignment.Center,
            ) { Text(action, color = Color.White, fontWeight = FontWeight.Bold) }
        }
    }
}
