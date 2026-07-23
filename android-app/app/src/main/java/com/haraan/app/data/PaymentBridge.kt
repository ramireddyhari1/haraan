package com.haraan.app.data

/**
 * Bridges the Razorpay Checkout result from the host Activity (which implements
 * [com.razorpay.PaymentResultWithDataListener]) back to the Compose screen that opened it.
 *
 * The SDK delivers its callback to the Activity, not to whatever composable started the flow,
 * so the screen registers a one-shot [pending] handler before opening checkout and the Activity
 * forwards the outcome here. Single-flight: opening a new checkout replaces any stale handler.
 */
object PaymentBridge {

    sealed interface Outcome {
        /** Payment succeeded — carries the fields the backend needs to verify the signature. */
        data class Success(
            val orderId: String,
            val paymentId: String,
            val signature: String,
        ) : Outcome

        /** The buyer dismissed the sheet — the reservation should be released. */
        data object Cancelled : Outcome

        /** Payment failed at the gateway. */
        data class Failed(val message: String) : Outcome
    }

    @Volatile
    private var pending: ((Outcome) -> Unit)? = null

    /** Arm the one-shot handler for the checkout about to open. */
    fun await(onResult: (Outcome) -> Unit) {
        pending = onResult
    }

    /** Called from the Activity's Razorpay listener; delivers to and clears the handler. */
    fun deliver(outcome: Outcome) {
        val handler = pending
        pending = null
        handler?.invoke(outcome)
    }
}
