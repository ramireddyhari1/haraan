package com.haraan.app.ui.membership

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.requiredSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.TokenStore
import com.haraan.app.data.membership.AppIconCatalog
import com.haraan.app.data.membership.AppIconSlot
import com.haraan.app.data.membership.AppIcons
import com.haraan.app.data.membership.MembershipRepository
import com.haraan.app.ui.Feel
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

private val TileSize = 64.dp
private val TileShape = RoundedCornerShape(16.dp)

/**
 * "App icon": the member swaps Haraan's home-screen icon.
 *
 * Which icons show, which are unlocked, and which plan unlocks a locked one all come from the
 * server (/control → Finance → App icons, via [AppIconCatalog]); this build only owns the art. A
 * locked icon still shows in full colour — the honest picture of what that plan gives — and a
 * tap on it opens that plan in the showcase above instead of doing nothing.
 *
 * Feel, because this is a physical object on their phone:
 *  - press sinks the tile into the page (it shrinks and its shadow tightens) while the finger is
 *    down, and it springs back past rest on release;
 *  - picking an icon lands with the app's knock vocabulary and settles like a coin set down;
 *  - a locked tile shakes its head once with a reject haptic.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun AppIconPicker(
    onShowPlan: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    var catalog by remember { mutableStateOf<AppIconCatalog?>(null) }
    LaunchedEffect(Unit) {
        catalog = runCatching {
            withContext(Dispatchers.IO) {
                MembershipRepository().appIcons(TokenStore.getSignedInToken(context))
            }
        }.getOrNull()
    }
    var current by remember { mutableStateOf(AppIcons.current(context)) }
    var switched by remember { mutableStateOf(false) }
    // Bumped on each landing so only the tile just picked plays its settle.
    var landed by remember { mutableIntStateOf(0) }

    val offers = catalog?.known ?: return
    // Only the default on offer: nothing to choose between.
    if (offers.size < 2) return

    Column(modifier) {
        Text("App icon", color = HaraanColors.TextPrimary, fontSize = 20.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.3).sp)
        Spacer(Modifier.height(4.dp))
        Text(
            "The Haraan icon on your home screen.",
            color = HaraanColors.TextSecondary,
            fontSize = 13.sp,
        )
        Spacer(Modifier.height(16.dp))
        FlowRow(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            offers.forEach { (icon, offer) ->
                IconTile(
                    icon = icon,
                    label = offer.name,
                    selected = icon == current,
                    locked = !offer.unlocked,
                    lockLabel = offer.unlockedBy?.name,
                    landedTick = if (icon == current) landed else 0,
                    onPick = {
                        if (icon == current) return@IconTile
                        if (AppIcons.apply(context, icon)) {
                            current = icon
                            switched = true
                            landed++
                            scope.launch {
                                cricketThud(
                                    context,
                                    when (icon) {
                                        AppIconSlot.DEFAULT -> Thud.RUN
                                        AppIconSlot.HERO -> Thud.SIX
                                        else -> Thud.FOUR
                                    },
                                )
                            }
                        } else {
                            view.performHapticFeedback(Feel.REMOVE)
                        }
                    },
                    onLocked = {
                        view.performHapticFeedback(Feel.REMOVE)
                        offer.unlockedBy?.let { onShowPlan(it.code) }
                    },
                )
            }
        }
        AnimatedVisibility(switched, enter = fadeIn(tween(220)), exit = fadeOut()) {
            Text(
                "Your home screen updates in a few seconds. If you'd placed Haraan on it, some phones need it added again.",
                color = HaraanColors.TextMuted,
                fontSize = 12.sp,
                lineHeight = 16.sp,
                modifier = Modifier.padding(top = 14.dp),
            )
        }
    }
}

@Composable
private fun IconTile(
    icon: AppIconSlot,
    label: String,
    selected: Boolean,
    locked: Boolean,
    lockLabel: String?,
    landedTick: Int,
    onPick: () -> Unit,
    onLocked: () -> Unit,
) {
    val scope = rememberCoroutineScope()
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()

    // Pushed into the page while held; released with a little overshoot.
    val pressScale by animateFloatAsState(
        targetValue = if (pressed) 0.9f else 1f,
        animationSpec = spring(dampingRatio = 0.42f, stiffness = if (pressed) 1400f else 520f),
        label = "iconPress",
    )
    val lift by animateDpAsState(
        targetValue = if (pressed) 1.dp else 8.dp,
        animationSpec = spring(stiffness = Spring.StiffnessMedium),
        label = "iconLift",
    )
    // The picked icon lands: a quick swell that settles, like a coin set down on a table.
    val settle = remember { Animatable(1f) }
    LaunchedEffect(landedTick) {
        if (landedTick == 0) return@LaunchedEffect
        settle.snapTo(1.1f)
        settle.animateTo(1f, spring(dampingRatio = 0.35f, stiffness = 380f))
    }
    // A locked tile shakes its head once.
    val shake = remember { Animatable(0f) }

    val accent = when (icon) {
        AppIconSlot.PRO -> MemberTierStyles.Pro.accent
        AppIconSlot.HERO, AppIconSlot.FESTIVE -> MemberTierStyles.Hero.accent
        else -> HaraanColors.EventsBlue
    }
    val ring by animateFloatAsState(
        targetValue = if (selected) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.6f, stiffness = 500f),
        label = "iconRing",
    )

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.semantics(mergeDescendants = true) {
            role = Role.RadioButton
            this.selected = selected
            contentDescription = when {
                locked && lockLabel != null -> "$label icon, included with $lockLabel"
                locked -> "$label icon, locked"
                else -> "$label icon"
            }
        },
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(TileSize + 12.dp)) {
            // Selection ring: draws in from slightly wide, so it reads as closing around the icon.
            Box(
                Modifier
                    .size(TileSize + 10.dp)
                    .graphicsLayer {
                        alpha = ring
                        val s = 1.08f - 0.08f * ring
                        scaleX = s; scaleY = s
                    }
                    .border(2.dp, accent, RoundedCornerShape(20.dp)),
            )
            Box(
                Modifier
                    .graphicsLayer {
                        val s = pressScale * settle.value
                        scaleX = s; scaleY = s
                        translationX = shake.value
                    }
                    .shadow(lift, TileShape, ambientColor = Color.Black.copy(alpha = 0.25f), spotColor = Color.Black.copy(alpha = 0.35f))
                    .clip(TileShape)
                    .clickable(interactionSource = interaction, indication = null) {
                        if (locked) {
                            scope.launch {
                                for (x in floatArrayOf(10f, -8f, 6f, -3f, 0f)) shake.animateTo(x, tween(45))
                            }
                            onLocked()
                        } else {
                            onPick()
                        }
                    },
            ) {
                LauncherIcon(icon, TileSize)
            }
            if (selected) {
                Box(
                    Modifier
                        .align(Alignment.BottomEnd)
                        .offset(x = (-1).dp, y = (-1).dp)
                        .graphicsLayer { scaleX = ring; scaleY = ring }
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(Color.White)
                        .padding(2.dp)
                        .clip(CircleShape)
                        .background(accent),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Filled.Check,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier.size(12.dp),
                    )
                }
            }
        }
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (locked) {
                Icon(Icons.Filled.Lock, contentDescription = null, tint = HaraanColors.TextMuted, modifier = Modifier.size(11.dp))
                Spacer(Modifier.width(3.dp))
            }
            Text(
                label,
                color = if (selected) HaraanColors.TextPrimary else HaraanColors.TextSecondary,
                fontSize = 12.sp,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            )
        }
    }
}

/**
 * The launcher icon as the launcher draws it: both adaptive layers on the full 108dp canvas,
 * scaled so the middle 72dp fills [size], clipped to a rounded tile.
 */
@Composable
private fun LauncherIcon(icon: AppIconSlot, size: Dp) {
    val canvas = size * (108f / 72f)
    Box(Modifier.size(size).clip(TileShape), contentAlignment = Alignment.Center) {
        Image(painterResource(icon.plate), contentDescription = null, modifier = Modifier.requiredSize(canvas))
        Image(painterResource(icon.mark), contentDescription = null, modifier = Modifier.requiredSize(canvas))
    }
}
