package com.haraan.app.ui.membership

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.PaymentBridge
import com.haraan.app.data.TokenStore
import com.haraan.app.data.membership.CataloguePlan
import com.haraan.app.data.membership.CheckoutSession
import com.haraan.app.data.membership.MemberPaymentItem
import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.MembershipCatalogue
import com.haraan.app.data.membership.MembershipException
import com.haraan.app.data.membership.MembershipRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.supervisorScope

/** Where a checkout has got to. One at a time; the screen reacts to each phase. */
sealed interface CheckoutPhase {
    data object Idle : CheckoutPhase
    data object Starting : CheckoutPhase

    /** The server made the subscription; the screen opens Razorpay with it. */
    data class AwaitingPayment(val session: CheckoutSession, val planName: String) : CheckoutPhase
    data object Verifying : CheckoutPhase

    /** Paid, but Razorpay hasn't confirmed the charge to us yet — we poll. */
    data object Confirming : CheckoutPhase

    /** [startsOn] is set when the new plan begins at the end of the current one. */
    data class Succeeded(val planName: String, val startsOn: String?) : CheckoutPhase

    /** Confirmation is taking longer than we poll for; the webhook will finish it. */
    data object StillConfirming : CheckoutPhase
}

data class MembershipUiState(
    val loading: Boolean = true,
    val loadError: String? = null,
    val signedIn: Boolean = false,
    val catalogue: MembershipCatalogue? = null,
    val membership: Membership? = null,
    val payments: List<MemberPaymentItem> = emptyList(),
    val interval: String = MembershipFormat.MONTH,
    val selectedPlan: String? = null,
    val checkout: CheckoutPhase = CheckoutPhase.Idle,
    val cancelling: Boolean = false,
    /** A one-off message for a snackbar/toast; cleared once shown. */
    val message: String? = null,
) {
    val plans: List<CataloguePlan> get() = catalogue?.plans.orEmpty()
}

class MembershipViewModel(app: Application) : AndroidViewModel(app) {

    private val repo = MembershipRepository()
    private val _state = MutableStateFlow(MembershipUiState())
    val state: StateFlow<MembershipUiState> = _state.asStateFlow()

    private var pollJob: Job? = null

    private fun token(): String? = TokenStore.getSignedInToken(getApplication())

    /**
     * Called each time the screen is entered. The ViewModel outlives a visit (Activity-scoped),
     * and the plan may have changed meanwhile, so start from a clean read — unless a checkout is
     * mid-flight, whose state must survive the trip to Razorpay's sheet and back.
     */
    fun enter() {
        if (_state.value.checkout != CheckoutPhase.Idle) return
        pollJob?.cancel()
        _state.value = MembershipUiState()
        load()
    }

    fun load() {
        val token = token()
        _state.update { it.copy(loading = it.catalogue == null, loadError = null, signedIn = token != null) }
        viewModelScope.launch {
            try {
                // supervisorScope: a failed call surfaces at await() as a catchable error
                // instead of cancelling this coroutine and crashing the ViewModel scope.
                val (c, m, paid) = supervisorScope {
                    val catalogue = async { repo.catalogue(token) }
                    val membership = token?.let { t -> async { repo.membership(t) } }
                    val payments = token?.let { t -> async { runCatching { repo.payments(t) }.getOrDefault(emptyList()) } }
                    Triple(catalogue.await(), membership?.await(), payments?.await().orEmpty())
                }
                _state.update { s ->
                    s.copy(
                        loading = false,
                        catalogue = c,
                        membership = m,
                        payments = paid,
                        // Start on the term they already pay, so their own plan reads "Your plan";
                        // otherwise the shortest term on sale. Keep a term the member picked while
                        // it's still offered.
                        interval = MembershipFormat.terms(c.plans).let { offered ->
                            when {
                                s.catalogue != null && s.interval in offered -> s.interval
                                m?.subscription?.interval in offered -> m!!.subscription!!.interval!!
                                else -> offered.firstOrNull() ?: s.interval
                            }
                        },
                        selectedPlan = s.selectedPlan ?: defaultSelection(c, m),
                    )
                }
            } catch (e: MembershipException) {
                _state.update { it.copy(loading = false, loadError = e.message) }
            }
        }
    }

    /**
     * The plan the showcase opens on: the one the member is on. What they have comes before what
     * they could buy, and their standing and usage live on that plan's panel.
     */
    private fun defaultSelection(c: MembershipCatalogue, m: Membership?): String? =
        m?.plan?.code ?: c.currentPlan.takeIf { code -> c.plans.any { it.code == code } } ?: c.plans.firstOrNull()?.code

    fun selectInterval(interval: String) = _state.update { it.copy(interval = interval) }

    fun selectPlan(code: String) = _state.update { it.copy(selectedPlan = code) }

    fun consumeMessage() = _state.update { it.copy(message = null) }

    /** Ask the server for a subscription to pay for. */
    fun beginCheckout() {
        val s = _state.value
        val token = token() ?: return
        if (s.checkout != CheckoutPhase.Idle && s.checkout !is CheckoutPhase.Succeeded && s.checkout != CheckoutPhase.StillConfirming) return
        // The server decides whether this app may sell plans (an admin switch); never start a
        // payment when it hasn't said yes, whatever the UI is showing.
        if (s.catalogue?.checkout?.inApp != true) return
        val plan = s.plans.firstOrNull { it.code == s.selectedPlan } ?: return
        val cta = MembershipFormat.ctaFor(plan, s.membership, s.interval, signedIn = true)
        if (cta !is MembershipFormat.Cta.Buy) return

        _state.update { it.copy(checkout = CheckoutPhase.Starting) }
        viewModelScope.launch {
            try {
                val session = repo.subscribe(token, cta.price.id)
                _state.update { it.copy(checkout = CheckoutPhase.AwaitingPayment(session, plan.name)) }
            } catch (e: MembershipException) {
                _state.update { it.copy(checkout = CheckoutPhase.Idle, message = e.message) }
                if (e.code == "already_subscribed" || e.code == "change_already_scheduled") load()
            }
        }
    }

    /** The screen couldn't open Razorpay at all (no activity / SDK threw). */
    fun onCheckoutCouldNotOpen(session: CheckoutSession) {
        abandonQuietly(session)
        _state.update { it.copy(checkout = CheckoutPhase.Idle, message = "Couldn't open payment. Try again.") }
    }

    fun onPaymentOutcome(session: CheckoutSession, planName: String, outcome: PaymentBridge.Outcome) {
        when (outcome) {
            is PaymentBridge.Outcome.Cancelled -> {
                abandonQuietly(session)
                _state.update { it.copy(checkout = CheckoutPhase.Idle, message = "Checkout closed. You haven't been charged.") }
            }
            is PaymentBridge.Outcome.Failed -> {
                abandonQuietly(session)
                _state.update { it.copy(checkout = CheckoutPhase.Idle, message = outcome.message.ifBlank { "Payment failed." }) }
            }
            is PaymentBridge.Outcome.Success -> verify(session, planName, outcome)
        }
    }

    private fun verify(session: CheckoutSession, planName: String, outcome: PaymentBridge.Outcome.Success) {
        val token = token() ?: return
        _state.update { it.copy(checkout = CheckoutPhase.Verifying) }
        viewModelScope.launch {
            try {
                val result = repo.verify(
                    token = token,
                    paymentId = outcome.paymentId,
                    // The SDK's callback carries it; the session is the fallback for SDK builds that don't.
                    subscriptionId = outcome.subscriptionId.ifBlank { session.subscriptionId },
                    signature = outcome.signature,
                )
                if (result.confirmed) {
                    succeed(result.membership, planName, session)
                } else {
                    _state.update { it.copy(membership = result.membership, checkout = CheckoutPhase.Confirming) }
                    pollUntilConfirmed(token, session, planName)
                }
            } catch (e: MembershipException) {
                // Payment may well have gone through — never tell them it failed. The webhook
                // settles it; they see the result the next time this screen loads.
                _state.update { it.copy(checkout = CheckoutPhase.StillConfirming, message = e.message) }
            }
        }
    }

    private fun pollUntilConfirmed(token: String, session: CheckoutSession, planName: String) {
        pollJob?.cancel()
        pollJob = viewModelScope.launch {
            repeat(POLL_ATTEMPTS) {
                delay(POLL_INTERVAL_MS)
                val m = runCatching { repo.membership(token) }.getOrNull() ?: return@repeat
                val switched = m.plan.code == session.plan.code
                val scheduled = m.scheduledChange?.plan?.code == session.plan.code
                if (switched || scheduled) {
                    succeed(m, planName, session)
                    return@launch
                }
            }
            _state.update { it.copy(checkout = CheckoutPhase.StillConfirming) }
        }
    }

    private fun succeed(m: Membership, planName: String, session: CheckoutSession) {
        val startsOn = m.scheduledChange?.takeIf { it.plan.code == session.plan.code }?.startsAt?.let { MembershipFormat.date(it) }
        _state.update { it.copy(membership = m, checkout = CheckoutPhase.Succeeded(planName, startsOn)) }
        load()
    }

    fun dismissResult() = _state.update { it.copy(checkout = CheckoutPhase.Idle) }

    fun cancel(atPeriodEnd: Boolean = true) {
        val token = token() ?: return
        _state.update { it.copy(cancelling = true) }
        viewModelScope.launch {
            try {
                val m = repo.cancel(token, atPeriodEnd)
                val line = m.subscription?.endsAt?.let { MembershipFormat.date(it) }
                _state.update {
                    it.copy(
                        cancelling = false,
                        membership = m,
                        message = if (line != null) "Cancelled. Your plan stays on until $line." else "Your membership is cancelled.",
                    )
                }
                load()
            } catch (e: MembershipException) {
                _state.update { it.copy(cancelling = false, message = e.message) }
            }
        }
    }

    private fun abandonQuietly(session: CheckoutSession) {
        val token = token() ?: return
        viewModelScope.launch { runCatching { repo.abandon(token, session.subscriptionId) } }
    }

    private companion object {
        const val POLL_ATTEMPTS = 10
        const val POLL_INTERVAL_MS = 2_000L
    }
}
