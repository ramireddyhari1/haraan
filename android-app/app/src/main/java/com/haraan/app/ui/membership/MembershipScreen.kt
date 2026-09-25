package com.haraan.app.ui.membership

import android.app.Activity
import android.widget.Toast
import androidx.compose.animation.animateColorAsState
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
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Info
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
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.data.PaymentBridge
import com.haraan.app.data.membership.CataloguePlan
import com.haraan.app.data.membership.CheckoutSession
import com.haraan.app.data.membership.MemberPaymentItem
import com.haraan.app.data.membership.Membership
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.razorpay.Checkout
import org.json.JSONObject

private val Bg = HaraanColors.Background
private val Surface = HaraanColors.Surface
private val Blue = HaraanColors.EventsBlue
private val BlueTint = HaraanColors.AccentTint
private val Green = HaraanColors.Success
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val Hairline = HaraanColors.Hairline

/**
 * Member plans: where the member stands, what each plan actually includes (straight from the
 * server's entitlement rows), and one commit button for the plan they pick.
 *
 * Checkout: the server creates the Razorpay subscription → Razorpay's sheet takes the mandate
 * and first payment → the server verifies the signature and re-reads Razorpay → the plan is
 * on. The app never decides that someone has paid.
 */
@Composable
fun MembershipScreen(
    onClose: () -> Unit,
    onSignIn: () -> Unit,
    prefillName: String = "",
    prefillEmail: String = "",
    prefillPhone: String = "",
    vm: MembershipViewModel = viewModel(),
) {
    val state by vm.state.collectAsState()
    val context = LocalContext.current
    var confirmCancel by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { vm.enter() }

    // Coming back from the sign-in wall: reload as the member they now are.
    val signedInNow = com.haraan.app.data.TokenStore.isSignedIn(com.haraan.app.data.TokenStore.getToken(context))
    LaunchedEffect(signedInNow) {
        if (signedInNow != state.signedIn && !state.loading) vm.load()
    }

    LaunchedEffect(state.message) {
        state.message?.let {
            Toast.makeText(context, it, Toast.LENGTH_LONG).show()
            vm.consumeMessage()
        }
    }

    // Open Razorpay exactly once per subscription the server hands us.
    val awaiting = state.checkout as? CheckoutPhase.AwaitingPayment
    LaunchedEffect(awaiting?.session?.subscriptionId) {
        val phase = awaiting ?: return@LaunchedEffect
        val activity = context as? Activity
        if (activity == null || phase.session.key.isNullOrBlank()) {
            vm.onCheckoutCouldNotOpen(phase.session)
            return@LaunchedEffect
        }
        PaymentBridge.await { outcome -> vm.onPaymentOutcome(phase.session, phase.planName, outcome) }
        val opened = runCatching {
            openSubscriptionCheckout(activity, phase.session, phase.planName, prefillName, prefillEmail, prefillPhone)
        }.isSuccess
        if (!opened) vm.onCheckoutCouldNotOpen(phase.session)
    }

    Column(Modifier.fillMaxSize().background(Bg).statusBarsPadding()) {
        Header(onClose)

        when {
            state.loading -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = Blue) }
            state.loadError != null && state.catalogue == null -> LoadError(state.loadError!!, onRetry = vm::load)
            else -> {
                val selected = state.plans.firstOrNull { it.code == state.selectedPlan }
                val cta = selected?.let { MembershipFormat.ctaFor(it, state.membership, state.interval, state.signedIn) }
                // The checkout tray floats over the list, so the plan panel scrolls beneath it; the
                // list leaves room at the end so its last line can still clear the tray.
                // The app sells plans only when an admin has allowed it; otherwise the plans are
                // shown and the admin's note stands where the buy button would be.
                val canSellHere = state.catalogue?.checkout?.inApp == true
                val buyable = cta is MembershipFormat.Cta.Buy || cta == MembershipFormat.Cta.SignIn
                val trayShown = canSellHere && buyable

                Box(Modifier.weight(1f)) {
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = if (trayShown) 120.dp else 24.dp),
                    ) {
                        state.checkout.let { phase ->
                            if (phase is CheckoutPhase.Succeeded || phase == CheckoutPhase.StillConfirming) {
                                item { ResultCard(phase, onDismiss = vm::dismissResult); Spacer(Modifier.height(14.dp)) }
                            }
                        }

                        // Where the member stands now lives inside their plan's panel. Only a problem
                        // with the plan is lifted above everything, so it can't be scrolled past.
                        state.membership?.takeIf { it.attention != null }?.let { m ->
                            item { AttentionBanner(m); Spacer(Modifier.height(20.dp)) }
                        }

                        item {
                            Text("Plans", color = Text1, fontSize = 20.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.3).sp)
                            Spacer(Modifier.height(12.dp))
                            val terms = MembershipFormat.terms(state.plans)
                            if (terms.size > 1) {
                                TermSelector(terms, state.interval, state.plans, vm::selectInterval)
                                Spacer(Modifier.height(12.dp))
                            }
                        }

                        item(key = "plans") {
                            PlanShowcase(
                                plans = state.plans,
                                membership = state.membership,
                                interval = state.interval,
                                signedIn = state.signedIn,
                                selectedCode = state.selectedPlan,
                                onSelect = vm::selectPlan,
                            )
                            Spacer(Modifier.height(12.dp))
                            if (!canSellHere && buyable) {
                                StoreNote(state.catalogue?.checkout?.note)
                            }
                        }

                        if (state.payments.isNotEmpty()) {
                            item { Spacer(Modifier.height(12.dp)); PaymentHistory(state.payments) }
                        }

                        if (state.membership?.subscription?.canCancel == true) {
                            item {
                                Spacer(Modifier.height(20.dp))
                                Text(
                                    if (state.cancelling) "Cancelling…" else "Cancel membership",
                                    color = HaraanColors.Danger,
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    textAlign = TextAlign.Center,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(12.dp))
                                        .clickable(enabled = !state.cancelling) { confirmCancel = true }
                                        .padding(vertical = 14.dp),
                                )
                            }
                        }
                    }

                    // Only when an admin lets the app sell: otherwise nothing here can start a payment.
                    if (canSellHere) CommitBar(
                        plan = selected,
                        cta = cta,
                        membership = state.membership,
                        busy = state.checkout is CheckoutPhase.Starting ||
                            state.checkout is CheckoutPhase.AwaitingPayment ||
                            state.checkout == CheckoutPhase.Verifying ||
                            state.checkout == CheckoutPhase.Confirming,
                        busyLabel = when (state.checkout) {
                            CheckoutPhase.Verifying -> "Verifying payment…"
                            CheckoutPhase.Confirming -> "Confirming with your bank…"
                            else -> "Opening checkout…"
                        },
                        onCommit = vm::beginCheckout,
                        onSignIn = onSignIn,
                        modifier = Modifier.align(Alignment.BottomCenter),
                    )
                }
            }
        }
    }

    if (confirmCancel) {
        val sub = state.membership?.subscription
        val until = MembershipFormat.date(sub?.renewsAt ?: sub?.currentPeriodEnd)
        AlertDialog(
            onDismissRequest = { confirmCancel = false },
            title = { Text("Cancel ${sub?.plan?.name ?: "membership"}?") },
            text = {
                Text(
                    if (until != null) "You keep everything in ${sub?.plan?.name} until $until and won't be charged again. After that you're on the free plan."
                    else "You won't be charged again. Your plan ends at the end of this period.",
                )
            },
            confirmButton = {
                TextButton(onClick = { confirmCancel = false; vm.cancel(atPeriodEnd = true) }) {
                    Text("Cancel membership", color = HaraanColors.Danger, fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmCancel = false }) { Text("Keep plan", color = Blue, fontWeight = FontWeight.SemiBold) }
            },
            containerColor = Surface,
        )
    }
}

internal fun openSubscriptionCheckout(
    activity: Activity,
    session: CheckoutSession,
    planName: String,
    name: String,
    email: String,
    phone: String,
) {
    val checkout = Checkout()
    checkout.setKeyID(session.key)
    val options = JSONObject().apply {
        put("name", "Haraan")
        put("description", "$planName membership")
        put("subscription_id", session.subscriptionId)
        put("prefill", JSONObject().apply {
            if (name.isNotBlank()) put("name", name)
            if (email.isNotBlank()) put("email", email)
            if (phone.isNotBlank()) put("contact", phone)
        })
        put("theme", JSONObject().apply { put("color", "#2563EB") })
    }
    checkout.open(activity, options)
}

@Composable
private fun Header(onClose: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().background(Surface).padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFEFF2F7)).clickable(onClick = onClose),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp)) }
        Spacer(Modifier.width(12.dp))
        Text("Membership", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun LoadError(message: String, onRetry: () -> Unit) {
    Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(message, color = Text2, fontSize = 14.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(12.dp))
            Box(
                Modifier.clip(RoundedCornerShape(10.dp)).background(Blue).clickable(onClick = onRetry)
                    .padding(horizontal = 20.dp, vertical = 10.dp),
            ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
        }
    }
}

/** A plan that needs the member's attention (a failed or retrying renewal), said once, up top. */
@Composable
private fun AttentionBanner(m: Membership) {
    val failed = m.attention == "payment_failed"
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (failed) HaraanColors.DangerTint else HaraanColors.WarningTint)
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Icon(
            Icons.Default.ErrorOutline, null,
            tint = if (failed) HaraanColors.Danger else HaraanColors.Warning,
            modifier = Modifier.padding(top = 1.dp).size(18.dp),
        )
        Spacer(Modifier.width(10.dp))
        Column {
            Text(m.plan.name, color = Text1, fontSize = 14.5.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(MembershipFormat.statusLine(m), color = Text2, fontSize = 13.sp, lineHeight = 18.sp)
        }
    }
}

/**
 * The billing terms on sale (Monthly, 3 months, 6 months, Yearly) as one control: blue tint for
 * the chosen one, never a solid fill, with each longer term's real saving beside it.
 */
@Composable
private fun TermSelector(terms: List<String>, interval: String, plans: List<CataloguePlan>, onSelect: (String) -> Unit) {
    // Every term gets an equal share of the row, with its saving on a second line, so all four
    // fit on a phone without scrolling a price choice out of view.
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFEEF2F7))
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        val anySaving = terms.any { MembershipFormat.saving(plans, it) != null }
        terms.forEach { term ->
            val on = interval == term
            val bg by animateColorAsState(if (on) Surface else Color.Transparent, label = "term-bg")
            val saving = MembershipFormat.saving(plans, term)
            Column(
                Modifier
                    .pressable(haptic = if (on) null else com.haraan.app.ui.Feel.SELECT) { onSelect(term) }
                    .weight(1f)
                    .clip(RoundedCornerShape(11.dp))
                    .background(bg)
                    .then(if (on) Modifier.border(1.dp, Blue.copy(alpha = 0.35f), RoundedCornerShape(11.dp)) else Modifier)
                    .padding(vertical = 8.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(MembershipFormat.termLabel(term), color = if (on) Blue else Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
                if (anySaving) {
                    Text(
                        saving?.let { "Save $it%" } ?: " ",
                        color = Green, fontSize = 11.sp, fontWeight = FontWeight.Bold, maxLines = 1,
                    )
                }
            }
        }
    }
}

/**
 * Stands where the buy button would be when an admin hasn't let the app sell plans. Words only
 * — no link or button to pay elsewhere — so the store build stays within billing rules.
 */
@Composable
private fun StoreNote(note: String?) {
    val text = note?.takeIf { it.isNotBlank() } ?: return
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFEEF2F7))
            .padding(horizontal = 16.dp, vertical = 13.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Default.Info, null, tint = Text3, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(10.dp))
        Text(text, color = Text2, fontSize = 13.sp, lineHeight = 18.sp)
    }
}

@Composable
private fun PaymentHistory(payments: List<MemberPaymentItem>) {
    Column {
        Text("Payment history", color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(12.dp))
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Surface).border(1.dp, Stroke, RoundedCornerShape(18.dp)),
        ) {
            payments.forEachIndexed { i, p ->
                if (i > 0) Box(Modifier.fillMaxWidth().padding(start = 16.dp).height(1.dp).background(Hairline))
                Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 13.dp), verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(p.plan ?: "Membership", color = Text1, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                        Text(
                            listOfNotNull(MembershipFormat.date(p.paidAt), p.method?.uppercase()).joinToString(" · "),
                            color = Text3, fontSize = 12.sp,
                        )
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text(MembershipFormat.rupees(p.amountPaise), color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        if (p.status != "captured") {
                            Text(p.status.replaceFirstChar { it.uppercase() }, color = HaraanColors.Danger, fontSize = 12.sp)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ResultCard(phase: CheckoutPhase, onDismiss: () -> Unit) {
    val (title, body) = when (phase) {
        is CheckoutPhase.Succeeded ->
            if (phase.startsOn != null) "${phase.planName} starts on ${phase.startsOn}" to "You keep your current plan until then."
            else "You're on ${phase.planName}" to "Everything in ${phase.planName} is unlocked now."
        else -> "Confirming your payment" to "Your bank is taking a moment. Your plan switches on as soon as Razorpay confirms — you don't need to pay again."
    }
    val success = phase is CheckoutPhase.Succeeded
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (success) HaraanColors.SuccessTint else HaraanColors.WarningTint)
            .clickable(onClick = onDismiss)
            .padding(16.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Icon(
            if (success) Icons.Default.Check else Icons.Default.ErrorOutline, null,
            tint = if (success) Green else HaraanColors.Warning,
            modifier = Modifier.padding(top = 1.dp).size(18.dp),
        )
        Spacer(Modifier.width(10.dp))
        Column {
            Text(title, color = Text1, fontSize = 14.5.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(body, color = Text2, fontSize = 13.sp, lineHeight = 18.sp)
        }
    }
}

/**
 * Checkout tray: the price and the one line of terms that matter on the left, a compact blue
 * button on the right. It rises off the page on a soft shadow with rounded shoulders, so the plan
 * panel slides beneath it instead of being cut off by a flat slab.
 */
@Composable
private fun CommitBar(
    plan: CataloguePlan?,
    cta: MembershipFormat.Cta?,
    membership: Membership?,
    busy: Boolean,
    busyLabel: String,
    onCommit: () -> Unit,
    onSignIn: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val buy = cta as? MembershipFormat.Cta.Buy
    val signIn = cta == MembershipFormat.Cta.SignIn
    if (plan == null || (buy == null && !signIn)) return

    val tray = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
    Row(
        modifier
            .fillMaxWidth()
            .shadow(24.dp, tray, ambientColor = Color(0x1A0F172A), spotColor = Color(0x330F172A))
            .clip(tray)
            .background(Surface)
            .navigationBarsPadding()
            .padding(start = 20.dp, end = 16.dp, top = 14.dp, bottom = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            if (buy != null) {
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        MembershipFormat.rupees(buy.price.amountPaise),
                        color = Text1, fontSize = 22.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.4).sp,
                    )
                    Text(
                        MembershipFormat.perInterval(buy.price.interval),
                        color = Text2, fontSize = 13.sp, fontWeight = FontWeight.Medium,
                        modifier = Modifier.padding(start = 2.dp, bottom = 3.dp),
                    )
                }
            } else {
                Text(plan.name, color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.height(1.dp))
            Text(
                when {
                    busy -> busyLabel
                    buy != null -> MembershipFormat.commitTerms(buy, membership)
                    else -> "Sign in to choose a plan"
                },
                color = Text3, fontSize = 12.sp, lineHeight = 16.sp, maxLines = 2,
            )
        }
        Spacer(Modifier.width(12.dp))
        Box(
            Modifier
                // Presses in under the finger; paying lands with the heavier confirm tick.
                .pressable(enabled = !busy, haptic = if (signIn) com.haraan.app.ui.Feel.SELECT else com.haraan.app.ui.Feel.COMMIT) {
                    if (signIn) onSignIn() else onCommit()
                }
                .height(52.dp)
                .widthIn(min = 128.dp)
                .shadow(if (busy) 0.dp else 10.dp, RoundedCornerShape(16.dp), ambientColor = Blue.copy(alpha = 0.25f), spotColor = Blue.copy(alpha = 0.45f))
                .clip(RoundedCornerShape(16.dp))
                .background(Brush.verticalGradient(listOf(Color(0xFF3B7BF6), Blue)))
                .padding(horizontal = 22.dp),
            contentAlignment = Alignment.Center,
        ) {
            if (busy) {
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            } else {
                Text(
                    if (signIn) "Sign in" else MembershipFormat.commitAction(plan, buy!!),
                    color = Color.White, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1,
                )
            }
        }
    }
}
