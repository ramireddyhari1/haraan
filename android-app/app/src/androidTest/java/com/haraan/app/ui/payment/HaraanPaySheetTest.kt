package com.haraan.app.ui.payment

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performTextInput
import androidx.test.ext.junit.runners.AndroidJUnit4
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/**
 * The pay sheet's contract with checkout.
 *
 * The thing worth pinning down is what [HaraanPaySheet] hands back, because that is what decides
 * the `prefill` block Razorpay receives. A VPA that is half-typed must NOT travel — Razorpay would
 * reject it at the gateway, after the buyer has already committed.
 */
@RunWith(AndroidJUnit4::class)
class HaraanPaySheetTest {

    @get:Rule
    val compose = createComposeRule()

    private var instrument: HaraanPaymentInstrument? = null
    private var vpa: String? = null
    private var payCount = 0

    private fun showSheet() {
        compose.setContent {
            HaraanPaySheet(
                title = "Gaurav Gupta Live",
                totalPaise = 38_300L,
                subtotal = 383.0,
                fee = 0.0,
                discount = 0.0,
                couponCode = null,
                prefillName = "Hariharan",
                prefillEmail = "hari@haraan.app",
                prefillPhone = "9876543210",
                onDismiss = {},
                onPayRequested = { i, v ->
                    instrument = i
                    vpa = v
                    payCount++
                },
            )
        }
    }

    @Test
    fun opensOnUpiAndShowsTheTotalInRupees() {
        showSheet()

        compose.onNodeWithText("UPI").assertIsDisplayed()
        // Straight from paise, with no stray decimals on a whole-rupee amount.
        compose.onNode(hasText("Pay ₹383")).assertIsDisplayed()
    }

    @Test
    fun payingWithoutTypingAVpaSendsPlainUpiAndNoVpa() {
        showSheet()

        compose.onNode(hasText("Pay ₹383")).performClick()
        compose.waitForIdle()

        assertEquals(1, payCount)
        assertEquals(HaraanPaymentInstrument.UPI, instrument)
        assertNull("a VPA the buyer never typed must not reach Razorpay", vpa)
    }

    @Test
    fun aHalfTypedVpaIsNotSentToRazorpay() {
        showSheet()

        compose.onNodeWithText("Pay to a UPI ID instead").performClick()
        compose.onNodeWithText("name@bank").performTextInput("hariharan")
        compose.waitForIdle()

        compose.onNode(hasText("Pay ₹383")).performClick()
        compose.waitForIdle()

        assertEquals(HaraanPaymentInstrument.UPI, instrument)
        assertNull("an incomplete VPA would be rejected at the gateway, after the buyer committed", vpa)
    }

    @Test
    fun aCompleteVpaTravelsAsUpiVpa() {
        showSheet()

        compose.onNodeWithText("Pay to a UPI ID instead").performClick()
        compose.onNodeWithText("name@bank").performTextInput("hariharan@okhdfcbank")
        compose.waitForIdle()

        compose.onNode(hasText("Pay ₹383")).performClick()
        compose.waitForIdle()

        assertEquals(HaraanPaymentInstrument.UPI_VPA, instrument)
        assertEquals("hariharan@okhdfcbank", vpa)
    }

    @Test
    fun switchingToCardClearsTheUpiIntent() {
        showSheet()

        compose.onNodeWithText("Card").performClick()
        compose.onNode(hasText("Pay ₹383")).performClick()
        compose.waitForIdle()

        assertEquals(HaraanPaymentInstrument.CARD, instrument)
        assertNull(vpa)
    }
}
