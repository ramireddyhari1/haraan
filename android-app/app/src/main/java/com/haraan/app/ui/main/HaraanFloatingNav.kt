package com.haraan.app.ui.main

import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.ui.Feel
import com.haraan.app.ui.theme.HaraanColors

/**
 * The member app's navigation glyphs.
 *
 * Drawn in the same hand as the partner app's bar (`partner-app/.../HaraanNavIcons.kt`)
 * so both Haraan apps speak one icon language: 24x24 viewport, round caps and joins,
 * a 1.7 resting stroke and a 2.05 selected stroke.
 *
 * Every glyph exists twice on the *same* skeleton — [outline] at rest, [active] with
 * the heavier stroke plus one element filled in — so the bar can cross-dissolve the
 * two and the eye reads one icon thickening, not a picture swap. Keep that property
 * when adding a glyph: never move a line between the variants.
 *
 * The previous bar mixed Material Filled/Outlined icons, a paper-plane for Chat and a
 * hand-drawn scoreboard with "2:0" text for Matches — five different visual weights.
 */
internal object MemberNavIcons {

    // ---- Home: a roof over a body, with an arched door ----------------------
    private const val HOUSE =
        "M3.7 10.3 L12 3.9 L20.3 10.3 V18.5 A2 2 0 0 1 18.3 20.5 H5.7 A2 2 0 0 1 3.7 18.5 Z"
    private const val DOOR = "M10 20.5 V16.4 A2 2 0 0 1 14 16.4 V20.5"

    val HomeOutline = icon("HomeOutline") {
        stroke(HOUSE, IDLE)
        stroke(DOOR, IDLE)
    }
    val HomeActive = icon("HomeActive") {
        stroke(HOUSE, ON)
        fill("$DOOR Z")
    }

    // ---- Matches: a corner flag ---------------------------------------------
    // Same glyph as the partner app's Matches tab. A scoreboard reads as a monitor
    // at 24.dp; the flag keeps its silhouette and says "on the pitch" in any sport.
    private const val FLAG = "M7.2 3.6 V20.4 M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2"
    private const val PENNANT = "M7.2 4.8 H18.2 L14.8 8.5 L18.2 12.2 H7.2 Z"

    val FlagOutline = icon("FlagOutline") {
        stroke(FLAG, IDLE)
    }
    val FlagActive = icon("FlagActive") {
        fill(PENNANT, alpha = 0.28f)
        stroke(FLAG, ON)
    }

    // ---- Chat: a speech bubble with two lines of text -----------------------
    private const val BUBBLE =
        "M4 6.6 A2.8 2.8 0 0 1 6.8 3.8 H17.2 A2.8 2.8 0 0 1 20 6.6 V13.8 " +
            "A2.8 2.8 0 0 1 17.2 16.6 H11.2 L7.2 19.9 V16.6 H6.8 A2.8 2.8 0 0 1 4 13.8 Z"
    private const val BUBBLE_LINES = "M8.4 8.6 H15.6 M8.4 11.8 H12.8"

    val ChatOutline = icon("ChatOutline") {
        stroke(BUBBLE, IDLE)
        stroke(BUBBLE_LINES, IDLE)
    }
    val ChatActive = icon("ChatActive") {
        // A wash, not a solid fill: solid would swallow the lines inside.
        fill(BUBBLE, alpha = 0.15f)
        stroke(BUBBLE, ON)
        stroke(BUBBLE_LINES, IDLE)
    }

    // ---- Alerts: a bell whose clapper fills in ------------------------------
    private const val BELL =
        "M6.3 16.6 V11.1 A5.7 5.7 0 0 1 17.7 11.1 V16.6 L19.3 18.3 H4.7 Z"
    private const val BELL_KNOB = "M12 3.4 V5.4"
    private const val CLAPPER = "M10.1 20.6 A1.9 1.9 0 0 0 13.9 20.6"
    private const val CLAPPER_SOLID = "M10.1 20.4 H13.9 A1.9 1.9 0 0 1 10.1 20.4 Z"

    val BellOutline = icon("BellOutline") {
        stroke(BELL, IDLE)
        stroke(BELL_KNOB, IDLE)
        stroke(CLAPPER, IDLE)
    }
    val BellActive = icon("BellActive") {
        fill(BELL, alpha = 0.15f)
        stroke(BELL, ON)
        stroke(BELL_KNOB, ON)
        fill(CLAPPER_SOLID)
    }

    // ---- Player: head and shoulders -----------------------------------------
    // Only shown when the account has no photo; otherwise the tab is their face.
    private const val HEAD = "M8.3 8 A3.7 3.7 0 1 1 15.7 8 A3.7 3.7 0 1 1 8.3 8 Z"
    private const val SHOULDERS =
        "M4.6 20.4 V19.4 A4.8 4.8 0 0 1 9.4 14.6 H14.6 A4.8 4.8 0 0 1 19.4 19.4 V20.4"

    val PlayerOutline = icon("PlayerOutline") {
        stroke(HEAD, IDLE)
        stroke(SHOULDERS, IDLE)
    }
    val PlayerActive = icon("PlayerActive") {
        fill(HEAD)
        stroke(SHOULDERS, ON)
    }

    // ---- Builders ----------------------------------------------------------

    private const val IDLE = 1.7f
    private const val ON = 2.05f

    private fun icon(name: String, body: ImageVector.Builder.() -> Unit): ImageVector =
        ImageVector.Builder(
            name = name,
            defaultWidth = 24.dp,
            defaultHeight = 24.dp,
            viewportWidth = 24f,
            viewportHeight = 24f,
        ).apply(body).build()

    // Laid down in black; `Icon` re-tints the vector and keeps per-path alpha, which
    // is how the two-tone selected state survives tinting.
    private fun ImageVector.Builder.stroke(pathData: String, width: Float) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = null,
            stroke = SolidColor(Color.Black),
            strokeLineWidth = width,
            strokeLineCap = StrokeCap.Round,
            strokeLineJoin = StrokeJoin.Round,
        )
    }

    private fun ImageVector.Builder.fill(pathData: String, alpha: Float = 1f) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = SolidColor(Color.Black),
            fillAlpha = alpha,
        )
    }
}

private data class FloatingNavItem(
    val label: String,
    val outline: ImageVector,
    val active: ImageVector,
)

private val FloatingNavItems = listOf(
    FloatingNavItem("Home", MemberNavIcons.HomeOutline, MemberNavIcons.HomeActive),
    FloatingNavItem("Matches", MemberNavIcons.FlagOutline, MemberNavIcons.FlagActive),
    FloatingNavItem("Chat", MemberNavIcons.ChatOutline, MemberNavIcons.ChatActive),
    FloatingNavItem("Alerts", MemberNavIcons.BellOutline, MemberNavIcons.BellActive),
    FloatingNavItem("Player", MemberNavIcons.PlayerOutline, MemberNavIcons.PlayerActive),
)

/** Labels in slot order — the index passed to `onSelect` indexes this list. */
internal val FloatingNavLabels: List<String> = FloatingNavItems.map { it.label }

private val NavAccent = HaraanColors.EventsBlue
private val NavIdle = Color(0xFF677285)
private val NavHairline = Color(0xFFE6EAF0)
private val NavShadow = Color(0xFF0F1B33)

private val BarHeight = 64.dp
private val IconTop = 9.dp
private val PillWidth = 54.dp
private val PillHeight = 30.dp

/**
 * The member app's floating bottom navigation.
 *
 * A white capsule inset from the screen edges, so the feed visibly scrolls beneath it.
 * One blue indicator lives on the bar and *travels* between slots on a spring, rather
 * than a lozenge popping in and out per item — the motion tells you where you went.
 *
 * Micro-interactions, all on the icon itself (no ripple):
 *  - the press squashes the glyph under the finger immediately,
 *  - the outline cross-dissolves into the filled variant as the indicator arrives,
 *  - the label warms from slate to blue on the same curve.
 *
 * Tapping the tab you're already on still calls [onSelect] (Matches uses it to scroll
 * back to the top); the haptic fires only when the tab actually changes.
 */
@Composable
internal fun HaraanFloatingNav(
    selectedIndex: Int,
    onSelect: (Int) -> Unit,
    modifier: Modifier = Modifier,
    avatarUrl: String? = null,
    hasUnreadAlerts: Boolean = false,
) {
    val view = LocalView.current
    val shape = RoundedCornerShape(26.dp)

    Box(
        modifier
            .fillMaxWidth()
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 10.dp)
    ) {
        BoxWithConstraints(
            Modifier
                .fillMaxWidth()
                .height(BarHeight)
                // A wide, low-opacity tinted shadow reads as lift; the default grey
                // elevation shadow reads as a Material card.
                .shadow(
                    elevation = 18.dp,
                    shape = shape,
                    ambientColor = NavShadow.copy(alpha = 0.08f),
                    spotColor = NavShadow.copy(alpha = 0.14f),
                )
                .background(Color.White, shape)
                .border(BorderStroke(1.dp, NavHairline), shape)
                .padding(horizontal = 6.dp)
        ) {
            val slot = maxWidth / FloatingNavItems.size
            val index = selectedIndex.coerceIn(0, FloatingNavItems.lastIndex)

            // Near-critically damped: it glides and lands with the faintest settle,
            // never wobbles past the neighbouring icon.
            val pillX by animateDpAsState(
                targetValue = slot * index + (slot - PillWidth) / 2,
                animationSpec = spring(dampingRatio = 0.78f, stiffness = 520f),
                label = "nav-pill-x",
            )
            Box(
                Modifier
                    .offset(x = pillX, y = IconTop)
                    .size(PillWidth, PillHeight)
                    .background(NavAccent.copy(alpha = 0.10f), CircleShape)
            )

            Row(Modifier.fillMaxSize(), verticalAlignment = Alignment.Top) {
                FloatingNavItems.forEachIndexed { i, item ->
                    val selected = i == index
                    NavSlot(
                        item = item,
                        selected = selected,
                        avatarUrl = if (item.label == "Player") avatarUrl else null,
                        showDot = item.label == "Alerts" && hasUnreadAlerts,
                        onClick = {
                            if (!selected) view.performHapticFeedback(Feel.SELECT)
                            onSelect(i)
                        },
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
private fun NavSlot(
    item: FloatingNavItem,
    selected: Boolean,
    avatarUrl: String?,
    showDot: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()

    val onRaw by animateFloatAsState(
        targetValue = if (selected) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.8f, stiffness = 480f),
        label = "nav-on-${item.label}",
    )
    // The spring settles with a hair of overshoot; alpha and colour must stay in range.
    val on = onRaw.coerceIn(0f, 1f)
    // Stiff so the squash lands under the finger, not after it.
    val squash by animateFloatAsState(
        targetValue = if (pressed) 0.86f else 1f,
        animationSpec = spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessHigh),
        label = "nav-press-${item.label}",
    )
    val tint = lerp(NavIdle, NavAccent, on)

    Column(
        modifier
            .fillMaxHeight()
            .selectable(
                selected = selected,
                interactionSource = interaction,
                indication = null,
                role = Role.Tab,
                onClick = onClick,
            )
            .padding(top = IconTop),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Top,
    ) {
        Box(
            Modifier
                .size(PillWidth, PillHeight)
                .graphicsLayer {
                    scaleX = squash
                    scaleY = squash
                },
            contentAlignment = Alignment.Center,
        ) {
            var avatarFailed by remember(avatarUrl) { mutableStateOf(false) }
            if (!avatarUrl.isNullOrBlank() && !avatarFailed) {
                // The Player tab is the account's own face. The ring does the job the
                // glyph's fill does elsewhere: slate hairline at rest, blue when here.
                AsyncImage(
                    model = avatarUrl,
                    contentDescription = null,
                    contentScale = ContentScale.Crop,
                    onError = { avatarFailed = true },
                    modifier = Modifier
                        .size(24.dp)
                        .border(if (selected) 1.8.dp else 1.dp, if (selected) NavAccent else NavHairline, CircleShape)
                        .padding(if (selected) 2.dp else 1.dp)
                        .clip(CircleShape),
                )
            } else {
                Icon(
                    item.outline,
                    contentDescription = null,
                    tint = NavIdle,
                    modifier = Modifier.size(23.dp).alpha(1f - on),
                )
                Icon(
                    item.active,
                    contentDescription = null,
                    tint = NavAccent,
                    modifier = Modifier.size(23.dp).alpha(on),
                )
            }
            if (showDot) {
                Box(
                    Modifier
                        .align(Alignment.Center)
                        .offset(x = 7.dp, y = (-8).dp)
                        .size(9.dp)
                        .background(Color.White, CircleShape)
                        .padding(1.8.dp)
                        .background(NavAccent, CircleShape)
                )
            }
        }
        Spacer(Modifier.height(3.dp))
        Text(
            item.label,
            fontSize = 11.sp,
            lineHeight = 13.sp,
            letterSpacing = 0.1.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
            color = tint,
            maxLines = 1,
        )
    }
}
