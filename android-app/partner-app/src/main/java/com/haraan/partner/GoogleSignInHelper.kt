package com.haraan.partner

import android.content.Context
import android.util.Log
import androidx.credentials.CredentialManager
import androidx.credentials.CredentialOption
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.GetCredentialResponse
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential

/** Outcome of the Credential Manager "Continue with Google" flow. */
sealed interface GoogleSignInResult {
  data class Success(val idToken: String) : GoogleSignInResult
  data object Cancelled : GoogleSignInResult
  data class Error(val message: String) : GoogleSignInResult
}

/**
 * Drives the Android Credential Manager "Sign in with Google" sheet and returns the Google
 * **ID token** for the backend (`POST /api/auth/google`) to verify. Configured entirely by the
 * OAuth Web client ID baked into [BuildConfig]; the button is hidden when that's blank, so this
 * is never called unconfigured. (Google also requires the partner package's signing SHA-1 to be
 * registered as an Android OAuth client in the same Cloud project for the sheet to complete.)
 */
object GoogleSignInHelper {

  private const val TAG = "GoogleSignIn"

  /** True once a Web client ID has been provided at build time. */
  val isConfigured: Boolean get() = BuildConfig.GOOGLE_WEB_CLIENT_ID.isNotBlank()

  suspend fun signIn(context: Context): GoogleSignInResult {
    if (!isConfigured) return GoogleSignInResult.Error("Google sign-in isn't configured.")
    val clientId = BuildConfig.GOOGLE_WEB_CLIENT_ID

    // Pass 1 — the quiet one. Accounts that have already signed in here come back in a single
    // tap. Everyone else yields NO_CREDENTIAL, which is the normal state on a first sign-in,
    // so that outcome falls through to pass 2 instead of being reported as a failure.
    val returning = GetGoogleIdOption.Builder()
      .setServerClientId(clientId)
      .setFilterByAuthorizedAccounts(true)
      .setAutoSelectEnabled(false)
      .build()
    when (val first = attempt(context, returning)) {
      is Attempt.Done -> return first.result
      Attempt.NoCredential -> Unit
    }

    // Pass 2 — the explicit button flow, and the one that has to work for a first-time user.
    // GetSignInWithGoogleOption always opens the full account picker; crucially it is NOT
    // subject to the One Tap dismissal cooldown that GetGoogleIdOption carries — after a few
    // dismissed or failed sheets, Play Services stops offering that bottom sheet for ~24h and
    // reports it as "no credential", which is indistinguishable from owning no Google account.
    val picker = GetSignInWithGoogleOption.Builder(clientId).build()
    return when (val second = attempt(context, picker)) {
      is Attempt.Done -> second.result
      Attempt.NoCredential -> GoogleSignInResult.Error(
        "Google sign-in isn't available on this device. Please continue with email or phone."
      )
    }
  }

  /** One Credential Manager round trip; [Attempt.NoCredential] means "try another option". */
  private sealed interface Attempt {
    data class Done(val result: GoogleSignInResult) : Attempt
    data object NoCredential : Attempt
  }

  private suspend fun attempt(context: Context, option: CredentialOption): Attempt = try {
    val request = GetCredentialRequest.Builder().addCredentialOption(option).build()
    Attempt.Done(read(CredentialManager.create(context).getCredential(context, request)))
  } catch (_: GetCredentialCancellationException) {
    Attempt.Done(GoogleSignInResult.Cancelled)
  } catch (e: NoCredentialException) {
    // Not necessarily an empty device: an unregistered signing SHA-1, a consent screen the
    // user isn't a test user on, and the One Tap cooldown all land here too.
    Log.w(TAG, "no credential from ${option.javaClass.simpleName} for ${BuildConfig.GOOGLE_WEB_CLIENT_ID}", e)
    Attempt.NoCredential
  } catch (e: GetCredentialException) {
    Log.w(TAG, "getCredential failed (${e.type})", e)
    Attempt.Done(GoogleSignInResult.Error(e.message ?: "Google sign-in failed. Please try again."))
  } catch (e: Exception) {
    Log.w(TAG, "getCredential threw", e)
    Attempt.Done(GoogleSignInResult.Error(e.message ?: "Google sign-in failed. Please try again."))
  }

  private fun read(response: GetCredentialResponse): GoogleSignInResult {
    val credential = response.credential
    return if (credential is CustomCredential &&
      credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
    ) {
      GoogleSignInResult.Success(GoogleIdTokenCredential.createFrom(credential.data).idToken)
    } else {
      Log.w(TAG, "unexpected credential type ${credential.type}")
      GoogleSignInResult.Error("Unexpected sign-in response. Please try again.")
    }
  }
}
