package com.haraan.app.data

import android.app.Activity
import com.google.firebase.FirebaseException
import com.google.firebase.FirebaseTooManyRequestsException
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.FirebaseAuthInvalidCredentialsException
import com.google.firebase.auth.PhoneAuthCredential
import com.google.firebase.auth.PhoneAuthOptions
import com.google.firebase.auth.PhoneAuthProvider
import kotlinx.coroutines.suspendCancellableCoroutine
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import kotlin.coroutines.resume

/** Outcome of asking Firebase to send an SMS code. */
sealed interface PhoneSendResult {
  /** Code is on its way; hold [verificationId] to pair with the code the user types. */
  data class CodeSent(val verificationId: String) : PhoneSendResult
  /**
   * Instant verification (Play-Integrity auto-retrieval or a Firebase test number):
   * no code entry needed — [idToken] is ready to send to the backend.
   */
  data class AutoVerified(val idToken: String) : PhoneSendResult
  data class Error(val message: String) : PhoneSendResult
}

/** Outcome of confirming a typed code. */
sealed interface PhoneVerifyResult {
  data class Success(val idToken: String) : PhoneVerifyResult
  data class Error(val message: String) : PhoneVerifyResult
}

/**
 * Drives Firebase phone-auth (SMS OTP) and hands the backend a **Firebase ID token**,
 * which {@code /api/auth/firebase-phone} verifies and exchanges for an app JWT. This is
 * the app twin of the website's Firebase phone login, and uses the same Firebase project
 * (google-services.json), so a number resolves to the same account on both surfaces.
 *
 * Two steps: [sendCode] triggers the SMS, then [verifyCode] confirms what the user typed.
 * On devices where Firebase can auto-retrieve/instantly verify, [sendCode] short-circuits
 * with [PhoneSendResult.AutoVerified] and the code step is skipped.
 */
object PhoneAuthHelper {

  private val auth: FirebaseAuth by lazy { FirebaseAuth.getInstance() }

  /**
   * Ask Firebase to send an SMS code to [phoneE164] (must be full E.164, e.g. +919876543210).
   * [activity] is required for the reCAPTCHA / Play-Integrity app check.
   */
  suspend fun sendCode(activity: Activity, phoneE164: String): PhoneSendResult =
    suspendCancellableCoroutine { cont ->
      // onVerificationCompleted, onCodeSent and onVerificationFailed can race (e.g. instant
      // verification arriving alongside code-sent); resume the continuation exactly once.
      val done = AtomicBoolean(false)

      val callbacks = object : PhoneAuthProvider.OnVerificationStateChangedCallbacks() {
        override fun onVerificationCompleted(credential: PhoneAuthCredential) {
          if (!done.compareAndSet(false, true)) return
          // Instant verification — exchange the credential for an ID token right away so the
          // caller can skip the code screen entirely.
          exchangeForIdToken(credential) { result ->
            if (cont.isActive) cont.resume(
              when (result) {
                is PhoneVerifyResult.Success -> PhoneSendResult.AutoVerified(result.idToken)
                is PhoneVerifyResult.Error -> PhoneSendResult.Error(result.message)
              }
            )
          }
        }

        override fun onVerificationFailed(e: FirebaseException) {
          if (done.compareAndSet(false, true) && cont.isActive) {
            cont.resume(PhoneSendResult.Error(mapError(e)))
          }
        }

        override fun onCodeSent(verificationId: String, token: PhoneAuthProvider.ForceResendingToken) {
          if (done.compareAndSet(false, true) && cont.isActive) {
            cont.resume(PhoneSendResult.CodeSent(verificationId))
          }
        }
      }

      val options = PhoneAuthOptions.newBuilder(auth)
        .setPhoneNumber(phoneE164)
        .setTimeout(60L, TimeUnit.SECONDS)
        .setActivity(activity)
        .setCallbacks(callbacks)
        .build()

      PhoneAuthProvider.verifyPhoneNumber(options)
    }

  /** Confirm the [code] the user typed against the [verificationId] from [sendCode]. */
  suspend fun verifyCode(verificationId: String, code: String): PhoneVerifyResult =
    suspendCancellableCoroutine { cont ->
      val credential = PhoneAuthProvider.getCredential(verificationId, code.trim())
      exchangeForIdToken(credential) { result ->
        if (cont.isActive) cont.resume(result)
      }
    }

  /**
   * Sign in with a phone credential and read back the Firebase ID token. Kept callback-based
   * (no coroutines-play-services dependency) so it can be reused from the send-code callbacks.
   */
  private fun exchangeForIdToken(credential: PhoneAuthCredential, onResult: (PhoneVerifyResult) -> Unit) {
    auth.signInWithCredential(credential)
      .addOnSuccessListener { authResult ->
        val user = authResult.user
        if (user == null) {
          onResult(PhoneVerifyResult.Error("Couldn't complete sign-in. Please try again."))
          return@addOnSuccessListener
        }
        user.getIdToken(false)
          .addOnSuccessListener { tokenResult ->
            val token = tokenResult.token
            if (token.isNullOrBlank()) {
              onResult(PhoneVerifyResult.Error("Couldn't complete sign-in. Please try again."))
            } else {
              onResult(PhoneVerifyResult.Success(token))
            }
          }
          .addOnFailureListener { e -> onResult(PhoneVerifyResult.Error(mapError(e))) }
      }
      .addOnFailureListener { e -> onResult(PhoneVerifyResult.Error(mapError(e))) }
  }

  private fun mapError(e: Exception): String = when (e) {
    is FirebaseAuthInvalidCredentialsException ->
      "That code isn't valid. Please check it and try again."
    is FirebaseTooManyRequestsException ->
      "Too many attempts from this device. Please try again later."
    else -> e.message ?: "Phone sign-in failed. Please try again."
  }
}
