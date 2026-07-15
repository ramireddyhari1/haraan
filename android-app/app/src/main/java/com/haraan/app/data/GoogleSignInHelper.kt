package com.haraan.app.data

import android.content.Context
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.haraan.app.BuildConfig

/** Outcome of the Credential Manager "Sign in with Google" flow. */
sealed interface GoogleSignInResult {
  data class Success(val idToken: String) : GoogleSignInResult
  data object Cancelled : GoogleSignInResult
  data class Error(val message: String) : GoogleSignInResult
}

/**
 * Drives the Android Credential Manager "Sign in with Google" sheet and returns the Google
 * **ID token** for the backend to verify. Configured entirely by the OAuth Web client ID baked
 * into [BuildConfig]; the button is hidden when that's blank, so this is never called unconfigured.
 */
object GoogleSignInHelper {

  /** True once a Web client ID has been provided at build time. */
  val isConfigured: Boolean get() = BuildConfig.GOOGLE_WEB_CLIENT_ID.isNotBlank()

  suspend fun signIn(context: Context): GoogleSignInResult {
    if (!isConfigured) return GoogleSignInResult.Error("Google sign-in isn't configured.")

    val googleIdOption = GetGoogleIdOption.Builder()
      .setServerClientId(BuildConfig.GOOGLE_WEB_CLIENT_ID)
      // false = also offer accounts that have never signed in here, so a first-time user
      // (sign-up) still sees the picker rather than an empty "no credentials" error.
      .setFilterByAuthorizedAccounts(false)
      .setAutoSelectEnabled(false)
      .build()

    val request = GetCredentialRequest.Builder()
      .addCredentialOption(googleIdOption)
      .build()

    return try {
      val response = CredentialManager.create(context).getCredential(context, request)
      val credential = response.credential
      if (credential is CustomCredential &&
        credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
      ) {
        val googleCredential = GoogleIdTokenCredential.createFrom(credential.data)
        GoogleSignInResult.Success(googleCredential.idToken)
      } else {
        GoogleSignInResult.Error("Unexpected sign-in response. Please try again.")
      }
    } catch (_: GetCredentialCancellationException) {
      GoogleSignInResult.Cancelled
    } catch (_: NoCredentialException) {
      GoogleSignInResult.Error("No Google account found on this device.")
    } catch (e: GetCredentialException) {
      GoogleSignInResult.Error(e.message ?: "Google sign-in failed. Please try again.")
    } catch (e: Exception) {
      GoogleSignInResult.Error(e.message ?: "Google sign-in failed. Please try again.")
    }
  }
}
