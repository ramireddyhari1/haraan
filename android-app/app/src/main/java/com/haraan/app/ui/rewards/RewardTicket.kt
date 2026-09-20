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
 * Athletic Perks Vault:
 * A high-density horizontal discovery section featuring:
 * 1. Vault Header with live unlocked chip
 * 2. 3-Cell Athletic Stat Strip (Total Value, Ready count, Wallet count)
 * 3. Horizontal Discovery Carousel (LazyRow) with compact digital-wallet passes (RewardVaultCard)
 */
@Composable
internal fun PerksVaultSection(
    ui: RewardScreenUi,
    state: PostMatchRewardsState,
    adsAvailable: Boolean,
    stage: Int,
    onClaim: (RewardItem) -> Unit,
    onWatch: (RewardItem) -> Unit,
    onReveal: (RewardItem) -> Unit,
    onUse: (scope: String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(modifier.fillMaxWidth().rise(stage >= 5)) {
        // 1. Eyebrow Header Row
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 20.dp, end = 20.dp, top = 26.dp, bottom = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "PERKS VAULT",
                    color = Board.InkFaint,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 11.sp,
                    letterSpacing = 1.6.sp,
                )
                if (ui.readyCount > 0) {
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "${ui.readyCount} READY",
                        color = Board.Blue,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 10.sp,
                        letterSpacing = 0.6.sp,
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(Board.BlueWell)
                            .padding(horizontal = 8.dp, vertical = 2.5.dp),
                    )
                }
            }

            Text(
                "${ui.rewards.size} UNLOCKED",
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.SemiBold,
                fontSize = 10.5.sp,
                letterSpacing = 0.5.sp,
            )
        }

        // 2. Vault Quick Stat Strip (3 Compact Metrics)
        VaultStatStrip(
            rewards = ui.rewards,
            readyCount = ui.readyCount,
            modifier = Modifier.padding(start = 16.dp, end = 16.dp, bottom = 14.dp),
        )

        // 3. Horizontal Discovery Carousel (LazyRow)
        LazyRow(
            modifier = Modifier.fillMaxWidth(),
            contentPadding = PaddingValues(horizontal = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(ui.rewards, key = { "vault_${it.id}" }) { item ->
                RewardVaultCard(
                    item = item,
                    full = state.revealed[item.id],
                    actions = TicketActions(
                        claiming = item.id in state.claiming,
                        watching = state.watching == item.id,
                        adsAvailable = adsAvailable,
                        onClaim = { onClaim(item) },
                        onWatch = { onWatch(item) },
                        onReveal = { onReveal(item) },
                        onUse = onUse,
                    ),
                )
            }
        }
    }
}

/**
 * 3-Cell Athletic Stat Strip:
 * High-density horizontal summary anchoring aggregate match perks value, ready count, and wallet passes.
 */
@Composable
internal fun VaultStatStrip(
    rewards: List<RewardItem>,
    readyCount: Int,
    modifier: Modifier = Modifier,
) {
    val claimedCount = rewards.count { it.status == "claimed" || it.status == "redeemed" }

    var totalRupees = 0
    var hasOtherDeals = false
    rewards.forEach { r ->
        val v = r.stub?.value ?: ""
        if (v.startsWith("₹")) {
            val num = v.removePrefix("₹").toIntOrNull() ?: 0
            totalRupees += num
        } else if (v.isNotBlank()) {
            hasOtherDeals = true
        }
    }
    val totalValueDisplay = when {
        totalRupees > 0 && hasOtherDeals -> "₹$totalRupees+"
        totalRupees > 0 -> "₹$totalRupees"
        rewards.isNotEmpty() -> "${rewards.size} OFFERS"
        else -> "—"
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .shadow(
                elevation = 2.dp,
                shape = RoundedCornerShape(14.dp),
                ambientColor = Color(0x080F172A),
                spotColor = Color(0x120F172A),
            )
            .clip(RoundedCornerShape(14.dp))
            .background(Board.Surface)
            .border(1.dp, Board.Line, RoundedCornerShape(14.dp))
            .padding(vertical = 12.dp, horizontal = 8.dp),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            // Stat 1: Total Value
            StatCell(
                modifier = Modifier.weight(1f),
                label = "TOTAL VALUE",
                value = totalValueDisplay,
                valueColor = Board.Ink,
                caption = "Match perks",
            )

            // Divider 1
            Box(
                Modifier
                    .width(1.dp)
                    .height(32.dp)
                    .background(Board.Line),
            )

            // Stat 2: Ready to Claim
            StatCell(
                modifier = Modifier.weight(1f),
                label = "READY TO CLAIM",
                value = if (readyCount > 0) "$readyCount" else "0",
                valueColor = if (readyCount > 0) Board.Blue else Board.InkMuted,
                caption = if (readyCount > 0) "Instant unlock" else "All claimed",
            )

            // Divider 2
            Box(
                Modifier
                    .width(1.dp)
                    .height(32.dp)
                    .background(Board.Line),
            )

            // Stat 3: In Wallet
            StatCell(
                modifier = Modifier.weight(1f),
                label = "IN WALLET",
                value = if (claimedCount > 0) "$claimedCount" else "0",
                valueColor = if (claimedCount > 0) Board.Green else Board.InkFaint,
                caption = if (claimedCount > 0) "Saved passes" else "None yet",
            )
        }
    }
}

@Composable
private fun StatCell(
    label: String,
    value: String,
    valueColor: Color,
    caption: String,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier,
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(
            text = label,
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 9.sp,
            letterSpacing = 0.8.sp,
            maxLines = 1,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            text = value,
            color = valueColor,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 17.sp,
            letterSpacing = (-0.3).sp,
            maxLines = 1,
        )
        Spacer(Modifier.height(1.dp))
        Text(
            text = caption,
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Medium,
            fontSize = 10.sp,
            maxLines = 1,
        )
    }
}

/**
 * Handcrafted Vertical Wallet Pass Card for Horizontal Discovery:
 * - Circular Partner Monogram / Logo Disc
 * - High-Contrast Value Anchor (₹100 OFF / 15% OFF / FREE KIT)
 * - Concise Perk Title & Scope
 * - Tactile State-Driven Base (Ready / Claiming / Claimed / Locked)
 * - 150-400ms spring-based press interaction
 */
@Composable
internal fun RewardVaultCard(
    item: RewardItem,
    full: RewardItem?,
    actions: TicketActions,
    modifier: Modifier = Modifier,
) {
    val locked = item.status == "locked"
    val claimed = item.status == "claimed" || item.status == "redeemed"

    // Dynamic brand color accent
    val brandAccent = parseHex(item.brandColor) ?: when {
        claimed -> Board.Green
        locked -> Board.InkFaint
        else -> Board.Blue
    }

    // Tactile spring press for the whole card
    val cardInteraction = remember { MutableInteractionSource() }
    val cardPressed by cardInteraction.collectIsPressedAsState()
    val cardScale by animateFloatAsState(
        targetValue = if (cardPressed) 0.965f else 1f,
        animationSpec = spring(dampingRatio = 0.75f, stiffness = 420f),
        label = "vaultCardSpring",
    )
    val cardElevation by animateDpAsState(
        targetValue = if (cardPressed) 1.5.dp else 4.dp,
        animationSpec = spring(dampingRatio = 0.8f, stiffness = 400f),
        label = "cardElevation",
    )

    val shape = RoundedCornerShape(16.dp)

    Box(
        modifier = modifier
            .width(176.dp)
            .height(248.dp)
            .graphicsLayer {
                scaleX = cardScale
                scaleY = cardScale
            }
            .shadow(
                elevation = cardElevation,
                shape = shape,
                ambientColor = Color(0x120F172A),
                spotColor = Color(0x220F172A),
            )
            .clip(shape)
            .background(Board.Surface)
            .border(
                1.dp,
                if (claimed) Board.Green.copy(alpha = 0.45f) else Board.Line,
                shape,
            ),
    ) {
        // 1. Top 3dp Brand Identity Accent Rim
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(3.dp)
                .background(if (locked) Board.Line else brandAccent)
                .align(Alignment.TopCenter),
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(start = 12.dp, end = 12.dp, top = 11.dp, bottom = 12.dp),
            verticalArrangement = Arrangement.SpaceBetween,
        ) {
            // TOP SECTION: Header + Value Anchor + Title/Description
            Column(Modifier.fillMaxWidth()) {
                // Partner Header Row
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.weight(1f),
                    ) {
                        val sponsor = item.sponsor
                        val partnerName = sponsor?.name ?: if (item.sponsored) "Partner" else "Haraan"
                        val logoUrl = sponsor?.logo

                        if (!logoUrl.isNullOrBlank()) {
                            AsyncImage(
                                model = logoUrl,
                                contentDescription = partnerName,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .size(28.dp)
                                    .clip(CircleShape)
                                    .background(Board.Raised)
                                    .border(0.8.dp, Board.Line, CircleShape),
                            )
                        } else {
                            val monogramBg = if (locked) Board.Raised else brandAccent.copy(alpha = 0.12f)
                            val monogramBorder = if (locked) Board.Line else brandAccent.copy(alpha = 0.35f)
                            val monogramColor = if (locked) Board.InkFaint else brandAccent

                            Box(
                                modifier = Modifier
                                    .size(28.dp)
                                    .clip(CircleShape)
                                    .background(monogramBg)
                                    .border(0.8.dp, monogramBorder, CircleShape),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    partnerName.take(1).uppercase(),
                                    color = monogramColor,
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.ExtraBold,
                                    fontSize = 11.5.sp,
                                )
                            }
                        }

                        Spacer(Modifier.width(7.dp))

                        Column(Modifier.weight(1f)) {
                            Text(
                                partnerName.uppercase(),
                                color = Board.Ink,
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Bold,
                                fontSize = 10.5.sp,
                                letterSpacing = 0.6.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                            sponsor?.category?.let { cat ->
                                Text(
                                    cat.uppercase(),
                                    color = Board.InkFaint,
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 8.5.sp,
                                    letterSpacing = 0.4.sp,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                )
                            }
                        }
                    }

                    // Mini Status Indicator
                    when {
                        claimed -> {
                            Box(
                                modifier = Modifier
                                    .size(18.dp)
                                    .clip(CircleShape)
                                    .background(Board.GreenWell),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(
                                    Icons.Filled.Check,
                                    null,
                                    tint = Board.Green,
                                    modifier = Modifier.size(11.dp),
                                )
                            }
                        }
                        locked -> {
                            Icon(
                                Icons.Filled.Lock,
                                null,
                                tint = Board.InkFaint,
                                modifier = Modifier.size(11.dp),
                            )
                        }
                        else -> {
                            Box(
                                modifier = Modifier
                                    .size(7.dp)
                                    .clip(CircleShape)
                                    .background(Board.Blue),
                            )
                        }
                    }
                }

                Spacer(Modifier.height(8.dp))

                // 2. Visual Anchor: Handcrafted Value Hero
                VaultValueHero(
                    stub = item.stub,
                    locked = locked,
                    claimed = claimed,
                )

                Spacer(Modifier.height(7.dp))

                // 3. Perk Title & Short Scope
                Text(
                    item.title,
                    color = if (locked) Board.InkMuted else Board.Ink,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.5.sp,
                    lineHeight = 15.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                item.description?.takeIf { it.isNotBlank() }?.let { desc ->
                    Spacer(Modifier.height(2.dp))
                    Text(
                        desc,
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontSize = 10.sp,
                        lineHeight = 13.5.sp,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                item.geo?.let { geo ->
                    Spacer(Modifier.height(4.dp))
                    RewardLocationLine(geo)
                }
            }

            // BOTTOM SECTION: Hairline Pass Seam Divider + State-Driven Action Base
            Column(Modifier.fillMaxWidth()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(1.dp)
                        .background(Board.Line.copy(alpha = 0.7f)),
                )
                Spacer(Modifier.height(8.dp))
                AnimatedContent(
                    targetState = when {
                        claimed -> 2
                        locked -> 1
                        else -> 0
                    },
                    transitionSpec = {
                        (fadeIn(tween(180)) + scaleIn(spring(dampingRatio = 0.75f, stiffness = 420f), initialScale = 0.96f))
                            .togetherWith(fadeOut(tween(120)) + scaleOut(tween(120), targetScale = 0.96f))
                    },
                    label = "vaultCardFooter",
                    modifier = Modifier.fillMaxWidth(),
                ) { state ->
                    when (state) {
                        0 -> ReadyCardFooter(item = item, actions = actions)
                        1 -> LockedCardFooter(item = item, actions = actions)
                        else -> ClaimedCardFooter(item = item, full = full, actions = actions)
                    }
                }
            }
        }
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
private fun RewardLocationLine(geo: RewardGeo, modifier: Modifier = Modifier) {
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

@Composable
private fun VaultValueHero(
    stub: RewardStub?,
    locked: Boolean,
    claimed: Boolean,
    modifier: Modifier = Modifier,
) {
    val heroBg = when {
        claimed -> Board.GreenWell
        locked -> Board.Raised.copy(alpha = 0.6f)
        else -> Color(0xFFF8FAFC)
    }
    val heroBorder = when {
        claimed -> Board.Green.copy(alpha = 0.25f)
        locked -> Board.Line.copy(alpha = 0.7f)
        else -> Board.Line
    }
    val heroValueColor = when {
        claimed -> Board.Green
        locked -> Board.InkFaint
        else -> Board.Ink
    }
    val heroCaptionColor = when {
        claimed -> Board.Green
        locked -> Board.InkFaint
        else -> Board.InkMuted
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(48.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(heroBg)
            .border(1.dp, heroBorder, RoundedCornerShape(10.dp)),
        contentAlignment = Alignment.Center,
    ) {
        if (stub != null) {
            val rawValue = stub.value.trim()
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.Center,
                modifier = Modifier.padding(horizontal = 6.dp),
            ) {
                when {
                    rawValue.startsWith("₹") -> {
                        val numPart = rawValue.removePrefix("₹")
                        Text(
                            "₹",
                            color = heroValueColor,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            modifier = Modifier.padding(bottom = 3.dp),
                        )
                        Spacer(Modifier.width(1.dp))
                        Text(
                            numPart,
                            color = heroValueColor,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = if (numPart.length > 3) 19.sp else 23.sp,
                            letterSpacing = (-0.5).sp,
                            maxLines = 1,
                        )
                    }
                    rawValue.endsWith("%") -> {
                        val numPart = rawValue.removeSuffix("%")
                        Text(
                            numPart,
                            color = heroValueColor,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = 22.sp,
                            letterSpacing = (-0.5).sp,
                            maxLines = 1,
                        )
                        Text(
                            "%",
                            color = heroValueColor,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            modifier = Modifier.padding(bottom = 3.dp),
                        )
                    }
                    else -> {
                        Text(
                            rawValue,
                            color = heroValueColor,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = if (rawValue.length > 5) 15.sp else 18.sp,
                            letterSpacing = (-0.3).sp,
                            maxLines = 1,
                        )
                    }
                }

                Spacer(Modifier.width(6.dp))

                // Compact Pill Badge for caption
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(4.dp))
                        .background(
                            when {
                                claimed -> Board.Green.copy(alpha = 0.15f)
                                locked -> Board.InkFaint.copy(alpha = 0.10f)
                                else -> Board.Ink.copy(alpha = 0.07f)
                            }
                        )
                        .padding(horizontal = 4.5.dp, vertical = 2.dp),
                ) {
                    Text(
                        stub.caption.uppercase(),
                        color = heroCaptionColor,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 8.5.sp,
                        letterSpacing = 0.6.sp,
                        maxLines = 1,
                    )
                }
            }
        } else {
            Icon(
                if (locked) Icons.Filled.Lock else Icons.Filled.Check,
                null,
                tint = heroValueColor,
                modifier = Modifier.size(18.dp),
            )
        }
    }
}

@Composable
private fun ReadyCardFooter(item: RewardItem, actions: TicketActions) {
    TactileCardButton(
        text = if (actions.claiming) "Claiming…" else "Claim Perk →",
        primary = true,
        loading = actions.claiming,
        onClick = actions.onClaim,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun LockedCardFooter(item: RewardItem, actions: TicketActions) {
    if ("rewarded_ad" in item.lockReasons && item.canWatchAd && actions.adsAvailable) {
        TactileCardButton(
            text = if (actions.watching) "Verifying…" else "Watch to unlock",
            primary = false,
            icon = Icons.Filled.PlayCircle,
            loading = actions.watching,
            onClick = actions.onWatch,
            modifier = Modifier.fillMaxWidth(),
        )
    } else {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(Board.Raised)
                .border(0.8.dp, Board.Line, RoundedCornerShape(8.dp))
                .padding(vertical = 7.dp, horizontal = 6.dp),
            contentAlignment = Alignment.Center,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.Lock, null, tint = Board.InkFaint, modifier = Modifier.size(10.dp))
                Spacer(Modifier.width(4.dp))
                Text(
                    text = if ("verification" in item.lockReasons) "Needs verification" else "Locked perk",
                    color = Board.InkFaint,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 10.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun ClaimedCardFooter(item: RewardItem, full: RewardItem?, actions: TicketActions) {
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

    if (claim?.code != null) {
        val codeInteraction = remember { MutableInteractionSource() }
        val codePressed by codeInteraction.collectIsPressedAsState()
        val codeScale by animateFloatAsState(
            targetValue = if (codePressed) 0.97f else 1f,
            animationSpec = spring(dampingRatio = 0.75f, stiffness = 420f),
            label = "codeScale",
        )

        Box(
            modifier = Modifier
                .fillMaxWidth()
                .graphicsLayer {
                    scaleX = codeScale
                    scaleY = codeScale
                }
                .clip(RoundedCornerShape(8.dp))
                .background(if (copied) Board.GreenWell else Board.Raised)
                .border(
                    1.dp,
                    if (copied) Board.Green.copy(alpha = 0.4f) else Board.Line,
                    RoundedCornerShape(8.dp),
                )
                .clickable(
                    interactionSource = codeInteraction,
                    indication = null,
                ) {
                    view.performHapticFeedback(Feel.SELECT)
                    clipboard.setText(AnnotatedString(claim.code))
                    copied = true
                    Toast.makeText(context, "Pass code copied", Toast.LENGTH_SHORT).show()
                }
                .padding(horizontal = 7.dp, vertical = 6.dp),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(
                    text = claim.code,
                    fontFamily = FontFamily.Monospace,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    letterSpacing = 0.6.sp,
                    color = if (copied) Board.Green else Board.Ink,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                Spacer(Modifier.width(4.dp))
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .clip(RoundedCornerShape(4.dp))
                        .background(if (copied) Board.Green else Board.Blue)
                        .padding(horizontal = 5.dp, vertical = 2.5.dp),
                ) {
                    if (copied) {
                        Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(9.dp))
                        Spacer(Modifier.width(2.dp))
                    }
                    Text(
                        text = if (copied) "COPIED" else "COPY",
                        color = Color.White,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 9.sp,
                        letterSpacing = 0.4.sp,
                    )
                }
            }
        }
    } else {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(Board.GreenWell)
                .border(1.dp, Board.Green.copy(alpha = 0.3f), RoundedCornerShape(8.dp))
                .padding(vertical = 7.dp, horizontal = 6.dp),
            contentAlignment = Alignment.Center,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.Check, null, tint = Board.Green, modifier = Modifier.size(11.dp))
                Spacer(Modifier.width(4.dp))
                Text(
                    text = "Saved in wallet",
                    color = Board.Green,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 10.5.sp,
                )
            }
        }
    }
}

@Composable
private fun TactileCardButton(
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
        label = "cardBtnSpring",
    )

    val shape = RoundedCornerShape(8.dp)
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
            .padding(vertical = 8.dp, horizontal = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
    ) {
        if (loading) {
            CircularProgressIndicator(
                color = if (primary) Color.White else Board.Blue,
                strokeWidth = 1.8.dp,
                modifier = Modifier.size(12.dp),
            )
            Spacer(Modifier.width(5.dp))
        } else if (icon != null) {
            Icon(
                icon,
                null,
                tint = if (primary) Color.White else Board.Blue,
                modifier = Modifier.size(12.dp),
            )
            Spacer(Modifier.width(4.dp))
        }
        Text(
            text = text,
            color = if (primary) Color.White else Board.Blue,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 11.5.sp,
            maxLines = 1,
        )
    }
}

