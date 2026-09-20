package com.haraan.app.ui.payment

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.R
import com.haraan.app.ui.Feel
import com.haraan.app.ui.membership.MembershipFormat
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanTypography

enum class HaraanPaymentInstrument {
    /** UPI. Razorpay's sheet opens on its UPI tab, where the app handoff happens. */
    UPI,

    /** UPI with a VPA the buyer typed, prefilled into Razorpay's sheet via `prefill.vpa`. */
    UPI_VPA,
    CARD,
    NETBANKING,

    /**
     * Razorpay's full sheet, with nothing prefilled.
     *
     * This is the route for a buyer who wants to tap Google Pay and *be taken there*: Razorpay's
     * own screen performs real UPI intent handoff, plus wallets and EMI that we do not list. We
     * cannot do that handoff from our sheet (see [StandardCheckoutUpi]), so rather than fake it we
     * offer the door that genuinely opens.
     */
    RAZORPAY,
}

/**
 * The Haraan payment sheet — chooses *how* to pay, then hands off to Razorpay to take the money.
 *
 * ## Shape of the screen
 *
 * UPI is not one of four equal options; it is most of what people use, so it gets the room. The
 * installed apps are the headline, typing a UPI ID hides behind a disclosure underneath, and card,
 * netbanking and Razorpay's own sheet sit below as short rows. An earlier draft made all four
 * identical bordered cards with matching icon/title/subtitle/radio, which is a settings form
 * wearing a payment sheet's clothes.
 *
 * ## Why this sheet does not launch UPI apps itself
 *
 * Choosing an app here is a preference, not a launch, and the copy says so. The SDK we ship cannot
 * take a named app — [StandardCheckoutUpi] documents exactly what was decompiled to establish that.
 * Buyers who want the real handoff have [HaraanPaymentInstrument.RAZORPAY].
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HaraanPaySheet(
    title: String,
    totalPaise: Long,
    subtotal: Double,
    fee: Double,
    discount: Double,
    couponCode: String?,
    prefillName: String,
    prefillEmail: String,
    prefillPhone: String,
    onDismiss: () -> Unit,
    onPayRequested: (instrument: HaraanPaymentInstrument, vpa: String?) -> Unit,
    capability: HaraanPayUpiCapability = StandardCheckoutUpi,
) {
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    val installedUpiApps = remember(context, capability) { capability.installedApps(context) }
    var selected by remember { mutableStateOf(HaraanPaymentInstrument.UPI) }
    var chosenApp by remember { mutableStateOf(installedUpiApps.firstOrNull()) }
    var vpaInput by remember { mutableStateOf("") }
    var vpaExpanded by remember { mutableStateOf(false) }
    var showBreakdown by remember { mutableStateOf(false) }

    // Straight from paise — the total is the one number that must never round-trip through a Double.
    val formattedTotal = MembershipFormat.rupees(totalPaise)
    val isVpaValid = remember(vpaInput) { vpaInput.trim().matches(VpaPattern) }
    val onUpi = selected == HaraanPaymentInstrument.UPI || selected == HaraanPaymentInstrument.UPI_VPA

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HaraanColors.Surface,
        contentColor = HaraanColors.TextPrimary,
        scrimColor = HaraanColors.TextPrimary.copy(alpha = 0.32f),
        dragHandle = {
            Box(
                modifier = Modifier
                    .padding(top = 12.dp, bottom = 2.dp)
                    .width(36.dp)
                    .height(4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(HaraanColors.BorderLight),
            )
        },
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 22.dp),
        ) {
            // ── Identity, kept to one line ───────────────────────────────────
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(30.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(HaraanColors.AccentTint),
                    contentAlignment = Alignment.Center,
                ) {
                    Image(
                        painter = painterResource(id = R.drawable.haraan_copy),
                        contentDescription = null,
                        // The asset ships near-black and disappears into the pale well.
                        colorFilter = ColorFilter.tint(HaraanColors.EventsBlue),
                        modifier = Modifier.size(17.dp),
                    )
                }
                Spacer(Modifier.width(9.dp))
                Text(
                    text = "Checkout",
                    color = HaraanColors.TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                )
            }

            Spacer(Modifier.height(20.dp))

            // ── The amount, as type rather than as a boxed figure ────────────
            AmountHeadline(
                title = title,
                formattedTotal = formattedTotal,
                subtotal = subtotal,
                fee = fee,
                discount = discount,
                couponCode = couponCode,
                showBreakdown = showBreakdown,
                onToggleBreakdown = { showBreakdown = !showBreakdown },
            )

            Spacer(Modifier.height(24.dp))

            // ── UPI: the one that gets the room ──────────────────────────────
            UpiBlock(
                apps = installedUpiApps,
                chosenApp = chosenApp,
                isActive = onUpi,
                supportsDirectHandoff = capability.supportsDirectAppHandoff,
                vpaInput = vpaInput,
                vpaExpanded = vpaExpanded,
                isVpaValid = isVpaValid,
                onActivate = {
                    selected = if (vpaExpanded && isVpaValid) {
                        HaraanPaymentInstrument.UPI_VPA
                    } else {
                        HaraanPaymentInstrument.UPI
                    }
                },
                onChooseApp = {
                    chosenApp = it
                    selected = HaraanPaymentInstrument.UPI
                    focusManager.clearFocus()
                },
                onToggleVpa = {
                    vpaExpanded = !vpaExpanded
                    if (!vpaExpanded) {
                        focusManager.clearFocus()
                        selected = HaraanPaymentInstrument.UPI
                    }
                },
                onVpaChange = {
                    vpaInput = it.trim().lowercase()
                    selected = if (vpaInput.trim().matches(VpaPattern)) {
                        HaraanPaymentInstrument.UPI_VPA
                    } else {
                        HaraanPaymentInstrument.UPI
                    }
                },
                onVpaDone = { focusManager.clearFocus() },
            )

            Spacer(Modifier.height(20.dp))

            Text(
                text = "OTHER WAYS TO PAY",
                color = HaraanColors.TextMuted,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.1.sp,
            )

            Spacer(Modifier.height(4.dp))

            MethodRow(
                label = "Card",
                idle = HaraanPayIcons.CardOutline,
                active = HaraanPayIcons.CardActive,
                isSelected = selected == HaraanPaymentInstrument.CARD,
                onSelect = {
                    selected = HaraanPaymentInstrument.CARD
                    focusManager.clearFocus()
                },
            )
            MethodRow(
                label = "Netbanking",
                idle = HaraanPayIcons.BankOutline,
                active = HaraanPayIcons.BankActive,
                isSelected = selected == HaraanPaymentInstrument.NETBANKING,
                onSelect = {
                    selected = HaraanPaymentInstrument.NETBANKING
                    focusManager.clearFocus()
                },
            )
            MethodRow(
                label = "More ways to pay",
                idle = HaraanPayIcons.WalletOutline,
                active = HaraanPayIcons.WalletActive,
                isSelected = selected == HaraanPaymentInstrument.RAZORPAY,
                // The one concrete thing this row knows that a generic gateway list would not.
                note = "Opens your UPI app directly",
                onSelect = {
                    selected = HaraanPaymentInstrument.RAZORPAY
                    focusManager.clearFocus()
                },
            )

            Spacer(Modifier.height(24.dp))

            PayButton(
                label = "Pay $formattedTotal",
                onClick = {
                    onPayRequested(
                        selected,
                        vpaInput.takeIf { selected == HaraanPaymentInstrument.UPI_VPA && isVpaValid },
                    )
                },
            )

            // Who the ticket and receipt go to. The last screen where a typo is still cheap.
            val payingAs = listOf(prefillName, prefillPhone, prefillEmail)
                .filter { it.isNotBlank() }
                .distinct()
            if (payingAs.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Text(
                    text = payingAs.joinToString("  ·  "),
                    color = HaraanColors.TextMuted,
                    fontSize = 11.5.sp,
                    textAlign = TextAlign.Center,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.fillMaxWidth(),
                )
            }

            Spacer(Modifier.height(18.dp))
        }
    }
}

/**
 * The total, set as type.
 *
 * It used to sit in a grey rounded well with a label above it — the boxed-figure pattern that makes
 * a screen look assembled from components rather than designed. The number is the most important
 * thing here, so it is simply the biggest thing here.
 */
@Composable
private fun AmountHeadline(
    title: String,
    formattedTotal: String,
    subtotal: Double,
    fee: Double,
    discount: Double,
    couponCode: String?,
    showBreakdown: Boolean,
    onToggleBreakdown: () -> Unit,
) {
    Column(Modifier.animateContentSize()) {
        Text(
            text = formattedTotal,
            color = HaraanColors.TextPrimary,
            fontSize = 38.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.8).sp,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            text = title,
            color = HaraanColors.TextSecondary,
            fontSize = 14.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )

        if (discount > 0.0) {
            Spacer(Modifier.height(10.dp))
            Text(
                text = buildString {
                    append("Saved ")
                    append(MembershipFormat.rupees(Math.round(discount * 100)))
                    if (!couponCode.isNullOrBlank()) append(" with ").append(couponCode)
                },
                color = HaraanColors.Success,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(RoundedCornerShape(7.dp))
                    .background(HaraanColors.SuccessTint)
                    .padding(horizontal = 9.dp, vertical = 5.dp),
            )
        }

        Spacer(Modifier.height(10.dp))

        Row(
            modifier = Modifier
                .clip(RoundedCornerShape(8.dp))
                .pressable(haptic = Feel.TICK, onClick = onToggleBreakdown)
                .padding(vertical = 3.dp, horizontal = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = if (showBreakdown) "Hide breakdown" else "View breakdown",
                color = HaraanColors.EventsBlue,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Medium,
            )
            Spacer(Modifier.width(3.dp))
            val chevron by animateFloatAsState(
                targetValue = if (showBreakdown) 180f else 0f,
                animationSpec = spring(),
                label = "breakdownChevron",
            )
            Icon(
                imageVector = Icons.Default.KeyboardArrowDown,
                contentDescription = null,
                tint = HaraanColors.EventsBlue,
                modifier = Modifier
                    .size(16.dp)
                    .rotate(chevron),
            )
        }

        AnimatedVisibility(
            visible = showBreakdown,
            enter = expandVertically() + fadeIn(),
            exit = shrinkVertically() + fadeOut(),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 6.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(HaraanColors.Background)
                    .padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                BillRow("Subtotal", MembershipFormat.rupees(Math.round(subtotal * 100)))
                if (fee > 0.0) {
                    BillRow("Convenience fee", MembershipFormat.rupees(Math.round(fee * 100)))
                }
                if (discount > 0.0) {
                    BillRow(
                        "Discount",
                        "− " + MembershipFormat.rupees(Math.round(discount * 100)),
                        HaraanColors.Success,
                    )
                }
                HorizontalDivider(color = HaraanColors.Hairline)
                BillRow("Total", formattedTotal, HaraanColors.TextPrimary, isBold = true)
            }
        }
    }
}

/**
 * UPI, given the space it earns.
 *
 * No radio button: the block is either lit or it isn't, and its contents fold away when another
 * method takes over. A radio beside a card that is already outlined and tinted is gateway
 * furniture saying the same thing three times.
 */
@Composable
private fun UpiBlock(
    apps: List<UpiAppTarget>,
    chosenApp: UpiAppTarget?,
    isActive: Boolean,
    supportsDirectHandoff: Boolean,
    vpaInput: String,
    vpaExpanded: Boolean,
    isVpaValid: Boolean,
    onActivate: () -> Unit,
    onChooseApp: (UpiAppTarget) -> Unit,
    onToggleVpa: () -> Unit,
    onVpaChange: (String) -> Unit,
    onVpaDone: () -> Unit,
) {
    val lit by animateFloatAsState(
        targetValue = if (isActive) 1f else 0f,
        animationSpec = spring(stiffness = Spring.StiffnessMediumLow),
        label = "upiLit",
    )

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(lerp(HaraanColors.Surface, HaraanColors.AccentTint.copy(alpha = 0.5f), lit))
            .border(
                width = (1f + 0.6f * lit).dp,
                color = lerp(HaraanColors.BorderLight, HaraanColors.EventsBlue, lit),
                shape = RoundedCornerShape(20.dp),
            )
            .pressable(haptic = Feel.SELECT, onClick = onActivate)
            .animateContentSize()
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                imageVector = if (isActive) HaraanPayIcons.UpiActive else HaraanPayIcons.UpiOutline,
                contentDescription = null,
                tint = lerp(HaraanColors.TextSecondary, HaraanColors.EventsBlue, lit),
                modifier = Modifier.size(24.dp),
            )
            Spacer(Modifier.width(11.dp))
            Text(
                text = "UPI",
                color = HaraanColors.TextPrimary,
                fontSize = 16.sp,
                fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.weight(1f))
            if (apps.isNotEmpty()) {
                Text(
                    text = "on this phone",
                    color = HaraanColors.TextMuted,
                    fontSize = 11.5.sp,
                )
            }
        }

        AnimatedVisibility(
            visible = isActive,
            enter = expandVertically() + fadeIn(),
            exit = shrinkVertically() + fadeOut(),
        ) {
            Column {
                Spacer(Modifier.height(14.dp))

                if (apps.isNotEmpty()) {
                    UpiAppPicker(apps = apps, chosen = chosenApp, onChoose = onChooseApp)

                    // What actually happens next, in plain words — see [StandardCheckoutUpi].
                    if (!supportsDirectHandoff) {
                        Spacer(Modifier.height(12.dp))
                        Text(
                            text = chosenApp?.let { "You'll confirm in ${it.name} on the next screen." }
                                ?: "You'll confirm on the next screen.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 12.sp,
                            lineHeight = 17.sp,
                        )
                    }

                    Spacer(Modifier.height(14.dp))
                    HorizontalDivider(color = HaraanColors.Hairline)
                }

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .pressable(haptic = Feel.TICK, onClick = onToggleVpa)
                        .padding(vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        text = "Pay to a UPI ID instead",
                        color = HaraanColors.EventsBlue,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                    )
                    Spacer(Modifier.width(4.dp))
                    val chevron by animateFloatAsState(
                        targetValue = if (vpaExpanded) 180f else 0f,
                        animationSpec = spring(),
                        label = "vpaChevron",
                    )
                    Icon(
                        imageVector = Icons.Default.KeyboardArrowDown,
                        contentDescription = null,
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier
                            .size(16.dp)
                            .rotate(chevron),
                    )
                }

                AnimatedVisibility(
                    visible = vpaExpanded,
                    enter = expandVertically() + fadeIn(),
                    exit = shrinkVertically() + fadeOut(),
                ) {
                    VpaField(
                        value = vpaInput,
                        isValid = isVpaValid,
                        onValueChange = onVpaChange,
                        onDone = onVpaDone,
                    )
                }
            }
        }
    }
}

/**
 * A secondary method: one short row, a glyph and a name.
 *
 * No subtitle. "Pay straight from your bank account" explains netbanking to people who have used it
 * for twenty years, and padding every row with a line of filler is what makes a list look generated.
 */
@Composable
private fun MethodRow(
    label: String,
    idle: ImageVector,
    active: ImageVector,
    isSelected: Boolean,
    onSelect: () -> Unit,
    note: String? = null,
) {
    val lit by animateFloatAsState(
        targetValue = if (isSelected) 1f else 0f,
        animationSpec = spring(stiffness = Spring.StiffnessMediumLow),
        label = "methodLit",
    )

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .pressable(haptic = Feel.SELECT, onClick = onSelect)
            .padding(vertical = 13.dp, horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            imageVector = if (isSelected) active else idle,
            contentDescription = null,
            tint = lerp(HaraanColors.TextSecondary, HaraanColors.EventsBlue, lit),
            modifier = Modifier.size(23.dp),
        )
        Spacer(Modifier.width(13.dp))
        Text(
            text = label,
            color = HaraanColors.TextPrimary,
            fontSize = 14.5.sp,
            fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Medium,
        )
        if (note != null) {
            Spacer(Modifier.width(8.dp))
            Text(
                text = note,
                color = HaraanColors.TextMuted,
                fontSize = 11.5.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.weight(1f))
        Icon(
            imageVector = Icons.Default.Check,
            contentDescription = null,
            tint = HaraanColors.EventsBlue,
            modifier = Modifier
                .size(17.dp)
                .alpha(lit),
        )
    }
}

@Composable
private fun UpiAppPicker(
    apps: List<UpiAppTarget>,
    chosen: UpiAppTarget?,
    onChoose: (UpiAppTarget) -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        apps.forEach { app ->
            val isChosen = chosen?.packageName == app.packageName

            // One spring drives lift and tint together, so selection reads as a single
            // movement rather than two properties changing at slightly different times.
            val lift by animateFloatAsState(
                targetValue = if (isChosen) 1f else 0f,
                animationSpec = spring(
                    dampingRatio = Spring.DampingRatioMediumBouncy,
                    stiffness = Spring.StiffnessMediumLow,
                ),
                label = "upiAppLift",
            )

            Column(
                modifier = Modifier
                    .width(76.dp)
                    .scale(1f + 0.03f * lift)
                    .clip(RoundedCornerShape(16.dp))
                    .background(lerp(HaraanColors.Surface, Color.White, lift))
                    .border(
                        width = (1f + 0.7f * lift).dp,
                        color = lerp(HaraanColors.BorderLight, HaraanColors.EventsBlue, lift),
                        shape = RoundedCornerShape(16.dp),
                    )
                    .pressable(haptic = Feel.SELECT) { onChoose(app) }
                    .padding(vertical = 12.dp, horizontal = 6.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                // getApplicationIcon can come back null on a restricted or half-installed
                // profile. A monogram keeps the tile the same height, so the row does not
                // collapse into ragged dead space when one app misbehaves.
                if (app.icon != null) {
                    AsyncImage(
                        model = app.icon,
                        contentDescription = app.name,
                        modifier = Modifier.size(34.dp),
                    )
                } else {
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(HaraanColors.Field),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            text = app.name.take(1).uppercase(),
                            color = HaraanColors.TextSecondary,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                        )
                    }
                }
                Spacer(Modifier.height(7.dp))
                Text(
                    text = app.name,
                    color = if (isChosen) HaraanColors.TextPrimary else HaraanColors.TextSecondary,
                    fontSize = 11.sp,
                    lineHeight = 13.sp,
                    fontWeight = if (isChosen) FontWeight.SemiBold else FontWeight.Medium,
                    // Two lines, because a bank's UPI app is called "Bank of Baroda", not "BoB".
                    // On one line those truncate to noise the buyer cannot tell apart.
                    maxLines = 2,
                    minLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}

@Composable
private fun VpaField(
    value: String,
    isValid: Boolean,
    onValueChange: (String) -> Unit,
    onDone: () -> Unit,
) {
    Column(Modifier.padding(top = 2.dp, bottom = 4.dp)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(HaraanColors.Surface)
                .border(
                    1.dp,
                    if (isValid) HaraanColors.Success else HaraanColors.BorderLight,
                    RoundedCornerShape(12.dp),
                )
                .padding(horizontal = 14.dp, vertical = 13.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                modifier = Modifier.weight(1f),
                textStyle = HaraanTypography.BodyLarge.copy(
                    color = HaraanColors.TextPrimary,
                    fontSize = 14.sp,
                ),
                cursorBrush = SolidColor(HaraanColors.EventsBlue),
                singleLine = true,
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Email,
                    imeAction = ImeAction.Done,
                ),
                keyboardActions = KeyboardActions(onDone = { onDone() }),
                decorationBox = { innerTextField ->
                    if (value.isEmpty()) {
                        Text(
                            text = "name@bank",
                            color = HaraanColors.TextMuted,
                            fontSize = 14.sp,
                        )
                    }
                    innerTextField()
                },
            )
            AnimatedVisibility(visible = isValid, enter = fadeIn(), exit = fadeOut()) {
                Icon(
                    imageVector = Icons.Default.Check,
                    contentDescription = "Valid UPI ID",
                    tint = HaraanColors.Success,
                    modifier = Modifier.size(18.dp),
                )
            }
        }
    }
}

@Composable
private fun PayButton(label: String, onClick: () -> Unit) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .height(54.dp)
            .pressable(haptic = Feel.COMMIT, onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        color = HaraanColors.EventsBlue,
    ) {
        Box(contentAlignment = Alignment.Center) {
            Text(
                text = label,
                color = Color.White,
                fontSize = 16.sp,
                fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun BillRow(
    label: String,
    value: String,
    valueColor: Color = HaraanColors.TextPrimary,
    isBold: Boolean = false,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = label,
            color = if (isBold) HaraanColors.TextPrimary else HaraanColors.TextSecondary,
            fontSize = if (isBold) 13.5.sp else 12.5.sp,
            fontWeight = if (isBold) FontWeight.SemiBold else FontWeight.Normal,
        )
        Text(
            text = value,
            color = valueColor,
            fontSize = if (isBold) 14.sp else 12.5.sp,
            fontWeight = if (isBold) FontWeight.Bold else FontWeight.Medium,
        )
    }
}

/** `name@bank` — NPCI allows dots, hyphens and underscores in the address part. */
private val VpaPattern = Regex("^[a-zA-Z0-9._-]{2,256}@[a-zA-Z]{2,64}$")
