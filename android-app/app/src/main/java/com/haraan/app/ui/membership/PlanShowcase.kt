package com.haraan.app.ui.membership

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.SizeTransform
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Check
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.membership.CataloguePlan
import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.MembershipNav
import com.haraan.app.data.membership.PlanFeature
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

/**
 * The material a plan is made of. Free (and any paid plan the app has no mark for) is slate on
 * white; Pro is Haraan navy on white; Hero is onyx through and through with gold. Switching plans
 * springs every one of these, so the panel reads as one object changing, not a card swapped out.
 */
@Immutable
private data class PlanPalette(
    val band: List<Color>,
    val body: Color,
    val text: Color,
    val sub: Color,
    val muted: Color,
    val rule: Color,
    val accent: Color,
    val edge: Color,
    val shadow: Color,
)

private val SlatePalette = PlanPalette(
    band = listOf(Color(0xFF4A5A70), Color(0xFF2C3849), Color(0xFF161D29)),
    body = HaraanColors.Surface,
    text = HaraanColors.TextPrimary,
    sub = HaraanColors.TextSecondary,
    muted = Color(0xFFB4BFCC),
    rule = HaraanColors.Hairline,
    accent = HaraanColors.EventsBlue,
    edge = HaraanColors.BorderLight,
    shadow = Color(0xFF0F172A),
)

private val ProPalette = SlatePalette.copy(
    band = MemberTierStyles.Pro.band,
    accent = Color(0xFF2563EB),
    edge = MemberTierStyles.Pro.rim,
    shadow = Color(0xFF102A66),
)

private val HeroPalette = PlanPalette(
    band = MemberTierStyles.Hero.band,
    body = Color(0xFF14120E),
    text = Color(0xFFF5F2EC),
    sub = Color(0xFFA39C92),
    muted = Color(0xFF59534B),
    rule = Color(0x17FFFFFF),
    accent = Color(0xFFD9AE55),
    edge = Color(0xFF4A3A1C),
    shadow = Color(0xFF000000),
)

private fun tierOf(plan: CataloguePlan): MemberTier =
    if (plan.rank > 0) MemberTier.fromBadge(plan.code) else MemberTier.REGULAR

private fun paletteOf(tier: MemberTier): PlanPalette = when (tier) {
    MemberTier.PRO -> ProPalette
    MemberTier.HERO -> HeroPalette
    MemberTier.REGULAR -> SlatePalette
}

/** Every colour of the palette, sprung toward [target] together. */
@Composable
private fun animatePalette(target: PlanPalette): PlanPalette {
    val spec = spring<Color>(stiffness = Spring.StiffnessMediumLow)
    return PlanPalette(
        band = target.band.mapIndexed { i, color -> animateColorAsState(color, spec, label = "band$i").value },
        body = animateColorAsState(target.body, spec, label = "body").value,
        text = animateColorAsState(target.text, spec, label = "text").value,
        sub = animateColorAsState(target.sub, spec, label = "sub").value,
        muted = animateColorAsState(target.muted, spec, label = "muted").value,
        rule = animateColorAsState(target.rule, spec, label = "rule").value,
        accent = animateColorAsState(target.accent, spec, label = "accent").value,
        edge = animateColorAsState(target.edge, spec, label = "edge").value,
        shadow = animateColorAsState(target.shadow, spec, label = "shadow").value,
    )
}

/** The lit top edge of a dark surface — it reads as a physical object catching light. */
private fun Modifier.edgeLight(alpha: Float = 0.16f): Modifier = drawWithContent {
    drawContent()
    drawLine(
        brush = Brush.horizontalGradient(listOf(Color.Transparent, Color.White.copy(alpha = alpha), Color.Transparent)),
        start = Offset(0f, 0.5f),
        end = Offset(size.width, 0.5f),
        strokeWidth = 1.dp.toPx(),
    )
}

/** "₹99" + "/month", "₹0" for the free plan, or null when the plan isn't on sale at [interval]. */
private fun priceOf(plan: CataloguePlan, interval: String): Pair<String, String?>? {
    if (plan.isDefault) return MembershipFormat.rupees(0) to null
    val price = MembershipFormat.priceFor(plan, interval) ?: return null
    return MembershipFormat.rupees(price.amountPaise) to MembershipFormat.perInterval(price.interval)
}

/**
 * The plans as one control and one panel. Tap a plan and the switcher's pill slides to it, the
 * panel's material changes to that plan's, and each line's value turns over in place — so the
 * difference between plans is something you watch happen. Every name, figure and price is the
 * server's; a plan that isn't on sale says so once.
 */
@Composable
fun PlanShowcase(
    plans: List<CataloguePlan>,
    membership: Membership?,
    interval: String,
    signedIn: Boolean,
    selectedCode: String?,
    onSelect: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    if (plans.isEmpty()) return
    val selectedIndex = plans.indexOfFirst { it.code == selectedCode }.takeIf { it >= 0 } ?: 0
    val selected = plans[selectedIndex]
    val currentCode = membership?.plan?.code ?: plans.firstOrNull { it.isCurrent }?.code
    val isCurrent = membership != null && selected.code == currentCode

    // The member's own plan follows their real standing — a paused plan is shown plain, never in
    // its premium material. Any other plan wears its own.
    val tier = if (isCurrent) MemberTier.planOf(membership) else tierOf(selected)
    val palette = animatePalette(paletteOf(tier))

    Column(modifier.fillMaxWidth()) {
        PlanSwitcher(
            plans = plans,
            selectedIndex = selectedIndex,
            currentCode = currentCode.takeIf { membership != null },
            interval = interval,
            indicator = palette,
            onSelect = onSelect,
        )
        Spacer(Modifier.height(16.dp))
        PlanPanel(
            plans = plans,
            plan = selected,
            tier = tier,
            palette = palette,
            interval = interval,
            isCurrent = isCurrent,
            membership = membership,
            // Compare with what the member has; a guest has nothing to compare with.
            yours = plans.firstOrNull { it.code == currentCode }?.features?.takeIf { signedIn && membership != null && !isCurrent },
            afterCancel = MembershipFormat.ctaFor(selected, membership, interval, signedIn) == MembershipFormat.Cta.IncludedAfterCancel,
        )
    }
}

@Composable
private fun PlanSwitcher(
    plans: List<CataloguePlan>,
    selectedIndex: Int,
    currentCode: String?,
    interval: String,
    indicator: PlanPalette,
    onSelect: (String) -> Unit,
) {
    // Prices ride in the switcher only when some paid plan is actually on sale; otherwise every
    // segment would carry the same non-answer.
    val showPrices = plans.any { !it.isDefault && MembershipFormat.priceFor(it, interval) != null }
    val outer = RoundedCornerShape(20.dp)
    val inner = RoundedCornerShape(16.dp)

    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .height(if (showPrices) 64.dp else 54.dp)
            .shadow(6.dp, outer, ambientColor = Color(0x140F172A), spotColor = Color(0x1F0F172A))
            .clip(outer)
            .background(HaraanColors.Surface)
            .border(1.dp, HaraanColors.BorderLight, outer)
            .padding(4.dp),
    ) {
        val slot = maxWidth / plans.size
        // Near-critically damped, like the bottom nav's pill: it glides and settles, never wobbles.
        val x by animateDpAsState(
            targetValue = slot * selectedIndex,
            animationSpec = spring(dampingRatio = 0.8f, stiffness = 480f),
            label = "plan-pill-x",
        )
        Box(
            Modifier
                .offset(x = x)
                .width(slot)
                .fillMaxHeight()
                .shadow(8.dp, inner, ambientColor = indicator.shadow.copy(alpha = 0.25f), spotColor = indicator.shadow.copy(alpha = 0.45f))
                .clip(inner)
                .background(Brush.linearGradient(indicator.band))
                .edgeLight(0.22f),
        )
        Row(Modifier.fillMaxSize()) {
            plans.forEachIndexed { i, plan ->
                val on = i == selectedIndex
                val ink by animateColorAsState(if (on) Color.White else HaraanColors.TextPrimary, label = "seg-ink")
                val soft by animateColorAsState(if (on) Color.White.copy(alpha = 0.72f) else HaraanColors.TextSecondary, label = "seg-soft")
                val isYours = plan.code == currentCode
                Column(
                    Modifier
                        .pressable(haptic = if (on) null else Feel.SELECT) { if (!on) onSelect(plan.code) }
                        .weight(1f)
                        .fillMaxHeight()
                        .semantics {
                            role = Role.Tab
                            selected = on
                            contentDescription = plan.name + if (isYours) ", your plan" else ""
                        },
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center,
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        MemberTierStyles.of(tierOf(plan))?.let {
                            Image(painterResource(it.mark), contentDescription = null, modifier = Modifier.size(17.dp))
                            Spacer(Modifier.width(6.dp))
                        }
                        Text(plan.name, color = ink, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
                        if (isYours) {
                            Spacer(Modifier.width(5.dp))
                            Box(Modifier.size(5.dp).clip(CircleShape).background(if (on) Color.White.copy(alpha = 0.8f) else HaraanColors.EventsBlue))
                        }
                    }
                    if (showPrices) {
                        Text(
                            priceOf(plan, interval)?.let { (amount, per) -> amount + (per ?: "") } ?: "Not on sale",
                            color = soft,
                            fontSize = 11.sp,
                            maxLines = 1,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun PlanPanel(
    plans: List<CataloguePlan>,
    plan: CataloguePlan,
    tier: MemberTier,
    palette: PlanPalette,
    interval: String,
    isCurrent: Boolean,
    membership: Membership?,
    yours: List<PlanFeature>?,
    afterCancel: Boolean,
) {
    val shape = RoundedCornerShape(22.dp)
    val sections = PlanSheet.of(plan.features, yours)
    val order = { code: String -> plans.indexOfFirst { it.code == code } }

    Column(
        Modifier
            .fillMaxWidth()
            .shadow(18.dp, shape, ambientColor = palette.shadow.copy(alpha = 0.10f), spotColor = palette.shadow.copy(alpha = 0.22f))
            .clip(shape)
            .background(palette.body)
            .border(1.dp, palette.edge, shape),
    ) {
        // ── Band: the plan's name and promise, turning over in the direction you moved ──
        Box(Modifier.fillMaxWidth().background(Brush.linearGradient(palette.band)).edgeLight()) {
            AnimatedContent(
                targetState = plan,
                contentKey = { it.code },
                transitionSpec = {
                    val up = order(targetState.code) > order(initialState.code)
                    (slideInVertically(tween(280)) { h -> if (up) h / 4 else -h / 4 } + fadeIn(tween(220)))
                        .togetherWith(slideOutVertically(tween(200)) { h -> if (up) -h / 4 else h / 4 } + fadeOut(tween(140)))
                        .using(SizeTransform(clip = false))
                },
                label = "plan-band",
            ) { p ->
                val showing = p.code == plan.code
                BandContent(p, if (showing) tier else tierOf(p), interval, isCurrent = isCurrent && showing)
            }
        }

        // ── Your plan, when you're looking at it: where you stand and what you've used ──
        AnimatedVisibility(
            visible = isCurrent && membership != null,
            enter = expandVertically(spring(stiffness = Spring.StiffnessMediumLow)) + fadeIn(),
            exit = shrinkVertically(spring(stiffness = Spring.StiffnessMedium)) + fadeOut(tween(120)),
        ) {
            membership?.let { CurrentPlanDetails(it, palette) }
        }

        // ── Sheet: lines stay put, values turn over in place ──
        Column(Modifier.padding(start = 20.dp, end = 20.dp, bottom = 12.dp)) {
            sections.forEachIndexed { index, section ->
                Text(
                    section.area.title,
                    color = palette.sub,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(top = if (index == 0) 18.dp else 22.dp, bottom = 2.dp),
                )
                section.rows.forEachIndexed { i, row ->
                    key(row.key) {
                        if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(palette.rule))
                        SheetRow(row, palette)
                    }
                }
            }
            if (afterCancel) {
                Spacer(Modifier.height(12.dp))
                Text("You'd move here if you cancel.", color = palette.sub, fontSize = 12.sp)
            }
        }
    }
}

@Composable
private fun BandContent(plan: CataloguePlan, tier: MemberTier, interval: String, isCurrent: Boolean) {
    Row(
        Modifier.fillMaxWidth().padding(20.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        MemberTierStyles.of(tier)?.let {
            Image(painterResource(it.mark), contentDescription = null, modifier = Modifier.size(46.dp))
            Spacer(Modifier.width(14.dp))
        }
        Column(Modifier.weight(1f)) {
            Text(plan.name, color = Color.White, fontSize = 25.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.5).sp)
            plan.tagline?.takeIf { it.isNotBlank() }?.let {
                Spacer(Modifier.height(3.dp))
                Text(it, color = Color.White.copy(alpha = 0.72f), fontSize = 13.sp, lineHeight = 18.sp)
            }
        }
        Spacer(Modifier.width(12.dp))
        val price = priceOf(plan, interval)
        when {
            isCurrent -> Text(
                "Your plan",
                color = Color.White,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.14f))
                    .border(1.dp, Color.White.copy(alpha = 0.18f), CircleShape)
                    .padding(horizontal = 11.dp, vertical = 5.dp),
            )
            price != null -> Column(horizontalAlignment = Alignment.End) {
                Text(price.first, color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.3).sp)
                price.second?.let { Text(it, color = Color.White.copy(alpha = 0.64f), fontSize = 11.5.sp) }
            }
            else -> Text("Not on sale yet", color = Color.White.copy(alpha = 0.64f), fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun SheetRow(row: PlanSheetRow, palette: PlanPalette) {
    val name by animateColorAsState(if (row.included) palette.text else palette.muted, label = "row-name")
    // Holds the last comparison while it collapses, so the words don't vanish mid-exit.
    var caption by remember { mutableStateOf(row.yours) }
    if (row.yours != null) caption = row.yours

    Row(
        Modifier.fillMaxWidth().heightIn(min = 50.dp).padding(vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(row.name, color = name, fontSize = 14.5.sp, fontWeight = FontWeight.Medium, maxLines = 1, overflow = TextOverflow.Ellipsis)
            AnimatedVisibility(
                visible = row.yours != null,
                enter = expandVertically(tween(220)) + fadeIn(tween(220)),
                exit = shrinkVertically(tween(160)) + fadeOut(tween(100)),
            ) {
                Text(
                    caption.orEmpty(),
                    color = if (row.gain) palette.accent else palette.sub,
                    fontSize = 12.sp,
                    fontWeight = if (row.gain) FontWeight.SemiBold else FontWeight.Normal,
                    modifier = Modifier.padding(top = 1.dp),
                )
            }
        }
        Spacer(Modifier.width(12.dp))
        AnimatedContent(
            targetState = row.value,
            transitionSpec = {
                (slideInVertically(tween(240)) { it / 2 } + fadeIn(tween(200)) + scaleIn(tween(240), initialScale = 0.9f))
                    .togetherWith(slideOutVertically(tween(160)) { -it / 2 } + fadeOut(tween(120)))
                    .using(SizeTransform(clip = false))
            },
            contentAlignment = Alignment.CenterEnd,
            label = "row-value",
        ) { value -> ValueMark(value, palette) }
    }
}

@Composable
private fun ValueMark(value: String?, palette: PlanPalette) {
    if (value == null) {
        Box(Modifier.size(22.dp).semantics { contentDescription = "Not included" }, contentAlignment = Alignment.Center) {
            Box(Modifier.width(10.dp).height(1.5.dp).clip(CircleShape).background(palette.muted))
        }
        return
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        if (value != "Included") {
            Text(value, color = palette.accent, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
            Spacer(Modifier.width(9.dp))
        }
        Box(
            Modifier.size(22.dp).clip(CircleShape).background(palette.accent.copy(alpha = 0.14f))
                .semantics { contentDescription = "Included" },
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Default.Check, contentDescription = null, tint = palette.accent, modifier = Modifier.size(13.dp))
        }
    }
}

/** Where the member stands on the plan they're looking at: renewal, sport picks, quota used. */
@Composable
private fun CurrentPlanDetails(m: Membership, palette: PlanPalette) {
    Column(Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, top = 16.dp)) {
        // A problem with the plan is stated above the plans instead; here it would repeat.
        if (m.attention == null) {
            Text(MembershipFormat.statusLine(m), color = palette.sub, fontSize = 13.5.sp, lineHeight = 19.sp)
        }
        m.scheduledChange?.let { change ->
            val on = MembershipFormat.date(change.startsAt)
            if (on != null && change.plan.name != null) {
                Spacer(Modifier.height(6.dp))
                Text("Switching to ${change.plan.name} on $on.", color = palette.accent, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            }
        }

        m.insightSports?.let { sports ->
            Spacer(Modifier.height(14.dp))
            Row(
                Modifier
                    .pressable { MembershipNav.openInsightSports() }
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(palette.rule)
                    .padding(start = 14.dp, end = 8.dp, top = 12.dp, bottom = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("Advanced insights", color = palette.text, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    Text(InsightSportPicker.summary(sports), color = palette.sub, fontSize = 12.5.sp)
                }
                Text(
                    when (sports.mode) {
                        "choose" -> if (sports.selected.isEmpty()) "Choose sports" else "Change"
                        "all" -> "View"
                        else -> "See sports"
                    },
                    color = palette.accent, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                )
                Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = palette.accent, modifier = Modifier.size(18.dp))
            }
        }

        m.entitlements.forEach { f ->
            val line = MembershipFormat.usageLine(f) ?: return@forEach
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(f.name, color = palette.text, fontSize = 13.5.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                Text(line, color = palette.sub, fontSize = 12.5.sp)
            }
            val limit = f.limit
            if (!f.unlimited && limit != null && limit > 0) {
                Spacer(Modifier.height(7.dp))
                val fraction = ((f.used ?: 0).toFloat() / limit).coerceIn(0f, 1f)
                Box(Modifier.fillMaxWidth().height(5.dp).clip(CircleShape).background(palette.rule)) {
                    if (fraction > 0f) {
                        Box(
                            Modifier.fillMaxWidth(fraction).height(5.dp).clip(CircleShape)
                                .background(if (fraction >= 1f) HaraanColors.Warning else palette.accent),
                        )
                    }
                }
            }
        }
        Spacer(Modifier.height(4.dp))
    }
}
