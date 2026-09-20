package com.haraan.app.ui.payment

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.drawable.Drawable
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.CreditCard
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.QrCode
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
 * The Haraan payment sheet — chooses *how* to pay, then hands off to Razorpay to actually take
 * the money.
 *
 * ## Why this sheet does not launch UPI apps itself
 *
 * An earlier draft showed Google Pay / PhonePe / Paytm as tappable targets and passed the chosen
 * package to checkout. Auditing the SDK we actually ship (`com.razorpay:checkout:1.6.40`, which
 * resolves `standard-core:1.7.18`) shows that handoff cannot work:
 *
 * - `com.razorpay.Checkout` exposes no way to list UPI apps or to name one. The internal helper
 *   `CheckoutUtils.getUpiIntentsDataInJsonArray()` and the `upi_intents_data` / `callNativeIntent`
 *   bridge are package-private to the SDK's own checkout UI.
 * - The options payload recognises `prefill.method` and `prefill.vpa` (both handled by
 *   `PayloadHelper`), but there is no `upi_app_package_name` or `_[flow]` key anywhere in the jar.
 *
 * Firing our own `upi://pay` intent would work, but it would bypass the Razorpay order the backend
 * created, so nothing would reconcile against the webhook — and it would put us outside the
 * gateway's PCI scope for no gain.
 *
 * So the honest design: we *detect* installed apps and show the buyer which ones are ready, then
 * let Razorpay's secure sheet — which does real UPI intent handoff, with the same apps — perform
 * the authorization. Typing a UPI ID is genuinely useful and is prefilled, because `prefill.vpa`
 * is a key the SDK really honours.
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

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HaraanColors.Surface,
        contentColor = HaraanColors.TextPrimary,
        scrimColor = HaraanColors.TextPrimary.copy(alpha = 0.32f),
        dragHandle = {
            Box(
                modifier = Modifier
                    .padding(top = 12.dp, bottom = 4.dp)
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
                .padding(horizontal = 20.dp),
        ) {
            SheetHeader()

            Spacer(Modifier.height(18.dp))

            AmountCard(
                title = title,
                formattedTotal = formattedTotal,
                subtotal = subtotal,
                fee = fee,
                discount = discount,
                couponCode = couponCode,
                showBreakdown = showBreakdown,
                onToggleBreakdown = { showBreakdown = !showBreakdown },
            )

            Spacer(Modifier.height(22.dp))

            Text(
                text = "How would you like to pay?",
                color = HaraanColors.TextPrimary,
                style = HaraanTypography.BodyLarge,
                fontWeight = FontWeight.SemiBold,
                fontSize = 15.sp,
            )

            Spacer(Modifier.height(12.dp))

            // UPI — apps first, UPI ID demoted to the slow path underneath.
            InstrumentCard(
                title = "UPI",
                subtitle = if (installedUpiApps.isEmpty()) {
                    "Pay from any UPI app or bank account"
                } else {
                    "Pay from an app on this phone"
                },
                icon = Icons.Outlined.QrCode,
                isSelected = selected == HaraanPaymentInstrument.UPI ||
                    selected == HaraanPaymentInstrument.UPI_VPA,
                recommended = true,
                onSelect = {
                    selected = if (vpaExpanded && isVpaValid) {
                        HaraanPaymentInstrument.UPI_VPA
                    } else {
                        HaraanPaymentInstrument.UPI
                    }
                },
            ) {
                Column(Modifier.padding(top = 14.dp)) {
                    if (installedUpiApps.isNotEmpty()) {
                        UpiAppPicker(
                            apps = installedUpiApps,
                            chosen = chosenApp,
                            onChoose = {
                                chosenApp = it
                                selected = HaraanPaymentInstrument.UPI
                                focusManager.clearFocus()
                            },
                        )

                        // What actually happens next, in plain words. The SDK we ship cannot open
                        // a named app itself, so the sheet must not imply that it will — see
                        // [StandardCheckoutUpi]. Buyers who want a real handoff have "More ways
                        // to pay" below, which is Razorpay's own sheet.
                        if (!capability.supportsDirectAppHandoff) {
                            Spacer(Modifier.height(12.dp))
                            Text(
                                text = chosenApp?.let {
                                    "Razorpay's secure screen opens next — choose ${it.name} there."
                                } ?: "Razorpay's secure screen opens next.",
                                color = HaraanColors.TextSecondary,
                                fontSize = 12.sp,
                                lineHeight = 17.sp,
                            )
                        }

                        Spacer(Modifier.height(14.dp))
                        HorizontalDivider(color = HaraanColors.Hairline)
                    }

                    // Secondary and collapsed: typing a VPA is the slow path, not the headline.
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .pressable(haptic = Feel.TICK) {
                                vpaExpanded = !vpaExpanded
                                if (!vpaExpanded) {
                                    focusManager.clearFocus()
                                    selected = HaraanPaymentInstrument.UPI
                                }
                            }
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
                            onValueChange = {
                                vpaInput = it.trim().lowercase()
                                selected = if (vpaInput.trim().matches(VpaPattern)) {
                                    HaraanPaymentInstrument.UPI_VPA
                                } else {
                                    HaraanPaymentInstrument.UPI
                                }
                            },
                            onDone = { focusManager.clearFocus() },
                        )
                    }
                }
            }

            Spacer(Modifier.height(10.dp))

            InstrumentCard(
                title = "Card",
                subtitle = "Visa, Mastercard, RuPay and Amex",
                icon = Icons.Outlined.CreditCard,
                isSelected = selected == HaraanPaymentInstrument.CARD,
                onSelect = { selected = HaraanPaymentInstrument.CARD },
            )

            Spacer(Modifier.height(10.dp))

            InstrumentCard(
                title = "Netbanking",
                subtitle = "Pay straight from your bank account",
                icon = Icons.Outlined.AccountBalance,
                isSelected = selected == HaraanPaymentInstrument.NETBANKING,
                onSelect = { selected = HaraanPaymentInstrument.NETBANKING },
            )

            Spacer(Modifier.height(10.dp))

            // The one route that really does open a UPI app for you: Razorpay's own sheet performs
            // the intent handoff we cannot, and carries wallets and EMI we do not list.
            InstrumentCard(
                title = "More ways to pay",
                subtitle = "Razorpay's screen \u2014 opens your UPI app directly, plus wallets and EMI",
                icon = Icons.Outlined.AccountBalanceWallet,
                isSelected = selected == HaraanPaymentInstrument.RAZORPAY,
                onSelect = { selected = HaraanPaymentInstrument.RAZORPAY },
            )

            Spacer(Modifier.height(22.dp))

            PayButton(
                label = "Pay $formattedTotal",
                onClick = {
                    onPayRequested(
                        selected,
                        vpaInput.takeIf { selected == HaraanPaymentInstrument.UPI_VPA && isVpaValid },
                    )
                },
            )

            // Who the receipt and the ticket will go to. This is the last screen where a typo in
            // the email is still cheap to fix, so it is worth the two lines.
            val payingAs = listOf(prefillName, prefillPhone, prefillEmail)
                .filter { it.isNotBlank() }
                .distinct()
            if (payingAs.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Text(
                    text = "Paying as ${payingAs.joinToString(" · ")}",
                    color = HaraanColors.TextSecondary,
                    fontSize = 12.sp,
                    textAlign = TextAlign.Center,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.fillMaxWidth(),
                )
            }

            Spacer(Modifier.height(12.dp))

            Text(
                text = "Razorpay takes the payment on a secure screen. Haraan never sees your card or UPI PIN.",
                color = HaraanColors.TextMuted,
                fontSize = 11.5.sp,
                lineHeight = 16.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )

            Spacer(Modifier.height(20.dp))
        }
    }
}

/** "Google Pay, PhonePe and 2 more ready on this phone" — never a fabricated count. */
private fun upiSubtitle(apps: List<UpiAppTarget>): String = when (apps.size) {
    1 -> "${apps[0].name} is ready on this phone"
    2 -> "${apps[0].name} and ${apps[1].name} are ready on this phone"
    else -> "${apps[0].name}, ${apps[1].name} and ${apps.size - 2} more ready on this phone"
}

@Composable
private fun SheetHeader() {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(34.dp)
                .clip(RoundedCornerShape(11.dp))
                .background(HaraanColors.AccentTint),
            contentAlignment = Alignment.Center,
        ) {
            Image(
                painter = painterResource(id = R.drawable.haraan_copy),
                contentDescription = null,
                // The asset ships near-black, which disappears into the pale accent well.
                // Tinted to brand blue so the monogram reads as the mark, not a smudge.
                colorFilter = ColorFilter.tint(HaraanColors.EventsBlue),
                modifier = Modifier.size(19.dp),
            )
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(
                text = "Checkout",
                color = HaraanColors.TextPrimary,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
            )
            Text(
                text = "Secured by Razorpay",
                color = HaraanColors.TextSecondary,
                fontSize = 11.5.sp,
            )
        }
        Row(
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(HaraanColors.SuccessTint)
                .padding(horizontal = 9.dp, vertical = 5.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Icon(
                imageVector = Icons.Outlined.Lock,
                contentDescription = null,
                tint = HaraanColors.Success,
                modifier = Modifier.size(11.dp),
            )
            Text(
                text = "Encrypted",
                color = HaraanColors.Success,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun AmountCard(
    title: String,
    formattedTotal: String,
    subtotal: Double,
    fee: Double,
    discount: Double,
    couponCode: String?,
    showBreakdown: Boolean,
    onToggleBreakdown: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(HaraanColors.Background)
            .border(1.dp, HaraanColors.Hairline, RoundedCornerShape(18.dp))
            .animateContentSize()
            .padding(16.dp),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = "Paying for",
                    color = HaraanColors.TextMuted,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                )
                Text(
                    text = title,
                    color = HaraanColors.TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.padding(top = 2.dp),
                )
            }
            Spacer(Modifier.width(12.dp))
            Text(
                text = formattedTotal,
                color = HaraanColors.TextPrimary,
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
            )
        }

        if (discount > 0.0) {
            Spacer(Modifier.height(12.dp))
            Text(
                text = buildString {
                    append("You saved ")
                    append(formatRupees(discount))
                    if (!couponCode.isNullOrBlank()) append(" with $couponCode")
                },
                color = HaraanColors.Success,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(HaraanColors.SuccessTint)
                    .padding(horizontal = 10.dp, vertical = 6.dp),
            )
        }

        Spacer(Modifier.height(10.dp))

        Row(
            modifier = Modifier
                .clip(RoundedCornerShape(8.dp))
                .pressable(haptic = Feel.TICK, onClick = onToggleBreakdown)
                .padding(vertical = 4.dp, horizontal = 2.dp),
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
                    .padding(top = 8.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(HaraanColors.Surface)
                    .border(1.dp, HaraanColors.Hairline, RoundedCornerShape(12.dp))
                    .padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                BillRow("Subtotal", formatRupees(subtotal))
                if (fee > 0.0) BillRow("Convenience fee", formatRupees(fee))
                if (discount > 0.0) {
                    BillRow("Discount", "− ${formatRupees(discount)}", HaraanColors.Success)
                }
                HorizontalDivider(color = HaraanColors.Hairline)
                BillRow("Total", formattedTotal, HaraanColors.TextPrimary, isBold = true)
            }
        }
    }
}

/**
 * The UPI apps we found on this phone. Deliberately not tappable — the SDK gives us no way to
 * route to a chosen one, and a tile that looks selectable but changes nothing is a lie.
 */
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
                    .width(78.dp)
                    .scale(1f + 0.03f * lift)
                    .clip(RoundedCornerShape(16.dp))
                    .background(
                        lerp(HaraanColors.Surface, HaraanColors.AccentTint, lift),
                    )
                    .border(
                        width = (1f + 0.6f * lift).dp,
                        color = lerp(HaraanColors.BorderLight, HaraanColors.EventsBlue, lift),
                        shape = RoundedCornerShape(16.dp),
                    )
                    .pressable(haptic = Feel.SELECT) { onChoose(app) }
                    .padding(vertical = 12.dp, horizontal = 6.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                // getApplicationIcon can come back null on a restricted or half-installed
                // profile. Falling back to a monogram keeps the tile the same height, so the
                // row does not collapse into ragged dead space when one app misbehaves.
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
                    fontWeight = if (isChosen) FontWeight.SemiBold else FontWeight.Medium,
                    maxLines = 1,
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
    Column(Modifier.padding(top = 6.dp)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(HaraanColors.Field)
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
        Spacer(Modifier.height(6.dp))
        Text(
            text = "We'll fill this in for you on the payment screen.",
            color = HaraanColors.TextMuted,
            fontSize = 11.5.sp,
        )
    }
}

@Composable
private fun InstrumentCard(
    title: String,
    subtitle: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    isSelected: Boolean,
    onSelect: () -> Unit,
    recommended: Boolean = false,
    content: (@Composable () -> Unit)? = null,
) {
    val borderWidth by animateFloatAsState(
        targetValue = if (isSelected) 1.6f else 1f,
        animationSpec = spring(),
        label = "instrumentBorder",
    )
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (isSelected) HaraanColors.AccentTint.copy(alpha = 0.45f) else HaraanColors.Surface)
            .border(
                borderWidth.dp,
                if (isSelected) HaraanColors.EventsBlue else HaraanColors.BorderLight,
                RoundedCornerShape(16.dp),
            )
            .pressable(haptic = Feel.SELECT, onClick = onSelect)
            .animateContentSize()
            .padding(15.dp),
    ) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(38.dp)
                        .clip(RoundedCornerShape(11.dp))
                        .background(if (isSelected) HaraanColors.EventsBlue else HaraanColors.Field),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = if (isSelected) Color.White else HaraanColors.TextSecondary,
                        modifier = Modifier.size(19.dp),
                    )
                }

                Spacer(Modifier.width(12.dp))

                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = title,
                            color = HaraanColors.TextPrimary,
                            fontSize = 14.5.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                        if (recommended) {
                            Spacer(Modifier.width(7.dp))
                            Text(
                                text = "Fastest",
                                color = HaraanColors.EventsBlue,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(999.dp))
                                    .background(HaraanColors.AccentTint)
                                    .padding(horizontal = 7.dp, vertical = 2.dp),
                            )
                        }
                    }
                    Text(
                        text = subtitle,
                        color = HaraanColors.TextSecondary,
                        fontSize = 12.sp,
                        lineHeight = 16.sp,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.padding(top = 2.dp),
                    )
                }

                Spacer(Modifier.width(10.dp))

                Box(
                    modifier = Modifier
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(if (isSelected) HaraanColors.EventsBlue else Color.Transparent)
                        .border(
                            1.5.dp,
                            if (isSelected) HaraanColors.EventsBlue else HaraanColors.BorderLight,
                            CircleShape,
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    // Fades rather than pops, so switching instruments reads as one movement.
                    val tickAlpha by animateFloatAsState(
                        targetValue = if (isSelected) 1f else 0f,
                        animationSpec = spring(),
                        label = "instrumentTick",
                    )
                    Icon(
                        imageVector = Icons.Default.Check,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier
                            .size(13.dp)
                            .alpha(tickAlpha),
                    )
                }
            }

            if (isSelected && content != null) content()
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
        shadowElevation = 0.dp,
    ) {
        Row(
            modifier = Modifier.fillMaxSize(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Center,
        ) {
            Icon(
                imageVector = Icons.Outlined.Lock,
                contentDescription = null,
                tint = Color.White,
                modifier = Modifier.size(16.dp),
            )
            Spacer(Modifier.width(8.dp))
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

/**
 * Money, the way the rest of the app writes it.
 *
 * Delegates to [MembershipFormat.rupees] rather than repeating the lakh-grouping logic: it is the
 * one formatter that knows `1,00,000` is not `100,000`, and it is already under test.
 */
private fun formatRupees(amount: Double): String =
    MembershipFormat.rupees(Math.round(amount * 100))
