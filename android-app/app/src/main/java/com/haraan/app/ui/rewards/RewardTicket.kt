package com.haraan.app.ui.rewards

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
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
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.PlayCircle
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.rewards.RewardGeo
import com.haraan.app.data.rewards.RewardItem
import com.haraan.app.data.rewards.RewardStub
import com.haraan.app.ui.Feel
import com.haraan.app.ui.openExternalUrl
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.delay
import java.util.Locale
import kotlin.math.roundToInt

private val PassShape = RoundedCornerShape(16.dp)

/** What a ticket/pass can do; the screen supplies the handlers. */
internal data class TicketActions(
    val claiming: Boolean = false,
    val watching: Boolean = false,
    val adsAvailable: Boolean = false,
    val onClaim: () -> Unit = {},
    val onWatch: () -> Unit = {},
    val onReveal: () -> Unit = {},
    val onUse: (scope: String?) -> Unit = {},
)

/**
 * Modern Athletic Digital Wallet Pass:
 * Premium elevated card with handcrafted typography, dedicated value anchor badge,
 * tactile spring micro-interactions, and distinct states for Ready, Claiming, Claimed, and Locked.
 */
@Composable
internal fun RewardTicket(item: RewardItem, full: RewardItem?, actions: TicketActions) {
    val locked = item.status == "locked"
    val claimed = item.status == "claimed" || item.status == "redeemed"

    // Tactile spring press for the whole card
    val cardInteraction = remember { MutableInteractionSource() }
    val cardPressed by cardInteraction.collectIsPressedAsState()
    val cardScale by animateFloatAsState(
        targetValue = if (cardPressed) 0.985f else 1f,
        animationSpec = spring(dampingRatio = 0.75f, stiffness = 450f),
        label = "cardSpring",
    )

    Box(
        Modifier
            .fillMaxWidth()
            .graphicsLayer {
                scaleX = cardScale
                scaleY = cardScale
            }
            .clip(PassShape)
            .background(Board.Surface)
            .border(
                1.dp,
                if (claimed) Board.Green.copy(alpha = 0.35f) else Board.Line,
                PassShape,
            )
            .padding(14.dp),
    ) {
        Column(Modifier.fillMaxWidth()) {
            // 1. Partner Header Row
            PassHeader(item = item, locked = locked, claimed = claimed)

            Spacer(Modifier.height(10.dp))

            // 2. Value Anchor & Perk Title/Description Row
            Row(
                Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                // Visual Anchor: Handcrafted Value Badge
                PassValueAnchor(item = item, locked = locked, claimed = claimed)

                Spacer(Modifier.width(12.dp))

                // Perk Title and Scope Description
                Column(Modifier.weight(1f)) {
                    Text(
                        item.title,
                        color = if (locked) Board.InkMuted else Board.Ink,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Bold,
                        fontSize = 14.5.sp,
                        lineHeight = 19.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    item.description?.takeIf { it.isNotBlank() }?.let { desc ->
                        Spacer(Modifier.height(2.dp))
                        Text(
                            desc,
                            color = Board.InkMuted,
                            fontFamily = PlusJakartaSans,
                            fontSize = 12.sp,
                            lineHeight = 16.sp,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))

            // 3. State-driven Action / Voucher Footer (Animated with spring on claim)
            AnimatedContent(
                targetState = when {
                    claimed -> 2
                    locked -> 1
                    else -> 0
                },
                transitionSpec = {
                    (fadeIn(tween(220)) + scaleIn(spring(dampingRatio = 0.75f, stiffness = 380f), initialScale = 0.96f))
                        .togetherWith(fadeOut(tween(140)) + scaleOut(tween(140), targetScale = 0.96f))
                },
                label = "passFooterState",
            ) { state ->
                when (state) {
                    0 -> ReadyFooter(item = item, actions = actions)
                    1 -> LockedFooter(item = item, actions = actions)
                    else -> ClaimedFooter(item = item, full = full, actions = actions)
                }
            }

            // Optional Terms Link
            if (item.sponsored && item.termsUrl != null) {
                val ctx = LocalContext.current
                Spacer(Modifier.height(8.dp))
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        "Terms apply",
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Medium,
                        fontSize = 10.5.sp,
                        modifier = Modifier
                            .clip(RoundedCornerShape(4.dp))
                            .clickable { openExternalUrl(ctx, item.termsUrl) }
                            .padding(horizontal = 4.dp, vertical = 2.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun PassHeader(item: RewardItem, locked: Boolean, claimed: Boolean) {
    Row(
        Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        // Partner Monogram / Name Pill
        Row(verticalAlignment = Alignment.CenterVertically) {
            val sponsor = item.sponsor
            val partnerName = sponsor?.name ?: if (item.sponsored) "Partner" else "Haraan"
            val logoUrl = sponsor?.logo

            if (!logoUrl.isNullOrBlank()) {
                AsyncImage(
                    model = logoUrl,
                    contentDescription = partnerName,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(Board.Raised)
                        .border(0.8.dp, Board.Line, CircleShape),
                )
            } else {
                Box(
                    Modifier
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(Board.Raised)
                        .border(0.8.dp, Board.Line, CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        partnerName.take(1).uppercase(),
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 10.sp,
                    )
                }
            }

            Spacer(Modifier.width(7.dp))

            // Partner Name & Category
            Text(
                partnerName.uppercase(),
                color = Board.Ink,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                letterSpacing = 0.8.sp,
            )

            sponsor?.category?.let { cat ->
                Text(
                    " · ${cat.uppercase()}",
                    color = Board.InkFaint,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 10.5.sp,
                    letterSpacing = 0.6.sp,
                )
            }
        }

        // Status / Expiry Chip
        when {
            claimed -> {
                Row(
                    Modifier
                        .clip(RoundedCornerShape(50))
                        .background(Board.GreenWell)
                        .padding(horizontal = 8.dp, vertical = 2.5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Filled.Check, null, tint = Board.Green, modifier = Modifier.size(11.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        "CLAIMED",
                        color = Board.Green,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 10.sp,
                        letterSpacing = 0.8.sp,
                    )
                }
            }
            locked -> {
                Row(
                    Modifier
                        .clip(RoundedCornerShape(50))
                        .background(Board.Raised)
                        .border(0.8.dp, Board.Line, RoundedCornerShape(50))
                        .padding(horizontal = 8.dp, vertical = 2.5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Filled.Lock, null, tint = Board.InkFaint, modifier = Modifier.size(10.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        "LOCKED",
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Bold,
                        fontSize = 10.sp,
                        letterSpacing = 0.8.sp,
                    )
                }
            }
            else -> {
                item.expiresAt?.let {
                    Text(
                        "Valid until ${shortDate(it)}",
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Medium,
                        fontSize = 11.sp,
                    )
                }
            }
        }
    }
}

@Composable
private fun PassValueAnchor(item: RewardItem, locked: Boolean, claimed: Boolean) {
    val stub = item.stub
    val bg = when {
        claimed -> Board.GreenWell
        locked -> Board.Raised.copy(alpha = 0.6f)
        else -> Board.Raised
    }
    val borderColor = when {
        claimed -> Board.Green.copy(alpha = 0.3f)
        else -> Board.Line
    }
    val valueColor = when {
        claimed -> Board.Green
        locked -> Board.InkFaint
        else -> Board.Ink
    }
    val captionColor = when {
        claimed -> Board.Green.copy(alpha = 0.85f)
        locked -> Board.InkFaint
        else -> Board.InkFaint
    }

    Box(
        Modifier
            .size(width = 74.dp, height = 54.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(bg)
            .border(1.dp, borderColor, RoundedCornerShape(12.dp)),
        contentAlignment = Alignment.Center,
    ) {
        if (stub != null) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
                modifier = Modifier.padding(horizontal = 4.dp),
            ) {
                Text(
                    stub.value,
                    color = valueColor,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = if (stub.value.length > 4) 16.sp else 19.sp,
                    letterSpacing = (-0.5).sp,
                    maxLines = 1,
                    textAlign = TextAlign.Center,
                )
                Text(
                    stub.caption.uppercase(),
                    color = captionColor,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 9.sp,
                    letterSpacing = 1.sp,
                    maxLines = 1,
                    textAlign = TextAlign.Center,
                )
            }
        } else {
            Icon(
                if (locked) Icons.Filled.Lock else Icons.Filled.Check,
                null,
                tint = valueColor,
                modifier = Modifier.size(20.dp),
            )
        }
    }
}

@Composable
private fun ReadyFooter(item: RewardItem, actions: TicketActions) {
    Row(
        Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(
            "Match perk · Ready to claim",
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontSize = 11.5.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
        )
        TactileButton(
            text = if (actions.claiming) "Claiming…" else "Claim Perk",
            primary = true,
            loading = actions.claiming,
            onClick = actions.onClaim,
        )
    }
}

@Composable
private fun LockedFooter(item: RewardItem, actions: TicketActions) {
    val explanation = when {
        item.statusReason == "trust_too_low" -> "Unlocks when an organiser verifies the result"
        "verification" in item.lockReasons -> "Unlocks upon captain verification"
        "rewarded_ad" in item.lockReasons -> "Bonus perk: watch a short video to unlock"
        else -> "Locked pending result confirmation"
    }
    Row(
        Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(
            explanation,
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontSize = 11.5.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        if ("rewarded_ad" in item.lockReasons && item.canWatchAd && actions.adsAvailable) {
            Spacer(Modifier.width(8.dp))
            TactileButton(
                text = if (actions.watching) "Verifying…" else "Watch",
                primary = false,
                icon = Icons.Filled.PlayCircle,
                loading = actions.watching,
                onClick = actions.onWatch,
            )
        }
    }
}

@Composable
private fun ClaimedFooter(item: RewardItem, full: RewardItem?, actions: TicketActions) {
    val claim = full?.claim ?: item.claim
    val context = LocalContext.current
    val view = LocalView.current
    val clipboard = LocalClipboardManager.current
    var copied by remember { mutableStateOf(false) }

    LaunchedEffect(copied) {
        if (copied) {
            delay(1800)
            copied = false
        }
    }

    if (claim == null) {
        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                "Pass stored in wallet",
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontSize = 12.sp,
            )
            TactileButton(
                text = "Show code",
                primary = false,
                onClick = actions.onReveal,
            )
        }
        return
    }

    Column(Modifier.fillMaxWidth()) {
        // Digital Code Box with Tactile Tap
        claim.code?.let { code ->
            val codeInteraction = remember { MutableInteractionSource() }
            val codePressed by codeInteraction.collectIsPressedAsState()
            val codeScale by animateFloatAsState(
                targetValue = if (codePressed) 0.98f else 1f,
                animationSpec = spring(dampingRatio = 0.7f, stiffness = 500f),
                label = "codeScale",
            )

            Row(
                Modifier
                    .fillMaxWidth()
                    .graphicsLayer {
                        scaleX = codeScale
                        scaleY = codeScale
                    }
                    .clip(RoundedCornerShape(10.dp))
                    .background(Board.Raised)
                    .border(1.dp, if (copied) Board.Green else Board.Line, RoundedCornerShape(10.dp))
                    .clickable(
                        interactionSource = codeInteraction,
                        indication = null,
                    ) {
                        view.performHapticFeedback(Feel.SELECT)
                        clipboard.setText(AnnotatedString(code))
                        copied = true
                        Toast.makeText(context, "Pass code copied to clipboard", Toast.LENGTH_SHORT).show()
                    }
                    .padding(horizontal = 12.dp, vertical = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(
                        "DIGITAL PASS CODE",
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Bold,
                        fontSize = 9.sp,
                        letterSpacing = 1.sp,
                    )
                    Spacer(Modifier.height(1.dp))
                    Text(
                        code,
                        color = Board.Ink,
                        fontFamily = FontFamily.Monospace,
                        fontWeight = FontWeight.Bold,
                        fontSize = 14.5.sp,
                        letterSpacing = 1.2.sp,
                        maxLines = 1,
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(if (copied) Board.GreenWell else Board.Surface)
                        .border(0.8.dp, if (copied) Board.Green.copy(alpha = 0.3f) else Board.Line, RoundedCornerShape(6.dp))
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                ) {
                    Icon(
                        if (copied) Icons.Filled.Check else Icons.Filled.ContentCopy,
                        contentDescription = "Copy code",
                        tint = if (copied) Board.Green else Board.Blue,
                        modifier = Modifier.size(13.dp),
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        if (copied) "COPIED" else "COPY",
                        color = if (copied) Board.Green else Board.Blue,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Bold,
                        fontSize = 10.5.sp,
                        letterSpacing = 0.8.sp,
                    )
                }
            }
            Spacer(Modifier.height(6.dp))
        }

        claim.instructions?.let {
            Text(
                it,
                color = Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontSize = 11.5.sp,
                lineHeight = 15.sp,
            )
            Spacer(Modifier.height(8.dp))
        }

        // Secondary Action Row
        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            val meta = when {
                claim.used == true -> "Used"
                claim.expiresAt != null -> "Expires ${shortDate(claim.expiresAt)}"
                claim.endsAt != null -> "Active until ${shortDate(claim.endsAt)}"
                else -> "Pass saved in wallet"
            }
            Text(
                meta,
                color = if (claim.used == true) Board.InkFaint else Board.Green,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.SemiBold,
                fontSize = 11.5.sp,
            )

            when {
                claim.url != null -> TactileButton(
                    text = claim.ctaText ?: "Open offer",
                    primary = true,
                    onClick = { openExternalUrl(context, claim.url) },
                )
                item.type == "haraan_coupon" && claim.used != true -> TactileButton(
                    text = "Use at venue",
                    primary = false,
                    onClick = { actions.onUse(claim.scope) },
                )
            }
        }
    }
}

/**
 * Handcrafted tactile button:
 * Smooth 150-300ms spring compression on press, Haraan Blue actions, clean typography.
 */
@Composable
private fun TactileButton(
    text: String,
    primary: Boolean,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    loading: Boolean = false,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed && enabled && !loading) 0.94f else 1f,
        animationSpec = spring(
            dampingRatio = Spring.DampingRatioMediumBouncy,
            stiffness = Spring.StiffnessMediumLow,
        ),
        label = "btnSpring",
    )

    val shape = RoundedCornerShape(50)
    Row(
        modifier = modifier
            .graphicsLayer {
                scaleX = scale
                scaleY = scale
            }
            .clip(shape)
            .background(
                when {
                    primary && enabled && !loading -> Board.Blue
                    primary -> Board.Blue.copy(alpha = 0.5f)
                    else -> Board.BlueWell
                },
            )
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled && !loading,
            ) {
                view.performHapticFeedback(Feel.COMMIT)
                onClick()
            }
            .padding(horizontal = 14.dp, vertical = 7.5.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
    ) {
        if (loading) {
            CircularProgressIndicator(
                color = if (primary) Color.White else Board.Blue,
                strokeWidth = 1.8.dp,
                modifier = Modifier.size(13.dp),
            )
            Spacer(Modifier.width(6.dp))
        } else if (icon != null) {
            Icon(
                icon,
                null,
                tint = if (primary) Color.White else Board.Blue,
                modifier = Modifier.size(14.dp),
            )
            Spacer(Modifier.width(5.dp))
        }
        Text(
            text,
            color = if (primary) Color.White else Board.Blue,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 12.5.sp,
            maxLines = 1,
        )
    }
}

/**
 * Where a location-targeted perk came from, and — when it has a redemption point — a tap that
 * opens it in the phone's maps app.
 *
 * The distance shown is how far the MATCH was from the zone, measured when the perk was won. The
 * app never sends the player's position to earn anything, so this line is history, not tracking.
 */
@Composable
internal fun RewardLocationLine(geo: RewardGeo, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val view = LocalView.current
    val place = geo.redeemAt

    val label = buildString {
        geo.distanceKm?.let { km ->
            append(if (km < 1) "${(km * 1000).roundToInt()} m" else String.format(Locale.getDefault(), "%.1f km", km))
            append(" · ")
        }
        append(geo.zone)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .clip(RoundedCornerShape(5.dp))
            .then(
                if (place == null) {
                    Modifier
                } else {
                    Modifier.clickable {
                        view.performHapticFeedback(Feel.SELECT)
                        runCatching {
                            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(place.directionsUri())))
                        }.onFailure {
                            Toast.makeText(context, "No maps app to open this", Toast.LENGTH_SHORT).show()
                        }
                    }
                },
            )
            .padding(vertical = 1.dp),
    ) {
        Icon(Icons.Filled.Place, null, tint = Board.InkFaint, modifier = Modifier.size(10.dp))
        Spacer(Modifier.width(3.dp))
        Text(
            label,
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.SemiBold,
            fontSize = 9.5.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        if (place != null) {
            Spacer(Modifier.width(4.dp))
            Text(
                "Directions",
                color = Board.Blue,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 9.sp,
            )
        }
    }
}
