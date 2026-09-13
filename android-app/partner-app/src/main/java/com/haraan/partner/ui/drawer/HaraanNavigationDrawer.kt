package com.haraan.partner.ui.drawer

import android.view.HapticFeedbackConstants
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import com.haraan.partner.ui.icons.HaraanIcons
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.Lane
import com.haraan.partner.PartnerContext
import com.haraan.partner.R
import com.haraan.partner.Session
import com.haraan.partner.Tab
import com.haraan.partner.altitudeLabel
import com.haraan.partner.labelFor
import com.haraan.partner.ui.theme.HaraanTheme

/**
 * Handcrafted, enterprise-grade Navigation Drawer for HARAAN Partner App.
 * Inspired by Linear, Stripe, and Apple executive design standards.
 *
 * Architecture Principles:
 * - Crisp matte titanium/slate header card with 20 dp corners and ultra-fine hairline.
 * - Emerald is reserved strictly as a semantic accent (active destination indicator, online status).
 * - Full 56 dp touch targets on all interactive items for high accessibility and ergonomics.
 * - Restrained micro-interactions (press scale, gentle 150ms color fades).
 * - Pinned footer isolating Settings, Help, and Destructive Sign Out safely.
 */
@Composable
internal fun HaraanPartnerDrawer(
    session: Session,
    ctx: PartnerContext?,
    lane: Lane,
    tabs: List<Tab>,
    current: Tab,
    branchId: Long?,
    onTab: (Tab) -> Unit,
    onBranch: (Long?) -> Unit,
    onCustomers: () -> Unit,
    onPackages: (() -> Unit)?,
    onStaff: (() -> Unit)?,
    onPayouts: (() -> Unit)?,
    onReports: (() -> Unit)?,
    onAcademy: (() -> Unit)?,
    onShiftRegister: (() -> Unit)? = null,
    onStandingSlots: (() -> Unit)? = null,
    onPricingMatrix: (() -> Unit)? = null,
    onWhatsAppDesk: (() -> Unit)? = null,
    onOperationsCenter: (() -> Unit)? = null,
    onNotifications: () -> Unit,
    onSupport: () -> Unit,
    onSettings: (() -> Unit)? = null,
    onSignOut: () -> Unit,
) {
    ModalDrawerSheet(
        drawerContainerColor = HaraanTheme.colors.surfaceDefault,
        drawerShape = RoundedCornerShape(topEnd = 24.dp, bottomEnd = 24.dp),
        windowInsets = WindowInsets(0, 0, 0, 0),
        modifier = Modifier.widthIn(max = 330.dp),
    ) {
        Column(Modifier.fillMaxHeight().background(HaraanTheme.colors.surfaceDefault)) {
            // Pinned Executive Header Card
            HaraanDrawerHeader(session = session, ctx = ctx)

            // Scrollable Navigation & Tools Content
            Column(
                Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    .padding(top = 2.dp, bottom = 20.dp),
            ) {
                // Section: Destinations
                HaraanDrawerSectionHeader("Navigation", isFirst = true)
                tabs.forEach { t ->
                    HaraanDrawerItem(
                        icon = drawerIconFor(t),
                        label = labelFor(t, lane),
                        selected = t == current,
                        onClick = { onTab(t) },
                    )
                }

                // Section: Operations & Management
                val tools = listOfNotNull(
                    onOperationsCenter?.let { Triple(HaraanIcons.Operations, "Operations Center", it) },
                    onWhatsAppDesk?.let { Triple(HaraanIcons.WhatsAppDesk, "WhatsApp Desk", it) },
                    onShiftRegister?.let { Triple(HaraanIcons.CashSettlement, "Cash Settlement", it) },
                    onStandingSlots?.let { Triple(HaraanIcons.StandingSlots, "Standing Slots", it) },
                    onPricingMatrix?.let { Triple(HaraanIcons.PricingCourts, "Pricing & Courts", it) },
                    Triple(HaraanIcons.Customers, "Customers", onCustomers),
                    onPackages?.let { Triple(HaraanIcons.Packages, "Packages", it) },
                    onAcademy?.let { Triple(HaraanIcons.Academy, "Academy", it) },
                    onStaff?.let { Triple(HaraanIcons.Staff, "Staff", it) },
                    onPayouts?.let { Triple(HaraanIcons.Payouts, "Payouts", it) },
                    onReports?.let { Triple(HaraanIcons.Reports, "Reports", it) },
                )
                if (tools.isNotEmpty()) {
                    HaraanDrawerSectionHeader("Management")
                    tools.forEach { (icon, label, action) ->
                        HaraanDrawerItem(
                            icon = icon,
                            label = label,
                            selected = false,
                            onClick = action,
                        )
                    }
                }

                // Section: Outlets / Branches (Only rendered for multi-branch partners)
                ctx?.takeIf { it.isMultiBranch }?.let { known ->
                    HaraanDrawerSectionHeader("Outlets")
                    HaraanDrawerItem(
                        icon = HaraanIcons.Outlet,
                        label = "All Outlets",
                        selected = branchId == null,
                        trailing = {
                            if (branchId == null) {
                                Icon(
                                    HaraanIcons.Check,
                                    contentDescription = null,
                                    tint = HaraanTheme.colors.emeraldPrimary,
                                    modifier = Modifier.size(18.dp),
                                )
                            }
                        },
                        onClick = { onBranch(null) },
                    )
                    known.branches.forEach { b ->
                        HaraanDrawerItem(
                            icon = HaraanIcons.Outlet,
                            label = b.branch.ifBlank { b.name },
                            selected = branchId == b.id,
                            trailing = {
                                if (branchId == b.id) {
                                    Icon(
                                        HaraanIcons.Check,
                                        contentDescription = null,
                                        tint = HaraanTheme.colors.emeraldPrimary,
                                        modifier = Modifier.size(18.dp),
                                    )
                                }
                            },
                            onClick = { onBranch(b.id) },
                        )
                    }
                }

                Spacer(Modifier.height(16.dp))
            }

            // Pinned Executive Footer (Settings, Support, Sign Out)
            HaraanDrawerFooter(
                sessionName = session.name ?: "Partner",
                onSettings = onSettings ?: onNotifications,
                onSupport = onSupport,
                onSignOut = onSignOut,
            )
        }
    }
}

private fun drawerIconFor(tab: Tab): ImageVector = when (tab) {
    Tab.Home -> HaraanIcons.Home
    Tab.Matches -> HaraanIcons.Matches
    Tab.Events -> HaraanIcons.Bookings
    Tab.Venues -> HaraanIcons.Venues
    Tab.Sales -> HaraanIcons.Sales
    Tab.Scan -> HaraanIcons.Scan
}

/**
 * Premium Executive Header Card with 20 dp rounded corners, matte slate/titanium
 * surface, HARAAN brand logo, venue name, role pill badge, and live pulsing online indicator.
 */
@Composable
private fun HaraanDrawerHeader(
    session: Session,
    ctx: PartnerContext?,
    modifier: Modifier = Modifier,
) {
    val venueName = ctx?.businessName ?: session.name ?: "Demo Venue Partner"
    val subline = ctx?.typeLabel ?: if (session.isDesk) "Desk Console" else "Sports Arena"
    val role = altitudeLabel(session, ctx)

    // Pulsing animation for the Live Operational Indicator
    val infiniteTransition = rememberInfiniteTransition(label = "pulse-transition")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 0.85f,
        targetValue = 1.25f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "pulse-scale",
    )
    val pulseAlpha by infiniteTransition.animateFloat(
        initialValue = 0.4f,
        targetValue = 0.9f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "pulse-alpha",
    )

    Box(
        modifier = modifier
            .fillMaxWidth()
            .statusBarsPadding()
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Card(
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF0F172A)),
            border = BorderStroke(1.dp, Color(0x1FFFFFFF)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 16.dp),
            ) {
                // Top Row: Logo + Live Operational Status Badge
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Image(
                        painter = painterResource(R.drawable.haraan_logo_white),
                        contentDescription = "HARAAN",
                        contentScale = ContentScale.Fit,
                        colorFilter = ColorFilter.tint(Color.White),
                        modifier = Modifier.height(18.dp),
                    )

                    // Semantic Operational Pill (Emerald Accent Only)
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .clip(RoundedCornerShape(99.dp))
                            .background(Color(0x1410B981))
                            .border(1.dp, Color(0x2610B981), RoundedCornerShape(99.dp))
                            .padding(horizontal = 8.dp, vertical = 3.5.dp),
                    ) {
                        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(10.dp)) {
                            // Pulsing halo
                            Box(
                                modifier = Modifier
                                    .size(9.dp)
                                    .graphicsLayer {
                                        scaleX = pulseScale
                                        scaleY = pulseScale
                                        alpha = pulseAlpha
                                    }
                                    .clip(CircleShape)
                                    .background(Color(0x6610B981)),
                            )
                            // Solid Core
                            Box(
                                modifier = Modifier
                                    .size(5.dp)
                                    .clip(CircleShape)
                                    .background(Color(0xFF10B981)),
                            )
                        }
                        Spacer(Modifier.width(5.dp))
                        Text(
                            text = "ONLINE",
                            fontSize = 9.5.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 0.8.sp,
                            color = Color(0xFF34D399),
                        )
                    }
                }

                Spacer(Modifier.height(14.dp))

                // Middle Row: Venue Avatar Monogram + Venue Name + Category
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // Titanium Monogram Box
                    Box(
                        modifier = Modifier
                            .size(42.dp)
                            .clip(RoundedCornerShape(13.dp))
                            .background(
                                Brush.verticalGradient(
                                    listOf(Color(0xFF1E293B), Color(0xFF0F172A)),
                                ),
                            )
                            .border(1.dp, Color(0x2694A3B8), RoundedCornerShape(13.dp)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            text = venueName.trim().take(1).uppercase(),
                            fontSize = 17.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = Color.White,
                        )
                    }

                    Spacer(Modifier.width(12.dp))

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = venueName,
                            fontSize = 16.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            letterSpacing = (-0.3).sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        Spacer(Modifier.height(2.dp))
                        Text(
                            text = subline,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Normal,
                            color = Color(0xFF94A3B8),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }

                Spacer(Modifier.height(13.dp))

                // Bottom Row: Altitude / Role Badge + Metadata
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // Role Badge
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0x1FFFFFFF))
                            .border(1.dp, Color(0x1F94A3B8), RoundedCornerShape(8.dp))
                            .padding(horizontal = 9.dp, vertical = 3.5.dp),
                    ) {
                        Text(
                            text = role.uppercase(),
                            fontSize = 10.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            letterSpacing = 0.6.sp,
                            color = Color(0xFFE2E8F0),
                        )
                    }

                    ctx?.branches?.firstOrNull { it.id == session.branchId }?.let { activeBranch ->
                        Spacer(Modifier.width(7.dp))
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(Color(0x12FFFFFF))
                                .padding(horizontal = 8.dp, vertical = 3.5.dp),
                        ) {
                            Text(
                                text = activeBranch.branch.ifBlank { activeBranch.name },
                                fontSize = 10.5.sp,
                                fontWeight = FontWeight.Normal,
                                color = Color(0xFF94A3B8),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Handcrafted Navigation Row adhering strictly to 56 dp touch target ergonomics.
 * Active item features an Emerald vertical indicator bar on the leading edge,
 * soft highlight with smooth transitions, and subtle press-scale micro-animation.
 */
@Composable
fun HaraanDrawerItem(
    icon: ImageVector,
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    tint: Color? = null,
    trailing: @Composable (() -> Unit)? = null,
) {
    val view = LocalView.current
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()

    // Smooth, restrained micro-bounce on press
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.985f else 1f,
        animationSpec = spring(stiffness = Spring.StiffnessMediumLow),
        label = "drawer-item-scale",
    )

    // Gentle 150ms background fade
    val containerBg by animateColorAsState(
        targetValue = if (selected) Color(0x12059669) else Color.Transparent,
        animationSpec = tween(150),
        label = "drawer-item-bg",
    )

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 10.dp, vertical = 1.dp)
            .graphicsLayer {
                scaleX = scale
                scaleY = scale
            }
            .clip(RoundedCornerShape(12.dp))
            .background(containerBg)
            .defaultMinSize(minHeight = 56.dp)
            .heightIn(min = 56.dp)
            .clickable(interactionSource = interactionSource, indication = null) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            }
            .padding(horizontal = 6.dp, vertical = 4.dp),
    ) {
        // Leading Emerald Active Indicator Bar (Semantic Accent Only)
        if (selected) {
            Box(
                modifier = Modifier
                    .width(3.5.dp)
                    .height(26.dp)
                    .clip(RoundedCornerShape(topEnd = 2.dp, bottomEnd = 2.dp))
                    .background(HaraanTheme.colors.emeraldPrimary),
            )
        } else {
            Spacer(Modifier.width(3.5.dp))
        }

        Spacer(Modifier.width(8.dp))

        // Consistent 36 dp rounded square icon container
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(
                    when {
                        tint != null -> tint.copy(alpha = 0.10f)
                        selected -> Color(0x1F059669)
                        else -> Color(0x080F172A)
                    },
                ),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = when {
                    tint != null -> tint
                    selected -> HaraanTheme.colors.emeraldPrimary
                    else -> Color(0xFF475569)
                },
                modifier = Modifier.size(20.dp),
            )
        }

        Spacer(Modifier.width(13.dp))

        // High-contrast label with WCAG AAA accessibility
        Text(
            text = label,
            fontSize = 14.5.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
            color = when {
                tint != null -> tint
                selected -> HaraanTheme.colors.emeraldPrimary
                else -> HaraanTheme.colors.textPrimary
            },
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )

        trailing?.let {
            Spacer(Modifier.width(8.dp))
            it()
        }
    }
}

/**
 * Uppercase section header with increased letter spacing and subtle hairline rule.
 */
@Composable
fun HaraanDrawerSectionHeader(
    title: String,
    modifier: Modifier = Modifier,
    isFirst: Boolean = false,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .padding(
                start = 22.dp,
                end = 16.dp,
                top = if (isFirst) 8.dp else 20.dp,
                bottom = 6.dp,
            ),
    ) {
        Text(
            text = title.uppercase(),
            fontSize = 10.5.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.3.sp,
            color = HaraanTheme.colors.textMuted,
        )
        Spacer(Modifier.width(10.dp))
        Box(
            Modifier
                .weight(1f)
                .height(1.dp)
                .background(HaraanTheme.colors.borderHairline),
        )
    }
}

/**
 * Pinned Executive Drawer Footer with Settings, Support, and visually separated Sign Out.
 * Conforms to 56 dp touch targets and isolates destructive actions cleanly.
 */
@Composable
fun HaraanDrawerFooter(
    sessionName: String,
    onSettings: () -> Unit,
    onSupport: () -> Unit,
    onSignOut: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(HaraanTheme.colors.surfaceDefault),
    ) {
        HorizontalDivider(
            color = HaraanTheme.colors.borderHairline,
            thickness = 1.dp,
        )

        Spacer(Modifier.height(4.dp))

        // Settings Entry (56 dp target)
        HaraanDrawerItem(
            icon = HaraanIcons.Settings,
            label = "Settings",
            selected = false,
            onClick = onSettings,
        )

        // Support & Help Desk (56 dp target)
        HaraanDrawerItem(
            icon = HaraanIcons.Support,
            label = "Help & Support",
            selected = false,
            onClick = onSupport,
        )

        Spacer(Modifier.height(4.dp))

        // Visually separated Sign Out row with dedicated safety container
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 10.dp, vertical = 4.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0x0ADC2626)),
        ) {
            HaraanDrawerItem(
                icon = HaraanIcons.SignOut,
                label = "Sign Out",
                selected = false,
                tint = HaraanTheme.colors.crimsonPrimary,
                onClick = onSignOut,
            )
        }

        Spacer(Modifier.height(6.dp).navigationBarsPadding())
    }
}
